<?php

namespace App\Services\DiscountRequests;

use App\Enums\DiscountRequestStatus;
use App\Enums\PaymentStatus;
use App\Models\ContractReturnReport;
use App\Models\DiscountRequest;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiscountRequestDecisionService
{
    public function approve(DiscountRequest $discountRequest, User $reviewer): DiscountRequest
    {
        return DB::transaction(function () use ($discountRequest, $reviewer): DiscountRequest {
            $lockedRequest = DiscountRequest::query()
                ->whereKey($discountRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== DiscountRequestStatus::PENDING) {
                throw ValidationException::withMessages([
                    'discount_request' => 'This discount request is no longer pending.',
                ]);
            }

            $report = ContractReturnReport::query()
                ->with(['reservation.payments'])
                ->whereKey($lockedRequest->contract_return_report_id)
                ->lockForUpdate()
                ->firstOrFail();

            $remainingAmount = $this->returnReportBalance($report);
            if ($remainingAmount <= 0) {
                throw ValidationException::withMessages([
                    'discount_request' => 'There is no remaining return report amount to discount.',
                ]);
            }

            $appliedDiscount = round(min((float) $lockedRequest->discount_amount, $remainingAmount), 2);
            $newDiscountAmount = round((float) ($report->discount ?? 0) + $appliedDiscount, 2);
            $newTotalExtraCharges = round(max(0, (float) $report->total_extra_charges - $appliedDiscount), 2);

            $report->forceFill([
                'discount' => $newDiscountAmount,
                'total_extra_charges' => $newTotalExtraCharges,
                'payment_status' => $this->reportPaymentStatusAfterDiscount($report, $newTotalExtraCharges),
            ])->save();

            $lockedRequest->forceFill([
                'discount_amount' => $appliedDiscount,
                'final_amount' => round(max(0, $remainingAmount - $appliedDiscount), 2),
                'status' => DiscountRequestStatus::APPROVED,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'approved_at' => now(),
            ])->save();

            return $lockedRequest->fresh([
                'reservation.user',
                'reservation.car.branch',
                'contract',
                'returnReport',
                'requestedBy',
                'reviewedBy',
            ]);
        });
    }

    public function reject(DiscountRequest $discountRequest, User $reviewer, ?string $reviewNote = null): DiscountRequest
    {
        return DB::transaction(function () use ($discountRequest, $reviewer, $reviewNote): DiscountRequest {
            $lockedRequest = DiscountRequest::query()
                ->whereKey($discountRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== DiscountRequestStatus::PENDING) {
                throw ValidationException::withMessages([
                    'discount_request' => 'This discount request is no longer pending.',
                ]);
            }

            $lockedRequest->forceFill([
                'status' => DiscountRequestStatus::REJECTED,
                'reviewed_by_user_id' => $reviewer->id,
                'review_note' => trim((string) $reviewNote) ?: null,
                'reviewed_at' => now(),
                'rejected_at' => now(),
            ])->save();

            return $lockedRequest->fresh([
                'reservation.user',
                'reservation.car.branch',
                'contract',
                'returnReport',
                'requestedBy',
                'reviewedBy',
            ]);
        });
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

    private function isPaymentForReturnReport(Payment $payment, ContractReturnReport $report): bool
    {
        $sourceType = (string) data_get($payment->gateway_data, 'cash_source.type');
        $sourceId = (int) data_get($payment->gateway_data, 'cash_source.id');

        if ($sourceType === 'contract_return_report' && $sourceId === (int) $report->id) {
            return true;
        }

        return $report->payment_id && (int) $payment->id === (int) $report->payment_id;
    }

    private function paymentStatusValue(Payment $payment): string
    {
        return $payment->status instanceof PaymentStatus ? $payment->status->value : (string) $payment->status;
    }
}
