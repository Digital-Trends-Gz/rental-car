<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class TenantPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'tenant-manage-cars',
                'display_name' => 'Manage Cars',
                'description' => 'Create, edit, and delete cars.',
            ],
            [
                'name' => 'tenant-manage-reservations',
                'display_name' => 'Manage Reservations',
                'description' => 'View and manage all car reservations.',
            ],
            [
                'name' => 'tenant-edit-return-reports',
                'display_name' => 'Edit Return Reports',
                'description' => 'Create and edit return status reports and their extra charges.',
            ],
            [
                'name' => 'tenant-manage-clients',
                'display_name' => 'Manage Clients',
                'description' => 'Create and manage client accounts.',
            ],
            [
                'name' => 'tenant-manage-payments',
                'display_name' => 'Manage Payments',
                'description' => 'View and handle reservation payments.',
            ],
            [
                'name' => 'tenant-manage-support',
                'display_name' => 'Manage Support',
                'description' => 'View and manage support tickets and replies.',
            ],
            [
                'name' => 'tenant-manage-employees',
                'display_name' => 'Manage Employees',
                'description' => 'Manage branch employees and their roles.',
            ],
            [
                'name' => 'tenant-manage-branches',
                'display_name' => 'Manage Branches',
                'description' => 'Create and edit company branches.',
            ],
            [
                'name' => 'tenant-view-reports',
                'display_name' => 'View Reports',
                'description' => 'Access and export financial and operational reports.',
            ],
            [
                'name' => 'tenant-manage-settings',
                'display_name' => 'Manage Settings',
                'description' => 'Update tenant profile and settings.',
            ],
        ];

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
