<?php

namespace App\Services\Bookings;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class AwaitingPaymentReservationReleaser
{
    public function release(
        Reservation $reservation,
        ?Payment $payment = null,
        string $reason = 'payment_cancelled',
        array $gatewayData = []
    ): bool {
        return (bool) DB::transaction(function () use ($reservation, $payment, $reason, $gatewayData) {
            $reservation = Reservation::withoutGlobalScope('tenant')->find($reservation->id);

            if (!$reservation || $reservation->status !== ReservationStatus::AWAITING_PAYMENT) {
                return false;
            }

            $payment = $payment ?: Payment::withoutGlobalScope('tenant')
                ->where('reservation_id', $reservation->id)
                ->latest('id')
                ->first();

            if ($payment && $payment->status === PaymentStatus::PENDING) {
                $payment->forceFill([
                    'status' => PaymentStatus::CANCELLED,
                    'gateway_response' => $reason,
                    'gateway_data' => array_merge((array) $payment->gateway_data, $gatewayData, [
                        'released_at' => now()->toDateTimeString(),
                        'release_reason' => $reason,
                    ]),
                ])->save();
            }

            $redemptions = CouponRedemption::withoutGlobalScope('tenant')
                ->where('reservation_id', $reservation->id)
                ->get(['id', 'coupon_id']);

            $redemptions
                ->groupBy('coupon_id')
                ->each(function ($couponRedemptions, $couponId): void {
                    $coupon = Coupon::withoutGlobalScope('tenant')->find($couponId);
                    if (!$coupon) {
                        return;
                    }

                    for ($i = 0; $i < $couponRedemptions->count(); $i++) {
                        Coupon::withoutGlobalScope('tenant')
                            ->whereKey($coupon->id)
                            ->where('used_count', '>', 0)
                            ->decrement('used_count');
                    }
                });

            if ($redemptions->isNotEmpty()) {
                CouponRedemption::withoutGlobalScope('tenant')
                    ->whereIn('id', $redemptions->pluck('id')->all())
                    ->delete();
            }

            $reservation->forceFill([
                'status' => ReservationStatus::CANCELLED->value,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
            ])->save();
            $reservation->delete();

            return true;
        });
    }
}
