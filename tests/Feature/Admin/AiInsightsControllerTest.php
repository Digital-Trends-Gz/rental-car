<?php

namespace Tests\Feature\Admin;

use App\Core\TenantContext;
use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\UserRole;
use App\Models\AiInsightReport;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AiInsightsControllerTest extends TestCase
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

    public function test_admin_ai_insights_page_loads(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        TenantContext::set($tenant);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'AI-1001',
            'color' => CarColor::WHITE->value,
            'price_per_day' => 100,
            'mileage' => 1000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'description' => null,
            'status' => CarStatus::AVAILABLE->value,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ai-insights.index', [
                'subdomain' => $tenant->slug,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/AiInsights/Index')
                ->has('insights.summary')
                ->has('insights.market_study')
                ->where('latestReport', null)
                ->has('savedReports', 0)
                ->where('openAiStatus.connected', false)
            );

        $this->actingAs($admin)
            ->post(route('admin.ai-insights.generate', [
                'subdomain' => $tenant->slug,
            ]), [
                'period' => 'this_month',
                'locale' => 'ar',
            ])
            ->assertRedirect();

        $this->assertSame(1, AiInsightReport::query()->count());
        $this->assertDatabaseHas('ai_insight_reports', [
            'tenant_id' => $tenant->id,
            'branch_id' => null,
            'created_by' => $admin->id,
            'period' => 'this_month',
            'locale' => 'ar',
            'status' => 'internal_ready',
            'provider' => 'openai',
        ]);

        $report = AiInsightReport::query()->firstOrFail();
        $request = Request::create('/admin/ai-insights/'.$report->id.'/analyze', 'POST');
        $request->setUserResolver(fn () => $admin);

        app(\App\Http\Controllers\Admin\AiInsightsController::class)->analyze($request, $report);

        $this->assertDatabaseHas('ai_insight_reports', [
            'id' => $report->id,
            'status' => 'failed',
            'error_message' => 'OpenAI provider is not configured. Add the API key in Super Admin settings first.',
        ]);
    }
}
