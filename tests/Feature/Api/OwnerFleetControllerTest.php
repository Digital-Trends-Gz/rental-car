<?php

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\MaintenanceRecordStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\CarMaintenance;
use App\Models\MaintenanceType;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// ─────────────────────────────────────────────────────────────
//  Helpers
// ─────────────────────────────────────────────────────────────

function createOwnerWithCar(): array
{
    $tenant = Tenant::factory()->create(['is_active' => true]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name'      => 'Olaya Branch',
        'city'      => 'Riyadh',
        'country'   => 'Saudi Arabia',
    ]);

    $owner = User::factory()->create([
        'tenant_id'          => $tenant->id,
        'branch_id'          => null,
        'role'               => UserRole::ADMIN,
        'is_active'          => true,
        'email_verified_at'  => now(),
    ]);

    $ownerRole = Role::create([
        'tenant_id'    => $tenant->id,
        'name'         => 'tenant-owner',
        'display_name' => 'Tenant Owner',
        'description'  => 'Tenant owner',
    ]);
    $owner->roles()->syncWithoutDetaching([$ownerRole->id]);

    $car = Car::create([
        'tenant_id'     => $tenant->id,
        'branch_id'     => $branch->id,
        'make'          => 'Hyundai',
        'model'         => 'Tucson',
        'year'          => 2023,
        'license_plate' => 'ABC 1234',
        'color'         => CarColor::WHITE->value,
        'price_per_day' => 150,
        'mileage'       => 12000,
        'transmission'  => 'automatic',
        'seats'         => 5,
        'fuel_type'     => FuelType::GASOLINE->value,
        'status'        => CarStatus::AVAILABLE->value,
    ]);

    return compact('tenant', 'branch', 'owner', 'car');
}

// ─────────────────────────────────────────────────────────────
//  Show test (existing)
// ─────────────────────────────────────────────────────────────

test('owner fleet show endpoint returns occupancy rate, upcoming summary, last maintenance, damage summary and net monthly revenue', function () {
    ['tenant' => $tenant, 'branch' => $branch, 'owner' => $owner, 'car' => $car] = createOwnerWithCar();

    $customer = User::factory()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'role'      => UserRole::CLIENT,
        'is_active' => true,
    ]);

    $upcomingReservation = Reservation::withoutEvents(fn () => Reservation::create([
        'tenant_id'          => $tenant->id,
        'reservation_number' => 'RES-FLEET-SHOW-001',
        'user_id'            => $customer->id,
        'car_id'             => $car->id,
        'start_date'         => today()->addDays(5)->toDateString(),
        'end_date'           => today()->addDays(8)->toDateString(),
        'pickup_time'        => '11:00:00',
        'total_days'         => 3,
        'daily_rate'         => 150,
        'subtotal'           => 450,
        'total_amount'       => 450,
        'status'             => ReservationStatus::CONFIRMED->value,
    ]));

    Payment::create([
        'tenant_id'       => $tenant->id,
        'reservation_id'  => $upcomingReservation->id,
        'user_id'         => $customer->id,
        'amount'          => 500,
        'base_amount'     => 500,
        'refunded_amount' => 50,
        'currency'        => 'OMR',
        'payment_method'  => PaymentMethod::CASH->value,
        'status'          => PaymentStatus::COMPLETED->value,
        'processed_at'    => now(),
    ]);

    CarMaintenance::create([
        'tenant_id'      => $tenant->id,
        'branch_id'      => $branch->id,
        'car_id'         => $car->id,
        'scheduled_date' => today()->subDays(10)->toDateString(),
        'completed_at'   => today()->subDays(10),
        'cost'           => 200,
        'notes'          => 'Routine service',
    ]);

    Sanctum::actingAs($owner, ['*']);

    $response = $this->getJson(route('api.owner.fleet.show', ['car' => $car->id]));

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.id', $car->id)
        ->assertJsonPath('data.make', 'Hyundai')
        ->assertJsonPath('data.model', 'Tucson')
        ->assertJsonPath('data.year', 2023)
        ->assertJsonPath('data.license_plate', 'ABC 1234')
        ->assertJsonPath('data.monthly_revenue', 450)
        ->assertJsonPath('data.upcoming_reservations_summary.count', 1)
        ->assertJsonPath('data.last_maintenance.count', 1)
        ->assertJsonPath('data.last_maintenance.days_ago', 10)
        ->assertJsonPath('data.damage_record_summary.count', 0)
        ->assertJsonPath('data.damage_record_summary.status', 'excellent');

    expect($response->json('data.occupancy_rate'))->toBeGreaterThanOrEqual(0);
    expect($response->json('data.formatted_occupancy_rate'))->toContain('%');
});

// ─────────────────────────────────────────────────────────────
//  Maintenance Options
// ─────────────────────────────────────────────────────────────

