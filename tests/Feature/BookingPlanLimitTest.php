<?php

namespace Tests\Feature;

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\BookingRequest;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rentals\RentalStatusSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPlanLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_booking_over_monthly_reservation_limit_creates_locked_request_without_payment_or_reservation(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'trial_ends_at' => now()->addMonth(),
        ]);

        $plan = Plan::create([
            'name' => 'Limited Plan',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'max_reservations_per_month' => 1,
            'is_active' => true,
        ]);
        $tenant->update(['plan_id' => $plan->id]);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $car = Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2024,
            'license_plate' => 'BOOK-LIMIT-1',
            'color' => CarColor::WHITE,
            'price_per_day' => 25,
            'transmission' => 'automatic',
            'seats' => 5,
            'mileage' => 0,
            'fuel_type' => FuelType::GASOLINE,
            'status' => CarStatus::AVAILABLE,
        ]);

        $client = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::CLIENT,
            'name' => 'Public Client',
            'email' => 'public.client@example.com',
            'civil_number' => '12345670',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Reservation::withoutEvents(fn () => Reservation::create([
            'tenant_id' => $tenant->id,
            'reservation_number' => 'RES-CURRENT-LIMIT',
            'user_id' => $client->id,
            'car_id' => $car->id,
            'start_date' => today()->subDays(10)->toDateString(),
            'end_date' => today()->subDays(8)->toDateString(),
            'total_days' => 3,
            'daily_rate' => 25,
            'subtotal' => 75,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 75,
            'status' => ReservationStatus::COMPLETED,
        ]));

        $this->actingAs($client)
            ->post(route('tenant.fleet.book', ['subdomain' => $tenant->slug, 'car' => $car->id]), [
                'start_date' => today()->addDays(3)->toDateString(),
                'end_date' => today()->addDays(5)->toDateString(),
                'pickup_location' => 'Office',
                'return_location' => 'Office',
            ])
            ->assertRedirect(route('tenant.fleet.show', ['subdomain' => $tenant->slug, 'car' => $car->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('booking_requests', [
            'tenant_id' => $tenant->id,
            'car_id' => $car->id,
            'user_id' => $client->id,
            'status' => BookingRequest::STATUS_LOCKED_PLAN_LIMIT,
            'locked_reason' => 'reservation_plan_limit',
        ]);

        $bookingRequest = BookingRequest::withoutTenantScope()->firstOrFail();
        $this->assertSame('Public Client', $bookingRequest->customer_name);
        $this->assertNotSame('Public Client', $bookingRequest->getRawOriginal('customer_name'));
    }

    public function test_locked_booking_request_can_be_converted_after_plan_allows_more_monthly_reservations(): void
    {
        $this->mock(RentalStatusSyncService::class, function ($mock) {
            $mock->shouldReceive('syncCarsByIds')->zeroOrMoreTimes();
        });

        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'trial_ends_at' => now()->addMonth(),
        ]);

        $plan = Plan::create([
            'name' => 'Upgradeable Plan',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'max_reservations_per_month' => 2,
            'is_active' => true,
        ]);
        $tenant->update(['plan_id' => $plan->id]);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $car = Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2024,
            'license_plate' => 'CONVERT-LIMIT-1',
            'color' => CarColor::WHITE,
            'price_per_day' => 25,
            'transmission' => 'automatic',
            'seats' => 5,
            'mileage' => 0,
            'fuel_type' => FuelType::GASOLINE,
            'status' => CarStatus::AVAILABLE,
        ]);

        $admin = $this->adminWithPermission($tenant, $branch, 'tenant-manage-reservations');
        $existingClient = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::CLIENT,
            'name' => 'Existing Client',
            'email' => 'existing.client@example.com',
            'civil_number' => '12345670',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Reservation::withoutEvents(fn () => Reservation::create([
            'tenant_id' => $tenant->id,
            'reservation_number' => 'RES-CONVERT-LIMIT-1',
            'user_id' => $existingClient->id,
            'car_id' => $car->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->startOfMonth()->addDay()->toDateString(),
            'total_days' => 2,
            'daily_rate' => 25,
            'subtotal' => 50,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 50,
            'status' => ReservationStatus::COMPLETED,
        ]));

        $bookingRequest = BookingRequest::create([
            'tenant_id' => $tenant->id,
            'car_id' => $car->id,
            'status' => BookingRequest::STATUS_LOCKED_PLAN_LIMIT,
            'start_date' => today()->addDays(10)->toDateString(),
            'end_date' => today()->addDays(12)->toDateString(),
            'total_days' => 3,
            'daily_rate' => 25,
            'subtotal' => 75,
            'tax_amount' => 15.75,
            'discount_amount' => 0,
            'return_location_fee' => 0,
            'total_amount' => 90.75,
            'currency' => 'USD',
            'customer_name' => 'Locked Client',
            'customer_email' => 'locked.client@example.com',
            'customer_phone' => '+970599000001',
            'pickup_location' => 'Office',
            'return_location' => 'Office',
            'locked_reason' => 'reservation_plan_limit',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.booking-requests.convert', [
                'subdomain' => $tenant->slug,
                'bookingRequest' => $bookingRequest->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('booking_requests', [
            'id' => $bookingRequest->id,
            'status' => BookingRequest::STATUS_CONVERTED,
        ]);

        $convertedRequest = $bookingRequest->refresh();

        $this->assertNotNull($convertedRequest->converted_reservation_id);
        $this->assertDatabaseHas('reservations', [
            'id' => $convertedRequest->converted_reservation_id,
            'tenant_id' => $tenant->id,
            'car_id' => $car->id,
            'status' => ReservationStatus::PENDING->value,
            'total_amount' => 90.75,
        ]);
        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'email' => 'locked.client@example.com',
            'role' => UserRole::CLIENT->value,
        ]);
    }

    private function adminWithPermission(Tenant $tenant, Branch $branch, string $permissionName): User
    {
        $permission = Permission::withoutGlobalScope('tenant')->create([
            'name' => $permissionName,
            'display_name' => str($permissionName)->replace('-', ' ')->title()->toString(),
            'description' => 'Test permission',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => UserRole::ADMIN,
            'name' => 'Tenant Admin',
            'email' => uniqid('admin.', true).'@example.com',
            'civil_number' => (string) random_int(10000000, 99999999),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->syncPermissions([$permission->id]);

        return $admin;
    }
}
