<?php

namespace Tests\Feature\Admin;

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Contract;
use App\Models\Permission;
use App\Models\Plan;
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

        $this->actingAs($admin)
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

        $this->actingAs($admin)
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

        $this->actingAs($admin)
            ->get(route('admin.contracts.create', ['subdomain' => $tenant->slug]))
            ->assertForbidden();
    }

    private function adminWithPermission(Tenant $tenant, string $permissionName): User
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
}
