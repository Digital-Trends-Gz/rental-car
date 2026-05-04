<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CarStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Core\ReservationSettings;
use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\CarDamageReport;
use App\Models\Contract;
use App\Models\ContractReturnReport;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use App\Support\BranchAccess;
use App\Support\PdfRuntime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf as SpatiePdf;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Throwable;

class ContractReturnReportsController extends Controller
{
    public function __construct(private BranchAccess $branchAccess)
    {
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $contract = $this->findContractFromRequest($request);
        abort_unless($this->canAccessContract($contract, $request->user()), 403);

        $contract->loadMissing([
            'reservation.user:id,name,email',
            'reservation.car:id,make,model,year,license_plate,mileage,branch_id',
            'branch:id,name',
            'damageReports.items',
            'returnStatusReport.payment',
        ]);

        $existingReport = $contract->returnStatusReport;
        if (!$existingReport && $contract->status->value !== 'active') {
            return redirect()
                ->to(url('/admin/contracts/'.$contract->getKey()))
                ->withErrors([
                    'return_report' => 'The vehicle must be delivered before creating a return status report.',
                ]);
        }

        $settings = $this->reservationSettings((int) $contract->tenant_id);
        $afterReturnDamageReports = $contract->damageReports
            ->filter(fn (CarDamageReport $report) => $report->report_type === 'after_return')
            ->values();

        return Inertia::render('Admin/Contracts/ReturnStatusReport', [
            'contract' => [
                'id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'status' => $contract->status,
                'renter_name' => $contract->renter_name,
                'car_details' => $contract->car_details,
                'plate_number' => $contract->plate_number,
                'start_date' => optional($contract->start_date)->toDateString(),
                'end_date' => optional($contract->end_date)->toDateString(),
                'vehicle_odometer' => $contract->vehicle_odometer,
                'vehicle_fuel_level' => $contract->vehicle_fuel_level,
                'vehicle_condition_before' => $contract->vehicle_condition_before,
                'vehicle_condition_after' => $contract->vehicle_condition_after,
                'daily_rate' => $contract->price_per_day ?? $contract->reservation?->daily_rate,
                'allowed_km_per_day' => $contract->allowed_km_per_day,
                'allowed_km_per_week' => $contract->allowed_km_per_week,
                'allowed_km_per_month' => $contract->allowed_km_per_month,
                'reservation' => $contract->reservation ? [
                    'id' => $contract->reservation->id,
                    'reservation_number' => $contract->reservation->reservation_number,
                    'status' => $contract->reservation->status instanceof ReservationStatus
                        ? $contract->reservation->status->value
                        : (string) $contract->reservation->status,
                    'status_label' => $contract->reservation->status instanceof ReservationStatus
                        ? $contract->reservation->status->label()
                        : ucfirst(str_replace('_', ' ', (string) $contract->reservation->status)),
                    'status_color' => $contract->reservation->status instanceof ReservationStatus
                        ? $contract->reservation->status->color()
                        : '#6B7280',
                    'user_name' => $contract->reservation->user?->name,
                    'car' => $contract->reservation->car
                        ? "{$contract->reservation->car->year} {$contract->reservation->car->make} {$contract->reservation->car->model}"
                        : null,
                    'pickup_time' => optional($contract->reservation->pickup_time)?->format('H:i'),
                    'return_time' => optional($contract->reservation->return_time)?->format('H:i'),
                    'return_location' => $contract->reservation->return_location,
                    'return_location_fee' => $contract->reservation->return_location_fee,
                ] : null,
                'branch_name' => $contract->branch?->name,
                'damage_reports' => $afterReturnDamageReports->map(function (CarDamageReport $report) {
                    $afterReturnItems = $this->afterReturnDamageItems($report);

                    return [
                        'id' => $report->id,
                        'report_number' => $report->report_number,
                        'report_type' => $report->report_type,
                        'status' => $report->status,
                        'inspected_at' => optional($report->inspected_at)?->format('Y-m-d H:i'),
                        'items_count' => $afterReturnItems->count(),
                        'total_estimated_cost' => (float) $afterReturnItems->sum('estimated_cost'),
                        'after_return_items_count' => $afterReturnItems->count(),
                        'after_return_total_estimated_cost' => (float) $afterReturnItems->sum('estimated_cost'),
                        'summary' => $report->summary,
                        'edit_url' => url('/admin/car-damage-reports/'.$report->getKey().'/edit'),
                    ];
                })->values()->all(),
            ],
            'report' => $this->serializeReport($existingReport),
            'settings' => [
                'return_time_policy' => $settings['return_time_policy'] ?? [],
                'pickup_return_locations' => $settings['pickup_return_locations'] ?? [],
                'kilometer_pricing' => $settings['kilometer_pricing'] ?? [],
                'fuel_pricing' => $settings['fuel_pricing'] ?? [],
                'late_return' => $settings['late_return'] ?? [],
                'cleaning_fee' => $settings['cleaning_fee'] ?? 0,
            ],
            'defaults' => $this->resolveDefaultCharges($contract, $settings),
            'options' => [
                'fuelLevels' => [
                    ['value' => 'empty', 'label' => 'Empty'],
                    ['value' => 'quarter', 'label' => '1/4 Tank'],
                    ['value' => 'half', 'label' => '1/2 Tank'],
                    ['value' => 'three_quarters', 'label' => '3/4 Tank'],
                    ['value' => 'full', 'label' => 'Full'],
                ],
                'vehicleConditions' => [
                    ['value' => 'clean', 'label' => 'Clean'],
                    ['value' => 'not_clean', 'label' => 'Not Clean'],
                ],
            ],
            'actions' => [
                'index' => route('admin.contracts.index', ['subdomain' => $request->route('subdomain')]),
                'store' => url('/admin/contracts/'.$contract->getKey().'/return-status-report'),
                'print' => $existingReport ? route('admin.contracts.return-report.pdf', [
                    'subdomain' => $request->route('subdomain'),
                    'contractId' => $contract->id,
                ]) : null,
            ],
            'permissions' => [
                'can_edit_return_report' => $this->canEditReturnReport($request->user()),
            ],
        ]);
    }

