<?php

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\ContractStatus;
use App\Enums\DiscountRequestStatus;
use App\Enums\FuelType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Contract;
use App\Models\ContractReturnReport;
use App\Models\DiscountRequest;
use App\Models\Payment;
use App\Models\Plan;
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

test('api can create a pending return report discount request by contract', function () {
    $fixtures = createDiscountRequestApiFixtures('RES-DISC-001', 500);
    Payment::create([
        'tenant_id' => $fixtures['tenant']->id,
        'reservation_id' => $fixtures['reservation']->id,
        'user_id' => $fixtures['client']->id,
        'amount' => 50,
        'currency' => 'USD',
        'payment_method' => PaymentMethod::CASH,
        'status' => PaymentStatus::COMPLETED,
        'processed_at' => now(),
        'gateway_data' => [
            'cash_source' => [
                'type' => 'contract_return_report',
                'id' => $fixtures['returnReport']->id,
            ],
        ],
    ]);

    Sanctum::actingAs($fixtures['admin'], ['*']);

    $response = $this->postJson(route('api.contracts.return-report.discount-requests.store', [
        'contract' => $fixtures['contract']->id,
    ]), [
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'reason' => 'Customer requested a return report discount.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('discount_request.status', DiscountRequestStatus::PENDING->value)
        ->assertJsonPath('discount_request.contract_id', $fixtures['contract']->id)
        ->assertJsonPath('discount_request.return_report_id', $fixtures['returnReport']->id)
        ->assertJsonPath('discount_request.base_amount', 150)
        ->assertJsonPath('discount_request.discount_type', 'percentage')
        ->assertJsonPath('discount_request.discount_value', 10)
        ->assertJsonPath('discount_request.discount_amount', 15)
        ->assertJsonPath('discount_request.final_amount', 135);

    expect(DiscountRequest::query()->count())->toBe(1);
});

test('api rejects a second pending discount request for the same return report', function () {
    $fixtures = createDiscountRequestApiFixtures('RES-DISC-002', 500);
    DiscountRequest::create([
        'tenant_id' => $fixtures['tenant']->id,
        'reservation_id' => $fixtures['reservation']->id,
        'contract_id' => $fixtures['contract']->id,
        'contract_return_report_id' => $fixtures['returnReport']->id,
        'requested_by_user_id' => $fixtures['admin']->id,
        'base_amount' => 200,
        'discount_type' => 'fixed',
        'discount_value' => 25,
        'discount_amount' => 25,
        'final_amount' => 175,
        'reason' => 'Existing pending request.',
        'status' => DiscountRequestStatus::PENDING,
    ]);

    Sanctum::actingAs($fixtures['admin'], ['*']);

    $response = $this->postJson(route('api.contracts.return-report.discount-requests.store', [
        'contract' => $fixtures['contract']->id,
    ]), [
        'discount_type' => 'fixed',
        'discount_value' => 50,
        'reason' => 'Second request should fail.',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['discount_request']);

    expect(DiscountRequest::query()->count())->toBe(1);
});

test('api can show latest return report discount request status by contract', function () {
    $fixtures = createDiscountRequestApiFixtures('RES-DISC-STATUS', 500);
    $discountRequest = DiscountRequest::create([
        'tenant_id' => $fixtures['tenant']->id,
        'reservation_id' => $fixtures['reservation']->id,
        'contract_id' => $fixtures['contract']->id,
        'contract_return_report_id' => $fixtures['returnReport']->id,
        'requested_by_user_id' => $fixtures['admin']->id,
        'reviewed_by_user_id' => $fixtures['admin']->id,
        'base_amount' => 200,
        'discount_type' => 'fixed',
        'discount_value' => 25,
        'discount_amount' => 25,
        'final_amount' => 175,
        'reason' => 'Approved discount.',
        'status' => DiscountRequestStatus::APPROVED,
        'reviewed_at' => now(),
        'approved_at' => now(),
    ]);

    Sanctum::actingAs($fixtures['admin'], ['*']);

    $response = $this->getJson(route('api.contracts.return-report.discount-request.show', [
        'contract' => $fixtures['contract']->id,
    ]));

    $response->assertOk()
        ->assertJsonPath('has_return_report', true)
        ->assertJsonPath('has_discount_request', true)
        ->assertJsonPath('discount_request.id', $discountRequest->id)
        ->assertJsonPath('discount_request.status', DiscountRequestStatus::APPROVED->value)
        ->assertJsonPath('discount_request.discount_amount', 25);
});

test('api returns null discount request when return report has no request', function () {
    $fixtures = createDiscountRequestApiFixtures('RES-DISC-NONE', 500);
    Sanctum::actingAs($fixtures['admin'], ['*']);

    $response = $this->getJson(route('api.contracts.return-report.discount-request.show', [
        'contract' => $fixtures['contract']->id,
    ]));

    $response->assertOk()
        ->assertJsonPath('has_return_report', true)
        ->assertJsonPath('has_discount_request', false)
        ->assertJsonPath('discount_request', null);
});

function createDiscountRequestApiFixtures(string $reservationNumber, float $totalAmount): array
{
    $plan = Plan::factory()->create([
        'feature_flags' => array_fill_keys(Plan::FEATURE_KEYS, true),
    ]);
    $tenant = Tenant::factory()->create([
        'plan_id' => $plan->id,
        'is_active' => true,
    ]);
    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Discount API Branch',
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
        'make' => 'Audi',
        'model' => 'Q5',
        'year' => 2020,
        'license_plate' => $reservationNumber,
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
        'reservation_number' => $reservationNumber,
        'start_date' => today()->subDays(2)->toDateString(),
        'end_date' => today()->toDateString(),
        'pickup_time' => '09:00',
        'return_time' => '18:00',
        'pickup_location' => 'Main Office',
        'return_location' => 'Main Office',
        'total_days' => 2,
        'daily_rate' => $totalAmount / 2,
        'subtotal' => $totalAmount,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => $totalAmount,
        'status' => ReservationStatus::ACTIVE,
    ]);
    $contract = Contract::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'reservation_id' => $reservation->id,
        'contract_number' => 'CON-'.$reservationNumber,
        'status' => ContractStatus::ACTIVE,
        'contract_date' => today()->subDays(2)->toDateString(),
        'renter_name' => $client->name,
        'renter_id_number' => '123456789',
        'renter_phone' => '97000000000',
        'car_details' => 'Audi Q5 2020',
        'plate_number' => $car->license_plate,
        'start_date' => today()->subDays(2)->toDateString(),
        'end_date' => today()->toDateString(),
        'total_amount' => $reservation->total_amount,
        'currency' => 'USD',
    ]);
    $returnReport = ContractReturnReport::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'contract_id' => $contract->id,
        'reservation_id' => $reservation->id,
        'car_id' => $car->id,
        'created_by' => $admin->id,
        'report_number' => 'RR-'.$reservationNumber,
        'status' => 'finalized',
        'actual_return_time' => now(),
        'return_location' => 'Main Office',
        'return_odometer' => 1250,
        'return_fuel_level' => 'full',
        'vehicle_condition_after' => 'clean',
        'payment_status' => 'not_paid',
        'total_extra_charges' => 200,
    ]);

    return compact('tenant', 'branch', 'admin', 'client', 'car', 'reservation', 'contract', 'returnReport');
}
