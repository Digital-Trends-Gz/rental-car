<?php

namespace Tests\Feature\Client;

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RentalExtensionRequestStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\RentalExtensionRequest;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ContractExtensionRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ExtensionRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_extension_request_can_be_created_and_approved_by_client(): void
    {
        Notification::fake();

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
            'license_plate' => 'REQ-1001',
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
        $requestedEndDate = today()->addDays(5)->toDateString();

        $reservation = Reservation::create([
            'tenant_id' => $tenant->id,
            'user_id' => $client->id,
            'car_id' => $car->id,
            'reservation_number' => 'RES-REQ-1',
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
            'contract_number' => 'CTR-REQ-1',
            'status' => 'active',
            'contract_date' => today()->toDateString(),
            'renter_name' => $client->name,
            'renter_id_number' => '123456789',
            'renter_phone' => '97000000000',
            'car_details' => '2024 Toyota Camry',
            'plate_number' => 'REQ-1001',
            'price_per_day' => 100,
            'start_date' => today()->toDateString(),
            'end_date' => $currentEndDate,
            'total_amount' => 300,
            'currency' => 'USD',
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware()
            ->post(route('admin.contracts.request-extension', [
                'subdomain' => $tenant->slug,
                'contract' => $contract->id,
            ]), [
                'new_end_date' => $requestedEndDate,
                'notes' => 'Client asked for more days.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($client, ContractExtensionRequestedNotification::class);

        $requestRecord = RentalExtensionRequest::query()->firstOrFail();
        $this->assertSame(RentalExtensionRequestStatus::PENDING, $requestRecord->status);
        $this->assertSame(3, $requestRecord->extra_days);
        $this->assertSame('300.00', (string) $requestRecord->extra_amount);

        $this->actingAs($client)
            ->withoutMiddleware()
            ->post(route('client.reservations.extension-requests.approve', [
                'subdomain' => $tenant->slug,
                'reservation' => $reservation->id,
                'extensionRequest' => $requestRecord->id,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $contract->refresh();
        $reservation->refresh();
        $requestRecord->refresh();
        $payment = Payment::query()->where('reservation_id', $reservation->id)->latest('id')->first();

        $this->assertSame($requestedEndDate, $contract->end_date?->toDateString());
        $this->assertSame('600.00', (string) $contract->total_amount);
        $this->assertSame($requestedEndDate, $reservation->end_date?->toDateString());
        $this->assertSame(6, $reservation->total_days);
        $this->assertSame('600.00', (string) $reservation->total_amount);
        $this->assertSame(RentalExtensionRequestStatus::APPROVED, $requestRecord->status);
        $this->assertNotNull($payment);
        $this->assertSame('300.00', (string) $payment->amount);
        $this->assertSame(PaymentMethod::CASH, $payment->payment_method);
        $this->assertSame(PaymentStatus::PENDING, $payment->status);
    }
}
