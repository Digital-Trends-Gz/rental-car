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
use App\Models\ContractHandoverPhoto;
use App\Models\ContractDriver;
use App\Models\ContractDriverDocument;
use App\Models\ContractReturnReport;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use App\Models\User;
use App\Services\Rentals\RentalStatusSyncService;
use App\Services\Contracts\ContractDriverDocumentExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    app()->instance(RentalStatusSyncService::class, new class {
        public function syncCarsByIds(array $carIds): void
        {
        }
    });

    app()->instance(ContractDriverDocumentExtractor::class, new class extends ContractDriverDocumentExtractor {
        public function __construct()
        {
        }

        public function extractFromTempFolders(array $tempFolders, string $documentType): array
        {
            return [
                'fields' => [
                    'full_name' => 'Extracted Customer',
                    'full_name_ar' => 'عميل مستخرج',
                    'document_number' => '99881122',
                    'date_of_birth' => today()->subYears(30)->toDateString(),
                    'nationality' => 'Jordanian',
                    'place_of_issue' => 'Amman',
                    'expiry_date' => today()->addYear()->toDateString(),
                ],
                'raw_output' => ['stub' => true, 'document_type' => $documentType],
                'raw_text' => 'stub preview',
                'confidence' => 0.95,
                'provider' => 'openai',
                'engine' => 'gpt-4.1-mini',
            ];
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

    TenantSiteSetting::create([
        'tenant_id' => $tenant->id,
        'reservation_settings' => [
            'fuel_pricing' => [
                [
                    'fuel_level' => 'quarter',
                    'price' => 20,
                ],
            ],
        ],
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

    TenantSiteSetting::create([
        'tenant_id' => $tenant->id,
        'reservation_settings' => [
            'fuel_pricing' => [
                [
                    'fuel_level' => 'quarter',
                    'price' => 20,
                ],
            ],
        ],
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
        'status' => ReservationStatus::ACTIVE->value,
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

    DB::table('files')->insert([
        'original_name' => 'Chevrolet Tahoe',
        'filename' => 'Chevrolet_Tahoe.webp',
        'path' => "files/car/{$car->id}/image/Chevrolet_Tahoe.webp",
        'mime_type' => 'image/webp',
        'size' => 100,
        'fileable_id' => $car->id,
        'fileable_type' => Car::class,
        'collection' => 'image',
        'order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
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
        ->assertJsonPath('reservation.car.image_url', url("storage/files/car/{$car->id}/image/Chevrolet_Tahoe.webp"))
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

test('handover api creates a draft contract and continues the wizard', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Handover Branch',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::SUPER_ADMIN,
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
        'model' => 'Land Cruiser',
        'year' => 2024,
        'license_plate' => 'HAND-001',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 150,
        'mileage' => 500,
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
        'reservation_number' => 'RES-HND-001',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'pickup_time' => '09:00',
        'return_time' => '18:00',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 2,
        'daily_rate' => 150,
        'subtotal' => 300,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 300,
        'status' => ReservationStatus::CONFIRMED,
    ]);

    Sanctum::actingAs($admin, ['*']);

    $showResponse = $this->getJson(route('api.reservations.handover', [
        'reservation' => $reservation->id,
    ]));

    $contractId = $showResponse->json('contract.id');

    $showResponse->assertOk()
        ->assertJsonPath('reservation.id', $reservation->id)
        ->assertJsonPath('contract.status', 'draft')
        ->assertJsonPath('contract.reservation.id', $reservation->id)
        ->assertJsonPath('reservation.client_status', 'active')
        ->assertJsonPath('handover.current_page', 1)
        ->assertJsonPath('handover.completed_pages', [])
        ->assertJsonPath('handover.steps.0.key', 'customer_review')
        ->assertJsonPath('handover.steps.0.page', 1)
        ->assertJsonPath('handover.steps.0.payload.reservation.client_status', 'active')
        ->assertJsonPath('handover.steps.1.key', 'report_upload')
        ->assertJsonPath('handover.steps.1.page', 2)
        ->assertJsonPath('handover.steps.1.payload.contract_inputs.renter_name', $client->name);

    $firstStepResponse = $this->patchJson(route('api.contracts.handover', [
        'contract' => $contractId,
    ]), [
        'page' => 1,
        'payload' => [
            'reviewed' => true,
            'notes' => 'Looks good.',
        ],
    ]);

    $firstStepResponse->assertOk()
        ->assertJsonPath('handover.current_page', 2)
        ->assertJsonPath('handover.completed_pages.0', 1)
        ->assertJsonPath('handover.steps.0.completed', true)
        ->assertJsonPath('handover.steps.0.payload.reviewed', true)
        ->assertJsonPath('handover.steps.0.payload.notes', 'Looks good.');

    Queue::fake();

      $secondStepResponse = $this->post(route('api.contracts.handover', [
          'contract' => $contractId,
      ]), [
          'page' => 2,
          'documents' => [
              [
                  'document_type' => 'passport',
                  'files' => [
                      UploadedFile::fake()->image('passport.jpg'),
                  ],
                  'notes' => 'Primary document.',
              ],
              [
                  'document_type' => 'id_card',
                  'files' => [
                      UploadedFile::fake()->image('id-card.jpg'),
                  ],
                  'notes' => 'Archive document.',
              ],
          ],
      ]);

      $secondStepResponse->assertOk()
        ->assertJsonPath('handover.current_page', 3)
        ->assertJsonPath('handover.completed_pages.0', 1)
          ->assertJsonPath('handover.completed_pages.1', 2)
          ->assertJsonPath('handover.steps.1.completed', true)
          ->assertJsonPath('handover.steps.1.key', 'report_upload')
          ->assertJsonPath('handover.steps.1.payload.extraction_status', 'pending')
          ->assertJsonPath('handover.steps.1.payload.documents.0.storage_target', 'primary_driver')
          ->assertJsonPath('handover.steps.1.payload.documents.1.storage_target', 'additional_archive')
          ->assertJsonPath('contract.ai_extraction_status', 'pending')
          ->assertJsonPath('extraction.status', 'pending');

      Queue::assertPushed(\App\Jobs\ProcessContractHandoverExtraction::class, function ($job) use ($contractId) {
          return $job->contractId === $contractId;
      });

      $contract = Contract::findOrFail($contractId);
      expect($contract->reservation_id)->toBe($reservation->id);
      expect($contract->ai_extraction_status)->toBe('pending');
      expect($contract->ai_extracted_data)->toBeNull();

      $contract->loadMissing(['primaryDriver.documents', 'archiveFiles', 'additionalDrivers']);
      $primaryDriver = $contract->primaryDriver()->first();
      expect($primaryDriver)->not->toBeNull();
      expect($primaryDriver?->documents)->toHaveCount(1);
      expect($primaryDriver?->documents->first()?->document_type)->toBe('passport');
      expect($primaryDriver?->documents->first()?->side)->toBe('single');
      expect($contract->archiveFiles)->toHaveCount(1);
      expect($contract->archiveFiles->first()?->document_type)->toBe('id_card');
      expect($contract->additionalDrivers)->toHaveCount(0);

      $thirdStepResponse = $this->post(route('api.contracts.handover', [
          'contract' => $contractId,
      ]), [
          'page' => 3,
          'photos' => [
              [
                  'view_side' => 'front',
                  'photo_type' => 'damage',
                  'files' => [
                      UploadedFile::fake()->image('front.jpg'),
                  ],
                  'notes' => 'Front view',
              ],
              [
                  'view_side' => 'front',
                  'photo_type' => 'odometer',
                  'files' => [
                      UploadedFile::fake()->image('odometer.jpg'),
                  ],
                  'notes' => 'Odometer photo',
              ],
              [
                  'view_side' => 'rear',
                  'photo_type' => 'fuel',
                  'files' => [
                      UploadedFile::fake()->image('rear.jpg'),
                  ],
                  'notes' => 'Fuel photo',
              ],
          ],
      ]);

      $thirdStepResponse->assertOk()
          ->assertJsonPath('handover.current_page', 4)
          ->assertJsonPath('handover.steps.2.key', 'damage_photo_upload')
          ->assertJsonPath('handover.steps.2.payload.extraction_status', 'pending')
          ->assertJsonPath('extraction.status', 'processing')
          ->assertJsonPath('extraction.damage_report.report_type', 'before_delivery')
          ->assertJsonPath('extraction.damage_report.source_type', 'ai');
      $thirdStepResponse->assertJsonPath('handover.steps.2.payload.photos.0.photo_type', 'damage')
          ->assertJsonPath('handover.steps.2.payload.handover_photos.0.storage_target', 'handover_archive')
          ->assertJsonPath('handover.steps.3.key', 'vehicle_readings')
          ->assertJsonPath('handover.steps.3.payload.vehicle_odometer', 500)
          ->assertJsonPath('handover.steps.3.payload.vehicle_fuel_level', null);

      Queue::assertPushed(\App\Jobs\ProcessContractDamagePhotoExtraction::class, function ($job) use ($contractId) {
          return $job->damageReportId > 0;
      });

      $damageReport = CarDamageReport::query()
          ->where('contract_id', $contractId)
          ->where('report_type', 'before_delivery')
          ->latest('id')
          ->first();

      expect($damageReport)->not->toBeNull();
      $damageReport?->loadMissing('items');
      expect($damageReport?->items)->toHaveCount(0);

      $contract->refresh()->loadMissing(['handoverPhotos', 'archiveFiles']);
      expect($contract->handoverPhotos)->toHaveCount(3);
      expect($contract->handoverPhotos->first()?->phase)->toBe('delivery');
      expect($contract->handoverPhotos->first()?->photo_type)->toBe('damage');
      expect($contract->handoverPhotos->where('photo_type', 'odometer')->count())->toBe(1);
      expect($contract->handoverPhotos->where('photo_type', 'fuel')->count())->toBe(1);

      $repeatResponse = $this->post(route('api.contracts.handover', [
          'contract' => $contractId,
      ]), [
          'page' => 3,
          'photos' => [
              [
                  'view_side' => 'front',
                  'photo_type' => 'damage',
                  'files' => [
                      UploadedFile::fake()->image('front-new.jpg'),
                  ],
                  'notes' => 'Front view updated',
              ],
              [
                  'view_side' => 'front',
                  'photo_type' => 'odometer',
                  'files' => [
                      UploadedFile::fake()->image('odometer-new.jpg'),
                  ],
                  'notes' => 'Odometer photo updated',
              ],
              [
                  'view_side' => 'rear',
                  'photo_type' => 'fuel',
                  'files' => [
                      UploadedFile::fake()->image('rear-new.jpg'),
                  ],
                  'notes' => 'Fuel photo updated',
              ],
          ],
      ]);

      $repeatResponse->assertOk();

      $fourthStepResponse = $this->patch(route('api.contracts.handover', [
          'contract' => $contractId,
      ]), [
          'page' => 4,
          'payload' => [
              'reviewed' => true,
              'vehicle_odometer' => 650,
              'vehicle_fuel_level' => '1/2',
              'notes' => 'Please handle with care.',
          ],
      ]);

      $fourthStepResponse->assertOk()
          ->assertJsonPath('handover.current_page', 5)
          ->assertJsonPath('handover.steps.3.key', 'vehicle_readings')
          ->assertJsonPath('handover.steps.3.payload.vehicle_odometer', 650)
          ->assertJsonPath('handover.steps.3.payload.vehicle_fuel_level', '1/2')
          ->assertJsonPath('handover.steps.3.payload.notes', 'Please handle with care.');

      $fourthStepResponse->assertJsonPath('handover.steps.4.key', 'terms_confirmation')
          ->assertJsonPath('handover.steps.4.payload.mobile_signature_text', 'Please review the contract details on mobile and confirm before signing.');

      $fifthStepResponse = $this->post(route('api.contracts.handover', [
          'contract' => $contractId,
      ]), [
          'page' => 5,
          'accepted_terms' => true,
      ]);

      $fifthStepResponse->assertOk()
          ->assertJsonPath('handover.current_page', 6)
          ->assertJsonPath('handover.steps.4.completed', true)
          ->assertJsonPath('handover.steps.4.payload.accepted_terms', true)
          ->assertJsonPath('handover.steps.4.payload.mobile_signature_text', 'Please review the contract details on mobile and confirm before signing.');

      $sixthStepResponse = $this->post(route('api.contracts.handover', [
          'contract' => $contractId,
      ]), [
          'page' => 6,
          'delivery_confirmed' => true,
          'summary' => 'Delivery damage reviewed.',
          'items' => [
              [
                  'zone_code' => 'hood',
                  'view_side' => 'front',
                  'damage_type' => 'scratch',
                  'severity' => 'minor',
                  'quantity' => 1,
                  'estimated_cost' => 120,
                  'notes' => 'Small scratch before delivery.',
              ],
              [
                  'zone_code' => 'front_bumper',
                  'view_side' => 'front',
                  'damage_type' => 'dent',
                  'severity' => 'moderate',
                  'quantity' => 1,
                  'estimated_cost' => 220,
                  'notes' => 'Front bumper dent before delivery.',
              ],
          ],
      ]);

      $sixthStepResponse->assertOk()
          ->assertJsonPath('handover.current_page', 7)
          ->assertJsonPath('handover.steps.5.key', 'damage_review')
          ->assertJsonPath('handover.steps.5.payload.damage_report.report_type', 'before_delivery')
          ->assertJsonPath('handover.steps.5.payload.damage_report.items_count', 2)
          ->assertJsonPath('handover.steps.5.payload.damage_report.items.0.zone_code', 'hood')
          ->assertJsonPath('handover.steps.5.payload.mobile_signature_text', 'Please review the contract details on mobile and confirm before signing.')
          ->assertJsonPath('contract.status', 'draft')
          ->assertJsonPath('reservation.status', 'confirmed')
          ->assertJsonPath('contract.car.status', 'available');

      $deliveryItems = $sixthStepResponse->json('handover.steps.5.payload.damage_report.items');
      $hoodItemId = $deliveryItems[0]['id'];
      $bumperItemId = $deliveryItems[1]['id'];

      $deliveryDamageUpdateResponse = $this->patchJson(route('api.contracts.handover', [
          'contract' => $contractId,
      ]), [
          'page' => 6,
          'delivery_confirmed' => true,
          'summary' => 'Delivery damage reviewed after edit.',
          'deleted_item_ids' => [$bumperItemId],
          'items' => [
              [
                  'id' => $hoodItemId,
                  'zone_code' => 'hood',
                  'view_side' => 'front',
                  'damage_type' => 'scratch',
                  'severity' => 'major',
                  'quantity' => 2,
                  'estimated_cost' => 300,
                  'notes' => 'Updated hood scratch before delivery.',
              ],
          ],
      ]);

      $deliveryDamageUpdateResponse->assertOk()
          ->assertJsonPath('handover.steps.5.payload.damage_report.summary', 'Delivery damage reviewed after edit.')
          ->assertJsonPath('handover.steps.5.payload.damage_report.items_count', 1)
          ->assertJsonPath('handover.steps.5.payload.damage_report.items.0.id', $hoodItemId)
          ->assertJsonPath('handover.steps.5.payload.damage_report.items.0.severity', 'major')
          ->assertJsonPath('handover.steps.5.payload.damage_report.items.0.quantity', 2);

      $seventhStepResponse = $this->post(route('api.contracts.handover', [
          'contract' => $contractId,
      ]), [
          'page' => 7,
          'delivery_confirmed' => true,
      ]);

      $seventhStepResponse->assertOk()
          ->assertJsonPath('handover.current_page', 7)
          ->assertJsonPath('handover.steps.6.key', 'delivery_confirmation')
          ->assertJsonPath('handover.steps.6.payload.delivery_confirmed', true)
          ->assertJsonPath('handover.steps.6.payload.contract_file.type', 'pdf')
          ->assertJsonPath('handover.steps.6.payload.contract_file.filename', 'CTR-'.now()->format('Ymd').'-0001-en-report.pdf')
          ->assertJsonPath('contract.status', 'active')
          ->assertJsonPath('reservation.status', 'active')
          ->assertJsonPath('contract.car.status', 'rented');

      expect($seventhStepResponse->json('handover.steps.6.payload.contract_file.api_url'))
          ->toContain('/api/contracts/'.$contractId.'/pdf');

      $contract->refresh()->loadMissing(['handoverPhotos', 'reservation.car', 'damageReports.items']);
      expect($contract->handoverPhotos)->toHaveCount(3);
      expect($contract->handoverPhotos->where('photo_type', 'damage')->first()?->notes)->toBe('Front view updated');
      expect($contract->vehicle_odometer)->toBe(650);
      expect($contract->vehicle_fuel_level)->toBe('1/2');
      expect($contract->notes)->toBe('Please handle with care.');
      expect($contract->reservation?->car?->mileage)->toBe(650);
      expect($contract->archiveFiles)->toHaveCount(1);
      expect($contract->damageReports->firstWhere('report_type', 'before_delivery')?->items)->toHaveCount(1);
  });

test('handover api supports a return wizard with review and inspection steps', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Return Branch',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::SUPER_ADMIN,
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
        'license_plate' => 'RET-001',
        'color' => CarColor::BLACK->value,
        'price_per_day' => 120,
        'mileage' => 84200,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::RENTED->value,
    ]);

    $reservation = Reservation::create([
        'tenant_id' => $tenant->id,
        'user_id' => $client->id,
        'car_id' => $car->id,
        'reservation_number' => 'RES-RET-001',
        'start_date' => today()->subDay()->toDateString(),
        'end_date' => today()->toDateString(),
        'pickup_time' => '09:00',
        'return_time' => '18:00',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 2,
        'daily_rate' => 120,
        'subtotal' => 240,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 240,
        'status' => ReservationStatus::ACTIVE,
    ]);

    $contract = Contract::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'reservation_id' => $reservation->id,
        'contract_number' => 'CON-RET-001',
        'status' => ContractStatus::ACTIVE->value,
        'contract_date' => today()->subDay()->toDateString(),
        'start_date' => today()->subDay()->toDateString(),
        'end_date' => today()->toDateString(),
        'vehicle_odometer' => 84200,
        'vehicle_fuel_level' => '1/2',
    ]);

    Sanctum::actingAs($admin, ['*']);

    $showResponse = $this->getJson(route('api.reservations.handover', [
        'reservation' => $reservation->id,
        'phase' => 'return',
    ]));

    $showResponse->assertOk()
        ->assertJsonPath('phase', 'return')
        ->assertJsonPath('phase_label', 'Return')
        ->assertJsonPath('handover.phase', 'return')
        ->assertJsonPath('handover.current_page', 1)
        ->assertJsonPath('handover.steps.0.key', 'customer_review')
        ->assertJsonPath('handover.steps.1.key', 'return_inspection')
        ->assertJsonPath('handover.steps.2.key', 'vehicle_readings');

    $firstStepResponse = $this->patchJson(route('api.contracts.handover', [
        'contract' => $contract->id,
    ]), [
        'phase' => 'return',
        'page' => 1,
        'payload' => [
            'reviewed' => true,
            'notes' => 'Return review completed.',
        ],
    ]);

    $firstStepResponse->assertOk()
        ->assertJsonPath('phase', 'return')
        ->assertJsonPath('handover.current_page', 2)
        ->assertJsonPath('handover.steps.0.completed', true)
        ->assertJsonPath('handover.steps.0.payload.reviewed', true)
        ->assertJsonPath('handover.steps.0.payload.notes', 'Return review completed.')
        ->assertJsonPath('handover.steps.0.payload.note', 'Return review completed.');

    $beforeDeliveryReturnReport = CarDamageReport::create([
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'branch_id' => $branch->id,
        'contract_id' => $contract->id,
        'reservation_id' => $reservation->id,
        'created_by' => $admin->id,
        'report_number' => 'DR-RET-BEFORE-1',
        'report_type' => 'before_delivery',
        'status' => 'finalized',
        'inspected_at' => now()->subDay(),
        'odometer' => 84200,
        'summary' => 'Pre-delivery inspection.',
    ]);

    CarDamageItem::create([
        'tenant_id' => $tenant->id,
        'car_damage_report_id' => $beforeDeliveryReturnReport->id,
        'zone_code' => 'hood',
        'view_side' => 'front',
        'damage_type' => 'dent',
        'severity' => 'minor',
        'quantity' => 1,
        'estimated_cost' => 150,
        'sort_order' => 1,
    ]);

    Queue::fake();

    $secondStepResponse = $this->post(route('api.contracts.handover', [
        'contract' => $contract->id,
    ]), [
        'phase' => 'return',
        'page' => 2,
        'photos' => [
            [
                'view_side' => 'front',
                'photo_type' => 'damage',
                'files' => [
                    UploadedFile::fake()->image('return-front.jpg'),
                ],
                'notes' => 'Front scratch',
            ],
        ],
    ]);

      $secondStepResponse->assertOk()
          ->assertJsonPath('phase', 'return')
          ->assertJsonPath('handover.current_page', 3)
          ->assertJsonPath('handover.steps.1.key', 'return_inspection')
          ->assertJsonPath('extraction.status', 'processing')
          ->assertJsonPath('extraction.damage_report.report_type', 'after_return')
          ->assertJsonPath('extraction.damage_report.comparison.against_report.report_number', 'DR-RET-BEFORE-1')
          ->assertJsonPath('extraction.damage_report.comparison.items_only_in_against.0.zone_code', 'hood')
          ->assertJsonPath('extraction.damage_report.comparison.merged_items.0.zone_code', 'hood');

      Queue::assertPushed(\App\Jobs\ProcessContractDamagePhotoExtraction::class, function ($job): bool {
          return $job->damageReportId > 0;
      });

      $thirdStepResponse = $this->post(route('api.contracts.handover', [
          'contract' => $contract->id,
      ]), [
          'phase' => 'return',
          'page' => 3,
        'return_odometer' => 84520,
        'return_fuel_level' => '1/2',
        'notes' => 'Return readings confirmed.',
    ]);

    $thirdStepResponse->assertOk()
        ->assertJsonPath('phase', 'return')
        ->assertJsonPath('handover.current_page', 4)
        ->assertJsonPath('handover.steps.2.key', 'vehicle_readings')
        ->assertJsonPath('handover.steps.2.payload.return_odometer', 84520)
        ->assertJsonPath('handover.steps.2.payload.return_fuel_level', '1/2')
        ->assertJsonPath('handover.steps.2.payload.notes', 'Return readings confirmed.')
        ->assertJsonPath('extraction.status', 'extracted')
        ->assertJsonPath('extraction.vehicle_readings.return_odometer', 84520)
        ->assertJsonPath('extraction.vehicle_readings.return_fuel_level', '1/2');

      $fourthStepResponse = $this->patchJson(route('api.contracts.handover', [
          'contract' => $contract->id,
      ]), [
          'phase' => 'return',
          'page' => 4,
          'summary' => 'Return damage reviewed and updated.',
          'items' => [
              [
                  'zone_code' => 'hood',
                  'view_side' => 'front',
                  'damage_type' => 'dent',
                  'severity' => 'major',
                  'quantity' => 1,
                'estimated_cost' => 250,
                'notes' => 'Hood dent updated.',
            ],
            [
                'zone_code' => 'front_bumper',
                'view_side' => 'front',
                'damage_type' => 'scratch',
                'severity' => 'minor',
                'quantity' => 1,
                'estimated_cost' => 120,
                'notes' => 'New bumper scratch.',
            ],
        ],
    ]);

      $fourthStepResponse->assertOk()
          ->assertJsonPath('phase', 'return')
          ->assertJsonPath('handover.current_page', 5)
          ->assertJsonPath('handover.steps.3.key', 'damage_review')
          ->assertJsonPath('handover.steps.3.payload.summary', 'Return damage reviewed and updated.')
          ->assertJsonPath('handover.steps.3.payload.items.0.zone_code', 'hood')
          ->assertJsonPath('handover.steps.3.payload.items.1.zone_code', 'front_bumper')
          ->assertJsonPath('handover.steps.3.payload.final_summary.damage_fee', 370)
          ->assertJsonPath('handover.steps.3.payload.final_summary.total_extra_charges', 370)
          ->assertJsonPath('extraction.status', 'extracted')
          ->assertJsonPath('extraction.damage_report.report_type', 'after_return')
          ->assertJsonPath('extraction.damage_report.items.0.zone_code', 'hood')
          ->assertJsonPath('extraction.damage_report.items.1.zone_code', 'front_bumper')
          ->assertJsonPath('extraction.final_summary.damage_fee', 370)
          ->assertJsonPath('extraction.final_summary.total_extra_charges', 370);

      $hoodItemId = (int) $fourthStepResponse->json('extraction.damage_report.items.0.id');
      $frontBumperItemId = (int) $fourthStepResponse->json('extraction.damage_report.items.1.id');
      expect($hoodItemId)->toBeGreaterThan(0);
      expect($frontBumperItemId)->toBeGreaterThan(0);

      $finalStepResponse = $this->patchJson(route('api.contracts.handover', [
          'contract' => $contract->id,
      ]), [
          'phase' => 'return',
          'page' => 4,
          'summary' => 'Return damage reviewed after deletion.',
          'items' => [
              [
                  'id' => $hoodItemId,
                  'zone_code' => 'hood',
                  'view_side' => 'front',
                  'damage_type' => 'dent',
                  'severity' => 'major',
                  'quantity' => 2,
                  'estimated_cost' => 300,
                  'notes' => 'Hood dent adjusted again.',
              ],
          ],
          'deleted_item_ids' => [$frontBumperItemId],
      ]);

      $finalStepResponse->assertOk()
          ->assertJsonPath('phase', 'return')
          ->assertJsonPath('handover.current_page', 5)
          ->assertJsonPath('handover.steps.3.key', 'damage_review')
          ->assertJsonPath('handover.steps.3.payload.summary', 'Return damage reviewed after deletion.')
          ->assertJsonPath('handover.steps.3.payload.items.0.id', $hoodItemId)
          ->assertJsonPath('handover.steps.3.payload.items.0.quantity', 2)
          ->assertJsonPath('handover.steps.3.payload.final_summary.damage_fee', 300)
          ->assertJsonPath('handover.steps.3.payload.final_summary.total_extra_charges', 300)
          ->assertJsonPath('extraction.status', 'extracted')
          ->assertJsonPath('extraction.damage_report.items.0.id', $hoodItemId)
          ->assertJsonPath('extraction.damage_report.items.0.quantity', 2)
          ->assertJsonPath('extraction.final_summary.damage_fee', 300)
          ->assertJsonPath('extraction.final_summary.total_extra_charges', 300);

      $summaryStepResponse = $this->patchJson(route('api.contracts.handover', [
          'contract' => $contract->id,
      ]), [
          'phase' => 'return',
          'page' => 5,
          'payment_status' => 'not_paid',
          'discount' => 50,
      ]);

      $summaryStepResponse->assertOk()
          ->assertJsonPath('phase', 'return')
          ->assertJsonPath('handover.current_page', 6)
          ->assertJsonPath('handover.steps.4.key', 'final_summary')
          ->assertJsonPath('handover.steps.4.payload.final_summary.damage_fee', 300)
          ->assertJsonPath('handover.steps.4.payload.final_summary.discount', 50)
          ->assertJsonPath('handover.steps.4.payload.final_summary.total_extra_charges', 250)
          ->assertJsonPath('handover.steps.4.payload.return_status_report.discount', '50.00')
          ->assertJsonPath('extraction.final_summary.damage_fee', 300)
          ->assertJsonPath('extraction.final_summary.discount', 50)
          ->assertJsonPath('extraction.final_summary.total_extra_charges', 250)
          ->assertJsonPath('extraction.return_status_report.discount', '50.00');

      $confirmationStepResponse = $this->patchJson(route('api.contracts.handover', [
          'contract' => $contract->id,
      ]), [
          'phase' => 'return',
          'page' => 6,
          'return_confirmed' => true,
          'payment_status' => 'not_paid',
      ]);

      $confirmationStepResponse->assertOk()
          ->assertJsonPath('phase', 'return')
          ->assertJsonPath('handover.current_page', 6)
          ->assertJsonPath('handover.steps.5.key', 'return_confirmation')
          ->assertJsonPath('handover.steps.5.payload.return_confirmed', true)
          ->assertJsonPath('handover.steps.5.payload.contract_status', ContractStatus::COMPLETED->value)
          ->assertJsonPath('handover.steps.5.payload.reservation_status', ReservationStatus::COMPLETED->value)
          ->assertJsonPath('handover.steps.5.payload.car_status', CarStatus::AVAILABLE->value)
          ->assertJsonPath('handover.steps.5.payload.return_status_report.status', 'finalized')
          ->assertJsonPath('handover.steps.5.payload.return_status_report_file.type', 'pdf')
          ->assertJsonPath('handover.steps.5.payload.return_status_report_file.filename', 'RTR-'.now()->format('Ymd').'-0001-en-invoice.pdf')
          ->assertJsonPath('handover.steps.5.payload.final_summary.total_extra_charges', 250)
          ->assertJsonPath('extraction.status', 'finalized')
          ->assertJsonPath('extraction.return_status_report.status', 'finalized')
          ->assertJsonPath('extraction.return_status_report_file.type', 'pdf')
          ->assertJsonPath('extraction.final_summary.total_extra_charges', 250);

      expect($confirmationStepResponse->json('handover.steps.5.payload.return_status_report_file.api_url'))
          ->toContain('/api/contracts/'.$contract->id.'/return-status-report/pdf');

      $contract->refresh()->loadMissing(['handoverPhotos', 'reservation.car', 'returnStatusReport']);
      expect($contract->return_odometer)->toBe(84520);
      expect($contract->return_fuel_level)->toBe('1/2');
      expect($contract->status)->toBe(ContractStatus::COMPLETED);
      expect($contract->reservation?->status)->toBe(ReservationStatus::COMPLETED);
      expect($contract->reservation?->car?->status)->toBe(CarStatus::AVAILABLE);
      expect($contract->returnStatusReport?->status)->toBe('finalized');
      expect((float) $contract->returnStatusReport?->total_extra_charges)->toBe(250.0);
      expect($contract->returnStatusReport?->payment_id)->not()->toBeNull();
      expect(Payment::query()->find($contract->returnStatusReport?->payment_id)?->status)->toBe(PaymentStatus::PENDING);
      expect($contract->notes)->toBe('Return review completed.');
      expect($contract->handoverPhotos)->toHaveCount(1);
      expect($contract->handoverPhotos->first()?->phase)->toBe('return');
      expect($contract->handoverPhotos->first()?->photo_type)->toBe('damage');
      expect($contract->reservation?->car?->mileage)->toBe(84520);
      $returnDamageReport = $contract->damageReports->first(fn (CarDamageReport $report): bool => $report->report_type === 'after_return');
      expect($returnDamageReport)->not()->toBeNull();
      expect($returnDamageReport?->items)->toHaveCount(1);
      expect($returnDamageReport?->items->first()?->id)->toBe($hoodItemId);
      expect($returnDamageReport?->items->first()?->quantity)->toBe(2);
  });

test('return handover page four can skip damage report creation when there is no damage', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Return Branch',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::SUPER_ADMIN,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $client = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::CLIENT,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    TenantSiteSetting::create([
        'tenant_id' => $tenant->id,
        'reservation_settings' => [
            'fuel_pricing' => [
                [
                    'fuel_level' => 'quarter',
                    'price' => 20,
                ],
            ],
        ],
    ]);

    $car = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'make' => 'Toyota',
        'model' => 'Corolla',
        'year' => 2024,
        'license_plate' => 'RET-SKIP-001',
        'color' => CarColor::SILVER->value,
        'price_per_day' => 95,
        'mileage' => 12000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::RENTED->value,
    ]);

    $reservation = Reservation::create([
        'tenant_id' => $tenant->id,
        'user_id' => $client->id,
        'car_id' => $car->id,
        'reservation_number' => 'RES-RET-SKIP-001',
        'start_date' => today()->subDay()->toDateString(),
        'end_date' => today()->toDateString(),
        'pickup_time' => '09:00',
        'return_time' => '18:00',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 2,
        'daily_rate' => 95,
        'subtotal' => 190,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 190,
        'status' => ReservationStatus::ACTIVE,
    ]);

    $contract = Contract::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'reservation_id' => $reservation->id,
        'contract_number' => 'CON-RET-SKIP-001',
        'status' => ContractStatus::ACTIVE->value,
        'contract_date' => today()->subDay()->toDateString(),
        'start_date' => today()->subDay()->toDateString(),
        'end_date' => today()->toDateString(),
        'vehicle_odometer' => 12000,
        'vehicle_fuel_level' => '1/2',
        'return_fuel_level' => '1/4',
    ]);

    $existingDamageReport = CarDamageReport::create([
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'branch_id' => $branch->id,
        'contract_id' => $contract->id,
        'reservation_id' => $reservation->id,
        'created_by' => $admin->id,
        'report_number' => 'DMG-RET-SKIP-001',
        'report_type' => 'after_return',
        'status' => 'draft',
        'inspected_at' => now(),
        'odometer' => 12000,
        'summary' => 'Draft return damage report.',
    ]);

    Sanctum::actingAs($admin, ['*']);

    $response = $this->patchJson(route('api.contracts.handover', [
        'contract' => $contract->id,
    ]), [
        'phase' => 'return',
        'page' => 4,
        'has_damage' => false,
        'discount' => 5,
        'vehicle_condition_after' => 'clean',
        'notes' => 'No visible damage on return.',
    ]);

    $response->assertOk()
        ->assertJsonPath('phase', 'return')
        ->assertJsonPath('handover.current_page', 5)
        ->assertJsonPath('handover.steps.3.key', 'damage_review')
        ->assertJsonPath('handover.steps.3.payload.has_damage', false)
        ->assertJsonPath('handover.steps.3.payload.damage_report_status', 'skipped')
        ->assertJsonPath('handover.steps.3.payload.final_summary.damage_report_status', 'skipped')
        ->assertJsonPath('handover.steps.3.payload.final_summary.fuel_fee', 20)
        ->assertJsonPath('handover.steps.3.payload.final_summary.discount', 5)
        ->assertJsonPath('handover.steps.3.payload.final_summary.total_extra_charges', 15)
        ->assertJsonPath('extraction.damage_report_status', 'skipped')
        ->assertJsonPath('extraction.damage_report', null)
        ->assertJsonPath('extraction.final_summary.damage_report_status', 'skipped')
        ->assertJsonPath('extraction.final_summary.fuel_fee', 20)
        ->assertJsonPath('extraction.final_summary.discount', 5)
        ->assertJsonPath('extraction.final_summary.total_extra_charges', 15);

    $contract->refresh()->loadMissing(['damageReports', 'returnStatusReport']);

    expect($contract->returnStatusReport?->has_damage)->toBeFalse();
    expect($contract->damageReports->firstWhere('report_type', 'after_return'))->toBeNull();
    expect($existingDamageReport->fresh())->toBeNull();
});

