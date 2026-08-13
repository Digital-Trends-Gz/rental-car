<?php

namespace App\Http\Controllers\Admin;

use App\Core\ReservationSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Models\BookingRequest;
use App\Models\Car;
use App\Models\CarDamageCase;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use App\Models\User;
use App\Services\Plans\PlanUsageLimits;
use App\Services\Plans\PlanUsageNotifier;
use App\Support\ClientReturnDebt;
use App\Support\CurrencyCatalog;
use App\Support\PaidReturnReportLock;
use App\Support\PdfRuntime;
use App\Support\TenantTranslations;
use App\Enums\CarStatus;
use App\Enums\ReservationStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Carbon\Carbon;
use App\Support\BranchAccess;
use App\Services\Rentals\RentalStatusSyncService;
use App\Services\Clients\ClientStatusService;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf as SpatiePdf;
use Throwable;

class ReservationsController extends Controller
{
    public function __construct(
        private BranchAccess $branchAccess,
        private ClientStatusService $clientStatusService,
        private PlanUsageLimits $planUsageLimits,
        private PlanUsageNotifier $planUsageNotifier,
    )
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
        $scope = $request->string('scope')->toString();
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
            ->when($scope === 'today_delivery', function ($query) {
                $query
                    ->whereDate('start_date', Carbon::today())
                    ->whereIn('status', [
                        ReservationStatus::CONFIRMED->value,
                        ReservationStatus::ACTIVE->value,
                        ReservationStatus::COMPLETED_WAIT_CONTRACT->value,
                    ]);
            })
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

