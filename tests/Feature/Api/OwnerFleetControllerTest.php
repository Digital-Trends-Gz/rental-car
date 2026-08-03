<?php

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\CarMaintenance;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('owner fleet show endpoint returns occupancy rate, upcoming summary, last maintenance, damage summary and net monthly revenue', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Olaya Branch',
        'city' => 'Riyadh',
        'country' => 'Saudi Arabia',
    ]);

    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => null,
        'role' => UserRole::ADMIN,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $ownerRole = Role::create([
        'tenant_id' => $tenant->id,
        'name' => 'tenant-owner',
        'display_name' => 'Tenant Owner',
        'description' => 'Tenant owner',
    ]);
    $owner->roles()->syncWithoutDetaching([$ownerRole->id]);

    $car = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'make' => 'Hyundai',
        'model' => 'Tucson',
        'year' => 2023,
        'license_plate' => 'ABC 1234',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 150,
        'mileage' => 12000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    $customer = User::factory()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'role' => UserRole::CLIENT,
        'is_active' => true,
    ]);

    $upcomingReservation = Reservation::withoutEvents(fn () => Reservation::create([
        'tenant_id' => $tenant->id,
        'reservation_number' => 'RES-FLEET-SHOW-001',
        'user_id' => $customer->id,
        'car_id' => $car->id,
        'start_date' => today()->addDays(5)->toDateString(),
        'end_date' => today()->addDays(8)->toDateString(),
        'pickup_time' => '11:00:00',
        'total_days' => 3,
        'daily_rate' => 150,
        'subtotal' => 450,
        'total_amount' => 450,
        'status' => ReservationStatus::CONFIRMED->value,
    ]));

    Payment::create([
        'tenant_id' => $tenant->id,
        'reservation_id' => $upcomingReservation->id,
        'user_id' => $customer->id,
        'amount' => 500,
        'base_amount' => 500,
        'refunded_amount' => 50,
        'currency' => 'OMR',
        'payment_method' => PaymentMethod::CASH->value,
        'status' => PaymentStatus::COMPLETED->value,
        'processed_at' => now(),
    ]);

    CarMaintenance::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'car_id' => $car->id,
        'scheduled_date' => today()->subDays(10)->toDateString(),
        'completed_at' => today()->subDays(10),
        'cost' => 200,
        'notes' => 'Routine service',
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
