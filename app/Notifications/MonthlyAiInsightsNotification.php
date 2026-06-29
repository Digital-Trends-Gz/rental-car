<?php

namespace App\Notifications;

use App\Models\AiInsightReport;
use App\Models\TenantSiteSetting;
use App\Support\PdfRuntime;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf as SpatiePdf;
use Throwable;

class MonthlyAiInsightsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly AiInsightReport $report)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $periodStart = $this->report->period_start?->toDateString() ?: '';
        $periodEnd = $this->report->period_end?->toDateString() ?: '';

        return [
            'kind' => 'monthly_ai_insights',
            'title' => 'Monthly AI Insights Report Ready',
            'message' => "Your AI Insights report for {$periodStart} to {$periodEnd} has been generated.",
            'url' => '/admin/ai-insights',
            'report_id' => $this->report->id,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->report->tenant;
        if ($tenant) {
            $tenant->loadMissing('siteSetting.files');
        }
        $siteSettings = $tenant ? TenantSiteSetting::forTenant($tenant) : [];

        $companyName = trim((string) ($siteSettings['site_name'] ?? $tenant?->name ?? config('app.name')));
        if ($companyName === '') {
            $companyName = (string) config('app.name');
        }

        $logoUrl = $siteSettings['logo_url'] ?? null;
        $companyLogo = null;
        if ($logoUrl) {
            $logoUrl = trim((string) $logoUrl);
            if ($logoUrl !== '') {
                if (str_starts_with($logoUrl, 'data:') || preg_match('/^https?:\/\//i', $logoUrl) === 1) {
                    $companyLogo = $logoUrl;
                } else {
                    $path = null;
                    if (str_starts_with($logoUrl, '/storage/')) {
                        $path = public_path(ltrim($logoUrl, '/'));
                    } elseif (str_starts_with($logoUrl, 'storage/')) {
                        $path = public_path($logoUrl);
                    } elseif (str_starts_with($logoUrl, '/')) {
                        $path = public_path(ltrim($logoUrl, '/'));
                    }

                    if ($path && is_file($path)) {
                        $contents = file_get_contents($path);
                        if (is_string($contents) && $contents !== '') {
                            $mime = mime_content_type($path) ?: 'application/octet-stream';
                            $companyLogo = 'data:' . $mime . ';base64,' . base64_encode($contents);
                        }
                    }
                }
            }
        }

        $serializedReport = [
            'id' => $this->report->id,
            'period' => $this->report->period,
            'locale' => $this->report->locale ?: 'en',
            'period_start' => $this->report->period_start?->toDateString(),
            'period_end' => $this->report->period_end?->toDateString(),
            'status' => $this->report->status,
            'provider' => $this->report->provider,
            'model' => $this->report->model,
            'branch_name' => $this->report->branch?->name,
            'created_by_name' => $this->report->creator?->name ?: 'System',
            'generated_at' => $this->report->generated_at?->toDateTimeString(),
            'completed_at' => $this->report->completed_at?->toDateTimeString(),
            'created_at' => $this->report->created_at?->toDateTimeString(),
            'has_ai_result' => is_array($this->report->ai_result) && $this->report->ai_result !== [],
            'error_message' => $this->report->error_message,
            'ai_result' => $this->report->ai_result,
            'internal_payload' => $this->report->internal_payload,
        ];

        $payload = [
            'report' => $serializedReport,
            'tenant' => $tenant,
            'generatedAt' => now(),
            'companyName' => $companyName,
            'companyLogo' => $companyLogo,
            'siteSettings' => $siteSettings,
            'pdfHeader' => data_get($siteSettings, 'pdf_header', []),
        ];

        $pdfBinary = null;

        if (PdfRuntime::canUseBrowsershot()) {
            try {
                $pdfBinary = SpatiePdf::view('admin.ai-insights.pdf', $payload)
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
                    })
                    ->output();
            } catch (Throwable $e) {
                report($e);
            }
        }

        if (!$pdfBinary) {
            PdfRuntime::ensureDompdfDirectories();
            $dompdf = DomPdf::loadView('admin.ai-insights.pdf', $payload)
                ->setPaper('a4', 'portrait')
                ->setOption('isRemoteEnabled', true)
                ->setOption('fontDir', PdfRuntime::dompdfFontDirectory())
                ->setOption('fontCache', PdfRuntime::dompdfFontDirectory())
                ->setOption('tempDir', PdfRuntime::dompdfTempDirectory())
                ->setOption('defaultFont', 'DejaVu Sans');

            $pdfBinary = $dompdf->output();
        }

        $periodStart = $this->report->period_start?->toDateString() ?: '';
        $periodEnd = $this->report->period_end?->toDateString() ?: '';

        $email = (new MailMessage())
            ->subject("Monthly AI Insights Report: {$periodStart} to {$periodEnd}")
            ->greeting("Hello from {$companyName}!")
            ->line("Your monthly AI business insights report for the period {$periodStart} to {$periodEnd} is now ready.")
            ->line("We have compiled internal database snapshots regarding unprofitable cars, pricing opportunities, high-risk customers, and uncollected losses.")
            ->line("You can view the full details and OpenAI market study directly on your dashboard.");

        if ($pdfBinary) {
            $email->attachData($pdfBinary, "ai-insights-report-{$this->report->id}.pdf", [
                'mime' => 'application/pdf',
            ]);
        }

        $email->action('View AI Insights Dashboard', url('/admin/ai-insights'));

        return $email;
    }
}
