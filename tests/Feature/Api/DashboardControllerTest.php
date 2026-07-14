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
    app()->instance(RentalStatusSyncService::class, new class extends RentalStatusSyncService {
        public function syncCarsByIds(array $carIds, bool $dryRun = false, ?int $reserveBeforeHours = null): int
        {
            return 0;
        }
    });
});

test('pickup remains visible after delivery handover starts until delivery is confirmed', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Pickup Branch',
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

    $car = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'make' => 'Toyota',
        'model' => 'Camry',
        'year' => 2024,
        'license_plate' => 'PU-START-14',
        'color' => CarColor::BLACK->value,
        'price_per_day' => 50,
        'mileage' => 1000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'status' => CarStatus::RESERVED->value,
    ]);

    $reservation = Reservation::create([
        'tenant_id' => $tenant->id,
        'user_id' => $client->id,
        'car_id' => $car->id,
        'reservation_number' => 'RES-PU-START-14',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'pickup_time' => '09:00',
        'return_time' => '18:00',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 2,
        'daily_rate' => 50,
        'subtotal' => 100,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 100,
        'status' => ReservationStatus::ACTIVE,
    ]);

    Contract::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'reservation_id' => $reservation->id,
        'contract_number' => 'CTR-PU-START-14',
        'status' => ContractStatus::ACTIVE->value,
        'contract_date' => today()->toDateString(),
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'handover_state' => [
            'current_page' => 2,
            'completed_pages' => [1],
            'steps' => [
                'customer_review' => [
                    'page' => 1,
                    'key' => 'customer_review',
                    'completed' => true,
                    'payload' => ['reviewed' => true],
                ],
            ],
        ],
    ]);

    Sanctum::actingAs($admin, ['*']);

    $this->getJson(route('api.reservations.today-pickups', [
        'type' => '',
        'per_page' => 100,
        'page' => 1,
    ]))
        ->assertOk()
        ->assertJsonPath('pickup.count', 1)
        ->assertJsonPath('reservations.0.id', $reservation->id);

    $summary = $this->getJson(route('api.dashboard.summary'))
        ->assertOk();

    $todayPickupCard = collect($summary->json('cards'))->firstWhere('key', 'today_pickups');
    expect($todayPickupCard['count'])->toBe(1);
    expect($todayPickupCard['items'][0]['reservation_id'])->toBe($reservation->id);

    $this->getJson(route('api.tasks.today', ['type' => 'pickup']))
        ->assertOk()
        ->assertJsonPath('progress.total', 1)
        ->assertJsonPath('tasks.0.source_type', 'reservation')
        ->assertJsonPath('tasks.0.source_id', $reservation->id);
});

test('return remains visible after return handover starts until return is finalized', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Return Branch',
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

    $car = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'make' => 'Hyundai',
        'model' => 'Tucson',
        'year' => 2023,
        'license_plate' => 'RET-START-14',
        'color' => CarColor::BLACK->value,
        'price_per_day' => 80,
        'mileage' => 2000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'status' => CarStatus::RENTED->value,
    ]);

    $reservation = Reservation::create([
        'tenant_id' => $tenant->id,
        'user_id' => $client->id,
        'car_id' => $car->id,
        'reservation_number' => 'RES-RET-START-14',
        'start_date' => today()->subDay()->toDateString(),
        'end_date' => today()->toDateString(),
        'pickup_time' => '09:00',
        'return_time' => '18:00',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 2,
        'daily_rate' => 80,
        'subtotal' => 160,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 160,
        'status' => ReservationStatus::ACTIVE,
    ]);

    $contract = Contract::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'reservation_id' => $reservation->id,
        'contract_number' => 'CTR-RET-START-14',
        'status' => ContractStatus::ACTIVE->value,
        'contract_date' => today()->subDay()->toDateString(),
        'start_date' => today()->subDay()->toDateString(),
        'end_date' => today()->toDateString(),
    ]);

    ContractReturnReport::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'contract_id' => $contract->id,
        'reservation_id' => $reservation->id,
        'car_id' => $car->id,
        'created_by' => $admin->id,
        'report_number' => 'RTR-DRAFT-14',
        'status' => 'draft',
        'payment_status' => 'not_paid',
        'return_location' => 'Main Office',
        'notes' => 'Return review started.',
    ]);

    Sanctum::actingAs($admin, ['*']);

    $this->getJson(route('api.reservations.today-pickups', [
        'type' => '',
        'per_page' => 100,
        'page' => 1,
    ]))
        ->assertOk()
        ->assertJsonPath('return.count', 1)
        ->assertJsonPath('returns.0.contract_id', $contract->id);

    $summary = $this->getJson(route('api.dashboard.summary'))
        ->assertOk();

    $todayReturnCard = collect($summary->json('cards'))->firstWhere('key', 'today_returns');
    expect($todayReturnCard['count'])->toBe(1);
    expect($todayReturnCard['items'][0]['id'])->toBe($contract->id);

    $this->getJson(route('api.tasks.today', ['type' => 'return']))
        ->assertOk()
        ->assertJsonPath('progress.total', 1)
        ->assertJsonPath('tasks.0.source_type', 'contract')
        ->assertJsonPath('tasks.0.source_id', $contract->id);
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

test('active today contracts are empty for branch restricted user without assigned branch', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Branch A',
    ]);

    $client = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::CLIENT,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    createDashboardOverdueContract($tenant, $branch, $client, 'ACTIVE-TODAY');

    $employeeWithoutBranch = User::factory()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => null,
        'role' => UserRole::ADMIN,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($employeeWithoutBranch, ['*']);

    $response = $this->getJson(route('api.contracts.active-today'));

    $response->assertOk()
        ->assertJsonPath('count', 0)
        ->assertJsonPath('contracts', []);
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
