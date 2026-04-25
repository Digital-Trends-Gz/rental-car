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
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Plans\PlanUsageLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\Rentals\RentalStatusSyncService;

class PlanUsageLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        app()->instance(RentalStatusSyncService::class, new class {
            public function syncCarsByIds(array $carIds): void
            {
            }
        });
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_employee_creation_is_blocked_by_plan_limit(): void
    {
        $tenant = $this->tenantWithPlan(['max_employees' => 1]);
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
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

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'name' => 'Existing Employee',
            'email' => 'existing.employee@example.com',
            'civil_number' => '22223333',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.employees.store', ['subdomain' => $tenant->slug]), [
                'name' => 'Second Employee',
                'email' => 'second.employee@example.com',
                'civil_number' => '33334444',
                'branch_id' => $branch->id,
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'is_active' => true,
                'role_ids' => [],
                'permission_ids' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('users', [
            'tenant_id' => $tenant->id,
            'email' => 'second.employee@example.com',
        ]);
    }

    public function test_branch_creation_is_blocked_by_plan_limit(): void
    {
        $tenant = $this->tenantWithPlan(['max_branches' => 1]);
        TenantContext::set($tenant);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'civil_number' => '11112222',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Existing Branch',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.branches.store', ['subdomain' => $tenant->slug]), [
                'name' => 'Second Branch',
                'country' => 'KW',
                'city' => 'Kuwait City',
                'street_name' => 'Main Street',
                'street_number' => '1',
                'building_number' => '2',
                'office_number' => '3',
                'post_code' => '12345',
                'phone_1' => '+965 1111 2222',
                'showroom_temp_folders' => [],
                'showroom_removed_files' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('branches', [
            'tenant_id' => $tenant->id,
            'name' => 'Second Branch',
        ]);
    }

    public function test_car_creation_is_blocked_by_plan_limit(): void
    {
        $tenant = $this->tenantWithPlan(['max_cars' => 1]);
        TenantContext::set($tenant);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'civil_number' => '11112222',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'AAA-111',
            'color' => CarColor::WHITE,
            'price_per_day' => 25,
            'mileage' => 1000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'status' => CarStatus::AVAILABLE,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.cars.store', ['subdomain' => $tenant->slug]), [
                'make' => 'Honda',
                'model' => 'Civic',
                'year' => 2023,
                'license_plate' => 'BBB-222',
                'branch_id' => $branch->id,
                'color' => CarColor::BLACK->value,
                'price_per_day' => 30,
                'mileage' => 500,
                'transmission' => 'automatic',
                'seats' => 5,
                'fuel_type' => FuelType::GASOLINE->value,
                'status' => CarStatus::AVAILABLE->value,
                'image' => [],
                'additional_photos' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('cars', [
            'tenant_id' => $tenant->id,
            'license_plate' => 'BBB-222',
        ]);
    }

    public function test_contract_creation_is_blocked_by_plan_limit(): void
    {
        $tenant = $this->tenantWithPlan(['max_contracts' => 1]);
        TenantContext::set($tenant);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'civil_number' => '11112222',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $reservation = \App\Models\Reservation::create([
            'tenant_id' => $tenant->id,
            'reservation_number' => 'RES-001',
            'user_id' => $admin->id,
            'car_id' => Car::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'make' => 'Toyota',
                'model' => 'Camry',
                'year' => 2024,
                'license_plate' => 'CCC-333',
                'color' => CarColor::WHITE,
                'price_per_day' => 25,
                'mileage' => 1000,
                'transmission' => 'automatic',
                'seats' => 5,
                'fuel_type' => FuelType::GASOLINE->value,
                'status' => CarStatus::AVAILABLE,
            ])->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'pickup_time' => '09:00',
            'return_time' => '18:00',
            'total_days' => 2,
            'daily_rate' => 25,
            'subtotal' => 50,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 50,
            'status' => ReservationStatus::CONFIRMED,
        ]);

        Contract::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'reservation_id' => $reservation->id,
            'contract_number' => 'CTR-001',
            'status' => 'draft',
            'currency' => 'USD',
        ]);

        $this->assertNotNull(app(PlanUsageLimits::class)->contractLimitMessage());

        $this->actingAs($admin)
            ->post(route('admin.contracts.store', ['subdomain' => $tenant->slug]), [
                'contract_number' => 'CTR-002',
                'status' => 'draft',
                'contract_date' => today()->toDateString(),
                'currency' => 'USD',
                'primary_driver' => [
                    'temp_folders' => [],
                    'removed_file_ids' => [],
                    'documents' => [],
                    'customer_photo_temp_folders' => [],
                    'customer_photo_removed_file_ids' => [],
                ],
                'additional_drivers' => [],
                'contract_archive' => [
                    'temp_folders' => [],
                    'removed_file_ids' => [],
                ],
                'additional_archive' => [],
                'additional_archive_removed_ids' => [],
                'start_contract_temp_folders' => [],
                'start_contract_removed_files' => [],
                'end_contract_temp_folders' => [],
                'end_contract_removed_files' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('contracts', [
            'tenant_id' => $tenant->id,
            'contract_number' => 'CTR-002',
        ]);
    }

    private function tenantWithPlan(array $limits): Tenant
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $plan = Plan::create(array_merge([
            'name' => 'Plan '.uniqid(),
            'monthly_price' => 10,
            'yearly_price' => 100,
            'is_active' => true,
        ], $limits));

        $tenant->update([
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->addMonth(),
        ]);

        return $tenant->refresh();
    }
}
