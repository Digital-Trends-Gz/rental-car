<?php

namespace Tests\Feature\Admin;

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\MaintenanceRecordStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\MaintenanceType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRecordsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
    }

    public function test_admin_cannot_create_maintenance_record_with_completed_at_equal_to_started_at(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $car = Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'MNT-1001',
            'color' => CarColor::WHITE->value,
            'price_per_day' => 100,
            'mileage' => 1000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'description' => null,
            'status' => CarStatus::AVAILABLE->value,
        ]);

        $type = MaintenanceType::create([
            'tenant_id' => $tenant->id,
            'name' => 'Oil Change',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.maintenance-records.store', ['subdomain' => $tenant->slug]), [
                'car_id' => $car->id,
                'maintenance_type_id' => $type->id,
                'status' => MaintenanceRecordStatus::IN_PROGRESS->value,
                'scheduled_date' => today()->toDateString(),
                'started_at' => '2026-04-25T10:00',
                'completed_at' => '2026-04-25T10:00',
                'cost' => 100,
                'odometer' => 1200,
                'notes' => 'Maintenance record created from admin panel.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['completed_at']);
    }
}
