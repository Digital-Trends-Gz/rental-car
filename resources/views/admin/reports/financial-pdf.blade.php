<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Financial Report</title>
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
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: cairo, "DejaVu Sans", Arial, sans-serif;
            font-size: 11px;
            color: #111827;
            background: #ffffff;
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
            direction: rtl;
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
            direction: rtl;
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
            margin: 2px 0 6px;
        }
        .report-top {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .report-title {
            font-size: 22px;
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
            margin-top: 8px;
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
            padding: 6px 10px;
            border-bottom: 1px solid #cbd5e1;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th,
        .data-table td {
            padding: 4px 8px;
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
        .ar {
            direction: rtl;
            unicode-bidi: plaintext;
            font-family: cairo, "DejaVu Sans", Arial, sans-serif;
        }
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
    $safeReportNumber = $reportNumber ?? 'FIN-'.now()->format('Ymd-Hi');
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
                <div class="title-line">FINANCIAL REPORT <span class="ar">التقرير المالي</span></div>
            </td>
            <td class="head-right ar">
                <div>{{ $headerCountryAr }}</div>
                <div>رقم السجل التجاري : {{ $headerCrNumber }}</div>
                <div>ص.ب : {{ $headerPoBox }}</div>
                <div>الرمز البريدي : {{ $headerPc }}</div>
                <div>نقال : {{ $headerGsm1 }}</div>
                <div>نقال : {{ $headerGsm2 }}</div>
                <div>نقال : {{ $headerGsm3 }}</div>
                <div class="serial">رقم التقرير : <span>{{ $safeReportNumber }}</span></div>
            </td>
        </tr>
    </table>

    <div class="thick-rule"></div>

    <table class="report-top">
        <tr>
            <td>
                <div class="report-title">Financial Report</div>
                <span class="badge">{{ $periodLabel }}</span>
            </td>
            <td class="meta-box">
                <div>Generated: {{ $generatedAt->format('Y-m-d H:i') }}</div>
                <div>Branch: {{ $branchName }}</div>
                <div>Date Range: {{ $dateRange['start']->format('Y-m-d') }} to {{ $dateRange['end']->format('Y-m-d') }}</div>
            </td>
        </tr>
    </table>

    @foreach($financialReportSections as $section)
        <div class="section">
            <div class="section-title">
                {{ $section['title']['en'] }} <span class="ar">| {{ $section['title']['ar'] }}</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th>Arabic</th>
                        <th>Amount</th>
                        <th>Records</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($section['items'] as $item)
                        <tr>
                            <td>{{ $item['en'] }}</td>
                            <td class="ar">{{ $item['ar'] }}</td>
                            <td class="metric-value">{{ $item['formatted'] ?? '$0.00' }}</td>
                            <td>{{ $item['count'] ?? 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="footer">Generated by Car4u.</div>
</div>
</body>
</html>
