<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
    }

    public function test_admin_can_create_client_with_civil_number(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.clients.store', ['subdomain' => $tenant->slug]), [
                'name' => 'Client One',
                'email' => 'client1@example.com',
                'civil_number' => '99887766',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
            ])
            ->assertRedirect(route('admin.clients.index', ['subdomain' => $tenant->slug]));

        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'role' => UserRole::CLIENT->value,
            'email' => 'client1@example.com',
            'civil_number' => '99887766',
        ]);
    }
}
