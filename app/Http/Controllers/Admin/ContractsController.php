<?php

namespace App\Http\Controllers\Admin;

use App\Core\AiAutomationSettings;
use App\Core\AiProviderSettings;
use App\Core\TenantContext;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarDamageCase;
use App\Models\Contract;
use App\Models\ContractArchiveFile;
use App\Models\ContractDriver;
use App\Models\ContractDriverDocument;
use App\Models\Payment;
use App\Models\RentalExtensionRequest;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use App\Models\User;
use App\Notifications\ContractExtensionRequestedNotification;
use App\Notifications\ContractForceExtendedNotification;
use App\Services\Contracts\ContractAiExtractor;
use App\Services\Contracts\ContractDriverDocumentExtractor;
use App\Services\Plans\PlanUsageLimits;
use App\Rules\DigitsOnly;
use App\Rules\LettersOnly;
use App\Support\BranchAccess;
use App\Support\CarDamageCatalog;
use App\Support\ContractCustomerPhotoExtractor;
use App\Support\PaidReturnReportLock;
use App\Support\PdfRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use MohamedGaldi\ViltFilepond\Models\TempFile;
use MohamedGaldi\ViltFilepond\Services\FilePondService;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

class ContractsController extends Controller
{
    public function __construct(
        private BranchAccess $branchAccess,
        private FilePondService $filePondService,
        private ContractAiExtractor $contractAiExtractor,
        private ContractDriverDocumentExtractor $contractDriverDocumentExtractor,
        private ContractCustomerPhotoExtractor $contractCustomerPhotoExtractor,
        private PlanUsageLimits $planUsageLimits
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);
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

        $contractsQuery = Contract::query()
            ->with([
                'reservation:id,reservation_number,user_id,car_id',
                'reservation.user:id,name,email',
                'reservation.car:id,make,model,year,license_plate',
                'branch:id,name',
            ])
            ->withCount([
                'files as start_contract_count' => fn ($q) => $q->where('collection', 'start_contract'),
                'files as end_contract_count' => fn ($q) => $q->where('collection', 'end_contract'),
            ]);

        $this->applyContractBranchScope($contractsQuery, $user, $branchId);

