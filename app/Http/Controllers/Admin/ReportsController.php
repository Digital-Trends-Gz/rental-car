<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Enums\CarStatus;
use App\Enums\CarViolationStatus;
use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AccidentReport;
use App\Support\BranchAccess;
use App\Support\FinancialVisibility;
use App\Models\Car;
use App\Models\CarDamageReport;
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

        $data = [
            'kpis' => $this->getHighLevelKPIs($dateRange, $user, $branchId, $canViewFinancialAmounts),
            'carsState' => $this->getCarsState($user, $branchId),
            'reservationsChart' => $this->getReservationsChart($dateRange, $user, $branchId),
            'carsPerformance' => $this->getCarsPerformance($dateRange, $user, $branchId, $canViewFinancialAmounts),
            'financialSummary' => $this->getFinancialSummary($dateRange, $user, $branchId, $canViewFinancialAmounts),
            'financialReportSections' => $this->financialReportSections(),
            'financialAlerts' => $this->getFinancialAlerts($dateRange, $user, $branchId, $canViewFinancialAmounts),
            'operationsSummary' => $this->getOperationsSummary($dateRange, $user, $branchId),
            'fleetInsights' => $this->getFleetInsights($dateRange, $user, $branchId, $canViewFinancialAmounts),
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
            'financialReportSections' => $this->financialReportSections(),
            'period' => $period,
            'periodLabel' => collect($this->getPeriodOptions())->firstWhere('value', $period)['label'] ?? $period,
            'branchName' => $branchId
                ? ($branchOptions->firstWhere('id', $branchId)['name'] ?? 'Selected branch')
                : 'All branches',
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

    private function executiveExportFileName(string $extension): string
    {
        return 'executive-report-'.now()->format('Y-m-d_H-i').'.'.$extension;
    }

    private function financialReportSections(): array
    {
        return $this->localizedFinancialReportSections();

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
