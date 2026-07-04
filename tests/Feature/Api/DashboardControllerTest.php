<?php

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\ContractStatus;
use App\Enums\FuelType;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Contract;
use App\Models\ContractReturnReport;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rentals\RentalStatusSyncService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    app()->instance(RentalStatusSyncService::class, new class {
        public function syncCarsByIds(array $carIds): void
        {
        }
    });
});

test('dashboard summary overdue count excludes contracts with return reports', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Branch A',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'role' => UserRole::ADMIN,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($admin, ['*']);

    $client = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::CLIENT,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $openOverdueContract = createDashboardOverdueContract($tenant, $branch, $client, 'OPEN');
    $reportedOverdueContract = createDashboardOverdueContract($tenant, $branch, $client, 'REPORTED');

    ContractReturnReport::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'contract_id' => $reportedOverdueContract->id,
        'reservation_id' => $reportedOverdueContract->reservation_id,
        'car_id' => $reportedOverdueContract->reservation->car_id,
        'created_by' => $admin->id,
        'report_number' => 'RR-DASH-REPORTED',
        'status' => 'draft',
        'actual_return_time' => now(),
        'return_location' => 'Main Office',
        'return_odometer' => 1250,
        'return_fuel_level' => 'full',
        'vehicle_condition_after' => 'clean',
        'payment_status' => 'not_paid',
        'total_extra_charges' => 0,
        'notes' => null,
    ]);

    $summaryResponse = $this->getJson(route('api.dashboard.summary', [
        'branch_id' => $branch->id,
    ]));

    $tasksResponse = $this->getJson(route('api.reservations.today-pickups', [
        'branch_id' => $branch->id,
    ]));

    $summaryResponse->assertOk();
    $tasksResponse->assertOk()
        ->assertJsonPath('overdue.count', 1)
        ->assertJsonPath('overdue.items.0.contract_number', $openOverdueContract->contract_number);

    $overdueCard = collect($summaryResponse->json('cards'))
        ->firstWhere('key', 'overdue');

    expect($overdueCard['count'] ?? null)->toBe(1);
    expect($overdueCard['items'][0]['contract_number'] ?? null)->toBe($openOverdueContract->contract_number);
});

test('dashboard summary includes maintenance cars count', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Branch A',
    ]);

    $otherBranch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Branch B',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'role' => UserRole::ADMIN,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'make' => 'Toyota',
        'model' => 'Camry',
        'year' => 2024,
        'license_plate' => 'MNT-001',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 100,
        'mileage' => 1000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::MAINTENANCE->value,
    ]);

    Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $otherBranch->id,
        'make' => 'Honda',
        'model' => 'Civic',
        'year' => 2024,
        'license_plate' => 'MNT-002',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 100,
        'mileage' => 1000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::MAINTENANCE->value,
    ]);

    Sanctum::actingAs($admin, ['*']);

    $response = $this->getJson(route('api.dashboard.summary', [
        'branch_id' => $branch->id,
    ]));

    $response->assertOk()
        ->assertJsonPath('stats.maintenance_cars', 1);

    $maintenanceCard = collect($response->json('cards'))
        ->firstWhere('key', 'needs_maintenance');

    expect($maintenanceCard['count'] ?? null)->toBe(1);
    expect($maintenanceCard['items'][0]['license_plate'] ?? null)->toBe('MNT-001');
});

function createDashboardOverdueContract(Tenant $tenant, Branch $branch, User $client, string $suffix): Contract
{
    $car = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'make' => 'Toyota',
        'model' => 'Camry',
        'year' => 2024,
        'license_plate' => "DASH-{$suffix}",
        'color' => CarColor::WHITE->value,
        'price_per_day' => 100,
        'mileage' => 1000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    $reservation = Reservation::create([
        'tenant_id' => $tenant->id,
        'user_id' => $client->id,
        'car_id' => $car->id,
        'reservation_number' => "RES-DASH-{$suffix}",
        'start_date' => today()->subDays(5)->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
        'pickup_time' => '09:00',
        'return_time' => '18:00',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 5,
        'daily_rate' => 100,
        'subtotal' => 500,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 500,
        'status' => ReservationStatus::COMPLETED_WAIT_CONTRACT,
    ]);

    return Contract::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'reservation_id' => $reservation->id,
        'contract_number' => "CON-DASH-{$suffix}",
        'status' => ContractStatus::ACTIVE,
        'contract_date' => today()->subDays(5)->toDateString(),
        'renter_name' => $client->name,
        'renter_id_number' => '123456789',
        'renter_phone' => '97000000000',
        'car_details' => 'Toyota Camry 2024',
        'plate_number' => "DASH-{$suffix}",
        'start_date' => today()->subDays(5)->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
        'total_amount' => 500,
        'currency' => 'USD',
    ]);
}