test('damage report status api returns pending until the return damage report is ready', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Return Branch',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::SUPER_ADMIN,
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
        'model' => 'RAV4',
        'year' => 2024,
        'license_plate' => 'RET-STATUS-001',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 110,
        'mileage' => 25000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::RENTED->value,
    ]);

    $reservation = Reservation::create([
        'tenant_id' => $tenant->id,
        'user_id' => $client->id,
        'car_id' => $car->id,
        'reservation_number' => 'RES-RET-STATUS-001',
        'start_date' => today()->subDay()->toDateString(),
        'end_date' => today()->toDateString(),
        'pickup_time' => '09:00',
        'return_time' => '18:00',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 2,
        'daily_rate' => 110,
        'subtotal' => 220,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 220,
        'status' => ReservationStatus::ACTIVE,
    ]);

    $contract = Contract::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'reservation_id' => $reservation->id,
        'contract_number' => 'CON-RET-STATUS-001',
        'status' => ContractStatus::ACTIVE->value,
        'contract_date' => today()->subDay()->toDateString(),
        'start_date' => today()->subDay()->toDateString(),
        'end_date' => today()->toDateString(),
        'vehicle_odometer' => 25000,
        'vehicle_fuel_level' => '1/2',
    ]);

    Sanctum::actingAs($admin, ['*']);

    $pendingResponse = $this->getJson(route('api.contracts.damage-report-status', [
        'contract' => $contract->id,
        'phase' => 'return',
    ]));

    $pendingResponse->assertOk()
        ->assertJsonPath('damage_report_status', 'pending')
        ->assertJsonPath('damage_report', null);

    $beforeDeliveryReport = CarDamageReport::create([
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'branch_id' => $branch->id,
        'contract_id' => $contract->id,
        'reservation_id' => $reservation->id,
        'created_by' => $admin->id,
        'report_number' => 'DMG-DEL-STATUS-001',
        'report_type' => 'before_delivery',
        'status' => 'draft',
        'inspected_at' => now()->subDay(),
        'odometer' => 24000,
        'summary' => 'Before delivery damage report ready.',
    ]);

    CarDamageItem::create([
        'tenant_id' => $tenant->id,
        'car_damage_report_id' => $beforeDeliveryReport->id,
        'zone_code' => 'front_bumper',
        'view_side' => 'front',
        'damage_type' => 'dent',
        'severity' => 'moderate',
        'damage_timing' => 'before_pickup',
        'quantity' => 1,
        'estimated_cost' => 125,
        'sort_order' => 1,
    ]);

    $beforeDeliveryResponse = $this->getJson(route('api.contracts.damage-report-status', [
        'contract' => $contract->id,
        'phase' => 'return',
        'report_type' => 'before_delivery',
    ]));

    $beforeDeliveryResponse->assertOk()
        ->assertJsonPath('phase', 'return')
        ->assertJsonPath('damage_report_status', 'done')
        ->assertJsonPath('damage_report_type', 'before_delivery')
        ->assertJsonPath('damage_report.report_number', 'DMG-DEL-STATUS-001')
        ->assertJsonPath('damage_report.items.0.zone_code', 'front_bumper');

    $damageReport = CarDamageReport::create([
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'branch_id' => $branch->id,
        'contract_id' => $contract->id,
        'reservation_id' => $reservation->id,
        'created_by' => $admin->id,
        'report_number' => 'DMG-RET-STATUS-001',
        'report_type' => 'after_return',
        'status' => 'draft',
        'inspected_at' => now(),
        'odometer' => 25000,
        'summary' => 'Return damage report ready.',
    ]);

    CarDamageItem::create([
        'tenant_id' => $tenant->id,
        'car_damage_report_id' => $damageReport->id,
        'zone_code' => 'hood',
        'view_side' => 'front',
        'damage_type' => 'scratch',
        'severity' => 'minor',
        'quantity' => 1,
        'estimated_cost' => 75,
        'sort_order' => 1,
    ]);

    $readyResponse = $this->getJson(route('api.contracts.damage-report-status', [
        'contract' => $contract->id,
        'phase' => 'return',
    ]));

    $readyResponse->assertOk()
        ->assertJsonPath('damage_report_status', 'done')
        ->assertJsonPath('damage_report.report_number', 'DMG-RET-STATUS-001')
        ->assertJsonPath('damage_report.items.0.zone_code', 'hood');
});

