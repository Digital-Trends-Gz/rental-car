<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('tenant-login'));

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $plan = \App\Models\Plan::factory()->create(['is_active' => true]);
    $tenant = \App\Models\Tenant::factory()->create([
        'is_active' => true,
        'plan_id' => $plan->id,
    ]);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => \App\Enums\UserRole::ADMIN,
        'is_active' => true,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('admin.cars.index', ['subdomain' => $tenant->slug]));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(implode('|', [$user->email, '127.0.0.1']), amount: 10);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');

    $errors = session('errors');

    $this->assertStringContainsString('Too many login attempts', $errors->first('email'));
});

test('unauthenticated guest on subdomain is redirected to /login', function () {
    $plan = \App\Models\Plan::factory()->create(['is_active' => true]);
    $tenant = \App\Models\Tenant::factory()->create([
        'is_active' => true,
        'plan_id' => $plan->id,
    ]);

    $response = $this->get(route('admin.cars.index', ['subdomain' => $tenant->slug]));

    $response->assertRedirect(route('tenant.login', ['subdomain' => $tenant->slug]));
});

test('unauthenticated guest on main domain is redirected to /tenant/login', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('tenant-login'));
});

test('tenant/login on subdomain redirects to /login', function () {
    $plan = \App\Models\Plan::factory()->create(['is_active' => true]);
    $tenant = \App\Models\Tenant::factory()->create([
        'is_active' => true,
        'plan_id' => $plan->id,
    ]);

    $response = $this->get(route('tenant.tenant-login', ['subdomain' => $tenant->slug]));

    $response->assertRedirect(route('tenant.login', ['subdomain' => $tenant->slug]));
});