<?php

namespace Tests\Feature\Admin;

use App\Core\TenantContext;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReservationSettingsControllerTest extends TestCase
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

    public function test_admin_can_view_tenant_reservation_settings_page(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);
        TenantContext::set($tenant);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.settings.reservation-settings.edit', ['subdomain' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/ReservationSettings')
                ->has('settings')
                ->where('settings.return_time_policy.mode', 'fixed_time')
            );
    }

    public function test_admin_can_save_tenant_reservation_settings(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);
        TenantContext::set($tenant);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $payload = [
                'settings' => [
                    'return_time_policy' => [
                        'mode' => 'fixed_time',
                        'fixed_time' => '18:00',
                    ],
                'pickup_return_locations' => [
                    [
                        'name' => 'Main Office',
                        'pickup_fee' => 0,
                        'return_fee' => 10,
                        'pickup_free' => true,
                        'return_free' => false,
                        'is_active' => true,
                    ],
                ],
                'kilometer_pricing' => [
                    [
                        'from_km' => 0,
                        'to_km' => 100,
                        'price' => 15,
                    ],
                ],
                'fuel_pricing' => [
                    [
                        'fuel_level' => 'half',
                        'price' => 12.5,
                    ],
                ],
                'late_return' => [
                    'mode' => 'hourly',
                    'hourly_fee' => 8,
                    'after_hours' => 3,
                ],
                'cleaning_fee' => 20,
            ],
        ];

        $this->actingAs($admin)
            ->put(route('admin.settings.reservation-settings.update', ['subdomain' => $tenant->slug]), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $settings = TenantSiteSetting::query()
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $this->assertEquals('fixed_time', data_get($settings->reservation_settings, 'return_time_policy.mode'));
        $this->assertEquals('18:00', data_get($settings->reservation_settings, 'return_time_policy.fixed_time'));
        $this->assertEquals('Main Office', data_get($settings->reservation_settings, 'pickup_return_locations.0.name'));
        $this->assertEquals(15.0, data_get($settings->reservation_settings, 'kilometer_pricing.0.price'));
        $this->assertEquals('half', data_get($settings->reservation_settings, 'fuel_pricing.0.fuel_level'));
        $this->assertEquals(8.0, data_get($settings->reservation_settings, 'late_return.hourly_fee'));
        $this->assertEquals(20.0, data_get($settings->reservation_settings, 'cleaning_fee'));
    }
}
