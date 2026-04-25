<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Models\Car;
use App\Models\CarDamageCase;
use App\Models\Payment;
use App\Models\User;
use App\Enums\ReservationStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Support\BranchAccess;
use App\Services\Rentals\RentalStatusSyncService;

class ReservationsController extends Controller
{
    public function __construct(private BranchAccess $branchAccess)
    {
    }

    /**
     * Display a listing of reservations.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);
        $status = $request->input('status');
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));

        $branchOptions = $this->branchAccess
            ->availableBranchesForUser($user)
            ->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])
            ->values();

        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;

        // Status counts for filter chips
        $statusCountsQuery = Reservation::query()->selectRaw('status, count(*) as count');
        $this->applyReservationBranchScope($statusCountsQuery, $user, $branchId);
        $statusCounts = $statusCountsQuery
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $reservationsQuery = Reservation::query()
            ->with([
                'user:id,name,email',
                'car:id,branch_id,make,model,year,license_plate',
                'car.branch:id,name',
            ]);

        $this->applyReservationBranchScope($reservationsQuery, $user, $branchId);

        $reservations = $reservationsQuery
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reservation_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('car', function ($cq) use ($search) {
                            $cq->where('make', 'like', "%{$search}%")
                                ->orWhere('model', 'like', "%{$search}%")
                                ->orWhere('license_plate', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status && $status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $reservations->getCollection()->transform(function (Reservation $reservation) {
            return [
                'id' => $reservation->id,
                'reservation_number' => $reservation->reservation_number,
                'user' => $reservation->user ? [
                    'id' => $reservation->user->id,
                    'name' => $reservation->user->name,
                    'email' => $reservation->user->email,
                ] : null,
                'car' => $reservation->car ? [
                    'id' => $reservation->car->id,
                    'make' => $reservation->car->make,
                    'model' => $reservation->car->model,
                    'year' => $reservation->car->year,
                    'license_plate' => $reservation->car->license_plate,
                ] : null,
                'branch_name' => $reservation->car?->branch?->name,
                'start_date' => optional($reservation->start_date)->toDateString(),
                'end_date' => optional($reservation->end_date)->toDateString(),
                'total_days' => $reservation->total_days,
                'total_amount' => $reservation->total_amount,
                'status' => $reservation->status instanceof ReservationStatus
                    ? $reservation->status->value
                    : (string) $reservation->status,
            ];
        });

        $statuses = collect(ReservationStatus::cases())->mapWithKeys(function ($st) use ($statusCounts) {
            $meta = ReservationStatus::getMeta();
            $statusMeta = collect($meta)->firstWhere('value', $st->value);
            
            return [
                $st->value => [
                    'label' => $statusMeta['label'] ?? ucfirst(str_replace('_', ' ', $st->value)),
                    'count' => $statusCounts[$st->value] ?? 0,
                    'color' => $statusMeta['color'] ?? '#6B7280',
                ],
            ];
        })->toArray();

        return Inertia::render('Admin/Reservations/Index', [
            'reservations' => $reservations,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $status,
                'branch_id' => $branchId,
            ],
            'statuses' => $statuses,
            'branches' => $branchOptions,
            'canAccessAllBranches' => $canAccessAllBranches,
        ]);
    }

    public function create(Request $request): Response
    {
        $cars = $this->carOptions($request);

        return Inertia::render('Admin/Reservations/Edit', [
            'reservation' => null,
            'clients' => $this->clientOptions($request),
            'cars' => $cars,
            'carDamagesByCar' => $this->serializeCarDamageCaseMap(collect($cars)->pluck('id')->all(), $request->user()),
            'enums' => [
                'statuses' => ReservationStatus::manualMeta(),
                'allStatuses' => ReservationStatus::getMeta(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'car_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'return_time' => ['nullable', 'date_format:H:i'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'return_location' => ['nullable', 'string', 'max:255'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(ReservationStatus::manualValues())],
            'cancellation_reason' => ['nullable', 'string'],
        ]);

        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $user = $request->user();
        $client = User::query()
            ->where('tenant_id', $user?->tenant_id)
            ->where('role', 'client')
            ->findOrFail((int) $validated['user_id']);

        $car = Car::query()
            ->where('tenant_id', $user?->tenant_id)
            ->with('branch:id')
            ->findOrFail((int) $validated['car_id']);

        abort_unless($this->branchAccess->canAccessBranchId($user, $car->branch_id), 403);

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $this->ensureNoReservationConflict($car->id, $start, $end);

        $discountAmount = (float) ($validated['discount_amount'] ?? 0);
        $depositAmount = (float) ($validated['deposit_amount'] ?? 0);
        $totalDays = $start->diffInDays($end) + 1;
        $subtotal = (float) $car->price_per_day * $totalDays;
        $taxAmount = round($subtotal * 0.21, 2);
        $reservationStatus = $this->normalizeReservationStatusForPersistence($validated['status']);

        $reservation = null;
        $depositPayment = null;

        DB::transaction(function () use (
            &$reservation,
            &$depositPayment,
            $user,
            $client,
            $car,
            $validated,
            $totalDays,
            $subtotal,
            $taxAmount,
            $discountAmount,
            $depositAmount,
            $reservationStatus
        ) {
            $reservation = Reservation::create([
                'tenant_id' => $user?->tenant_id,
                'user_id' => $client->id,
                'car_id' => $car->id,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'pickup_time' => $validated['pickup_time'] ?? '09:00',
                'return_time' => $validated['return_time'] ?? '18:00',
                'pickup_location' => $validated['pickup_location'] ?? null,
                'return_location' => $validated['return_location'] ?? null,
                'total_days' => $totalDays,
                'daily_rate' => $car->price_per_day,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => max(0, $subtotal + $taxAmount - $discountAmount),
                'status' => $reservationStatus,
                'notes' => $validated['notes'] ?? null,
                'cancellation_reason' => $reservationStatus === ReservationStatus::CANCELLED->value
                    ? ($validated['cancellation_reason'] ?? null)
                    : null,
                'cancelled_at' => $reservationStatus === ReservationStatus::CANCELLED->value ? now() : null,
            ]);

            if ($depositAmount > 0) {
                $depositPayment = Payment::create([
                    'tenant_id' => $user?->tenant_id,
                    'reservation_id' => $reservation->id,
                    'user_id' => $client->id,
                    'amount' => $depositAmount,
                    'currency' => strtoupper((string) config('app.currency_code', 'USD')),
                    'payment_method' => PaymentMethod::CASH,
                    'status' => PaymentStatus::COMPLETED,
                    'notes' => 'Cash deposit recorded from admin reservation form.',
                    'processed_at' => now(),
                ]);
            }
        });

        $reservation->load([
            'user:id,name,email',
            'car:id,branch_id,make,model,year,license_plate',
            'contract:id,reservation_id',
            'payments',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Reservation created successfully.',
                'reservation' => $this->reservationOptionPayload($reservation),
                'deposit_payment_id' => $depositPayment?->id,
            ], 201);
        }

        return redirect()
            ->route('admin.reservations.show', [
                'subdomain' => $request->route('subdomain'),
                'reservation' => $reservation->id,
            ])
            ->with('success', 'Reservation created successfully.');
    }

    /**
     * Display the specified reservation details.
     */
    public function show(Reservation $reservation): Response
    {
        abort_unless($this->canAccessReservation($reservation, request()->user()), 403);
        $reservation->load(['user', 'car', 'payments', 'contract']);
        $completedPaymentsTotal = (float) $reservation->payments()
            ->completed()
            ->sum('amount');
        $balanceDue = max(0, (float) $reservation->total_amount - $completedPaymentsTotal);
        $reservationStatus = $reservation->status instanceof ReservationStatus
            ? $reservation->status->value
            : (string) $reservation->status;
        $reservation->setAttribute('amount_paid', $completedPaymentsTotal);
        $reservation->setAttribute('balance_due', $balanceDue);
        $reservation->setAttribute('can_collect_final_cash', $balanceDue > 0 && !in_array($reservationStatus, [
            ReservationStatus::CANCELLED->value,
            ReservationStatus::COMPLETED->value,
        ], true));

        return Inertia::render('Admin/Reservations/Show', [
            'reservation' => $reservation,
            'statusMeta' => ReservationStatus::getMeta(),
            'paymentStatusMeta' => PaymentStatus::getMeta(),
        ]);
    }

