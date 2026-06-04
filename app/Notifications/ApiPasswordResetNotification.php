<?php

namespace App\Notifications;

use App\Core\AppBrandingSettings;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApiPasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $otp
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $user = $notifiable instanceof User ? $notifiable : null;
        $appName = AppBrandingSettings::load()['app_name'] ?? config('app.name', 'Car4u');
        $resetUrl = $this->resetUrl($user);
        $tenantName = $this->tenantName($user);
        $expireMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage())
            ->subject("Reset your password for {$appName}")
            ->greeting('Hello'.($user?->name ? ' '.$user->name : '').',')
            ->line('We received a request to reset your password.')
            ->line('Use the link below to reset it in the browser, or enter the OTP code in the mobile app.')
            ->action('Reset Password', $resetUrl)
            ->line('OTP code: '.$this->otp)
            ->line('This reset link will expire in '.$expireMinutes.' minutes.')
            ->line('If you did not request this reset, you can ignore this email.')
            ->salutation($tenantName ? $tenantName : $appName);
    }

    private function resetUrl(?User $user): string
    {
        if (!$user) {
            return route('password.request');
        }

        $tenantSlug = $this->tenantSlug($user);

        if ($tenantSlug !== null) {
            return route('tenant.password.reset', [
                'subdomain' => $tenantSlug,
                'token' => $this->token,
                'email' => $user->email,
            ]);
        }

        return route('password.reset', [
            'token' => $this->token,
            'email' => $user->email,
        ]);
    }

    private function tenantName(?User $user): ?string
    {
        if (!$user || empty($user->tenant_id)) {
            return null;
        }

        return Tenant::query()
            ->whereKey((int) $user->tenant_id)
            ->value('name');
    }

    private function tenantSlug(?User $user): ?string
    {
        if (!$user || empty($user->tenant_id)) {
            return null;
        }

        $slug = Tenant::query()
            ->whereKey((int) $user->tenant_id)
            ->value('slug');

        return is_string($slug) && trim($slug) !== '' ? $slug : null;
    }
}
