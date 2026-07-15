<?php

use App\Core\TenantContext;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('api login returns the assigned branch name', function () {
    $plan = Plan::factory()->create(['is_active' => true]);
    $tenant = Tenant::factory()->create([
        'plan_id' => $plan->id,
        'trial_ends_at' => now()->addMonth(),
    ]);
    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Ramallah Branch',
    ]);
    $user = User::factory()->create([
        'email' => 'branch-login@example.com',
        'password' => 'password',
        'role' => UserRole::ADMIN,
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.branch_id', $branch->id)
        ->assertJsonPath('user.branch_name', 'Ramallah Branch');
});

test('api login returns company owner account type for tenant owner', function () {
    $plan = Plan::factory()->create(['is_active' => true]);
    $tenant = Tenant::factory()->create([
        'email' => 'owner-login@example.com',
        'plan_id' => $plan->id,
        'trial_ends_at' => now()->addMonth(),
    ]);
    $user = User::factory()->create([
        'name' => 'Owner User',
        'email' => 'owner-login@example.com',
        'password' => 'password',
        'role' => UserRole::ADMIN,
        'tenant_id' => $tenant->id,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.account_type', 'company_owner')
        ->assertJsonPath('user.account_type_label', 'صاحب الشركة');
});

test('api login returns employee account type for tenant employee', function () {
    $plan = Plan::factory()->create(['is_active' => true]);
    $tenant = Tenant::factory()->create([
        'email' => 'owner-account@example.com',
        'plan_id' => $plan->id,
        'trial_ends_at' => now()->addMonth(),
    ]);
    $user = User::factory()->create([
        'name' => 'Employee User',
        'email' => 'employee-login@example.com',
        'password' => 'password',
        'role' => UserRole::ADMIN,
        'tenant_id' => $tenant->id,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.account_type', 'employee')
        ->assertJsonPath('user.account_type_label', 'موظف');
});

test('api me returns the assigned branch name', function () {
    $tenant = Tenant::factory()->create();
    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Nablus Branch',
    ]);
    $user = User::factory()->create([
        'role' => UserRole::ADMIN,
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/auth/me');

    $response->assertOk()
        ->assertJsonPath('user.branch_id', $branch->id)
        ->assertJsonPath('user.branch_name', 'Nablus Branch');
});

test('api login credentials error uses accept language header', function () {
    app()->setLocale('en');

    $response = $this
        ->withHeader('Accept-Language', 'ar')
        ->postJson('/api/auth/login', [
            'email' => 'missing-api-user@example.com',
            'password' => 'wrong-password',
        ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'بيانات الدخول غير صحيحة.')
        ->assertJsonPath('errors.email.0', 'بيانات الدخول غير صحيحة.');
});

test('api forgot password can use tenant translation overrides', function () {
    $tenant = Tenant::factory()->create();

    TenantSiteSetting::create([
        'tenant_id' => $tenant->id,
        'translations' => [
            'ar' => [
                'auth' => [
                    'api' => [
                        'account_not_found' => 'CUSTOM_ACCOUNT_NOT_FOUND',
                    ],
                ],
            ],
        ],
    ]);

    TenantContext::set($tenant);

    $response = $this
        ->withHeader('Accept-Language', 'ar')
        ->postJson('/api/auth/forgot-password', [
            'email' => 'missing-api-user@example.com',
        ]);

    TenantContext::clear();

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'CUSTOM_ACCOUNT_NOT_FOUND')
        ->assertJsonPath('errors.email.0', 'CUSTOM_ACCOUNT_NOT_FOUND');
});

test('api forgot password email error uses accept language header', function () {
    app()->setLocale('en');

    $response = $this
        ->withHeader('Accept-Language', 'ar')
        ->postJson('/api/auth/forgot-password', [
            'email' => 'missing-api-user@example.com',
        ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'لم نتمكن من العثور على حساب بهذا البريد الإلكتروني.')
        ->assertJsonPath('errors.email.0', 'لم نتمكن من العثور على حساب بهذا البريد الإلكتروني.');
});
