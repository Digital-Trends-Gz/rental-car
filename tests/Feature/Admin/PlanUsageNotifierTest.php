<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PlanUsageLimitNotification;
use App\Services\Plans\PlanUsageNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanUsageNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_notifies_once_when_plan_usage_is_near_limit(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);
        $plan = Plan::create([
            'name' => 'Limited Branches',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'max_branches' => 4,
            'is_active' => true,
        ]);
        $tenant->update(['plan_id' => $plan->id]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        foreach (range(1, 3) as $index) {
            Branch::create([
                'tenant_id' => $tenant->id,
                'name' => 'Branch '.$index,
            ]);
        }

        app(PlanUsageNotifier::class)->checkBranches($tenant->refresh());
        app(PlanUsageNotifier::class)->checkBranches($tenant->refresh());

        $this->assertSame(1, $admin->notifications()->where('type', PlanUsageLimitNotification::class)->count());

        $notification = $admin->notifications()->where('type', PlanUsageLimitNotification::class)->firstOrFail();

        $this->assertSame('plan_usage_limit', $notification->data['kind']);
        $this->assertSame('branches', $notification->data['resource']);
        $this->assertSame('near', $notification->data['threshold']);
        $this->assertSame(3, $notification->data['current']);
        $this->assertSame(4, $notification->data['limit']);
    }
}
