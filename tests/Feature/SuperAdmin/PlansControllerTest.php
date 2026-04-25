<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PlansControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true, // Ensure user is active to pass CheckUserActive middleware
        ]);
        
        $this->withoutMiddleware([
            \App\Http\Middleware\PermissionMiddleware::class,
            \App\Http\Middleware\SuperAdminMiddleware::class,
            \App\Http\Middleware\CheckUserActive::class,
            // DO NOT disable 'auth' middleware here as it might interfere with actingAs in some scenarios
            'verified',
        ]);
    }

    public function test_index_page_is_accessible()
    {
        $this->actingAs($this->user)
            ->get(route('superadmin.plans.index'))
            ->assertStatus(200);
    }

    public function test_can_create_plan()
    {
        $planData = [
            'name' => 'Pro Plan',
            'description' => 'Test Description',
            'features' => ['Feature 1', 'Feature 2'],
            'feature_flags' => [
                'client_portal' => true,
                'booking_calendar' => true,
                'cash_payments' => false,
                'extension_request' => true,
                'force_extend_contract' => true,
                'car_documents' => false,
                'maintenance_module' => false,
                'violations_module' => false,
                'damage_reports' => false,
                'reports_module' => true,
                'pdf_export' => true,
                'ai_contract_extraction' => false,
                'whatsapp_notifications' => false,
                'sms_notifications' => false,
                'email_notifications' => true,
                'custom_branding' => false,
                'custom_domain' => false,
                'stripe_connect' => false,
                'coupon_system' => true,
                'auto_discounts' => false,
                'roles_and_permissions' => true,
            ],
            'monthly_price' => 29.99,
            'monthly_price_id' => 'price_123',
            'yearly_price' => 299.99,
            'yearly_price_id' => 'price_456',
            'max_employees' => 10,
            'max_branches' => null,
            'max_cars' => 25,
            'max_contracts' => 50,
            'openai_requests_per_day' => 100,
            'is_active' => true,
        ];

        $this->actingAs($this->user)
            ->post(route('superadmin.plans.store'), $planData)
            ->assertRedirect(route('superadmin.plans.index'));

        $this->assertDatabaseHas('plans', [
            'name' => 'Pro Plan',
            'monthly_price' => 29.99,
            'max_employees' => 10,
            'max_cars' => 25,
            'max_contracts' => 50,
            'openai_requests_per_day' => 100,
        ]);

        $plan = Plan::query()->where('name', 'Pro Plan')->firstOrFail();
        $this->assertTrue((bool) data_get($plan->feature_flags, 'client_portal'));
        $this->assertTrue((bool) data_get($plan->feature_flags, 'booking_calendar'));
        $this->assertTrue((bool) data_get($plan->feature_flags, 'extension_request'));
    }

    public function test_can_update_plan()
    {
        $plan = Plan::create([
            'name' => 'Old Plan',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'max_employees' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->from(route('superadmin.plans.index'))
            ->put(route('superadmin.plans.update', $plan), [
                'name' => 'Updated Plan',
                'description' => 'Updated Desc',
                'feature_flags' => [
                    'client_portal' => false,
                    'booking_calendar' => false,
                    'cash_payments' => true,
                    'extension_request' => false,
                    'force_extend_contract' => true,
                    'car_documents' => true,
                    'maintenance_module' => false,
                    'violations_module' => true,
                    'damage_reports' => false,
                    'reports_module' => true,
                    'pdf_export' => false,
                    'ai_contract_extraction' => false,
                    'whatsapp_notifications' => false,
                    'sms_notifications' => false,
                    'email_notifications' => true,
                    'custom_branding' => false,
                    'custom_domain' => false,
                    'stripe_connect' => false,
                    'coupon_system' => false,
                    'auto_discounts' => true,
                    'roles_and_permissions' => false,
                ],
                'monthly_price' => 20.00,
                'yearly_price' => 200.00,
                'max_employees' => null,
                'max_branches' => 4,
                'max_cars' => 8,
                'max_contracts' => 12,
                'openai_requests_per_day' => 20,
                'is_active' => false,
                'features' => ['New Feature'],
            ])
            ->assertRedirect(route('superadmin.plans.index'));

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => 'Updated Plan',
            'is_active' => false,
            'max_branches' => 4,
            'max_contracts' => 12,
        ]);

        $plan->refresh();
        $this->assertFalse((bool) data_get($plan->feature_flags, 'client_portal'));
        $this->assertTrue((bool) data_get($plan->feature_flags, 'cash_payments'));
        $this->assertTrue((bool) data_get($plan->feature_flags, 'force_extend_contract'));
    }

    public function test_can_delete_plan()
    {
        $plan = Plan::create([
            'name' => 'To be deleted',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'max_employees' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->delete(route('superadmin.plans.destroy', $plan))
            ->assertRedirect(route('superadmin.plans.index'));

        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }
}