test('damage option APIs return localized select options', function () {
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

    $response = $this
        ->withHeader('Accept-Language', 'en')
        ->getJson(route('api.contracts.damage-options'));

    $response->assertOk()
        ->assertJsonPath('locale', 'en')
        ->assertJsonPath('data.zones.0.value', 'front_bumper')
        ->assertJsonPath('data.damage_types.0.value', 'scratch')
        ->assertJsonPath('data.damage_types.0.label', 'Scratch')
        ->assertJsonPath('data.severity_levels.0.value', 'minor')
        ->assertJsonPath('data.damage_timings.0.value', 'before_pickup')
        ->assertJsonPath('data.view_sides.0.value', 'front');

    $groupResponse = $this
        ->withHeader('Accept-Language', 'ar')
        ->getJson(route('api.contracts.damage-options.group', [
            'group' => 'damage-types',
        ]));

    $groupResponse->assertOk()
        ->assertJsonPath('locale', 'ar')
        ->assertJsonPath('group', 'damage_types')
        ->assertJsonPath('data.0.value', 'scratch');

    expect($groupResponse->json('data.0.label'))->not()->toBe('Scratch');
});

test('handover api accepts direct uploaded files for document extraction', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Upload Branch',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::SUPER_ADMIN,
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
        'model' => 'Corolla',
        'year' => 2024,
        'license_plate' => 'UPL-001',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 90,
        'mileage' => 700,
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
        'reservation_number' => 'RES-UPL-001',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'pickup_time' => '09:00',
        'return_time' => '18:00',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 2,
        'daily_rate' => 90,
        'subtotal' => 180,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 180,
        'status' => ReservationStatus::CONFIRMED,
    ]);

    Sanctum::actingAs($admin, ['*']);

    $showResponse = $this->getJson(route('api.reservations.handover', [
        'reservation' => $reservation->id,
    ]));

    $contractId = $showResponse->json('contract.id');

    Queue::fake();

      $uploadResponse = $this->post(route('api.contracts.handover', [
          'contract' => $contractId,
      ]), [
          'page' => 2,
          'documents' => [
            [
                'document_type' => 'passport',
                'files' => [
                    UploadedFile::fake()->image('passport.jpg'),
                ],
                'notes' => 'Passport image upload',
            ],
            [
                'document_type' => 'id_card',
                'files' => [
                    UploadedFile::fake()->image('id-card.jpg'),
                ],
                'notes' => 'ID card image upload',
            ],
        ],
      ]);

      $uploadResponse->assertOk()
          ->assertJsonPath('handover.steps.1.payload.documents.0.document_type', 'passport')
          ->assertJsonPath('handover.steps.1.payload.documents.1.document_type', 'id_card')
          ->assertJsonPath('handover.steps.1.payload.documents.0.storage_target', 'primary_driver')
          ->assertJsonPath('handover.steps.1.payload.documents.1.storage_target', 'additional_archive')
          ->assertJsonPath('handover.steps.1.payload.extraction_status', 'pending')
          ->assertJsonPath('contract.ai_extraction_status', 'pending')
          ->assertJsonPath('extraction.status', 'pending');

      Queue::assertPushed(\App\Jobs\ProcessContractHandoverExtraction::class, function ($job) use ($contractId) {
          return $job->contractId === $contractId;
      });

      $contract = Contract::findOrFail($contractId);
      expect($contract->ai_extraction_status)->toBe('pending');
      expect($contract->ai_extracted_data)->toBeNull();

      $contract->loadMissing(['primaryDriver.documents', 'archiveFiles', 'additionalDrivers']);
      $primaryDriver = $contract->primaryDriver()->first();
      expect($primaryDriver)->not->toBeNull();
      expect($primaryDriver?->documents)->toHaveCount(1);
      expect($primaryDriver?->documents->first()?->document_type)->toBe('passport');
      expect($primaryDriver?->documents->first()?->side)->toBe('single');
      expect($contract->archiveFiles)->toHaveCount(1);
      expect($contract->archiveFiles->first()?->document_type)->toBe('id_card');
      expect($contract->additionalDrivers)->toHaveCount(0);
  });

