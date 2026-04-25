<?php

namespace App\Http\Middleware;

use App\Core\TenantContext;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = TenantContext::get();

        if (!$tenant instanceof Tenant) {
            return $next($request);
        }

        $tenant->load([
            'subscriptionPlan' => fn ($query) => $query->select('id', 'name', 'is_active', 'feature_flags'),
        ]);

        if ($tenant->supportsFeature($feature)) {
            return $next($request);
        }

        abort(403, 'This feature is not included in your current plan.');
    }
}
