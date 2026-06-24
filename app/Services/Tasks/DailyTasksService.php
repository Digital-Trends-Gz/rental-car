<?php

namespace App\Services\Tasks;

use App\Enums\CarStatus;
use App\Enums\ContractStatus;
use App\Enums\MaintenanceRecordStatus;
use App\Enums\ReservationStatus;
use App\Models\Car;
use App\Models\CarMaintenance;
use App\Models\Contract;
use App\Models\DailyTaskStatus;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Rentals\RentalStatusSyncService;
use App\Support\BranchAccess;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DailyTasksService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        private readonly BranchAccess $branchAccess,
        private readonly RentalStatusSyncService $rentalStatusSync,
    ) {
    }

    public function timeline(User $user, ?CarbonInterface $date = null, ?int $branchId = null, ?string $type = null, string $locale = 'en'): array
    {
        $date = $date ? Carbon::parse($date)->startOfDay() : Carbon::today();
        $tenantId = (int) ($user->tenant_id ?? 0);
        $type = $type === 'all' ? null : $type;

        $tasks = collect();

        if ($type === null || $type === 'pickup') {
            $tasks = $tasks->merge($this->pickupTasks($user, $tenantId, $date, $branchId, $locale));
        }

        if ($type === null || $type === 'return') {
            $tasks = $tasks->merge($this->returnTasks($user, $tenantId, $date, $branchId, $locale));
        }

        if ($type === null || $type === 'maintenance') {
            $tasks = $tasks->merge($this->maintenanceTasks($user, $tenantId, $date, $branchId, $locale));
            $tasks = $tasks->merge($this->carStatusTasks($user, $tenantId, $date, $branchId, $locale)->where('task_type', 'maintenance'));
        }

        if ($type === null || $type === 'cleaning') {
            $tasks = $tasks->merge($this->carStatusTasks($user, $tenantId, $date, $branchId, $locale)->where('task_type', 'cleaning'));
        }

        if ($type === null || in_array($type, ['pickup', 'return'], true)) {
            $tasks = $tasks->merge($this->completedWorkflowTasks($user, $tenantId, $date, $branchId, $type, $locale));
        }

        $tasks = $tasks
            ->unique(fn (array $task) => $this->statusKey($task['task_type'], $task['source_type'], (int) $task['source_id']))
            ->values();

        $statusMap = $this->statusMap($tenantId, $tasks);

        $tasks = $tasks
            ->map(fn (array $task) => $this->applyStoredStatus($task, $statusMap, $locale))
            ->sortBy(fn (array $task) => $this->taskTimeSortValue($task))
            ->values();

        $completed = $tasks->where('status', self::STATUS_COMPLETED)->count();
        $inProgress = $tasks->where('status', self::STATUS_IN_PROGRESS)->count();
        $late = $tasks->where('is_late', true)->count();
        $total = $tasks->count();

        return [
            'date' => $date->toDateString(),
            'branch_id' => $branchId,
            'type' => $type ?? 'all',
            'progress' => [
                'total' => $total,
                'completed' => $completed,
                'in_progress' => $inProgress,
                'pending' => max(0, $total - $completed - $inProgress),
                'late' => $late,
                'percentage' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            ],
            'filters' => [
                ['value' => 'all', 'label' => $this->translate($locale, 'All', 'الكل')],
                ['value' => 'pickup', 'label' => $this->translate($locale, 'Pickup', 'تسليم')],
                ['value' => 'return', 'label' => $this->translate($locale, 'Return', 'استلام')],
                ['value' => 'maintenance', 'label' => $this->translate($locale, 'Maintenance', 'صيانة')],
                ['value' => 'cleaning', 'label' => $this->translate($locale, 'Cleaning', 'تنظيف')],
            ],
            'tasks' => $tasks->all(),
        ];
    }

    public function start(User $user, string $taskType, string $sourceType, int $sourceId, ?string $notes = null): DailyTaskStatus
    {
        return $this->setStatus($user, $taskType, $sourceType, $sourceId, self::STATUS_IN_PROGRESS, $notes);
    }

    public function complete(User $user, string $taskType, string $sourceType, int $sourceId, ?string $notes = null): DailyTaskStatus
    {
        $status = $this->setStatus($user, $taskType, $sourceType, $sourceId, self::STATUS_COMPLETED, $notes);

        $this->completeSourceSideEffects($user, $taskType, $sourceType, $sourceId);

        return $status;
    }

    public function schedule(User $user, string $taskType, string $sourceType, int $sourceId, CarbonInterface $scheduledAt, ?string $notes = null): DailyTaskStatus
    {
        if (!in_array($taskType, ['cleaning', 'maintenance'], true)) {
            abort(422, 'Only cleaning and maintenance tasks can have a manual task time.');
        }

        if (!in_array($sourceType, ['car', 'maintenance'], true)) {
            abort(422, 'Only car and maintenance task sources can have a manual task time.');
        }

        $tenantId = (int) ($user->tenant_id ?? 0);

        return DailyTaskStatus::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'task_type' => $taskType,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ],
            array_filter([
                'scheduled_at' => Carbon::parse($scheduledAt),
                'notes' => $notes,
            ], static fn ($value) => $value !== null)
        )->refresh();
    }

    private function completeSourceSideEffects(User $user, string $taskType, string $sourceType, int $sourceId): void
    {
        $tenantId = (int) ($user->tenant_id ?? 0);

        if ($sourceType === 'car' && in_array($taskType, ['cleaning', 'maintenance'], true)) {
            $car = Car::query()
                ->where('tenant_id', $tenantId)
                ->find($sourceId);

            if ($car) {
                $this->releaseCarAfterOperationalTask($car);
            }

            return;
        }

        if ($sourceType !== 'maintenance' || $taskType !== 'maintenance') {
            return;
        }

        $maintenance = CarMaintenance::query()
            ->with('car')
            ->where('tenant_id', $tenantId)
            ->find($sourceId);

        if (!$maintenance) {
            return;
        }

        if ($this->enumValue($maintenance->status) !== MaintenanceRecordStatus::COMPLETED->value) {
            $maintenance->forceFill([
                'status' => MaintenanceRecordStatus::COMPLETED->value,
                'completed_at' => $maintenance->completed_at ?? now(),
            ])->save();
        }

        if ($maintenance->car) {
            $this->releaseCarAfterOperationalTask($maintenance->car, (int) $maintenance->id);
        }
    }

    private function releaseCarAfterOperationalTask(Car $car, ?int $completedMaintenanceId = null): void
    {
        $currentStatus = $this->enumValue($car->status);

        if (!in_array($currentStatus, [CarStatus::CLEANING->value, CarStatus::MAINTENANCE->value], true)) {
            return;
        }

        if ($currentStatus === CarStatus::MAINTENANCE->value && $this->hasOpenMaintenance($car, $completedMaintenanceId)) {
            return;
        }

        $targetStatus = $this->rentalStatusSync->targetStatusForCar((int) $car->id);
        $car->forceFill(['status' => $targetStatus->value])->saveQuietly();
    }

    private function hasOpenMaintenance(Car $car, ?int $completedMaintenanceId = null): bool
    {
        return CarMaintenance::query()
            ->where('car_id', $car->id)
            ->when($completedMaintenanceId, fn (Builder $query) => $query->where('id', '!=', $completedMaintenanceId))
            ->whereNotIn('status', [
                MaintenanceRecordStatus::COMPLETED->value,
                MaintenanceRecordStatus::CANCELLED->value,
            ])
            ->exists();
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }

    private function setStatus(User $user, string $taskType, string $sourceType, int $sourceId, string $status, ?string $notes = null): DailyTaskStatus
    {
        $tenantId = (int) ($user->tenant_id ?? 0);

        $task = DailyTaskStatus::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'task_type' => $taskType,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ],
            array_filter([
                'status' => $status,
                'started_at' => $status === self::STATUS_IN_PROGRESS ? now() : null,
                'started_by' => $status === self::STATUS_IN_PROGRESS ? $user->id : null,
                'completed_at' => $status === self::STATUS_COMPLETED ? now() : null,
                'completed_by' => $status === self::STATUS_COMPLETED ? $user->id : null,
                'notes' => $notes,
            ], static fn ($value) => $value !== null)
        );

        if ($status === self::STATUS_COMPLETED && !$task->started_at) {
            $task->forceFill([
                'started_at' => now(),
                'started_by' => $user->id,
            ])->save();
        }

        return $task->refresh();
    }

    private function pickupTasks(User $user, int $tenantId, CarbonInterface $date, ?int $branchId, string $locale): Collection
    {
        $query = Reservation::query()
            ->with([
                'user:id,name,email',
                'car:id,tenant_id,branch_id,year,make,model,license_plate',
                'car.branch:id,name',
                'contract:id,reservation_id,status',
            ])
            ->where('tenant_id', $tenantId)
            ->whereDate('start_date', $date)
            ->whereIn('status', [
                ReservationStatus::CONFIRMED->value,
                ReservationStatus::ACTIVE->value,
                ReservationStatus::COMPLETED_WAIT_CONTRACT->value,
            ]);

        $this->applyReservationBranchScope($query, $user, $branchId);

        return $query->get()->map(function (Reservation $reservation) use ($date, $locale) {
            $scheduledAt = $this->scheduledAt($date, $reservation->pickup_time, '09:00');
            $car = $reservation->car;

            return $this->baseTask(
                locale: $locale,
                taskType: 'pickup',
                sourceType: 'reservation',
                sourceId: (int) $reservation->id,
                titleEn: 'Car pickup',
                titleAr: 'تسليم سيارة',
                scheduledAt: $scheduledAt,
                car: $car,
                client: $reservation->user,
                reference: $reservation->reservation_number,
                location: (string) ($reservation->pickup_location ?? ''),
                sourceStatus: $reservation->status instanceof ReservationStatus ? $reservation->status->value : (string) $reservation->status,
                description: trim(sprintf('%s - %s', $car?->full_name ?? '', $reservation->user?->name ?? '')),
                reservationId: (int) $reservation->id,
                contractId: $reservation->contract ? (int) $reservation->contract->id : null,
                actionUrl: $reservation->contract
                    ? url('/admin/contracts/'.$reservation->contract->id.'/edit')
                    : url('/admin/contracts/create?reservation_id='.$reservation->id),
            );
        });
    }

    private function returnTasks(User $user, int $tenantId, CarbonInterface $date, ?int $branchId, string $locale): Collection
    {
        $query = Contract::query()
            ->with([
                'reservation:id,tenant_id,reservation_number,user_id,car_id,return_time,return_location',
                'reservation.user:id,name,email',
                'reservation.car:id,tenant_id,branch_id,year,make,model,license_plate',
                'reservation.car.branch:id,name',
                'branch:id,name',
            ])
            ->where('tenant_id', $tenantId)
            ->where('status', ContractStatus::ACTIVE->value)
            ->whereNotNull('reservation_id')
            ->whereDate('end_date', $date);

        $this->applyContractBranchScope($query, $user, $branchId);

        return $query->get()->map(function (Contract $contract) use ($date, $locale) {
            $reservation = $contract->reservation;
            $car = $reservation?->car;
            $scheduledDate = $contract->end_date ? Carbon::parse($contract->end_date) : $date;
            $scheduledAt = $this->scheduledAt($scheduledDate, $reservation?->return_time, '18:00');

            return $this->baseTask(
                locale: $locale,
                taskType: 'return',
                sourceType: 'contract',
                sourceId: (int) $contract->id,
                titleEn: 'Car return',
                titleAr: 'استلام سيارة',
                scheduledAt: $scheduledAt,
                car: $car,
                client: $reservation?->user,
                reference: $contract->contract_number,
                location: (string) ($reservation?->return_location ?? ''),
                sourceStatus: $contract->status instanceof ContractStatus ? $contract->status->value : (string) $contract->status,
                description: trim(sprintf('%s - %s', $car?->full_name ?? $contract->car_details ?? '', $reservation?->user?->name ?? $contract->renter_name ?? '')),
                reservationId: $reservation ? (int) $reservation->id : null,
                contractId: (int) $contract->id,
                actionUrl: url('/admin/contracts/'.$contract->id.'/return-status-report'),
            );
        });
    }

    private function maintenanceTasks(User $user, int $tenantId, CarbonInterface $date, ?int $branchId, string $locale): Collection
    {
        $query = CarMaintenance::query()
            ->with(['car:id,tenant_id,branch_id,year,make,model,license_plate', 'car.branch:id,name', 'branch:id,name', 'maintenanceType:id,name'])
            ->where('tenant_id', $tenantId)
            ->whereDate('scheduled_date', '<=', $date)
            ->whereNotIn('status', [
                MaintenanceRecordStatus::COMPLETED->value,
                MaintenanceRecordStatus::CANCELLED->value,
            ]);

        $this->applyDirectBranchScope($query, $user, $branchId);

        return $query->get()->map(function (CarMaintenance $maintenance) use ($locale) {
            $scheduledAt = $maintenance->scheduled_date
                ? Carbon::parse($maintenance->scheduled_date)->setTime(9, 0)
                : Carbon::today()->setTime(9, 0);
            $car = $maintenance->car;
            $typeName = (string) ($maintenance->maintenanceType?->name ?? '');

            return $this->baseTask(
                locale: $locale,
                taskType: 'maintenance',
                sourceType: 'maintenance',
                sourceId: (int) $maintenance->id,
                titleEn: 'Car maintenance',
                titleAr: 'صيانة سيارة',
                scheduledAt: $scheduledAt,
                car: $car,
                client: null,
                reference: $typeName !== '' ? $typeName : ('MNT-'.$maintenance->id),
                location: (string) ($maintenance->workshop_name ?? $maintenance->branch?->name ?? ''),
                sourceStatus: $maintenance->status instanceof MaintenanceRecordStatus ? $maintenance->status->value : (string) $maintenance->status,
                description: trim(sprintf('%s - %s', $car?->full_name ?? '', $typeName)),
            );
        });
    }

    private function baseTask(
        string $locale,
        string $taskType,
        string $sourceType,
        int $sourceId,
        string $titleEn,
        string $titleAr,
        CarbonInterface $scheduledAt,
        mixed $car,
        mixed $client,
        ?string $reference,
        string $location,
        string $sourceStatus,
        string $description,
        ?int $reservationId = null,
        ?int $contractId = null,
        ?string $actionUrl = null,
    ): array {
        return [
            'id' => $taskType.'-'.$sourceId,
            'task_type' => $taskType,
            'task_type_label' => $this->translate($locale, $titleEn, $titleAr),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reservation_id' => $reservationId,
            'contract_id' => $contractId,
            'reference' => $reference,
            'title' => $this->translate($locale, $titleEn, $titleAr),
            'description' => $description,
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'scheduled_date' => $scheduledAt->toDateString(),
            'scheduled_time' => $scheduledAt->format('H:i'),
            'location' => $location,
            'source_status' => $sourceStatus,
            'action_url' => $actionUrl,
            'car' => $car ? [
                'id' => $car->id,
                'name' => trim((string) ($car->full_name ?? sprintf('%s %s %s', $car->year ?? '', $car->make ?? '', $car->model ?? ''))),
                'license_plate' => (string) ($car->license_plate ?? ''),
                'branch_id' => $car->branch_id,
                'branch_name' => (string) ($car->branch?->name ?? ''),
                'image_url' => $car->image_url ?? null,
            ] : null,
            'client' => $client ? [
                'id' => $client->id,
                'name' => (string) $client->name,
                'email' => (string) $client->email,
            ] : null,
        ];
    }

    private function applyStoredStatus(array $task, Collection $statusMap, string $locale): array
    {
        $stored = $statusMap->get($this->statusKey($task['task_type'], $task['source_type'], (int) $task['source_id']));
        $task = $this->applyStoredSchedule($task, $stored);
        $status = $this->storedStatusForTask($task, $stored);
        $usesStoredLifecycle = $stored && $status === $stored->status;
        $isCompleted = $status === self::STATUS_COMPLETED;
        $scheduledAt = Carbon::parse($task['scheduled_at']);
        $isLate = !$isCompleted && $task['source_type'] !== 'car' && $scheduledAt->lt(now());

        return array_merge($task, [
            'status' => $status,
            'status_label' => $this->statusLabel($status, $locale),
            'computed_status' => $isLate ? 'late' : $status,
            'computed_status_label' => $isLate
                ? $this->translate($locale, 'Late', 'متأخرة')
                : $this->statusLabel($status, $locale),
            'is_late' => $isLate,
            'remaining_minutes' => $isCompleted ? 0 : now()->diffInMinutes($scheduledAt, false),
            'started_at' => $usesStoredLifecycle ? $stored?->started_at?->toIso8601String() : null,
            'completed_at' => $usesStoredLifecycle ? $stored?->completed_at?->toIso8601String() : null,
            'notes' => $usesStoredLifecycle ? $stored?->notes : null,
            'actions' => [
                'can_start' => in_array($status, [self::STATUS_PENDING, self::STATUS_CANCELLED], true),
                'can_complete' => $status === self::STATUS_IN_PROGRESS
                    && !in_array($task['task_type'], ['pickup', 'return'], true),
            ],
        ]);
    }

    private function applyStoredSchedule(array $task, ?DailyTaskStatus $stored): array
    {
        if (
            !$stored?->scheduled_at
            || !in_array($task['task_type'], ['cleaning', 'maintenance'], true)
            || !in_array($task['source_type'], ['car', 'maintenance'], true)
        ) {
            return $task;
        }

        $scheduledAt = Carbon::parse($stored->scheduled_at);

        $task['scheduled_at'] = $scheduledAt->toIso8601String();
        $task['scheduled_date'] = $scheduledAt->toDateString();
        $task['scheduled_time'] = $scheduledAt->format('H:i');

        return $task;
    }

    private function storedStatusForTask(array $task, ?DailyTaskStatus $stored): string
    {
        if (!$stored) {
            return self::STATUS_PENDING;
        }

        if (
            $task['source_type'] === 'car'
            && in_array($task['task_type'], ['cleaning', 'maintenance'], true)
            && $stored->status === self::STATUS_COMPLETED
            && in_array((string) ($task['source_status'] ?? ''), [CarStatus::CLEANING->value, CarStatus::MAINTENANCE->value], true)
            && $stored->completed_at
            && Carbon::parse($task['scheduled_at'])->gte($stored->completed_at)
        ) {
            return self::STATUS_PENDING;
        }

        return $stored->status;
    }

    private function statusMap(int $tenantId, Collection $tasks): Collection
    {
        if ($tasks->isEmpty()) {
            return collect();
        }

        return DailyTaskStatus::query()
            ->where('tenant_id', $tenantId)
            ->where(function (Builder $query) use ($tasks) {
                foreach ($tasks as $task) {
                    $query->orWhere(function (Builder $inner) use ($task) {
                        $inner
                            ->where('task_type', $task['task_type'])
                            ->where('source_type', $task['source_type'])
                            ->where('source_id', $task['source_id']);
                    });
                }
            })
            ->get()
            ->keyBy(fn (DailyTaskStatus $status) => $this->statusKey($status->task_type, $status->source_type, $status->source_id));
    }

    private function statusKey(string $taskType, string $sourceType, int $sourceId): string
    {
        return $taskType.'|'.$sourceType.'|'.$sourceId;
    }

    private function taskTimeSortValue(array $task): array
    {
        $scheduledAt = $task['scheduled_at'] ?? null;

        try {
            $timestamp = $scheduledAt ? Carbon::parse($scheduledAt)->timestamp : PHP_INT_MAX;
        } catch (\Throwable) {
            $timestamp = PHP_INT_MAX;
        }

        return [
            $timestamp,
            (string) ($task['task_type'] ?? ''),
            (int) ($task['source_id'] ?? 0),
        ];
    }

    private function scheduledAt(CarbonInterface $date, mixed $time, string $fallback): CarbonInterface
    {
        [$hour, $minute] = explode(':', $this->normalizeTime($time, $fallback));

        return Carbon::parse($date)->setTime((int) $hour, (int) $minute);
    }

    private function normalizeTime(mixed $time, string $fallback): string
    {
        if ($time instanceof CarbonInterface) {
            return $time->format('H:i');
        }

        $value = trim((string) $time);
        if ($value !== '' && preg_match('/(\d{1,2}):(\d{2})/', $value, $matches)) {
            return str_pad($matches[1], 2, '0', STR_PAD_LEFT).':'.$matches[2];
        }

        return $fallback;
    }

    private function applyReservationBranchScope(Builder $query, User $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->whereHas('car', fn (Builder $q) => $q->where('branch_id', $branchId));
            }
            return;
        }

        $userBranchId = (int) ($user->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('car', fn (Builder $q) => $q->where('branch_id', $userBranchId));
    }

    private function applyContractBranchScope(Builder $query, User $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->where(function (Builder $branchQuery) use ($branchId) {
                    $branchQuery
                        ->where('branch_id', $branchId)
                        ->orWhereHas('reservation.car', fn (Builder $q) => $q->where('branch_id', $branchId));
                });
            }
            return;
        }

        $userBranchId = (int) ($user->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function (Builder $branchQuery) use ($userBranchId) {
            $branchQuery
                ->where('branch_id', $userBranchId)
                ->orWhereHas('reservation.car', fn (Builder $q) => $q->where('branch_id', $userBranchId));
        });
    }

    private function applyDirectBranchScope(Builder $query, User $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
            return;
        }

        $userBranchId = (int) ($user->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where('branch_id', $userBranchId);
    }

    private function statusLabel(string $status, string $locale): string
    {
        return match ($status) {
            self::STATUS_IN_PROGRESS => $this->translate($locale, 'In progress', 'قيد التنفيذ'),
            self::STATUS_COMPLETED => $this->translate($locale, 'Completed', 'مكتملة'),
            self::STATUS_CANCELLED => $this->translate($locale, 'Cancelled', 'ملغاة'),
            default => $this->translate($locale, 'Pending', 'بانتظار البدء'),
        };
    }

    private function carStatusTasks(User $user, int $tenantId, CarbonInterface $date, ?int $branchId, string $locale): Collection
    {
        $isToday = $date->isToday();

        // 1. Get currently active cars in cleaning/maintenance status
        $activeCars = collect();
        if ($isToday) {
            $activeQuery = Car::query()
                ->with(['branch:id,name'])
                ->where('tenant_id', $tenantId)
                ->whereIn('status', [CarStatus::CLEANING->value, CarStatus::MAINTENANCE->value]);

            $this->applyDirectBranchScope($activeQuery, $user, $branchId);
            $activeCars = $activeQuery->get();
        }

        // 2. Get completed task statuses for cars on this date
        $completedTaskStatuses = DailyTaskStatus::query()
            ->where('tenant_id', $tenantId)
            ->where('source_type', 'car')
            ->whereIn('task_type', ['cleaning', 'maintenance'])
            ->whereDate('completed_at', $date)
            ->get();

        $completedCarIds = $completedTaskStatuses->pluck('source_id')->all();
        $completedCars = collect();
        if (!empty($completedCarIds)) {
            $completedQuery = Car::query()
                ->with(['branch:id,name'])
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $completedCarIds);

            $this->applyDirectBranchScope($completedQuery, $user, $branchId);
            $completedCars = $completedQuery->get()->keyBy('id');
        }

        $tasks = collect();

        // Add active cars
        foreach ($activeCars as $car) {
            $carStatus = $this->enumValue($car->status);
            $taskType = $carStatus === CarStatus::CLEANING->value ? 'cleaning' : 'maintenance';
            
            // If it's maintenance, check if it's already covered by an active CarMaintenance record to avoid duplicate
            if ($taskType === 'maintenance') {
                $hasActiveMaintenanceRecord = CarMaintenance::query()
                    ->where('car_id', $car->id)
                    ->whereDate('scheduled_date', '<=', $date)
                    ->whereNotIn('status', [
                        MaintenanceRecordStatus::COMPLETED->value,
                        MaintenanceRecordStatus::CANCELLED->value,
                    ])
                    ->exists();
                if ($hasActiveMaintenanceRecord) {
                    continue;
                }
            }

            $scheduledAt = $car->updated_at ? Carbon::parse($car->updated_at) : Carbon::today()->setTime(9, 0);

            $tasks->push($this->baseTask(
                locale: $locale,
                taskType: $taskType,
                sourceType: 'car',
                sourceId: (int) $car->id,
                titleEn: $taskType === 'cleaning' ? 'Car cleaning' : 'Car maintenance',
                titleAr: $taskType === 'cleaning' ? 'تنظيف سيارة' : 'صيانة سيارة',
                scheduledAt: $scheduledAt,
                car: $car,
                client: null,
                reference: $car->license_plate,
                location: (string) ($car->branch?->name ?? ''),
                sourceStatus: $carStatus,
                description: trim(sprintf('%s - %s', $car->full_name ?? '', $taskType === 'cleaning' ? 'Cleaning' : 'Maintenance')),
            ));
        }

        // Add completed cars (that are no longer in cleaning/maintenance status)
        foreach ($completedTaskStatuses as $statusRecord) {
            $car = $completedCars->get($statusRecord->source_id);
            if (!$car) {
                continue;
            }

            $taskType = $statusRecord->task_type;
            $scheduledAt = $statusRecord->started_at ?? $statusRecord->created_at ?? Carbon::today()->setTime(9, 0);

            // Avoid adding it again if it's already in the active list (e.g. if it was completed then put back to cleaning)
            if ($tasks->contains(fn ($t) => $t['task_type'] === $taskType && $t['source_id'] === $statusRecord->source_id)) {
                continue;
            }

            $tasks->push($this->baseTask(
                locale: $locale,
                taskType: $taskType,
                sourceType: 'car',
                sourceId: (int) $car->id,
                titleEn: $taskType === 'cleaning' ? 'Car cleaning' : 'Car maintenance',
                titleAr: $taskType === 'cleaning' ? 'تنظيف سيارة' : 'صيانة سيارة',
                scheduledAt: $scheduledAt,
                car: $car,
                client: null,
                reference: $car->license_plate,
                location: (string) ($car->branch?->name ?? ''),
                sourceStatus: $this->enumValue($car->status),
                description: trim(sprintf('%s - %s', $car->full_name ?? '', $taskType === 'cleaning' ? 'Cleaning' : 'Maintenance')),
            ));
        }

        return $tasks;
    }

    private function completedWorkflowTasks(User $user, int $tenantId, CarbonInterface $date, ?int $branchId, ?string $type, string $locale): Collection
    {
        $query = DailyTaskStatus::query()
            ->where('tenant_id', $tenantId)
            ->where('status', self::STATUS_COMPLETED)
            ->whereIn('task_type', $type ? [$type] : ['pickup', 'return'])
            ->whereIn('source_type', ['reservation', 'contract'])
            ->whereDate('completed_at', $date);

        $statuses = $query->get();
        if ($statuses->isEmpty()) {
            return collect();
        }

        $tasks = collect();

        $reservationIds = $statuses
            ->where('task_type', 'pickup')
            ->where('source_type', 'reservation')
            ->pluck('source_id')
            ->unique()
            ->values()
            ->all();

        if (!empty($reservationIds)) {
            $reservationsQuery = Reservation::query()
                ->with([
                    'user:id,name,email',
                    'car:id,tenant_id,branch_id,year,make,model,license_plate',
                    'car.branch:id,name',
                    'contract:id,reservation_id,status',
                ])
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $reservationIds);

            $this->applyReservationBranchScope($reservationsQuery, $user, $branchId);
            $reservations = $reservationsQuery->get()->keyBy('id');

            foreach ($statuses->where('task_type', 'pickup')->where('source_type', 'reservation') as $status) {
                $reservation = $reservations->get($status->source_id);
                if (!$reservation) {
                    continue;
                }

                $scheduledAt = $status->completed_at ?? $status->started_at ?? $status->created_at ?? Carbon::parse($date);
                $car = $reservation->car;

                $tasks->push($this->baseTask(
                    locale: $locale,
                    taskType: 'pickup',
                    sourceType: 'reservation',
                    sourceId: (int) $reservation->id,
                    titleEn: 'Car pickup',
                    titleAr: 'طھط³ظ„ظٹظ… ط³ظٹط§ط±ط©',
                    scheduledAt: Carbon::parse($scheduledAt),
                    car: $car,
                    client: $reservation->user,
                    reference: $reservation->reservation_number,
                    location: (string) ($reservation->pickup_location ?? ''),
                    sourceStatus: $reservation->status instanceof ReservationStatus ? $reservation->status->value : (string) $reservation->status,
                    description: trim(sprintf('%s - %s', $car?->full_name ?? '', $reservation->user?->name ?? '')),
                    reservationId: (int) $reservation->id,
                    contractId: $reservation->contract ? (int) $reservation->contract->id : null,
                    actionUrl: $reservation->contract
                        ? url('/admin/contracts/'.$reservation->contract->id.'/edit')
                        : url('/admin/contracts/create?reservation_id='.$reservation->id),
                ));
            }
        }

        $contractIds = $statuses
            ->where('task_type', 'return')
            ->where('source_type', 'contract')
            ->pluck('source_id')
            ->unique()
            ->values()
            ->all();

        if (!empty($contractIds)) {
            $contractsQuery = Contract::query()
                ->with([
                    'reservation:id,tenant_id,reservation_number,user_id,car_id,return_time,return_location',
                    'reservation.user:id,name,email',
                    'reservation.car:id,tenant_id,branch_id,year,make,model,license_plate',
                    'reservation.car.branch:id,name',
                    'branch:id,name',
                ])
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $contractIds);

            $this->applyContractBranchScope($contractsQuery, $user, $branchId);
            $contracts = $contractsQuery->get()->keyBy('id');

            foreach ($statuses->where('task_type', 'return')->where('source_type', 'contract') as $status) {
                $contract = $contracts->get($status->source_id);
                if (!$contract) {
                    continue;
                }

                $reservation = $contract->reservation;
                $car = $reservation?->car;
                $scheduledAt = $status->completed_at ?? $status->started_at ?? $status->created_at ?? Carbon::parse($date);

                $tasks->push($this->baseTask(
                    locale: $locale,
                    taskType: 'return',
                    sourceType: 'contract',
                    sourceId: (int) $contract->id,
                    titleEn: 'Car return',
                    titleAr: 'ط§ط³طھظ„ط§ظ… ط³ظٹط§ط±ط©',
                    scheduledAt: Carbon::parse($scheduledAt),
                    car: $car,
                    client: $reservation?->user,
                    reference: $contract->contract_number,
                    location: (string) ($reservation?->return_location ?? ''),
                    sourceStatus: $contract->status instanceof ContractStatus ? $contract->status->value : (string) $contract->status,
                    description: trim(sprintf('%s - %s', $car?->full_name ?? $contract->car_details ?? '', $reservation?->user?->name ?? $contract->renter_name ?? '')),
                    reservationId: $reservation ? (int) $reservation->id : null,
                    contractId: (int) $contract->id,
                    actionUrl: url('/admin/contracts/'.$contract->id.'/return-status-report'),
                ));
            }
        }

        return $tasks;
    }

    private function translate(string $locale, string $en, string $ar): string
    {
        return Str::startsWith(strtolower($locale), 'ar') ? $ar : $en;
    }
}
