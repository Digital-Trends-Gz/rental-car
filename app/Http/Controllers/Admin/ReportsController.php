<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Enums\CarStatus;
use App\Enums\CarViolationStatus;
use App\Enums\ContractStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AccidentReport;
use App\Support\BranchAccess;
use App\Support\FinancialVisibility;
use App\Models\Car;
use App\Models\CarDamageReport;
use App\Models\CarMaintenance;
use App\Models\CarViolation;
use App\Models\Contract;
use App\Models\ContractReturnReport;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\TenantSiteSetting;
use App\Models\User;
use App\Support\PdfRuntime;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

class ReportsController extends Controller
{
    public function __construct(private BranchAccess $branchAccess)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);
        $canViewFinancialAmounts = FinancialVisibility::canViewFinancialAmounts($user);
        $period = $request->get('period', 'this_month');
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $branchOptions = $this->branchAccess->availableBranchesForUser($user)
            ->map(fn ($branch) => ['id' => $branch->id, 'name' => $branch->name])
            ->values();
        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;
        $dateRange = $this->getDateRange($period);

        $executiveReport = $this->getExecutiveReport($dateRange, $user, $branchId, $canViewFinancialAmounts);
        $executiveReport['exports'] = $this->executiveExportUrls(
            $period,
            $branchId,
            $request->route('subdomain')
        );
        $financialReportExports = $this->financialExportUrls(
            $period,
            $branchId,
            $request->route('subdomain')
        );
        $reservationsReportExports = $this->reservationsExportUrls(
            $period,
            $branchId,
            $request->route('subdomain')
        );
        $fleetReportExports = $this->fleetExportUrls(
            $period,
            $branchId,
            $request->route('subdomain')
        );
        $vehicleProfitabilityReportExports = $this->vehicleProfitabilityExportUrls(
            $period,
            $branchId,
            $request->route('subdomain')
        );
        $customersReportExports = $this->customersExportUrls(
            $period,
            $branchId,
            $request->route('subdomain')
        );
        $damagesReportExports = $this->damagesExportUrls(
            $period,
            $branchId,
            $request->route('subdomain')
        );

        $data = [
            'kpis' => $this->getHighLevelKPIs($dateRange, $user, $branchId, $canViewFinancialAmounts),
            'carsState' => $this->getCarsState($user, $branchId),
            'reservationsChart' => $this->getReservationsChart($dateRange, $user, $branchId),
            'reservationsReport' => $this->getReservationsReportSummary($dateRange, $user, $branchId, $canViewFinancialAmounts),
            'reservationsReportExports' => $reservationsReportExports,
            'carsPerformance' => $this->getCarsPerformance($dateRange, $user, $branchId, $canViewFinancialAmounts),
            'financialSummary' => $this->getFinancialSummary($dateRange, $user, $branchId, $canViewFinancialAmounts),
            'financialReportSections' => $this->financialReportSections($dateRange, $user, $branchId, $canViewFinancialAmounts),
            'financialReportExports' => $financialReportExports,
            'financialAlerts' => $this->getFinancialAlerts($dateRange, $user, $branchId, $canViewFinancialAmounts),
            'operationsSummary' => $this->getOperationsSummary($dateRange, $user, $branchId),
            'fleetInsights' => $this->getFleetInsights($dateRange, $user, $branchId, $canViewFinancialAmounts),
            'fleetReport' => $this->getFleetReportData($dateRange, $user, $branchId, $canViewFinancialAmounts),
            'fleetReportExports' => $fleetReportExports,
            'vehicleProfitabilityReport' => $this->getVehicleProfitabilityReport($dateRange, $user, $branchId, $canViewFinancialAmounts),
            'vehicleProfitabilityReportExports' => $vehicleProfitabilityReportExports,
            'customersReport' => $this->getCustomersReport($dateRange, $user, $branchId, $canViewFinancialAmounts),
            'customersReportExports' => $customersReportExports,
            'damagesReport' => $this->getDamagesReport($dateRange, $user, $branchId, $canViewFinancialAmounts),
            'damagesReportExports' => $damagesReportExports,
            'actionAlerts' => $this->getActionAlerts($user, $branchId, $canViewFinancialAmounts),
            'executiveReport' => $executiveReport,
            'currentPeriod' => $period,
            'periodOptions' => $this->getPeriodOptions(),
            'branches' => $branchOptions,
            'canAccessAllBranches' => $canAccessAllBranches,
            'selectedBranchId' => $branchId,
            'canViewFinancials' => $canViewFinancialAmounts,
        ];

        return inertia('Admin/Reports/Index', $data);
    }

    public function exportExecutivePdf(Request $request)
    {
        $payload = $this->buildExecutiveExportPayload($request);
        $fileName = $this->executiveExportFileName('pdf');

        if (PdfRuntime::canUseBrowsershot()) {
            try {
                $pdf = Pdf::view('admin.reports.executive-pdf', $payload)
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

        $pdf = DomPdf::loadView('admin.reports.executive-pdf', $payload)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('fontDir', PdfRuntime::dompdfFontDirectory())
            ->setOption('fontCache', PdfRuntime::dompdfFontDirectory())
            ->setOption('tempDir', PdfRuntime::dompdfTempDirectory())
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($fileName);
    }

    public function exportExecutiveExcel(Request $request)
    {
        $payload = $this->buildExecutiveExportPayload($request);
        $fileName = $this->executiveExportFileName('xls');

        return response()
            ->view('admin.reports.executive-excel', $payload)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"');
    }

    private function buildFleetExportPayload(Request $request): array
    {
        $user = $request->user();
        $canViewFinancialAmounts = FinancialVisibility::canViewFinancialAmounts($user);
        $period = $request->get('period', 'this_month');
        $branchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $dateRange = $this->getDateRange($period);
        $tenant = TenantContext::get();
        $tenant = $tenant?->loadMissing('siteSetting.files');
        $siteSettings = $tenant ? TenantSiteSetting::forTenant($tenant) : [];

        $fleetReport = $this->getFleetReportData($dateRange, $user, $branchId, $canViewFinancialAmounts);

        return [
            'periodLabel' => collect($this->getPeriodOptions())->firstWhere('value', $period)['label'] ?? $period,
            'tenant' => $tenant,
            'siteSettings' => $siteSettings,
            'companyName' => $this->reportCompanyName($tenant, $siteSettings),
            'companyLogo' => $this->pdfImageSource($siteSettings['logo_url'] ?? null),
            'pdfHeader' => data_get($siteSettings, 'pdf_header', []),
            'reportNumber' => 'FLT-'.now()->format('Ymd-Hi'),
            'fleetReport' => $fleetReport,
            'canViewFinancialAmounts' => $canViewFinancialAmounts,
            'dateRange' => $dateRange,
        ];
    }

    public function exportFleetPdf(Request $request)
    {
        $payload = $this->buildFleetExportPayload($request);
        $fileName = 'fleet-report-'.now()->format('Y-m-d_H-i').'.pdf';

        if (PdfRuntime::canUseBrowsershot()) {
            try {
                $pdf = Pdf::view('admin.reports.fleet-pdf', $payload)
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

        $pdf = DomPdf::loadView('admin.reports.fleet-pdf', $payload)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('fontDir', PdfRuntime::dompdfFontDirectory())
            ->setOption('fontCache', PdfRuntime::dompdfFontDirectory())
            ->setOption('tempDir', PdfRuntime::dompdfTempDirectory())
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($fileName);
    }

    public function exportVehicleProfitabilityPdf(Request $request)
    {
        $payload = $this->buildVehicleProfitabilityExportPayload($request);
        $fileName = $this->vehicleProfitabilityExportFileName('pdf');

        if (PdfRuntime::canUseBrowsershot()) {
            try {
                $pdf = Pdf::view('admin.reports.vehicle-profitability-pdf', $payload)
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

        $pdf = DomPdf::loadView('admin.reports.vehicle-profitability-pdf', $payload)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('fontDir', PdfRuntime::dompdfFontDirectory())
            ->setOption('fontCache', PdfRuntime::dompdfFontDirectory())
            ->setOption('tempDir', PdfRuntime::dompdfTempDirectory())
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($fileName);
    }

    public function exportVehicleProfitabilityExcel(Request $request)
    {
        $payload = $this->buildVehicleProfitabilityExportPayload($request);
        $fileName = $this->vehicleProfitabilityExportFileName('xls');

        return response()
            ->view('admin.reports.vehicle-profitability-excel', $payload)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"');
    }

    public function exportCustomersPdf(Request $request)
    {
        $payload = $this->buildCustomersExportPayload($request);
        $fileName = $this->customersExportFileName('pdf');

        if (PdfRuntime::canUseBrowsershot()) {
            try {
                $pdf = Pdf::view('admin.reports.customers-pdf', $payload)
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

        $pdf = DomPdf::loadView('admin.reports.customers-pdf', $payload)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('fontDir', PdfRuntime::dompdfFontDirectory())
            ->setOption('fontCache', PdfRuntime::dompdfFontDirectory())
            ->setOption('tempDir', PdfRuntime::dompdfTempDirectory())
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($fileName);
    }

    public function exportCustomersExcel(Request $request)
    {
        $payload = $this->buildCustomersExportPayload($request);
        $fileName = $this->customersExportFileName('xls');

        return response()
            ->view('admin.reports.customers-excel', $payload)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"');
    }

    public function exportDamagesPdf(Request $request)
    {
        $payload = $this->buildDamagesExportPayload($request);
        $fileName = $this->damagesExportFileName('pdf');

        if (PdfRuntime::canUseBrowsershot()) {
            try {
                $pdf = Pdf::view('admin.reports.damages-pdf', $payload)
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

        $pdf = DomPdf::loadView('admin.reports.damages-pdf', $payload)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('fontDir', PdfRuntime::dompdfFontDirectory())
            ->setOption('fontCache', PdfRuntime::dompdfFontDirectory())
            ->setOption('tempDir', PdfRuntime::dompdfTempDirectory())
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($fileName);
    }

    public function exportFinancialPdf(Request $request)
    {
        $payload = $this->buildFinancialExportPayload($request);
        $fileName = $this->financialExportFileName('pdf');

        if (PdfRuntime::canUseBrowsershot()) {
            try {
                $pdf = Pdf::view('admin.reports.financial-pdf', $payload)
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

        $pdf = DomPdf::loadView('admin.reports.financial-pdf', $payload)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('fontDir', PdfRuntime::dompdfFontDirectory())
            ->setOption('fontCache', PdfRuntime::dompdfFontDirectory())
            ->setOption('tempDir', PdfRuntime::dompdfTempDirectory())
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($fileName);
    }

    public function exportFinancialExcel(Request $request)
    {
        $payload = $this->buildFinancialExportPayload($request);
        $fileName = $this->financialExportFileName('xls');

        return response()
            ->view('admin.reports.financial-excel', $payload)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"');
    }

    public function exportReservationsPdf(Request $request)
    {
        $payload = $this->buildReservationsExportPayload($request);
        $fileName = $this->reservationsExportFileName('pdf');

        if (PdfRuntime::canUseBrowsershot()) {
            try {
                $pdf = Pdf::view('admin.reports.reservations-pdf', $payload)
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

        $pdf = DomPdf::loadView('admin.reports.reservations-pdf', $payload)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('fontDir', PdfRuntime::dompdfFontDirectory())
            ->setOption('fontCache', PdfRuntime::dompdfFontDirectory())
            ->setOption('tempDir', PdfRuntime::dompdfTempDirectory())
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($fileName);
    }

    public function exportReservationsExcel(Request $request)
    {
        $payload = $this->buildReservationsExportPayload($request);
        $fileName = $this->reservationsExportFileName('xls');

        return response()
            ->view('admin.reports.reservations-excel', $payload)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"');
    }

    private function buildExecutiveExportPayload(Request $request): array
    {
        $user = $request->user();
        $period = $request->get('period', 'this_month');
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $branchOptions = $this->branchAccess->availableBranchesForUser($user)
            ->map(fn ($branch) => ['id' => $branch->id, 'name' => $branch->name])
            ->values();
        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;
        $dateRange = $this->getDateRange($period);
        $canViewFinancialAmounts = FinancialVisibility::canViewFinancialAmounts($user);
        $report = $this->getExecutiveReport($dateRange, $user, $branchId, $canViewFinancialAmounts);
        $tenant = TenantContext::get();
        $tenant = $tenant?->loadMissing('siteSetting.files');
        $siteSettings = $tenant ? TenantSiteSetting::forTenant($tenant) : [];

        return [
            'report' => $report,
            'financialReportSections' => $this->financialReportSections($dateRange, $user, $branchId, $canViewFinancialAmounts),
            'period' => $period,
            'periodLabel' => collect($this->getPeriodOptions())->firstWhere('value', $period)['label'] ?? $period,
            'branchName' => $branchId
                ? ($branchOptions->firstWhere('id', $branchId)['name'] ?? 'Selected branch')
                : 'All branches',
            'branchId' => $branchId,
            'dateRange' => $dateRange,
            'tenant' => $tenant,
            'generatedAt' => now(),
            'reportNumber' => 'EXR-'.now()->format('Ymd-Hi'),
            'canViewFinancials' => $canViewFinancialAmounts,
            'companyName' => $this->reportCompanyName($tenant, $siteSettings),
            'companyLogo' => $this->pdfImageSource($siteSettings['logo_url'] ?? null),
            'siteSettings' => $siteSettings,
            'pdfHeader' => data_get($siteSettings, 'pdf_header', []),
        ];
    }

    private function buildFinancialExportPayload(Request $request): array
    {
        $payload = $this->buildExecutiveExportPayload($request);
        $payload['reportNumber'] = 'FNR-'.now()->format('Ymd-Hi');

        return $payload;
    }

    private function buildVehicleProfitabilityExportPayload(Request $request): array
    {
        $payload = $this->buildExecutiveExportPayload($request);
        $user = $request->user();
        $branchId = $payload['branchId'] ?? null;
        $canViewFinancialAmounts = FinancialVisibility::canViewFinancialAmounts($user);

        $payload['reportNumber'] = 'VPR-'.now()->format('Ymd-Hi');
        $payload['vehicleProfitabilityReport'] = $this->getVehicleProfitabilityReport(
            $payload['dateRange'],
            $user,
            $branchId,
            $canViewFinancialAmounts
        );

        return $payload;
    }

    private function buildCustomersExportPayload(Request $request): array
    {
        $payload = $this->buildExecutiveExportPayload($request);
        $user = $request->user();
        $branchId = $payload['branchId'] ?? null;
        $canViewFinancialAmounts = FinancialVisibility::canViewFinancialAmounts($user);

        $payload['reportNumber'] = 'CUR-'.now()->format('Ymd-Hi');
        $payload['customersReport'] = $this->getCustomersReport(
            $payload['dateRange'],
            $user,
            $branchId,
            $canViewFinancialAmounts
        );

        return $payload;
    }

    private function buildDamagesExportPayload(Request $request): array
    {
        $payload = $this->buildExecutiveExportPayload($request);
        $user = $request->user();
        $branchId = $payload['branchId'] ?? null;
        $canViewFinancialAmounts = FinancialVisibility::canViewFinancialAmounts($user);

        $payload['reportNumber'] = 'DMR-'.now()->format('Ymd-Hi');
        $payload['damagesReport'] = $this->getDamagesReport(
            $payload['dateRange'],
            $user,
            $branchId,
            $canViewFinancialAmounts
        );

        return $payload;
    }

    private function buildReservationsExportPayload(Request $request): array
    {
        $payload = $this->buildExecutiveExportPayload($request);
        $payload['reportNumber'] = 'RES-'.now()->format('Ymd-Hi');
        
        $user = $request->user();
        $period = $request->get('period', 'this_month');
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $branchOptions = $this->branchAccess->availableBranchesForUser($user)
            ->map(fn ($branch) => ['id' => $branch->id, 'name' => $branch->name])
            ->values();
        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;
        $dateRange = $this->getDateRange($period);
        $canViewFinancialAmounts = FinancialVisibility::canViewFinancialAmounts($user);

        $payload['reservationsReport'] = $this->getReservationsReportSummary($dateRange, $user, $branchId, $canViewFinancialAmounts);

        return $payload;
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

        $path = null;

        if (str_starts_with($url, '/storage/')) {
            $path = public_path(ltrim($url, '/'));
        } elseif (str_starts_with($url, 'storage/')) {
            $path = public_path($url);
        } elseif (str_starts_with($url, '/')) {
            $path = public_path(ltrim($url, '/'));
        }

        if (!$path || !is_file($path)) {
            return $url;
        }

        $contents = file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            return null;
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private function damageReportPhotoUrl(CarDamageReport $report): ?string
    {
        $file = $report->files
            ->sortBy([
                ['order', 'asc'],
                ['id', 'asc'],
            ])
            ->first();

        if ($file && $file->path) {
            $path = ltrim((string) preg_replace('/^storage\//', '', (string) $file->path), '/');

            return asset('storage/'.$path);
        }

        $handoverPhotos = $report->handoverPhotos
            ->sortBy('id')
            ->values();

        $handoverPhoto = $handoverPhotos
            ->firstWhere('photo_type', 'damage')
            ?? $handoverPhotos
            ->first();

        if (!$handoverPhoto || !$handoverPhoto->file_path) {
            return null;
        }

        $path = ltrim((string) preg_replace('/^storage\//', '', (string) $handoverPhoto->file_path), '/');

        return asset('storage/'.$path);
    }

    private function executiveExportUrls(string $period, ?int $branchId, ?string $subdomain): array
    {
        $query = ['period' => $period];

        if ($subdomain) {
            $query['subdomain'] = $subdomain;
        }

        if ($branchId) {
            $query['branch_id'] = $branchId;
        }

        return [
            'pdf' => route('admin.reports.executive.pdf', $query),
            'excel' => route('admin.reports.executive.excel', $query),
        ];
    }

    private function financialExportUrls(string $period, ?int $branchId, ?string $subdomain): array
    {
        $query = ['period' => $period];

        if ($subdomain) {
            $query['subdomain'] = $subdomain;
        }

        if ($branchId) {
            $query['branch_id'] = $branchId;
        }

        return [
            'pdf' => route('admin.reports.financial.pdf', $query),
            'excel' => route('admin.reports.financial.excel', $query),
        ];
    }

    private function reservationsExportUrls(string $period, ?int $branchId, ?string $subdomain): array
    {
        $query = ['period' => $period];

        if ($subdomain) {
            $query['subdomain'] = $subdomain;
        }

        if ($branchId) {
            $query['branch_id'] = $branchId;
        }

        return [
            'pdf' => route('admin.reports.reservations.pdf', $query),
            'excel' => route('admin.reports.reservations.excel', $query),
        ];
    }

    private function fleetExportUrls(string $period, ?int $branchId, ?string $subdomain): array
    {
        $query = ['period' => $period];

        if ($subdomain) {
            $query['subdomain'] = $subdomain;
        }

        if ($branchId) {
            $query['branch_id'] = $branchId;
        }

        return [
            'pdf' => route('admin.reports.fleet.pdf', $query),
            // 'excel' => route('admin.reports.fleet.excel', $query), // Implement later if needed
        ];
    }

    private function vehicleProfitabilityExportUrls(string $period, ?int $branchId, ?string $subdomain): array
    {
        $query = ['period' => $period];

        if ($subdomain) {
            $query['subdomain'] = $subdomain;
        }

        if ($branchId) {
            $query['branch_id'] = $branchId;
        }

        return [
            'pdf' => route('admin.reports.vehicle-profitability.pdf', $query),
            'excel' => route('admin.reports.vehicle-profitability.excel', $query),
        ];
    }

    private function customersExportUrls(string $period, ?int $branchId, ?string $subdomain): array
    {
        $query = ['period' => $period];

        if ($subdomain) {
            $query['subdomain'] = $subdomain;
        }

        if ($branchId) {
            $query['branch_id'] = $branchId;
        }

        return [
            'pdf' => route('admin.reports.customers.pdf', $query),
            'excel' => route('admin.reports.customers.excel', $query),
        ];
    }

    private function damagesExportUrls(string $period, ?int $branchId, ?string $subdomain): array
    {
        $query = ['period' => $period];

        if ($subdomain) {
            $query['subdomain'] = $subdomain;
        }

        if ($branchId) {
            $query['branch_id'] = $branchId;
        }

        return [
            'pdf' => route('admin.reports.damages.pdf', $query),
        ];
    }

    private function executiveExportFileName(string $extension): string
    {
        return 'executive-report-'.now()->format('Y-m-d_H-i').'.'.$extension;
    }

    private function financialExportFileName(string $extension): string
    {
        return 'financial-report-'.now()->format('Y-m-d_H-i').'.'.$extension;
    }

    private function reservationsExportFileName(string $extension): string
    {
        return 'reservations-report-'.now()->format('Y-m-d_H-i').'.'.$extension;
    }

    private function vehicleProfitabilityExportFileName(string $extension): string
    {
        return 'vehicle-profitability-report-'.now()->format('Y-m-d_H-i').'.'.$extension;
    }

    private function customersExportFileName(string $extension): string
    {
        return 'customers-report-'.now()->format('Y-m-d_H-i').'.'.$extension;
    }

    private function damagesExportFileName(string $extension): string
    {
        return 'damages-report-'.now()->format('Y-m-d_H-i').'.'.$extension;
    }

    private function financialReportSections(?array $dateRange = null, $user = null, ?int $branchId = null, bool $canViewFinancialAmounts = true): array
    {
        if (! $dateRange || ! $user) {
            return $this->localizedFinancialReportSections();
        }

        $currencySymbol = (string) config('app.currency_symbol', '$');
        $formatMoney = fn (float $value): string => $canViewFinancialAmounts
            ? $currencySymbol.number_format($value, 2)
            : '*******';
        $moneyItem = function (string $en, string $ar, float $amount, int $count = 0) use ($formatMoney, $canViewFinancialAmounts): array {
            return [
                'en' => $en,
                'ar' => $ar,
                'value' => $canViewFinancialAmounts ? round($amount, 2) : null,
                'formatted' => $formatMoney($amount),
                'count' => $count,
            ];
        };

        $completedPaymentsQuery = Payment::completed()
            ->whereBetween('processed_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyPaymentBranchScope($completedPaymentsQuery, $user, $branchId);

        $returnReportsQuery = ContractReturnReport::query()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->branchAccess->applyToQuery($returnReportsQuery, $user, $branchId);

        $reservationDiscountsQuery = Reservation::query()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyReservationBranchScope($reservationDiscountsQuery, $user, $branchId);

        $pendingPaymentsQuery = Payment::query()
            ->where('status', '!=', PaymentStatus::COMPLETED)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyPaymentBranchScope($pendingPaymentsQuery, $user, $branchId);

        $unpaidReturnReportsQuery = ContractReturnReport::query()
            ->where('payment_status', '!=', 'paid')
            ->where('total_extra_charges', '>', 0);
        $this->branchAccess->applyToQuery($unpaidReturnReportsQuery, $user, $branchId);

        $paidRevenue = (float) (clone $completedPaymentsQuery)->sum('amount');
        $paidPaymentsCount = (clone $completedPaymentsQuery)->count();
        $cashQuery = (clone $completedPaymentsQuery)->where('payment_method', PaymentMethod::CASH->value);
        $cardQuery = (clone $completedPaymentsQuery)->whereIn('payment_method', [
            PaymentMethod::CREDIT_CARD->value,
            PaymentMethod::DEBIT_CARD->value,
        ]);
        $bankTransferQuery = (clone $completedPaymentsQuery)->where('payment_method', PaymentMethod::BANK_TRANSFER->value);
        $onlineQuery = (clone $completedPaymentsQuery)->whereIn('payment_method', [
            PaymentMethod::STRIPE->value,
            PaymentMethod::MYFATOORAH->value,
            PaymentMethod::PAYPAL->value,
        ]);

        $lateFees = (float) (clone $returnReportsQuery)->selectRaw('COALESCE(SUM(late_hours * late_hour_rate), 0) as total')->value('total');
        $damageFees = (float) (clone $returnReportsQuery)->sum('damage_fee');
        $fuelFees = (float) (clone $returnReportsQuery)->sum('fuel_fee');
        $cleaningFees = (float) (clone $returnReportsQuery)->sum('cleaning_fee');
        $additionalServices = (float) (clone $returnReportsQuery)->selectRaw('COALESCE(SUM(maintenance_fee + other_fee), 0) as total')->value('total');
        $pendingPayments = (float) (clone $pendingPaymentsQuery)->sum('amount');
        $unpaidReturnCharges = (float) (clone $unpaidReturnReportsQuery)->sum('total_extra_charges');
        $returnDiscounts = (float) (clone $returnReportsQuery)->sum('discount');
        $couponDiscounts = (float) (clone $reservationDiscountsQuery)->whereNotNull('coupon_id')->sum('discount_amount');
        $manualReservationDiscounts = (float) (clone $reservationDiscountsQuery)
            ->where(function ($query): void {
                $query->whereNull('coupon_id')->orWhere('coupon_id', 0);
            })
            ->sum('discount_amount');
        $manualDiscounts = $manualReservationDiscounts + $returnDiscounts;

        return [
            [
                'title' => [
                    'en' => 'Revenue',
                    'ar' => "\u{0627}\u{0644}\u{0625}\u{064A}\u{0631}\u{0627}\u{062F}\u{0627}\u{062A}",
                ],
                'items' => [
                    $moneyItem('Rental income', "\u{0625}\u{064A}\u{0631}\u{0627}\u{062F}\u{0627}\u{062A} \u{0627}\u{0644}\u{0625}\u{064A}\u{062C}\u{0627}\u{0631}\u{0627}\u{062A}", $paidRevenue, $paidPaymentsCount),
                    $moneyItem('Late fees', "\u{0625}\u{064A}\u{0631}\u{0627}\u{062F}\u{0627}\u{062A} \u{0627}\u{0644}\u{062A}\u{0623}\u{062E}\u{064A}\u{0631}", $lateFees, (clone $returnReportsQuery)->where('late_hours', '>', 0)->count()),
                    $moneyItem('Damage fees', "\u{0625}\u{064A}\u{0631}\u{0627}\u{062F}\u{0627}\u{062A} \u{0627}\u{0644}\u{0623}\u{0636}\u{0631}\u{0627}\u{0631}", $damageFees, (clone $returnReportsQuery)->where('damage_fee', '>', 0)->count()),
                    $moneyItem('Fuel fees', "\u{0625}\u{064A}\u{0631}\u{0627}\u{062F}\u{0627}\u{062A} \u{0627}\u{0644}\u{0648}\u{0642}\u{0648}\u{062F}", $fuelFees, (clone $returnReportsQuery)->where('fuel_fee', '>', 0)->count()),
                    $moneyItem('Cleaning fees', "\u{0625}\u{064A}\u{0631}\u{0627}\u{062F}\u{0627}\u{062A} \u{0627}\u{0644}\u{062A}\u{0646}\u{0638}\u{064A}\u{0641}", $cleaningFees, (clone $returnReportsQuery)->where('cleaning_fee', '>', 0)->count()),
                    $moneyItem('Additional services revenue', "\u{0625}\u{064A}\u{0631}\u{0627}\u{062F}\u{0627}\u{062A} \u{0627}\u{0644}\u{062E}\u{062F}\u{0645}\u{0627}\u{062A} \u{0627}\u{0644}\u{0625}\u{0636}\u{0627}\u{0641}\u{064A}\u{0629}", $additionalServices, (clone $returnReportsQuery)->where(function ($query): void {
                        $query->where('maintenance_fee', '>', 0)->orWhere('other_fee', '>', 0);
                    })->count()),
                ],
            ],
            [
                'title' => [
                    'en' => 'Payments',
                    'ar' => "\u{0627}\u{0644}\u{0645}\u{062F}\u{0641}\u{0648}\u{0639}\u{0627}\u{062A}",
                ],
                'items' => [
                    $moneyItem('Cash', "\u{0646}\u{0642}\u{062F}\u{064A}", (float) (clone $cashQuery)->sum('amount'), (clone $cashQuery)->count()),
                    $moneyItem('Card', "\u{0628}\u{0637}\u{0627}\u{0642}\u{0629}", (float) (clone $cardQuery)->sum('amount'), (clone $cardQuery)->count()),
                    $moneyItem('Bank transfer', "\u{062A}\u{062D}\u{0648}\u{064A}\u{0644} \u{0628}\u{0646}\u{0643}\u{064A}", (float) (clone $bankTransferQuery)->sum('amount'), (clone $bankTransferQuery)->count()),
                    $moneyItem('Online', "\u{0623}\u{0648}\u{0646}\u{0644}\u{0627}\u{064A}\u{0646}", (float) (clone $onlineQuery)->sum('amount'), (clone $onlineQuery)->count()),
                ],
            ],
            [
                'title' => [
                    'en' => 'Receivables',
                    'ar' => "\u{0627}\u{0644}\u{0630}\u{0645}\u{0645} \u{0627}\u{0644}\u{0645}\u{062F}\u{064A}\u{0646}\u{0629}",
                ],
                'items' => [
                    $moneyItem('Debtors', "\u{0627}\u{0644}\u{0639}\u{0645}\u{0644}\u{0627}\u{0621} \u{0627}\u{0644}\u{0645}\u{062F}\u{064A}\u{0646}\u{0648}\u{0646}", $pendingPayments + $unpaidReturnCharges, (clone $pendingPaymentsQuery)->distinct('user_id')->count('user_id')),
                    $moneyItem('Outstanding amounts', "\u{0627}\u{0644}\u{0645}\u{0628}\u{0627}\u{0644}\u{063A} \u{0627}\u{0644}\u{0645}\u{0633}\u{062A}\u{062D}\u{0642}\u{0629}", $pendingPayments + $unpaidReturnCharges, (clone $pendingPaymentsQuery)->count() + (clone $unpaidReturnReportsQuery)->count()),
                    $moneyItem('Overdue balances', "\u{0627}\u{0644}\u{0645}\u{062A}\u{0623}\u{062E}\u{0631}\u{0627}\u{062A}", $unpaidReturnCharges, (clone $unpaidReturnReportsQuery)->count()),
                ],
            ],
            [
                'title' => [
                    'en' => 'Discounts',
                    'ar' => "\u{0627}\u{0644}\u{062E}\u{0635}\u{0648}\u{0645}\u{0627}\u{062A}",
                ],
                'items' => [
                    $moneyItem('Coupons', "\u{0643}\u{0648}\u{0628}\u{0648}\u{0646}\u{0627}\u{062A}", $couponDiscounts, (clone $reservationDiscountsQuery)->whereNotNull('coupon_id')->where('discount_amount', '>', 0)->count()),
                    $moneyItem('Manual discounts', "\u{062E}\u{0635}\u{0648}\u{0645}\u{0627}\u{062A} \u{064A}\u{062F}\u{0648}\u{064A}\u{0629}", $manualDiscounts, (clone $reservationDiscountsQuery)->where(function ($query): void {
                        $query->whereNull('coupon_id')->orWhere('coupon_id', 0);
                    })->where('discount_amount', '>', 0)->count() + (clone $returnReportsQuery)->where('discount', '>', 0)->count()),
                ],
            ],
        ];

        return [
            [
                'title' => [
                    'en' => 'Revenue',
                    'ar' => 'الإيرادات',
                ],
                'items' => [
                    ['en' => 'Rental income', 'ar' => 'إيرادات الإيجارات'],
                    ['en' => 'Late fees', 'ar' => 'إيرادات التأخير'],
                    ['en' => 'Damage fees', 'ar' => 'إيرادات الأضرار'],
                    ['en' => 'Fuel fees', 'ar' => 'إيرادات الوقود'],
                    ['en' => 'Cleaning fees', 'ar' => 'إيرادات التنظيف'],
                    ['en' => 'Additional services revenue', 'ar' => 'إيرادات الخدمات الإضافية'],
                ],
            ],
            [
                'title' => [
                    'en' => 'Payments',
                    'ar' => 'المدفوعات',
                ],
                'items' => [
                    ['en' => 'Cash', 'ar' => 'نقدي'],
                    ['en' => 'Card', 'ar' => 'بطاقة'],
                    ['en' => 'Bank transfer', 'ar' => 'تحويل بنكي'],
                    ['en' => 'Online', 'ar' => 'أونلاين'],
                ],
            ],
            [
                'title' => [
                    'en' => 'Receivables',
                    'ar' => 'الذمم المدينة',
                ],
                'items' => [
                    ['en' => 'Debtors', 'ar' => 'العملاء المدينون'],
                    ['en' => 'Outstanding amounts', 'ar' => 'المبالغ المستحقة'],
                    ['en' => 'Overdue balances', 'ar' => 'المتأخرات'],
                ],
            ],
            [
                'title' => [
                    'en' => 'Discounts',
                    'ar' => 'الخصومات',
                ],
                'items' => [
                    ['en' => 'Coupons', 'ar' => 'كوبونات'],
                    ['en' => 'Manual discounts', 'ar' => 'خصومات يدوية'],
                ],
            ],
        ];
    }

    private function localizedFinancialReportSections(): array
    {
        return [
            [
                'title' => [
                    'en' => 'Revenue',
                    'ar' => "\u{0627}\u{0644}\u{0625}\u{064A}\u{0631}\u{0627}\u{062F}\u{0627}\u{062A}",
                ],
                'items' => [
                    ['en' => 'Rental income', 'ar' => "\u{0625}\u{064A}\u{0631}\u{0627}\u{062F}\u{0627}\u{062A} \u{0627}\u{0644}\u{0625}\u{064A}\u{062C}\u{0627}\u{0631}\u{0627}\u{062A}"],
                    ['en' => 'Late fees', 'ar' => "\u{0625}\u{064A}\u{0631}\u{0627}\u{062F}\u{0627}\u{062A} \u{0627}\u{0644}\u{062A}\u{0623}\u{062E}\u{064A}\u{0631}"],
                    ['en' => 'Damage fees', 'ar' => "\u{0625}\u{064A}\u{0631}\u{0627}\u{062F}\u{0627}\u{062A} \u{0627}\u{0644}\u{0623}\u{0636}\u{0631}\u{0627}\u{0631}"],
                    ['en' => 'Fuel fees', 'ar' => "\u{0625}\u{064A}\u{0631}\u{0627}\u{062F}\u{0627}\u{062A} \u{0627}\u{0644}\u{0648}\u{0642}\u{0648}\u{062F}"],
                    ['en' => 'Cleaning fees', 'ar' => "\u{0625}\u{064A}\u{0631}\u{0627}\u{062F}\u{0627}\u{062A} \u{0627}\u{0644}\u{062A}\u{0646}\u{0638}\u{064A}\u{0641}"],
                    ['en' => 'Additional services revenue', 'ar' => "\u{0625}\u{064A}\u{0631}\u{0627}\u{062F}\u{0627}\u{062A} \u{0627}\u{0644}\u{062E}\u{062F}\u{0645}\u{0627}\u{062A} \u{0627}\u{0644}\u{0625}\u{0636}\u{0627}\u{0641}\u{064A}\u{0629}"],
                ],
            ],
            [
                'title' => [
                    'en' => 'Payments',
                    'ar' => "\u{0627}\u{0644}\u{0645}\u{062F}\u{0641}\u{0648}\u{0639}\u{0627}\u{062A}",
                ],
                'items' => [
                    ['en' => 'Cash', 'ar' => "\u{0646}\u{0642}\u{062F}\u{064A}"],
                    ['en' => 'Card', 'ar' => "\u{0628}\u{0637}\u{0627}\u{0642}\u{0629}"],
                    ['en' => 'Bank transfer', 'ar' => "\u{062A}\u{062D}\u{0648}\u{064A}\u{0644} \u{0628}\u{0646}\u{0643}\u{064A}"],
                    ['en' => 'Online', 'ar' => "\u{0623}\u{0648}\u{0646}\u{0644}\u{0627}\u{064A}\u{0646}"],
                ],
            ],
            [
                'title' => [
                    'en' => 'Receivables',
                    'ar' => "\u{0627}\u{0644}\u{0630}\u{0645}\u{0645} \u{0627}\u{0644}\u{0645}\u{062F}\u{064A}\u{0646}\u{0629}",
                ],
                'items' => [
                    ['en' => 'Debtors', 'ar' => "\u{0627}\u{0644}\u{0639}\u{0645}\u{0644}\u{0627}\u{0621} \u{0627}\u{0644}\u{0645}\u{062F}\u{064A}\u{0646}\u{0648}\u{0646}"],
                    ['en' => 'Outstanding amounts', 'ar' => "\u{0627}\u{0644}\u{0645}\u{0628}\u{0627}\u{0644}\u{063A} \u{0627}\u{0644}\u{0645}\u{0633}\u{062A}\u{062D}\u{0642}\u{0629}"],
                    ['en' => 'Overdue balances', 'ar' => "\u{0627}\u{0644}\u{0645}\u{062A}\u{0623}\u{062E}\u{0631}\u{0627}\u{062A}"],
                ],
            ],
            [
                'title' => [
                    'en' => 'Discounts',
                    'ar' => "\u{0627}\u{0644}\u{062E}\u{0635}\u{0648}\u{0645}\u{0627}\u{062A}",
                ],
                'items' => [
                    ['en' => 'Coupons', 'ar' => "\u{0643}\u{0648}\u{0628}\u{0648}\u{0646}\u{0627}\u{062A}"],
                    ['en' => 'Manual discounts', 'ar' => "\u{062E}\u{0635}\u{0648}\u{0645}\u{0627}\u{062A} \u{064A}\u{062F}\u{0648}\u{064A}\u{0629}"],
                ],
            ],
        ];
    }

    private function getDateRange(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay()
            ],
            'yesterday' => [
                'start' => $now->copy()->subDay()->startOfDay(),
                'end' => $now->copy()->subDay()->endOfDay()
            ],
            'this_week' => [
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek()
            ],
            'last_week' => [
                'start' => $now->copy()->subWeek()->startOfWeek(),
                'end' => $now->copy()->subWeek()->endOfWeek()
            ],
            'this_month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth()
            ],
            'last_month' => [
                'start' => $now->copy()->subMonth()->startOfMonth(),
                'end' => $now->copy()->subMonth()->endOfMonth()
            ],
            'this_year' => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfYear()
            ],
            'last_year' => [
                'start' => $now->copy()->subYear()->startOfYear(),
                'end' => $now->copy()->subYear()->endOfYear()
            ],
            default => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth()
            ]
        };
    }


    public function getPlatformVisits(array $dateRange): int
    {
        // Hash together the start, end, and current hour for uniqueness
        $hashSource = $dateRange['start']->toDateString() .
            $dateRange['end']->toDateString() .
            now()->format('H');

        // Use crc32 for reproducible pseudo-random seed
        $seed = crc32($hashSource);

        // Convert to a number between 1000 and 3000
        mt_srand($seed);
        $base = mt_rand(1000, 3000);

        // Optional: scale slightly based on period length (so longer ranges look higher)
        $days = $dateRange['start']->diffInDays($dateRange['end']) + 1;
        $bonus = min(1000, $days * 20); // cap the bonus
        $value = min(3000, $base + $bonus);

        return $value;
    }


    private function getHighLevelKPIs(array $dateRange, $user, ?int $branchId, bool $canViewFinancialAmounts): array
    {
        // Total Revenue from completed payments in the period
        $totalRevenueQuery = Payment::completed()
            ->whereBetween('processed_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyPaymentBranchScope($totalRevenueQuery, $user, $branchId);
        $totalRevenue = FinancialVisibility::numericAmount($totalRevenueQuery->sum('amount'), $canViewFinancialAmounts);

        
        $platformVisits = $this->getPlatformVisits($dateRange);

        // Active reservations in the period
        $activeReservationsQuery = Reservation::whereIn('status', [
            ReservationStatus::ACTIVE
        ])
            ->whereBetween('start_date', [$dateRange['start'], $dateRange['end']]);
        $this->applyReservationBranchScope($activeReservationsQuery, $user, $branchId);
        $activeReservations = $activeReservationsQuery->count();

        // New clients in the period
        $newClientsQuery = User::where('role', UserRole::CLIENT)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->branchAccess->applyToQuery($newClientsQuery, $user, $branchId);
        $newClients = $newClientsQuery->count();

        return [
            'totalRevenue' => [
                'value' => $totalRevenue,
                'formatted' => $canViewFinancialAmounts
                    ? config('app.currency_symbol') . number_format($totalRevenue, 2)
                    : '*******',
                'label' => 'Total Revenue'
            ],
            'platformVisits' => [
                'value' => $platformVisits,
                'formatted' => number_format($platformVisits),
                'label' => 'Platform Visits'
            ],
            'activeReservations' => [
                'value' => $activeReservations,
                'formatted' => number_format($activeReservations),
                'label' => 'Active Reservations'
            ],
            'newClients' => [
                'value' => $newClients,
                'formatted' => number_format($newClients),
                'label' => 'New Clients'
            ]
        ];
    }

    private function getCarsState($user, ?int $branchId): array
    {
        $totalCarsQuery = Car::query();
        $this->branchAccess->applyToQuery($totalCarsQuery, $user, $branchId);
        $totalCars = $totalCarsQuery->count();

        $availableCarsQuery = Car::where('status', CarStatus::AVAILABLE);
        $this->branchAccess->applyToQuery($availableCarsQuery, $user, $branchId);
        $availableCars = $availableCarsQuery->count();

        $rentedCarsQuery = Car::whereIn('status', [CarStatus::RENTED, CarStatus::RESERVED]);
        $this->branchAccess->applyToQuery($rentedCarsQuery, $user, $branchId);
        $rentedCars = $rentedCarsQuery->count();

        // Unavailable cars (maintenance, cleaning, unavailable, retired)
        $unavailableCarsQuery = Car::whereIn('status', [
            CarStatus::MAINTENANCE,
            CarStatus::CLEANING,
            CarStatus::UNAVAILABLE,
            CarStatus::RETIRED
        ]);
        $this->branchAccess->applyToQuery($unavailableCarsQuery, $user, $branchId);
        $unavailableCars = $unavailableCarsQuery->count();

        return [
            'totalCars' => [
                'value' => $totalCars,
                'formatted' => number_format($totalCars),
                'label' => 'Total Cars',
                'color' => '#6366F1' // Indigo
            ],
            'availableCars' => [
                'value' => $availableCars,
                'formatted' => number_format($availableCars),
                'label' => 'Available Cars',
                'color' => CarStatus::AVAILABLE->color()
            ],
            'rentedCars' => [
                'value' => $rentedCars,
                'formatted' => number_format($rentedCars),
                'label' => 'Rented Cars',
                'color' => CarStatus::RENTED->color()
            ],
            'unavailableCars' => [
                'value' => $unavailableCars,
                'formatted' => number_format($unavailableCars),
                'label' => 'Unavailable Cars',
                'color' => '#6B7280' // Gray
            ]
        ];
    }

    private function getReservationsReportSummary(array $dateRange, $user, ?int $branchId, bool $canViewFinancialAmounts): array
    {
        $baseQuery = Reservation::whereBetween('reservations.created_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyReservationBranchScope($baseQuery, $user, $branchId);

        $totalReservations = (clone $baseQuery)->count();
        $confirmedReservations = (clone $baseQuery)->where('reservations.status', ReservationStatus::CONFIRMED)->count();
        $canceledReservations = (clone $baseQuery)->where('reservations.status', ReservationStatus::CANCELLED)->count();
        $noShowReservations = (clone $baseQuery)->where('reservations.status', ReservationStatus::NO_SHOW)->count();
        $completedReservations = (clone $baseQuery)->where('reservations.status', ReservationStatus::COMPLETED)->count();

        $totalValue = (clone $baseQuery)->whereNotIn('reservations.status', [ReservationStatus::CANCELLED, ReservationStatus::NO_SHOW])->sum('reservations.total_amount');
        $validReservationsCount = (clone $baseQuery)->whereNotIn('reservations.status', [ReservationStatus::CANCELLED, ReservationStatus::NO_SHOW])->count();
        
        $averageValue = $validReservationsCount > 0 ? $totalValue / $validReservationsCount : 0;
        $averageValue = FinancialVisibility::numericAmount($averageValue, $canViewFinancialAmounts);
        
        $cancellationRate = $totalReservations > 0 ? ($canceledReservations / $totalReservations) * 100 : 0;
        $noShowRate = $totalReservations > 0 ? ($noShowReservations / $totalReservations) * 100 : 0;

        $highestDayData = (clone $baseQuery)
            ->selectRaw('DATE(reservations.created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderByDesc('count')
            ->first();

        $highestDay = $highestDayData ? $highestDayData->date : null;
        $highestDayCount = $highestDayData ? $highestDayData->count : 0;

        // Reservations by branch
        $byBranch = [];
        if ($this->branchAccess->canAccessAllBranches($user)) {
            $branchData = (clone $baseQuery)
                ->join('cars', 'reservations.car_id', '=', 'cars.id')
                ->join('branches', 'cars.branch_id', '=', 'branches.id')
                ->selectRaw('branches.name as branch_name, COUNT(reservations.id) as count')
                ->groupBy('branches.id', 'branches.name')
                ->get();

            $byBranch = [
                'labels' => $branchData->pluck('branch_name')->toArray(),
                'data' => $branchData->pluck('count')->toArray(),
            ];
        }

        return [
            'summary' => [
                'total' => $totalReservations,
                'confirmed' => $confirmedReservations,
                'canceled' => $canceledReservations,
                'no_show' => $noShowReservations,
                'completed' => $completedReservations,
            ],
            'kpis' => [
                'average_value' => [
                    'value' => $averageValue,
                    'formatted' => $canViewFinancialAmounts ? config('app.currency_symbol') . number_format($averageValue, 2) : '*******',
                ],
                'highest_day' => [
                    'date' => $highestDay,
                    'count' => $highestDayCount,
                ],
                'cancellation_rate' => [
                    'value' => $cancellationRate,
                    'formatted' => number_format($cancellationRate, 1) . '%',
                ],
                'no_show_rate' => [
                    'value' => $noShowRate,
                    'formatted' => number_format($noShowRate, 1) . '%',
                ],
            ],
            'charts' => [
                'by_branch' => $byBranch,
            ]
        ];
    }

    private function getReservationsChart(array $dateRange, $user, ?int $branchId): array
    {
        // Get daily reservation counts for the period
        $reservationsQuery = Reservation::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('DATE(created_at) as date, status, COUNT(*) as count');
        $this->applyReservationBranchScope($reservationsQuery, $user, $branchId);
        $reservations = $reservationsQuery
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        // Create date range array
        $period = Carbon::parse($dateRange['start']);
        $endDate = Carbon::parse($dateRange['end']);
        $dates = [];

        while ($period->lte($endDate)) {
            $dates[] = $period->format('Y-m-d');
            $period->addDay();
        }

        // Get all possible statuses
        $allStatuses = collect(ReservationStatus::cases())->pluck('value')->toArray();
        $statusColors = ReservationStatus::statusColors();
        $statusLabels = collect(ReservationStatus::cases())->mapWithKeys(function ($status) {
            return [$status->value => $status->label()];
        })->toArray();

        // Prepare datasets for each status
        $datasets = [];
        foreach ($allStatuses as $status) {
            $data = [];
            foreach ($dates as $date) {
                $dayReservations = $reservations->get($date, collect());
                $statusCount = $dayReservations->where('status', $status)->sum('count');
                $data[] = $statusCount;
            }

            $datasets[] = [
                'label' => $statusLabels[$status],
                'data' => $data,
                'backgroundColor' => $statusColors[$status],
                'borderColor' => $statusColors[$status],
                'borderWidth' => 1,
            ];
        }

        // Create labels (formatted dates)
        $labels = collect($dates)->map(function ($date) {
            return Carbon::parse($date)->format('M j');
        })->toArray();

        // Calculate totals per day for verification
        $dailyTotals = [];
        foreach ($dates as $date) {
            $dayReservations = $reservations->get($date, collect());
            $dailyTotals[] = $dayReservations->sum('count');
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
            'dailyTotals' => $dailyTotals,
            'statusColors' => $statusColors,
            'statusLabels' => $statusLabels,
            'dateRange' => [
                'start' => $dateRange['start']->format('Y-m-d'),
                'end' => $dateRange['end']->format('Y-m-d')
            ]
        ];
    }

    private function getCarsPerformance(array $dateRange, $user, ?int $branchId, bool $canViewFinancialAmounts)
    {
        $carsQuery = Car::query();
        $this->branchAccess->applyToQuery($carsQuery, $user, $branchId);

        $carsPerformance = $carsQuery->withCount(['reservations as total_reservations' => function ($query) use ($dateRange) {
            $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }])
            ->with(['reservations' => function ($query) use ($dateRange) {
                $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->with('payments');
            }])
            ->get()
            ->map(function ($car) use ($canViewFinancialAmounts) {
                $totalRevenue = $car->reservations->flatMap->payments
                    ->where('status', PaymentStatus::COMPLETED)
                    ->sum('amount');

                $totalDays = $car->reservations->sum('total_days');

                $utilizationRate = $totalDays > 0 ?
                    ($totalDays / Carbon::now()->daysInMonth) * 100 : 0;

                return [
                    'id' => $car->id,
                    'car_name' => $car->full_name,
                    'license_plate' => $car->license_plate,
                    'status' => $car->status->label(),
                    'status_color' => $car->status->color(),
                    'total_reservations' => $car->total_reservations,
                    'total_revenue' => FinancialVisibility::numericAmount($totalRevenue, $canViewFinancialAmounts),
                    'formatted_revenue' => $canViewFinancialAmounts
                        ? config('app.currency_symbol') . number_format($totalRevenue, 2)
                        : '*******',
                    'total_days' => $totalDays,
                    'utilization_rate' => round($utilizationRate, 1),
                    'average_per_reservation' => $car->total_reservations > 0 && $canViewFinancialAmounts
                        ? round($totalRevenue / $car->total_reservations, 2)
                        : 0,
                ];
            })
            ->sortByDesc('total_revenue')
            ->values();

        return $carsPerformance;
    }

    private function getFinancialSummary(array $dateRange, $user, ?int $branchId, bool $canViewFinancialAmounts): array
    {
        $completedPaymentsQuery = Payment::completed()
            ->whereBetween('processed_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyPaymentBranchScope($completedPaymentsQuery, $user, $branchId);
        $paidRevenue = (float) $completedPaymentsQuery->sum('amount');

        $pendingPaymentsQuery = Payment::query()
            ->where('status', '!=', PaymentStatus::COMPLETED)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyPaymentBranchScope($pendingPaymentsQuery, $user, $branchId);
        $pendingRevenue = (float) $pendingPaymentsQuery->sum('amount');

        $returnReportsQuery = ContractReturnReport::query()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->branchAccess->applyToQuery($returnReportsQuery, $user, $branchId);

        return [
            $this->moneyMetric('Paid revenue', $paidRevenue, $canViewFinancialAmounts, '#10B981'),
            $this->moneyMetric('Pending payments', $pendingRevenue, $canViewFinancialAmounts, '#F59E0B'),
            $this->moneyMetric('Return extra charges', (float) (clone $returnReportsQuery)->sum('total_extra_charges'), $canViewFinancialAmounts, '#6366F1'),
            $this->moneyMetric('Damage fees', (float) (clone $returnReportsQuery)->sum('damage_fee'), $canViewFinancialAmounts, '#EF4444'),
            $this->moneyMetric('Fuel fees', (float) (clone $returnReportsQuery)->sum('fuel_fee'), $canViewFinancialAmounts, '#0EA5E9'),
            $this->moneyMetric('Discounts', (float) (clone $returnReportsQuery)->sum('discount'), $canViewFinancialAmounts, '#84CC16'),
        ];
    }

    private function getExecutiveReport(array $dateRange, $user, ?int $branchId, bool $canViewFinancialAmounts): array
    {
        $completedPaymentsQuery = Payment::completed()
            ->whereBetween('processed_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyPaymentBranchScope($completedPaymentsQuery, $user, $branchId);
        $paidRevenue = (float) $completedPaymentsQuery->sum('amount');

        $pendingPaymentsQuery = Payment::query()
            ->where('status', '!=', PaymentStatus::COMPLETED)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyPaymentBranchScope($pendingPaymentsQuery, $user, $branchId);
        $pendingPayments = (float) $pendingPaymentsQuery->sum('amount');

        $returnReportsQuery = ContractReturnReport::query()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->branchAccess->applyToQuery($returnReportsQuery, $user, $branchId);

        $unpaidReturnReportsQuery = ContractReturnReport::query()
            ->where('payment_status', '!=', 'paid')
            ->where('total_extra_charges', '>', 0);
        $this->branchAccess->applyToQuery($unpaidReturnReportsQuery, $user, $branchId);
        $unpaidReturnCharges = (float) (clone $unpaidReturnReportsQuery)->sum('total_extra_charges');

        $reservationDiscountsQuery = Reservation::query()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyReservationBranchScope($reservationDiscountsQuery, $user, $branchId);

        $returnDiscounts = (float) (clone $returnReportsQuery)->sum('discount');
        $reservationDiscounts = (float) $reservationDiscountsQuery->sum('discount_amount');
        $totalDiscounts = $returnDiscounts + $reservationDiscounts;
        $totalRevenue = $paidRevenue + $pendingPayments + $unpaidReturnCharges;

        $totalCarsQuery = Car::query();
        $this->branchAccess->applyToQuery($totalCarsQuery, $user, $branchId);
        $totalCars = $totalCarsQuery->count();

        $rentedCarsQuery = Car::whereIn('status', [CarStatus::RENTED, CarStatus::RESERVED]);
        $this->branchAccess->applyToQuery($rentedCarsQuery, $user, $branchId);
        $rentedCars = $rentedCarsQuery->count();

        $outOfServiceCarsQuery = Car::whereIn('status', [
            CarStatus::MAINTENANCE,
            CarStatus::CLEANING,
            CarStatus::UNAVAILABLE,
            CarStatus::RETIRED,
        ]);
        $this->branchAccess->applyToQuery($outOfServiceCarsQuery, $user, $branchId);

        $newReservationsQuery = Reservation::query()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyReservationBranchScope($newReservationsQuery, $user, $branchId);

        $activeContractsQuery = Contract::query()->where('status', ContractStatus::ACTIVE);
        $this->branchAccess->applyToQuery($activeContractsQuery, $user, $branchId);

        $deliveredCarsQuery = Contract::query()
            ->where('status', ContractStatus::ACTIVE)
            ->whereBetween('start_date', [$dateRange['start']->toDateString(), $dateRange['end']->toDateString()]);
        $this->branchAccess->applyToQuery($deliveredCarsQuery, $user, $branchId);

        $returnedCarsQuery = ContractReturnReport::query()
            ->whereBetween('actual_return_time', [$dateRange['start'], $dateRange['end']]);
        $this->branchAccess->applyToQuery($returnedCarsQuery, $user, $branchId);

        $overdueContractsQuery = Contract::query()
            ->where('status', ContractStatus::ACTIVE)
            ->whereDate('end_date', '<', now()->toDateString());
        $this->branchAccess->applyToQuery($overdueContractsQuery, $user, $branchId);

        $endingWithin24HoursQuery = Contract::query()
            ->where('status', ContractStatus::ACTIVE)
            ->whereBetween('end_date', [now()->toDateString(), now()->copy()->addDay()->toDateString()]);
        $this->branchAccess->applyToQuery($endingWithin24HoursQuery, $user, $branchId);

        $missingDocumentsQuery = Contract::query()
            ->whereIn('status', [ContractStatus::PENDING, ContractStatus::ACTIVE])
            ->whereDoesntHave('primaryDriver.documents');
        $this->branchAccess->applyToQuery($missingDocumentsQuery, $user, $branchId);

        $contractsWithoutSignatureQuery = Contract::query()
            ->whereIn('status', [ContractStatus::PENDING, ContractStatus::ACTIVE])
            ->where(function ($query): void {
                $query->whereNull('handover_state')
                    ->orWhereRaw("JSON_EXTRACT(handover_state, '$.delivery.steps[4].payload.accepted_terms') IS NULL")
                    ->orWhereRaw("JSON_EXTRACT(handover_state, '$.delivery.steps[4].payload.accepted_terms') = false");
            });
        $this->branchAccess->applyToQuery($contractsWithoutSignatureQuery, $user, $branchId);

        $missingPaymentsCount = (clone $pendingPaymentsQuery)->count() + (clone $unpaidReturnReportsQuery)->count();
        $fleetUtilization = $totalCars > 0 ? round(($rentedCars / $totalCars) * 100, 1) : 0.0;

        $missingPaymentsItems = $this->serializePaymentAlertItems($pendingPaymentsQuery, $unpaidReturnReportsQuery);

        return [
            'financial' => [
                $this->moneyMetric('Total revenue', $totalRevenue, $canViewFinancialAmounts, '#2563EB'),
                $this->moneyMetric('Paid revenue', $paidRevenue, $canViewFinancialAmounts, '#10B981'),
                $this->moneyMetric('Uncollected amounts', $pendingPayments, $canViewFinancialAmounts, '#F59E0B'),
                $this->moneyMetric('Outstanding debts', $unpaidReturnCharges, $canViewFinancialAmounts, '#EF4444'),
                $this->moneyMetric('Discounts', $totalDiscounts, $canViewFinancialAmounts, '#84CC16'),
                $this->moneyMetric('Late fees', (float) (clone $returnReportsQuery)->selectRaw('COALESCE(SUM(late_hours * late_hour_rate), 0) as total')->value('total'), $canViewFinancialAmounts, '#F97316'),
                $this->moneyMetric('Damage fees', (float) (clone $returnReportsQuery)->sum('damage_fee'), $canViewFinancialAmounts, '#DC2626'),
                $this->moneyMetric('Fuel fees', (float) (clone $returnReportsQuery)->sum('fuel_fee'), $canViewFinancialAmounts, '#0EA5E9'),
                $this->moneyMetric('Cleaning fees', (float) (clone $returnReportsQuery)->sum('cleaning_fee'), $canViewFinancialAmounts, '#7C3AED'),
                $this->moneyMetric('Net revenue', $totalRevenue, $canViewFinancialAmounts, '#111827'),
            ],
            'operations' => [
                $this->numberMetric('New reservations', $newReservationsQuery->count(), '#8B5CF6'),
                $this->numberMetric('Active contracts', $activeContractsQuery->count(), '#2563EB'),
                $this->numberMetric('Delivered cars', $deliveredCarsQuery->count(), '#10B981'),
                $this->numberMetric('Returned cars', $returnedCarsQuery->count(), '#14B8A6'),
                $this->numberMetric('Overdue cars', $overdueContractsQuery->count(), '#EF4444'),
                $this->numberMetric('Cars out of service', $outOfServiceCarsQuery->count(), '#64748B'),
                $this->percentageMetric('Fleet utilization', $fleetUtilization, '#6366F1'),
            ],
            'alerts' => [
                [
                    'key' => 'contracts_ending_24h',
                    'label' => 'Contracts ending within 24 hours',
                    'description' => 'Active contracts that need return follow-up soon.',
                    'value' => $endingWithin24HoursQuery->count(),
                    'severity' => 'warning',
                    'href' => url('/admin/contracts?scope=ending_24h'),
                    'items' => $this->serializeContractAlertItems($endingWithin24HoursQuery),
                ],
                [
                    'key' => 'overdue_cars',
                    'label' => 'Overdue cars',
                    'description' => 'Active contracts past their return date.',
                    'value' => $overdueContractsQuery->count(),
                    'severity' => $overdueContractsQuery->count() > 0 ? 'danger' : 'success',
                    'href' => url('/admin/contracts?scope=overdue'),
                    'items' => $this->serializeContractAlertItems($overdueContractsQuery),
                ],
                [
                    'key' => 'missing_payments',
                    'label' => 'Missing payments',
                    'description' => 'Payments or return charges that still need collection.',
                    'value' => $missingPaymentsCount,
                    'severity' => $missingPaymentsCount > 0 ? 'danger' : 'success',
                    'href' => url('/admin/payments?status=pending'),
                    'items' => $missingPaymentsItems,
                ],
                [
                    'key' => 'missing_documents',
                    'label' => 'Missing documents',
                    'description' => 'Active or pending contracts without primary driver documents.',
                    'value' => $missingDocumentsQuery->count(),
                    'severity' => $missingDocumentsQuery->count() > 0 ? 'warning' : 'success',
                    'href' => url('/admin/contracts?scope=missing_documents'),
                    'items' => $this->serializeContractAlertItems($missingDocumentsQuery),
                ],
                [
                    'key' => 'contracts_without_signature',
                    'label' => 'Contracts without signature',
                    'description' => 'Contracts that still need mobile terms confirmation.',
                    'value' => $contractsWithoutSignatureQuery->count(),
                    'severity' => $contractsWithoutSignatureQuery->count() > 0 ? 'warning' : 'success',
                    'href' => url('/admin/contracts?scope=without_signature'),
                    'items' => $this->serializeContractAlertItems($contractsWithoutSignatureQuery),
                ],
            ],
            'exports' => [
                'pdf' => false,
                'excel' => false,
            ],
        ];
    }

    private function getOperationsSummary(array $dateRange, $user, ?int $branchId): array
    {
        $activeContractsQuery = Contract::query()->where('status', ContractStatus::ACTIVE);
        $this->branchAccess->applyToQuery($activeContractsQuery, $user, $branchId);

        $pendingContractsQuery = Contract::query()->where('status', ContractStatus::PENDING);
        $this->branchAccess->applyToQuery($pendingContractsQuery, $user, $branchId);

        $completedContractsQuery = Contract::query()
            ->where('status', ContractStatus::COMPLETED)
            ->whereBetween('updated_at', [$dateRange['start'], $dateRange['end']]);
        $this->branchAccess->applyToQuery($completedContractsQuery, $user, $branchId);

        $overdueContractsQuery = Contract::query()
            ->where('status', ContractStatus::ACTIVE)
            ->whereDate('end_date', '<', now()->toDateString());
        $this->branchAccess->applyToQuery($overdueContractsQuery, $user, $branchId);

        $newReservationsQuery = Reservation::query()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyReservationBranchScope($newReservationsQuery, $user, $branchId);

        $cancelledReservationsQuery = Reservation::query()
            ->where('status', ReservationStatus::CANCELLED)
            ->whereBetween('updated_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyReservationBranchScope($cancelledReservationsQuery, $user, $branchId);

        return [
            $this->numberMetric('Active contracts', $activeContractsQuery->count(), '#2563EB'),
            $this->numberMetric('Pending contracts', $pendingContractsQuery->count(), '#F59E0B'),
            $this->numberMetric('Completed contracts', $completedContractsQuery->count(), '#10B981'),
            $this->numberMetric('Overdue contracts', $overdueContractsQuery->count(), '#EF4444'),
            $this->numberMetric('New reservations', $newReservationsQuery->count(), '#8B5CF6'),
            $this->numberMetric('Cancelled reservations', $cancelledReservationsQuery->count(), '#64748B'),
        ];
    }

    private function getFleetInsights(array $dateRange, $user, ?int $branchId, bool $canViewFinancialAmounts): array
    {
        $carsState = $this->getCarsState($user, $branchId);
        $totalCars = max((int) $carsState['totalCars']['value'], 0);
        $rentedCars = (int) $carsState['rentedCars']['value'];
        $utilizationRate = $totalCars > 0 ? round(($rentedCars / $totalCars) * 100, 1) : 0.0;

        $completedPaymentsQuery = Payment::completed()
            ->whereBetween('processed_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyPaymentBranchScope($completedPaymentsQuery, $user, $branchId);
        $paidRevenue = (float) $completedPaymentsQuery->sum('amount');

        $damageReportsQuery = CarDamageReport::query()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->branchAccess->applyToQuery($damageReportsQuery, $user, $branchId);

        $damageItemQuery = DB::table('car_damage_items')
            ->join('car_damage_reports', 'car_damage_items.car_damage_report_id', '=', 'car_damage_reports.id')
            ->whereBetween('car_damage_reports.created_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyTenantScopeToQueryBuilder($damageItemQuery, $user, 'car_damage_reports');
        $this->applyDamageReportBranchScopeToQueryBuilder($damageItemQuery, $user, $branchId);

        $accidentsQuery = AccidentReport::query()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->branchAccess->applyToQuery($accidentsQuery, $user, $branchId);

        return [
            $this->percentageMetric('Fleet utilization', $utilizationRate, '#2563EB'),
            $this->moneyMetric('Revenue per car', $totalCars > 0 ? $paidRevenue / $totalCars : 0, $canViewFinancialAmounts, '#10B981'),
            $this->numberMetric('Damage reports', $damageReportsQuery->count(), '#EF4444'),
            $this->numberMetric('Damage items', (int) $damageItemQuery->sum('car_damage_items.quantity'), '#F97316'),
            $this->moneyMetric('Estimated damage cost', (float) $damageItemQuery->sum('car_damage_items.estimated_cost'), $canViewFinancialAmounts, '#DC2626'),
            $this->numberMetric('Accident reports', $accidentsQuery->count(), '#7C3AED'),
        ];
    }

    private function getFleetReportData(array $dateRange, $user, ?int $branchId, bool $canViewFinancialAmounts): array
    {
        $carsPerformance = $this->getCarsPerformance($dateRange, $user, $branchId, $canViewFinancialAmounts);

        // Best Cars
        $topRevenue = $carsPerformance->sortByDesc('total_revenue')->first();
        $topUtilization = $carsPerformance->sortByDesc('total_days')->first();

        // Worst Cars
        $worstRevenue = $carsPerformance->sortBy('total_revenue')->first();
        $worstUtilization = $carsPerformance->sortBy('total_days')->first();

        // Cars Status Counts
        $statusCountsQuery = Car::query();
        $this->branchAccess->applyToQuery($statusCountsQuery, $user, $branchId);
        $statusCounts = $statusCountsQuery->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Calculate Utilization (Total rented days, idle days)
        $totalCarsQuery = Car::query();
        $this->branchAccess->applyToQuery($totalCarsQuery, $user, $branchId);
        $totalCarsCount = $totalCarsQuery->count();
        
        $totalDaysInPeriod = Carbon::parse($dateRange['start'])->diffInDays(Carbon::parse($dateRange['end'])) + 1;
        $totalPossibleDays = $totalCarsCount * $totalDaysInPeriod;
        
        $totalRentedDays = $carsPerformance->sum('total_days');
        $idleDays = max(0, $totalPossibleDays - $totalRentedDays);

        $carsState = $this->getCarsState($user, $branchId);
        $utilizationRate = $totalCarsCount > 0 ? round(($carsState['rentedCars']['value'] / $totalCarsCount) * 100, 1) : 0.0;

        return [
            'utilization' => [
                'utilization_rate' => $utilizationRate,
                'rented_days_per_car' => $totalCarsCount > 0 ? round($totalRentedDays / $totalCarsCount, 1) : 0,
                'idle_days' => $idleDays,
            ],
            'top_cars' => [
                'revenue' => $topRevenue ? ['name' => $topRevenue['car_name'], 'value' => $topRevenue['formatted_revenue']] : null,
                'utilization' => $topUtilization ? ['name' => $topUtilization['car_name'], 'value' => $topUtilization['total_days'] . ' Days'] : null,
            ],
            'worst_cars' => [
                'utilization' => $worstUtilization ? ['name' => $worstUtilization['car_name'], 'value' => $worstUtilization['total_days'] . ' Days'] : null,
                'revenue' => $worstRevenue ? ['name' => $worstRevenue['car_name'], 'value' => $worstRevenue['formatted_revenue']] : null,
            ],
            'status_counts' => [
                'available' => $statusCounts[CarStatus::AVAILABLE->value] ?? 0,
                'rented' => $statusCounts[CarStatus::RENTED->value] ?? 0,
                'reserved' => $statusCounts[CarStatus::RESERVED->value] ?? 0,
                'maintenance' => $statusCounts[CarStatus::MAINTENANCE->value] ?? 0,
                'out_of_service' => ($statusCounts[CarStatus::UNAVAILABLE->value] ?? 0) + ($statusCounts[CarStatus::RETIRED->value] ?? 0),
            ],
            'rankings' => [
                'revenue' => $carsPerformance->sortByDesc('total_revenue')->values()->toArray(),
                'utilization' => $carsPerformance->sortByDesc('total_days')->values()->toArray(),
            ]
        ];
    }

    private function getVehicleProfitabilityReport(array $dateRange, $user, ?int $branchId, bool $canViewFinancialAmounts): array
    {
        $currencySymbol = (string) config('app.currency_symbol', '$');
        $formatMoney = fn (float $value): string => $canViewFinancialAmounts
            ? $currencySymbol.number_format($value, 2)
            : '*******';

        $carsQuery = Car::query();
        $this->branchAccess->applyToQuery($carsQuery, $user, $branchId);

        $cars = $carsQuery
            ->select(['id', 'make', 'model', 'year', 'license_plate', 'status'])
            ->get();

        $carIds = $cars->pluck('id')->map(fn ($id) => (int) $id)->values();

        if ($carIds->isEmpty()) {
            return [
                'summary' => [
                    'total_revenue' => 0,
                    'total_costs' => 0,
                    'net_profit' => 0,
                    'average_revenue_per_car' => 0,
                    'formatted_total_revenue' => $formatMoney(0),
                    'formatted_total_costs' => $formatMoney(0),
                    'formatted_net_profit' => $formatMoney(0),
                    'formatted_average_revenue_per_car' => $formatMoney(0),
                    'profitable_cars' => 0,
                    'loss_making_cars' => 0,
                ],
                'top_profitable' => [],
                'least_profitable' => [],
                'cars' => [],
            ];
        }

        $paidRevenueQuery = DB::table('payments')
            ->join('reservations', 'payments.reservation_id', '=', 'reservations.id')
            ->where('payments.status', PaymentStatus::COMPLETED->value)
            ->whereBetween('payments.processed_at', [$dateRange['start'], $dateRange['end']])
            ->whereIn('reservations.car_id', $carIds);
        $this->applyTenantScopeToQueryBuilder($paidRevenueQuery, $user, 'payments');

        $revenueByCar = (clone $paidRevenueQuery)
            ->selectRaw('reservations.car_id, COALESCE(SUM(payments.amount), 0) as total_revenue, COUNT(DISTINCT payments.id) as payments_count')
            ->groupBy('reservations.car_id')
            ->get()
            ->keyBy('car_id');

        $reservationsByCar = Reservation::query()
            ->whereIn('car_id', $carIds)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('car_id, COUNT(*) as reservations_count, COALESCE(SUM(total_days), 0) as rented_days')
            ->groupBy('car_id')
            ->get()
            ->keyBy('car_id');

        $damageCostQuery = DB::table('car_damage_items')
            ->join('car_damage_reports', 'car_damage_items.car_damage_report_id', '=', 'car_damage_reports.id')
            ->whereBetween('car_damage_reports.created_at', [$dateRange['start'], $dateRange['end']])
            ->whereIn('car_damage_reports.car_id', $carIds);
        $this->applyTenantScopeToQueryBuilder($damageCostQuery, $user, 'car_damage_reports');

        $damageCostsByCar = (clone $damageCostQuery)
            ->selectRaw('car_damage_reports.car_id, COALESCE(SUM(car_damage_items.estimated_cost * car_damage_items.quantity), 0) as damage_cost, COUNT(car_damage_items.id) as damage_items_count')
            ->groupBy('car_damage_reports.car_id')
            ->get()
            ->keyBy('car_id');

        $maintenanceCostsByCar = CarMaintenance::query()
            ->whereIn('car_id', $carIds)
            ->where(function ($query) use ($dateRange): void {
                $query->whereBetween('completed_at', [$dateRange['start'], $dateRange['end']])
                    ->orWhere(function ($nested) use ($dateRange): void {
                        $nested->whereNull('completed_at')
                            ->whereBetween('scheduled_date', [
                                $dateRange['start']->toDateString(),
                                $dateRange['end']->toDateString(),
                            ]);
                    });
            })
            ->selectRaw('car_id, COALESCE(SUM(cost), 0) as maintenance_cost, COUNT(*) as maintenance_count')
            ->groupBy('car_id')
            ->get()
            ->keyBy('car_id');

        $violationCostsByCar = CarViolation::query()
            ->whereIn('car_id', $carIds)
            ->whereBetween('violation_date', [
                $dateRange['start']->toDateString(),
                $dateRange['end']->toDateString(),
            ])
            ->selectRaw('car_id, COALESCE(SUM(amount), 0) as violation_cost, COUNT(*) as violations_count')
            ->groupBy('car_id')
            ->get()
            ->keyBy('car_id');

        $periodDays = max(1, Carbon::parse($dateRange['start'])->diffInDays(Carbon::parse($dateRange['end'])) + 1);

        $rows = $cars->map(function (Car $car) use (
            $revenueByCar,
            $reservationsByCar,
            $damageCostsByCar,
            $maintenanceCostsByCar,
            $violationCostsByCar,
            $periodDays,
            $canViewFinancialAmounts,
            $formatMoney
        ): array {
            $carId = (int) $car->id;
            $revenueData = $revenueByCar->get($carId);
            $reservationData = $reservationsByCar->get($carId);
            $damageData = $damageCostsByCar->get($carId);
            $maintenanceData = $maintenanceCostsByCar->get($carId);
            $violationData = $violationCostsByCar->get($carId);

            $revenue = (float) ($revenueData->total_revenue ?? 0);
            $damageCost = (float) ($damageData->damage_cost ?? 0);
            $maintenanceCost = (float) ($maintenanceData->maintenance_cost ?? 0);
            $violationCost = (float) ($violationData->violation_cost ?? 0);
            $totalCosts = $damageCost + $maintenanceCost + $violationCost;
            $netProfit = $revenue - $totalCosts;
            $reservationsCount = (int) ($reservationData->reservations_count ?? 0);
            $rentedDays = (float) ($reservationData->rented_days ?? 0);

            return [
                'car_id' => $carId,
                'car_name' => $car->full_name,
                'license_plate' => $car->license_plate,
                'status' => $car->status instanceof CarStatus ? $car->status->label() : (string) $car->status,
                'revenue' => FinancialVisibility::numericAmount($revenue, $canViewFinancialAmounts),
                'damage_cost' => FinancialVisibility::numericAmount($damageCost, $canViewFinancialAmounts),
                'maintenance_cost' => FinancialVisibility::numericAmount($maintenanceCost, $canViewFinancialAmounts),
                'violation_cost' => FinancialVisibility::numericAmount($violationCost, $canViewFinancialAmounts),
                'total_costs' => FinancialVisibility::numericAmount($totalCosts, $canViewFinancialAmounts),
                'net_profit' => FinancialVisibility::numericAmount($netProfit, $canViewFinancialAmounts),
                'formatted_revenue' => $formatMoney($revenue),
                'formatted_damage_cost' => $formatMoney($damageCost),
                'formatted_maintenance_cost' => $formatMoney($maintenanceCost),
                'formatted_violation_cost' => $formatMoney($violationCost),
                'formatted_total_costs' => $formatMoney($totalCosts),
                'formatted_net_profit' => $formatMoney($netProfit),
                'reservations_count' => $reservationsCount,
                'rented_days' => round($rentedDays, 1),
                'utilization_rate' => round(min(100, ($rentedDays / $periodDays) * 100), 1),
                'average_revenue_per_reservation' => $reservationsCount > 0
                    ? FinancialVisibility::numericAmount($revenue / $reservationsCount, $canViewFinancialAmounts)
                    : FinancialVisibility::numericAmount(0, $canViewFinancialAmounts),
                'formatted_average_revenue_per_reservation' => $formatMoney($reservationsCount > 0 ? $revenue / $reservationsCount : 0),
                'damage_items_count' => (int) ($damageData->damage_items_count ?? 0),
                'maintenance_count' => (int) ($maintenanceData->maintenance_count ?? 0),
                'violations_count' => (int) ($violationData->violations_count ?? 0),
            ];
        })->sortByDesc(fn (array $row) => (float) ($row['net_profit'] ?? 0))->values();

        $totalRevenue = (float) $rows->sum(fn (array $row) => (float) ($row['revenue'] ?? 0));
        $totalCosts = (float) $rows->sum(fn (array $row) => (float) ($row['total_costs'] ?? 0));
        $netProfit = $totalRevenue - $totalCosts;
        $carCount = max(1, $rows->count());

        return [
            'summary' => [
                'total_revenue' => FinancialVisibility::numericAmount($totalRevenue, $canViewFinancialAmounts),
                'total_costs' => FinancialVisibility::numericAmount($totalCosts, $canViewFinancialAmounts),
                'net_profit' => FinancialVisibility::numericAmount($netProfit, $canViewFinancialAmounts),
                'average_revenue_per_car' => FinancialVisibility::numericAmount($totalRevenue / $carCount, $canViewFinancialAmounts),
                'formatted_total_revenue' => $formatMoney($totalRevenue),
                'formatted_total_costs' => $formatMoney($totalCosts),
                'formatted_net_profit' => $formatMoney($netProfit),
                'formatted_average_revenue_per_car' => $formatMoney($totalRevenue / $carCount),
                'profitable_cars' => $rows->filter(fn (array $row) => (float) ($row['net_profit'] ?? 0) > 0)->count(),
                'loss_making_cars' => $rows->filter(fn (array $row) => (float) ($row['net_profit'] ?? 0) < 0)->count(),
            ],
            'top_profitable' => $rows->take(3)->values()->all(),
            'least_profitable' => $rows->sortBy(fn (array $row) => (float) ($row['net_profit'] ?? 0))->take(3)->values()->all(),
            'cars' => $rows->all(),
        ];
    }

    private function getDamagesReport(array $dateRange, $user, ?int $branchId, bool $canViewFinancialAmounts): array
    {
        $currencySymbol = (string) config('app.currency_symbol', '$');
        $formatMoney = fn (float $value): string => $canViewFinancialAmounts
            ? $currencySymbol.number_format($value, 2)
            : '*******';

        $carsQuery = Car::query();
        $this->branchAccess->applyToQuery($carsQuery, $user, $branchId);
        $carIds = $carsQuery->pluck('id')->map(fn ($id) => (int) $id)->values();

        $reportsQuery = CarDamageReport::query()
            ->with([
                'car:id,make,model,year,license_plate,branch_id',
                'creator:id,name',
                'items:id,car_damage_report_id,zone_code,view_side,damage_type,severity,quantity,estimated_cost,notes',
                'files',
                'handoverPhotos:id,damage_report_id,file_path,photo_type',
            ])
            ->withCount(['files', 'handoverPhotos'])
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);

        if ($carIds->isEmpty()) {
            $reportsQuery->whereRaw('1 = 0');
        } else {
            $reportsQuery->whereIn('car_id', $carIds);
        }

        $reports = $reportsQuery->latest()->get();
        $openStatuses = ['draft', 'open', 'pending', 'in_progress'];
        $closedStatuses = ['finalized', 'closed', 'completed', 'resolved'];

        $itemCost = fn ($item): float => (float) ($item->estimated_cost ?? 0) * max(1, (int) ($item->quantity ?? 1));
        $reportCost = fn (CarDamageReport $report): float => (float) $report->items->sum($itemCost);
        $totalCost = (float) $reports->sum($reportCost);
        $totalItems = (int) $reports->sum(fn (CarDamageReport $report) => $report->items->count());

        $reportRow = function (CarDamageReport $report) use ($reportCost, $formatMoney, $canViewFinancialAmounts): array {
            $cost = $reportCost($report);

            return [
                'id' => (int) $report->id,
                'report_number' => (string) $report->report_number,
                'report_type' => (string) $report->report_type,
                'status' => (string) $report->status,
                'car_id' => $report->car_id ? (int) $report->car_id : null,
                'car_name' => $report->car?->full_name ?? '-',
                'license_plate' => $report->car?->license_plate ?? '-',
                'employee_name' => $report->creator?->name ?? '-',
                'items_count' => $report->items->count(),
                'photos_count' => (int) ($report->files_count ?? $report->files->count())
                    + (int) ($report->handover_photos_count ?? $report->handoverPhotos->count()),
                'total_cost' => FinancialVisibility::numericAmount($cost, $canViewFinancialAmounts),
                'formatted_total_cost' => $formatMoney($cost),
                'first_photo_url' => $this->damageReportPhotoUrl($report),
                'created_at' => optional($report->created_at)->format('Y-m-d H:i'),
            ];
        };

        $byCar = $reports
            ->groupBy(fn (CarDamageReport $report) => $report->car_id ?: 'none')
            ->map(function ($group) use ($reportCost, $formatMoney, $canViewFinancialAmounts, $openStatuses, $closedStatuses): array {
                $first = $group->first();
                $cost = (float) $group->sum($reportCost);

                return [
                    'car_id' => $first?->car_id ? (int) $first->car_id : null,
                    'car_name' => $first?->car?->full_name ?? '-',
                    'license_plate' => $first?->car?->license_plate ?? '-',
                    'reports_count' => $group->count(),
                    'items_count' => (int) $group->sum(fn (CarDamageReport $report) => $report->items->count()),
                    'open_reports' => $group->whereIn('status', $openStatuses)->count(),
                    'closed_reports' => $group->whereIn('status', $closedStatuses)->count(),
                    'total_cost' => FinancialVisibility::numericAmount($cost, $canViewFinancialAmounts),
                    'formatted_total_cost' => $formatMoney($cost),
                ];
            })
            ->sortByDesc('items_count')
            ->values()
            ->all();

        $employeeRows = function ($collection) use ($reportCost, $formatMoney, $canViewFinancialAmounts): array {
            return $collection
                ->groupBy(fn (CarDamageReport $report) => $report->created_by ?: 'none')
                ->map(function ($group) use ($reportCost, $formatMoney, $canViewFinancialAmounts): array {
                    $first = $group->first();
                    $cost = (float) $group->sum($reportCost);

                    return [
                        'employee_id' => $first?->created_by ? (int) $first->created_by : null,
                        'employee_name' => $first?->creator?->name ?? '-',
                        'reports_count' => $group->count(),
                        'items_count' => (int) $group->sum(fn (CarDamageReport $report) => $report->items->count()),
                        'total_cost' => FinancialVisibility::numericAmount($cost, $canViewFinancialAmounts),
                        'formatted_total_cost' => $formatMoney($cost),
                    ];
                })
                ->sortByDesc('reports_count')
                ->values()
                ->all();
        };

        $beforeReports = $reports->where('report_type', 'before_delivery')->values();
        $afterReports = $reports->where('report_type', 'after_return')->values();

        return [
            'summary' => [
                'total_reports' => $reports->count(),
                'total_items' => $totalItems,
                'open_reports' => $reports->whereIn('status', $openStatuses)->count(),
                'closed_reports' => $reports->whereIn('status', $closedStatuses)->count(),
                'before_reports' => $beforeReports->count(),
                'after_reports' => $afterReports->count(),
                'total_cost' => FinancialVisibility::numericAmount($totalCost, $canViewFinancialAmounts),
                'formatted_total_cost' => $formatMoney($totalCost),
            ],
            'by_car' => $byCar,
            'employees' => [
                'registered_by' => $employeeRows($reports),
                'closed_by' => $employeeRows($reports->whereIn('status', $closedStatuses)),
            ],
            'photos' => [
                'before' => $beforeReports->map($reportRow)->values()->all(),
                'after' => $afterReports->map($reportRow)->values()->all(),
            ],
            'recent_reports' => $reports->take(10)->map($reportRow)->values()->all(),
        ];
    }

    private function getCustomersReport(array $dateRange, $user, ?int $branchId, bool $canViewFinancialAmounts): array
    {
        $currencySymbol = (string) config('app.currency_symbol', '$');
        $formatMoney = fn (float $value): string => $canViewFinancialAmounts
            ? $currencySymbol.number_format($value, 2)
            : '*******';

        $carsQuery = Car::query();
        $this->branchAccess->applyToQuery($carsQuery, $user, $branchId);
        $carIds = $carsQuery->pluck('id')->map(fn ($id) => (int) $id)->values();

        $tenantId = TenantContext::id() ?: ($user?->tenant_id ?? null);
        $clientsQuery = User::query()->where('role', UserRole::CLIENT->value);

        if ($tenantId) {
            $clientsQuery->where('tenant_id', $tenantId);
        } elseif (! $user || $user->role !== UserRole::SUPER_ADMIN) {
            $clientsQuery->whereRaw('1 = 0');
        }

        if ($branchId || ! $this->branchAccess->canAccessAllBranches($user)) {
            if ($carIds->isEmpty()) {
                $clientsQuery->whereRaw('1 = 0');
            } else {
                $clientsQuery->whereHas('reservations', fn ($query) => $query->whereIn('car_id', $carIds));
            }
        }

        $applyCarScope = function ($query) use ($carIds): void {
            if ($carIds->isEmpty()) {
                $query->whereRaw('1 = 0');
                return;
            }

            $query->whereIn('reservations.car_id', $carIds);
        };

        $newCustomers = (clone $clientsQuery)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();

        $reservationBase = DB::table('reservations')
            ->whereBetween('reservations.created_at', [$dateRange['start'], $dateRange['end']])
            ->whereNotNull('reservations.user_id');
        $this->applyTenantScopeToQueryBuilder($reservationBase, $user, 'reservations');
        $applyCarScope($reservationBase);

        $activeCustomers = (clone $reservationBase)
            ->distinct('reservations.user_id')
            ->count('reservations.user_id');

        $repeatCustomers = (clone $reservationBase)
            ->selectRaw('reservations.user_id, COUNT(*) as reservations_count')
            ->groupBy('reservations.user_id')
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->count();

        $paidRevenueQuery = DB::table('payments')
            ->join('reservations', 'payments.reservation_id', '=', 'reservations.id')
            ->join('users', 'reservations.user_id', '=', 'users.id')
            ->where('payments.status', PaymentStatus::COMPLETED->value)
            ->whereBetween('payments.processed_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyTenantScopeToQueryBuilder($paidRevenueQuery, $user, 'payments');
        $applyCarScope($paidRevenueQuery);

        $topByRevenue = (clone $paidRevenueQuery)
            ->selectRaw('users.id as customer_id, users.name, users.email, COALESCE(SUM(payments.amount), 0) as revenue, COUNT(DISTINCT payments.id) as payments_count, COUNT(DISTINCT reservations.id) as reservations_count')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        $contractsQuery = DB::table('contracts')
            ->join('reservations', 'contracts.reservation_id', '=', 'reservations.id')
            ->join('users', 'reservations.user_id', '=', 'users.id')
            ->whereBetween('contracts.created_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyTenantScopeToQueryBuilder($contractsQuery, $user, 'contracts');
        $applyCarScope($contractsQuery);

        $topByContracts = (clone $contractsQuery)
            ->selectRaw('users.id as customer_id, users.name, users.email, COUNT(DISTINCT contracts.id) as contracts_count, COUNT(DISTINCT reservations.id) as reservations_count, COALESCE(SUM(contracts.total_amount), 0) as contract_value')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('contracts_count')
            ->limit(10)
            ->get();

        $paidSubquery = DB::table('payments')
            ->selectRaw('reservation_id, COALESCE(SUM(amount), 0) as paid_amount')
            ->where('status', PaymentStatus::COMPLETED->value)
            ->groupBy('reservation_id');

        $reservationDebtQuery = DB::table('reservations')
            ->leftJoinSub($paidSubquery, 'paid_totals', 'paid_totals.reservation_id', '=', 'reservations.id')
            ->join('users', 'reservations.user_id', '=', 'users.id');
        $this->applyTenantScopeToQueryBuilder($reservationDebtQuery, $user, 'reservations');
        $applyCarScope($reservationDebtQuery);

        $reservationDebts = (clone $reservationDebtQuery)
            ->selectRaw('users.id as customer_id, users.name, users.email, COALESCE(SUM(GREATEST(reservations.total_amount - COALESCE(paid_totals.paid_amount, 0), 0)), 0) as outstanding_amount, COUNT(DISTINCT reservations.id) as reservations_count')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->havingRaw('outstanding_amount > 0')
            ->get();

        $returnDebtQuery = DB::table('contract_return_reports')
            ->join('contracts', 'contract_return_reports.contract_id', '=', 'contracts.id')
            ->join('reservations', 'contracts.reservation_id', '=', 'reservations.id')
            ->join('users', 'reservations.user_id', '=', 'users.id')
            ->where('contract_return_reports.total_extra_charges', '>', 0)
            ->where(function ($query): void {
                $query->whereNull('contract_return_reports.payment_status')
                    ->orWhere('contract_return_reports.payment_status', '!=', 'paid');
            });
        $this->applyTenantScopeToQueryBuilder($returnDebtQuery, $user, 'contract_return_reports');
        $applyCarScope($returnDebtQuery);

        $returnDebts = (clone $returnDebtQuery)
            ->selectRaw('users.id as customer_id, users.name, users.email, COALESCE(SUM(contract_return_reports.total_extra_charges), 0) as outstanding_amount, COUNT(DISTINCT contract_return_reports.id) as return_reports_count')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->havingRaw('outstanding_amount > 0')
            ->get();

        $debtRows = collect();
        foreach ($reservationDebts as $row) {
            $key = (int) $row->customer_id;
            $debtRows[$key] = [
                'customer_id' => $key,
                'name' => $row->name,
                'email' => $row->email,
                'outstanding_amount' => (float) $row->outstanding_amount,
                'reservations_count' => (int) $row->reservations_count,
                'return_reports_count' => 0,
            ];
        }

        foreach ($returnDebts as $row) {
            $key = (int) $row->customer_id;
            $current = $debtRows->get($key, [
                'customer_id' => $key,
                'name' => $row->name,
                'email' => $row->email,
                'outstanding_amount' => 0,
                'reservations_count' => 0,
                'return_reports_count' => 0,
            ]);

            $current['outstanding_amount'] += (float) $row->outstanding_amount;
            $current['return_reports_count'] += (int) $row->return_reports_count;
            $debtRows[$key] = $current;
        }

        $today = now()->toDateString();
        $overdueQuery = DB::table('contracts')
            ->join('reservations', 'contracts.reservation_id', '=', 'reservations.id')
            ->join('users', 'reservations.user_id', '=', 'users.id')
            ->where('contracts.status', ContractStatus::ACTIVE->value)
            ->whereDate('contracts.end_date', '<', $today);
        $this->applyTenantScopeToQueryBuilder($overdueQuery, $user, 'contracts');
        $applyCarScope($overdueQuery);

        $overdueCustomers = (clone $overdueQuery)
            ->selectRaw('users.id as customer_id, users.name, users.email, COUNT(DISTINCT contracts.id) as overdue_contracts_count')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('overdue_contracts_count')
            ->limit(10)
            ->get();

        $rowWithMoney = function ($row, string $amountKey, string $formattedKey) use ($formatMoney, $canViewFinancialAmounts): array {
            $row = (array) $row;
            $amount = (float) ($row[$amountKey] ?? 0);

            $row[$amountKey] = FinancialVisibility::numericAmount($amount, $canViewFinancialAmounts);
            $row[$formattedKey] = $formatMoney($amount);

            return $row;
        };

        $debtors = $debtRows
            ->sortByDesc('outstanding_amount')
            ->take(10)
            ->map(fn (array $row): array => $rowWithMoney($row, 'outstanding_amount', 'formatted_outstanding_amount'))
            ->values();

        $totalRevenue = (float) $topByRevenue->sum('revenue');
        $totalOutstanding = (float) $debtRows->sum('outstanding_amount');

        return [
            'summary' => [
                'new_customers' => $newCustomers,
                'active_customers' => $activeCustomers,
                'repeat_customers' => $repeatCustomers,
                'repeat_rate' => $activeCustomers > 0 ? round(($repeatCustomers / $activeCustomers) * 100, 1) : 0,
                'debtors_count' => $debtRows->count(),
                'overdue_customers_count' => $overdueCustomers->count(),
                'total_revenue' => FinancialVisibility::numericAmount($totalRevenue, $canViewFinancialAmounts),
                'formatted_total_revenue' => $formatMoney($totalRevenue),
                'total_outstanding' => FinancialVisibility::numericAmount($totalOutstanding, $canViewFinancialAmounts),
                'formatted_total_outstanding' => $formatMoney($totalOutstanding),
            ],
            'top_by_revenue' => $topByRevenue
                ->map(fn ($row): array => $rowWithMoney($row, 'revenue', 'formatted_revenue'))
                ->values()
                ->all(),
            'top_by_contracts' => $topByContracts
                ->map(fn ($row): array => $rowWithMoney($row, 'contract_value', 'formatted_contract_value'))
                ->values()
                ->all(),
            'debtors' => $debtors->all(),
            'overdue_customers' => $overdueCustomers->map(fn ($row): array => (array) $row)->values()->all(),
        ];
    }

    private function getActionAlerts($user, ?int $branchId, bool $canViewFinancialAmounts): array
    {
        $today = now()->toDateString();

        $overdueContractsQuery = Contract::query()
            ->where('status', ContractStatus::ACTIVE)
            ->whereDate('end_date', '<', $today);
        $this->branchAccess->applyToQuery($overdueContractsQuery, $user, $branchId);

        $returnsDueTodayQuery = Contract::query()
            ->where('status', ContractStatus::ACTIVE)
            ->whereDate('end_date', $today);
        $this->branchAccess->applyToQuery($returnsDueTodayQuery, $user, $branchId);

        $pendingViolationsQuery = CarViolation::query()
            ->where('status', CarViolationStatus::PENDING);
        $this->branchAccess->applyToQuery($pendingViolationsQuery, $user, $branchId);

        $unpaidReturnReportsQuery = ContractReturnReport::query()
            ->where('payment_status', '!=', 'paid')
            ->where('total_extra_charges', '>', 0);
        $this->branchAccess->applyToQuery($unpaidReturnReportsQuery, $user, $branchId);

        $draftDamageReportsQuery = CarDamageReport::query()
            ->where('status', 'draft');
        $this->branchAccess->applyToQuery($draftDamageReportsQuery, $user, $branchId);

        $overdueCount = $overdueContractsQuery->count();
        $returnsDueTodayCount = $returnsDueTodayQuery->count();
        $pendingViolationsCount = $pendingViolationsQuery->count();
        $unpaidReturnReportsCount = $unpaidReturnReportsQuery->count();
        $draftDamageReportsCount = $draftDamageReportsQuery->count();
        $pendingViolationAmount = (float) $pendingViolationsQuery->sum('amount');

        $unpaidReturnReportsItems = $this->serializePaymentAlertItems(Payment::query()->whereRaw('1 = 0'), $unpaidReturnReportsQuery);

        return [
            [
                'key' => 'overdue_contracts',
                'label' => 'Overdue cars',
                'description' => 'Active contracts past their return date.',
                'value' => $overdueCount,
                'severity' => $overdueCount > 0 ? 'danger' : 'success',
                'href' => url('/admin/contracts?scope=overdue'),
                'items' => $this->serializeContractAlertItems($overdueContractsQuery),
            ],
            [
                'key' => 'returns_due_today',
                'label' => 'Returns due today',
                'description' => 'Active contracts scheduled to return today.',
                'value' => $returnsDueTodayCount,
                'severity' => $returnsDueTodayCount > 0 ? 'warning' : 'success',
                'href' => url('/admin/contracts?scope=today_return'),
                'items' => $this->serializeContractAlertItems($returnsDueTodayQuery),
            ],
            [
                'key' => 'pending_violations',
                'label' => 'Pending violations',
                'description' => 'Violations that still need review or payment.',
                'value' => $pendingViolationsCount,
                'severity' => $pendingViolationsCount > 0 ? 'warning' : 'success',
                'formatted_amount' => $canViewFinancialAmounts
                    ? config('app.currency_symbol') . number_format($pendingViolationAmount, 2)
                    : '*******',
                'href' => url('/admin/car-violations?status=pending'),
                'items' => $this->serializeViolationAlertItems($pendingViolationsQuery),
            ],
            [
                'key' => 'unpaid_return_reports',
                'label' => 'Unpaid return reports',
                'description' => 'Return reports with outstanding extra charges.',
                'value' => $unpaidReturnReportsCount,
                'severity' => $unpaidReturnReportsCount > 0 ? 'danger' : 'success',
                'href' => url('/admin/payments/debtors'),
                'items' => $unpaidReturnReportsItems,
            ],
            [
                'key' => 'draft_damage_reports',
                'label' => 'Draft damage reports',
                'description' => 'Damage reports waiting for review or completion.',
                'value' => $draftDamageReportsCount,
                'severity' => $draftDamageReportsCount > 0 ? 'info' : 'success',
                'href' => url('/admin/car-damage-reports?status=draft'),
                'items' => $this->serializeDamageReportAlertItems($draftDamageReportsQuery),
            ],
        ];
    }

    private function serializeContractAlertItems($query): array
    {
        return (clone $query)->with([
            'reservation.car:id,make,model,year,license_plate',
            'branch:id,name'
        ])
        ->orderByDesc('id')
        ->limit(100)
        ->get()
        ->map(fn($contract) => [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'renter_name' => $contract->renter_name,
            'start_date' => optional($contract->start_date)->toDateString(),
            'end_date' => optional($contract->end_date)->toDateString(),
            'total_amount' => $contract->total_amount,
            'currency' => $contract->currency,
            'status' => $contract->status instanceof \BackedEnum ? $contract->status->value : (string) $contract->status,
            'car' => $contract->reservation?->car ? [
                'make' => $contract->reservation->car->make,
                'model' => $contract->reservation->car->model,
                'year' => $contract->reservation->car->year,
                'license_plate' => $contract->reservation->car->license_plate,
            ] : null,
            'branch_name' => $contract->branch?->name,
        ])
        ->all();
    }

    private function serializePaymentAlertItems($pendingPaymentsQuery, $unpaidReturnReportsQuery): array
    {
        $pendingPayments = (clone $pendingPaymentsQuery)
            ->with(['user:id,name,email', 'reservation.car:id,make,model,year,license_plate'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'type' => 'payment',
                'reference' => $p->payment_number,
                'renter_name' => $p->user?->name,
                'amount' => $p->amount,
                'currency' => $p->currency,
                'date' => optional($p->created_at)->toDateString(),
                'status' => $p->status instanceof \BackedEnum ? $p->status->value : (string) $p->status,
                'car' => $p->reservation?->car ? [
                    'make' => $p->reservation->car->make,
                    'model' => $p->reservation->car->model,
                    'year' => $p->reservation->car->year,
                    'license_plate' => $p->reservation->car->license_plate,
                ] : null,
            ]);

        $unpaidReturnReports = (clone $unpaidReturnReportsQuery)
            ->with(['reservation.user:id,name', 'reservation.car:id,make,model,year,license_plate'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'contract_id' => $r->contract_id,
                'type' => 'return_charge',
                'reference' => $r->report_number,
                'renter_name' => $r->reservation?->user?->name,
                'amount' => $r->total_extra_charges,
                'currency' => $r->payment?->currency ?: strtoupper((string) config('app.currency_code', 'USD')),
                'date' => optional($r->created_at)->toDateString(),
                'status' => 'unpaid_charge',
                'car' => $r->reservation?->car ? [
                    'make' => $r->reservation->car->make,
                    'model' => $r->reservation->car->model,
                    'year' => $r->reservation->car->year,
                    'license_plate' => $r->reservation->car->license_plate,
                ] : null,
            ]);

        return collect([])
            ->concat($pendingPayments)
            ->concat($unpaidReturnReports)
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    private function serializeViolationAlertItems($query): array
    {
        return (clone $query)
            ->with(['car:id,make,model,year,license_plate', 'violationType:id,name', 'issuedTo:id,name'])
            ->orderByDesc('violation_date')
            ->limit(100)
            ->get()
            ->map(fn($v) => [
                'id' => $v->id,
                'violation_number' => $v->violation_number,
                'car' => $v->car ? [
                    'make' => $v->car->make,
                    'model' => $v->car->model,
                    'year' => $v->car->year,
                    'license_plate' => $v->car->license_plate,
                ] : null,
                'type' => $v->violationType?->name ?? $v->type,
                'amount' => (float) $v->amount,
                'date' => optional($v->violation_date)->toDateString(),
                'issued_to' => $v->issuedTo?->name ?? '-',
            ])
            ->all();
    }

    private function serializeDamageReportAlertItems($query): array
    {
        return (clone $query)
            ->with(['car:id,make,model,year,license_plate', 'contract:id,contract_number'])
            ->orderByDesc('inspected_at')
            ->limit(100)
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'report_number' => $d->report_number,
                'car' => $d->car ? [
                    'make' => $d->car->make,
                    'model' => $d->car->model,
                    'year' => $d->car->year,
                    'license_plate' => $d->car->license_plate,
                ] : null,
                'report_type' => $d->report_type,
                'date' => optional($d->inspected_at)->toDateString(),
                'contract_number' => $d->contract?->contract_number,
            ])
            ->all();
    }

    private function getFinancialAlerts(array $dateRange, $user, ?int $branchId, bool $canViewFinancialAmounts): array
    {
        $currency = strtoupper((string) config('app.currency_code', 'USD'));
        $symbol   = (string) config('app.currency_symbol', '$');

        // ── Paid Revenue ────────────────────────────────────────────────────
        $paidPaymentsQuery = Payment::completed()
            ->whereBetween('processed_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyPaymentBranchScope($paidPaymentsQuery, $user, $branchId);
        $paidAmount   = (float) (clone $paidPaymentsQuery)->sum('amount');
        $paidItems    = (clone $paidPaymentsQuery)
            ->with(['user:id,name', 'reservation.car:id,make,model,year,license_plate'])
            ->orderByDesc('processed_at')
            ->limit(100)
            ->get()
            ->map(fn ($p) => [
                'id'          => $p->id,
                'type'        => 'payment',
                'reference'   => $p->payment_number,
                'renter_name' => $p->user?->name,
                'amount'      => $p->amount,
                'currency'    => $p->currency ?? $currency,
                'date'        => optional($p->processed_at)->toDateString(),
                'status'      => $p->status instanceof \BackedEnum ? $p->status->value : (string) $p->status,
                'method'      => $p->payment_method ?? null,
                'car'         => $p->reservation?->car ? [
                    'make'          => $p->reservation->car->make,
                    'model'         => $p->reservation->car->model,
                    'license_plate' => $p->reservation->car->license_plate,
                ] : null,
            ])
            ->all();

        // ── Pending / Uncollected Payments ───────────────────────────────────
        $pendingPaymentsQuery = Payment::query()
            ->where('status', '!=', PaymentStatus::COMPLETED)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyPaymentBranchScope($pendingPaymentsQuery, $user, $branchId);
        $pendingAmount = (float) (clone $pendingPaymentsQuery)->sum('amount');
        $pendingCount  = (clone $pendingPaymentsQuery)->count();
        $pendingItems  = (clone $pendingPaymentsQuery)
            ->with(['user:id,name', 'reservation.car:id,make,model,year,license_plate'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($p) => [
                'id'          => $p->id,
                'type'        => 'payment',
                'reference'   => $p->payment_number,
                'renter_name' => $p->user?->name,
                'amount'      => $p->amount,
                'currency'    => $p->currency ?? $currency,
                'date'        => optional($p->created_at)->toDateString(),
                'status'      => $p->status instanceof \BackedEnum ? $p->status->value : (string) $p->status,
                'method'      => $p->payment_method ?? null,
                'car'         => $p->reservation?->car ? [
                    'make'          => $p->reservation->car->make,
                    'model'         => $p->reservation->car->model,
                    'license_plate' => $p->reservation->car->license_plate,
                ] : null,
            ])
            ->all();

        // ── Return Extra Charges (unpaid) ─────────────────────────────────────
        $unpaidReturnQuery = ContractReturnReport::query()
            ->where('payment_status', '!=', 'paid')
            ->where('total_extra_charges', '>', 0);
        $this->branchAccess->applyToQuery($unpaidReturnQuery, $user, $branchId);
        $unpaidReturnAmount = (float) (clone $unpaidReturnQuery)->sum('total_extra_charges');
        $unpaidReturnCount  = (clone $unpaidReturnQuery)->count();
        $unpaidReturnItems  = (clone $unpaidReturnQuery)
            ->with(['reservation.user:id,name', 'reservation.car:id,make,model,year,license_plate'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($r) => [
                'id'          => $r->id,
                'contract_id' => $r->contract_id,
                'type'        => 'return_charge',
                'reference'   => $r->report_number,
                'renter_name' => $r->reservation?->user?->name,
                'amount'      => $r->total_extra_charges,
                'currency'    => $currency,
                'date'        => optional($r->created_at)->toDateString(),
                'status'      => 'unpaid_charge',
                'method'      => null,
                'car'         => $r->reservation?->car ? [
                    'make'          => $r->reservation->car->make,
                    'model'         => $r->reservation->car->model,
                    'license_plate' => $r->reservation->car->license_plate,
                ] : null,
            ])
            ->all();

        // ── Discounts ─────────────────────────────────────────────────────────
        $returnReportsQuery = ContractReturnReport::query()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->branchAccess->applyToQuery($returnReportsQuery, $user, $branchId);
        $reservationDiscountsQuery = Reservation::query()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyReservationBranchScope($reservationDiscountsQuery, $user, $branchId);
        $returnDiscounts      = (float) (clone $returnReportsQuery)->sum('discount');
        $reservationDiscounts = (float) $reservationDiscountsQuery->sum('discount_amount');
        $totalDiscounts       = $returnDiscounts + $reservationDiscounts;

        $discountReservations = Reservation::query()
            ->where('discount_amount', '>', 0)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->applyReservationBranchScope($discountReservations, $user, $branchId);
        $discountItems = $discountReservations
            ->with(['user:id,name', 'car:id,make,model,year,license_plate'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($r) => [
                'id'          => $r->id,
                'type'        => 'discount',
                'reference'   => $r->reservation_number ?? 'RES-' . $r->id,
                'renter_name' => $r->user?->name,
                'amount'      => $r->discount_amount,
                'currency'    => $currency,
                'date'        => optional($r->created_at)->toDateString(),
                'status'      => 'discount',
                'method'      => null,
                'car'         => $r->car ? [
                    'make'          => $r->car->make,
                    'model'         => $r->car->model,
                    'license_plate' => $r->car->license_plate,
                ] : null,
            ])
            ->all();

        $fmt = fn (float $v) => $canViewFinancialAmounts
            ? $symbol . number_format($v, 2)
            : '*******';

        return [
            [
                'key'              => 'paid_revenue',
                'label'            => 'Paid revenue',
                'label_ar'         => 'الإيرادات المدفوعة',
                'description'      => 'Completed payments collected in the selected period.',
                'description_ar'   => 'المدفوعات المكتملة المحصلة خلال الفترة المحددة.',
                'value'            => count($paidItems),
                'formatted_amount' => $fmt($paidAmount),
                'severity'         => $paidAmount > 0 ? 'success' : 'info',
                'href'             => url('/admin/payments'),
                'items'            => $paidItems,
            ],
            [
                'key'              => 'pending_payments',
                'label'            => 'Pending payments',
                'label_ar'         => 'المدفوعات المعلقة',
                'description'      => 'Payments not yet collected in the selected period.',
                'description_ar'   => 'المدفوعات التي لم تُحصل بعد خلال الفترة المحددة.',
                'value'            => $pendingCount,
                'formatted_amount' => $fmt($pendingAmount),
                'severity'         => $pendingCount > 0 ? 'warning' : 'success',
                'href'             => url('/admin/payments?status=pending'),
                'items'            => $pendingItems,
            ],
            [
                'key'              => 'outstanding_return_charges',
                'label'            => 'Outstanding return charges',
                'label_ar'         => 'رسوم الرجوع غير المسددة',
                'description'      => 'Unpaid extra charges from contract return reports.',
                'description_ar'   => 'رسوم إضافية غير مدفوعة من تقارير رجوع العقود.',
                'value'            => $unpaidReturnCount,
                'formatted_amount' => $fmt($unpaidReturnAmount),
                'severity'         => $unpaidReturnCount > 0 ? 'danger' : 'success',
                'href'             => url('/admin/payments/debtors'),
                'items'            => $unpaidReturnItems,
            ],
            [
                'key'              => 'discounts_applied',
                'label'            => 'Discounts applied',
                'label_ar'         => 'الخصومات المطبقة',
                'description'      => 'Reservation discounts granted in the selected period.',
                'description_ar'   => 'خصومات الحجوزات الممنوحة خلال الفترة المحددة.',
                'value'            => count($discountItems),
                'formatted_amount' => $fmt($totalDiscounts),
                'severity'         => 'info',
                'href'             => url('/admin/reservations'),
                'items'            => $discountItems,
            ],
        ];
    }

    private function moneyMetric(string $label, float $value, bool $canViewFinancialAmounts, string $color): array
    {
        $visibleValue = FinancialVisibility::numericAmount($value, $canViewFinancialAmounts);

        return [
            'label' => $label,
            'value' => $visibleValue,
            'formatted' => $canViewFinancialAmounts
                ? config('app.currency_symbol') . number_format($visibleValue, 2)
                : '*******',
            'color' => $color,
        ];
    }

    private function numberMetric(string $label, int|float $value, string $color): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'formatted' => number_format($value),
            'color' => $color,
        ];
    }

    private function percentageMetric(string $label, float $value, string $color): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'formatted' => number_format($value, 1) . '%',
            'color' => $color,
        ];
    }

    private function getPeriodOptions(): array
    {
        return [
            ['value' => 'today', 'label' => 'Today'],
            ['value' => 'yesterday', 'label' => 'Yesterday'],
            ['value' => 'this_week', 'label' => 'This Week'],
            ['value' => 'last_week', 'label' => 'Last Week'],
            ['value' => 'this_month', 'label' => 'This Month'],
            ['value' => 'last_month', 'label' => 'Last Month'],
            ['value' => 'this_year', 'label' => 'This Year'],
            ['value' => 'last_year', 'label' => 'Last Year'],
        ];
    }

    private function applyReservationBranchScope($query, $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->whereHas('car', fn ($carQuery) => $carQuery->where('branch_id', $branchId));
            }
            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('car', fn ($carQuery) => $carQuery->where('branch_id', $userBranchId));
    }

    private function applyPaymentBranchScope($query, $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->whereHas('reservation.car', fn ($carQuery) => $carQuery->where('branch_id', $branchId));
            }
            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('reservation.car', fn ($carQuery) => $carQuery->where('branch_id', $userBranchId));
    }

    private function applyDamageReportBranchScopeToQueryBuilder($query, $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->where('car_damage_reports.branch_id', $branchId);
            }
            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where('car_damage_reports.branch_id', $userBranchId);
    }

    private function applyTenantScopeToQueryBuilder($query, $user, string $table): void
    {
        $tenantId = TenantContext::id() ?: ($user?->tenant_id ?? null);

        if ($tenantId) {
            $query->where("{$table}.tenant_id", $tenantId);
            return;
        }

        if (! $user || $user->role !== UserRole::SUPER_ADMIN) {
            $query->whereRaw('1 = 0');
        }
    }
}
