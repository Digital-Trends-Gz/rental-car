<?php

namespace App\Http\Middleware;

use App\Core\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = LaravelLocalization::getSupportedLanguagesKeys();
        $tenant = TenantContext::get();
        $enabled = $this->tenantEnabledLocales($supported);
        $fallback = $this->tenantDefaultLocale($enabled) ?: config('app.fallback_locale', config('app.locale', 'en'));

        $isDashboard = $this->isDashboardRequest($request, $supported);
        $sessionKey = $isDashboard ? 'dashboard_locale' : 'site_locale';

        $segments = $request->segments();
        $urlLocale = $segments[0] ?? null;

        if ($urlLocale && in_array($urlLocale, $supported, true)) {
            $locale = $urlLocale;
        } else {
            $locale = $request->session()->get(
                $sessionKey,
                $isDashboard ? $fallback : $request->session()->get('locale', config('app.locale', $fallback))
            );
        }

        if ($tenant && $urlLocale && in_array($urlLocale, $supported, true) && !in_array($urlLocale, $enabled, true)) {
            $locale = $fallback;

            if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
                return redirect()->to($this->fallbackLocalizedPath($request, $supported, $fallback));
            }
        }

        if (!in_array($locale, $enabled, true)) {
            $locale = $fallback;
        }

        $request->session()->put($sessionKey, $locale);
        $request->session()->put('locale', $locale);

        app()->setLocale($locale);
        LaravelLocalization::setLocale($locale);

        return $next($request);
    }

    /**
     * Dashboard language must be independent from the public website language.
     *
     * Localized routes look like /ar/admin/..., so strip the optional locale
     * prefix before checking the first real path segment.
     */
    private function isDashboardRequest(Request $request, array $supportedLocales): bool
    {
        $segments = $request->segments();

        if (isset($segments[0]) && in_array($segments[0], $supportedLocales, true)) {
            array_shift($segments);
        }

        return in_array($segments[0] ?? null, ['admin', 'superadmin', 'client', 'dashboard'], true);
    }

    private function tenantEnabledLocales(array $supportedLocales): array
    {
        $tenant = TenantContext::get();

        if (! $tenant) {
            return $supportedLocales;
        }

        $tenant->loadMissing('siteSetting');
        $enabled = $tenant->siteSetting?->enabled_locales;

        if (! is_array($enabled) || $enabled === []) {
            $default = $this->tenantDefaultLocale($supportedLocales) ?: (string) config('app.locale', 'en');

            return in_array($default, $supportedLocales, true) ? [$default] : [$supportedLocales[0] ?? 'en'];
        }

        $filtered = array_values(array_intersect($supportedLocales, array_map('strval', $enabled)));

        return $filtered !== [] ? $filtered : [$supportedLocales[0] ?? 'en'];
    }

    private function tenantDefaultLocale(array $enabledLocales): ?string
    {
        $tenant = TenantContext::get();

        if (! $tenant) {
            return null;
        }

        $tenant->loadMissing('siteSetting');
        $default = (string) ($tenant->siteSetting?->default_locale ?: config('app.locale', 'en'));

        return in_array($default, $enabledLocales, true) ? $default : ($enabledLocales[0] ?? null);
    }

    private function fallbackLocalizedPath(Request $request, array $supportedLocales, string $fallbackLocale): string
    {
        $path = '/'.ltrim($request->path(), '/');
        $escapedLocales = array_map(static fn (string $locale): string => preg_quote($locale, '#'), $supportedLocales);
        $path = preg_replace('#^/('.implode('|', $escapedLocales).')(?=/|$)#', '', $path, 1) ?: '/';
        $path = '/'.ltrim($path, '/');

        $shouldHideLocale = (bool) config('laravellocalization.hideDefaultLocaleInURL', false);
        if (! ($shouldHideLocale && $fallbackLocale === config('app.locale', 'en'))) {
            $path = '/'.$fallbackLocale.($path === '/' ? '' : $path);
        }

        $query = $request->getQueryString();

        return $path.($query ? '?'.$query : '');
    }
}
