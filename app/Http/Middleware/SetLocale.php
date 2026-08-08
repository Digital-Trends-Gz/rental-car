<?php

namespace App\Http\Middleware;

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
        $fallback = config('app.fallback_locale', config('app.locale', 'en'));

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

        if (!in_array($locale, $supported, true)) {
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
}
