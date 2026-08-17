<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Plans\PlanEntityLocks;
use Illuminate\Console\Command;

class SyncPlanEntityLocks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-plan-entity-locks {--tenant= : Sync a specific tenant by ID or slug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync plan entity locks (cars, branches, employees) for all tenants or a specific one';

    /**
     * Execute the console command.
     */
    public function handle(PlanEntityLocks $planEntityLocks): int
    {
        $tenantOption = $this->option('tenant');

        $query = Tenant::query()->with('subscriptionPlan')->whereNotNull('plan_id');

        if ($tenantOption) {
            $query->where(function ($q) use ($tenantOption) {
                $q->where('id', (int) $tenantOption)
                  ->orWhere('slug', $tenantOption);
            });
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found with an assigned plan.');
            return self::SUCCESS;
        }

        $this->info("Syncing plan entity locks for {$tenants->count()} tenant(s)...");

        foreach ($tenants as $tenant) {
            $this->line("  → {$tenant->name} (plan_id: {$tenant->plan_id})");
            $planEntityLocks->sync($tenant);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
