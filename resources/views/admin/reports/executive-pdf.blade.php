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
            font-size: 11px;
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
            padding: 8px 12px 14px;
        }
        .document-header {
            width: 100%;
            border-collapse: collapse;
            color: #17306f;
            margin-bottom: 8px;
        }
        .document-header td {
            padding: 4px 8px;
            vertical-align: top;
        }
        .head-left,
        .head-right {
            width: 24%;
            font-size: 8px;
            line-height: 1.35;
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
            max-height: 38px;
            max-width: 170px;
            object-fit: contain;
            margin-bottom: 2px;
        }
        .company-en {
            font-size: 23px;
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: .4px;
            color: #17306f;
            text-transform: uppercase;
        }
        .company-ar {
            font-size: 18px;
            line-height: 1.05;
            font-weight: 900;
            color: #17306f;
        }
        .title-line {
            font-size: 13px;
            line-height: 1.1;
            font-weight: 900;
            color: #17306f;
            margin-top: 2px;
        }
        .title-line .ar {
            display: inline-block;
            margin-left: 8px;
            font-size: 13px;
        }
        .serial {
            margin-top: 5px;
            font-size: 9px;
        }
        .serial span {
            color: #dc2626;
            font-size: 11px;
            font-weight: 900;
        }
        .thick-rule {
            height: 5px;
            background: #17306f;
            margin: 2px 0 10px;
        }
        .report-top {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .report-top td {
            padding: 0;
            vertical-align: top;
        }
        .report-title {
            font-size: 21px;
            font-weight: 900;
            color: #111827;
            line-height: 1.15;
        }
        .meta-box {
            text-align: right;
            color: #475569;
            font-size: 10px;
            line-height: 1.5;
            font-weight: 700;
        }
        .badge {
            display: inline-block;
            border-radius: 999px;
            background: #dbeafe;
            color: #2563eb;
            font-size: 9px;
            font-weight: 800;
            padding: 3px 8px;
            margin-top: 4px;
        }
        .section {
            margin-top: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .section-title {
            background: #f1f5f9;
            color: #17306f;
            font-size: 13px;
            font-weight: 900;
            padding: 8px 10px;
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
            padding: 8px 10px;
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
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .02em;
        }
        .metric-value {
            font-size: 14px;
            font-weight: 900;
            color: #0f172a;
        }
        .alert-pill {
            display: inline-block;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
        }
        .danger { background: #fee2e2; color: #991b1b; }
        .warning { background: #fef3c7; color: #92400e; }
        .info { background: #dbeafe; color: #1e40af; }
        .success { background: #dcfce7; color: #166534; }
        .footer {
            margin-top: 14px;
            color: #64748b;
            font-size: 10px;
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
                <div class="serial">{{ $headerRegistryLabelAr }} : <span>{{ $safeReportNumber }}</span></div>
            </td>
        </tr>
    </table>

    <div class="thick-rule"></div>

    <table class="report-top">
        <tr>
            <td>
                <div class="report-title">Executive Report</div>
                <div class="badge">{{ $periodLabel }}</div>
            </td>
            <td class="meta-box">
                <div><strong>Generated:</strong> {{ $generatedAt->format('Y-m-d H:i') }}</div>
                <div><strong>Branch:</strong> {{ $branchName }}</div>
                <div>
                    <strong>Date Range:</strong>
                    {{ $dateRange['start']->format('Y-m-d') }} to {{ $dateRange['end']->format('Y-m-d') }}
                </div>
            </td>
        </tr>
    </table>

    <div class="section" style="display: none;">
        <div class="section-title">Financial Report <span class="ar">التقرير المالي</span></div>
        <table class="data-table">
            <tbody>
                @foreach([] as $section)
                    <tr>
                        <td colspan="2" style="background:#f8fafc;font-weight:bold;">
                            {{ $section['title']['ar'] }} <span class="ar">/ {{ $section['title']['en'] }}</span>
                        </td>
                    </tr>
                    @foreach($section['items'] as $item)
                        <tr>
                            <td style="padding-left:18px;">&bull; {{ $item['ar'] }}</td>
                            <td>{{ $item['en'] }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Financial Summary <span class="ar">الملخص المالي</span></div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['financial'] as $metric)
                    <tr>
                        <td>{{ $metric['label'] }}</td>
                        <td class="metric-value">{{ $metric['formatted'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Operations Summary <span class="ar">مؤشرات التشغيل</span></div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['operations'] as $metric)
                    <tr>
                        <td>{{ $metric['label'] }}</td>
                        <td class="metric-value">{{ $metric['formatted'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Action Alerts <span class="ar">التنبيهات</span></div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Alert</th>
                    <th>Description</th>
                    <th>Count</th>
                    <th>Severity</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['alerts'] as $alert)
                    <tr>
                        <td>{{ $alert['label'] }}</td>
                        <td>{{ $alert['description'] }}</td>
                        <td class="metric-value">{{ number_format($alert['value']) }}</td>
                        <td>
                            <span class="alert-pill {{ $alert['severity'] }}">
                                {{ ucfirst($alert['severity']) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        Generated by {{ config('app.name') }}.
    </div>
</div>
</body>
</html>
