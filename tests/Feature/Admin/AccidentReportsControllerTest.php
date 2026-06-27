<?php

namespace Tests\Feature\Admin;

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccidentReportsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_branch_accident_without_contract(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $car = Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'ACC-001',
            'color' => CarColor::WHITE->value,
            'price_per_day' => 100,
            'mileage' => 1000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'description' => null,
            'status' => CarStatus::AVAILABLE->value,
        ]);

        $response = $this
            ->actingAs($admin)
            ->withoutMiddleware()
            ->post(route('admin.accident-reports.store', ['subdomain' => $tenant->slug]), [
                'accident_context' => 'branch',
                'branch_id' => $branch->id,
                'car_id' => $car->id,
                'responsibility' => 'third_party',
                'location_type' => 'branch_gate',
                'accident_at' => now()->format('Y-m-d\TH:i'),
                'location' => 'Office gate',
                'description' => 'Third party hit the car at the office gate.',
                'has_injuries' => false,
                'third_party_involved' => true,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('accident_reports', [
            'contract_id' => null,
            'accident_context' => 'branch',
            'branch_id' => $branch->id,
            'car_id' => $car->id,
            'responsibility' => 'third_party',
            'location_type' => 'branch_gate',
        ]);
    }
}
