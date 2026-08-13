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

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Obtain the user information from the provider and log them in / redirect to tenant.
     */
    public function callback(Request $request, $provider)
    {
        if (!$this->providerIsEnabled($provider)) {
            abort(404);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect('/login')->with('error', 'Authentication failed. Please try again.');
        }

        $tenantSubdomain = $request->session()->get('social_login_tenant');
        $request->session()->forget('social_login_tenant');

        if (!$tenantSubdomain) {
            return redirect('/login')->with('error', 'Tenant context missing. Please try logging in from the tenant\'s website.');
        }

        $tenant = Tenant::where('slug', $tenantSubdomain)->first();

        if (!$tenant || !$tenant->is_active) {
            return redirect('/login')->with('error', 'Invalid or inactive tenant.');
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
        $user = User::find($userId);

        if (!$user || $user->role !== UserRole::CLIENT) {
            return redirect()->route('login')->with('error', 'Invalid login attempt.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('client.home', ['subdomain' => $user->tenant->slug ?? $request->route('subdomain')]);
    }

    private function providerIsEnabled(string $provider): bool
    {
        if (!in_array($provider, ['google', 'apple'], true)) {
            return false;
        }

        $settings = SocialLoginSettings::load();

        return (bool) data_get($settings, "{$provider}.enabled");
    }
}
