<?php

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
