<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="UTF-8" />
    <title>{{ __('contracts.pdf.document_title', ['number' => $contract->contract_number]) }}</title>
    <style>
        @font-face {
            font-family: cairo;
            src: url("{{ file_exists(storage_path('fonts/cairo_normal_a5cea5fc45f6bf5f483d9f082575cfe3.ttf')) ? 'data:font/truetype;base64,'.base64_encode(file_get_contents(storage_path('fonts/cairo_normal_a5cea5fc45f6bf5f483d9f082575cfe3.ttf'))) : '' }}") format("truetype");
            font-weight: 400;
            font-style: normal;
        }
        @font-face {
            font-family: cairo;
            src: url("{{ file_exists(storage_path('fonts/cairo_bold_23a9b2dc30935e892c606fbbafd14072.ttf')) ? 'data:font/truetype;base64,'.base64_encode(file_get_contents(storage_path('fonts/cairo_bold_23a9b2dc30935e892c606fbbafd14072.ttf'))) : '' }}") format("truetype");
            font-weight: 700 900;
            font-style: normal;
        }
        @page { size: A4 portrait; margin: 8mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #16336f;
            background: #ffffff;
            font-family: cairo, "DejaVu Sans", Arial, sans-serif;
            font-size: 10px;
            line-height: 1.45;
        }
        .document { width: 100%; }
        .header-shell {
            border: 2px solid #16336f;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 12px;
        }
        .header-table,
        .two-col,
        .summary-table,
        .drivers-table,
        .damage-table,
        .rates-table,
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td,
        .two-col td { vertical-align: top; }
        .meta-col { width: 26%; font-size: 9px; }
        .meta-col.right { text-align: right; direction: rtl; }
        .brand-col { width: 48%; text-align: center; }
        .brand-logo {
            max-width: 180px;
            max-height: 48px;
            object-fit: contain;
            margin: 0 auto 6px;
            display: block;
        }
        .company-en {
            font-size: 22px;
            font-weight: 900;
            letter-spacing: 0.8px;
            line-height: 1.05;
        }
        .company-ar {
            font-size: 18px;
            font-weight: 700;
            direction: rtl;
            line-height: 1.15;
        }
        .document-title {
            margin-top: 6px;
            font-size: 15px;
            font-weight: 900;
        }
        .document-title .ar {
            display: inline-block;
            margin-inline-start: 10px;
            direction: rtl;
        }
        .contract-number {
            margin-top: 8px;
            font-size: 12px;
            font-weight: 900;
            color: #d0202a;
        }
        .panel {
            border: 1px solid #16336f;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .panel-title {
            background: #16336f;
            color: #ffffff;
            padding: 7px 10px;
            font-size: 11px;
            font-weight: 900;
        }
        .panel-body { padding: 10px; }
        .panel-title .ar {
            float: right;
            direction: rtl;
        }
        .summary-table td {
            width: 25%;
            border: 1px solid #d9e2f2;
            padding: 8px;
        }
        .summary-label {
            display: block;
            color: #5d6c91;
            font-size: 8px;
            margin-bottom: 3px;
        }
        .summary-value {
            color: #102857;
            font-size: 12px;
            font-weight: 900;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 5px 0;
            vertical-align: top;
        }
        .info-table .label {
            width: 28%;
            color: #4c5f8a;
            font-weight: 700;
        }
        .info-table .value {
            width: 40%;
            color: #111827;
            font-weight: 900;
            border-bottom: 1px dashed #9eb0d4;
            padding-bottom: 3px;
        }
        .info-table .label-ar {
            width: 32%;
            color: #4c5f8a;
            font-weight: 700;
            text-align: right;
            direction: rtl;
        }
        .drivers-table th,
        .drivers-table td,
        .damage-table th,
        .damage-table td,
        .rates-table th,
        .rates-table td,
        .signature-table td {
            border: 1px solid #cad7f0;
            padding: 6px 7px;
        }
        .drivers-table th,
        .damage-table th,
        .rates-table th {
            background: #f4f7ff;
            color: #16336f;
            font-size: 9px;
            font-weight: 900;
            text-align: left;
        }
        .damage-table th.ar,
        .drivers-table th.ar {
            text-align: right;
            direction: rtl;
        }
        .muted {
            color: #6b7280;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .rtl { direction: rtl; text-align: right; }
        .terms {
            margin: 0;
            padding-left: 16px;
            color: #16336f;
        }
        .terms li { margin-bottom: 5px; }
        .note-box {
            background: #f7faff;
            border: 1px solid #dce6f8;
            border-radius: 8px;
            padding: 8px 10px;
            color: #30466f;
        }
        .footer-note {
            margin-top: 6px;
            text-align: center;
            color: #16336f;
            font-size: 10px;
            font-weight: 900;
        }
        .small { font-size: 8px; }
    </style>
</head>
<body>
@php
    $reservation = $contract->reservation;
    $reservationCar = $reservation?->car;
    $reservationUser = $reservation?->user;
    $primaryDriver = $contract->primaryDriver;
    $drivers = collect();
    if ($contract->primaryDriver) { $drivers->push($contract->primaryDriver); }
    foreach ($contract->additionalDrivers as $driver) { $drivers->push($driver); }

    $headerCompanyNameEn = data_get($pdfHeader, 'company_name.en') ?: $companyName;
    $headerCompanyNameAr = data_get($pdfHeader, 'company_name.ar') ?: $headerCompanyNameEn;
    $headerCrNumber = data_get($pdfHeader, 'cr_number') ?: '-';
    $headerPoBox = data_get($pdfHeader, 'po_box') ?: '-';
    $headerPc = data_get($pdfHeader, 'pc') ?: '-';
    $headerCountryEn = data_get($pdfHeader, 'country.en') ?: 'Sultanate of Oman';
    $headerCountryAr = data_get($pdfHeader, 'country.ar') ?: 'سلطنة عمان';
    $headerGsm1 = data_get($pdfHeader, 'gsm_1') ?: '-';
    $headerGsm2 = data_get($pdfHeader, 'gsm_2') ?: '-';
    $headerGsm3 = data_get($pdfHeader, 'gsm_3') ?: '-';

    $dailyRate = $contract->price_per_day ?? $reservation?->daily_rate ?? $reservationCar?->price_per_day;
    $weeklyRate = $contract->price_per_week ?? $reservationCar?->price_per_week;
    $monthlyRate = $contract->price_per_month ?? $reservationCar?->price_per_month;
    $allowedKmDay = $contract->allowed_km_per_day ?? $reservationCar?->allowed_km_per_day;
    $allowedKmWeek = $contract->allowed_km_per_week ?? $reservationCar?->allowed_km_per_week;
    $allowedKmMonth = $contract->allowed_km_per_month ?? $reservationCar?->allowed_km_per_month;

    $contactAddress = data_get($siteSettings, 'contact.address.'.$locale)
        ?? data_get($siteSettings, 'contact.address.en')
        ?? data_get($siteSettings, 'contact.address.ar')
        ?? $contract->branch?->address
        ?? '-';
    $visaExpiry = optional($primaryDriver?->visa_expiry_date)->format('Y-m-d') ?: '-';
    $passportExpiry = optional($primaryDriver?->passport_expiry_date)->format('Y-m-d') ?: '-';
    $licenseExpiry = optional($primaryDriver?->license_expiry_date)->format('Y-m-d') ?: '-';
    $pickupDate = optional($reservation?->start_date)->format('Y-m-d') ?? optional($contract->start_date)->format('Y-m-d') ?? '-';
    $returnDate = optional($reservation?->end_date)->format('Y-m-d') ?? optional($contract->end_date)->format('Y-m-d') ?? '-';
    $pickupTime = optional($reservation?->pickup_time)->format('H:i') ?: '-';
    $returnTime = optional($reservation?->return_time)->format('H:i') ?: '-';
    $money = static fn ($value) => $value === null || $value === '' ? '-' : number_format((float) $value, 2).' '.($contract->currency ?: $currencySymbol);
    $line = static fn ($value) => filled($value) ? (string) $value : '-';
    $contractPdf = data_get($siteSettings, 'contract_pdf', []);
    $contractText = static function (string $key, string $lang) use ($contractPdf) {
        $value = data_get($contractPdf, "{$key}.{$lang}");

        if (filled($value)) {
            return $value;
        }

        return \Illuminate\Support\Facades\Lang::get("contracts.pdf.contract_texts.{$key}.{$lang}", [], $lang);
    };
@endphp

<div class="document">
    <div class="header-shell">
        <table class="header-table">
            <tr>
                <td class="meta-col">
                    <div><strong>C.R:</strong> {{ $headerCrNumber }}</div>
                    <div><strong>P.O Box:</strong> {{ $headerPoBox }}</div>
                    <div><strong>P.C:</strong> {{ $headerPc }}</div>
                    <div><strong>Country:</strong> {{ $headerCountryEn }}</div>
                    <div><strong>GSM 1:</strong> {{ $headerGsm1 }}</div>
                    <div><strong>GSM 2:</strong> {{ $headerGsm2 }}</div>
                    <div><strong>GSM 3:</strong> {{ $headerGsm3 }}</div>
                </td>
                <td class="brand-col">
                    @if(!empty($companyLogo))
                        <img src="{{ $companyLogo }}" class="brand-logo" alt="Logo" />
                    @endif
                    <div class="company-en">{{ strtoupper($headerCompanyNameEn) }}</div>
                    <div class="company-ar">{{ $headerCompanyNameAr }}</div>
                    <div class="document-title">
                        CAR RENTAL CONTRACT
                        <span class="ar">عقد إيجار سيارة</span>
                    </div>
                    <div class="contract-number">{{ $contract->contract_number }}</div>
                </td>
                <td class="meta-col right">
                    <div><strong>رقم السجل التجاري:</strong> {{ $headerCrNumber }}</div>
                    <div><strong>ص.ب:</strong> {{ $headerPoBox }}</div>
                    <div><strong>الرمز البريدي:</strong> {{ $headerPc }}</div>
                    <div><strong>الدولة:</strong> {{ $headerCountryAr }}</div>
                    <div><strong>نقال 1:</strong> {{ $headerGsm1 }}</div>
                    <div><strong>نقال 2:</strong> {{ $headerGsm2 }}</div>
                    <div><strong>نقال 3:</strong> {{ $headerGsm3 }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="panel">
        <div class="panel-title">
            Contract Summary
            <span class="ar">ملخص العقد</span>
        </div>
        <div class="panel-body">
            <table class="summary-table">
                <tr>
                    <td>
                        <span class="summary-label">Contract Date</span>
                        <span class="summary-value">{{ optional($contract->contract_date)->format('Y-m-d') ?: '-' }}</span>
                    </td>
                    <td>
                        <span class="summary-label">Status</span>
                        <span class="summary-value">{{ ucfirst((string) $contract->status->value) }}</span>
                    </td>
                    <td>
                        <span class="summary-label">Start</span>
                        <span class="summary-value">{{ $pickupDate }} {{ $pickupTime !== '-' ? $pickupTime : '' }}</span>
                    </td>
                    <td>
                        <span class="summary-label">End</span>
                        <span class="summary-value">{{ $returnDate }} {{ $returnTime !== '-' ? $returnTime : '' }}</span>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <table class="two-col">
        <tr>
            <td style="width: 50%; padding-right: 5px;">
                <div class="panel">
                    <div class="panel-title">
                        Renter Details
                        <span class="ar">تفاصيل المستأجر</span>
                    </div>
                    <div class="panel-body">
                        <table class="info-table">
                            <tr>
                                <td class="label">Name</td>
                                <td class="value">{{ $line($contract->renter_name ?: $primaryDriver?->full_name ?: $reservationUser?->name) }}</td>
                                <td class="label-ar">اسم المستأجر</td>
                            </tr>
                            <tr>
                                <td class="label">Nationality</td>
                                <td class="value">{{ $line($primaryDriver?->nationality) }}</td>
                                <td class="label-ar">الجنسية</td>
                            </tr>
                            <tr>
                                <td class="label">Phone</td>
                                <td class="value">{{ $line($contract->renter_phone ?: $primaryDriver?->phone) }}</td>
                                <td class="label-ar">الهاتف</td>
                            </tr>
                            <tr>
                                <td class="label">Address</td>
                                <td class="value">{{ $line($contactAddress) }}</td>
                                <td class="label-ar">العنوان</td>
                            </tr>
                            <tr>
                                <td class="label">ID / Civil No.</td>
                                <td class="value">{{ $line($primaryDriver?->identity_number ?: $primaryDriver?->residency_number ?: $contract->renter_id_number) }}</td>
                                <td class="label-ar">الرقم المدني</td>
                            </tr>
                            <tr>
                                <td class="label">Visa No.</td>
                                <td class="value">{{ $line($primaryDriver?->visa_number) }}</td>
                                <td class="label-ar">رقم التأشيرة</td>
                            </tr>
                            <tr>
                                <td class="label">Visa Expiry</td>
                                <td class="value">{{ $visaExpiry }}</td>
                                <td class="label-ar">تاريخ الانتهاء</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
            <td style="width: 50%; padding-left: 5px;">
                <div class="panel">
                    <div class="panel-title">
                        Documents & License
                        <span class="ar">الوثائق والرخصة</span>
                    </div>
                    <div class="panel-body">
                        <table class="info-table">
                            <tr>
                                <td class="label">Passport No.</td>
                                <td class="value">{{ $line($primaryDriver?->passport_number) }}</td>
                                <td class="label-ar">رقم الجواز</td>
                            </tr>
                            <tr>
                                <td class="label">Passport Expiry</td>
                                <td class="value">{{ $passportExpiry }}</td>
                                <td class="label-ar">انتهاء الجواز</td>
                            </tr>
                            <tr>
                                <td class="label">License No.</td>
                                <td class="value">{{ $line($primaryDriver?->license_number) }}</td>
                                <td class="label-ar">رقم الرخصة</td>
                            </tr>
                            <tr>
                                <td class="label">Issue Place</td>
                                <td class="value">{{ $line($primaryDriver?->place_of_issue) }}</td>
                                <td class="label-ar">مكان الإصدار</td>
                            </tr>
                            <tr>
                                <td class="label">Issue Date</td>
                                <td class="value">{{ optional($primaryDriver?->license_issue_date)->format('Y-m-d') ?: '-' }}</td>
                                <td class="label-ar">تاريخ الإصدار</td>
                            </tr>
                            <tr>
                                <td class="label">License Expiry</td>
                                <td class="value">{{ $licenseExpiry }}</td>
                                <td class="label-ar">انتهاء الرخصة</td>
                            </tr>
                            <tr>
                                <td class="label">Reservation</td>
                                <td class="value">{{ $line($reservation?->reservation_number) }}</td>
                                <td class="label-ar">رقم الحجز</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="two-col">
        <tr>
            <td style="width: 55%; padding-right: 5px;">
                <div class="panel">
                    <div class="panel-title">
                        Vehicle Details
                        <span class="ar">بيانات المركبة</span>
                    </div>
                    <div class="panel-body">
                        <table class="info-table">
                            <tr>
                                <td class="label">Vehicle</td>
                                <td class="value">{{ $line(trim(($reservationCar?->year ? $reservationCar->year.' ' : '').($reservationCar?->make ?? '').' '.($reservationCar?->model ?? ''))) }}</td>
                                <td class="label-ar">المركبة</td>
                            </tr>
                            <tr>
                                <td class="label">Plate No.</td>
                                <td class="value">{{ $line($reservationCar?->license_plate ?: $contract->plate_number) }}</td>
                                <td class="label-ar">رقم اللوحة</td>
                            </tr>
                            <tr>
                                <td class="label">Delivery Odometer</td>
                                <td class="value">{{ $line($contract->vehicle_odometer) }}</td>
                                <td class="label-ar">عداد التسليم</td>
                            </tr>
                            <tr>
                                <td class="label">Return Odometer</td>
                                <td class="value">{{ $line($contract->return_odometer) }}</td>
                                <td class="label-ar">عداد الإرجاع</td>
                            </tr>
                            <tr>
                                <td class="label">Fuel Level</td>
                                <td class="value">{{ $line($contract->vehicle_fuel_level) }}</td>
                                <td class="label-ar">مستوى الوقود</td>
                            </tr>
                            <tr>
                                <td class="label">Condition Before</td>
                                <td class="value">{{ $line($contract->vehicle_condition_before) }}</td>
                                <td class="label-ar">الحالة قبل التسليم</td>
                            </tr>
                            <tr>
                                <td class="label">Condition After</td>
                                <td class="value">{{ $line($contract->vehicle_condition_after) }}</td>
                                <td class="label-ar">الحالة بعد الإرجاع</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
            <td style="width: 45%; padding-left: 5px;">
                <div class="panel">
                    <div class="panel-title">
                        Rental Rates
                        <span class="ar">الأسعار والكيلومترات</span>
                    </div>
                    <div class="panel-body">
                        <table class="rates-table">
                            <tr>
                                <th>Period</th>
                                <th class="text-center">Rate</th>
                                <th class="text-center">Allowed KM</th>
                            </tr>
                            <tr>
                                <td>Per Day</td>
                                <td class="text-center">{{ $money($dailyRate) }}</td>
                                <td class="text-center">{{ $line($allowedKmDay) }}</td>
                            </tr>
                            <tr>
                                <td>Per Week</td>
                                <td class="text-center">{{ $money($weeklyRate) }}</td>
                                <td class="text-center">{{ $line($allowedKmWeek) }}</td>
                            </tr>
                            <tr>
                                <td>Per Month</td>
                                <td class="text-center">{{ $money($monthlyRate) }}</td>
                                <td class="text-center">{{ $line($allowedKmMonth) }}</td>
                            </tr>
                        </table>
                        <div class="note-box" style="margin-top: 8px;">
                            {{ $line($contract->notes) }}
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="panel">
        <div class="panel-title">
            Drivers
            <span class="ar">السائقون</span>
        </div>
        <div class="panel-body">
            <table class="drivers-table">
                <tr>
                    <th>Role</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Nationality</th>
                    <th>License No.</th>
                    <th class="ar">الاسم</th>
                </tr>
                @forelse($drivers as $driver)
                    <tr>
                        <td>{{ $driver->role === 'primary' ? 'Primary' : 'Additional' }}</td>
                        <td>{{ $line($driver->full_name) }}</td>
                        <td>{{ $line($driver->phone) }}</td>
                        <td>{{ $line($driver->nationality) }}</td>
                        <td>{{ $line($driver->license_number) }}</td>
                        <td class="rtl">{{ $line($driver->full_name_ar ?: $driver->full_name) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center muted">No driver records attached to this contract.</td>
                    </tr>
                @endforelse
            </table>
        </div>
    </div>

    <table class="two-col">
        <tr>
            <td style="width: 56%; padding-right: 5px;">
                <div class="panel">
                    <div class="panel-title">
                        Current Car Damages
                        <span class="ar">أضرار السيارة الحالية</span>
                    </div>
                    <div class="panel-body">
                        <table class="damage-table">
                            <tr>
                                <th>#</th>
                                <th>Zone</th>
                                <th>View</th>
                                <th>Type</th>
                                <th>Severity</th>
                                <th>Qty</th>
                            </tr>
                            @forelse($currentDamageCases as $index => $damage)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $line($damage['zone_label'] ?? null) }}</td>
                                    <td>{{ $line($damage['view_side_label'] ?? null) }}</td>
                                    <td>{{ $line($damage['damage_type_label'] ?? null) }}</td>
                                    <td>{{ $line($damage['severity_label'] ?? null) }}</td>
                                    <td class="text-center">{{ (int) ($damage['quantity'] ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center muted">No current damages are recorded for this vehicle.</td>
                                </tr>
                            @endforelse
                        </table>
                    </div>
                </div>
            </td>
            <td style="width: 44%; padding-left: 5px;">
                <div class="panel">
                    <div class="panel-title">
                        Terms & Signatures
                        <span class="ar">الشروط والتوقيع</span>
                    </div>
                    <div class="panel-body">
                        <ul class="terms">
                            <li>Vehicle is delivered under the agreed reservation and contract terms.</li>
                            <li>The renter is responsible for violations, damage, and late return charges.</li>
                            <li>Any additional fees are settled before closing the contract.</li>
                        </ul>

                        <table class="signature-table" style="margin-top: 10px;">
                            <tr>
                                <td>
                                    <div class="small muted">Renter Signature</div>
                                    <div style="height: 28px;"></div>
                                </td>
                                <td>
                                    <div class="small muted">Incharge Signature</div>
                                    <div style="height: 28px;"></div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="small muted">Date</div>
                                    <div>{{ optional($contract->contract_date)->format('Y-m-d') ?: '-' }}</div>
                                </td>
                                <td>
                                    <div class="small muted">Generated</div>
                                    <div>{{ $generatedAt->format('Y-m-d H:i') }}</div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    @if($contract->damageReports->isNotEmpty())
        <div class="panel">
            <div class="panel-title">
                Linked Damage Reports
                <span class="ar">تقارير الأضرار المرتبطة</span>
            </div>
            <div class="panel-body">
                <table class="damage-table">
                    <tr>
                        <th>Report No.</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Inspected At</th>
                    </tr>
                    @foreach($contract->damageReports as $report)
                        <tr>
                            <td>{{ $line($report->report_number) }}</td>
                            <td>{{ $line($reportTypeLabels[$report->report_type] ?? $report->report_type) }}</td>
                            <td>{{ $line($statusLabels[$report->status] ?? $report->status) }}</td>
                            <td class="text-center">{{ $report->items->count() }}</td>
                            <td>{{ optional($report->inspected_at)->format('Y-m-d H:i') ?: '-' }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    @endif

    <div class="footer-note">
        {{ $contractText('closing_notice', 'en') }}
        <div class="rtl">{{ $contractText('closing_notice', 'ar') }}</div>
    </div>
</div>
</body>
</html>
