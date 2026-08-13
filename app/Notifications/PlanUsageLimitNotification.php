<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PlanUsageLimitNotification extends Notification
{
    use Queueable;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(private readonly array $payload)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return array_merge($this->payload, [
            'kind' => 'plan_usage_limit',
            'created_at' => now()->toDateTimeString(),
        ]);
    }
}