    public function pdf(Request $request, ...$args)
    {
        $routeContractId = $request->route('contractId') ?? $request->route('contract') ?? null;
        $routeSubdomain = $request->route('subdomain') ?? null;
        $contractId = (int) ($routeContractId ?? collect($args)->first(fn ($value) => is_numeric($value)) ?? 0);
        $subdomain = trim((string) ($routeSubdomain ?? $request->query('subdomain', collect($args)->first(fn ($value) => is_string($value) && !is_numeric($value), ''))));

        if ($contractId <= 0) {
            abort(404);
        }

        $contract = Contract::query()
            ->withoutGlobalScope('tenant')
            ->findOrFail($contractId);

        $tenant = $subdomain !== ''
            ? Tenant::query()->withoutGlobalScopes()->where('slug', $subdomain)->firstOrFail()
            : Tenant::query()->withoutGlobalScopes()->findOrFail($contract->tenant_id);
        abort_unless((int) $contract->tenant_id === (int) $tenant->id, 404);

        if ($tenant) {
            TenantContext::set($tenant);
        }

        abort_unless($this->canAccessContract($contract, $request->user()), 403);

        $contract->loadMissing([
            'reservation.user:id,name,email',
            'reservation.car:id,make,model,year,license_plate,mileage',
            'branch:id,name',
            'tenant.siteSetting.files',
            'damageReports.items',
            'returnStatusReport.payment',
        ]);

        $supportedLocales = array_values((array) config('app.available_locales', ['en', 'ar']));
        $fallbackLocale = (string) config('app.fallback_locale', config('app.locale', 'en'));
        $requestedLocale = strtolower((string) $request->query('lang', app()->getLocale()));
        $locale = in_array($requestedLocale, $supportedLocales, true) ? $requestedLocale : $fallbackLocale;

        app()->setLocale($locale);
        $report = $contract->returnStatusReport;
        abort_unless($report, 404);

        $settings = $this->reservationSettings((int) $contract->tenant_id);
        $branding = $this->pdfBranding($contract->tenant);
        $companyName = $branding['name'];
        $companyLogo = $branding['logo'];
        $siteSettings = $contract->tenant?->siteSetting ? \App\Models\TenantSiteSetting::forTenant($contract->tenant) : [];
        $contactPhone = data_get($siteSettings, 'contact.phone') ?? $contract->branch?->phone_1 ?? $contract->branch?->phone ?? '-';
        $contactWhatsapp = data_get($siteSettings, 'contact.whatsapp') ?? '-';
        $pdfHeader = data_get($siteSettings, 'pdf_header', []);
        $headerCompanyNameEn = data_get($pdfHeader, 'company_name.en') ?: $companyName;
        $headerCompanyNameAr = data_get($pdfHeader, 'company_name.ar') ?: $headerCompanyNameEn;
        $headerCrNumber = data_get($pdfHeader, 'cr_number') ?? '';
        $headerPoBox = data_get($pdfHeader, 'po_box') ?? '';
        $headerPc = data_get($pdfHeader, 'pc') ?? '';
        $headerCountryEn = data_get($pdfHeader, 'country.en') ?: 'Sultanate of Oman';
        $headerCountryAr = data_get($pdfHeader, 'country.ar') ?: 'سلطنة عمان';
        $headerGsm1 = data_get($pdfHeader, 'gsm_1') ?: $contactWhatsapp;
        $headerGsm2 = data_get($pdfHeader, 'gsm_2') ?: $contactPhone;
        $headerGsm3 = data_get($pdfHeader, 'gsm_3') ?: '';
        $headerRegistryLabelEn = data_get($pdfHeader, 'registry_label.en') ?: 'No.';
        $headerRegistryLabelAr = data_get($pdfHeader, 'registry_label.ar') ?: 'رقم';
        $currencySymbol = config('app.currency_symbol', '$');
        $extraKilometerCharges = $this->normalizeMoney((float) $report->extra_kilometers * (float) $report->kilometer_rate);
        $lateFee = $this->calculateLateFee(
            (float) $report->late_hours,
            $settings,
            $this->normalizeMoney($contract->price_per_day ?? $contract->reservation?->daily_rate ?? 0),
            $this->normalizeMoney($report->late_hour_rate ?? 0)
        );
        $damageFee = $this->normalizeMoney((float) $report->damage_fee);
        $maintenanceFee = $this->normalizeMoney((float) $report->maintenance_fee);
        $otherFee = $this->normalizeMoney((float) $report->other_fee);

        PdfRuntime::ensureDompdfDirectories();
        File::ensureDirectoryExists(storage_path('app/pdf-temp'));

        $pdfFallback = !PdfRuntime::canUseBrowsershot();

        $viewData = [
            'contract' => $contract,
            'report' => $report,
            'settings' => $settings,
            'companyName' => $companyName,
            'companyLogo' => $pdfFallback ? null : $companyLogo,
            'pdfFallback' => $pdfFallback,
            'headerCompanyNameEn' => $headerCompanyNameEn,
            'headerCompanyNameAr' => $headerCompanyNameAr,
            'headerCrNumber' => $headerCrNumber,
            'headerPoBox' => $headerPoBox,
            'headerPc' => $headerPc,
            'headerCountryEn' => $headerCountryEn,
            'headerCountryAr' => $headerCountryAr,
            'headerGsm1' => $headerGsm1,
            'headerGsm2' => $headerGsm2,
            'headerGsm3' => $headerGsm3,
            'headerRegistryLabelEn' => $headerRegistryLabelEn,
            'headerRegistryLabelAr' => $headerRegistryLabelAr,
            'currencySymbol' => $currencySymbol,
            'locale' => $locale,
            'extraKilometerCharges' => $extraKilometerCharges,
            'lateFee' => $lateFee,
            'damageFee' => $damageFee,
            'maintenanceFee' => $maintenanceFee,
            'otherFee' => $otherFee,
        ];

        $downloadName = $report->report_number.'-'.$locale.'-invoice.pdf';
        $tempPath = storage_path('app/pdf-temp/'.$downloadName);

        if (!$pdfFallback) {
            try {
                SpatiePdf::view('admin.contracts.return-report-invoice', $viewData)
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
                            ->waitUntilNetworkIdle(false)
                            ->timeout(120)
                            ->newHeadless();
                    })
                    ->save($tempPath);

                return response(file_get_contents($tempPath), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.$downloadName.'"',
                ]);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $dompdf = DomPdf::loadView('admin.contracts.return-report-invoice', $viewData)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('fontDir', PdfRuntime::dompdfFontDirectory())
            ->setOption('fontCache', PdfRuntime::dompdfFontDirectory())
            ->setOption('tempDir', PdfRuntime::dompdfTempDirectory())
            ->setOption('isRemoteEnabled', true)
            ->setPaper('a4', 'portrait');

