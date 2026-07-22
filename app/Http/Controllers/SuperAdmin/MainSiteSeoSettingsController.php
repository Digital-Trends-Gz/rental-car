<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Core\LocalizationSettings;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\TenantSiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class MainSiteSeoSettingsController extends Controller
{
    public const KEY = 'main_site_seo';

    public function __construct(
        private readonly FilePondService $filePondService,
    ) {}

    public function edit(): Response
    {
        $localizationSettings = LocalizationSettings::load();
        $siteSetting = SiteSetting::query()
            ->where('key', self::KEY)
            ->with('files')
            ->first();

        $seoOgImageFiles = $siteSetting
            ? $siteSetting->files()
                ->where('collection', 'seo_og_image')
                ->get()
                ->map(fn ($file) => [
                    'id' => $file->id,
                    'url' => SiteSetting::publicUrlFromPath($file->path),
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('SuperAdmin/Settings/SeoSettings', [
            'settings' => $this->seoSettings(),
            'locales' => $localizationSettings['locales'],
            'defaultLocale' => $localizationSettings['default_locale'],
            'seoOgImageFiles' => $seoOgImageFiles,
            'actions' => [
                'update' => route('superadmin.settings.seo.update'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->validationRules());
        $this->validateRedirectRules($validated);

        $siteSetting = SiteSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => $this->buildSeoPayload($validated, $this->seoSettings())]
        );

        $siteSetting = SiteSetting::query()
            ->where('key', self::KEY)
            ->with('files')
            ->first();

        if ($siteSetting) {
            $this->syncSeoOgImageUpload($request, $siteSetting);
        }

        return back()->with('success', 'Main site SEO settings updated successfully.');
    }

    private function seoSettings(): array
    {
        $stored = SiteSetting::query()
            ->where('key', self::KEY)
            ->value('value');

        $defaults = TenantSiteSetting::defaults()['seo'];
        $value = is_array($stored) ? $stored : [];

        return array_replace_recursive($defaults, $value);
    }

    private function validationRules(): array
    {
        $supportedLocales = $this->supportedLocaleKeys();

        $rules = [
            'seo.defaults.og_image' => ['nullable', 'string', 'max:1000'],
            'seo_og_image_temp_folders' => ['array'],
            'seo_og_image_temp_folders.*' => ['string'],
            'seo_og_image_removed_files' => ['array'],
            'seo_og_image_removed_files.*' => ['integer'],
            'seo.defaults.robots' => ['nullable', 'string', 'max:255'],
            'seo.technical.sitemap.pages' => ['nullable', 'array'],
            'seo.technical.sitemap.pages.*.path' => ['nullable', 'string', 'max:500'],
            'seo.technical.sitemap.pages.*.priority' => ['nullable', 'numeric', 'min:0.1', 'max:1.0'],
            'seo.technical.sitemap.pages.*.changeFreq' => ['nullable', Rule::in(['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'])],
            'seo.technical.sitemap.pages.*.lastmod' => ['nullable', 'date'],
            'seo.technical.robots.allowAll' => ['nullable', 'boolean'],
            'seo.technical.robots.disallowPaths' => ['nullable', 'array'],
            'seo.technical.robots.disallowPaths.*' => ['nullable', 'string', 'max:500'],
            'seo.technical.robots.crawlDelay' => ['nullable', 'integer', 'min:0', 'max:60'],
            'seo.technical.robots.requestRate' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'seo.technical.robots.sitemapUrl' => ['nullable', 'string', 'max:500'],
            'seo.technical.redirects.items' => ['nullable', 'array'],
            'seo.technical.redirects.items.*.id' => ['nullable', 'string', 'max:100'],
            'seo.technical.redirects.items.*.fromPath' => ['required_with:seo.technical.redirects.items', 'string', 'max:500'],
            'seo.technical.redirects.items.*.toPath' => ['required_with:seo.technical.redirects.items', 'string', 'max:500'],
            'seo.technical.redirects.items.*.statusCode' => ['required_with:seo.technical.redirects.items', Rule::in([301, 302, 307, 308])],
            'seo.technical.redirects.items.*.isPermanent' => ['nullable', 'boolean'],
            'seo.technical.redirects.items.*.isActive' => ['nullable', 'boolean'],
        ];

        foreach ($supportedLocales as $locale) {
            $rules["seo.defaults.title_suffix.{$locale}"] = ['nullable', 'string', 'max:255'];
            $rules["seo.defaults.default_description.{$locale}"] = ['nullable', 'string', 'max:500'];
            $rules["seo.defaults.og_image_alt.{$locale}"] = ['nullable', 'string', 'max:255'];
        }

        foreach ($this->seoPageKeys() as $pageKey) {
            $rules["seo.pages.{$pageKey}.canonical_url"] = ['nullable', 'string', 'max:1000'];
            $rules["seo.pages.{$pageKey}.og_image"] = ['nullable', 'string', 'max:1000'];
            $rules["seo.pages.{$pageKey}.robots"] = ['nullable', 'string', 'max:255'];

            foreach ($supportedLocales as $locale) {
                $rules["seo.pages.{$pageKey}.title.{$locale}"] = ['nullable', 'string', 'max:255'];
                $rules["seo.pages.{$pageKey}.description.{$locale}"] = ['nullable', 'string', 'max:500'];
                $rules["seo.pages.{$pageKey}.focus_keyword.{$locale}"] = ['nullable', 'string', 'max:255'];
            }
        }

        return $rules;
    }

    private function buildSeoPayload(array $validated, array $existing = []): array
    {
        $supportedLocales = $this->supportedLocaleKeys();

        return [
            'defaults' => [
                'title_suffix' => $this->localizedPayload($validated, 'seo.defaults.title_suffix', $supportedLocales, $existing, 'defaults.title_suffix'),
                'default_description' => $this->localizedPayload($validated, 'seo.defaults.default_description', $supportedLocales, $existing, 'defaults.default_description'),
                'og_image' => $this->nullableString(data_get($validated, 'seo.defaults.og_image')),
                'og_image_alt' => $this->localizedPayload($validated, 'seo.defaults.og_image_alt', $supportedLocales, $existing, 'defaults.og_image_alt'),
                'robots' => $this->nullableString(data_get($validated, 'seo.defaults.robots')) ?: 'index,follow',
            ],
            'pages' => collect($this->seoPageKeys())
                ->mapWithKeys(fn (string $pageKey) => [
                    $pageKey => $this->pagePayload($validated, $pageKey, $supportedLocales, $existing),
                ])
                ->all(),
            'technical' => [
                'sitemap' => [
                    'pages' => collect((array) data_get($validated, 'seo.technical.sitemap.pages', data_get($existing, 'technical.sitemap.pages', [])))
                        ->map(function ($page) {
                            $path = $this->nullableString(data_get($page, 'path'));
                            if ($path === null) {
                                return null;
                            }

                            $priority = data_get($page, 'priority');
                            $priority = is_numeric($priority) ? round((float) $priority, 1) : 0.5;

                            return [
                                'path' => str_starts_with($path, '/') ? $path : '/'.$path,
                                'priority' => max(0.1, min(1.0, $priority)),
                                'changeFreq' => (string) (data_get($page, 'changeFreq') ?: 'weekly'),
                                'lastmod' => $this->nullableString(data_get($page, 'lastmod')),
                            ];
                        })->filter()->values()->all(),
                ],
                'robots' => [
                    'allowAll' => (bool) data_get($validated, 'seo.technical.robots.allowAll', true),
                    'disallowPaths' => collect((array) data_get($validated, 'seo.technical.robots.disallowPaths', data_get($existing, 'technical.robots.disallowPaths', [])))
                        ->map(fn ($path) => $this->nullableString($path))
                        ->filter()->values()->all(),
                    'crawlDelay' => (int) data_get($validated, 'seo.technical.robots.crawlDelay', data_get($existing, 'technical.robots.crawlDelay', 1)),
                    'requestRate' => (int) data_get($validated, 'seo.technical.robots.requestRate', data_get($existing, 'technical.robots.requestRate', 30)),
                    'sitemapUrl' => (string) (data_get($validated, 'seo.technical.robots.sitemapUrl') ?: data_get($existing, 'technical.robots.sitemapUrl', '/sitemap.xml')),
                ],
                'redirects' => [
                    'items' => collect((array) data_get($validated, 'seo.technical.redirects.items', data_get($existing, 'technical.redirects.items', [])))
                        ->map(function ($item) {
                            $fromPath = $this->nullableString(data_get($item, 'fromPath'));
                            $toPath = $this->nullableString(data_get($item, 'toPath'));
                            if ($fromPath === null || $toPath === null) {
                                return null;
                            }

                            $statusCode = (int) data_get($item, 'statusCode', 301);

                            return [
                                'id' => (string) (data_get($item, 'id') ?: uniqid('redirect_', true)),
                                'fromPath' => str_starts_with($fromPath, '/') ? $fromPath : '/'.$fromPath,
                                'toPath' => str_starts_with($toPath, '/') ? $toPath : '/'.$toPath,
                                'statusCode' => in_array($statusCode, [301, 302, 307, 308], true) ? $statusCode : 301,
                                'isPermanent' => (bool) data_get($item, 'isPermanent', in_array($statusCode, [301, 308], true)),
                                'isActive' => (bool) data_get($item, 'isActive', true),
                            ];
                        })->filter()->values()->all(),
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function supportedLocaleKeys(): array
    {
        $settings = LocalizationSettings::load();

        return LocalizationSettings::localeCodes($settings);
    }

    /**
     * @return list<string>
     */
    private function seoPageKeys(): array
    {
        return [
            'home',
            'fleet',
            'applications',
            'plans',
            'privacy-policy',
            'terms-of-use',
            'security-policy',
        ];
    }

    /**
     * @param  list<string>  $supportedLocales
     */
    private function pagePayload(array $validated, string $key, array $supportedLocales, array $existing = []): array
    {
        return [
            'title' => $this->localizedPayload($validated, "seo.pages.{$key}.title", $supportedLocales, $existing, "pages.{$key}.title"),
            'description' => $this->localizedPayload($validated, "seo.pages.{$key}.description", $supportedLocales, $existing, "pages.{$key}.description"),
            'focus_keyword' => $this->localizedPayload($validated, "seo.pages.{$key}.focus_keyword", $supportedLocales, $existing, "pages.{$key}.focus_keyword"),
            'canonical_url' => $this->nullableString(data_get($validated, "seo.pages.{$key}.canonical_url")),
            'og_image' => $this->nullableString(data_get($validated, "seo.pages.{$key}.og_image")),
            'robots' => $this->nullableString(data_get($validated, "seo.pages.{$key}.robots")),
        ];
    }

    /**
     * @param  list<string>  $supportedLocales
     * @return array<string, string|null>
     */
    private function localizedPayload(array $validated, string $prefix, array $supportedLocales, array $existing = [], ?string $existingPrefix = null): array
    {
        $values = [];

        foreach ($supportedLocales as $locale) {
            $existingValue = $existingPrefix !== null ? data_get($existing, "{$existingPrefix}.{$locale}") : null;
            $values[$locale] = $this->nullableString(data_get($validated, "{$prefix}.{$locale}", $existingValue));
        }

        return $values;
    }

    private function syncSeoOgImageUpload(Request $request, SiteSetting $siteSetting): void
    {
        $tempFolders = is_array($request->input('seo_og_image_temp_folders', []))
            ? array_values(array_filter($request->input('seo_og_image_temp_folders', [])))
            : [];
        $removedIds = is_array($request->input('seo_og_image_removed_files', []))
            ? array_values(array_filter($request->input('seo_og_image_removed_files', [])))
            : [];
        $removedFileUrls = [];

        if (!empty($removedIds)) {
            $removedFileUrls = $siteSetting->files()
                ->where('collection', 'seo_og_image')
                ->whereIn('id', $removedIds)
                ->get()
                ->map(fn ($file) => SiteSetting::publicUrlFromPath($file->path))
                ->filter()
                ->values()
                ->all();
        }

        if (!empty($tempFolders)) {
            $existingIds = $siteSetting->files()->where('collection', 'seo_og_image')->pluck('id')->all();
            $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
        }

        $this->filePondService->handleFileUpdates(
            $siteSetting,
            $tempFolders,
            $removedIds,
            'seo_og_image'
        );

        $this->clearSeoOgImageReferenceIfNeeded($siteSetting, $removedFileUrls, $tempFolders);

        if (empty($tempFolders)) {
            return;
        }

        $ogImageFile = $siteSetting->files()
            ->where('collection', 'seo_og_image')
            ->latest('id')
            ->first();

        if (!$ogImageFile || !$ogImageFile->path) {
            return;
        }

        $seo = is_array($siteSetting->value) ? $siteSetting->value : [];
        data_set($seo, 'defaults.og_image', SiteSetting::publicUrlFromPath($ogImageFile->path));

        $siteSetting->update(['value' => $seo]);
    }

    private function clearSeoOgImageReferenceIfNeeded(SiteSetting $siteSetting, array $removedFileUrls, array $tempFolders): void
    {
        if (!empty($tempFolders) || empty($removedFileUrls)) {
            return;
        }

        $remainingFileExists = $siteSetting->files()
            ->where('collection', 'seo_og_image')
            ->exists();

        if ($remainingFileExists) {
            return;
        }

        $seo = is_array($siteSetting->value) ? $siteSetting->value : [];
        $currentOgImage = trim((string) data_get($seo, 'defaults.og_image', ''));

        if ($currentOgImage === '' || !in_array($currentOgImage, $removedFileUrls, true)) {
            return;
        }

        data_set($seo, 'defaults.og_image', null);
        $siteSetting->update(['value' => $seo]);
    }

    private function validateRedirectRules(array $validated): void
    {
        $items = collect((array) data_get($validated, 'seo.technical.redirects.items', []))
            ->map(function ($item, $index) {
                return [
                    'index' => $index,
                    'is_active' => (bool) data_get($item, 'isActive', true),
                    'from' => $this->normalizePath(data_get($item, 'fromPath')),
                    'to' => $this->normalizePath(data_get($item, 'toPath')),
                ];
            })
            ->filter(fn (array $item) => $item['is_active'] && $item['from'] !== null && $item['to'] !== null)
            ->values();

        $errors = [];
        $activeFrom = [];

        foreach ($items as $item) {
            if ($item['from'] === $item['to']) {
                $errors["seo.technical.redirects.items.{$item['index']}.fromPath"] = 'Redirect source and destination cannot be the same.';
            }

            if (array_key_exists($item['from'], $activeFrom)) {
                $errors["seo.technical.redirects.items.{$item['index']}.fromPath"] = 'Duplicate active redirect source path is not allowed.';
            } else {
                $activeFrom[$item['from']] = $item['to'];
            }
        }

        foreach ($activeFrom as $from => $to) {
            if (isset($activeFrom[$to]) && $activeFrom[$to] === $from) {
                $errors['seo.technical.redirects.items'] = 'Two-way redirect loops are not allowed.';
                break;
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function normalizePath(mixed $value): ?string
    {
        $path = trim((string) ($value ?? ''));
        if ($path === '') {
            return null;
        }
        if (!str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return rtrim(preg_replace('#/+#', '/', $path) ?: $path, '/') ?: '/';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
