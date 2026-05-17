<?php

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\CarViolationStatus;
use App\Enums\ContractStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\FuelType;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\CarDamageCase;
use App\Models\CarDamageItem;
use App\Models\CarDamageReport;
use App\Models\CarViolation;
use App\Models\Contract;
use App\Models\ContractArchiveFile;
use App\Models\ContractDriver;
use App\Models\ContractDriverDocument;
use App\Models\ContractReturnReport;
use App\Models\Payment;
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

test('today pickups api supports pagination and reservation status filtering', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branchA = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Branch A',
    ]);

    $branchB = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Branch B',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::SUPER_ADMIN,
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

    $pendingCarOne = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branchA->id,
        'make' => 'Toyota',
        'model' => 'Camry',
        'year' => 2024,
        'license_plate' => 'API-A-001',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 100,
        'mileage' => 1000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    $pendingCarTwo = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branchA->id,
        'make' => 'Honda',
        'model' => 'Civic',
        'year' => 2024,
        'license_plate' => 'API-A-002',
        'color' => CarColor::BLACK->value,
        'price_per_day' => 90,
        'mileage' => 500,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    $otherBranchCar = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branchB->id,
        'make' => 'Nissan',
        'model' => 'Altima',
        'year' => 2023,
        'license_plate' => 'API-B-001',
        'color' => CarColor::SILVER->value,
        'price_per_day' => 80,
        'mileage' => 750,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    Reservation::create([
        'tenant_id' => $tenant->id,
        'user_id' => $client->id,
        'car_id' => $pendingCarOne->id,
        'reservation_number' => 'RES-API-001',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'pickup_time' => '09:30',
        'return_time' => '17:00',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 2,
        'daily_rate' => 100,
        'subtotal' => 200,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 200,
        'status' => ReservationStatus::PENDING,
    ]);

    Reservation::create([
        'tenant_id' => $tenant->id,
        'user_id' => $client->id,
        'car_id' => $pendingCarTwo->id,
        'reservation_number' => 'RES-API-002',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'pickup_time' => '11:00',
        'return_time' => '19:00',
        'pickup_location' => 'Airport',
        'return_location' => 'Airport',
        'total_days' => 2,
        'daily_rate' => 90,
        'subtotal' => 180,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 180,
        'status' => ReservationStatus::PENDING,
    ]);

    Reservation::create([
        'tenant_id' => $tenant->id,
        'user_id' => $client->id,
        'car_id' => $otherBranchCar->id,
        'reservation_number' => 'RES-API-003',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'pickup_time' => '13:00',
        'return_time' => '18:00',
        'pickup_location' => 'Airport',
        'return_location' => 'Airport',
        'total_days' => 2,
        'daily_rate' => 80,
        'subtotal' => 160,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 160,
        'status' => ReservationStatus::CONFIRMED,
    ]);

    $returnCar = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branchA->id,
        'make' => 'Mazda',
        'model' => 'CX-5',
        'year' => 2024,
        'license_plate' => 'API-A-003',
        'color' => CarColor::RED->value,
        'price_per_day' => 120,
        'mileage' => 300,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    $overdueCar = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branchA->id,
        'make' => 'Kia',
        'model' => 'Sportage',
        'year' => 2023,
        'license_plate' => 'API-A-004',
        'color' => CarColor::GRAY->value,
        'price_per_day' => 95,
        'mileage' => 600,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    $returnReservation = Reservation::create([
        'tenant_id' => $tenant->id,
        'user_id' => $client->id,
        'car_id' => $returnCar->id,
        'reservation_number' => 'RES-API-004',
        'start_date' => today()->subDays(2)->toDateString(),
        'end_date' => today()->toDateString(),
        'pickup_time' => '08:30',
        'return_time' => '18:30',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 3,
        'daily_rate' => 120,
        'subtotal' => 360,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 360,
        'status' => ReservationStatus::COMPLETED_WAIT_CONTRACT,
    ]);

    Contract::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branchA->id,
        'reservation_id' => $returnReservation->id,
        'contract_number' => 'CON-API-001',
        'status' => ContractStatus::ACTIVE,
        'contract_date' => today()->subDays(2)->toDateString(),
        'renter_name' => $client->name,
        'renter_id_number' => '111111111',
        'renter_phone' => '97000000002',
        'car_details' => '2024 Mazda CX-5',
        'plate_number' => 'API-A-003',
        'start_date' => today()->subDays(2)->toDateString(),
        'end_date' => today()->toDateString(),
        'total_amount' => 360,
        'currency' => 'USD',
    ]);

    $overdueReservation = Reservation::create([
        'tenant_id' => $tenant->id,
        'user_id' => $client->id,
        'car_id' => $overdueCar->id,
        'reservation_number' => 'RES-API-005',
        'start_date' => today()->subDays(4)->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
        'pickup_time' => '07:30',
        'return_time' => '17:30',
        'pickup_location' => 'Airport',
        'return_location' => 'Airport',
        'total_days' => 4,
        'daily_rate' => 95,
        'subtotal' => 380,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 380,
        'status' => ReservationStatus::COMPLETED_WAIT_CONTRACT,
    ]);

    Contract::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branchA->id,
        'reservation_id' => $overdueReservation->id,
        'contract_number' => 'CON-API-002',
        'status' => ContractStatus::ACTIVE,
        'contract_date' => today()->subDays(4)->toDateString(),
        'renter_name' => $client->name,
        'renter_id_number' => '222222222',
        'renter_phone' => '97000000003',
        'car_details' => '2023 Kia Sportage',
        'plate_number' => 'API-A-004',
        'start_date' => today()->subDays(4)->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
        'total_amount' => 380,
        'currency' => 'USD',
    ]);

    $overviewResponse = $this->getJson(route('api.reservations.today-pickups', [
        'branch_id' => $branchA->id,
    ]));

    $overviewResponse->assertOk()
        ->assertJson([
            'date' => today()->toDateString(),
            'branch_id' => $branchA->id,
        ])
        ->assertJsonPath('pagination.current_page', 1)
        ->assertJsonPath('pagination.per_page', 15)
        ->assertJsonPath('pagination.total', 4)
        ->assertJsonPath('pagination.last_page', 1)
        ->assertJsonPath('pagination.from', 1)
        ->assertJsonPath('pagination.to', 4)
        ->assertJsonPath('pagination.has_more_pages', false)
        ->assertJsonPath('pickup.type', 'pickup')
        ->assertJsonPath('pickup.type_label', 'Pickup')
        ->assertJsonPath('pickup.count', 2)
        ->assertJsonPath('pickup.items.0.reservation_number', 'RES-API-001')
        ->assertJsonPath('pickup.reservations.0.reservation_number', 'RES-API-001')
        ->assertJsonPath('return.type', 'return')
        ->assertJsonPath('return.type_label', 'Return')
        ->assertJsonPath('return.count', 1)
        ->assertJsonPath('return.items.0.id', $returnReservation->id)
        ->assertJsonPath('return.items.0.contract_number', 'CON-API-001')
        ->assertJsonPath('return.returns.0.contract_number', 'CON-API-001')
        ->assertJsonPath('overdue.type', 'overdue')
        ->assertJsonPath('overdue.type_label', 'Overdue')
        ->assertJsonPath('overdue.count', 1)
        ->assertJsonPath('overdue.items.0.id', $overdueReservation->id)
        ->assertJsonPath('overdue.items.0.contract_number', 'CON-API-002')
        ->assertJsonPath('overdue.returns.0.contract_number', 'CON-API-002');

    $pageOne = $this->getJson(route('api.reservations.today-pickups', [
        'branch_id' => $branchA->id,
        'status' => 'pending',
        'type' => 'pickup',
        'per_page' => 1,
        'page' => 1,
    ]));

    $pageOne->assertOk()
        ->assertJson([
            'date' => today()->toDateString(),
            'branch_id' => $branchA->id,
            'type' => 'pickup',
            'count' => 2,
        ])
        ->assertJsonPath('pagination.current_page', 1)
        ->assertJsonPath('pagination.last_page', 2)
        ->assertJsonPath('items.0.reservation_number', 'RES-API-001')
        ->assertJsonPath('reservations.0.reservation_number', 'RES-API-001');

    $pageTwo = $this->getJson(route('api.reservations.today-pickups', [
        'branch_id' => $branchA->id,
        'status' => 'pending',
        'type' => 'pickup',
        'per_page' => 1,
        'page' => 2,
    ]));

    $pageTwo->assertOk()
        ->assertJsonPath('pagination.current_page', 2)
        ->assertJsonPath('pagination.last_page', 2)
        ->assertJsonPath('reservations.0.reservation_number', 'RES-API-002');
});

