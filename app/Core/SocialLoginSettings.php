<?php

namespace App\Core;

use App\Models\SiteSetting;

class SocialLoginSettings
{
    public const KEY = 'social_login';

    /**
     * Default social login settings.
     */
    public static function defaults(): array
    {
        return [
            'google' => [
                'enabled' => false,
                'client_id' => '',
                'client_secret' => '',
            ],
            'apple' => [
                'enabled' => false,
                'client_id' => '',
                'client_secret' => '',
            ],
            // Note: We'll construct redirect URIs dynamically in the AppServiceProvider based on config('app.url')
        ];
    }

    /**
     * Load settings from the database or return defaults.
     */
    public static function load(): array
    {
        $stored = SiteSetting::query()
            ->where('key', self::KEY)
            ->value('value');

        return self::normalize(is_array($stored) ? $stored : []);
    }

    /**
     * Normalize incoming data to ensure it matches the expected structure.
     */
    public static function normalize(?array $data): array
    {
        $settings = array_replace_recursive(self::defaults(), is_array($data) ? $data : []);

        $settings['google']['enabled'] = (bool) ($settings['google']['enabled'] ?? false);
        $settings['google']['client_id'] = trim((string) ($settings['google']['client_id'] ?? ''));
        $settings['google']['client_secret'] = trim((string) ($settings['google']['client_secret'] ?? ''));

        $settings['apple']['enabled'] = (bool) ($settings['apple']['enabled'] ?? false);
        $settings['apple']['client_id'] = trim((string) ($settings['apple']['client_id'] ?? ''));
        $settings['apple']['client_secret'] = trim((string) ($settings['apple']['client_secret'] ?? ''));

        return $settings;
    }

    /**
     * Merge new settings over current ones, preserving secrets if they are masked.
     */
    public static function mergeSecrets(array $current, array $incoming): array
    {
        $merged = $incoming;

        // Preserve Google Secret
        if ($incoming['google']['client_secret'] === '********' || trim($incoming['google']['client_secret']) === '') {
            $merged['google']['client_secret'] = $current['google']['client_secret'] ?? '';
        }

        // Preserve Apple Secret
        if ($incoming['apple']['client_secret'] === '********' || trim($incoming['apple']['client_secret']) === '') {
            $merged['apple']['client_secret'] = $current['apple']['client_secret'] ?? '';
        }

        return $merged;
    }

    /**
     * Mask secrets for the UI.
     */
    public static function forUi(): array
    {
        $settings = self::load();

        if ($settings['google']['client_secret'] !== '') {
            $settings['google']['client_secret'] = '********';
        }

        if ($settings['apple']['client_secret'] !== '') {
            $settings['apple']['client_secret'] = '********';
        }

        return $settings;
    }

    public static function providerIsReady(string $provider): bool
    {
        if (!in_array($provider, ['google', 'apple'], true)) {
            return false;
        }

        $settings = self::load();

        if (! (bool) data_get($settings, "{$provider}.enabled")) {
            return false;
        }

        $credentials = self::credentials($provider, $settings);

        return $credentials['client_id'] !== '' && $credentials['client_secret'] !== '';
    }

    public static function credentials(string $provider, ?array $settings = null): array
    {
        $settings ??= self::load();

        return [
            'client_id' => trim((string) (data_get($settings, "{$provider}.client_id") ?: config("services.{$provider}.client_id", ''))),
            'client_secret' => trim((string) (data_get($settings, "{$provider}.client_secret") ?: config("services.{$provider}.client_secret", ''))),
        ];
    }
}
