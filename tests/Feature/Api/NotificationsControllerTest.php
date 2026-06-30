<?php

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\ContractStatus;
use App\Enums\FuelType;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\CarDamageItem;
use App\Models\CarDamageReport;
use App\Models\Contract;
use App\Models\ContractDriver;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rentals\RentalStatusSyncService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    app()->instance(RentalStatusSyncService::class, new class {
        public function syncCarsByIds(array $carIds): void
        {
        }
    });
});

test('notifications api sorts mixed notification types by newest occurrence first', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

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

    $client = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::CLIENT,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $oldCar = createNotificationTestCar($tenant, $branch, 'OLD-DOC');
    $newCar = createNotificationTestCar($tenant, $branch, 'NEW-DMG');

    $oldReservation = createNotificationTestReservation($tenant, $client, $oldCar, 'RES-OLD-DOC');
    $oldContract = Contract::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'reservation_id' => $oldReservation->id,
        'contract_number' => 'CON-OLD-DOC',
        'status' => ContractStatus::DRAFT,
        'contract_date' => today()->subDays(6)->toDateString(),
        'renter_name' => $client->name,
        'renter_id_number' => '123456789',
        'renter_phone' => '97000000000',
        'car_details' => 'Toyota Camry 2024',
        'plate_number' => 'OLD-DOC',
        'start_date' => today()->subDays(6)->toDateString(),
        'end_date' => today()->subDays(5)->toDateString(),
        'total_amount' => 0,
        'currency' => 'USD',
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);

    ContractDriver::create([
        'tenant_id' => $tenant->id,
        'contract_id' => $oldContract->id,
        'client_id' => $client->id,
        'role' => 'primary',
        'full_name' => $client->name,
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);
    DB::table('contracts')
        ->where('id', $oldContract->id)
        ->update([
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

    $newReservation = createNotificationTestReservation($tenant, $client, $newCar, 'RES-NEW-DMG');
    $damageReport = CarDamageReport::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'car_id' => $newCar->id,
        'reservation_id' => $newReservation->id,
        'created_by' => $admin->id,
        'report_number' => 'DMG-NEW-FIRST',
        'report_type' => 'after_return',
        'status' => 'draft',
        'inspected_at' => now()->subDay(),
        'summary' => 'Newer damage',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    CarDamageItem::create([
        'tenant_id' => $tenant->id,
        'car_damage_report_id' => $damageReport->id,
        'zone_code' => 'front_bumper',
        'view_side' => 'front',
        'damage_type' => 'scratch',
        'severity' => 'minor',
        'quantity' => 1,
    ]);

    Sanctum::actingAs($admin, ['*']);

    $response = $this->getJson(route('api.notifications.index', [
        'branch_id' => $branch->id,
        'limit' => 10,
    ]));

    $response->assertOk();

    expect($response->json('notifications.0.type'))->toBe('new_damage');
    expect($response->json('notifications.1.type'))->toBe('missing_documents');
});

function createNotificationTestCar(Tenant $tenant, Branch $branch, string $plate): Car
{
    return Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'make' => 'Toyota',
        'model' => 'Camry',
        'year' => 2024,
        'license_plate' => $plate,
        'color' => CarColor::WHITE->value,
        'price_per_day' => 100,
        'mileage' => 1000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::AVAILABLE->value,
    ]);
}

function createNotificationTestReservation(Tenant $tenant, User $client, Car $car, string $number): Reservation
{
    return Reservation::create([
        'tenant_id' => $tenant->id,
        'user_id' => $client->id,
        'car_id' => $car->id,
        'reservation_number' => $number,
        'start_date' => today()->subDays(5)->toDateString(),
        'end_date' => today()->subDays(4)->toDateString(),
        'pickup_time' => '09:00',
        'return_time' => '18:00',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 1,
        'daily_rate' => 0,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
        'status' => ReservationStatus::COMPLETED,
    ]);
}
