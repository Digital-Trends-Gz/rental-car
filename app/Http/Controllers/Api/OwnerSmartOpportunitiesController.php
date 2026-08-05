<?php

namespace App\Http\Controllers\Api;

use App\Enums\CarStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Car;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnerSmartOpportunitiesController extends Controller
{
    public function __construct(
        private readonly BranchAccess $branchAccess,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $branchId = $this->resolveOwnerBranchId($request, $user);

        $tenantId = (int) $user->tenant_id;
        $tenant = Tenant::query()->with(['siteSetting', 'subscriptionPlan'])->findOrFail($tenantId);

        if ($deniedResponse = $this->reportsModuleDeniedResponse($tenant, $locale)) {
            return $deniedResponse;
        }

        $currency = CurrencyCatalog::forTenant($tenant, null, $locale);

        $idleCars = $this->idleCarsCount($tenantId, $branchId);
        $idleCarImage = $this->idleCarImage($tenantId, $branchId);
        $opportunities = $this->generateOpportunities($tenantId, $branchId, $currency, $locale);

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'summary' => [
                'idle_cars_count' => $idleCars,
                'title' => $this->ownerText('smart_opportunities.idle_cars_title', $locale, 'Idle cars now'),
                'subtitle' => $this->ownerText('smart_opportunities.idle_cars_subtitle', $locale, 'idle cars'),
                'image_url' => $idleCarImage,
            ],
            'opportunities_section_title' => $this->ownerText('smart_opportunities.section_title', $locale, 'Smart Opportunities'),
            'opportunities_section_subtitle' => $this->ownerText('smart_opportunities.section_subtitle', $locale, 'AI-powered suggestions to increase revenue'),
            'opportunities' => $opportunities,
        ]);
    }

    private function idleCarsCount(int $tenantId, ?int $branchId): int
    {
        $query = Car::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('status', CarStatus::AVAILABLE->value);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->count();
    }

    private function idleCarImage(int $tenantId, ?int $branchId): ?string
    {
        $car = Car::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('status', CarStatus::AVAILABLE->value)
            ->when($branchId, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
            ->latest('updated_at')
            ->first();

        return $car?->image_url;
    }

    private function generateOpportunities(int $tenantId, ?int $branchId, array $currency, string $locale): array
    {
        $opportunities = [];
        $id = 1;

        $redistribution = $this->redistributionOpportunity($tenantId, $branchId, $currency, $locale, $id);
        if ($redistribution) {
            $opportunities[] = $redistribution;
            $id++;
        }

        $promotional = $this->promotionalOfferOpportunity($tenantId, $branchId, $locale, $id);
        if ($promotional) {
            $opportunities[] = $promotional;
            $id++;
        }

        $pricing = $this->pricingOpportunity($tenantId, $branchId, $currency, $locale, $id);
        if ($pricing) {
            $opportunities[] = $pricing;
        }

        return $opportunities;
    }

    private function redistributionOpportunity(int $tenantId, ?int $branchId, array $currency, string $locale, int $id): ?array
    {
        if ($branchId) {
            return null;
        }

        $branches = Branch::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($branches->count() < 2) {
            return null;
        }

        $last30DaysFrom = Carbon::today()->subDays(29);
        $last30DaysTo = Carbon::today();

        $branchDemand = DB::table('reservations')
            ->join('cars', 'cars.id', '=', 'reservations.car_id')
            ->where('reservations.tenant_id', $tenantId)
            ->whereNotIn('reservations.status', [ReservationStatus::CANCELLED->value])
            ->whereBetween(DB::raw('DATE(reservations.created_at)'), [$last30DaysFrom->toDateString(), $last30DaysTo->toDateString()])
            ->selectRaw('cars.branch_id, COUNT(reservations.id) as reservation_count')
            ->groupBy('cars.branch_id')
            ->pluck('reservation_count', 'cars.branch_id');

        $branchAvailable = Car::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('status', CarStatus::AVAILABLE->value)
            ->selectRaw('branch_id, COUNT(*) as available_count')
            ->groupBy('branch_id')
            ->pluck('available_count', 'branch_id');

        $highDemandBranch = null;
        $highDemandCount = 0;
        $lowDemandBranch = null;
        $lowDemandAvailable = 0;

        foreach ($branches as $branch) {
            $demand = (int) ($branchDemand[$branch->id] ?? 0);
            $available = (int) ($branchAvailable[$branch->id] ?? 0);

            if ($demand > $highDemandCount && $available <= 2) {
                $highDemandCount = $demand;
                $highDemandBranch = $branch;
            }

            if ($available > $lowDemandAvailable) {
                $lowDemandAvailable = $available;
                $lowDemandBranch = $branch;
            }
        }

        if (!$highDemandBranch || !$lowDemandBranch || $highDemandBranch->id === $lowDemandBranch->id) {
            return null;
        }

        $suggestedCarsCount = min($lowDemandAvailable, max(1, (int) ceil($lowDemandAvailable * 0.3)));

        $avgDailyRate = (float) Car::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $lowDemandBranch->id)
            ->where('status', CarStatus::AVAILABLE->value)
            ->avg('price_per_day') ?? 0;

        $estimatedRevenue = round($avgDailyRate * $suggestedCarsCount * 15, 2);

        $candidateCars = Car::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $lowDemandBranch->id)
            ->where('status', CarStatus::AVAILABLE->value)
            ->limit(10)
            ->get(['id', 'make', 'model', 'year', 'license_plate', 'price_per_day']);

        $carsData = $candidateCars->map(fn (Car $car): array => [
            'id' => (int) $car->id,
            'make' => $car->make,
            'model' => $car->model,
            'year' => $car->year,
            'name' => trim("{$car->year} {$car->make} {$car->model}"),
            'license_plate' => $car->license_plate,
            'image_url' => $car->image_url ? url($car->image_url) : null,
            'price_per_day' => (float) $car->price_per_day,
        ])->all();

        $sourceDemand = (int) ($branchDemand[$lowDemandBranch->id] ?? 0);
        $sourceAvailable = (int) ($branchAvailable[$lowDemandBranch->id] ?? 0);
        $targetDemand = (int) ($branchDemand[$highDemandBranch->id] ?? 0);
        $targetAvailable = (int) ($branchAvailable[$highDemandBranch->id] ?? 0);

        return [
            'id' => $id,
            'type' => 'redistribution',
            'icon' => 'swap',
            'title' => $this->ownerText('smart_opportunities.redistribution_title', $locale, 'Redistribution suggestion'),
            'description' => str_replace(
                [':target_branch', ':count', ':source_branch'],
                [$highDemandBranch->name, $suggestedCarsCount, $lowDemandBranch->name],
                $this->ownerText('smart_opportunities.redistribution_description', $locale, 'High demand at :target_branch. We suggest transferring :count cars from :source_branch.')
            ),
            'analysis' => [
                'source_branch' => [
                    'id' => (int) $lowDemandBranch->id,
                    'name' => $lowDemandBranch->name,
                    'reservations_last_30_days' => $sourceDemand,
                    'available_cars' => $sourceAvailable,
                    'label' => $this->ownerText('smart_opportunities.low_demand', $locale, 'Low demand / surplus cars'),
                ],
                'target_branch' => [
                    'id' => (int) $highDemandBranch->id,
                    'name' => $highDemandBranch->name,
                    'reservations_last_30_days' => $targetDemand,
                    'available_cars' => $targetAvailable,
                    'label' => $this->ownerText('smart_opportunities.high_demand', $locale, 'High demand / low supply'),
                ],
                'reason' => str_replace(
                    [':target_branch', ':target_reservations', ':target_available', ':source_branch', ':source_available'],
                    [$highDemandBranch->name, $targetDemand, $targetAvailable, $lowDemandBranch->name, $sourceAvailable],
                    $this->ownerText('smart_opportunities.redistribution_reason', $locale, ':target_branch had :target_reservations reservations in 30 days with only :target_available available cars. :source_branch has :source_available idle cars.')
                ),
            ],
            'metric' => [
                'label' => $this->ownerText('smart_opportunities.expected_revenue_increase', $locale, 'Expected revenue increase'),
                'value' => $estimatedRevenue,
                'type' => 'money',
                'formatted_value' => $this->formatMoney($estimatedRevenue, $currency),
            ],
            'action' => [
                'type' => 'view_details',
                'label' => $this->ownerText('smart_opportunities.view_details', $locale, 'View details'),
                'meta' => [
                    'source_branch_id' => (int) $lowDemandBranch->id,
                    'source_branch_name' => $lowDemandBranch->name,
                    'target_branch_id' => (int) $highDemandBranch->id,
                    'target_branch_name' => $highDemandBranch->name,
                    'suggested_cars_count' => $suggestedCarsCount,
                    'cars' => $carsData,
                ],
            ],
        ];
    }

    private function promotionalOfferOpportunity(int $tenantId, ?int $branchId, string $locale, int $id): ?array
    {
        $last30DaysFrom = Carbon::today()->subDays(29);
        $last30DaysTo = Carbon::today();

        $query = Reservation::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [ReservationStatus::CANCELLED->value])
            ->whereBetween(DB::raw('DATE(created_at)'), [$last30DaysFrom->toDateString(), $last30DaysTo->toDateString()]);

        if ($branchId) {
            $query->whereHas('car', fn (Builder $q): Builder => $q
                ->withoutGlobalScope('tenant')
                ->where('branch_id', $branchId));
        }

        $demandByDay = $query
            ->get(['id', 'start_date'])
            ->groupBy(fn (Reservation $r): string => $r->start_date?->format('l') ?? 'Unknown')
            ->map(fn ($rows, string $day): array => [
                'day' => $day,
                'count' => $rows->count(),
            ])
            ->sortBy('count')
            ->values();

        if ($demandByDay->isEmpty()) {
            return null;
        }

        $avgDemand = $demandByDay->avg('count');
        $lowDemandDays = $demandByDay->filter(fn (array $row): bool => $row['count'] < $avgDemand * 0.7);

        if ($lowDemandDays->isEmpty()) {
            return null;
        }

        $lowestDay = $lowDemandDays->first();
        $dropPercent = $avgDemand > 0
            ? round((1 - ($lowestDay['count'] / $avgDemand)) * 100)
            : 0;

        $suggestedDiscount = match (true) {
            $dropPercent >= 60 => 20,
            $dropPercent >= 40 => 15,
            $dropPercent >= 20 => 10,
            default => 8,
        };

        $estimatedBookingIncrease = min(30, max(5, (int) round($suggestedDiscount * 1.2)));

        $dayNames = $lowDemandDays->pluck('day')->values()->all();
        $isWeekend = count(array_intersect($dayNames, ['Saturday', 'Sunday', 'Friday'])) > 0;

        $periodLabel = $isWeekend
            ? $this->ownerText('smart_opportunities.weekend', $locale, 'weekends')
            : $lowestDay['day'];

        return [
            'id' => $id,
            'type' => 'promotional_offer',
            'icon' => 'megaphone',
            'title' => $this->ownerText('smart_opportunities.promotional_title', $locale, 'Promotional offer suggestion'),
            'description' => str_replace(
                [':period', ':discount'],
                [$periodLabel, $suggestedDiscount],
                $this->ownerText('smart_opportunities.promotional_description', $locale, 'Demand drops during :period. We recommend creating a :discount% discount offer to increase bookings.')
            ),
            'metric' => [
                'label' => $this->ownerText('smart_opportunities.expected_booking_increase', $locale, 'Expected booking increase'),
                'value' => (float) $estimatedBookingIncrease,
                'type' => 'percentage',
                'formatted_value' => '+' . $estimatedBookingIncrease . '%',
            ],
            'action' => [
                'type' => 'create_offer',
                'label' => $this->ownerText('smart_opportunities.create_offer', $locale, 'Create offer'),
                'meta' => [
                    'suggested_discount_percent' => $suggestedDiscount,
                    'target_days' => $dayNames,
                ],
            ],
        ];
    }

    private function pricingOpportunity(int $tenantId, ?int $branchId, array $currency, string $locale, int $id): ?array
    {
        $last30DaysFrom = Carbon::today()->subDays(29);
        $last30DaysTo = Carbon::today();

        $topCars = DB::table('reservations')
            ->join('cars', 'cars.id', '=', 'reservations.car_id')
            ->where('reservations.tenant_id', $tenantId)
            ->whereNotIn('reservations.status', [ReservationStatus::CANCELLED->value, ReservationStatus::NO_SHOW->value])
            ->whereBetween(DB::raw('DATE(reservations.start_date)'), [$last30DaysFrom->toDateString(), $last30DaysTo->toDateString()])
            ->when($branchId, fn ($q) => $q->where('cars.branch_id', $branchId))
            ->selectRaw('cars.id as car_id, cars.make, cars.model, cars.year, cars.price_per_day, COUNT(reservations.id) as reservation_count, COALESCE(SUM(reservations.total_days), 0) as utilization_days')
            ->groupBy('cars.id', 'cars.make', 'cars.model', 'cars.year', 'cars.price_per_day')
            ->havingRaw('utilization_days >= 10')
            ->orderByDesc('utilization_days')
            ->limit(1)
            ->first();

        if (!$topCar = $topCars) {
            return null;
        }

        $utilDays = (int) $topCar->utilization_days;
        $suggestedIncrease = match (true) {
            $utilDays >= 25 => 15,
            $utilDays >= 20 => 10,
            $utilDays >= 10 => 7,
            default => 5,
        };

        $currentPrice = (float) $topCar->price_per_day;
        $newPrice = round($currentPrice * (1 + $suggestedIncrease / 100), 2);
        $carName = trim("{$topCar->make} {$topCar->model} {$topCar->year}");

        return [
            'id' => $id,
            'type' => 'pricing_adjustment',
            'icon' => 'trending_up',
            'title' => $this->ownerText('smart_opportunities.pricing_title', $locale, 'Pricing adjustment suggestion'),
            'description' => str_replace(
                [':car_name', ':percent', ':days'],
                [$carName, $suggestedIncrease, $utilDays],
                $this->ownerText('smart_opportunities.pricing_description', $locale, ':car_name has high demand (:days rental days). We suggest a :percent% price increase.')
            ),
            'metric' => [
                'label' => $this->ownerText('smart_opportunities.suggested_new_price', $locale, 'Suggested new price'),
                'value' => $newPrice,
                'type' => 'money',
                'formatted_value' => $this->formatMoney($newPrice, $currency),
            ],
            'action' => [
                'type' => 'view_details',
                'label' => $this->ownerText('smart_opportunities.view_details', $locale, 'View details'),
                'meta' => [
                    'car_id' => (int) $topCar->car_id,
                    'car_name' => $carName,
                    'current_price' => $currentPrice,
                    'suggested_price' => $newPrice,
                    'suggested_increase_percent' => $suggestedIncrease,
                    'utilization_days' => $utilDays,
                ],
            ],
        ];
    }

    private function authorizedOwner(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(!empty($user->tenant_id), 403);
        abort_unless($this->branchAccess->canAccessAllBranches($user), 403);

        return $user;
    }

    private function reportsModuleDeniedResponse(Tenant $tenant, string $locale): ?JsonResponse
    {
        if ($tenant->supportsFeature('reports_module')) {
            return null;
        }

        return response()->json([
            'message' => $this->ownerText(
                'errors.reports_module_not_available',
                $locale,
                'Your current plan does not include access to AI reports.'
            ),
        ], 403);
    }

    private function resolveOwnerBranchId(Request $request, User $user): ?int
    {
        $branchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));

        if (!$branchId) {
            return null;
        }

        $exists = Branch::query()
            ->withoutGlobalScope('tenant')
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

    private function formatMoney(float $amount, array $currency): string
    {
        $symbol = trim((string) ($currency['symbol'] ?? $currency['code'] ?? ''));

        return trim($symbol . ' ' . number_format($amount, 2));
    }

    private function ownerText(string $key, string $locale, string $fallback): string
    {
        $translationKey = 'owner_api.' . $key;
        $fileKey = 'site.' . $translationKey;
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
}
