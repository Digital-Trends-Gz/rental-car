<?php

namespace Tests\Feature\Admin;

use App\Core\TenantContext;
use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Contract;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportsControllerTest extends TestCase
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

    public function test_admin_reports_index_loads_without_json_path_error(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

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
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'RPT-1001',
            'color' => CarColor::WHITE->value,
            'price_per_day' => 100,
            'mileage' => 1000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'description' => null,
            'status' => CarStatus::AVAILABLE->value,
        ]);

        $reservation = Reservation::create([
            'tenant_id' => $tenant->id,
            'user_id' => $client->id,
            'car_id' => $car->id,
            'reservation_number' => 'RES-RPT-1',
            'start_date' => today()->subDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'pickup_time' => '10:00',
            'return_time' => '18:00',
            'pickup_location' => 'Main Office',
            'return_location' => 'Main Office',
            'total_days' => 3,
            'daily_rate' => 100,
            'subtotal' => 300,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 300,
            'status' => ReservationStatus::ACTIVE->value,
        ]);

        Contract::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'reservation_id' => $reservation->id,
            'contract_number' => 'CON-RPT-1',
            'status' => 'active',
            'contract_date' => today()->subDay()->toDateString(),
            'renter_name' => $client->name,
            'renter_id_number' => '123456789',
            'renter_phone' => '97000000000',
            'car_details' => 'Toyota Camry 2024',
            'plate_number' => 'RPT-1001',
            'start_date' => today()->subDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'total_amount' => 300,
            'currency' => 'USD',
            'handover_state' => [
                'delivery' => [
                    'steps' => [
                        ['page' => 1, 'payload' => []],
                        ['page' => 2, 'payload' => []],
                        ['page' => 3, 'payload' => []],
                        ['page' => 4, 'payload' => []],
                        ['page' => 5, 'payload' => ['accepted_terms' => false]],
                    ],
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'subdomain' => $tenant->slug,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Index')
                ->has('financialReportSections', 4)
                ->where('financialReportSections.0.title.ar', 'الإيرادات')
                ->where('financialReportSections.1.items.0.ar', 'نقدي')
            );
    }
}
