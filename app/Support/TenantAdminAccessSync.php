<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantPermissionCatalog;

class TenantAdminAccessSync
{
    public function syncUser(User $user, Tenant $tenant): bool
    {
        if ($user->role !== UserRole::ADMIN || (int) $user->tenant_id !== (int) $tenant->id) {
            return false;
        }

        $tenantId = (int) $tenant->id;
        $ownerUserId = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('role', UserRole::ADMIN)
            ->orderBy('id')
            ->value('id');

        if (!$ownerUserId || (int) $user->id !== (int) $ownerUserId) {
            return false;
        }

        $ownerRole = Role::withoutGlobalScope('tenant')->firstOrCreate(
            [
                'name' => 'tenant-owner',
                'tenant_id' => $tenantId,
            ],
            [
                'display_name' => 'Tenant Owner',
                'description' => 'Default full-access role for the tenant account owner.',
            ]
        );

        $partnerRole = Role::withoutGlobalScope('tenant')->firstOrCreate(
            [
                'name' => 'tenant-partner',
                'tenant_id' => $tenantId,
            ],
            [
                'display_name' => 'Tenant Partner',
                'description' => 'Full-access partner role for this tenant.',
            ]
        );

        $permissionIds = Permission::withoutGlobalScope('tenant')
            ->where('name', 'like', 'tenant-%')
            ->whereNotIn('name', TenantPermissionCatalog::legacyPermissionNames())
            ->where(function ($query) use ($tenantId) {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', $tenantId);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $ownerRole->permissions()->sync($permissionIds);
        $partnerRole->permissions()->sync($permissionIds);
        $user->roles()->syncWithoutDetaching([$ownerRole->id]);

        return true;
    }

    /**
     * @return array{tenants:int, admins:int, synced:int}
     */
    public function syncAllTenants(): array
    {
        $tenants = Tenant::query()->get(['id']);
        $admins = User::withoutGlobalScope('tenant')
            ->where('role', UserRole::ADMIN)
            ->whereNotNull('tenant_id')
            ->get(['id', 'tenant_id', 'role']);

        $adminsByTenant = $admins->groupBy(fn (User $user) => (int) $user->tenant_id);

        $synced = 0;

        foreach ($tenants as $tenant) {
            foreach ($adminsByTenant->get((int) $tenant->id, collect()) as $admin) {
                if ($this->syncUser($admin, $tenant)) {
                    $synced++;
                }
            }
        }

        return [
            'tenants' => $tenants->count(),
            'admins' => $admins->count(),
            'synced' => $synced,
        ];
    }
}
