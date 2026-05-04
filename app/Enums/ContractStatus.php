<?php

namespace App\Enums;

enum ContractStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public static function statusColors(): array
    {
        return [
            self::DRAFT->value => '#9CA3AF',        // Gray-400
            self::PENDING->value => '#F59E0B',      // Amber-500
            self::ACTIVE->value => '#3B82F6',       // Blue-500
            self::COMPLETED->value => '#10B981',    // Green-500
            self::CANCELLED->value => '#EF4444',    // Red-500
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::ACTIVE => 'Active',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return self::statusColors()[$this->value] ?? '#6B7280';
    }
}