    /**
     * Show the form for editing the specified reservation.
     */
    public function edit(Request $request, Reservation $reservation): Response
    {
        abort_unless($this->canAccessReservation($reservation, request()->user()), 403);
        $reservation->load(['user:id,name,email', 'car:id,make,model,year,license_plate']);

        return Inertia::render('Admin/Reservations/Edit', [
            'reservation' => $reservation,
            'clients' => [],
            'cars' => [],
            'carDamagesByCar' => $reservation->car?->id ? $this->serializeCarDamageCaseMap([(int) $reservation->car->id], $request->user()) : [],
            'enums' => [
                'statuses' => ReservationStatus::manualMeta($reservation->status instanceof ReservationStatus ? $reservation->status->value : (string) $reservation->status),
                'allStatuses' => ReservationStatus::getMeta(),
            ],
        ]);
    }

    /**
     * Update the specified reservation in storage.
     */
    public function update(Request $request, Reservation $reservation)
    {
        abort_unless($this->canAccessReservation($reservation, $request->user()), 403);
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'return_time' => ['nullable', 'date_format:H:i'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'return_location' => ['nullable', 'string', 'max:255'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(ReservationStatus::manualValues($reservation->status instanceof ReservationStatus ? $reservation->status->value : (string) $reservation->status))],
            'cancellation_reason' => ['nullable', 'string'],
        ]);

        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $reservation->fill($validated);
        $reservation->status = $this->normalizeReservationStatusForPersistence(
            $validated['status'],
            $reservation->relationLoaded('contract') ? (bool) $reservation->contract : $reservation->contract()->exists()
        );

