<?php

namespace App\Http\Middleware;

use App\Core\TenantContext;
use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyTenantSeoRedirects
{
    private const MAIN_SITE_SEO_KEY = 'main_site_seo';

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = TenantContext::get();
        $redirects = [];

        if ($tenant) {
            $settings = $tenant->siteSetting;
            $redirects = (array) data_get($settings?->seo, 'technical.redirects.items', []);
        } else {
            $stored = SiteSetting::query()
                ->where('key', self::MAIN_SITE_SEO_KEY)
                ->value('value');
            $redirects = (array) data_get(is_array($stored) ? $stored : [], 'technical.redirects.items', []);
        }

        if (empty($redirects)) {
            return $next($request);
        }

        $currentPath = '/'.ltrim($request->getPathInfo(), '/');

        foreach ($redirects as $redirect) {
            if (!is_array($redirect)) {
                continue;
            }

            if (!(bool) data_get($redirect, 'isActive', true)) {
                continue;
            }

            $fromPath = trim((string) data_get($redirect, 'fromPath', ''));
            $toPath = trim((string) data_get($redirect, 'toPath', ''));

            if ($fromPath === '' || $toPath === '') {
                continue;
            }

            if (!str_starts_with($fromPath, '/')) {
                $fromPath = '/'.$fromPath;
            }

            if (!str_starts_with($toPath, '/')) {
                $toPath = '/'.$toPath;
            }

            if (rtrim($currentPath, '/') !== rtrim($fromPath, '/')) {
                continue;
            }

            $statusCode = (int) data_get($redirect, 'statusCode', 301);
            if (!in_array($statusCode, [301, 302, 307, 308], true)) {
                $statusCode = 301;
            }

            $queryString = $request->getQueryString();
            $target = $toPath.($queryString ? '?'.$queryString : '');

            return redirect($target, $statusCode);
        }

        return $next($request);
    }
}
