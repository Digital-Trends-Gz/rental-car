<?php

namespace Tests\Feature\Api;

use App\Core\LocalizationSettings;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_settings_include_active_dashboard_language(): void
    {
        SiteSetting::query()->create([
            'key' => LocalizationSettings::KEY,
            'value' => [
                'default_locale' => 'en',
                'locales' => [
                    [
                        'code' => 'en',
                        'name' => 'English',
                        'native' => 'English',
                        'regional' => 'en_US',
                        'script' => 'Latn',
                        'direction' => 'ltr',
                    ],
                    [
                        'code' => 'ar',
                        'name' => 'Arabic',
                        'native' => 'العربية',
                        'regional' => 'ar_SA',
                        'script' => 'Arab',
                        'direction' => 'rtl',
                    ],
                ],
            ],
        ]);

        $this->getJson('/api/settings/general?locale=ar')
            ->assertOk()
            ->assertJsonPath('language', 'ar')
            ->assertJsonPath('active_language', 'ar')
            ->assertJsonPath('default_language', 'en')
            ->assertJsonPath('available_languages.1.code', 'ar')
            ->assertJsonPath('available_languages.1.direction', 'rtl');
    }

    public function test_tenant_settings_include_access_flag(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/settings/tenant')
            ->assertOk()
            ->assertJsonPath('access', true)
            ->assertJsonPath('source', 'tenant')
            ->assertJsonPath('tenant.id', $tenant->id);
    }

    public function test_currencies_endpoint_returns_tenant_enabled_currencies_for_authenticated_user(): void
    {
        $tenant = Tenant::factory()->create();
        TenantSiteSetting::query()->create([
            'tenant_id' => $tenant->id,
            'market_location' => [
                'currency_code' => 'OMR',
                'enabled_currency_codes' => ['AED', 'USD'],
            ],
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/settings/currencies')
            ->assertOk()
            ->assertJsonPath('source', 'tenant')
            ->assertJsonPath('base_currency_code', 'OMR')
            ->assertJsonPath('enabled_currency_codes', ['AED', 'USD', 'OMR'])
            ->assertJsonCount(3, 'currencies')
            ->assertJsonPath('currencies.0.code', 'AED')
            ->assertJsonPath('currencies.1.code', 'USD')
            ->assertJsonPath('currencies.2.code', 'OMR');
    }
}