        // Recalculate totals when dates or discount change
        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $totalDays = $start->diffInDays($end) + 1;
        $reservation->total_days = $totalDays;
        $reservation->subtotal = $reservation->daily_rate * $totalDays;
        $reservation->tax_amount = round($reservation->subtotal * 0.21, 2);
        $reservation->total_amount = $reservation->subtotal + $reservation->tax_amount - (float)($reservation->discount_amount ?? 0);

        // Maintain cancellation metadata
        if ($reservation->status === ReservationStatus::CANCELLED && !$reservation->cancelled_at) {
            $reservation->cancelled_at = now();
        }
        if ($reservation->status !== ReservationStatus::CANCELLED) {
            $reservation->cancellation_reason = null;
            $reservation->cancelled_at = null;
        }

        $reservation->save();

        return redirect()
            ->route('admin.reservations.show', [
                'subdomain' => $request->route('subdomain'),
                'reservation' => $reservation->id,
            ])
            ->with('success', 'Reservation updated successfully.');
    }

    private function normalizeReservationStatusForPersistence(string $requestedStatus, bool $hasContract = false): string
    {
        if ($requestedStatus === ReservationStatus::COMPLETED->value && !$hasContract) {
            return ReservationStatus::COMPLETED_WAIT_CONTRACT->value;
        }

        return $requestedStatus;
    }

    public function collectCashPayment(Request $request)
    {
        $reservationId = (int) $request->route('reservation');
        $subdomain = $request->route('subdomain');

        $reservationModel = Reservation::withoutGlobalScope('tenant')
            ->with('car:id,branch_id')
            ->findOrFail($reservationId);

        abort_unless($this->canAccessReservation($reservationModel, $request->user()), 403);

        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        if ($reservationModel->status === ReservationStatus::CANCELLED) {
            return redirect()
                ->back()
                ->with('error', 'Cancelled reservations cannot be settled.');
        }

        $reservationRow = DB::table('reservations')
            ->where('id', $reservationId)
            ->first();

        if (!$reservationRow) {
            abort(404);
        }

        $completedPaymentsTotal = (float) DB::table('payments')
            ->where('reservation_id', $reservationRow->id)
            ->where('status', PaymentStatus::COMPLETED->value)
            ->sum('amount');
        $balanceDue = round(max(0, (float) $reservationRow->total_amount - $completedPaymentsTotal), 2);

        if ($balanceDue <= 0) {
            return redirect()
                ->back()
                ->with('info', 'This reservation is already fully paid.');
        }

        DB::transaction(function () use ($reservationRow, $request, $balanceDue) {
            $nextStatus = ReservationStatus::COMPLETED_WAIT_CONTRACT->value;
            $reservationHasContract = Reservation::withoutGlobalScope('tenant')
                ->where('id', $reservationRow->id)
                ->whereHas('contract')
                ->exists();

            if ($reservationHasContract) {
                $nextStatus = ReservationStatus::COMPLETED->value;
            }

            Payment::create([
                'tenant_id' => $request->user()?->tenant_id,
                'reservation_id' => $reservationRow->id,
                'user_id' => $reservationRow->user_id,
                'amount' => $balanceDue,
                'currency' => strtoupper((string) config('app.currency_code', 'USD')),
                'payment_method' => PaymentMethod::CASH,
                'status' => PaymentStatus::COMPLETED,
                'notes' => 'Final cash payment recorded from admin reservation details.',
                'processed_at' => now(),
            ]);

            DB::table('reservations')
                ->where('id', $reservationRow->id)
                ->update([
                    'status' => $nextStatus,
                    'updated_at' => now(),
                ]);

            app(RentalStatusSyncService::class)->syncCarsByIds([$reservationRow->car_id]);
        });

        return redirect()
            ->route('admin.reservations.show', [
                'subdomain' => $subdomain,
                'reservation' => $reservationRow->id,
            ])
            ->with('success', 'Final cash payment recorded and reservation status updated.');
    }

    /**
     * Generate and download a PDF for the reservation details.
     */
    public function print(Reservation $reservation)
    {
        abort_unless($this->canAccessReservation($reservation, request()->user()), 403);
        $reservation->load(['user', 'car', 'payments']);

        $pdf = Pdf::loadView('admin.reservations.print', [
            'reservation' => $reservation,
            'statusMeta' => ReservationStatus::getMeta(),
            'paymentStatusMeta' => PaymentStatus::getMeta(),
            'currency' => config('app.currency_symbol'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($reservation->reservation_number . '.pdf');
    }

    private function applyReservationBranchScope($query, $user, ?int $branchId): void
    {
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

        if ($canAccessAllBranches) {
            if ($branchId) {
                $query->whereHas('car', fn ($carQuery) => $carQuery->where('branch_id', $branchId));
            }

            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('car', fn ($carQuery) => $carQuery->where('branch_id', $userBranchId));
    }

    private function canAccessReservation(Reservation $reservation, $user): bool
    {
        $reservation->loadMissing('car:id,branch_id');

        return $this->branchAccess->canAccessBranchId($user, $reservation->car?->branch_id ? (int) $reservation->car->branch_id : null);
    }

    private function clientOptions(Request $request)
    {
        return User::query()
            ->where('tenant_id', $request->user()?->tenant_id)
            ->where('role', 'client')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
            ])
            ->values();
    }

    private function carOptions(Request $request)
    {
        $query = Car::query()
            ->where('tenant_id', $request->user()?->tenant_id)
            ->with('branch:id,name')
            ->orderBy('make')
            ->orderBy('model');

        $this->applyReservationBranchScopeToCars($query, $request->user());

        return $query->get(['id', 'branch_id', 'make', 'model', 'year', 'license_plate', 'price_per_day'])
            ->map(fn (Car $car) => [
                'id' => $car->id,
                'label' => sprintf('%s %s %s', $car->year, $car->make, $car->model),
                'license_plate' => $car->license_plate,
                'branch_name' => $car->branch?->name,
                'price_per_day' => (float) $car->price_per_day,
            ])
            ->values();
    }

    private function applyReservationBranchScopeToCars($query, $user): void
    {
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);
        if ($canAccessAllBranches) {
            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where('branch_id', $userBranchId);
    }

    private function ensureNoReservationConflict(int $carId, Carbon $start, Carbon $end, ?int $ignoreReservationId = null): void
    {
        $query = Reservation::query()
            ->where('car_id', $carId)
            ->whereIn('status', [
                ReservationStatus::PENDING->value,
                ReservationStatus::CONFIRMED->value,
                ReservationStatus::ACTIVE->value,
            ])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString());

        if ($ignoreReservationId) {
            $query->whereKeyNot($ignoreReservationId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'This car already has a reservation in the selected date range.',
                'end_date' => 'Please choose another date range.',
            ]);
        }
    }

    private function reservationOptionPayload(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'reservation_number' => $reservation->reservation_number,
            'label' => "{$reservation->reservation_number} - {$reservation->user?->name}",
            'car' => $reservation->car ? "{$reservation->car->year} {$reservation->car->make} {$reservation->car->model}" : null,
            'car_id' => $reservation->car?->id,
            'car_details' => $reservation->car ? "{$reservation->car->year} {$reservation->car->make} {$reservation->car->model}" : null,
            'plate_number' => $reservation->car?->license_plate,
            'branch_id' => $reservation->car?->branch_id,
            'user_id' => $reservation->user?->id,
            'user_name' => $reservation->user?->name,
            'start_date' => optional($reservation->start_date)->toDateString(),
            'end_date' => optional($reservation->end_date)->toDateString(),
            'total_amount' => $reservation->total_amount,
            'has_contract' => (bool) $reservation->contract,
        ];
    }

    private function serializeCarDamageCaseMap(array $carIds, $user): array
    {
        $normalizedIds = collect($carIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($normalizedIds === []) {
            return [];
        }

        $zoneLabels = \App\Support\CarDamageCatalog::zoneLabelMap();
        $viewLabels = collect(\App\Support\CarDamageCatalog::viewSides())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $damageTypeLabels = collect(\App\Support\CarDamageCatalog::damageTypes())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $severityLabels = collect(\App\Support\CarDamageCatalog::severityLevels())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();

        $query = CarDamageCase::query()
            ->whereIn('car_id', $normalizedIds)
            ->whereIn('status', ['open', 'in_repair'])
            ->orderBy('zone_code')
            ->orderBy('id');
        $this->branchAccess->applyToQuery($query, $user, null, 'branch_id');

        $grouped = [];
        foreach ($query->get() as $case) {
            $grouped[$case->car_id][] = [
                'id' => $case->id,
                'zone_code' => $case->zone_code,
                'zone_label' => $zoneLabels[$case->zone_code] ?? $case->zone_code,
                'view_side' => $case->view_side,
                'view_side_label' => $viewLabels[$case->view_side] ?? $case->view_side,
                'damage_type' => $case->damage_type,
                'damage_type_label' => $damageTypeLabels[$case->damage_type] ?? $case->damage_type,
                'severity' => $case->severity,
                'severity_label' => $severityLabels[$case->severity] ?? $case->severity,
                'quantity' => (int) $case->quantity,
                'notes' => $case->notes,
                'first_detected_at' => optional($case->first_detected_at)?->format('Y-m-d H:i'),
            ];
        }

        return $grouped;
    }
}
