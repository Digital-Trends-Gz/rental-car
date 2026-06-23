<?php

namespace Tests\Feature\Api;

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CarsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_cars_index_returns_all_cars_with_status_counts(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'CAR-001',
            'color' => CarColor::WHITE->value,
            'price_per_day' => 100,
            'mileage' => 1000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'status' => CarStatus::AVAILABLE->value,
        ]);

        Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Honda',
            'model' => 'Accord',
            'year' => 2023,
            'license_plate' => 'CAR-002',
            'color' => CarColor::BLACK->value,
            'price_per_day' => 90,
            'mileage' => 2000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'status' => CarStatus::RESERVED->value,
        ]);

        Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Nissan',
            'model' => 'Altima',
            'year' => 2022,
            'license_plate' => 'CAR-003',
            'color' => CarColor::SILVER->value,
            'price_per_day' => 80,
            'mileage' => 3000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'status' => CarStatus::CLEANING->value,
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson(route('api.cars.index'));

        $response->assertOk()
            ->assertJsonPath('count', 3)
            ->assertJsonCount(3, 'cars')
            ->assertJsonPath('status_counts.0.value', CarStatus::DRAFT->value)
            ->assertJsonPath('cars.0.license_plate', 'CAR-003')
            ->assertJsonStructure([
                'branch_id',
                'filters',
                'count',
                'status_counts' => [
                    ['value', 'label', 'color', 'count'],
                ],
                'pagination',
                'cars' => [
                    ['id', 'make', 'model', 'year', 'name', 'license_plate', 'status', 'status_label', 'status_color', 'branch_name', 'image_url'],
                ],
            ]);

        $availableCount = collect($response->json('status_counts'))
            ->firstWhere('value', CarStatus::AVAILABLE->value)['count'] ?? null;
        $reservedCount = collect($response->json('status_counts'))
            ->firstWhere('value', CarStatus::RESERVED->value)['count'] ?? null;
        $cleaningCount = collect($response->json('status_counts'))
            ->firstWhere('value', CarStatus::CLEANING->value)['count'] ?? null;

        $this->assertSame(1, $availableCount);
        $this->assertSame(1, $reservedCount);
        $this->assertSame(1, $cleaningCount);
    }

    public function test_cars_index_can_filter_by_status(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => null,
            'make' => 'Toyota',
            'model' => 'Yaris',
            'year' => 2021,
            'license_plate' => 'CLN-001',
            'color' => CarColor::WHITE->value,
            'price_per_day' => 50,
            'mileage' => 500,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'status' => CarStatus::CLEANING->value,
        ]);

        Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => null,
            'make' => 'Hyundai',
            'model' => 'Elantra',
            'year' => 2020,
            'license_plate' => 'AVL-001',
            'color' => CarColor::WHITE->value,
            'price_per_day' => 45,
            'mileage' => 700,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'status' => CarStatus::AVAILABLE->value,
        ]);

        Sanctum::actingAs($admin, ['*']);

        $this->getJson(route('api.cars.index', ['status' => CarStatus::CLEANING->value]))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('cars.0.status', CarStatus::CLEANING->value)
            ->assertJsonPath('filters.status', CarStatus::CLEANING->value);
    }

    public function test_cars_status_endpoint_returns_all_status_options(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson(route('api.cars.status'), [
            'Accept-Language' => 'ar',
        ]);

        $response->assertOk()
            ->assertJsonCount(count(CarStatus::cases()), 'statuses')
            ->assertJsonPath('statuses.1.value', CarStatus::AVAILABLE->value)
            ->assertJsonPath('statuses.1.label', 'متاحة');
    }
}
