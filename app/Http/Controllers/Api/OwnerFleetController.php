<?php

namespace App\Http\Controllers\Api;

use App\Enums\CarStatus;
use App\Enums\ContractStatus;
use App\Enums\MaintenanceRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Car;
use App\Models\CarDamageCase;
use App\Models\CarDamageReport;
use App\Models\CarMaintenance;
use App\Models\Contract;
use App\Models\MaintenanceType;
use App\Models\MaintenanceWorkshop;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Support\BranchAccess;
use App\Support\CurrencyCatalog;
use App\Support\TenantTranslations;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OwnerFleetController extends Controller
{
    public function __construct(private readonly BranchAccess $branchAccess)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $branchId = $this->resolveOwnerBranchId($request, $user);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(array_merge(['all'], array_map(
                static fn (CarStatus $status): string => $status->value,
                CarStatus::cases()
            )))],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = (string) ($validated['status'] ?? 'all');
        $search = trim((string) ($validated['search'] ?? ''));
        $tenant = Tenant::query()->with('siteSetting')->findOrFail((int) $user->tenant_id);
        $currency = CurrencyCatalog::forTenant($tenant, null, $locale);

        $statusCounts = $this->statusCounts((int) $user->tenant_id, $branchId);
        $reservationCount = $this->reservationCount((int) $user->tenant_id, $branchId);
        $monthlyRevenueByCar = $this->monthlyRevenueByCar((int) $user->tenant_id, $branchId);
        $nextEventsByCar = $this->nextEventsByCar((int) $user->tenant_id, $branchId);

        $query = Car::query()
            ->with(['branch:id,name,country,city,address', 'files', 'tenant.siteSetting'])
            ->where('tenant_id', (int) $user->tenant_id);

        $this->branchAccess->applyToQuery($query, $user, $branchId);

        $query
            ->when($status !== 'all', fn (Builder $query): Builder => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('make', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('year', 'like', "%{$search}%")
                        ->orWhere('license_plate', 'like', "%{$search}%")
                        ->orWhereHas('branch', fn (Builder $branchQuery) => $branchQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw("FIELD(status, 'available', 'reserved', 'rented', 'maintenance', 'cleaning', 'unavailable', 'retired', 'draft')")
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'branch_id' => $branchId,
            'currency' => $currency,
            'filters' => [
                'status' => $status,
                'search' => $search !== '' ? $search : null,
            ],
            'summary' => $this->summaryPayload($statusCounts, $reservationCount, $locale),
            'data' => $paginator->getCollection()
                ->map(fn (Car $car): array => $this->carPayload(
                    $car,
                    $locale,
                    $currency,
                    (float) ($monthlyRevenueByCar[(int) $car->id] ?? 0),
                    $nextEventsByCar[(int) $car->id] ?? null
                ))
                ->values()
                ->all(),
            'pagination' => $this->paginationPayload($paginator),
        ]);
    }

    public function statuses(Request $request): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $branchId = $this->resolveOwnerBranchId($request, $user);
        $statusCounts = $this->statusCounts((int) $user->tenant_id, $branchId);
        $reservationCount = $this->reservationCount((int) $user->tenant_id, $branchId);

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'branch_id' => $branchId,
            'summary' => $this->summaryPayload($statusCounts, $reservationCount, $locale),
            'data' => $this->statusesPayload($statusCounts, $locale),
        ]);
    }

    public function show(Request $request, Car $car): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $branchId = $this->resolveOwnerBranchId($request, $user);

        abort_unless((int) $car->tenant_id === (int) $user->tenant_id, 404);
        abort_unless($this->branchAccess->canAccessBranchId($user, $car->branch_id ? (int) $car->branch_id : null), 403);
        if ($branchId && (int) $car->branch_id !== $branchId) {
            abort(404);
        }

        $car->load(['branch:id,name,country,city,address', 'files', 'tenant.siteSetting']);
        $tenant = Tenant::query()->with('siteSetting')->findOrFail((int) $user->tenant_id);
        $currency = CurrencyCatalog::forTenant($tenant, null, $locale);
        $monthlyRevenue = $this->monthlyRevenueByCar((int) $user->tenant_id, $car->branch_id ? (int) $car->branch_id : null, (int) $car->id);
        $nextEvent = $this->nextEventsByCar((int) $user->tenant_id, $car->branch_id ? (int) $car->branch_id : null, (int) $car->id)[(int) $car->id] ?? null;

        $occupancyData = $this->occupancyRateByCar((int) $user->tenant_id, $car);
        $upcomingSummary = $this->upcomingReservationsSummary((int) $user->tenant_id, $car, $locale);
        $lastMaintenance = $this->lastMaintenanceSummary((int) $user->tenant_id, $car, $locale);
        $damageSummary = $this->damageRecordSummary((int) $user->tenant_id, $car, $locale);

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'data' => array_merge(
                $this->carPayload(
                    $car,
                    $locale,
                    $currency,
                    (float) ($monthlyRevenue[(int) $car->id] ?? 0),
                    $nextEvent
                ),
                [
                    'occupancy_rate' => $occupancyData['rate'],
                    'formatted_occupancy_rate' => $occupancyData['formatted_rate'],
                    'upcoming_reservations_summary' => $upcomingSummary,
                    'last_maintenance' => $lastMaintenance,
                    'damage_record_summary' => $damageSummary,
                    'images' => collect($car->images)
                        ->map(fn (array $image): array => array_merge($image, [
                            'url' => $this->absoluteUrl((string) ($image['url'] ?? '')),
                        ]))
                        ->values()
                        ->all(),
                    'additional_photos' => collect($car->additional_photos)
                        ->map(fn (array $photo): array => array_merge($photo, [
                            'url' => !empty($photo['url']) ? $this->absoluteUrl((string) $photo['url']) : null,
                        ]))
                        ->all(),
                    'stats' => $this->carStats((int) $user->tenant_id, (int) $car->id),
                    'recent_reservations' => $this->recentReservations((int) $user->tenant_id, (int) $car->id, $locale, $currency),
                ]
            ),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Maintenance Options (form dropdowns for mobile)
    // ─────────────────────────────────────────────────────────────

    public function maintenanceOptions(Request $request): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);

        $statuses = collect(MaintenanceRecordStatus::cases())->map(fn (MaintenanceRecordStatus $s): array => [
            'value' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
        ])->values();

        $maintenanceTypes = MaintenanceType::query()
            ->withoutGlobalScope('tenant')
            ->with(['workshops' => fn ($q) => $q
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', (int) $user->tenant_id)
                ->select(['id', 'maintenance_type_id', 'name', 'phone', 'city', 'country'])
                ->orderBy('name'),
            ])
            ->where('tenant_id', (int) $user->tenant_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->select(['id', 'name'])
            ->get()
            ->map(fn (MaintenanceType $type): array => [
                'id'        => $type->id,
                'name'      => $type->name,
                'workshops' => $type->workshops->map(fn (MaintenanceWorkshop $w): array => [
                    'id'    => $w->id,
                    'name'  => $w->name,
                    'phone' => $w->phone,
                    'city'  => $w->city,
                    'label' => trim($w->name.($w->city ? " - {$w->city}" : '').($w->phone ? " ({$w->phone})" : '')),
                ])->values()->all(),
            ])->values();

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'data'   => [
                'statuses'          => $statuses,
                'maintenance_types' => $maintenanceTypes,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Schedule Maintenance
    // ─────────────────────────────────────────────────────────────

    public function scheduleMaintenance(Request $request, Car $car): JsonResponse
    {
        $user   = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);

        abort_unless((int) $car->tenant_id === (int) $user->tenant_id, 404);
        abort_unless($this->branchAccess->canAccessBranchId($user, $car->branch_id ? (int) $car->branch_id : null), 403);

        $tenantId = (int) $user->tenant_id;

        $validated = $request->validate([
            'status'                    => ['required', 'string', Rule::enum(MaintenanceRecordStatus::class)],
            'scheduled_date'            => ['nullable', 'date'],
            'task_time'                 => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'maintenance_type_id'       => [
                'nullable',
                'integer',
                Rule::exists('maintenance_types', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'maintenance_workshop_id'   => [
                'nullable',
                'integer',
                Rule::exists('maintenance_workshops', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'cost'                      => ['nullable', 'numeric', 'min:0'],
            'odometer'                  => ['nullable', 'integer', 'min:0'],
            'notes'                     => ['nullable', 'string', 'max:5000'],
        ]);

        // Resolve workshop (must belong to same tenant)
        $workshop = null;
        if (!empty($validated['maintenance_workshop_id'])) {
            $workshop = MaintenanceWorkshop::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->find((int) $validated['maintenance_workshop_id']);

            if (!$workshop) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'maintenance_workshop_id' => ['Selected workshop is not accessible.'],
                ]);
            }
        }

        // Combine scheduled_date + task_time → started_at
        $scheduledDate = !empty($validated['scheduled_date'])
            ? Carbon::parse($validated['scheduled_date'])
            : null;

        $startedAt = null;
        if ($scheduledDate && !empty($validated['task_time'])) {
            [$hours, $minutes] = explode(':', $validated['task_time']);
            $startedAt = $scheduledDate->copy()->setHour((int) $hours)->setMinute((int) $minutes)->setSecond(0);
        }

        $maintenance = CarMaintenance::create([
            'tenant_id'                 => $tenantId,
            'car_id'                    => $car->id,
            'branch_id'                 => $car->branch_id,
            'maintenance_type_id'       => $validated['maintenance_type_id'] ?? null,
            'maintenance_workshop_id'   => $workshop?->id,
            'status'                    => $validated['status'],
            'scheduled_date'            => $scheduledDate?->toDateString(),
            'started_at'                => $startedAt,
            'cost'                      => $validated['cost'] ?? null,
            'odometer'                  => $validated['odometer'] ?? null,
            'workshop_name'             => $workshop?->name,
            'notes'                     => $validated['notes'] ?? null,
            'created_by'                => $user->id,
        ]);

        // Sync car status when in_progress
        $this->syncCarStatusForMaintenance($car);

        $statusEnum = MaintenanceRecordStatus::from($maintenance->status instanceof MaintenanceRecordStatus
            ? $maintenance->status->value
            : (string) $maintenance->status);

        $maintenanceType = $maintenance->maintenance_type_id
            ? MaintenanceType::withoutGlobalScope('tenant')->find($maintenance->maintenance_type_id)
            : null;

        return response()->json([
            'status'  => 'success',
            'message' => $this->ownerText('fleet.maintenance.created', $locale, 'Maintenance scheduled successfully.'),
            'data'    => [
                'id'                      => $maintenance->id,
                'car_id'                  => $car->id,
                'status'                  => $statusEnum->value,
                'status_label'            => $statusEnum->label(),
                'status_color'            => $statusEnum->color(),
                'scheduled_date'          => $scheduledDate?->toDateString(),
                'task_time'               => $validated['task_time'] ?? null,
                'maintenance_type_id'     => $maintenance->maintenance_type_id,
                'maintenance_type'        => $maintenanceType?->name,
                'maintenance_workshop_id' => $maintenance->maintenance_workshop_id,
                'workshop_name'           => $maintenance->workshop_name,
                'cost'                    => $maintenance->cost !== null ? (float) $maintenance->cost : null,
                'odometer'                => $maintenance->odometer,
                'notes'                   => $maintenance->notes,
                'created_at'              => $maintenance->created_at?->toIso8601String(),
            ],
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────
    //  Transfer Branch
    // ─────────────────────────────────────────────────────────────

    public function transferBranch(Request $request, Car $car): JsonResponse
    {
        $user   = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);

        abort_unless((int) $car->tenant_id === (int) $user->tenant_id, 404);
        abort_unless($this->branchAccess->canAccessBranchId($user, $car->branch_id ? (int) $car->branch_id : null), 403);

        $tenantId = (int) $user->tenant_id;

        $validated = $request->validate([
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ]);

        $newBranchId = (int) $validated['branch_id'];

        if ($car->branch_id && (int) $car->branch_id === $newBranchId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'branch_id' => [$this->ownerText('fleet.transfer.same_branch', $locale, 'Car is already in the selected branch.')],
            ]);
        }

        $newBranch = Branch::query()->where('tenant_id', $tenantId)->findOrFail($newBranchId);

        $car->update(['branch_id' => $newBranchId]);

        return response()->json([
            'status'  => 'success',
            'message' => $this->ownerText('fleet.transfer.success', $locale, 'Car transferred successfully.'),
            'data'    => [
                'car_id'          => $car->id,
                'new_branch_id'   => $newBranch->id,
                'new_branch_name' => $newBranch->name,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Bulk Transfer Branch
    // ─────────────────────────────────────────────────────────────

    public function bulkTransfer(Request $request): JsonResponse
    {
        $user   = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $tenantId = (int) $user->tenant_id;

        $validated = $request->validate([
            'car_ids'   => ['required', 'array', 'min:1'],
            'car_ids.*' => [
                'required',
                'integer',
                Rule::exists('cars', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ]);

        $newBranchId = (int) $validated['branch_id'];
        $newBranch = Branch::query()->where('tenant_id', $tenantId)->findOrFail($newBranchId);

        $cars = Car::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $validated['car_ids'])
            ->get();

        foreach ($cars as $car) {
            abort_unless($this->branchAccess->canAccessBranchId($user, $car->branch_id ? (int) $car->branch_id : null), 403);
        }

        $transferredCount = 0;
        $transferredIds = [];

        foreach ($cars as $car) {
            if ($car->branch_id && (int) $car->branch_id === $newBranchId) {
                continue;
            }
            $car->update(['branch_id' => $newBranchId]);
            $transferredCount++;
            $transferredIds[] = $car->id;
        }

        $message = str_replace(
            [':count', ':branch'],
            [$transferredCount, $newBranch->name],
            $this->ownerText('fleet.transfer.bulk_success', $locale, 'Successfully transferred :count cars to :branch.')
        );

        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => [
                'transferred_car_ids' => $transferredIds,
                'new_branch_id'       => $newBranch->id,
                'new_branch_name'     => $newBranch->name,
            ],
        ]);
    }

    private function authorizedOwner(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(!empty($user->tenant_id), 403);
        abort_unless($this->branchAccess->canAccessAllBranches($user), 403);

        return $user;
    }

    private function resolveOwnerBranchId(Request $request, User $user): ?int
    {
        $branchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));

        if (!$branchId) {
            return null;
        }

        $exists = Branch::query()
            ->where('tenant_id', (int) $user->tenant_id)
            ->whereKey($branchId)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'branch_id' => [$this->ownerText('errors.branch_invalid', $this->resolveLocale($request), 'Selected branch is invalid or not accessible.')],
            ]);
        }

        return $branchId;
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(int $tenantId, ?int $branchId): array
    {
        $query = Car::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as aggregate');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    private function reservationCount(int $tenantId, ?int $branchId): int
    {
        return Reservation::query()
            ->where('tenant_id', $tenantId)
            ->when($branchId, fn (Builder $query): Builder => $query->whereHas(
                'car',
                fn (Builder $carQuery): Builder => $carQuery->where('branch_id', $branchId)
            ))
            ->count();
    }

    /**
     * @return array<int, float>
     */
    private function monthlyRevenueByCar(int $tenantId, ?int $branchId, ?int $carId = null): array
    {
        $start = Carbon::today()->subDays(30)->startOfDay();
        $end = Carbon::today()->endOfDay();

        $query = Payment::query()
            ->withoutGlobalScope('tenant')
            ->join('reservations', 'reservations.id', '=', 'payments.reservation_id')
            ->where('payments.tenant_id', $tenantId)
            ->where('payments.status', PaymentStatus::COMPLETED->value)
            ->whereBetween(DB::raw('COALESCE(payments.processed_at, payments.created_at)'), [$start, $end])
            ->when($branchId, fn ($query) => $query->whereExists(function ($branchQuery) use ($branchId): void {
                $branchQuery->selectRaw('1')
                    ->from('cars')
                    ->whereColumn('cars.id', 'reservations.car_id')
                    ->where('cars.branch_id', $branchId);
            }))
            ->when($carId, fn ($query) => $query->where('reservations.car_id', $carId))
            ->groupBy('reservations.car_id')
            ->selectRaw('reservations.car_id, SUM(COALESCE(payments.base_amount, payments.amount, 0) - COALESCE(payments.refunded_amount, 0)) as revenue');

        return $query
            ->pluck('revenue', 'reservations.car_id')
            ->map(fn (mixed $value): float => max(0.0, round((float) $value, 2)))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function nextEventsByCar(int $tenantId, ?int $branchId, ?int $carId = null): array
    {
        $today = Carbon::today();

        $reservationRows = Reservation::query()
            ->with(['user:id,name,email'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [
                ReservationStatus::PENDING->value,
                ReservationStatus::CONFIRMED->value,
                ReservationStatus::ACTIVE->value,
                ReservationStatus::COMPLETED_WAIT_CONTRACT->value,
            ])
            ->where(function (Builder $query) use ($today): void {
                $query
                    ->whereDate('start_date', '>=', $today->toDateString())
                    ->orWhereDate('end_date', '>=', $today->toDateString());
            })
            ->when($branchId, fn (Builder $query): Builder => $query->whereHas('car', fn (Builder $carQuery): Builder => $carQuery->where('branch_id', $branchId)))
            ->when($carId, fn (Builder $query): Builder => $query->where('car_id', $carId))
            ->orderBy('start_date')
            ->orderBy('pickup_time')
            ->get();

        $contractRows = Contract::query()
            ->with(['reservation.user:id,name,email'])
            ->where('tenant_id', $tenantId)
            ->where('status', ContractStatus::ACTIVE->value)
            ->where(function (Builder $query) use ($today): void {
                $query
                    ->whereDate('end_date', '>=', $today->toDateString())
                    ->orWhereHas('reservation', fn (Builder $reservationQuery): Builder => $reservationQuery->whereDate('end_date', '>=', $today->toDateString()));
            })
            ->whereHas('reservation')
            ->when($branchId, fn (Builder $query): Builder => $query->where(function (Builder $branchQuery) use ($branchId): void {
                $branchQuery
                    ->where('branch_id', $branchId)
                    ->orWhereHas('reservation.car', fn (Builder $carQuery): Builder => $carQuery->where('branch_id', $branchId));
            }))
            ->when($carId, fn (Builder $query): Builder => $query->whereHas('reservation', fn (Builder $reservationQuery): Builder => $reservationQuery->where('car_id', $carId)))
            ->orderBy('end_date')
            ->get();

        $events = [];

        foreach ($reservationRows as $reservation) {
            $groupCarId = (int) $reservation->car_id;
            if ($groupCarId <= 0) {
                continue;
            }

            $status = $reservation->status instanceof ReservationStatus
                ? $reservation->status->value
                : (string) $reservation->status;

            if (in_array($status, [ReservationStatus::PENDING->value, ReservationStatus::CONFIRMED->value], true)
                && $this->dateIsTodayOrLater($reservation->start_date, $today)) {
                $this->rememberNearestEvent($events, $groupCarId, [
                    'type' => 'reservation',
                    'label_key' => 'fleet.next_events.next_reservation',
                    'date' => $reservation->start_date?->toDateString(),
                    'time' => $reservation->pickup_time?->format('H:i'),
                    'reservation_id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'client_name' => $reservation->user?->name,
                ]);
            }

            if (in_array($status, [ReservationStatus::ACTIVE->value, ReservationStatus::COMPLETED_WAIT_CONTRACT->value], true)
                && $this->dateIsTodayOrLater($reservation->end_date, $today)) {
                $this->rememberNearestEvent($events, $groupCarId, [
                    'type' => 'return',
                    'label_key' => 'fleet.next_events.return_date',
                    'date' => $reservation->end_date?->toDateString(),
                    'time' => $reservation->return_time?->format('H:i'),
                    'reservation_id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'client_name' => $reservation->user?->name,
                ]);
            }
        }

        foreach ($contractRows as $contract) {
            $groupCarId = (int) $contract->reservation?->car_id;
            if ($groupCarId <= 0) {
                continue;
            }

            $this->rememberNearestEvent($events, $groupCarId, [
                'type' => 'return',
                'label_key' => 'fleet.next_events.return_date',
                'date' => $contract->end_date?->toDateString() ?? $contract->reservation?->end_date?->toDateString(),
                'time' => $contract->reservation?->return_time?->format('H:i'),
                'contract_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'reservation_id' => $contract->reservation_id,
                'client_name' => $contract->reservation?->user?->name,
            ]);
        }

        return array_map(function (array $event): array {
            unset($event['_sort_key']);

            return $event;
        }, $events);
    }

    private function dateIsTodayOrLater(mixed $date, Carbon $today): bool
    {
        if (!$date) {
            return false;
        }

        return Carbon::parse($date)->toDateString() >= $today->toDateString();
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @param  array<string, mixed>  $event
     */
    private function rememberNearestEvent(array &$events, int $carId, array $event): void
    {
        if (empty($event['date'])) {
            return;
        }

        $event['_sort_key'] = sprintf('%s %s', (string) $event['date'], (string) ($event['time'] ?? '23:59'));

        if (!isset($events[$carId]) || strcmp((string) $event['_sort_key'], (string) ($events[$carId]['_sort_key'] ?? '9999-12-31 23:59')) < 0) {
            $events[$carId] = $event;
        }
    }

    private function carPayload(Car $car, string $locale, array $currency, float $monthlyRevenue, ?array $nextEvent): array
    {
        $status = $car->status instanceof CarStatus
            ? $car->status
            : CarStatus::tryFrom((string) $car->status);

        $statusValue = $status?->value ?? (string) $car->status;
        $nextEvent = $nextEvent ? array_merge($nextEvent, [
            'label' => $this->ownerText((string) $nextEvent['label_key'], $locale, (string) ($nextEvent['type'] ?? 'event')),
        ]) : null;
        if ($nextEvent) {
            unset($nextEvent['label_key']);
        }

        return [
            'id' => $car->id,
            'make' => $car->make,
            'model' => $car->model,
            'year' => $car->year,
            'name' => trim(sprintf('%s %s %s', (string) $car->year, (string) $car->make, (string) $car->model)),
            'display_name' => trim(sprintf('%s %s %s', (string) $car->make, (string) $car->model, (string) $car->year)),
            'image_url' => $this->absoluteUrl($car->image_url),
            'license_plate' => $car->license_plate,
            'license_plate_format' => $car->license_plate_format,
            'status' => $statusValue,
            'status_label' => $this->statusLabel($statusValue, $locale),
            'status_color' => $status?->color() ?? '#6B7280',
            'branch' => [
                'id' => $car->branch_id,
                'name' => $car->branch?->name,
                'country' => $car->branch?->country,
                'city' => $car->branch?->city,
                'location_label' => $this->branchLocationLabel($car),
            ],
            'category' => $car->seats ? $car->seats.' seats' : null,
            'description' => $car->localizedDescription($locale),
            'transmission' => $car->transmission,
            'fuel_type' => $car->fuel_type instanceof \BackedEnum ? $car->fuel_type->value : (string) $car->fuel_type,
            'price_per_day' => (float) $car->price_per_day,
            'formatted_price_per_day' => $this->formatMoney((float) $car->price_per_day, $currency),
            'monthly_revenue' => $monthlyRevenue,
            'formatted_monthly_revenue' => $this->formatMoney($monthlyRevenue, $currency),
            'next_event' => $nextEvent,
            'updated_at' => $car->updated_at?->toIso8601String(),
        ];
    }

    private function summaryPayload(array $statusCounts, int $reservationCount, string $locale): array
    {
        return [
            'total_cars' => array_sum($statusCounts),
            'total_cars_label' => $this->ownerText('fleet.summary.total_cars', $locale, 'Total cars'),
            'total_reservations' => $reservationCount,
            'total_reservations_label' => $this->ownerText('fleet.summary.total_reservations', $locale, 'Total reservations'),
            'available_cars' => (int) ($statusCounts[CarStatus::AVAILABLE->value] ?? 0),
            'rented_cars' => (int) ($statusCounts[CarStatus::RENTED->value] ?? 0),
            'maintenance_cars' => (int) ($statusCounts[CarStatus::MAINTENANCE->value] ?? 0),
        ];
    }

    private function statusesPayload(array $statusCounts, string $locale): array
    {
        $all = [[
            'value' => 'all',
            'label' => $this->ownerText('fleet.statuses.all', $locale, 'All'),
            'color' => '#0F2F7F',
            'count' => array_sum($statusCounts),
        ]];

        $statuses = collect(CarStatus::cases())
            ->map(fn (CarStatus $status): array => [
                'value' => $status->value,
                'label' => $this->statusLabel($status->value, $locale),
                'color' => $status->color(),
                'count' => (int) ($statusCounts[$status->value] ?? 0),
            ])
            ->values()
            ->all();

        return array_merge($all, $statuses);
    }

    private function carStats(int $tenantId, int $carId): array
    {
        return [
            'reservations_count' => Reservation::query()
                ->where('tenant_id', $tenantId)
                ->where('car_id', $carId)
                ->count(),
            'active_reservations_count' => Reservation::query()
                ->where('tenant_id', $tenantId)
                ->where('car_id', $carId)
                ->whereIn('status', [ReservationStatus::CONFIRMED->value, ReservationStatus::ACTIVE->value])
                ->count(),
            'completed_reservations_count' => Reservation::query()
                ->where('tenant_id', $tenantId)
                ->where('car_id', $carId)
                ->where('status', ReservationStatus::COMPLETED->value)
                ->count(),
        ];
    }

    private function recentReservations(int $tenantId, int $carId, string $locale, array $currency): array
    {
        return Reservation::query()
            ->with(['user:id,name,email'])
            ->where('tenant_id', $tenantId)
            ->where('car_id', $carId)
            ->latest('start_date')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Reservation $reservation): array => [
                'id' => $reservation->id,
                'reservation_number' => $reservation->reservation_number,
                'client_name' => $reservation->user?->name,
                'start_date' => $reservation->start_date?->toDateString(),
                'end_date' => $reservation->end_date?->toDateString(),
                'pickup_time' => $reservation->pickup_time?->format('H:i'),
                'return_time' => $reservation->return_time?->format('H:i'),
                'total_amount' => (float) $reservation->total_amount,
                'formatted_total_amount' => $this->formatMoney((float) $reservation->total_amount, $currency),
                'status' => $reservation->status instanceof ReservationStatus ? $reservation->status->value : (string) $reservation->status,
                'status_label' => $this->reservationStatusLabel($reservation->status instanceof ReservationStatus ? $reservation->status->value : (string) $reservation->status, $locale),
            ])
            ->values()
            ->all();
    }

    private function statusLabel(string $status, string $locale): string
    {
        return $this->ownerText('fleet.statuses.'.$status, $locale, ucfirst(str_replace('_', ' ', $status)));
    }

    private function reservationStatusLabel(string $status, string $locale): string
    {
        $key = 'api.reservation_statuses.'.$status;
        $translated = trans($key, [], $locale);

        return is_string($translated) && $translated !== $key
            ? $translated
            : ucfirst(str_replace('_', ' ', $status));
    }

    private function branchLocationLabel(Car $car): ?string
    {
        $parts = array_values(array_filter([
            $car->branch?->country,
            $car->branch?->city,
        ], fn (mixed $value): bool => trim((string) $value) !== ''));

        return $parts ? implode(' - ', $parts) : null;
    }

    private function absoluteUrl(string $url): string
    {
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        return url($url);
    }

    private function formatMoney(float $amount, array $currency): string
    {
        $symbol = trim((string) ($currency['symbol'] ?? $currency['code'] ?? ''));

        return trim($symbol.' '.number_format($amount, 2));
    }

    private function ownerText(string $key, string $locale, string $fallback): string
    {
        $translationKey = 'owner_api.'.$key;
        $fileKey = 'site.'.$translationKey;
        $fileFallback = trans($fileKey, [], $locale);

        if (!is_string($fileFallback) || $fileFallback === $fileKey) {
            $fileFallback = $fallback;
        }

        return TenantTranslations::get($translationKey, $locale, $fileFallback);
    }

    private function occupancyRateByCar(int $tenantId, Car $car): array
    {
        $today = Carbon::today();
        $start = $today->copy()->subDays(30)->startOfDay();
        $end = $today->copy()->endOfDay();
        $totalDays = 30;

        $rentedDays = Reservation::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('car_id', $car->id)
            ->whereIn('status', [
                ReservationStatus::CONFIRMED->value,
                ReservationStatus::ACTIVE->value,
                ReservationStatus::COMPLETED->value,
            ])
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get(['start_date', 'end_date'])
            ->sum(function (Reservation $res) use ($start, $end): int {
                $resStart = Carbon::parse($res->start_date)->max($start);
                $resEnd = Carbon::parse($res->end_date)->min($end);

                return max(1, (int) $resStart->diffInDays($resEnd) + 1);
            });

        $percent = min(100, (int) round(($rentedDays / $totalDays) * 100));

        return [
            'rate' => $percent,
            'formatted_rate' => $percent.'%',
        ];
    }

    private function upcomingReservationsSummary(int $tenantId, Car $car, string $locale): array
    {
        $today = Carbon::today();

        $upcoming = Reservation::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('car_id', $car->id)
            ->whereIn('status', [
                ReservationStatus::PENDING->value,
                ReservationStatus::CONFIRMED->value,
                ReservationStatus::ACTIVE->value,
            ])
            ->where('start_date', '>=', $today->toDateString())
            ->orderBy('start_date')
            ->orderBy('pickup_time')
            ->get();

        $count = $upcoming->count();
        $nearest = $upcoming->first();

        $nearestDateLabel = null;
        if ($nearest && $nearest->start_date) {
            $formattedDate = Carbon::parse($nearest->start_date)->locale($locale)->isoFormat('D MMMM');
            $formattedTime = $nearest->pickup_time ? Carbon::parse($nearest->pickup_time)->locale($locale)->isoFormat('h:mm a') : null;
            $nearestDateLabel = trim($formattedDate.($formattedTime ? '، '.$formattedTime : ''));
        }

        $subtitleText = $count === 1
            ? $this->ownerText('fleet.show.upcoming_one', $locale, '1 upcoming reservation')
            : ($count > 1
                ? sprintf($this->ownerText('fleet.show.upcoming_plural', $locale, '%d upcoming reservations'), $count)
                : $this->ownerText('fleet.show.no_upcoming', $locale, 'No upcoming reservations'));

        return [
            'count' => $count,
            'subtitle' => $subtitleText,
            'nearest_date_label' => $nearestDateLabel,
        ];
    }

    private function lastMaintenanceSummary(int $tenantId, Car $car, string $locale): array
    {
        $lastMaintenance = CarMaintenance::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('car_id', $car->id)
            ->latest('completed_at')
            ->latest('scheduled_date')
            ->latest('id')
            ->first();

        $count = CarMaintenance::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('car_id', $car->id)
            ->count();

        if (!$lastMaintenance) {
            return [
                'count' => $count,
                'date' => null,
                'formatted_date' => null,
                'days_ago' => null,
                'days_ago_label' => $this->ownerText('fleet.show.no_maintenance', $locale, 'No maintenance recorded'),
                'status' => 'good',
                'status_label' => $this->ownerText('fleet.show.status_good', $locale, 'Good'),
                'status_color' => '#10B981',
            ];
        }

        $date = $lastMaintenance->completed_at ?? $lastMaintenance->scheduled_date ?? $lastMaintenance->created_at;
        $daysAgo = $date ? (int) max(0, Carbon::parse($date)->diffInDays(Carbon::today())) : 0;

        return [
            'count' => $count,
            'date' => $date?->toDateString(),
            'formatted_date' => $date ? Carbon::parse($date)->locale($locale)->isoFormat('D MMMM YYYY') : null,
            'days_ago' => $daysAgo,
            'days_ago_label' => sprintf($this->ownerText('fleet.show.days_ago', $locale, '%d days ago'), $daysAgo),
            'status' => 'good',
            'status_label' => $this->ownerText('fleet.show.status_good', $locale, 'Good'),
            'status_color' => '#10B981',
        ];
    }

    private function damageRecordSummary(int $tenantId, Car $car, string $locale): array
    {
        $damageCount = CarDamageReport::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('car_id', $car->id)
            ->count() + CarDamageCase::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('car_id', $car->id)
            ->count();

        $description = $damageCount === 0
            ? $this->ownerText('fleet.show.no_damages', $locale, 'No registered damages')
            : sprintf($this->ownerText('fleet.show.damages_count', $locale, '%d registered damages'), $damageCount);

        $status = $damageCount === 0 ? 'excellent' : 'attention_needed';
        $statusLabel = $damageCount === 0
            ? $this->ownerText('fleet.show.status_excellent', $locale, 'Excellent')
            : $this->ownerText('fleet.show.status_attention', $locale, 'Attention needed');
        $statusColor = $damageCount === 0 ? '#10B981' : '#EF4444';

        return [
            'count' => $damageCount,
            'description' => $description,
            'status' => $status,
            'status_label' => $statusLabel,
            'status_color' => $statusColor,
        ];
    }

    private function resolveLocale(Request $request): string
    {
        $supportedLocales = array_values(array_filter(
            (array) config('app.available_locales', ['en']),
            static fn ($locale) => is_string($locale) && $locale !== ''
        ));
        $fallback = (string) config('app.fallback_locale', config('app.locale', 'en'));
        $preferred = $request->getPreferredLanguage($supportedLocales);

        if (is_string($preferred) && $preferred !== '') {
            return $preferred;
        }

        return in_array($fallback, $supportedLocales, true) ? $fallback : ($supportedLocales[0] ?? 'en');
    }

    private function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->integer('per_page', 20)));
    }

    private function paginationPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->total() > 0 ? $paginator->firstItem() : null,
            'to' => $paginator->total() > 0 ? $paginator->lastItem() : null,
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    private function syncCarStatusForMaintenance(Car $car): void
    {
        $car->refresh();

        $hasInProgress = CarMaintenance::query()
            ->withoutGlobalScope('tenant')
            ->where('car_id', $car->id)
            ->where('status', MaintenanceRecordStatus::IN_PROGRESS->value)
            ->exists();

        if ($hasInProgress && $car->status !== CarStatus::MAINTENANCE) {
            $car->update(['status' => CarStatus::MAINTENANCE->value]);

            return;
        }

        if (!$hasInProgress && $car->status === CarStatus::MAINTENANCE) {
            $car->update(['status' => CarStatus::AVAILABLE->value]);
        }
    }
}