test('damage item updates mark ai damage items as employee sourced', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Damage Branch',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::SUPER_ADMIN,
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
        'model' => 'Corolla',
        'year' => 2024,
        'license_plate' => 'DAMAGE-001',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 90,
        'mileage' => 700,
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
        'reservation_number' => 'RES-DAMAGE-001',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'pickup_time' => '09:00',
        'return_time' => '18:00',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 2,
        'daily_rate' => 90,
        'subtotal' => 180,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 180,
        'status' => ReservationStatus::CONFIRMED->value,
    ]);

    $contract = Contract::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'reservation_id' => $reservation->id,
        'contract_number' => 'CTR-DAMAGE-001',
        'status' => ContractStatus::DRAFT->value,
        'contract_date' => today()->toDateString(),
        'renter_name' => $client->name,
        'renter_id_number' => '123456789',
        'renter_phone' => '97000000000',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'total_amount' => 180,
        'currency' => 'USD',
    ]);

    $damageReport = CarDamageReport::create([
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'branch_id' => $branch->id,
        'contract_id' => $contract->id,
        'reservation_id' => $reservation->id,
        'created_by' => $admin->id,
        'source_type' => CarDamageReport::SOURCE_TYPE_AI,
        'report_number' => 'DR-AI-001',
        'report_type' => 'before_delivery',
        'status' => 'draft',
        'inspected_at' => now(),
        'odometer' => 700,
        'summary' => 'AI generated draft.',
    ]);

    $damageItem = CarDamageItem::create([
        'tenant_id' => $tenant->id,
        'car_damage_report_id' => $damageReport->id,
        'source_type' => CarDamageItem::SOURCE_TYPE_AI,
        'zone_code' => 'hood',
        'view_side' => 'front',
        'damage_type' => 'scratch',
        'severity' => 'minor',
        'damage_timing' => 'before_pickup',
        'quantity' => 1,
        'estimated_cost' => 120,
        'sort_order' => 1,
        'notes' => 'Initial AI item.',
    ]);

    Sanctum::actingAs($admin, ['*']);

    $response = $this->patchJson(route('api.contracts.damage-items.update', [
        'contract' => $contract->id,
        'damageItem' => $damageItem->id,
    ]), [
        'zone_code' => 'hood',
        'view_side' => 'front',
        'damage_type' => 'scratch',
        'severity' => 'major',
        'damage_timing' => 'before_pickup',
        'quantity' => 2,
        'estimated_cost' => 300,
        'notes' => 'Edited by employee.',
    ]);

    $response->assertOk()
        ->assertJsonPath('item.source_type', 'employee')
        ->assertJsonPath('item.severity', 'major')
        ->assertJsonPath('item.quantity', 2);

    $damageItem->refresh();
    expect($damageItem->source_type)->toBe(CarDamageItem::SOURCE_TYPE_EMPLOYEE);
});

