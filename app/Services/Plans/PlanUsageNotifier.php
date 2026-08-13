<?php

namespace App\Services\Plans;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PlanUsageLimitNotification;
use App\Support\TenantTranslations;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlanUsageNotifier
{
    public function __construct(private readonly PlanUsageLimits $usageLimits)
    {
    }

    public function checkEmployees(?Tenant $tenant): void
    {
        $this->notifyIfNeeded($tenant, 'employees', $this->usageLimits->employeeUsage($tenant));
    }

    public function checkBranches(?Tenant $tenant): void
    {
        $this->notifyIfNeeded($tenant, 'branches', $this->usageLimits->branchUsage($tenant));
    }

    public function checkCars(?Tenant $tenant): void
    {
        $this->notifyIfNeeded($tenant, 'cars', $this->usageLimits->carUsage($tenant));
    }

    public function checkReservations(?Tenant $tenant): void
    {
        $this->notifyIfNeeded($tenant, 'reservations', $this->usageLimits->reservationUsage($tenant), now()->format('Y-m'));
    }

    public function checkContracts(?Tenant $tenant): void
    {
        $this->notifyIfNeeded($tenant, 'contracts', $this->usageLimits->contractUsage($tenant), now()->format('Y-m'));
    }

    /**
     * @param array{current?:int,limit?:int|null,remaining?:int|null,at_limit?:bool} $usage
     */
    private function notifyIfNeeded(?Tenant $tenant, string $resource, array $usage, string $period = 'lifetime'): void
    {
        if (!$tenant?->id) {
            return;
        }

        $limit = $usage['limit'] ?? null;
        $current = (int) ($usage['current'] ?? 0);

        if (!is_int($limit) || $limit < 1 || $current < 1) {
            return;
        }

        $threshold = $this->threshold($current, $limit);

        if ($threshold === null) {
            return;
        }

        $dedupeKey = sprintf('plan_usage:%d:%s:%s:%s', $tenant->id, $resource, $period, $threshold);

        if ($this->wasAlreadySent($dedupeKey)) {
            return;
        }

        $recipients = $this->recipients($tenant);

        if ($recipients->isEmpty()) {
            return;
        }

        $payload = $this->payload($tenant, $resource, $usage, $period, $threshold, $dedupeKey);

        $recipients->each(fn (User $recipient) => $recipient->notify(new PlanUsageLimitNotification($payload)));
    }

    private function threshold(int $current, int $limit): ?string
    {
        if ($current >= $limit) {
            return 'reached';
        }

        $remaining = max(0, $limit - $current);

        if (($current / $limit) >= 0.8 || $remaining <= 1) {
            return 'near';
        }

        return null;
    }

    private function wasAlreadySent(string $dedupeKey): bool
    {
        return DB::table('notifications')
            ->where('type', PlanUsageLimitNotification::class)
            ->where('data', 'like', '%'.$dedupeKey.'%')
            ->exists();
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Tenant $tenant): Collection
    {
        return User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('role', UserRole::ADMIN->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(5)
            ->get();
    }

    /**
     * @param array{current?:int,limit?:int|null,remaining?:int|null,at_limit?:bool} $usage
     * @return array<string, mixed>
     */
    private function payload(Tenant $tenant, string $resource, array $usage, string $period, string $threshold, string $dedupeKey): array
    {
        $locale = app()->getLocale();
        $resourceLabel = TenantTranslations::get("dashboard.common.{$resource}", $locale, $resource, $tenant);
        $titleKey = $threshold === 'reached'
            ? 'dashboard.notifications.plan_limit_reached_title'
            : 'dashboard.notifications.plan_limit_near_title';
        $messageKey = $threshold === 'reached'
            ? 'dashboard.notifications.plan_limit_reached_body'
            : 'dashboard.notifications.plan_limit_near_body';

        $title = TenantTranslations::get(
            $titleKey,
            $locale,
            $threshold === 'reached' ? 'Plan limit reached' : 'Plan limit almost reached',
            $tenant
        );
        $message = TenantTranslations::get(
            $messageKey,
            $locale,
            $threshold === 'reached'
                ? 'You have used :current of :limit :resource. Upgrade your plan to add more.'
                : 'You have used :current of :limit :resource. Upgrade your plan soon to avoid interruption.',
            $tenant
        );

        $replacements = [
            ':current' => (string) ($usage['current'] ?? 0),
            ':limit' => (string) ($usage['limit'] ?? 0),
            ':remaining' => (string) ($usage['remaining'] ?? 0),
            ':resource' => (string) $resourceLabel,
        ];

        return [
            'dedupe_key' => $dedupeKey,
            'tenant_id' => $tenant->id,
            'resource' => $resource,
            'threshold' => $threshold,
            'period' => $period,
            'current' => (int) ($usage['current'] ?? 0),
            'limit' => (int) ($usage['limit'] ?? 0),
            'remaining' => (int) ($usage['remaining'] ?? 0),
            'title' => strtr($title, $replacements),
            'message' => strtr($message, $replacements),
            'url' => '/admin/dashboard',
        ];
    }
}
