<?php

namespace Tests\Feature\Admin;

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Core\TenantContext;
use App\Models\Branch;
use App\Models\Car;
use App\Models\CarDamageItem;
use App\Models\CarDamageReport;
use App\Models\Contract;
use App\Models\ContractReturnReport;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\TenantSiteSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rentals\RentalStatusSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractReturnReportsControllerTest extends TestCase
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

    public function test_contract_return_report_page_opens_with_damage_reports(): void
    {
        $fixtures = $this->createContractReturnFixtures();

        $this->actingAs($fixtures['admin'])
            ->get(route('admin.contracts.return-report', [
                'subdomain' => $fixtures['tenant']->slug,
                'contract' => $fixtures['contract']->id,
            ]))
            ->assertOk()
            ->assertSee($fixtures['damageReport']->report_number)
            ->assertDontSee($fixtures['beforeDeliveryReport']->report_number);
    }

    public function test_admin_can_save_return_report_and_create_cash_payment(): void
    {
        $fixtures = $this->createContractReturnFixtures();

        $this->actingAs($fixtures['admin'])
            ->post(route('admin.contracts.return-report.store', [
                'subdomain' => $fixtures['tenant']->slug,
                'contract' => $fixtures['contract']->id,
            ]), [
                'actual_return_time' => today()->setTime(20, 0)->format('Y-m-d\TH:i'),
                'return_location' => 'Main Office',
                'return_odometer' => 1240,
                'return_fuel_level' => 'half',
                'vehicle_condition_after' => 'not_clean',
                'damage_report_id' => $fixtures['damageReport']->id,
                'payment_status' => 'paid',
                'extra_kilometers' => 0,
                'kilometer_rate' => 0,
                'cleaning_fee' => 0,
                'fuel_fee' => 0,
                'late_hours' => 0,
                'late_hour_rate' => 0,
                'damage_fee' => 0,
                'maintenance_fee' => 30,
                'other_fee' => 5,
                'notes' => 'Return inspection finished.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $report = ContractReturnReport::query()->where('contract_id', $fixtures['contract']->id)->first();
        $this->assertNotNull($report);
        $this->assertSame('paid', $report->payment_status);
        $this->assertSame('Main Office', $report->return_location);
        $this->assertSame(1406.0, (float) $report->total_extra_charges);
        $this->assertNotNull($report->payment_id);

        $payment = Payment::query()->find($report->payment_id);
        $this->assertNotNull($payment);
        $this->assertSame(PaymentMethod::CASH->value, $payment->payment_method instanceof \BackedEnum ? $payment->payment_method->value : (string) $payment->payment_method);
        $this->assertSame(PaymentStatus::COMPLETED->value, $payment->status instanceof \BackedEnum ? $payment->status->value : (string) $payment->status);
        $this->assertSame(1406.0, (float) $payment->amount);

        $this->assertSame(ReservationStatus::COMPLETED->value, $fixtures['reservation']->fresh()->status->value);
        $this->assertSame('completed', $fixtures['contract']->fresh()->status);

        $response = $this->actingAs($fixtures['admin'])
            ->get(route('admin.contracts.return-report.pdf', [
                'subdomain' => $fixtures['tenant']->slug,
                'contractId' => $fixtures['contract']->id,
            ]));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_paid_return_report_locks_related_contract_reservation_and_damage_editing(): void
    {
        $fixtures = $this->createContractReturnFixtures();

        $this->actingAs($fixtures['admin'])
            ->post(route('admin.contracts.return-report.store', [
                'subdomain' => $fixtures['tenant']->slug,
                'contract' => $fixtures['contract']->id,
            ]), [
                'actual_return_time' => today()->setTime(20, 0)->format('Y-m-d\TH:i'),
                'return_location' => 'Main Office',
                'return_odometer' => 1240,
                'return_fuel_level' => 'half',
                'vehicle_condition_after' => 'not_clean',
                'damage_report_id' => $fixtures['damageReport']->id,
                'payment_status' => 'paid',
                'extra_kilometers' => 0,
                'kilometer_rate' => 0,
                'cleaning_fee' => 0,
                'fuel_fee' => 0,
                'fuel_credit' => 0,
                'late_hours' => 0,
                'late_hour_rate' => 0,
                'damage_fee' => 0,
                'maintenance_fee' => 30,
                'other_fee' => 5,
                'notes' => 'Return inspection finished.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($fixtures['admin'])
            ->get(route('admin.contracts.edit', [
                'subdomain' => $fixtures['tenant']->slug,
                'contract' => $fixtures['contract']->id,
            ]))
            ->assertOk()
            ->assertSee('"is_locked":true')
            ->assertSee('"update":null');

        $this->actingAs($fixtures['admin'])
            ->get(route('admin.reservations.edit', [
                'subdomain' => $fixtures['tenant']->slug,
                'reservation' => $fixtures['reservation']->id,
            ]))
            ->assertOk()
            ->assertSee('"is_locked":true');

        $this->actingAs($fixtures['admin'])
            ->get(route('admin.car-damage-reports.edit', [
                'subdomain' => $fixtures['tenant']->slug,
                'carDamageReport' => $fixtures['damageReport']->id,
            ]))
            ->assertOk()
            ->assertSee('"is_locked":true');
    }

    public function test_admin_can_save_return_report_as_not_paid_without_creating_payment(): void
    {
        $fixtures = $this->createContractReturnFixtures();

        $this->actingAs($fixtures['admin'])
            ->post(route('admin.contracts.return-report.store', [
                'subdomain' => $fixtures['tenant']->slug,
                'contract' => $fixtures['contract']->id,
            ]), [
                'actual_return_time' => today()->setTime(20, 0)->format('Y-m-d\TH:i'),
                'return_location' => 'Main Office',
                'return_odometer' => 1240,
                'return_fuel_level' => 'half',
                'vehicle_condition_after' => 'not_clean',
                'damage_report_id' => $fixtures['damageReport']->id,
                'payment_status' => 'not_paid',
                'extra_kilometers' => 0,
                'kilometer_rate' => 0,
                'cleaning_fee' => 0,
                'fuel_fee' => 0,
                'fuel_credit' => 0,
                'late_hours' => 0,
                'late_hour_rate' => 0,
                'damage_fee' => 0,
                'maintenance_fee' => 30,
                'other_fee' => 5,
                'notes' => 'Return inspection finished.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $report = ContractReturnReport::query()->where('contract_id', $fixtures['contract']->id)->first();
        $this->assertNotNull($report);
        $this->assertSame('not_paid', $report->payment_status);
        $this->assertNull($report->payment_id);
        $this->assertSame(1406.0, (float) $report->total_extra_charges);

        $this->assertSame(ReservationStatus::COMPLETED->value, $fixtures['reservation']->fresh()->status->value);
        $this->assertSame('completed', $fixtures['contract']->fresh()->status);
    }

    /**
     * @return array{tenant:Tenant,admin:User,client:User,branch:Branch,car:Car,reservation:Reservation,contract:Contract,damageReport:CarDamageReport,beforeDeliveryReport:CarDamageReport}
     */
    private function createContractReturnFixtures(): array
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

        TenantSiteSetting::create([
            'tenant_id' => $tenant->id,
            'reservation_settings' => [
                'return_time_policy' => [
                    'mode' => 'fixed_time',
                    'fixed_time' => '18:00',
                ],
                'pickup_return_locations' => [
                    [
                        'name' => 'Main Office',
                        'pickup_fee' => 0,
                        'return_fee' => 0,
                        'pickup_free' => true,
                        'return_free' => true,
                        'is_active' => true,
                    ],
                ],
                'kilometer_pricing' => [
                    [
                        'from_km' => 0,
                        'to_km' => 9999,
                        'price' => 5,
                    ],
                ],
                'fuel_pricing' => [
                    [
                        'fuel_level' => 'half',
                        'price' => 20,
                    ],
                ],
                'late_return' => [
                    'mode' => 'hourly',
                    'hourly_fee' => 8,
                    'after_hours' => 0,
                ],
                'cleaning_fee' => 15,
            ],
        ]);

        $car = Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'RET-1001',
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
            'reservation_number' => 'RES-RET-1',
            'start_date' => today()->subDays(3)->toDateString(),
            'end_date' => today()->toDateString(),
            'pickup_time' => '10:00',
            'return_time' => '18:00',
            'pickup_location' => 'Main Office',
            'return_location' => 'Main Office',
            'total_days' => 2,
            'daily_rate' => 100,
            'subtotal' => 200,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 200,
            'status' => ReservationStatus::COMPLETED_WAIT_CONTRACT->value,
        ]);

        $contract = Contract::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'reservation_id' => $reservation->id,
            'contract_number' => 'CTR-RET-1',
            'status' => 'active',
            'contract_date' => today()->subDays(3)->toDateString(),
            'renter_name' => $client->name,
            'renter_id_number' => '123456789',
            'renter_phone' => '97000000000',
            'car_details' => $car->full_name,
            'plate_number' => $car->license_plate,
            'vehicle_odometer' => 1000,
            'vehicle_fuel_level' => 'full',
            'start_date' => today()->subDays(3)->toDateString(),
            'end_date' => today()->toDateString(),
            'total_amount' => 200,
            'currency' => 'USD',
            'notes' => null,
        ]);

        $damageReport = CarDamageReport::create([
            'tenant_id' => $tenant->id,
            'car_id' => $car->id,
            'branch_id' => $branch->id,
            'contract_id' => $contract->id,
            'reservation_id' => $reservation->id,
            'created_by' => $admin->id,
            'report_number' => 'DR-RET-1',
            'report_type' => 'after_return',
            'status' => 'finalized',
            'inspected_at' => now(),
            'odometer' => 1240,
            'summary' => 'Scratch on front bumper.',
        ]);

        CarDamageItem::create([
            'tenant_id' => $tenant->id,
            'car_damage_report_id' => $damageReport->id,
            'zone_code' => 'front_bumper',
            'view_side' => 'front',
            'damage_type' => 'scratch',
            'severity' => 'minor',
            'quantity' => 1,
            'estimated_cost' => 120,
            'sort_order' => 1,
        ]);

        $beforeDeliveryReport = CarDamageReport::create([
            'tenant_id' => $tenant->id,
            'car_id' => $car->id,
            'branch_id' => $branch->id,
            'contract_id' => $contract->id,
            'reservation_id' => $reservation->id,
            'created_by' => $admin->id,
            'report_number' => 'DR-BEFORE-1',
            'report_type' => 'before_delivery',
            'status' => 'finalized',
            'inspected_at' => now()->subDay(),
            'odometer' => 1000,
            'summary' => 'Pre-delivery inspection.',
        ]);

        CarDamageItem::create([
            'tenant_id' => $tenant->id,
            'car_damage_report_id' => $beforeDeliveryReport->id,
            'zone_code' => 'rear_bumper',
            'view_side' => 'rear',
            'damage_type' => 'scratch',
            'severity' => 'minor',
            'quantity' => 1,
            'estimated_cost' => 50,
            'sort_order' => 1,
        ]);

        return compact('tenant', 'admin', 'client', 'branch', 'car', 'reservation', 'contract', 'damageReport', 'beforeDeliveryReport');
    }
}
