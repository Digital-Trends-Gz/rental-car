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

class PlateFormatSettingsControllerTest extends TestCase
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

    public function test_admin_can_view_plate_formats_settings_page(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        TenantContext::set($tenant);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.settings.plate-formats.edit', ['subdomain' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/PlateFormats')
                ->has('settings')
            );
    }

    public function test_admin_can_save_plate_formats_settings(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        TenantContext::set($tenant);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $payload = [
            'settings' => [
                'plate_formats' => [
                    [
                        'code' => 'oman-standard-a',
                        'name' => 'Oman Standard A',
                        'country' => 'OM',
                        'mask' => 'NNNNN A',
                        'example' => '12345 A',
                        'is_active' => true,
                    ],
                    [
                        'code' => 'oman-standard-ab',
                        'name' => 'Oman Standard AB',
                        'country' => 'OM',
                        'mask' => 'NNNNN AA',
                        'example' => '12345 AB',
                        'is_active' => false,
                    ],
                ],
            ],
        ];

        $this->actingAs($admin)
            ->put(route('admin.settings.plate-formats.update', ['subdomain' => $tenant->slug]), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $settings = TenantSiteSetting::query()
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $this->assertEquals('oman-standard-a', data_get($settings->plate_formats, '0.code'));
        $this->assertEquals('Oman Standard A', data_get($settings->plate_formats, '0.name'));
        $this->assertEquals('NNNNN A', data_get($settings->plate_formats, '0.mask'));
        $this->assertEquals(false, data_get($settings->plate_formats, '1.is_active'));
    }
}
