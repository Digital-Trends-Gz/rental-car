<?php

namespace App\Notifications;

use App\Core\AppBrandingSettings;
use App\Core\EmailTemplateSettings;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class TenantAwareVerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $tenantName = $this->tenantName($notifiable);
        $tenantSlug = $this->tenantSlug($notifiable);
        $appName = AppBrandingSettings::load()['app_name'] ?? config('app.name', 'Car4u');
        $verificationUrl = $this->verificationUrl($notifiable);
        $expireMinutes = (int) config('auth.verification.expire', 60);
        $template = EmailTemplateSettings::load()['verify_email_after_payment'];
        $tokens = [
            '{app_name}' => $appName,
            '{name}' => (string) $notifiable->name,
            '{email}' => (string) $notifiable->email,
            '{tenant_name}' => $tenantName ?: $appName,
            '{tenant_slug}' => $tenantSlug ?: '',
            '{expire_minutes}' => (string) $expireMinutes,
        ];

        return (new MailMessage())
            ->subject($this->render($template['subject'], $tokens))
            ->greeting($this->render($template['greeting'], $tokens))
            ->line($this->render($template['intro'], $tokens))
            ->line($this->render($template['body'], $tokens))
            ->action($this->render($template['action_text'], $tokens), $verificationUrl)
            ->line($this->render($template['outro'], $tokens))
            ->salutation($this->render($template['salutation'], $tokens));
    }

    protected function verificationUrl($notifiable): string
    {
        $routeName = 'verification.verify';
        $parameters = [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ];

        if ($notifiable instanceof User && !empty($notifiable->tenant_id)) {
            $tenantSlug = Tenant::query()
                ->whereKey((int) $notifiable->tenant_id)
                ->value('slug');

            if (is_string($tenantSlug) && $tenantSlug !== '') {
                $routeName = 'tenant.verification.verify';
                $parameters['subdomain'] = $tenantSlug;
            }
        }

        return URL::temporarySignedRoute(
            $routeName,
            Carbon::now()->addMinutes((int) config('auth.verification.expire', 60)),
            $parameters,
        );
    }

    private function tenantName(object $notifiable): ?string
    {
        if (!$notifiable instanceof User || empty($notifiable->tenant_id)) {
            return null;
        }

        $tenant = Tenant::query()
            ->select(['id', 'name'])
            ->find((int) $notifiable->tenant_id);

        return $tenant?->name ?: null;
    }

    private function tenantSlug(object $notifiable): ?string
    {
        if (!$notifiable instanceof User || empty($notifiable->tenant_id)) {
            return null;
        }

        return Tenant::query()
            ->whereKey((int) $notifiable->tenant_id)
            ->value('slug');
    }

    private function render(string $value, array $tokens): string
    {
        return strtr($value, $tokens);
    }
}
