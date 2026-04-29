<?php

namespace Tests\Feature\Admin;

use App\Core\TenantContext;
use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarsPlateFormatValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_car_creation_is_blocked_when_plate_does_not_match_selected_format(): void
    {
        $tenant = $this->tenantWithPlateFormats();
        TenantContext::set($tenant);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.cars.store', ['subdomain' => $tenant->slug]), [
                'make' => 'Toyota',
                'model' => 'Camry',
                'year' => 2024,
                'license_plate' => '1234 A',
                'license_plate_format' => 'oman-standard-a',
                'branch_id' => $branch->id,
                'color' => CarColor::WHITE->value,
                'price_per_day' => 25,
                'price_per_week' => 150,
                'price_per_month' => 500,
                'allowed_km_per_day' => 200,
                'allowed_km_per_week' => 1200,
                'allowed_km_per_month' => 4000,
                'mileage' => 1000,
                'transmission' => 'automatic',
                'seats' => 5,
                'fuel_type' => FuelType::GASOLINE->value,
                'status' => CarStatus::AVAILABLE->value,
                'description' => 'Demo car',
                'image' => [],
                'additional_photos' => [],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('license_plate');

        $this->assertDatabaseMissing('cars', [
            'tenant_id' => $tenant->id,
            'license_plate' => '1234 A',
        ]);
    }

    public function test_car_creation_accepts_plate_matching_selected_format(): void
    {
        $tenant = $this->tenantWithPlateFormats();
        TenantContext::set($tenant);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.cars.store', ['subdomain' => $tenant->slug]), [
                'make' => 'Toyota',
                'model' => 'Camry',
                'year' => 2024,
                'license_plate' => '12345 A',
                'license_plate_format' => 'oman-standard-a',
                'branch_id' => $branch->id,
                'color' => CarColor::WHITE->value,
                'price_per_day' => 25,
                'price_per_week' => 150,
                'price_per_month' => 500,
                'allowed_km_per_day' => 200,
                'allowed_km_per_week' => 1200,
                'allowed_km_per_month' => 4000,
                'mileage' => 1000,
                'transmission' => 'automatic',
                'seats' => 5,
                'fuel_type' => FuelType::GASOLINE->value,
                'status' => CarStatus::AVAILABLE->value,
                'description' => 'Demo car',
                'image' => [],
                'additional_photos' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cars', [
            'tenant_id' => $tenant->id,
            'license_plate' => '12345 A',
            'license_plate_format' => 'oman-standard-a',
        ]);
    }

    public function test_car_creation_can_use_global_plate_format_templates(): void
    {
        SiteSetting::query()->updateOrCreate(
            ['key' => 'plate_format_templates'],
            [
                'value' => [
                    [
                        'code' => 'uae-standard',
                        'name' => 'UAE Standard',
                        'country' => 'AE',
                        'mask' => 'NNNNN',
                        'example' => '12345',
                        'is_active' => true,
                    ],
                ],
            ]
        );

        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);
        TenantContext::set($tenant);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.cars.store', ['subdomain' => $tenant->slug]), [
                'make' => 'Nissan',
                'model' => 'Sunny',
                'year' => 2024,
                'license_plate' => '12345',
                'license_plate_format' => 'uae-standard',
                'branch_id' => $branch->id,
                'color' => CarColor::WHITE->value,
                'price_per_day' => 20,
                'price_per_week' => 120,
                'price_per_month' => 400,
                'allowed_km_per_day' => 200,
                'allowed_km_per_week' => 1200,
                'allowed_km_per_month' => 4000,
                'mileage' => 1000,
                'transmission' => 'automatic',
                'seats' => 5,
                'fuel_type' => FuelType::GASOLINE->value,
                'status' => CarStatus::AVAILABLE->value,
                'description' => 'Demo car',
                'image' => [],
                'additional_photos' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cars', [
            'tenant_id' => $tenant->id,
            'license_plate' => '12345',
            'license_plate_format' => 'uae-standard',
        ]);
    }

    private function tenantWithPlateFormats(): Tenant
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        TenantSiteSetting::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plate_formats' => [
                    [
                        'code' => 'oman-standard-a',
                        'name' => 'Oman Standard A',
                        'country' => 'OM',
                        'mask' => 'NNNNN A',
                        'example' => '12345 A',
                        'is_active' => true,
                    ],
                ],
            ]
        );

        return $tenant;
    }
}
