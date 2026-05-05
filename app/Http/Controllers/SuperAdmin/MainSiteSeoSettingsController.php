<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\TenantSiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MainSiteSeoSettingsController extends Controller
{
    public const KEY = 'main_site_seo';

    public function edit(): Response
    {
        return Inertia::render('SuperAdmin/Settings/SeoSettings', [
            'settings' => $this->seoSettings(),
            'actions' => [
                'update' => route('superadmin.settings.seo.update'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->validationRules());
        $this->validateRedirectRules($validated);

        SiteSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => $this->buildSeoPayload($validated)]
        );

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
        return [
            'seo.defaults.title_suffix.en' => ['nullable', 'string', 'max:255'],
            'seo.defaults.title_suffix.ar' => ['nullable', 'string', 'max:255'],
            'seo.defaults.default_description.en' => ['nullable', 'string', 'max:500'],
            'seo.defaults.default_description.ar' => ['nullable', 'string', 'max:500'],
            'seo.defaults.og_image' => ['nullable', 'string', 'max:1000'],
            'seo.defaults.robots' => ['nullable', 'string', 'max:255'],

            'seo.pages.home.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.home.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.home.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.home.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.home.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.home.robots' => ['nullable', 'string', 'max:255'],

            'seo.pages.fleet.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.fleet.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.fleet.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.fleet.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.fleet.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.fleet.robots' => ['nullable', 'string', 'max:255'],

            'seo.pages.about.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.about.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.about.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.about.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.about.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.about.robots' => ['nullable', 'string', 'max:255'],

            'seo.pages.contact.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.contact.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.contact.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.contact.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.contact.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.contact.robots' => ['nullable', 'string', 'max:255'],
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
    }

    private function buildSeoPayload(array $validated): array
    {
        return [
            'defaults' => [
                'title_suffix' => [
                    'en' => $this->nullableString(data_get($validated, 'seo.defaults.title_suffix.en')),
                    'ar' => $this->nullableString(data_get($validated, 'seo.defaults.title_suffix.ar')),
                ],
                'default_description' => [
                    'en' => $this->nullableString(data_get($validated, 'seo.defaults.default_description.en')),
                    'ar' => $this->nullableString(data_get($validated, 'seo.defaults.default_description.ar')),
                ],
                'og_image' => $this->nullableString(data_get($validated, 'seo.defaults.og_image')),
                'robots' => $this->nullableString(data_get($validated, 'seo.defaults.robots')) ?: 'index,follow',
            ],
            'pages' => [
                'home' => $this->pagePayload($validated, 'home'),
                'fleet' => $this->pagePayload($validated, 'fleet'),
                'about' => $this->pagePayload($validated, 'about'),
                'contact' => $this->pagePayload($validated, 'contact'),
                'car' => $this->pagePayload($validated, 'car'),
                'booking_checkout' => $this->pagePayload($validated, 'booking_checkout'),
                'booking_confirmation' => $this->pagePayload($validated, 'booking_confirmation'),
            ],
            'technical' => [
                'sitemap' => [
                    'pages' => collect((array) data_get($validated, 'seo.technical.sitemap.pages', []))
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
                    'disallowPaths' => collect((array) data_get($validated, 'seo.technical.robots.disallowPaths', []))
                        ->map(fn ($path) => $this->nullableString($path))
                        ->filter()->values()->all(),
                    'crawlDelay' => (int) data_get($validated, 'seo.technical.robots.crawlDelay', 1),
                    'requestRate' => (int) data_get($validated, 'seo.technical.robots.requestRate', 30),
                    'sitemapUrl' => (string) (data_get($validated, 'seo.technical.robots.sitemapUrl') ?: '/sitemap.xml'),
                ],
                'redirects' => [
                    'items' => collect((array) data_get($validated, 'seo.technical.redirects.items', []))
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

    private function pagePayload(array $validated, string $key): array
    {
        return [
            'title' => [
                'en' => $this->nullableString(data_get($validated, "seo.pages.{$key}.title.en")),
                'ar' => $this->nullableString(data_get($validated, "seo.pages.{$key}.title.ar")),
            ],
            'description' => [
                'en' => $this->nullableString(data_get($validated, "seo.pages.{$key}.description.en")),
                'ar' => $this->nullableString(data_get($validated, "seo.pages.{$key}.description.ar")),
            ],
            'canonical_url' => $this->nullableString(data_get($validated, "seo.pages.{$key}.canonical_url")),
            'robots' => $this->nullableString(data_get($validated, "seo.pages.{$key}.robots")),
        ];
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
