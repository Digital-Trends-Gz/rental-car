<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;

class ApiAccessMode
{
    public const MODE_OWNER = 'owner';
    public const MODE_EMPLOYEE = 'employee';

    private const ABILITY_PREFIX = 'app-mode:';
    private const BRANCH_PREFIX = 'branch:';

    public static function abilitiesFor(string $mode, ?int $branchId = null): array
    {
        $abilities = ['*', self::ABILITY_PREFIX.$mode];

        if ($mode === self::MODE_EMPLOYEE && $branchId) {
            $abilities[] = self::BRANCH_PREFIX.$branchId;
        }

        return $abilities;
    }

    public static function activeMode(User $user, ?Tenant $tenant = null, ?string $explicitMode = null): string
    {
        $mode = $explicitMode ?: self::tokenMode($user);

        if ($mode === self::MODE_EMPLOYEE) {
            return self::MODE_EMPLOYEE;
        }

        if ($mode === self::MODE_OWNER && self::isOwnerCapable($user, $tenant)) {
            return self::MODE_OWNER;
        }

        if (self::isOwnerCapable($user, $tenant)) {
            return self::MODE_OWNER;
        }

        if ($user->role === UserRole::ADMIN) {
            return self::MODE_EMPLOYEE;
        }

        return $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
    }

    public static function isEmployeeMode(?User $user, ?Tenant $tenant = null): bool
    {
        if (!$user) {
            return false;
        }

        return self::activeMode($user, $tenant) === self::MODE_EMPLOYEE;
    }

    public static function isOwnerMode(?User $user, ?Tenant $tenant = null): bool
    {
        if (!$user) {
            return false;
        }

        return self::activeMode($user, $tenant) === self::MODE_OWNER;
    }

    public static function isOwnerCapable(User $user, ?Tenant $tenant = null): bool
    {
        if ($user->role !== UserRole::ADMIN || empty($user->tenant_id)) {
            return false;
        }

        if (self::hasTenantRole($user, ['tenant-owner', 'tenant-partner'])) {
            return true;
        }

        $tenant = $tenant ?? Tenant::query()->withoutGlobalScope('tenant')->find((int) $user->tenant_id);

        if ($tenant && !empty($tenant->email)
            && strcasecmp((string) $tenant->email, (string) $user->email) === 0) {
            return true;
        }

        if (!empty($user->branch_id)) {
            return false;
        }

        $ownerUserId = User::withoutGlobalScope('tenant')
            ->where('tenant_id', (int) $user->tenant_id)
            ->where('role', UserRole::ADMIN)
            ->whereNull('branch_id')
            ->orderBy('id')
            ->value('id');

        return $ownerUserId && (int) $user->id === (int) $ownerUserId;
    }

    public static function hasTenantRole(User $user, string|array $roles): bool
    {
        $roleNames = array_values(array_filter((array) $roles));

        if ($roleNames === []) {
            return false;
        }

        return Role::withoutGlobalScope('tenant')
            ->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->where('role_user.user_type', User::class)
            ->whereIn('roles.name', $roleNames)
            ->where(function ($query) use ($user): void {
                $query->whereNull('roles.tenant_id');

                if ($user->tenant_id) {
                    $query->orWhere('roles.tenant_id', (int) $user->tenant_id);
                }
            })
            ->exists();
    }

    public static function effectiveBranchId(User $user, ?int $explicitBranchId = null): ?int
    {
        if ($explicitBranchId) {
            return $explicitBranchId;
        }

        return self::branchIdFromToken($user) ?: (!empty($user->branch_id) ? (int) $user->branch_id : null);
    }

    public static function tokenMode(?User $user): ?string
    {
        $abilities = self::tokenAbilities($user);

        if (in_array(self::ABILITY_PREFIX.self::MODE_EMPLOYEE, $abilities, true)) {
            return self::MODE_EMPLOYEE;
        }

        if (in_array(self::ABILITY_PREFIX.self::MODE_OWNER, $abilities, true)) {
            return self::MODE_OWNER;
        }

        return null;
    }

    public static function branchIdFromToken(?User $user): ?int
    {
        foreach (self::tokenAbilities($user) as $ability) {
            if (!is_string($ability) || !str_starts_with($ability, self::BRANCH_PREFIX)) {
                continue;
            }

            $branchId = (int) substr($ability, strlen(self::BRANCH_PREFIX));

            return $branchId > 0 ? $branchId : null;
        }

        return null;
    }

    private static function tokenAbilities(?User $user): array
    {
        $token = $user?->currentAccessToken();

        if (!$token) {
            return [];
        }

        $abilities = $token->abilities ?? [];

        if (is_string($abilities)) {
            $decoded = json_decode($abilities, true);

            return is_array($decoded) ? array_values($decoded) : [];
        }

        if ($abilities instanceof \Illuminate\Support\Collection) {
            return $abilities->values()->all();
        }

        return is_array($abilities) ? array_values($abilities) : [];
    }
}
