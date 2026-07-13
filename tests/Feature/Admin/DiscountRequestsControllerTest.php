<?php

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\ContractStatus;
use App\Enums\DiscountRequestStatus;
use App\Enums\FuelType;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Contract;
use App\Models\ContractReturnReport;
use App\Models\DiscountRequest;
use App\Models\Permission;
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

test('admin can view discount requests dashboard page', function () {
    $fixtures = createAdminDiscountRequestFixtures('RES-ADMIN-DISC-001');
    grantTenantPaymentManagerPermissions($fixtures['admin']);
    $this->actingAs($fixtures['admin']);
    Sanctum::actingAs($fixtures['admin'], ['*']);
    createPendingAdminDiscountRequest($fixtures);

    $response = $this->get(route('admin.discount-requests.index', [
        'subdomain' => $fixtures['tenant']->slug,
    ]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/DiscountRequests/Index')
            ->has('discountRequests.data', 1)
        );
});

test('admin can approve discount request and apply it to return report total', function () {
    $fixtures = createAdminDiscountRequestFixtures('RES-ADMIN-DISC-002');
    grantTenantPaymentManagerPermissions($fixtures['admin']);
    $this->actingAs($fixtures['admin']);
    Sanctum::actingAs($fixtures['admin'], ['*']);
    $discountRequest = createPendingAdminDiscountRequest($fixtures, 50);

    $response = $this->post(route('admin.discount-requests.approve', [
        'subdomain' => $fixtures['tenant']->slug,
        'discountRequest' => $discountRequest,
    ]));

    $response->assertRedirect();

    $discountRequest->refresh();
    $returnReport = $fixtures['returnReport']->fresh();

    expect($discountRequest->status)->toBe(DiscountRequestStatus::APPROVED);
    expect((float) $returnReport->discount)->toBe(50.0);
    expect((float) $returnReport->total_extra_charges)->toBe(150.0);
});

test('admin can reject discount request without changing return report total', function () {
    $fixtures = createAdminDiscountRequestFixtures('RES-ADMIN-DISC-003');
    grantTenantPaymentManagerPermissions($fixtures['admin']);
    $this->actingAs($fixtures['admin']);
    Sanctum::actingAs($fixtures['admin'], ['*']);
    $discountRequest = createPendingAdminDiscountRequest($fixtures, 50);

    $response = $this->post(route('admin.discount-requests.reject', [
        'subdomain' => $fixtures['tenant']->slug,
        'discountRequest' => $discountRequest,
    ]), [
        'review_note' => 'Too high.',
    ]);

    $response->assertRedirect();

    $discountRequest->refresh();
    $returnReport = $fixtures['returnReport']->fresh();

    expect($discountRequest->status)->toBe(DiscountRequestStatus::REJECTED);
    expect($discountRequest->review_note)->toBe('Too high.');
    expect((float) $returnReport->discount)->toBe(0.0);
    expect((float) $returnReport->total_extra_charges)->toBe(200.0);
});

function grantTenantPaymentManagerPermissions(User $user): void
{
    $managePayments = Permission::query()->create([
        'name' => 'tenant-manage-payments',
        'display_name' => 'Manage Payments',
    ]);
    $viewFinancials = Permission::query()->create([
        'name' => 'tenant-view-financials',
        'display_name' => 'View Financials',
    ]);

    $user->givePermission($managePayments);
    $user->givePermission($viewFinancials);
}

function createPendingAdminDiscountRequest(array $fixtures, float $amount = 50): DiscountRequest
{
    return DiscountRequest::create([
        'tenant_id' => $fixtures['tenant']->id,
        'reservation_id' => $fixtures['reservation']->id,
        'contract_id' => $fixtures['contract']->id,
        'contract_return_report_id' => $fixtures['returnReport']->id,
        'requested_by_user_id' => $fixtures['admin']->id,
        'base_amount' => 200,
        'discount_type' => 'fixed',
        'discount_value' => $amount,
        'discount_amount' => $amount,
        'final_amount' => 200 - $amount,
        'reason' => 'Customer requested a discount.',
        'status' => DiscountRequestStatus::PENDING,
    ]);
}

function createAdminDiscountRequestFixtures(string $reservationNumber): array
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
        'name' => 'Admin Discount Branch',
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
        'daily_rate' => 250,
        'subtotal' => 500,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 500,
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
        'discount' => 0,
        'total_extra_charges' => 200,
    ]);

    return compact('tenant', 'branch', 'admin', 'client', 'car', 'reservation', 'contract', 'returnReport');
}
