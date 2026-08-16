<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SocialLoginControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_social_callback_logs_admin_into_tenant_dashboard(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'golden-gate',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $url = URL::temporarySignedRoute(
            'tenant.social-login.callback',
            now()->addMinutes(5),
            ['subdomain' => $tenant->slug, 'user' => $user->id]
        );

        $this->get($url)
            ->assertRedirect(route('admin.home', ['subdomain' => $tenant->slug]));

        $this->assertAuthenticatedAs($user);
    }

    public function test_tenant_social_callback_logs_client_into_client_dashboard(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'golden-gate',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::CLIENT,
            'is_active' => true,
        ]);

        $url = URL::temporarySignedRoute(
            'tenant.social-login.callback',
            now()->addMinutes(5),
            ['subdomain' => $tenant->slug, 'user' => $user->id]
        );

        $this->get($url)
            ->assertRedirect(route('client.home', ['subdomain' => $tenant->slug]));

        $this->assertAuthenticatedAs($user);
    }
}
