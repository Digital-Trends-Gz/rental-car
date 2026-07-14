<?php

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\ContractStatus;
use App\Enums\FuelType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Contract;
use App\Models\ContractReturnReport;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rentals\RentalStatusSyncService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    app()->instance(RentalStatusSyncService::class, new class extends RentalStatusSyncService {
        public function syncCarsByIds(array $carIds, bool $dryRun = false, ?int $reserveBeforeHours = null): int
        {
            return 0;
        }
    });

    Storage::fake(config('vilt-filepond.storage_disk', 'public'));
});

test('api can collect reservation cash payment with attachments', function () {
    $fixtures = createCashPaymentApiFixtures('RES-CASH-001', 500);
    Sanctum::actingAs($fixtures['admin'], ['*']);

    $response = $this->postJson(route('api.reservations.cash-payments.store', [
        'reservation' => $fixtures['reservation']->id,
    ]), [
        'amount' => 200,
        'notes' => 'Cash received at office.',
        'attachments' => [
            UploadedFile::fake()->image('receipt.jpg'),
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('payment.amount', 200)
        ->assertJsonPath('payment.payment_method', PaymentMethod::CASH->value)
        ->assertJsonPath('payment.status', PaymentStatus::COMPLETED->value)
        ->assertJsonPath('reservation.paid_amount', 200)
        ->assertJsonPath('reservation.remaining_amount', 300)
        ->assertJsonPath('reservation.payment_status', 'partial')
        ->assertJsonCount(1, 'payment.attachments');

    $payment = Payment::query()->firstOrFail();
    expect(data_get($payment->gateway_data, 'cash_source.type'))->toBe('reservation');
    expect($payment->files()->where('collection', 'cash_payment_attachments')->count())->toBe(1);
});

test('api can collect return status cash payment separately from reservation balance', function () {
    $fixtures = createCashPaymentApiFixtures('RES-CASH-002', 500);
    $contract = createCashPaymentApiContract($fixtures);
    $report = createCashPaymentApiReturnReport($fixtures, $contract, 65);

    $pendingPayment = Payment::create([
        'tenant_id' => $fixtures['tenant']->id,
        'reservation_id' => $fixtures['reservation']->id,
        'user_id' => $fixtures['client']->id,
        'amount' => 65,
        'currency' => 'USD',
        'payment_method' => PaymentMethod::CASH,
        'status' => PaymentStatus::PENDING,
        'notes' => 'Pending return report settlement.',
    ]);
    $report->forceFill(['payment_id' => $pendingPayment->id])->save();

    Sanctum::actingAs($fixtures['admin'], ['*']);

    $response = $this->postJson(route('api.contracts.return-report.cash-payments.store', [
        'contract' => $contract->id,
    ]), [
        'amount' => 65,
        'notes' => 'Return fees collected.',
        'attachments' => [
            UploadedFile::fake()->create('return-receipt.pdf', 64, 'application/pdf'),
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('payment.id', $pendingPayment->id)
        ->assertJsonPath('payment.amount', 65)
        ->assertJsonPath('return_status_report.paid_amount', 65)
        ->assertJsonPath('return_status_report.remaining_amount', 0)
        ->assertJsonPath('return_status_report.payment_status', 'paid')
        ->assertJsonCount(1, 'payment.attachments');

    $pendingPayment->refresh();
    $report->refresh();

    expect($pendingPayment->status)->toBe(PaymentStatus::COMPLETED);
    expect(data_get($pendingPayment->gateway_data, 'cash_source.type'))->toBe('contract_return_report');
    expect($report->payment_status)->toBe('paid');
    expect((float) $fixtures['reservation']->fresh()->payments()->completed()->sum('amount'))->toBe(65.0);
});

test('api rejects cash payment when amount is empty', function () {
    $fixtures = createCashPaymentApiFixtures('RES-CASH-EMPTY', 500);
    Sanctum::actingAs($fixtures['admin'], ['*']);

    $response = $this->postJson(route('api.reservations.cash-payments.store', [
        'reservation' => $fixtures['reservation']->id,
    ]), [
        'amount' => '',
        'notes' => 'No amount should not collect.',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);

    expect(Payment::query()->count())->toBe(0);
});

test('api marks return status partial when collected cash is less than remaining amount', function () {
    $fixtures = createCashPaymentApiFixtures('RES-CASH-PARTIAL-RR', 500);
    $contract = createCashPaymentApiContract($fixtures);
    $report = createCashPaymentApiReturnReport($fixtures, $contract, 100);

    Sanctum::actingAs($fixtures['admin'], ['*']);

    $response = $this->postJson(route('api.contracts.return-report.cash-payments.store', [
        'contract' => $contract->id,
    ]), [
        'amount' => 40,
        'notes' => 'Partial return payment.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('payment.amount', 40)
        ->assertJsonPath('return_status_report.paid_amount', 40)
        ->assertJsonPath('return_status_report.remaining_amount', 60)
        ->assertJsonPath('return_status_report.payment_status', 'partial');

    expect($report->fresh()->payment_status)->toBe('partial');
});

test('api converts return status cash payment using selected currency exchange rate', function () {
    $fixtures = createCashPaymentApiFixtures('RES-CASH-FX-RR', 500);
    $contract = createCashPaymentApiContract($fixtures);
    createCashPaymentApiReturnReport($fixtures, $contract, 100);

    Sanctum::actingAs($fixtures['admin'], ['*']);

    $response = $this->postJson(route('api.contracts.return-report.cash-payments.store', [
        'contract' => $contract->id,
    ]), [
        'amount' => 50,
        'currency_code' => 'EUR',
        'exchange_rate' => 1.2,
        'notes' => 'Foreign currency return payment.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('payment.amount', 50)
        ->assertJsonPath('payment.currency', 'EUR')
        ->assertJsonPath('payment.base_amount', 60)
        ->assertJsonPath('payment.base_currency', 'USD')
        ->assertJsonPath('payment.exchange_rate', 1.2)
        ->assertJsonPath('return_status_report.paid_amount', 60)
        ->assertJsonPath('return_status_report.remaining_amount', 40)
        ->assertJsonPath('return_status_report.currency', 'USD')
        ->assertJsonPath('return_status_report.payment_status', 'partial');

    $payment = Payment::query()->latest('id')->firstOrFail();
    expect((float) $payment->amount)->toBe(50.0);
    expect($payment->currency)->toBe('EUR');
    expect((float) $payment->base_amount)->toBe(60.0);
    expect($payment->base_currency)->toBe('USD');
    expect((float) $payment->exchange_rate)->toBe(1.2);
});

function createCashPaymentApiFixtures(string $reservationNumber, float $totalAmount): array
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
        'name' => 'Cash API Branch',
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

    return compact('tenant', 'branch', 'admin', 'client', 'car', 'reservation');
}

function createCashPaymentApiContract(array $fixtures): Contract
{
    return Contract::create([
        'tenant_id' => $fixtures['tenant']->id,
        'branch_id' => $fixtures['branch']->id,
        'reservation_id' => $fixtures['reservation']->id,
        'contract_number' => 'CON-'.$fixtures['reservation']->reservation_number,
        'status' => ContractStatus::ACTIVE,
        'contract_date' => today()->subDays(2)->toDateString(),
        'renter_name' => $fixtures['client']->name,
        'renter_id_number' => '123456789',
        'renter_phone' => '97000000000',
        'car_details' => 'Audi Q5 2020',
        'plate_number' => $fixtures['car']->license_plate,
        'start_date' => today()->subDays(2)->toDateString(),
        'end_date' => today()->toDateString(),
        'total_amount' => $fixtures['reservation']->total_amount,
        'currency' => 'USD',
    ]);
}

function createCashPaymentApiReturnReport(array $fixtures, Contract $contract, float $amount): ContractReturnReport
{
    return ContractReturnReport::create([
        'tenant_id' => $fixtures['tenant']->id,
        'branch_id' => $fixtures['branch']->id,
        'contract_id' => $contract->id,
        'reservation_id' => $fixtures['reservation']->id,
        'car_id' => $fixtures['car']->id,
        'created_by' => $fixtures['admin']->id,
        'report_number' => 'RR-'.$fixtures['reservation']->reservation_number,
        'status' => 'finalized',
        'actual_return_time' => now(),
        'return_location' => 'Main Office',
        'return_odometer' => 1250,
        'return_fuel_level' => 'full',
        'vehicle_condition_after' => 'clean',
        'payment_status' => 'not_paid',
        'total_extra_charges' => $amount,
    ]);
}
