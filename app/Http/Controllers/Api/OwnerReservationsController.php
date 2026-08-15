<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Car;
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

class OwnerReservationsController extends Controller
{
    private const PAYMENT_STATUSES = ['paid', 'partial', 'not_paid'];

    public function __construct(private readonly BranchAccess $branchAccess)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $branchId = $this->resolveOwnerBranchId($request, $user);

        $validated = $request->validate($this->filterRules());
        $filters = $this->normalizedFilters($validated);

        $tenantId = (int) $user->tenant_id;
        $tenant = Tenant::query()->with('siteSetting')->findOrFail($tenantId);
        $currency = CurrencyCatalog::forTenant($tenant, null, $locale);

        $query = $this->baseReservationQuery($tenantId)
            ->with([
                'user:id,name,email',
                'car:id,tenant_id,branch_id,make,model,year,license_plate,license_plate_format,status,price_per_day,description,description_translations',
                'car.branch:id,name,country,city,address',
                'car.files',
                'contract:id,tenant_id,branch_id,reservation_id,contract_number,status,start_date,end_date',
            ]);

        $this->applyBranchScope($query, $branchId);
        $this->applyPaidAmountJoin($query, $tenantId);

        $this->applyReservationFilters($query, $filters)
            ->orderByDesc('reservations.start_date')
            ->orderByDesc('reservations.pickup_time')
            ->orderByDesc('reservations.id');

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'branch_id' => $branchId,
            'currency' => $currency,
            'filters' => $filters,
            'summary' => $this->summaryPayload($tenantId, $branchId, $locale, $filters),
            'data' => $paginator->getCollection()
                ->map(fn (Reservation $reservation): array => $this->reservationPayload($reservation, $locale, $currency))
                ->values()
                ->all(),
            'pagination' => $this->paginationPayload($paginator),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $branchId = $this->resolveOwnerBranchId($request, $user);
        $filters = $this->normalizedFilters($request->validate($this->filterRules()));

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'branch_id' => $branchId,
            'filters' => $filters,
            'data' => $this->summaryPayload((int) $user->tenant_id, $branchId, $locale, $filters),
        ]);
    }

    public function statuses(Request $request): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $branchId = $this->resolveOwnerBranchId($request, $user);
        $statusCounts = $this->reservationStatusCounts((int) $user->tenant_id, $branchId);

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'branch_id' => $branchId,
            'data' => $this->reservationStatusesPayload($statusCounts, $locale),
        ]);
    }

    public function show(Request $request, Reservation $reservation): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $branchId = $this->resolveOwnerBranchId($request, $user);

        abort_unless((int) $reservation->tenant_id === (int) $user->tenant_id, 404);

        $reservation->load([
            'user:id,name,email',
            'car:id,tenant_id,branch_id,make,model,year,license_plate,license_plate_format,status,price_per_day,description,description_translations',
            'car.branch:id,name,country,city,address',
            'car.files',
            'payments:id,tenant_id,reservation_id,amount,base_amount,currency,base_currency,exchange_rate,payment_method,status,processed_at,created_at',
            'contract:id,tenant_id,branch_id,reservation_id,contract_number,status,start_date,end_date,created_at',
            'returnStatusReport:id,tenant_id,contract_id,reservation_id,status,payment_status,total_extra_charges,created_at',
        ]);

        $carBranchId = $reservation->car?->branch_id ? (int) $reservation->car->branch_id : null;
        abort_unless($this->branchAccess->canAccessBranchId($user, $carBranchId), 403);
        if ($branchId && $carBranchId !== $branchId) {
            abort(404);
        }

        $tenant = Tenant::query()->with('siteSetting')->findOrFail((int) $user->tenant_id);
        $currency = CurrencyCatalog::forTenant($tenant, null, $locale);

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'data' => array_merge($this->reservationPayload($reservation, $locale, $currency), [
                'payments' => $reservation->payments
                    ->map(fn (Payment $payment): array => [
                        'id' => $payment->id,
                        'amount' => (float) $payment->amount,
                        'base_amount' => (float) ($payment->base_amount ?? $payment->amount),
                        'currency' => $payment->currency,
                        'base_currency' => $payment->base_currency,
                        'exchange_rate' => $payment->exchange_rate ? (float) $payment->exchange_rate : null,
                        'payment_method' => $payment->payment_method,
                        'status' => $payment->status instanceof PaymentStatus ? $payment->status->value : (string) $payment->status,
                        'processed_at' => $payment->processed_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
                'timeline' => $this->timelinePayload($reservation, $locale),
                'return_status_report' => $reservation->returnStatusReport ? [
                    'id' => $reservation->returnStatusReport->id,
                    'status' => $reservation->returnStatusReport->status,
                    'payment_status' => $reservation->returnStatusReport->payment_status,
                    'total_extra_charges' => (float) $reservation->returnStatusReport->total_extra_charges,
                    'created_at' => $reservation->returnStatusReport->created_at?->toIso8601String(),
                ] : null,
            ]),
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

    private function filterRules(): array
    {
        return [
            'status' => ['nullable', Rule::in(array_merge(['all'], array_map(
                static fn (ReservationStatus $status): string => $status->value,
                ReservationStatus::cases()
            )))],
            'payment_status' => ['nullable', Rule::in(array_merge(['all'], self::PAYMENT_STATUSES))],
            'date' => ['nullable', 'date'],
            'date_type' => ['nullable', Rule::in(['pickup', 'return', 'created'])],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function normalizedFilters(array $validated): array
    {
        $search = trim((string) ($validated['search'] ?? ''));

        return [
            'status' => (string) ($validated['status'] ?? 'all'),
            'payment_status' => (string) ($validated['payment_status'] ?? 'all'),
            'date' => isset($validated['date']) ? Carbon::parse((string) $validated['date'])->toDateString() : null,
            'date_type' => (string) ($validated['date_type'] ?? 'pickup'),
            'search' => $search !== '' ? $search : null,
        ];
    }

    private function resolveOwnerBranchId(Request $request, User $user): ?int
    {
        $branchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));

        $resolvedBranchId = $this->branchAccess->resolveAccessibleBranchId($user, $branchId);

        if (!$this->branchAccess->canAccessAllBranches($user)) {
            return $resolvedBranchId;
        }

        if (!$resolvedBranchId) {
            return null;
        }

        $exists = Branch::query()
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

    private function baseReservationQuery(int $tenantId): Builder
    {
        return Reservation::query()
            ->where('reservations.tenant_id', $tenantId);
    }

    private function applyBranchScope(Builder $query, ?int $branchId): void
    {
        if (!$branchId) {
            return;
        }

        $query->whereHas('car', fn (Builder $carQuery): Builder => $carQuery->where('branch_id', $branchId));
    }

    private function applyPaidAmountJoin(Builder $query, int $tenantId): void
    {
        $paidTotals = Payment::query()
            ->selectRaw('reservation_id, SUM(COALESCE(base_amount, amount, 0)) as paid_amount')
            ->where('tenant_id', $tenantId)
            ->where('status', PaymentStatus::COMPLETED->value)
            ->groupBy('reservation_id');

        $query
            ->leftJoinSub($paidTotals, 'owner_paid_totals', 'owner_paid_totals.reservation_id', '=', 'reservations.id')
            ->select('reservations.*')
            ->selectRaw('COALESCE(owner_paid_totals.paid_amount, 0) as owner_paid_amount');
    }

    private function applyPaymentStatusFilter(Builder $query, string $paymentStatus): Builder
    {
        return match ($paymentStatus) {
            'paid' => $query->where(function (Builder $query): void {
                $query->whereRaw('COALESCE(reservations.total_amount, 0) <= 0')
                    ->orWhereRaw('COALESCE(owner_paid_totals.paid_amount, 0) >= COALESCE(reservations.total_amount, 0)');
            }),
            'partial' => $query
                ->whereRaw('COALESCE(owner_paid_totals.paid_amount, 0) > 0')
                ->whereRaw('COALESCE(owner_paid_totals.paid_amount, 0) < COALESCE(reservations.total_amount, 0)'),
            'not_paid' => $query
                ->whereRaw('COALESCE(owner_paid_totals.paid_amount, 0) <= 0')
                ->whereRaw('COALESCE(reservations.total_amount, 0) > 0'),
            default => $query,
        };
    }

    private function applyReservationFilters(Builder $query, array $filters): Builder
    {
        $status = (string) ($filters['status'] ?? 'all');
        $paymentStatus = (string) ($filters['payment_status'] ?? 'all');
        $date = $filters['date'] ?? null;
        $dateType = (string) ($filters['date_type'] ?? 'pickup');
        $search = trim((string) ($filters['search'] ?? ''));

        return $query
            ->when($status !== 'all', fn (Builder $query): Builder => $query->where('reservations.status', $status))
            ->when($paymentStatus !== 'all', fn (Builder $query): Builder => $this->applyPaymentStatusFilter($query, $paymentStatus))
            ->when($date, fn (Builder $query): Builder => $this->applyDateFilter($query, $dateType, (string) $date))
            ->when($search !== '', fn (Builder $query): Builder => $this->applySearchFilter($query, $search));
    }

    private function applyDateFilter(Builder $query, string $dateType, string $date): Builder
    {
        return match ($dateType) {
            'return' => $query->whereDate('reservations.end_date', $date),
            'created' => $query->whereDate('reservations.created_at', $date),
            default => $query->whereDate('reservations.start_date', $date),
        };
    }

    private function applySearchFilter(Builder $query, string $search): Builder
    {
        $like = $this->likeTerm($search);
        $tokens = collect(preg_split('/\s+/', $search) ?: [])
            ->map(fn (string $token): string => trim($token))
            ->filter()
            ->values()
            ->all();

        return $query->where(function (Builder $inner) use ($like, $tokens): void {
            $inner->where('reservations.reservation_number', 'like', $like)
                ->orWhereHas('contract', fn (Builder $contractQuery): Builder => $contractQuery->where('contract_number', 'like', $like))
                ->orWhereHas('user', function (Builder $userQuery) use ($like): void {
                    $userQuery->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                })
                ->orWhereHas('car', function (Builder $carQuery) use ($like, $tokens): void {
                    $carQuery->where(function (Builder $carInner) use ($like): void {
                        $carInner->where('make', 'like', $like)
                            ->orWhere('model', 'like', $like)
                            ->orWhere('year', 'like', $like)
                            ->orWhere('license_plate', 'like', $like)
                            ->orWhereRaw("CONCAT_WS(' ', cars.year, cars.make, cars.model) LIKE ?", [$like])
                            ->orWhereRaw("CONCAT_WS(' ', cars.make, cars.model, cars.year) LIKE ?", [$like]);
                    })
                        ->orWhere(function (Builder $carInner) use ($tokens): void {
                            foreach ($tokens as $token) {
                                $tokenLike = $this->likeTerm($token);

                                $carInner->where(function (Builder $partQuery) use ($tokenLike): void {
                                    $partQuery->where('make', 'like', $tokenLike)
                                        ->orWhere('model', 'like', $tokenLike)
                                        ->orWhere('year', 'like', $tokenLike)
                                        ->orWhere('license_plate', 'like', $tokenLike);
                                });
                            }
                        })
                        ->orWhereHas('branch', fn (Builder $branchQuery): Builder => $branchQuery->where('name', 'like', $like));
                });
        });
    }

    private function likeTerm(string $value): string
    {
        return '%'.addcslashes($value, "\\%_").'%';
    }

    private function reservationStatusCounts(int $tenantId, ?int $branchId): array
    {
        $query = $this->baseReservationQuery($tenantId);
        $this->applyBranchScope($query, $branchId);

        return $query
            ->select('reservations.status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('reservations.status')
            ->pluck('aggregate', 'reservations.status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    private function reservationStatusesPayload(array $statusCounts, string $locale): array
    {
        $all = [[
            'value' => 'all',
            'label' => $this->ownerText('reservations.statuses.all', $locale, 'All'),
            'color' => '#0F2F7F',
            'count' => array_sum($statusCounts),
        ]];

        $statuses = collect(ReservationStatus::cases())
            ->map(fn (ReservationStatus $status): array => [
                'value' => $status->value,
                'label' => $this->reservationStatusLabel($status->value, $locale),
                'color' => $status->color(),
                'count' => (int) ($statusCounts[$status->value] ?? 0),
            ])
            ->values()
            ->all();

        return array_merge($all, $statuses);
    }

    private function summaryPayload(int $tenantId, ?int $branchId, string $locale, array $filters): array
    {
        $baseQuery = $this->baseReservationQuery($tenantId);
        $this->applyBranchScope($baseQuery, $branchId);
        $this->applyPaidAmountJoin($baseQuery, $tenantId);
        $this->applyReservationFilters($baseQuery, $filters);

        $todayQuery = clone $baseQuery;
        $waitingPickupQuery = clone $baseQuery;
        $today = Carbon::today()->toDateString();

        return [
            'total_reservations' => [
                'key' => 'total_reservations',
                'label' => $this->ownerText('reservations.summary.total_reservations', $locale, 'Total reservations'),
                'value' => (int) $baseQuery->count(),
                'accent' => '#22C55E',
            ],
            'today_reservations' => [
                'key' => 'today_reservations',
                'label' => $this->ownerText('reservations.summary.today_reservations', $locale, 'Today reservations'),
                'value' => (int) $todayQuery->whereDate('reservations.start_date', $today)->count(),
                'accent' => '#3B82F6',
            ],
            'waiting_pickup' => [
                'key' => 'waiting_pickup',
                'label' => $this->ownerText('reservations.summary.waiting_pickup', $locale, 'Waiting pickup'),
                'value' => (int) $waitingPickupQuery
                    ->whereIn('reservations.status', [ReservationStatus::PENDING->value, ReservationStatus::CONFIRMED->value])
                    ->whereDate('reservations.start_date', '<=', $today)
                    ->count(),
                'accent' => '#F59E0B',
            ],
        ];
    }

    private function reservationPayload(Reservation $reservation, string $locale, array $currency): array
    {
        $status = $reservation->status instanceof ReservationStatus
            ? $reservation->status
            : ReservationStatus::tryFrom((string) $reservation->status);
        $statusValue = $status?->value ?? (string) $reservation->status;
        $paidAmount = $this->paidAmount($reservation);
        $totalAmount = (float) $reservation->total_amount;
        $balanceDue = max(0, $totalAmount - $paidAmount);
        $paymentStatus = $this->paymentStatusValue($totalAmount, $paidAmount);

        return [
            'id' => $reservation->id,
            'reservation_id' => $reservation->id,
            'reservation_number' => $reservation->reservation_number,
            'client' => [
                'id' => $reservation->user_id,
                'name' => $reservation->user?->name ?? $this->ownerText('reservations.labels.unknown_client', $locale, 'Client'),
                'email' => $reservation->user?->email,
                'phone' => null,
            ],
            'client_name' => $reservation->user?->name ?? $this->ownerText('reservations.labels.unknown_client', $locale, 'Client'),
            'car' => $this->carPayload($reservation->car, $locale),
            'branch' => $this->branchPayload($reservation->car),
            'status' => $statusValue,
            'status_label' => $this->reservationStatusLabel($statusValue, $locale),
            'status_color' => $status?->color() ?? '#6B7280',
            'payment_status' => $paymentStatus,
            'payment_status_label' => $this->paymentStatusLabel($paymentStatus, $locale),
            'payment_status_color' => $this->paymentStatusColor($paymentStatus),
            'total_amount' => $totalAmount,
            'formatted_total_amount' => $this->formatMoney($totalAmount, $currency),
            'amount_paid' => $paidAmount,
            'formatted_amount_paid' => $this->formatMoney($paidAmount, $currency),
            'balance_due' => $balanceDue,
            'formatted_balance_due' => $this->formatMoney($balanceDue, $currency),
            'start_date' => $reservation->start_date?->toDateString(),
            'end_date' => $reservation->end_date?->toDateString(),
            'pickup_time' => $reservation->pickup_time?->format('H:i'),
            'return_time' => $reservation->return_time?->format('H:i'),
            'pickup_at' => $this->dateTimeValue($reservation->start_date, $reservation->pickup_time),
            'return_at' => $this->dateTimeValue($reservation->end_date, $reservation->return_time),
            'pickup_location' => $reservation->pickup_location,
            'return_location' => $reservation->return_location,
            'total_days' => (int) $reservation->total_days,
            'contract' => $reservation->contract ? [
                'id' => $reservation->contract->id,
                'contract_number' => $reservation->contract->contract_number,
                'status' => $reservation->contract->status instanceof \BackedEnum ? $reservation->contract->status->value : (string) $reservation->contract->status,
            ] : null,
            'created_at' => $reservation->created_at?->toIso8601String(),
        ];
    }

    private function carPayload(?Car $car, string $locale): ?array
    {
        if (!$car) {
            return null;
        }

        return [
            'id' => $car->id,
            'make' => $car->make,
            'model' => $car->model,
            'year' => $car->year,
            'name' => trim(sprintf('%s %s %s', (string) $car->year, (string) $car->make, (string) $car->model)),
            'display_name' => trim(sprintf('%s %s %s', (string) $car->make, (string) $car->model, (string) $car->year)),
            'license_plate' => $car->license_plate,
            'license_plate_format' => $car->license_plate_format,
            'image_url' => $this->absoluteUrl((string) $car->image_url),
            'status' => $car->status instanceof \BackedEnum ? $car->status->value : (string) $car->status,
            'description' => method_exists($car, 'localizedDescription') ? $car->localizedDescription($locale) : null,
        ];
    }

    private function branchPayload(?Car $car): ?array
    {
        if (!$car?->branch) {
            return null;
        }

        return [
            'id' => $car->branch->id,
            'name' => $car->branch->name,
            'country' => $car->branch->country,
            'city' => $car->branch->city,
            'address' => $car->branch->address,
            'location_label' => $this->branchLocationLabel($car),
        ];
    }

    private function paidAmount(Reservation $reservation): float
    {
        if (isset($reservation->owner_paid_amount)) {
            return (float) $reservation->owner_paid_amount;
        }

        if ($reservation->relationLoaded('payments')) {
            return (float) $reservation->payments
                ->filter(fn (Payment $payment): bool => ($payment->status instanceof PaymentStatus ? $payment->status->value : (string) $payment->status) === PaymentStatus::COMPLETED->value)
                ->sum(fn (Payment $payment): float => (float) ($payment->base_amount ?? $payment->amount ?? 0));
        }

        return (float) $reservation->payments()
            ->where('status', PaymentStatus::COMPLETED->value)
            ->sum(DB::raw('COALESCE(base_amount, amount, 0)'));
    }

    private function paymentStatusValue(float $totalAmount, float $paidAmount): string
    {
        if ($totalAmount <= 0 || $paidAmount >= $totalAmount) {
            return 'paid';
        }

        if ($paidAmount <= 0) {
            return 'not_paid';
        }

        return 'partial';
    }

    private function paymentStatusLabel(string $status, string $locale): string
    {
        return $this->ownerText('reservations.payment_statuses.'.$status, $locale, ucfirst(str_replace('_', ' ', $status)));
    }

    private function paymentStatusColor(string $status): string
    {
        return match ($status) {
            'paid' => '#22C55E',
            'partial' => '#0EA5E9',
            default => '#EF4444',
        };
    }

    private function reservationStatusLabel(string $status, string $locale): string
    {
        return $this->ownerText('reservations.statuses.'.$status, $locale, ucfirst(str_replace('_', ' ', $status)));
    }

    private function timelinePayload(Reservation $reservation, string $locale): array
    {
        $events = [[
            'key' => 'reservation_created',
            'label' => $this->ownerText('reservations.timeline.reservation_created', $locale, 'Reservation created'),
            'date' => $reservation->created_at?->toIso8601String(),
        ]];

        if ($reservation->contract) {
            $events[] = [
                'key' => 'contract_created',
                'label' => $this->ownerText('reservations.timeline.contract_created', $locale, 'Contract created'),
                'date' => $reservation->contract->created_at?->toIso8601String(),
            ];
        }

        if ($reservation->returnStatusReport) {
            $events[] = [
                'key' => 'return_report_created',
                'label' => $this->ownerText('reservations.timeline.return_report_created', $locale, 'Return report created'),
                'date' => $reservation->returnStatusReport->created_at?->toIso8601String(),
            ];
        }

        return collect($events)
            ->filter(fn (array $event): bool => !empty($event['date']))
            ->sortBy('date')
            ->values()
            ->all();
    }

    private function dateTimeValue(mixed $date, mixed $time): ?string
    {
        if (!$date) {
            return null;
        }

        $dateValue = $date instanceof Carbon ? $date->toDateString() : Carbon::parse((string) $date)->toDateString();
        $timeValue = $time instanceof Carbon ? $time->format('H:i:s') : ($time ? Carbon::parse((string) $time)->format('H:i:s') : '00:00:00');

        return Carbon::parse($dateValue.' '.$timeValue)->toIso8601String();
    }

    private function branchLocationLabel(Car $car): ?string
    {
        $parts = array_values(array_filter([
            $car->branch?->country,
            $car->branch?->city,
        ], fn (mixed $value): bool => trim((string) $value) !== ''));

        return $parts ? implode(' - ', $parts) : null;
    }

    private function absoluteUrl(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

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
