<?php

use App\Core\TenantContext;
use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\UserRole;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;

test('admin can create a purchase contract car document with purchase date', function () {
    $tenant = Tenant::factory()->create(['is_active' => true]);
    TenantContext::set($tenant);

    $admin = User::factory()->create([
        'role' => UserRole::SUPER_ADMIN,
        'is_active' => true,
    ]);

    $car = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => null,
        'make' => 'Toyota',
        'model' => 'Camry',
        'year' => 2024,
        'license_plate' => 'CAR-2024',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 50,
        'mileage' => 1000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    $payload = [
        'type' => 'purchase_contract',
        'document_number' => 'PC-2024-001',
        'issuer' => 'Auto Dealer',
        'purchase_date' => '2026-04-22',
        'cost' => '25000',
        'notes' => 'Purchase contract uploaded from admin panel.',
        'is_active' => true,
    ];

    $this->actingAs($admin)
        ->withoutMiddleware()
        ->post(route('admin.cars.documents.store', [
            'subdomain' => $tenant->slug,
            'car' => $car->id,
        ]), $payload)
        ->assertRedirect(route('admin.cars.documents.index', [
            'subdomain' => $tenant->slug,
            'car' => $car->id,
        ]));

        $this->assertDatabaseHas('car_documents', [
            'tenant_id' => $tenant->id,
            'car_id' => $car->id,
            'type' => 'purchase_contract',
            'document_number' => 'PC-2024-001',
            'purchase_date' => '2026-04-22 00:00:00',
            'expiry_date' => '2026-04-22 00:00:00',
        ]);
});

test('admin cannot create a car document with expiry date equal to issue date', function () {
    $tenant = Tenant::factory()->create(['is_active' => true]);
    TenantContext::set($tenant);

    $admin = User::factory()->create([
        'role' => UserRole::SUPER_ADMIN,
        'is_active' => true,
    ]);

    $car = Car::create([
        'tenant_id' => $tenant->id,
        'branch_id' => null,
        'make' => 'Toyota',
        'model' => 'Camry',
        'year' => 2024,
        'license_plate' => 'CAR-2025',
        'color' => CarColor::WHITE->value,
        'price_per_day' => 50,
        'mileage' => 1000,
        'transmission' => 'automatic',
        'seats' => 5,
        'fuel_type' => FuelType::GASOLINE->value,
        'description' => null,
        'status' => CarStatus::AVAILABLE->value,
    ]);

    $payload = [
        'type' => 'license',
        'document_number' => 'LIC-2025-001',
        'issuer' => 'Transport Authority',
        'issue_date' => '2026-04-22',
        'expiry_date' => '2026-04-22',
        'cost' => '100',
        'notes' => 'License document uploaded from admin panel.',
        'is_active' => true,
    ];

    $this->actingAs($admin)
        ->withoutMiddleware()
        ->post(route('admin.cars.documents.store', [
            'subdomain' => $tenant->slug,
            'car' => $car->id,
        ]), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors(['expiry_date']);
});
