<?php

namespace App\Http\Middleware;

use App\Core\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscriptionIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $tenant = TenantContext::get();
        if (!$tenant) {
            return $next($request);
        }

        if (!$tenant->is_active) {
            return $this->redirectToMainLogin($request, 'This tenant account is inactive. Please contact support.');
        }

        $tenant->loadMissing('subscriptionPlan:id,is_active');

        if (!$tenant->plan_id || !$tenant->trial_ends_at || $tenant->trial_ends_at->isPast() || !$tenant->subscriptionPlan?->is_active) {
            return $this->redirectToMainLogin($request, 'Your plan has expired. Please login and renew your subscription.');
        }

        return $next($request);
    }

    private function redirectToMainLogin(Request $request, string $message): Response
    {
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $request->session()->flash('error', $message);

        $routeName = \App\Core\TenantContext::id() ? 'tenant.login' : 'tenant-login';
        $url = route($routeName);

        if ($request->header('X-Inertia')) {
            return Inertia::location($url);
        }

        return redirect()->to($url);
    }
}
