<?php

namespace App\Http\Controllers\Api;

use App\Enums\CarStatus;
use App\Enums\CarViolationStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarViolation;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Support\BranchAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly BranchAccess $branchAccess
    ) {
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true), 403);

        $locale = $this->resolveLocale($request);
        $today = Carbon::today();
        $branchId = $this->resolveBranchId($request, $user);

        $carsQuery = Car::query();
        $this->applyCarBranchScope($carsQuery, $user, $branchId);

        $reservationsQuery = Reservation::query();
        $this->applyReservationBranchScope($reservationsQuery, $user, $branchId);

        $contractsQuery = Contract::query();
        $this->applyContractBranchScope($contractsQuery, $user, $branchId);

        $todayPickupsQuery = (clone $reservationsQuery)
            ->with(['user:id,name,email', 'car:id,branch_id,year,make,model,license_plate', 'car.branch:id,name'])
            ->whereDate('start_date', $today)
            ->whereIn('status', [
                ReservationStatus::CONFIRMED->value,
                ReservationStatus::ACTIVE->value,
                ReservationStatus::COMPLETED_WAIT_CONTRACT->value,
            ])
            ->orderBy('start_date')
            ->orderBy('pickup_time')
            ->latest('id');

        $todayReturnsQuery = (clone $contractsQuery)
            ->with([
                'reservation.user:id,name,email',
                'reservation.car:id,branch_id,year,make,model,license_plate',
                'branch:id,name',
            ])
            ->whereDate('end_date', $today)
            ->where('status', 'active')
            ->orderBy('end_date')
            ->latest('id');

        $cleaningCarsQuery = (clone $carsQuery)
            ->with(['branch:id,name'])
            ->where('status', CarStatus::CLEANING->value)
            ->orderBy('updated_at', 'desc')
            ->latest('id');

        $overdueContractsQuery = (clone $contractsQuery)
            ->with([
                'reservation.user:id,name,email',
                'reservation.car:id,branch_id,year,make,model,license_plate',
                'branch:id,name',
            ])
            ->where('status', 'active')
            ->whereDoesntHave('returnStatusReport')
            ->whereDate('end_date', '<', $today)
            ->orderBy('end_date')
            ->latest('id');

        $totalClientsQuery = User::query()
            ->where('role', UserRole::CLIENT);

        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $totalClientsQuery->where('branch_id', $branchId);
            }
        } elseif (!empty($user?->branch_id)) {
            $totalClientsQuery->where('branch_id', (int) $user->branch_id);
        } else {
            $totalClientsQuery->whereRaw('1 = 0');
        }

        $totalClients = $totalClientsQuery->count();

        $cards = [
            $this->reservationCard(
                key: 'today_pickups',
                locale: $locale,
                titleEn: 'Today Pickup',
                titleAr: 'تسليم اليوم',
                accent: '#2563EB',
                count: (clone $todayPickupsQuery)->count(),
                items: $todayPickupsQuery->limit(10)->get()->map(fn (Reservation $reservation) => $this->reservationItem($reservation))->values()->all(),
                descriptionEn: 'Reservations scheduled for pickup today',
                descriptionAr: 'الحجوزات المجدولة للاستلام اليوم',
            ),
            $this->contractCard(
                key: 'today_returns',
                locale: $locale,
                titleEn: 'Today Return',
                titleAr: 'استلام اليوم',
                accent: '#16A34A',
                count: (clone $todayReturnsQuery)->count(),
                items: $todayReturnsQuery->limit(10)->get()->map(fn (Contract $contract) => $this->contractItem($contract))->values()->all(),
                descriptionEn: 'Active contracts ending today',
                descriptionAr: 'العقود النشطة التي تنتهي اليوم',
            ),
            $this->carCard(
                key: 'needs_cleaning',
                locale: $locale,
                titleEn: 'Needs Cleaning',
                titleAr: 'تحتاج تنظيف',
                accent: '#F59E0B',
                count: (clone $cleaningCarsQuery)->count(),
                items: $cleaningCarsQuery->limit(10)->get()->map(fn (Car $car) => $this->carItem($car))->values()->all(),
                descriptionEn: 'Cars currently marked for cleaning',
                descriptionAr: 'السيارات المصنفة حاليًا كتنظيف',
            ),
            $this->contractCard(
                key: 'overdue',
                locale: $locale,
                titleEn: 'Overdue',
                titleAr: 'متأخرة',
                accent: '#EF4444',
                count: (clone $overdueContractsQuery)->count(),
                items: $overdueContractsQuery->limit(10)->get()->map(fn (Contract $contract) => $this->contractItem($contract, true))->values()->all(),
                descriptionEn: 'Active contracts past their end date',
                descriptionAr: 'العقود النشطة التي تجاوزت تاريخ الانتهاء',
            ),
        ];

        $pendingViolationsQuery = CarViolation::query()
            ->where('status', CarViolationStatus::PENDING);
        $this->branchAccess->applyToQuery($pendingViolationsQuery, $user, $branchId, 'branch_id');

        $paymentsQuery = Payment::query()->where('status', PaymentStatus::COMPLETED);
        $this->applyPaymentBranchScope($paymentsQuery, $user, $branchId);

        return response()->json([
            'date' => $today->toDateString(),
            'locale' => $locale,
            'branch_id' => $branchId,
            'stats' => [
                'total_cars' => (clone $carsQuery)->count(),
                'available_cars' => (clone $carsQuery)->where('status', CarStatus::AVAILABLE->value)->count(),
                'active_reservations' => (clone $reservationsQuery)->where('status', ReservationStatus::ACTIVE->value)->count(),
                'pending_reservations' => (clone $reservationsQuery)->where('status', ReservationStatus::PENDING->value)->count(),
                'total_reservations' => (clone $reservationsQuery)->count(),
                'total_clients' => $totalClients,
                'pending_violations' => (clone $pendingViolationsQuery)->count(),
                'total_revenue' => (float) (clone $paymentsQuery)->sum('amount'),
            ],
            'cards' => $cards,
        ]);
    }

    private function resolveLocale(Request $request): string
    {
        $supportedLocales = array_values(array_filter((array) config('app.available_locales', ['en']), static fn ($locale) => is_string($locale) && $locale !== ''));
        $fallback = (string) config('app.fallback_locale', config('app.locale', 'en'));
        $preferred = $request->getPreferredLanguage($supportedLocales);

        if (is_string($preferred) && $preferred !== '') {
            return $preferred;
        }

        return in_array($fallback, $supportedLocales, true) ? $fallback : ($supportedLocales[0] ?? 'en');
    }

    private function localizedText(string $locale, array $translations, string $fallbackKey): string
    {
        $selected = trim((string) ($translations[$locale] ?? ''));
        if ($selected !== '') {
            return $selected;
        }

        $english = trim((string) ($translations['en'] ?? ''));
        if ($english !== '') {
            return $english;
        }

        $arabic = trim((string) ($translations['ar'] ?? ''));
        if ($arabic !== '') {
            return $arabic;
        }

        return ucfirst(str_replace('_', ' ', $fallbackKey));
    }

    private function reservationCard(string $key, string $locale, string $titleEn, string $titleAr, string $accent, int $count, array $items, string $descriptionEn, string $descriptionAr): array
    {
        return [
            'key' => $key,
            'type' => 'reservation',
            'title' => $this->localizedText($locale, [
                'en' => $titleEn,
                'ar' => $titleAr,
            ], $key),
            'description' => $this->localizedText($locale, [
                'en' => $descriptionEn,
                'ar' => $descriptionAr,
            ], $key),
            'accent' => $accent,
            'count' => $count,
            'items' => $items,
        ];
    }

    private function contractCard(string $key, string $locale, string $titleEn, string $titleAr, string $accent, int $count, array $items, string $descriptionEn, string $descriptionAr): array
    {
        return [
            'key' => $key,
            'type' => 'contract',
            'title' => $this->localizedText($locale, [
                'en' => $titleEn,
                'ar' => $titleAr,
            ], $key),
            'description' => $this->localizedText($locale, [
                'en' => $descriptionEn,
                'ar' => $descriptionAr,
            ], $key),
            'accent' => $accent,
            'count' => $count,
            'items' => $items,
        ];
    }

    private function carCard(string $key, string $locale, string $titleEn, string $titleAr, string $accent, int $count, array $items, string $descriptionEn, string $descriptionAr): array
    {
        return [
            'key' => $key,
            'type' => 'car',
            'title' => $this->localizedText($locale, [
                'en' => $titleEn,
                'ar' => $titleAr,
            ], $key),
            'description' => $this->localizedText($locale, [
                'en' => $descriptionEn,
                'ar' => $descriptionAr,
            ], $key),
            'accent' => $accent,
            'count' => $count,
            'items' => $items,
        ];
    }

    private function reservationItem(Reservation $reservation): array
    {
        $car = $reservation->car;
        $user = $reservation->user;

        return [
            'id' => $reservation->id,
            'reservation_number' => $reservation->reservation_number,
            'client_name' => $user?->name,
            'car_name' => trim(sprintf(
                '%s %s %s',
                (string) ($car?->year ?? ''),
                (string) ($car?->make ?? ''),
                (string) ($car?->model ?? '')
            )),
            'license_plate' => (string) ($car?->license_plate ?? ''),
            'branch_name' => (string) ($car?->branch?->name ?? ''),
            'start_date' => optional($reservation->start_date)->toDateString(),
            'end_date' => optional($reservation->end_date)->toDateString(),
            'status' => $reservation->status instanceof ReservationStatus ? $reservation->status->value : (string) $reservation->status,
        ];
    }

    private function contractItem(Contract $contract, bool $isOverdue = false): array
    {
        $reservation = $contract->reservation;
        $car = $reservation?->car;
        $client = $reservation?->user;
        $endDate = optional($contract->end_date)->toDateString();

        return [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'reservation_number' => $reservation?->reservation_number,
            'client_name' => $client?->name,
            'car_name' => trim(sprintf(
                '%s %s %s',
                (string) ($car?->year ?? ''),
                (string) ($car?->make ?? ''),
                (string) ($car?->model ?? '')
            )),
            'license_plate' => (string) ($car?->license_plate ?? ''),
            'branch_name' => (string) ($contract->branch?->name ?? $car?->branch?->name ?? ''),
            'start_date' => optional($contract->start_date)->toDateString(),
            'end_date' => $endDate,
            'days_overdue' => $isOverdue && $contract->end_date
                ? now()->startOfDay()->diffInDays($contract->end_date->copy()->startOfDay())
                : 0,
            'status' => $contract->status instanceof \App\Enums\ContractStatus ? $contract->status->value : (string) $contract->status,
        ];
    }

    private function carItem(Car $car): array
    {
        return [
            'id' => $car->id,
            'car_name' => trim(sprintf('%s %s %s', (string) $car->year, (string) $car->make, (string) $car->model)),
            'license_plate' => (string) ($car->license_plate ?? ''),
            'branch_name' => (string) ($car->branch?->name ?? ''),
            'status' => $car->status instanceof CarStatus ? $car->status->value : (string) $car->status,
            'status_label' => $car->status instanceof CarStatus ? $car->status->label() : (string) $car->status,
        ];
    }

    private function resolveBranchId(Request $request, User $user): ?int
    {
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));

        if ($this->branchAccess->canAccessAllBranches($user)) {
            return $requestedBranchId;
        }

        return (int) ($user->branch_id ?? 0) > 0 ? (int) $user->branch_id : null;
    }

    private function applyCarBranchScope($query, ?User $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where('branch_id', $userBranchId);
    }

    private function applyReservationBranchScope($query, ?User $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->whereHas('car', fn ($q) => $q->where('branch_id', $branchId));
            }

            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('car', fn ($q) => $q->where('branch_id', $userBranchId));
    }

    private function applyContractBranchScope($query, ?User $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->whereHas('reservation.car', fn ($q) => $q->where('branch_id', $branchId));
            }

            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('reservation.car', fn ($q) => $q->where('branch_id', $userBranchId));
    }

    private function applyPaymentBranchScope($query, ?User $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->whereHas('reservation.car', fn ($q) => $q->where('branch_id', $branchId));
            }

            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('reservation.car', fn ($q) => $q->where('branch_id', $userBranchId));
    }
}
