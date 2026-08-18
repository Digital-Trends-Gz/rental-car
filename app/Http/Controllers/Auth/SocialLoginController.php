<?php

namespace App\Http\Controllers\Auth;

use App\Core\LandingPageSettings;
use App\Core\SocialLoginSettings;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
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
        $locale = trim((string) ($request->query('locale') ?: app()->getLocale()));

        if ($tenantSubdomain) {
            $request->session()->put('social_login_tenant', $tenantSubdomain);
        }
        if ($locale) {
            $request->session()->put('social_login_locale', $locale);
        }

        $redirectResponse = Socialite::driver($provider)
            ->redirectUrl($this->callbackUrl($provider))
            ->stateless()
            ->with([
                'state' => $this->encodeState($provider, $tenantSubdomain, $locale),
            ])
            ->redirect();

        Log::info('Social login redirect generated.', [
            'provider' => $provider,
            'tenant' => $tenantSubdomain,
            'locale' => $locale,
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

        $this->hydrateQueryStringFromRequestUri($request);

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

        $statePayload = $this->statePayload($request, $provider);
        $tenantSubdomain = trim((string) ($statePayload['tenant'] ?? ''))
            ?: trim((string) $request->session()->get('social_login_tenant', ''));
        $locale = trim((string) ($statePayload['locale'] ?? ''))
            ?: trim((string) $request->session()->get('social_login_locale', ''));

        if ($locale !== '') {
            app()->setLocale($locale);
        }

        if ($request->filled('error')) {
            Log::warning('Social login provider returned an error.', [
                'provider' => $provider,
                'tenant' => $tenantSubdomain,
                'error' => $request->query('error'),
                'error_description' => $request->query('error_description'),
            ]);

            return $this->redirectToTenantLogin(
                $tenantSubdomain,
                'auth.social_login_failed'
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

            return $this->redirectToTenantLogin($tenantSubdomain, 'auth.social_login_failed');
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

            return $this->redirectToTenantLogin($tenantSubdomain, 'auth.social_login_failed');
        }

        $request->session()->forget('social_login_tenant');
        $request->session()->forget('social_login_locale');

        if (!$tenantSubdomain) {
            return $this->redirectToTenantLogin('', 'auth.unauthorized_access');
        }

        $tenant = Tenant::where('slug', $tenantSubdomain)->first();

        if (!$tenant || !$tenant->is_active) {
            return $this->redirectToTenantLogin($tenantSubdomain, 'auth.tenant_account_inactive');
        }

        $email = strtolower(trim((string) $socialUser->getEmail()));
        if ($email === '') {
            return $this->redirectToTenantLogin($tenantSubdomain, 'auth.social_email_missing');
        }

        // Find or create the client user within this specific tenant
        $user = User::where('email', $email)
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($user) {
            if (!$user->is_active) {
                return $this->redirectToTenantLogin($tenantSubdomain, 'auth.tenant_account_inactive');
            }

            // Update existing user with provider details if they logged in with password before
            if (!$user->provider_id) {
                $user->update([
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                ]);
            }
        } else {
            // Create the new client user within this tenant
            try {
                $user = User::create([
                    'name' => $socialUser->getName() ?: ($socialUser->getNickname() ?: 'Social User'),
                    'email' => $email,
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'password' => Hash::make(Str::random(24)),
                    'role' => UserRole::CLIENT,
                    'tenant_id' => $tenant->id,
                    'is_active' => true,
                    'email_verified_at' => now(), // Assume social emails are verified
                ]);
            } catch (UniqueConstraintViolationException | QueryException $e) {
                Log::warning('Social login user creation caught duplicate key exception.', [
                    'provider' => $provider,
                    'tenant' => $tenantSubdomain,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);

                return $this->redirectToTenantLogin(
                    $tenantSubdomain,
                    'auth.social_email_already_exists'
                );
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
            return $this->redirectToTenantLogin($routeSubdomain, 'auth.unauthorized_access');
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

        return $this->redirectToTenantLogin($tenantSubdomain, 'auth.unauthorized_access');
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

    private function encodeState(string $provider, string $tenantSubdomain, string $locale = 'en'): string
    {
        return Crypt::encryptString(json_encode([
            'provider' => $provider,
            'tenant' => $tenantSubdomain,
            'locale' => $locale,
            'issued_at' => now()->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    private function statePayload(Request $request, string $provider): ?array
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

        return $payload;
    }

    private function hydrateQueryStringFromRequestUri(Request $request): void
    {
        if ($request->query->count() > 0) {
            return;
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $queryString = parse_url($requestUri, PHP_URL_QUERY);

        if (!is_string($queryString) || $queryString === '') {
            return;
        }

        parse_str($queryString, $query);

        if (!is_array($query) || $query === []) {
            return;
        }

        $request->query->replace($query);
        $_GET = $query;
        $_SERVER['QUERY_STRING'] = $queryString;
    }

    private function redirectToTenantLogin(string $tenantSubdomain, string $messageKeyOrDefault)
    {
        $message = $this->resolveMessage($messageKeyOrDefault);

        if ($tenantSubdomain !== '') {
            return redirect()
                ->route('tenant.login', ['subdomain' => $tenantSubdomain])
                ->with('error', $message);
        }

        return redirect()
            ->route('tenant-login')
            ->with('error', $message);
    }

    private function resolveMessage(string $keyOrDefault): string
    {
        $locale = app()->getLocale();

        // 1. Check LandingPageSettings override from SiteSetting
        try {
            $landingSettings = SiteSetting::query()
                ->where('key', LandingPageSettings::KEY)
                ->first()
                ?->value;

            if (is_array($landingSettings)) {
                $normalized = LandingPageSettings::normalize($landingSettings);
                $override = data_get($normalized, "translations.{$locale}.{$keyOrDefault}");
                if (is_string($override) && trim($override) !== '') {
                    return $override;
                }
            }
        } catch (\Throwable) {
        }

        // 2. Check Laravel lang dictionaries
        $siteKey = str_starts_with($keyOrDefault, 'site.') ? $keyOrDefault : 'site.'.$keyOrDefault;
        $translated = __($siteKey);
        if ($translated !== $siteKey && is_string($translated)) {
            return $translated;
        }

        $translatedDirect = __($keyOrDefault);
        if ($translatedDirect !== $keyOrDefault && is_string($translatedDirect)) {
            return $translatedDirect;
        }

        return $keyOrDefault;
    }
}
