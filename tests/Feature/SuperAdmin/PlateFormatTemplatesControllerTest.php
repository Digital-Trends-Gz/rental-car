<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\UserRole;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlateFormatTemplatesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->withoutMiddleware([
            \App\Http\Middleware\PermissionMiddleware::class,
            \App\Http\Middleware\SuperAdminMiddleware::class,
            \App\Http\Middleware\CheckUserActive::class,
            'verified',
        ]);
    }

    public function test_page_is_accessible(): void
    {
        $this->actingAs($this->user)
            ->get(route('superadmin.settings.plate-format-templates'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Settings/PlateFormatTemplates')
                ->has('settings')
            );
    }

    public function test_can_save_global_plate_format_templates(): void
    {
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
                ],
            ],
        ];

        $this->actingAs($this->user)
            ->put(route('superadmin.settings.plate-format-templates.update'), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $stored = SiteSetting::query()
            ->where('key', 'plate_format_templates')
            ->firstOrFail();

        $this->assertEquals('oman-standard-a', data_get($stored->value, '0.code'));
        $this->assertEquals('Oman Standard A', data_get($stored->value, '0.name'));
    }
}
