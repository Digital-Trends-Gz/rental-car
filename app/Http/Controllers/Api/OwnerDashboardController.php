<?php

namespace App\Http\Controllers\Api;

use App\Enums\CarStatus;
use App\Enums\CarViolationStatus;
use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Car;
use App\Models\CarViolation;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use App\Models\User;
use App\Services\Notifications\OperationalNotificationsService;
use App\Support\BranchAccess;
use App\Support\CurrencyCatalog;
use App\Support\TenantTranslations;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OwnerDashboardController extends Controller
{
    public function __construct(
        private readonly BranchAccess $branchAccess,
        private readonly OperationalNotificationsService $notifications,
    ) {
    }

    public function branches(Request $request): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);

        $branches = Branch::query()
            ->where('tenant_id', (int) $user->tenant_id)
            ->orderBy('name')
            ->get(['id', 'name', 'country', 'city', 'address', 'phone', 'email'])
            ->map(fn (Branch $branch): array => $this->branchPayload($branch))
            ->values()
            ->all();

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'tenant_id' => (int) $user->tenant_id,
            'can_access_all_branches' => $this->branchAccess->canAccessAllBranches($user),
            'data' => array_values(array_filter([
                $this->branchAccess->canAccessAllBranches($user)
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
        $branchId = $this->resolveOwnerBranchId($request, $user);
        $tenant = Tenant::query()->with('siteSetting')->findOrFail((int) $user->tenant_id);
        $currency = $this->tenantCurrency($tenant, $request);
        $selectedBranch = $branchId
            ? Branch::query()->where('tenant_id', (int) $user->tenant_id)->find($branchId)
            : null;

        $carsQuery = Car::query()->where('tenant_id', (int) $user->tenant_id);
        $this->applyCarBranchScope($carsQuery, $branchId);

        $reservationsQuery = Reservation::query()->where('tenant_id', (int) $user->tenant_id);
        $this->applyReservationBranchScope($reservationsQuery, $branchId);

        $contractsQuery = Contract::query()->where('tenant_id', (int) $user->tenant_id);
        $this->applyContractBranchScope($contractsQuery, $branchId);

        $paymentsQuery = Payment::query()
            ->where('tenant_id', (int) $user->tenant_id)
            ->where('status', PaymentStatus::COMPLETED->value);
        $this->applyPaymentBranchScope($paymentsQuery, $branchId);

        $todayRevenue = $this->paymentTotalForDate((clone $paymentsQuery), $today);
        $yesterdayRevenue = $this->paymentTotalForDate((clone $paymentsQuery), $today->copy()->subDay());
        $revenueChange = $this->changePayload($todayRevenue, $yesterdayRevenue, $locale);

        $availableCars = (clone $carsQuery)->where('status', CarStatus::AVAILABLE->value)->count();
        $activeReservations = (clone $reservationsQuery)->where('status', ReservationStatus::ACTIVE->value)->count();
        $lateReturns = (clone $contractsQuery)
            ->pendingReturnTask($today)
            ->whereDate('end_date', '<', $today)
            ->count();
        $rentedCars = (clone $carsQuery)->where('status', CarStatus::RENTED->value)->count();
        $maintenanceCars = (clone $carsQuery)->where('status', CarStatus::MAINTENANCE->value)->count();

        $pendingViolationsQuery = CarViolation::query()
            ->where('tenant_id', (int) $user->tenant_id)
            ->where('status', CarViolationStatus::PENDING->value);
        $this->branchAccess->applyToQuery($pendingViolationsQuery, $user, $branchId, 'branch_id');
        $pendingViolations = (clone $pendingViolationsQuery)->count();

        $notificationBadgeCount = $this->notifications
            ->unreadForUser($user, $branchId, 500, $locale)
            ->count();

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'date' => $today->toDateString(),
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
                $this->metricCard('available_cars', $locale, 'Available cars', $availableCars, 'count', '#0EA5E9', $currency),
                $this->metricCard('active_reservations', $locale, 'Active reservations', $activeReservations, 'count', '#14B8A6', $currency),
                $this->metricCard('late_returns', $locale, 'Late returns', $lateReturns, 'count', '#EF4444', $currency),
                $this->metricCard('rented_cars', $locale, 'Rented cars', $rentedCars, 'count', '#8B5CF6', $currency),
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
            'revenue_chart' => $this->revenueChart((clone $paymentsQuery), $today->copy()->subDays(6), $today),
            'quick_alerts' => [
                $this->alertPayload('late_returns', $locale, 'Late car returns', $lateReturns, '#FEE2E2', '#DC2626'),
                $this->alertPayload('unpaid_violations', $locale, 'Unpaid violations', $pendingViolations, '#FFEDD5', '#EA580C'),
                $this->alertPayload('maintenance_cars', $locale, 'Cars need maintenance', $maintenanceCars, '#F1F5F9', '#475569'),
            ],
            'notification_badge_count' => $notificationBadgeCount,
        ]);
    }

    private function authorizedOwner(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($user->role === UserRole::ADMIN, 403);
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

    private function paymentTotalForDate(Builder $query, Carbon $date): float
    {
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

    private function revenueChart(Builder $query, Carbon $from, Carbon $to): array
    {
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

    private function changePayload(float $current, float $previous, string $locale): array
    {
        $difference = round($current - $previous, 2);
        $percent = $previous > 0
            ? round(($difference / $previous) * 100, 2)
            : ($current > 0 ? 100.0 : 0.0);

        return [
            'value' => $difference,
            'percent' => abs($percent),
            'direction' => $difference > 0 ? 'up' : ($difference < 0 ? 'down' : 'flat'),
            'comparison' => 'yesterday',
            'comparison_label' => $this->ownerText('comparisons.yesterday', $locale, 'Yesterday'),
        ];
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
