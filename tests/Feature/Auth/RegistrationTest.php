<?php

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register', function () {
    $tenant = \App\Models\Tenant::factory()->create(['is_active' => true]);
    $url = 'http://' . $tenant->slug . '.real-rent-car-main.test/register';
    
    $response = $this->from($url)
        ->post($url, [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'civil_number' => '1234567890',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

test('registration rejects names with digits', function () {
    $tenant = \App\Models\Tenant::factory()->create(['is_active' => true]);
    $url = 'http://' . $tenant->slug . '.real-rent-car-main.test/register';

    $response = $this->from($url)
        ->post($url, [
            'name' => 'Test User 1',
            'email' => 'test-name@example.com',
            'civil_number' => '1234567890',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

    $response->assertSessionHasErrors(['name']);
});

test('tenant admin can start existing tenant plan upgrade flow', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'Upgrade Tenant',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::ADMIN,
        'email' => 'upgrade-admin@example.com',
    ]);

    $response = $this->actingAs($admin)->get(route('register.upgrade'));

    $response->assertRedirect(route('register.plans'));
    $response->assertSessionHas('saas.registration.mode', 'existing_tenant');
    $response->assertSessionHas('saas.registration.existing_tenant_id', $tenant->id);
    $response->assertSessionMissing('saas.registration.plan');
});
