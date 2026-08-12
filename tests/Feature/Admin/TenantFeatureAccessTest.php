<?php

namespace Tests\Feature\Admin;

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantFeatureAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->withoutMiddleware([
            \App\Http\Middleware\PermissionMiddleware::class,
            \App\Http\Middleware\CheckUserActive::class,
            'verified',
        ]);
    }

    public function test_coupon_page_returns_forbidden_when_feature_is_disabled(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $plan = Plan::create([
            'name' => 'Starter',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'feature_flags' => [
                'coupon_system' => false,
            ],
            'is_active' => true,
        ]);

        $tenant->update([
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->addMonth(),
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'civil_number' => '99998888',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.coupons.index', ['subdomain' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_auto_discount_page_returns_forbidden_when_feature_is_disabled(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $plan = Plan::create([
            'name' => 'Starter',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'feature_flags' => [
                'auto_discounts' => false,
            ],
            'is_active' => true,
        ]);

        $tenant->update([
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->addMonth(),
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'civil_number' => '99998888',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.car-discounts.index', ['subdomain' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_roles_page_returns_forbidden_when_roles_and_permissions_feature_is_disabled(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $plan = Plan::create([
            'name' => 'Starter',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'feature_flags' => [
                'roles_and_permissions' => false,
            ],
            'is_active' => true,
        ]);

        $tenant->update([
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->addMonth(),
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'civil_number' => '99998888',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.roles.index', ['subdomain' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_sidebar_feature_routes_return_forbidden_when_disabled(): void
    {
        $tenant = $this->tenantWithDisabledFeatures([
            'booking_calendar',
            'cash_payments',
            'custom_branding',
            'damage_reports',
            'pdf_export',
        ]);
        $admin = $this->tenantAdmin($tenant);
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);
        $car = Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2024,
            'license_plate' => 'FEATURE-LOCK-1',
            'color' => CarColor::WHITE,
            'price_per_day' => 25,
            'transmission' => 'automatic',
            'seats' => 5,
            'mileage' => 0,
            'fuel_type' => FuelType::GASOLINE,
            'status' => CarStatus::AVAILABLE,
        ]);

        $routes = [
            route('admin.cars.calendar', ['subdomain' => $tenant->slug, 'car' => $car]),
            route('admin.payments.index', ['subdomain' => $tenant->slug]),
            route('admin.payments.debtors', ['subdomain' => $tenant->slug]),
            route('admin.discount-requests.index', ['subdomain' => $tenant->slug]),
            route('admin.settings.website.edit', ['subdomain' => $tenant->slug]),
            route('admin.accident-reports.index', ['subdomain' => $tenant->slug]),
            route('admin.settings.contract-pdf.edit', ['subdomain' => $tenant->slug]),
            route('admin.settings.mrta-pdf.edit', ['subdomain' => $tenant->slug]),
        ];

        foreach ($routes as $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertForbidden();
        }
    }

    public function test_employee_direct_permissions_are_rejected_when_roles_and_permissions_feature_is_disabled(): void
    {
        $this->withoutMiddleware();

        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $plan = Plan::create([
            'name' => 'Starter',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'max_employees' => 5,
            'feature_flags' => [
                'roles_and_permissions' => false,
            ],
            'is_active' => true,
        ]);

        $tenant->update([
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->addMonth(),
        ]);
        \App\Core\TenantContext::set($tenant->refresh());

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $permission = Permission::create([
            'name' => 'tenant-manage-cars',
            'display_name' => 'Manage cars',
            'description' => 'Manage tenant cars.',
            'tenant_id' => null,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'civil_number' => '99998888',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.employees.create', ['subdomain' => $tenant->slug]))
            ->post(route('admin.employees.store', ['subdomain' => $tenant->slug]), [
                'name' => 'Second Employee',
                'email' => 'second.employee@example.com',
                'civil_number' => '33334444',
                'branch_id' => $branch->id,
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'is_active' => true,
                'role_ids' => [],
                'permission_ids' => [$permission->id],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('permission_ids');

        $this->assertDatabaseMissing('users', [
            'tenant_id' => $tenant->id,
            'email' => 'second.employee@example.com',
        ]);
    }

    public function test_plan_disabled_report_permission_is_hidden_from_employee_form(): void
    {
        $this->withoutMiddleware();

        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $plan = Plan::create([
            'name' => 'Starter',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'feature_flags' => [
                'roles_and_permissions' => true,
                'reports_module' => false,
            ],
            'is_active' => true,
        ]);

        $tenant->update([
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->addMonth(),
        ]);
        \App\Core\TenantContext::set($tenant->refresh());

        \App\Core\TenantContext::clear();

        Permission::withoutGlobalScope('tenant')->updateOrCreate(
            ['name' => 'tenant-view-reports', 'tenant_id' => null],
            [
                'display_name' => 'View Reports',
                'description' => 'Access and export reports.',
            ]
        );

        Permission::withoutGlobalScope('tenant')->updateOrCreate(
            ['name' => 'tenant-manage-cars', 'tenant_id' => null],
            [
                'display_name' => 'Manage Cars',
                'description' => 'Create, edit, and delete cars.',
            ]
        );

        \App\Core\TenantContext::set($tenant->refresh());

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'civil_number' => '99998888',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.employees.create', ['subdomain' => $tenant->slug]))
            ->assertOk();

        $this->assertTrue($response->inertiaProps('canManageRolesAndPermissions'));

        $permissionNames = collect($response->inertiaProps('permissions'))
            ->map(fn ($permission) => data_get($permission, 'display_name'))
            ->filter()
            ->values();

        $this->assertTrue(
            $permissionNames->contains('Manage Cars'),
            'Available permissions: '.$permissionNames->implode(', ')
        );
        $this->assertFalse($permissionNames->contains('View Reports'));
    }

    public function test_plan_disabled_report_permission_is_rejected_when_assigning_employee_permissions(): void
    {
        $this->withoutMiddleware();

        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);
        \App\Core\TenantContext::set($tenant);

        $plan = Plan::create([
            'name' => 'Starter',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'max_employees' => 5,
            'feature_flags' => [
                'roles_and_permissions' => true,
                'reports_module' => false,
            ],
            'is_active' => true,
        ]);

        $tenant->update([
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->addMonth(),
        ]);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        \App\Core\TenantContext::clear();

        $permission = Permission::withoutGlobalScope('tenant')->updateOrCreate(
            ['name' => 'tenant-view-reports', 'tenant_id' => null],
            [
                'display_name' => 'View Reports',
                'description' => 'Access and export reports.',
            ]
        );

        \App\Core\TenantContext::set($tenant->refresh());

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'civil_number' => '99998888',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.employees.create', ['subdomain' => $tenant->slug]))
            ->post(route('admin.employees.store', ['subdomain' => $tenant->slug]), [
                'name' => 'Second Employee',
                'email' => 'second.employee@example.com',
                'civil_number' => '33334444',
                'branch_id' => $branch->id,
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'is_active' => true,
                'role_ids' => [],
                'permission_ids' => [$permission->id],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('permission_ids.0');
    }

    private function tenantWithDisabledFeatures(array $features): Tenant
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $plan = Plan::create([
            'name' => 'Starter',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'feature_flags' => array_fill_keys($features, false),
            'is_active' => true,
        ]);

        $tenant->update([
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->addMonth(),
        ]);

        return $tenant->refresh();
    }

    private function tenantAdmin(Tenant $tenant): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Admin',
            'email' => uniqid('admin.', true).'@example.com',
            'civil_number' => (string) random_int(10000000, 99999999),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
