<?php

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('owner smart opportunities returns forbidden when reports module is disabled', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $plan = Plan::factory()->create([
        'feature_flags' => array_merge(
            array_fill_keys(Plan::FEATURE_KEYS, true),
            ['reports_module' => false]
        ),
    ]);

    $tenant->update([
        'plan_id' => $plan->id,
        'trial_ends_at' => now()->addMonth(),
    ]);

    Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Branch',
    ]);

    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => null,
        'role' => UserRole::ADMIN,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $ownerRole = Role::create([
        'tenant_id' => $tenant->id,
        'name' => 'tenant-owner',
        'display_name' => 'Tenant Owner',
        'description' => 'Tenant owner',
    ]);
    $owner->roles()->syncWithoutDetaching([$ownerRole->id]);

    Sanctum::actingAs($owner, ['*']);

    $this->getJson(route('api.owner.smart-opportunities'), [
        'Accept-Language' => 'ar',
    ])
        ->assertForbidden()
        ->assertExactJson([
            'message' => 'خطتك الحالية لا تتضمن صلاحية الوصول لتقارير الذكاء الاصطناعي.',
        ]);
});

test('owner smart opportunities forbidden message can use landing translation override', function () {
    SiteSetting::query()->create([
        'key' => 'landing_page',
        'value' => [
            'translations' => [
                'ar' => [
                    'owner_api' => [
                        'errors' => [
                            'reports_module_not_available' => 'CUSTOM_AI_REPORTS_DENIED',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $plan = Plan::factory()->create([
        'feature_flags' => array_merge(
            array_fill_keys(Plan::FEATURE_KEYS, true),
            ['reports_module' => false]
        ),
    ]);

    $tenant->update([
        'plan_id' => $plan->id,
        'trial_ends_at' => now()->addMonth(),
    ]);

    Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Branch',
    ]);

    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => null,
        'role' => UserRole::ADMIN,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $ownerRole = Role::create([
        'tenant_id' => $tenant->id,
        'name' => 'tenant-owner',
        'display_name' => 'Tenant Owner',
        'description' => 'Tenant owner',
    ]);
    $owner->roles()->syncWithoutDetaching([$ownerRole->id]);

    Sanctum::actingAs($owner, ['*']);

    $this->getJson(route('api.owner.smart-opportunities'), [
        'Accept-Language' => 'ar',
    ])
        ->assertForbidden()
        ->assertExactJson([
            'message' => 'CUSTOM_AI_REPORTS_DENIED',
        ]);
});

test('owner smart opportunities returns success when reports module is enabled', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $plan = Plan::factory()->create([
        'feature_flags' => array_fill_keys(Plan::FEATURE_KEYS, true),
    ]);

    $tenant->update([
        'plan_id' => $plan->id,
        'trial_ends_at' => now()->addMonth(),
    ]);

    Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Branch',
    ]);

    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => null,
        'role' => UserRole::ADMIN,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $ownerRole = Role::create([
        'tenant_id' => $tenant->id,
        'name' => 'tenant-owner',
        'display_name' => 'Tenant Owner',
        'description' => 'Tenant owner',
    ]);
    $owner->roles()->syncWithoutDetaching([$ownerRole->id]);

    Sanctum::actingAs($owner, ['*']);

    $this->getJson(route('api.owner.smart-opportunities'))
        ->assertOk()
        ->assertJsonPath('status', 'success');
});
