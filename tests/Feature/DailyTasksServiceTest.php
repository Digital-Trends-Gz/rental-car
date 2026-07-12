<?php

namespace Tests\Feature;

use App\Enums\CarStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rentals\RentalStatusSyncService;
use App\Services\Tasks\DailyTasksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DailyTasksServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_cleaning_task_restores_car_to_reserved_when_upcoming_reservation_exists(): void
    {
        $this->travelTo('2026-06-23 08:00:00');

        $tenant = Tenant::factory()->create(['is_active' => true]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $client = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::CLIENT,
            'is_active' => true,
        ]);

        $car = Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => null,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'CLN-1001',
            'color' => 'white',
            'price_per_day' => 100,
            'mileage' => 1000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => 'gasoline',
            'status' => CarStatus::CLEANING->value,
        ]);

        Reservation::create([
            'tenant_id' => $tenant->id,
            'user_id' => $client->id,
            'car_id' => $car->id,
            'reservation_number' => 'RES-CLN-001',
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(2)->toDateString(),
            'pickup_time' => '10:00',
            'return_time' => '18:00',
            'pickup_location' => 'Main Office',
            'return_location' => 'Main Office',
            'total_days' => 3,
            'daily_rate' => 100,
            'subtotal' => 300,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 300,
            'status' => ReservationStatus::CONFIRMED->value,
        ]);

        $car->refresh();
        $this->assertSame(CarStatus::CLEANING, $car->status);

        $this->mock(RentalStatusSyncService::class, function ($mock): void {
            $mock->shouldReceive('targetStatusForCar')
                ->once()
                ->andReturn(CarStatus::RESERVED);
        });

        $this->actingAs($admin);

        app(DailyTasksService::class)->complete(
            user: $admin,
            taskType: 'cleaning',
            sourceType: 'car',
            sourceId: (int) $car->id,
        );

        $car->refresh();
        $this->assertSame(CarStatus::RESERVED, $car->status);
    }

    public function test_completing_cleaning_task_restores_car_to_available_when_no_upcoming_reservation(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $car = Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => null,
            'make' => 'Honda',
            'model' => 'Accord',
            'year' => 2023,
            'license_plate' => 'CLN-1002',
            'color' => 'black',
            'price_per_day' => 80,
            'mileage' => 2000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => 'gasoline',
            'status' => CarStatus::CLEANING->value,
        ]);

        $this->mock(RentalStatusSyncService::class, function ($mock): void {
            $mock->shouldReceive('targetStatusForCar')
                ->once()
                ->andReturn(CarStatus::AVAILABLE);
        });

        $this->actingAs($admin);

        app(DailyTasksService::class)->complete(
            user: $admin,
            taskType: 'cleaning',
            sourceType: 'car',
            sourceId: (int) $car->id,
        );

        $car->refresh();
        $this->assertSame(CarStatus::AVAILABLE, $car->status);
    }

    public function test_employee_cannot_complete_task_for_car_in_another_branch(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $branchA = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Branch A',
        ]);

        $branchB = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Branch B',
        ]);

        $employee = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchA->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $otherBranchCar = Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchB->id,
            'make' => 'Nissan',
            'model' => 'Altima',
            'year' => 2024,
            'license_plate' => 'BR-B-1001',
            'color' => 'white',
            'price_per_day' => 75,
            'mileage' => 1500,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => 'gasoline',
            'status' => CarStatus::CLEANING->value,
        ]);

        $this->expectException(HttpException::class);

        app(DailyTasksService::class)->complete(
            user: $employee,
            taskType: 'cleaning',
            sourceType: 'car',
            sourceId: (int) $otherBranchCar->id,
        );
    }
}
