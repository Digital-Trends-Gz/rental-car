<?php

namespace App\Http\Middleware;

use App\Core\AppBrandingSettings;
use App\Core\CaptchaSettings;
use App\Core\LandingPageSettings;
use App\Core\SocialLoginSettings;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use App\Support\CurrencyCatalog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Middleware;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $appBranding = AppBrandingSettings::load();

        return [
            ...parent::share($request),
            'name' => $appBranding['app_name'] ?? config('app.name'),
            'app_branding' => $appBranding,
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'locale' => app()->getLocale(),
            'direction' => LaravelLocalization::getCurrentLocaleDirection(),
            'available_locales' => function () use ($request) {
                $supported = LaravelLocalization::getSupportedLanguagesKeys();
                $tenant = \App\Core\TenantContext::get();

                if (!$tenant) {
                    return $supported;
                }

                $tenant->loadMissing('siteSetting');
                $enabled = $tenant->siteSetting?->enabled_locales;

                if (!is_array($enabled) || empty($enabled)) {
                    $default = (string) ($tenant->siteSetting?->default_locale ?: config('app.locale', 'en'));

                    return in_array($default, $supported, true) ? [$default] : [$supported[0] ?? 'en'];
                }

                $filtered = array_values(array_intersect($supported, array_map('strval', $enabled)));

                return empty($filtered) ? [$supported[0] ?? 'en'] : $filtered;
            },
            'translations' => function () use ($request) {
                $base = __('site');
                $locale = app()->getLocale();
                $supportedLocales = LaravelLocalization::getSupportedLanguagesKeys();
                $isDashboardRequest = $this->isDashboardRequest($request, $supportedLocales);
                $landingSettings = SiteSetting::query()
                    ->where('key', LandingPageSettings::KEY)
                    ->first()
                    ?->value;
                $landingOverrides = [];

                if (is_array($landingSettings)) {
                    $normalizedLandingSettings = LandingPageSettings::normalize($landingSettings);

                    foreach ($this->landingTranslationLocales($locale, $isDashboardRequest) as $landingLocale) {
                        $localeOverrides = data_get($normalizedLandingSettings, "translations.$landingLocale", []);

                        if (is_array($localeOverrides) && !empty($localeOverrides)) {
                            $landingOverrides = array_replace_recursive(
                                $landingOverrides,
                                $this->expandTranslationOverrides($localeOverrides),
                            );
                        }
                    }
                }

                if (is_array($landingOverrides) && !empty($landingOverrides)) {
                    $base = array_replace_recursive($base, $landingOverrides);
                }

                $tenant = \App\Core\TenantContext::get();

                if (!$tenant) {
                    return $base;
                }

                $tenant->loadMissing('siteSetting');
                $overrides = data_get($tenant->siteSetting?->translations, $locale);

                if (!is_array($overrides) || empty($overrides)) {
                    return $base;
                }

                $overrides = $this->expandTranslationOverrides($overrides);

                if ($isDashboardRequest && !empty($landingOverrides)) {
                    return array_replace_recursive($base, $overrides, $landingOverrides);
                }

                return array_replace_recursive($base, $overrides);
            },
            'auth' => [
                'user' => $request->user()?->load('roles.permissions'),
                'permissions' => $request->user()?->allPermissions()->pluck('name') ?? [],
                'notifications_unread_count' => $request->user()?->unreadNotifications()->count() ?? 0,
                'notifications' => function () use ($request) {
                    $user = $request->user();
                    if (!$user) {
                        return [];
                    }

                    return $user->notifications()
                        ->latest()
                        ->limit(10)
                        ->get()
                        ->map(function ($notification) {
                            $data = is_array($notification->data) ? $notification->data : [];

                            return [
                                'id' => (string) $notification->id,
                                'kind' => (string) ($data['kind'] ?? 'generic'),
                                'title' => (string) ($data['title'] ?? 'Notification'),
                                'message' => (string) ($data['message'] ?? ''),
                                'url' => (string) ($data['url'] ?? ''),
                                'read_at' => optional($notification->read_at)?->toDateTimeString(),
                                'created_at' => optional($notification->created_at)?->toDateTimeString(),
                            ];
                        })
                        ->values()
                        ->all();
                },
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'csrf_token' => csrf_token(),
            'fileUploadConfig' => [
                'locale' => config('vilt-filepond.locale'),
                'chunkSize' => config('vilt-filepond.chunk_size'),
            ],
            'social_login' => function () {
                try {
                    return [
                        'google' => ['enabled' => SocialLoginSettings::providerIsReady('google')],
                        'apple' => ['enabled' => SocialLoginSettings::providerIsReady('apple')],
                    ];
                } catch (\Throwable) {
                    return [
                        'google' => ['enabled' => false],
                        'apple' => ['enabled' => false],
                    ];
                }
            },
            'captcha' => function () {
                try {
                    return CaptchaSettings::publicConfig();
                } catch (\Throwable) {
                    return [
                        'enabled' => false,
                        'provider' => 'turnstile',
                        'site_key' => '',
                        'forms' => [
                            'login' => false,
                            'register' => false,
                        ],
                    ];
                }
            },
            'currency' => function () {
                $tenant = \App\Core\TenantContext::get();

                if ($tenant) {
                    return CurrencyCatalog::forTenant($tenant);
                }

                return CurrencyCatalog::find(config('app.currency_code', 'USD'));
            },
            'app_url_base' => parse_url(config('app.url'), PHP_URL_HOST),
            'current_tenant' => function () use ($request) {
                $tenant = $this->resolveTenantForRequest($request);

                if (!$tenant) {
                    return null;
                }

                $tenant->load([
                    'subscriptionPlan' => fn ($query) => $query->select(
                        'id',
                        'name',
                        'is_active',
                        'feature_flags',
                        'max_employees',
                        'max_branches',
                        'max_cars',
                        'max_reservations_per_month',
                        'max_contracts',
                        'openai_requests_per_day',
                    ),
                ]);

                return $tenant;
            },
            'tenant_site_settings' => function () use ($request) {
                $tenant = $this->resolveTenantForRequest($request);

                if (!$tenant) {
                    return null;
                }

                $tenant->loadMissing('siteSetting.files');

                return TenantSiteSetting::forTenant($tenant);
            },
            'flash' => [
                'restricted_action' => $request->session()->get('restricted_action'),
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * Dashboard locale is separate from tenant website locale, even when the
     * route still contains a localized URL prefix such as /ar/admin.
     */
    private function isDashboardRequest(Request $request, array $supportedLocales): bool
    {
        $segments = $request->segments();

        if (isset($segments[0]) && in_array($segments[0], $supportedLocales, true)) {
            array_shift($segments);
        }

        return in_array($segments[0] ?? null, ['admin', 'superadmin', 'client', 'dashboard'], true);
    }

    /**
     * Some existing dashboard URLs use /ur while the editable dashboard strings
     * are maintained in the Arabic Landing Translation column. Keep /ur-specific
     * values authoritative when they exist, but allow Arabic landing overrides
     * to fill missing dashboard strings.
     *
     * @return array<int, string>
     */
    private function landingTranslationLocales(string $locale, bool $isDashboardRequest): array
    {
        if ($isDashboardRequest && $locale === 'ur') {
            return ['ar', 'ur'];
        }

        return [$locale];
    }

    /**
     * Older/custom translation payloads can contain flat dot keys. Inertia's
     * frontend translator expects nested arrays, so normalize both shapes.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function expandTranslationOverrides(array $overrides): array
    {
        $expanded = [];

        foreach ($overrides as $key => $value) {
            if (is_string($key) && str_contains($key, '.')) {
                Arr::set($expanded, $key, is_array($value) ? $this->expandTranslationOverrides($value) : $value);
                continue;
            }

            $expanded[$key] = is_array($value)
                ? $this->expandTranslationOverrides($value)
                : $value;
        }

        return $expanded;
    }

    private function resolveTenantForRequest(Request $request): ?Tenant
    {
        $tenant = \App\Core\TenantContext::get();

        if ($tenant) {
            return $tenant;
        }

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        if ($tenantId <= 0) {
            return null;
        }

        return Tenant::query()->whereKey($tenantId)->first();
    }
}