        $reservationUsage = $this->planUsageLimits->reservationUsage($this->currentTenant($request));
        $canRevealLockedBookingRequests = !($reservationUsage['at_limit'] ?? false);
        $lockedBookingRequestsCount = BookingRequest::query()
            ->where('tenant_id', $request->user()?->tenant_id)
            ->where('status', BookingRequest::STATUS_LOCKED_PLAN_LIMIT)
            ->count();
        $lockedBookingRequests = BookingRequest::query()
            ->with('car:id,make,model,year,license_plate')
            ->where('tenant_id', $request->user()?->tenant_id)
            ->where('status', BookingRequest::STATUS_LOCKED_PLAN_LIMIT)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (BookingRequest $bookingRequest) => [
                'id' => $bookingRequest->id,
                'car' => $bookingRequest->car ? [
                    'id' => $bookingRequest->car->id,
                    'make' => $bookingRequest->car->make,
                    'model' => $bookingRequest->car->model,
                    'year' => $bookingRequest->car->year,
                    'license_plate' => $bookingRequest->car->license_plate,
                ] : null,
                'start_date' => optional($bookingRequest->start_date)->toDateString(),
                'end_date' => optional($bookingRequest->end_date)->toDateString(),
                'total_days' => $bookingRequest->total_days,
                'total_amount' => $bookingRequest->total_amount,
                'currency' => $bookingRequest->currency,
                'created_at' => optional($bookingRequest->created_at)->toDateTimeString(),
                'customer_name' => $canRevealLockedBookingRequests ? $bookingRequest->customer_name : null,
                'customer_email' => $canRevealLockedBookingRequests ? $bookingRequest->customer_email : null,
                'customer_phone' => $canRevealLockedBookingRequests ? $bookingRequest->customer_phone : null,
                'pickup_location' => $canRevealLockedBookingRequests ? $bookingRequest->pickup_location : null,
                'return_location' => $canRevealLockedBookingRequests ? $bookingRequest->return_location : null,
            ])
            ->values();

        return Inertia::render('Admin/Reservations/Index', [
            'reservations' => $reservations,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $status,
                'scope' => $scope,
                'branch_id' => $branchId,
            ],
            'statuses' => $statuses,
            'branches' => $branchOptions,
            'canAccessAllBranches' => $canAccessAllBranches,
            'reservationUsage' => $reservationUsage,
            'canCreateReservation' => !($reservationUsage['at_limit'] ?? false),
            'lockedBookingRequestsCount' => $lockedBookingRequestsCount,
            'lockedBookingRequests' => $lockedBookingRequests,
            'canRevealLockedBookingRequests' => $canRevealLockedBookingRequests,
        ]);
    }

    public function create(Request $request): Response
    {
        $tenant = $this->currentTenant($request);

        if (!$tenant) {
            abort(403, 'Tenant context is required to check plan limits.');
        }

        if ($message = $this->planUsageLimits->reservationLimitMessage($tenant)) {
            abort(403, $message);
        }

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
        $tenant = $this->currentTenant($request);

        if (!$tenant) {
            abort(403, 'Tenant context is required to check plan limits.');
        }

        if ($message = $this->planUsageLimits->reservationLimitMessage($tenant)) {
            return redirect()->back()->with('error', $message);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'car_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'return_time' => ['nullable', 'date_format:H:i'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'return_location' => ['nullable', 'string', 'max:255'],
            'return_location_fee' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', Rule::in(['fixed', 'percentage'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(ReservationStatus::manualValues())],
            'cancellation_reason' => ['nullable', 'string'],
            'confirm_client_debt' => ['nullable', 'boolean'],
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

        $outstandingReturnDebt = ClientReturnDebt::outstandingTotal((int) ($user?->tenant_id ?? 0), (int) $client->id);
        if ($outstandingReturnDebt > 0 && ! $this->requestConfirmedClientDebt($request)) {
            throw ValidationException::withMessages([
                'user_id' => ClientReturnDebt::blockingMessage($outstandingReturnDebt, app()->getLocale()),
            ]);
        }

        $car = Car::query()
            ->where('tenant_id', $user?->tenant_id)
            ->with('branch:id')
            ->findOrFail((int) $validated['car_id']);

        if (in_array($car->status?->value ?? (string) $car->status, $this->nonBookableCarStatuses(), true)) {
            throw ValidationException::withMessages([
                'car_id' => __('This car is not available for reservation right now.'),
            ]);
        }

        abort_unless($this->branchAccess->canAccessBranchId($user, $car->branch_id), 403);

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $this->ensureNoReservationConflict($car->id, $start, $end);

        $depositAmount = (float) ($validated['deposit_amount'] ?? 0);
        $returnLocationFee = $this->resolveReservationReturnLocationFee(
            $user?->tenant_id,
            $validated['return_location'] ?? null,
            $validated['return_location_fee'] ?? null
        );
        $totalDays = $start->diffInDays($end) + 1;
        $pricing = $this->calculateReservationPricing($car, $totalDays);
        $subtotal = $pricing['subtotal'];
        $taxAmount = round($subtotal * 0.21, 2);
        $discountType = $validated['discount_type'] ?? 'fixed';
        $discountValue = $validated['discount_value'] ?? ($validated['discount_amount'] ?? 0);
        $discountAmount = $this->calculateReservationDiscountAmount($discountType, $discountValue, $subtotal);
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
            $pricing,
            $subtotal,
            $taxAmount,
            $returnLocationFee,
            $discountType,
            $discountValue,
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
                'return_location_fee' => $returnLocationFee,
                'total_days' => $totalDays,
                'daily_rate' => $pricing['daily_rate'],
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'total_amount' => max(0, $subtotal + $taxAmount + $returnLocationFee - $discountAmount),
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
                    'currency' => CurrencyCatalog::codeForTenantId($user?->tenant_id),
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

        $this->planUsageNotifier->checkReservations($tenant->refresh());

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

    public function convertBookingRequest(Request $request, BookingRequest $bookingRequest)
    {
        $tenant = $this->currentTenant($request);
        $user = $request->user();

        if (!$tenant) {
            abort(403, 'Tenant context is required to check plan limits.');
        }

        abort_unless((int) $bookingRequest->tenant_id === (int) $tenant->id, 404);

        if ($message = $this->planUsageLimits->reservationLimitMessage($tenant)) {
            return redirect()->back()->with('error', $message);
        }

        $reservation = DB::transaction(function () use ($tenant, $user, $bookingRequest) {
            $lockedRequest = BookingRequest::query()
                ->whereKey($bookingRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless((int) $lockedRequest->tenant_id === (int) $tenant->id, 404);

            if ($lockedRequest->status !== BookingRequest::STATUS_LOCKED_PLAN_LIMIT) {
                throw ValidationException::withMessages([
                    'booking_request' => __('This booking request is no longer waiting for conversion.'),
                ]);
            }

            if ($message = $this->planUsageLimits->reservationLimitMessage($tenant->refresh())) {
                throw ValidationException::withMessages([
                    'booking_request' => $message,
                ]);
            }

            $car = Car::query()
                ->where('tenant_id', $tenant->id)
                ->with('branch:id')
                ->findOrFail($lockedRequest->car_id);

            $carStatus = $car->status instanceof CarStatus ? $car->status->value : (string) $car->status;
            if (in_array($carStatus, $this->nonBookableCarStatuses(), true)) {
                throw ValidationException::withMessages([
                    'booking_request' => __('This car is not available for reservation right now.'),
                ]);
            }

            abort_unless($this->branchAccess->canAccessBranchId($user, $car->branch_id), 403);

            $start = Carbon::parse($lockedRequest->start_date);
            $end = Carbon::parse($lockedRequest->end_date);
            $this->ensureNoReservationConflict($car->id, $start, $end);

            $client = $this->clientForBookingRequest($tenant, $lockedRequest);

            $reservation = Reservation::create([
                'tenant_id' => $tenant->id,
                'user_id' => $client->id,
                'car_id' => $car->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'pickup_time' => '09:00',
                'return_time' => '18:00',
                'pickup_location' => $lockedRequest->pickup_location,
                'return_location' => $lockedRequest->return_location,
                'return_location_fee' => (float) ($lockedRequest->return_location_fee ?? 0),
                'total_days' => max(1, (int) $lockedRequest->total_days),
                'daily_rate' => (float) ($lockedRequest->daily_rate ?? 0),
                'subtotal' => (float) ($lockedRequest->subtotal ?? 0),
                'tax_amount' => (float) ($lockedRequest->tax_amount ?? 0),
                'discount_type' => 'fixed',
                'discount_value' => (float) ($lockedRequest->discount_amount ?? 0),
                'discount_amount' => (float) ($lockedRequest->discount_amount ?? 0),
                'coupon_code' => $lockedRequest->coupon_code,
                'total_amount' => (float) ($lockedRequest->total_amount ?? 0),
                'status' => ReservationStatus::PENDING->value,
                'notes' => 'Converted from locked booking request #'.$lockedRequest->id.'.',
            ]);

            $lockedRequest->update([
                'status' => BookingRequest::STATUS_CONVERTED,
                'user_id' => $client->id,
                'converted_reservation_id' => $reservation->id,
                'unlocked_at' => $lockedRequest->unlocked_at ?? now(),
                'converted_at' => now(),
            ]);

            return $reservation;
        });

        $this->planUsageNotifier->checkReservations($tenant->refresh());

        return redirect()
            ->route('admin.reservations.show', [
                'subdomain' => $request->route('subdomain'),
                'reservation' => $reservation->id,
            ])
            ->with('success', __('Booking request converted to a reservation.'));
    }

    /**
     * Display the specified reservation details.
     */
    public function show(Reservation $reservation): Response
    {
        abort_unless($this->canAccessReservation($reservation, request()->user()), 403);
        $reservation->load(['user', 'car', 'payments', 'contract']);
        $this->syncReservationAmounts($reservation);
        $reservation->setAttribute('is_locked', PaidReturnReportLock::reservation($reservation));
        $completedPaymentsTotal = (float) $reservation->payments()
            ->completed()
            ->sum('amount');
        $balanceDue = max(0, (float) $reservation->total_amount - $completedPaymentsTotal);
        $reservationStatus = $reservation->status instanceof ReservationStatus
            ? $reservation->status->value
            : (string) $reservation->status;
        $hasPendingOnlinePayment = $this->reservationHasPendingOnlinePaymentId((int) $reservation->id);
        $reservation->setAttribute('amount_paid', $completedPaymentsTotal);
        $reservation->setAttribute('balance_due', $balanceDue);
        $reservation->setAttribute('has_pending_online_payment', $hasPendingOnlinePayment);
        $reservation->setAttribute('can_collect_final_cash', $balanceDue > 0 && ! $hasPendingOnlinePayment && !in_array($reservationStatus, [
            ReservationStatus::CANCELLED->value,
            ReservationStatus::COMPLETED->value,
        ], true));
        $pricingSummary = $this->reservationPricingSummary($reservation);
        $reservation->setAttribute('pricing_label', $pricingSummary['label']);
        $reservation->setAttribute('pricing_rate', $pricingSummary['rate']);
        $contractBlock = $this->clientContractBlockPayload($reservation->user);
        $reservation->setAttribute('client_debt_amount', $contractBlock['debt_amount']);
        $reservation->setAttribute('contract_block_reason', $contractBlock['blocked'] ? 'client_debt' : null);
        $reservation->setAttribute('contract_block_message', $contractBlock['message']);
        $reservation->setAttribute(
            'can_create_contract',
            ! $reservation->contract
        );

        return Inertia::render('Admin/Reservations/Show', [
            'reservation' => $reservation,
            'statusMeta' => ReservationStatus::getMeta(),
            'paymentStatusMeta' => PaymentStatus::getMeta(),
        ]);
    }

    /**
     * @return array{blocked: bool, debt_amount: float, message: string|null}
     */
    private function clientContractBlockPayload(?User $client): array
    {
        if (!$client) {
            return [
                'blocked' => false,
                'debt_amount' => 0.0,
                'message' => null,
            ];
        }

        $status = $this->clientStatusService->build($client, app()->getLocale());
        $debtFlag = collect($status['flags'] ?? [])
            ->first(fn (array $flag): bool => ($flag['type'] ?? null) === 'debt');

        $debtAmount = round((float) data_get($debtFlag, 'meta.total', 0), 2);
        if ($debtAmount <= 0) {
            return [
                'blocked' => false,
                'debt_amount' => 0.0,
                'message' => null,
            ];
        }

        $formattedDebtAmount = number_format($debtAmount, 2);
        $message = str_replace(
            ':amount',
            $formattedDebtAmount,
            TenantTranslations::get(
                'dashboard.admin.contracts.edit.client_has_outstanding_balance_amount_admin_can_continue',
                app()->getLocale(),
                "Client has outstanding balance ({$formattedDebtAmount}). Admin can continue creating the contract if approved."
            )
        );

        return [
            'blocked' => true,
            'debt_amount' => $debtAmount,
            'message' => $message,
        ];
    }

    private function requestConfirmedClientDebt(Request $request): bool
    {
        if (! $request->has('confirm_client_debt')) {
            return false;
        }

        return filter_var($request->input('confirm_client_debt'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Show the form for editing the specified reservation.
     */
    public function edit(Request $request, Reservation $reservation): Response
    {
        $routeReservation = $request->route('reservation');
        $reservationId = $routeReservation instanceof Reservation
            ? (int) $routeReservation->getKey()
            : (int) ($routeReservation ?? $reservation->getKey() ?? 0);
        abort_unless($reservationId > 0, 404);

        $reservation = Reservation::query()->withoutGlobalScopes()->findOrFail($reservationId);
        abort_unless($this->canAccessReservation($reservation, request()->user()), 403);
        $reservation->load(['user:id,name,email', 'car:id,make,model,year,license_plate,price_per_day,price_per_week,price_per_month']);
        $reservation->setAttribute('is_locked', PaidReturnReportLock::reservation($reservation));

        return Inertia::render('Admin/Reservations/Edit', [
            'reservation' => $reservation,
            'is_locked' => PaidReturnReportLock::reservation($reservation),
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
        $this->abortIfReservationLocked($reservation);
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'return_time' => ['nullable', 'date_format:H:i'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'return_location' => ['nullable', 'string', 'max:255'],
            'return_location_fee' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', Rule::in(['fixed', 'percentage'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
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
        $reservation->loadMissing('car');
        $reservation->status = $this->normalizeReservationStatusForPersistence(
            $validated['status'],
            $reservation->relationLoaded('contract') ? (bool) $reservation->contract : $reservation->contract()->exists()
        );

        // Recalculate totals when dates or discount change
        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $totalDays = $start->diffInDays($end) + 1;
        $returnLocationFee = $this->resolveReservationReturnLocationFee(
            $request->user()?->tenant_id,
            $validated['return_location'] ?? null,
            $validated['return_location_fee'] ?? null
        );
        $pricing = $reservation->car
            ? $this->calculateReservationPricing($reservation->car, $totalDays)
            : [
                'daily_rate' => (float) ($reservation->daily_rate ?? 0),
                'subtotal' => (float) ($reservation->daily_rate ?? 0) * $totalDays,
            ];

        $reservation->total_days = $totalDays;
        $reservation->daily_rate = $pricing['daily_rate'];
        $reservation->subtotal = $pricing['subtotal'];
        $reservation->tax_amount = round($reservation->subtotal * 0.21, 2);
        $reservation->return_location_fee = $returnLocationFee;
        $reservation->discount_type = $validated['discount_type'] ?? $reservation->discount_type ?? 'fixed';
        $reservation->discount_value = $validated['discount_value'] ?? $validated['discount_amount'] ?? $reservation->discount_value ?? 0;
        $reservation->discount_amount = $this->calculateReservationDiscountAmount(
            $reservation->discount_type,
            $reservation->discount_value,
            (float) $reservation->subtotal
        );
        $reservation->total_amount = max(0, $reservation->subtotal + $reservation->tax_amount + $returnLocationFee - (float) ($reservation->discount_amount ?? 0));

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

    private function syncReservationAmounts(Reservation $reservation): void
    {
        if (!$reservation->car || !$reservation->start_date || !$reservation->end_date) {
            return;
        }

        $start = Carbon::parse($reservation->start_date);
        $end = Carbon::parse($reservation->end_date);
        $totalDays = max(1, $start->diffInDays($end) + 1);
        $pricing = $this->calculateReservationPricing($reservation->car, $totalDays);
        $subtotal = $pricing['subtotal'];
        $taxAmount = round($subtotal * 0.21, 2);
        $returnLocationFee = (float) ($reservation->return_location_fee ?? 0);
        $discountType = $reservation->discount_type ?: 'fixed';
        $discountValue = $reservation->discount_value ?? $reservation->discount_amount ?? 0;
        $discountAmount = $this->calculateReservationDiscountAmount($discountType, $discountValue, $subtotal);
        $totalAmount = max(0, $subtotal + $taxAmount + $returnLocationFee - $discountAmount);

        $changes = [
            'total_days' => $totalDays,
            'daily_rate' => $pricing['daily_rate'],
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
        ];

        $dirty = false;
        foreach ($changes as $key => $value) {
            $current = $reservation->{$key};
            if (is_numeric($value)) {
                if (abs((float) ($current ?? 0) - (float) $value) > 0.009) {
                    $dirty = true;
                    break;
                }
                continue;
            }

            if ($current !== $value) {
                $dirty = true;
                break;
            }
        }

        if (!$dirty) {
            return;
        }

        $reservation->forceFill($changes)->save();
    }

    private function normalizeReservationStatusForPersistence(string $requestedStatus, bool $hasContract = false): string
    {
        if ($requestedStatus === ReservationStatus::COMPLETED->value && !$hasContract) {
            return ReservationStatus::COMPLETED_WAIT_CONTRACT->value;
        }

        return $requestedStatus;
    }

    private function calculateReservationDiscountAmount(?string $type, mixed $value, float $subtotal): float
    {
        $discountType = in_array($type, ['fixed', 'percentage'], true) ? $type : 'fixed';
        $discountValue = max(0, (float) ($value ?? 0));

        if ($discountType === 'percentage') {
            $discountValue = min($discountValue, 100);
            $amount = $subtotal * ($discountValue / 100);
        } else {
            $amount = $discountValue;
        }

        return round(max(0, min($amount, $subtotal)), 2);
    }

    /**
     * Calculate reservation subtotal using monthly, weekly, then daily pricing.
     *
     * @return array{daily_rate:float,subtotal:float}
     */
    private function calculateReservationPricing(Car $car, int $days): array
    {
        $days = max(1, $days);
        $dailyRate = max(0, (float) $car->price_per_day);
        $weeklyRate = max(0, (float) ($car->price_per_week ?? 0));
        $monthlyRate = max(0, (float) ($car->price_per_month ?? 0));

        $remainingDays = $days;
        $subtotal = 0.0;

        $months = intdiv($remainingDays, 30);
        if ($months > 0) {
            $subtotal += $months * ($monthlyRate > 0 ? $monthlyRate : $dailyRate * 30);
            $remainingDays -= $months * 30;
        }

        $weeks = intdiv($remainingDays, 7);
        if ($weeks > 0) {
            $subtotal += $weeks * ($weeklyRate > 0 ? $weeklyRate : $dailyRate * 7);
            $remainingDays -= $weeks * 7;
        }

        if ($remainingDays > 0) {
            $subtotal += $remainingDays * $dailyRate;
        }

        return [
            'daily_rate' => round($dailyRate, 2),
            'subtotal' => round($subtotal, 2),
        ];
    }

    /**
     * @return array{label:string,rate:float}
     */
    private function reservationPricingSummary(Reservation $reservation): array
    {
        $days = max(1, (int) ($reservation->total_days ?? 1));
        $car = $reservation->car;

        if ($car && $days >= 30 && (float) ($car->price_per_month ?? 0) > 0) {
            return [
                'label' => 'dashboard.admin.reservations.show.fields.monthly_rate',
                'rate' => (float) $car->price_per_month,
            ];
        }

        if ($car && $days >= 7 && (float) ($car->price_per_week ?? 0) > 0) {
            return [
                'label' => 'dashboard.admin.reservations.show.fields.weekly_rate',
                'rate' => (float) $car->price_per_week,
            ];
        }

        return [
            'label' => 'dashboard.admin.reservations.show.fields.daily_rate',
            'rate' => (float) ($reservation->daily_rate ?? $car?->price_per_day ?? 0),
        ];
    }

    private function reservationHasPendingOnlinePaymentId(int $reservationId): bool
    {
        return DB::table('payments')
            ->where('reservation_id', $reservationId)
            ->where('status', PaymentStatus::PENDING->value)
            ->where('payment_method', '!=', PaymentMethod::CASH->value)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function collectCashPayment(Request $request)
    {
        $routeReservation = $request->route('reservation');
        $reservationId = $routeReservation instanceof Reservation
            ? (int) $routeReservation->getKey()
            : (int) $routeReservation;
        $subdomain = $request->route('subdomain');

        $reservationModel = Reservation::withoutGlobalScope('tenant')
            ->with('car:id,branch_id')
            ->findOrFail($reservationId);

        abort_unless($this->canAccessReservation($reservationModel, $request->user()), 403);
        $this->abortIfReservationLocked($reservationModel);

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

        if ($this->reservationHasPendingOnlinePaymentId((int) $reservationRow->id)) {
            return redirect()
                ->back()
                ->with('error', 'This reservation has a pending online payment. Wait for the payment result before collecting cash.');
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
                'currency' => CurrencyCatalog::codeForTenantId($request->user()?->tenant_id),
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
        $reservation->load(['user', 'car.branch', 'payments', 'tenant.siteSetting.files']);
        $siteSettings = $reservation->tenant?->siteSetting ? TenantSiteSetting::forTenant($reservation->tenant) : [];
        $branding = $this->pdfBranding($reservation->tenant);

        $viewData = [
            'reservation' => $reservation,
            'statusMeta' => ReservationStatus::getMeta(),
            'paymentStatusMeta' => PaymentStatus::getMeta(),
            'currency' => CurrencyCatalog::forTenant($reservation->tenant)['symbol'],
            'companyLogo' => $branding['logo'],
            'companyName' => $branding['name'],
            'siteSettings' => $siteSettings,
        ];
        $fileName = $reservation->reservation_number . '.pdf';

        if (PdfRuntime::canUseBrowsershot()) {
            try {
                return SpatiePdf::view('admin.reservations.print', $viewData)
                    ->format(Format::A4)
                    ->portrait()
                    ->margins(4, 4, 4, 4)
                    ->withBrowsershot(function (Browsershot $browsershot): void {
                        $nodeBinary = PdfRuntime::nodeBinary();
                        if ($nodeBinary) {
                            $browsershot->setNodeBinary($nodeBinary);
                        }

                        $npmBinary = PdfRuntime::npmBinary();
                        if ($npmBinary) {
                            $browsershot->setNpmBinary($npmBinary);
                        }

                        $chromePath = PdfRuntime::chromeBinary();
                        if ($chromePath) {
                            $browsershot->setChromePath($chromePath);
                        }

                        $browsershot
                            ->noSandbox()
                            ->addChromiumArguments([
                                'disable-dev-shm-usage',
                                'disable-gpu',
                            ])
                            ->setOption('printBackground', true)
                            ->setOption('preferCSSPageSize', true)
                            ->waitUntilNetworkIdle(false)
                            ->timeout(120)
                            ->newHeadless();
                    })
                    ->download($fileName);
            } catch (Throwable $e) {
                report($e);
            }
        }

        PdfRuntime::ensureDompdfDirectories();

        $pdf = DomPdf::loadView('admin.reservations.print', $viewData)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('fontDir', PdfRuntime::dompdfFontDirectory())
            ->setOption('fontCache', PdfRuntime::dompdfFontDirectory())
            ->setOption('tempDir', PdfRuntime::dompdfTempDirectory())
            ->setOption('isRemoteEnabled', true)
            ->setPaper('a4', 'portrait');

        return $pdf->download($fileName);
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

    private function clientForBookingRequest(Tenant $tenant, BookingRequest $bookingRequest): User
    {
        $email = Str::lower(trim((string) $bookingRequest->customer_email));

        if ($email === '') {
            throw ValidationException::withMessages([
                'booking_request' => __('Customer email is required before this booking request can be converted.'),
            ]);
        }

        $existingUser = User::withoutGlobalScope('tenant')
            ->where('email', $email)
            ->first();

        if ($existingUser) {
            if ((int) $existingUser->tenant_id !== (int) $tenant->id) {
                throw ValidationException::withMessages([
                    'booking_request' => __('Customer email already belongs to another account.'),
                ]);
            }

            if (($existingUser->role instanceof UserRole ? $existingUser->role->value : (string) $existingUser->role) !== UserRole::CLIENT->value) {
                throw ValidationException::withMessages([
                    'booking_request' => __('Customer email belongs to a staff account.'),
                ]);
            }

            return $existingUser;
        }

        return User::create([
            'tenant_id' => $tenant->id,
            'name' => trim((string) $bookingRequest->customer_name) ?: 'Guest Customer',
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Str::random(32),
            'role' => UserRole::CLIENT,
            'is_active' => true,
        ]);
    }

    private function clientOptions(Request $request)
    {
        $tenantId = (int) ($request->user()?->tenant_id ?? 0);
        $clients = User::query()
            ->where('tenant_id', $tenantId)
            ->where('role', 'client')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $outstandingDebts = ClientReturnDebt::outstandingTotalsByClientIds(
            $tenantId,
            $clients->pluck('id')->map(fn ($id) => (int) $id)->all()
        );

        return $clients
            ->map(fn (User $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'outstanding_return_debt' => $outstandingDebts[(int) $client->id] ?? 0.0,
            ])
            ->values();
    }

    private function carOptions(Request $request)
    {
        $query = Car::query()
            ->where('tenant_id', $request->user()?->tenant_id)
            ->whereNotIn('status', $this->nonBookableCarStatuses())
            ->with('branch:id,name')
            ->orderBy('make')
            ->orderBy('model');

        $this->applyReservationBranchScopeToCars($query, $request->user());

        return $query->get(['id', 'branch_id', 'make', 'model', 'year', 'license_plate', 'price_per_day', 'price_per_week', 'price_per_month'])
            ->map(fn (Car $car) => [
                'id' => $car->id,
                'label' => sprintf('%s %s %s', $car->year, $car->make, $car->model),
                'license_plate' => $car->license_plate,
                'branch_name' => $car->branch?->name,
                'price_per_day' => (float) $car->price_per_day,
                'price_per_week' => (float) ($car->price_per_week ?? 0),
                'price_per_month' => (float) ($car->price_per_month ?? 0),
            ])
            ->values();
    }

    private function nonBookableCarStatuses(): array
    {
        return [
            CarStatus::DRAFT->value,
            CarStatus::MAINTENANCE->value,
            CarStatus::UNAVAILABLE->value,
            CarStatus::RETIRED->value,
        ];
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
            'return_location' => $reservation->return_location,
            'return_location_fee' => (float) $reservation->return_location_fee,
            'has_contract' => (bool) $reservation->contract,
        ];
    }

    private function resolveReservationReturnLocationFee(?int $tenantId, ?string $returnLocation, mixed $providedFee): float
    {
        if ($providedFee !== null && $providedFee !== '' && is_numeric($providedFee)) {
            return max(0, round((float) $providedFee, 2));
        }

        $location = trim((string) ($returnLocation ?? ''));
        if ($tenantId === null || $location === '') {
            return 0.0;
        }

        $tenantSettings = TenantSiteSetting::query()
            ->where('tenant_id', $tenantId)
            ->first();

        $settings = ReservationSettings::normalize(
            is_array($tenantSettings?->reservation_settings) ? $tenantSettings->reservation_settings : null
        );

        return ReservationSettings::resolveLocationFee($settings, $location, 'return');
    }

    private function pdfBranding($tenant): array
    {
        $tenant = $tenant?->loadMissing('siteSetting.files');
        $settings = $tenant ? TenantSiteSetting::forTenant($tenant) : [];
        $name = trim((string) ($settings['site_name'] ?? $tenant?->name ?? config('app.name')));

        return [
            'name' => $name !== '' ? $name : (string) config('app.name'),
            'logo' => $this->pdfImageSource($settings['logo_url'] ?? null),
        ];
    }

    private function pdfImageSource(?string $url): ?string
    {
        $url = trim((string) ($url ?? ''));
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, 'data:') || preg_match('/^https?:\/\//i', $url) === 1) {
            return $url;
        }

        $path = null;

        if (str_starts_with($url, '/storage/')) {
            $path = public_path(ltrim($url, '/'));
        } elseif (str_starts_with($url, 'storage/')) {
            $path = public_path($url);
        } elseif (str_starts_with($url, '/')) {
            $path = public_path(ltrim($url, '/'));
        }

        if (!$path || !is_file($path)) {
            return $url;
        }

        $contents = file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            return null;
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private function abortIfReservationLocked(Reservation $reservation): void
    {
        if (PaidReturnReportLock::reservation($reservation)) {
            abort(423, 'This reservation is locked because the return report is paid.');
        }
    }

    private function currentTenant(Request $request): ?Tenant
    {
        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        if ($tenantId <= 0) {
            return null;
        }

        return Tenant::query()
            ->with('subscriptionPlan')
            ->find($tenantId);
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
