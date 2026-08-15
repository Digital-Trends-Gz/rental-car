<?php

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarDiscount;
use App\Models\Tenant;
use App\Models\User;
use App\Support\BranchAccess;
use App\Support\CurrencyCatalog;
use App\Support\TenantTranslations;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OwnerCarDiscountsController extends Controller
{
    public function __construct(private readonly BranchAccess $branchAccess)
    {
    }

    public function index(Request $request): JsonResponse
    {
        [$user, $locale, $tenant] = $this->beginOwnerRequest($request);
        $tenantId = (int) $user->tenant_id;
        $currency = CurrencyCatalog::forTenant($tenant, null, $locale);

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', 'all'));
        $carId = $this->branchAccess->normalizeRequestedBranchId($request->query('car_id'));

        $query = CarDiscount::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->with('car:id,make,model,year,license_plate,branch_id');

        if ($carId) {
            $query->where('car_id', $carId);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Branch safety: Filter by cars user can access (or if car_id is null, it's global and always visible)
        $query->where(function ($q) use ($user) {
            $q->whereNull('car_id')
                ->orWhereHas('car', function ($carQuery) use ($user) {
                    $this->branchAccess->applyToQuery($carQuery, $user, null);
                });
        });

        $paginator = $query->latest('priority')->latest('id')->paginate($this->perPage($request));

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'data' => collect($paginator->items())->map(fn (CarDiscount $d) => $this->discountPayload($d, $currency, $locale))->all(),
            'pagination' => $this->paginationPayload($paginator),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        [$user, $locale, $tenant] = $this->beginOwnerRequest($request);
        $tenantId = (int) $user->tenant_id;
        $currency = CurrencyCatalog::forTenant($tenant, null, $locale);

        $validated = $this->validateDiscount($request, $tenantId);
        $carId = $validated['car_id'] ? (int) $validated['car_id'] : null;

        if ($carId) {
            $this->resolveAccessibleCar($user, $carId);
        }

        $discount = CarDiscount::create([
            'tenant_id' => $tenantId,
            'car_id' => $carId,
            'created_by' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'value' => $validated['value'],
            'max_discount_amount' => $validated['max_discount_amount'] ?? null,
            'min_total_amount' => $validated['min_total_amount'] ?? null,
            'min_days' => $validated['min_days'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'priority' => (int) ($validated['priority'] ?? 0),
            'is_active' => (bool) $validated['is_active'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => $this->ownerText('car_discounts.created', $locale, 'Car discount created successfully.'),
            'data' => $this->discountPayload($discount, $currency, $locale),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        [$user, $locale, $tenant] = $this->beginOwnerRequest($request);
        $tenantId = (int) $user->tenant_id;
        $currency = CurrencyCatalog::forTenant($tenant, null, $locale);

        $discount = CarDiscount::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->with('car:id,make,model,year,license_plate,branch_id')
            ->findOrFail($id);

        if ($discount->car_id) {
            abort_unless($this->branchAccess->canAccessBranchId($user, $discount->car->branch_id ? (int) $discount->car->branch_id : null), 403);
        }

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'data' => $this->discountPayload($discount, $currency, $locale),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        [$user, $locale, $tenant] = $this->beginOwnerRequest($request);
        $tenantId = (int) $user->tenant_id;
        $currency = CurrencyCatalog::forTenant($tenant, null, $locale);

        $discount = CarDiscount::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->with('car:id,make,model,year,license_plate,branch_id')
            ->findOrFail($id);

        if ($discount->car_id) {
            abort_unless($this->branchAccess->canAccessBranchId($user, $discount->car->branch_id ? (int) $discount->car->branch_id : null), 403);
        }

        $validated = $this->validateDiscount($request, $tenantId);
        $carId = $validated['car_id'] ? (int) $validated['car_id'] : null;

        if ($carId) {
            $this->resolveAccessibleCar($user, $carId);
        }

        $discount->update([
            'car_id' => $carId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'value' => $validated['value'],
            'max_discount_amount' => $validated['max_discount_amount'] ?? null,
            'min_total_amount' => $validated['min_total_amount'] ?? null,
            'min_days' => $validated['min_days'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'priority' => (int) ($validated['priority'] ?? 0),
            'is_active' => (bool) $validated['is_active'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => $this->ownerText('car_discounts.updated', $locale, 'Car discount updated successfully.'),
            'data' => $this->discountPayload($discount->fresh(), $currency, $locale),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        [$user, $locale] = $this->beginOwnerRequest($request);
        $tenantId = (int) $user->tenant_id;

        $discount = CarDiscount::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->with('car:id,make,model,year,license_plate,branch_id')
            ->findOrFail($id);

        if ($discount->car_id) {
            abort_unless($this->branchAccess->canAccessBranchId($user, $discount->car->branch_id ? (int) $discount->car->branch_id : null), 403);
        }

        $discount->delete();

        return response()->json([
            'status' => 'success',
            'message' => $this->ownerText('car_discounts.deleted', $locale, 'Car discount deleted successfully.'),
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

    /**
     * @return array{0: User, 1: string, 2: Tenant}
     */
    private function beginOwnerRequest(Request $request): array
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $tenant = Tenant::query()
            ->with(['siteSetting', 'subscriptionPlan'])
            ->findOrFail((int) $user->tenant_id);

        $this->ensureAutoDiscountsFeature($tenant, $locale);

        return [$user, $locale, $tenant];
    }

    private function ensureAutoDiscountsFeature(Tenant $tenant, string $locale): void
    {
        if ($tenant->supportsFeature('auto_discounts')) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => $this->ownerText(
                'errors.auto_discounts_not_available',
                $locale,
                'Your current plan does not include access to auto discounts.'
            ),
        ], 403));
    }

    private function validateDiscount(Request $request, int $tenantId): array
    {
        if ($request->has('is_active')) {
            $val = $request->input('is_active');
            if ($val === 'true' || $val === '1' || $val === 1) {
                $request->merge(['is_active' => true]);
            } elseif ($val === 'false' || $val === '0' || $val === 0) {
                $request->merge(['is_active' => false]);
            }
        }

        $validated = $request->validate([
            'car_id' => ['nullable', 'integer', Rule::exists('cars', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', 'numeric', 'min:0.01'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0.01'],
            'min_total_amount' => ['nullable', 'numeric', 'min:0'],
            'min_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($validated['type'] === CouponType::PERCENTAGE->value && (float) $validated['value'] > 100) {
            throw ValidationException::withMessages([
                'value' => ['Percentage value cannot exceed 100.'],
            ]);
        }

        return $validated;
    }

    private function resolveAccessibleCar(User $user, int $carId): Car
    {
        $query = Car::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', (int) $user->tenant_id)
            ->whereKey($carId);

        $this->branchAccess->applyToQuery($query, $user, null);
        $car = $query->first();

        abort_if(!$car, 422, 'Selected car is not accessible.');

        return $car;
    }

    private function discountPayload(CarDiscount $d, array $currency, string $locale): array
    {
        $typeLabel = $d->type instanceof CouponType
            ? $d->type->label()
            : ($d->type === 'percentage' ? 'Percentage' : 'Fixed amount');

        $valueFormatted = $d->type === CouponType::PERCENTAGE->value
            ? $d->value . '%'
            : $this->formatMoney((float) $d->value, $currency);

        return [
            'id' => (int) $d->id,
            'car_id' => $d->car_id ? (int) $d->car_id : null,
            'car_name' => $d->car
                ? trim("{$d->car->year} {$d->car->make} {$d->car->model}")
                : $this->ownerText('car_discounts.all_cars', $locale, 'All cars'),
            'car_license_plate' => $d->car?->license_plate,
            'name' => $d->name,
            'description' => $d->description,
            'type' => $d->type instanceof CouponType ? $d->type->value : $d->type,
            'type_label' => $typeLabel,
            'value' => (float) $d->value,
            'value_formatted' => $valueFormatted,
            'max_discount_amount' => $d->max_discount_amount !== null ? (float) $d->max_discount_amount : null,
            'max_discount_amount_formatted' => $d->max_discount_amount !== null ? $this->formatMoney((float) $d->max_discount_amount, $currency) : null,
            'min_total_amount' => $d->min_total_amount !== null ? (float) $d->min_total_amount : null,
            'min_total_amount_formatted' => $d->min_total_amount !== null ? $this->formatMoney((float) $d->min_total_amount, $currency) : null,
            'min_days' => $d->min_days ? (int) $d->min_days : null,
            'starts_at' => $d->starts_at?->toIso8601String(),
            'ends_at' => $d->ends_at?->toIso8601String(),
            'priority' => (int) $d->priority,
            'is_active' => (bool) $d->is_active,
            'created_at' => $d->created_at?->toIso8601String(),
        ];
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

    private function perPage(Request $request): int
    {
        return max(15, min(100, (int) $request->query('per_page', 15)));
    }

    private function paginationPayload(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'count' => $paginator->count(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
