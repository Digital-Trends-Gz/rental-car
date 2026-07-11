<?php

namespace App\Support;

use App\Models\Discount;
use App\Models\Plan;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PlanPricing
{
    public static function decorateCollection(iterable $plans, ?CarbonInterface $now = null): Collection
    {
        return collect($plans)->map(
            static fn (Plan $plan): Plan => self::decoratePlan($plan, $now)
        );
    }

    public static function decoratePlan(Plan $plan, ?CarbonInterface $now = null): Plan
    {
        $plan->setAttribute('pricing_meta', [
            'monthly' => self::pricingForCycle($plan, 'monthly', $now),
            'yearly' => self::pricingForCycle($plan, 'yearly', $now),
            'one_time' => self::pricingForCycle($plan, 'one_time', $now),
        ]);

        return $plan;
    }

    public static function resolveAmount(Plan $plan, string $billingCycle, ?CarbonInterface $now = null): float
    {
        return (float) data_get(
            self::pricingForCycle($plan, $billingCycle, $now),
            'final_amount',
            0.0
        );
    }

    public static function pricingForCycle(Plan $plan, string $billingCycle, ?CarbonInterface $now = null): array
    {
        $baseAmount = self::baseAmountForCycle($plan, $billingCycle);

        if ($baseAmount === null) {
            return [
                'billing_cycle' => $billingCycle,
                'original_amount' => null,
                'final_amount' => null,
                'savings_amount' => 0.0,
                'savings_percentage' => 0.0,
                'has_discount' => false,
                'discount' => null,
            ];
        }

        $best = null;
        foreach (self::activeDiscountsForPlan($plan, $now) as $discount) {
            $candidate = self::applyDiscount($baseAmount, $discount, $billingCycle);

            if (($candidate['savings_amount'] ?? 0.0) <= 0) {
                continue;
            }

            if ($best === null || $candidate['final_amount'] < $best['final_amount']) {
                $best = $candidate;
            }
        }

        if ($best === null) {
            return [
                'billing_cycle' => $billingCycle,
                'original_amount' => round($baseAmount, 2),
                'final_amount' => round($baseAmount, 2),
                'savings_amount' => 0.0,
                'savings_percentage' => 0.0,
                'has_discount' => false,
                'discount' => null,
            ];
        }

        return $best;
    }

    private static function activeDiscountsForPlan(Plan $plan, ?CarbonInterface $now = null): Collection
    {
        $now ??= now();

        if ($plan->relationLoaded('discounts')) {
            return collect($plan->discounts)->filter(
                static fn (Discount $discount): bool => self::isDiscountActive($discount, $now)
            )->values();
        }

        return $plan->discounts()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $now->toDateString())
            ->whereDate('end_date', '>=', $now->toDateString())
            ->get();
    }

    private static function isDiscountActive(Discount $discount, CarbonInterface $now): bool
    {
        if (!$discount->is_active) {
            return false;
        }

        $today = $now->toDateString();

        return (string) optional($discount->start_date)->toDateString() <= $today
            && (string) optional($discount->end_date)->toDateString() >= $today;
    }

    private static function baseAmountForCycle(Plan $plan, string $billingCycle): ?float
    {
        return match ($billingCycle) {
            'yearly' => (float) $plan->yearly_price,
            'one_time' => $plan->one_time_price !== null ? (float) $plan->one_time_price : null,
            default => (float) $plan->monthly_price,
        };
    }

    private static function applyDiscount(float $baseAmount, Discount $discount, string $billingCycle): array
    {
        $discountType = (string) $discount->type;
        $discountValue = max(0.0, (float) $discount->value);

        $finalAmount = $baseAmount;

        if ($discountType === 'percentage') {
            $finalAmount = $baseAmount * (1 - min($discountValue, 100) / 100);
        } elseif ($discountType === 'fixed') {
            $finalAmount = max(0.0, $baseAmount - $discountValue);
        }

        $finalAmount = round(max(0.0, $finalAmount), 2);
        $savingsAmount = round(max(0.0, $baseAmount - $finalAmount), 2);
        $savingsPercentage = $baseAmount > 0
            ? round(($savingsAmount / $baseAmount) * 100, 2)
            : 0.0;

        return [
            'billing_cycle' => $billingCycle,
            'original_amount' => round($baseAmount, 2),
            'final_amount' => $finalAmount,
            'savings_amount' => $savingsAmount,
            'savings_percentage' => $savingsPercentage,
            'has_discount' => $savingsAmount > 0,
            'discount' => [
                'id' => $discount->id,
                'name' => (string) $discount->name,
                'type' => $discountType,
                'value' => $discountValue,
            ],
        ];
    }
}