test('returns api supports today and overdue scopes', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Branch A',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::SUPER_ADMIN,
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

    $carToday = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'make' => 'Chevrolet',
        'model' => 'Tahoe',
        'year' => 2021,
        'license_plate' => 'RET-001',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 110,
        'mileage' => 1200,
        'transmission' => 'automatic',
        'seats' => 7,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    $carOverdue = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'make' => 'Toyota',
        'model' => 'Land Cruiser',
        'year' => 2022,
        'license_plate' => 'RET-002',
        'color' => CarColor::BLACK->value,
        'price_per_day' => 130,
        'mileage' => 2000,
        'transmission' => 'automatic',
        'seats' => 7,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    $carPickup = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'make' => 'Nissan',
        'model' => 'Patrol',
        'year' => 2023,
        'license_plate' => 'PICK-001',
        'color' => CarColor::SILVER->value,
        'price_per_day' => 150,
        'mileage' => 500,
        'transmission' => 'automatic',
        'seats' => 7,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    Reservation::create([
        'tenant_id' => $tenant->id,
        'user_id' => $client->id,
        'car_id' => $carPickup->id,
        'reservation_number' => 'RES-PICK-001',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(2)->toDateString(),
        'pickup_time' => '10:00',
        'return_time' => '18:00',
        'pickup_location' => 'Branch Office',
        'return_location' => 'Branch Office',
        'total_days' => 3,
        'daily_rate' => 150,
        'subtotal' => 450,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 450,
        'status' => ReservationStatus::PENDING,
    ]);

    $reservationToday = Reservation::create([
        'tenant_id' => $tenant->id,
        'user_id' => $client->id,
        'car_id' => $carToday->id,
        'reservation_number' => 'RES-RET-001',
        'start_date' => today()->subDays(2)->toDateString(),
        'end_date' => today()->toDateString(),
        'pickup_time' => '09:00',
        'return_time' => '18:00',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 3,
        'daily_rate' => 110,
        'subtotal' => 330,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 330,
        'status' => ReservationStatus::COMPLETED_WAIT_CONTRACT,
    ]);

    Contract::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'reservation_id' => $reservationToday->id,
        'contract_number' => 'CON-RET-001',
        'status' => ContractStatus::ACTIVE,
        'contract_date' => today()->subDays(2)->toDateString(),
        'renter_name' => $client->name,
        'renter_id_number' => '123456789',
        'renter_phone' => '97000000000',
        'car_details' => '2021 Chevrolet Tahoe',
        'plate_number' => 'RET-001',
        'start_date' => today()->subDays(2)->toDateString(),
        'end_date' => today()->toDateString(),
        'total_amount' => 330,
        'currency' => 'USD',
    ]);

    $reservationOverdue = Reservation::create([
        'tenant_id' => $tenant->id,
        'user_id' => $client->id,
        'car_id' => $carOverdue->id,
        'reservation_number' => 'RES-RET-002',
        'start_date' => today()->subDays(5)->toDateString(),
        'end_date' => today()->subDays(1)->toDateString(),
        'pickup_time' => '08:00',
        'return_time' => '18:00',
        'pickup_location' => 'Airport',
        'return_location' => 'Airport',
        'total_days' => 5,
        'daily_rate' => 130,
        'subtotal' => 650,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 650,
        'status' => ReservationStatus::COMPLETED_WAIT_CONTRACT,
    ]);

    Contract::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'reservation_id' => $reservationOverdue->id,
        'contract_number' => 'CON-RET-002',
        'status' => ContractStatus::ACTIVE,
        'contract_date' => today()->subDays(5)->toDateString(),
        'renter_name' => $client->name,
        'renter_id_number' => '987654321',
        'renter_phone' => '97000000001',
        'car_details' => '2022 Toyota Land Cruiser',
        'plate_number' => 'RET-002',
        'start_date' => today()->subDays(5)->toDateString(),
        'end_date' => today()->subDays(1)->toDateString(),
        'total_amount' => 650,
        'currency' => 'USD',
    ]);

    $arHeaders = ['Accept-Language' => 'ar'];
    $enHeaders = ['Accept-Language' => 'en'];

    $todayResponse = $this->getJson(route('api.reservations.returns', [
        'scope' => 'today',
        'per_page' => 1,
    ]), $arHeaders);

    $todayResponse->assertOk()
        ->assertJson([
            'scope' => 'today',
            'count' => 1,
            'pagination' => [
                'current_page' => 1,
                'per_page' => 1,
                'total' => 1,
            ],
        ])
        ->assertJsonPath('returns.0.contract_number', 'CON-RET-001')
        ->assertJsonPath('returns.0.is_overdue', false);

    $overdueResponse = $this->getJson(route('api.reservations.returns', [
        'scope' => 'overdue',
        'per_page' => 1,
    ]), $arHeaders);

    $overdueResponse->assertOk()
        ->assertJson([
            'scope' => 'overdue',
            'count' => 1,
            'pagination' => [
                'current_page' => 1,
                'per_page' => 1,
                'total' => 1,
            ],
        ])
        ->assertJsonPath('returns.0.contract_number', 'CON-RET-002')
        ->assertJsonPath('returns.0.is_overdue', true)
        ->assertJsonPath('returns.0.days_overdue', 1);

    $todayPickupTypeResponse = $this->getJson(route('api.reservations.today-pickups', [
        'type' => 'return',
        'per_page' => 1,
    ]), $arHeaders);

    $todayPickupTypeResponse->assertOk()
        ->assertJsonPath('type', 'return')
        ->assertJsonPath('type_label', 'تسليم')
        ->assertJsonPath('count', 1)
        ->assertJsonPath('items.0.contract_number', 'CON-RET-001')
        ->assertJsonPath('items.0.task_type', 'return');

    $tasksResponse = $this->getJson(route('api.reservations.tasks', [
        'per_page' => 1,
    ]), $arHeaders);

    $tasksResponse->assertOk()
        ->assertJsonPath('counts.pickup', 1)
        ->assertJsonPath('counts.return', 1)
        ->assertJsonPath('counts.overdue', 1)
        ->assertJsonPath('status.0.key', 'pickup')
        ->assertJsonPath('status.0.value', 1)
        ->assertJsonPath('status.0.label', 'استلام')
        ->assertJsonPath('status.1.key', 'return')
        ->assertJsonPath('status.1.value', 1)
        ->assertJsonPath('status.1.label', 'تسليم')
        ->assertJsonPath('status.2.key', 'overdue')
        ->assertJsonPath('status.2.value', 1)
        ->assertJsonPath('status.2.label', 'متأخر');

    $todayPickupsSummaryResponse = $this->getJson(route('api.reservations.today-pickups', [
        'per_page' => 1,
    ]), $arHeaders);

    $todayPickupsSummaryResponse->assertOk()
        ->assertJsonPath('pagination.current_page', 1)
        ->assertJsonPath('pagination.per_page', 1)
        ->assertJsonPath('pagination.total', 3)
        ->assertJsonPath('pagination.last_page', 3)
        ->assertJsonPath('pagination.from', 1)
        ->assertJsonPath('pagination.to', 3)
        ->assertJsonPath('pagination.has_more_pages', false)
        ->assertJsonPath('pickup.type', 'pickup')
        ->assertJsonPath('pickup.count', 1)
        ->assertJsonPath('pickup.items.0.reservation_number', 'RES-PICK-001')
        ->assertJsonPath('pickup.type_label', 'استلام')
        ->assertJsonPath('return.type', 'return')
        ->assertJsonPath('return.count', 1)
        ->assertJsonPath('return.items.0.id', $reservationToday->id)
        ->assertJsonPath('return.items.0.contract_number', 'CON-RET-001')
        ->assertJsonPath('return.type_label', 'تسليم')
        ->assertJsonPath('overdue.type', 'overdue')
        ->assertJsonPath('overdue.count', 1)
        ->assertJsonPath('overdue.items.0.id', $reservationOverdue->id)
        ->assertJsonPath('overdue.items.0.contract_number', 'CON-RET-002')
        ->assertJsonPath('overdue.type_label', 'متأخر');

    $taskTypesResponse = $this->getJson(route('api.reservations.task-types'), $arHeaders);

    $taskTypesResponse->assertOk()
        ->assertJsonPath('task_types.0.key', 'pickup')
        ->assertJsonPath('task_types.0.label', 'استلام')
        ->assertJsonPath('task_types.1.key', 'return')
        ->assertJsonPath('task_types.1.label', 'تسليم')
        ->assertJsonPath('task_types.2.key', 'overdue')
        ->assertJsonPath('task_types.2.label', 'متأخر');

    $overdueTypeResponse = $this->getJson(route('api.reservations.today-pickups', [
        'type' => 'overdue',
        'per_page' => 1,
    ]), $arHeaders);

    $englishTaskTypesResponse = $this->getJson(route('api.reservations.task-types'), $enHeaders);

    $englishTaskTypesResponse->assertOk()
        ->assertJsonPath('task_types.0.key', 'pickup')
        ->assertJsonPath('task_types.0.label', 'Pickup')
        ->assertJsonPath('task_types.1.key', 'return')
        ->assertJsonPath('task_types.1.label', 'Return')
        ->assertJsonPath('task_types.2.key', 'overdue')
        ->assertJsonPath('task_types.2.label', 'Overdue');

    $overdueTypeResponse->assertOk()
        ->assertJsonPath('type', 'overdue')
        ->assertJsonPath('type_label', 'متأخر')
        ->assertJsonPath('items.0.contract_number', 'CON-RET-002')
        ->assertJsonPath('items.0.task_type', 'overdue')
        ->assertJsonPath('items.0.is_overdue', true);
});

