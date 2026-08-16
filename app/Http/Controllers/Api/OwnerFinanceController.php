<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Car;
use App\Models\DamageRepair;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Dashboard\OwnerDashboardMetricsService;
use App\Support\BranchAccess;
use App\Support\CurrencyCatalog;
use App\Support\TenantTranslations;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnerFinanceController extends Controller
{
    public function __construct(
        private readonly BranchAccess $branchAccess,
        private readonly OwnerDashboardMetricsService $dashboardMetrics,
    ) {
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $branchId = $this->resolveOwnerBranchId($request, $user);
        [$dateFrom, $dateTo] = $this->resolveDateRange($request, $locale);

        $tenantId = (int) $user->tenant_id;
        $tenant = Tenant::query()->with('siteSetting')->findOrFail($tenantId);
        $currency = CurrencyCatalog::forTenant($tenant, null, $locale);

        $totalRevenue = $this->reservationTotalForRange($tenantId, $branchId, $dateFrom, $dateTo);
        $collectedAmount = $this->dashboardMetrics->paymentTotalForDateRange(
            $this->dashboardMetrics->paymentsQuery($tenantId, $branchId),
            $dateFrom,
            $dateTo
        );
        $uncollectedAmount = $this->uncollectedReservationTotalForRange($tenantId, $branchId, $dateFrom, $dateTo);
        $expenses = $this->expensesTotalForRange($tenantId, $branchId, $dateFrom, $dateTo);
        $netProfit = round($collectedAmount - $expenses, 2);

        $previousRange = $this->previousRange($dateFrom, $dateTo);

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'tenant_id' => $tenantId,
            'branch_id' => is_int($branchId) ? $branchId : null,
            'date_range' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
                'max_days' => 7,
            ],
            'currency' => $currency,
            'cards' => [
                $this->moneyCard(
                    'total_revenue',
                    $locale,
                    'Total revenue',
                    $totalRevenue,
                    '#14B8A6',
                    $currency,
                    $this->periodChange(
                        $totalRevenue,
                        $this->reservationTotalForRange($tenantId, $branchId, $previousRange['from'], $previousRange['to']),
                        $previousRange,
                        $locale
                    )
                ),
                $this->moneyCard(
                    'collected_amount',
                    $locale,
                    'Collected',
                    $collectedAmount,
                    '#14B8A6',
                    $currency,
                    $this->periodChange(
                        $collectedAmount,
                        $this->dashboardMetrics->paymentTotalForDateRange(
                            $this->dashboardMetrics->paymentsQuery($tenantId, $branchId),
                            $previousRange['from'],
                            $previousRange['to']
                        ),
                        $previousRange,
                        $locale
                    )
                ),
                $this->moneyCard(
                    'uncollected_amount',
                    $locale,
                    'Uncollected',
                    $uncollectedAmount,
                    '#F59E0B',
                    $currency,
                    $this->periodChange(
                        $uncollectedAmount,
                        $this->uncollectedReservationTotalForRange($tenantId, $branchId, $previousRange['from'], $previousRange['to']),
                        $previousRange,
                        $locale
                    )
                ),
                $this->moneyCard(
                    'expenses',
                    $locale,
                    'Expenses',
                    $expenses,
                    '#8B5CF6',
                    $currency,
                    $this->periodChange(
                        $expenses,
                        $this->expensesTotalForRange($tenantId, $branchId, $previousRange['from'], $previousRange['to']),
                        $previousRange,
                        $locale
                    )
                ),
                $this->moneyCard(
                    'net_profit',
                    $locale,
                    'Net profit',
                    $netProfit,
                    '#22C55E',
                    $currency,
                    $this->periodChange(
                        $netProfit,
                        round(
                            $this->dashboardMetrics->paymentTotalForDateRange(
                                $this->dashboardMetrics->paymentsQuery($tenantId, $branchId),
                                $previousRange['from'],
                                $previousRange['to']
                            ) - $this->expensesTotalForRange($tenantId, $branchId, $previousRange['from'], $previousRange['to']),
                            2
                        ),
                        $previousRange,
                        $locale
                    )
                ),
            ],
            'fleet_occupancy' => $this->fleetOccupancy($tenantId, $branchId, $dateFrom, $dateTo, $previousRange, $locale),
            'top_performing_cars' => $this->topPerformingCars($tenantId, $branchId, $dateFrom, $dateTo, $currency),
            'revenue_chart' => $this->dashboardMetrics->revenueChart(
                $this->dashboardMetrics->paymentsQuery($tenantId, $branchId),
                $dateFrom,
                $dateTo
            ),
            'branch_breakdown' => $this->branchBreakdown($tenantId, $branchId, $dateFrom, $dateTo, $currency),
        ]);
    }

    private function authorizedOwner(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(!empty($user->tenant_id), 403);
        abort_unless($this->branchAccess->canUseOwnerApis($user), 403);

        return $user;
    }

    private function resolveOwnerBranchId(Request $request, User $user): int|array|null
    {
        $branchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));

        $resolvedBranchId = $this->branchAccess->resolveAccessibleBranchId($user, $branchId);

        if (!$this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId && !$resolvedBranchId) {
                throw ValidationException::withMessages([
                    'branch_id' => [$this->ownerText('errors.branch_invalid', $this->resolveLocale($request), 'Selected branch is invalid or not accessible.')],
                ]);
            }

            return $resolvedBranchId ?: $this->branchAccess->accessibleBranchIds($user);
        }

        if (!$resolvedBranchId) {
            return null;
        }

        $exists = Branch::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', (int) $user->tenant_id)
            ->whereKey($resolvedBranchId)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'branch_id' => [$this->ownerText('errors.branch_invalid', $this->resolveLocale($request), 'Selected branch is invalid or not accessible.')],
            ]);
        }

        return $resolvedBranchId;
    }

    private function resolveDateRange(Request $request, string $locale): array
    {
        $dateFromInput = $this->firstFilledDateInput($request, ['date_from', 'from', 'from_date', 'start_date', 'dateFrom']);
        $dateToInput = $this->firstFilledDateInput($request, ['date_to', 'to', 'to_date', 'end_date', 'dateTo']);

        $validated = validator([
            'date_from' => $dateFromInput,
            'date_to' => $dateToInput,
        ], [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ])->validate();

        if (!$dateFromInput && !$dateToInput) {
            $dateTo = Carbon::today();
            $dateFrom = $dateTo->copy()->subDays(6);
        } elseif ($dateFromInput && !$dateToInput) {
            $dateFrom = Carbon::parse((string) $validated['date_from'])->startOfDay();
            $dateTo = $dateFrom->copy()->addDays(6);
        } elseif (!$dateFromInput && $dateToInput) {
            $dateTo = Carbon::parse((string) $validated['date_to'])->startOfDay();
            $dateFrom = $dateTo->copy()->subDays(6);
        } else {
            $dateFrom = Carbon::parse((string) $validated['date_from'])->startOfDay();
            $dateTo = Carbon::parse((string) $validated['date_to'])->startOfDay();
        }

        if ($dateFrom->diffInDays($dateTo) > 6) {
            throw ValidationException::withMessages([
                'date_to' => [$this->ownerText('errors.date_range_too_long', $locale, 'The chart date range cannot exceed 7 days.')],
            ]);
        }

        return [$dateFrom, $dateTo];
    }

    private function firstFilledDateInput(Request $request, array $keys): ?string
    {
        foreach ($keys as $key) {
            if ($request->filled($key)) {
                return (string) $request->input($key);
            }
        }

        return null;
    }

    private function reservationTotalForRange(int $tenantId, int|array|null $branchId, Carbon $from, Carbon $to): float
    {
        $query = Reservation::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereBetween(DB::raw('DATE(created_at)'), [$from->toDateString(), $to->toDateString()]);

        $this->applyReservationBranchScope($query, $branchId);

        return round((float) $query->sum('total_amount'), 2);
    }

    private function uncollectedReservationTotalForRange(int $tenantId, int|array|null $branchId, Carbon $from, Carbon $to): float
    {
        $paidTotals = Payment::query()
            ->withoutGlobalScope('tenant')
            ->selectRaw('reservation_id, SUM(COALESCE(base_amount, amount, 0)) as paid_amount')
            ->where('tenant_id', $tenantId)
            ->where('status', PaymentStatus::COMPLETED->value)
            ->groupBy('reservation_id');

        $query = Reservation::query()
            ->withoutGlobalScope('tenant')
            ->leftJoinSub($paidTotals, 'owner_finance_paid_totals', 'owner_finance_paid_totals.reservation_id', '=', 'reservations.id')
            ->where('reservations.tenant_id', $tenantId)
            ->whereBetween(DB::raw('DATE(reservations.created_at)'), [$from->toDateString(), $to->toDateString()]);

        $this->applyReservationBranchScope($query, $branchId);

        $total = $query
            ->selectRaw('COALESCE(SUM(GREATEST(COALESCE(reservations.total_amount, 0) - COALESCE(owner_finance_paid_totals.paid_amount, 0), 0)), 0) as aggregate')
            ->value('aggregate');

        return round((float) $total, 2);
    }

    private function expensesTotalForRange(int $tenantId, int|array|null $branchId, Carbon $from, Carbon $to): float
    {
        $query = DamageRepair::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereRaw('DATE(COALESCE(completed_at, started_at, opened_at, created_at)) between ? and ?', [$from->toDateString(), $to->toDateString()]);

        $this->applyBranchColumnScope($query, $branchId);

        $total = $query
            ->selectRaw('COALESCE(SUM(COALESCE(actual_cost, estimated_cost, 0)), 0) as aggregate')
            ->value('aggregate');

        return round((float) $total, 2);
    }

    private function branchBreakdown(int $tenantId, int|array|null $branchId, Carbon $from, Carbon $to, array $currency): array
    {
        $branches = Branch::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->when(is_int($branchId), fn (Builder $query): Builder => $query->whereKey($branchId))
            ->when(is_array($branchId), fn (Builder $query): Builder => $query->whereIn('id', $branchId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $totals = Payment::query()
            ->withoutGlobalScope('tenant')
            ->join('reservations', 'reservations.id', '=', 'payments.reservation_id')
            ->join('cars', 'cars.id', '=', 'reservations.car_id')
            ->where('payments.tenant_id', $tenantId)
            ->where('payments.status', PaymentStatus::COMPLETED->value)
            ->whereRaw('DATE(COALESCE(payments.processed_at, payments.created_at)) between ? and ?', [$from->toDateString(), $to->toDateString()])
            ->when(is_int($branchId), fn (Builder $query): Builder => $query->where('cars.branch_id', $branchId))
            ->when(is_array($branchId), fn (Builder $query): Builder => $query->whereIn('cars.branch_id', $branchId))
            ->selectRaw('cars.branch_id, COALESCE(SUM(COALESCE(payments.base_amount, payments.amount)), 0) as total')
            ->groupBy('cars.branch_id')
            ->pluck('total', 'cars.branch_id');

        $grandTotal = round((float) $totals->sum(), 2);

        return $branches
            ->map(function (Branch $branch) use ($totals, $grandTotal, $currency): array {
                $total = round((float) ($totals[$branch->id] ?? 0), 2);

                return [
                    'branch_id' => (int) $branch->id,
                    'branch_name' => $branch->name,
                    'amount' => $total,
                    'formatted_amount' => $this->formatMoney($total, $currency),
                    'percent' => $grandTotal > 0 ? round(($total / $grandTotal) * 100, 2) : 0.0,
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    private function applyReservationBranchScope(Builder $query, int|array|null $branchId): void
    {
        if (is_array($branchId)) {
            if ($branchId === []) {
                $query->whereRaw('1 = 0');
                return;
            }

            $query->whereHas('car', fn (Builder $query): Builder => $query
                ->withoutGlobalScope('tenant')
                ->whereIn('branch_id', $branchId));
            return;
        }

        if ($branchId) {
            $query->whereHas('car', fn (Builder $query): Builder => $query
                ->withoutGlobalScope('tenant')
                ->where('branch_id', $branchId));
        }
    }

    private function applyBranchColumnScope(Builder $query, int|array|null $branchId, string $column = 'branch_id'): void
    {
        if (is_array($branchId)) {
            if ($branchId === []) {
                $query->whereRaw('1 = 0');
                return;
            }

            $query->whereIn($column, $branchId);
            return;
        }

        if ($branchId) {
            $query->where($column, $branchId);
        }
    }

    private function previousRange(Carbon $from, Carbon $to): array
    {
        $days = $from->diffInDays($to) + 1;
        $previousTo = $from->copy()->subDay();
        $previousFrom = $previousTo->copy()->subDays($days - 1);

        return [
            'from' => $previousFrom,
            'to' => $previousTo,
        ];
    }

    private function moneyCard(string $key, string $locale, string $fallbackTitle, float $value, string $accent, array $currency, array $change): array
    {
        return [
            'key' => $key,
            'title' => $this->ownerText('finance.cards.'.$key, $locale, $fallbackTitle),
            'value' => $value,
            'value_type' => 'money',
            'formatted_value' => $this->formatMoney($value, $currency),
            'accent' => $accent,
            'change' => $change,
        ];
    }

    private function periodChange(float $current, float $previous, array $previousRange, string $locale): array
    {
        $difference = round($current - $previous, 2);

        if ($previous != 0.0) {
            $percent = round(($difference / abs($previous)) * 100, 2);
        } else {
            $percent = $current > 0 ? 100.0 : 0.0;
        }

        return [
            'value' => $difference,
            'percent' => abs($percent),
            'direction' => $difference > 0 ? 'up' : ($difference < 0 ? 'down' : 'flat'),
            'comparison' => 'previous_period',
            'comparison_label' => $this->ownerText('comparisons.previous_period', $locale, 'Previous period'),
            'comparison_period' => [
                'from' => $previousRange['from']->toDateString(),
                'to' => $previousRange['to']->toDateString(),
            ],
        ];
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

    private function fleetOccupancy(int $tenantId, int|array|null $branchId, Carbon $from, Carbon $to, array $previousRange, string $locale): array
    {
        $carsQuery = Car::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId);

        $this->applyBranchColumnScope($carsQuery, $branchId);

        $totalFleet = $carsQuery->count();
        $days = $from->diffInDays($to) + 1;
        $totalCapacityDays = $totalFleet * $days;

        $occupiedDays = $this->calculateOccupiedCarDays($tenantId, $branchId, $from, $to);
        $occupancyRate = $totalCapacityDays > 0
            ? round(min(100.0, ($occupiedDays / $totalCapacityDays) * 100), 2)
            : 0.0;

        $prevDays = $previousRange['from']->diffInDays($previousRange['to']) + 1;
        $prevCapacityDays = $totalFleet * $prevDays;
        $prevOccupiedDays = $this->calculateOccupiedCarDays($tenantId, $branchId, $previousRange['from'], $previousRange['to']);
        $prevOccupancyRate = $prevCapacityDays > 0
            ? round(min(100.0, ($prevOccupiedDays / $prevCapacityDays) * 100), 2)
            : 0.0;

        return [
            'occupancy_rate' => $occupancyRate,
            'formatted_occupancy_rate' => number_format($occupancyRate, 0).'%',
            'total_fleet' => $totalFleet,
            'change' => $this->periodChange($occupancyRate, $prevOccupancyRate, $previousRange, $locale),
        ];
    }

    private function calculateOccupiedCarDays(int $tenantId, int|array|null $branchId, Carbon $from, Carbon $to): int
    {
        $query = Reservation::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', \App\Enums\ReservationStatus::CANCELLED->value)
            ->where(function (Builder $query) use ($from, $to): void {
                $query->whereDate('start_date', '<=', $to->toDateString())
                    ->whereDate('end_date', '>=', $from->toDateString());
            });

        $this->applyReservationBranchScope($query, $branchId);

        $reservations = $query->get(['car_id', 'start_date', 'end_date']);
        $carOccupiedDays = [];
        $totalPeriodDays = $from->diffInDays($to) + 1;

        foreach ($reservations as $reservation) {
            if (!$reservation->car_id || !$reservation->start_date || !$reservation->end_date) {
                continue;
            }

            $resFrom = Carbon::parse($reservation->start_date)->startOfDay();
            $resTo = Carbon::parse($reservation->end_date)->startOfDay();

            $overlapFrom = $resFrom->greaterThan($from) ? $resFrom : $from->copy();
            $overlapTo = $resTo->lessThan($to) ? $resTo : $to->copy();

            if ($overlapFrom->lessThanOrEqualTo($overlapTo)) {
                $days = $overlapFrom->diffInDays($overlapTo) + 1;
                $carId = (int) $reservation->car_id;
                $carOccupiedDays[$carId] = min($totalPeriodDays, ($carOccupiedDays[$carId] ?? 0) + $days);
            }
        }

        return (int) array_sum($carOccupiedDays);
    }

    private function topPerformingCars(int $tenantId, int|array|null $branchId, Carbon $from, Carbon $to, array $currency, int $limit = 3): array
    {
        $rows = Payment::query()
            ->withoutGlobalScope('tenant')
            ->join('reservations', 'reservations.id', '=', 'payments.reservation_id')
            ->join('cars', 'cars.id', '=', 'reservations.car_id')
            ->where('payments.tenant_id', $tenantId)
            ->where('payments.status', PaymentStatus::COMPLETED->value)
            ->whereRaw('DATE(COALESCE(payments.processed_at, payments.created_at)) between ? and ?', [$from->toDateString(), $to->toDateString()])
            ->when(is_int($branchId), fn (Builder $query): Builder => $query->where('cars.branch_id', $branchId))
            ->when(is_array($branchId), fn (Builder $query): Builder => $query->whereIn('cars.branch_id', $branchId))
            ->selectRaw('cars.id as car_id, COALESCE(SUM(COALESCE(payments.base_amount, payments.amount)), 0) as total_revenue, COUNT(DISTINCT reservations.id) as reservations_count')
            ->groupBy('cars.id')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $rows = Reservation::query()
                ->withoutGlobalScope('tenant')
                ->join('cars', 'cars.id', '=', 'reservations.car_id')
                ->where('reservations.tenant_id', $tenantId)
                ->whereBetween(DB::raw('DATE(reservations.created_at)'), [$from->toDateString(), $to->toDateString()])
                ->when(is_int($branchId), fn (Builder $query): Builder => $query->where('cars.branch_id', $branchId))
                ->when(is_array($branchId), fn (Builder $query): Builder => $query->whereIn('cars.branch_id', $branchId))
                ->selectRaw('cars.id as car_id, COALESCE(SUM(reservations.total_amount), 0) as total_revenue, COUNT(reservations.id) as reservations_count')
                ->groupBy('cars.id')
                ->orderByDesc('total_revenue')
                ->limit($limit)
                ->get();
        }

        $carIds = $rows->pluck('car_id')->all();
        if (empty($carIds)) {
            return [];
        }

        $cars = Car::query()
            ->withoutGlobalScope('tenant')
            ->whereIn('id', $carIds)
            ->get()
            ->keyBy('id');

        return $rows
            ->map(function ($row) use ($cars, $currency): ?array {
                /** @var Car|null $car */
                $car = $cars->get($row->car_id);
                if (!$car) {
                    return null;
                }

                $revenue = round((float) $row->total_revenue, 2);
                $make = trim((string) $car->make);
                $model = trim((string) $car->model);
                $year = (int) $car->year;
                $fullName = trim("{$make} {$model} {$year}");

                return [
                    'car_id' => (int) $car->id,
                    'name' => $fullName !== '' ? $fullName : "Car #{$car->id}",
                    'make' => $car->make,
                    'model' => $car->model,
                    'year' => $year,
                    'license_plate' => $car->license_plate,
                    'total_revenue' => $revenue,
                    'formatted_total_revenue' => $this->formatMoney($revenue, $currency),
                    'reservations_count' => (int) $row->reservations_count,
                    'image_url' => $car->image_url,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
