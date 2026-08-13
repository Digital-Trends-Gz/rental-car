<?php

namespace Tests\Feature;

use App\Core\TenantContext;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();

        Route::middleware(['web', 'auth', 'permission:test-permission'])
            ->get('/_test/protected-route', function () {
                return 'Access Granted';
            });

        Route::middleware(['web', 'auth', 'permission:test-permission|alternate-permission'])
            ->get('/_test/protected-route-any', function () {
                return 'Access Granted';
            });
    }

    public function test_user_without_permission_is_redirected()
    {
        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
        ]);

        $response = $this->actingAs($user)->get('/_test/protected-route');

        $response->assertStatus(403);
    }

    public function test_user_with_permission_can_access()
    {
        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
        ]);

        $permission = Permission::create(['name' => 'test-permission', 'display_name' => 'Test Permission']);
        $user->givePermission($permission);

        $response = $this->actingAs($user)->get('/_test/protected-route');

        $response->assertStatus(200);
        $response->assertSee('Access Granted');
    }

    public function test_tenant_admin_with_direct_global_tenant_permission_can_access()
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);
        TenantContext::set($tenant);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $permission = Permission::withoutGlobalScope('tenant')->create([
            'tenant_id' => null,
            'name' => 'test-permission',
            'display_name' => 'Test Permission',
        ]);

        $user->syncPermissions([$permission->id]);

        $response = $this->actingAs($user)->get('/_test/protected-route');

        $response->assertStatus(200);
        $response->assertSee('Access Granted');
    }

    public function test_user_with_any_allowed_permission_can_access()
    {
        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
        ]);

        $permission = Permission::create(['name' => 'alternate-permission', 'display_name' => 'Alternate Permission']);
        $user->givePermission($permission);

        $response = $this->actingAs($user)->get('/_test/protected-route-any');

        $response->assertStatus(200);
        $response->assertSee('Access Granted');
    }
}
