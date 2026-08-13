<?php

namespace Tests\Feature\Admin;

use App\Core\TenantContext;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \App\Http\Middleware\AdminMiddleware::class,
            \App\Http\Middleware\CheckUserActive::class,
            \App\Http\Middleware\EnsureTenantEmailIsVerified::class,
            \App\Http\Middleware\EnsureTenantSubscriptionIsActive::class,
            \App\Http\Middleware\PermissionMiddleware::class,
        ]);

        TenantContext::clear();
    }

    public function test_admin_can_create_employee_with_civil_number(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'email' => 'owner@example.com',
        ]);
        TenantContext::set($tenant);

        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $manageEmployeesPermission = Permission::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'tenant-manage-employees',
            'display_name' => 'Manage Employees',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'civil_number' => '11112222',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->syncPermissions([$manageEmployeesPermission->id]);

        $this->actingAs($admin)
            ->post(route('admin.employees.store', ['subdomain' => $tenant->slug]), [
                'name' => 'Branch Employee',
                'email' => 'employee@example.com',
                'civil_number' => '99887766',
                'branch_id' => $branch->id,
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'is_active' => true,
                'role_ids' => [],
                'permission_ids' => [],
            ])
            ->assertRedirect(route('admin.employees.index', ['subdomain' => $tenant->slug]));

        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN->value,
            'email' => 'employee@example.com',
            'civil_number' => '99887766',
            'branch_id' => $branch->id,
        ]);
    }

    public function test_admin_can_update_employee_civil_number(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'email' => 'owner@example.com',
        ]);
        TenantContext::set($tenant);

        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $manageEmployeesPermission = Permission::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'tenant-manage-employees',
            'display_name' => 'Manage Employees',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'civil_number' => '11112222',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->syncPermissions([$manageEmployeesPermission->id]);

        $employee = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'name' => 'Employee One',
            'email' => 'employee@example.com',
            'civil_number' => '12345678',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->assertTrue($employee->role === UserRole::ADMIN || (string) $employee->role === UserRole::ADMIN->value);

        $this->actingAs($admin)
            ->put(route('admin.employees.update', ['subdomain' => $tenant->slug, 'employee' => $employee->id]), [
                'name' => 'Employee One',
                'email' => 'employee@example.com',
                'civil_number' => '87654321',
                'branch_id' => $branch->id,
                'password' => '',
                'password_confirmation' => '',
                'is_active' => true,
                'role_ids' => [],
                'permission_ids' => [],
            ])
            ->assertRedirect(route('admin.employees.index', ['subdomain' => $tenant->slug]));

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'civil_number' => '87654321',
        ]);
    }

    public function test_admin_cannot_create_employee_with_digits_in_name(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'email' => 'owner@example.com',
        ]);
        TenantContext::set($tenant);

        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $manageEmployeesPermission = Permission::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'tenant-manage-employees',
            'display_name' => 'Manage Employees',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'civil_number' => '11112222',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->syncPermissions([$manageEmployeesPermission->id]);

        $response = $this->actingAs($admin)
            ->from(route('admin.employees.create', ['subdomain' => $tenant->slug]))
            ->post(route('admin.employees.store', ['subdomain' => $tenant->slug]), [
                'name' => 'Employee 1',
                'email' => 'employee1@example.com',
                'civil_number' => '99887766',
                'branch_id' => $branch->id,
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'is_active' => true,
                'role_ids' => [],
                'permission_ids' => [],
            ]);

        $response->assertSessionHasErrors(['name']);
        $this->assertDatabaseMissing('users', [
            'tenant_id' => $tenant->id,
            'email' => 'employee1@example.com',
        ]);
    }

    public function test_admin_can_create_partner_employee_when_partner_seat_is_available(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'email' => 'owner@example.com',
            'partner_seats' => 1,
        ]);
        $tenant->update(['plan_id' => Plan::create([
            'name' => 'Roles Plan',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'feature_flags' => ['roles_and_permissions' => true],
            'is_active' => true,
        ])->id]);
        TenantContext::set($tenant);

        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $partnerRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'tenant-partner',
            'display_name' => 'Tenant Partner',
            'description' => 'Full-access partner role for this tenant.',
        ]);

        $manageEmployeesPermission = Permission::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'tenant-manage-employees',
            'display_name' => 'Manage Employees',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'civil_number' => '11112222',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->syncPermissions([$manageEmployeesPermission->id]);

        $this->actingAs($admin)
            ->post(route('admin.employees.store', ['subdomain' => $tenant->slug]), [
                'name' => 'Partner User',
                'email' => 'partner@example.com',
                'civil_number' => '99887766',
                'branch_id' => $branch->id,
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'is_active' => true,
                'role_ids' => [$partnerRole->id],
                'permission_ids' => [],
            ])
            ->assertRedirect(route('admin.employees.index', ['subdomain' => $tenant->slug]));

        $employee = User::query()->where('email', 'partner@example.com')->firstOrFail();

        $this->assertTrue($employee->hasRole('tenant-partner'));
    }

    public function test_admin_cannot_create_partner_employee_when_partner_seat_limit_is_reached(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'email' => 'owner@example.com',
            'partner_seats' => 1,
        ]);
        $tenant->update(['plan_id' => Plan::create([
            'name' => 'Roles Limit Plan',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'feature_flags' => ['roles_and_permissions' => true],
            'is_active' => true,
        ])->id]);
        TenantContext::set($tenant);

        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $partnerRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'tenant-partner',
            'display_name' => 'Tenant Partner',
            'description' => 'Full-access partner role for this tenant.',
        ]);

        $manageEmployeesPermission = Permission::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'tenant-manage-employees',
            'display_name' => 'Manage Employees',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'civil_number' => '11112222',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->syncPermissions([$manageEmployeesPermission->id]);

        $existingPartner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'name' => 'Existing Partner',
            'email' => 'existing-partner@example.com',
            'civil_number' => '22223333',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $existingPartner->syncRoles([$partnerRole->id]);

        $response = $this->actingAs($admin)
            ->from(route('admin.employees.create', ['subdomain' => $tenant->slug]))
            ->post(route('admin.employees.store', ['subdomain' => $tenant->slug]), [
                'name' => 'Extra Partner',
                'email' => 'extra-partner@example.com',
                'civil_number' => '99887766',
                'branch_id' => $branch->id,
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'is_active' => true,
                'role_ids' => [$partnerRole->id],
                'permission_ids' => [],
            ]);

        $response->assertRedirect(route('admin.employees.create', ['subdomain' => $tenant->slug]));
        $response->assertSessionHasErrors(['role_ids']);

        $this->assertDatabaseMissing('users', [
            'tenant_id' => $tenant->id,
            'email' => 'extra-partner@example.com',
        ]);
    }
}
