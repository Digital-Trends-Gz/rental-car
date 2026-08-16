<?php

namespace App\Http\Controllers\Auth;

use App\Core\SocialLoginSettings;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

        $tenantSubdomain = trim((string) $request->query('tenant', ''));

        if ($tenantSubdomain) {
            $request->session()->put('social_login_tenant', $tenantSubdomain);
        }

        $redirectResponse = Socialite::driver($provider)
            ->redirectUrl($this->callbackUrl($provider))
            ->stateless()
            ->with([
                'state' => $this->encodeState($provider, $tenantSubdomain),
            ])
            ->redirect();

        Log::info('Social login redirect generated.', [
            'provider' => $provider,
            'tenant' => $tenantSubdomain,
            'callback_url' => $this->callbackUrl($provider),
            'location_has_code_response_type' => str_contains((string) $redirectResponse->headers->get('Location'), 'response_type=code'),
            'location_has_state' => str_contains((string) $redirectResponse->headers->get('Location'), 'state='),
        ]);

        return $redirectResponse;
    }

    /**
     * Obtain the user information from the provider and log them in / redirect to tenant.
     */
    public function callback(Request $request, $provider)
    {
        if (!$this->providerIsEnabled($provider)) {
            abort(404);
        }

        Log::info('Social login callback received.', [
            'provider' => $provider,
            'full_url' => $request->fullUrl(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'query_string' => $_SERVER['QUERY_STRING'] ?? null,
            'query_keys' => array_keys($request->query()),
            'has_code' => $request->filled('code'),
            'has_state' => $request->filled('state'),
            'referer' => $request->headers->get('referer'),
            'user_agent' => $request->userAgent(),
        ]);

        $tenantSubdomain = $this->tenantFromState($request, $provider)
            ?: trim((string) $request->session()->get('social_login_tenant', ''));

        if ($request->filled('error')) {
            Log::warning('Social login provider returned an error.', [
                'provider' => $provider,
                'tenant' => $tenantSubdomain,
                'error' => $request->query('error'),
                'error_description' => $request->query('error_description'),
            ]);

            return $this->redirectToTenantLogin(
                $tenantSubdomain,
                (string) ($request->query('error_description') ?: 'Authentication failed. Please try again.')
            );
        }

        if (! $request->filled('code')) {
            Log::warning('Social login callback missing authorization code.', [
                'provider' => $provider,
                'tenant' => $tenantSubdomain,
                'full_url' => $request->fullUrl(),
                'query_keys' => array_keys($request->query()),
                'has_state' => $request->filled('state'),
            ]);

            return $this->redirectToTenantLogin($tenantSubdomain, 'Google did not return an authorization code. Please try again.');
        }

        try {
            $socialUser = Socialite::driver($provider)
                ->redirectUrl($this->callbackUrl($provider))
                ->stateless()
                ->user();
        } catch (\Exception $e) {
            Log::warning('Social login callback failed.', [
                'provider' => $provider,
                'tenant' => $tenantSubdomain,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

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

    private function encodeState(string $provider, string $tenantSubdomain): string
    {
        return Crypt::encryptString(json_encode([
            'provider' => $provider,
            'tenant' => $tenantSubdomain,
            'issued_at' => now()->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    private function tenantFromState(Request $request, string $provider): ?string
    {
        $state = trim((string) $request->query('state', ''));

        if ($state === '') {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($state), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::warning('Social login state could not be decoded.', [
                'provider' => $provider,
                'exception' => $e::class,
            ]);

            return null;
        }

        if (!is_array($payload) || ($payload['provider'] ?? null) !== $provider) {
            return null;
        }

        $issuedAt = (int) ($payload['issued_at'] ?? 0);
        if ($issuedAt < now()->subMinutes(10)->timestamp) {
            return null;
        }

        $tenant = trim((string) ($payload['tenant'] ?? ''));

        return $tenant !== '' ? $tenant : null;
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
