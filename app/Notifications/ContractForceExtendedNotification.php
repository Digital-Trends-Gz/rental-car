<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractForceExtendedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Contract $contract,
        private readonly int $extraDays,
        private readonly float $extraAmount,
        private readonly string $reason = ''
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
            'kind' => 'contract_force_extended',
            'title' => 'Rental period extended by office',
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
            'extra_days' => $this->extraDays,
            'extra_amount' => number_format($this->extraAmount, 2, '.', ''),
            'reason' => $this->reason,
            'url' => $this->reservationUrl(),
            'created_at' => now()->toDateTimeString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $currency = strtoupper((string) ($this->contract->currency ?: config('app.currency_code', 'USD')));

        return (new MailMessage())
            ->subject(sprintf(
                'Your rental period was extended: %s',
                $this->contract->contract_number
            ))
            ->greeting('Rental update')
            ->line($this->message())
            ->line(sprintf(
                'Extra days: %d',
                $this->extraDays
            ))
            ->line(sprintf(
                'Extra amount: %s %s',
                $currency,
                number_format($this->extraAmount, 2, '.', ',')
            ))
            ->when($this->reason !== '', fn (MailMessage $message) => $message->line('Reason: '.$this->reason))
            ->action('View reservation', $this->reservationUrl())
            ->line('This change was applied by the rental office.');
    }

    private function message(): string
    {
        $reservation = $this->contract->reservation;
        $client = $reservation?->user;
        $carLabel = $this->carLabel();
        $contractNumber = $this->contract->contract_number ?: ('#'.$this->contract->id);
        $clientLabel = $client?->name ?: 'the client';

        return sprintf(
            'Contract %s for %s%s and %s was extended by the office.',
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

    private function reservationUrl(): string
    {
        $tenantSlug = Tenant::query()
            ->whereKey($this->contract->tenant_id)
            ->value('slug');

        if ($tenantSlug && $this->contract->reservation_id) {
            return route('client.reservations.show', [
                'subdomain' => $tenantSlug,
                'id' => $this->contract->reservation_id,
            ]);
        }

        return url("/client/reservations/{$this->contract->reservation_id}");
    }
}
