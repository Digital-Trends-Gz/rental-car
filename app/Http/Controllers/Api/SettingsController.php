<?php

namespace App\Http\Controllers\Api;

use App\Core\AppBrandingSettings;
use App\Core\LocalizationSettings;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function general(Request $request): JsonResponse
    {
        $branding = AppBrandingSettings::load();
        $siteName = $this->nullableString($branding['app_name'] ?? null) ?? config('app.name', 'Car4u');
        $localization = LocalizationSettings::load();
        $defaultLanguage = LocalizationSettings::defaultLocale($localization);
        $availableLanguages = $this->availableLanguages($localization);
        $enabledLanguageCodes = array_values(array_map(
            static fn (array $language): string => (string) $language['code'],
            $availableLanguages
        ));
        $activeLanguage = $this->resolveActiveLanguage($request, $defaultLanguage, $enabledLanguageCodes);

        return response()->json([
            'source' => 'super_admin',
            'site_name' => $siteName,
            'app_name' => $siteName,
            'logo_url' => $this->nullableString($branding['logo_url'] ?? null),
            'primary_color' => (string) ($branding['primary_color'] ?? '#3b82f6'),
            'secondary_color' => (string) ($branding['secondary_color'] ?? '#6d28d9'),
            'language' => $activeLanguage,
            'active_language' => $activeLanguage,
            'default_language' => $defaultLanguage,
            'enabled_language_codes' => $enabledLanguageCodes,
            'available_languages' => $availableLanguages,
        ]);
    }

    public function tenant(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        if (empty($user->tenant_id)) {
            return response()->json([
                'message' => 'Tenant not found for this account.',
            ], 403);
        }

        $tenant = Tenant::query()->with('siteSetting.files')->find((int) $user->tenant_id);

        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant not found.',
            ], 404);
        }

        $settings = TenantSiteSetting::forTenant($tenant);
        $siteName = $this->nullableString(data_get($settings, 'site_name')) ?? $tenant->name;
        $availableLanguages = $this->availableLanguagesForTenant($settings);

        return response()->json([
            'source' => 'tenant',
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'domain' => $tenant->domain,
            ],
            'site_name' => $siteName,
            'app_name' => $siteName,
            'logo_url' => $this->nullableString(data_get($settings, 'logo_url')),
            'primary_color' => $this->normalizeHexColor(data_get($settings, 'primary_color'), '#f97316'),
            'secondary_color' => $this->normalizeHexColor(data_get($settings, 'secondary_color'), '#ea580c'),
            'default_language' => (string) data_get($settings, 'default_locale', config('app.locale', 'en')),
            'enabled_language_codes' => array_values(array_map(
                static fn (array $language): string => (string) $language['code'],
                $availableLanguages
            )),
            'available_languages' => $availableLanguages,
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function normalizeHexColor(mixed $value, string $fallback): string
    {
        $value = strtolower(trim((string) ($value ?? '')));

        if ($value !== '' && preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $value)) {
            return $value;
        }

        return $fallback;
    }

    /**
     * @return array<int, array{code: string, name: string, native: string, regional: string, script: string, direction: string}>
     */
    private function availableLanguagesForTenant(array $settings): array
    {
        $platformLanguages = collect(LocalizationSettings::load()['locales'] ?? [])
            ->filter(static fn (mixed $language): bool => is_array($language) && !empty($language['code']))
            ->keyBy(static fn (array $language): string => (string) $language['code']);

        $enabledCodes = array_values(array_filter(
            array_map('strval', (array) data_get($settings, 'enabled_locales', [])),
            static fn (string $code): bool => $code !== ''
        ));

        if (empty($enabledCodes)) {
            $enabledCodes = $platformLanguages->keys()->map(static fn ($code): string => (string) $code)->all();
        }

        return collect($enabledCodes)
            ->unique()
            ->map(function (string $code) use ($platformLanguages): array {
                $language = (array) ($platformLanguages->get($code) ?? []);

                return [
                    'code' => $code,
                    'name' => trim((string) ($language['name'] ?? strtoupper($code))),
                    'native' => trim((string) ($language['native'] ?? strtoupper($code))),
                    'regional' => trim((string) ($language['regional'] ?? '')),
                    'script' => trim((string) ($language['script'] ?? '')),
                    'direction' => in_array(($language['direction'] ?? null), ['ltr', 'rtl'], true)
                        ? (string) $language['direction']
                        : (str_starts_with($code, 'ar') ? 'rtl' : 'ltr'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{code: string, name: string, native: string, regional: string, script: string, direction: string}>
     */
    private function availableLanguages(array $localization): array
    {
        return collect($localization['locales'] ?? [])
            ->filter(static fn (mixed $language): bool => is_array($language) && !empty($language['code']))
            ->map(function (array $language): array {
                $code = (string) $language['code'];

                return [
                    'code' => $code,
                    'name' => trim((string) ($language['name'] ?? strtoupper($code))),
                    'native' => trim((string) ($language['native'] ?? strtoupper($code))),
                    'regional' => trim((string) ($language['regional'] ?? '')),
                    'script' => trim((string) ($language['script'] ?? '')),
                    'direction' => in_array(($language['direction'] ?? null), ['ltr', 'rtl'], true)
                        ? (string) $language['direction']
                        : (str_starts_with($code, 'ar') ? 'rtl' : 'ltr'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $enabledLanguageCodes
     */
    private function resolveActiveLanguage(Request $request, string $defaultLanguage, array $enabledLanguageCodes): string
    {
        $candidates = [
            $request->query('locale'),
            $request->query('lang'),
            $request->header('X-Locale'),
            $request->header('X-Language'),
            $request->getPreferredLanguage($enabledLanguageCodes),
            app()->getLocale(),
            $defaultLanguage,
        ];

        foreach ($candidates as $candidate) {
            $language = $this->normalizeLanguageCode($candidate);

            if ($language !== null && in_array($language, $enabledLanguageCodes, true)) {
                return $language;
            }
        }

        return $defaultLanguage;
    }

    private function normalizeLanguageCode(mixed $value): ?string
    {
        $value = trim(str_replace('_', '-', (string) ($value ?? '')));

        return $value === '' ? null : $value;
    }
}
