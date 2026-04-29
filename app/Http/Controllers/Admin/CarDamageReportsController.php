<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarDamageCase;
use App\Models\CarDamageReport;
use App\Models\Contract;
use App\Models\Reservation;
use App\Support\BranchAccess;
use App\Support\CarDamageCatalog;
use App\Support\PaidReturnReportLock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CarDamageReportsController extends Controller
{
    public function __construct(private BranchAccess $branchAccess)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = $request->string('search')->toString();
        $reportType = $request->string('report_type')->toString();
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $carId = $this->branchAccess->normalizeRequestedBranchId($request->input('car_id'));
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

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

        $carsQuery = Car::query()
            ->select(['id', 'make', 'model', 'year', 'license_plate', 'branch_id'])
            ->orderBy('make')
            ->orderBy('model');
        $this->branchAccess->applyToQuery($carsQuery, $user, $branchId);
        $cars = $carsQuery
            ->get()
            ->map(fn (Car $car) => [
                'id' => $car->id,
                'label' => trim("{$car->year} {$car->make} {$car->model} ({$car->license_plate})"),
            ])
            ->values();

        $query = CarDamageReport::query()
            ->with([
                'car:id,make,model,year,license_plate',
                'branch:id,name',
                'contract:id,contract_number',
                'reservation:id,reservation_number',
            ])
            ->withCount('items')
            ->withSum('items as total_quantity', 'quantity')
            ->withSum('items as total_estimated_cost', 'estimated_cost');

        $this->branchAccess->applyToQuery($query, $user, $branchId, 'branch_id');

        if ($carId) {
            $query->where('car_id', $carId);
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('report_number', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%")
                    ->orWhereHas('contract', fn (Builder $contractQuery) => $contractQuery->where('contract_number', 'like', "%{$search}%"))
                    ->orWhereHas('reservation', fn (Builder $reservationQuery) => $reservationQuery->where('reservation_number', 'like', "%{$search}%"))
                    ->orWhereHas('car', function (Builder $carQuery) use ($search) {
                        $carQuery->where('make', 'like', "%{$search}%")
                            ->orWhere('model', 'like', "%{$search}%")
                            ->orWhere('license_plate', 'like', "%{$search}%");
                    });
            });
        }

        if ($reportType !== '' && $reportType !== 'all') {
            $query->where('report_type', $reportType);
        }

        $reports = $query
            ->latest('inspected_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $reportTypeLabels = collect(CarDamageCatalog::reportTypes())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']]);

        $reports->getCollection()->transform(function (CarDamageReport $report) use ($reportTypeLabels) {
            return [
                'id' => $report->id,
                'report_number' => $report->report_number,
                'car' => $report->car
                    ? trim("{$report->car->year} {$report->car->make} {$report->car->model} ({$report->car->license_plate})")
                    : '-',
                'report_type' => $report->report_type,
                'report_type_label' => $reportTypeLabels[$report->report_type] ?? Str::title(str_replace('_', ' ', (string) $report->report_type)),
                'status' => $report->status,
                'inspected_at' => optional($report->inspected_at)?->format('Y-m-d H:i'),
                'branch' => $report->branch?->name ?? '-',
                'contract_number' => $report->contract?->contract_number,
                'reservation_number' => $report->reservation?->reservation_number,
                'items_count' => (int) $report->items_count,
                'total_quantity' => (int) ($report->total_quantity ?? 0),
                'total_estimated_cost' => (float) ($report->total_estimated_cost ?? 0),
                'edit_url' => url('/admin/car-damage-reports/'.$report->getKey().'/edit'),
                'destroy_url' => url('/admin/car-damage-reports/'.$report->getKey()),
            ];
        });

        return Inertia::render('Admin/CarDamageReports/Index', [
            'reports' => $reports,
            'reportTypes' => CarDamageCatalog::reportTypes(),
            'branches' => $branchOptions,
            'cars' => $cars,
            'canAccessAllBranches' => $canAccessAllBranches,
            'filters' => [
                'search' => $search,
                'report_type' => $reportType === '' ? 'all' : $reportType,
                'branch_id' => $branchId,
                'car_id' => $carId,
            ],
            'indexUrl' => url('/admin/car-damage-reports'),
            'contractsIndexUrl' => url('/admin/contracts'),
        ]);
    }

    public function create(Request $request): Response
    {
        $prefilledContract = $request->filled('contract_id')
            ? $this->resolveAccessibleContract($request, $request->integer('contract_id'))
            : null;

        if ($prefilledContract) {
            $this->abortIfContractLocked($prefilledContract);
        }

        [$prefilledReservation, $prefilledCar] = $prefilledContract
            ? $this->resolveContractReservationAndCar($request, $prefilledContract)
            : [null, null];

        return Inertia::render('Admin/CarDamageReports/Edit', [
            'report' => [
                'report_number' => $this->generateReportNumber(),
                'car_id' => $prefilledCar?->id,
                'contract_id' => $prefilledContract?->id,
                'reservation_id' => $prefilledReservation?->id,
                'report_type' => 'before_delivery',
                'status' => 'draft',
                'inspected_at' => now()->format('Y-m-d\TH:i'),
                'odometer' => null,
                'summary' => $prefilledContract
                    ? 'Initial inspection linked to contract '.$prefilledContract->contract_number.'.'
                    : '',
                'items' => [],
            ],
            ...$this->formOptions($request),
            'currentCarDamages' => $prefilledCar ? $this->serializeCarDamageCases($prefilledCar->id, $request) : [],
            'indexUrl' => url('/admin/car-damage-reports'),
            'submitUrl' => url('/admin/car-damage-reports'),
            'method' => 'post',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $contract = $this->resolveAccessibleContract($request, (int) $validated['contract_id']);
        $this->abortIfContractLocked($contract);
        [$reservation, $car] = $this->resolveContractReservationAndCar($request, $contract);

        $report = DB::transaction(function () use ($request, $validated, $car, $contract, $reservation) {
            $report = CarDamageReport::create([
                'car_id' => $car->id,
                'branch_id' => $car->branch_id,
                'contract_id' => $contract->id,
                'reservation_id' => $reservation->id,
                'created_by' => $request->user()?->id,
                'report_number' => $validated['report_number'],
                'report_type' => $validated['report_type'],
                'status' => $validated['status'],
                'inspected_at' => $validated['inspected_at'] ?? null,
                'odometer' => $validated['odometer'] ?? null,
                'summary' => $validated['summary'] ?? null,
            ]);

            $this->syncItems($report, $validated['items'] ?? []);
            $this->syncDamageCases($report, $validated['items'] ?? [], $car, $contract, $reservation, $request->user()?->id);

            return $report;
        });

        return redirect()
            ->to(url('/admin/car-damage-reports/'.$report->getKey().'/edit'))
            ->with('success', 'Damage report created successfully.');
    }

    public function edit(Request $request, CarDamageReport $carDamageReport): Response
    {
        $routeReport = $request->route('carDamageReport');
        $reportId = $routeReport instanceof CarDamageReport
            ? (int) $routeReport->getKey()
            : (int) ($routeReport ?? $carDamageReport->getKey() ?? 0);
        abort_unless($reportId > 0, 404);

        $carDamageReport = CarDamageReport::query()->withoutGlobalScopes()->findOrFail($reportId);
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $carDamageReport->branch_id ? (int) $carDamageReport->branch_id : null), 403);

        $carDamageReport->loadMissing([
            'items',
            'contract:id,contract_number',
            'reservation:id,reservation_number',
        ]);

        return Inertia::render('Admin/CarDamageReports/Edit', [
            'report' => [
                'id' => $carDamageReport->id,
                'report_number' => $carDamageReport->report_number,
                'car_id' => $carDamageReport->car_id,
                'contract_id' => $carDamageReport->contract_id,
                'reservation_id' => $carDamageReport->reservation_id,
                'report_type' => $carDamageReport->report_type,
                'status' => $carDamageReport->status,
                'inspected_at' => optional($carDamageReport->inspected_at)?->format('Y-m-d\TH:i'),
                'odometer' => $carDamageReport->odometer,
                'summary' => $carDamageReport->summary,
                'items' => $carDamageReport->items->map(function ($item) use ($carDamageReport) {
                    return [
                        'id' => $item->id,
                        'zone_code' => $item->zone_code,
                        'view_side' => $item->view_side,
                        'damage_type' => $item->damage_type,
                        'severity' => $item->severity,
                        'damage_timing' => $item->damage_timing ?: ($carDamageReport->report_type === 'after_return' ? 'after_return' : 'before_pickup'),
                        'quantity' => (int) $item->quantity,
                        'marker_x' => $item->marker_x !== null ? (float) $item->marker_x : null,
                        'marker_y' => $item->marker_y !== null ? (float) $item->marker_y : null,
                        'estimated_cost' => $item->estimated_cost !== null ? (float) $item->estimated_cost : null,
                        'notes' => $item->notes,
                    ];
                })->values()->all(),
                'is_locked' => PaidReturnReportLock::damageReport($carDamageReport),
            ],
            ...$this->formOptions($request),
            'currentCarDamages' => $this->serializeCarDamageCases($carDamageReport->car_id, $request),
            'indexUrl' => url('/admin/car-damage-reports'),
            'submitUrl' => url('/admin/car-damage-reports/'.$carDamageReport->getKey()),
            'method' => 'put',
        ]);
    }

    public function update(Request $request, CarDamageReport $carDamageReport): RedirectResponse
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $carDamageReport->branch_id ? (int) $carDamageReport->branch_id : null), 403);
        $this->abortIfDamageReportLocked($carDamageReport);

        $validated = $this->validatePayload($request, $carDamageReport);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $contract = $this->resolveAccessibleContract($request, (int) $validated['contract_id']);
        [$reservation, $car] = $this->resolveContractReservationAndCar($request, $contract);

        DB::transaction(function () use ($validated, $car, $contract, $reservation, $carDamageReport) {
            $carDamageReport->update([
                'car_id' => $car->id,
                'branch_id' => $car->branch_id,
                'contract_id' => $contract->id,
                'reservation_id' => $reservation->id,
                'report_number' => $validated['report_number'],
                'report_type' => $validated['report_type'],
                'status' => $validated['status'],
                'inspected_at' => $validated['inspected_at'] ?? null,
                'odometer' => $validated['odometer'] ?? null,
                'summary' => $validated['summary'] ?? null,
            ]);

            $this->syncItems($carDamageReport, $validated['items'] ?? []);
            $this->syncDamageCases($carDamageReport, $validated['items'] ?? [], $car, $contract, $reservation, null);
        });

        return redirect()
            ->to(url('/admin/car-damage-reports/'.$carDamageReport->getKey().'/edit'))
            ->with('success', 'Damage report updated successfully.');
    }

    public function destroy(Request $request, CarDamageReport $carDamageReport): RedirectResponse
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $carDamageReport->branch_id ? (int) $carDamageReport->branch_id : null), 403);
        $this->abortIfDamageReportLocked($carDamageReport);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $carDamageReport->delete();

        return back()->with('success', 'Damage report deleted successfully.');
    }

    private function validatePayload(Request $request, ?CarDamageReport $report = null): array
    {
        $tenantId = (int) (TenantContext::id() ?? $request->user()?->tenant_id ?? 0);

        return $request->validate([
            'report_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('car_damage_reports', 'report_number')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($report?->id),
            ],
            'contract_id' => [
                'required',
                'integer',
                Rule::exists('contracts', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'report_type' => ['required', 'string', Rule::in(array_column(CarDamageCatalog::reportTypes(), 'value'))],
            'status' => ['required', 'string', Rule::in(array_column(CarDamageCatalog::statuses(), 'value'))],
            'inspected_at' => ['nullable', 'date'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'items' => ['nullable', 'array'],
            'items.*.zone_code' => ['required', 'string', Rule::in(CarDamageCatalog::zoneCodes())],
            'items.*.view_side' => ['required', 'string', Rule::in(array_column(CarDamageCatalog::viewSides(), 'value'))],
            'items.*.damage_type' => ['required', 'string', Rule::in(array_column(CarDamageCatalog::damageTypes(), 'value'))],
            'items.*.severity' => ['required', 'string', Rule::in(array_column(CarDamageCatalog::severityLevels(), 'value'))],
            'items.*.damage_timing' => ['required', 'string', Rule::in(array_column(CarDamageCatalog::damageTimings(), 'value'))],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.marker_x' => ['nullable', 'numeric', 'min:0'],
            'items.*.marker_y' => ['nullable', 'numeric', 'min:0'],
            'items.*.estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function resolveAccessibleCar(Request $request, int $carId): Car
    {
        $query = Car::query()->whereKey($carId);
        $this->branchAccess->applyToQuery($query, $request->user(), null);
        $car = $query->first();

        abort_if(!$car, 422, 'Selected car is not accessible.');

        return $car;
    }

    private function abortIfContractLocked(Contract $contract): void
    {
        if (PaidReturnReportLock::contract($contract)) {
            abort(423, 'This contract is locked because the return report is paid.');
        }
    }

    private function abortIfDamageReportLocked(CarDamageReport $carDamageReport): void
    {
        if (PaidReturnReportLock::damageReport($carDamageReport)) {
            abort(423, 'This damage report is locked because the linked contract return report is paid.');
        }
    }

    private function resolveAccessibleContract(Request $request, mixed $contractId): ?Contract
    {
        if (!$contractId) {
            return null;
        }

        $query = Contract::query()->whereKey((int) $contractId);
        $this->branchAccess->applyToQuery($query, $request->user(), null, 'branch_id');
        $contract = $query->first();

        abort_if(!$contract, 422, 'Selected contract is not accessible.');

        return $contract;
    }

    private function resolveAccessibleReservation(Request $request, mixed $reservationId): ?Reservation
    {
        if (!$reservationId) {
            return null;
        }

        $query = Reservation::query()->whereKey((int) $reservationId);
        $query->whereHas('car', function (Builder $builder) use ($request) {
            $this->branchAccess->applyToQuery($builder, $request->user(), null, 'branch_id');
        });
        $reservation = $query->first();

        abort_if(!$reservation, 422, 'Selected reservation is not accessible.');

        return $reservation;
    }

    private function assertRelationships(Car $car, ?Contract $contract, ?Reservation $reservation): void
    {
        if ($reservation && (int) $reservation->car_id !== (int) $car->id) {
            abort(422, 'The selected reservation does not belong to the selected car.');
        }

        if ($contract && $contract->reservation_id && $reservation && (int) $contract->reservation_id !== (int) $reservation->id) {
            abort(422, 'The selected contract does not match the selected reservation.');
        }

        if ($contract && $contract->reservation_id) {
            $contractReservation = Reservation::query()->find((int) $contract->reservation_id);
            if ($contractReservation && (int) $contractReservation->car_id !== (int) $car->id) {
                abort(422, 'The selected contract does not belong to the selected car.');
            }
        }
    }

    private function resolveContractReservationAndCar(Request $request, Contract $contract): array
    {
        abort_if(!$contract->reservation_id, 422, 'The selected contract is not linked to a reservation.');

        $reservation = $this->resolveAccessibleReservation($request, (int) $contract->reservation_id);
        abort_if(!$reservation || !$reservation->car_id, 422, 'The selected contract reservation is not linked to a car.');

        $car = $this->resolveAccessibleCar($request, (int) $reservation->car_id);
        $this->assertRelationships($car, $contract, $reservation);

        return [$reservation, $car];
    }

    private function syncItems(CarDamageReport $report, array $items): void
    {
        $report->items()->delete();

        foreach (array_values($items) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $report->items()->create([
                'tenant_id' => $report->tenant_id,
                'zone_code' => $item['zone_code'],
                'view_side' => $item['view_side'],
                'damage_type' => $item['damage_type'],
                'severity' => $item['severity'],
                'damage_timing' => $item['damage_timing'],
                'quantity' => (int) $item['quantity'],
                'marker_x' => $item['marker_x'] ?? null,
                'marker_y' => $item['marker_y'] ?? null,
                'estimated_cost' => $item['estimated_cost'] ?? null,
                'notes' => $item['notes'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    private function syncDamageCases(
        CarDamageReport $report,
        array $items,
        Car $car,
        Contract $contract,
        Reservation $reservation,
        ?int $userId
    ): void {
        foreach (array_values($items) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $existingCase = CarDamageCase::query()
                ->where('tenant_id', $report->tenant_id)
                ->where('car_id', $car->id)
                ->where('zone_code', $item['zone_code'])
                ->where('damage_type', $item['damage_type'])
                ->whereIn('status', ['open', 'in_repair'])
                ->orderBy('id')
                ->first();

            $payload = [
                'branch_id' => $car->branch_id,
                'opened_in_contract_id' => $existingCase?->opened_in_contract_id ?: $contract->id,
                'opened_in_reservation_id' => $existingCase?->opened_in_reservation_id ?: $reservation->id,
                'last_report_id' => $report->id,
                'created_by' => $existingCase?->created_by ?: $userId,
                'zone_code' => $item['zone_code'],
                'view_side' => $item['view_side'],
                'damage_type' => $item['damage_type'],
                'severity' => $item['severity'],
                'damage_timing' => $item['damage_timing'],
                'quantity' => (int) $item['quantity'],
                'marker_x' => $item['marker_x'] ?? null,
                'marker_y' => $item['marker_y'] ?? null,
                'estimated_cost' => $item['estimated_cost'] ?? null,
                'notes' => $item['notes'] ?? null,
                'status' => 'open',
                'first_detected_at' => $existingCase?->first_detected_at ?: ($report->inspected_at ?? now()),
                'last_detected_at' => $report->inspected_at ?? now(),
            ];

            if ($existingCase) {
                $existingCase->update($payload);
                continue;
            }

            CarDamageCase::create([
                'tenant_id' => $report->tenant_id,
                'car_id' => $car->id,
                ...$payload,
            ]);
        }
    }

    private function generateReportNumber(): string
    {
        return 'DMG-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
    }

    private function formOptions(Request $request): array
    {
        $user = $request->user();
        $tenantId = (int) (TenantContext::id() ?? $user?->tenant_id ?? 0);

        $carsQuery = Car::query()
            ->select(['id', 'year', 'make', 'model', 'license_plate', 'branch_id'])
            ->orderBy('make')
            ->orderBy('model');
        $this->branchAccess->applyToQuery($carsQuery, $user, null);
        $cars = $carsQuery
            ->get()
            ->map(fn (Car $car) => [
                'id' => $car->id,
                'label' => trim("{$car->year} {$car->make} {$car->model} ({$car->license_plate})"),
                'branch_id' => $car->branch_id,
            ])
            ->values();

        $contractsQuery = Contract::query()
            ->select(['id', 'contract_number', 'reservation_id', 'branch_id'])
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->limit(200);
        $this->branchAccess->applyToQuery($contractsQuery, $user, null, 'branch_id');
        $contracts = $contractsQuery
            ->get()
            ->map(fn (Contract $contract) => [
                'id' => $contract->id,
                'label' => $contract->contract_number,
                'reservation_id' => $contract->reservation_id,
                'branch_id' => $contract->branch_id,
            ])
            ->values();

        $reservations = Reservation::query()
            ->select(['id', 'reservation_number', 'car_id'])
            ->where('tenant_id', $tenantId)
            ->whereHas('car', function (Builder $builder) use ($user) {
                $this->branchAccess->applyToQuery($builder, $user, null, 'branch_id');
            })
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'label' => $reservation->reservation_number ?: ('Reservation #'.$reservation->id),
                'car_id' => $reservation->car_id,
            ])
            ->values();

        return [
            'cars' => $cars,
            'contracts' => $contracts,
            'reservations' => $reservations,
            'reportTypes' => CarDamageCatalog::reportTypes(),
            'statuses' => CarDamageCatalog::statuses(),
            'damageTypes' => CarDamageCatalog::damageTypes(),
            'severityLevels' => CarDamageCatalog::severityLevels(),
            'damageTimings' => CarDamageCatalog::damageTimings(),
            'viewSides' => CarDamageCatalog::viewSides(),
            'zoneOptions' => CarDamageCatalog::zoneDefinitions(),
            'zoneViews' => CarDamageCatalog::zoneViews(),
            'zoneLabelMap' => CarDamageCatalog::zoneLabelMap(),
        ];
    }

    private function serializeCarDamageCases(?int $carId, Request $request): array
    {
        if (!$carId) {
            return [];
        }

        $query = CarDamageCase::query()
            ->where('car_id', $carId)
            ->whereIn('status', ['open', 'in_repair'])
            ->orderBy('zone_code')
            ->orderBy('id');

        $this->branchAccess->applyToQuery($query, $request->user(), null, 'branch_id');

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
        $damageTimingLabels = collect(CarDamageCatalog::damageTimings())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();

        return $query->get()->map(function (CarDamageCase $case) use ($zoneLabels, $viewLabels, $damageTypeLabels, $severityLabels, $damageTimingLabels) {
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
                'damage_timing' => $case->damage_timing ?: 'before_pickup',
                'damage_timing_label' => $damageTimingLabels[$case->damage_timing ?: 'before_pickup'] ?? ($case->damage_timing ?: 'before_pickup'),
                'quantity' => (int) $case->quantity,
                'notes' => $case->notes,
                'first_detected_at' => optional($case->first_detected_at)?->format('Y-m-d H:i'),
            ];
        })->values()->all();
    }
}

