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

class DailyTasksControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_tasks_are_limited_to_the_employee_branch_when_no_branch_is_requested(): void
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
            'email_verified_at' => now(),
        ]);

        $this->createCleaningTaskCar($tenant, $branchA, 'TASK-A-001');
        $this->createCleaningTaskCar($tenant, $branchB, 'TASK-B-001');

        Sanctum::actingAs($employee, ['*']);

        $response = $this->getJson(route('api.tasks.today', [
            'type' => 'cleaning',
        ]));

        $response->assertOk()
            ->assertJsonPath('branch_id', $branchA->id);

        $licensePlates = collect($response->json('tasks'))
            ->pluck('car.license_plate')
            ->all();

        $this->assertContains('TASK-A-001', $licensePlates);
        $this->assertNotContains('TASK-B-001', $licensePlates);
    }

    public function test_employee_cannot_request_tasks_for_another_branch(): void
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
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($employee, ['*']);

        $this->getJson(route('api.tasks.today', [
            'branch_id' => $branchB->id,
        ]))->assertForbidden();
    }

    private function createCleaningTaskCar(Tenant $tenant, Branch $branch, string $licensePlate): Car
    {
        return Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => $licensePlate,
            'color' => CarColor::WHITE->value,
            'price_per_day' => 100,
            'mileage' => 1000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'description' => null,
            'status' => CarStatus::CLEANING->value,
        ]);
    }
}
