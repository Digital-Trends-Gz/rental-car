<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\ReservationStatus;
use App\Core\PlateFormatSettings as PlateFormatSettingsCore;
use App\Core\TenantContext;
use App\Models\Car;
use App\Models\CarDamageReport;
use App\Models\CarDiscount;
use App\Models\CarDocument;
use App\Models\DailyTaskStatus;
use App\Models\DamageRepair;
use App\Models\Contract;
use App\Models\CarMaintenance;
use App\Models\CarViolation;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use App\Models\TenantCarCatalogEntry;
use App\Services\Plans\PlanUsageLimits;
use App\Services\Plans\PlanUsageNotifier;
use App\Support\BranchAccess;
use App\Support\BranchLocationOptions;
use App\Support\CarCatalogOptions;
use App\Support\FileUrl;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;
use MohamedGaldi\ViltFilepond\Services\FilePondService;
use Illuminate\Support\Facades\Storage;

class CarsController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const ADDITIONAL_PHOTO_TYPES = Car::ADDITIONAL_PHOTO_TYPES;

    protected FilePondService $filePondService;
    protected BranchAccess $branchAccess;

    public function __construct(
        FilePondService $filePondService,
        BranchAccess $branchAccess,
        private PlanUsageLimits $planUsageLimits,
        private PlanUsageNotifier $planUsageNotifier,
    )
    {
        $this->filePondService = $filePondService;
        $this->branchAccess = $branchAccess;
    }

    /**
     * Display a listing of cars.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

        $status = $request->input('status');
        $scope = $request->string('scope')->toString();
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));

        $branchOptions = $this->branchAccess
            ->availableBranchesForUser($user)
            ->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])
            ->values();

        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;
        
        // Get status counts for the filter
        $statusCountsQuery = Car::query()->selectRaw('status, count(*) as count');
        $this->branchAccess->applyToQuery($statusCountsQuery, $user, $branchId);
        $statusCounts = $statusCountsQuery
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $carsQuery = Car::query()
            ->with('files')
            ->with('branch:id,name');

        $this->branchAccess->applyToQuery($carsQuery, $user, $branchId);

        $cars = $carsQuery
            ->when($scope === 'expiring_documents', function ($query) {
                $today = Carbon::today();

                $query->whereHas('documents', function ($documentQuery) use ($today) {
                    $documentQuery
                        ->where('is_active', true)
                        ->whereDate('expiry_date', '>=', $today)
                        ->whereDate('expiry_date', '<=', $today->copy()->addDays(10));
                });
            })
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('make', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('license_plate', 'like', "%{$search}%");
                });
            })
            ->when($status && $status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $cars->getCollection()->transform(function (Car $car) {
            $status = $car->status;

            return [
                'id' => $car->id,
                'make' => $car->make,
                'model' => $car->model,
                'year' => $car->year,
                'license_plate' => $car->license_plate,
                'price_per_day' => $car->price_per_day,
                'price_per_week' => $car->price_per_week,
                'price_per_month' => $car->price_per_month,
                'allowed_km_per_day' => $car->allowed_km_per_day,
                'allowed_km_per_week' => $car->allowed_km_per_week,
                'allowed_km_per_month' => $car->allowed_km_per_month,
                'status' => $status instanceof CarStatus ? $status->value : (string) $status,
                'status_label' => $status instanceof CarStatus ? $status->label() : null,
                'status_color' => $status instanceof CarStatus ? $status->color() : null,
                'image_url' => $car->image_url,
                'branch_id' => $car->branch_id,
                'branch_name' => $car->branch?->name,
            ];
        });

        $statuses = collect(CarStatus::cases())->mapWithKeys(function ($status) use ($statusCounts) {
            return [
                $status->value => [
                    'label' => $status->label(),
                    'count' => $statusCounts[$status->value] ?? 0,
                    'color' => $status->color(),
                ]
            ];
        })->toArray();

        $carUsage = $this->planUsageLimits->carUsage($this->currentTenant($request));

        return Inertia::render('Admin/Cars/Index', [
            'cars' => $cars,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $status,
                'scope' => $scope,
                'branch_id' => $branchId,
            ],
            'statuses' => $statuses,
            'branches' => $branchOptions,
            'canAccessAllBranches' => $canAccessAllBranches,
            'carUsage' => $carUsage,
            'canCreateCar' => !($carUsage['at_limit'] ?? false),
        ]);
    }

    /**
     * Show the form for creating a new car.
     */
    public function create(Request $request): Response
    {
        $tenant = $this->currentTenant($request);

        if (!$tenant) {
            abort(403, 'Tenant context is required to check plan limits.');
        }

        if ($message = $this->planUsageLimits->carLimitMessage($tenant)) {
            abort(403, $message);
        }

        $user = request()->user();
        $plateFormats = $this->plateFormatOptions($user);

        return Inertia::render('Admin/Cars/Edit', [
            'car' => null,
            'selectedPlateFormat' => null,
            'plateFormats' => $plateFormats,
            'imageFiles' => [],
            'additionalPhotoFiles' => [],
            'catalog' => [
                'years' => CarCatalogOptions::yearOptions(),
                'makes' => $this->carCatalogForTenant($user),
            ],
            'branches' => $this->branchAccess->availableBranchesForUser($user)->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])->values(),
            'countries' => BranchLocationOptions::countrySelectOptions(app()->getLocale()),
            'supportedLocales' => $this->supportedLocaleMeta(),
            'canAccessAllBranches' => $this->branchAccess->canAccessAllBranches($user),
            'branchUsage' => $this->planUsageLimits->branchUsage(),
            'enums' => [
                'colors' => CarColor::forFrontend(),
                'fuelTypes' => FuelType::forFrontend(),
                'statuses' => array_map(fn($status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'color' => $status->color()
                ], CarStatus::cases()),
            ],
        ]);
    }

    public function storeCatalogEntry(Request $request): JsonResponse
    {
        $tenantId = TenantContext::id() ?: (int) ($request->user()?->tenant_id ?? 0);

        abort_if($tenantId <= 0, 403);

        $validated = $request->validate([
            'make' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:'.((int) now()->format('Y') + 1)],
            'fuel_type' => ['nullable', 'string', Rule::enum(FuelType::class)],
            'transmission' => ['nullable', Rule::in(['automatic', 'manual'])],
            'seats' => ['nullable', 'integer', 'min:1', 'max:20'],
            'engine_power' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        $entry = TenantCarCatalogEntry::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'make' => trim((string) $validated['make']),
                'model' => trim((string) $validated['model']),
                'year' => $validated['year'] ?? null,
            ],
            [
                'fuel_type' => $validated['fuel_type'] ?? null,
                'transmission' => $validated['transmission'] ?? null,
                'seats' => $validated['seats'] ?? null,
                'engine_power' => $validated['engine_power'] ?? null,
            ],
        );

        return response()->json([
            'message' => 'Vehicle model saved successfully.',
            'entry' => $this->catalogEntryPayload($entry),
            'catalog' => [
                'years' => CarCatalogOptions::yearOptions(),
                'makes' => $this->carCatalogForTenant($request->user()),
            ],
        ]);
    }

    private function carCatalogForTenant(?\App\Models\User $user): array
    {
        $makes = collect(CarCatalogOptions::makeOptions())
            ->mapWithKeys(fn (array $make) => [(string) $make['value'] => $make])
            ->all();

        $tenantId = TenantContext::id() ?: (int) ($user?->tenant_id ?? 0);
        if ($tenantId <= 0) {
            return array_values($makes);
        }

        TenantCarCatalogEntry::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('make')
            ->orderBy('model')
            ->orderByDesc('year')
            ->get()
            ->each(function (TenantCarCatalogEntry $entry) use (&$makes): void {
                $makeName = trim((string) $entry->make);
                $modelName = trim((string) $entry->model);

                if ($makeName === '' || $modelName === '') {
                    return;
                }

                if (!isset($makes[$makeName])) {
                    $makes[$makeName] = [
                        'value' => $makeName,
                        'label' => $makeName,
                        'models' => [],
                    ];
                }

                $models = collect($makes[$makeName]['models'] ?? [])
                    ->mapWithKeys(fn (array $model) => [(string) $model['value'] => $model])
                    ->all();

                if (!isset($models[$modelName])) {
                    $models[$modelName] = [
                        'value' => $modelName,
                        'label' => $modelName,
                        'years' => [],
                        'specs' => null,
                    ];
                }

                if ($entry->year) {
                    $years = collect($models[$modelName]['years'] ?? [])
                        ->mapWithKeys(fn (array $year) => [(string) $year['value'] => $year])
                        ->all();
                    $years[(string) $entry->year] = [
                        'value' => (string) $entry->year,
                        'label' => (string) $entry->year,
                    ];
                    $models[$modelName]['years'] = collect($years)
                        ->sortByDesc(fn (array $year) => (int) $year['value'])
                        ->values()
                        ->all();
                }

                $models[$modelName]['specs'] = array_filter([
                    'fuel_type' => $entry->fuel_type,
                    'transmission' => $entry->transmission,
                    'seats' => $entry->seats,
                    'engine_power' => $entry->engine_power,
                ], static fn ($value) => $value !== null && $value !== '');

                $makes[$makeName]['models'] = collect($models)
                    ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all();
            });

        return collect($makes)
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function catalogEntryPayload(TenantCarCatalogEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'make' => $entry->make,
            'model' => $entry->model,
            'year' => $entry->year,
            'fuel_type' => $entry->fuel_type,
            'transmission' => $entry->transmission,
            'seats' => $entry->seats,
            'engine_power' => $entry->engine_power,
        ];
    }

    /**
     * Display the specified car overview.
     */
    public function show(Car $car): Response
    {
        abort_unless($this->branchAccess->canAccessBranchId(request()->user(), $car->branch_id), 403);

        $car->load(['files', 'branch:id,name']);

        $reservations = Reservation::query()
            ->with(['user:id,name,email', 'contract:id,reservation_id,contract_number,status'])
            ->where('car_id', $car->id)
            ->latest('start_date')
            ->get()
            ->map(function (Reservation $reservation) {
                $status = $reservation->status;

                return [
                    'id' => $reservation->id,
                    'number' => $reservation->reservation_number,
                    'client_name' => $reservation->user?->name,
                    'client_email' => $reservation->user?->email,
                    'status' => $status instanceof \BackedEnum ? (string) $status->value : (string) $status,
                    'status_label' => ucfirst(str_replace('_', ' ', $status instanceof \BackedEnum ? (string) $status->value : (string) $status)),
                    'start_date' => optional($reservation->start_date)->toDateString(),
                    'end_date' => optional($reservation->end_date)->toDateString(),
                    'total_amount' => $reservation->total_amount !== null ? (float) $reservation->total_amount : null,
                    'show_url' => route('admin.reservations.show', $reservation),
                    'contract' => $reservation->contract ? [
                        'id' => $reservation->contract->id,
                        'number' => $reservation->contract->contract_number,
                        'status' => $reservation->contract->status,
                        'show_url' => route('admin.contracts.show', $reservation->contract),
                    ] : null,
                ];
            })
            ->values();

        $maintenances = CarMaintenance::query()
            ->with(['maintenanceType:id,name'])
            ->where('car_id', $car->id)
            ->latest('scheduled_date')
            ->latest('id')
            ->get()
            ->map(function (CarMaintenance $maintenance) {
                $status = $maintenance->status;

                return [
                    'id' => $maintenance->id,
                    'type' => $maintenance->maintenanceType?->name ?? '-',
                    'status' => $status instanceof \BackedEnum ? (string) $status->value : (string) $status,
                    'status_label' => method_exists($status, 'label') ? $status->label() : ucfirst(str_replace('_', ' ', (string) $status)),
                    'scheduled_date' => optional($maintenance->scheduled_date)?->toDateString(),
                    'started_at' => optional($maintenance->started_at)?->format('Y-m-d H:i'),
                    'completed_at' => optional($maintenance->completed_at)?->format('Y-m-d H:i'),
                    'cost' => $maintenance->cost !== null ? (float) $maintenance->cost : null,
                    'workshop_name' => $maintenance->workshop_name,
                    'edit_url' => route('admin.maintenance-records.edit', $maintenance),
                ];
            })
            ->values();

        $damageZoneLabels = \App\Support\CarDamageCatalog::zoneLabelMap();
        $damageTypeLabels = collect(\App\Support\CarDamageCatalog::damageTypes())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();

        $damageRepairs = DamageRepair::query()
            ->with(['damageCase:id,zone_code,damage_type', 'workshop:id,name'])
            ->where('car_id', $car->id)
            ->latest('opened_at')
            ->latest('id')
            ->get()
            ->map(function (DamageRepair $repair) use ($damageZoneLabels, $damageTypeLabels) {
                $status = $repair->status;

                return [
                    'id' => $repair->id,
                    'repair_number' => $repair->repair_number,
                    'damage_zone' => $damageZoneLabels[$repair->damageCase?->zone_code ?? ''] ?? ($repair->damageCase?->zone_code ?? '-'),
                    'damage_type' => $damageTypeLabels[$repair->damageCase?->damage_type ?? ''] ?? ($repair->damageCase?->damage_type ?? '-'),
                    'workshop_name' => $repair->workshop_name ?: ($repair->workshop?->name ?? null),
                    'status' => $status instanceof \BackedEnum ? (string) $status->value : (string) $status,
                    'status_label' => method_exists($status, 'label') ? $status->label() : ucfirst(str_replace('_', ' ', (string) $status)),
                    'opened_at' => optional($repair->opened_at)?->format('Y-m-d H:i'),
                    'completed_at' => optional($repair->completed_at)?->format('Y-m-d H:i'),
                    'estimated_cost' => $repair->estimated_cost !== null ? (float) $repair->estimated_cost : null,
                    'actual_cost' => $repair->actual_cost !== null ? (float) $repair->actual_cost : null,
                    'edit_url' => route('admin.damage-repairs.edit', $repair),
                ];
            })
            ->values();

        $documents = CarDocument::query()
            ->with('files')
            ->where('car_id', $car->id)
            ->latest('expiry_date')
            ->latest('id')
            ->get()
            ->map(function (CarDocument $document) use ($car) {
                return [
                    'id' => $document->id,
                    'type' => $document->type,
                    'type_label' => CarDocument::labelForType($document->type),
                    'number' => $document->document_number,
                    'issuer' => $document->issuer,
                    'issue_date' => optional($document->issue_date)->toDateString(),
                    'purchase_date' => optional($document->purchase_date)->toDateString(),
                    'expiry_date' => optional($document->expiry_date)->toDateString(),
                    'days_remaining' => $document->days_remaining,
                    'status_key' => $document->status_key,
                    'front_image_url' => $document->front_image_url,
                    'back_image_url' => $document->back_image_url,
                    'edit_url' => route('admin.cars.documents.edit', ['car' => $car->id, 'document' => $document->id]),
                ];
            })
            ->values();

        $damageReports = CarDamageReport::query()
            ->withCount('items')
            ->where('car_id', $car->id)
            ->latest('inspected_at')
            ->latest('id')
            ->get()
            ->map(function (CarDamageReport $report) {
                return [
                    'id' => $report->id,
                    'number' => $report->report_number,
                    'report_type' => $report->report_type,
                    'report_type_label' => ucfirst(str_replace('_', ' ', $report->report_type)),
                    'status' => $report->status,
                    'status_label' => ucfirst(str_replace('_', ' ', $report->status)),
                    'inspected_at' => optional($report->inspected_at)?->format('Y-m-d H:i'),
                    'items_count' => (int) $report->items_count,
                    'reservation_id' => $report->reservation_id,
                    'contract_id' => $report->contract_id,
                    'edit_url' => route('admin.car-damage-reports.edit', $report),
                ];
            })
            ->values();

        $violations = CarViolation::query()
            ->where('car_id', $car->id)
            ->latest('violation_date')
            ->latest('id')
            ->get()
            ->map(function (CarViolation $violation) {
                $status = $violation->status;

                return [
                    'id' => $violation->id,
                    'number' => $violation->violation_number,
                    'type' => $violation->type,
                    'amount' => $violation->amount !== null ? (float) $violation->amount : null,
                    'status' => $status instanceof \BackedEnum ? (string) $status->value : (string) $status,
                    'status_label' => method_exists($status, 'label') ? $status->label() : ucfirst(str_replace('_', ' ', (string) $status)),
                    'violation_date' => optional($violation->violation_date)->toDateString(),
                    'due_date' => optional($violation->due_date)->toDateString(),
                    'authority' => $violation->authority,
                    'location' => $violation->location,
                    'edit_url' => route('admin.car-violations.edit', $violation),
                ];
            })
            ->values();

        $contracts = Contract::query()
            ->with(['reservation:id,reservation_number,start_date,end_date,user_id', 'reservation.user:id,name,email'])
            ->whereHas('reservation', fn ($query) => $query->where('car_id', $car->id))
            ->latest('contract_date')
            ->latest('id')
            ->get()
            ->map(function (Contract $contract) {
                return [
                    'id' => $contract->id,
                    'number' => $contract->contract_number,
                    'status' => $contract->status,
                    'contract_date' => optional($contract->contract_date)->toDateString(),
                    'renter_name' => $contract->reservation?->user?->name ?? $contract->renter_name,
                    'reservation_number' => $contract->reservation?->reservation_number,
                    'show_url' => route('admin.contracts.show', $contract),
                ];
            })
            ->values();

        $discounts = CarDiscount::query()
            ->where('car_id', $car->id)
            ->latest('starts_at')
            ->latest('id')
            ->get()
            ->map(function (CarDiscount $discount) {
                return [
                    'id' => $discount->id,
                    'name' => $discount->name,
                    'type' => $discount->type instanceof \BackedEnum ? (string) $discount->type->value : (string) $discount->type,
                    'value' => $discount->value !== null ? (float) $discount->value : null,
                    'starts_at' => optional($discount->starts_at)?->format('Y-m-d H:i'),
                    'ends_at' => optional($discount->ends_at)?->format('Y-m-d H:i'),
                    'is_active' => (bool) $discount->is_active,
                    'edit_url' => route('admin.car-discounts.edit', $discount),
                ];
            })
            ->values();

        return Inertia::render('Admin/Cars/Show', [
            'car' => [
                'id' => $car->id,
                'make' => $car->make,
                'model' => $car->model,
                'year' => $car->year,
                'license_plate' => $car->license_plate,
                'status' => $car->status instanceof \BackedEnum ? (string) $car->status->value : (string) $car->status,
                'status_label' => $car->status instanceof CarStatus ? $car->status->label() : (string) $car->status,
                'status_color' => $car->status instanceof CarStatus ? $car->status->color() : '#6B7280',
                'branch_name' => $car->branch?->name,
                'price_per_day' => $car->price_per_day !== null ? (float) $car->price_per_day : null,
                'price_per_week' => $car->price_per_week !== null ? (float) $car->price_per_week : null,
                'price_per_month' => $car->price_per_month !== null ? (float) $car->price_per_month : null,
                'allowed_km_per_day' => $car->allowed_km_per_day,
                'allowed_km_per_week' => $car->allowed_km_per_week,
                'allowed_km_per_month' => $car->allowed_km_per_month,
                'mileage' => $car->mileage,
                'fuel_type' => $car->fuel_type instanceof \BackedEnum ? (string) $car->fuel_type->value : (string) $car->fuel_type,
                'transmission' => $car->transmission,
                'seats' => $car->seats,
                'engine_power' => $car->engine_power,
                'color' => $car->color instanceof \BackedEnum ? (string) $car->color->value : (string) $car->color,
                'description' => method_exists($car, 'localizedDescription')
                    ? $car->localizedDescription(app()->getLocale())
                    : $car->description,
                'image_url' => $car->image_url,
                'additional_photos' => $car->additional_photos,
            ],
            'summary' => [
                'reservations_count' => $reservations->count(),
                'contracts_count' => $contracts->count(),
                'maintenances_count' => $maintenances->count(),
                'damage_repairs_count' => $damageRepairs->count(),
                'documents_count' => $documents->count(),
                'damage_reports_count' => $damageReports->count(),
                'violations_count' => $violations->count(),
                'discounts_count' => $discounts->count(),
            ],
            'reservations' => $reservations,
            'contracts' => $contracts,
            'maintenances' => $maintenances,
            'damageRepairs' => $damageRepairs,
            'documents' => $documents,
            'damageReports' => $damageReports,
            'violations' => $violations,
            'discounts' => $discounts,
            'actions' => [
                'create_maintenance_url' => route('admin.maintenance-records.create', ['car_id' => $car->id]),
                'maintenance_index_url' => route('admin.maintenance-records.index', ['car_id' => $car->id]),
                'create_damage_repair_url' => route('admin.damage-repairs.create'),
                'damage_repairs_index_url' => route('admin.damage-repairs.index', ['car_id' => $car->id]),
                'photo_histories_index_url' => route('admin.cars.photo-histories.index', $car->id),
                'create_photo_history_url' => route('admin.cars.photo-histories.create', $car->id),
            ],
        ]);
    }

    /**
     * Store a newly created car in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $tenantId = (int) ($user?->tenant_id ?? 0);

        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $tenant = $this->currentTenant($request);

        if (!$tenant) {
            abort(403, 'Tenant context is required to check plan limits.');
        }

        if ($message = $this->planUsageLimits->carLimitMessage($tenant)) {
            return redirect()->back()->with('error', $message);
        }

        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);
        $isDraftSubmission = $request->boolean('save_as_draft') || $request->input('status') === CarStatus::DRAFT->value;
        $requiredRule = $isDraftSubmission ? 'nullable' : 'required';
        $plateFormats = $this->plateFormatOptions($user);
        $allowedPlateFormatCodes = array_map(static fn (array $format) => (string) ($format['value'] ?? ''), $plateFormats);
        $this->normalizeLicensePlateInput($request);

        $validated = $request->validate([
            'make' => [$requiredRule, 'string', 'max:255'],
            'model' => [$requiredRule, 'string', 'max:255'],
            'year' => [$requiredRule, 'integer', 'min:1900', 'max:2100'],
            'license_plate' => [$requiredRule, 'string', 'max:255', 'unique:cars,license_plate'],
            'license_plate_format' => [$requiredRule, 'string', Rule::in($allowedPlateFormatCodes)],
            'branch_id' => [
                $isDraftSubmission ? 'nullable' : ($canAccessAllBranches ? 'required' : 'nullable'),
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'color' => [$requiredRule, 'string', Rule::enum(CarColor::class)],
            'price_per_day' => [$requiredRule, 'numeric', 'min:0'],
            'price_per_week' => ['nullable', 'numeric', 'min:0'],
            'price_per_month' => ['nullable', 'numeric', 'min:0'],
            'allowed_km_per_day' => ['nullable', 'integer', 'min:0'],
            'allowed_km_per_week' => ['nullable', 'integer', 'min:0'],
            'allowed_km_per_month' => ['nullable', 'integer', 'min:0'],
            'mileage' => [$requiredRule, 'integer', 'min:0'],
            'transmission' => [$requiredRule, Rule::in(['automatic', 'manual'])],
            'seats' => [$requiredRule, 'integer', 'min:1'],
            'engine_power' => ['nullable', 'integer', 'min:0'],
            'fuel_type' => [$requiredRule, 'string', Rule::enum(FuelType::class)],
            'description' => ['nullable', 'string'],
            'description_translations' => ['nullable', 'array'],
            'description_translations.*' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::enum(CarStatus::class)],
            'status_task_time' => ['nullable', 'date_format:H:i'],
            'image' => ['array'],
            'image.*' => ['string'],
            'additional_photos' => ['array'],
            'additional_photos.*.type' => ['required', 'string', 'distinct', Rule::in(self::ADDITIONAL_PHOTO_TYPES)],
            'additional_photos.*.temp_folders' => ['array'],
            'additional_photos.*.temp_folders.*' => ['string'],
        ]);

        $this->validatePlateNumberAgainstFormat(
            $validated['license_plate'] ?? null,
            $validated['license_plate_format'] ?? null,
            $plateFormats,
            $isDraftSubmission
        );

        $validated['branch_id'] = $this->branchAccess->resolveWritableBranchId(
            $user,
            $this->branchAccess->normalizeRequestedBranchId($validated['branch_id'] ?? null)
        );

        if (!$isDraftSubmission && !$validated['branch_id']) {
            return back()->withErrors([
                'branch_id' => 'A branch is required to create a car.',
            ])->withInput();
        }

        $validated['description_translations'] = $this->localizedTextPayload(
            (array) ($validated['description_translations'] ?? []),
            $validated['description'] ?? null
        );

        $car = Car::create(collect($this->normalizeCarPayload($validated, $isDraftSubmission))->except([
            'image',
            'additional_photos',
            'status_task_time',
        ])->toArray());

        $this->syncCarDailyTaskSchedule($request, $car);

        // Handle uploaded cover image if provided
        if ($request->filled('image')) {
            $this->filePondService->handleFileUploads(
                $car,
                $request->input('image', []),
                'image'
            );
        }

        foreach ($validated['additional_photos'] ?? [] as $photo) {
            $tempFolders = $photo['temp_folders'] ?? [];

            if (empty($photo['type']) || empty($tempFolders)) {
                continue;
            }

            $this->filePondService->handleFileUploads(
                $car,
                $tempFolders,
                Car::additionalPhotoCollection($photo['type'])
            );
        }

        $this->planUsageNotifier->checkCars($tenant->refresh());

        return redirect()
            ->route('admin.cars.index', ['subdomain' => $request->route('subdomain')])
            ->with('success', $isDraftSubmission ? 'Car draft saved successfully.' : 'Car created successfully.');
    }

    /**
     * Show the form for editing the specified car.
     */
    public function edit(Car $car): Response
    {
        abort_unless($this->branchAccess->canAccessBranchId(request()->user(), $car->branch_id), 403);
        $plateFormats = $this->plateFormatOptions(request()->user(), $car->license_plate_format);

        // Provide initial image files for FilePond (only the 'image' collection)
        $disk = config('vilt-filepond.storage_disk', 'public');
        $imageFiles = $car->files()
            ->where('collection', 'image')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'url' => FileUrl::fromStoragePath($f->path),
            ]);

        $additionalPhotoFiles = collect(self::ADDITIONAL_PHOTO_TYPES)
            ->map(function (string $type) use ($car) {
                $files = $car->files()
                    ->where('collection', Car::additionalPhotoCollection($type))
                    ->get()
                    ->map(fn ($file) => [
                        'id' => $file->id,
                        'url' => FileUrl::fromStoragePath($file->path),
                    ])
                    ->values()
                    ->all();

                return [
                    'type' => $type,
                    'files' => $files,
                ];
            })
            ->filter(fn (array $item) => !empty($item['files']))
            ->values();

        $car->setAttribute('status_task_time', $this->currentCarDailyTaskTime($car));

        return Inertia::render('Admin/Cars/Edit', [
            'car' => $car,
            'selectedPlateFormat' => $this->resolveSelectedPlateFormat($car, $plateFormats),
            'plateFormats' => $plateFormats,
            'imageFiles' => $imageFiles,
            'additionalPhotoFiles' => $additionalPhotoFiles,
            'catalog' => [
                'years' => CarCatalogOptions::yearOptions(),
                'makes' => $this->carCatalogForTenant(request()->user()),
            ],
            'branches' => $this->branchAccess->availableBranchesForUser(request()->user())->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])->values(),
            'countries' => BranchLocationOptions::countrySelectOptions(app()->getLocale()),
            'supportedLocales' => $this->supportedLocaleMeta(),
            'canAccessAllBranches' => $this->branchAccess->canAccessAllBranches(request()->user()),
            'branchUsage' => $this->planUsageLimits->branchUsage(),
            'enums' => [
                'colors' => CarColor::forFrontend(),
                'fuelTypes' => FuelType::forFrontend(),
                'statuses' => array_map(fn($status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'color' => $status->color()
                ], CarStatus::cases()),
            ],
        ]);
    }

    public function calendar(Request $request, Car $car): Response
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $car->branch_id), 403);

        $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'view' => ['nullable', Rule::in(['month', 'next_30_days', 'booked_only'])],
        ]);

        $view = $request->string('view')->toString() ?: 'month';
        $selectedMonth = $request->string('month')->toString();
        $month = $selectedMonth !== ''
            ? Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth()
            : now()->startOfMonth();

        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $gridStart = $month->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $month->copy()->endOfWeek(Carbon::SATURDAY);
        $today = now()->startOfDay();

        [$rangeStart, $rangeEnd] = match ($view) {
            'next_30_days' => [$today->copy(), $today->copy()->addDays(29)->endOfDay()],
            default => [$monthStart->copy(), $monthEnd->copy()->endOfDay()],
        };

        $reservations = Reservation::query()
            ->with(['user:id,name,email'])
            ->where('car_id', $car->id)
            ->whereDate('start_date', '<=', $rangeEnd->toDateString())
            ->whereDate('end_date', '>=', $rangeStart->toDateString())
            ->orderBy('start_date')
            ->get()
            ->map(function (Reservation $reservation) {
                $status = $reservation->status;
                $statusValue = match (true) {
                    $status instanceof \BackedEnum => (string) $status->value,
                    $status instanceof \UnitEnum => $status->name,
                    is_string($status) => $status,
                    $status === null => '',
                    default => (string) $status,
                };

                return [
                    'id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'status' => $statusValue,
                    'status_label' => ucfirst(str_replace('_', ' ', $statusValue)),
                    'client_name' => $reservation->user?->name,
                    'start_date' => optional($reservation->start_date)->toDateString(),
                    'end_date' => optional($reservation->end_date)->toDateString(),
                    'pickup_time' => optional($reservation->pickup_time)->format('H:i'),
                    'return_time' => optional($reservation->return_time)->format('H:i'),
                ];
            })
            ->values();

        return Inertia::render('Admin/Cars/Calendar', [
            'car' => [
                'id' => $car->id,
                'make' => $car->make,
                'model' => $car->model,
                'year' => $car->year,
                'license_plate' => $car->license_plate,
                'branch_name' => $car->branch?->name,
            ],
            'month' => [
                'value' => $month->format('Y-m'),
                'label' => $month->format('F Y'),
                'starts_at' => $monthStart->toDateString(),
                'ends_at' => $monthEnd->toDateString(),
                'grid_starts_at' => $gridStart->toDateString(),
                'grid_ends_at' => $gridEnd->toDateString(),
                'previous' => $month->copy()->subMonth()->format('Y-m'),
                'next' => $month->copy()->addMonth()->format('Y-m'),
            ],
            'view' => [
                'value' => $view,
                'window_starts_at' => $rangeStart->toDateString(),
                'window_ends_at' => $rangeEnd->toDateString(),
            ],
            'reservations' => $reservations,
        ]);
    }

    public function availabilityCalendar(Request $request, Car $car): JsonResponse
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $car->branch_id), 403);

        $request->validate([
            'window_start' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $today = now()->startOfDay();
        $selectedWindowStart = $request->string('window_start')->toString();
        $windowStart = $selectedWindowStart !== ''
            ? Carbon::createFromFormat('Y-m-d', $selectedWindowStart)->startOfDay()
            : $today->copy();
        $windowEnd = $windowStart->copy()->addDays(29)->endOfDay();

        $blockedRanges = Reservation::query()
            ->where('car_id', $car->id)
            ->whereIn('status', [
                ReservationStatus::PENDING->value,
                ReservationStatus::CONFIRMED->value,
                ReservationStatus::ACTIVE->value,
            ])
            ->whereDate('start_date', '<=', $windowEnd->toDateString())
            ->whereDate('end_date', '>=', $windowStart->toDateString())
            ->orderBy('start_date')
            ->get(['start_date', 'end_date'])
            ->map(static function (Reservation $reservation) {
                return [
                    'start_date' => optional($reservation->start_date)->toDateString(),
                    'end_date' => optional($reservation->end_date)->toDateString(),
                ];
            })
            ->values();

        return response()->json([
            'availabilityCalendar' => [
                'window_starts_at' => $windowStart->toDateString(),
                'window_ends_at' => $windowEnd->toDateString(),
                'today' => $today->toDateString(),
                'window' => [
                    'starts_at' => $windowStart->toDateString(),
                    'ends_at' => $windowEnd->toDateString(),
                    'label' => sprintf('%s - %s', $windowStart->format('M j, Y'), $windowEnd->format('M j, Y')),
                    'previous' => $windowStart->copy()->subDays(30)->toDateString(),
                    'next' => $windowStart->copy()->addDays(30)->toDateString(),
                ],
                'blocked_ranges' => $blockedRanges,
            ],
        ]);
    }

    /**
     * Update the specified car in storage.
     */
    public function update(Request $request, Car $car)
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $car->branch_id), 403);

        $user = $request->user();
        $tenantId = (int) ($user?->tenant_id ?? 0);
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);
        $isDraftSubmission = $request->boolean('save_as_draft') || $request->input('status') === CarStatus::DRAFT->value;
        $requiredRule = $isDraftSubmission ? 'nullable' : 'required';
        $plateFormats = $this->plateFormatOptions($user);
        $allowedPlateFormatCodes = array_map(static fn (array $format) => (string) ($format['value'] ?? ''), $plateFormats);
        $this->normalizeLicensePlateInput($request);

        $validated = $request->validate([
            'make' => [$requiredRule, 'string', 'max:255'],
            'model' => [$requiredRule, 'string', 'max:255'],
            'year' => [$requiredRule, 'integer', 'min:1900', 'max:2100'],
            'branch_id' => [
                $isDraftSubmission ? 'nullable' : ($canAccessAllBranches ? 'required' : 'nullable'),
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'license_plate' => [
                $requiredRule, 'string', 'max:255', Rule::unique('cars', 'license_plate')->ignore($car->id),
            ],
            'license_plate_format' => [$requiredRule, 'string', Rule::in($allowedPlateFormatCodes)],
            'color' => [$requiredRule, 'string', Rule::enum(CarColor::class)],
            'price_per_day' => [$requiredRule, 'numeric', 'min:0'],
            'price_per_week' => ['nullable', 'numeric', 'min:0'],
            'price_per_month' => ['nullable', 'numeric', 'min:0'],
            'allowed_km_per_day' => ['nullable', 'integer', 'min:0'],
            'allowed_km_per_week' => ['nullable', 'integer', 'min:0'],
            'allowed_km_per_month' => ['nullable', 'integer', 'min:0'],
            'mileage' => [$requiredRule, 'integer', 'min:0'],
            'transmission' => [$requiredRule, Rule::in(['automatic', 'manual'])],
            'seats' => [$requiredRule, 'integer', 'min:1'],
            'engine_power' => ['nullable', 'integer', 'min:0'],
            'fuel_type' => [$requiredRule, 'string', Rule::enum(FuelType::class)],
            'description' => ['nullable', 'string'],
            'description_translations' => ['nullable', 'array'],
            'description_translations.*' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::enum(CarStatus::class)],
            'status_task_time' => ['nullable', 'date_format:H:i'],
            // File updates for the cover image
            'image_temp_folders' => ['array'],
            'image_temp_folders.*' => ['string'],
            'image_removed_files' => ['array'],
            'image_removed_files.*' => ['integer'],
            'additional_photos' => ['array'],
            'additional_photos.*.type' => ['required', 'string', 'distinct', Rule::in(self::ADDITIONAL_PHOTO_TYPES)],
            'additional_photos.*.temp_folders' => ['array'],
            'additional_photos.*.temp_folders.*' => ['string'],
            'additional_photos.*.removed_file_ids' => ['array'],
            'additional_photos.*.removed_file_ids.*' => ['integer'],
            'deleted_additional_photo_types' => ['array'],
            'deleted_additional_photo_types.*' => ['string', Rule::in(self::ADDITIONAL_PHOTO_TYPES)],
        ]);

        $this->validatePlateNumberAgainstFormat(
            $validated['license_plate'] ?? null,
            $validated['license_plate_format'] ?? null,
            $plateFormats,
            $isDraftSubmission
        );

        $validated['branch_id'] = $this->branchAccess->resolveWritableBranchId(
            $user,
            $this->branchAccess->normalizeRequestedBranchId($validated['branch_id'] ?? null)
        );

        if (!$isDraftSubmission && !$validated['branch_id']) {
            return back()->withErrors([
                'branch_id' => 'A branch is required to update a car.',
            ])->withInput();
        }

        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $validated['description_translations'] = $this->localizedTextPayload(
            (array) ($validated['description_translations'] ?? []),
            $validated['description'] ?? null
        );

        $car->update(collect($this->normalizeCarPayload($validated, $isDraftSubmission, $car))->except([
            'image_temp_folders',
            'image_removed_files',
            'additional_photos',
            'deleted_additional_photo_types',
            'status_task_time',
        ])->toArray());

        $this->syncCarDailyTaskSchedule($request, $car);

        $tempFolders = $request->input('image_temp_folders', []);
        $removedIds = $request->input('image_removed_files', []);

        // Handle file updates for the cover image collection
        $this->filePondService->handleFileUpdates(
            $car,
            $tempFolders,
            $removedIds,
            'image'
        );

        foreach ($validated['deleted_additional_photo_types'] ?? [] as $deletedType) {
            $existingIds = $car->files()
                ->where('collection', Car::additionalPhotoCollection($deletedType))
                ->pluck('id')
                ->all();

            if (!empty($existingIds)) {
                $this->filePondService->handleFileUpdates(
                    $car,
                    [],
                    $existingIds,
                    Car::additionalPhotoCollection($deletedType)
                );
            }
        }

        foreach ($validated['additional_photos'] ?? [] as $photo) {
            $type = $photo['type'] ?? null;

            if (!$type) {
                continue;
            }

            $this->filePondService->handleFileUpdates(
                $car,
                $photo['temp_folders'] ?? [],
                $photo['removed_file_ids'] ?? [],
                Car::additionalPhotoCollection($type)
            );
        }

        return redirect()
            ->route('admin.cars.index', ['subdomain' => $request->route('subdomain')])
            ->with('success', $isDraftSubmission ? 'Car draft saved successfully.' : 'Car updated successfully.');
    }

    private function currentCarDailyTaskTime(Car $car): ?string
    {
        $taskType = $this->dailyTaskTypeForCarStatus($car->status instanceof CarStatus ? $car->status->value : (string) $car->status);

        if (!$taskType) {
            return null;
        }

        $stored = DailyTaskStatus::query()
            ->where('tenant_id', $car->tenant_id)
            ->where('task_type', $taskType)
            ->where('source_type', 'car')
            ->where('source_id', $car->id)
            ->first();

        return $stored?->scheduled_at?->format('H:i');
    }

    private function syncCarDailyTaskSchedule(Request $request, Car $car): void
    {
        $taskType = $this->dailyTaskTypeForCarStatus($car->status instanceof CarStatus ? $car->status->value : (string) $car->status);
        $time = trim((string) $request->input('status_task_time', ''));

        if (!$taskType || $time === '') {
            return;
        }

        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        DailyTaskStatus::query()->updateOrCreate(
            [
                'tenant_id' => $car->tenant_id,
                'task_type' => $taskType,
                'source_type' => 'car',
                'source_id' => $car->id,
            ],
            [
                'scheduled_at' => Carbon::today()->setTime($hour, $minute),
            ]
        );
    }

    private function dailyTaskTypeForCarStatus(string $status): ?string
    {
        return match ($status) {
            CarStatus::CLEANING->value => 'cleaning',
            CarStatus::MAINTENANCE->value => 'maintenance',
            default => null,
        };
    }

    /**
     * Normalize car attributes for draft saves so incomplete records can still be stored safely.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeCarPayload(array $validated, bool $isDraftSubmission, ?Car $car = null): array
    {
        if (!$isDraftSubmission) {
            return $validated;
        }

        $now = now();

        return array_merge($validated, [
            'make' => $this->fallbackString($validated['make'] ?? null, $car?->make, 'Draft car'),
            'model' => $this->fallbackString($validated['model'] ?? null, $car?->model, 'Draft car'),
            'year' => $this->fallbackInteger($validated['year'] ?? null, $car?->year, (int) $now->year),
            'license_plate' => $this->fallbackString(
                $validated['license_plate'] ?? null,
                $car?->license_plate,
                'DRAFT-' . strtoupper($now->format('YmdHis')) . '-' . Str::upper(Str::random(6))
            ),
            'license_plate_format' => $this->fallbackString($validated['license_plate_format'] ?? null, $car?->license_plate_format, 'custom'),
            'color' => $this->fallbackString($validated['color'] ?? null, $car?->color?->value ?? $car?->color, CarColor::WHITE->value),
            'price_per_day' => $this->fallbackNumeric($validated['price_per_day'] ?? null, $car?->price_per_day, 0),
            'price_per_week' => $this->fallbackNumeric($validated['price_per_week'] ?? null, $car?->price_per_week, 0),
            'price_per_month' => $this->fallbackNumeric($validated['price_per_month'] ?? null, $car?->price_per_month, 0),
            'allowed_km_per_day' => $this->fallbackInteger($validated['allowed_km_per_day'] ?? null, $car?->allowed_km_per_day, 0),
            'allowed_km_per_week' => $this->fallbackInteger($validated['allowed_km_per_week'] ?? null, $car?->allowed_km_per_week, 0),
            'allowed_km_per_month' => $this->fallbackInteger($validated['allowed_km_per_month'] ?? null, $car?->allowed_km_per_month, 0),
            'mileage' => $this->fallbackInteger($validated['mileage'] ?? null, $car?->mileage, 0),
            'transmission' => $this->fallbackString($validated['transmission'] ?? null, $car?->transmission, 'automatic'),
            'seats' => $this->fallbackInteger($validated['seats'] ?? null, $car?->seats, 1),
            'engine_power' => $this->fallbackInteger($validated['engine_power'] ?? null, $car?->engine_power, 0),
            'fuel_type' => $this->fallbackString($validated['fuel_type'] ?? null, $car?->fuel_type?->value ?? $car?->fuel_type, FuelType::GASOLINE->value),
            'status' => CarStatus::DRAFT->value,
            'branch_id' => $validated['branch_id'] ?? $car?->branch_id,
        ]);
    }

    /**
     * @return array<int, array{code:string,name:string,native:string,direction:string}>
     */
    private function supportedLocaleMeta(): array
    {
        $meta = (array) config('laravellocalization.supportedLocales', []);

        return array_map(function (string $locale) use ($meta): array {
            $details = (array) ($meta[$locale] ?? []);
            $script = strtolower((string) ($details['script'] ?? ''));

            return [
                'code' => $locale,
                'name' => (string) ($details['name'] ?? strtoupper($locale)),
                'native' => (string) ($details['native'] ?? strtoupper($locale)),
                'direction' => $script === 'arab' || in_array($locale, ['ar', 'ur'], true) ? 'rtl' : 'ltr',
            ];
        }, $this->supportedLocaleKeys());
    }

    /**
     * @return list<string>
     */
    private function supportedLocaleKeys(): array
    {
        $supported = array_keys((array) config('laravellocalization.supportedLocales', []));

        if (empty($supported)) {
            $supported = array_values((array) config('app.available_locales', ['en']));
        }

        return array_values(array_filter(array_map('strval', $supported)));
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    private function localizedTextPayload(array $values, ?string $fallback = null): array
    {
        $payload = [];
        $fallback = trim((string) ($fallback ?? ''));

        foreach ($this->supportedLocaleKeys() as $locale) {
            $value = trim((string) ($values[$locale] ?? ''));

            if ($value === '' && $locale === 'en') {
                $value = $fallback;
            }

            if ($value !== '') {
                $payload[$locale] = $value;
            }
        }

        return $payload;
    }

    /**
     * @return array<int, array{value:string,label:string,mask:?string,example:?string,is_active:bool}>
     */
    private function plateFormatOptions(?\App\Models\User $user, ?string $currentCode = null): array
    {
        $tenant = TenantContext::get();
        if (!$tenant && $user?->tenant_id) {
            $tenant = \App\Models\Tenant::query()->find($user->tenant_id);
        }

        $tenantSettings = $tenant
            ? TenantSiteSetting::query()->where('tenant_id', $tenant->id)->first()
            : null;

        $normalizedFormats = PlateFormatSettingsCore::normalize(
            is_array($tenantSettings?->plate_formats) ? $tenantSettings->plate_formats : null
        );
        $globalFormats = PlateFormatSettingsCore::activeFormats(PlateFormatSettingsCore::loadGlobal());
        $formats = collect(array_merge($globalFormats, PlateFormatSettingsCore::activeFormats($normalizedFormats)))
            ->keyBy(static fn (array $format) => strtolower((string) ($format['code'] ?? '')))
            ->values()
            ->all();
        $allFormats = array_merge(PlateFormatSettingsCore::loadGlobal(), $normalizedFormats);

        $options = array_merge([
            [
                'value' => 'custom',
                'label' => 'Custom / Any',
                'mask' => null,
                'example' => null,
                'is_active' => true,
            ],
        ], array_map(static function (array $format): array {
            $parts = array_filter([
                $format['name'] ?? null,
                $format['country'] ? '(' . $format['country'] . ')' : null,
                $format['mask'] ? '[' . $format['mask'] . ']' : null,
            ], static fn ($value) => $value !== null && $value !== '');

            return [
                'value' => (string) ($format['code'] ?? 'custom'),
                'label' => trim(implode(' ', $parts)) ?: (string) ($format['code'] ?? 'Format'),
                'mask' => isset($format['mask']) ? (string) $format['mask'] : null,
                'example' => isset($format['example']) ? (string) $format['example'] : null,
                'is_active' => (bool) ($format['is_active'] ?? true),
            ];
        }, $formats));

        $currentCode = trim((string) ($currentCode ?? ''));
        if ($currentCode !== '' && !collect($options)->contains(fn (array $option) => strcasecmp((string) ($option['value'] ?? ''), $currentCode) === 0)) {
            $currentFormat = PlateFormatSettingsCore::findByCode($allFormats, $currentCode);

            $options[] = $currentFormat ? [
                'value' => (string) ($currentFormat['code'] ?? $currentCode),
                'label' => trim(implode(' ', array_filter([
                    $currentFormat['name'] ?? null,
                    $currentFormat['country'] ? '(' . $currentFormat['country'] . ')' : null,
                    $currentFormat['mask'] ? '[' . $currentFormat['mask'] . ']' : null,
                ], static fn ($value) => $value !== null && $value !== ''))) . ' (Inactive)',
                'mask' => isset($currentFormat['mask']) ? (string) $currentFormat['mask'] : null,
                'example' => isset($currentFormat['example']) ? (string) $currentFormat['example'] : null,
                'is_active' => false,
            ] : [
                'value' => $currentCode,
                'label' => $currentCode . ' (Inactive)',
                'mask' => null,
                'example' => null,
                'is_active' => false,
            ];
        }

        return array_values($options);
    }

    private function resolveSelectedPlateFormat(Car $car, array $plateFormats): string
    {
        $selected = trim((string) ($car->license_plate_format ?? ''));
        if ($selected === '') {
            return 'custom';
        }

        return collect($plateFormats)->contains(
            fn (array $format) => strcasecmp((string) ($format['value'] ?? ''), $selected) === 0
        ) ? $selected : $selected;
    }

    /**
     * @param  array<int, array{value:string,label:string,mask:?string,example:?string,is_active:bool}>  $plateFormats
     */
    private function validatePlateNumberAgainstFormat(mixed $plateNumber, mixed $formatCode, array $plateFormats, bool $isDraftSubmission): void
    {
        $plate = PlateFormatSettingsCore::normalizePlate(is_string($plateNumber) ? $plateNumber : (string) $plateNumber);
        if ($plate === '' || $isDraftSubmission) {
            return;
        }

        $selectedCode = trim((string) ($formatCode ?? 'custom'));
        if ($selectedCode === '' || $selectedCode === 'custom') {
            return;
        }

        $selectedFormat = collect($plateFormats)->firstWhere('value', $selectedCode);
        if (!$selectedFormat || empty($selectedFormat['mask'])) {
            return;
        }

        $regex = PlateFormatSettingsCore::maskToRegex((string) $selectedFormat['mask']);
        if ($regex === null) {
            return;
        }

        if (!preg_match($regex, $plate)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'license_plate' => 'The license plate does not match the selected plate format.',
            ]);
        }
    }

    private function normalizeLicensePlateInput(Request $request): void
    {
        if (!$request->has('license_plate')) {
            return;
        }

        $plate = PlateFormatSettingsCore::normalizePlate($request->input('license_plate'));

        $request->merge([
            'license_plate' => $plate === '' ? null : $plate,
        ]);
    }

    private function fallbackString(mixed $value, mixed $current, string $default): string
    {
        $resolved = $value ?? $current ?? $default;

        return trim((string) $resolved) !== '' ? (string) $resolved : $default;
    }

    private function fallbackInteger(mixed $value, mixed $current, int $default): int
    {
        $resolved = $value ?? $current ?? $default;

        return (int) $resolved;
    }

    private function fallbackNumeric(mixed $value, mixed $current, float|int $default): float
    {
        $resolved = $value ?? $current ?? $default;

        return (float) $resolved;
    }

    /**
     * Remove the specified car from storage.
     */
    public function destroy(Car $car)
    {
        abort_unless($this->branchAccess->canAccessBranchId(request()->user(), $car->branch_id), 403);

        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $car->delete();

        return redirect()
            ->back()
            ->with('success', 'Car deleted successfully.');
    }

    private function currentTenant(?Request $request = null): ?Tenant
    {
        $tenant = TenantContext::get();

        if ($tenant) {
            return $tenant->loadMissing('subscriptionPlan');
        }

        $tenantId = (int) (($request?->user()?->tenant_id) ?? auth()->user()?->tenant_id ?? 0);

        if ($tenantId <= 0) {
            $slug = (string) (($request?->route('subdomain')) ?? request()->route('subdomain') ?? '');

            if ($slug === '') {
                $host = strtolower(request()->getHost());
                $baseHost = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));

                if ($baseHost !== '' && str_ends_with($host, '.'.$baseHost)) {
                    $slug = explode('.', substr($host, 0, -strlen('.'.$baseHost)))[0] ?? '';
                }
            }

            if ($slug === '') {
                return null;
            }

            return Tenant::query()->where('slug', $slug)->with('subscriptionPlan')->first();
        }

        return Tenant::query()->with('subscriptionPlan')->find($tenantId);
    }
}

