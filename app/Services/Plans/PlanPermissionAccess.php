<?php

namespace App\Services\Plans;

use App\Core\TenantContext;
use App\Models\Permission;
use App\Models\Tenant;
use App\Support\TenantPermissionCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PlanPermissionAccess
{
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
            ->get(['id', 'name', 'display_name', 'description'])
            ->map(function (Permission $permission): Permission {
                $metadata = TenantPermissionCatalog::metadataFor((string) $permission->name);

                $permission->setAttribute('module', $metadata['module'] ?? 'Other');
                $permission->setAttribute('action', $metadata['action'] ?? 'Access');
                $permission->setAttribute('legacy', $metadata['legacy'] ?? null);
                $permission->setAttribute('feature', $metadata['feature'] ?? null);

                return $permission;
            })
            ->sortBy([
                ['module', 'asc'],
                ['display_name', 'asc'],
            ])
            ->values();
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
            ->reject(fn (string $permission): bool => in_array(
                $permission,
                TenantPermissionCatalog::legacyPermissionNames(),
                true
            ))
            ->reject(function (string $permission) use ($tenant): bool {
                $feature = TenantPermissionCatalog::metadataFor($permission)['feature'] ?? null;

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
