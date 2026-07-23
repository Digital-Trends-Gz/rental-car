<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Core\LocalizationSettings;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaticPageContentSettingsController extends Controller
{
    public const KEY = 'main_static_pages_content';

    public function edit(): Response
    {
        $localization = LocalizationSettings::load();

        return Inertia::render('SuperAdmin/Settings/StaticPagesContent', [
            'settings' => $this->settings($localization),
            'locales' => $localization['locales'],
            'actions' => [
                'update' => route('superadmin.settings.static-pages-content.update'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $localization = LocalizationSettings::load();

        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.support' => ['nullable', 'array'],
            'settings.support.*' => ['nullable', 'string', 'max:50000'],
            'settings.privacy_policy' => ['nullable', 'array'],
            'settings.privacy_policy.*' => ['nullable', 'string', 'max:50000'],
            'settings.terms_conditions' => ['nullable', 'array'],
            'settings.terms_conditions.*' => ['nullable', 'string', 'max:50000'],
            'settings.security_policy' => ['nullable', 'array'],
            'settings.security_policy.*' => ['nullable', 'string', 'max:50000'],
            'settings.tenant_pages' => ['nullable', 'array'],
            'settings.tenant_pages.privacy_policy' => ['nullable', 'array'],
            'settings.tenant_pages.privacy_policy.*' => ['nullable', 'string', 'max:50000'],
            'settings.tenant_pages.terms_of_use' => ['nullable', 'array'],
            'settings.tenant_pages.terms_of_use.*' => ['nullable', 'string', 'max:50000'],
            'settings.tenant_pages.security_policy' => ['nullable', 'array'],
            'settings.tenant_pages.security_policy.*' => ['nullable', 'string', 'max:50000'],
        ]);

        SiteSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => $this->normalize($validated['settings'] ?? [], $localization, false)]
        );

        SiteSetting::query()->updateOrCreate(
            ['key' => WebPageContentSettingsController::KEY],
            ['value' => $this->normalizeTenantPages($validated['settings']['tenant_pages'] ?? [], $localization)]
        );

        return back()->with('success', 'Static page content updated successfully.');
    }

    private function settings(array $localization): array
    {
        $stored = SiteSetting::query()
            ->where('key', self::KEY)
            ->value('value');

        return $this->normalize(is_array($stored) ? $stored : [], $localization);
    }

    private function normalize(array $settings, array $localization, bool $includeTenantPages = true): array
    {
        $localeCodes = LocalizationSettings::localeCodes($localization);
        $defaultLocale = LocalizationSettings::defaultLocale($localization);

        $normalized = [
            'support' => $this->localizedField($settings['support'] ?? null, $localeCodes, $defaultLocale),
            'privacy_policy' => $this->localizedField($settings['privacy_policy'] ?? null, $localeCodes, $defaultLocale),
            'terms_conditions' => $this->localizedField($settings['terms_conditions'] ?? null, $localeCodes, $defaultLocale),
            'security_policy' => $this->localizedField($settings['security_policy'] ?? null, $localeCodes, $defaultLocale),
        ];

        if ($includeTenantPages) {
            $normalized['tenant_pages'] = $this->tenantPageSettings($localization);
        }

        return $normalized;
    }

    private function tenantPageSettings(array $localization): array
    {
        $stored = SiteSetting::query()
            ->where('key', WebPageContentSettingsController::KEY)
            ->value('value');

        return $this->normalizeTenantPages(is_array($stored) ? $stored : [], $localization);
    }

    private function normalizeTenantPages(array $settings, array $localization): array
    {
        $localeCodes = LocalizationSettings::localeCodes($localization);
        $defaultLocale = LocalizationSettings::defaultLocale($localization);

        return [
            'privacy_policy' => $this->localizedField($settings['privacy_policy'] ?? null, $localeCodes, $defaultLocale),
            'terms_of_use' => $this->localizedField($settings['terms_of_use'] ?? null, $localeCodes, $defaultLocale),
            'security_policy' => $this->localizedField($settings['security_policy'] ?? null, $localeCodes, $defaultLocale),
        ];
    }

    private function localizedField(mixed $value, array $localeCodes, string $defaultLocale): array
    {
        $localized = [];

        foreach ($localeCodes as $localeCode) {
            if (is_array($value)) {
                $localized[$localeCode] = $this->sanitizeContent((string) ($value[$localeCode] ?? ''));

                continue;
            }

            $localized[$localeCode] = $localeCode === $defaultLocale ? $this->sanitizeContent((string) ($value ?? '')) : '';
        }

        return $localized;
    }

    private function sanitizeContent(string $content): string
    {
        $content = strip_tags($content, '<p><br><strong><b><em><i><u><s><blockquote><hr><h2><h3><h4><ul><ol><li><a>');
        $content = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $content) ?? $content;
        $content = preg_replace('/href\s*=\s*(["\'])\s*javascript:.*?\1/i', 'href="#"', $content) ?? $content;

        return trim($content);
    }
}
