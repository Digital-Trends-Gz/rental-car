<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ApiPasswordResetNotification;
use App\Services\Plans\PlanEntityLocks;
use App\Services\Auth\DeviceAccessService;
use App\Support\ApiAccessMode;
use App\Support\BranchAccess;
use App\Support\TenantAdminAccessSync;
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
        $platform = trim((string) $request->input('platform', ''));
        $deviceAccess = app(DeviceAccessService::class);
        $deviceId = $deviceAccess->resolveApiDeviceId($request);
        $device = $deviceAccess->findOrCreateAllowedDevice(
            $user,
            $request,
            'api',
            $deviceId,
            $deviceName,
            $platform !== '' ? $platform : null
        );

        if (!$device) {
            return response()->json([
                'message' => $deviceAccess->limitReachedMessage(),
                'code' => 'device_limit_reached',
            ], 403);
        }

        $tenant = $this->resolveTenant($user);
        if ($tenant && $user->role === UserRole::ADMIN) {
            app(TenantAdminAccessSync::class)->syncUser($user, $tenant);
        }
        $activeMode = ApiAccessMode::activeMode($user, $tenant);
        $activeBranchId = $activeMode === ApiAccessMode::MODE_EMPLOYEE
            ? ApiAccessMode::effectiveBranchId($user)
            : null;

        $sanctumToken = $user->createToken(
            $deviceName,
            ApiAccessMode::abilitiesFor($activeMode, $activeBranchId),
            now()->addDays(self::TOKEN_EXPIRY_DAYS)
        );
        $sanctumToken->accessToken->forceFill([
            'user_device_id' => $device->id,
        ])->save();

        return response()->json([
            'message' => 'Login successful.',
            'token_type' => 'Bearer',
            'access_token' => $sanctumToken->plainTextToken,
            'expires_at' => optional($sanctumToken->accessToken->expires_at)->toIso8601String(),
            'active_mode' => $activeMode,
            'branch_id' => $activeBranchId,
            'user' => $this->userPayload($user, $activeMode, $activeBranchId),
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

        $device = $token->userDevice;
        if ($device) {
            $device->revoke();
        } else {
            $token->delete();
        }

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

    public function switchMode(Request $request): JsonResponse
    {
        $this->setApiLocale($request);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($response = $this->apiLoginRestriction($user)) {
            return response()->json($response['body'], $response['status']);
        }

        $tenant = $this->resolveTenant($user);
        if ($tenant && $user->role === UserRole::ADMIN) {
            app(TenantAdminAccessSync::class)->syncUser($user, $tenant);
        }

        if (!ApiAccessMode::isOwnerCapable($user, $tenant)) {
            return response()->json([
                'message' => $this->authMessage('switch_mode_forbidden'),
            ], 403);
        }

        $validated = $request->validate([
            'mode' => ['required', 'string', Rule::in([
                ApiAccessMode::MODE_OWNER,
                ApiAccessMode::MODE_EMPLOYEE,
                'company_owner',
            ])],
            'branch_id' => ['nullable', 'integer', 'required_if:mode,'.ApiAccessMode::MODE_EMPLOYEE],
            'device_name' => ['nullable', 'string', 'max:255'],
        ], [
            'branch_id.required_if' => $this->authMessage('switch_mode_branch_required'),
        ]);

        $activeMode = $validated['mode'] === 'company_owner'
            ? ApiAccessMode::MODE_OWNER
            : (string) $validated['mode'];

        $activeBranchId = null;

        if ($activeMode === ApiAccessMode::MODE_EMPLOYEE) {
            $requestedBranchId = (int) $validated['branch_id'];
            $branchAccess = app(BranchAccess::class);
            $activeBranchId = $branchAccess->resolveAccessibleBranchId($user, $requestedBranchId) ?: $requestedBranchId;

            $branch = Branch::query()
                ->withoutGlobalScope('tenant')
                ->whereKey($activeBranchId)
                ->where('tenant_id', (int) $user->tenant_id)
                ->first();

            if (!$branch || ($activeBranchId !== $requestedBranchId) || app(PlanEntityLocks::class)->branchIsLockedByPlan($branch)) {
                throw ValidationException::withMessages([
                    'branch_id' => [$this->authMessage('switch_mode_branch_invalid')],
                ]);
            }
        }

        $currentToken = $user->currentAccessToken();
        $deviceName = trim((string) ($validated['device_name'] ?? $currentToken?->name ?? 'mobile'));
        $deviceName = $deviceName !== '' ? $deviceName : 'mobile';
        $abilities = ApiAccessMode::abilitiesFor($activeMode, $activeBranchId);
        $expiresAt = now()->addDays(self::TOKEN_EXPIRY_DAYS);
        $plainTextToken = $request->bearerToken();

        if ($currentToken && $plainTextToken && method_exists($currentToken, 'forceFill') && method_exists($currentToken, 'save')) {
            $currentToken->forceFill([
                'name' => $deviceName,
                'abilities' => $abilities,
                'expires_at' => $expiresAt,
            ])->save();

            $accessToken = $plainTextToken;
            $tokenExpiresAt = optional($currentToken->fresh()?->expires_at)->toIso8601String();
        } else {
            $sanctumToken = $user->createToken(
                $deviceName,
                $abilities,
                $expiresAt
            );
            if ($currentToken?->user_device_id) {
                $sanctumToken->accessToken->forceFill([
                    'user_device_id' => $currentToken->user_device_id,
                ])->save();
            }

            $accessToken = $sanctumToken->plainTextToken;
            $tokenExpiresAt = optional($sanctumToken->accessToken->expires_at)->toIso8601String();
        }

        return response()->json([
            'message' => $this->authMessage(
                $activeMode === ApiAccessMode::MODE_EMPLOYEE
                    ? 'switch_mode_employee_success'
                    : 'switch_mode_owner_success'
            ),
            'token_type' => 'Bearer',
            'access_token' => $accessToken,
            'expires_at' => $tokenExpiresAt,
            'active_mode' => $activeMode,
            'branch_id' => $activeBranchId,
            'user' => $this->userPayload($user, $activeMode, $activeBranchId),
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

    private function userPayload(User $user, ?string $activeMode = null, ?int $activeBranchId = null): array
    {
        $tenant = $this->resolveTenant($user);
        $baseAccountType = $this->baseAccountType($user, $tenant);
        $isCompanyOwner = $baseAccountType === 'company_owner';
        $mode = ApiAccessMode::activeMode($user, $tenant, $activeMode);
        $accountType = $mode === ApiAccessMode::MODE_EMPLOYEE && $baseAccountType === 'company_owner'
            ? 'employee'
            : $baseAccountType;
        $branchId = $mode === ApiAccessMode::MODE_EMPLOYEE
            ? ApiAccessMode::effectiveBranchId($user, $activeBranchId)
            : null;
        $branch = $branchId
            ? Branch::query()->withoutGlobalScope('tenant')->select('id', 'name')->whereKey($branchId)->first()
            : null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role instanceof UserRole ? $user->role->value : (string) $user->role,
            'account_type' => $accountType,
            'account_type_label' => $this->accountTypeLabelFor($accountType, $tenant),
            'base_account_type' => $baseAccountType,
            'is_company_owner' => $isCompanyOwner,
            'active_mode' => $mode,
            'can_switch_modes' => ApiAccessMode::isOwnerCapable($user, $tenant),
            'available_modes' => $isCompanyOwner
                ? [ApiAccessMode::MODE_OWNER, ApiAccessMode::MODE_EMPLOYEE]
                : [$mode],
            'tenant_id' => $user->tenant_id,
            'branch_id' => $branchId,
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

    private function baseAccountType(User $user, ?Tenant $tenant = null): string
    {
        if ($user->role === UserRole::ADMIN) {
            return $this->isCompanyOwner($user, $tenant) ? 'company_owner' : 'employee';
        }

        return $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
    }

    private function accountTypeLabelFor(string $accountType, ?Tenant $tenant = null): string
    {
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
        return ApiAccessMode::isOwnerCapable($user, $tenant ?? $this->resolveTenant($user));
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
                'body' => ['message' => TenantTranslations::get(
                    'auth.tenant_account_inactive',
                    app()->getLocale(),
                    trans('site.auth.tenant_account_inactive'),
                    $tenant,
                )],
            ];
        }

        if ($user->role === UserRole::ADMIN && $tenant) {
            app(PlanEntityLocks::class)->sync($tenant);
            $user->refresh();

            if ($user->plan_locked_at) {
                return [
                    'status' => 403,
                    'body' => ['message' => $this->tenantDashboardMessage(
                        'employee_locked_by_plan',
                        $tenant
                    )],
                ];
            }

            if ($this->userBranchIsLockedByPlan($user)) {
                return [
                    'status' => 403,
                    'body' => ['message' => $this->tenantDashboardMessage(
                        'branch_locked_by_plan',
                        $tenant
                    )],
                ];
            }
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

    private function userBranchIsLockedByPlan(User $user): bool
    {
        if (!$user->branch_id || $this->canBypassBranchPlanLock($user)) {
            return false;
        }

        $branch = Branch::query()
            ->withoutGlobalScope('tenant')
            ->whereKey($user->branch_id)
            ->first();

        return $branch ? app(PlanEntityLocks::class)->branchIsLockedByPlan($branch) : false;
    }

    private function canBypassBranchPlanLock(User $user): bool
    {
        return ApiAccessMode::hasTenantRole($user, ['tenant-owner', 'tenant-partner']);
    }

    private function tenantDashboardMessage(string $key, Tenant $tenant): string
    {
        return TenantTranslations::get(
            "dashboard.common.{$key}",
            app()->getLocale(),
            trans("site.dashboard.common.{$key}"),
            $tenant,
        );
    }

    private function resolveTenant(User $user): ?Tenant
    {
        if (empty($user->tenant_id)) {
            return null;
        }

        return Tenant::query()
            ->select('id', 'name', 'email', 'slug', 'domain', 'phone', 'plan_id', 'trial_ends_at', 'is_active')
            ->with('subscriptionPlan:id,name,is_active,max_employees,max_branches')
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
