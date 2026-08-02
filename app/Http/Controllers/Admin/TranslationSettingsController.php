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
    public function edit(Request $request): Response
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        $tenant->loadMissing('siteSetting');
        $settings = TenantSiteSetting::forTenant($tenant);
        $supportedLocales = $this->supportedLocaleKeys();
        $supportedLocaleMeta = LaravelLocalization::getSupportedLocales();
        $search = trim((string) $request->query('search', ''));
        $focusedLocale = (string) $request->query('focus_locale', $supportedLocales[0] ?? 'en');
        if (! in_array($focusedLocale, $supportedLocales, true)) {
            $focusedLocale = $supportedLocales[0] ?? 'en';
        }
        $onlyCustomized = filter_var($request->query('only_customized', false), FILTER_VALIDATE_BOOLEAN);
        $onlyEmptyForFocusedLocale = filter_var($request->query('only_empty', false), FILTER_VALIDATE_BOOLEAN);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(200, max(25, (int) $request->query('per_page', 50)));

        $flatBaseByLocale = [];
        $flatOverrideByLocale = [];
        $keyPool = [];

        foreach ($supportedLocales as $locale) {
            $baseTranslations = $this->baseTranslationsForLocale($locale);

            $flatBaseByLocale[$locale] = is_array($baseTranslations)
                ? $this->flatten($baseTranslations)
                : [];
            $flatOverrideByLocale[$locale] = $this->flatten((array) data_get($settings, "translations.$locale", []));
            $keyPool = array_merge($keyPool, array_keys($flatBaseByLocale[$locale]), array_keys($flatOverrideByLocale[$locale]));
        }

        $this->addTenantWebsiteContentRows($settings, $supportedLocales, $flatBaseByLocale, $keyPool);

        $keys = array_values(array_unique($keyPool));
        sort($keys);

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $keys = array_values(array_filter($keys, function (string $key) use ($needle, $supportedLocales, $flatBaseByLocale, $flatOverrideByLocale): bool {
                if (str_contains(mb_strtolower($key), $needle)) {
                    return true;
                }

                foreach ($supportedLocales as $locale) {
                    if (str_contains(mb_strtolower((string) ($flatBaseByLocale[$locale][$key] ?? '')), $needle)) {
                        return true;
                    }

                    if (str_contains(mb_strtolower((string) ($flatOverrideByLocale[$locale][$key] ?? '')), $needle)) {
                        return true;
                    }
                }

                return false;
            }));
        }

        if ($onlyCustomized) {
            $keys = array_values(array_filter($keys, function (string $key) use ($supportedLocales, $flatOverrideByLocale): bool {
                foreach ($supportedLocales as $locale) {
                    if (trim((string) ($flatOverrideByLocale[$locale][$key] ?? '')) !== '') {
                        return true;
                    }
                }

                return false;
            }));
        }

        if ($onlyEmptyForFocusedLocale) {
            $keys = array_values(array_filter($keys, fn (string $key): bool => trim((string) ($flatOverrideByLocale[$focusedLocale][$key] ?? '')) === ''));
        }

        $total = count($keys);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $paginatedKeys = array_slice($keys, ($page - 1) * $perPage, $perPage);

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
        }, $paginatedKeys);

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
            'filters' => [
                'search' => $search,
                'focus_locale' => $focusedLocale,
                'only_customized' => $onlyCustomized,
                'only_empty' => $onlyEmptyForFocusedLocale,
                'per_page' => $perPage,
            ],
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total === 0 ? null : (($page - 1) * $perPage) + 1,
                'to' => $total === 0 ? null : min($total, $page * $perPage),
            ],
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

        $settings = TenantSiteSetting::forTenant($tenant);
        $overridesByLocale = [];
        foreach ($supportedLocales as $locale) {
            $overridesByLocale[$locale] = (array) data_get($settings, "translations.$locale", []);
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
                    Arr::forget($overridesByLocale[$locale], $key);
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

    private function baseTranslationsForLocale(string $locale): array
    {
        $baseTranslations = trans('site', [], $locale);
        if (!is_array($baseTranslations)) {
            $baseTranslations = trans('site', [], config('app.fallback_locale', 'en'));
        }

        $baseTranslations = is_array($baseTranslations) ? $baseTranslations : [];

        foreach (['api', 'auth'] as $group) {
            $groupTranslations = trans($group, [], $locale);

            if (!is_array($groupTranslations)) {
                $groupTranslations = trans($group, [], config('app.fallback_locale', 'en'));
            }

            if (is_array($groupTranslations)) {
                $baseTranslations[$group] = $groupTranslations;
            }
        }

        return $baseTranslations;
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

    /**
     * Repeatable tenant website content is stored in tenant_site_settings.about,
     * but tenants still need to edit its public translations from the same table.
     */
    private function addTenantWebsiteContentRows(
        TenantSiteSetting $settings,
        array $supportedLocales,
        array &$flatBaseByLocale,
        array &$keyPool
    ): void {
        $about = is_array($settings->about) ? $settings->about : [];

        foreach ((array) data_get($about, 'values', []) as $index => $item) {
            $this->addLocalizedContentRow(
                "tenant_about.values.$index.title",
                data_get($item, 'title', []),
                $supportedLocales,
                $flatBaseByLocale,
                $keyPool
            );
            $this->addLocalizedContentRow(
                "tenant_about.values.$index.description",
                data_get($item, 'description', []),
                $supportedLocales,
                $flatBaseByLocale,
                $keyPool
            );
        }

        foreach ((array) data_get($about, 'team_members', []) as $index => $item) {
            $this->addLocalizedContentRow(
                "tenant_about.team_members.$index.name",
                data_get($item, 'title', []),
                $supportedLocales,
                $flatBaseByLocale,
                $keyPool
            );
            $this->addLocalizedContentRow(
                "tenant_about.team_members.$index.role",
                (string) data_get($item, 'role', ''),
                $supportedLocales,
                $flatBaseByLocale,
                $keyPool
            );
            $this->addLocalizedContentRow(
                "tenant_about.team_members.$index.bio",
                data_get($item, 'description', []),
                $supportedLocales,
                $flatBaseByLocale,
                $keyPool
            );
        }
    }

    private function addLocalizedContentRow(
        string $key,
        mixed $value,
        array $supportedLocales,
        array &$flatBaseByLocale,
        array &$keyPool
    ): void {
        $hasValue = false;

        foreach ($supportedLocales as $locale) {
            $text = $this->localizedContentValue($value, $locale);
            $flatBaseByLocale[$locale][$key] = $text;
            $hasValue = $hasValue || trim($text) !== '';
        }

        if ($hasValue) {
            $keyPool[] = $key;
        }
    }

    private function localizedContentValue(mixed $value, string $locale): string
    {
        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $candidate = $value[$locale] ?? $value['en'] ?? $value['ar'] ?? null;

            return is_string($candidate) || is_numeric($candidate) ? (string) $candidate : '';
        }

        return '';
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
