<?php

namespace App\Services\Plans;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class PlanEntityLocks
{
    private const REASON_EMPLOYEE_LIMIT = 'plan_employee_limit';
    private const REASON_BRANCH_LIMIT = 'plan_branch_limit';

    public function sync(?Tenant $tenant): void
    {
        if (!$tenant?->id || !$tenant->plan_id) {
            return;
        }

        $tenant->loadMissing('subscriptionPlan');

        $this->syncEmployees($tenant);
        $this->syncBranches($tenant);
    }

    public function syncEmployees(?Tenant $tenant): void
    {
        if (!$tenant?->id || !$this->hasUserLockColumns()) {
            return;
        }

        $limit = $this->normalizeLimit($tenant->subscriptionPlan?->max_employees);

        if ($limit === null) {
            User::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('role', UserRole::ADMIN->value)
                ->where('plan_lock_reason', self::REASON_EMPLOYEE_LIMIT)
                ->update([
                    'plan_locked_at' => null,
                    'plan_lock_reason' => null,
                ]);

            return;
        }

        $employees = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('role', UserRole::ADMIN->value)
            ->with('roles:id,name')
            ->orderByDesc('is_active')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'name', 'email', 'role', 'is_active', 'created_at', 'plan_locked_at', 'plan_lock_reason']);

        if ($employees->isEmpty()) {
            return;
        }

        $ownerIds = $employees
            ->filter(fn (User $user): bool => $this->isTenantOwner($user, $tenant))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $allowedIds = collect($ownerIds);

        if ($limit > 0) {
            $allowedIds = $allowedIds
                ->merge(
                    $employees
                        ->reject(fn (User $user) => in_array((int) $user->id, $ownerIds, true))
                        ->take($limit)
                        ->pluck('id')
                );
        }

        $allowedIds = $allowedIds->map(fn ($id) => (int) $id)->unique()->values()->all();

        User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('role', UserRole::ADMIN->value)
            ->whereIn('id', $allowedIds ?: [0])
            ->where('plan_lock_reason', self::REASON_EMPLOYEE_LIMIT)
            ->update([
                'plan_locked_at' => null,
                'plan_lock_reason' => null,
            ]);

        User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('role', UserRole::ADMIN->value)
            ->whereNotIn('id', $allowedIds ?: [0])
            ->whereNull('plan_locked_at')
            ->update([
                'plan_locked_at' => now(),
                'plan_lock_reason' => self::REASON_EMPLOYEE_LIMIT,
            ]);
    }

    public function syncBranches(?Tenant $tenant): void
    {
        if (!$tenant?->id || !$this->hasBranchLockColumns()) {
            return;
        }

        $limit = $this->normalizeLimit($tenant->subscriptionPlan?->max_branches);

        if ($limit === null) {
            Branch::withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->where('plan_lock_reason', self::REASON_BRANCH_LIMIT)
                ->update([
                    'plan_locked_at' => null,
                    'plan_lock_reason' => null,
                ]);

            return;
        }

        $allowedIds = Branch::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        Branch::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $allowedIds ?: [0])
            ->where('plan_lock_reason', self::REASON_BRANCH_LIMIT)
            ->update([
                'plan_locked_at' => null,
                'plan_lock_reason' => null,
            ]);

        Branch::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('id', $allowedIds ?: [0])
            ->whereNull('plan_locked_at')
            ->update([
                'plan_locked_at' => now(),
                'plan_lock_reason' => self::REASON_BRANCH_LIMIT,
            ]);
    }

    public function branchIsLockedByPlan(Branch $branch): bool
    {
        if ($this->hasBranchLockColumns() && $branch->plan_locked_at) {
            return true;
        }

        $tenant = Tenant::query()
            ->with('subscriptionPlan')
            ->find((int) $branch->tenant_id);

        if (!$tenant) {
            return false;
        }

        $limit = $this->normalizeLimit($tenant->subscriptionPlan?->max_branches);

        if ($limit === null) {
            return false;
        }

        $allowedIds = Branch::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return !in_array((int) $branch->id, $allowedIds, true);
    }

    private function isTenantOwner(User $user, Tenant $tenant): bool
    {
        if ($tenant->email && strcasecmp((string) $user->email, (string) $tenant->email) === 0) {
            return true;
        }

        return $user->relationLoaded('roles')
            && $user->roles->contains(fn ($role) => $role->name === 'tenant-owner');
    }

    private function normalizeLimit(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $limit = (int) $value;

        return $limit >= 1 ? $limit : null;
    }

    private function hasUserLockColumns(): bool
    {
        return Schema::hasColumn('users', 'plan_locked_at')
            && Schema::hasColumn('users', 'plan_lock_reason');
    }

    private function hasBranchLockColumns(): bool
    {
        return Schema::hasColumn('branches', 'plan_locked_at')
            && Schema::hasColumn('branches', 'plan_lock_reason');
    }
}
