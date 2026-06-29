<?php

namespace App\Http\Controllers\Admin;

use App\Core\AiProviderSettings;
use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\AiInsightReport;
use App\Models\Car;
use App\Models\TenantSiteSetting;
use App\Services\Reports\AiInsightsOpenAiService;
use App\Services\Reports\AiInsightsReportService;
use App\Support\BranchAccess;
use App\Support\FinancialVisibility;
use App\Support\PdfRuntime;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf as SpatiePdf;
use Throwable;

class AiInsightsController extends Controller
{
    public function __construct(
        private BranchAccess $branchAccess,
        private AiInsightsReportService $insights,
        private AiInsightsOpenAiService $openAiInsights,
    ) {
    }

    public function index(Request $request): Response
    {
        $context = $this->context($request);
        $user = $context['user'];
        $period = $context['period'];
        $branchId = $context['branch_id'];
        $locale = $context['locale'];
        $branchOptions = $context['branches'];
        $canViewFinancials = FinancialVisibility::canViewFinancialAmounts($user);

        $reportId = $request->query('report_id');
        $selectedReport = null;

        if ($reportId) {
            $selectedReport = AiInsightReport::query()
                ->with(['creator:id,name', 'branch:id,name'])
                ->find($reportId);

            if ($selectedReport) {
                if ($selectedReport->branch_id !== null && !$this->branchAccess->canAccessBranchId($user, $selectedReport->branch_id)) {
                    abort(403);
                }
                $period = $selectedReport->period;
                $branchId = $selectedReport->branch_id;
                $locale = $selectedReport->locale;
            }
        }

        if ($selectedReport && is_array($selectedReport->internal_payload)) {
            $insights = $selectedReport->internal_payload;
            $latestReport = $selectedReport;
        } else {
            $dateRange = $this->dateRange($period);
            $insights = $this->insights->build($dateRange, $user, $branchId, $canViewFinancials);
            $latestReport = $this->latestReport($period, $branchId, $dateRange, $locale);
        }

        $previousInsights = $this->insights->build($this->previousDateRange($period), $user, $branchId, $canViewFinancials);

        $mom = [
            'critical_change' => ($insights['summary']['critical_count'] ?? 0) - ($previousInsights['summary']['critical_count'] ?? 0),
            'customers_change' => ($insights['summary']['high_risk_customers_count'] ?? 0) - ($previousInsights['summary']['high_risk_customers_count'] ?? 0),
            'pricing_change' => ($insights['summary']['pricing_opportunities_count'] ?? 0) - ($previousInsights['summary']['pricing_opportunities_count'] ?? 0),
            'losses_change_percent' => null,
        ];

        if ($canViewFinancials) {
            $prevLosses = (float) (($previousInsights['summary']['uncollected_losses'] ?? 0) === '*******' ? 0 : ($previousInsights['summary']['uncollected_losses'] ?? 0));
            $currLosses = (float) (($insights['summary']['uncollected_losses'] ?? 0) === '*******' ? 0 : ($insights['summary']['uncollected_losses'] ?? 0));
            if ($prevLosses > 0) {
                $mom['losses_change_percent'] = round((($currLosses - $prevLosses) / $prevLosses) * 100, 1);
            } else {
                $mom['losses_change_percent'] = $currLosses > 0 ? 100.0 : 0.0;
            }
        }

        return inertia('Admin/AiInsights/Index', [
            'insights' => $insights,
            'latestReport' => $this->serializeReport($latestReport),
            'savedReports' => $this->savedReports($branchId, $locale),
            'currentPeriod' => $period,
            'periodOptions' => $this->periodOptions(),
            'branches' => $branchOptions,
            'canAccessAllBranches' => $this->branchAccess->canAccessAllBranches($user),
            'selectedBranchId' => $branchId,
            'canViewFinancials' => $canViewFinancials,
            'mom' => $mom,
            'openAiStatus' => [
                'connected' => (string) (AiProviderSettings::load()['provider'] ?? 'openai') === 'openai'
                    && AiProviderSettings::isConfiguredForCurrentProvider(),
                'phase' => 'market_analysis',
            ],
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $context = $this->context($request);
        $user = $context['user'];
        $period = $context['period'];
        $branchId = $context['branch_id'];
        $locale = $context['locale'];
        $canViewFinancials = FinancialVisibility::canViewFinancialAmounts($user);
        $dateRange = $this->dateRange($period);
        $internalPayload = $this->insights->build($dateRange, $user, $branchId, $canViewFinancials);
        $settings = AiProviderSettings::load();
        $openAi = $settings['openai'] ?? [];

        AiInsightReport::create([
            'tenant_id' => $user->tenant_id,
            'branch_id' => $branchId,
            'created_by' => $user->id,
            'period' => $period,
            'locale' => $locale,
            'period_start' => $dateRange['start']->toDateString(),
            'period_end' => $dateRange['end']->toDateString(),
            'status' => 'internal_ready',
            'provider' => 'openai',
            'model' => (string) ($openAi['model'] ?? 'gpt-4.1-mini'),
            'internal_payload' => $internalPayload,
            'ai_result' => null,
            'generated_at' => now(),
            'completed_at' => null,
        ]);

        return redirect()
            ->route('admin.ai-insights.index', array_filter([
                'subdomain' => $request->route('subdomain'),
                'period' => $period,
                'branch_id' => $branchId,
                'locale' => $locale,
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', 'AI insights report snapshot generated successfully.');
    }

    public function analyze(Request $request, AiInsightReport $aiInsightReport): RedirectResponse
    {
        if ($aiInsightReport->branch_id !== null && !$this->branchAccess->canAccessBranchId($request->user(), $aiInsightReport->branch_id)) {
            abort(403);
        }

        $locale = $this->normalizeLocale((string) $request->input('locale', $aiInsightReport->locale ?: app()->getLocale()));

        $aiInsightReport->update([
            'status' => 'running',
            'locale' => $locale,
            'error_message' => null,
        ]);

        try {
            $freshReport = $aiInsightReport->fresh(['branch']) ?: $aiInsightReport;
            $result = $this->openAiInsights->analyze($freshReport, $locale);

            $aiInsightReport->update([
                'status' => 'completed',
                'ai_result' => $result,
                'error_message' => null,
                'completed_at' => now(),
            ]);

            return back()->with('success', 'OpenAI market analysis completed successfully.');
        } catch (Throwable $e) {
            $aiInsightReport->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array{
     *     user: \App\Models\User,
     *     period: string,
     *     locale: string,
     *     branch_id: int|null,
     *     branches: \Illuminate\Support\Collection<int, array{id:int,name:string}>
     * }
     */
    private function context(Request $request): array
    {
        $user = $request->user();
        $period = (string) $request->get('period', 'this_month');
        $allowedPeriods = collect($this->periodOptions())->pluck('value')->all();
        if (!in_array($period, $allowedPeriods, true)) {
            $period = 'this_month';
        }
        $locale = $this->normalizeLocale((string) $request->input('locale', app()->getLocale()));

        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $branchOptions = $this->branchAccess->availableBranchesForUser($user)
            ->map(fn ($branch) => ['id' => $branch->id, 'name' => $branch->name])
            ->values();
        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;

        return [
            'user' => $user,
            'period' => $period,
            'locale' => $locale,
            'branch_id' => $branchId,
            'branches' => $branchOptions,
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function dateRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'today' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'last_7_days' => [
                'start' => $now->copy()->subDays(6)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'last_30_days' => [
                'start' => $now->copy()->subDays(29)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'last_month' => [
                'start' => $now->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay(),
                'end' => $now->copy()->subMonthNoOverflow()->endOfMonth()->endOfDay(),
            ],
            default => [
                'start' => $now->copy()->startOfMonth()->startOfDay(),
                'end' => $now->copy()->endOfMonth()->endOfDay(),
            ],
        };
    }

    private function periodOptions(): array
    {
        return [
            ['value' => 'today', 'label' => 'Today'],
            ['value' => 'last_7_days', 'label' => 'Last 7 days'],
            ['value' => 'last_30_days', 'label' => 'Last 30 days'],
            ['value' => 'this_month', 'label' => 'This month'],
            ['value' => 'last_month', 'label' => 'Last month'],
        ];
    }

    private function latestReport(string $period, ?int $branchId, array $dateRange, string $locale): ?AiInsightReport
    {
        return AiInsightReport::query()
            ->with(['creator:id,name', 'branch:id,name'])
            ->where('period', $period)
            ->where('locale', $locale)
            ->whereDate('period_start', $dateRange['start']->toDateString())
            ->whereDate('period_end', $dateRange['end']->toDateString())
            ->when($branchId === null, fn ($query) => $query->whereNull('branch_id'), fn ($query) => $query->where('branch_id', $branchId))
            ->latest('created_at')
            ->first();
    }

    private function savedReports(?int $branchId, string $locale): array
    {
        return AiInsightReport::query()
            ->with(['creator:id,name', 'branch:id,name'])
            ->where('locale', $locale)
            ->when($branchId === null, fn ($query) => $query, fn ($query) => $query->where('branch_id', $branchId))
            ->latest('created_at')
            ->limit(6)
            ->get()
            ->map(fn (AiInsightReport $report): array => $this->serializeReport($report))
            ->all();
    }

    private function serializeReport(?AiInsightReport $report): ?array
    {
        if (!$report) {
            return null;
        }

        return [
            'id' => $report->id,
            'period' => $report->period,
            'locale' => $report->locale,
            'period_start' => $report->period_start?->toDateString(),
            'period_end' => $report->period_end?->toDateString(),
            'status' => $report->status,
            'provider' => $report->provider,
            'model' => $report->model,
            'branch_name' => $report->branch?->name,
            'created_by_name' => $report->creator?->name,
            'generated_at' => $report->generated_at?->toDateTimeString(),
            'completed_at' => $report->completed_at?->toDateTimeString(),
            'created_at' => $report->created_at?->toDateTimeString(),
            'has_ai_result' => is_array($report->ai_result) && $report->ai_result !== [],
            'error_message' => $report->error_message,
            'ai_result' => $this->serializeAiResult($report->ai_result),
            'internal_payload' => $report->internal_payload,
        ];
    }

    private function serializeAiResult(?array $result): ?array
    {
        if (!$result) {
            return null;
        }

        return [
            'language' => $result['language'] ?? '',
            'executive_summary' => $result['executive_summary'] ?? '',
            'market_summary' => $result['market_summary'] ?? '',
            'risk_level' => $result['risk_level'] ?? 'medium',
            'risks' => array_slice(array_values($result['risks'] ?? []), 0, 5),
            'opportunities' => array_slice(array_values($result['opportunities'] ?? []), 0, 5),
            'pricing_recommendations' => array_slice(array_values($result['pricing_recommendations'] ?? []), 0, 5),
            'collection_actions' => array_slice(array_values($result['collection_actions'] ?? []), 0, 5),
            'action_plan' => array_slice(array_values($result['action_plan'] ?? []), 0, 6),
            'sources' => array_slice(array_values($result['sources'] ?? []), 0, 8),
        ];
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = strtolower(str_replace('_', '-', trim($locale)));
        $locale = explode('-', $locale)[0] ?: 'en';

        return in_array($locale, ['ar', 'en', 'ur'], true) ? $locale : 'en';
    }

    public function applyPricing(Request $request): RedirectResponse
    {
        $request->validate([
            'car_id' => 'required|integer|exists:cars,id',
            'increase_percent' => 'required|numeric|min:0|max:100',
        ]);

        $car = Car::findOrFail($request->car_id);
        
        if (!$this->branchAccess->canAccessBranchId($request->user(), $car->branch_id)) {
            abort(403);
        }

        $oldPrice = (float) $car->price_per_day;
        $newPrice = round($oldPrice * (1 + ($request->increase_percent / 100)), 2);

        $car->update([
            'price_per_day' => $newPrice,
        ]);

        return back()->with('success', sprintf(
            'Price for %s updated from %s to %s (+%s%%).',
            $car->make . ' ' . $car->model,
            $oldPrice,
            $newPrice,
            $request->increase_percent
        ));
    }

    public function exportPdf(Request $request, AiInsightReport $aiInsightReport)
    {
        if ($aiInsightReport->branch_id !== null && !$this->branchAccess->canAccessBranchId($request->user(), $aiInsightReport->branch_id)) {
            abort(403);
        }

        $payload = $this->buildExportPayload($aiInsightReport);
        $fileName = 'ai-insights-report-' . $aiInsightReport->id . '.pdf';

        if (PdfRuntime::canUseBrowsershot()) {
            try {
                $pdf = SpatiePdf::view('admin.ai-insights.pdf', $payload)
                    ->format(Format::A4)
                    ->portrait()
                    ->margins(4, 4, 4, 4)
                    ->withBrowsershot(function (Browsershot $browsershot): void {
                        $nodeBinary = PdfRuntime::nodeBinary();
                        if ($nodeBinary) {
                            $browsershot->setNodeBinary($nodeBinary);
                        }

                        $npmBinary = PdfRuntime::npmBinary();
                        if ($npmBinary) {
                            $browsershot->setNpmBinary($npmBinary);
                        }

                        $chromePath = PdfRuntime::chromeBinary();
                        if ($chromePath) {
                            $browsershot->setChromePath($chromePath);
                        }

                        $browsershot
                            ->noSandbox()
                            ->addChromiumArguments([
                                'disable-dev-shm-usage',
                                'disable-gpu',
                            ])
                            ->setOption('printBackground', true)
                            ->setOption('preferCSSPageSize', true)
                            ->waitUntilNetworkIdle(false)
                            ->timeout(120)
                            ->newHeadless();
                    });

                return $pdf->download($fileName);
            } catch (Throwable $e) {
                report($e);
            }
        }

        PdfRuntime::ensureDompdfDirectories();

        $pdf = DomPdf::loadView('admin.ai-insights.pdf', $payload)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('fontDir', PdfRuntime::dompdfFontDirectory())
            ->setOption('fontCache', PdfRuntime::dompdfFontDirectory())
            ->setOption('tempDir', PdfRuntime::dompdfTempDirectory())
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($fileName);
    }

    public function exportExcel(Request $request, AiInsightReport $aiInsightReport)
    {
        if ($aiInsightReport->branch_id !== null && !$this->branchAccess->canAccessBranchId($request->user(), $aiInsightReport->branch_id)) {
            abort(403);
        }

        $payload = $this->buildExportPayload($aiInsightReport);
        $fileName = 'ai-insights-report-' . $aiInsightReport->id . '.xls';

        return response()
            ->view('admin.ai-insights.excel', $payload)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }

    private function previousDateRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'today' => [
                'start' => $now->copy()->subDay()->startOfDay(),
                'end' => $now->copy()->subDay()->endOfDay(),
            ],
            'last_7_days' => [
                'start' => $now->copy()->subDays(13)->startOfDay(),
                'end' => $now->copy()->subDays(7)->endOfDay(),
            ],
            'last_30_days' => [
                'start' => $now->copy()->subDays(59)->startOfDay(),
                'end' => $now->copy()->subDays(30)->endOfDay(),
            ],
            'last_month' => [
                'start' => $now->copy()->subMonthsNoOverflow(2)->startOfMonth()->startOfDay(),
                'end' => $now->copy()->subMonthsNoOverflow(2)->endOfMonth()->endOfDay(),
            ],
            default => [
                'start' => $now->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay(),
                'end' => $now->copy()->subMonthNoOverflow()->endOfMonth()->endOfDay(),
            ],
        };
    }

    private function buildExportPayload(AiInsightReport $report): array
    {
        $tenant = TenantContext::get();
        $tenant = $tenant?->loadMissing('siteSetting.files');
        $siteSettings = $tenant ? TenantSiteSetting::forTenant($tenant) : [];

        return [
            'report' => $this->serializeReport($report),
            'tenant' => $tenant,
            'generatedAt' => now(),
            'companyName' => $this->reportCompanyName($tenant, $siteSettings),
            'companyLogo' => $this->pdfImageSource($siteSettings['logo_url'] ?? null),
            'siteSettings' => $siteSettings,
            'pdfHeader' => data_get($siteSettings, 'pdf_header', []),
        ];
    }

    private function reportCompanyName($tenant, array $siteSettings): string
    {
        $name = trim((string) ($siteSettings['site_name'] ?? $tenant?->name ?? config('app.name')));

        return $name !== '' ? $name : (string) config('app.name');
    }

    private function pdfImageSource(?string $url): ?string
    {
        $url = trim((string) ($url ?? ''));
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, 'data:') || preg_match('/^https?:\/\//i', $url) === 1) {
            return $url;
        }

        // Try via public_path (works when storage symlink exists)
        $publicPath = public_path(ltrim($url, '/'));

        // Try via storage/app/public directly (works without symlink)
        $storagePath = storage_path('app/public/' . ltrim(preg_replace('#^/?storage/#', '', $url), '/'));

        foreach ([$publicPath, $storagePath] as $path) {
            if (!is_file($path)) {
                continue;
            }

            $contents = file_get_contents($path);
            if (!is_string($contents) || $contents === '') {
                continue;
            }

            $mime = mime_content_type($path) ?: 'application/octet-stream';

            return 'data:' . $mime . ';base64,' . base64_encode($contents);
        }

        return null; // skip broken URL rather than embedding it
    }
}
