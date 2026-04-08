<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Models\Car;
use App\Models\CarDamageReport;
use App\Models\CarDiscount;
use App\Models\CarDocument;
use App\Models\Contract;
use App\Models\CarMaintenance;
use App\Models\CarViolation;
use App\Models\Reservation;
use App\Support\BranchAccess;
use App\Support\CarCatalogOptions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
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

    public function __construct(FilePondService $filePondService, BranchAccess $branchAccess)
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

        return Inertia::render('Admin/Cars/Index', [
            'cars' => $cars,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $status,
                'branch_id' => $branchId,
            ],
            'statuses' => $statuses,
            'branches' => $branchOptions,
            'canAccessAllBranches' => $canAccessAllBranches,
        ]);
    }

    /**
     * Show the form for creating a new car.
     */
    public function create(): Response
    {
        $user = request()->user();

        return Inertia::render('Admin/Cars/Edit', [
            'car' => null,
            'imageFiles' => [],
            'additionalPhotoFiles' => [],
            'catalog' => [
                'years' => CarCatalogOptions::yearOptions(),
                'makes' => CarCatalogOptions::makeOptions(),
            ],
            'branches' => $this->branchAccess->availableBranchesForUser($user)->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])->values(),
            'canAccessAllBranches' => $this->branchAccess->canAccessAllBranches($user),
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
                    'type_label' => $document->type === 'license' ? 'Car License' : 'Car Insurance',
                    'number' => $document->document_number,
                    'issuer' => $document->issuer,
                    'issue_date' => optional($document->issue_date)->toDateString(),
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
                'mileage' => $car->mileage,
                'fuel_type' => $car->fuel_type instanceof \BackedEnum ? (string) $car->fuel_type->value : (string) $car->fuel_type,
                'transmission' => $car->transmission,
                'seats' => $car->seats,
                'color' => $car->color instanceof \BackedEnum ? (string) $car->color->value : (string) $car->color,
                'description' => $car->description,
                'image_url' => $car->image_url,
                'additional_photos' => $car->additional_photos,
            ],
            'summary' => [
                'reservations_count' => $reservations->count(),
                'contracts_count' => $contracts->count(),
                'maintenances_count' => $maintenances->count(),
                'documents_count' => $documents->count(),
                'damage_reports_count' => $damageReports->count(),
                'violations_count' => $violations->count(),
                'discounts_count' => $discounts->count(),
            ],
            'reservations' => $reservations,
            'contracts' => $contracts,
            'maintenances' => $maintenances,
            'documents' => $documents,
            'damageReports' => $damageReports,
            'violations' => $violations,
            'discounts' => $discounts,
        ]);
    }

    /**
     * Store a newly created car in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $tenantId = (int) ($user?->tenant_id ?? 0);
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

        $validated = $request->validate([
            'make' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'license_plate' => ['required', 'string', 'max:255', 'unique:cars,license_plate'],
            'branch_id' => [
                $canAccessAllBranches ? 'required' : 'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'color' => ['required', 'string', Rule::enum(CarColor::class)],
            'price_per_day' => ['required', 'numeric', 'min:0'],
            'mileage' => ['required', 'integer', 'min:0'],
            'transmission' => ['required', Rule::in(['automatic', 'manual'])],
            'seats' => ['required', 'integer', 'min:1'],
            'fuel_type' => ['required', 'string', Rule::enum(FuelType::class)],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::enum(CarStatus::class)],
            'image' => ['array'],
            'image.*' => ['string'],
            'additional_photos' => ['array'],
            'additional_photos.*.type' => ['required', 'string', 'distinct', Rule::in(self::ADDITIONAL_PHOTO_TYPES)],
            'additional_photos.*.temp_folders' => ['array'],
            'additional_photos.*.temp_folders.*' => ['string'],
        ]);

        $validated['branch_id'] = $this->branchAccess->resolveWritableBranchId(
            $user,
            $this->branchAccess->normalizeRequestedBranchId($validated['branch_id'] ?? null)
        );

        if (!$validated['branch_id']) {
            return back()->withErrors([
                'branch_id' => 'A branch is required to create a car.',
            ])->withInput();
        }

        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $car = Car::create(collect($validated)->except(['image', 'additional_photos'])->toArray());

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

        return redirect()
            ->route('admin.cars.index')
            ->with('success', 'Car created successfully.');
    }

    /**
     * Show the form for editing the specified car.
     */
    public function edit(Car $car): Response
    {
        abort_unless($this->branchAccess->canAccessBranchId(request()->user(), $car->branch_id), 403);

        // Provide initial image files for FilePond (only the 'image' collection)
        $disk = config('vilt-filepond.storage_disk', 'public');
        $imageFiles = $car->files()
            ->where('collection', 'image')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'url' => Storage::url($f->path),
            ]);

        $additionalPhotoFiles = collect(self::ADDITIONAL_PHOTO_TYPES)
            ->map(function (string $type) use ($car) {
                $files = $car->files()
                    ->where('collection', Car::additionalPhotoCollection($type))
                    ->get()
                    ->map(fn ($file) => [
                        'id' => $file->id,
                        'url' => Storage::url($file->path),
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

        return Inertia::render('Admin/Cars/Edit', [
            'car' => $car,
            'imageFiles' => $imageFiles,
            'additionalPhotoFiles' => $additionalPhotoFiles,
            'catalog' => [
                'years' => CarCatalogOptions::yearOptions(),
                'makes' => CarCatalogOptions::makeOptions(),
            ],
            'branches' => $this->branchAccess->availableBranchesForUser(request()->user())->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])->values(),
            'canAccessAllBranches' => $this->branchAccess->canAccessAllBranches(request()->user()),
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

    /**
     * Update the specified car in storage.
     */
    public function update(Request $request, Car $car)
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $car->branch_id), 403);

        $user = $request->user();
        $tenantId = (int) ($user?->tenant_id ?? 0);
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

        $validated = $request->validate([
            'make' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'branch_id' => [
                $canAccessAllBranches ? 'required' : 'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'license_plate' => [
                'required', 'string', 'max:255', Rule::unique('cars', 'license_plate')->ignore($car->id),
            ],
            'color' => ['required', 'string', Rule::enum(CarColor::class)],
            'price_per_day' => ['required', 'numeric', 'min:0'],
            'mileage' => ['required', 'integer', 'min:0'],
            'transmission' => ['required', Rule::in(['automatic', 'manual'])],
            'seats' => ['required', 'integer', 'min:1'],
            'fuel_type' => ['required', 'string', Rule::enum(FuelType::class)],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::enum(CarStatus::class)],
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

        $validated['branch_id'] = $this->branchAccess->resolveWritableBranchId(
            $user,
            $this->branchAccess->normalizeRequestedBranchId($validated['branch_id'] ?? null)
        );

        if (!$validated['branch_id']) {
            return back()->withErrors([
                'branch_id' => 'A branch is required to update a car.',
            ])->withInput();
        }

        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }


        $car->update(collect($validated)->except([
            'image_temp_folders',
            'image_removed_files',
            'additional_photos',
            'deleted_additional_photo_types',
        ])->toArray());

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
            ->route('admin.cars.index')
            ->with('success', 'Car updated successfully.');
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
}
