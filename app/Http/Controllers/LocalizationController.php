<?php

namespace App\Http\Controllers;

use App\Core\TenantContext;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class LocalizationController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $supported = LaravelLocalization::getSupportedLanguagesKeys();
        $enabled = $this->enabledLocales($supported);

        if (!in_array($locale, $enabled, true)) {
            abort(404);
        }

        $redirect = (string) $request->query('redirect', '');
        $isDashboardRedirect = $redirect !== '' && $this->isDashboardPath($redirect, $supported);

        if ($isDashboardRedirect) {
            $request->session()->put('dashboard_locale', $locale);
            $request->session()->put('locale', $locale);
        } else {
            $request->session()->put('site_locale', $locale);
            $request->session()->put('locale', $locale);
        }

        LaravelLocalization::setLocale($locale);

        if ($redirect !== '' && str_starts_with($redirect, '/')) {
            $localizedPath = $this->localizedRedirectPath($redirect, $locale, $supported);
            $tenantUrl = $this->tenantRedirectUrl($request, $localizedPath);

            if ($tenantUrl !== null) {
                return redirect()->away($tenantUrl);
            }

            return redirect()->to($localizedPath);
        }

        return redirect()->back();
    }

    private function localizedRedirectPath(string $redirect, string $targetLocale, array $supportedLocales): string
    {
        $parts = parse_url($redirect);
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '';

        $escapedLocales = array_map(
            static fn (string $locale): string => preg_quote($locale, '#'),
            $supportedLocales,
        );

        $normalizedPath = preg_replace(
            '#^/('.implode('|', $escapedLocales).')(?=/|$)#',
            '',
            $path,
            1,
        ) ?: $path;

        $normalizedPath = '/'.ltrim($normalizedPath, '/');

        $defaultLocale = config('app.locale', 'en');
        $shouldHideLocale = (bool) config('laravellocalization.hideDefaultLocaleInURL', false);

        if (!($shouldHideLocale && $targetLocale === $defaultLocale)) {
            $normalizedPath = '/'.$targetLocale.($normalizedPath === '/' ? '' : $normalizedPath);
        }

        return $normalizedPath.$query.$fragment;
    }

    private function isDashboardPath(string $path, array $supportedLocales): bool
    {
        $parts = parse_url($path);
        $path = trim((string) ($parts['path'] ?? ''), '/');

        if ($path === '') {
            return false;
        }

        $segments = explode('/', $path);

        if (isset($segments[0]) && in_array($segments[0], $supportedLocales, true)) {
            array_shift($segments);
        }

        return in_array($segments[0] ?? null, ['admin', 'superadmin', 'client', 'dashboard'], true);
    }

    private function tenantRedirectUrl(Request $request, string $localizedPath): ?string
    {
        $baseHost = (string) parse_url(config('app.url'), PHP_URL_HOST);
        if ($baseHost === '') {
            return null;
        }

        $refererHost = $this->normalizedRefererHost($request);
        $currentHost = strtolower($request->getHost());
        $baseHost = strtolower($baseHost);
        $scheme = $request->isSecure() ? 'https' : 'http';

        if (
            $currentHost !== $baseHost
            && $currentHost !== 'www.'.$baseHost
            && str_ends_with($currentHost, '.'.$baseHost)
        ) {
            return $scheme.'://'.$currentHost.$localizedPath;
        }

        if (
            $refererHost !== null
            && $refererHost !== $baseHost
            && $refererHost !== 'www.'.$baseHost
            && str_ends_with($refererHost, '.'.$baseHost)
        ) {
            return $scheme.'://'.$refererHost.$localizedPath;
        }

        $tenantSlug = TenantContext::get()?->slug;
        if (is_string($tenantSlug) && $tenantSlug !== '') {
            return $scheme.'://'.$tenantSlug.'.'.$baseHost.$localizedPath;
        }

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);
        if ($tenantId <= 0) {
            return null;
        }

        $tenantSlug = Tenant::query()->whereKey($tenantId)->value('slug');
        if (! is_string($tenantSlug) || $tenantSlug === '') {
            return null;
        }

        return $scheme.'://'.$tenantSlug.'.'.$baseHost.$localizedPath;
    }

    private function normalizedRefererHost(Request $request): ?string
    {
        $referer = (string) $request->headers->get('referer', '');
        if ($referer === '') {
            return null;
        }

        $host = parse_url($referer, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        return strtolower(rtrim($host, '.'));
    }

    private function enabledLocales(array $supportedLocales): array
    {
        $tenant = TenantContext::get();

        if (! $tenant) {
            return $supportedLocales;
        }

        $tenant->loadMissing('siteSetting');
        $enabled = $tenant->siteSetting?->enabled_locales;

        if (! is_array($enabled) || $enabled === []) {
            $default = (string) ($tenant->siteSetting?->default_locale ?: config('app.locale', 'en'));

            return in_array($default, $supportedLocales, true) ? [$default] : [$supportedLocales[0] ?? 'en'];
        }

        $filtered = array_values(array_intersect($supportedLocales, array_map('strval', $enabled)));

        return $filtered !== [] ? $filtered : [$supportedLocales[0] ?? 'en'];
    }
}
