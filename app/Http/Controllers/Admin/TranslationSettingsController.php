<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\TenantSiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class TranslationSettingsController extends Controller
{
    public function edit(): Response
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        $tenant->loadMissing('siteSetting');
        $settings = TenantSiteSetting::forTenant($tenant);
        $supportedLocales = $this->supportedLocaleKeys();
        $supportedLocaleMeta = LaravelLocalization::getSupportedLocales();

        $flatBaseByLocale = [];
        $flatOverrideByLocale = [];
        $keyPool = [];

        foreach ($supportedLocales as $locale) {
            $baseTranslations = trans('site', [], $locale);
            if (!is_array($baseTranslations)) {
                $baseTranslations = trans('site', [], config('app.fallback_locale', 'en'));
            }

            $flatBaseByLocale[$locale] = is_array($baseTranslations)
                ? $this->flatten($baseTranslations)
                : [];
            $flatOverrideByLocale[$locale] = $this->flatten((array) data_get($settings, "translations.$locale", []));
            $keyPool = array_merge($keyPool, array_keys($flatBaseByLocale[$locale]), array_keys($flatOverrideByLocale[$locale]));
        }

        $keys = array_values(array_unique($keyPool));
        sort($keys);

        $rows = array_map(function (string $key) use ($supportedLocales, $flatBaseByLocale, $flatOverrideByLocale): array {
            $defaults = [];
            $values = [];
            foreach ($supportedLocales as $locale) {
                $defaults[$locale] = (string) ($flatBaseByLocale[$locale][$key] ?? '');
                $values[$locale] = (string) ($flatOverrideByLocale[$locale][$key] ?? '');
            }

            return [
                'key' => $key,
                'defaults' => $defaults,
                'values' => $values,
            ];
        }, $keys);

        return Inertia::render('Admin/Settings/Translations', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'default_locale' => data_get($settings, 'default_locale', $supportedLocales[0] ?? 'en'),
            'supported_locales' => array_values(array_map(function (string $code) use ($supportedLocaleMeta): array {
                $meta = (array) ($supportedLocaleMeta[$code] ?? []);

                return [
                    'code' => $code,
                    'name' => (string) ($meta['name'] ?? strtoupper($code)),
                    'native' => (string) ($meta['native'] ?? strtoupper($code)),
                ];
            }, $supportedLocales)),
            'enabled_locales' => data_get($settings, 'enabled_locales', $supportedLocales),
            'rows' => $rows,
            'actions' => [
                'update' => url()->current(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);
        $supportedLocales = $this->supportedLocaleKeys();

        $validated = $request->validate([
            'default_locale' => ['required', 'string', Rule::in($supportedLocales)],
            'enabled_locales' => ['nullable', 'array'],
            'enabled_locales.*' => ['string', Rule::in($supportedLocales)],
            'rows' => ['required', 'array'],
            'rows.*.key' => ['required', 'string', 'max:255'],
            'rows.*.values' => ['nullable', 'array'],
        ]);

        $enabledLocales = $this->sanitizeEnabledLocales($validated['enabled_locales'] ?? $supportedLocales);
        $defaultLocale = (string) ($validated['default_locale'] ?? ($enabledLocales[0] ?? $supportedLocales[0] ?? 'en'));
        if (!in_array($defaultLocale, $enabledLocales, true)) {
            $defaultLocale = (string) ($enabledLocales[0] ?? $supportedLocales[0] ?? 'en');
        }

        $overridesByLocale = [];
        foreach ($supportedLocales as $locale) {
            $overridesByLocale[$locale] = [];
        }

        foreach ((array) ($validated['rows'] ?? []) as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $values = is_array($row['values'] ?? null) ? $row['values'] : [];
            foreach ($supportedLocales as $locale) {
                $text = trim((string) ($values[$locale] ?? ''));
                if ($text === '') {
                    continue;
                }
                Arr::set($overridesByLocale[$locale], $key, $text);
            }
        }

        TenantSiteSetting::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'default_locale' => $defaultLocale,
                'enabled_locales' => $enabledLocales,
                'translations' => $overridesByLocale,
            ]
        );

        return back()->with('success', 'Translations updated successfully.');
    }

    private function flatten(array $input, string $prefix = ''): array
    {
        $flat = [];

        foreach ($input as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;

            if (is_array($value)) {
                $flat = array_merge($flat, $this->flatten($value, $fullKey));
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $flat[$fullKey] = (string) ($value ?? '');
            }
        }

        return $flat;
    }

    private function sanitizeEnabledLocales(mixed $value): array
    {
        $supported = $this->supportedLocaleKeys();
        $enabled = is_array($value) ? $value : [];
        $enabled = array_values(array_unique(array_intersect($supported, array_map('strval', $enabled))));

        return empty($enabled) ? $supported : $enabled;
    }

    private function supportedLocaleKeys(): array
    {
        $supported = array_keys((array) config('laravellocalization.supportedLocales', []));
        if (empty($supported)) {
            $supported = array_values((array) config('app.available_locales', ['en']));
        }

        $supported = array_values(array_unique(array_map('strval', $supported)));

        return empty($supported) ? ['en'] : $supported;
    }
}
