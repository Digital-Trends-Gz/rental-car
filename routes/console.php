<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Support\TenantAdminAccessSync;
use App\Services\Contracts\ContractExpiryReminderService;
use App\Services\Cars\CarDocumentReminderService;
use App\Services\Maintenance\MaintenanceScheduleService;
use App\Services\Rentals\RentalStatusSyncService;

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

Schedule::command('maintenance:process-schedule')->everyFiveMinutes();
Schedule::command('rentals:sync-statuses')->everyFiveMinutes();
Schedule::command('cars:notify-expiring-documents')->dailyAt('14:22');
Schedule::command('contracts:notify-ending-tomorrow')->dailyAt('14:23');
Schedule::command('ai-insights:generate-monthly --with-openai')->monthlyOn(1, '09:00');
