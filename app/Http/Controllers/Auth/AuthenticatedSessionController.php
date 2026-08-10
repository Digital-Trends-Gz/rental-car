<?php

namespace App\Http\Controllers\Auth;

use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use App\Enums\UserRole;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class AuthenticatedSessionController extends Controller
{
    private const REGISTRATION_SESSION_KEY = 'saas.registration';
    private const PLAN_SELECTION_SESSION_KEY = 'saas.registration.plan';
    private const CHECKOUT_SESSION_KEY = 'saas.registration.checkout_session_id';

    /**
     * Show the login page.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if (!TenantContext::get()) {
            return redirect()->route('tenant-login');
        }

        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Show the tenant login page.
     */
    public function tenantLogin(Request $request): Response|RedirectResponse
    {
        if (TenantContext::get()) {
            return redirect()->route('tenant.login');
        }

        return Inertia::render('auth/TenantLogin', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Show the admin login page.
     */
    public function adminLogin(Request $request): Response
    {
        return Inertia::render('auth/AdminLogin', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|BaseResponse
    {
        $user = $request->validateCredentials();
        $tenant = $this->resolveTenant($user);
        $tenantSlug = $tenant?->slug;

        // Allow tenant admins and clients to login through tenant login.
        if (!in_array($user->role, [UserRole::ADMIN, UserRole::CLIENT], true)) {
            Auth::logout();
            return back()->withErrors([
                'email' => 'You are not authorized to access this area.',
            ])->onlyInput('email');
        }

        if ($this->userTrialExpired($user)) {
            if ($user->role === UserRole::ADMIN && $tenant) {
                $this->seedExistingTenantRegistrationSession($request, $user, $tenant);
                return $this->redirectToPlanSelection($request, $tenantSlug);
            }

            return back()->withErrors([
                'email' => 'Your trial period has ended. Please contact your administrator.',
            ])->onlyInput('email');
        }

        if ($user->role === UserRole::ADMIN && $tenant && !$tenant->is_active) {
            return back()->withErrors([
                'email' => 'auth.tenant_account_inactive',
            ])->onlyInput('email');
        }

        if ($tenant && $this->tenantRequiresRenewal($tenant) && $user->role === UserRole::CLIENT) {
            return back()->withErrors([
                'email' => 'This tenant subscription has expired. Please contact your administrator.',
            ])->onlyInput('email');
        }

        if ($user->role === UserRole::ADMIN && $tenant && $this->tenantRequiresRenewal($tenant)) {
            $this->seedExistingTenantRegistrationSession($request, $user, $tenant);

            return $this->redirectToPlanSelection($request, $tenantSlug);
        }

        $this->ensureTenantAdminFullAccess($user, $tenant);

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
            ]);

            if ($tenantSlug) {
                return $this->locationOrRedirect(
                    $request,
                    route('tenant.two-factor.login', ['subdomain' => $tenantSlug])
                );
            }

            return to_route('two-factor.login');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if (!$tenantSlug) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'No tenant is assigned to this account.',
            ])->onlyInput('email');
        }

        return $this->locationOrRedirect(
            $request,
            $this->postAuthenticationUrl($user, $tenantSlug)
        );
    }

    public function storeAdminLogin(LoginRequest $request): RedirectResponse|BaseResponse
    {
        $user = $request->validateCredentials();
        $tenant = $this->resolveTenant($user);
        $tenantSlug = $tenant?->slug;

        // Only allow admins to login through this method
        if ($user->role !== UserRole::ADMIN) {
            Auth::logout();
            return back()->withErrors([
                'email' => 'You are not authorized to access the admin area.',
            ])->onlyInput('email');
        }

        if ($this->userTrialExpired($user)) {
            if ($tenant) {
                $this->seedExistingTenantRegistrationSession($request, $user, $tenant);
                return $this->redirectToPlanSelection($request, $tenantSlug);
            }

            return back()->withErrors([
                'email' => 'Your trial period has ended.',
            ])->onlyInput('email');
        }

        if ($tenant && !$tenant->is_active) {
            return back()->withErrors([
                'email' => 'auth.tenant_account_inactive',
            ])->onlyInput('email');
        }

        if ($tenant && $this->tenantRequiresRenewal($tenant)) {
            $this->seedExistingTenantRegistrationSession($request, $user, $tenant);

            return $this->redirectToPlanSelection($request, $tenantSlug);
        }

        $this->ensureTenantAdminFullAccess($user, $tenant);

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
            ]);

            if ($tenantSlug) {
                return $this->locationOrRedirect(
                    $request,
                    route('tenant.two-factor.login', ['subdomain' => $tenantSlug])
                );
            }

            return to_route('two-factor.login');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if (!$tenantSlug) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'No tenant is assigned to this account.',
            ])->onlyInput('email');
        }

        return $this->locationOrRedirect(
            $request,
            $this->postAuthenticationUrl($user, $tenantSlug)
        );
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function resolveTenant($user): ?Tenant
    {
        if (empty($user->tenant_id)) {
            return null;
        }

        return Tenant::query()
            ->select('id', 'name', 'slug', 'domain', 'phone', 'plan_id', 'trial_ends_at', 'is_active')
            ->with('subscriptionPlan:id,name,is_active')
            ->whereKey($user->tenant_id)
            ->first();
    }

    private function tenantRequiresRenewal(Tenant $tenant): bool
    {
        return $tenant->requiresSubscriptionRenewal();
    }

    private function userTrialExpired(object $user): bool
    {
        if (!isset($user->trial_ends_at) || !$user->trial_ends_at) {
            return false;
        }

        return $user->trial_ends_at->isPast();
    }

    private function seedExistingTenantRegistrationSession(Request $request, $user, Tenant $tenant): void
    {
        $request->session()->put(self::REGISTRATION_SESSION_KEY, [
            'mode' => 'existing_tenant',
            'existing_user_id' => $user->id,
            'existing_tenant_id' => $tenant->id,
            'name' => $tenant->name ?: $user->name,
            'email' => $user->email,
            'custom_domain' => $tenant->domain,
            'phone' => $tenant->phone,
        ]);

        $request->session()->forget([
            self::PLAN_SELECTION_SESSION_KEY,
            self::CHECKOUT_SESSION_KEY,
        ]);
    }

    private function redirectToPlanSelection(Request $request, ?string $tenantSlug): RedirectResponse|BaseResponse
    {
        $request->session()->flash('error', 'Your plan is missing or expired. Please choose a plan to continue.');

        return $this->locationOrRedirect($request, route('register.plans'));
    }

    private function locationOrRedirect(Request $request, string $url): RedirectResponse|BaseResponse
    {
        if ($request->header('X-Inertia')) {
            return Inertia::location($url);
        }

        return redirect()->to($url);
    }

    private function postAuthenticationUrl(object $user, ?string $tenantSlug = null): string
    {
        if ($this->requiresTenantEmailVerification($user) && method_exists($user, 'hasVerifiedEmail') && !$user->hasVerifiedEmail()) {
            return $tenantSlug
                ? route('tenant.verification.notice', ['subdomain' => $tenantSlug])
                : route('verification.notice');
        }

        if (($user->role ?? null) === UserRole::ADMIN && $tenantSlug) {
            return route('admin.dashboard', ['subdomain' => $tenantSlug]);
        }

        if (($user->role ?? null) === UserRole::CLIENT && $tenantSlug) {
            return route('client.home', ['subdomain' => $tenantSlug]);
        }

        if (($user->role ?? null) === UserRole::SUPER_ADMIN) {
            return route('superadmin.dashboard');
        }

        return route('dashboard');
    }

    private function requiresTenantEmailVerification(object $user): bool
    {
        return !empty($user->tenant_id)
            && in_array($user->role ?? null, [UserRole::ADMIN, UserRole::CLIENT], true);
    }

    private function ensureTenantAdminFullAccess(object $user, ?Tenant $tenant = null): void
    {
        if (($user->role ?? null) !== UserRole::ADMIN || empty($user->tenant_id)) {
            return;
        }

        if (!$tenant) {
            $tenant = $this->resolveTenant($user);
        }

        if (!$tenant || strcasecmp((string) $tenant->email, (string) $user->email) !== 0) {
            return;
        }

        $tenantId = (int) $user->tenant_id;

        $role = Role::withoutGlobalScope('tenant')->firstOrCreate(
            [
                'name' => 'tenant-owner',
                'tenant_id' => $tenantId,
            ],
            [
                'display_name' => 'Tenant Owner',
                'description' => 'Default full-access role for the tenant account owner.',
            ]
        );

        $permissionIds = Permission::withoutGlobalScope('tenant')
            ->where('name', 'like', 'tenant-%')
            ->where(function ($query) use ($tenantId) {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', $tenantId);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $role->permissions()->sync($permissionIds);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
