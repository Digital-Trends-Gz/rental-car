<?php

namespace App\Enums;

enum RentalExtensionRequestStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public static function getMeta(): array
    {
        return array_map(function (self $case) {
            return [
                'value' => $case->value,
                'label' => $case->label(),
            ];
        }, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => '#FFC107',
            self::APPROVED => '#28A745',
            self::REJECTED => '#DC3545',
        };
    }
}
