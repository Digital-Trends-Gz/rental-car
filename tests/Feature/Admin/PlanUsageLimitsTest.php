<?php

namespace Tests\Feature\Admin;

use App\Core\LandingPageSettings;
use App\Core\TenantContext;
use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Contract;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Http\Middleware\EnsureTenantPlanLimitNotExceeded;
use App\Services\Plans\PlanUsageLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use App\Services\Rentals\RentalStatusSyncService;

class PlanUsageLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        app()->instance(RentalStatusSyncService::class, new class {
            public function syncCarsByIds(array $carIds): void
            {
            }
        });
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_employee_creation_is_blocked_by_plan_limit(): void
    {
        $tenant = $this->tenantWithPlan(['max_employees' => 1]);
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'civil_number' => '11112222',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'name' => 'Existing Employee',
            'email' => 'existing.employee@example.com',
            'civil_number' => '22223333',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.employees.store', ['subdomain' => $tenant->slug]), [
                'name' => 'Second Employee',
                'email' => 'second.employee@example.com',
                'civil_number' => '33334444',
                'branch_id' => $branch->id,
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'is_active' => true,
                'role_ids' => [],
                'permission_ids' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('users', [
            'tenant_id' => $tenant->id,
            'email' => 'second.employee@example.com',
        ]);
    }

    public function test_employee_index_reports_plan_limit_usage(): void
    {
        $tenant = $this->tenantWithPlan(['max_employees' => 3]);
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'civil_number' => '11112222',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        TenantContext::set($tenant);

        $this->actingAs($admin)
            ->get(route('admin.employees.index', ['subdomain' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Employees/Index')
                ->where('employeeUsage.current', 3)
                ->where('employeeUsage.limit', 3)
                ->where('employeeUsage.at_limit', true)
            );
    }

    public function test_plan_limit_message_uses_landing_translation_override(): void
    {
        app()->setLocale('ar');

        SiteSetting::query()->create([
            'key' => LandingPageSettings::KEY,
            'value' => LandingPageSettings::normalize([
                'translations' => [
                    'ar' => [
                        'dashboard' => [
                            'common' => [
                                'employees' => 'موظف مخصص',
                                'plan_limit_reached' => 'رسالة مخصصة :limit :resource',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $tenant = $this->tenantWithPlan(['max_employees' => 1]);
        TenantContext::set($tenant);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Owner',
            'email' => 'owner.translation@example.com',
            'civil_number' => '11112227',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->assertSame(
            'رسالة مخصصة 1 موظف مخصص',
            app(PlanUsageLimits::class)->employeeLimitMessage($tenant)
        );

        app()->setLocale('en');
    }

    public function test_branch_creation_is_blocked_by_plan_limit(): void
    {
        $tenant = $this->tenantWithPlan(['max_branches' => 1]);
        TenantContext::set($tenant);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'civil_number' => '11112222',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Existing Branch',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.branches.store', ['subdomain' => $tenant->slug]), [
                'name' => 'Second Branch',
                'country' => 'KW',
                'city' => 'Kuwait City',
                'street_name' => 'Main Street',
                'street_number' => '1',
                'building_number' => '2',
                'office_number' => '3',
                'post_code' => '12345',
                'phone_1' => '+965 1111 2222',
                'showroom_temp_folders' => [],
                'showroom_removed_files' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('branches', [
            'tenant_id' => $tenant->id,
            'name' => 'Second Branch',
        ]);
    }

    public function test_branch_create_page_is_blocked_by_plan_limit(): void
    {
        $tenant = $this->tenantWithPlan(['max_branches' => 1]);
        TenantContext::set($tenant);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Owner',
            'email' => 'owner.branch.create@example.com',
            'civil_number' => '11112225',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Existing Branch',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.branches.create', ['subdomain' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_branch_create_page_is_blocked_by_plan_limit_from_authenticated_tenant(): void
    {
        $tenant = $this->tenantWithPlan(['max_branches' => 1]);
        TenantContext::clear();

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Owner',
            'email' => 'owner.branch.create.context@example.com',
            'civil_number' => '11112230',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Existing Branch',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.branches.create', ['subdomain' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_branch_index_reports_plan_limit_usage(): void
    {
        $tenant = $this->tenantWithPlan(['max_branches' => 3]);
        TenantContext::set($tenant);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Owner',
            'email' => 'owner.branch.index@example.com',
            'civil_number' => '11112226',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        foreach (range(1, 3) as $index) {
            Branch::create([
                'tenant_id' => $tenant->id,
                'name' => "Branch {$index}",
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.branches.index', ['subdomain' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Branches/Index')
                ->where('branchUsage.current', 3)
                ->where('branchUsage.limit', 3)
                ->where('branchUsage.at_limit', true)
            );
    }

    public function test_inline_branch_creation_is_blocked_by_plan_limit(): void
    {
        $tenant = $this->tenantWithPlan(['max_branches' => 1]);
        TenantContext::set($tenant);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Owner',
            'email' => 'owner.inline@example.com',
            'civil_number' => '11112223',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Existing Branch',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.branches.store', ['subdomain' => $tenant->slug, 'inline' => 1]), [
                'name' => 'Inline Branch',
                'country' => 'KW',
                'city' => 'Kuwait City',
                'street_name' => 'Second Street',
                'phone_1' => '+965 2222 3333',
                'showroom_temp_folders' => [],
                'showroom_removed_files' => [],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Your plan allows up to 1 branches. Upgrade your plan to add more.');

        $this->assertDatabaseMissing('branches', [
            'tenant_id' => $tenant->id,
            'name' => 'Inline Branch',
        ]);
    }

    public function test_plan_limit_middleware_resolves_tenant_from_authenticated_user(): void
    {
        $tenant = $this->tenantWithPlan(['max_branches' => 1]);
        TenantContext::clear();

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Owner',
            'email' => 'owner.middleware@example.com',
            'civil_number' => '11112224',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Existing Branch',
        ]);

        $request = Request::create('/admin/branches', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $request->setUserResolver(fn () => $admin);

        $response = app(EnsureTenantPlanLimitNotExceeded::class)
            ->handle($request, fn () => response()->json(['message' => 'created'], 201), 'branches');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Your plan allows up to 1 branches.', $response->getContent());
    }

    public function test_plan_limit_middleware_fails_closed_without_tenant(): void
    {
        $request = Request::create('/admin/branches', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->expectException(HttpException::class);

        app(EnsureTenantPlanLimitNotExceeded::class)
            ->handle($request, fn () => response()->json(['message' => 'created'], 201), 'branches');
    }

    public function test_car_creation_is_blocked_by_plan_limit(): void
    {
        $tenant = $this->tenantWithPlan(['max_cars' => 1]);
        TenantContext::set($tenant);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'civil_number' => '11112222',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'AAA-111',
            'color' => CarColor::WHITE,
            'price_per_day' => 25,
            'mileage' => 1000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'status' => CarStatus::AVAILABLE,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.cars.store', ['subdomain' => $tenant->slug]), [
                'make' => 'Honda',
                'model' => 'Civic',
                'year' => 2023,
                'license_plate' => 'BBB-222',
                'branch_id' => $branch->id,
                'color' => CarColor::BLACK->value,
                'price_per_day' => 30,
                'mileage' => 500,
                'transmission' => 'automatic',
                'seats' => 5,
                'fuel_type' => FuelType::GASOLINE->value,
                'status' => CarStatus::AVAILABLE->value,
                'image' => [],
                'additional_photos' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('cars', [
            'tenant_id' => $tenant->id,
            'license_plate' => 'BBB-222',
        ]);
    }

    public function test_car_index_reports_plan_limit_usage_from_authenticated_tenant(): void
    {
        $tenant = $this->tenantWithPlan(['max_cars' => 2]);
        TenantContext::clear();

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Owner',
            'email' => 'owner.car.index@example.com',
            'civil_number' => '11112228',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        foreach (range(1, 2) as $index) {
            Car::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'make' => 'Toyota',
                'model' => "Camry {$index}",
                'year' => 2024,
                'license_plate' => "CAR-{$index}",
                'color' => CarColor::WHITE,
                'price_per_day' => 25,
                'mileage' => 1000,
                'transmission' => 'automatic',
                'seats' => 5,
                'fuel_type' => FuelType::GASOLINE->value,
                'status' => CarStatus::AVAILABLE,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.cars.index', ['subdomain' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Cars/Index')
                ->where('carUsage.current', 2)
                ->where('carUsage.limit', 2)
                ->where('carUsage.at_limit', true)
                ->where('canCreateCar', false)
            );
    }

    public function test_car_create_page_is_blocked_by_plan_limit_from_authenticated_tenant(): void
    {
        $tenant = $this->tenantWithPlan(['max_cars' => 1]);
        TenantContext::clear();

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Owner',
            'email' => 'owner.car.create@example.com',
            'civil_number' => '11112231',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'CAR-CREATE-1',
            'color' => CarColor::WHITE,
            'price_per_day' => 25,
            'mileage' => 1000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'status' => CarStatus::AVAILABLE,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.cars.create', ['subdomain' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_car_draft_can_be_saved_with_minimal_data(): void
    {
        $tenant = $this->tenantWithPlan(['max_cars' => 10]);
        TenantContext::set($tenant);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'civil_number' => '11112222',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.cars.store', ['subdomain' => $tenant->slug]), [
                'status' => CarStatus::DRAFT->value,
                'save_as_draft' => true,
                'image' => [],
                'additional_photos' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Car draft saved successfully.');

        $this->assertDatabaseHas('cars', [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'status' => CarStatus::DRAFT->value,
            'make' => 'Draft car',
            'model' => 'Draft car',
        ]);
    }

    public function test_contract_creation_is_blocked_by_plan_limit(): void
    {
        $tenant = $this->tenantWithPlan(['max_contracts' => 1]);
        TenantContext::set($tenant);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'civil_number' => '11112222',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $reservation = \App\Models\Reservation::create([
            'tenant_id' => $tenant->id,
            'reservation_number' => 'RES-001',
            'user_id' => $admin->id,
            'car_id' => Car::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'make' => 'Toyota',
                'model' => 'Camry',
                'year' => 2024,
                'license_plate' => 'CCC-333',
                'color' => CarColor::WHITE,
                'price_per_day' => 25,
                'mileage' => 1000,
                'transmission' => 'automatic',
                'seats' => 5,
                'fuel_type' => FuelType::GASOLINE->value,
                'status' => CarStatus::AVAILABLE,
            ])->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'pickup_time' => '09:00',
            'return_time' => '18:00',
            'total_days' => 2,
            'daily_rate' => 25,
            'subtotal' => 50,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 50,
            'status' => ReservationStatus::CONFIRMED,
        ]);

        Contract::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'reservation_id' => $reservation->id,
            'contract_number' => 'CTR-001',
            'status' => 'draft',
            'currency' => 'USD',
        ]);

        $this->assertNotNull(app(PlanUsageLimits::class)->contractLimitMessage());

        $this->actingAs($admin)
            ->post(route('admin.contracts.store', ['subdomain' => $tenant->slug]), [
                'contract_number' => 'CTR-002',
                'status' => 'draft',
                'reservation_id' => $reservation->id,
                'contract_date' => today()->toDateString(),
                'renter_name' => $admin->name,
                'renter_id_number' => $admin->civil_number,
                'renter_phone' => '97000000000',
                'start_date' => today()->toDateString(),
                'end_date' => today()->addDay()->toDateString(),
                'currency' => 'USD',
                'primary_driver' => [
                    'full_name' => $admin->name,
                    'phone' => '97000000000',
                    'identity_number' => $admin->civil_number,
                    'temp_folders' => [],
                    'removed_file_ids' => [],
                    'documents' => [],
                    'customer_photo_temp_folders' => [],
                    'customer_photo_removed_file_ids' => [],
                ],
                'additional_drivers' => [],
                'contract_archive' => [
                    'temp_folders' => [],
                    'removed_file_ids' => [],
                ],
                'additional_archive' => [],
                'additional_archive_removed_ids' => [],
                'start_contract_temp_folders' => [],
                'start_contract_removed_files' => [],
                'end_contract_temp_folders' => [],
                'end_contract_removed_files' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('contracts', [
            'tenant_id' => $tenant->id,
            'contract_number' => 'CTR-002',
        ]);
    }

    public function test_contract_index_reports_plan_limit_usage_from_authenticated_tenant(): void
    {
        $tenant = $this->tenantWithPlan(['max_contracts' => 2]);
        TenantContext::clear();

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Owner',
            'email' => 'owner.contract.index@example.com',
            'civil_number' => '11112229',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        foreach (range(1, 2) as $index) {
            Contract::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'contract_number' => "CTR-LIMIT-{$index}",
                'status' => 'draft',
                'currency' => 'USD',
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.contracts.index', ['subdomain' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Contracts/Index')
                ->where('contractUsage.current', 2)
                ->where('contractUsage.limit', 2)
                ->where('contractUsage.at_limit', true)
                ->where('canCreateContract', false)
            );
    }

    public function test_contract_create_page_is_blocked_by_plan_limit_from_authenticated_tenant(): void
    {
        $tenant = $this->tenantWithPlan(['max_contracts' => 1]);
        TenantContext::clear();

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Owner',
            'email' => 'owner.contract.create@example.com',
            'civil_number' => '11112232',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Contract::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'contract_number' => 'CTR-CREATE-LIMIT-1',
            'status' => 'draft',
            'currency' => 'USD',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.contracts.create', ['subdomain' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_contract_create_route_middleware_blocks_plan_limit(): void
    {
        $this->withMiddleware();

        $tenant = $this->tenantWithPlan(['max_contracts' => 1]);
        TenantContext::clear();

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $permission = Permission::withoutGlobalScope('tenant')->create([
            'name' => 'tenant-manage-reservations',
            'display_name' => 'Manage Reservations',
            'description' => 'Manage reservations',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'name' => 'Owner',
            'email' => 'owner.contract.middleware@example.com',
            'civil_number' => '11112233',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->syncPermissions([$permission->id]);

        Contract::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'contract_number' => 'CTR-MIDDLEWARE-LIMIT-1',
            'status' => 'draft',
            'currency' => 'USD',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.contracts.create', ['subdomain' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_contract_plan_limit_counts_current_month_only(): void
    {
        $tenant = $this->tenantWithPlan(['max_contracts' => 1]);
        TenantContext::set($tenant);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $previousMonthContract = Contract::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'contract_number' => 'CTR-PREVIOUS-MONTH',
            'status' => 'draft',
            'currency' => 'USD',
        ]);
        $previousMonthContract->forceFill(['created_at' => now()->subMonth()->startOfMonth()])->saveQuietly();

        $this->assertNull(app(PlanUsageLimits::class)->contractLimitMessage($tenant->refresh()));
        $this->assertSame(0, app(PlanUsageLimits::class)->contractUsage($tenant->refresh())['current']);

        Contract::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'contract_number' => 'CTR-CURRENT-MONTH',
            'status' => 'draft',
            'currency' => 'USD',
        ]);

        $this->assertNotNull(app(PlanUsageLimits::class)->contractLimitMessage($tenant->refresh()));
        $this->assertSame(1, app(PlanUsageLimits::class)->contractUsage($tenant->refresh())['current']);
    }

    public function test_reservation_plan_limit_counts_current_month_only(): void
    {
        $tenant = $this->tenantWithPlan(['max_reservations_per_month' => 1]);
        TenantContext::set($tenant);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Owner',
            'email' => 'owner.reservation.limit@example.com',
            'civil_number' => '11112235',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $car = Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'RES-MONTH-1',
            'color' => CarColor::WHITE,
            'price_per_day' => 25,
            'mileage' => 1000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'status' => CarStatus::AVAILABLE,
        ]);

        $previousMonthReservation = Reservation::create([
            'tenant_id' => $tenant->id,
            'reservation_number' => 'RES-PREVIOUS-MONTH',
            'user_id' => $admin->id,
            'car_id' => $car->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'total_days' => 2,
            'daily_rate' => 25,
            'subtotal' => 50,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 50,
            'status' => ReservationStatus::CONFIRMED,
        ]);
        $previousMonthReservation->forceFill(['created_at' => now()->subMonth()->startOfMonth()])->saveQuietly();

        $this->assertNull(app(PlanUsageLimits::class)->reservationLimitMessage($tenant->refresh()));
        $this->assertSame(0, app(PlanUsageLimits::class)->reservationUsage($tenant->refresh())['current']);

        Reservation::create([
            'tenant_id' => $tenant->id,
            'reservation_number' => 'RES-CURRENT-MONTH',
            'user_id' => $admin->id,
            'car_id' => $car->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'total_days' => 2,
            'daily_rate' => 25,
            'subtotal' => 50,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 50,
            'status' => ReservationStatus::CONFIRMED,
        ]);

        $this->assertNotNull(app(PlanUsageLimits::class)->reservationLimitMessage($tenant->refresh()));
        $this->assertSame(1, app(PlanUsageLimits::class)->reservationUsage($tenant->refresh())['current']);
    }

    private function tenantWithPlan(array $limits): Tenant
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $plan = Plan::create(array_merge([
            'name' => 'Plan '.uniqid(),
            'monthly_price' => 10,
            'yearly_price' => 100,
            'is_active' => true,
        ], $limits));

        $tenant->update([
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->addMonth(),
        ]);

        return $tenant->refresh();
    }
}
