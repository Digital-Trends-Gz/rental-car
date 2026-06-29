<!doctype html>
<html lang="{{ ($report['locale'] ?? 'ar') === 'ar' ? 'ar' : 'en' }}">
<head>
    <meta charset="utf-8">
    <title>AI Insights Report</title>
    <style>
        @font-face {
            font-family: cairo;
            src: url("{{ file_exists(storage_path('fonts/cairo_normal_a5cea5fc45f6bf5f483d9f082575cfe3.ttf')) ? 'data:font/truetype;base64,'.base64_encode(file_get_contents(storage_path('fonts/cairo_normal_a5cea5fc45f6bf5f483d9f082575cfe3.ttf'))) : '' }}") format("truetype");
            font-weight: 400; font-style: normal;
        }
        @font-face {
            font-family: cairo;
            src: url("{{ file_exists(storage_path('fonts/cairo_bold_23a9b2dc30935e892c606fbbafd14072.ttf')) ? 'data:font/truetype;base64,'.base64_encode(file_get_contents(storage_path('fonts/cairo_bold_23a9b2dc30935e892c606fbbafd14072.ttf'))) : '' }}") format("truetype");
            font-weight: 700 900; font-style: normal;
        }
        @font-face {
            font-family: "TahomaPdf";
            src: url("{{ public_path('fonts/tahoma.ttf') }}") format("truetype");
            font-weight: 400; font-style: normal;
        }
        @font-face {
            font-family: "TahomaPdf";
            src: url("{{ public_path('fonts/tahomabd.ttf') }}") format("truetype");
            font-weight: 700 900; font-style: normal;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: cairo, "TahomaPdf", "DejaVu Sans", Arial, sans-serif;
            font-size: 8px;
            color: #111827;
            background: #ffffff;
        }
        .ar { direction: rtl; unicode-bidi: plaintext; font-family: cairo, "TahomaPdf", "DejaVu Sans", Arial, sans-serif; }
        /* ── Page wrapper ── */
        .page { border: 2px solid #17306f; padding: 4px 8px 6px; }
        /* ── Document header ── */
        .document-header { width: 100%; border-collapse: collapse; color: #17306f; margin-bottom: 2px; }
        .document-header td { padding: 2px 4px; vertical-align: top; }
        .head-left, .head-right { width: 24%; font-size: 7px; line-height: 1.25; font-weight: 700; }
        .head-right { text-align: right; }
        .head-main { width: 52%; text-align: center; border-left: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; }
        .logo { max-height: 32px; max-width: 160px; object-fit: contain; margin-bottom: 2px; display: block; margin-left: auto; margin-right: auto; }
        .company-en { font-size: 17px; line-height: 1.02; font-weight: 900; letter-spacing: .4px; color: #17306f; text-transform: uppercase; }
        .company-ar { font-size: 13px; line-height: 1.05; font-weight: 900; color: #17306f; }
        .title-line { font-size: 10px; line-height: 1.1; font-weight: 900; color: #17306f; margin-top: 2px; }
        .title-line .ar { display: inline-block; margin-left: 6px; font-size: 10px; }
        .serial { margin-top: 3px; font-size: 7.5px; }
        .serial span { color: #dc2626; font-size: 9px; font-weight: 900; }
        .thick-rule { height: 4px; background: #17306f; margin: 2px 0 3px; }
        /* ── Report meta row ── */
        .report-top { width: 100%; border-collapse: collapse; margin-bottom: 3px; }
        .report-top td { padding: 0; vertical-align: top; }
        .report-title { font-size: 12px; font-weight: 900; color: #111827; line-height: 1.1; }
        .meta-box { text-align: right; color: #475569; font-size: 7.5px; line-height: 1.4; font-weight: 700; }
        .badge { display: inline-block; border-radius: 999px; background: #dbeafe; color: #2563eb; font-size: 7px; font-weight: 800; padding: 1px 5px; margin-top: 1px; }
        /* ── Sections ── */
        .section { margin-top: 3px; border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; page-break-inside: avoid; }
        .section-title { background: #f1f5f9; color: #17306f; font-size: 9px; font-weight: 900; padding: 2px 7px; border-bottom: 1px solid #cbd5e1; }
        /* ── Data tables ── */
        table.data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 1.5px 5px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .data-table tr:last-child td { border-bottom: 0; }
        .data-table th { background: #f8fafc; color: #475569; font-size: 7px; text-transform: uppercase; letter-spacing: .02em; }
        .data-table th.r, .data-table td.r { text-align: right; }
        /* ── Summary cards ── */
        .cards-table { width: 100%; border-collapse: separate; border-spacing: 3px; margin: 2px 0; }
        .card-cell { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 3px 5px; text-align: center; vertical-align: middle; }
        .card-num { font-size: 13px; font-weight: 900; color: #17306f; line-height: 1; }
        .card-lbl { font-size: 7px; color: #64748b; margin-top: 1px; }
        /* ── Pills ── */
        .pill { display: inline-block; padding: 1px 5px; border-radius: 999px; font-size: 7px; font-weight: 800; }
        .danger  { background: #fee2e2; color: #991b1b; }
        .warning { background: #fef3c7; color: #92400e; }
        .info    { background: #dbeafe; color: #1e40af; }
        .success { background: #dcfce7; color: #166534; }
        /* ── Footer ── */
        .footer { margin-top: 5px; color: #64748b; font-size: 7px; text-align: center; }
    </style>
</head>
<body>
@php
    /* ── Header data (same as all other PDFs) ── */
    $pdfHeader           = $pdfHeader ?? data_get($siteSettings ?? [], 'pdf_header', []);
    $headerCompanyNameEn = data_get($pdfHeader, 'company_name.en') ?: ($companyName ?? $tenant?->name ?? config('app.name'));
    $headerCompanyNameAr = data_get($pdfHeader, 'company_name.ar') ?: $headerCompanyNameEn;
    $headerCrNumber      = data_get($pdfHeader, 'cr_number') ?: '-';
    $headerPoBox         = data_get($pdfHeader, 'po_box') ?: '-';
    $headerPc            = data_get($pdfHeader, 'pc') ?: '-';
    $headerCountryEn     = data_get($pdfHeader, 'country.en') ?: 'Sultanate of Oman';
    $headerCountryAr     = data_get($pdfHeader, 'country.ar') ?: 'سلطنة عمان';
    $headerGsm1          = data_get($pdfHeader, 'gsm_1') ?: '-';
    $headerGsm2          = data_get($pdfHeader, 'gsm_2') ?: '-';
    $headerGsm3          = data_get($pdfHeader, 'gsm_3') ?: '-';
    $headerRegistryLabelEn = data_get($pdfHeader, 'registry_label.en') ?: 'No.';
    $headerRegistryLabelAr = data_get($pdfHeader, 'registry_label.ar') ?: 'رقم التقرير';
    $safeReportNumber    = 'AIR-' . ($report['id'] ?? now()->format('YmdHi'));

    /* ── Report payload ── */
    $summary          = $report['internal_payload']['summary'] ?? [];
    $unprofitable     = $report['internal_payload']['unprofitable_cars'] ?? [];
    $highDamage       = $report['internal_payload']['repeated_damage_cars'] ?? [];
    $highRisk         = $report['internal_payload']['high_risk_customers'] ?? [];
    $uncollected      = $report['internal_payload']['uncollected_losses'] ?? [];   // array of 3 items
    $problemContracts = $report['internal_payload']['problem_contracts'] ?? [];
    $demandDays       = $report['internal_payload']['demand_days'] ?? [];
    $pricingOpp       = $report['internal_payload']['price_opportunities'] ?? [];

    $aiResult = $report['ai_result'] ?? null;
    $risks    = $aiResult['risks'] ?? [];
    $opps     = $aiResult['opportunities'] ?? [];
    $actions  = $aiResult['action_plan'] ?? [];

    $periodLabel = $report['period'] ?? '';
    $startDate   = $report['period_start'] ?? '';
    $endDate     = $report['period_end'] ?? '';

    /* ── Logo: comes as base64 from controller's pdfImageSource() ── */
    $logoSrc = !empty($companyLogo) ? $companyLogo : null;
    /* DomPDF fallback: resolve from storage/app/public directly (no symlink needed) */
    if (!$logoSrc) {
        $rawLogoUrl = trim((string) (data_get($siteSettings ?? [], 'logo_url') ?? ''));
        if ($rawLogoUrl !== '') {
            if (str_starts_with($rawLogoUrl, 'data:') || preg_match('/^https?:\/\//i', $rawLogoUrl)) {
                $logoSrc = $rawLogoUrl;
            } else {
                /* Strip leading /storage/ and resolve against storage/app/public */
                $storagePath = storage_path('app/public/' . ltrim(preg_replace('#^/?storage/#', '', $rawLogoUrl), '/'));
                /* Also try public_path as a fallback (works when symlink exists) */
                $publicPath  = public_path(ltrim($rawLogoUrl, '/'));

                $resolved = null;
                foreach ([$storagePath, $publicPath] as $candidate) {
                    if (is_file($candidate)) { $resolved = $candidate; break; }
                }

                /* Last resort: search the directory for any image file (handles stale UUID in DB) */
                if (!$resolved) {
                    $dir = dirname($storagePath);
                    if (is_dir($dir)) {
                        $files = array_merge(
                            glob($dir . '/*.png') ?: [],
                            glob($dir . '/*.jpg') ?: [],
                            glob($dir . '/*.jpeg') ?: [],
                            glob($dir . '/*.gif') ?: [],
                            glob($dir . '/*.webp') ?: []
                        );
                        if ($files) { $resolved = $files[0]; }
                    }
                }

                if ($resolved) {
                    $c = @file_get_contents($resolved);
                    if ($c) {
                        $logoSrc = 'data:' . (mime_content_type($resolved) ?: 'image/png') . ';base64,' . base64_encode($c);
                    }
                }
            }
        }
    }
@endphp

<div class="page">

    {{-- ══════════════════════════════════════════════════════
         STANDARD DOCUMENT HEADER — same structure as all other PDFs
    ══════════════════════════════════════════════════════ --}}
    <table class="document-header">
        <tr>
            <td class="head-left">
                <div>{{ $headerCountryEn }}</div>
                <div>C.R : {{ $headerCrNumber }}</div>
                <div>P.O Box : {{ $headerPoBox }}</div>
                <div>P.C : {{ $headerPc }}</div>
                <div>GSM : {{ $headerGsm1 }}</div>
                <div>GSM : {{ $headerGsm2 }}</div>
                <div>GSM : {{ $headerGsm3 }}</div>
                <div class="serial">{{ $headerRegistryLabelEn }} <span>{{ $safeReportNumber }}</span></div>
            </td>
            <td class="head-main">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" class="logo" alt="" />
                @endif
                <div class="company-en">{{ strtoupper($headerCompanyNameEn) }}</div>
                <div class="company-ar ar">{{ $headerCompanyNameAr }}</div>
                <div class="title-line">AI INSIGHTS REPORT <span class="ar">تقرير تحليلات الذكاء الاصطناعي</span></div>
            </td>
            <td class="head-right ar">
                <div>{{ $headerCountryAr }}</div>
                <div>رقم السجل التجاري : {{ $headerCrNumber }}</div>
                <div>ص.ب : {{ $headerPoBox }}</div>
                <div>الرمز البريدي : {{ $headerPc }}</div>
                <div>نقال : {{ $headerGsm1 }}</div>
                <div>نقال : {{ $headerGsm2 }}</div>
                <div>نقال : {{ $headerGsm3 }}</div>
                <div class="serial ar">رقم التقرير : <span>{{ $safeReportNumber }}</span></div>
            </td>
        </tr>
    </table>

    <div class="thick-rule"></div>

    {{-- Meta row --}}
    <table class="report-top">
        <tr>
            <td>
                <div class="report-title">AI Insights Report</div>
                <div class="badge">{{ $periodLabel }}</div>
            </td>
            <td class="meta-box ar">
                <div><strong>تاريخ الإنشاء:</strong> {{ $generatedAt->format('Y-m-d H:i') }}</div>
                <div><strong>الفترة:</strong> {{ $startDate }} — {{ $endDate }}</div>
                @if(!empty($report['branch_name']))
                    <div><strong>الفرع:</strong> {{ $report['branch_name'] }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- ══════════════════════════════════════════════════════
         1. SUMMARY CARDS
    ══════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title ar">ملخص عام &nbsp;|&nbsp; Overview</div>
        <table class="cards-table">
            <tr>
                <td class="card-cell">
                    <div class="card-num">{{ $summary['unprofitable_cars_count'] ?? 0 }}</div>
                    <div class="card-lbl ar">سيارات غير مربحة</div>
                </td>
                <td class="card-cell">
                    <div class="card-num">{{ $summary['repeated_damage_cars_count'] ?? 0 }}</div>
                    <div class="card-lbl ar">أضرار متكررة</div>
                </td>
                <td class="card-cell">
                    <div class="card-num">{{ $summary['high_risk_customers_count'] ?? 0 }}</div>
                    <div class="card-lbl ar">عملاء خطرون</div>
                </td>
                <td class="card-cell">
                    <div class="card-num">{{ $summary['problem_contracts_count'] ?? 0 }}</div>
                    <div class="card-lbl ar">عقود مشكلة</div>
                </td>
                <td class="card-cell">
                    <div class="card-num">{{ $summary['pricing_opportunities_count'] ?? 0 }}</div>
                    <div class="card-lbl ar">فرص تسعير</div>
                </td>
                <td class="card-cell">
                    <div class="card-num {{ $summary['critical_count'] > 0 ? 'danger' : '' }}" style="{{ ($summary['critical_count'] ?? 0) > 0 ? 'color:#991b1b' : '' }}">{{ $summary['critical_count'] ?? 0 }}</div>
                    <div class="card-lbl ar">تنبيهات حرجة</div>
                </td>
            </tr>
        </table>
        @if(!empty($summary['formatted_uncollected_losses']))
        <div style="text-align:center; padding:2px 6px 3px; font-size:8px; color:#64748b; direction:rtl;">
            إجمالي الخسائر غير المحصّلة: <strong style="color:#991b1b;">{{ $summary['formatted_uncollected_losses'] }}</strong>
        </div>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════════════
         2. UNPROFITABLE CARS
    ══════════════════════════════════════════════════════ --}}
    @if(count($unprofitable))
    <div class="section">
        <div class="section-title ar">سيارات لا تحقق أرباحاً</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="r">السيارة</th>
                    <th class="r">اللوحة</th>
                    <th class="r">الإيرادات</th>
                    <th class="r">صافي الربح</th>
                    <th class="r">التوصية</th>
                </tr>
            </thead>
            <tbody>
                @foreach($unprofitable as $car)
                <tr>
                    <td class="r ar">{{ $car['car_name'] ?? '-' }}</td>
                    <td class="r">{{ $car['license_plate'] ?? '-' }}</td>
                    <td class="r">{{ $car['formatted_revenue'] ?? $car['revenue'] ?? 0 }}</td>
                    <td class="r"><span class="pill danger">{{ $car['formatted_net_profit'] ?? $car['net_profit'] ?? 0 }}</span></td>
                    <td class="r ar" style="color:#64748b;font-size:7px;">{{ Str::limit($car['recommendation'] ?? '', 60) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════
         3. HIGH DAMAGE CARS
    ══════════════════════════════════════════════════════ --}}
    @if(count($highDamage))
    <div class="section">
        <div class="section-title ar">سيارات كثيرة الأضرار</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="r">السيارة</th>
                    <th class="r">اللوحة</th>
                    <th class="r">تقارير الأضرار</th>
                    <th class="r">الحوادث</th>
                    <th class="r">التكلفة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($highDamage as $car)
                <tr>
                    <td class="r ar">{{ $car['car_name'] ?? '-' }}</td>
                    <td class="r">{{ $car['license_plate'] ?? '-' }}</td>
                    <td class="r"><span class="pill warning">{{ $car['damage_reports_count'] ?? 0 }}</span></td>
                    <td class="r">{{ $car['accidents_count'] ?? 0 }}</td>
                    <td class="r">{{ $car['formatted_costs'] ?? $car['costs'] ?? 0 }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════
         4. HIGH-RISK CUSTOMERS
    ══════════════════════════════════════════════════════ --}}
    @if(count($highRisk))
    <div class="section">
        <div class="section-title ar">عملاء عالي الخطورة</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="r">العميل</th>
                    <th class="r">الحجوزات</th>
                    <th class="r">مبلغ غير مدفوع</th>
                    <th class="r">أضرار</th>
                    <th class="r">عقود متأخرة</th>
                    <th class="r">الدرجة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($highRisk as $c)
                <tr>
                    <td class="r ar">{{ $c['name'] ?? '-' }}</td>
                    <td class="r">{{ $c['reservations_count'] ?? 0 }}</td>
                    <td class="r">{{ $c['formatted_unpaid_amount'] ?? $c['unpaid_amount'] ?? 0 }}</td>
                    <td class="r">{{ $c['damage_reports_count'] ?? 0 }}</td>
                    <td class="r">{{ $c['overdue_contracts_count'] ?? 0 }}</td>
                    <td class="r"><span class="pill {{ ($c['severity'] ?? 'info') === 'danger' ? 'danger' : (($c['severity'] ?? 'info') === 'warning' ? 'warning' : 'info') }}">{{ $c['score'] ?? 0 }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════
         5. UNCOLLECTED LOSSES (3 categories)
    ══════════════════════════════════════════════════════ --}}
    @if(count($uncollected))
    <div class="section">
        <div class="section-title ar">خسائر غير محصّلة</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="r">البند</th>
                    <th class="r">المبلغ</th>
                </tr>
            </thead>
            <tbody>
                @foreach($uncollected as $loss)
                <tr>
                    <td class="r ar">{{ $loss['label'] ?? $loss['key'] ?? '-' }}</td>
                    <td class="r"><span class="pill {{ ($loss['amount'] ?? 0) > 0 ? 'danger' : 'info' }}">{{ $loss['formatted_amount'] ?? $loss['amount'] ?? 0 }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════
         6. PROBLEM CONTRACTS
    ══════════════════════════════════════════════════════ --}}
    @if(count($problemContracts))
    <div class="section">
        <div class="section-title ar">عقود معرضة للمشاكل</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="r">رقم العقد</th>
                    <th class="r">العميل</th>
                    <th class="r">السيارة</th>
                    <th class="r">تاريخ الانتهاء</th>
                    <th class="r">تأخر (أيام)</th>
                    <th class="r">الدرجة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($problemContracts as $contract)
                <tr>
                    <td class="r">{{ $contract['contract_number'] ?? '-' }}</td>
                    <td class="r ar">{{ $contract['customer_name'] ?? '-' }}</td>
                    <td class="r ar">{{ $contract['car_name'] ?? '-' }}</td>
                    <td class="r">{{ $contract['end_date'] ?? '-' }}</td>
                    <td class="r">{{ $contract['days_late'] ?? 0 }}</td>
                    <td class="r"><span class="pill {{ ($contract['severity'] ?? 'warning') === 'danger' ? 'danger' : 'warning' }}">{{ $contract['score'] ?? 0 }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════
         7. PRICING OPPORTUNITIES
    ══════════════════════════════════════════════════════ --}}
    @if(count($pricingOpp))
    <div class="section">
        <div class="section-title ar">فرص تحسين الأسعار</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="r">السيارة</th>
                    <th class="r">اللوحة</th>
                    <th class="r">السعر الحالي / يوم</th>
                    <th class="r">أيام الاستخدام</th>
                    <th class="r">نسبة الربح</th>
                    <th class="r">مقترح الزيادة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pricingOpp as $opp)
                <tr>
                    <td class="r ar">{{ $opp['car_name'] ?? '-' }}</td>
                    <td class="r">{{ $opp['license_plate'] ?? '-' }}</td>
                    <td class="r">{{ $opp['formatted_current_price'] ?? $opp['current_price'] ?? 0 }}</td>
                    <td class="r">{{ $opp['utilization_days'] ?? 0 }}</td>
                    <td class="r">{{ $opp['profit_margin'] ?? 0 }}%</td>
                    <td class="r"><span class="pill success">+{{ $opp['suggested_increase_percent'] ?? 0 }}%</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════
         8. HIGH DEMAND DAYS
    ══════════════════════════════════════════════════════ --}}
    @if(count($demandDays))
    <div class="section">
        <div class="section-title ar">أيام الطلب المرتفع</div>
        <table class="data-table">
            <thead>
                <tr>
                    @foreach($demandDays as $d)
                    <th class="r">{{ $d['day'] ?? '-' }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    @foreach($demandDays as $d)
                    <td class="r"><span class="pill info">{{ $d['reservations_count'] ?? 0 }}</span></td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════
         9. AI MARKET STUDY (OpenAI results)
    ══════════════════════════════════════════════════════ --}}
    @if($aiResult)
        {{-- Risks --}}
        @if(count($risks))
        <div class="section">
            <div class="section-title ar">⚠ المخاطر — تحليل OpenAI</div>
            @foreach($risks as $risk)
            <div style="direction:rtl; padding:2px 7px; border-bottom:1px solid #f1f5f9;">
                <span class="pill {{ ($risk['severity'] ?? 'medium') === 'high' || ($risk['severity'] ?? '') === 'critical' ? 'danger' : (($risk['severity'] ?? '') === 'medium' ? 'warning' : 'info') }}">{{ $risk['severity'] ?? '' }}</span>
                &nbsp;<strong>{{ $risk['title'] ?? '' }}</strong>
                @if(!empty($risk['description']))
                    <span style="color:#64748b;"> — {{ Str::limit($risk['description'], 130) }}</span>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        {{-- Opportunities --}}
        @if(count($opps))
        <div class="section">
            <div class="section-title ar">✦ الفرص — تحليل OpenAI</div>
            @foreach($opps as $opp)
            <div style="direction:rtl; padding:2px 7px; border-bottom:1px solid #f1f5f9;">
                <span class="pill success">{{ $opp['impact'] ?? '' }}</span>
                &nbsp;<strong>{{ $opp['title'] ?? '' }}</strong>
                @if(!empty($opp['description']))
                    <span style="color:#64748b;"> — {{ Str::limit($opp['description'], 130) }}</span>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        {{-- Action Plan --}}
        @if(count($actions))
        <div class="section">
            <div class="section-title ar">✔ خطة الإجراءات — OpenAI</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th class="r">الإجراء</th>
                        <th class="r">الأولوية</th>
                        <th class="r">التوقيت</th>
                        <th class="r">المسؤول</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($actions as $i => $action)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td class="r ar">{{ $action['action'] ?? '-' }}</td>
                        <td class="r"><span class="pill {{ ($action['priority'] ?? '') === 'high' ? 'danger' : (($action['priority'] ?? '') === 'medium' ? 'warning' : 'info') }}">{{ $action['priority'] ?? '-' }}</span></td>
                        <td class="r ar">{{ $action['timeline'] ?? '-' }}</td>
                        <td class="r ar">{{ $action['owner'] ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    @endif

    {{-- Footer --}}
    <div class="footer ar" style="direction:rtl;">
        تم إنشاء هذا التقرير بواسطة نظام تحليلات الذكاء الاصطناعي &nbsp;|&nbsp; {{ $generatedAt->format('Y-m-d H:i') }}
        @if(!empty($report['created_by_name'])) &nbsp;|&nbsp; أنشأه: {{ $report['created_by_name'] }} @endif
    </div>

</div>
</body>
</html>
