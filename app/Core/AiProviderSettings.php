<?php

namespace App\Core;

use App\Models\SiteSetting;

class AiProviderSettings
{
    public const KEY = 'ai_provider_settings';

    public static function defaults(): array
    {
        return [
            'provider' => 'openai',
            'document_extraction_daily_limit' => 10,
            'openai' => [
                'api_key' => '',
                'organization' => '',
                'project' => '',
                'base_uri' => '',
                'model' => 'gpt-4.1-mini',
                'temperature' => 0.1,
                'max_output_tokens' => 1200,
                'system_prompt' => '',
            ],
            'google_document_ai' => [
                'enabled' => false,
                'project_id' => '',
                'location' => 'us',
                'processor_id' => '',
                'service_account_json' => '',
            ],
        ];
    }

    public static function normalize(?array $data): array
    {
        $defaults = self::defaults();
        $settings = array_replace_recursive($defaults, is_array($data) ? $data : []);

        return [
            'provider' => in_array(($settings['provider'] ?? ''), ['openai', 'google_document_ai'], true)
                ? (string) $settings['provider']
                : 'openai',
            'document_extraction_daily_limit' => self::normalizePositiveInteger(
                $settings['document_extraction_daily_limit'] ?? $defaults['document_extraction_daily_limit'],
                (int) $defaults['document_extraction_daily_limit']
            ),
            'openai' => [
                'api_key' => trim((string) ($settings['openai']['api_key'] ?? '')),
                'organization' => trim((string) ($settings['openai']['organization'] ?? '')),
                'project' => trim((string) ($settings['openai']['project'] ?? '')),
                'base_uri' => trim((string) ($settings['openai']['base_uri'] ?? '')),
                'model' => trim((string) ($settings['openai']['model'] ?? 'gpt-4.1-mini')),
                'temperature' => (float) ($settings['openai']['temperature'] ?? 0.1),
                'max_output_tokens' => (int) ($settings['openai']['max_output_tokens'] ?? 1200),
                'system_prompt' => trim((string) ($settings['openai']['system_prompt'] ?? '')),
            ],
            'google_document_ai' => [
                'enabled' => (bool) ($settings['google_document_ai']['enabled'] ?? false),
                'project_id' => trim((string) ($settings['google_document_ai']['project_id'] ?? '')),
                'location' => trim((string) ($settings['google_document_ai']['location'] ?? 'us')),
                'processor_id' => trim((string) ($settings['google_document_ai']['processor_id'] ?? '')),
                'service_account_json' => trim((string) ($settings['google_document_ai']['service_account_json'] ?? '')),
            ],
        ];
    }

    public static function load(): array
    {
        $stored = SiteSetting::query()
            ->where('key', self::KEY)
            ->value('value');

        return self::normalize(is_array($stored) ? $stored : null);
    }

    public static function forUi(): array
    {
        $settings = self::load();
        $hasOpenAiApiKey = $settings['openai']['api_key'] !== '';
        $hasGoogleCredentials = $settings['google_document_ai']['service_account_json'] !== '';

        // Do not expose existing secrets to the frontend.
        $settings['openai']['api_key'] = '';
        $settings['google_document_ai']['service_account_json'] = '';
        $settings['meta'] = [
            'has_openai_api_key' => $hasOpenAiApiKey,
            'has_google_credentials' => $hasGoogleCredentials,
        ];

        return $settings;
    }

    public static function mergeSecrets(array $current, array $incoming): array
    {
        if (($incoming['openai']['api_key'] ?? '') === '') {
            $incoming['openai']['api_key'] = (string) ($current['openai']['api_key'] ?? '');
        }

        if (($incoming['google_document_ai']['service_account_json'] ?? '') === '') {
            $incoming['google_document_ai']['service_account_json'] = (string) ($current['google_document_ai']['service_account_json'] ?? '');
        }

        return $incoming;
    }

    public static function isConfiguredForCurrentProvider(): bool
    {
        $settings = self::load();
        $provider = (string) ($settings['provider'] ?? 'openai');

        if ($provider === 'google_document_ai') {
            return (bool) ($settings['google_document_ai']['enabled'] ?? false)
                && trim((string) ($settings['google_document_ai']['project_id'] ?? '')) !== ''
                && trim((string) ($settings['google_document_ai']['location'] ?? '')) !== ''
                && trim((string) ($settings['google_document_ai']['processor_id'] ?? '')) !== ''
                && trim((string) ($settings['google_document_ai']['service_account_json'] ?? '')) !== '';
        }

        return trim((string) ($settings['openai']['api_key'] ?? '')) !== '';
    }

    private static function normalizePositiveInteger(mixed $value, int $fallback): int
    {
        if ($value === null || $value === '') {
            return max(1, $fallback);
        }

        if (is_numeric($value)) {
            $integer = (int) $value;

            return $integer >= 1 ? min($integer, 100000) : max(1, $fallback);
        }

        return max(1, $fallback);
    }
}
