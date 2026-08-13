<?php

namespace App\Core;

use App\Models\SiteSetting;

class CaptchaSettings
{
    public const KEY = 'captcha';

    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'provider' => 'turnstile',
            'site_key' => '',
            'secret_key' => '',
            'forms' => [
                'login' => false,
                'register' => false,
            ],
        ];
    }

    public static function load(): array
    {
        $stored = SiteSetting::query()
            ->where('key', self::KEY)
            ->value('value');

        return self::normalize(is_array($stored) ? $stored : []);
    }

    public static function normalize(?array $data): array
    {
        $settings = array_replace_recursive(self::defaults(), is_array($data) ? $data : []);

        $settings['enabled'] = (bool) ($settings['enabled'] ?? false);
        $settings['provider'] = 'turnstile';
        $settings['site_key'] = trim((string) ($settings['site_key'] ?? ''));
        $settings['secret_key'] = trim((string) ($settings['secret_key'] ?? ''));
        $settings['forms']['login'] = (bool) data_get($settings, 'forms.login', false);
        $settings['forms']['register'] = (bool) data_get($settings, 'forms.register', false);

        return $settings;
    }

    public static function mergeSecrets(array $current, array $incoming): array
    {
        $merged = $incoming;

        if (($incoming['secret_key'] ?? '') === '********' || trim((string) ($incoming['secret_key'] ?? '')) === '') {
            $merged['secret_key'] = $current['secret_key'] ?? '';
        }

        return $merged;
    }

    public static function forUi(): array
    {
        $settings = self::load();

        if ($settings['secret_key'] !== '') {
            $settings['secret_key'] = '********';
        }

        return $settings;
    }

    public static function publicConfig(): array
    {
        $settings = self::load();
        $hasSiteKey = $settings['site_key'] !== '';
        $hasSecretKey = $settings['secret_key'] !== '';

        return [
            'enabled' => $settings['enabled'] && $hasSiteKey && $hasSecretKey,
            'provider' => $settings['provider'],
            'site_key' => $settings['site_key'],
            'forms' => $settings['forms'],
        ];
    }

    public static function enabledFor(string $form): bool
    {
        $settings = self::load();

        return $settings['enabled']
            && $settings['site_key'] !== ''
            && $settings['secret_key'] !== ''
            && (bool) data_get($settings, "forms.{$form}", false);
    }
}
