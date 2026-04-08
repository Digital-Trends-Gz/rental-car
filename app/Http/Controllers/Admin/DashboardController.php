<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CarStatus;
use App\Enums\CarViolationStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarDocument;
use App\Models\CarViolation;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Support\BranchAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private BranchAccess $branchAccess)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

        $branchOptions = $this->branchAccess
            ->availableBranchesForUser($user)
            ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])
            ->values();

        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;

        // ── KPI Stats ────────────────────────────────────────────────
        $carsQuery = Car::query();
        $this->applyCarBranchScope($carsQuery, $user, $branchId);

        $totalCars      = (clone $carsQuery)->count();
        $availableCars  = (clone $carsQuery)->where('status', CarStatus::AVAILABLE)->count();

        $reservationsQuery = Reservation::query();
        $this->applyReservationBranchScope($reservationsQuery, $user, $branchId);

        $activeReservations  = (clone $reservationsQuery)->where('status', ReservationStatus::ACTIVE)->count();
        $pendingReservations = (clone $reservationsQuery)->where('status', ReservationStatus::PENDING)->count();
        $totalReservations   = (clone $reservationsQuery)->count();

        $pendingViolationsQuery = CarViolation::query()
            ->where('status', CarViolationStatus::PENDING);
        $this->branchAccess->applyToQuery($pendingViolationsQuery, $user, $branchId, 'branch_id');
        $pendingViolations = (clone $pendingViolationsQuery)->count();

        $paymentsQuery = Payment::query()->where('status', PaymentStatus::COMPLETED);
        $this->applyPaymentBranchScope($paymentsQuery, $user, $branchId);
        $totalRevenue = (clone $paymentsQuery)->sum('amount');

        $totalClients = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'client'))
            ->count();

        // ── Reservations by Status ────────────────────────────────────
        $reservationsByStatus = collect(ReservationStatus::cases())->map(function ($status) use ($reservationsQuery) {
            return [
                'status' => $status->value,
                'label'  => ucfirst(str_replace('_', ' ', $status->value)),
                'count'  => (clone $reservationsQuery)->where('status', $status->value)->count(),
                'color'  => ReservationStatus::statusColors()[$status->value] ?? '#6B7280',
            ];
        })->values();

        // ── Fleet Status ──────────────────────────────────────────────
        $fleetStatus = collect(CarStatus::cases())->map(function ($status) use ($carsQuery) {
            return [
                'status' => $status->value,
                'label'  => $status->label(),
                'count'  => (clone $carsQuery)->where('status', $status->value)->count(),
                'color'  => $status->color(),
            ];
        })->values();

        // ── Monthly Revenue (last 6 months) ───────────────────────────
        $monthlyRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->startOfMonth()->subMonths($i);
            $q = Payment::query()
                ->where('status', PaymentStatus::COMPLETED)
                ->whereBetween('processed_at', [
                    $month->copy()->startOfMonth(),
                    $month->copy()->endOfMonth(),
                ]);
            $this->applyPaymentBranchScope($q, $user, $branchId);

            $monthlyRevenue[] = [
                'month'   => $month->format('M Y'),
                'revenue' => (float) $q->sum('amount'),
            ];
        }

        // ── Recent Reservations (last 5) ──────────────────────────────
        $recentReservations = (clone $reservationsQuery)
            ->with(['user:id,name,email', 'car:id,make,model,year,branch_id', 'car.branch:id,name'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Reservation $r) => [
                'id'                 => $r->id,
                'reservation_number' => $r->reservation_number,
                'client_name'        => $r->user?->name,
                'car_name'           => $r->car ? "{$r->car->year} {$r->car->make} {$r->car->model}" : '—',
                'branch_name'        => $r->car?->branch?->name ?? '—',
                'start_date'         => optional($r->start_date)->toDateString(),
                'end_date'           => optional($r->end_date)->toDateString(),
                'total_amount'       => (float) $r->total_amount,
                'status'             => $r->status instanceof ReservationStatus
                    ? $r->status->value
                    : (string) $r->status,
                'status_color'       => ReservationStatus::statusColors()[$r->status instanceof ReservationStatus ? $r->status->value : (string) $r->status] ?? '#6B7280',
            ]);

        // ── Top Cars (by completed reservation count, top 5) ──────────
        $topCars = Car::query()
            ->select('cars.id', 'cars.make', 'cars.model', 'cars.year', 'cars.price_per_day', 'cars.status')
            ->withCount([
                'reservations as completed_count' => fn ($q) => $q->where('status', ReservationStatus::COMPLETED),
            ])
            ->when(!$canAccessAllBranches && $user?->branch_id, fn ($q) => $q->where('branch_id', (int) $user->branch_id))
            ->when($canAccessAllBranches && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('completed_count')
            ->limit(5)
            ->get()
            ->map(fn (Car $car) => [
                'id'             => $car->id,
                'name'           => "{$car->year} {$car->make} {$car->model}",
                'price_per_day'  => (float) $car->price_per_day,
                'status'         => $car->status instanceof CarStatus ? $car->status->value : (string) $car->status,
                'status_label'   => $car->status instanceof CarStatus ? $car->status->label() : (string) $car->status,
                'status_color'   => $car->status instanceof CarStatus ? $car->status->color() : '#6B7280',
                'completed_count'=> $car->completed_count,
            ]);

        $today = Carbon::today();
        $expiringDocumentsQuery = CarDocument::query()
            ->with(['car:id,branch_id,year,make,model,license_plate'])
            ->where('is_active', true)
            ->whereDate('expiry_date', '>=', $today)
            ->whereDate('expiry_date', '<=', $today->copy()->addDays(10));

        $this->applyCarDocumentBranchScope($expiringDocumentsQuery, $user, $branchId);

        $expiringCarDocuments = $expiringDocumentsQuery
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->limit(6)
            ->get()
            ->map(function (CarDocument $document) use ($today) {
                $car = $document->car;
                $daysRemaining = $document->expiry_date
                    ? max(0, $today->diffInDays(Carbon::parse($document->expiry_date), false))
                    : null;

                return [
                    'id' => $document->id,
                    'type' => $document->type,
                    'car_name' => trim(sprintf(
                        '%s %s %s',
                        (string) ($car?->year ?? ''),
                        (string) ($car?->make ?? ''),
                        (string) ($car?->model ?? '')
                    )),
                    'license_plate' => (string) ($car?->license_plate ?? ''),
                    'expiry_date' => optional($document->expiry_date)?->toDateString(),
                    'days_remaining' => $daysRemaining,
                    'edit_url' => route('admin.cars.documents.edit', [
                        'car' => $document->car_id,
                        'document' => $document->id,
                    ]),
                ];
            })
            ->values();

        $recentPendingViolations = (clone $pendingViolationsQuery)
            ->with([
                'car:id,branch_id,year,make,model,license_plate',
                'branch:id,name',
            ])
            ->latest('violation_date')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(function (CarViolation $violation) {
                $car = $violation->car;

                return [
                    'id' => $violation->id,
                    'violation_number' => (string) ($violation->violation_number ?: ('#'.$violation->id)),
                    'type' => (string) $violation->type,
                    'car_name' => trim(sprintf(
                        '%s %s %s',
                        (string) ($car?->year ?? ''),
                        (string) ($car?->make ?? ''),
                        (string) ($car?->model ?? '')
                    )),
                    'license_plate' => (string) ($car?->license_plate ?? ''),
                    'branch_name' => (string) ($violation->branch?->name ?? ''),
                    'violation_date' => optional($violation->violation_date)?->toDateString(),
                    'due_date' => optional($violation->due_date)?->toDateString(),
                    'amount' => (float) $violation->amount,
                    'edit_url' => route('admin.car-violations.edit', $violation),
                ];
            })
            ->values();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_cars'           => $totalCars,
                'available_cars'       => $availableCars,
                'active_reservations'  => $activeReservations,
                'pending_reservations' => $pendingReservations,
                'pending_violations'   => $pendingViolations,
                'total_reservations'   => $totalReservations,
                'total_revenue'        => (float) $totalRevenue,
                'total_clients'        => $totalClients,
            ],
            'reservationsByStatus' => $reservationsByStatus,
            'fleetStatus'          => $fleetStatus,
            'monthlyRevenue'       => $monthlyRevenue,
            'recentReservations'   => $recentReservations,
            'topCars'              => $topCars,
            'expiringCarDocuments' => $expiringCarDocuments,
            'recentPendingViolations' => $recentPendingViolations,
            'branches'             => $branchOptions,
            'filters'              => ['branch_id' => $branchId],
            'canAccessAllBranches' => $canAccessAllBranches,
        ]);
    }

    // ── Branch scope helpers ──────────────────────────────────────────

    private function applyCarBranchScope($query, $user, ?int $branchId): void
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

    private function applyReservationBranchScope($query, $user, ?int $branchId): void
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

    private function applyPaymentBranchScope($query, $user, ?int $branchId): void
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

    private function applyCarDocumentBranchScope($query, $user, ?int $branchId): void
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
}
