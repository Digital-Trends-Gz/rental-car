<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ApiPasswordResetNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
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
        $request->validate([
            'email' => [
                'required',
                'email',
                Rule::exists((new User())->getTable(), 'email'),
            ],
            'code' => ['nullable', 'digits:6', 'required_without:otp'],
            'otp' => ['nullable', 'digits:6', 'required_without:code'],
        ], [
            'email.exists' => 'We could not find an account with this email.',
            'code.required_without' => 'The otp field is required.',
            'otp.required_without' => 'The otp field is required.',
            'code.digits' => 'The otp must be exactly 6 digits.',
            'otp.digits' => 'The otp must be exactly 6 digits.',
        ]);

        $email = trim((string) $request->input('email'));
        $code = trim((string) ($request->input('code') ?? $request->input('otp')));

        if ($code === '') {
            return response()->json([
                'message' => 'no same or not match',
                'verified' => false,
            ], 422);
        }

        if (!$this->isValidPasswordResetOtp($email, $code)) {
            return response()->json([
                'message' => 'no same or not match',
                'verified' => false,
            ], 422);
        }

        Cache::put(
            $this->otpVerifiedCacheKey($email),
            true,
            now()->addMinutes(self::OTP_VERIFIED_EXPIRY_MINUTES)
        );
        Cache::forget($this->otpCacheKey($email));

        return response()->json([
            'message' => 'success',
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
        $request->validate([
            'email' => [
                'required',
                'email',
                Rule::exists((new User())->getTable(), 'email'),
            ],
        ], [
            'email.exists' => 'We could not find an account with this email.',
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
            Cache::forget($this->otpVerifiedCacheKey($email));

            Notification::send($user, new ApiPasswordResetNotification($token, $otp));
        }

        return response()->json([
            'message' => 'If the account exists, we sent a password reset link and OTP to the registered email.',
            'test_otp' => $otp,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => [
                'required',
                'email',
                Rule::exists((new User())->getTable(), 'email'),
            ],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ], [
            'email.exists' => 'We could not find an account with this email.',
        ]);

        $email = trim((string) $request->input('email'));
        $user = $this->findUserByEmail($email);

        if (!$user || !Cache::pull($this->otpVerifiedCacheKey($email), false)) {
            throw ValidationException::withMessages([
                'email' => ['Please verify the OTP first.'],
            ]);
        }

        $this->completePasswordReset($user, (string) $request->input('password'));

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }

    private function userPayload(User $user): array
    {
        $tenant = $this->resolveTenant($user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role instanceof UserRole ? $user->role->value : (string) $user->role,
            'tenant_id' => $user->tenant_id,
            'branch_id' => $user->branch_id,
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
            ->select('id', 'name', 'slug', 'domain', 'phone', 'plan_id', 'trial_ends_at', 'is_active')
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
