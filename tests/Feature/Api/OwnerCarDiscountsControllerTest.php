<?php

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\CouponType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\CarDiscount;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function createOwnerWithDiscountSetup(): array
{
    $tenant = Tenant::factory()->create(['is_active' => true]);

    $plan = Plan::factory()->create([
        'feature_flags' => array_fill_keys(Plan::FEATURE_KEYS, true),
    ]);

    $tenant->update([
        'plan_id' => $plan->id,
        'trial_ends_at' => now()->addMonth(),
    ]);

    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name'      => 'Olaya Branch',
        'city'      => 'Riyadh',
        'country'   => 'Saudi Arabia',
    ]);

    $owner = User::factory()->create([
        'tenant_id'          => $tenant->id,
        'branch_id'          => null,
        'role'               => UserRole::ADMIN,
        'is_active'          => true,
        'email_verified_at'  => now(),
    ]);

    $ownerRole = Role::create([
        'tenant_id'    => $tenant->id,
        'name'         => 'tenant-owner',
        'display_name' => 'Tenant Owner',
        'description'  => 'Tenant owner',
    ]);
    $owner->roles()->syncWithoutDetaching([$ownerRole->id]);

    $car = Car::create([
        'tenant_id'     => $tenant->id,
        'branch_id'     => $branch->id,
        'make'          => 'Hyundai',
        'model'         => 'Tucson',
        'year'          => 2023,
        'license_plate' => 'ABC 1234',
        'color'         => CarColor::WHITE->value,
        'price_per_day' => 150,
        'mileage'       => 12000,
        'transmission'  => 'automatic',
        'seats'         => 5,
        'fuel_type'     => FuelType::GASOLINE->value,
        'status'        => CarStatus::AVAILABLE->value,
    ]);

    return compact('tenant', 'branch', 'owner', 'car');
}

test('owner car discounts returns forbidden when auto discounts feature is disabled', function () {
    ['owner' => $owner] = createOwnerWithDiscountSetup();

    $tenant = Tenant::query()->findOrFail($owner->tenant_id);
    $plan = Plan::factory()->create([
        'feature_flags' => array_merge(
            array_fill_keys(Plan::FEATURE_KEYS, true),
            ['auto_discounts' => false]
        ),
    ]);
    $tenant->update(['plan_id' => $plan->id]);

    Sanctum::actingAs($owner);

    $this->getJson(route('api.owner.car-discounts.index'), [
        'Accept-Language' => 'ar',
    ])
        ->assertForbidden()
        ->assertExactJson([
            'message' => 'خطتك الحالية لا تتضمن صلاحية الوصول للخصومات التلقائية.',
        ]);
});

test('owner can list car discounts', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'car' => $car] = createOwnerWithDiscountSetup();

    CarDiscount::create([
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'created_by' => $owner->id,
        'name' => 'Tucson Special Offer',
        'description' => '20% off for Hyundai Tucson',
        'type' => CouponType::PERCENTAGE->value,
        'value' => 20.00,
        'is_active' => true,
    ]);

    Sanctum::actingAs($owner);

    $response = $this->getJson(route('api.owner.car-discounts.index'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'locale',
            'data' => [
                '*' => [
                    'id',
                    'car_id',
                    'car_name',
                    'name',
                    'description',
                    'type',
                    'type_label',
                    'value',
                    'value_formatted',
                    'is_active',
                ]
            ],
            'pagination',
        ])
        ->assertJsonFragment([
            'name' => 'Tucson Special Offer',
            'car_name' => '2023 Hyundai Tucson',
        ]);
});

test('owner can filter car discounts by car_id and status', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'car' => $car] = createOwnerWithDiscountSetup();

    $activeDiscount = CarDiscount::create([
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'created_by' => $owner->id,
        'name' => 'Active Discount',
        'type' => CouponType::PERCENTAGE->value,
        'value' => 15.00,
        'is_active' => true,
    ]);

    $inactiveDiscount = CarDiscount::create([
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'created_by' => $owner->id,
        'name' => 'Inactive Discount',
        'type' => CouponType::PERCENTAGE->value,
        'value' => 25.00,
        'is_active' => false,
    ]);

    Sanctum::actingAs($owner);

    // Filter active
    $response = $this->getJson(route('api.owner.car-discounts.index', ['status' => 'active']));
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'Active Discount']);

    // Filter inactive
    $response = $this->getJson(route('api.owner.car-discounts.index', ['status' => 'inactive']));
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'Inactive Discount']);
});

test('owner can create a new car discount', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'car' => $car] = createOwnerWithDiscountSetup();

    Sanctum::actingAs($owner);

    $payload = [
        'car_id' => $car->id,
        'name' => 'Weekend Flash Sale',
        'description' => 'Super fast weekend sale',
        'type' => CouponType::PERCENTAGE->value,
        'value' => 18.50,
        'starts_at' => now()->toDateString(),
        'ends_at' => now()->addDays(3)->toDateString(),
        'priority' => 10,
        'is_active' => true,
    ];

    $response = $this->postJson(route('api.owner.car-discounts.store'), $payload);

    $response->assertStatus(201)
        ->assertJsonFragment([
            'status' => 'success',
            'name' => 'Weekend Flash Sale',
            'value' => 18.5,
        ]);

    $this->assertDatabaseHas('car_discounts', [
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'name' => 'Weekend Flash Sale',
        'value' => 18.50,
        'priority' => 10,
    ]);
});

test('owner can view a car discount details', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'car' => $car] = createOwnerWithDiscountSetup();

    $discount = CarDiscount::create([
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'created_by' => $owner->id,
        'name' => 'Single Discount Detail',
        'type' => CouponType::FIXED->value,
        'value' => 50.00,
        'is_active' => true,
    ]);

    Sanctum::actingAs($owner);

    $response = $this->getJson(route('api.owner.car-discounts.show', $discount->id));

    $response->assertStatus(200)
        ->assertJsonFragment([
            'name' => 'Single Discount Detail',
            'value' => 50.00,
        ]);
});

test('owner can update a car discount', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'car' => $car] = createOwnerWithDiscountSetup();

    $discount = CarDiscount::create([
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'created_by' => $owner->id,
        'name' => 'Old Name',
        'type' => CouponType::PERCENTAGE->value,
        'value' => 10.00,
        'is_active' => true,
    ]);

    Sanctum::actingAs($owner);

    $payload = [
        'car_id' => $car->id,
        'name' => 'New Awesome Name',
        'description' => 'Updated desc',
        'type' => CouponType::PERCENTAGE->value,
        'value' => 15.00,
        'is_active' => false,
    ];

    $response = $this->putJson(route('api.owner.car-discounts.update', $discount->id), $payload);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'name' => 'New Awesome Name',
            'value' => 15.0,
            'is_active' => false,
        ]);

    $this->assertDatabaseHas('car_discounts', [
        'id' => $discount->id,
        'name' => 'New Awesome Name',
        'value' => 15.00,
        'is_active' => false,
    ]);
});

test('owner can delete a car discount', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'car' => $car] = createOwnerWithDiscountSetup();

    $discount = CarDiscount::create([
        'tenant_id' => $tenant->id,
        'car_id' => $car->id,
        'created_by' => $owner->id,
        'name' => 'To Be Deleted',
        'type' => CouponType::PERCENTAGE->value,
        'value' => 12.00,
        'is_active' => true,
    ]);

    Sanctum::actingAs($owner);

    $response = $this->deleteJson(route('api.owner.car-discounts.destroy', $discount->id));

    $response->assertStatus(200);

    $this->assertDatabaseMissing('car_discounts', [
        'id' => $discount->id,
    ]);
});
