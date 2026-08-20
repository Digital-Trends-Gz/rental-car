<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case AWAITING_PAYMENT = 'awaiting_payment';
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case ACTIVE = 'active';
    case COMPLETED_WAIT_CONTRACT = 'completed_wait_contract';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case NO_SHOW = 'no_show';

    public static function statusColors(): array
    {
        return [
            self::AWAITING_PAYMENT->value => '#64748B',
            self::PENDING->value => '#F59E0B',    // Gray-900
            self::CONFIRMED->value => '#10B981',  // Green-500
            self::ACTIVE->value => '#3B82F6',     // Amber-500
            self::COMPLETED_WAIT_CONTRACT->value => '#F59E0B', // Amber-500
            self::COMPLETED->value => '#111827',  // Blue-500
            self::CANCELLED->value => '#EF4444',  // Red-500
            self::NO_SHOW->value => '#6B7280',    // Gray-500
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::AWAITING_PAYMENT => 'Awaiting Payment',
            self::PENDING => 'Pending',
            self::CONFIRMED => 'Confirmed',
            self::ACTIVE => 'Active',
            self::COMPLETED_WAIT_CONTRACT => 'Completed - Waiting for Contract',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::NO_SHOW => 'No Show',
        };
    }

    public function color(): string
    {
        return self::statusColors()[$this->value] ?? '#6B7280';
    }

    public static function manualCases(?string $currentValue = null): array
    {
        $cases = array_values(array_filter(
            self::cases(),
            fn (self $case) => !in_array($case, [self::AWAITING_PAYMENT, self::COMPLETED_WAIT_CONTRACT], true)
        ));

        foreach ([self::AWAITING_PAYMENT, self::COMPLETED_WAIT_CONTRACT] as $hiddenCase) {
            if ($currentValue === $hiddenCase->value) {
                $cases[] = $hiddenCase;
            }
        }

        return $cases;
    }

    public static function manualValues(?string $currentValue = null): array
    {
        return array_map(fn (self $case) => $case->value, self::manualCases($currentValue));
    }

    public static function manualMeta(?string $currentValue = null): array
    {
        return array_map(function (self $case) {
            return [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
            ];
        }, self::manualCases($currentValue));
    }

    public static function getMeta(): array
    {
        return array_map(function ($case) {
            return [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
            ];
        }, self::cases());
    }

    public static function dateBlockingValues(): array
    {
        return [
            self::AWAITING_PAYMENT->value,
            self::PENDING->value,
            self::CONFIRMED->value,
            self::ACTIVE->value,
        ];
    }

    public static function realBookingValues(): array
    {
        return array_values(array_filter(
            array_map(fn (self $case) => $case->value, self::cases()),
            fn (string $value) => $value !== self::AWAITING_PAYMENT->value
        ));
    }
}
