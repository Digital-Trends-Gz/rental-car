<?php

namespace App\Services\Plans;

use App\Core\TenantContext;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Contract;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantTranslations;

class PlanUsageLimits
{
    public function employeeLimitMessage(?Tenant $tenant = null): ?string
    {
        $tenant = $this->resolveTenant($tenant);

        return $this->limitMessage(
            'max_employees',
            'employees',
            $this->employeeCount($tenant),
            $tenant
        );
    }

    public function employeeUsage(?Tenant $tenant = null): array
    {
        return $this->usage('max_employees', 'employees', $this->resolveTenant($tenant), fn (?Tenant $tenant): int => $this->employeeCount($tenant));
    }

    public function branchLimitMessage(?Tenant $tenant = null): ?string
    {
        $tenant = $this->resolveTenant($tenant);

        return $this->limitMessage(
            'max_branches',
            'branches',
            $this->branchCount($tenant),
            $tenant
        );
    }

    public function branchUsage(?Tenant $tenant = null): array
    {
        $tenant = $this->resolveTenant($tenant);
        $limit = $this->limitFor($tenant, 'max_branches');
        $current = $this->branchCount($tenant);

        return [
            'current' => $current,
            'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $current),
            'at_limit' => $limit !== null && $current >= $limit,
            'message' => $this->limitMessage('max_branches', 'branches', $current, $tenant),
        ];
    }

    public function carLimitMessage(?Tenant $tenant = null): ?string
    {
        $tenant = $this->resolveTenant($tenant);

        return $this->limitMessage(
            'max_cars',
            'cars',
            $this->carCount($tenant),
            $tenant
        );
    }

    public function carUsage(?Tenant $tenant = null): array
    {
        return $this->usage('max_cars', 'cars', $this->resolveTenant($tenant), fn (?Tenant $tenant): int => $this->carCount($tenant));
    }

    public function contractLimitMessage(?Tenant $tenant = null): ?string
    {
        $tenant = $this->resolveTenant($tenant);

        return $this->limitMessage(
            'max_contracts',
            'contracts',
            $this->contractCount($tenant),
            $tenant
        );
    }

    public function contractUsage(?Tenant $tenant = null): array
    {
        return $this->usage('max_contracts', 'contracts', $this->resolveTenant($tenant), fn (?Tenant $tenant): int => $this->contractCount($tenant));
    }

    public function reservationLimitMessage(?Tenant $tenant = null): ?string
    {
        $tenant = $this->resolveTenant($tenant);

        return $this->limitMessage(
            'max_reservations_per_month',
            'reservations',
            $this->reservationCount($tenant),
            $tenant
        );
    }

    public function reservationUsage(?Tenant $tenant = null): array
    {
        return $this->usage('max_reservations_per_month', 'reservations', $this->resolveTenant($tenant), fn (?Tenant $tenant): int => $this->reservationCount($tenant));
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

        if (!$tenant->plan_id) {
            return null;
        }

        return $this->normalizeLimit(
            Plan::query()
                ->whereKey((int) $tenant->plan_id)
                ->value($field)
        );
    }

    private function limitMessage(string $field, string $label, int $currentCount, ?Tenant $tenant = null): ?string
    {
        $limit = $this->limitFor($tenant, $field);

        if ($limit === null || $currentCount < $limit) {
            return null;
        }

        $locale = app()->getLocale();
        $resource = TenantTranslations::get("dashboard.common.{$label}", $locale, $label, $tenant);

        if ($resource === "dashboard.common.{$label}" || $resource === "site.dashboard.common.{$label}") {
            $resource = $label;
        }

        if ($locale === 'en') {
            $resource = strtolower((string) $resource);
        }

        $message = TenantTranslations::get(
            'dashboard.common.plan_limit_reached',
            $locale,
            trans('site.dashboard.common.plan_limit_reached'),
            $tenant
        );

        return strtr($message, [
            ':limit' => (string) $limit,
            ':resource' => (string) $resource,
        ]);
    }

    private function usage(string $field, string $label, ?Tenant $tenant, callable $counter): array
    {
        $limit = $this->limitFor($tenant, $field);
        $current = $counter($tenant);

        return [
            'current' => $current,
            'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $current),
            'at_limit' => $limit !== null && $current >= $limit,
            'message' => $this->limitMessage($field, $label, $current, $tenant),
        ];
    }

    private function employeeCount(?Tenant $tenant): int
    {
        if (!$tenant?->id) {
            return 0;
        }

        return User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('role', UserRole::ADMIN->value)
            ->where(function ($query) use ($tenant) {
                if ($tenant->email) {
                    $query->whereRaw('LOWER(email) <> ?', [strtolower((string) $tenant->email)]);
                }

                $query->whereDoesntHave('roles', fn ($roleQuery) => $roleQuery->where('name', 'tenant-owner'));
            })
            ->count();
    }

    private function branchCount(?Tenant $tenant): int
    {
        if (!$tenant?->id) {
            return 0;
        }

        return Branch::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->count();
    }

    private function carCount(?Tenant $tenant): int
    {
        if (!$tenant?->id) {
            return 0;
        }

        return Car::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->count();
    }

    private function contractCount(?Tenant $tenant): int
    {
        if (!$tenant?->id) {
            return 0;
        }

        return Contract::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->whereBetween('created_at', $this->currentMonthRange())
            ->count();
    }

    private function reservationCount(?Tenant $tenant): int
    {
        if (!$tenant?->id) {
            return 0;
        }

        return Reservation::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->whereBetween('created_at', $this->currentMonthRange())
            ->count();
    }

    private function currentMonthRange(): array
    {
        $start = now()->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
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
