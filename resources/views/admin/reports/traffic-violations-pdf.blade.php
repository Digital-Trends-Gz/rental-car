<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Traffic Violations Report</title>
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
        @page { size: A4 portrait; margin: 4mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: cairo, "TahomaPdf", "DejaVu Sans", Arial, sans-serif;
            font-size: 10px;
            color: #111827;
            background: #fff;
        }
        .ar {
            direction: rtl;
            unicode-bidi: plaintext;
            font-family: cairo, "TahomaPdf", "DejaVu Sans", Arial, sans-serif;
        }
        .page {
            min-height: 287mm;
            border: 2px solid #17306f;
            padding: 4px 8px 8px;
        }
        .document-header {
            width: 100%;
            border-collapse: collapse;
            color: #17306f;
        }
        .document-header td {
            vertical-align: top;
            padding: 2px 6px;
        }
        .head-left,
        .head-right {
            width: 24%;
            font-size: 7px;
            line-height: 1.25;
            font-weight: 700;
        }
        .head-right { text-align: right; }
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
            margin-top: 1px;
            font-size: 11px;
            line-height: 1.1;
            font-weight: 900;
            color: #17306f;
            text-transform: uppercase;
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
            margin: 2px 0 6px;
        }
        .topbar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .topbar td {
            padding: 0;
            vertical-align: top;
        }
        .report-title {
            font-size: 22px;
            font-weight: 900;
            color: #111827;
        }
        .meta {
            text-align: right;
            color: #475569;
            line-height: 1.5;
            font-weight: 700;
        }
        .badge {
            display: inline-block;
            margin-top: 3px;
            padding: 2px 8px;
            border-radius: 999px;
            background: #dbeafe;
            color: #2563eb;
            font-weight: 800;
        }
        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .summary td {
            width: 16.666%;
            padding: 3px;
        }
        .card {
            min-height: 55px;
            padding: 7px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            background: #f8fafc;
        }
        .label {
            color: #64748b;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .value {
            margin-top: 5px;
            color: #17306f;
            font-size: 15px;
            font-weight: 900;
        }
        .section-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .section-grid > tbody > tr > td {
            width: 50%;
            padding: 4px;
            vertical-align: top;
        }
        .section {
            margin-bottom: 7px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .section-title {
            padding: 6px 8px;
            background: #f1f5f9;
            color: #17306f;
            font-size: 12px;
            font-weight: 900;
            border-bottom: 1px solid #cbd5e1;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th,
        .data-table td {
            padding: 5px 7px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .data-table th {
            background: #f8fafc;
            color: #475569;
            text-align: left;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .data-table tr:last-child td { border-bottom: 0; }
        .money {
            color: #0f172a;
            font-weight: 900;
            white-space: nowrap;
        }
        .status {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 999px;
            background: #eef2ff;
            color: #17306f;
            font-size: 8px;
            font-weight: 800;
        }
        .muted {
            color: #64748b;
            font-size: 8px;
        }
        .footer {
            margin-top: 8px;
            color: #64748b;
            text-align: center;
            font-size: 8px;
        }
    </style>
</head>
<body>
@php
    $report = $trafficViolationsReport ?? ['summary' => [], 'by_client' => [], 'by_car' => [], 'recent_violations' => []];
    $summary = $report['summary'] ?? [];
    $byClient = collect($report['by_client'] ?? []);
    $byCar = collect($report['by_car'] ?? []);
    $recentViolations = collect($report['recent_violations'] ?? []);
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
    $safeReportNumber = $reportNumber ?? 'TVR-'.now()->format('Ymd-Hi');
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
                @if(! empty($companyLogo))
                    <img src="{{ $companyLogo }}" class="logo" alt="Logo">
                @endif
                <div class="company-en">{{ strtoupper($headerCompanyNameEn) }}</div>
                <div class="company-ar ar">{{ $headerCompanyNameAr }}</div>
                <div class="title-line">TRAFFIC VIOLATIONS REPORT <span class="ar">تقرير المخالفات</span></div>
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

    <table class="topbar">
        <tr>
            <td>
                <div class="report-title">Traffic Violations Report</div>
                <span class="badge">{{ $periodLabel }}</span>
            </td>
            <td class="meta">
                <div>Generated: {{ $generatedAt->format('Y-m-d H:i') }}</div>
                <div>Branch: {{ $branchName }}</div>
                <div>Date Range: {{ $dateRange['start']->format('Y-m-d') }} to {{ $dateRange['end']->format('Y-m-d') }}</div>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td><div class="card"><div class="label">Total violations</div><div class="value">{{ $summary['total_violations'] ?? 0 }}</div></div></td>
            <td><div class="card"><div class="label">Open</div><div class="value">{{ $summary['open_violations'] ?? 0 }}</div></div></td>
            <td><div class="card"><div class="label">Paid</div><div class="value">{{ $summary['paid_violations'] ?? 0 }}</div></div></td>
            <td><div class="card"><div class="label">Unpaid</div><div class="value">{{ $summary['unpaid_violations'] ?? 0 }}</div></div></td>
            <td><div class="card"><div class="label">Total amount</div><div class="value">{{ $summary['formatted_total_amount'] ?? '$0.00' }}</div></div></td>
            <td><div class="card"><div class="label">Unpaid amount</div><div class="value">{{ $summary['formatted_unpaid_amount'] ?? '$0.00' }}</div></div></td>
        </tr>
    </table>

    <table class="section-grid">
        <tr>
            <td>
                <div class="section">
                    <div class="section-title">By Client <span class="ar"> حسب العميل |</span></div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Violations</th>
                                <th>Paid</th>
                                <th>Unpaid</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($byClient as $row)
                                <tr>
                                    <td>
                                        <strong>{{ $row['client_name'] ?? '-' }}</strong>
                                        <div class="muted">{{ $row['client_email'] ?? '-' }}</div>
                                    </td>
                                    <td>{{ $row['violations_count'] ?? 0 }}</td>
                                    <td>{{ $row['paid_count'] ?? 0 }}</td>
                                    <td>{{ $row['unpaid_count'] ?? 0 }}</td>
                                    <td class="money">{{ $row['formatted_total_amount'] ?? '$0.00' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" style="text-align: center; color: #64748b;">No client violations.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </td>
            <td>
                <div class="section">
                    <div class="section-title">By Vehicle <span class="ar"> حسب السيارة |</span></div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Plate</th>
                                <th>Violations</th>
                                <th>Unpaid</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($byCar as $row)
                                <tr>
                                    <td><strong>{{ $row['car_name'] ?? '-' }}</strong></td>
                                    <td>{{ $row['license_plate'] ?? '-' }}</td>
                                    <td>{{ $row['violations_count'] ?? 0 }}</td>
                                    <td>{{ $row['unpaid_count'] ?? 0 }}</td>
                                    <td class="money">{{ $row['formatted_total_amount'] ?? '$0.00' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" style="text-align: center; color: #64748b;">No vehicle violations.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">Recent Violations <span class="ar">أحدث المخالفات |</span></div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Violation #</th>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Vehicle</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentViolations as $row)
                    <tr>
                        <td><strong>{{ $row['violation_number'] ?? '-' }}</strong></td>
                        <td>{{ $row['violation_date'] ?? '-' }}</td>
                        <td>{{ $row['client_name'] ?? '-' }}</td>
                        <td>
                            <strong>{{ $row['car_name'] ?? '-' }}</strong>
                            <div class="muted">{{ $row['license_plate'] ?? '-' }}</div>
                        </td>
                        <td>{{ $row['type_name'] ?? '-' }}</td>
                        <td><span class="status">{{ $row['status_label'] ?? '-' }}</span></td>
                        <td class="money">{{ $row['formatted_amount'] ?? '$0.00' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align: center; color: #64748b;">No traffic violations in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="footer">Generated by Car4u.</div>
</div>
</body>
</html>
