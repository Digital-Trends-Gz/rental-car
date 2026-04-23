<?php

namespace Tests\Feature\Admin;

use App\Core\TenantContext;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchesControllerTest extends TestCase
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

    public function test_admin_can_create_branch_with_manager_and_cr_details(): void
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
            ->post(route('admin.branches.store', ['subdomain' => $tenant->slug]), [
                'name' => 'Main Branch',
                'cr_number' => 'CR-10001',
                'manager_name' => 'Mohammed Ali',
                'manager_civil_number' => '123456789',
                'phone_1' => '+968 9000 0000',
                'email' => 'branch@example.com',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('branches', [
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'cr_number' => 'CR-10001',
            'manager_name' => 'Mohammed Ali',
            'manager_civil_number' => '123456789',
        ]);
    }

    public function test_admin_can_update_branch_manager_details(): void
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

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'cr_number' => 'CR-10001',
            'manager_name' => 'Mohammed Ali',
            'manager_civil_number' => '123456789',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.branches.update', [
                'subdomain' => $tenant->slug,
                'branch' => $branch->id,
            ]), [
                'name' => 'Main Branch Updated',
                'cr_number' => 'CR-20002',
                'manager_name' => 'Sara Ahmed',
                'manager_civil_number' => '987654321',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch Updated',
            'cr_number' => 'CR-20002',
            'manager_name' => 'Sara Ahmed',
            'manager_civil_number' => '987654321',
        ]);
    }
}