test('returns api returns empty pagination payload when no contracts exist', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::SUPER_ADMIN,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($admin, ['*']);

    $response = $this->getJson(route('api.reservations.returns', [
        'scope' => 'today',
        'per_page' => 10,
        'page' => 1,
    ]));

    $response->assertOk()
        ->assertJsonPath('count', 0)
        ->assertJsonPath('pagination.current_page', 1)
        ->assertJsonPath('pagination.per_page', 10)
        ->assertJsonPath('pagination.total', 0)
        ->assertJsonPath('pagination.last_page', 1)
        ->assertJsonPath('pagination.from', null)
        ->assertJsonPath('pagination.to', null)
        ->assertJsonPath('pagination.has_more_pages', false)
        ->assertJsonCount(0, 'returns');
});

test('reservation note api updates reservation notes and returns them in detail response', function () {
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

    $car = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'make' => 'Hyundai',
        'model' => 'Tucson',
        'year' => 2023,
        'license_plate' => 'NOTE-001',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 95,
        'mileage' => 1500,
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
        'reservation_number' => 'RES-NOTE-001',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'pickup_time' => '10:00',
        'return_time' => '18:00',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 2,
        'daily_rate' => 95,
        'subtotal' => 190,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 190,
        'status' => ReservationStatus::PENDING,
    ]);

    $note = 'Client asked for child seat.';

    $response = $this->postJson(route('api.reservations.note', $reservation), [
        'note' => $note,
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Note updated successfully.',
            'reservation_id' => $reservation->id,
            'note' => $note,
        ]);

    $this->assertDatabaseHas('reservations', [
        'id' => $reservation->id,
        'notes' => $note,
    ]);

    $showResponse = $this->getJson(route('api.reservations.show', $reservation));

    $showResponse->assertOk()
        ->assertJsonPath('reservation.notes', $note);
});

