<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ApiPasswordResetNotification;
use App\Support\TenantTranslations;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Laravel\Fortify\Features;

class AuthController extends Controller
{
    private const OTP_EXPIRY_MINUTES = 15;
    private const OTP_VERIFIED_EXPIRY_MINUTES = 10;
    private const OTP_MAX_ATTEMPTS = 5;
    private const OTP_VERIFY_MAX_FAILURES = 3;
    private const OTP_VERIFY_BLOCK_MINUTES = 2;
    private const TOKEN_EXPIRY_DAYS = 30;

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $request->validateCredentials();

        if ($response = $this->apiLoginRestriction($user)) {
            return response()->json($response['body'], $response['status']);
        }

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'message' => 'Two-factor authentication is enabled for this account.',
                'requires_two_factor' => true,
            ], 409);
        }

        $deviceName = trim((string) $request->input('device_name', 'mobile'));
        $deviceName = $deviceName !== '' ? $deviceName : 'mobile';

        $sanctumToken = $user->createToken($deviceName, ['*'], now()->addDays(self::TOKEN_EXPIRY_DAYS));

        return response()->json([
            'message' => 'Login successful.',
            'token_type' => 'Bearer',
            'access_token' => $sanctumToken->plainTextToken,
            'expires_at' => optional($sanctumToken->accessToken->expires_at)->toIso8601String(),
            'user' => $this->userPayload($user),
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $this->setApiLocale($request);

        $request->validate([
            'email' => [
                'required',
                'email',
                Rule::exists((new User())->getTable(), 'email'),
            ],
            'code' => ['nullable', 'digits:6', 'required_without:otp'],
            'otp' => ['nullable', 'digits:6', 'required_without:code'],
        ], [
            'email.exists' => $this->authMessage('account_not_found'),
            'code.required_without' => $this->authMessage('otp_required'),
            'otp.required_without' => $this->authMessage('otp_required'),
            'code.digits' => $this->authMessage('otp_digits'),
            'otp.digits' => $this->authMessage('otp_digits'),
        ]);

        $email = trim((string) $request->input('email'));
        $code = trim((string) ($request->input('code') ?? $request->input('otp')));

        if ($code === '') {
            return response()->json([
                'message' => $this->authMessage('otp_invalid'),
                'verified' => false,
            ], 422);
        }

        if ($this->isOtpVerificationBlocked($email)) {
            return response()->json([
                'message' => $this->authMessage('otp_blocked'),
                'verified' => false,
                'blocked' => true,
            ], 429);
        }

        if (!$this->isValidPasswordResetOtp($email, $code)) {
            $attempts = $this->incrementOtpVerificationFailures($email);

            if ($attempts >= self::OTP_VERIFY_MAX_FAILURES) {
                $this->blockOtpVerification($email);

                return response()->json([
                    'message' => $this->authMessage('otp_blocked'),
                    'verified' => false,
                    'blocked' => true,
                ], 429);
            }

            return response()->json([
                'message' => $this->authMessage('otp_invalid'),
                'verified' => false,
                'attempts_left' => max(0, self::OTP_VERIFY_MAX_FAILURES - $attempts),
            ], 422);
        }

        $this->clearOtpVerificationState($email);
        Cache::put(
            $this->otpVerifiedCacheKey($email),
            true,
            now()->addMinutes(self::OTP_VERIFIED_EXPIRY_MINUTES)
        );
        Cache::forget($this->otpCacheKey($email));

        return response()->json([
            'message' => $this->authMessage('success'),
            'verified' => true,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if (!$token) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $token->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'user' => $this->userPayload($user),
            'token' => $token ? [
                'id' => $token->id,
                'name' => $token->name,
                'expires_at' => optional($token->expires_at)->toIso8601String(),
                'last_used_at' => optional($token->last_used_at)->toIso8601String(),
            ] : null,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $this->setApiLocale($request);

        $request->validate([
            'email' => [
                'required',
                'email',
                Rule::exists((new User())->getTable(), 'email'),
            ],
        ], [
            'email.exists' => $this->authMessage('account_not_found'),
        ]);

        $email = trim((string) $request->input('email'));
        $user = $this->findUserByEmail($email);
        $otp = null;

        if ($user) {
            $token = Password::broker()->createToken($user);
            $otp = (string) random_int(100000, 999999);

            Cache::put(
                $this->otpCacheKey($email),
                [
                    'hash' => Hash::make($otp),
                    'attempts' => 0,
                ],
                now()->addMinutes(self::OTP_EXPIRY_MINUTES)
            );
            $this->clearOtpVerificationState($email);
            Cache::forget($this->otpVerifiedCacheKey($email));

            Notification::send($user, new ApiPasswordResetNotification($token, $otp));
        }

        return response()->json([
            'message' => $this->authMessage('password_reset_sent'),
            'test_otp' => $otp,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $this->setApiLocale($request);

        $request->validate([
            'email' => [
                'required',
                'email',
                Rule::exists((new User())->getTable(), 'email'),
            ],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ], [
            'email.exists' => $this->authMessage('account_not_found'),
        ]);

        $email = trim((string) $request->input('email'));
        $user = $this->findUserByEmail($email);

        if (!$user || !Cache::pull($this->otpVerifiedCacheKey($email), false)) {
            throw ValidationException::withMessages([
                'email' => [$this->authMessage('otp_verify_first')],
            ]);
        }

        $this->completePasswordReset($user, (string) $request->input('password'));

        return response()->json([
            'message' => $this->authMessage('password_reset_success'),
        ]);
    }

    private function setApiLocale(Request $request): void
    {
        $locales = array_values(array_filter(
            (array) config('app.available_locales', ['en']),
            static fn ($locale): bool => is_string($locale) && $locale !== ''
        ));

        $fallback = (string) config('app.fallback_locale', config('app.locale', 'en'));
        $locale = $request->getPreferredLanguage($locales) ?: $fallback;

        app()->setLocale($locale);
    }

    private function authMessage(string $key): string
    {
        $translationKey = "auth.api.{$key}";

        if (Lang::has($translationKey)) {
            $fallback = trans($translationKey);
        } else {
            $fallback = match ($key) {
                'account_not_found' => 'We could not find an account with this email.',
                'otp_required' => 'The otp field is required.',
                'otp_digits' => 'The otp must be exactly 6 digits.',
                'otp_invalid' => 'no same or not match',
                'otp_blocked' => 'OTP verification is blocked for 2 minutes. Please try again later.',
                'success' => 'success',
                'password_reset_sent' => 'If the account exists, we sent a password reset link and OTP to the registered email.',
                'otp_verify_first' => 'Please verify the OTP first.',
                'password_reset_success' => 'Password reset successfully.',
                default => $translationKey,
            };
        }

        return TenantTranslations::get($translationKey, app()->getLocale(), $fallback);
    }

    private function userPayload(User $user): array
    {
        $tenant = $this->resolveTenant($user);
        $branch = $user->branch_id
            ? $user->branch()->withoutGlobalScope('tenant')->select('id', 'name')->first()
            : null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role instanceof UserRole ? $user->role->value : (string) $user->role,
            'account_type' => $this->accountType($user, $tenant),
            'account_type_label' => $this->accountTypeLabel($user, $tenant),
            'tenant_id' => $user->tenant_id,
            'branch_id' => $user->branch_id,
            'branch_name' => $branch?->name,
            'is_active' => (bool) $user->is_active,
            'email_verified_at' => optional($user->email_verified_at)->toIso8601String(),
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'is_active' => (bool) $tenant->is_active,
            ] : null,
        ];
    }

    private function accountType(User $user, ?Tenant $tenant = null): string
    {
        if ($user->role === UserRole::ADMIN) {
            return $this->isCompanyOwner($user, $tenant) ? 'company_owner' : 'employee';
        }

        return $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
    }

    private function accountTypeLabel(User $user, ?Tenant $tenant = null): string
    {
        $accountType = $this->accountType($user, $tenant);

        $fallback = match ($accountType) {
            'company_owner' => 'Company owner',
            'employee' => 'Employee',
            'super_admin' => 'Super Admin',
            'client' => 'Client',
            default => 'User',
        };

        return TenantTranslations::get("auth.api.account_types.{$accountType}", app()->getLocale(), $fallback, $tenant);
    }

    private function isCompanyOwner(User $user, ?Tenant $tenant = null): bool
    {
        if ($user->role !== UserRole::ADMIN || empty($user->tenant_id)) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('tenant-owner')) {
            return true;
        }

        $tenant ??= $this->resolveTenant($user);

        return $tenant && !empty($tenant->email)
            && strcasecmp((string) $tenant->email, (string) $user->email) === 0;
    }

    private function apiLoginRestriction(User $user): ?array
    {
        if (!$user->is_active) {
            return [
                'status' => 403,
                'body' => ['message' => 'This account is inactive.'],
            ];
        }

        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN, UserRole::CLIENT], true)) {
            return [
                'status' => 403,
                'body' => ['message' => 'You are not authorized to access this API.'],
            ];
        }

        if ($this->userTrialExpired($user)) {
            return [
                'status' => 403,
                'body' => ['message' => 'Your trial period has ended. Please contact support.'],
            ];
        }

        $tenant = $this->resolveTenant($user);

        if ($user->role === UserRole::ADMIN && $tenant && !$tenant->is_active) {
            return [
                'status' => 403,
                'body' => ['message' => 'This tenant account is inactive. Please contact support.'],
            ];
        }

        if ($tenant && $this->tenantRequiresRenewal($tenant) && $user->role === UserRole::CLIENT) {
            return [
                'status' => 403,
                'body' => ['message' => 'This tenant subscription has expired. Please contact your administrator.'],
            ];
        }

        if ($user->role === UserRole::ADMIN && $tenant && $this->tenantRequiresRenewal($tenant)) {
            return [
                'status' => 403,
                'body' => ['message' => 'This tenant subscription has expired. Please contact support.'],
            ];
        }

        return null;
    }

    private function resolveTenant(User $user): ?Tenant
    {
        if (empty($user->tenant_id)) {
            return null;
        }

        return Tenant::query()
            ->select('id', 'name', 'email', 'slug', 'domain', 'phone', 'plan_id', 'trial_ends_at', 'is_active')
            ->with('subscriptionPlan:id,name,is_active')
            ->whereKey($user->tenant_id)
            ->first();
    }

    private function tenantRequiresRenewal(Tenant $tenant): bool
    {
        return $tenant->requiresSubscriptionRenewal();
    }

    private function userTrialExpired(User $user): bool
    {
        return !empty($user->trial_ends_at) && $user->trial_ends_at->isPast();
    }

    private function findUserByEmail(string $email): ?User
    {
        $user = Auth::getProvider()->retrieveByCredentials([
            'email' => $email,
        ]);

        return $user instanceof User ? $user : null;
    }

    private function otpCacheKey(string $email): string
    {
        return 'api-password-reset-otp:'.sha1(mb_strtolower(trim($email)));
    }

    private function otpVerifiedCacheKey(string $email): string
    {
        return 'api-password-reset-otp-verified:'.sha1(mb_strtolower(trim($email)));
    }

    private function otpVerificationFailuresKey(string $email): string
    {
        return 'api-password-reset-otp-verify-failures:'.sha1(mb_strtolower(trim($email)));
    }

    private function otpVerificationBlockedKey(string $email): string
    {
        return 'api-password-reset-otp-verify-blocked:'.sha1(mb_strtolower(trim($email)));
    }

    private function incrementOtpVerificationFailures(string $email): int
    {
        $key = $this->otpVerificationFailuresKey($email);
        $attempts = (int) Cache::get($key, 0) + 1;

        Cache::put($key, $attempts, now()->addMinutes(self::OTP_VERIFY_BLOCK_MINUTES));

        return $attempts;
    }

    private function isOtpVerificationBlocked(string $email): bool
    {
        return Cache::has($this->otpVerificationBlockedKey($email));
    }

    private function blockOtpVerification(string $email): void
    {
        Cache::put($this->otpVerificationBlockedKey($email), true, now()->addMinutes(self::OTP_VERIFY_BLOCK_MINUTES));
        Cache::forget($this->otpVerificationFailuresKey($email));
    }

    private function clearOtpVerificationState(string $email): void
    {
        Cache::forget($this->otpVerificationFailuresKey($email));
        Cache::forget($this->otpVerificationBlockedKey($email));
    }

    private function isValidPasswordResetOtp(string $email, string $otp): bool
    {
        $cacheKey = $this->otpCacheKey($email);
        $payload = Cache::get($cacheKey);

        if (!is_array($payload)) {
            return false;
        }

        $attempts = (int) ($payload['attempts'] ?? 0);

        if ($attempts >= self::OTP_MAX_ATTEMPTS) {
            Cache::forget($cacheKey);

            return false;
        }

        $hash = (string) ($payload['hash'] ?? '');

        if ($hash === '' || !Hash::check($otp, $hash)) {
            $payload['attempts'] = $attempts + 1;
            Cache::put($cacheKey, $payload, now()->addMinutes(self::OTP_EXPIRY_MINUTES));

            return false;
        }

        return true;
    }

    private function completePasswordReset(User $user, string $password): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
            'remember_token' => Str::random(60),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Password::broker()->deleteToken($user);
        $user->tokens()->delete();
        Cache::forget($this->otpCacheKey((string) $user->email));
        Cache::forget($this->otpVerifiedCacheKey((string) $user->email));

        event(new PasswordReset($user));
    }
}
