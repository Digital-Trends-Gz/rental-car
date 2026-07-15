<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Core\LandingPageSettings;

class LandingSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_design_images(): void
    {
        Storage::fake('public');

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

        $defaults = LandingPageSettings::defaults();

        $response = $this->actingAs($user)
            ->post(route('superadmin.settings.design.update.post'), [
                '_method' => 'put',
                'settings' => $defaults,
                'hero_direct_file' => UploadedFile::fake()->image('hero.jpg'),
                'feature_card_direct_files' => [
                    0 => UploadedFile::fake()->image('feature0.jpg'),
                ],
            ]);

        $response->assertRedirect();

        $landingSetting = SiteSetting::where('key', 'landing_page')->first();
        $this->assertNotNull($landingSetting);

        $value = $landingSetting->value;
        $this->assertNotEmpty($value['hero']['image_url']);
        $this->assertNotEmpty($value['features_section']['cards'][0]['image_url']);

        // Check if file is registered in the files table
        $this->assertDatabaseHas('files', [
            'collection' => 'hero',
            'fileable_id' => $landingSetting->id,
        ]);
        $this->assertDatabaseHas('files', [
            'collection' => 'feature_card_0_image',
            'fileable_id' => $landingSetting->id,
        ]);
    }

    public function test_validation_passes_with_string_null_files(): void
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

        $defaults = LandingPageSettings::defaults();

        $response = $this->actingAs($user)
            ->post(route('superadmin.settings.design.update.post'), [
                '_method' => 'put',
                'settings' => $defaults,
                'hero_direct_file' => 'null',
                'feature_card_direct_files' => [
                    0 => 'null',
                    1 => UploadedFile::fake()->image('feature1.jpg'),
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $landingSetting = SiteSetting::where('key', 'landing_page')->first();
        $this->assertNotNull($landingSetting);

        $value = $landingSetting->value;
        // The valid uploaded file should be saved and stored
        $this->assertNotEmpty($value['features_section']['cards'][1]['image_url']);
        // The ones with "null" should be ignored and empty
        $this->assertEmpty($value['hero']['image_url']);
        $this->assertEmpty($value['features_section']['cards'][0]['image_url']);
    }
}
