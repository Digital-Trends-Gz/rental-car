<?php

namespace App\Enums;

enum DamageRepairStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPEN => '#3B82F6',
            self::IN_PROGRESS => '#F59E0B',
            self::COMPLETED => '#10B981',
            self::CANCELLED => '#6B7280',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return [
            self::OPEN->value,
            self::IN_PROGRESS->value,
        ];
    }
}
