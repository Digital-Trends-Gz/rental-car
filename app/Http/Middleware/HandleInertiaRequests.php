<?php

namespace App\Http\Middleware;

use App\Core\AppBrandingSettings;
use App\Models\TenantSiteSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
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
            'available_locales' => function () {
                $supported = LaravelLocalization::getSupportedLanguagesKeys();
                $tenant = \App\Core\TenantContext::get();

                if (!$tenant) {
                    return $supported;
                }

                $tenant->loadMissing('siteSetting');
                $enabled = $tenant->siteSetting?->enabled_locales;

                if (!is_array($enabled) || empty($enabled)) {
                    return $supported;
                }

                $filtered = array_values(array_intersect($supported, array_map('strval', $enabled)));

                return empty($filtered) ? $supported : $filtered;
            },
            'translations' => function () {
                $base = __('site');
                $tenant = \App\Core\TenantContext::get();

                if (!$tenant) {
                    return $base;
                }

                $tenant->loadMissing('siteSetting');
                $locale = app()->getLocale();
                $overrides = data_get($tenant->siteSetting?->translations, $locale);

                if (!is_array($overrides) || empty($overrides)) {
                    return $base;
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
            'currency' =>[
                'symbol' => config('app.currency_symbol'),
                'code' => config('app.currency_code'),
            ],
            'app_url_base' => parse_url(config('app.url'), PHP_URL_HOST),
            'current_tenant' => function () {
                $tenant = \App\Core\TenantContext::get();

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
                        'max_contracts',
                        'openai_requests_per_day',
                    ),
                ]);

                return $tenant;
            },
            'tenant_site_settings' => function () {
                $tenant = \App\Core\TenantContext::get();

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
}
