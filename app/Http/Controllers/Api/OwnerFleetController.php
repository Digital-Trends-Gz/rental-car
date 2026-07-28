<?php

namespace App\Http\Controllers\Api;

use App\Enums\CarStatus;
use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Contract;
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
        $start = Carbon::today()->startOfMonth();
        $end = Carbon::today()->endOfMonth();

        $query = Payment::query()
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
            ->selectRaw('reservations.car_id, SUM(COALESCE(payments.base_amount, payments.amount, 0)) as revenue');

        return $query
            ->pluck('revenue', 'reservations.car_id')
            ->map(fn (mixed $value): float => (float) $value)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function nextEventsByCar(int $tenantId, ?int $branchId, ?int $carId = null): array
    {
        $today = Carbon::today()->toDateString();

        $reservationRows = Reservation::query()
            ->with(['user:id,name,email'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [ReservationStatus::PENDING->value, ReservationStatus::CONFIRMED->value, ReservationStatus::ACTIVE->value])
            ->whereDate('start_date', '>=', $today)
            ->when($branchId, fn (Builder $query): Builder => $query->whereHas('car', fn (Builder $carQuery): Builder => $carQuery->where('branch_id', $branchId)))
            ->when($carId, fn (Builder $query): Builder => $query->where('car_id', $carId))
            ->orderBy('start_date')
            ->orderBy('pickup_time')
            ->get()
            ->groupBy('car_id');

        $contractRows = Contract::query()
            ->with(['reservation.user:id,name,email'])
            ->where('tenant_id', $tenantId)
            ->where('status', ContractStatus::ACTIVE->value)
            ->whereDate('end_date', '>=', $today)
            ->whereHas('reservation')
            ->when($branchId, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
            ->when($carId, fn (Builder $query): Builder => $query->whereHas('reservation', fn (Builder $reservationQuery): Builder => $reservationQuery->where('car_id', $carId)))
            ->orderBy('end_date')
            ->get()
            ->groupBy(fn (Contract $contract): int => (int) $contract->reservation?->car_id);

        $events = [];

        foreach ($reservationRows as $groupCarId => $reservations) {
            $reservation = $reservations->first();
            if ($reservation instanceof Reservation) {
                $events[(int) $groupCarId] = [
                    'type' => 'reservation',
                    'label_key' => 'fleet.next_events.next_reservation',
                    'date' => $reservation->start_date?->toDateString(),
                    'time' => $reservation->pickup_time?->format('H:i'),
                    'reservation_id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'client_name' => $reservation->user?->name,
                ];
            }
        }

        foreach ($contractRows as $groupCarId => $contracts) {
            $contract = $contracts->first();
            if (!$contract instanceof Contract) {
                continue;
            }

            $current = $events[(int) $groupCarId] ?? null;
            $contractDate = $contract->end_date?->toDateString();

            if ($current && $contractDate && ($current['date'] ?? null) && strcmp((string) $current['date'], $contractDate) <= 0) {
                continue;
            }

            $events[(int) $groupCarId] = [
                'type' => 'return',
                'label_key' => 'fleet.next_events.return_date',
                'date' => $contractDate,
                'time' => $contract->reservation?->return_time?->format('H:i'),
                'contract_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'reservation_id' => $contract->reservation_id,
                'client_name' => $contract->reservation?->user?->name,
            ];
        }

        return $events;
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
}
