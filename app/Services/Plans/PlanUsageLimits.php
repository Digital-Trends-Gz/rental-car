<?php

namespace App\Services\Plans;

use App\Core\TenantContext;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\User;

class PlanUsageLimits
{
    public function employeeLimitMessage(?Tenant $tenant = null): ?string
    {
        $tenant = $this->resolveTenant($tenant);

        return $this->limitMessage(
            'max_employees',
            'employees',
            User::query()
                ->where('tenant_id', $tenant?->id)
                ->where('role', UserRole::ADMIN->value)
                ->count(),
            $tenant
        );
    }

    public function branchLimitMessage(?Tenant $tenant = null): ?string
    {
        $tenant = $this->resolveTenant($tenant);

        return $this->limitMessage(
            'max_branches',
            'branches',
            Branch::query()
                ->where('tenant_id', $tenant?->id)
                ->count(),
            $tenant
        );
    }

    public function carLimitMessage(?Tenant $tenant = null): ?string
    {
        $tenant = $this->resolveTenant($tenant);

        return $this->limitMessage(
            'max_cars',
            'cars',
            Car::query()
                ->where('tenant_id', $tenant?->id)
                ->count(),
            $tenant
        );
    }

    public function contractLimitMessage(?Tenant $tenant = null): ?string
    {
        $tenant = $this->resolveTenant($tenant);

        return $this->limitMessage(
            'max_contracts',
            'contracts',
            Contract::query()
                ->where('tenant_id', $tenant?->id)
                ->count(),
            $tenant
        );
    }

    public function openAiRequestsPerDayLimit(?Tenant $tenant = null): ?int
    {
        return $this->limitFor($tenant, 'openai_requests_per_day');
    }

    public function limitFor(?Tenant $tenant, string $field): ?int
    {
        $tenant = $this->resolveTenant($tenant);

        if (!$tenant) {
            return null;
        }

        $tenant->loadMissing('subscriptionPlan');

        return $this->normalizeLimit(data_get($tenant->subscriptionPlan, $field));
    }

    private function limitMessage(string $field, string $label, int $currentCount, ?Tenant $tenant = null): ?string
    {
        $limit = $this->limitFor($tenant, $field);

        if ($limit === null || $currentCount < $limit) {
            return null;
        }

        return "Your plan allows up to {$limit} {$label}. Upgrade your plan to add more.";
    }

    private function normalizeLimit(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $limit = (int) $value;

        return $limit >= 1 ? $limit : null;
    }

    private function resolveTenant(?Tenant $tenant = null): ?Tenant
    {
        if ($tenant) {
            return $tenant;
        }

        $tenant = TenantContext::get();
        if ($tenant) {
            return $tenant;
        }

        $user = auth()->user();
        if (!$user?->tenant_id) {
            return null;
        }

        return Tenant::query()->with('subscriptionPlan')->find((int) $user->tenant_id);
    }
}
