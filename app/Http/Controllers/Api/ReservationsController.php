<?php

namespace App\Http\Controllers\Api;

use App\Enums\CarStatus;
use App\Enums\CarViolationStatus;
use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CarDamageCase;
use App\Models\CarDamageReport;
use App\Models\CarViolation;
use App\Models\Contract;
use App\Models\ContractReturnReport;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Support\BranchAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationsController extends Controller
{
    public function __construct(
        private readonly BranchAccess $branchAccess
    ) {
    }

    public function todayPickups(Request $request): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);
        $today = Carbon::today();
        $branchId = $this->resolveBranchId($request, $user);
        $statuses = $this->resolvePickupStatuses($request->input('status'));

        $query = $this->reservationQuery($user, $branchId)
            ->whereDate('start_date', $today)
            ->whereIn('status', $statuses)
            ->orderBy('start_date')
            ->orderBy('pickup_time')
            ->orderByDesc('id');

        $paginator = $query->paginate($this->resolvePerPage($request))->withQueryString();

        return response()->json([
            'date' => $today->toDateString(),
            'branch_id' => $branchId,
            'filters' => [
                'status' => $statuses,
            ],
            'count' => $paginator->total(),
            'pagination' => $this->paginationPayload($paginator),
            'reservations' => $this->mapReservations($paginator),
        ]);
    }

    public function returns(Request $request): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);
        $today = Carbon::today();
        $branchId = $this->resolveBranchId($request, $user);
        $scope = $this->resolveReturnScope($request->input('scope'));

        $query = $this->contractQuery($user, $branchId)
            ->where('status', ContractStatus::ACTIVE->value);

        if ($scope === 'overdue') {
            $query->whereDate('end_date', '<', $today)
                ->orderBy('end_date')
                ->orderByDesc('id');
        } else {
            $query->whereDate('end_date', $today)
                ->orderBy('end_date')
                ->orderByDesc('id');
        }

        $paginator = $query->paginate($this->resolvePerPage($request))->withQueryString();

        return response()->json([
            'date' => $today->toDateString(),
            'scope' => $scope,
            'branch_id' => $branchId,
            'count' => $paginator->total(),
            'pagination' => $this->paginationPayload($paginator),
            'returns' => $this->mapContracts($paginator, $scope === 'overdue'),
        ]);
    }

    public function updateNote(Request $request, Reservation $reservation): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);
        abort_unless($this->canAccessReservation($reservation, $user), 403);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $note = trim((string) ($validated['note'] ?? ''));
        $reservation->forceFill([
            'notes' => $note !== '' ? $note : null,
        ])->saveQuietly();

        return response()->json([
            'message' => 'Note updated successfully.',
            'reservation_id' => $reservation->id,
            'note' => $reservation->fresh()->notes,
        ]);
    }

    public function show(Request $request, Reservation $reservation): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);

        $reservation->loadMissing([
            'user:id,name,email',
            'car:id,branch_id,year,make,model,license_plate,status,mileage,price_per_day,price_per_week,price_per_month,allowed_km_per_day,allowed_km_per_week,allowed_km_per_month',
            'car.branch:id,name',
            'payments',
            'contract.branch:id,name',
            'contract.reservation.user:id,name,email',
            'contract.reservation.car:id,branch_id,year,make,model,license_plate,status,mileage,price_per_day',
            'contract.returnStatusReport.payment',
            'contract.returnStatusReport.damageReport.items',
            'contract.damageReports.items',
            'contract.openedDamageCases.lastReport',
            'damageReports.items',
            'openedDamageCases.lastReport',
        ]);

        abort_unless($this->canAccessReservation($reservation, $user), 403);

        if ($reservation->car) {
            $reservation->loadMissing([
                'car.violations' => fn ($query) => $query
                    ->where('reservation_id', $reservation->id)
                    ->with(['violationType:id,name', 'issuedTo:id,name,email', 'creator:id,name,email'])
                    ->latest('violation_date')
                    ->latest('id'),
            ]);
        }

        $completedPaymentsTotal = (float) $reservation->payments
            ->filter(fn (Payment $payment): bool => $this->isCompletedPayment($payment))
            ->sum(fn (Payment $payment) => (float) $payment->amount);
        $returnReportTotal = (float) ($reservation->contract?->returnStatusReport?->total_extra_charges ?? 0);
        $totalDue = max(0, (float) $reservation->total_amount + $returnReportTotal);
        $balanceDue = max(0, $totalDue - $completedPaymentsTotal);
        $reservationStatus = $this->reservationStatusValue($reservation->status);

        $reservation->setAttribute('amount_paid', $completedPaymentsTotal);
        $reservation->setAttribute('balance_due', $balanceDue);
        $reservation->setAttribute('can_collect_final_cash', $balanceDue > 0 && !in_array($reservationStatus, [
            ReservationStatus::CANCELLED->value,
            ReservationStatus::COMPLETED->value,
        ], true));

        return response()->json([
            'reservation' => $this->reservationDetailPayload($reservation, $completedPaymentsTotal, $balanceDue),
            'contract' => $reservation->contract ? $this->contractDetailPayload($reservation->contract) : null,
            'payments' => $reservation->payments->map(fn (Payment $payment) => $this->paymentPayload($payment))->values()->all(),
            'damage_reports' => $reservation->damageReports->map(fn (CarDamageReport $report) => $this->damageReportPayload($report, 'reservation'))->values()->all(),
            'contract_damage_reports' => $reservation->contract?->damageReports->map(fn (CarDamageReport $report) => $this->damageReportPayload($report, 'contract'))->values()->all() ?? [],
            'opened_damage_cases' => $reservation->openedDamageCases->map(fn (CarDamageCase $case) => $this->damageCasePayload($case, 'reservation'))->values()->all(),
            'contract_opened_damage_cases' => $reservation->contract?->openedDamageCases->map(fn (CarDamageCase $case) => $this->damageCasePayload($case, 'contract'))->values()->all() ?? [],
            'car_violations' => $reservation->car?->violations?->map(fn (CarViolation $violation) => $this->carViolationPayload($violation))->values()->all() ?? [],
        ]);
    }

    private function authorizeAdminApiUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true), 403);

        return $user;
    }

    private function reservationQuery(User $user, ?int $branchId): Builder
    {
        $query = Reservation::query();
        $this->applyReservationBranchScope($query, $user, $branchId);

        return $query->with([
            'user:id,name,email',
            'car:id,branch_id,year,make,model,license_plate,status',
            'car.branch:id,name',
        ]);
    }

    private function contractQuery(User $user, ?int $branchId): Builder
    {
        $query = Contract::query();
        $this->applyContractBranchScope($query, $user, $branchId);

        return $query->with([
            'reservation.user:id,name,email',
            'reservation.car:id,branch_id,year,make,model,license_plate,status',
            'reservation.car.branch:id,name',
            'branch:id,name',
        ]);
    }

    private function mapReservations(LengthAwarePaginator $paginator): array
    {
        return $paginator->getCollection()
            ->map(fn (Reservation $reservation) => $this->reservationItem($reservation))
            ->values()
            ->all();
    }

    private function mapContracts(LengthAwarePaginator $paginator, bool $isOverdue): array
    {
        return $paginator->getCollection()
            ->map(fn (Contract $contract) => $this->contractItem($contract, $isOverdue))
            ->values()
            ->all();
    }

    private function reservationItem(Reservation $reservation): array
    {
        $car = $reservation->car;
        $user = $reservation->user;

        return [
            'id' => $reservation->id,
            'reservation_number' => $reservation->reservation_number,
            'client_name' => $user?->name,
            'client_email' => $user?->email,
            'car' => [
                'id' => $car?->id,
                'name' => trim(sprintf(
                    '%s %s %s',
                    (string) ($car?->year ?? ''),
                    (string) ($car?->make ?? ''),
                    (string) ($car?->model ?? '')
                )),
                'license_plate' => (string) ($car?->license_plate ?? ''),
                'branch_name' => (string) ($car?->branch?->name ?? ''),
                'status' => $car?->status instanceof \App\Enums\CarStatus
                    ? $car->status->value
                    : (string) ($car?->status ?? ''),
            ],
            'start_date' => optional($reservation->start_date)->toDateString(),
            'end_date' => optional($reservation->end_date)->toDateString(),
            'pickup_time' => optional($reservation->pickup_time)->format('H:i'),
            'return_time' => optional($reservation->return_time)->format('H:i'),
            'pickup_location' => $reservation->pickup_location,
            'return_location' => $reservation->return_location,
            'status' => $reservation->status instanceof ReservationStatus ? $reservation->status->value : (string) $reservation->status,
        ];
    }

    private function reservationDetailPayload(Reservation $reservation, float $amountPaid, float $balanceDue): array
    {
        $car = $reservation->car;

        return [
            'id' => $reservation->id,
            'reservation_number' => $reservation->reservation_number,
            'status' => $this->reservationStatusValue($reservation->status),
            'status_label' => $this->reservationStatusLabel($reservation->status),
            'status_color' => $this->reservationStatusColor($reservation->status),
            'amount_paid' => $amountPaid,
            'balance_due' => $balanceDue,
            'total_amount' => (float) $reservation->total_amount,
            'can_collect_final_cash' => (bool) $reservation->getAttribute('can_collect_final_cash'),
            'start_date' => optional($reservation->start_date)->toDateString(),
            'end_date' => optional($reservation->end_date)->toDateString(),
            'pickup_time' => optional($reservation->pickup_time)->format('H:i'),
            'return_time' => optional($reservation->return_time)->format('H:i'),
            'pickup_location' => $reservation->pickup_location,
            'return_location' => $reservation->return_location,
            'return_location_fee' => (float) ($reservation->return_location_fee ?? 0),
            'total_days' => (int) ($reservation->total_days ?? 0),
            'daily_rate' => (float) ($reservation->daily_rate ?? 0),
            'subtotal' => (float) ($reservation->subtotal ?? 0),
            'tax_amount' => (float) ($reservation->tax_amount ?? 0),
            'discount_amount' => (float) ($reservation->discount_amount ?? 0),
            'notes' => $reservation->notes,
            'cancellation_reason' => $reservation->cancellation_reason,
            'cancelled_at' => optional($reservation->cancelled_at)->toIso8601String(),
            'user' => $reservation->user ? [
                'id' => $reservation->user->id,
                'name' => $reservation->user->name,
                'email' => $reservation->user->email,
            ] : null,
            'car' => $car ? [
                'id' => $car->id,
                'name' => trim(sprintf(
                    '%s %s %s',
                    (string) ($car->year ?? ''),
                    (string) ($car->make ?? ''),
                    (string) ($car->model ?? '')
                )),
                'license_plate' => (string) ($car->license_plate ?? ''),
                'branch_id' => $car->branch_id,
                'branch_name' => (string) ($car->branch?->name ?? ''),
                'status' => $car->status instanceof CarStatus ? $car->status->value : (string) $car->status,
                'status_label' => $car->status instanceof CarStatus ? $car->status->label() : Str::title(str_replace('_', ' ', (string) $car->status)),
            ] : null,
        ];
    }

    private function contractDetailPayload(Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'status' => $this->contractStatusValue($contract->status),
            'status_label' => $this->contractStatusLabel($contract->status),
            'status_color' => $this->contractStatusColor($contract->status),
            'contract_date' => optional($contract->contract_date)->toDateString(),
            'start_date' => optional($contract->start_date)->toDateString(),
            'end_date' => optional($contract->end_date)->toDateString(),
            'renter_name' => $contract->renter_name,
            'renter_id_number' => $contract->renter_id_number,
            'renter_phone' => $contract->renter_phone,
            'car_details' => $contract->car_details,
            'plate_number' => $contract->plate_number,
            'vehicle_odometer' => $contract->vehicle_odometer,
            'vehicle_fuel_level' => $contract->vehicle_fuel_level,
            'vehicle_condition_before' => $contract->vehicle_condition_before,
            'vehicle_condition_after' => $contract->vehicle_condition_after,
            'total_amount' => (float) ($contract->total_amount ?? 0),
            'currency' => $contract->currency,
            'notes' => $contract->notes,
            'branch_name' => $contract->branch?->name,
            'reservation' => $contract->reservation ? [
                'id' => $contract->reservation->id,
                'reservation_number' => $contract->reservation->reservation_number,
                'status' => $this->reservationStatusValue($contract->reservation->status),
                'status_label' => $this->reservationStatusLabel($contract->reservation->status),
            ] : null,
            'finance_status' => $this->financeStatusPayload($contract),
            'return_status_report' => $contract->returnStatusReport ? $this->contractReturnReportPayload($contract->returnStatusReport) : null,
            'damage_reports' => $contract->damageReports->map(fn (CarDamageReport $report) => $this->damageReportPayload($report, 'contract'))->values()->all(),
            'opened_damage_cases' => $contract->openedDamageCases->map(fn (CarDamageCase $case) => $this->damageCasePayload($case, 'contract'))->values()->all(),
        ];
    }

    private function damageReportPayload(CarDamageReport $report, string $source): array
    {
        return [
            'id' => $report->id,
            'source' => $source,
            'report_number' => $report->report_number,
            'report_type' => $report->report_type,
            'report_type_label' => Str::title(str_replace('_', ' ', (string) $report->report_type)),
            'status' => $report->status,
            'inspected_at' => optional($report->inspected_at)?->format('Y-m-d H:i'),
            'summary' => $report->summary,
            'items_count' => $report->relationLoaded('items') ? $report->items->count() : null,
            'total_quantity' => $report->relationLoaded('items') ? (int) $report->items->sum('quantity') : null,
            'total_estimated_cost' => $report->relationLoaded('items') ? (float) $report->items->sum('estimated_cost') : null,
            'items' => $report->relationLoaded('items') ? $report->items->map(fn ($item) => [
                'id' => $item->id,
                'zone_code' => $item->zone_code,
                'view_side' => $item->view_side,
                'damage_type' => $item->damage_type,
                'severity' => $item->severity,
                'damage_timing' => $item->damage_timing,
                'quantity' => (int) ($item->quantity ?? 0),
                'marker_x' => $item->marker_x,
                'marker_y' => $item->marker_y,
                'estimated_cost' => (float) ($item->estimated_cost ?? 0),
                'notes' => $item->notes,
                'sort_order' => $item->sort_order,
            ])->values()->all() : [],
        ];
    }

    private function damageCasePayload(CarDamageCase $case, string $source): array
    {
        return [
            'id' => $case->id,
            'source' => $source,
            'zone_code' => $case->zone_code,
            'view_side' => $case->view_side,
            'damage_type' => $case->damage_type,
            'severity' => $case->severity,
            'damage_timing' => $case->damage_timing,
            'quantity' => (int) ($case->quantity ?? 0),
            'marker_x' => $case->marker_x,
            'marker_y' => $case->marker_y,
            'estimated_cost' => (float) ($case->estimated_cost ?? 0),
            'notes' => $case->notes,
            'status' => $case->status,
            'first_detected_at' => optional($case->first_detected_at)->toIso8601String(),
            'last_detected_at' => optional($case->last_detected_at)->toIso8601String(),
            'repaired_at' => optional($case->repaired_at)->toIso8601String(),
            'last_report' => $case->lastReport ? [
                'id' => $case->lastReport->id,
                'report_number' => $case->lastReport->report_number,
                'report_type' => $case->lastReport->report_type,
                'status' => $case->lastReport->status,
                'inspected_at' => optional($case->lastReport->inspected_at)?->format('Y-m-d H:i'),
            ] : null,
        ];
    }

    private function carViolationPayload(CarViolation $violation): array
    {
        $status = $violation->status instanceof CarViolationStatus
            ? $violation->status
            : CarViolationStatus::tryFrom((string) $violation->status);

        return [
            'id' => $violation->id,
            'violation_number' => $violation->violation_number,
            'violation_date' => optional($violation->violation_date)->toDateString(),
            'type' => $violation->type,
            'amount' => (float) ($violation->amount ?? 0),
            'status' => $status?->value ?? (string) $violation->status,
            'status_label' => $status?->label() ?? Str::title(str_replace('_', ' ', (string) $violation->status)),
            'status_color' => $status?->color() ?? '#6B7280',
            'due_date' => optional($violation->due_date)->toDateString(),
            'paid_at' => optional($violation->paid_at)->toIso8601String(),
            'payment_reference' => $violation->payment_reference,
            'authority' => $violation->authority,
            'location' => $violation->location,
            'description' => $violation->description,
            'notes' => $violation->notes,
            'reservation_id' => $violation->reservation_id,
            'car_id' => $violation->car_id,
        ];
    }

    private function paymentPayload(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'payment_number' => $payment->payment_number,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'payment_method' => $payment->payment_method instanceof \App\Enums\PaymentMethod ? $payment->payment_method->value : (string) $payment->payment_method,
            'payment_method_label' => Str::title(str_replace('_', ' ', $payment->payment_method instanceof \App\Enums\PaymentMethod ? $payment->payment_method->value : (string) $payment->payment_method)),
            'status' => $this->paymentStatusValue($payment->status),
            'status_label' => $this->paymentStatusLabel($payment->status),
            'processed_at' => optional($payment->processed_at)->toIso8601String(),
            'transaction_id' => $payment->transaction_id,
            'notes' => $payment->notes,
        ];
    }

    private function contractReturnReportPayload(ContractReturnReport $report): array
    {
        return [
            'id' => $report->id,
            'report_number' => $report->report_number,
            'status' => $report->status,
            'actual_return_time' => optional($report->actual_return_time)->format('Y-m-d\TH:i'),
            'return_location' => $report->return_location,
            'return_odometer' => $report->return_odometer,
            'return_fuel_level' => $report->return_fuel_level,
            'vehicle_condition_after' => $report->vehicle_condition_after,
            'payment_status' => $report->payment_status ?? ($report->payment ? 'paid' : 'not_paid'),
            'damage_report_id' => $report->damage_report_id,
            'extra_kilometers' => $report->extra_kilometers,
            'kilometer_rate' => $report->kilometer_rate,
            'cleaning_fee' => $report->cleaning_fee,
            'fuel_fee' => $report->fuel_fee,
            'fuel_credit' => $report->fuel_credit,
            'late_hours' => $report->late_hours,
            'late_hour_rate' => $report->late_hour_rate,
            'damage_fee' => $report->damage_fee,
            'maintenance_fee' => $report->maintenance_fee,
            'other_fee' => $report->other_fee,
            'discount' => $report->discount ?? 0,
            'total_extra_charges' => $report->total_extra_charges,
            'notes' => $report->notes,
            'damage_report' => $report->damageReport ? $this->damageReportPayload($report->damageReport, 'return_report') : null,
            'payment' => $report->payment ? $this->paymentPayload($report->payment) : null,
        ];
    }

    private function reservationStatusValue(mixed $status): string
    {
        return $status instanceof ReservationStatus ? $status->value : (string) $status;
    }

    private function reservationStatusLabel(mixed $status): string
    {
        $enum = $status instanceof ReservationStatus ? $status : ReservationStatus::tryFrom((string) $status);

        return $enum?->label() ?? Str::title(str_replace('_', ' ', (string) $status));
    }

    private function reservationStatusColor(mixed $status): string
    {
        $enum = $status instanceof ReservationStatus ? $status : ReservationStatus::tryFrom((string) $status);

        return $enum?->color() ?? '#6B7280';
    }

    private function contractStatusValue(mixed $status): string
    {
        return $status instanceof ContractStatus ? $status->value : (string) $status;
    }

    private function contractStatusLabel(mixed $status): string
    {
        $enum = $status instanceof ContractStatus ? $status : ContractStatus::tryFrom((string) $status);

        return $enum?->label() ?? Str::title(str_replace('_', ' ', (string) $status));
    }

    private function contractStatusColor(mixed $status): string
    {
        $colors = [
            ContractStatus::DRAFT->value => '#9CA3AF',
            ContractStatus::PENDING->value => '#F59E0B',
            ContractStatus::ACTIVE->value => '#3B82F6',
            ContractStatus::COMPLETED->value => '#10B981',
            ContractStatus::CANCELLED->value => '#EF4444',
        ];

        $value = $status instanceof ContractStatus ? $status->value : (string) $status;

        return $colors[$value] ?? '#6B7280';
    }

    private function paymentStatusValue(mixed $status): string
    {
        return $status instanceof PaymentStatus ? $status->value : (string) $status;
    }

    private function paymentStatusLabel(mixed $status): string
    {
        $enum = $status instanceof PaymentStatus ? $status : PaymentStatus::tryFrom((string) $status);

        return $enum?->label() ?? Str::title(str_replace('_', ' ', (string) $status));
    }

    private function isCompletedPayment(Payment $payment): bool
    {
        return $payment->status instanceof PaymentStatus
            ? $payment->status === PaymentStatus::COMPLETED
            : (string) $payment->status === PaymentStatus::COMPLETED->value;
    }

    private function canAccessReservation(Reservation $reservation, User $user): bool
    {
        $reservation->loadMissing('car:id,branch_id');

        return $this->branchAccess->canAccessBranchId($user, $reservation->car?->branch_id ? (int) $reservation->car->branch_id : null);
    }

    private function financeStatusPayload(Contract $contract): array
    {
        $contractTotal = (float) ($contract->total_amount ?? 0);
        $returnReportTotal = (float) ($contract->returnStatusReport?->total_extra_charges ?? 0);
        $totalDue = max(0, $contractTotal + $returnReportTotal);
        $completedPayments = (float) ($contract->reservation?->payments ?? collect())
            ->filter(fn (Payment $payment): bool => $this->isCompletedPayment($payment))
            ->sum(fn (Payment $payment) => (float) $payment->amount);
        $balance = round(max(0, $totalDue - $completedPayments), 2);

        if ($totalDue <= 0) {
            $value = 'no_charge';
            $label = 'No Charge';
            $color = '#6B7280';
        } elseif ($balance <= 0) {
            $value = 'paid';
            $label = 'Paid';
            $color = '#10B981';
        } elseif ($completedPayments > 0) {
            $value = 'partial';
            $label = 'Partially Paid';
            $color = '#F59E0B';
        } else {
            $value = 'unpaid';
            $label = 'Unpaid';
            $color = '#EF4444';
        }

        if (($contract->returnStatusReport?->payment_status ?? null) === 'not_paid' && $returnReportTotal > 0 && $balance > 0) {
            $value = $completedPayments > 0 ? 'partial_with_return_debt' : 'return_debt';
            $label = $completedPayments > 0 ? 'Partial + Return Debt' : 'Return Debt';
            $color = '#DC2626';
        }

        return [
            'value' => $value,
            'label' => $label,
            'color' => $color,
            'total_due' => round($totalDue, 2),
            'paid_amount' => round($completedPayments, 2),
            'balance_due' => $balance,
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
            'car' => [
                'id' => $car?->id,
                'name' => trim(sprintf(
                    '%s %s %s',
                    (string) ($car?->year ?? ''),
                    (string) ($car?->make ?? ''),
                    (string) ($car?->model ?? '')
                )),
                'license_plate' => (string) ($car?->license_plate ?? ''),
                'branch_name' => (string) ($contract->branch?->name ?? $car?->branch?->name ?? ''),
            ],
            'start_date' => optional($contract->start_date)->toDateString(),
            'end_date' => $endDate,
            'is_overdue' => $isOverdue,
            'days_overdue' => $isOverdue && $contract->end_date
                ? abs(now()->startOfDay()->diffInDays($contract->end_date->copy()->startOfDay()))
                : 0,
            'status' => $contract->status instanceof ContractStatus ? $contract->status->value : (string) $contract->status,
            'reservation_status' => $reservation?->status instanceof ReservationStatus
                ? $reservation->status->value
                : (string) ($reservation?->status ?? ''),
        ];
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', 15);

        return max(1, min(100, $perPage));
    }

    private function paginationPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    private function resolvePickupStatuses(mixed $statusInput): array
    {
        $allowed = $this->reservationStatusValues();
        $default = [
            ReservationStatus::PENDING->value,
            ReservationStatus::CONFIRMED->value,
            ReservationStatus::ACTIVE->value,
            ReservationStatus::COMPLETED_WAIT_CONTRACT->value,
        ];

        if ($statusInput === null || $statusInput === '') {
            return $default;
        }

        $requested = $this->normalizeStatusInput($statusInput);
        $invalid = array_values(array_diff($requested, $allowed));

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'status' => ['Invalid reservation status filter.'],
            ]);
        }

        return $requested !== [] ? $requested : $default;
    }

    private function resolveReturnScope(mixed $scopeInput): string
    {
        $scope = strtolower(trim((string) ($scopeInput ?? 'today')));

        if (!in_array($scope, ['today', 'overdue'], true)) {
            throw ValidationException::withMessages([
                'scope' => ['The scope must be today or overdue.'],
            ]);
        }

        return $scope;
    }

    private function normalizeStatusInput(mixed $statusInput): array
    {
        $values = is_array($statusInput) ? $statusInput : preg_split('/[,\|]+/', (string) $statusInput);

        return array_values(array_filter(array_map(static function ($value): string {
            return strtolower(trim((string) $value));
        }, $values ?: []), static fn (string $value): bool => $value !== ''));
    }

    private function reservationStatusValues(): array
    {
        return array_map(static fn (ReservationStatus $status): string => $status->value, ReservationStatus::cases());
    }

    private function resolveBranchId(Request $request, User $user): ?int
    {
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));

        if ($this->branchAccess->canAccessAllBranches($user)) {
            return $requestedBranchId;
        }

        return (int) ($user->branch_id ?? 0) > 0 ? (int) $user->branch_id : null;
    }

    private function applyReservationBranchScope(Builder $query, User $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->whereHas('car', fn (Builder $q) => $q->where('branch_id', $branchId));
            }

            return;
        }

        $userBranchId = (int) ($user->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('car', fn (Builder $q) => $q->where('branch_id', $userBranchId));
    }

    private function applyContractBranchScope(Builder $query, User $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->whereHas('reservation.car', fn (Builder $q) => $q->where('branch_id', $branchId));
            }

            return;
        }

        $userBranchId = (int) ($user->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('reservation.car', fn (Builder $q) => $q->where('branch_id', $userBranchId));
    }
}