test('ai damage photo extraction creates ai sourced damage items', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'AI Branch',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::SUPER_ADMIN,
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
        'model' => 'Corolla',
        'year' => 2024,
        'license_plate' => 'AI-001',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 90,
        'mileage' => 700,
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
        'reservation_number' => 'RES-AI-001',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'pickup_time' => '09:00',
        'return_time' => '18:00',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 2,
        'daily_rate' => 90,
        'subtotal' => 180,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 180,
        'status' => ReservationStatus::CONFIRMED->value,
    ]);

    $contract = Contract::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'reservation_id' => $reservation->id,
        'contract_number' => 'CTR-AI-001',
        'status' => ContractStatus::DRAFT->value,
        'contract_date' => today()->toDateString(),
        'renter_name' => $client->name,
        'renter_id_number' => '123456789',
        'renter_phone' => '97000000000',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'total_amount' => 180,
        'currency' => 'USD',
    ]);

    $damageReport = CarDamageReport::create([
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'branch_id' => $branch->id,
        'contract_id' => $contract->id,
        'reservation_id' => $reservation->id,
        'created_by' => $admin->id,
        'source_type' => CarDamageReport::SOURCE_TYPE_AI,
        'report_number' => 'DR-AI-002',
        'report_type' => 'before_delivery',
        'status' => 'draft',
        'inspected_at' => now(),
        'odometer' => 700,
        'summary' => 'AI generated draft.',
    ]);

    ContractHandoverPhoto::create([
        'tenant_id' => $tenant->id,
        'contract_id' => $contract->id,
        'damage_report_id' => $damageReport->id,
        'phase' => 'delivery',
        'photo_type' => 'damage',
        'view_side' => 'front',
        'title' => 'Damage Photo',
        'notes' => 'AI photo upload',
        'file_path' => 'storage/damage-photo.jpg',
        'file_name' => 'damage-photo.jpg',
        'mime_type' => 'image/jpeg',
    ]);

    app()->instance(\App\Services\Contracts\ContractDamagePhotoExtractor::class, new class extends \App\Services\Contracts\ContractDamagePhotoExtractor {
        public function extractFromPhotoGroups(array $photoGroups, string $reportType = 'before_delivery'): array
        {
            return [
                'items' => [
                    [
                        'zone_code' => 'hood',
                        'view_side' => 'front',
                        'damage_type' => 'scratch',
                        'severity' => 'minor',
                        'damage_timing' => 'before_pickup',
                        'quantity' => 1,
                        'estimated_cost' => 120,
                        'notes' => 'Detected by AI.',
                    ],
                ],
                'summary' => 'AI detected damage.',
                'vehicle_readings' => [],
                'raw_output' => ['stub' => true],
                'raw_text' => 'stub',
                'confidence' => 0.9,
                'provider' => 'openai',
                'engine' => 'gpt-4.1-mini',
            ];
        }
    });

    $job = new \App\Jobs\ProcessContractDamagePhotoExtraction($damageReport->id);
    $job->handle(app(\App\Services\Contracts\ContractDamagePhotoExtractor::class));

    $createdItems = CarDamageItem::withoutTenantScope()
        ->where('car_damage_report_id', $damageReport->id)
        ->get();

    expect($createdItems)->toHaveCount(1);
    expect($createdItems->first()?->source_type)->toBe(CarDamageItem::SOURCE_TYPE_AI);
});

test('api requests without a token return a token not found message', function () {
    $response = $this->get('/api/reservations/11');

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Token not found.',
        ]);
});
