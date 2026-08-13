<?php

namespace Tests\Feature\Admin;

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Car;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use App\Models\User;
use App\Services\Rentals\RentalStatusSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReservationsControllerTest extends TestCase
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

    public function test_admin_can_create_reservation_with_cash_deposit_payment(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
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
            'branch_id' => null,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'RES-2024',
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
            ->post(route('admin.reservations.store', ['subdomain' => $tenant->slug]), [
                'user_id' => $client->id,
                'car_id' => $car->id,
                'start_date' => '2026-04-25',
                'end_date' => '2026-04-27',
                'pickup_time' => '10:00',
                'return_time' => '18:00',
                'pickup_location' => 'Main Office',
                'return_location' => 'Main Office',
                'discount_amount' => 0,
                'deposit_amount' => 150,
                'notes' => 'Reservation created from dashboard.',
                'status' => 'confirmed',
            ])
            ->assertRedirect();

        $reservation = Reservation::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $client->id)
            ->where('car_id', $car->id)
            ->firstOrFail();

        $this->assertSame(3, $reservation->total_days);
        $this->assertSame('300.00', (string) $reservation->subtotal);
        $this->assertSame('363.00', (string) $reservation->total_amount);
        $this->assertCount(1, $reservation->payments);

        $payment = $reservation->payments->first();
        $this->assertSame('150.00', (string) $payment->amount);
        $this->assertSame(PaymentMethod::CASH, $payment->payment_method);
        $this->assertSame(PaymentStatus::COMPLETED, $payment->status);
        $this->assertNotNull($payment->processed_at);

        $this->assertDatabaseHas('payments', [
            'tenant_id' => $tenant->id,
            'reservation_id' => $reservation->id,
            'user_id' => $client->id,
            'amount' => '150.00',
            'currency' => strtoupper((string) config('app.currency_code', 'USD')),
            'payment_method' => PaymentMethod::CASH->value,
            'status' => PaymentStatus::COMPLETED->value,
        ]);
    }

    public function test_admin_can_create_reservation_with_return_location_fee_from_settings(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        TenantSiteSetting::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'reservation_settings' => [
                    'return_time_policy' => [
                        'mode' => 'fixed_fee',
                        'fixed_fee' => 0,
                    ],
                    'pickup_return_locations' => [
                        [
                            'name' => 'Main Office',
                            'pickup_fee' => 0,
                            'return_fee' => 25,
                            'pickup_free' => true,
                            'return_free' => false,
                            'is_active' => true,
                        ],
                    ],
                ],
            ]
        );

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
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
            'branch_id' => null,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'RES-FEE-1',
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
            ->post(route('admin.reservations.store', ['subdomain' => $tenant->slug]), [
                'user_id' => $client->id,
                'car_id' => $car->id,
                'start_date' => '2026-04-25',
                'end_date' => '2026-04-27',
                'pickup_time' => '10:00',
                'return_time' => '18:00',
                'pickup_location' => 'Main Office',
                'return_location' => 'Main Office',
                'discount_amount' => 0,
                'deposit_amount' => 0,
                'notes' => 'Reservation created from dashboard.',
                'status' => 'confirmed',
            ])
            ->assertRedirect();

        $reservation = Reservation::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $client->id)
            ->where('car_id', $car->id)
            ->firstOrFail();

        $this->assertSame('25.00', (string) $reservation->return_location_fee);
        $this->assertSame('388.00', (string) $reservation->total_amount);
    }

    public function test_admin_can_override_return_location_fee_for_single_reservation(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        TenantSiteSetting::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'reservation_settings' => [
                    'return_time_policy' => [
                        'mode' => 'fixed_fee',
                        'fixed_fee' => 0,
                    ],
                    'pickup_return_locations' => [
                        [
                            'name' => 'Main Office',
                            'pickup_fee' => 0,
                            'return_fee' => 25,
                            'pickup_free' => true,
                            'return_free' => false,
                            'is_active' => true,
                        ],
                    ],
                ],
            ]
        );

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
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
            'branch_id' => null,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'RES-FEE-2',
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
            ->post(route('admin.reservations.store', ['subdomain' => $tenant->slug]), [
                'user_id' => $client->id,
                'car_id' => $car->id,
                'start_date' => '2026-04-25',
                'end_date' => '2026-04-27',
                'pickup_time' => '10:00',
                'return_time' => '18:00',
                'pickup_location' => 'Main Office',
                'return_location' => 'Main Office',
                'return_location_fee' => 7.5,
                'discount_amount' => 0,
                'deposit_amount' => 0,
                'notes' => 'Reservation created from dashboard.',
                'status' => 'confirmed',
            ])
            ->assertRedirect();

        $reservation = Reservation::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $client->id)
            ->where('car_id', $car->id)
            ->firstOrFail();

        $this->assertSame('7.50', (string) $reservation->return_location_fee);
        $this->assertSame('370.50', (string) $reservation->total_amount);
    }

    public function test_admin_cannot_create_reservation_with_end_date_equal_to_start_date(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
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
            'branch_id' => null,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2024,
            'license_plate' => 'RES-SAME-1',
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
            ->post(route('admin.reservations.store', ['subdomain' => $tenant->slug]), [
                'user_id' => $client->id,
                'car_id' => $car->id,
                'start_date' => '2026-04-25',
                'end_date' => '2026-04-25',
                'pickup_time' => '10:00',
                'return_time' => '18:00',
                'pickup_location' => 'Main Office',
                'return_location' => 'Main Office',
                'discount_amount' => 0,
                'deposit_amount' => 0,
                'notes' => 'Reservation created from dashboard.',
                'status' => 'confirmed',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['end_date']);
    }

    public function test_admin_reservation_create_form_hides_system_managed_status_option(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reservations.create', ['subdomain' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reservations/Edit')
                ->where('enums.statuses', fn ($statuses) => collect($statuses)->doesntContain(
                    fn ($status) => ($status['value'] ?? null) === ReservationStatus::COMPLETED_WAIT_CONTRACT->value
                ))
            );
    }

    public function test_admin_cannot_manually_set_system_managed_reservation_status(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
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
            'branch_id' => null,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2024,
            'license_plate' => 'RES-3030',
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
            ->post(route('admin.reservations.store', ['subdomain' => $tenant->slug]), [
                'user_id' => $client->id,
                'car_id' => $car->id,
                'start_date' => '2026-04-25',
                'end_date' => '2026-04-27',
                'pickup_time' => '10:00',
                'return_time' => '18:00',
                'pickup_location' => 'Main Office',
                'return_location' => 'Main Office',
                'discount_amount' => 0,
                'deposit_amount' => 0,
                'notes' => 'Reservation created from dashboard.',
                'status' => ReservationStatus::COMPLETED_WAIT_CONTRACT->value,
            ])
            ->assertSessionHasErrors(['status']);
    }

    public function test_admin_completed_status_without_contract_is_converted_to_waiting_contract_status(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
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
            'branch_id' => null,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2024,
            'license_plate' => 'RES-4040',
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
            ->post(route('admin.reservations.store', ['subdomain' => $tenant->slug]), [
                'user_id' => $client->id,
                'car_id' => $car->id,
                'start_date' => '2026-04-25',
                'end_date' => '2026-04-27',
                'pickup_time' => '10:00',
                'return_time' => '18:00',
                'pickup_location' => 'Main Office',
                'return_location' => 'Main Office',
                'discount_amount' => 0,
                'deposit_amount' => 0,
                'notes' => 'Reservation created from dashboard.',
                'status' => ReservationStatus::COMPLETED->value,
            ])
            ->assertRedirect();

        $reservation = Reservation::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $client->id)
            ->where('car_id', $car->id)
            ->firstOrFail();

        $this->assertSame(ReservationStatus::COMPLETED_WAIT_CONTRACT, $reservation->status);
    }

    public function test_admin_can_collect_final_cash_payment_and_complete_reservation(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
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
            'branch_id' => null,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'RES-2025',
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
            ->post(route('admin.reservations.store', ['subdomain' => $tenant->slug]), [
                'user_id' => $client->id,
                'car_id' => $car->id,
                'start_date' => '2026-04-25',
                'end_date' => '2026-04-27',
                'pickup_time' => '10:00',
                'return_time' => '18:00',
                'pickup_location' => 'Main Office',
                'return_location' => 'Main Office',
                'discount_amount' => 0,
                'deposit_amount' => 150,
                'notes' => 'Reservation created from dashboard.',
                'status' => 'confirmed',
            ])
            ->assertRedirect();

        $reservation = Reservation::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $client->id)
            ->where('car_id', $car->id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->post('http://' . $tenant->slug . '.real-rent-car-main.test/admin/reservations/' . $reservation->id . '/cash-payment')
            ->assertRedirect();

        $reservation->refresh()->load('payments');

        $this->assertSame(ReservationStatus::COMPLETED_WAIT_CONTRACT, $reservation->status);
        $this->assertCount(2, $reservation->payments);

        $finalPayment = $reservation->payments->sortByDesc('id')->first();
        $this->assertSame('213.00', (string) $finalPayment->amount);
        $this->assertSame(PaymentMethod::CASH, $finalPayment->payment_method);
        $this->assertSame(PaymentStatus::COMPLETED, $finalPayment->status);
        $this->assertNotNull($finalPayment->processed_at);

        $this->assertDatabaseHas('payments', [
            'reservation_id' => $reservation->id,
            'amount' => '213.00',
            'payment_method' => PaymentMethod::CASH->value,
            'status' => PaymentStatus::COMPLETED->value,
        ]);
    }

    public function test_admin_cannot_collect_final_cash_when_online_payment_is_pending(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
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
            'branch_id' => null,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'RES-PENDING-PAY',
            'color' => CarColor::WHITE->value,
            'price_per_day' => 100,
            'mileage' => 1000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'description' => null,
            'status' => CarStatus::AVAILABLE->value,
        ]);

        $reservation = Reservation::withoutEvents(fn () => Reservation::create([
            'tenant_id' => $tenant->id,
            'reservation_number' => 'RES-PENDING-PAYMENT',
            'user_id' => $client->id,
            'car_id' => $car->id,
            'start_date' => '2026-04-25',
            'end_date' => '2026-04-27',
            'total_days' => 3,
            'daily_rate' => 100,
            'subtotal' => 300,
            'tax_amount' => 63,
            'discount_amount' => 0,
            'total_amount' => 363,
            'status' => ReservationStatus::CONFIRMED,
        ]));

        Payment::create([
            'tenant_id' => $tenant->id,
            'reservation_id' => $reservation->id,
            'user_id' => $client->id,
            'amount' => 114.59,
            'currency' => 'AED',
            'payment_method' => PaymentMethod::MYFATOORAH,
            'status' => PaymentStatus::PENDING,
        ]);

        $this->assertDatabaseHas('payments', [
            'reservation_id' => $reservation->id,
            'payment_method' => PaymentMethod::MYFATOORAH->value,
            'status' => PaymentStatus::PENDING->value,
        ]);
        $this->assertTrue(\Illuminate\Support\Facades\DB::table('payments')
            ->where('reservation_id', $reservation->id)
            ->where('status', PaymentStatus::PENDING->value)
            ->where('payment_method', '!=', PaymentMethod::CASH->value)
            ->whereNull('deleted_at')
            ->exists());

        $this->actingAs($admin)
            ->get(route('admin.reservations.show', [
                'subdomain' => $tenant->slug,
                'reservation' => $reservation->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reservation.can_collect_final_cash', false)
            );

        $this->actingAs($admin)
            ->post('http://' . $tenant->slug . '.real-rent-car-main.test/admin/reservations/' . $reservation->id . '/cash-payment')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('payments', [
            'reservation_id' => $reservation->id,
            'payment_method' => PaymentMethod::CASH->value,
            'status' => PaymentStatus::COMPLETED->value,
        ]);
    }
}
