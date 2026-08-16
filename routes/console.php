<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use App\Support\TenantAdminAccessSync;
use App\Support\TenantPermissionCatalog;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Dashboard\OwnerDashboardMetricsService;
use App\Services\Contracts\ContractExpiryReminderService;
use App\Services\Cars\CarDocumentReminderService;
use App\Services\Maintenance\MaintenanceScheduleService;
use App\Services\Rentals\RentalStatusSyncService;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('maintenance:process-schedule {--dry-run}', function () {
    $dryRun = (bool) $this->option('dry-run');
    $result = app(MaintenanceScheduleService::class)->run($dryRun);

    $this->info('Maintenance schedule processed.');
    $this->line('Started: '.$result['started']);
    $this->line('Completed: '.$result['completed']);
    $this->line('Upcoming notified: '.$result['upcoming_notified']);
    $this->line('Mode: '.($dryRun ? 'dry-run' : 'live'));
})->purpose('Process maintenance schedule and create notifications');

Artisan::command('rentals:sync-statuses {--dry-run}', function () {
    $dryRun = (bool) $this->option('dry-run');
    $result = app(RentalStatusSyncService::class)->run($dryRun);

    $this->info('Rental status sync completed.');
    $this->line('Activated reservations: '.$result['activated']);
    $this->line('Completed reservations: '.$result['completed']);
    $this->line('Cars updated: '.$result['cars_updated']);
    $this->line('Reserve window (hours): '.$result['reserve_before_hours']);
    $this->line('Checked at: '.$result['checked_at']);
    $this->line('Mode: '.($dryRun ? 'dry-run' : 'live'));
})->purpose('Sync reservation lifecycle by date/time and update related car statuses');

Artisan::command('tenants:sync-owner-access', function () {
    $result = app(TenantAdminAccessSync::class)->syncAllTenants();

    $this->info('Tenant owner access sync completed.');
    $this->line('Tenants checked: '.$result['tenants']);
    $this->line('Tenant admins checked: '.$result['admins']);
    $this->line('Admins synced: '.$result['synced']);
})->purpose('Backfill tenant-owner role and tenant-* permissions for existing tenant admins');

Artisan::command('tenants:expand-legacy-permissions', function () {
    $permissionsByName = Permission::withoutGlobalScope('tenant')
        ->whereNull('tenant_id')
        ->whereIn('name', collect(TenantPermissionCatalog::permissions())->pluck('name')->all())
        ->pluck('id', 'name');

    $roleInserts = [];
    $userInserts = [];

    foreach (TenantPermissionCatalog::legacyExpansionMap() as $legacyName => $granularNames) {
        $legacyId = $permissionsByName[$legacyName] ?? null;
        if (!$legacyId) {
            continue;
        }

        $granularIds = collect($granularNames)
            ->map(fn (string $name) => $permissionsByName[$name] ?? null)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($granularIds->isEmpty()) {
            continue;
        }

        $roleIds = DB::table('permission_role')
            ->where('permission_id', (int) $legacyId)
            ->pluck('role_id')
            ->map(fn ($id) => (int) $id);

        foreach ($roleIds as $roleId) {
            foreach ($granularIds as $granularId) {
                $roleInserts[] = [
                    'permission_id' => $granularId,
                    'role_id' => $roleId,
                ];
            }
        }

        $userIds = DB::table('permission_user')
            ->where('permission_id', (int) $legacyId)
            ->where('user_type', User::class)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id);

        foreach ($userIds as $userId) {
            foreach ($granularIds as $granularId) {
                $userInserts[] = [
                    'permission_id' => $granularId,
                    'user_id' => $userId,
                    'user_type' => User::class,
                ];
            }
        }
    }

    collect($roleInserts)
        ->unique(fn (array $row) => $row['permission_id'].'-'.$row['role_id'])
        ->chunk(500)
        ->each(fn ($chunk) => DB::table('permission_role')->insertOrIgnore($chunk->values()->all()));

    collect($userInserts)
        ->unique(fn (array $row) => $row['permission_id'].'-'.$row['user_id'].'-'.$row['user_type'])
        ->chunk(500)
        ->each(fn ($chunk) => DB::table('permission_user')->insertOrIgnore($chunk->values()->all()));

    $this->info('Legacy tenant permissions expanded.');
    $this->line('Role permission rows prepared: '.count($roleInserts));
    $this->line('User permission rows prepared: '.count($userInserts));
})->purpose('Add granular tenant permissions wherever legacy tenant permissions are already assigned');

Artisan::command('cars:notify-expiring-documents', function () {
    $result = app(CarDocumentReminderService::class)->run();

    $this->info('Car document reminder check completed.');
    $this->line('Documents checked: '.$result['checked']);
    $this->line('Notifications sent: '.$result['notified']);
})->purpose('Notify tenant admins when car license or insurance expires in 10 days');

Artisan::command('contracts:notify-ending-tomorrow', function () {
    $result = app(ContractExpiryReminderService::class)->run();

    $this->info('Contract reminder check completed.');
    $this->line('Contracts checked: '.$result['checked']);
    $this->line('Notifications sent: '.$result['notified']);
})->purpose('Notify tenant admins when active contracts end tomorrow');

Artisan::command('owner-dashboard:snapshot {--date=} {--tenant_id=}', function () {
    $date = $this->option('date')
        ? Carbon::parse((string) $this->option('date'))->startOfDay()
        : Carbon::today();
    $tenantId = $this->option('tenant_id');
    $service = app(OwnerDashboardMetricsService::class);

    $tenants = Tenant::query()
        ->when($tenantId, fn ($query) => $query->whereKey((int) $tenantId))
        ->get(['id', 'name']);

    foreach ($tenants as $tenant) {
        $snapshots = $service->snapshotTenant($tenant, $date);
        $this->line("Tenant {$tenant->id} {$tenant->name}: ".count($snapshots).' branch scope(s) snapshotted for '.$date->toDateString());
    }

    $this->info('Owner dashboard metric snapshots completed.');
})->purpose('Capture owner dashboard metrics per tenant and branch for exact day-over-day comparison');

Schedule::command('maintenance:process-schedule')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command('rentals:sync-statuses')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command('cars:notify-expiring-documents')->dailyAt('14:22')->withoutOverlapping(60);
Schedule::command('contracts:notify-ending-tomorrow')->dailyAt('14:23')->withoutOverlapping(60);
Schedule::command('ai-insights:generate-monthly --with-openai')->monthlyOn(1, '09:00')->withoutOverlapping(120);
Schedule::command('owner-dashboard:snapshot')->dailyAt('23:59')->withoutOverlapping(60);
