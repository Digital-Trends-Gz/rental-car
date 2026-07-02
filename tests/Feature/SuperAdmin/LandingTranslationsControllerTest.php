<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LandingTranslationsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_translations_page_includes_api_translation_keys(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $permission = Permission::withoutGlobalScope('tenant')->create([
            'name' => 'manage-settings',
            'display_name' => 'Manage Settings',
            'description' => 'Manage settings',
        ]);
        $user->syncPermissions([$permission->id]);

        $this->withoutMiddleware([
            \App\Http\Middleware\SuperAdminMiddleware::class,
            \App\Http\Middleware\CheckUserActive::class,
            'verified',
        ]);

        $this->actingAs($user)
            ->get(route('superadmin.settings.landing-translations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Settings/LandingTranslations')
                ->where('rows', fn ($rows): bool => collect($rows)->pluck('key')->contains('api.task_types.pickup'))
                ->where('rows', fn ($rows): bool => collect($rows)->pluck('key')->contains('auth.api.account_not_found'))
                ->where('rows', fn ($rows): bool => collect($rows)->pluck('key')->contains('contracts.damage_catalog.damage_types.scratch'))
                ->where('rows', fn ($rows): bool => collect($rows)->pluck('key')->contains('validation.required'))
            );
    }

    public function test_api_labels_can_use_global_landing_translation_overrides(): void
    {
        SiteSetting::query()->create([
            'key' => 'landing_page',
            'value' => [
                'translations' => [
                    'ar' => [
                        'api' => [
                            'task_types' => [
                                'pickup' => 'GLOBAL_CUSTOM_PICKUP',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson(route('api.reservations.task-types'), [
            'Accept-Language' => 'ar',
        ]);

        $response->assertOk()
            ->assertJsonPath('task_types.0.key', 'pickup')
            ->assertJsonPath('task_types.0.label', 'GLOBAL_CUSTOM_PICKUP');
    }

    public function test_clearing_a_global_landing_translation_removes_the_override(): void
    {
        SiteSetting::query()->create([
            'key' => 'landing_page',
            'value' => [
                'enabled_locales' => ['en', 'ar'],
                'translations' => [
                    'ar' => [
                        'api' => [
                            'task_types' => [
                                'pickup' => 'GLOBAL_CUSTOM_PICKUP',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $permission = Permission::withoutGlobalScope('tenant')->create([
            'name' => 'manage-settings',
            'display_name' => 'Manage Settings',
            'description' => 'Manage settings',
        ]);
        $user->syncPermissions([$permission->id]);

        $this->withoutMiddleware([
            \App\Http\Middleware\SuperAdminMiddleware::class,
            \App\Http\Middleware\CheckUserActive::class,
            'verified',
        ]);

        $this->actingAs($user)
            ->put(route('superadmin.settings.landing-translations.update'), [
                'enabled_locales' => ['en', 'ar'],
                'rows' => [
                    [
                        'key' => 'api.task_types.pickup',
                        'values' => [
                            'en' => '',
                            'ar' => '',
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $settings = SiteSetting::query()
            ->where('key', 'landing_page')
            ->value('value');

        $this->assertNull(data_get($settings, 'translations.ar.api.task_types.pickup'));

        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin, ['*']);

        $this->getJson(route('api.reservations.task-types'), [
            'Accept-Language' => 'ar',
        ])->assertOk()
            ->assertJsonPath('task_types.0.key', 'pickup')
            ->assertJsonPath('task_types.0.label', 'استلام');
    }

    public function test_contract_damage_options_use_global_translation_overrides(): void
    {
        SiteSetting::query()->create([
            'key' => 'landing_page',
            'value' => [
                'translations' => [
                    'ar' => [
                        'contracts' => [
                            'damage_catalog' => [
                                'damage_types' => [
                                    'scratch' => 'GLOBAL_CUSTOM_SCRATCH',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson(route('api.contracts.damage-options'), [
            'Accept-Language' => 'ar',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.damage_types.0.value', 'scratch')
            ->assertJsonPath('data.damage_types.0.label', 'GLOBAL_CUSTOM_SCRATCH');
    }
}
