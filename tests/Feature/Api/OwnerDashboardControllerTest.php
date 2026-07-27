<?php

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\OwnerDashboardMetricSnapshot;
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
