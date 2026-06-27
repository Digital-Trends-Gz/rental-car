<?php

namespace Tests\Feature\Api;

use App\Core\LocalizationSettings;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
