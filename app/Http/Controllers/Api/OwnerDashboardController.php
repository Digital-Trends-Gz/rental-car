<?php

namespace App\Http\Controllers\Api;

use App\Enums\CarViolationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CarViolation;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use App\Models\User;
use App\Services\Dashboard\OwnerDashboardMetricsService;
use App\Services\Notifications\OwnerNotificationsService;
use App\Services\Plans\PlanEntityLocks;
use App\Support\BranchAccess;
use App\Support\CurrencyCatalog;
use App\Support\TenantTranslations;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OwnerDashboardController extends Controller
{
    public function __construct(
        private readonly BranchAccess $branchAccess,
        private readonly OwnerNotificationsService $notifications,
        private readonly OwnerDashboardMetricsService $dashboardMetrics,
        private readonly PlanEntityLocks $planEntityLocks,
    ) {
    }

    public function branches(Request $request): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $tenant = Tenant::query()
            ->with('subscriptionPlan')
            ->find((int) $user->tenant_id);

        $this->planEntityLocks->sync($tenant);
        $allowedBranchIds = $this->allowedPlanBranchIds($tenant);
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);
        $scopedBranchIds = $canAccessAllBranches
            ? null
            : $this->branchAccess->accessibleBranchIds($user);

        $branches = Branch::withoutTenantScope()
            ->where('tenant_id', (int) $user->tenant_id)
            ->when($scopedBranchIds !== null, fn ($query) => $query->whereIn('id', $scopedBranchIds ?: [0]))
            ->when($allowedBranchIds !== null, fn ($query) => $query->whereIn('id', $allowedBranchIds ?: [0]))
            ->orderBy('name')
            ->get(['id', 'name', 'country', 'city', 'address', 'phone', 'email'])
            ->map(fn (Branch $branch): array => $this->branchPayload($branch))
            ->values()
            ->all();

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'tenant_id' => (int) $user->tenant_id,
            'can_access_all_branches' => $canAccessAllBranches,
            'data' => array_values(array_filter([
                $canAccessAllBranches
                    ? [
                        'id' => null,
                        'name' => $this->ownerText('branches.all', $locale, 'All branches'),
                        'is_all' => true,
                    ]
                    : null,
                ...$branches,
            ])),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();
        [$dateFrom, $dateTo, $hasCustomDateRange] = $this->resolveDateRange($request, $today, $locale);
        $branchScope = $this->resolveOwnerBranchScope($request, $user);
        $branchId = is_int($branchScope) ? $branchScope : null;
        $tenant = Tenant::query()->with('siteSetting')->findOrFail((int) $user->tenant_id);
        $currency = $this->tenantCurrency($tenant, $request);
        $selectedBranch = $branchId
            ? Branch::query()->where('tenant_id', (int) $user->tenant_id)->find($branchId)
            : null;

        $currentMetrics = $hasCustomDateRange
            ? $this->dashboardMetrics->currentMetricsForDateRange((int) $user->tenant_id, $branchScope, $dateFrom, $dateTo)
            : $this->dashboardMetrics->currentMetrics((int) $user->tenant_id, $branchScope, $today);
        $paymentsQuery = $this->dashboardMetrics->paymentsQuery((int) $user->tenant_id, $branchScope);

        $todayRevenue = (float) $currentMetrics[OwnerDashboardMetricsService::TODAY_REVENUE];
        $availableCars = (int) $currentMetrics[OwnerDashboardMetricsService::AVAILABLE_CARS];
        $activeReservations = (int) $currentMetrics[OwnerDashboardMetricsService::ACTIVE_RESERVATIONS];
        $lateReturns = (int) $currentMetrics[OwnerDashboardMetricsService::LATE_RETURNS];
        $rentedCars = (int) $currentMetrics[OwnerDashboardMetricsService::RENTED_CARS];
        $maintenanceCars = $this->dashboardMetrics->maintenanceCars((int) $user->tenant_id, $branchScope);

        $revenueChange = $hasCustomDateRange
            ? $this->dateRangeRevenueChangePayload((int) $user->tenant_id, $branchScope, $todayRevenue, $dateFrom, $dateTo, $locale)
            : $this->snapshotChangePayload((int) $user->tenant_id, $branchScope, OwnerDashboardMetricsService::TODAY_REVENUE, $todayRevenue, $yesterday, $locale);
        $availableCarsChange = $this->snapshotChangePayload((int) $user->tenant_id, $branchScope, OwnerDashboardMetricsService::AVAILABLE_CARS, $availableCars, $yesterday, $locale);
        $activeReservationsChange = $this->snapshotChangePayload((int) $user->tenant_id, $branchScope, OwnerDashboardMetricsService::ACTIVE_RESERVATIONS, $activeReservations, $yesterday, $locale);
        $lateReturnsChange = $this->snapshotChangePayload((int) $user->tenant_id, $branchScope, OwnerDashboardMetricsService::LATE_RETURNS, $lateReturns, $yesterday, $locale);
        $rentedCarsChange = $this->snapshotChangePayload((int) $user->tenant_id, $branchScope, OwnerDashboardMetricsService::RENTED_CARS, $rentedCars, $yesterday, $locale);

        $pendingViolationsQuery = CarViolation::query()
            ->where('tenant_id', (int) $user->tenant_id)
            ->where('status', CarViolationStatus::PENDING->value);
        $this->branchAccess->applyToQuery($pendingViolationsQuery, $user, $branchId, 'branch_id');
        $pendingViolations = (clone $pendingViolationsQuery)->count();

        $notificationBadgeCount = $this->notifications->unreadCount($user, $branchScope, $locale);

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'date' => $today->toDateString(),
            'date_range' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
                'is_custom' => $hasCustomDateRange,
            ],
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'domain' => $tenant->domain,
            ],
            'branch_id' => $branchId,
            'selected_branch' => $selectedBranch ? $this->branchPayload($selectedBranch) : [
                'id' => null,
                'name' => $this->ownerText('branches.all', $locale, 'All branches'),
                'is_all' => true,
            ],
            'currency' => $currency,
            'cards' => [
                $this->metricCard('today_revenue', $locale, 'Today revenue', $todayRevenue, 'money', '#14B8A6', $currency, $revenueChange),
                $this->metricCard('available_cars', $locale, 'Available cars', $availableCars, 'count', '#0EA5E9', $currency, $availableCarsChange),
                $this->metricCard('active_reservations', $locale, 'Active reservations', $activeReservations, 'count', '#14B8A6', $currency, $activeReservationsChange),
                $this->metricCard('late_returns', $locale, 'Late returns', $lateReturns, 'count', '#EF4444', $currency, $lateReturnsChange),
                $this->metricCard('rented_cars', $locale, 'Rented cars', $rentedCars, 'count', '#8B5CF6', $currency, $rentedCarsChange),
            ],
            'stats' => [
                'today_revenue' => $todayRevenue,
                'available_cars' => $availableCars,
                'active_reservations' => $activeReservations,
                'late_returns' => $lateReturns,
                'rented_cars' => $rentedCars,
                'maintenance_cars' => $maintenanceCars,
                'pending_violations' => $pendingViolations,
                'notification_badge_count' => $notificationBadgeCount,
            ],
            'revenue_chart' => $this->dashboardMetrics->revenueChart(
                (clone $paymentsQuery),
                $hasCustomDateRange ? $dateFrom : $today->copy()->subDays(6),
                $hasCustomDateRange ? $dateTo : $today
            ),
            'quick_alerts' => [
                $this->alertPayload('late_returns', $locale, 'Late car returns', $lateReturns, '#FEE2E2', '#DC2626'),
                $this->alertPayload('unpaid_violations', $locale, 'Unpaid violations', $pendingViolations, '#FFEDD5', '#EA580C'),
                $this->alertPayload('maintenance_cars', $locale, 'Cars need maintenance', $maintenanceCars, '#F1F5F9', '#475569'),
            ],
            'notification_badge_count' => $notificationBadgeCount,
        ]);
    }

    private function resolveDateRange(Request $request, Carbon $defaultDate, string $locale): array
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

        $hasCustomDateRange = $dateFromInput !== null || $dateToInput !== null;

        if (!$hasCustomDateRange) {
            return [$defaultDate->copy(), $defaultDate->copy(), false];
        }

        $dateFrom = Carbon::parse($validated['date_from'] ?? $validated['date_to'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'] ?? $validated['date_from'])->startOfDay();

        if ($dateFrom->diffInDays($dateTo) > 6) {
            throw ValidationException::withMessages([
                'date_to' => [$this->ownerText('errors.date_range_too_long', $locale, 'The chart date range cannot exceed 7 days.')],
            ]);
        }

        return [$dateFrom, $dateTo, true];
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

    private function authorizedOwner(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($user->role === UserRole::ADMIN, 403);
        abort_unless(!empty($user->tenant_id), 403);
        abort_unless($this->branchAccess->canUseOwnerApis($user), 403);

        return $user;
    }

    private function resolveOwnerBranchScope(Request $request, User $user): int|array|null
    {
        $branchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));

        if ($branchId && !$this->branchAccess->canAccessBranchId($user, $branchId)) {
            throw ValidationException::withMessages([
                'branch_id' => [$this->ownerText('errors.branch_invalid', $this->resolveLocale($request), 'Selected branch is invalid or not accessible.')],
            ]);
        }

        $resolvedBranchId = $this->branchAccess->resolveAccessibleBranchId($user, $branchId);

        if (!$this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                return $resolvedBranchId;
            }

            return $this->branchAccess->accessibleBranchIds($user);
        }

        if (!$resolvedBranchId) {
            return null;
        }

        $exists = Branch::withoutTenantScope()
            ->where('tenant_id', (int) $user->tenant_id)
            ->whereKey($resolvedBranchId)
            ->first();

        if (!$exists || $this->planEntityLocks->branchIsLockedByPlan($exists)) {
            throw ValidationException::withMessages([
                'branch_id' => [$this->ownerText('errors.branch_invalid', $this->resolveLocale($request), 'Selected branch is invalid or not accessible.')],
            ]);
        }

        return $resolvedBranchId;
    }

    /**
     * @return array<int, int>|null Null means unlimited.
     */
    private function allowedPlanBranchIds(?Tenant $tenant): ?array
    {
        $limit = $this->normalizePlanLimit($tenant?->subscriptionPlan?->max_branches);

        if (!$tenant?->id || $limit === null) {
            return null;
        }

        return Branch::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function normalizePlanLimit(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $limit = (int) $value;

        return $limit >= 1 ? $limit : null;
    }

    private function branchPayload(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'country' => $branch->country,
            'city' => $branch->city,
            'address' => $branch->address,
            'phone' => $branch->phone,
            'email' => $branch->email,
            'is_all' => false,
        ];
    }

    private function tenantCurrency(Tenant $tenant, Request $request): array
    {
        $settings = TenantSiteSetting::forTenant($tenant);
        $locale = $this->resolveLocale($request);
        $currency = CurrencyCatalog::forTenant($tenant, null, $locale);
        $currency['enabled_currency_codes'] = collect((array) data_get($settings, 'market_location.enabled_currency_codes', []))
            ->map(fn (mixed $code): string => CurrencyCatalog::normalizeCode($code, $currency['code']))
            ->push($currency['code'])
            ->unique()
            ->values()
            ->all();

        return $currency;
    }

    private function metricCard(string $key, string $locale, string $fallbackTitle, float|int $value, string $valueType, string $accent, array $currency, ?array $change = null): array
    {
        return [
            'key' => $key,
            'title' => $this->ownerText('metrics.'.$key, $locale, $fallbackTitle),
            'value' => $value,
            'value_type' => $valueType,
            'formatted_value' => $valueType === 'money'
                ? $this->formatMoney((float) $value, $currency)
                : number_format((float) $value, 0),
            'accent' => $accent,
            'change' => $change,
        ];
    }

    private function alertPayload(string $key, string $locale, string $fallbackTitle, int $count, string $backgroundColor, string $textColor): array
    {
        return [
            'key' => $key,
            'title' => $this->ownerText('alerts.'.$key, $locale, $fallbackTitle),
            'count' => $count,
            'background_color' => $backgroundColor,
            'text_color' => $textColor,
        ];
    }

    private function snapshotChangePayload(int $tenantId, int|array|null $branchId, string $metricKey, float $current, Carbon $comparisonDate, string $locale): ?array
    {
        $previous = $this->snapshotValueForScope($tenantId, $branchId, $metricKey, $comparisonDate);

        return $previous === null ? null : $this->changePayload($current, $previous, $locale);
    }

    private function dateRangeRevenueChangePayload(int $tenantId, int|array|null $branchId, float $current, Carbon $dateFrom, Carbon $dateTo, string $locale): array
    {
        $days = $dateFrom->diffInDays($dateTo) + 1;
        $comparisonTo = $dateFrom->copy()->subDay();
        $comparisonFrom = $comparisonTo->copy()->subDays($days - 1);
        $previous = $this->dashboardMetrics->paymentTotalForDateRange(
            $this->dashboardMetrics->paymentsQuery($tenantId, $branchId),
            $comparisonFrom,
            $comparisonTo
        );

        return $this->changePayload($current, $previous, $locale, [
            'comparison' => 'previous_period',
            'comparison_label' => $this->ownerText('comparisons.previous_period', $locale, 'Previous period'),
            'comparison_period' => [
                'from' => $comparisonFrom->toDateString(),
                'to' => $comparisonTo->toDateString(),
            ],
        ]);
    }

    private function snapshotValueForScope(int $tenantId, int|array|null $branchId, string $metricKey, Carbon $comparisonDate): ?float
    {
        if (!is_array($branchId)) {
            return $this->dashboardMetrics->snapshotValue($tenantId, $branchId, $metricKey, $comparisonDate);
        }

        if ($branchId === []) {
            return 0.0;
        }

        $values = [];
        foreach ($branchId as $id) {
            $value = $this->dashboardMetrics->snapshotValue($tenantId, (int) $id, $metricKey, $comparisonDate);
            if ($value === null) {
                return null;
            }

            $values[] = $value;
        }

        return round(array_sum($values), 2);
    }

    private function changePayload(float $current, float $previous, string $locale, array $overrides = []): array
    {
        $difference = round($current - $previous, 2);
        $percent = $previous > 0
            ? round(($difference / $previous) * 100, 2)
            : ($current == 0.0 ? 0.0 : null);

        return array_merge([
            'value' => $difference,
            'percent' => $percent === null ? null : abs($percent),
            'direction' => $difference > 0 ? 'up' : ($difference < 0 ? 'down' : 'flat'),
            'comparison' => 'yesterday',
            'comparison_label' => $this->ownerText('comparisons.yesterday', $locale, 'Yesterday'),
        ], $overrides);
    }

    private function formatMoney(float $amount, array $currency): string
    {
        $symbol = trim((string) ($currency['symbol'] ?? $currency['code'] ?? ''));

        return trim($symbol.' '.number_format($amount, 2));
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
}
