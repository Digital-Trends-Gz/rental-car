<?php

namespace App\Notifications;

use App\Core\AppBrandingSettings;
use App\Core\EmailTemplateSettings;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

class TenantAdminInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Tenant $tenant)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $user = $notifiable instanceof User ? $notifiable : null;
        $token = $user ? Password::broker()->createToken($user) : null;
        $activationUrl = $this->activationUrl($user, $token);
        $appName = AppBrandingSettings::load()['app_name'] ?? config('app.name', 'Car4u');
        $template = EmailTemplateSettings::load()['tenant_admin_invitation'];
        $tokens = [
            '{app_name}' => $appName,
            '{name}' => (string) $notifiable->name,
            '{email}' => (string) ($user?->email ?? ''),
            '{tenant_name}' => (string) $this->tenant->name,
            '{tenant_slug}' => (string) $this->tenant->slug,
            '{expire_minutes}' => (string) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
        ];

        return (new MailMessage())
            ->subject($this->render($template['subject'], $tokens))
            ->greeting($this->render($template['greeting'], $tokens))
            ->line($this->render($template['intro'], $tokens))
            ->line($this->render($template['body'], $tokens))
            ->action($this->render($template['action_text'], $tokens), $activationUrl)
            ->line($this->render($template['outro'], $tokens))
            ->salutation($this->render($template['salutation'], $tokens));
    }

    private function activationUrl(?User $user, ?string $token): string
    {
        if (!$user || !$token || trim((string) $this->tenant->slug) === '') {
            return route('home');
        }

        return route('tenant.password.reset', [
            'subdomain' => $this->tenant->slug,
            'token' => $token,
            'email' => $user->email,
        ]);
    }

    private function render(string $value, array $tokens): string
    {
        return strtr($value, $tokens);
    }
}