test('contract documents api returns driver photos licenses ids passports and archive files', function () {
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

    $car = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'make' => 'Toyota',
        'model' => 'Camry',
        'year' => 2024,
        'license_plate' => 'DOC-001',
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
        'reservation_number' => 'RES-DOC-001',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'pickup_time' => '09:00',
        'return_time' => '18:00',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 2,
        'daily_rate' => 100,
        'subtotal' => 200,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 200,
        'status' => ReservationStatus::ACTIVE,
    ]);

    $contract = Contract::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'reservation_id' => $reservation->id,
        'contract_number' => 'CON-DOC-001',
        'status' => ContractStatus::ACTIVE,
        'contract_date' => today()->toDateString(),
        'renter_name' => $client->name,
        'renter_id_number' => '123456789',
        'renter_phone' => '97000000000',
        'car_details' => '2024 Toyota Camry',
        'plate_number' => 'DOC-001',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'total_amount' => 200,
        'currency' => 'USD',
    ]);

    $driver = ContractDriver::create([
        'tenant_id' => $tenant->id,
        'contract_id' => $contract->id,
        'client_id' => $client->id,
        'role' => 'primary',
        'sort_order' => 1,
        'full_name' => $client->name,
        'full_name_ar' => 'العميل',
        'phone' => '97000000000',
        'nationality' => 'Jordanian',
        'place_of_issue' => 'Amman',
        'date_of_birth' => today()->subYears(30)->toDateString(),
        'identity_number' => 'ID-123',
        'passport_number' => 'P-123',
        'passport_expiry_date' => today()->addYear()->toDateString(),
        'visa_number' => 'V-123',
        'visa_expiry_date' => today()->addYear()->toDateString(),
        'residency_number' => 'R-123',
        'license_number' => 'L-123',
        'license_issue_date' => today()->subYear()->toDateString(),
        'identity_expiry_date' => today()->addYear()->toDateString(),
        'license_expiry_date' => today()->addYear()->toDateString(),
        'customer_photo_path' => 'storage/contracts/drivers/customer-photo.jpg',
        'customer_photo_name' => 'customer-photo.jpg',
        'customer_photo_mime_type' => 'image/jpeg',
        'notes' => 'Primary driver documents.',
    ]);

    ContractDriverDocument::create([
        'tenant_id' => $tenant->id,
        'contract_driver_id' => $driver->id,
        'document_type' => 'driver_license',
        'side' => 'front',
        'file_path' => 'storage/contracts/drivers/license-front.jpg',
        'file_name' => 'license-front.jpg',
        'mime_type' => 'image/jpeg',
        'ocr_status' => 'completed',
        'confidence' => 0.91,
    ]);

    ContractDriverDocument::create([
        'tenant_id' => $tenant->id,
        'contract_driver_id' => $driver->id,
        'document_type' => 'id_card',
        'side' => 'front',
        'file_path' => 'storage/contracts/drivers/id-front.jpg',
        'file_name' => 'id-front.jpg',
        'mime_type' => 'image/jpeg',
        'ocr_status' => 'completed',
        'confidence' => 0.88,
    ]);

    ContractDriverDocument::create([
        'tenant_id' => $tenant->id,
        'contract_driver_id' => $driver->id,
        'document_type' => 'passport',
        'side' => 'single',
        'file_path' => 'storage/contracts/drivers/passport.jpg',
        'file_name' => 'passport.jpg',
        'mime_type' => 'image/jpeg',
        'ocr_status' => 'completed',
        'confidence' => 0.93,
    ]);

    ContractArchiveFile::create([
        'tenant_id' => $tenant->id,
        'contract_id' => $contract->id,
        'contract_driver_id' => $driver->id,
        'document_type' => 'signed_contract',
        'title' => 'Signed Contract',
        'notes' => 'Final signed copy.',
        'file_path' => 'storage/contracts/archive/signed-contract.pdf',
        'file_name' => 'signed-contract.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $response = $this->getJson(route('api.contracts.documents', $contract));

    $response->assertOk()
        ->assertJsonPath('contract.contract_number', 'CON-DOC-001')
        ->assertJsonPath('drivers.0.owner_key', 'primary')
        ->assertJsonPath('drivers.0.customer_photo.file_name', 'customer-photo.jpg')
        ->assertJsonPath('drivers.0.documents.0.document_type', 'driver_license')
        ->assertJsonPath('archive_files.0.document_type', 'signed_contract')
        ->assertJsonPath('documents.0.document_type', 'customer_photo')
        ->assertJsonPath('documents.1.document_type', 'driver_license')
        ->assertJsonCount(1, 'drivers')
        ->assertJsonCount(1, 'archive_files')
        ->assertJsonCount(5, 'documents');
});

