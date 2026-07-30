<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Branch;
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

        if (method_exists($user, 'hasRole') && ($user->hasRole('tenant-owner') || $user->hasRole('tenant-partner'))) {
            return true;
        }

        return false;
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

        $branchId = ApiAccessMode::effectiveBranchId($user);

        if (empty($branchId)) {
            return collect();
        }

        return Branch::query()
            ->whereKey((int) $branchId)
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

        $branchId = ApiAccessMode::effectiveBranchId($user);

        if (empty($branchId)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($column, (int) $branchId);
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

        return (int) (ApiAccessMode::effectiveBranchId($user) ?? 0) === $branchId;
    }

    public function resolveWritableBranchId(?User $user, ?int $requestedBranchId): ?int
    {
        if (!$user) {
            return null;
        }

        if ($this->canAccessAllBranches($user)) {
            return $requestedBranchId;
        }

        return ApiAccessMode::effectiveBranchId($user);
    }

    private function tenantHasNoBranches(User $user): bool
    {
        if (empty($user->tenant_id)) {
            return false;
        }

        return !Branch::query()->exists();
    }

}
