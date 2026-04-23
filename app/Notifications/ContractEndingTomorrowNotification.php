<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractEndingTomorrowNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Contract $contract
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $reservation = $this->contract->reservation;
        $car = $reservation?->car;
        $client = $reservation?->user;

        return [
            'kind' => 'contract_ending_tomorrow',
            'title' => 'Contract ends tomorrow',
            'message' => $this->message(),
            'contract_id' => $this->contract->id,
            'contract_number' => $this->contract->contract_number,
            'reservation_id' => $this->contract->reservation_id,
            'reservation_number' => $reservation?->reservation_number,
            'car_id' => $reservation?->car_id,
            'car_label' => $this->carLabel(),
            'client_name' => $client?->name,
            'client_email' => $client?->email,
            'end_date' => optional($this->contract->end_date)?->toDateString(),
            'days_remaining' => 1,
            'url' => $this->contractUrl(),
            'created_at' => now()->toDateTimeString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject(sprintf(
                'Contract ends tomorrow: %s',
                $this->contract->contract_number
            ))
            ->greeting('Contract expiry reminder')
            ->line($this->message())
            ->line(sprintf(
                'End date: %s',
                optional($this->contract->end_date)?->format('Y-m-d') ?? 'N/A'
            ))
            ->action('Open contract', $this->contractUrl())
            ->line('This reminder was sent automatically from the platform.');
    }

    private function message(): string
    {
        $reservation = $this->contract->reservation;
        $client = $reservation?->user;
        $carLabel = $this->carLabel();
        $contractNumber = $this->contract->contract_number ?: ('#'.$this->contract->id);
        $clientLabel = $client?->name ?: 'the client';

        return sprintf(
            'Contract %s for %s%s and %s ends tomorrow.',
            $contractNumber,
            $carLabel,
            $carLabel !== '' ? '' : ' the reserved car',
            $clientLabel
        );
    }

    private function carLabel(): string
    {
        $car = $this->contract->reservation?->car;

        if (!$car) {
            return '';
        }

        return trim(sprintf(
            '%s %s %s (%s)',
            (string) ($car->year ?? ''),
            (string) ($car->make ?? ''),
            (string) ($car->model ?? ''),
            (string) ($car->license_plate ?? '')
        ));
    }

    private function contractUrl(): string
    {
        $tenantSlug = Tenant::query()
            ->whereKey($this->contract->tenant_id)
            ->value('slug');

        if ($tenantSlug) {
            return route('admin.contracts.show', [
                'subdomain' => $tenantSlug,
                'contract' => $this->contract->id,
            ]);
        }

        return url("/admin/contracts/{$this->contract->id}");
    }
}
