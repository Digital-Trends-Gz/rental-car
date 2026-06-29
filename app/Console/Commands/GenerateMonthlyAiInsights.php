<?php

namespace App\Console\Commands;

use App\Core\AiProviderSettings;
use App\Core\TenantContext;
use App\Enums\UserRole;
use App\Models\AiInsightReport;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MonthlyAiInsightsNotification;
use App\Services\Reports\AiInsightsOpenAiService;
use App\Services\Reports\AiInsightsReportService;
use App\Support\BranchAccess;
use App\Support\FinancialVisibility;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class GenerateMonthlyAiInsights extends Command
{
    protected $signature = 'ai-insights:generate-monthly
        {--dry-run : Preview which tenants would be processed without actually generating}
        {--with-openai : Also run OpenAI market study after generating the internal snapshot}';

    protected $description = 'Auto-generate monthly AI Insights reports for all active tenants and email to their admins.';

    public function __construct(
        private AiInsightsReportService $insightsService,
        private AiInsightsOpenAiService $openAiService,
        private BranchAccess $branchAccess,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $withOpenAi = (bool) $this->option('with-openai');

        $this->info('Starting monthly AI Insights generation...');

        if ($dryRun) {
            $this->warn('[DRY-RUN] No reports will be created or emails sent.');
        }

        $tenants = Tenant::query()->get(['id', 'name']);
        $processed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($tenants as $tenant) {
            TenantContext::set($tenant);

            try {
                $admins = User::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('role', UserRole::ADMIN)
                    ->where('is_active', true)
                    ->get();

                if ($admins->isEmpty()) {
                    $this->line("  [{$tenant->name}] No active admins found. Skipping.");
                    $skipped++;
                    continue;
                }

                // Use the first admin as context user for branch access / financial visibility
                $contextUser = $admins->first();

                $now = Carbon::now();
                $period = 'last_month';
                $dateRange = [
                    'start' => $now->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay(),
                    'end' => $now->copy()->subMonthNoOverflow()->endOfMonth()->endOfDay(),
                ];
                $canViewFinancials = FinancialVisibility::canViewFinancialAmounts($contextUser);

                $this->line("  [{$tenant->name}] Generating internal snapshot for period {$dateRange['start']->toDateString()} to {$dateRange['end']->toDateString()}...");

                if (!$dryRun) {
                    $internalPayload = $this->insightsService->build($dateRange, $contextUser, null, $canViewFinancials);

                    $report = AiInsightReport::create([
                        'tenant_id' => $tenant->id,
                        'branch_id' => null,
                        'created_by' => $contextUser->id,
                        'period' => $period,
                        'locale' => 'ar',
                        'period_start' => $dateRange['start'],
                        'period_end' => $dateRange['end'],
                        'status' => 'internal_ready',
                        'internal_payload' => $internalPayload,
                        'generated_at' => now(),
                    ]);

                    if ($withOpenAi) {
                        $settings = AiProviderSettings::load();
                        $openAiSettings = $settings['openai'] ?? [];

                        $this->line("  [{$tenant->name}] Running OpenAI market study...");

                        try {
                            $report->update(['status' => 'running']);
                            $aiResult = $this->openAiService->analyze($report, 'ar');
                            $report->update([
                                'status' => 'completed',
                                'ai_result' => $aiResult,
                                'provider' => 'openai',
                                'model' => $openAiSettings['model'] ?? 'gpt-4.1-mini',
                                'completed_at' => now(),
                            ]);
                        } catch (Throwable $e) {
                            report($e);
                            $report->update([
                                'status' => 'failed',
                                'error_message' => $e->getMessage(),
                            ]);
                            $this->warn("  [{$tenant->name}] OpenAI failed: {$e->getMessage()}");
                        }
                    }

                    // Notify all active admins
                    foreach ($admins as $admin) {
                        try {
                            $admin->notify(new MonthlyAiInsightsNotification($report));
                        } catch (Throwable $e) {
                            report($e);
                            $this->warn("  [{$tenant->name}] Failed to notify admin {$admin->name}: {$e->getMessage()}");
                        }
                    }

                    $this->info("  [{$tenant->name}] Done. Report #{$report->id} created and {$admins->count()} admin(s) notified.");
                    $processed++;
                } else {
                    $this->info("  [{$tenant->name}] [DRY-RUN] Would generate report for {$admins->count()} admin(s).");
                    $processed++;
                }
            } catch (Throwable $e) {
                report($e);
                $this->error("  [{$tenant->name}] ERROR: {$e->getMessage()}");
                $failed++;
            } finally {
                TenantContext::clear();
            }
        }

        $this->newLine();
        $this->info("Monthly AI Insights generation completed.");
        $this->line("Tenants processed: {$processed}");
        $this->line("Skipped (no admins): {$skipped}");
        $this->line("Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
