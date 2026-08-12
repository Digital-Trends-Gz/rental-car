<?php

namespace App\Http\Middleware;

use App\Core\TenantContext;
use App\Models\Tenant;
use App\Services\Plans\PlanUsageLimits;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantPlanLimitNotExceeded
{
    public function __construct(private readonly PlanUsageLimits $planUsageLimits)
    {
    }

    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            abort(403, 'Tenant context is required to check plan limits.');
        }

        $message = match ($resource) {
            'employees' => $this->planUsageLimits->employeeLimitMessage($tenant),
            'branches' => $this->planUsageLimits->branchLimitMessage($tenant),
            'cars' => $this->planUsageLimits->carLimitMessage($tenant),
            'contracts' => $this->planUsageLimits->contractLimitMessage($tenant),
            default => abort(500, "Unsupported plan limit resource [{$resource}]."),
        };

        if (!$message) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        if ($request->isMethod('get')) {
            abort(403, $message);
        }

        return redirect()->back()->with('error', $message);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $tenant = TenantContext::get();

        if ($tenant) {
            return $tenant->loadMissing('subscriptionPlan');
        }

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        if ($tenantId <= 0) {
            $slug = (string) ($request->route('subdomain') ?? '');

            if ($slug === '') {
                $host = strtolower($request->getHost());
                $baseHost = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));

                if ($baseHost !== '' && str_ends_with($host, '.'.$baseHost)) {
                    $slug = explode('.', substr($host, 0, -strlen('.'.$baseHost)))[0] ?? '';
                }
            }

            if ($slug === '') {
                return null;
            }

            return Tenant::query()
                ->where('slug', $slug)
                ->with('subscriptionPlan')
                ->first();
        }

        return Tenant::query()->with('subscriptionPlan')->find($tenantId);
    }
}
