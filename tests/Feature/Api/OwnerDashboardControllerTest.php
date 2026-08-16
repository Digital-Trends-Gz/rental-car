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
use App\Models\OwnerDashboardMetricSnapshot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Dashboard\OwnerDashboardMetricsService;
use Laravel\Sanctum\Sanctum;

test('owner dashboard change percent is null when yesterday snapshot value is zero', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Branch',
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

    Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'make' => 'Toyota',
        'model' => 'Camry',
        'year' => 2024,
        'license_plate' => 'OWN-DASH-001',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 100,
        'mileage' => 1000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    OwnerDashboardMetricSnapshot::create([
        'tenant_id' => $tenant->id,
        'branch_id' => null,
        'branch_scope' => 'all',
        'metric_key' => OwnerDashboardMetricsService::AVAILABLE_CARS,
        'metric_date' => today()->subDay()->toDateString(),
        'value' => 0,
        'captured_at' => now()->subDay(),
    ]);

    Sanctum::actingAs($owner, ['*']);

    $response = $this->getJson(route('api.owner.dashboard.summary'));

    $response->assertOk();

    $availableCarsCard = collect($response->json('cards'))
        ->firstWhere('key', OwnerDashboardMetricsService::AVAILABLE_CARS);

    expect($availableCarsCard['value'])->toBe(1);
    expect($availableCarsCard['change']['value'])->toBe(1);
    expect($availableCarsCard['change']['percent'])->toBeNull();
    expect($availableCarsCard['change']['direction'])->toBe('up');
});

test('owner dashboard ignores backfilled historical snapshots captured after their metric date', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Branch',
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

    Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'make' => 'Toyota',
        'model' => 'Camry',
        'year' => 2024,
        'license_plate' => 'OWN-DASH-002',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 100,
        'mileage' => 1000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    OwnerDashboardMetricSnapshot::create([
        'tenant_id' => $tenant->id,
        'branch_id' => null,
        'branch_scope' => 'all',
        'metric_key' => OwnerDashboardMetricsService::AVAILABLE_CARS,
        'metric_date' => today()->subDay()->toDateString(),
        'value' => 0,
        'captured_at' => now(),
    ]);

    Sanctum::actingAs($owner, ['*']);

    $response = $this->getJson(route('api.owner.dashboard.summary'));

    $response->assertOk();

    $availableCarsCard = collect($response->json('cards'))
        ->firstWhere('key', OwnerDashboardMetricsService::AVAILABLE_CARS);

    expect($availableCarsCard['value'])->toBe(1);
    expect($availableCarsCard['change'])->toBeNull();
});

