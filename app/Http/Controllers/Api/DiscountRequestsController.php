<?php

namespace App\Http\Controllers\Api;

use App\Core\ReservationSettings;
use App\Enums\DiscountRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractReturnReport;
use App\Models\DiscountRequest;
use App\Models\Payment;
use App\Models\TenantSiteSetting;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DiscountRequestsController extends Controller
{
    public function __construct(
        private readonly BranchAccess $branchAccess
    ) {
    }

    public function showForContract(Request $request, Contract $contract): JsonResponse
    {
        $user = $this->authorizeRequestUser($request);

        $contract->loadMissing([
            'reservation.car:id,branch_id',
            'returnStatusReport:id,tenant_id,contract_id,reservation_id,report_number,total_extra_charges,payment_status',
        ]);

        abort_unless($this->canAccessContract($contract, $user), 403);

        if (!$contract->returnStatusReport) {
            return response()->json([
                'has_return_report' => false,
                'has_discount_request' => false,
                'discount_request' => null,
            ]);
        }

        $discountRequest = DiscountRequest::query()
            ->with([
                'reservation:id,reservation_number',
                'contract:id,contract_number',
                'returnReport:id,report_number',
                'requestedBy:id,name,email',
                'reviewedBy:id,name,email',
            ])
            ->where('contract_return_report_id', $contract->returnStatusReport->id)
            ->latest()
            ->first();

        return response()->json([
            'has_return_report' => true,
            'has_discount_request' => (bool) $discountRequest,
            'discount_request' => $discountRequest ? $this->discountRequestPayload($discountRequest) : null,
        ]);
    }

    public function store(Request $request, Contract $contract): JsonResponse
    {
        $user = $this->authorizeRequestUser($request);

        $contract->loadMissing([
            'reservation.car:id,branch_id',
            'returnStatusReport.reservation.payments',
        ]);

        abort_unless($this->canAccessContract($contract, $user), 403);

        $validated = $request->validate([
            'discount_type' => ['required', 'string', Rule::in(['fixed', 'percentage'])],
            'discount_value' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        if (!$contract->returnStatusReport) {
            throw ValidationException::withMessages([
                'contract' => [$this->message('This contract does not have a return report yet.', 'لا يوجد تقرير إرجاع لهذا العقد بعد.')],
            ]);
        }

        $discountRequest = null;

        DB::transaction(function () use ($contract, $user, $validated, &$discountRequest): void {
            $lockedReport = ContractReturnReport::query()
                ->with(['reservation.payments'])
                ->where('contract_id', $contract->id)
                ->lockForUpdate()
                ->firstOrFail();

            $hasPendingRequest = DiscountRequest::query()
                ->where('contract_return_report_id', $lockedReport->id)
                ->where('status', DiscountRequestStatus::PENDING->value)
                ->exists();

            if ($hasPendingRequest) {
                throw ValidationException::withMessages([
                    'discount_request' => [$this->message('This return report already has a pending discount request.', 'يوجد طلب خصم قيد الانتظار لتقرير الإرجاع هذا.')],
                ]);
            }

            $baseAmount = $this->returnReportBalance($lockedReport);
            if ($baseAmount <= 0) {
                throw ValidationException::withMessages([
                    'return_report' => [$this->message('There is no remaining return report amount to discount.', 'لا يوجد مبلغ متبقٍ على تقرير الإرجاع يمكن الخصم منه.')],
                ]);
            }

            $discountType = (string) $validated['discount_type'];
            $discountValue = round((float) $validated['discount_value'], 2);
            $discountAmount = $this->calculateDiscountAmount($discountType, $discountValue, $baseAmount);
            $autoApprovalLimit = ReservationSettings::employeeDiscountAutoApprovalLimit(
                $this->reservationSettings((int) $lockedReport->tenant_id),
                $baseAmount
            );
            $isAutoApproved = $autoApprovalLimit > 0 && $discountAmount <= $autoApprovalLimit;

            if ($discountAmount <= 0) {
                throw ValidationException::withMessages([
                    'discount_value' => [$this->message('The discount value must produce a discount greater than zero.', 'يجب أن ينتج عن قيمة الخصم مبلغ أكبر من صفر.')],
                ]);
            }

            $appliedDiscount = $isAutoApproved ? round(min($discountAmount, $baseAmount), 2) : $discountAmount;

            if ($isAutoApproved) {
                $newDiscountAmount = round((float) ($lockedReport->discount ?? 0) + $appliedDiscount, 2);
                $newTotalExtraCharges = round(max(0, (float) $lockedReport->total_extra_charges - $appliedDiscount), 2);

                $lockedReport->forceFill([
                    'discount' => $newDiscountAmount,
                    'total_extra_charges' => $newTotalExtraCharges,
                    'payment_status' => $this->reportPaymentStatusAfterDiscount($lockedReport, $newTotalExtraCharges),
                ])->save();
            }

            $discountRequest = DiscountRequest::create([
                'tenant_id' => $lockedReport->tenant_id,
                'reservation_id' => $lockedReport->reservation_id,
                'contract_id' => $lockedReport->contract_id,
                'contract_return_report_id' => $lockedReport->id,
                'requested_by_user_id' => $user->id,
                'reviewed_by_user_id' => $isAutoApproved ? $user->id : null,
                'base_amount' => $baseAmount,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $appliedDiscount,
                'final_amount' => round(max(0, $baseAmount - $appliedDiscount), 2),
                'reason' => trim((string) $validated['reason']),
                'status' => $isAutoApproved ? DiscountRequestStatus::APPROVED : DiscountRequestStatus::PENDING,
                'review_note' => $isAutoApproved
                    ? sprintf('Automatically approved within configured employee discount limit (%.2f).', $autoApprovalLimit)
                    : null,
                'reviewed_at' => $isAutoApproved ? now() : null,
                'approved_at' => $isAutoApproved ? now() : null,
            ]);
        });

        $status = $discountRequest->status instanceof DiscountRequestStatus
            ? $discountRequest->status
            : DiscountRequestStatus::from((string) $discountRequest->status);

        return response()->json([
            'status' => 'success',
            'message' => $status === DiscountRequestStatus::APPROVED
                ? $this->message('Discount request approved automatically.', 'تمت الموافقة على طلب الخصم تلقائياً.')
                : $this->message('Discount request sent for approval.', 'تم إرسال طلب الخصم للموافقة.'),
            'discount_request' => $this->discountRequestPayload($discountRequest->fresh([
                'reservation:id,reservation_number',
                'contract:id,contract_number',
                'returnReport:id,report_number',
                'requestedBy:id,name,email',
                'reviewedBy:id,name,email',
            ])),
        ], 201);
    }

    private function authorizeRequestUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true), 403);

        return $user;
    }

    private function canAccessContract(Contract $contract, User $user): bool
    {
        $contract->loadMissing('reservation.car:id,branch_id');
        $branchId = $contract->branch_id
            ? (int) $contract->branch_id
            : ($contract->reservation?->car?->branch_id ? (int) $contract->reservation->car->branch_id : null);

        return $this->branchAccess->canAccessBranchId($user, $branchId);
    }

    private function returnReportBalance(ContractReturnReport $report): float
    {
        $paid = $report->reservation?->payments
            ? $report->reservation->payments
                ->filter(fn (Payment $payment): bool => $this->paymentStatusValue($payment) === PaymentStatus::COMPLETED->value)
                ->filter(fn (Payment $payment): bool => $this->isPaymentForReturnReport($payment, $report))
                ->sum(fn (Payment $payment): float => (float) $payment->amount)
            : 0;

        return round(max(0, (float) $report->total_extra_charges - (float) $paid), 2);
    }

    private function isPaymentForReturnReport(Payment $payment, ContractReturnReport $report): bool
    {
        $sourceType = (string) data_get($payment->gateway_data, 'cash_source.type');
        $sourceId = (int) data_get($payment->gateway_data, 'cash_source.id');

        if ($sourceType === 'contract_return_report' && $sourceId === (int) $report->id) {
            return true;
        }

        return $report->payment_id && (int) $payment->id === (int) $report->payment_id;
    }

    private function calculateDiscountAmount(string $type, float $value, float $baseAmount): float
    {
        if ($type === 'percentage') {
            $value = min($value, 100);

            return round(min($baseAmount, $baseAmount * ($value / 100)), 2);
        }

        return round(min($baseAmount, $value), 2);
    }

    private function reportPaymentStatusAfterDiscount(ContractReturnReport $report, float $newTotalExtraCharges): string
    {
        $paid = $report->reservation?->payments
            ? $report->reservation->payments
                ->filter(fn (Payment $payment): bool => $this->paymentStatusValue($payment) === PaymentStatus::COMPLETED->value)
                ->filter(fn (Payment $payment): bool => $this->isPaymentForReturnReport($payment, $report))
                ->sum(fn (Payment $payment): float => (float) $payment->amount)
            : 0;

        return round(max(0, $newTotalExtraCharges - (float) $paid), 2) <= 0 ? 'paid' : 'not_paid';
    }

    private function reservationSettings(int $tenantId): array
    {
        $settings = TenantSiteSetting::query()
            ->where('tenant_id', $tenantId)
            ->value('reservation_settings');

        $decoded = is_string($settings) ? json_decode($settings, true) : $settings;

        return ReservationSettings::normalize(is_array($decoded) ? $decoded : null);
    }

    private function discountRequestPayload(DiscountRequest $discountRequest): array
    {
        return [
            'id' => $discountRequest->id,
            'reservation_id' => $discountRequest->reservation_id,
            'reservation_number' => $discountRequest->reservation?->reservation_number,
            'contract_id' => $discountRequest->contract_id,
            'contract_number' => $discountRequest->contract?->contract_number,
            'return_report_id' => $discountRequest->contract_return_report_id,
            'return_report_number' => $discountRequest->returnReport?->report_number,
            'requested_by' => $discountRequest->requestedBy ? [
                'id' => $discountRequest->requestedBy->id,
                'name' => $discountRequest->requestedBy->name,
                'email' => $discountRequest->requestedBy->email,
            ] : null,
            'base_amount' => (float) $discountRequest->base_amount,
            'discount_type' => $discountRequest->discount_type,
            'discount_value' => (float) $discountRequest->discount_value,
            'discount_amount' => (float) $discountRequest->discount_amount,
            'final_amount' => (float) $discountRequest->final_amount,
            'reason' => $discountRequest->reason,
            'status' => $discountRequest->status instanceof DiscountRequestStatus ? $discountRequest->status->value : (string) $discountRequest->status,
            'review_note' => $discountRequest->review_note,
            'reviewed_by' => $discountRequest->reviewedBy ? [
                'id' => $discountRequest->reviewedBy->id,
                'name' => $discountRequest->reviewedBy->name,
                'email' => $discountRequest->reviewedBy->email,
            ] : null,
            'created_at' => optional($discountRequest->created_at)->toIso8601String(),
            'reviewed_at' => optional($discountRequest->reviewed_at)->toIso8601String(),
            'approved_at' => optional($discountRequest->approved_at)->toIso8601String(),
            'rejected_at' => optional($discountRequest->rejected_at)->toIso8601String(),
        ];
    }

    private function paymentStatusValue(Payment $payment): string
    {
        return $payment->status instanceof PaymentStatus ? $payment->status->value : (string) $payment->status;
    }

    private function message(string $en, string $ar): string
    {
        $locale = strtolower(substr((string) request()->header('Accept-Language', app()->getLocale()), 0, 2));

        return $locale === 'ar' ? $ar : $en;
    }
}
