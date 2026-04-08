<?php

namespace App\Notifications;

use App\Models\CarDocument;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CarDocumentExpiryNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly CarDocument $document,
        private readonly int $daysRemaining
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
        $car = $this->document->car;
        $type = $this->document->type === 'license' ? 'license' : 'insurance';
        $title = $this->daysRemaining === 0
            ? ucfirst($type).' expires today'
            : ucfirst($type).' expiring soon';
        $carLabel = trim(sprintf(
            '%s %s %s',
            (string) ($car?->year ?? ''),
            (string) ($car?->make ?? ''),
            (string) ($car?->model ?? '')
        ));
        $plate = (string) ($car?->license_plate ?? '');
        $message = trim(sprintf(
            'The %s for %s%s %s.',
            $type,
            $carLabel !== '' ? $carLabel : 'this car',
            $plate !== '' ? " ({$plate})" : '',
            $this->expiryPhrase()
        ));

        return [
            'kind' => 'car_document_expiry',
            'title' => $title,
            'message' => $message,
            'url' => "/admin/cars/{$this->document->car_id}/documents/{$this->document->id}/edit",
            'car_id' => $this->document->car_id,
            'car_document_id' => $this->document->id,
            'document_type' => $this->document->type,
            'expiry_date' => optional($this->document->expiry_date)?->toDateString(),
            'days_remaining' => $this->daysRemaining,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $car = $this->document->car;
        $typeLabel = $this->document->type === 'license' ? 'car license' : 'car insurance';
        $carLabel = trim(sprintf(
            '%s %s %s',
            (string) ($car?->year ?? ''),
            (string) ($car?->make ?? ''),
            (string) ($car?->model ?? '')
        ));
        $plate = trim((string) ($car?->license_plate ?? ''));
        $subjectCar = $carLabel !== '' ? $carLabel : 'your car';
        $subjectSuffix = $plate !== '' ? " ({$plate})" : '';
        $subjectAction = $this->daysRemaining === 0
            ? ucfirst($typeLabel).' expires today'
            : sprintf('%s expires in %d days', ucfirst($typeLabel), $this->daysRemaining);

        return (new MailMessage())
            ->subject($subjectAction)
            ->greeting('Expiring vehicle document')
            ->line(sprintf(
                'The %s for %s%s %s.',
                $typeLabel,
                $subjectCar,
                $subjectSuffix,
                $this->expiryPhrase()
            ))
            ->line('Review the document details and renew it before the expiry date to avoid disruption.')
            ->line(sprintf(
                'Expiry date: %s',
                optional($this->document->expiry_date)?->format('Y-m-d') ?? 'N/A'
            ))
            ->action('Review document', $this->documentUrl())
            ->line('This reminder was sent automatically from the platform.');
    }

    private function expiryPhrase(): string
    {
        return $this->daysRemaining === 0
            ? 'expires today'
            : sprintf('expires in %d days', $this->daysRemaining);
    }

    private function documentUrl(): string
    {
        $tenantSlug = Tenant::query()
            ->whereKey($this->document->tenant_id)
            ->value('slug');

        if ($tenantSlug) {
            return route('admin.cars.documents.edit', [
                'subdomain' => $tenantSlug,
                'car' => $this->document->car_id,
                'document' => $this->document->id,
            ]);
        }

        return url("/admin/cars/{$this->document->car_id}/documents/{$this->document->id}/edit");
    }
}