test('owner dashboard summary filters revenue by requested date range and branch', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $mainBranch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Branch',
    ]);

    $otherBranch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Other Branch',
    ]);

    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => null,
        'role' => UserRole::ADMIN,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $customer = User::factory()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => $mainBranch->id,
        'role' => UserRole::CLIENT,
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

    $mainCar = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $mainBranch->id,
        'make' => 'Toyota',
        'model' => 'Camry',
        'year' => 2024,
        'license_plate' => 'OWN-DASH-RANGE-001',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 100,
        'mileage' => 1000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    $otherCar = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $otherBranch->id,
        'make' => 'Honda',
        'model' => 'Accord',
        'year' => 2024,
        'license_plate' => 'OWN-DASH-RANGE-002',
        'color' => CarColor::BLACK->value,
        'price_per_day' => 100,
        'mileage' => 1000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    $reservationInMainBranch = Reservation::withoutEvents(fn () => Reservation::create([
        'tenant_id' => $tenant->id,
        'reservation_number' => 'RES-OWN-DASH-RANGE-001',
        'user_id' => $customer->id,
        'car_id' => $mainCar->id,
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-12',
        'total_days' => 3,
        'daily_rate' => 100,
        'subtotal' => 300,
        'total_amount' => 300,
        'status' => ReservationStatus::CONFIRMED->value,
    ]));

    $reservationInOtherBranch = Reservation::withoutEvents(fn () => Reservation::create([
        'tenant_id' => $tenant->id,
        'reservation_number' => 'RES-OWN-DASH-RANGE-002',
        'user_id' => $customer->id,
        'car_id' => $otherCar->id,
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-12',
        'total_days' => 3,
        'daily_rate' => 100,
        'subtotal' => 300,
        'total_amount' => 300,
        'status' => ReservationStatus::CONFIRMED->value,
    ]));

    Payment::create([
        'tenant_id' => $tenant->id,
        'reservation_id' => $reservationInMainBranch->id,
        'user_id' => $customer->id,
        'amount' => 125,
        'base_amount' => 125,
        'currency' => 'USD',
        'payment_method' => PaymentMethod::CASH->value,
        'status' => PaymentStatus::COMPLETED->value,
        'processed_at' => '2026-07-10 10:00:00',
    ]);

    Payment::create([
        'tenant_id' => $tenant->id,
        'reservation_id' => $reservationInMainBranch->id,
        'user_id' => $customer->id,
        'amount' => 75,
        'base_amount' => 75,
        'currency' => 'USD',
        'payment_method' => PaymentMethod::CASH->value,
        'status' => PaymentStatus::COMPLETED->value,
        'processed_at' => '2026-07-12 10:00:00',
    ]);

    Payment::create([
        'tenant_id' => $tenant->id,
        'reservation_id' => $reservationInMainBranch->id,
        'user_id' => $customer->id,
        'amount' => 50,
        'base_amount' => 50,
        'currency' => 'USD',
        'payment_method' => PaymentMethod::CASH->value,
        'status' => PaymentStatus::COMPLETED->value,
        'processed_at' => '2026-07-08 10:00:00',
    ]);

    Payment::create([
        'tenant_id' => $tenant->id,
        'reservation_id' => $reservationInMainBranch->id,
        'user_id' => $customer->id,
        'amount' => 500,
        'base_amount' => 500,
        'currency' => 'USD',
        'payment_method' => PaymentMethod::CASH->value,
        'status' => PaymentStatus::COMPLETED->value,
        'processed_at' => '2026-07-15 10:00:00',
    ]);

    Payment::create([
        'tenant_id' => $tenant->id,
        'reservation_id' => $reservationInOtherBranch->id,
        'user_id' => $customer->id,
        'amount' => 900,
        'base_amount' => 900,
        'currency' => 'USD',
        'payment_method' => PaymentMethod::CASH->value,
        'status' => PaymentStatus::COMPLETED->value,
        'processed_at' => '2026-07-11 10:00:00',
    ]);

    Sanctum::actingAs($owner, ['*']);

    $response = $this->getJson(route('api.owner.dashboard.summary', [
        'branch_id' => $mainBranch->id,
        'date_from' => '2026-07-10',
        'date_to' => '2026-07-12',
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('date_range.from', '2026-07-10')
        ->assertJsonPath('date_range.to', '2026-07-12')
        ->assertJsonPath('date_range.is_custom', true)
        ->assertJsonPath('stats.today_revenue', 200)
        ->assertJsonPath('cards.0.change.value', 150)
        ->assertJsonPath('cards.0.change.percent', 300)
        ->assertJsonPath('cards.0.change.direction', 'up')
        ->assertJsonPath('cards.0.change.comparison', 'previous_period')
        ->assertJsonPath('cards.0.change.comparison_period.from', '2026-07-07')
        ->assertJsonPath('cards.0.change.comparison_period.to', '2026-07-09');

    expect(collect($response->json('revenue_chart'))->pluck('value', 'date')->all())->toEqual([
        '2026-07-10' => 125.0,
        '2026-07-11' => 0.0,
        '2026-07-12' => 75.0,
    ]);

    $aliasResponse = $this->getJson(route('api.owner.dashboard.summary', [
        'branch_id' => $mainBranch->id,
        'from' => '2026-07-10',
        'to' => '2026-07-12',
    ]));

    $aliasResponse
        ->assertOk()
        ->assertJsonPath('date_range.from', '2026-07-10')
        ->assertJsonPath('date_range.to', '2026-07-12')
        ->assertJsonPath('date_range.is_custom', true)
        ->assertJsonPath('stats.today_revenue', 200);
});

test('owner admin without pre-attached tenant-owner role can access branches API via fallback and auto-sync', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
        'email' => 'owner-test@tenant.com',
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Branch',
    ]);

    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'owner-test@tenant.com',
        'branch_id' => null,
        'role' => UserRole::ADMIN,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($owner, ['*']);

    $response = $this->getJson(route('api.owner.branches'));

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('can_access_all_branches', true);
});

