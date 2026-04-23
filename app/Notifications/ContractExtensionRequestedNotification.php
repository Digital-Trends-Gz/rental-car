<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Models\RentalExtensionRequest;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractExtensionRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly RentalExtensionRequest $request
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $reservation = $this->request->reservation;
        $contract = $this->request->contract;
        $car = $reservation?->car;
        $client = $reservation?->user;

        return [
            'kind' => 'contract_extension_requested',
            'title' => 'Extension request',
            'message' => $this->message(),
            'request_id' => $this->request->id,
            'contract_id' => $contract?->id,
            'contract_number' => $contract?->contract_number,
            'reservation_id' => $reservation?->id,
            'reservation_number' => $reservation?->reservation_number,
            'car_id' => $car?->id,
            'car_label' => $this->carLabel(),
            'client_name' => $client?->name,
            'client_email' => $client?->email,
            'new_end_date' => optional($this->request->new_end_date)?->toDateString(),
            'extra_days' => $this->request->extra_days,
            'extra_amount' => number_format((float) $this->request->extra_amount, 2, '.', ''),
            'reason' => $this->request->reason,
            'url' => $this->reservationUrl(),
            'created_at' => now()->toDateTimeString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $currency = strtoupper((string) ($this->request->contract?->currency ?: config('app.currency_code', 'USD')));

        return (new MailMessage())
            ->subject(sprintf(
                'Extension request for %s',
                $this->request->contract?->contract_number ?? '#'.$this->request->contract_id
            ))
            ->greeting('Rental extension request')
            ->line($this->message())
            ->line(sprintf('New end date: %s', optional($this->request->new_end_date)?->format('Y-m-d') ?? 'N/A'))
            ->line(sprintf(
                'Extra amount: %s %s',
                $currency,
                number_format((float) $this->request->extra_amount, 2, '.', ',')
            ))
            ->when((string) $this->request->reason !== '', fn (MailMessage $message) => $message->line('Reason: '.$this->request->reason))
            ->action('Review reservation', $this->reservationUrl())
            ->line('Please approve or reject the request from your reservation dashboard.');
    }

    private function message(): string
    {
        $contract = $this->request->contract;
        $reservation = $this->request->reservation;
        $client = $reservation?->user;
        $contractNumber = $contract?->contract_number ?: ('#'.$this->request->contract_id);
        $clientLabel = $client?->name ?: 'the client';

        return sprintf(
            'The office requested an extension for contract %s for %s.',
            $contractNumber,
            $clientLabel
        );
    }

    private function carLabel(): string
    {
        $car = $this->request->reservation?->car;

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
            ->whereKey($this->request->tenant_id)
            ->value('slug');

        if ($tenantSlug) {
            return route('client.reservations.index', [
                'subdomain' => $tenantSlug,
            ]);
        }

        return url('/client/reservations');
    }
}
