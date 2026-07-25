<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class CheckUserActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            return redirect()->route('login')
                ->with('error', 'Your account has been deactivated. Please contact the administrator.');
        }

        // Fallback safety gate: if login flow bypasses plan checks, block dashboard access here.
        if (in_array($user->role, [UserRole::ADMIN, UserRole::CLIENT], true) && $user->tenant_id) {
            $tenant = Tenant::query()
                ->with('subscriptionPlan:id,is_active')
                ->find($user->tenant_id);

            if (!$tenant) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('error', 'No tenant is assigned to this account.');
            }

            $userTrialExpired = $user->trial_ends_at && $user->trial_ends_at->isPast();
            $tenantNeedsRenewal = !$tenant->is_active || $tenant->requiresSubscriptionRenewal();

            if ($user->role === UserRole::ADMIN && ($userTrialExpired || $tenantNeedsRenewal)) {
                $request->session()->put('saas.registration', [
                    'mode' => 'existing_tenant',
                    'existing_user_id' => $user->id,
                    'existing_tenant_id' => $tenant->id,
                    'name' => $tenant->name ?: $user->name,
                    'email' => $user->email,
                    'custom_domain' => $tenant->domain,
                    'phone' => $tenant->phone,
                ]);
                $request->session()->forget([
                    'saas.registration.plan',
                    'saas.registration.checkout_session_id',
                ]);
                $request->session()->flash('error', 'Your plan is missing or expired. Please choose a plan to continue.');

                $url = route('register.plans');

                if ($request->header('X-Inertia')) {
                    return Inertia::location($url);
                }

                return redirect()->to($url);
            }

            if ($user->role === UserRole::CLIENT && ($userTrialExpired || $tenantNeedsRenewal)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $routeName = \App\Core\TenantContext::id() ? 'tenant.login' : 'tenant-login';
                $url = route($routeName);

                if ($request->header('X-Inertia')) {
                    $request->session()->flash('error', 'This tenant subscription has expired. Please contact your administrator.');
                    return Inertia::location($url);
                }

                return redirect()->to($url)
                    ->with('error', 'This tenant subscription has expired. Please contact your administrator.');
            }
        }

        return $next($request);
    }
}
