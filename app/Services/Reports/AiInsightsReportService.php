<?php

namespace App\Services\Reports;

use App\Enums\CarViolationStatus;
use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Car;
use App\Models\Contract;
use App\Models\Reservation;
use App\Models\TenantSiteSetting;
use App\Models\User;
use App\Support\BranchAccess;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AiInsightsReportService
{
    public function __construct(private BranchAccess $branchAccess)
    {
    }

    /**
     * @param array{start: CarbonInterface, end: CarbonInterface} $dateRange
     */
    public function build(array $dateRange, User $user, ?int $branchId, bool $canViewFinancials): array
    {
        $cars = $this->accessibleCars($user, $branchId);
        $carIds = $cars->pluck('id')->map(fn ($id) => (int) $id)->values();

        $carRows = $this->carRows($cars, $carIds, $dateRange, $canViewFinancials);
        $highRiskCustomers = $this->highRiskCustomers($carIds, $dateRange, $canViewFinancials);
        $losses = $this->uncollectedLosses($carIds, $dateRange, $canViewFinancials);
        $problemContracts = $this->problemContracts($user, $branchId, $canViewFinancials);
        $demandDays = $this->demandDays($carIds, $dateRange);
        $priceOpportunities = $this->priceOpportunities($carRows);
        $marketLocation = $this->marketLocation($user);

        $unprofitableCars = $carRows
            ->filter(fn (array $row): bool => (float) $row['net_profit_raw'] < 0)
            ->sortBy('net_profit_raw')
            ->take(8)
            ->values();

        $repeatedDamageCars = $carRows
            ->filter(fn (array $row): bool => (int) $row['damage_reports_count'] >= 2 || (int) $row['accidents_count'] > 0)
            ->sortByDesc(fn (array $row): int => ((int) $row['damage_reports_count'] * 2) + (int) $row['damage_items_count'] + ((int) $row['accidents_count'] * 3))
            ->take(8)
            ->values();

        $criticalCount = $unprofitableCars->count()
            + $highRiskCustomers->filter(fn (array $row): bool => $row['score'] >= 70)->count()
            + $problemContracts->filter(fn (array $row): bool => $row['severity'] === 'danger')->count();

        return [
            'generated_at' => now()->toDateTimeString(),
            'period' => [
                'start' => $dateRange['start']->toDateString(),
                'end' => $dateRange['end']->toDateString(),
            ],
            'market_location' => $marketLocation,
            'summary' => [
                'critical_count' => $criticalCount,
                'unprofitable_cars_count' => $unprofitableCars->count(),
                'repeated_damage_cars_count' => $repeatedDamageCars->count(),
                'high_risk_customers_count' => $highRiskCustomers->filter(fn (array $row): bool => $row['score'] >= 60)->count(),
                'problem_contracts_count' => $problemContracts->count(),
                'pricing_opportunities_count' => $priceOpportunities->count(),
                'uncollected_losses' => $this->visibleMoney((float) $losses['total_raw'], $canViewFinancials),
                'formatted_uncollected_losses' => $this->formatMoney((float) $losses['total_raw'], $canViewFinancials),
            ],
            'unprofitable_cars' => $this->stripRawMoney($unprofitableCars),
            'repeated_damage_cars' => $this->stripRawMoney($repeatedDamageCars),
            'high_risk_customers' => $this->stripRawMoney($highRiskCustomers),
            'price_opportunities' => $this->stripRawMoney($priceOpportunities),
            'demand_days' => $demandDays->values()->all(),
            'uncollected_losses' => $losses['visible'],
            'problem_contracts' => $this->stripRawMoney($problemContracts),
            'market_study' => [
                'status' => 'not_connected',
                'title' => 'OpenAI market study is ready for phase 2.',
                'description' => 'The internal data packet is prepared. The next phase will send a controlled summary to OpenAI for market comparison, pricing advice, and action planning.',
            ],
        ];
    }

    private function accessibleCars(User $user, ?int $branchId): Collection
    {
        $query = Car::query()
            ->select(['id', 'branch_id', 'make', 'model', 'year', 'license_plate', 'price_per_day', 'status'])
            ->orderBy('make')
            ->orderBy('model');

        $this->branchAccess->applyToQuery($query, $user, $branchId);

        return $query->get();
    }

    private function marketLocation(User $user): array
    {
        $tenant = $user->tenant;
        if (!$tenant) {
            return $this->defaultMarketLocation();
        }

        $tenant->loadMissing('siteSetting');
        $settings = TenantSiteSetting::forTenant($tenant);
        $marketLocation = is_array($settings['market_location'] ?? null) ? $settings['market_location'] : [];

        return [
            'country_code' => $marketLocation['country_code'] ?? null,
            'country_name' => $marketLocation['country_name'] ?? null,
            'region' => $marketLocation['region'] ?? null,
            'city' => $marketLocation['city'] ?? null,
            'market_area' => $marketLocation['market_area'] ?? null,
            'timezone' => $marketLocation['timezone'] ?? config('app.timezone'),
            'currency_code' => $marketLocation['currency_code'] ?? strtoupper((string) config('app.currency_code', 'USD')),
            'currency_symbol' => (string) config('app.currency_symbol', '$'),
        ];
    }

    private function defaultMarketLocation(): array
    {
        return [
            'country_code' => null,
            'country_name' => null,
            'region' => null,
            'city' => null,
            'market_area' => null,
            'timezone' => config('app.timezone'),
            'currency_code' => strtoupper((string) config('app.currency_code', 'USD')),
            'currency_symbol' => (string) config('app.currency_symbol', '$'),
        ];
    }

    private function carRows(Collection $cars, Collection $carIds, array $dateRange, bool $canViewFinancials): Collection
    {
        if ($carIds->isEmpty()) {
            return collect();
        }

        $revenueByCar = DB::table('payments')
            ->join('reservations', 'payments.reservation_id', '=', 'reservations.id')
            ->whereIn('reservations.car_id', $carIds)
            ->where('payments.status', PaymentStatus::COMPLETED->value)
            ->whereBetween('payments.processed_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('reservations.car_id, COALESCE(SUM(payments.amount - COALESCE(payments.refunded_amount, 0)), 0) as revenue')
            ->groupBy('reservations.car_id')
            ->pluck('revenue', 'car_id');

        $maintenanceByCar = DB::table('car_maintenances')
            ->whereIn('car_id', $carIds)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('car_id, COALESCE(SUM(cost), 0) as cost')
            ->groupBy('car_id')
            ->pluck('cost', 'car_id');

        $damageByCar = DB::table('car_damage_reports')
            ->leftJoin('car_damage_items', 'car_damage_reports.id', '=', 'car_damage_items.car_damage_report_id')
            ->whereIn('car_damage_reports.car_id', $carIds)
            ->whereBetween('car_damage_reports.created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('car_damage_reports.car_id, COUNT(DISTINCT car_damage_reports.id) as reports_count, COUNT(car_damage_items.id) as items_count, COALESCE(SUM(car_damage_items.estimated_cost * COALESCE(NULLIF(car_damage_items.quantity, 0), 1)), 0) as damage_cost')
            ->groupBy('car_damage_reports.car_id')
            ->get()
            ->keyBy('car_id');

        $violationsByCar = DB::table('car_violations')
            ->whereIn('car_id', $carIds)
            ->whereBetween('violation_date', [$dateRange['start'], $dateRange['end']])
            ->whereIn('status', [CarViolationStatus::PENDING->value, CarViolationStatus::DISPUTED->value])
            ->selectRaw('car_id, COUNT(*) as violations_count, COALESCE(SUM(amount), 0) as violations_amount')
            ->groupBy('car_id')
            ->get()
            ->keyBy('car_id');

        $accidentsByCar = DB::table('accident_reports')
            ->whereIn('car_id', $carIds)
            ->whereBetween('accident_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('car_id, COUNT(*) as accidents_count')
            ->groupBy('car_id')
            ->pluck('accidents_count', 'car_id');

        $reservationStatsByCar = DB::table('reservations')
            ->whereIn('car_id', $carIds)
            ->whereBetween('start_date', [$dateRange['start']->toDateString(), $dateRange['end']->toDateString()])
            ->whereNotIn('status', [ReservationStatus::CANCELLED->value, ReservationStatus::NO_SHOW->value])
            ->selectRaw('car_id, COUNT(*) as reservations_count, COALESCE(SUM(total_days), 0) as utilization_days')
            ->groupBy('car_id')
            ->get()
            ->keyBy('car_id');

        return $cars->map(function (Car $car) use ($revenueByCar, $maintenanceByCar, $damageByCar, $violationsByCar, $accidentsByCar, $reservationStatsByCar, $canViewFinancials): array {
            $carId = (int) $car->id;
            $damage = $damageByCar->get($carId);
            $violations = $violationsByCar->get($carId);
            $reservationStats = $reservationStatsByCar->get($carId);
            $revenue = (float) ($revenueByCar[$carId] ?? 0);
            $maintenanceCost = (float) ($maintenanceByCar[$carId] ?? 0);
            $damageCost = (float) ($damage->damage_cost ?? 0);
            $violationsAmount = (float) ($violations->violations_amount ?? 0);
            $totalCosts = $maintenanceCost + $damageCost + $violationsAmount;
            $netProfit = $revenue - $totalCosts;
            $margin = $revenue > 0 ? round(($netProfit / $revenue) * 100, 1) : 0.0;

            return [
                'car_id' => $carId,
                'car_name' => trim(sprintf('%s %s %s', $car->year, $car->make, $car->model)),
                'license_plate' => $car->license_plate,
                'price_per_day' => $this->visibleMoney((float) $car->price_per_day, $canViewFinancials),
                'formatted_price_per_day' => $this->formatMoney((float) $car->price_per_day, $canViewFinancials),
                'revenue' => $this->visibleMoney($revenue, $canViewFinancials),
                'formatted_revenue' => $this->formatMoney($revenue, $canViewFinancials),
                'costs' => $this->visibleMoney($totalCosts, $canViewFinancials),
                'formatted_costs' => $this->formatMoney($totalCosts, $canViewFinancials),
                'net_profit' => $this->visibleMoney($netProfit, $canViewFinancials),
                'formatted_net_profit' => $this->formatMoney($netProfit, $canViewFinancials),
                'net_profit_raw' => $netProfit,
                'profit_margin' => $margin,
                'reservations_count' => (int) ($reservationStats->reservations_count ?? 0),
                'utilization_days' => (int) ($reservationStats->utilization_days ?? 0),
                'damage_reports_count' => (int) ($damage->reports_count ?? 0),
                'damage_items_count' => (int) ($damage->items_count ?? 0),
                'accidents_count' => (int) ($accidentsByCar[$carId] ?? 0),
                'open_violations_count' => (int) ($violations->violations_count ?? 0),
                'recommendation' => $this->carRecommendation($netProfit, $revenue, $totalCosts, (int) ($damage->reports_count ?? 0), (int) ($accidentsByCar[$carId] ?? 0)),
            ];
        })->values();
    }

    private function highRiskCustomers(Collection $carIds, array $dateRange, bool $canViewFinancials): Collection
    {
        if ($carIds->isEmpty()) {
            return collect();
        }

        $reservations = Reservation::query()
            ->with(['user:id,name,email', 'payments:id,reservation_id,amount,refunded_amount,status'])
            ->whereIn('car_id', $carIds)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->get(['id', 'user_id', 'car_id', 'total_amount', 'status']);

        $overdueCounts = DB::table('contracts')
            ->join('reservations', 'contracts.reservation_id', '=', 'reservations.id')
            ->whereIn('reservations.car_id', $carIds)
            ->where('contracts.status', ContractStatus::ACTIVE->value)
            ->whereDate('contracts.end_date', '<', now()->toDateString())
            ->selectRaw('reservations.user_id, COUNT(*) as overdue_count')
            ->groupBy('reservations.user_id')
            ->pluck('overdue_count', 'user_id');

        $damageCounts = DB::table('car_damage_reports')
            ->join('reservations', 'car_damage_reports.reservation_id', '=', 'reservations.id')
            ->whereIn('car_damage_reports.car_id', $carIds)
            ->whereBetween('car_damage_reports.created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('reservations.user_id, COUNT(*) as damage_count')
            ->groupBy('reservations.user_id')
            ->pluck('damage_count', 'user_id');

        return $reservations
            ->filter(fn (Reservation $reservation): bool => $reservation->user_id !== null)
            ->groupBy('user_id')
            ->map(function (Collection $rows, int|string $userId) use ($overdueCounts, $damageCounts, $canViewFinancials): array {
                $first = $rows->first();
                $totalAmount = (float) $rows->sum(fn (Reservation $reservation): float => (float) $reservation->total_amount);
                $paidAmount = (float) $rows->sum(fn (Reservation $reservation): float => (float) $reservation->payments
                    ->filter(fn ($payment): bool => (string) $payment->status->value === PaymentStatus::COMPLETED->value)
                    ->sum(fn ($payment): float => max(0, (float) $payment->amount - (float) ($payment->refunded_amount ?? 0))));
                $unpaid = max(0, $totalAmount - $paidAmount);
                $cancelled = $rows->filter(fn (Reservation $reservation): bool => (string) $reservation->status->value === ReservationStatus::CANCELLED->value)->count();
                $noShow = $rows->filter(fn (Reservation $reservation): bool => (string) $reservation->status->value === ReservationStatus::NO_SHOW->value)->count();
                $overdue = (int) ($overdueCounts[$userId] ?? 0);
                $damage = (int) ($damageCounts[$userId] ?? 0);
                $score = min(100, ($overdue * 25) + ($damage * 15) + ($cancelled * 8) + ($noShow * 15) + min(30, (int) floor($unpaid / 100)));

                return [
                    'customer_id' => (int) $userId,
                    'name' => $first?->user?->name ?? 'Customer',
                    'email' => $first?->user?->email,
                    'score' => $score,
                    'severity' => $score >= 70 ? 'danger' : ($score >= 45 ? 'warning' : 'info'),
                    'reservations_count' => $rows->count(),
                    'overdue_contracts_count' => $overdue,
                    'damage_reports_count' => $damage,
                    'cancelled_count' => $cancelled,
                    'no_show_count' => $noShow,
                    'unpaid_amount' => $this->visibleMoney($unpaid, $canViewFinancials),
                    'formatted_unpaid_amount' => $this->formatMoney($unpaid, $canViewFinancials),
                    'unpaid_amount_raw' => $unpaid,
                    'recommendation' => $score >= 70 ? 'Require manual approval and higher deposit before the next booking.' : 'Review the customer before approving long bookings.',
                ];
            })
            ->filter(fn (array $row): bool => $row['score'] > 0)
            ->sortByDesc('score')
            ->take(8)
            ->values();
    }

    private function uncollectedLosses(Collection $carIds, array $dateRange, bool $canViewFinancials): array
    {
        if ($carIds->isEmpty()) {
            return [
                'total_raw' => 0,
                'visible' => [],
            ];
        }

        $unpaidReturnCharges = (float) DB::table('contract_return_reports')
            ->whereIn('car_id', $carIds)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->where(function ($query): void {
                $query->whereNull('payment_status')->orWhere('payment_status', '!=', 'paid');
            })
            ->sum('total_extra_charges');

        $openViolations = (float) DB::table('car_violations')
            ->whereIn('car_id', $carIds)
            ->whereBetween('violation_date', [$dateRange['start'], $dateRange['end']])
            ->whereIn('status', [CarViolationStatus::PENDING->value, CarViolationStatus::DISPUTED->value])
            ->sum('amount');

        $pendingPayments = (float) DB::table('payments')
            ->join('reservations', 'payments.reservation_id', '=', 'reservations.id')
            ->whereIn('reservations.car_id', $carIds)
            ->whereBetween('payments.created_at', [$dateRange['start'], $dateRange['end']])
            ->where('payments.status', PaymentStatus::PENDING->value)
            ->sum('payments.amount');

        $items = [
            [
                'key' => 'unpaid_return_charges',
                'label' => 'Unpaid return charges',
                'amount' => $this->visibleMoney($unpaidReturnCharges, $canViewFinancials),
                'formatted_amount' => $this->formatMoney($unpaidReturnCharges, $canViewFinancials),
                'amount_raw' => $unpaidReturnCharges,
            ],
            [
                'key' => 'open_violations',
                'label' => 'Open traffic violations',
                'amount' => $this->visibleMoney($openViolations, $canViewFinancials),
                'formatted_amount' => $this->formatMoney($openViolations, $canViewFinancials),
                'amount_raw' => $openViolations,
            ],
            [
                'key' => 'pending_payments',
                'label' => 'Pending payments',
                'amount' => $this->visibleMoney($pendingPayments, $canViewFinancials),
                'formatted_amount' => $this->formatMoney($pendingPayments, $canViewFinancials),
                'amount_raw' => $pendingPayments,
            ],
        ];

        return [
            'total_raw' => $unpaidReturnCharges + $openViolations + $pendingPayments,
            'visible' => $this->stripRawMoney(collect($items)),
        ];
    }

    private function problemContracts(User $user, ?int $branchId, bool $canViewFinancials): Collection
    {
        $query = Contract::query()
            ->with(['reservation.user:id,name,email', 'reservation.car:id,make,model,year,license_plate', 'returnStatusReport:id,contract_id,total_extra_charges,payment_status'])
            ->whereIn('status', [ContractStatus::ACTIVE->value, ContractStatus::PENDING->value])
            ->orderBy('end_date');

        $this->branchAccess->applyToQuery($query, $user, $branchId);

        return $query->limit(50)->get()
            ->map(function (Contract $contract) use ($canViewFinancials): ?array {
                $daysLate = $contract->end_date && $contract->end_date->isPast()
                    ? max(0, (int) $contract->end_date->diffInDays(now(), false))
                    : 0;
                $unpaidReturn = $contract->returnStatusReport
                    && $contract->returnStatusReport->payment_status !== 'paid'
                    && (float) $contract->returnStatusReport->total_extra_charges > 0;
                $score = ($daysLate > 0 ? min(60, $daysLate * 10) : 0) + ($unpaidReturn ? 25 : 0);

                if ($score === 0) {
                    return null;
                }

                return [
                    'contract_id' => (int) $contract->id,
                    'contract_number' => $contract->contract_number,
                    'customer_name' => $contract->reservation?->user?->name ?? $contract->renter_name,
                    'car_name' => $contract->reservation?->car
                        ? trim(sprintf('%s %s %s', $contract->reservation->car->year, $contract->reservation->car->make, $contract->reservation->car->model))
                        : $contract->car_details,
                    'end_date' => $contract->end_date?->toDateString(),
                    'days_late' => $daysLate,
                    'score' => min(100, $score),
                    'severity' => $score >= 50 ? 'danger' : 'warning',
                    'unpaid_return_charges' => $this->visibleMoney((float) ($contract->returnStatusReport?->total_extra_charges ?? 0), $canViewFinancials),
                    'formatted_unpaid_return_charges' => $this->formatMoney((float) ($contract->returnStatusReport?->total_extra_charges ?? 0), $canViewFinancials),
                    'unpaid_return_charges_raw' => (float) ($contract->returnStatusReport?->total_extra_charges ?? 0),
                    'recommendation' => $daysLate > 0 ? 'Contact the customer and schedule immediate return follow-up.' : 'Review unpaid return charges before closing.',
                ];
            })
            ->filter()
            ->sortByDesc('score')
            ->take(8)
            ->values();
    }

    private function demandDays(Collection $carIds, array $dateRange): Collection
    {
        if ($carIds->isEmpty()) {
            return collect();
        }

        return Reservation::query()
            ->whereIn('car_id', $carIds)
            ->whereBetween('start_date', [$dateRange['start']->toDateString(), $dateRange['end']->toDateString()])
            ->whereNotIn('status', [ReservationStatus::CANCELLED->value, ReservationStatus::NO_SHOW->value])
            ->get(['id', 'start_date', 'total_days'])
            ->groupBy(fn (Reservation $reservation): string => $reservation->start_date?->format('l') ?? 'Unknown')
            ->map(fn (Collection $rows, string $day): array => [
                'day' => $day,
                'reservations_count' => $rows->count(),
                'rental_days' => (int) $rows->sum('total_days'),
                'recommendation' => 'Keep more cars ready and review pricing for this demand pattern.',
            ])
            ->sortByDesc('reservations_count')
            ->take(7)
            ->values();
    }

    private function priceOpportunities(Collection $carRows): Collection
    {
        return $carRows
            ->filter(fn (array $row): bool =>
                (int) $row['utilization_days'] >= 5   // at least 5 rental days (lowered from 10)
                && (float) $row['net_profit_raw'] > 0 // profitable
                && (int) $row['damage_reports_count'] <= 2 // allow up to 2 damage reports (raised from 1)
            )
            ->sortByDesc('utilization_days')
            ->take(8)
            ->map(function (array $row): array {
                $utilDays = (int) $row['utilization_days'];
                $margin   = (float) $row['profit_margin'];

                // Suggest increase based on utilization and margin
                $suggestedIncrease = match (true) {
                    $utilDays >= 25 && $margin >= 60 => 15,
                    $utilDays >= 20 => 10,
                    $utilDays >= 10 => 7,
                    default         => 5,
                };

                return [
                    'car_id'                   => $row['car_id'],
                    'car_name'                 => $row['car_name'],
                    'license_plate'            => $row['license_plate'],
                    'current_price'            => $row['price_per_day'],
                    'formatted_current_price'  => $row['formatted_price_per_day'],
                    'suggested_increase_percent' => $suggestedIncrease,
                    'utilization_days'         => $utilDays,
                    'profit_margin'            => $margin,
                    'recommendation'           => "Test a {$suggestedIncrease}% daily rate increase for future bookings.",
                ];
            })
            ->values();
    }

    private function carRecommendation(float $netProfit, float $revenue, float $costs, int $damageReports, int $accidents): string
    {
        if ($netProfit < 0) {
            return 'Review pricing, maintenance cost, and whether the car should stay active.';
        }

        if ($damageReports >= 2 || $accidents > 0) {
            return 'Inspect recurring damage causes before increasing utilization.';
        }

        if ($revenue > 0 && $costs === 0.0) {
            return 'Good candidate for pricing optimization.';
        }

        return 'Monitor for the next reporting period.';
    }

    private function formatMoney(float $value, bool $canViewFinancials): string
    {
        if (!$canViewFinancials) {
            return '*******';
        }

        return (string) config('app.currency_symbol', '$').number_format($value, 2);
    }

    private function visibleMoney(float $value, bool $canViewFinancials): float|string
    {
        return $canViewFinancials ? round($value, 2) : '*******';
    }

    private function stripRawMoney(Collection $rows): array
    {
        return $rows
            ->map(function (array $row): array {
                foreach (array_keys($row) as $key) {
                    if (str_ends_with($key, '_raw')) {
                        unset($row[$key]);
                    }
                }

                return $row;
            })
            ->values()
            ->all();
    }
}