        file_put_contents($tempPath, $dompdf->output());

        return response(file_get_contents($tempPath), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$downloadName.'"',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $contract = $this->findContractFromRequest($request);
        abort_unless($this->canAccessContract($contract, $request->user()), 403);

        $existingReport = $contract->returnStatusReport()->first();

        if ($this->reportIsPaid($existingReport)) {
            throw ValidationException::withMessages([
                'payment_status' => 'This return report is paid and locked.',
            ]);
        }

        abort_unless($this->canEditReturnReport($request->user()), 403);

        $validated = $this->validatePayload($request, $contract);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $tenantId = (int) (TenantContext::id() ?? 0);
        if ($tenantId <= 0) {
            abort(404);
        }

        $contract->loadMissing(['reservation', 'damageReports.items']);
        $settings = $this->reservationSettings($tenantId);
        $damageReport = null;
        $paymentStatus = (string) ($validated['payment_status'] ?? 'not_paid');

        if (!empty($validated['damage_report_id'])) {
            $damageReport = $contract->damageReports
                ->first(fn (CarDamageReport $report) => $report->id === (int) $validated['damage_report_id'] && $report->report_type === 'after_return');

            if (!$damageReport) {
                throw ValidationException::withMessages([
                    'damage_report_id' => 'The selected damage report must be an after-return damage report.',
                ]);
            }
        }

        $returnOdometer = isset($validated['return_odometer'])
            ? (int) $validated['return_odometer']
            : (int) ($contract->return_odometer ?? 0);

        $this->ensureReturnOdometerIsNotLowerThanCheckout($contract, $returnOdometer);

        $autoExtraKilometers = $this->resolveExtraKilometers($contract, $settings, $validated['actual_return_time'] ?? null, $returnOdometer);
        $autoKilometerRate = ReservationSettings::resolveKilometerRate($settings, $autoExtraKilometers);
        $autoCleaningFee = ReservationSettings::resolveCleaningFee($settings);
        $autoFuelFee = ReservationSettings::resolveFuelFeeByLoss(
            $settings,
            $contract->vehicle_fuel_level,
            $validated['return_fuel_level'] ?? null
        );
        $autoFuelCredit = ReservationSettings::resolveFuelCreditByGain(
            $settings,
            $contract->vehicle_fuel_level,
            $validated['return_fuel_level'] ?? null
        );
        $autoLateHours = $this->resolveLateHours($contract, $settings, $validated['actual_return_time'] ?? null);
        $autoLateHourRate = ReservationSettings::resolveLateHourlyFee($settings);
        $autoDamageFee = 0.0;
        if ($damageReport) {
            $autoDamageFee = $this->normalizeMoney($this->afterReturnDamageItems($damageReport)->sum('estimated_cost'));
        }

        $extraKilometers = $this->normalizeMoney($validated['extra_kilometers'] ?? $autoExtraKilometers);
        $kilometerRate = $this->normalizeMoney($validated['kilometer_rate'] ?? $autoKilometerRate);
        $cleaningFee = $this->normalizeMoney($validated['cleaning_fee'] ?? $autoCleaningFee);
        $fuelFee = $this->normalizeMoney($validated['fuel_fee'] ?? $autoFuelFee);
        $fuelCredit = $this->normalizeMoney($validated['fuel_credit'] ?? $autoFuelCredit);
        $lateHours = $this->normalizeMoney($validated['late_hours'] ?? $autoLateHours);
        $lateHourRate = $this->normalizeMoney($validated['late_hour_rate'] ?? $autoLateHourRate);
        $damageFee = $this->normalizeMoney($validated['damage_fee'] ?? $autoDamageFee);
        $maintenanceFee = $this->normalizeMoney($validated['maintenance_fee'] ?? 0);
        $otherFee = $this->normalizeMoney($validated['other_fee'] ?? 0);

        $lateFee = $this->calculateLateFee(
            $lateHours,
            $settings,
            $this->normalizeMoney($contract->price_per_day ?? $contract->reservation?->daily_rate ?? 0),
            $lateHourRate
        );
        $subtotalBeforeDiscount = $this->normalizeMoney(
            $extraKilometers * $kilometerRate
            + $cleaningFee
            + $fuelFee
            - $fuelCredit
            + $lateFee
            + $damageFee
            + $maintenanceFee
            + $otherFee
        );
        $discount = min(
            $this->normalizeMoney($validated['discount'] ?? 0),
            max(0.0, $subtotalBeforeDiscount)
        );
        $totalExtraCharges = $this->normalizeMoney(
            $subtotalBeforeDiscount - $discount
        );

        $report = DB::transaction(function () use (
            $request,
            $validated,
            $contract,
            $damageReport,
            $paymentStatus,
            $extraKilometers,
            $kilometerRate,
            $cleaningFee,
            $fuelFee,
            $fuelCredit,
            $lateHours,
            $lateHourRate,
            $damageFee,
            $maintenanceFee,
            $otherFee,
            $discount,
            $lateFee,
            $totalExtraCharges,
            $existingReport,
            $returnOdometer
        ) {
            $report = ContractReturnReport::query()->updateOrCreate(
                ['tenant_id' => $contract->tenant_id, 'contract_id' => $contract->id],
                [
                    'branch_id' => $contract->branch_id ?: $contract->reservation?->car?->branch_id,
                    'reservation_id' => $contract->reservation_id,
                    'car_id' => $contract->reservation?->car?->id,
                    'damage_report_id' => $damageReport?->id,
                    'created_by' => $request->user()?->id,
                    'report_number' => $existingReport?->report_number ?: $this->generateReportNumber(),
                    'status' => 'finalized',
                    'payment_status' => $paymentStatus,
                    'actual_return_time' => $validated['actual_return_time'] ?? null,
                    'return_location' => $validated['return_location'] ?? null,
                    'return_odometer' => $validated['return_odometer'] ?? null,
                    'return_fuel_level' => $validated['return_fuel_level'] ?? null,
                    'vehicle_condition_after' => $validated['vehicle_condition_after'] ?? null,
                    'extra_kilometers' => $extraKilometers,
                    'kilometer_rate' => $kilometerRate,
                    'cleaning_fee' => $cleaningFee,
                    'fuel_fee' => $fuelFee,
                    'fuel_credit' => $fuelCredit,
                    'late_hours' => $lateHours,
                    'late_hour_rate' => $lateHourRate,
                    'damage_fee' => $damageFee,
                    'maintenance_fee' => $maintenanceFee,
                    'other_fee' => $otherFee,
                    'discount' => $discount,
                    'total_extra_charges' => $totalExtraCharges,
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            if ($totalExtraCharges > 0) {
                $payment = $report->payment_id
                    ? Payment::query()->where('tenant_id', $contract->tenant_id)->find($report->payment_id)
                    : null;

                if (!$payment) {
                    $payment = new Payment();
                    $payment->tenant_id = $contract->tenant_id;
                    $payment->reservation_id = $contract->reservation_id;
                    $payment->user_id = $contract->reservation?->user_id;
                }

                $payment->amount = $totalExtraCharges;
                $payment->currency = strtoupper((string) ($contract->currency ?: config('app.currency_code', 'USD')));
                $payment->payment_method = PaymentMethod::CASH;
                $payment->status = $paymentStatus === 'paid'
                    ? PaymentStatus::COMPLETED
                    : PaymentStatus::PENDING;
                $payment->processed_at = $paymentStatus === 'paid' ? now() : null;
                $payment->notes = trim(sprintf(
                    'Return status report %s for contract %s%s.',
                    $report->report_number,
                    $contract->contract_number,
                    $paymentStatus === 'paid' ? '' : ' pending settlement'
                ));
                $payment->save();

                $report->payment_id = $payment->id;
                $report->save();
            } elseif ($report->payment_id) {
                $payment = Payment::query()->where('tenant_id', $contract->tenant_id)->find($report->payment_id);

                if ($payment && $payment->status !== PaymentStatus::COMPLETED) {
                    $payment->status = PaymentStatus::CANCELLED;
                    $payment->processed_at = null;
                    $payment->notes = trim(sprintf(
                        'Return status report %s no longer has payable extra charges.',
                        $report->report_number
                    ));
                    $payment->save();
                }

                $report->payment_id = null;
                $report->save();
            }

            $contract->forceFill([
                'actual_return_time' => $validated['actual_return_time'] ?? $contract->actual_return_time,
                'return_odometer' => $validated['return_odometer'] ?? $contract->return_odometer,
                'return_fuel_level' => $validated['return_fuel_level'] ?? $contract->return_fuel_level,
                'vehicle_condition_after' => $validated['vehicle_condition_after'] ?? $contract->vehicle_condition_after,
                'status' => 'completed',
            ])->save();

            if ($contract->reservation) {
                $contract->reservation->update([
                    'status' => ReservationStatus::COMPLETED,
                ]);

                $car = $contract->reservation->car;
                if ($car) {
                    $updates = [
                        'status' => CarStatus::AVAILABLE->value,
                    ];

                    if ($returnOdometer > (int) ($car->mileage ?? 0)) {
                        $updates['mileage'] = $returnOdometer;
                    }

                    $car->forceFill($updates)->save();
                }
            }

            return $report;
        });

        return redirect()
            ->route('admin.contracts.return-report', [
                'subdomain' => $request->route('subdomain'),
                'contract' => $contract->id,
            ])
            ->with('success', 'Return status report saved successfully.');
    }

    private function validatePayload(Request $request, Contract $contract): array
    {
        return $request->validate([
            'actual_return_time' => ['required', 'date'],
            'return_location' => ['nullable', 'string', 'max:255'],
            'return_odometer' => ['nullable', 'integer', 'min:0'],
            'return_fuel_level' => ['nullable', Rule::in(['empty', 'quarter', 'half', 'three_quarters', 'full'])],
            'vehicle_condition_after' => ['nullable', Rule::in(['clean', 'not_clean'])],
            'payment_status' => ['nullable', Rule::in(['paid', 'not_paid'])],
            'damage_report_id' => ['nullable', 'integer'],
            'extra_kilometers' => ['nullable', 'numeric', 'min:0'],
            'kilometer_rate' => ['nullable', 'numeric', 'min:0'],
            'cleaning_fee' => ['nullable', 'numeric', 'min:0'],
            'fuel_fee' => ['nullable', 'numeric', 'min:0'],
            'fuel_credit' => ['nullable', 'numeric', 'min:0'],
            'late_hours' => ['nullable', 'numeric', 'min:0'],
            'late_hour_rate' => ['nullable', 'numeric', 'min:0'],
            'damage_fee' => ['nullable', 'numeric', 'min:0'],
            'maintenance_fee' => ['nullable', 'numeric', 'min:0'],
            'other_fee' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function ensureReturnOdometerIsNotLowerThanCheckout(Contract $contract, int $returnOdometer): void
    {
        $checkoutOdometer = (int) ($contract->vehicle_odometer ?? 0);

        if ($returnOdometer < $checkoutOdometer) {
            throw ValidationException::withMessages([
                'return_odometer' => "Return odometer cannot be lower than the contract checkout odometer ({$checkoutOdometer}).",
            ]);
        }
    }

    private function findContractFromRequest(Request $request): Contract
    {
        $contractId = (int) $request->route('contract');
        if ($contractId <= 0) {
            abort(404);
        }

        return Contract::query()
            ->with([
                'reservation.user:id,name,email',
                'reservation.car:id,make,model,year,license_plate,mileage,branch_id',
                'branch:id,name',
                'damageReports.items',
                'returnStatusReport.payment',
            ])
            ->findOrFail($contractId);
    }

    private function canAccessContract(Contract $contract, ?\App\Models\User $user): bool
    {
        return $this->branchAccess->canAccessBranchId(
            $user,
            $contract->branch_id ? (int) $contract->branch_id : ($contract->reservation?->car?->branch_id ? (int) $contract->reservation->car->branch_id : null)
        );
    }

    private function canEditReturnReport(?\App\Models\User $user): bool
    {
        return $user !== null
            && method_exists($user, 'hasPermission')
            && $user->hasPermission('tenant-edit-return-reports');
    }

    private function reportIsPaid(?ContractReturnReport $report): bool
    {
        return $report !== null && (string) ($report->payment_status ?? ($report->payment_id ? 'paid' : 'not_paid')) === 'paid';
    }

    private function reservationSettings(int $tenantId): array
    {
        $settings = DB::table('tenant_site_settings')
            ->where('tenant_id', $tenantId)
            ->value('reservation_settings');

        $decoded = is_array($settings) ? $settings : (json_decode((string) $settings, true) ?: null);

        return ReservationSettings::normalize($decoded);
    }

    private function serializeReport(?ContractReturnReport $report): array
    {
        if (!$report) {
            return [
                'id' => null,
                'report_number' => $this->generateReportNumber(),
                'status' => 'finalized',
                'actual_return_time' => now()->format('Y-m-d\TH:i'),
                'return_location' => '',
                'return_odometer' => null,
                'return_fuel_level' => '',
                'vehicle_condition_after' => 'clean',
                'payment_status' => 'not_paid',
                'damage_report_id' => null,
                'extra_kilometers' => 0,
                'kilometer_rate' => 0,
                'cleaning_fee' => 0,
                'fuel_fee' => 0,
                'fuel_credit' => 0,
                'late_hours' => 0,
                'late_hour_rate' => 0,
                'damage_fee' => 0,
                'maintenance_fee' => 0,
                'other_fee' => 0,
                'discount' => 0,
                'total_extra_charges' => 0,
                'notes' => '',
            ];
        }

        return [
            'id' => $report->id,
            'report_number' => $report->report_number,
            'status' => $report->status,
            'actual_return_time' => optional($report->actual_return_time)->format('Y-m-d\TH:i'),
            'return_location' => $report->return_location,
            'return_odometer' => $report->return_odometer,
            'return_fuel_level' => $report->return_fuel_level,
            'vehicle_condition_after' => $report->vehicle_condition_after,
            'payment_status' => $report->payment_status ?? ($report->payment ? 'paid' : 'not_paid'),
            'damage_report_id' => $report->damage_report_id,
            'extra_kilometers' => $report->extra_kilometers,
            'kilometer_rate' => $report->kilometer_rate,
            'cleaning_fee' => $report->cleaning_fee,
            'fuel_fee' => $report->fuel_fee,
            'fuel_credit' => $report->fuel_credit,
            'late_hours' => $report->late_hours,
            'late_hour_rate' => $report->late_hour_rate,
            'damage_fee' => $report->damage_fee,
            'maintenance_fee' => $report->maintenance_fee,
            'other_fee' => $report->other_fee,
            'discount' => $report->discount ?? 0,
            'total_extra_charges' => $report->total_extra_charges,
            'notes' => $report->notes,
        ];
    }

    private function normalizeMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    private function pdfBranding($tenant): array
    {
        $tenant = $tenant?->loadMissing('siteSetting.files');
        $settings = $tenant ? TenantSiteSetting::forTenant($tenant) : [];
        $name = trim((string) ($settings['site_name'] ?? $tenant?->name ?? config('app.name')));

        return [
            'name' => $name !== '' ? $name : (string) config('app.name'),
            'logo' => $this->pdfImageSource($settings['logo_url'] ?? null),
        ];
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

    private function calculateLateFee(float $lateHours, array $settings, float $dailyRate, ?float $hourlyRateOverride = null): float
    {
        if ($lateHours <= 0) {
            return 0.0;
        }

        $lateReturn = is_array($settings['late_return'] ?? null) ? $settings['late_return'] : [];
        $mode = (string) ($lateReturn['mode'] ?? 'hourly');
        $hourlyFee = $hourlyRateOverride !== null && $hourlyRateOverride > 0
            ? $hourlyRateOverride
            : ReservationSettings::resolveLateHourlyFee($settings);
        $afterHours = isset($lateReturn['after_hours']) ? (int) $lateReturn['after_hours'] : 0;

        if ($mode === 'daily_after_threshold' && $afterHours > 0 && $lateHours > $afterHours) {
            $excessHours = $lateHours - $afterHours;
            $fullDays = (int) floor($excessHours / 24);
            $remainingHours = round($excessHours - ($fullDays * 24), 2);
            $dayRate = $dailyRate > 0 ? $dailyRate : $hourlyFee * 24;

            return round(($fullDays * $dayRate) + ($remainingHours * $hourlyFee), 2);
        }

        return round($lateHours * $hourlyFee, 2);
    }

    private function resolveDefaultCharges(Contract $contract, array $settings): array
    {
        $returnLocation = $contract->reservation?->return_location;
        $returnFuelLevel = $contract->vehicle_fuel_level;
        $returnLocationFee = ReservationSettings::resolveLocationFee($settings, $returnLocation, 'return');
        $returnOdometer = $contract->return_odometer !== null ? (int) $contract->return_odometer : null;

        return [
            'return_location_fee' => $returnLocationFee,
            'cleaning_fee' => ReservationSettings::resolveCleaningFee($settings),
            'fuel_fee' => ReservationSettings::resolveFuelFeeByLoss($settings, $contract->vehicle_fuel_level, $returnFuelLevel),
            'fuel_credit' => ReservationSettings::resolveFuelCreditByGain($settings, $contract->vehicle_fuel_level, $returnFuelLevel),
            'late_hour_rate' => ReservationSettings::resolveLateHourlyFee($settings),
            'kilometer_rate' => $this->resolveKilometerRate($contract, $settings, $contract->actual_return_time?->format('Y-m-d H:i'), $returnOdometer),
            'late_hours' => $this->resolveLateHours($contract, $settings),
            'damage_fee' => 0,
        ];
    }

    private function resolveKilometerRate(Contract $contract, array $settings, ?string $actualReturnTime = null, ?int $returnOdometer = null): float
    {
        $extraKilometers = $this->resolveExtraKilometers($contract, $settings, $actualReturnTime, $returnOdometer);

        return ReservationSettings::resolveKilometerRate($settings, $extraKilometers);
    }

    private function resolveExtraKilometers(Contract $contract, array $settings, ?string $actualReturnTime = null, ?int $returnOdometer = null): float
    {
        $vehicleOdometer = (int) ($contract->vehicle_odometer ?? 0);
        $returnOdometer = $returnOdometer ?? ($contract->return_odometer !== null ? (int) $contract->return_odometer : 0);
        $drivenKilometers = max(0, $returnOdometer - $vehicleOdometer);
        $allowedKilometers = $this->resolveAllowedKilometers($contract, $settings, $actualReturnTime);

        return max(0, $drivenKilometers - $allowedKilometers);
    }

    private function resolveAllowedKilometers(Contract $contract, array $settings, ?string $actualReturnTime = null): float
    {
        $days = $this->resolveContractDurationDays($contract) + $this->resolveLateChargeDays($contract, $settings, $actualReturnTime);
        if ($days <= 0) {
            return 0.0;
        }

        $daily = (float) ($contract->allowed_km_per_day ?? 0);
        if ($daily > 0) {
            return $this->normalizeMoney($days * $daily);
        }

        $weekly = (float) ($contract->allowed_km_per_week ?? 0);
        if ($weekly > 0) {
            $weeks = max(1, (int) ceil($days / 7));

            return $this->normalizeMoney($weeks * $weekly);
        }

        $monthly = (float) ($contract->allowed_km_per_month ?? 0);
        if ($monthly > 0) {
            $months = max(1, (int) ceil($days / 30));

            return $this->normalizeMoney($months * $monthly);
        }

        return 0.0;
    }

    private function resolveLateChargeDays(Contract $contract, array $settings, ?string $actualReturnTime = null): int
    {
        $lateHours = $this->resolveLateHours($contract, $settings, $actualReturnTime);
        if ($lateHours <= 0) {
            return 0;
        }

        $lateSettings = $settings['late_return'] ?? [];
        if (($lateSettings['mode'] ?? 'hourly') !== 'daily_after_threshold') {
            return 0;
        }

        $threshold = (float) ($lateSettings['after_hours'] ?? 0);
        if ($threshold <= 0 || $lateHours <= $threshold) {
            return 0;
        }

        return max(1, (int) ceil(($lateHours - $threshold) / 24));
    }

    private function resolveContractDurationDays(Contract $contract): int
    {
        if (!$contract->start_date || !$contract->end_date) {
            return 0;
        }

        return max(1, (int) Carbon::parse($contract->start_date)->diffInDays(Carbon::parse($contract->end_date)) + 1);
    }

    private function resolveLateHours(Contract $contract, array $settings, ?string $actualReturnTime = null): float
    {
        $actual = $actualReturnTime ? Carbon::parse($actualReturnTime) : ($contract->actual_return_time ? Carbon::parse($contract->actual_return_time) : null);
        $expected = $this->expectedReturnDateTime($contract, $settings);

        if (!$actual || !$expected || $actual->lessThanOrEqualTo($expected)) {
            return 0.0;
        }

        return round($expected->diffInMinutes($actual) / 60, 2);
    }

    private function expectedReturnDateTime(Contract $contract, array $settings): ?Carbon
    {
        $endDate = $contract->end_date;
        if (!$endDate) {
            return null;
        }

        $policy = $settings['return_time_policy']['mode'] ?? 'fixed_time';
        $time = match ($policy) {
            'same_pickup' => (string) ($contract->reservation?->pickup_time ?? '18:00'),
            'set_during_reservation' => (string) ($contract->reservation?->return_time ?? '18:00'),
            default => (string) ($settings['return_time_policy']['fixed_time'] ?? '18:00'),
        };

        $time = $time ?: '18:00';

        return Carbon::parse($endDate->format('Y-m-d').' '.$time);
    }

    private function resolveDamageFee(Contract $contract): float
    {
        $damageReport = $contract->damageReports->first(fn (CarDamageReport $report) => $report->report_type === 'after_return');
        if (!$damageReport) {
            return 0.0;
        }

        return $this->normalizeMoney($this->afterReturnDamageItems($damageReport)->sum('estimated_cost'));
    }

    private function afterReturnDamageItems(CarDamageReport $report)
    {
        $items = $report->items;
        $hasExplicitTiming = $items->contains(fn ($item) => !blank($item->damage_timing));

        if ($hasExplicitTiming) {
            return $items->filter(fn ($item) => $item->damage_timing === 'after_return')->values();
        }

        if ($report->report_type === 'after_return') {
            return $items->values();
        }

        return collect();
    }

    private function generateReportNumber(): string
    {
        $year = now()->format('Y');
        $prefix = 'RTR';
        $sequence = (int) (ContractReturnReport::query()
            ->where('tenant_id', TenantContext::id())
            ->where('report_number', 'like', "{$prefix}-{$year}-%")
            ->count() + 1);

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }
}
