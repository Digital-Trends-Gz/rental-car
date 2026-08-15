<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BranchAccess
{
    public function canAccessAllBranches(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (ApiAccessMode::isEmployeeMode($user)) {
            return false;
        }

        if ($user->role === UserRole::ADMIN && $this->tenantHasNoBranches($user)) {
            return true;
        }

        if ($user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        if ($user->role !== UserRole::ADMIN) {
            return false;
        }

        if (ApiAccessMode::hasTenantRole($user, 'tenant-owner')) {
            return true;
        }

        if (ApiAccessMode::hasTenantRole($user, 'tenant-partner')) {
            return $this->assignedBranchIds($user) === []
                && empty(ApiAccessMode::effectiveBranchId($user));
        }

        if (ApiAccessMode::isOwnerCapable($user)) {
            $tenant = Tenant::query()->withoutGlobalScope('tenant')->find((int) $user->tenant_id);
            if ($tenant) {
                app(TenantAdminAccessSync::class)->syncUser($user, $tenant);
            }

            return true;
        }

        return false;
    }

    public function canUseOwnerApis(?User $user): bool
    {
        return $user instanceof User
            && $user->role === UserRole::ADMIN
            && !empty($user->tenant_id)
            && ApiAccessMode::isOwnerCapable($user);
    }

    public function ownerScopedBranchId(?User $user): ?int
    {
        if (!$user || $this->canAccessAllBranches($user)) {
            return null;
        }

        $branchIds = $this->accessibleBranchIds($user);

        return $branchIds[0] ?? null;
    }

    /**
     * @return array<int, int>
     */
    public function accessibleBranchIds(?User $user): array
    {
        if (!$user || empty($user->tenant_id) || $this->canAccessAllBranches($user)) {
            return [];
        }

        $branchIds = $this->assignedBranchIds($user);
        $legacyBranchId = ApiAccessMode::effectiveBranchId($user);

        if ($legacyBranchId) {
            $branchIds[] = (int) $legacyBranchId;
        }

        return array_values(array_unique(array_filter($branchIds)));
    }

    public function availableBranchesForUser(?User $user): Collection
    {
        if (!$user || empty($user->tenant_id)) {
            return collect();
        }

        if ($this->canAccessAllBranches($user)) {
            $query = Branch::query()
                ->orderBy('name');

            $this->onlyUnlockedBranches($query);

            return $query
                ->get(['id', 'name']);
        }

        $branchIds = $this->accessibleBranchIds($user);

        if ($branchIds === []) {
            return collect();
        }

        $query = Branch::query()
            ->whereIn('id', $branchIds)
            ->orderBy('name');

        $this->onlyUnlockedBranches($query);

        return $query
            ->get(['id', 'name']);
    }

    public function normalizeRequestedBranchId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    public function resolveAccessibleBranchId(?User $user, ?int $requestedBranchId): ?int
    {
        if (!$user) {
            return null;
        }

        if ($this->canAccessAllBranches($user)) {
            return $requestedBranchId;
        }

        $branchIds = $this->accessibleBranchIds($user);

        if ($branchIds === []) {
            return null;
        }

        if ($requestedBranchId && in_array($requestedBranchId, $branchIds, true)) {
            return $requestedBranchId;
        }

        return $branchIds[0];
    }

    public function applyToQuery(Builder $query, ?User $user, ?int $requestedBranchId, string $column = 'branch_id'): Builder
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->canAccessAllBranches($user)) {
            return $requestedBranchId
                ? $query->where($column, $requestedBranchId)
                : $query;
        }

        $branchIds = $this->accessibleBranchIds($user);

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $branchIds);
    }

    public function canAccessBranchId(?User $user, ?int $branchId): bool
    {
        if ($branchId === null) {
            return $this->canAccessAllBranches($user);
        }

        if (!$user) {
            return false;
        }

        if ($this->canAccessAllBranches($user)) {
            return true;
        }

        return in_array($branchId, $this->accessibleBranchIds($user), true);
    }

    public function resolveWritableBranchId(?User $user, ?int $requestedBranchId): ?int
    {
        if (!$user) {
            return null;
        }

        if ($this->canAccessAllBranches($user)) {
            if (!$requestedBranchId) {
                return null;
            }

            $query = Branch::query()
                ->whereKey($requestedBranchId)
                ->limit(1);

            $this->onlyUnlockedBranches($query);

            return $query->exists() ? $requestedBranchId : null;
        }

        $branchIds = $this->accessibleBranchIds($user);

        if ($branchIds === []) {
            return null;
        }

        if ($requestedBranchId && !in_array($requestedBranchId, $branchIds, true)) {
            return null;
        }

        $branchId = $requestedBranchId ?: (count($branchIds) === 1 ? $branchIds[0] : null);

        if (!$branchId) {
            return null;
        }

        $query = Branch::query()
            ->whereKey($branchId)
            ->limit(1);

        $this->onlyUnlockedBranches($query);

        return $query->exists() ? (int) $branchId : null;
    }

    private function onlyUnlockedBranches(Builder $query): void
    {
        if (Schema::hasColumn('branches', 'plan_locked_at')) {
            $query->whereNull('plan_locked_at');
        }
    }

    /**
     * @return array<int, int>
     */
    private function assignedBranchIds(User $user): array
    {
        if (!Schema::hasTable('branch_user')) {
            return [];
        }

        return DB::table('branch_user')
            ->where('user_id', $user->id)
            ->where('tenant_id', (int) $user->tenant_id)
            ->pluck('branch_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function tenantHasNoBranches(User $user): bool
    {
        if (empty($user->tenant_id)) {
            return false;
        }

        return !Branch::query()->exists();
    }

}
