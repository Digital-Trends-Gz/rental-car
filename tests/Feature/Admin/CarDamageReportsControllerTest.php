<?php

namespace Tests\Feature\Admin;

use App\Core\TenantContext;
use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\CarDamageItem;
use App\Models\CarDamageReport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarDamageReportsControllerTest extends TestCase
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

    public function test_admin_save_preserves_ai_source_for_unchanged_damage_items(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        TenantContext::set($tenant);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Damage Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $client = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::CLIENT,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $car = Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2024,
            'license_plate' => 'DAMAGE-002',
            'color' => CarColor::WHITE->value,
            'price_per_day' => 90,
            'mileage' => 700,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'description' => null,
            'status' => CarStatus::AVAILABLE->value,
        ]);

        $report = CarDamageReport::create([
            'tenant_id' => $tenant->id,
            'car_id' => $car->id,
            'branch_id' => $branch->id,
            'created_by' => $admin->id,
            'source_type' => CarDamageReport::SOURCE_TYPE_AI,
            'report_number' => 'DMG-AI-002',
            'report_type' => 'before_delivery',
            'status' => 'draft',
            'inspected_at' => now(),
            'odometer' => 700,
            'summary' => 'AI generated draft.',
        ]);

        $item = CarDamageItem::create([
            'tenant_id' => $tenant->id,
            'car_damage_report_id' => $report->id,
            'source_type' => CarDamageItem::SOURCE_TYPE_AI,
            'zone_code' => 'hood',
            'view_side' => 'front',
            'damage_type' => 'scratch',
            'severity' => 'minor',
            'damage_timing' => 'before_pickup',
            'quantity' => 1,
            'estimated_cost' => 120,
            'sort_order' => 1,
            'notes' => 'Initial AI item.',
        ]);

        $controller = app(\App\Http\Controllers\Admin\CarDamageReportsController::class);
        $method = new \ReflectionMethod($controller, 'syncItems');
        $method->setAccessible(true);
        $method->invoke($controller, $report, [[
            'id' => $item->id,
            'zone_code' => 'hood',
            'view_side' => 'front',
            'damage_type' => 'scratch',
            'severity' => 'minor',
            'damage_timing' => 'before_pickup',
            'quantity' => 1,
            'marker_x' => null,
            'marker_y' => null,
            'estimated_cost' => 120,
            'notes' => 'Initial AI item.',
        ]]);

        $item->refresh();
        $this->assertSame(CarDamageItem::SOURCE_TYPE_AI, $item->source_type);
    }
}
