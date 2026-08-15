<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Plans\PlanEntityLocks;
use App\Support\TenantTranslations;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiUserPlanUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof User || $user->role !== UserRole::ADMIN || !$user->tenant_id) {
            return $next($request);
        }

        $tenant = Tenant::query()
            ->select('id', 'name', 'email', 'slug', 'domain', 'phone', 'plan_id', 'trial_ends_at', 'is_active')
            ->with('subscriptionPlan:id,name,is_active,max_employees,max_branches')
            ->whereKey($user->tenant_id)
            ->first();

        if (!$tenant) {
            return response()->json([
                'message' => 'No tenant is assigned to this account.',
            ], 403);
        }

        app(PlanEntityLocks::class)->sync($tenant);
        $user->refresh();

        if ($user->plan_locked_at) {
            return response()->json([
                'message' => $this->tenantDashboardMessage('employee_locked_by_plan', $tenant),
            ], 403);
        }

        if ($this->userBranchIsLockedByPlan($user)) {
            return response()->json([
                'message' => $this->tenantDashboardMessage('branch_locked_by_plan', $tenant),
            ], 403);
        }

        return $next($request);
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
        return method_exists($user, 'hasRole')
            && ($user->hasRole('tenant-owner') || $user->hasRole('tenant-partner'));
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
}
