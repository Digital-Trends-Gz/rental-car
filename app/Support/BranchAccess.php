<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BranchAccess
{
    public function canAccessAllBranches(?User $user): bool
    {
        if (!$user) {
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

        if (!(method_exists($user, 'hasRole') && $user->hasRole('tenant-owner'))) {
            return false;
        }

        if (empty($user->branch_id)) {
            return true;
        }

        return $this->isPrimaryTenantAccount($user);
    }

    public function availableBranchesForUser(?User $user): Collection
    {
        if (!$user || empty($user->tenant_id)) {
            return collect();
        }

        if ($this->canAccessAllBranches($user)) {
            return Branch::query()
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        if (empty($user->branch_id)) {
            return collect();
        }

        return Branch::query()
            ->whereKey((int) $user->branch_id)
            ->orderBy('name')
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

        if (empty($user->branch_id)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($column, (int) $user->branch_id);
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

        return (int) ($user->branch_id ?? 0) === $branchId;
    }

    public function resolveWritableBranchId(?User $user, ?int $requestedBranchId): ?int
    {
        if (!$user) {
            return null;
        }

        if ($this->canAccessAllBranches($user)) {
            return $requestedBranchId;
        }

        return !empty($user->branch_id) ? (int) $user->branch_id : null;
    }

    private function tenantHasNoBranches(User $user): bool
    {
        if (empty($user->tenant_id)) {
            return false;
        }

        return !Branch::query()->exists();
    }

    private function isPrimaryTenantAccount(User $user): bool
    {
        if (empty($user->tenant_id)) {
            return false;
        }

        $tenant = Tenant::query()
            ->select('id', 'email')
            ->whereKey((int) $user->tenant_id)
            ->first();

        if (!$tenant || empty($tenant->email)) {
            return false;
        }

        return strcasecmp((string) $tenant->email, (string) $user->email) === 0;
    }
}
