<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Executive Report</title>
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
        @font-face {
            font-family: "TahomaPdf";
            src: url("{{ public_path('fonts/tahoma.ttf') }}") format("truetype");
            font-weight: 400;
            font-style: normal;
        }
        @font-face {
            font-family: "TahomaPdf";
            src: url("{{ public_path('fonts/tahomabd.ttf') }}") format("truetype");
            font-weight: 700 900;
            font-style: normal;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: cairo, "TahomaPdf", "DejaVu Sans", Arial, sans-serif;
            font-size: 9px;
            color: #111827;
            background: #ffffff;
        }
        .ar {
            direction: rtl;
            unicode-bidi: plaintext;
            font-family: cairo, "TahomaPdf", "DejaVu Sans", Arial, sans-serif;
        }
        .page {
            border: 2px solid #17306f;
            padding: 4px 8px 6px;
        }
        .document-header {
            width: 100%;
            border-collapse: collapse;
            color: #17306f;
            margin-bottom: 2px;
        }
        .document-header td {
            padding: 2px 4px;
            vertical-align: top;
        }
        .head-left,
        .head-right {
            width: 24%;
            font-size: 7px;
            line-height: 1.2;
            font-weight: 700;
        }
        .head-right {
            text-align: right;
        }
        .head-main {
            width: 52%;
            text-align: center;
            border-left: 1px solid #cbd5e1;
            border-right: 1px solid #cbd5e1;
        }
        .logo {
            max-height: 30px;
            max-width: 150px;
            object-fit: contain;
            margin-bottom: 1px;
        }
        .company-en {
            font-size: 18px;
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: .4px;
            color: #17306f;
            text-transform: uppercase;
        }
        .company-ar {
            font-size: 14px;
            line-height: 1.05;
            font-weight: 900;
            color: #17306f;
        }
        .title-line {
            font-size: 11px;
            line-height: 1.1;
            font-weight: 900;
            color: #17306f;
            margin-top: 1px;
        }
        .title-line .ar {
            display: inline-block;
            margin-left: 8px;
            font-size: 11px;
        }
        .serial {
            margin-top: 3px;
            font-size: 8px;
        }
        .serial span {
            color: #dc2626;
            font-size: 10px;
            font-weight: 900;
        }
        .thick-rule {
            height: 4px;
            background: #17306f;
            margin: 1px 0 4px;
        }
        .report-top {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .report-top td {
            padding: 0;
            vertical-align: top;
        }
        .report-title {
            font-size: 18px;
            font-weight: 900;
            color: #111827;
            line-height: 1.1;
        }
        .meta-box {
            text-align: right;
            color: #475569;
            font-size: 9px;
            line-height: 1.3;
            font-weight: 700;
        }
        .badge {
            display: inline-block;
            border-radius: 999px;
            background: #dbeafe;
            color: #2563eb;
            font-size: 8px;
            font-weight: 800;
            padding: 2px 6px;
            margin-top: 2px;
        }
        .section {
            margin-top: 4px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .section-title {
            background: #f1f5f9;
            color: #17306f;
            font-size: 11px;
            font-weight: 900;
            padding: 4px 8px;
            border-bottom: 1px solid #cbd5e1;
        }
        .section-title .ar {
            display: inline-block;
            margin-left: 6px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th,
        .data-table td {
            padding: 2px 6px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .data-table tr:last-child td {
            border-bottom: 0;
        }
        .data-table th {
            background: #f8fafc;
            color: #475569;
            text-align: left;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: .02em;
        }
        .metric-value {
            font-size: 11px;
            font-weight: 900;
            color: #0f172a;
        }
        .alert-pill {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 800;
        }
        .danger { background: #fee2e2; color: #991b1b; }
        .warning { background: #fef3c7; color: #92400e; }
        .info { background: #dbeafe; color: #1e40af; }
        .success { background: #dcfce7; color: #166534; }
        .footer {
            margin-top: 8px;
            color: #64748b;
            font-size: 8px;
            text-align: center;
        }
    </style>
</head>
<body>
@php
    $pdfHeader = $pdfHeader ?? data_get($siteSettings ?? [], 'pdf_header', []);
    $headerCompanyNameEn = data_get($pdfHeader, 'company_name.en') ?: ($companyName ?? $tenant?->name ?? config('app.name'));
    $headerCompanyNameAr = data_get($pdfHeader, 'company_name.ar') ?: $headerCompanyNameEn;
    $headerCrNumber = data_get($pdfHeader, 'cr_number') ?: '-';
    $headerPoBox = data_get($pdfHeader, 'po_box') ?: '-';
    $headerPc = data_get($pdfHeader, 'pc') ?: '-';
    $headerCountryEn = data_get($pdfHeader, 'country.en') ?: 'Sultanate of Oman';
    $headerCountryAr = data_get($pdfHeader, 'country.ar') ?: 'سلطنة عمان';
    $headerGsm1 = data_get($pdfHeader, 'gsm_1') ?: '-';
    $headerGsm2 = data_get($pdfHeader, 'gsm_2') ?: '-';
    $headerGsm3 = data_get($pdfHeader, 'gsm_3') ?: '-';
    $headerRegistryLabelEn = data_get($pdfHeader, 'registry_label.en') ?: 'No.';
    $headerRegistryLabelAr = data_get($pdfHeader, 'registry_label.ar') ?: 'رقم التقرير';
    $safeReportNumber = $reportNumber ?? 'EXR-'.now()->format('Ymd-Hi');
@endphp

<div class="page">
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
                @if(!empty($companyLogo))
                    <img src="{{ $companyLogo }}" class="logo" alt="Logo" />
                @endif
                <div class="company-en">{{ strtoupper($headerCompanyNameEn) }}</div>
                <div class="company-ar ar">{{ $headerCompanyNameAr }}</div>
                <div class="title-line">EXECUTIVE REPORT <span class="ar">التقرير التنفيذي</span></div>
            </td>
            <td class="head-right ar">
                <div>{{ $headerCountryAr }}</div>
                <div>رقم السجل التجاري : {{ $headerCrNumber }}</div>
                <div>ص.ب : {{ $headerPoBox }}</div>
                <div>الرمز البريدي : {{ $headerPc }}</div>
                <div>نقال : {{ $headerGsm1 }}</div>
                <div>نقال : {{ $headerGsm2 }}</div>
                <div>نقال : {{ $headerGsm3 }}</div>
                <div class="serial">الرقم التقرير : <span>{{ $safeReportNumber }}</span></div>
            </td>
        </tr>
    </table>

    <div class="thick-rule"></div>

    <table class="report-top">
        <tr>
            <td>
                <div class="report-title">Fleet Report</div>
                <div class="badge">{{ $periodLabel }}</div>
            </td>
            <td class="meta-box">
                <div><strong>Generated:</strong> {{ \Carbon\Carbon::now()->format('Y-m-d H:i') }}</div>
                <div><strong>Branch:</strong> {{ $branchName ?? 'All Branches' }}</div>
                <div>
                    <strong>Date Range:</strong>
                    {{ $dateRange['start']->format('Y-m-d') }} to {{ $dateRange['end']->format('Y-m-d') }}
                </div>
            </td>
        </tr>
    </table>

    <table style="width: 100%; border-collapse: collapse; margin-top: 4px;">
        <tr>
            <td style="width: 49%; vertical-align: top; padding: 0;">
                <div class="section" style="margin-top: 0;">
                    <div class="section-title">Utilization <span class="ar">الاستخدام</span></div>
                    <table class="data-table">
                        <tbody>
                            <tr>
                                <td>Fleet utilization <br><span class="ar">نسبة تشغيل الأسطول</span></td>
                                <td class="metric-value">{{ $fleetReport['utilization']['utilization_rate'] }}%</td>
                            </tr>
                            <tr>
                                <td>Rented days per car <br><span class="ar">عدد الأيام المؤجرة لكل سيارة</span></td>
                                <td class="metric-value">{{ $fleetReport['utilization']['rented_days_per_car'] }}</td>
                            </tr>
                            <tr>
                                <td>Idle days <br><span class="ar">عدد الأيام المتوقفة</span></td>
                                <td class="metric-value">{{ $fleetReport['utilization']['idle_days'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="section">
                    <div class="section-title">Top Cars <span class="ar">أفضل السيارات</span></div>
                    <table class="data-table">
                        <tbody>
                            @if(!empty($fleetReport['top_cars']['revenue']))
                            <tr>
                                <td>Highest revenue <br><span class="ar">أعلى إيراد</span></td>
                                <td class="metric-value">{{ $fleetReport['top_cars']['revenue']['name'] }} ({{ $fleetReport['top_cars']['revenue']['value'] }})</td>
                            </tr>
                            @endif
                            @if(!empty($fleetReport['top_cars']['utilization']))
                            <tr>
                                <td>Highest utilization <br><span class="ar">أعلى استخدام</span></td>
                                <td class="metric-value">{{ $fleetReport['top_cars']['utilization']['name'] }} ({{ $fleetReport['top_cars']['utilization']['value'] }})</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                
                <div class="section">
                    <div class="section-title">Worst Cars <span class="ar">أسوأ السيارات</span></div>
                    <table class="data-table">
                        <tbody>
                            @if(!empty($fleetReport['worst_cars']['utilization']))
                            <tr>
                                <td>Lowest utilization <br><span class="ar">أقل استخدام</span></td>
                                <td class="metric-value">{{ $fleetReport['worst_cars']['utilization']['name'] }} ({{ $fleetReport['worst_cars']['utilization']['value'] }})</td>
                            </tr>
                            @endif
                            @if(!empty($fleetReport['worst_cars']['revenue']))
                            <tr>
                                <td>Lowest revenue <br><span class="ar">أقل إيراد</span></td>
                                <td class="metric-value">{{ $fleetReport['worst_cars']['revenue']['name'] }} ({{ $fleetReport['worst_cars']['revenue']['value'] }})</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 49%; vertical-align: top; padding: 0;">
                <div class="section" style="margin-top: 0;">
                    <div class="section-title">Fleet Status <span class="ar">حالة الأسطول</span></div>
                    <table class="data-table">
                        <tbody>
                            <tr>
                                <td>Available <br><span class="ar">متاحة</span></td>
                                <td class="metric-value">{{ $fleetReport['status_counts']['available'] }}</td>
                            </tr>
                            <tr>
                                <td>Rented <br><span class="ar">مؤجرة</span></td>
                                <td class="metric-value">{{ $fleetReport['status_counts']['rented'] }}</td>
                            </tr>
                            <tr>
                                <td>Reserved <br><span class="ar">محجوزة</span></td>
                                <td class="metric-value">{{ $fleetReport['status_counts']['reserved'] }}</td>
                            </tr>
                            <tr>
                                <td>Maintenance <br><span class="ar">صيانة</span></td>
                                <td class="metric-value">{{ $fleetReport['status_counts']['maintenance'] }}</td>
                            </tr>
                            <tr>
                                <td>Out of service <br><span class="ar">خارج الخدمة</span></td>
                                <td class="metric-value">{{ $fleetReport['status_counts']['out_of_service'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="section" style="margin-top: 10px;">
                    <div class="section-title">Top 10 Cars by Revenue <span class="ar">أفضل 10 سيارات بالإيراد</span></div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Car / السيارة</th>
                                <th>Revenue / الإيراد</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(array_slice($fleetReport['rankings']['revenue'], 0, 10) as $index => $car)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $car['car_name'] }}</td>
                                    <td class="metric-value">{{ $car['formatted_revenue'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Generated by {{ config('app.name') }}.
    </div>
</div>
</body>
</html>
