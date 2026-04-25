<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantFeatureAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \App\Http\Middleware\PermissionMiddleware::class,
            \App\Http\Middleware\CheckUserActive::class,
            'verified',
        ]);
    }

    public function test_coupon_page_returns_forbidden_when_feature_is_disabled(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $plan = Plan::create([
            'name' => 'Starter',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'feature_flags' => [
                'coupon_system' => false,
            ],
            'is_active' => true,
        ]);

        $tenant->update([
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->addMonth(),
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'civil_number' => '99998888',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.coupons.index', ['subdomain' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_auto_discount_page_returns_forbidden_when_feature_is_disabled(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $plan = Plan::create([
            'name' => 'Starter',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'feature_flags' => [
                'auto_discounts' => false,
            ],
            'is_active' => true,
        ]);

        $tenant->update([
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->addMonth(),
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'civil_number' => '99998888',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.car-discounts.index', ['subdomain' => $tenant->slug]))
            ->assertForbidden();
    }
}
