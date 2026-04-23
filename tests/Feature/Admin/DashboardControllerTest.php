<?php

namespace Tests\Feature\Admin;

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_contracts_ending_soon(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'email' => 'tenant-a@example.com',
            'plan' => 'basic',
            'is_active' => true,
        ]);

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
            'license_plate' => 'DASH-1001',
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
            'reservation_number' => 'RES-DASH-1',
            'start_date' => today()->subDays(2)->toDateString(),
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
            'status' => 'active',
        ]);

        $contract = Contract::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'reservation_id' => $reservation->id,
            'contract_number' => 'CON-0001',
            'status' => 'active',
            'contract_date' => today()->toDateString(),
            'renter_name' => $client->name,
            'renter_id_number' => '123456789',
            'renter_phone' => '97000000000',
            'car_details' => 'Toyota Camry 2024',
            'plate_number' => 'DASH-1001',
            'start_date' => today()->subDays(2)->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'total_amount' => 300,
            'currency' => 'USD',
        ]);

        $payment = Payment::create([
            'tenant_id' => $tenant->id,
            'reservation_id' => $reservation->id,
            'user_id' => $client->id,
            'amount' => 150,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentStatus::COMPLETED,
            'notes' => 'Rental extension payment recorded from contract extension. 1 day(s) added at USD 150.00 per day.',
            'processed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware()
            ->get(route('admin.dashboard', ['subdomain' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('expiringContracts', 1)
                ->where('expiringContracts.0.contract_number', $contract->contract_number)
                ->where('expiringContracts.0.client_name', $client->name)
                ->where('expiringContracts.0.days_remaining', 1)
                ->has('recentForcedExtensions', 1)
                ->where('recentForcedExtensions.0.payment_number', $payment->payment_number)
                ->where('recentForcedExtensions.0.contract_number', $contract->contract_number)
                ->where('recentForcedExtensions.0.client_name', $client->name)
            );
    }
}