        $contracts = $contractsQuery
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('contract_number', 'like', "%{$search}%")
                        ->orWhere('renter_name', 'like', "%{$search}%")
                        ->orWhereHas('reservation', function ($rq) use ($search) {
                            $rq->where('reservation_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== '' && $status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $contracts->getCollection()->transform(function (Contract $contract) {
            return [
                'id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'status' => $contract->status,
                'reservation_number' => $contract->reservation?->reservation_number,
                'renter_name' => $contract->renter_name,
                'branch_name' => $contract->branch?->name,
                'start_date' => optional($contract->start_date)->toDateString(),
                'end_date' => optional($contract->end_date)->toDateString(),
                'total_amount' => $contract->total_amount,
                'currency' => $contract->currency,
                'has_start_contract' => (int) $contract->start_contract_count > 0,
                'has_end_contract' => (int) $contract->end_contract_count > 0,
            ];
        });

        return Inertia::render('Admin/Contracts/Index', [
            'contracts' => $contracts,
            'filters' => [
                'search' => $search,
                'status' => $status === '' ? 'all' : $status,
                'branch_id' => $branchId,
            ],
            'branches' => $branchOptions,
            'canAccessAllBranches' => $canAccessAllBranches,
            'statuses' => ['draft', 'active', 'completed', 'cancelled'],
            'actions' => [
                'index' => route('admin.contracts.index', ['subdomain' => $request->route('subdomain')]),
                'create' => route('admin.contracts.create', ['subdomain' => $request->route('subdomain')]),
                'extract' => route('admin.contracts.extract', ['subdomain' => $request->route('subdomain')]),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $reservation = null;
        if ($request->filled('reservation_id')) {
            $reservation = Reservation::query()
                ->with(['user:id,name,email', 'car:id,branch_id,make,model,year,license_plate'])
                ->find($request->integer('reservation_id'));
            if ($reservation) {
                $this->ensureReservationAccessible($reservation, $request->user(), null);
            }
        }

        $reservations = $this->reservationOptions($request);
        $carDamageMap = $this->serializeCarDamageCaseMap(
            collect($reservations)
                ->pluck('car_id')
                ->push($reservation?->car_id)
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all(),
            $request->user()
        );

        return Inertia::render('Admin/Contracts/Edit', [
            'mode' => 'create',
            'contract' => $reservation ? $this->prefillFromReservation($reservation) : [
                'contract_number' => $this->generateContractNumber(),
                'status' => 'draft',
                'contract_date' => now()->toDateString(),
                'currency' => strtoupper((string) config('app.currency_code', 'USD')),
            ],
            'carData' => $reservation ? $this->prefillCarDataFromReservation($reservation) : $this->emptyCarData(),
            'currentCarDamages' => $reservation?->car_id ? $this->serializeCarDamageCases((int) $reservation->car_id, $request->user()) : [],
            'carDamagesByCar' => $carDamageMap,
            'primaryDriver' => $reservation ? $this->prefillPrimaryDriverFromReservation($reservation) : $this->emptyDriverPayload('primary'),
            'additionalDrivers' => [],
            'contractArchive' => $this->emptyContractArchivePayload(),
            'additionalArchive' => [],
            'reservationOptions' => $reservations,
            'reservationFormOptions' => [
                'clients' => $this->reservationClientOptions($request),
                'cars' => $this->reservationCarOptions($request),
            ],
            'startContractFiles' => [],
            'endContractFiles' => [],
            'ai' => [
                'contracts_extraction_enabled' => AiAutomationSettings::isContractsExtractionEnabled(),
            ],
            'actions' => [
                'index' => route('admin.contracts.index', ['subdomain' => $request->route('subdomain')]),
                'store' => route('admin.contracts.store', ['subdomain' => $request->route('subdomain')]),
                'extract' => route('admin.contracts.extract', ['subdomain' => $request->route('subdomain')]),
                'extractDriver' => route('admin.contracts.drivers.extract', ['subdomain' => $request->route('subdomain')]),
                'extractCustomerPhoto' => route('admin.contracts.drivers.photo.extract', ['subdomain' => $request->route('subdomain')]),
                'reservationStore' => route('admin.reservations.store', ['subdomain' => $request->route('subdomain')]),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = (int) (TenantContext::id() ?? 0);
        if ($tenantId <= 0) {
            abort(404);
        }

        if ($message = $this->planUsageLimits->contractLimitMessage()) {
            return redirect()->back()->with('error', $message);
        }

        $tenant = Tenant::query()
            ->with('subscriptionPlan')
            ->find($tenantId);

        if ($tenant?->subscriptionPlan?->max_contracts !== null) {
            $contractCount = Contract::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->count();

            if ($contractCount >= (int) $tenant->subscriptionPlan->max_contracts) {
                return redirect()->back()->with(
                    'error',
                    "Your plan allows up to {$tenant->subscriptionPlan->max_contracts} contracts. Upgrade your plan to add more."
                );
            }
        }

        $validated = $this->validatePayload($request, $tenantId);
        $reservation = $this->validatedReservation($validated['reservation_id'] ?? null, $request, null);

        $contract = DB::transaction(function () use ($request, $tenantId, $validated, $reservation) {
            $contract = new Contract();
            $contract->tenant_id = $tenantId;
            $contract->branch_id = $reservation?->car?->branch_id ? (int) $reservation->car->branch_id : null;
            if (!$contract->branch_id) {
                $contract->branch_id = $this->resolveContractBranchId($validated, $request);
            }
            $contract->reservation_id = $reservation?->id;
            $this->fillContract($contract, $validated, $reservation);
            $this->syncAiStatus($contract, $request, true);
            $contract->save();

            $this->syncFiles($contract, $request, 'start_contract');
            $this->syncFiles($contract, $request, 'end_contract');
            $this->syncContractDrivers($contract, $validated, $reservation);
            $this->syncAdditionalArchiveFiles($contract, $validated);
            $this->completeWaitingReservationIfNeeded($reservation);

            return $contract;
        });

        return redirect()
            ->route('admin.contracts.show', [
                'subdomain' => $request->route('subdomain'),
                'contract' => $contract->id,
            ])
            ->with('success', 'Contract created successfully.');
    }

    public function show(Contract $contract): Response
    {
        abort_unless($this->canAccessContract($contract, request()->user()), 403);
        $contract->loadMissing([
            'reservation.user:id,name,email',
            'reservation.car:id,make,model,year,license_plate,mileage',
            'branch:id,name',
            'files',
            'primaryDriver.documents',
            'additionalDrivers.documents',
            'damageReports.items',
            'extensionRequests' => fn ($query) => $query->latest('id'),
        ]);

        $damageCreateUrl = url('/admin/car-damage-reports/create') . '?contract_id=' . $contract->id;
        $isLocked = PaidReturnReportLock::contract($contract);

        return Inertia::render('Admin/Contracts/Show', [
            'contract' => [
                'id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'status' => $contract->status,
                'contract_date' => optional($contract->contract_date)->toDateString(),
                'renter_name' => $contract->renter_name,
                'renter_id_number' => $contract->renter_id_number,
                'renter_phone' => $contract->renter_phone,
                'car_details' => $contract->car_details,
                'plate_number' => $contract->plate_number,
                'vehicle_odometer' => $contract->vehicle_odometer,
                'vehicle_fuel_level' => $contract->vehicle_fuel_level,
                'price_per_day' => $contract->price_per_day,
                'daily_rate' => $this->resolveContractDailyRate($contract),
                'price_per_week' => $contract->price_per_week,
                'price_per_month' => $contract->price_per_month,
                'allowed_km_per_day' => $contract->allowed_km_per_day,
                'allowed_km_per_week' => $contract->allowed_km_per_week,
                'allowed_km_per_month' => $contract->allowed_km_per_month,
                'return_odometer' => $contract->return_odometer,
                'return_fuel_level' => $contract->return_fuel_level,
                'vehicle_condition_before' => $contract->vehicle_condition_before,
                'vehicle_condition_after' => $contract->vehicle_condition_after,
                'actual_return_time' => optional($contract->actual_return_time)->format('Y-m-d\TH:i'),
                'start_date' => optional($contract->start_date)->toDateString(),
                'end_date' => optional($contract->end_date)->toDateString(),
                'total_amount' => $contract->total_amount,
                'currency' => $contract->currency,
                'notes' => $contract->notes,
                'ai_extraction_status' => $contract->ai_extraction_status,
                'ai_extracted_data' => $contract->ai_extracted_data,
                'is_locked' => $isLocked,
                'reservation' => $contract->reservation ? [
                    'id' => $contract->reservation->id,
                    'reservation_number' => $contract->reservation->reservation_number,
                    'user_name' => $contract->reservation->user?->name,
                    'car' => $contract->reservation->car
                        ? "{$contract->reservation->car->year} {$contract->reservation->car->make} {$contract->reservation->car->model}"
                        : null,
                ] : null,
                'branch_name' => $contract->branch?->name,
                'current_damage_cases' => $contract->reservation?->car?->id
                    ? $this->serializeCarDamageCases((int) $contract->reservation->car->id, request()->user())
                    : [],
                'primary_driver' => $contract->primaryDriver ? $this->serializeDriver($contract->primaryDriver) : null,
                'additional_drivers' => $contract->additionalDrivers->map(fn (ContractDriver $driver) => $this->serializeDriver($driver))->values()->all(),
                'damage_reports' => $contract->damageReports->map(function (\App\Models\CarDamageReport $report) {
                    return [
                        'id' => $report->id,
                        'report_number' => $report->report_number,
                        'report_type' => $report->report_type,
                        'report_type_label' => collect(CarDamageCatalog::reportTypes())->firstWhere('value', $report->report_type)['label']
                            ?? Str::title(str_replace('_', ' ', (string) $report->report_type)),
                        'status' => $report->status,
                        'inspected_at' => optional($report->inspected_at)?->format('Y-m-d H:i'),
                        'items_count' => $report->items->count(),
                        'total_quantity' => (int) $report->items->sum('quantity'),
                        'items' => $report->items->map(function ($item) {
                            return [
                                'zone_code' => $item->zone_code,
                                'zone_label' => CarDamageCatalog::zoneLabelMap()[$item->zone_code] ?? $item->zone_code,
                                'damage_type' => $item->damage_type,
                                'severity' => $item->severity,
                                'quantity' => (int) $item->quantity,
                                'notes' => $item->notes,
                            ];
                        })->values()->all(),
                        'edit_url' => url('/admin/car-damage-reports/'.$report->getKey().'/edit'),
                    ];
                })->values()->all(),
                'extension_requests' => $contract->extensionRequests->map(function (RentalExtensionRequest $request) {
                    return [
                        'id' => $request->id,
                        'status' => $request->status instanceof \App\Enums\RentalExtensionRequestStatus
                            ? $request->status->value
                            : (string) $request->status,
                        'status_label' => $request->status instanceof \App\Enums\RentalExtensionRequestStatus
                            ? $request->status->label()
                            : ucfirst((string) $request->status),
                        'new_end_date' => optional($request->new_end_date)?->toDateString(),
                        'extra_days' => $request->extra_days,
                        'extra_amount' => (float) $request->extra_amount,
                        'reason' => $request->reason,
                        'client_notes' => $request->client_notes,
                        'requested_at' => optional($request->created_at)?->toDateTimeString(),
                        'responded_at' => optional($request->responded_at)?->toDateTimeString(),
                    ];
                })->values()->all(),
                'has_pending_extension_request' => $contract->extensionRequests
                    ->contains(fn (RentalExtensionRequest $request) => $request->status === \App\Enums\RentalExtensionRequestStatus::PENDING),
            ],
            'startRentalDocument' => $this->firstFileMeta($contract, 'start_contract'),
            'endRentalDocument' => $this->firstFileMeta($contract, 'end_contract'),
            'actions' => [
                'index' => url('/admin/contracts'),
                'edit' => $isLocked ? null : url('/admin/contracts/'.$contract->id.'/edit'),
                'damage_create' => $isLocked ? null : $damageCreateUrl,
                'pdf' => url('/admin/contracts/'.$contract->id.'/pdf'),
                'pdf_en' => url('/admin/contracts/'.$contract->id.'/pdf?lang=en'),
                'pdf_ar' => url('/admin/contracts/'.$contract->id.'/pdf?lang=ar'),
                'request_extend' => !$isLocked && $this->isExtendableContract($contract)
                    ? url('/admin/contracts/'.$contract->id.'/extension-request')
                    : null,
                'extend' => !$isLocked && $this->isExtendableContract($contract)
                    ? url('/admin/contracts/'.$contract->id.'/extend')
                    : null,
                'return_report' => url('/admin/contracts/'.$contract->id.'/return-status-report'),
            ],
        ]);
    }

    public function pdf(Request $request, Contract $contract)
    {
        abort_unless($this->canAccessContract($contract, $request->user()), 403);

        $supportedLocales = array_values((array) config('app.available_locales', ['en', 'ar']));
        $fallbackLocale = (string) config('app.fallback_locale', config('app.locale', 'en'));
        $requestedLocale = strtolower((string) $request->query('lang', app()->getLocale()));
        $locale = in_array($requestedLocale, $supportedLocales, true) ? $requestedLocale : $fallbackLocale;

        app()->setLocale($locale);
        LaravelLocalization::setLocale($locale);

        $contract->loadMissing([
            'reservation.user:id,name,email',
            'reservation.car:id,make,model,year,license_plate,mileage',
            'branch:id,name',
            'tenant.siteSetting.files',
            'primaryDriver.documents',
            'additionalDrivers.documents',
            'damageReports.items',
        ]);

        $reportTypeLabels = collect(CarDamageCatalog::reportTypes())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $statusLabels = collect(CarDamageCatalog::statuses())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $damageTypeLabels = collect(CarDamageCatalog::damageTypes())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $severityLabels = collect(CarDamageCatalog::severityLevels())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $viewSideLabels = collect(CarDamageCatalog::viewSides())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $zoneLabels = CarDamageCatalog::zoneLabelMap();
        $direction = str_starts_with($locale, 'ar') ? 'rtl' : 'ltr';

        $currentDamageCases = $contract->reservation?->car?->id
            ? $this->serializeCarDamageCases((int) $contract->reservation->car->id, $request->user())
            : [];
        $branding = $this->pdfBranding($contract->tenant);

        $viewData = [
            'contract' => $contract,
            'currentDamageCases' => $currentDamageCases,
            'damageDiagram' => $this->buildPrintableDamageDiagram($currentDamageCases, $viewSideLabels),
            'reportTypeLabels' => $reportTypeLabels,
            'statusLabels' => $statusLabels,
            'damageTypeLabels' => $damageTypeLabels,
            'severityLabels' => $severityLabels,
            'viewSideLabels' => $viewSideLabels,
            'zoneLabels' => $zoneLabels,
            'generatedAt' => now(),
            'companyName' => $branding['name'],
            'companyLogo' => $branding['logo'],
            'currencySymbol' => config('app.currency_symbol', '$'),
            'locale' => $locale,
            'direction' => $direction,
        ];

        $fileName = $contract->contract_number.'-'.$locale.'-report.pdf';

        if (PdfRuntime::hasNodeBinary()) {
            try {
                return Pdf::view('admin.contracts.pdf', $viewData)
                    ->format(Format::A4)
                    ->portrait()
                    ->margins(4, 4, 4, 4)
                    ->withBrowsershot(function (Browsershot $browsershot): void {
                        $browsershot
                            ->waitUntilNetworkIdle(false)
                            ->timeout(120)
                            ->newHeadless();
                    })
                    ->download($fileName);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return DomPdf::loadView('admin.contracts.pdf', $viewData)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isRemoteEnabled', true)
            ->setPaper('a4', 'portrait')
            ->download($fileName);
    }
    public function edit(Request $request, Contract $contract): Response
    {
        $routeContract = $request->route('contract');
        $contractId = $routeContract instanceof Contract
            ? (int) $routeContract->getKey()
            : (int) ($routeContract ?? $contract->getKey() ?? 0);
        abort_unless($contractId > 0, 404);

        $contract = Contract::query()->withoutGlobalScopes()->findOrFail($contractId);
        abort_unless($this->canAccessContract($contract, $request->user()), 403);
        $isLocked = PaidReturnReportLock::contract($contract);
        $contract->loadMissing([
            'files',
            'primaryDriver.documents',
            'additionalDrivers.documents',
            'archiveFiles.driver',
        ]);
        $reservationOptions = $this->reservationOptions($request);
        $carDamageMap = $this->serializeCarDamageCaseMap(
            collect($reservationOptions)
                ->pluck('car_id')
                ->push($contract->reservation?->car?->id)
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all(),
            $request->user()
        );

        return Inertia::render('Admin/Contracts/Edit', [
            'mode' => 'edit',
            'contract' => [
                'id' => $contract->id,
                'reservation_id' => $contract->reservation_id,
                'contract_number' => $contract->contract_number,
                'status' => $contract->status,
                'contract_date' => optional($contract->contract_date)->toDateString(),
                'renter_name' => $contract->renter_name,
                'renter_id_number' => $contract->renter_id_number,
                'renter_phone' => $contract->renter_phone,
                    'car_details' => $contract->car_details,
                    'plate_number' => $contract->plate_number,
                    'vehicle_odometer' => $contract->vehicle_odometer,
                    'vehicle_fuel_level' => $contract->vehicle_fuel_level,
                    'price_per_day' => $contract->price_per_day,
                    'price_per_week' => $contract->price_per_week,
                    'price_per_month' => $contract->price_per_month,
                    'allowed_km_per_day' => $contract->allowed_km_per_day,
                    'allowed_km_per_week' => $contract->allowed_km_per_week,
                    'allowed_km_per_month' => $contract->allowed_km_per_month,
                    'return_odometer' => $contract->return_odometer,
                    'return_fuel_level' => $contract->return_fuel_level,
                    'vehicle_condition_before' => $contract->vehicle_condition_before,
                    'vehicle_condition_after' => $contract->vehicle_condition_after,
                    'actual_return_time' => optional($contract->actual_return_time)->format('Y-m-d\TH:i'),
                    'start_date' => optional($contract->start_date)->toDateString(),
                'end_date' => optional($contract->end_date)->toDateString(),
                'total_amount' => $contract->total_amount,
                'currency' => $contract->currency,
                'notes' => $contract->notes,
                'ai_extracted_data' => $contract->ai_extracted_data,
                'ai_extraction_status' => $contract->ai_extraction_status,
                'is_locked' => $isLocked,
                'primary_driver' => $contract->primaryDriver ? $this->serializeDriver($contract->primaryDriver) : $this->emptyDriverPayload('primary'),
                'additional_drivers' => $contract->additionalDrivers->map(fn (ContractDriver $driver) => $this->serializeDriver($driver))->values()->all(),
                'car_data' => $this->serializeCarData($contract),
                'contract_archive' => $this->serializeContractArchive($contract),
            ],
            'carData' => $this->serializeCarData($contract),
            'currentCarDamages' => $contract->reservation?->car?->id
                ? $this->serializeCarDamageCases((int) $contract->reservation->car->id, $request->user())
                : [],
            'carDamagesByCar' => $carDamageMap,
            'primaryDriver' => $contract->primaryDriver ? $this->serializeDriver($contract->primaryDriver) : $this->emptyDriverPayload('primary'),
            'additionalDrivers' => $contract->additionalDrivers->map(fn (ContractDriver $driver) => $this->serializeDriver($driver))->values()->all(),
            'contractArchive' => $this->serializeContractArchive($contract),
            'additionalArchive' => $this->serializeAdditionalArchiveFiles($contract),
            'reservationOptions' => $reservationOptions,
            'reservationFormOptions' => [
                'clients' => $this->reservationClientOptions($request),
                'cars' => $this->reservationCarOptions($request),
            ],
            'startContractFiles' => $this->collectionFiles($contract, 'start_contract'),
            'endContractFiles' => $this->collectionFiles($contract, 'end_contract'),
            'ai' => [
                'contracts_extraction_enabled' => AiAutomationSettings::isContractsExtractionEnabled(),
            ],
            'actions' => [
                'index' => url('/admin/contracts'),
                'update' => $isLocked ? null : url('/admin/contracts/'.$contract->getKey()),
                'show' => url('/admin/contracts/'.$contract->getKey()),
                'extract' => $isLocked ? null : url('/admin/contracts/extract'),
                'extractDriver' => $isLocked ? null : url('/admin/contracts/drivers/extract'),
                'extractCustomerPhoto' => $isLocked ? null : url('/admin/contracts/drivers/photo/extract'),
                'reservationStore' => $isLocked ? null : url('/admin/reservations'),
            ],
        ]);
    }

    public function extract(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'temp_folders' => ['required', 'array', 'min:1'],
            'temp_folders.*' => ['string'],
        ]);

        if (!AiAutomationSettings::isContractsExtractionEnabled()) {
            return response()->json([
                'message' => 'AI extraction is disabled by Super Admin settings.',
            ], 422);
        }

        if (!AiProviderSettings::isConfiguredForCurrentProvider()) {
            return response()->json([
                'message' => 'AI provider is not fully configured in Super Admin settings.',
            ], 422);
        }

        try {
            $result = $this->contractAiExtractor->extractFromTempFolders($validated['temp_folders']);

            return response()->json([
                'message' => 'AI extraction completed.',
                'fields' => $result['fields'] ?? [],
                'raw_output' => $result['raw_output'] ?? null,
                'text_preview' => $result['text_preview'] ?? '',
            ]);
        } catch (\Throwable $e) {
            $message = (string) $e->getMessage();
            $status = str_contains(strtolower($message), 'rate limit') ? 429 : 422;

            return response()->json([
                'message' => $message,
            ], $status);
        }
    }

    public function extractDriverDocument(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver_role' => ['required', Rule::in(['primary', 'additional'])],
            'driver_index' => ['nullable', 'integer', 'min:0'],
            'document_type' => ['required', Rule::in(['passport', 'driver_license', 'id_card', 'residency_card'])],
            'temp_folders' => ['required', 'array', 'min:1'],
            'temp_folders.*' => ['string'],
        ]);

        try {
            $result = $this->contractDriverDocumentExtractor->extractFromTempFolders(
                $validated['temp_folders'],
                (string) $validated['document_type']
            );

            return response()->json([
                'message' => 'Driver document extraction completed.',
                'driver_role' => $validated['driver_role'],
                'driver_index' => $validated['driver_index'] ?? null,
                'document_type' => $validated['document_type'],
                'fields' => $result['fields'],
                'raw_output' => $result['raw_output'],
                'raw_text' => $result['raw_text'],
                'confidence' => $result['confidence'],
                'provider' => $result['provider'],
                'engine' => $result['engine'],
                'status' => 'extracted',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => (string) $e->getMessage(),
            ], 422);
        }
    }

    public function extractDriverCustomerPhoto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', Rule::in(['passport', 'driver_license', 'id_card', 'residency_card'])],
            'temp_folders' => ['required', 'array', 'min:1'],
            'temp_folders.*' => ['string'],
        ]);

        $tempFile = TempFile::query()->where('folder', (string) $validated['temp_folders'][0])->first();
        if (! $tempFile) {
            return response()->json(['message' => 'Uploaded document could not be found.'], 422);
        }

        try {
            $result = $this->contractCustomerPhotoExtractor->extractToTempFolder(
                (string) $tempFile->path,
                $tempFile->mime_type,
                (string) $validated['document_type'],
                config('vilt-filepond.storage_disk')
            );

            if (! $result) {
                return response()->json(['message' => 'Could not extract customer photo from the uploaded document.'], 422);
            }

            return response()->json([
                'message' => 'Customer photo extracted successfully.',
                'folder' => $result['folder'],
                'url' => $result['url'],
                'filename' => $result['filename'],
                'mime_type' => $result['mime_type'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => (string) $e->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, Contract $contract): RedirectResponse
    {
        abort_unless($this->canAccessContract($contract, $request->user()), 403);
        $this->abortIfContractLocked($contract);
        $tenantId = (int) (TenantContext::id() ?? 0);
        if ($tenantId <= 0) {
            abort(404);
        }

        $validated = $this->validatePayload($request, $tenantId, $contract->id, $contract);
        $reservation = $this->validatedReservation($validated['reservation_id'] ?? null, $request, $contract->id);

        DB::transaction(function () use ($request, $validated, $reservation, $contract) {
            $contract->branch_id = $reservation?->car?->branch_id ? (int) $reservation->car->branch_id : $contract->branch_id;
            if (!$contract->branch_id) {
                $contract->branch_id = $this->resolveContractBranchId($validated, $request);
            }
            $contract->reservation_id = $reservation?->id;
            $this->fillContract($contract, $validated, $reservation);
            $this->syncAiStatus($contract, $request, false);
            $contract->save();

            $this->syncFiles($contract, $request, 'start_contract');
            $this->syncFiles($contract, $request, 'end_contract');
            $this->syncContractDrivers($contract, $validated, $reservation);
            $this->syncAdditionalArchiveFiles($contract, $validated);
            $this->completeWaitingReservationIfNeeded($reservation);
        });

        return redirect()
            ->route('admin.contracts.show', [
                'subdomain' => $request->route('subdomain'),
                'contract' => $contract->id,
            ])
            ->with('success', 'Contract updated successfully.');
    }

    public function requestExtension(Request $request): RedirectResponse
    {
        $routeContract = $request->route('contract');
        $contractId = $routeContract instanceof Contract
            ? (int) $routeContract->getKey()
            : (int) $routeContract;
        if ($contractId <= 0) {
            abort(404);
        }

        $contract = Contract::withoutGlobalScope('tenant')
            ->with(['reservation.user:id,name,email', 'reservation.car:id,make,model,year,license_plate,price_per_day', 'extensionRequests'])
            ->findOrFail($contractId);

        abort_unless($this->canAccessContract($contract, $request->user()), 403);
        $this->abortIfContractLocked($contract);

        if (!$this->isExtendableContract($contract)) {
            throw ValidationException::withMessages([
                'new_end_date' => 'Only active contracts can be extended.',
            ]);
        }

        if ($contract->extensionRequests->contains(fn (RentalExtensionRequest $request) => $request->status === \App\Enums\RentalExtensionRequestStatus::PENDING)) {
            throw ValidationException::withMessages([
                'new_end_date' => 'There is already a pending extension request for this contract.',
            ]);
        }

        $validated = $request->validate([
            'new_end_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        [$extraDays, $dailyRate, $extraAmount] = $this->calculateExtensionPreview($contract, $validated['new_end_date']);
        $notes = trim((string) ($validated['notes'] ?? ''));

        $extensionRequest = DB::transaction(function () use ($contract, $validated, $extraDays, $extraAmount, $notes, $request): RentalExtensionRequest {
            $extensionRequest = RentalExtensionRequest::create([
                'tenant_id' => $contract->tenant_id,
                'contract_id' => $contract->id,
                'reservation_id' => $contract->reservation_id,
                'requested_by_admin_id' => $request->user()?->id,
                'new_end_date' => $validated['new_end_date'],
                'extra_days' => $extraDays,
                'extra_amount' => number_format($extraAmount, 2, '.', ''),
                'reason' => $notes !== '' ? $notes : null,
                'status' => \App\Enums\RentalExtensionRequestStatus::PENDING,
            ]);

            if ($contract->reservation?->user) {
                $contract->reservation->user->notify(new ContractExtensionRequestedNotification($extensionRequest->load(['contract.reservation.user', 'contract.reservation.car'])));
            }

            return $extensionRequest;
        });

        return redirect()
            ->route('admin.contracts.show', [
                'subdomain' => $request->route('subdomain'),
                'contract' => $contract->id,
            ])
            ->with('success', sprintf(
                'Extension request sent for %d day(s).',
                $extensionRequest->extra_days
            ));
    }

    public function extend(Request $request): RedirectResponse
    {
        $routeContract = $request->route('contract');
        $contractId = $routeContract instanceof Contract
            ? (int) $routeContract->getKey()
            : (int) $routeContract;
        if ($contractId <= 0) {
            abort(404);
        }

        $contract = Contract::withoutGlobalScope('tenant')
            ->with(['reservation.user:id,name,email', 'reservation.car:id,price_per_day'])
            ->findOrFail($contractId);

        abort_unless($this->canAccessContract($contract, $request->user()), 403);
        $this->abortIfContractLocked($contract);

        if (!$this->isExtendableContract($contract)) {
            throw ValidationException::withMessages([
                'new_end_date' => 'Only active contracts can be extended.',
            ]);
        }

        if (!$contract->reservation) {
            throw ValidationException::withMessages([
                'new_end_date' => 'This contract is not linked to a reservation.',
            ]);
        }

        $validated = $request->validate([
            'new_end_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        [$extraDays, $dailyRate, $extraAmount, $currentEndDate, $newEndDateString] = $this->calculateExtensionPreviewData($contract, $validated['new_end_date']);
        $notes = trim((string) ($validated['notes'] ?? ''));

        DB::transaction(function () use ($contract, $newEndDateString, $extraAmount, $notes, $extraDays, $dailyRate): void {
            $reservation = $contract->reservation;
            $baseReservationTotal = (float) ($reservation?->total_amount ?? $contract->total_amount ?? 0);
            $baseContractTotal = (float) ($contract->total_amount ?? $reservation?->total_amount ?? 0);

            $contract->end_date = $newEndDateString;
            $contract->total_amount = round($baseContractTotal + $extraAmount, 2);
            $contract->save();

            if ($reservation) {
                $reservation->end_date = $newEndDateString;
                $reservation->total_days = $reservation->start_date
                    ? $reservation->start_date->diffInDays(CarbonImmutable::parse($newEndDateString)) + 1
                    : $reservation->total_days;
                $reservation->subtotal = round((float) ($reservation->subtotal ?? 0) + $extraAmount, 2);
                $reservation->total_amount = round($baseReservationTotal + $extraAmount, 2);
                $reservation->save();

                $paymentNotes = sprintf(
                    'Rental extension payment recorded from contract extension. %s day(s) added at %s %s per day.%s',
                    $extraDays,
                    strtoupper((string) ($contract->currency ?: config('app.currency_code', 'USD'))),
                    number_format($dailyRate, 2, '.', ','),
                    $notes !== '' ? ' Notes: '.$notes : ''
                );

                Payment::create([
                    'tenant_id' => $contract->tenant_id,
                    'reservation_id' => $reservation->id,
                    'user_id' => $reservation->user_id,
                    'amount' => number_format($extraAmount, 2, '.', ''),
                    'currency' => strtoupper((string) ($contract->currency ?: config('app.currency_code', 'USD'))),
                    'payment_method' => PaymentMethod::CASH,
                    'status' => PaymentStatus::COMPLETED,
                    'notes' => $paymentNotes,
                    'processed_at' => now(),
                ]);
            }
        });

        if ($contract->reservation?->user) {
            $contract->reservation->user->notify(
                new ContractForceExtendedNotification(
                    $contract,
                    $extraDays,
                    $extraAmount,
                    $notes
                )
            );
        }

        return redirect()
            ->route('admin.contracts.show', [
                'subdomain' => $request->route('subdomain'),
                'contract' => $contract->id,
            ])
            ->with('success', sprintf(
                'Rental force extended by %d day(s).',
                $extraDays
            ));
    }

    private function abortIfContractLocked(Contract $contract): void
    {
        if (PaidReturnReportLock::contract($contract)) {
            abort(423, 'This contract is locked because the return report is paid.');
        }
    }

    /**
     * @return array{0:int,1:float,2:float,3:?CarbonImmutable,4:string}
     */
    private function calculateExtensionPreviewData(Contract $contract, string $newEndDate): array
    {
        $currentEndDate = $contract->end_date
            ? CarbonImmutable::parse($contract->end_date->toDateString())
            : null;
        if (!$currentEndDate) {
            throw ValidationException::withMessages([
                'new_end_date' => 'This contract does not have an end date to extend from.',
            ]);
        }

        $newEndDateCarbon = CarbonImmutable::parse($newEndDate)->startOfDay();
        if ($newEndDateCarbon->lessThanOrEqualTo($currentEndDate)) {
            throw ValidationException::withMessages([
                'new_end_date' => 'The new end date must be after the current end date.',
            ]);
        }

        $dailyRate = $this->resolveContractDailyRate($contract);
        if ($dailyRate === null || $dailyRate <= 0) {
            throw ValidationException::withMessages([
                'new_end_date' => 'Unable to determine the daily rate for this contract.',
            ]);
        }

        $extraDays = $currentEndDate->diffInDays($newEndDateCarbon);
        if ($extraDays <= 0) {
            throw ValidationException::withMessages([
                'new_end_date' => 'The selected extension must add at least one day.',
            ]);
        }

        return [
            $extraDays,
            $dailyRate,
            round($extraDays * $dailyRate, 2),
            $currentEndDate,
            $newEndDateCarbon->toDateString(),
        ];
    }

    /**
     * @return array{0:int,1:float,2:float}
     */
    private function calculateExtensionPreview(Contract $contract, string $newEndDate): array
    {
        [$extraDays, $dailyRate, $extraAmount] = $this->calculateExtensionPreviewData($contract, $newEndDate);

        return [$extraDays, $dailyRate, $extraAmount];
    }

    private function validatePayload(Request $request, int $tenantId, ?int $ignoreId = null, ?Contract $contract = null): array
    {
        $isCreate = $contract === null;
        $uniqueRule = Rule::unique('contracts', 'contract_number')
            ->where(fn ($query) => $query->where('tenant_id', $tenantId));

        if ($ignoreId) {
            $uniqueRule->ignore($ignoreId);
        }

        $requiredOnCreate = $isCreate ? 'required' : 'nullable';

        $contractDateRules = [$requiredOnCreate, 'date'];
        if ($isCreate) {
            $contractDateRules[] = 'after_or_equal:today';
        }

        $validator = Validator::make($request->all(), [
            'reservation_id' => [$requiredOnCreate, 'integer', 'exists:reservations,id'],
            'contract_number' => ['nullable', 'string', 'max:100', $uniqueRule],
            'status' => ['required', Rule::in(['draft', 'active', 'completed', 'cancelled'])],
            'contract_date' => $contractDateRules,
            'renter_name' => [$requiredOnCreate, 'string', 'max:255', new LettersOnly()],
            'renter_id_number' => [$requiredOnCreate, 'string', 'max:255', new DigitsOnly()],
            'renter_phone' => [$requiredOnCreate, 'string', 'max:100'],
            'car_details' => ['nullable', 'string', 'max:255'],
            'plate_number' => ['nullable', 'string', 'max:255'],
            'vehicle_odometer' => ['nullable', 'integer', 'min:0'],
            'vehicle_fuel_level' => ['nullable', 'string', 'max:50'],
            'price_per_day' => ['nullable', 'numeric', 'min:0'],
            'price_per_week' => ['nullable', 'numeric', 'min:0'],
            'price_per_month' => ['nullable', 'numeric', 'min:0'],
            'allowed_km_per_day' => ['nullable', 'integer', 'min:0'],
            'allowed_km_per_week' => ['nullable', 'integer', 'min:0'],
            'allowed_km_per_month' => ['nullable', 'integer', 'min:0'],
            'return_odometer' => ['nullable', 'integer', 'min:0'],
            'return_fuel_level' => ['nullable', 'string', 'max:50'],
            'vehicle_condition_before' => ['nullable', Rule::in(['clean', 'not_clean'])],
            'vehicle_condition_after' => ['nullable', Rule::in(['clean', 'not_clean'])],
            'actual_return_time' => ['nullable', 'date'],
            'start_date' => [$requiredOnCreate, 'date'],
            'end_date' => [$requiredOnCreate, 'date', 'after:start_date'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'ai_extracted_data' => ['nullable', 'array'],
            'car_data' => ['nullable', 'array'],
            'car_data.car_id' => ['nullable', 'integer', 'exists:cars,id'],
            'car_data.car_details' => ['nullable', 'string', 'max:255'],
            'car_data.plate_number' => ['nullable', 'string', 'max:255'],
            'car_data.vehicle_odometer' => ['nullable', 'integer', 'min:0'],
            'car_data.vehicle_fuel_level' => ['nullable', 'string', 'max:50'],
            'car_data.price_per_day' => ['nullable', 'numeric', 'min:0'],
            'car_data.price_per_week' => ['nullable', 'numeric', 'min:0'],
            'car_data.price_per_month' => ['nullable', 'numeric', 'min:0'],
            'car_data.allowed_km_per_day' => ['nullable', 'integer', 'min:0'],
            'car_data.allowed_km_per_week' => ['nullable', 'integer', 'min:0'],
            'car_data.allowed_km_per_month' => ['nullable', 'integer', 'min:0'],
            'car_data.branch_id' => ['nullable', 'integer', 'exists:branches,id'],

            'primary_driver' => ['nullable', 'array'],
            'primary_driver.id' => ['nullable', 'integer'],
            'primary_driver.client_id' => ['nullable', 'integer', 'exists:users,id'],
            'primary_driver.role' => ['nullable', Rule::in(['primary'])],
            'primary_driver.full_name' => [$requiredOnCreate, 'string', 'max:255', new LettersOnly()],
            'primary_driver.full_name_ar' => ['nullable', 'string', 'max:255', new LettersOnly()],
            'primary_driver.phone' => [$requiredOnCreate, 'string', 'max:100'],
            'primary_driver.nationality' => ['nullable', 'string', 'max:100'],
            'primary_driver.place_of_issue' => ['nullable', 'string', 'max:255'],
            'primary_driver.date_of_birth' => ['nullable', 'date'],
            'primary_driver.identity_number' => [$requiredOnCreate, 'string', 'max:255', new DigitsOnly()],
            'primary_driver.passport_number' => ['nullable', 'string', 'max:255'],
            'primary_driver.passport_expiry_date' => ['nullable', 'date'],
            'primary_driver.visa_number' => ['nullable', 'string', 'max:255'],
            'primary_driver.visa_expiry_date' => ['nullable', 'date'],
            'primary_driver.residency_number' => ['nullable', 'string', 'max:255', new DigitsOnly()],
            'primary_driver.license_number' => ['nullable', 'string', 'max:255'],
            'primary_driver.license_issue_date' => ['nullable', 'date'],
            'primary_driver.identity_expiry_date' => ['nullable', 'date'],
            'primary_driver.license_expiry_date' => ['nullable', 'date'],
            'primary_driver.extraction_status' => ['nullable', 'string', 'max:50'],
            'primary_driver.extracted_data' => ['nullable', 'array'],
            'primary_driver.raw_output' => ['nullable', 'array'],
            'primary_driver.confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'primary_driver.ai_reviewed' => ['nullable', 'boolean'],
            'primary_driver.notes' => ['nullable', 'string'],
            'primary_driver.document_type' => ['nullable', 'string', 'max:100'],
            'primary_driver.temp_folders' => ['array'],
            'primary_driver.temp_folders.*' => ['string'],
            'primary_driver.removed_file_ids' => ['array'],
            'primary_driver.removed_file_ids.*' => ['integer'],
            'primary_driver.documents' => ['array'],
            'primary_driver.documents.*.id' => ['nullable', 'integer'],
            'primary_driver.documents.*.document_type' => ['nullable', 'string', 'max:100'],
            'primary_driver.documents.*.side' => ['nullable', Rule::in(['front', 'back', 'single'])],
            'primary_driver.documents.*.temp_folders' => ['array'],
            'primary_driver.documents.*.temp_folders.*' => ['string'],
            'primary_driver.documents.*.removed_file_ids' => ['array'],
            'primary_driver.documents.*.removed_file_ids.*' => ['integer'],
            'primary_driver.customer_photo_temp_folders' => ['array'],
            'primary_driver.customer_photo_temp_folders.*' => ['string'],
            'primary_driver.customer_photo_removed_file_ids' => ['array'],
            'primary_driver.customer_photo_removed_file_ids.*' => ['integer'],

            'additional_drivers' => ['array'],
            'additional_drivers.*.id' => ['nullable', 'integer'],
            'additional_drivers.*.client_id' => ['nullable', 'integer', 'exists:users,id'],
            'additional_drivers.*.role' => ['nullable', Rule::in(['additional'])],
            'additional_drivers.*.full_name' => ['nullable', 'string', 'max:255', new LettersOnly()],
            'additional_drivers.*.full_name_ar' => ['nullable', 'string', 'max:255', new LettersOnly()],
            'additional_drivers.*.phone' => ['nullable', 'string', 'max:100'],
            'additional_drivers.*.nationality' => ['nullable', 'string', 'max:100'],
            'additional_drivers.*.place_of_issue' => ['nullable', 'string', 'max:255'],
            'additional_drivers.*.date_of_birth' => ['nullable', 'date'],
            'additional_drivers.*.identity_number' => ['nullable', 'string', 'max:255', new DigitsOnly()],
            'additional_drivers.*.passport_number' => ['nullable', 'string', 'max:255'],
            'additional_drivers.*.passport_expiry_date' => ['nullable', 'date'],
            'additional_drivers.*.visa_number' => ['nullable', 'string', 'max:255'],
            'additional_drivers.*.visa_expiry_date' => ['nullable', 'date'],
            'additional_drivers.*.residency_number' => ['nullable', 'string', 'max:255', new DigitsOnly()],
            'additional_drivers.*.license_number' => ['nullable', 'string', 'max:255'],
            'additional_drivers.*.license_issue_date' => ['nullable', 'date'],
            'additional_drivers.*.identity_expiry_date' => ['nullable', 'date'],
            'additional_drivers.*.license_expiry_date' => ['nullable', 'date'],
            'additional_drivers.*.extraction_status' => ['nullable', 'string', 'max:50'],
            'additional_drivers.*.extracted_data' => ['nullable', 'array'],
            'additional_drivers.*.raw_output' => ['nullable', 'array'],
            'additional_drivers.*.confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'additional_drivers.*.ai_reviewed' => ['nullable', 'boolean'],
            'additional_drivers.*.notes' => ['nullable', 'string'],
            'additional_drivers.*.document_type' => ['nullable', 'string', 'max:100'],
            'additional_drivers.*.temp_folders' => ['array'],
            'additional_drivers.*.temp_folders.*' => ['string'],
            'additional_drivers.*.removed_file_ids' => ['array'],
            'additional_drivers.*.removed_file_ids.*' => ['integer'],
            'additional_drivers.*.documents' => ['array'],
            'additional_drivers.*.documents.*.id' => ['nullable', 'integer'],
            'additional_drivers.*.documents.*.document_type' => ['nullable', 'string', 'max:100'],
            'additional_drivers.*.documents.*.side' => ['nullable', Rule::in(['front', 'back', 'single'])],
            'additional_drivers.*.documents.*.temp_folders' => ['array'],
            'additional_drivers.*.documents.*.temp_folders.*' => ['string'],
            'additional_drivers.*.documents.*.removed_file_ids' => ['array'],
            'additional_drivers.*.documents.*.removed_file_ids.*' => ['integer'],

            'contract_archive' => ['nullable', 'array'],
            'contract_archive.temp_folders' => ['array'],
            'contract_archive.temp_folders.*' => ['string'],
            'contract_archive.removed_file_ids' => ['array'],
            'contract_archive.removed_file_ids.*' => ['integer'],

            'additional_archive' => ['array'],
            'additional_archive.*.id' => ['nullable', 'integer'],
            'additional_archive.*.owner_key' => ['nullable', 'string', 'max:100'],
            'additional_archive.*.document_type' => ['nullable', 'string', 'max:100'],
            'additional_archive.*.title' => ['nullable', 'string', 'max:255'],
            'additional_archive.*.notes' => ['nullable', 'string'],
            'additional_archive.*.temp_folders' => ['array'],
            'additional_archive.*.temp_folders.*' => ['string'],
            'additional_archive.*.removed_file_ids' => ['array'],
            'additional_archive.*.removed_file_ids.*' => ['integer'],
            'additional_archive_removed_ids' => ['array'],
            'additional_archive_removed_ids.*' => ['integer'],

            'start_contract_temp_folders' => ['array'],
            'start_contract_temp_folders.*' => ['string'],
            'start_contract_removed_files' => ['array'],
            'start_contract_removed_files.*' => ['integer'],
            'end_contract_temp_folders' => ['array'],
            'end_contract_temp_folders.*' => ['string'],
            'end_contract_removed_files' => ['array'],
            'end_contract_removed_files.*' => ['integer'],
        ]);

        $validated = $validator->validate();
        $this->ensureExtractedDriversAreReviewed($validated);
        $this->ensureAdditionalArchiveDoesNotReuseDriverDocuments($validated, $contract);

        return $validated;
    }

    private function fillContract(Contract $contract, array $validated, ?Reservation $reservation): void
    {
        $carData = is_array($validated['car_data'] ?? null) ? $validated['car_data'] : [];
        $primaryDriver = $this->resolvedPrimaryDriverInput($validated, $reservation);

        $contract->contract_number = $this->nullableString($validated['contract_number'] ?? null)
            ?? $contract->contract_number
            ?? $this->generateContractNumber();
        $contract->status = (string) $validated['status'];
        $contract->contract_date = $validated['contract_date'] ?? null;
        $contract->renter_name = $this->nullableString($primaryDriver['full_name'] ?? null)
            ?? $this->nullableString($validated['renter_name'] ?? null)
            ?? $reservation?->user?->name;
        $contract->renter_id_number = $this->nullableString($primaryDriver['identity_number'] ?? null)
            ?? $this->nullableString($primaryDriver['residency_number'] ?? null)
            ?? $this->nullableString($validated['renter_id_number'] ?? null);
        $contract->renter_phone = $this->nullableString($primaryDriver['phone'] ?? null)
            ?? $this->nullableString($validated['renter_phone'] ?? null);
        $contract->car_details = $this->nullableString($carData['car_details'] ?? null)
            ?? $this->nullableString($validated['car_details'] ?? null)
            ?? ($reservation?->car ? "{$reservation->car->year} {$reservation->car->make} {$reservation->car->model}" : null);
        $contract->plate_number = $this->nullableString($carData['plate_number'] ?? null)
            ?? $this->nullableString($validated['plate_number'] ?? null)
            ?? $reservation?->car?->license_plate;
        $contract->vehicle_odometer = $carData['vehicle_odometer']
            ?? $validated['vehicle_odometer']
            ?? $reservation?->car?->mileage;
        $contract->vehicle_fuel_level = $this->nullableString($carData['vehicle_fuel_level'] ?? null)
            ?? $this->nullableString($validated['vehicle_fuel_level'] ?? null);
        $contract->price_per_day = $carData['price_per_day']
            ?? $validated['price_per_day']
            ?? $reservation?->car?->price_per_day;
        $contract->price_per_week = $carData['price_per_week']
            ?? $validated['price_per_week']
            ?? $reservation?->car?->price_per_week;
        $contract->price_per_month = $carData['price_per_month']
            ?? $validated['price_per_month']
            ?? $reservation?->car?->price_per_month;
        $contract->allowed_km_per_day = $carData['allowed_km_per_day']
            ?? $validated['allowed_km_per_day']
            ?? $reservation?->car?->allowed_km_per_day;
        $contract->allowed_km_per_week = $carData['allowed_km_per_week']
            ?? $validated['allowed_km_per_week']
            ?? $reservation?->car?->allowed_km_per_week;
        $contract->allowed_km_per_month = $carData['allowed_km_per_month']
            ?? $validated['allowed_km_per_month']
            ?? $reservation?->car?->allowed_km_per_month;
        $contract->return_odometer = $validated['return_odometer'] ?? null;
        $contract->return_fuel_level = $this->nullableString($validated['return_fuel_level'] ?? null);
        $contract->vehicle_condition_before = $this->nullableString($validated['vehicle_condition_before'] ?? null);
        $contract->vehicle_condition_after = $this->nullableString($validated['vehicle_condition_after'] ?? null);
        $contract->actual_return_time = $validated['actual_return_time'] ?? null;
        $contract->start_date = $validated['start_date'] ?? $reservation?->start_date?->toDateString();
        $contract->end_date = $validated['end_date'] ?? $reservation?->end_date?->toDateString();
        $contract->total_amount = $validated['total_amount'] ?? $reservation?->total_amount;
        $contract->currency = strtoupper((string) ($validated['currency'] ?? 'USD'));
        $contract->notes = $this->nullableString($validated['notes'] ?? null);
        if (array_key_exists('ai_extracted_data', $validated)) {
            $contract->ai_extracted_data = is_array($validated['ai_extracted_data']) && !empty($validated['ai_extracted_data'])
                ? $validated['ai_extracted_data']
                : null;
        }
    }

    private function syncAiStatus(Contract $contract, Request $request, bool $isCreate): void
    {
        $aiEnabled = AiAutomationSettings::isContractsExtractionEnabled()
            && AiProviderSettings::isConfiguredForCurrentProvider();
        $hasNewUploads = !empty($request->input('start_contract_temp_folders', []))
            || !empty($request->input('end_contract_temp_folders', []));

        if (!$aiEnabled) {
            $contract->ai_extraction_status = 'disabled';
            $contract->ai_extracted_data = null;
            return;
        }

        if ($hasNewUploads) {
            $contract->ai_extraction_status = 'pending';
            return;
        }

        if ($isCreate) {
            $contract->ai_extraction_status = 'not_requested';
            return;
        }

        if (empty($contract->ai_extraction_status)) {
            $contract->ai_extraction_status = 'not_requested';
        }
    }

    private function validatedReservation(?int $reservationId, Request $request, ?int $currentContractId): ?Reservation
    {
        if (!$reservationId) {
            return null;
        }

        $reservation = Reservation::query()
            ->with(['user:id,name,email', 'car:id,branch_id,make,model,year,license_plate,mileage,price_per_day,price_per_week,price_per_month,allowed_km_per_day,allowed_km_per_week,allowed_km_per_month'])
            ->find($reservationId);

        if (!$reservation) {
            return null;
        }

        $this->ensureReservationAccessible($reservation, $request->user(), $currentContractId);

        return $reservation;
    }

    private function reservationOptions(Request $request): array
    {
        $user = $request->user();
        $query = Reservation::query()
            ->with(['user:id,name,email', 'car:id,branch_id,make,model,year,license_plate,mileage,price_per_day,price_per_week,price_per_month,allowed_km_per_day,allowed_km_per_week,allowed_km_per_month', 'contract:id,reservation_id'])
            ->latest('id')
            ->limit(100);

        if ($this->branchAccess->canAccessAllBranches($user)) {
            // no-op
        } else {
            $userBranchId = (int) ($user?->branch_id ?? 0);
            if ($userBranchId <= 0) {
                return [];
            }
            $query->whereHas('car', fn ($carQuery) => $carQuery->where('branch_id', $userBranchId));
        }

        return $query->get()
            ->map(function (Reservation $reservation) {
                $hasContract = (bool) $reservation->contract;
                $status = $reservation->status instanceof ReservationStatus
                    ? $reservation->status
                    : ReservationStatus::tryFrom((string) $reservation->status);
                return [
                    'id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'label' => "{$reservation->reservation_number} - {$reservation->user?->name}",
                    'car' => $reservation->car ? "{$reservation->car->year} {$reservation->car->make} {$reservation->car->model}" : null,
                    'car_id' => $reservation->car?->id,
                    'car_details' => $reservation->car ? "{$reservation->car->year} {$reservation->car->make} {$reservation->car->model}" : null,
                    'plate_number' => $reservation->car?->license_plate,
                    'vehicle_odometer' => $reservation->car?->mileage,
                    'price_per_day' => $reservation->car?->price_per_day,
                    'price_per_week' => $reservation->car?->price_per_week,
                    'price_per_month' => $reservation->car?->price_per_month,
                    'allowed_km_per_day' => $reservation->car?->allowed_km_per_day,
                    'allowed_km_per_week' => $reservation->car?->allowed_km_per_week,
                    'allowed_km_per_month' => $reservation->car?->allowed_km_per_month,
                    'branch_id' => $reservation->car?->branch_id,
                    'user_id' => $reservation->user?->id,
                    'user_name' => $reservation->user?->name,
                    'start_date' => optional($reservation->start_date)->toDateString(),
                    'end_date' => optional($reservation->end_date)->toDateString(),
                    'total_amount' => $reservation->total_amount,
                    'has_contract' => $hasContract,
                    'status' => $status?->value ?? (string) $reservation->status,
                    'status_label' => $status?->label() ?? ucfirst(str_replace('_', ' ', (string) $reservation->status)),
                    'status_color' => $status?->color() ?? '#6B7280',
                ];
            })
            ->values()
            ->all();
    }

    private function reservationClientOptions(Request $request): array
    {
        return User::query()
            ->where('tenant_id', $request->user()?->tenant_id)
            ->where('role', 'client')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
            ])
            ->values()
            ->all();
    }

    private function reservationCarOptions(Request $request): array
    {
        $query = Car::query()
            ->where('tenant_id', $request->user()?->tenant_id)
            ->with('branch:id,name')
            ->orderBy('make')
            ->orderBy('model');

        if (!$this->branchAccess->canAccessAllBranches($request->user())) {
            $userBranchId = (int) ($request->user()?->branch_id ?? 0);
            if ($userBranchId <= 0) {
                return [];
            }

            $query->where('branch_id', $userBranchId);
        }

        return $query->get(['id', 'branch_id', 'make', 'model', 'year', 'license_plate', 'price_per_day', 'price_per_week', 'price_per_month', 'allowed_km_per_day', 'allowed_km_per_week', 'allowed_km_per_month'])
            ->map(fn (Car $car) => [
                'id' => $car->id,
                'label' => sprintf('%s %s %s', $car->year, $car->make, $car->model),
                'license_plate' => $car->license_plate,
                'branch_name' => $car->branch?->name,
                'price_per_day' => (float) $car->price_per_day,
                'price_per_week' => $car->price_per_week !== null ? (float) $car->price_per_week : null,
                'price_per_month' => $car->price_per_month !== null ? (float) $car->price_per_month : null,
                'allowed_km_per_day' => $car->allowed_km_per_day,
                'allowed_km_per_week' => $car->allowed_km_per_week,
                'allowed_km_per_month' => $car->allowed_km_per_month,
            ])
            ->values()
            ->all();
    }

    private function prefillFromReservation(Reservation $reservation): array
    {
        return [
            'reservation_id' => $reservation->id,
            'contract_number' => $this->generateContractNumber(),
            'status' => 'draft',
            'contract_date' => now()->toDateString(),
            'renter_name' => $reservation->user?->name,
            'renter_id_number' => null,
            'renter_phone' => null,
            'car_details' => $reservation->car ? "{$reservation->car->year} {$reservation->car->make} {$reservation->car->model}" : null,
            'plate_number' => $reservation->car?->license_plate,
            'vehicle_odometer' => $reservation->car?->mileage,
            'vehicle_fuel_level' => null,
            'price_per_day' => $reservation->car?->price_per_day,
            'price_per_week' => $reservation->car?->price_per_week,
            'price_per_month' => $reservation->car?->price_per_month,
            'allowed_km_per_day' => $reservation->car?->allowed_km_per_day,
            'allowed_km_per_week' => $reservation->car?->allowed_km_per_week,
            'allowed_km_per_month' => $reservation->car?->allowed_km_per_month,
            'return_odometer' => null,
            'return_fuel_level' => null,
            'actual_return_time' => null,
            'start_date' => optional($reservation->start_date)->toDateString(),
            'end_date' => optional($reservation->end_date)->toDateString(),
            'total_amount' => $reservation->total_amount,
            'currency' => strtoupper((string) config('app.currency_code', 'USD')),
            'notes' => null,
            'ai_extracted_data' => null,
            'ai_extraction_status' => AiAutomationSettings::isContractsExtractionEnabled() ? 'not_requested' : 'disabled',
            'primary_driver' => $this->prefillPrimaryDriverFromReservation($reservation),
            'additional_drivers' => [],
            'car_data' => $this->prefillCarDataFromReservation($reservation),
            'contract_archive' => $this->emptyContractArchivePayload(),
        ];
    }

    private function generateContractNumber(): string
    {
        $tenantId = (int) (TenantContext::id() ?? 0);
        $datePrefix = now()->format('Ymd');

        $latest = Contract::query()
            ->when($tenantId > 0, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('contract_number', 'like', "CTR-{$datePrefix}-%")
            ->latest('id')
            ->value('contract_number');

        $nextSequence = 1;
        if (is_string($latest) && preg_match('/CTR-\d{8}-(\d{4})$/', $latest, $matches)) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        return sprintf('CTR-%s-%04d', $datePrefix, $nextSequence);
    }

    private function ensureReservationAccessible(Reservation $reservation, $user, ?int $currentContractId): void
    {
        $reservation->loadMissing('car:id,branch_id', 'contract:id,reservation_id');

        if ($reservation->contract && (int) $reservation->contract->id !== (int) $currentContractId) {
            throw ValidationException::withMessages([
                'reservation_id' => 'This reservation already has a contract.',
            ]);
        }

        $canAccess = $this->branchAccess->canAccessBranchId(
            $user,
            $reservation->car?->branch_id ? (int) $reservation->car->branch_id : null
        );

        if (!$canAccess) {
            abort(403);
        }
    }

    private function applyContractBranchScope($query, $user, ?int $branchId): void
    {
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

        if ($canAccessAllBranches) {
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where('branch_id', $userBranchId);
    }

    private function canAccessContract(Contract $contract, $user): bool
    {
        return $this->branchAccess->canAccessBranchId(
            $user,
            $contract->branch_id ? (int) $contract->branch_id : null
        );
    }

    private function isExtendableContract(Contract $contract): bool
    {
        return !empty($contract->reservation_id);
    }

    private function resolveContractDailyRate(Contract $contract): ?float
    {
        $rate = $contract->price_per_day;
        if ($rate !== null && (float) $rate > 0) {
            return (float) $rate;
        }

        $contract->loadMissing('reservation.car:id,price_per_day');

        $reservationRate = $contract->reservation?->daily_rate;
        if ($reservationRate !== null && (float) $reservationRate > 0) {
            return (float) $reservationRate;
        }

        $carRate = $contract->reservation?->car?->price_per_day;
        if ($carRate !== null && (float) $carRate > 0) {
            return (float) $carRate;
        }

        return null;
    }

    private function syncFiles(Contract $contract, Request $request, string $collection): void
    {
        $tempFolders = $request->input("{$collection}_temp_folders", []);
        $removedIds = $request->input("{$collection}_removed_files", []);

        $tempFolders = is_array($tempFolders) ? $tempFolders : [];
        $removedIds = is_array($removedIds) ? $removedIds : [];

        if (!empty($tempFolders)) {
            $existingIds = $contract->files()->where('collection', $collection)->pluck('id')->all();
            $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
        }

        $this->filePondService->handleFileUpdates(
            $contract,
            $tempFolders,
            $removedIds,
            $collection
        );
    }

    private function collectionFiles(Contract $contract, string $collection): array
    {
        $files = $contract->relationLoaded('files')
            ? $contract->files->where('collection', $collection)->values()
            : $contract->files()->where('collection', $collection)->get();

        return $files->map(fn ($file) => [
            'id' => $file->id,
            'url' => $this->storageUrl($file->path),
        ])->values()->all();
    }

    private function firstFileMeta(Contract $contract, string $collection): ?array
    {
        $file = $contract->relationLoaded('files')
            ? $contract->files->firstWhere('collection', $collection)
            : $contract->files()->where('collection', $collection)->first();

        if (!$file || !$file->path) {
            return null;
        }

        return [
            'id' => $file->id,
            'name' => basename((string) $file->path),
            'url' => $this->storageUrl($file->path),
        ];
    }

    private function storageUrl(?string $path): ?string
    {
        $path = trim((string) ($path ?? ''));
        if ($path === '') {
            return null;
        }

        $normalized = ltrim($path, '/');
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        return Storage::url($normalized);
    }

    private function resolveContractBranchId(array $validated, Request $request): ?int
    {
        $carData = is_array($validated['car_data'] ?? null) ? $validated['car_data'] : [];
        $branchId = isset($carData['branch_id']) ? (int) $carData['branch_id'] : null;

        if ($branchId) {
            return $this->branchAccess->resolveWritableBranchId($request->user(), $branchId);
        }

        return $this->branchAccess->resolveWritableBranchId($request->user(), null);
    }

    private function resolvedPrimaryDriverInput(array $validated, ?Reservation $reservation): array
    {
        $payload = is_array($validated['primary_driver'] ?? null) ? $validated['primary_driver'] : [];

        return array_merge($this->emptyDriverPayload('primary'), [
            'id' => $payload['id'] ?? null,
            'client_id' => $payload['client_id'] ?? null,
            'full_name' => $payload['full_name'] ?? $validated['renter_name'] ?? $reservation?->user?->name,
            'full_name_ar' => $payload['full_name_ar'] ?? null,
            'phone' => $payload['phone'] ?? $validated['renter_phone'] ?? null,
            'nationality' => $payload['nationality'] ?? null,
            'place_of_issue' => $payload['place_of_issue'] ?? null,
            'date_of_birth' => $payload['date_of_birth'] ?? null,
            'identity_number' => $payload['identity_number'] ?? $validated['renter_id_number'] ?? null,
            'passport_number' => $payload['passport_number'] ?? null,
            'passport_expiry_date' => $payload['passport_expiry_date'] ?? null,
            'visa_number' => $payload['visa_number'] ?? null,
            'visa_expiry_date' => $payload['visa_expiry_date'] ?? null,
            'residency_number' => $payload['residency_number'] ?? null,
            'license_number' => $payload['license_number'] ?? null,
            'license_issue_date' => $payload['license_issue_date'] ?? null,
            'identity_expiry_date' => $payload['identity_expiry_date'] ?? null,
            'license_expiry_date' => $payload['license_expiry_date'] ?? null,
            'extraction_status' => $payload['extraction_status'] ?? 'not_requested',
            'extracted_data' => is_array($payload['extracted_data'] ?? null) ? $payload['extracted_data'] : null,
            'raw_output' => is_array($payload['raw_output'] ?? null) ? $payload['raw_output'] : null,
            'confidence' => $payload['confidence'] ?? null,
            'ai_reviewed' => (bool) ($payload['ai_reviewed'] ?? false),
            'notes' => $payload['notes'] ?? null,
            'document_type' => $payload['document_type'] ?? null,
            'temp_folders' => is_array($payload['temp_folders'] ?? null) ? $payload['temp_folders'] : [],
            'removed_file_ids' => is_array($payload['removed_file_ids'] ?? null) ? $payload['removed_file_ids'] : [],
            'documents' => is_array($payload['documents'] ?? null) ? $payload['documents'] : [],
            'customer_photo_temp_folders' => is_array($payload['customer_photo_temp_folders'] ?? null) ? $payload['customer_photo_temp_folders'] : [],
            'customer_photo_removed_file_ids' => is_array($payload['customer_photo_removed_file_ids'] ?? null) ? $payload['customer_photo_removed_file_ids'] : [],
        ]);
    }

    private function syncContractDrivers(Contract $contract, array $validated, ?Reservation $reservation): void
    {
        $primaryPayload = $this->resolvedPrimaryDriverInput($validated, $reservation);
        $primaryDriver = $contract->primaryDriver()->first() ?? new ContractDriver([
            'tenant_id' => $contract->tenant_id,
            'contract_id' => $contract->id,
            'role' => 'primary',
        ]);

        $this->fillDriverModel($primaryDriver, $primaryPayload, 'primary', 0);
        $primaryDriver->save();
        $this->syncDriverDocuments($primaryDriver, $primaryPayload);
        $this->syncDriverCustomerPhoto($primaryDriver, $primaryPayload);

        $keepDriverIds = [$primaryDriver->id];
        $additionalDrivers = is_array($validated['additional_drivers'] ?? null) ? $validated['additional_drivers'] : [];
        $existingAdditional = $contract->additionalDrivers()->with('documents')->get()->keyBy('id');

        foreach ($additionalDrivers as $index => $payload) {
            if (!is_array($payload) || !$this->driverPayloadHasData($payload)) {
                continue;
            }

            $driverId = isset($payload['id']) ? (int) $payload['id'] : 0;
            $driver = $driverId > 0
                ? ($existingAdditional->get($driverId) ?? new ContractDriver([
                    'tenant_id' => $contract->tenant_id,
                    'contract_id' => $contract->id,
                    'role' => 'additional',
                ]))
                : new ContractDriver([
                    'tenant_id' => $contract->tenant_id,
                    'contract_id' => $contract->id,
                    'role' => 'additional',
                ]);

            $this->fillDriverModel($driver, $payload, 'additional', $index);
            $driver->save();
            $this->syncDriverDocuments($driver, $payload);
            $keepDriverIds[] = $driver->id;
        }

        $contract->drivers()
            ->whereNotIn('id', $keepDriverIds)
            ->with('documents')
            ->get()
            ->each(function (ContractDriver $driver) {
                $driver->documents->each(fn (ContractDriverDocument $document) => $document->delete());
                $driver->delete();
            });
    }

    private function fillDriverModel(ContractDriver $driver, array $payload, string $role, int $sortOrder): void
    {
        $clientId = isset($payload['client_id']) && is_numeric($payload['client_id']) ? (int) $payload['client_id'] : null;
        if ($clientId) {
            $client = User::query()->find($clientId);
            if (!$client || (int) $client->tenant_id !== (int) $driver->tenant_id) {
                throw ValidationException::withMessages([
                    $role === 'primary' ? 'primary_driver.client_id' : 'additional_drivers' => 'Selected client is invalid for this tenant.',
                ]);
            }
        }

        $driver->client_id = $clientId ?: null;
        $driver->role = $role;
        $driver->sort_order = $sortOrder;
        $driver->full_name = $this->nullableString($payload['full_name'] ?? null);
        $driver->full_name_ar = $this->nullableString($payload['full_name_ar'] ?? null);
        $driver->phone = $this->nullableString($payload['phone'] ?? null);
        $driver->nationality = $this->nullableString($payload['nationality'] ?? null);
        $driver->place_of_issue = $this->nullableString($payload['place_of_issue'] ?? null);
        $driver->date_of_birth = $payload['date_of_birth'] ?? null;
        $driver->identity_number = $this->nullableString($payload['identity_number'] ?? null);
        $driver->passport_number = $this->nullableString($payload['passport_number'] ?? null);
        $driver->passport_expiry_date = $payload['passport_expiry_date'] ?? null;
        $driver->visa_number = $this->nullableString($payload['visa_number'] ?? null);
        $driver->visa_expiry_date = $payload['visa_expiry_date'] ?? null;
        $driver->residency_number = $this->nullableString($payload['residency_number'] ?? null);
        $driver->license_number = $this->nullableString($payload['license_number'] ?? null);
        $driver->license_issue_date = $payload['license_issue_date'] ?? null;
        $driver->identity_expiry_date = $payload['identity_expiry_date'] ?? null;
        $driver->license_expiry_date = $payload['license_expiry_date'] ?? null;
        $driver->extraction_status = $this->nullableString($payload['extraction_status'] ?? null) ?? 'not_requested';
        $driver->extracted_data = is_array($payload['extracted_data'] ?? null) && !empty($payload['extracted_data']) ? $payload['extracted_data'] : null;
        $driver->raw_output = is_array($payload['raw_output'] ?? null) && !empty($payload['raw_output']) ? $payload['raw_output'] : null;
        $driver->confidence = isset($payload['confidence']) && is_numeric($payload['confidence']) ? (float) $payload['confidence'] : null;
        $driver->ai_reviewed = (bool) ($payload['ai_reviewed'] ?? false);
        $driver->notes = $this->nullableString($payload['notes'] ?? null);
    }

    private function syncDriverDocuments(ContractDriver $driver, array $payload): void
    {
        $documentsInput = $this->normalizedDriverDocumentsInput($payload);
        if ($documentsInput === []) {
            return;
        }

        foreach ($documentsInput as $documentInput) {
            $removedIds = array_map('intval', is_array($documentInput['removed_file_ids'] ?? null) ? $documentInput['removed_file_ids'] : []);
            if ($removedIds !== []) {
                $driver->documents()->whereIn('id', $removedIds)->get()->each->delete();
            }

            $tempFolders = is_array($documentInput['temp_folders'] ?? null) ? array_values(array_filter($documentInput['temp_folders'])) : [];
            foreach ($tempFolders as $index => $folder) {
                $this->moveTempFileToDriverDocument(
                    $driver,
                    (string) $folder,
                    (string) ($documentInput['document_type'] ?? 'document'),
                    $this->resolveDocumentSide($documentInput, $index, count($tempFolders))
                );
            }
        }
    }

    private function syncDriverCustomerPhoto(ContractDriver $driver, array $payload): void
    {
        $removedFileIds = array_map('intval', is_array($payload['customer_photo_removed_file_ids'] ?? null) ? $payload['customer_photo_removed_file_ids'] : []);
        if ($removedFileIds !== []) {
            $this->deleteDriverCustomerPhoto($driver);
        }

        $tempFolders = array_values(array_filter(is_array($payload['customer_photo_temp_folders'] ?? null) ? $payload['customer_photo_temp_folders'] : []));
        if ($tempFolders === []) {
            return;
        }

        $this->replaceDriverCustomerPhotoFromTemp($driver, (string) $tempFolders[0]);
    }

    private function replaceDriverCustomerPhotoFromTemp(ContractDriver $driver, string $folder): void
    {
        $tempFile = TempFile::query()->where('folder', $folder)->first();
        if (! $tempFile) {
            return;
        }

        $disk = Storage::disk(config('vilt-filepond.storage_disk'));
        $extension = pathinfo((string) $tempFile->filename, PATHINFO_EXTENSION);
        $filename = 'contract_driver_photo_'.$driver->id.'_'.Str::uuid().($extension !== '' ? '.'.$extension : '');
        $newPath = config('vilt-filepond.files_path').'/contractdriverphoto/'.$driver->id.'/'.$filename;

        $this->deleteDriverCustomerPhoto($driver);
        $disk->move($tempFile->path, $newPath);

        $driver->customer_photo_path = 'storage/'.$newPath;
        $driver->customer_photo_name = $tempFile->original_name ?: $tempFile->filename;
        $driver->customer_photo_mime_type = $tempFile->mime_type;
        $driver->save();

        $disk->deleteDirectory(config('vilt-filepond.temp_path').'/'.$tempFile->folder);
        $tempFile->delete();
    }

    private function deleteDriverCustomerPhoto(ContractDriver $driver): void
    {
        $existingPath = ltrim((string) preg_replace('/^storage\//', '', (string) $driver->customer_photo_path), '/');
        if ($existingPath !== '') {
            $disk = Storage::disk(config('vilt-filepond.storage_disk'));
            if ($disk->exists($existingPath)) {
                $disk->delete($existingPath);
            }
        }

        $driver->customer_photo_path = null;
        $driver->customer_photo_name = null;
        $driver->customer_photo_mime_type = null;
        $driver->save();
    }

    private function normalizedDriverDocumentsInput(array $payload): array
    {
        $documents = is_array($payload['documents'] ?? null) ? array_values(array_filter($payload['documents'], 'is_array')) : [];
        if ($documents !== []) {
            return $documents;
        }

        $documentType = $this->nullableString($payload['document_type'] ?? null);
        $tempFolders = is_array($payload['temp_folders'] ?? null) ? $payload['temp_folders'] : [];
        $removedFileIds = is_array($payload['removed_file_ids'] ?? null) ? $payload['removed_file_ids'] : [];

        if ($documentType === null && $tempFolders === [] && $removedFileIds === []) {
            return [];
        }

        return [[
            'document_type' => $documentType ?? 'document',
            'temp_folders' => $tempFolders,
            'removed_file_ids' => $removedFileIds,
            'side' => null,
        ]];
    }

    private function resolveDocumentSide(array $documentInput, int $index, int $count): string
    {
        $explicit = $this->nullableString($documentInput['side'] ?? null);
        if ($explicit && in_array($explicit, ['front', 'back', 'single'], true)) {
            return $explicit;
        }

        if ($count === 1) {
            return 'single';
        }

        return $index === 0 ? 'front' : ($index === 1 ? 'back' : 'single');
    }

    private function moveTempFileToDriverDocument(
        ContractDriver $driver,
        string $folder,
        string $documentType,
        string $side
    ): ?ContractDriverDocument {
        $tempFile = TempFile::query()->where('folder', $folder)->first();
        if (!$tempFile) {
            return null;
        }

        $disk = Storage::disk(config('vilt-filepond.storage_disk'));
        $extension = pathinfo((string) $tempFile->filename, PATHINFO_EXTENSION);
        $filename = 'contract_driver_document_'.$driver->id.'_'.Str::uuid().($extension !== '' ? '.'.$extension : '');
        $newPath = config('vilt-filepond.files_path').'/contractdriverdocument/'.$driver->id.'/'.$documentType.'/'.$filename;

        $disk->move($tempFile->path, $newPath);

        $document = ContractDriverDocument::create([
            'tenant_id' => $driver->tenant_id,
            'contract_driver_id' => $driver->id,
            'document_type' => $documentType,
            'side' => $side,
            'file_path' => 'storage/'.$newPath,
            'file_name' => $tempFile->original_name,
            'mime_type' => $tempFile->mime_type,
            'ocr_status' => 'pending',
        ]);

        $disk->deleteDirectory(config('vilt-filepond.temp_path').'/'.$tempFile->folder);
        $tempFile->delete();

        return $document;
    }

    private function driverPayloadHasData(array $payload): bool
    {
        $keys = [
            'client_id',
            'full_name',
            'full_name_ar',
            'phone',
            'nationality',
            'place_of_issue',
            'date_of_birth',
            'identity_number',
            'passport_number',
            'passport_expiry_date',
            'residency_number',
            'license_number',
            'license_issue_date',
            'identity_expiry_date',
            'license_expiry_date',
            'document_type',
        ];

        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_array($value) && !empty($value)) {
                return true;
            }
            if (!is_array($value) && $this->nullableString($value) !== null) {
                return true;
            }
        }

        return !empty($payload['temp_folders']) || !empty($payload['documents']);
    }

    private function ensureExtractedDriversAreReviewed(array $validated): void
    {
        $messages = [];

        $drivers = [];
        if (is_array($validated['primary_driver'] ?? null)) {
            $drivers['primary_driver.ai_reviewed'] = $validated['primary_driver'];
        }

        foreach (is_array($validated['additional_drivers'] ?? null) ? $validated['additional_drivers'] : [] as $index => $driver) {
            if (is_array($driver)) {
                $drivers["additional_drivers.{$index}.ai_reviewed"] = $driver;
            }
        }

        foreach ($drivers as $key => $driver) {
            $hasExtractedData = !empty($driver['extracted_data']) || ($driver['extraction_status'] ?? null) === 'extracted';
            if (!$hasExtractedData) {
                continue;
            }

            if (!((bool) ($driver['ai_reviewed'] ?? false))) {
                $messages[$key] = 'Please review and approve the AI extracted data before saving.';
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function emptyDriverPayload(string $role = 'primary'): array
    {
        return [
            'id' => null,
            'client_id' => null,
            'role' => $role,
            'full_name' => '',
            'full_name_ar' => '',
            'phone' => '',
            'nationality' => '',
            'place_of_issue' => '',
            'date_of_birth' => '',
            'identity_number' => '',
            'passport_number' => '',
            'passport_expiry_date' => '',
            'visa_number' => '',
            'visa_expiry_date' => '',
            'residency_number' => '',
            'license_number' => '',
            'license_issue_date' => '',
            'identity_expiry_date' => '',
            'license_expiry_date' => '',
            'extraction_status' => 'not_requested',
            'extracted_data' => null,
            'raw_output' => null,
            'confidence' => null,
            'ai_reviewed' => false,
            'notes' => '',
            'document_type' => '',
            'temp_folders' => [],
            'removed_file_ids' => [],
            'documents' => [],
            'customer_photo' => null,
            'customer_photo_files' => [],
            'customer_photo_temp_folders' => [],
            'customer_photo_removed_file_ids' => [],
        ];
    }

    private function emptyCarData(): array
    {
        return [
            'car_id' => null,
            'car_details' => '',
            'plate_number' => '',
            'vehicle_odometer' => null,
            'vehicle_fuel_level' => '',
            'price_per_day' => '',
            'price_per_week' => '',
            'price_per_month' => '',
            'allowed_km_per_day' => '',
            'allowed_km_per_week' => '',
            'allowed_km_per_month' => '',
            'branch_id' => null,
        ];
    }
    private function ensureAdditionalArchiveDoesNotReuseDriverDocuments(array $validated, ?Contract $contract = null): void
    {
        $driverHashes = $this->driverDocumentHashes($validated, $contract);
        if ($driverHashes === []) {
            return;
        }

        $messages = [];
        foreach (is_array($validated['additional_archive'] ?? null) ? $validated['additional_archive'] : [] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            foreach (array_values(array_filter(is_array($item['temp_folders'] ?? null) ? $item['temp_folders'] : [])) as $folder) {
                $hash = $this->hashTempFolderFile((string) $folder);
                if ($hash !== null && in_array($hash, $driverHashes, true)) {
                    $messages["additional_archive.$index.temp_folders"] = 'Files already used in the main customer document section cannot be added again to Additional Archive.';
                    break;
                }
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function driverDocumentHashes(array $validated, ?Contract $contract = null): array
    {
        $hashes = [];

        $drivers = [];
        if (is_array($validated['primary_driver'] ?? null)) {
            $drivers[] = $validated['primary_driver'];
        }

        foreach (is_array($validated['additional_drivers'] ?? null) ? $validated['additional_drivers'] : [] as $driver) {
            if (is_array($driver)) {
                $drivers[] = $driver;
            }
        }

        foreach ($drivers as $driverPayload) {
            foreach ($this->normalizedDriverDocumentsInput($driverPayload) as $documentInput) {
                foreach (array_values(array_filter(is_array($documentInput['temp_folders'] ?? null) ? $documentInput['temp_folders'] : [])) as $folder) {
                    $hash = $this->hashTempFolderFile((string) $folder);
                    if ($hash !== null && $hash !== '') {
                        $hashes[$hash] = true;
                    }
                }
            }
        }

        if ($contract) {
            $contract->loadMissing('drivers.documents');
            foreach ($contract->drivers as $driver) {
                foreach ($driver->documents as $document) {
                    $hash = $this->hashStoredFilePath($document->file_path);
                    if ($hash !== null && $hash !== '') {
                        $hashes[$hash] = true;
                    }
                }
            }
        }

        return array_keys($hashes);
    }

    private function hashTempFolderFile(string $folder): ?string
    {
        $tempFile = TempFile::query()->where('folder', $folder)->first();
        if (!$tempFile) {
            return null;
        }

        return $this->hashDiskPath((string) $tempFile->path, false);
    }

    private function hashStoredFilePath(?string $path): ?string
    {
        return $this->hashDiskPath((string) ($path ?? ''), true);
    }

    private function hashDiskPath(string $path, bool $stripStoragePrefix): ?string
    {
        $normalized = ltrim($path, '/');
        if ($stripStoragePrefix && str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        if ($normalized === '') {
            return null;
        }

        try {
            $absolutePath = Storage::disk(config('vilt-filepond.storage_disk'))->path($normalized);
        } catch (\Throwable) {
            return null;
        }

        if (!is_file($absolutePath)) {
            return null;
        }

        $hash = sha1_file($absolutePath);

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    private function syncAdditionalArchiveFiles(Contract $contract, array $validated): void
    {
        $removedIds = array_map('intval', is_array($validated['additional_archive_removed_ids'] ?? null) ? $validated['additional_archive_removed_ids'] : []);
        if ($removedIds !== []) {
            $contract->archiveFiles()->whereIn('id', $removedIds)->get()->each->delete();
        }

        $rows = is_array($validated['additional_archive'] ?? null) ? $validated['additional_archive'] : [];
        if ($rows === []) {
            return;
        }

        $contract->loadMissing('drivers', 'archiveFiles');
        $driverMap = [];
        $primaryDriver = $contract->drivers->firstWhere('role', 'primary');
        if ($primaryDriver) {
            $driverMap['primary'] = $primaryDriver;
        }

        foreach ($contract->drivers->where('role', 'additional') as $driver) {
            $driverMap['additional_'.(int) $driver->sort_order] = $driver;
        }

        foreach ($rows as $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $archiveId = isset($payload['id']) ? (int) $payload['id'] : 0;
            $archiveFile = $archiveId > 0
                ? $contract->archiveFiles()->whereKey($archiveId)->first()
                : null;

            $removedFileIds = array_map('intval', is_array($payload['removed_file_ids'] ?? null) ? $payload['removed_file_ids'] : []);
            if ($archiveFile && in_array($archiveFile->id, $removedFileIds, true)) {
                $archiveFile->delete();
                $archiveFile = null;
            }

            $tempFolders = array_values(array_filter(is_array($payload['temp_folders'] ?? null) ? $payload['temp_folders'] : []));
            if (!$archiveFile && $tempFolders === []) {
                continue;
            }

            if (!$archiveFile) {
                $archiveFile = new ContractArchiveFile([
                    'tenant_id' => $contract->tenant_id,
                    'contract_id' => $contract->id,
                ]);
            }

            $ownerKey = $this->nullableString($payload['owner_key'] ?? null);
            $archiveFile->contract_driver_id = $ownerKey !== null && array_key_exists($ownerKey, $driverMap)
                ? $driverMap[$ownerKey]->id
                : null;
            $archiveFile->document_type = $this->nullableString($payload['document_type'] ?? null);
            $archiveFile->title = $this->nullableString($payload['title'] ?? null);
            $archiveFile->notes = $this->nullableString($payload['notes'] ?? null);

            if ($tempFolders !== []) {
                $this->replaceArchiveFileFromTemp($archiveFile, (string) $tempFolders[0]);
            }

            if ($this->nullableString($archiveFile->file_path) === null) {
                continue;
            }

            $archiveFile->save();
        }
    }

    private function completeWaitingReservationIfNeeded(?Reservation $reservation): void
    {
        if (!$reservation) {
            return;
        }

        if ($reservation->status !== ReservationStatus::COMPLETED_WAIT_CONTRACT) {
            return;
        }

        $reservation->forceFill([
            'status' => ReservationStatus::COMPLETED->value,
        ])->saveQuietly();
    }

    private function replaceArchiveFileFromTemp(ContractArchiveFile $archiveFile, string $folder): void
    {
        $tempFile = TempFile::query()->where('folder', $folder)->first();
        if (!$tempFile) {
            return;
        }

        $disk = Storage::disk(config('vilt-filepond.storage_disk'));
        $extension = pathinfo((string) $tempFile->filename, PATHINFO_EXTENSION);
        $filename = 'contract_archive_'.($archiveFile->contract_id ?: 'new').'_'.Str::uuid().($extension !== '' ? '.'.$extension : '');
        $newPath = config('vilt-filepond.files_path').'/contractarchive/'.($archiveFile->contract_id ?: 'new').'/'.$filename;

        $existingPath = ltrim((string) preg_replace('/^storage\//', '', (string) $archiveFile->file_path), '/');
        if ($existingPath !== '' && $disk->exists($existingPath)) {
            $disk->delete($existingPath);
        }

        $disk->move($tempFile->path, $newPath);

        $archiveFile->file_path = 'storage/'.$newPath;
        $archiveFile->file_name = $tempFile->original_name;
        $archiveFile->mime_type = $tempFile->mime_type;

        $disk->deleteDirectory(config('vilt-filepond.temp_path').'/'.$tempFile->folder);
        $tempFile->delete();
    }

    private function serializeAdditionalArchiveFiles(Contract $contract): array
    {
        $files = $contract->relationLoaded('archiveFiles')
            ? $contract->archiveFiles
            : $contract->archiveFiles()->with('driver')->get();

        return $files
            ->sortBy('id')
            ->values()
            ->map(fn (ContractArchiveFile $file) => [
                'id' => $file->id,
                'owner_key' => $this->serializeArchiveOwnerKey($file),
                'document_type' => $file->document_type,
                'title' => $file->title,
                'notes' => $file->notes,
                'existing_files' => $file->file_path
                    ? [[
                        'id' => $file->id,
                        'url' => $this->storageUrl($file->file_path),
                    ]]
                    : [],
            ])
            ->all();
    }

    private function serializeArchiveOwnerKey(ContractArchiveFile $file): string
    {
        $driver = $file->relationLoaded('driver') ? $file->driver : $file->driver()->first();
        if (!$driver) {
            return '';
        }

        return $driver->role === 'primary'
            ? 'primary'
            : 'additional_'.(int) $driver->sort_order;
    }
    private function emptyContractArchivePayload(): array
    {
        return [
            'temp_folders' => [],
            'removed_file_ids' => [],
            'files' => [],
        ];
    }

    private function prefillPrimaryDriverFromReservation(Reservation $reservation): array
    {
        return array_merge($this->emptyDriverPayload('primary'), [
            'client_id' => $reservation->user?->id,
            'full_name' => $reservation->user?->name ?? '',
        ]);
    }

    private function prefillCarDataFromReservation(Reservation $reservation): array
    {
        return [
            'car_id' => $reservation->car?->id,
            'car_details' => $reservation->car ? "{$reservation->car->year} {$reservation->car->make} {$reservation->car->model}" : '',
            'plate_number' => $reservation->car?->license_plate,
            'vehicle_odometer' => $reservation->car?->mileage,
            'vehicle_fuel_level' => '',
            'price_per_day' => $reservation->car?->price_per_day,
            'price_per_week' => $reservation->car?->price_per_week,
            'price_per_month' => $reservation->car?->price_per_month,
            'allowed_km_per_day' => $reservation->car?->allowed_km_per_day,
            'allowed_km_per_week' => $reservation->car?->allowed_km_per_week,
            'allowed_km_per_month' => $reservation->car?->allowed_km_per_month,
            'branch_id' => $reservation->car?->branch_id,
        ];
    }

    private function serializeDriver(ContractDriver $driver): array
    {
        $driver->loadMissing('documents');

        return [
            'id' => $driver->id,
            'client_id' => $driver->client_id,
            'role' => $driver->role,
            'full_name' => $driver->full_name,
            'full_name_ar' => $driver->full_name_ar,
            'phone' => $driver->phone,
            'nationality' => $driver->nationality,
            'place_of_issue' => $driver->place_of_issue,
            'date_of_birth' => optional($driver->date_of_birth)->toDateString(),
            'identity_number' => $driver->identity_number,
            'passport_number' => $driver->passport_number,
            'passport_expiry_date' => optional($driver->passport_expiry_date)->toDateString(),
            'visa_number' => $driver->visa_number,
            'visa_expiry_date' => optional($driver->visa_expiry_date)->toDateString(),
            'residency_number' => $driver->residency_number,
            'license_number' => $driver->license_number,
            'license_issue_date' => optional($driver->license_issue_date)->toDateString(),
            'identity_expiry_date' => optional($driver->identity_expiry_date)->toDateString(),
            'license_expiry_date' => optional($driver->license_expiry_date)->toDateString(),
            'extraction_status' => $driver->extraction_status,
            'extracted_data' => $driver->extracted_data,
            'raw_output' => $driver->raw_output,
            'confidence' => $driver->confidence !== null ? (float) $driver->confidence : null,
            'ai_reviewed' => (bool) $driver->ai_reviewed,
            'notes' => $driver->notes,
            'document_type' => $driver->documents->first()?->document_type,
            'temp_folders' => [],
            'removed_file_ids' => [],
            'documents' => $driver->documents->map(fn (ContractDriverDocument $document) => $this->serializeDriverDocument($document))->values()->all(),
            'customer_photo' => $driver->customer_photo_path ? [
                'url' => $this->storageUrl($driver->customer_photo_path),
                'name' => $driver->customer_photo_name,
                'mime_type' => $driver->customer_photo_mime_type,
            ] : null,
            'customer_photo_files' => $driver->customer_photo_path ? [[
                'id' => $driver->id,
                'url' => $this->storageUrl($driver->customer_photo_path),
            ]] : [],
            'customer_photo_temp_folders' => [],
            'customer_photo_removed_file_ids' => [],
        ];
    }

    private function serializeDriverDocument(ContractDriverDocument $document): array
    {
        return [
            'id' => $document->id,
            'document_type' => $document->document_type,
            'side' => $document->side,
            'file_path' => $document->file_path,
            'file_name' => $document->file_name,
            'mime_type' => $document->mime_type,
            'ocr_status' => $document->ocr_status,
            'ocr_provider' => $document->ocr_provider,
            'raw_ocr_json' => $document->raw_ocr_json,
            'normalized_json' => $document->normalized_json,
            'confidence' => $document->confidence !== null ? (float) $document->confidence : null,
            'reviewed_at' => optional($document->reviewed_at)->toDateTimeString(),
            'url' => $this->storageUrl($document->file_path),
        ];
    }

    private function serializeCarData(Contract $contract): array
    {
        return [
            'car_id' => $contract->reservation?->car?->id,
            'car_details' => $contract->car_details,
            'plate_number' => $contract->plate_number,
            'vehicle_odometer' => $contract->vehicle_odometer,
            'vehicle_fuel_level' => $contract->vehicle_fuel_level,
            'price_per_day' => $contract->price_per_day,
            'price_per_week' => $contract->price_per_week,
            'price_per_month' => $contract->price_per_month,
            'allowed_km_per_day' => $contract->allowed_km_per_day,
            'allowed_km_per_week' => $contract->allowed_km_per_week,
            'allowed_km_per_month' => $contract->allowed_km_per_month,
            'branch_id' => $contract->branch_id,
        ];
    }

    private function serializeContractArchive(Contract $contract): array
    {
        return [
            'temp_folders' => [],
            'removed_file_ids' => [],
            'files' => [
                ...$this->collectionFiles($contract, 'start_contract'),
                ...$this->collectionFiles($contract, 'end_contract'),
            ],
        ];
    }


    private function pdfBranding($tenant): array
    {
        $tenant = $tenant?->loadMissing('siteSetting.files');
        $settings = $tenant ? TenantSiteSetting::forTenant($tenant) : [];
        $name = trim((string) ($settings['site_name'] ?? $tenant?->name ?? config('app.name')));

        return [
            'name' => $name !== '' ? $name : (string) config('app.name'),
            'logo' => $this->pdfImageSource($settings['logo_url'] ?? null),
        ];
    }

    private function pdfImageSource(?string $url): ?string
    {
        $url = trim((string) ($url ?? ''));
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, 'data:') || preg_match('/^https?:\/\//i', $url) === 1) {
            return $url;
        }

        $path = null;

        if (str_starts_with($url, '/storage/')) {
            $path = public_path(ltrim($url, '/'));
        } elseif (str_starts_with($url, 'storage/')) {
            $path = public_path($url);
        } elseif (str_starts_with($url, '/')) {
            $path = public_path(ltrim($url, '/'));
        }

        if (!$path || !is_file($path)) {
            return $url;
        }

        $contents = file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            return null;
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function serializeCarDamageCases(int $carId, $user): array
    {
        $query = CarDamageCase::query()
            ->where('car_id', $carId)
            ->whereIn('status', ['open', 'in_repair'])
            ->orderBy('zone_code')
            ->orderBy('id');

        $this->branchAccess->applyToQuery($query, $user, null, 'branch_id');

        $zoneLabels = CarDamageCatalog::zoneLabelMap();
        $viewLabels = collect(CarDamageCatalog::viewSides())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $damageTypeLabels = collect(CarDamageCatalog::damageTypes())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $severityLabels = collect(CarDamageCatalog::severityLevels())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();

        return $query->get()->map(function (CarDamageCase $case) use ($zoneLabels, $viewLabels, $damageTypeLabels, $severityLabels) {
            return [
                'id' => $case->id,
                'zone_code' => $case->zone_code,
                'zone_label' => $zoneLabels[$case->zone_code] ?? $case->zone_code,
                'view_side' => $case->view_side,
                'view_side_label' => $viewLabels[$case->view_side] ?? $case->view_side,
                'damage_type' => $case->damage_type,
                'damage_type_label' => $damageTypeLabels[$case->damage_type] ?? $case->damage_type,
                'severity' => $case->severity,
                'severity_label' => $severityLabels[$case->severity] ?? $case->severity,
                'quantity' => (int) $case->quantity,
                'notes' => $case->notes,
                'first_detected_at' => optional($case->first_detected_at)?->format('Y-m-d H:i'),
            ];
        })->values()->all();
    }

    private function serializeCarDamageCaseMap(array $carIds, $user): array
    {
        $map = [];

        foreach ($carIds as $carId) {
            $normalizedCarId = (int) $carId;
            if ($normalizedCarId <= 0) {
                continue;
            }

            $map[$normalizedCarId] = $this->serializeCarDamageCases($normalizedCarId, $user);
        }

        return $map;
    }

    private function buildPrintableDamageDiagram(array $damageCases, array $viewSideLabels): array
    {
        $layout = $this->printableDamageDiagramLayout();
        $zoneViews = collect(CarDamageCatalog::zoneViews());
        $markers = [];

        foreach (array_values($damageCases) as $index => $damage) {
            if (!is_array($damage)) {
                continue;
            }

            $zone = $zoneViews->first(function (array $view) use ($damage): bool {
                return ($view['code'] ?? null) === ($damage['zone_code'] ?? null)
                    && ($view['view_side'] ?? null) === ($damage['view_side'] ?? null);
            });

            if ($zone === null) {
                $zone = $zoneViews->first(fn (array $view): bool => ($view['code'] ?? null) === ($damage['zone_code'] ?? null));
            }

            if ($zone === null) {
                continue;
            }

            $viewSide = (string) ($zone['view_side'] ?? $damage['view_side'] ?? '');
            $viewLayout = $layout['views'][$viewSide] ?? null;
            if ($viewLayout === null) {
                continue;
            }

            $centerX = (float) $zone['x'] + ((float) $zone['width'] / 2);
            $centerY = (float) $zone['y'] + ((float) $zone['height'] / 2);
            $point = $this->transformPrintableDamagePoint($viewLayout, $centerX, $centerY);

            $markers[] = [
                'number' => $index + 1,
                'view_side' => $viewSide,
                'x' => $point['x'],
                'y' => $point['y'],
                'zone_label' => $damage['zone_label'] ?? ($damage['zone_code'] ?? '-'),
                'view_side_label' => $damage['view_side_label'] ?? $viewSide,
                'damage_type_label' => $damage['damage_type_label'] ?? ($damage['damage_type'] ?? '-'),
                'severity_label' => $damage['severity_label'] ?? ($damage['severity'] ?? '-'),
                'quantity' => (int) ($damage['quantity'] ?? 0),
                'notes' => $damage['notes'] ?? null,
            ];
        }

        return [
            'canvas_width' => 785,
            'canvas_height' => 483,
            'views' => $layout['views'],
            'markers' => $markers,
            'data_uri' => 'data:image/svg+xml;base64,'.base64_encode(
                $this->renderPrintableDamageDiagramSvg($layout['views'], $markers, $viewSideLabels)
            ),
        ];
    }

    private function renderPrintableDamageDiagramSvg(array $views, array $markers, array $viewSideLabels): string
    {
        $escape = static fn (?string $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $parts = [
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 785 483" width="785" height="483">',
            $this->printableDamageDiagramAssetMarkup(),
        ];

        foreach ($markers as $marker) {
            $parts[] = '<circle cx="'.$marker['x'].'" cy="'.$marker['y'].'" r="12" fill="#111827" stroke="#FFFFFF" stroke-width="3" />';
            $parts[] = '<text x="'.$marker['x'].'" y="'.($marker['y'] + 4).'" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="12" font-weight="700" fill="#FFFFFF">'.$escape((string) $marker['number']).'</text>';
        }

        $parts[] = '</svg>';

        return implode('', $parts);
    }

    private function printableDamageDiagramAssetMarkup(): string
    {
        $path = public_path('images/contract-damage-layout.svg');
        if (!is_file($path)) {
            return '<rect x="1" y="1" width="758" height="468" rx="12" fill="#FFFFFF" stroke="#E5E7EB" stroke-width="2" />';
        }

        $contents = file_get_contents($path);
        if (!is_string($contents) || trim($contents) === '') {
            return '<rect x="1" y="1" width="758" height="468" rx="12" fill="#FFFFFF" stroke="#E5E7EB" stroke-width="2" />';
        }

        $contents = preg_replace('/<\?xml.*?\?>/s', '', $contents) ?? $contents;

        if (preg_match('/<svg[^>]*>(.*)<\/svg>/si', $contents, $matches) === 1) {
            return $matches[1];
        }

        return $contents;
    }

    private function printableDamageDiagramLayout(): array
    {
        return [
            'views' => [
                'front' => ['x' => 52, 'y' => 170, 'scale_x' => 0.62, 'scale_y' => 0.62, 'rotation' => 'cw', 'source_width' => 320, 'source_height' => 160, 'width' => 99, 'height' => 198],
                'top' => ['x' => 194, 'y' => 165, 'scale_x' => 1.18, 'scale_y' => 0.96, 'rotation' => 'none', 'source_width' => 320, 'source_height' => 160, 'width' => 378, 'height' => 154],
                'rear' => ['x' => 587, 'y' => 170, 'scale_x' => 0.62, 'scale_y' => 0.62, 'rotation' => 'ccw', 'source_width' => 320, 'source_height' => 160, 'width' => 99, 'height' => 198],
                'left' => ['x' => 191, 'y' => 328, 'scale_x' => 1.17, 'scale_y' => 0.78, 'rotation' => 'none', 'source_width' => 320, 'source_height' => 160, 'width' => 374, 'height' => 125],
                'right' => ['x' => 191, 'y' => 35, 'scale_x' => 1.17, 'scale_y' => 0.78, 'rotation' => 'flip', 'source_width' => 320, 'source_height' => 160, 'width' => 374, 'height' => 125],
            ],
        ];
    }

    private function transformPrintableDamagePoint(array $viewLayout, float $x, float $y): array
    {
        $sourceWidth = (float) ($viewLayout['source_width'] ?? 320);
        $sourceHeight = (float) ($viewLayout['source_height'] ?? 160);
        $scaleX = (float) ($viewLayout['scale_x'] ?? $viewLayout['scale'] ?? 1);
        $scaleY = (float) ($viewLayout['scale_y'] ?? $viewLayout['scale'] ?? 1);
        $rotation = (string) ($viewLayout['rotation'] ?? 'none');

        $mappedX = $x;
        $mappedY = $y;

        if ($rotation === 'cw') {
            $mappedX = $sourceHeight - $y;
            $mappedY = $x;
        } elseif ($rotation === 'ccw') {
            $mappedX = $y;
            $mappedY = $sourceWidth - $x;
        } elseif ($rotation === 'flip') {
            $mappedX = $sourceWidth - $x;
            $mappedY = $sourceHeight - $y;
        }

        return [
            'x' => round((float) $viewLayout['x'] + ($mappedX * $scaleX), 1),
            'y' => round((float) $viewLayout['y'] + ($mappedY * $scaleY), 1),
        ];
    }
}












