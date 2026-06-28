<?php

namespace Tests\Feature\Api;

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\ContractStatus;
use App\Enums\FuelType;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Contract;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rentals\RentalStatusSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccidentReportsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->instance(RentalStatusSyncService::class, new class {
            public function syncCarsByIds(array $carIds): void
            {
            }
        });
    }

    public function test_accident_report_options_include_internal_workflows(): void
    {
        [$tenant, $branch, $admin, $car] = $this->createAccidentApiContext();

        $employee = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson(route('api.accident-reports.options', [
            'branch_id' => $branch->id,
        ]), [
            'Accept-Language' => 'ar',
        ]);

        $response->assertOk()
            ->assertJsonPath('contexts.0.value', 'contract')
            ->assertJsonPath('contexts.0.label', "\u{0645}\u{0639} \u{0627}\u{0644}\u{0639}\u{0645}\u{064A}\u{0644}")
            ->assertJsonPath('contexts.1.value', 'employee')
            ->assertJsonPath('contexts.2.value', 'branch')
            ->assertJsonPath('responsibilities.3.value', 'third_party')
            ->assertJsonPath('location_types.1.value', 'branch_gate')
            ->assertJsonPath('branches.0.id', $branch->id)
            ->assertJsonPath('cars.0.id', $car->id);

        $employeeIds = collect($response->json('employees'))->pluck('id');
        $this->assertTrue($employeeIds->contains($admin->id));
        $this->assertTrue($employeeIds->contains($employee->id));
    }

    public function test_accident_report_options_can_be_loaded_separately(): void
    {
        [, $branch, $admin, $car] = $this->createAccidentApiContext();

        Sanctum::actingAs($admin, ['*']);

        $this->getJson(route('api.accident-reports.context-options'), [
            'Accept-Language' => 'ar',
        ])
            ->assertOk()
            ->assertJsonPath('contexts.0.value', 'contract')
            ->assertJsonMissingPath('cars');

        $this->getJson(route('api.accident-reports.branch-options'))
            ->assertOk()
            ->assertJsonPath('branches.0.id', $branch->id)
            ->assertJsonMissingPath('employees');

        $this->getJson(route('api.accident-reports.car-options', [
            'branch_id' => $branch->id,
        ]))
            ->assertOk()
            ->assertJsonPath('cars.0.id', $car->id)
            ->assertJsonMissingPath('branches');

        $this->getJson(route('api.accident-reports.employee-options', [
            'branch_id' => $branch->id,
        ]))
            ->assertOk()
            ->assertJsonPath('employees.0.id', $admin->id)
            ->assertJsonMissingPath('cars');

        $this->getJson(route('api.accident-reports.responsibility-options'))
            ->assertOk()
            ->assertJsonPath('responsibilities.0.value', 'customer')
            ->assertJsonMissingPath('location_types');

        $this->getJson(route('api.accident-reports.location-type-options'))
            ->assertOk()
            ->assertJsonPath('location_types.0.value', 'road')
            ->assertJsonMissingPath('responsibilities');
    }

    public function test_employee_options_include_tenant_owner_accounts(): void
    {
        [$tenant, $branch, $admin] = $this->createAccidentApiContext();

        $primaryAccount = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Office Owner',
            'email' => $tenant->email,
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $tenantOwnerRole = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'tenant-owner',
            'display_name' => 'Tenant Owner',
            'description' => 'Tenant owner',
        ]);

        $roleOwnerAccount = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Role Owner',
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $roleOwnerAccount->roles()->syncWithoutDetaching([$tenantOwnerRole->id]);

        $employee = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Branch Employee',
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson(route('api.accident-reports.employee-options', [
            'branch_id' => $branch->id,
        ]));

        $response->assertOk();

        $employeeIds = collect($response->json('employees'))->pluck('id');

        $this->assertTrue($employeeIds->contains($admin->id));
        $this->assertTrue($employeeIds->contains($employee->id));
        $this->assertTrue($employeeIds->contains($primaryAccount->id));
        $this->assertTrue($employeeIds->contains($roleOwnerAccount->id));
    }

    public function test_api_can_create_branch_accident_without_contract(): void
    {
        [, $branch, $admin, $car] = $this->createAccidentApiContext();

        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson(route('api.accident-reports.store'), [
            'accident_context' => 'branch',
            'branch_id' => $branch->id,
            'car_id' => $car->id,
            'responsibility' => 'third_party',
            'location_type' => 'branch_gate',
            'accident_at' => '2026-06-20 14:50:00',
            'location' => 'Office gate',
            'latitude' => 31.5013824,
            'longitude' => 34.4596665,
            'police_report_number' => 'pol-123456',
            'description' => 'Third party hit the car at the office gate.',
            'has_injuries' => true,
            'third_party_involved' => true,
            'third_party_details' => [
                'name' => 'Other Driver',
                'phone' => '970599000000',
                'plate_number' => 'TP-001',
            ],
        ], [
            'Accept-Language' => 'ar',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', "\u{062A}\u{0645} \u{0625}\u{0646}\u{0634}\u{0627}\u{0621} \u{0628}\u{0644}\u{0627}\u{063A} \u{0627}\u{0644}\u{062D}\u{0627}\u{062F}\u{062B} \u{0628}\u{0646}\u{062C}\u{0627}\u{062D}.")
            ->assertJsonPath('accident_report.contract', null)
            ->assertJsonPath('accident_report.accident_context', 'branch')
            ->assertJsonPath('accident_report.accident_context_label', "\u{0639}\u{0646}\u{062F} \u{0627}\u{0644}\u{0645}\u{0643}\u{062A}\u{0628} \u{0623}\u{0648} \u{0627}\u{0644}\u{0628}\u{0648}\u{0627}\u{0628}\u{0629}")
            ->assertJsonPath('accident_report.responsibility', 'third_party')
            ->assertJsonPath('accident_report.location_type', 'branch_gate')
            ->assertJsonPath('accident_report.branch.id', $branch->id)
            ->assertJsonPath('accident_report.car.id', $car->id);

        $this->assertDatabaseHas('accident_reports', [
            'contract_id' => null,
            'accident_context' => 'branch',
            'branch_id' => $branch->id,
            'car_id' => $car->id,
            'responsibility' => 'third_party',
            'location_type' => 'branch_gate',
        ]);
    }

    public function test_validation_errors_follow_accept_language_header(): void
    {
        [, , $admin] = $this->createAccidentApiContext();

        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson(route('api.accident-reports.store'), [
            'accident_context' => 'contract',
            'description' => 'Missing contract id.',
        ], [
            'Accept-Language' => 'ar',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'حقل العقد مطلوب عندما يكون نوع الحادث مع العميل.')
            ->assertJsonPath('errors.contract_id.0', 'حقل العقد مطلوب عندما يكون نوع الحادث مع العميل.');
    }

    public function test_access_validation_errors_follow_accept_language_header(): void
    {
        [, $branch, $admin] = $this->createAccidentApiContext();

        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson(route('api.accident-reports.store'), [
            'accident_context' => 'employee',
            'branch_id' => $branch->id,
            'car_id' => 999999,
            'employee_id' => $admin->id,
            'responsibility' => 'employee',
            'location_type' => 'road',
            'description' => 'Invalid car id.',
        ], [
            'Accept-Language' => 'ar',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'السيارة المحددة غير صحيحة أو غير متاحة.')
            ->assertJsonPath('errors.car_id.0', 'السيارة المحددة غير صحيحة أو غير متاحة.');
    }

    public function test_contract_accident_endpoint_still_creates_contract_reports(): void
    {
        [$tenant, $branch, $admin, $car] = $this->createAccidentApiContext();
        $client = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::CLIENT,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $contract = $this->createContract($tenant, $branch, $client, $car);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson(route('api.contracts.accident-reports.store', [
            'contract' => $contract->id,
        ]), [
            'accident_at' => '2026-06-20 14:50:00',
            'location' => 'Road',
            'description' => 'Customer reported a side collision.',
            'has_injuries' => false,
            'third_party_involved' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('accident_report.accident_context', 'contract')
            ->assertJsonPath('accident_report.contract.id', $contract->id)
            ->assertJsonPath('accident_report.branch.id', $branch->id)
            ->assertJsonPath('accident_report.car.id', $car->id);

        $this->assertDatabaseHas('accident_reports', [
            'contract_id' => $contract->id,
            'reservation_id' => $contract->reservation_id,
            'car_id' => $car->id,
            'branch_id' => $branch->id,
            'accident_context' => 'contract',
            'responsibility' => 'customer',
        ]);
    }

    /**
     * @return array{Tenant, Branch, User, Car}
     */
    private function createAccidentApiContext(): array
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

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

        $car = Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'ACC-API-001',
            'color' => CarColor::WHITE->value,
            'price_per_day' => 100,
            'mileage' => 1000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'description' => null,
            'status' => CarStatus::AVAILABLE->value,
        ]);

        return [$tenant, $branch, $admin, $car];
    }

    private function createContract(Tenant $tenant, Branch $branch, User $client, Car $car): Contract
    {
        $reservation = Reservation::create([
            'tenant_id' => $tenant->id,
            'user_id' => $client->id,
            'car_id' => $car->id,
            'reservation_number' => 'RES-ACC-API',
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'pickup_time' => '09:00',
            'return_time' => '18:00',
            'pickup_location' => 'Main Office',
            'return_location' => 'Main Office',
            'total_days' => 2,
            'daily_rate' => 100,
            'subtotal' => 200,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 200,
            'status' => ReservationStatus::ACTIVE,
        ]);

        return Contract::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'reservation_id' => $reservation->id,
            'contract_number' => 'CON-ACC-API',
            'status' => ContractStatus::ACTIVE,
            'contract_date' => today()->toDateString(),
            'renter_name' => $client->name,
            'renter_id_number' => '123456789',
            'renter_phone' => '97000000000',
            'car_details' => 'Toyota Camry 2024',
            'plate_number' => $car->license_plate,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'total_amount' => 200,
            'currency' => 'USD',
        ]);
    }
}
