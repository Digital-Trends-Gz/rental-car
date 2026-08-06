<?php

namespace App\Enums;

enum ClientDocumentExtractionStatus: string
{
    case NOT_REQUESTED = 'not_requested';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REVIEWED = 'reviewed';

    public function label(): string
    {
        return match ($this) {
            self::NOT_REQUESTED => __('site.dashboard.admin.clients.documents.statuses.not_requested'),
            self::COMPLETED => __('site.dashboard.admin.clients.documents.statuses.completed'),
            self::FAILED => __('site.dashboard.admin.clients.documents.statuses.failed'),
            self::REVIEWED => __('site.dashboard.admin.clients.documents.statuses.reviewed'),
        };
    }
}
