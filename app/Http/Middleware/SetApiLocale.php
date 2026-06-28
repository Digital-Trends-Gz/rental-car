<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locales = array_values(array_filter(
            (array) config('app.available_locales', ['en']),
            static fn ($locale): bool => is_string($locale) && $locale !== ''
        ));

        $fallback = (string) config('app.fallback_locale', config('app.locale', 'en'));
        $locale = $request->getPreferredLanguage($locales) ?: $fallback;

        if (! in_array($locale, $locales, true)) {
            $locale = $fallback;
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