test('reservation show api returns contract damage reports violations and payments', function () {
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

    $car = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'make' => 'Chevrolet',
        'model' => 'Tahoe',
        'year' => 2021,
        'license_plate' => 'API-DET-001',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 110,
        'mileage' => 1200,
        'transmission' => 'automatic',
        'seats' => 7,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    $reservation = Reservation::create([
        'tenant_id' => $tenant->id,
        'user_id' => $client->id,
        'car_id' => $car->id,
        'reservation_number' => 'RES-API-DET-001',
        'start_date' => today()->subDay()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'pickup_time' => '09:00',
        'return_time' => '18:00',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 3,
        'daily_rate' => 110,
        'subtotal' => 330,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 330,
        'status' => ReservationStatus::ACTIVE,
    ]);

    $contract = Contract::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'reservation_id' => $reservation->id,
        'contract_number' => 'CON-API-DET-001',
        'status' => ContractStatus::ACTIVE,
        'contract_date' => today()->subDay()->toDateString(),
        'renter_name' => $client->name,
        'renter_id_number' => '123456789',
        'renter_phone' => '97000000000',
        'car_details' => '2021 Chevrolet Tahoe',
        'plate_number' => 'API-DET-001',
        'vehicle_odometer' => 1200,
        'vehicle_fuel_level' => 'full',
        'price_per_day' => 110,
        'start_date' => today()->subDay()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'total_amount' => 330,
        'currency' => 'USD',
        'notes' => null,
    ]);

    $reservationDamageReport = CarDamageReport::create([
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'branch_id' => $branch->id,
        'contract_id' => null,
        'reservation_id' => $reservation->id,
        'created_by' => $admin->id,
        'report_number' => 'DR-RES-001',
        'report_type' => 'before_delivery',
        'status' => 'draft',
        'inspected_at' => now(),
        'odometer' => 1200,
        'summary' => 'Reservation damage report.',
    ]);

    CarDamageItem::create([
        'tenant_id' => $tenant->id,
        'car_damage_report_id' => $reservationDamageReport->id,
        'zone_code' => 'FRONT',
        'view_side' => 'front',
        'damage_type' => 'scratch',
        'severity' => 'minor',
        'damage_timing' => 'before_delivery',
        'quantity' => 1,
        'marker_x' => 10,
        'marker_y' => 20,
        'estimated_cost' => 15,
        'notes' => 'Small scratch.',
        'sort_order' => 1,
    ]);

    $contractDamageReport = CarDamageReport::create([
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'branch_id' => $branch->id,
        'contract_id' => $contract->id,
        'reservation_id' => $reservation->id,
        'created_by' => $admin->id,
        'report_number' => 'DR-CON-001',
        'report_type' => 'after_return',
        'status' => 'draft',
        'inspected_at' => now(),
        'odometer' => 1250,
        'summary' => 'Return damage report.',
    ]);

    CarDamageItem::create([
        'tenant_id' => $tenant->id,
        'car_damage_report_id' => $contractDamageReport->id,
        'zone_code' => 'REAR',
        'view_side' => 'rear',
        'damage_type' => 'dent',
        'severity' => 'medium',
        'damage_timing' => 'after_return',
        'quantity' => 1,
        'marker_x' => 15,
        'marker_y' => 30,
        'estimated_cost' => 50,
        'notes' => 'Rear dent.',
        'sort_order' => 1,
    ]);

    CarDamageCase::create([
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'branch_id' => $branch->id,
        'opened_in_reservation_id' => $reservation->id,
        'last_report_id' => $reservationDamageReport->id,
        'created_by' => $admin->id,
        'zone_code' => 'FRONT',
        'view_side' => 'front',
        'damage_type' => 'scratch',
        'severity' => 'minor',
        'damage_timing' => 'before_delivery',
        'quantity' => 1,
        'marker_x' => 10,
        'marker_y' => 20,
        'estimated_cost' => 15,
        'notes' => 'Reservation case.',
        'status' => 'open',
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    CarDamageCase::create([
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'branch_id' => $branch->id,
        'opened_in_contract_id' => $contract->id,
        'last_report_id' => $contractDamageReport->id,
        'created_by' => $admin->id,
        'zone_code' => 'REAR',
        'view_side' => 'rear',
        'damage_type' => 'dent',
        'severity' => 'medium',
        'damage_timing' => 'after_return',
        'quantity' => 1,
        'marker_x' => 15,
        'marker_y' => 30,
        'estimated_cost' => 50,
        'notes' => 'Contract case.',
        'status' => 'open',
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    ContractReturnReport::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'contract_id' => $contract->id,
        'reservation_id' => $reservation->id,
        'car_id' => $car->id,
        'damage_report_id' => $contractDamageReport->id,
        'created_by' => $admin->id,
        'report_number' => 'RR-001',
        'status' => 'draft',
        'actual_return_time' => now(),
        'return_location' => 'Main Office',
        'return_odometer' => 1250,
        'return_fuel_level' => 'full',
        'vehicle_condition_after' => 'not_clean',
        'payment_status' => 'not_paid',
        'total_extra_charges' => 65,
        'notes' => 'Return report notes.',
    ]);

    Payment::create([
        'tenant_id' => $tenant->id,
        'reservation_id' => $reservation->id,
        'user_id' => $client->id,
        'amount' => 100,
        'currency' => 'USD',
        'payment_method' => PaymentMethod::CASH,
        'status' => PaymentStatus::COMPLETED,
        'processed_at' => now(),
        'notes' => 'Partial payment.',
    ]);

    CarViolation::create([
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'branch_id' => $branch->id,
        'reservation_id' => $reservation->id,
        'violation_number' => 'VIOL-001',
        'violation_date' => today()->toDateString(),
        'type' => 'speeding',
        'amount' => 25,
        'status' => CarViolationStatus::PENDING,
        'due_date' => today()->addDays(7)->toDateString(),
        'authority' => 'Police',
        'location' => 'City Center',
        'description' => 'Speeding ticket.',
        'notes' => 'Recorded for reservation.',
        'issued_to_user_id' => $client->id,
        'created_by' => $admin->id,
    ]);

    $response = $this->getJson(route('api.reservations.show', [
        'reservation' => $reservation->id,
    ]));

    $response->assertOk()
        ->assertJsonPath('reservation.reservation_number', 'RES-API-DET-001')
        ->assertJsonPath('reservation.amount_paid', 100)
        ->assertJsonPath('reservation.balance_due', 295)
        ->assertJsonPath('contract.contract_number', 'CON-API-DET-001')
        ->assertJsonPath('contract.return_status_report.report_number', 'RR-001')
        ->assertJsonPath('contract.finance_status.balance_due', 295)
        ->assertJsonCount(1, 'payments')
        ->assertJsonPath('payments.0.payment_method', PaymentMethod::CASH->value)
        ->assertJsonPath('damage_reports.0.report_number', 'DR-RES-001')
        ->assertJsonPath('contract_damage_reports.0.report_number', 'DR-CON-001')
        ->assertJsonPath('opened_damage_cases.0.zone_code', 'FRONT')
        ->assertJsonPath('contract_opened_damage_cases.0.zone_code', 'REAR')
        ->assertJsonPath('car_violations.0.violation_number', 'VIOL-001');
});

test('api requests without a token return a token not found message', function () {
    $response = $this->get('/api/reservations/11');

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Token not found.',
        ]);
});
