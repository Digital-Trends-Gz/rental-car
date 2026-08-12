<?php

namespace App\Services\Plans;

use App\Core\TenantContext;
use App\Models\Permission;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PlanPermissionAccess
{
    private const PERMISSION_FEATURES = [
        'tenant-manage-payments' => 'cash_payments',
        'tenant-view-debtors' => 'cash_payments',
        'tenant-collect-debtors' => 'cash_payments',
        'tenant-view-financials' => 'reports_module',
        'tenant-view-reports' => 'reports_module',
    ];

    /**
     * @return Builder<Permission>
     */
    public function tenantPermissionQuery(?Tenant $tenant = null): Builder
    {
        return Permission::withoutGlobalScope('tenant')
            ->whereNull('tenant_id')
            ->where('name', 'like', 'tenant-%')
            ->whereIn('name', $this->allowedPermissionNames($tenant));
    }

    /**
     * @return Collection<int, Permission>
     */
    public function tenantPermissions(?Tenant $tenant = null): Collection
    {
        return $this->tenantPermissionQuery($tenant)
            ->orderBy('display_name')
            ->get(['id', 'name', 'display_name', 'description']);
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, int>
     */
    public function allowedIdsFromInput(array $ids, ?Tenant $tenant = null): array
    {
        $ids = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return $this->tenantPermissionQuery($tenant)
            ->whereIn('id', $ids->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function allowedPermissionNames(?Tenant $tenant = null): array
    {
        $tenant = $this->resolveTenant($tenant);
        $allPermissionNames = Permission::withoutGlobalScope('tenant')
            ->whereNull('tenant_id')
            ->where('name', 'like', 'tenant-%')
            ->pluck('name')
            ->map(fn ($name) => (string) $name)
            ->all();

        if (!$tenant) {
            return $allPermissionNames;
        }

        $tenant->load('subscriptionPlan');

        return collect($allPermissionNames)
            ->reject(function (string $permission) use ($tenant): bool {
                $feature = self::PERMISSION_FEATURES[$permission] ?? null;

                return $feature !== null && !$tenant->supportsFeature($feature);
            })
            ->values()
            ->all();
    }

    private function resolveTenant(?Tenant $tenant = null): ?Tenant
    {
        if ($tenant) {
            return $tenant;
        }

        $tenant = TenantContext::get();
        if ($tenant) {
            return $tenant;
        }

        $tenantId = (int) (auth()->user()?->tenant_id ?? 0);

        return $tenantId > 0 ? Tenant::query()->find($tenantId) : null;
    }
}
