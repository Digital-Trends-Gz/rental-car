<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Contract;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rentals\RentalStatusSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->withoutMiddleware([
            \App\Http\Middleware\PermissionMiddleware::class,
            \App\Http\Middleware\SuperAdminMiddleware::class,
            \App\Http\Middleware\CheckUserActive::class,
            'verified',
        ]);

        app()->instance(RentalStatusSyncService::class, new class {
            public function syncCarsByIds(array $carIds): void
            {
            }
        });
    }

    public function test_index_page_exposes_tenant_usage_counts(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $plan = Plan::create([
            'name' => 'Growth',
            'monthly_price' => 49,
            'yearly_price' => 490,
            'max_employees' => 5,
            'max_branches' => 2,
            'max_cars' => 10,
            'max_contracts' => 20,
            'openai_requests_per_day' => 25,
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

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'name' => 'Employee One',
            'email' => 'employee@example.com',
            'civil_number' => '12345678',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $car = Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'TEN-1001',
            'color' => CarColor::WHITE->value,
            'price_per_day' => 40,
            'mileage' => 1000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'status' => CarStatus::AVAILABLE->value,
        ]);

        $reservation = Reservation::create([
            'tenant_id' => $tenant->id,
            'reservation_number' => 'RES-TEN-1',
            'user_id' => $this->user->id,
            'car_id' => $car->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'pickup_time' => '09:00',
            'return_time' => '18:00',
            'total_days' => 2,
            'daily_rate' => 40,
            'subtotal' => 80,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 80,
            'status' => ReservationStatus::CONFIRMED->value,
        ]);

        Contract::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'reservation_id' => $reservation->id,
            'contract_number' => 'CTR-TEN-1',
            'status' => 'draft',
            'currency' => 'USD',
        ]);

        $this->actingAs($this->user)
            ->get(route('superadmin.tenants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Tenants/Index')
                ->has('tenants.data.0')
                ->where('tenants.data.0.name', $tenant->name)
                ->where('tenants.data.0.subscription_plan.name', 'Growth')
                ->where('tenants.data.0.users_count', 1)
                ->where('tenants.data.0.branches_count', 1)
                ->where('tenants.data.0.cars_count', 1)
                ->where('tenants.data.0.reservations_count', 1)
                ->where('tenants.data.0.contracts_count', 1)
            );
    }
}