test('maintenance options returns statuses and maintenance types with workshops', function () {
    ['tenant' => $tenant, 'owner' => $owner] = createOwnerWithCar();

    $type = MaintenanceType::create([
        'tenant_id'  => $tenant->id,
        'name'       => 'Oil Change',
        'is_active'  => true,
        'sort_order' => 1,
    ]);

    Sanctum::actingAs($owner, ['*']);

    $response = $this->getJson(route('api.owner.fleet.maintenance-options'));

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'data' => [
                'statuses'          => [['value', 'label', 'color']],
                'maintenance_types' => [['id', 'name', 'workshops']],
            ],
        ]);

    expect($response->json('data.statuses'))->toHaveCount(count(MaintenanceRecordStatus::cases()));
    expect(collect($response->json('data.maintenance_types'))->firstWhere('id', $type->id))->not->toBeNull();
});

// ─────────────────────────────────────────────────────────────
//  Schedule Maintenance
// ─────────────────────────────────────────────────────────────

test('schedule maintenance creates a maintenance record and returns 201', function () {
    ['tenant' => $tenant, 'branch' => $branch, 'owner' => $owner, 'car' => $car] = createOwnerWithCar();

    Sanctum::actingAs($owner, ['*']);

    $response = $this->postJson(
        route('api.owner.fleet.schedule-maintenance', ['car' => $car->id]),
        [
            'status'         => MaintenanceRecordStatus::SCHEDULED->value,
            'scheduled_date' => today()->addDays(3)->toDateString(),
            'task_time'      => '09:30',
            'notes'          => 'First service',
        ]
    );

    $response
        ->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.car_id', $car->id)
        ->assertJsonPath('data.status', MaintenanceRecordStatus::SCHEDULED->value)
        ->assertJsonPath('data.task_time', '09:30')
        ->assertJsonPath('data.notes', 'First service');

    $this->assertDatabaseHas('car_maintenances', [
        'car_id'    => $car->id,
        'tenant_id' => $tenant->id,
        'status'    => MaintenanceRecordStatus::SCHEDULED->value,
    ]);
});

test('schedule maintenance with in_progress status sets car to maintenance status', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'car' => $car] = createOwnerWithCar();

    Sanctum::actingAs($owner, ['*']);

    $this->postJson(
        route('api.owner.fleet.schedule-maintenance', ['car' => $car->id]),
        [
            'status'         => MaintenanceRecordStatus::IN_PROGRESS->value,
            'scheduled_date' => today()->toDateString(),
        ]
    )->assertStatus(201);

    expect($car->fresh()->status)->toBe(CarStatus::MAINTENANCE);
});

test('schedule maintenance validation fails for missing status', function () {
    ['owner' => $owner, 'car' => $car] = createOwnerWithCar();

    Sanctum::actingAs($owner, ['*']);

    $this->postJson(
        route('api.owner.fleet.schedule-maintenance', ['car' => $car->id]),
        []
    )->assertStatus(422)->assertJsonValidationErrors(['status']);
});

// ─────────────────────────────────────────────────────────────
//  Transfer Branch
// ─────────────────────────────────────────────────────────────

test('transfer branch moves car to new branch', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'car' => $car] = createOwnerWithCar();

    $newBranch = Branch::create([
        'tenant_id' => $tenant->id,
        'name'      => 'Malaz Branch',
        'city'      => 'Riyadh',
        'country'   => 'Saudi Arabia',
    ]);

    Sanctum::actingAs($owner, ['*']);

    $response = $this->postJson(
        route('api.owner.fleet.transfer-branch', ['car' => $car->id]),
        ['branch_id' => $newBranch->id]
    );

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.car_id', $car->id)
        ->assertJsonPath('data.new_branch_id', $newBranch->id)
        ->assertJsonPath('data.new_branch_name', 'Malaz Branch');

    expect($car->fresh()->branch_id)->toBe($newBranch->id);
});

test('transfer branch fails when moving to same branch', function () {
    ['branch' => $branch, 'owner' => $owner, 'car' => $car] = createOwnerWithCar();

    Sanctum::actingAs($owner, ['*']);

    $this->postJson(
        route('api.owner.fleet.transfer-branch', ['car' => $car->id]),
        ['branch_id' => $branch->id]
    )->assertStatus(422)->assertJsonValidationErrors(['branch_id']);
});

test('transfer branch fails when branch_id belongs to another tenant', function () {
    ['owner' => $owner, 'car' => $car] = createOwnerWithCar();

    $otherTenant = Tenant::factory()->create(['is_active' => true]);
    $foreignBranch = Branch::create([
        'tenant_id' => $otherTenant->id,
        'name'      => 'Foreign Branch',
        'city'      => 'Dubai',
        'country'   => 'UAE',
    ]);

    Sanctum::actingAs($owner, ['*']);

    $this->postJson(
        route('api.owner.fleet.transfer-branch', ['car' => $car->id]),
        ['branch_id' => $foreignBranch->id]
    )->assertStatus(422)->assertJsonValidationErrors(['branch_id']);
});

