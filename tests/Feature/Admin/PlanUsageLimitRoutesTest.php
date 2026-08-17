<?php

namespace Tests\Feature\Admin;

use App\Core\TenantContext;
use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Contract;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Plans\PlanUsageLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanUsageLimitRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_create_route_is_blocked_by_plan_limit(): void
    {
        $tenant = $this->tenantWithPlan(['max_branches' => 1]);

        Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = $this->adminWithPermission($tenant, 'tenant-manage-branches');

        $this->assertNotNull(app(PlanUsageLimits::class)->branchLimitMessage($tenant->refresh()));

        $baseHost = parse_url(config('app.url'), PHP_URL_HOST);

        $this->actingAs($admin)
            ->withServerVariables(['HTTP_HOST' => $tenant->slug . '.' . $baseHost])
            ->get(route('admin.branches.create', ['subdomain' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_car_create_route_is_blocked_by_plan_limit(): void
    {
        $tenant = $this->tenantWithPlan(['max_cars' => 1]);
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2024,
            'license_plate' => 'CAR-LIMIT-1',
            'color' => CarColor::WHITE,
            'price_per_day' => 25,
            'transmission' => 'automatic',
            'seats' => 5,
            'mileage' => 0,
            'fuel_type' => FuelType::GASOLINE,
            'status' => CarStatus::AVAILABLE,
        ]);

        $admin = $this->adminWithPermission($tenant, 'tenant-manage-cars');

        $this->assertNotNull(app(PlanUsageLimits::class)->carLimitMessage($tenant->refresh()));

        $baseHost = parse_url(config('app.url'), PHP_URL_HOST);

        $this->actingAs($admin)
            ->withServerVariables(['HTTP_HOST' => $tenant->slug . '.' . $baseHost])
            ->get(route('admin.cars.create', ['subdomain' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_contract_create_route_is_blocked_by_plan_limit(): void
    {
        $tenant = $this->tenantWithPlan(['max_contracts' => 1]);
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = $this->adminWithPermission($tenant, 'tenant-manage-reservations');

        Contract::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'contract_number' => 'CTR-ROUTE-LIMIT-1',
            'status' => 'draft',
            'currency' => 'USD',
        ]);

        $this->assertNotNull(app(PlanUsageLimits::class)->contractLimitMessage($tenant->refresh()));

        $baseHost = parse_url(config('app.url'), PHP_URL_HOST);

        $this->actingAs($admin)
            ->withServerVariables(['HTTP_HOST' => $tenant->slug . '.' . $baseHost])
            ->get(route('admin.contracts.create', ['subdomain' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_reservation_create_route_is_blocked_by_monthly_plan_limit(): void
    {
        $tenant = $this->tenantWithPlan(['max_reservations_per_month' => 1]);
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = $this->adminWithPermission($tenant, 'tenant-manage-reservations');
        $car = $this->carForTenant($tenant, $branch, 'RES-LIMIT-1');

        Reservation::withoutEvents(fn () => Reservation::create([
            'tenant_id' => $tenant->id,
            'reservation_number' => 'RES-ROUTE-LIMIT-1',
            'user_id' => $admin->id,
            'car_id' => $car->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'total_days' => 2,
            'daily_rate' => 25,
            'subtotal' => 50,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 50,
            'status' => ReservationStatus::CONFIRMED,
        ]));

        $this->assertNotNull(app(PlanUsageLimits::class)->reservationLimitMessage($tenant->refresh()));

        $baseHost = parse_url(config('app.url'), PHP_URL_HOST);

        $this->actingAs($admin)
            ->withServerVariables(['HTTP_HOST' => $tenant->slug . '.' . $baseHost])
            ->get(route('admin.reservations.create', ['subdomain' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_employee_edit_route_allows_editing_employees_within_limit(): void
    {
        $tenant = $this->tenantWithPlan(['max_employees' => 3]); // Increase limit to 3

        // Make sure tenant email doesn't match any employee email to avoid owner detection
        $tenant->email = 'tenant-owner@different-domain.com';
        $tenant->save();

        // Create a branch first
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        // Create 4 employees (none should be detected as owner)
        // With limit of 3, the newest (4th) should be locked
        $employee1 = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Employee 1',
            'email' => 'employee1@example.com',
            'civil_number' => '10000001',
            'branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
            'created_at' => now()->subDays(5), // Oldest
        ]);
        $employee1->branches()->attach($branch->id, ['tenant_id' => $tenant->id]);

        $employee2 = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Employee 2',
            'email' => 'employee2@example.com',
            'civil_number' => '10000002',
            'branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
            'created_at' => now()->subDays(3),
        ]);
        $employee2->branches()->attach($branch->id, ['tenant_id' => $tenant->id]);

        $employee3 = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Employee 3',
            'email' => 'employee3@example.com',
            'civil_number' => '10000003',
            'branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
            'created_at' => now()->subDays(2),
        ]);
        $employee3->branches()->attach($branch->id, ['tenant_id' => $tenant->id]);

        $lockedEmployee = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Locked Employee',
            'email' => 'locked@example.com',
            'civil_number' => '10000004',
            'branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
            'created_at' => now()->subDays(1), // Newest - should be locked
        ]);
        $lockedEmployee->branches()->attach($branch->id, ['tenant_id' => $tenant->id]);

        // Create admin with permission (use different email and old created_at)
        $admin = $this->adminWithPermission($tenant, 'tenant-employees.update', $branch->id);
        $admin->created_at = now()->subDays(6); // Make admin the oldest
        $admin->save();
        $admin->branches()->attach($branch->id, ['tenant_id' => $tenant->id]);

        // Note: We have 5 employees total (admin, employee1, employee2, employee3, lockedEmployee) with limit of 3
        // Order by created_at ASC: admin (oldest), employee1, employee2, employee3, lockedEmployee (newest)
        // The controller will call sync() which will keep first 3 unlocked (admin, employee1, employee2)
        // and lock the 2 newest (employee3, lockedEmployee)

        $baseHost = parse_url(config('app.url'), PHP_URL_HOST);
        $subdomain = $tenant->slug . '.' . $baseHost;

        // Should allow editing employees within limit (first 3 oldest: admin, employee1, employee2)
        $this->actingAs($admin)
            ->withServerVariables(['HTTP_HOST' => $subdomain])
            ->get(route('admin.employees.edit', ['subdomain' => $tenant->slug, 'employee' => $employee1->id]))
            ->assertOk();

        $this->actingAs($admin)
            ->withServerVariables(['HTTP_HOST' => $subdomain])
            ->get(route('admin.employees.edit', ['subdomain' => $tenant->slug, 'employee' => $employee2->id]))
            ->assertOk();

        // Should block editing locked employees (exceed limit: employee3 and lockedEmployee)
        $this->actingAs($admin)
            ->withServerVariables(['HTTP_HOST' => $subdomain])
            ->get(route('admin.employees.edit', ['subdomain' => $tenant->slug, 'employee' => $employee3->id]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->withServerVariables(['HTTP_HOST' => $subdomain])
            ->get(route('admin.employees.edit', ['subdomain' => $tenant->slug, 'employee' => $lockedEmployee->id]))
            ->assertForbidden();
    }
    public function test_employee_update_route_allows_updating_employees_within_limit(): void
    {
        $tenant = $this->tenantWithPlan(['max_employees' => 2]);

        // Create a branch first
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        // Create employees in specific order
        $employee1 = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Employee 1',
            'email' => 'employee1@example.com',
            'civil_number' => '20000001',
            'branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
            'created_at' => now()->subDays(3),
        ]);

        $employee2 = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Employee 2',
            'email' => 'employee2@example.com',
            'civil_number' => '20000002',
            'branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
            'created_at' => now()->subDays(2),
        ]);

        $lockedEmployee = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Locked Employee',
            'email' => 'locked2@example.com',
            'civil_number' => '20000003',
            'branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
            'created_at' => now()->subDays(1),
        ]);

        $admin = $this->adminWithPermission($tenant, 'tenant-manage-employees', $branch->id);

        // Sync locks
        app(\App\Services\Plans\PlanEntityLocks::class)->syncEmployees($tenant->refresh());

        $employee1->refresh();
        $employee2->refresh();
        $lockedEmployee->refresh();

        // Verify lock status (first 2 should not be locked, 3rd should be locked)
        $this->assertNull($employee1->plan_locked_at);
        $this->assertNull($employee2->plan_locked_at);
        $this->assertNotNull($lockedEmployee->plan_locked_at);

        $baseHost = parse_url(config('app.url'), PHP_URL_HOST);

        // Should allow updating employee within limit
        $response = $this->actingAs($admin)
            ->withServerVariables(['HTTP_HOST' => $tenant->slug . '.' . $baseHost])
            ->put(route('admin.employees.update', ['subdomain' => $tenant->slug, 'employee' => $employee1->id]), [
                'name' => 'Updated Name',
                'email' => 'employee1@example.com',
                'civil_number' => '20000001',
                'branch_id' => $branch->id,
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Should block updating locked employee
        $response = $this->actingAs($admin)
            ->withServerVariables(['HTTP_HOST' => $tenant->slug . '.' . $baseHost])
            ->put(route('admin.employees.update', ['subdomain' => $tenant->slug, 'employee' => $lockedEmployee->id]), [
                'name' => 'Trying to Update',
                'email' => 'locked2@example.com',
                'civil_number' => '20000003',
                'branch_id' => $branch->id,
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }





    private function adminWithPermission(Tenant $tenant, string $permissionName, ?int $branchId = null): User
    {
        $permission = Permission::withoutGlobalScope('tenant')->create([
            'name' => $permissionName,
            'display_name' => str($permissionName)->replace('-', ' ')->title()->toString(),
            'description' => 'Test permission',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Owner',
            'email' => uniqid('owner.', true).'@example.com',
            'civil_number' => (string) random_int(10000000, 99999999),
            'branch_id' => $branchId,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->syncPermissions([$permission->id]);

        return $admin;
    }

    private function tenantWithPlan(array $limits): Tenant
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'trial_ends_at' => now()->addMonth(),
        ]);

        $plan = Plan::create(array_merge([
            'name' => 'Plan '.uniqid(),
            'monthly_price' => 10,
            'yearly_price' => 100,
            'is_active' => true,
        ], $limits));

        $tenant->update(['plan_id' => $plan->id]);

        return $tenant->refresh();
    }

    private function carForTenant(Tenant $tenant, Branch $branch, string $plate): Car
    {
        return Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2024,
            'license_plate' => $plate,
            'color' => CarColor::WHITE,
            'price_per_day' => 25,
            'transmission' => 'automatic',
            'seats' => 5,
            'mileage' => 0,
            'fuel_type' => FuelType::GASOLINE,
            'status' => CarStatus::AVAILABLE,
        ]);
    }
}
