<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\TenantPermissionCatalog;
use Illuminate\Database\Seeder;

class TenantPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = TenantPermissionCatalog::permissions();

        foreach ($permissions as $permission) {
            Permission::withoutGlobalScope('tenant')->updateOrCreate(
                ['name' => $permission['name'], 'tenant_id' => null],
                [
                    'display_name' => $permission['display_name'],
                    'description' => $permission['description'],
                ]
            );
        }
    }
}
