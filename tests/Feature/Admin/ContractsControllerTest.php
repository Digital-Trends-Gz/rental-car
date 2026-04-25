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
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ContractForceExtendedNotification;
use App\Services\Rentals\RentalStatusSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ContractsControllerTest extends TestCase
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

    public function test_admin_cannot_create_a_contract_with_a_past_contract_date(): void
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
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'PAST-1001',
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
            'reservation_number' => 'RES-PAST-1',
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(2)->toDateString(),
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
            'status' => ReservationStatus::CONFIRMED->value,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.contracts.store', [
                'subdomain' => $tenant->slug,
            ]), [
                'contract_number' => 'CTR-PAST-1',
                'status' => 'draft',
                'reservation_id' => $reservation->id,
                'contract_date' => today()->subDay()->toDateString(),
                'renter_name' => $client->name,
                'renter_id_number' => '123456789',
                'renter_phone' => '97000000000',
                'start_date' => today()->toDateString(),
                'end_date' => today()->addDays(2)->toDateString(),
                'currency' => 'USD',
                'primary_driver' => [
                    'full_name' => $client->name,
                    'phone' => '97000000000',
                    'identity_number' => '123456789',
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
            ->assertSessionHasErrors('contract_date');
    }

    public function test_admin_cannot_create_a_contract_with_letters_in_identity_numbers(): void
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
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'VALID-1002',
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
            'reservation_number' => 'RES-ID-1',
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(2)->toDateString(),
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
            'status' => ReservationStatus::CONFIRMED->value,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.contracts.store', [
                'subdomain' => $tenant->slug,
            ]), [
                'contract_number' => 'CTR-ID-1',
                'status' => 'draft',
                'reservation_id' => $reservation->id,
                'contract_date' => today()->toDateString(),
                'renter_name' => 'Test Renter',
                'renter_id_number' => 'ABC123',
                'renter_phone' => '97000000000',
                'start_date' => today()->toDateString(),
                'end_date' => today()->addDays(2)->toDateString(),
                'currency' => 'USD',
                'primary_driver' => [
                    'full_name' => 'Test Driver',
                    'phone' => '97000000000',
                    'identity_number' => 'XYZ999',
                    'residency_number' => 'RES-ABC',
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
            ->assertSessionHasErrors([
                'renter_id_number',
                'primary_driver.identity_number',
                'primary_driver.residency_number',
            ]);
    }

    public function test_admin_can_finalize_waiting_reservation_when_contract_is_created(): void
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
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'WAIT-1001',
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
            'reservation_number' => 'RES-WAIT-1',
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(2)->toDateString(),
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
            'status' => ReservationStatus::COMPLETED_WAIT_CONTRACT->value,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.contracts.store', [
                'subdomain' => $tenant->slug,
            ]), [
                'contract_number' => 'CTR-WAIT-1',
                'status' => 'draft',
                'reservation_id' => $reservation->id,
                'contract_date' => today()->toDateString(),
                'renter_name' => 'Test Renter',
                'renter_id_number' => '123456789',
                'renter_phone' => '97000000000',
                'start_date' => today()->toDateString(),
                'end_date' => today()->addDays(2)->toDateString(),
                'currency' => 'USD',
                'primary_driver' => [
                    'full_name' => 'Test Driver',
                    'phone' => '97000000000',
                    'identity_number' => '123456789',
                    'residency_number' => '123456789',
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
            ->assertSessionHasNoErrors();

        $reservation->refresh();
        $this->assertSame(ReservationStatus::COMPLETED, $reservation->status);
        $this->assertDatabaseHas('contracts', [
            'tenant_id' => $tenant->id,
            'reservation_id' => $reservation->id,
            'contract_number' => 'CTR-WAIT-1',
        ]);
    }

    public function test_admin_can_extend_an_active_contract_and_record_cash_payment(): void
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
            'license_plate' => 'EXT-1001',
            'color' => CarColor::WHITE->value,
            'price_per_day' => 100,
            'mileage' => 1000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => FuelType::GASOLINE->value,
            'description' => null,
            'status' => CarStatus::AVAILABLE->value,
        ]);

        $currentEndDate = today()->addDays(2)->toDateString();
        $newEndDate = today()->addDays(5)->toDateString();

        $reservation = Reservation::create([
            'tenant_id' => $tenant->id,
            'user_id' => $client->id,
            'car_id' => $car->id,
            'reservation_number' => 'RES-EXT-1',
            'start_date' => today()->toDateString(),
            'end_date' => $currentEndDate,
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

        $contract = Contract::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'reservation_id' => $reservation->id,
            'contract_number' => 'CTR-EXT-1',
            'status' => 'active',
            'contract_date' => today()->toDateString(),
            'renter_name' => $client->name,
            'renter_id_number' => '123456789',
            'renter_phone' => '97000000000',
            'car_details' => '2024 Toyota Camry',
            'plate_number' => 'EXT-1001',
            'price_per_day' => 100,
            'start_date' => today()->toDateString(),
            'end_date' => $currentEndDate,
            'total_amount' => 300,
            'currency' => 'USD',
        ]);

        $this->assertSame('active', $contract->fresh()->status);
        $this->assertSame($reservation->id, $contract->fresh()->reservation_id);

        Notification::fake();

        $this->actingAs($admin)
            ->post(route('admin.contracts.extend', [
                'subdomain' => $tenant->slug,
                'contract' => $contract->id,
            ]), [
                'new_end_date' => $newEndDate,
                'notes' => 'Guest requested a 3-day extension.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(
            $client,
            ContractForceExtendedNotification::class,
            function (ContractForceExtendedNotification $notification, array $channels, User $notifiable) use ($contract): bool {
                $payload = $notification->toArray($notifiable);

                return $channels === ['database', 'mail']
                    && $payload['contract_id'] === $contract->id;
            }
        );

        $contract->refresh();
        $reservation->refresh();
        $payment = Payment::query()->where('reservation_id', $reservation->id)->latest('id')->first();

        $this->assertSame($newEndDate, $contract->end_date?->toDateString());
        $this->assertSame('600.00', (string) $contract->total_amount);
        $this->assertSame($newEndDate, $reservation->end_date?->toDateString());
        $this->assertSame(6, $reservation->total_days);
        $this->assertSame('600.00', (string) $reservation->total_amount);
        $this->assertNotNull($payment);
        $this->assertSame('300.00', (string) $payment->amount);
        $this->assertSame(PaymentMethod::CASH, $payment->payment_method);
        $this->assertSame(PaymentStatus::COMPLETED, $payment->status);
        $this->assertNotNull($payment->processed_at);
    }
}
