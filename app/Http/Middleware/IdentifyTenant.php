<?php

namespace App\Http\Middleware;

use App\Core\TenantContext;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $this->normalizeHost($request->getHost());
        $baseHost = $this->normalizeHost((string) parse_url(config('app.url'), PHP_URL_HOST));

        // If explicitly visiting the base host, it's the main domain (Landing Page / Super Admin)
        if ($host === $baseHost || $host === 'www.'.$baseHost) {
            TenantContext::clear();
            URL::defaults([]);
            return $next($request);
        }

        // Check if it's a subdomain of the base host
        if ($baseHost !== '' && str_ends_with($host, '.'.$baseHost)) {
            $subdomain = str_replace('.' . $baseHost, '', $host);
            
            // Allow for multi-level subdomains by taking the first part as the slug
            $slug = explode('.', $subdomain)[0];

            $tenant = Tenant::where('slug', $slug)->first();

            if ($tenant) {
                TenantContext::set($tenant);
                URL::defaults(['subdomain' => $tenant->slug]);

                $tenantSettings = TenantSiteSetting::forTenant($tenant);
                $defaultLocale = (string) data_get($tenantSettings, 'default_locale', config('app.locale', 'en'));

                config(['app.locale' => $defaultLocale]);
                app()->setLocale($defaultLocale);
                LaravelLocalization::setLocale($defaultLocale);
                
                // CRITICAL: Forget the subdomain parameter so it's not passed to controllers
                if ($request->route()) {
                    $request->route()->forgetParameter('subdomain');
                }
                
                return $next($request);
            }

            // If it's a subdomain pattern but no tenant matched
            abort(404, 'Tenant not found.');
        }

        // Custom domain support (tenants.domain), normalized without protocol/www/port.
        $customDomain = $this->normalizeHost($host);
        if (str_starts_with($customDomain, 'www.')) {
            $customDomain = substr($customDomain, 4);
        }

        $tenant = Tenant::where('domain', $customDomain)->first();

        if ($tenant) {
            TenantContext::set($tenant);
            URL::defaults(['subdomain' => $tenant->slug]);

            $tenantSettings = TenantSiteSetting::forTenant($tenant);
            $defaultLocale = (string) data_get($tenantSettings, 'default_locale', config('app.locale', 'en'));

            config(['app.locale' => $defaultLocale]);
            app()->setLocale($defaultLocale);
            LaravelLocalization::setLocale($defaultLocale);

            return $next($request);
        }

        // Default to main domain (clears tenant context)
        TenantContext::clear();
        URL::defaults([]);
        return $next($request);
    }

    private function normalizeHost(string $host): string
    {
        $normalized = strtolower(trim($host));
        $normalized = preg_replace('#^https?://#', '', $normalized) ?? $normalized;
        $normalized = explode('/', $normalized)[0] ?? $normalized;
        $normalized = rtrim($normalized, '.');

        return $normalized;
    }
}
