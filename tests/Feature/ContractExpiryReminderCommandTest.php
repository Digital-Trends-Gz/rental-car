<?php

namespace Tests\Feature;

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Contract;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ContractEndingTomorrowNotification;
use App\Services\Rentals\RentalStatusSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractExpiryReminderCommandTest extends TestCase
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

    public function test_command_notifies_admins_when_contract_ends_tomorrow(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'email' => 'tenant-a@example.com',
            'plan' => 'basic',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $client = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::CLIENT,
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $car = Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'CON-1001',
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
            'reservation_number' => 'RES-CONTRACT-1',
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
            'plate_number' => 'CON-1001',
            'start_date' => today()->subDays(2)->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'total_amount' => 300,
            'currency' => 'USD',
        ]);

        $notification = new ContractEndingTomorrowNotification($contract);
        $this->assertSame(['database', 'mail'], $notification->via($admin));

        $this->artisan('contracts:notify-ending-tomorrow')
            ->assertExitCode(0);

        $admin->refresh();

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
            'type' => ContractEndingTomorrowNotification::class,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
            'type' => ContractEndingTomorrowNotification::class,
            'data->kind' => 'contract_ending_tomorrow',
            'data->contract_id' => $contract->id,
        ]);
    }
}
