<?php

namespace App\Enums;

enum MaintenanceRecordStatus: string
{
    case SCHEDULED = 'scheduled';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        $locale = app()->getLocale();

        return match ($locale) {
            'ar' => match ($this) {
                self::SCHEDULED => 'مجدول',
                self::IN_PROGRESS => 'قيد التنفيذ',
                self::COMPLETED => 'مكتمل',
                self::CANCELLED => 'ملغي',
            },
            'ur' => match ($this) {
                self::SCHEDULED => 'شیڈول',
                self::IN_PROGRESS => 'جاری',
                self::COMPLETED => 'مکمل',
                self::CANCELLED => 'منسوخ',
            },
            default => match ($this) {
                self::SCHEDULED => 'Scheduled',
                self::IN_PROGRESS => 'In Progress',
                self::COMPLETED => 'Completed',
                self::CANCELLED => 'Cancelled',
            },
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SCHEDULED => '#3B82F6',
            self::IN_PROGRESS => '#F59E0B',
            self::COMPLETED => '#10B981',
            self::CANCELLED => '#EF4444',
        };
    }
}
