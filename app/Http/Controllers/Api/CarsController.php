<?php

namespace App\Http\Controllers\Api;

use App\Enums\CarStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class CarsController extends Controller
{
    private string $apiLocale = 'en';

    public function __construct(
        private readonly BranchAccess $branchAccess,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->setApiLocale($request);
        $user = $this->authorizeAdminApiUser($request);

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(array_merge(['all'], array_map(
                static fn (CarStatus $status) => $status->value,
                CarStatus::cases()
            )))],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $branchId = $this->resolveBranchId($request, $user);
        $statusFilter = $validated['status'] ?? 'all';
        $search = trim((string) ($validated['search'] ?? ''));

        $statusCountsQuery = Car::query()->selectRaw('status, count(*) as count');
        $this->branchAccess->applyToQuery($statusCountsQuery, $user, $branchId);
        $statusCounts = $statusCountsQuery
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $carsQuery = Car::query()
            ->with(['branch:id,name', 'files']);

        $this->branchAccess->applyToQuery($carsQuery, $user, $branchId);

        $carsQuery
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('make', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('license_plate', 'like', "%{$search}%");
                });
            })
            ->when($statusFilter !== 'all', fn (Builder $query) => $query->where('status', $statusFilter))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $paginator = $carsQuery->paginate($this->resolvePerPage($request))->withQueryString();

        return response()->json([
            'branch_id' => $branchId,
            'filters' => [
                'status' => $statusFilter,
                'search' => $search !== '' ? $search : null,
            ],
            'count' => $paginator->total(),
            'status_counts' => $this->statusCountsPayload($statusCounts),
            'pagination' => $this->paginationPayload($paginator),
            'cars' => $paginator->getCollection()
                ->map(fn (Car $car) => $this->carPayload($car))
                ->values()
                ->all(),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $this->setApiLocale($request);
        $user = $this->authorizeAdminApiUser($request);
        $branchId = $this->resolveBranchId($request, $user);

        $statusCountsQuery = Car::query()->selectRaw('status, count(*) as count');
        $this->branchAccess->applyToQuery($statusCountsQuery, $user, $branchId);
        $statusCounts = $statusCountsQuery
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        return response()->json([
            'branch_id' => $branchId,
            'statuses' => collect(CarStatus::cases())
                ->map(fn (CarStatus $status) => [
                    'value' => $status->value,
                    'label' => $this->statusLabel($status),
                    'color' => $status->color(),
                    'count' => (int) ($statusCounts[$status->value] ?? 0),
                ])
                ->values()
                ->all(),
        ]);
    }

    private function carPayload(Car $car): array
    {
        $status = $car->status instanceof CarStatus
            ? $car->status
            : CarStatus::tryFrom((string) $car->status);

        return [
            'id' => $car->id,
            'make' => $car->make,
            'model' => $car->model,
            'year' => $car->year,
            'name' => trim(sprintf('%s %s %s', (string) $car->year, (string) $car->make, (string) $car->model)),
            'license_plate' => $car->license_plate,
            'color' => $car->color instanceof \BackedEnum ? $car->color->value : (string) $car->color,
            'price_per_day' => $car->price_per_day,
            'price_per_week' => $car->price_per_week,
            'price_per_month' => $car->price_per_month,
            'allowed_km_per_day' => $car->allowed_km_per_day,
            'allowed_km_per_week' => $car->allowed_km_per_week,
            'allowed_km_per_month' => $car->allowed_km_per_month,
            'mileage' => $car->mileage,
            'transmission' => $car->transmission,
            'seats' => $car->seats,
            'fuel_type' => $car->fuel_type instanceof \BackedEnum ? $car->fuel_type->value : (string) $car->fuel_type,
            'status' => $status?->value ?? (string) $car->status,
            'status_label' => $status ? $this->statusLabel($status) : (string) $car->status,
            'status_color' => $status?->color(),
            'image_url' => $car->image_url,
            'branch_id' => $car->branch_id,
            'branch_name' => $car->branch?->name,
            'updated_at' => $car->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, int|string>  $statusCounts
     * @return array<int, array<string, mixed>>
     */
    private function statusCountsPayload(array $statusCounts): array
    {
        return collect(CarStatus::cases())
            ->map(function (CarStatus $status) use ($statusCounts): array {
                return [
                    'value' => $status->value,
                    'label' => $this->statusLabel($status),
                    'color' => $status->color(),
                    'count' => (int) ($statusCounts[$status->value] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function statusLabel(CarStatus $status): string
    {
        $labels = [
            CarStatus::DRAFT->value => ['en' => 'Draft', 'ar' => 'مسودة'],
            CarStatus::AVAILABLE->value => ['en' => 'Available', 'ar' => 'متاحة'],
            CarStatus::RESERVED->value => ['en' => 'Reserved', 'ar' => 'محجوزة'],
            CarStatus::RENTED->value => ['en' => 'Rented', 'ar' => 'مؤجرة'],
            CarStatus::MAINTENANCE->value => ['en' => 'Maintenance', 'ar' => 'صيانة'],
            CarStatus::CLEANING->value => ['en' => 'Cleaning', 'ar' => 'تنظيف'],
            CarStatus::UNAVAILABLE->value => ['en' => 'Unavailable', 'ar' => 'غير متاحة'],
            CarStatus::RETIRED->value => ['en' => 'Retired', 'ar' => 'متقاعدة'],
        ];

        $localized = $labels[$status->value] ?? null;

        if ($localized === null) {
            return $status->label();
        }

        return $localized[$this->apiLocale] ?? $localized['en'] ?? $status->label();
    }

    private function authorizeAdminApiUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true), 403);

        return $user;
    }

    private function resolveBranchId(Request $request, User $user): ?int
    {
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));

        if ($this->branchAccess->canAccessAllBranches($user)) {
            return $requestedBranchId;
        }

        return (int) ($user->branch_id ?? 0) > 0 ? (int) $user->branch_id : null;
    }

    private function setApiLocale(Request $request): void
    {
        $locales = array_values(array_filter((array) config('app.available_locales', ['en', 'ar', 'ur'])));
        $fallback = in_array(app()->getLocale(), $locales, true) ? app()->getLocale() : 'en';

        $this->apiLocale = $request->getPreferredLanguage($locales) ?: $fallback;
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', 50);

        return max(1, min(100, $perPage));
    }

    private function paginationPayload(LengthAwarePaginator $paginator): array
    {
        $total = $paginator->total();

        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $total,
            'last_page' => $paginator->lastPage(),
            'from' => $total > 0 ? $paginator->firstItem() : null,
            'to' => $total > 0 ? $paginator->lastItem() : null,
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }
}
