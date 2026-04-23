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
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rentals\RentalStatusSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->assertSame(ReservationStatus::COMPLETED, $reservation->status);
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
}