test('owner branches API includes management screen metrics', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Golden Gate 1',
        'city' => 'New Miami',
        'country' => 'US',
        'manager_name' => 'Branch Manager',
    ]);

    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => null,
        'role' => UserRole::ADMIN,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $customer = User::factory()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'role' => UserRole::CLIENT,
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

    $availableCar = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'make' => 'Toyota',
        'model' => 'Camry',
        'year' => 2024,
        'license_plate' => 'BR-MGMT-001',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 100,
        'mileage' => 1000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'make' => 'Honda',
        'model' => 'Civic',
        'year' => 2024,
        'license_plate' => 'BR-MGMT-002',
        'color' => CarColor::BLACK->value,
        'price_per_day' => 100,
        'mileage' => 1000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'status' => CarStatus::RENTED->value,
    ]);

    $reservation = Reservation::withoutEvents(fn () => Reservation::create([
        'tenant_id' => $tenant->id,
        'reservation_number' => 'RES-BR-MGMT-001',
        'user_id' => $customer->id,
        'car_id' => $availableCar->id,
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'total_days' => 2,
        'daily_rate' => 40,
        'subtotal' => 80,
        'total_amount' => 80,
        'status' => ReservationStatus::CONFIRMED->value,
    ]));

    Payment::create([
        'tenant_id' => $tenant->id,
        'reservation_id' => $reservation->id,
        'user_id' => $customer->id,
        'amount' => 80,
        'base_amount' => 80,
        'currency' => 'USD',
        'payment_method' => PaymentMethod::CASH->value,
        'status' => PaymentStatus::COMPLETED->value,
        'processed_at' => now(),
    ]);

    Sanctum::actingAs($owner, ['*']);

    $response = $this->getJson(route('api.owner.branches'));

    $response
        ->assertOk()
        ->assertJsonPath('summary.total_branches', 1)
        ->assertJsonPath('summary.total_fleet', 2)
        ->assertJsonPath('summary.todays_bookings', 1)
        ->assertJsonPath('branches.0.name', 'Golden Gate 1')
        ->assertJsonPath('branches.0.branch_owner.name', 'Branch Manager')
        ->assertJsonPath('branches.0.number_of_cars', 2)
        ->assertJsonPath('branches.0.todays_bookings', 1)
        ->assertJsonPath('branches.0.occupancy_rate', 50)
        ->assertJsonPath('branch_revenue.items.0.amount', 80);
});

test('tenant partner admin can access owner branches API', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Branch',
    ]);

    $partner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => null,
        'role' => UserRole::ADMIN,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $partnerRole = Role::create([
        'tenant_id' => $tenant->id,
        'name' => 'tenant-partner',
        'display_name' => 'Tenant Partner',
        'description' => 'Tenant partner',
    ]);
    $partner->roles()->syncWithoutDetaching([$partnerRole->id]);

    Sanctum::actingAs($partner, ['*']);

    $response = $this->getJson(route('api.owner.branches'));

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('can_access_all_branches', true);
});
