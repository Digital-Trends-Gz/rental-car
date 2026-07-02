<?php

use App\Core\TenantContext;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;

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
