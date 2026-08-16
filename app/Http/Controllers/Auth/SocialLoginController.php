<?php

namespace App\Http\Controllers\Auth;

use App\Core\SocialLoginSettings;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialLoginController extends Controller
{
    /**
     * Redirect the user to the provider authentication page.
     */
    public function redirect(Request $request, $provider)
    {
        if (!$this->providerIsEnabled($provider)) {
            abort(404);
        }

        $tenantSubdomain = $request->query('tenant');
        
        if ($tenantSubdomain) {
            $request->session()->put('social_login_tenant', $tenantSubdomain);
        }

        return Socialite::driver($provider)
            ->redirectUrl($this->callbackUrl($provider))
            ->redirect();
    }

    /**
     * Obtain the user information from the provider and log them in / redirect to tenant.
     */
    public function callback(Request $request, $provider)
    {
        if (!$this->providerIsEnabled($provider)) {
            abort(404);
        }

        $tenantSubdomain = trim((string) $request->session()->get('social_login_tenant', ''));

        try {
            $socialUser = Socialite::driver($provider)
                ->redirectUrl($this->callbackUrl($provider))
                ->user();
        } catch (\Exception $e) {
            return $this->redirectToTenantLogin($tenantSubdomain, 'Authentication failed. Please try again.');
        }

        $request->session()->forget('social_login_tenant');

        if (!$tenantSubdomain) {
            return $this->redirectToTenantLogin('', 'Tenant context missing. Please try logging in from the tenant\'s website.');
        }

        $tenant = Tenant::where('slug', $tenantSubdomain)->first();

        if (!$tenant || !$tenant->is_active) {
            return $this->redirectToTenantLogin($tenantSubdomain, 'Invalid or inactive tenant.');
        }

        // Find or create the client user within the tenant
        $user = User::where('email', $socialUser->getEmail())
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$user) {
            $user = User::create([
                'name' => $socialUser->getName() ?? 'Social User',
                'email' => $socialUser->getEmail(),
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'password' => Hash::make(Str::random(24)),
                'role' => UserRole::CLIENT,
                'tenant_id' => $tenant->id,
                'is_active' => true,
                'email_verified_at' => now(), // Assume social emails are verified
            ]);
        } else {
            // Update existing user with provider details if they logged in with password before
            if (!$user->provider_id) {
                $user->update([
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                ]);
            }
        }

        // Create a signed URL for logging in securely on the tenant's subdomain
        $url = URL::temporarySignedRoute(
            'tenant.social-login.callback',
            now()->addMinutes(5),
            ['user' => $user->id, 'subdomain' => $tenantSubdomain]
        );

        return redirect()->to($url);
    }

    /**
     * Handle the secure login on the tenant's subdomain.
     */
    public function tenantCallback(Request $request)
    {
        $userId = $request->query('user');
        $routeSubdomain = (string) $request->route('subdomain');
        $user = User::with('tenant')->find($userId);

        if (!$user || !$user->tenant) {
            return $this->redirectToTenantLogin($routeSubdomain, 'Invalid login attempt.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        $tenantSubdomain = $user->tenant->slug ?: $routeSubdomain;

        if ($user->role === UserRole::CLIENT) {
            return redirect()->route('client.home', ['subdomain' => $tenantSubdomain]);
        }

        if ($user->role === UserRole::ADMIN) {
            return redirect()->route('admin.home', ['subdomain' => $tenantSubdomain]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->redirectToTenantLogin($tenantSubdomain, 'Invalid login attempt.');
    }

    private function providerIsEnabled(string $provider): bool
    {
        if (!in_array($provider, ['google', 'apple'], true)) {
            return false;
        }

        return SocialLoginSettings::providerIsReady($provider);
    }

    private function callbackUrl(string $provider): string
    {
        return route('social-login.callback', ['provider' => $provider]);
    }

    private function redirectToTenantLogin(string $tenantSubdomain, string $message)
    {
        if ($tenantSubdomain !== '') {
            return redirect()
                ->route('tenant.login', ['subdomain' => $tenantSubdomain])
                ->with('error', $message);
        }

        return redirect()
            ->route('tenant-login')
            ->with('error', $message);
    }
}
