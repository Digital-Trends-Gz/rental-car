<?php

namespace App\Services\Dashboard;

use App\Enums\CarStatus;
use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Contract;
use App\Models\OwnerDashboardMetricSnapshot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Tenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class OwnerDashboardMetricsService
{
    public const TODAY_REVENUE = 'today_revenue';
    public const AVAILABLE_CARS = 'available_cars';
    public const ACTIVE_RESERVATIONS = 'active_reservations';
    public const LATE_RETURNS = 'late_returns';
    public const RENTED_CARS = 'rented_cars';

    public const SNAPSHOT_METRICS = [
        self::TODAY_REVENUE,
        self::AVAILABLE_CARS,
        self::ACTIVE_RESERVATIONS,
        self::LATE_RETURNS,
        self::RENTED_CARS,
    ];

    /**
     * Build the current dashboard values for the given tenant/branch/date.
     *
     * These values are accurate for "now". Historical comparison accuracy comes
     * from persisted snapshots captured on the actual comparison date.
     *
     * @return array<string, float|int>
     */
    public function currentMetrics(int $tenantId, ?int $branchId, CarbonInterface $date): array
    {
        $date = Carbon::instance($date->toDateTimeImmutable());

        $carsQuery = Car::query()->where('tenant_id', $tenantId);
        $this->applyCarBranchScope($carsQuery, $branchId);

        $reservationsQuery = Reservation::query()->where('tenant_id', $tenantId);
        $this->applyReservationBranchScope($reservationsQuery, $branchId);

        $contractsQuery = Contract::query()->where('tenant_id', $tenantId);
        $this->applyContractBranchScope($contractsQuery, $branchId);

        $paymentsQuery = Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', PaymentStatus::COMPLETED->value);
        $this->applyPaymentBranchScope($paymentsQuery, $branchId);

        return [
            self::TODAY_REVENUE => $this->paymentTotalForDate((clone $paymentsQuery), $date),
            self::AVAILABLE_CARS => (clone $carsQuery)->where('status', CarStatus::AVAILABLE->value)->count(),
            self::ACTIVE_RESERVATIONS => (clone $reservationsQuery)->where('status', ReservationStatus::ACTIVE->value)->count(),
            self::LATE_RETURNS => (clone $contractsQuery)
                ->pendingReturnTask($date)
                ->whereDate('end_date', '<', $date)
                ->count(),
            self::RENTED_CARS => (clone $carsQuery)->where('status', CarStatus::RENTED->value)->count(),
        ];
    }

    public function maintenanceCars(int $tenantId, ?int $branchId): int
    {
        $query = Car::query()->where('tenant_id', $tenantId);
        $this->applyCarBranchScope($query, $branchId);

        return $query->where('status', CarStatus::MAINTENANCE->value)->count();
    }

    public function snapshotTenant(Tenant $tenant, CarbonInterface $date): array
    {
        $branchIds = Branch::query()
            ->where('tenant_id', (int) $tenant->id)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $scopes = [null, ...$branchIds];
        $snapshots = [];

        foreach ($scopes as $branchId) {
            $snapshots[$this->branchScope($branchId)] = $this->snapshotBranch((int) $tenant->id, $branchId, $date);
        }

        return $snapshots;
    }

    /**
     * @return array<string, float|int>
     */
    public function snapshotBranch(int $tenantId, ?int $branchId, CarbonInterface $date): array
    {
        $date = Carbon::instance($date->toDateTimeImmutable());
        $metrics = $this->currentMetrics($tenantId, $branchId, $date);

        foreach ($metrics as $key => $value) {
            OwnerDashboardMetricSnapshot::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'branch_scope' => $this->branchScope($branchId),
                    'metric_key' => $key,
                    'metric_date' => $date->toDateString(),
                ],
                [
                    'branch_id' => $branchId,
                    'value' => round((float) $value, 2),
                    'captured_at' => now(),
                ]
            );
        }

        return $metrics;
    }

    public function snapshotValue(int $tenantId, ?int $branchId, string $metricKey, CarbonInterface $date): ?float
    {
        $value = OwnerDashboardMetricSnapshot::query()
            ->where('tenant_id', $tenantId)
            ->where('branch_scope', $this->branchScope($branchId))
            ->where('metric_key', $metricKey)
            ->whereDate('metric_date', $date)
            ->value('value');

        return $value === null ? null : round((float) $value, 2);
    }

    public function paymentTotalForDate(Builder $query, CarbonInterface $date): float
    {
        $date = Carbon::instance($date->toDateTimeImmutable());

        $total = $query
            ->where(function (Builder $query) use ($date): void {
                $query->whereDate('processed_at', $date)
                    ->orWhere(function (Builder $query) use ($date): void {
                        $query->whereNull('processed_at')
                            ->whereDate('created_at', $date);
                    });
            })
            ->selectRaw('COALESCE(SUM(COALESCE(base_amount, amount)), 0) as aggregate')
            ->value('aggregate');

        return round((float) $total, 2);
    }

    public function revenueChart(Builder $query, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = Carbon::instance($from->toDateTimeImmutable());
        $to = Carbon::instance($to->toDateTimeImmutable());

        $rows = $query
            ->whereRaw('DATE(COALESCE(processed_at, created_at)) between ? and ?', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('DATE(COALESCE(processed_at, created_at)) as payment_date, COALESCE(SUM(COALESCE(base_amount, amount)), 0) as total')
            ->groupBy('payment_date')
            ->pluck('total', 'payment_date');

        return collect(range(0, $from->diffInDays($to)))
            ->map(function (int $offset) use ($from, $rows): array {
                $date = $from->copy()->addDays($offset);
                $key = $date->toDateString();

                return [
                    'date' => $key,
                    'label' => $date->format('M j'),
                    'value' => round((float) ($rows[$key] ?? 0), 2),
                ];
            })
            ->values()
            ->all();
    }

    public function paymentsQuery(int $tenantId, ?int $branchId): Builder
    {
        $query = Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', PaymentStatus::COMPLETED->value);

        $this->applyPaymentBranchScope($query, $branchId);

        return $query;
    }

    private function applyCarBranchScope(Builder $query, ?int $branchId): void
    {
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
    }

    private function applyReservationBranchScope(Builder $query, ?int $branchId): void
    {
        if ($branchId) {
            $query->whereHas('car', fn (Builder $query) => $query->where('branch_id', $branchId));
        }
    }

    private function applyContractBranchScope(Builder $query, ?int $branchId): void
    {
        if ($branchId) {
            $query->where(function (Builder $query) use ($branchId): void {
                $query->where('branch_id', $branchId)
                    ->orWhereHas('reservation.car', fn (Builder $query) => $query->where('branch_id', $branchId));
            });
        }
    }

    private function applyPaymentBranchScope(Builder $query, ?int $branchId): void
    {
        if ($branchId) {
            $query->whereHas('reservation.car', fn (Builder $query) => $query->where('branch_id', $branchId));
        }
    }

    private function branchScope(?int $branchId): string
    {
        return $branchId ? 'branch:'.$branchId : 'all';
    }
}
