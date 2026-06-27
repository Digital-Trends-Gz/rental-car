<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Open Contracts Report</title>
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
        .status-danger {
            background: #fee2e2;
            color: #b91c1c;
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
    $report = $openContractsReport ?? ['summary' => [], 'contracts' => [], 'ending_soon' => []];
    $summary = $report['summary'] ?? [];
    $contracts = collect($report['contracts'] ?? []);
    $endingSoon = collect($report['ending_soon'] ?? []);
    $pdfHeader = $pdfHeader ?? data_get($siteSettings ?? [], 'pdf_header', []);
    $headerCompanyNameEn = data_get($pdfHeader, 'company_name.en') ?: ($companyName ?? $tenant?->name ?? config('app.name'));
    $headerCompanyNameAr = data_get($pdfHeader, 'company_name.ar') ?: $headerCompanyNameEn;
    $headerCrNumber = data_get($pdfHeader, 'cr_number') ?: '-';
    $headerPoBox = data_get($pdfHeader, 'po_box') ?: '-';
    $headerPc = data_get($pdfHeader, 'pc') ?: '-';
    $headerCountryEn = data_get($pdfHeader, 'country.en') ?: 'Sultanate of Oman';
    $headerCountryAr = data_get($pdfHeader, 'country.ar') ?: '&#1587;&#1604;&#1591;&#1606;&#1577; &#1593;&#1605;&#1575;&#1606;';
    $headerGsm1 = data_get($pdfHeader, 'gsm_1') ?: '-';
    $headerGsm2 = data_get($pdfHeader, 'gsm_2') ?: '-';
    $headerGsm3 = data_get($pdfHeader, 'gsm_3') ?: '-';
    $headerRegistryLabelEn = data_get($pdfHeader, 'registry_label.en') ?: 'No.';
    $safeReportNumber = $reportNumber ?? 'OCR-'.now()->format('Ymd-Hi');
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
                <div class="title-line">OPEN CONTRACTS REPORT <span class="ar">&#1578;&#1602;&#1585;&#1610;&#1585; &#1575;&#1604;&#1593;&#1602;&#1608;&#1583; &#1575;&#1604;&#1605;&#1601;&#1578;&#1608;&#1581;&#1577;</span></div>
            </td>
            <td class="head-right ar">
                <div>{!! $headerCountryAr !!}</div>
                <div>&#1585;&#1602;&#1605; &#1575;&#1604;&#1587;&#1580;&#1604; &#1575;&#1604;&#1578;&#1580;&#1575;&#1585;&#1610; : {{ $headerCrNumber }}</div>
                <div>&#1589;.&#1576; : {{ $headerPoBox }}</div>
                <div>&#1575;&#1604;&#1585;&#1605;&#1586; &#1575;&#1604;&#1576;&#1585;&#1610;&#1583;&#1610; : {{ $headerPc }}</div>
                <div>&#1606;&#1602;&#1575;&#1604; : {{ $headerGsm1 }}</div>
                <div>&#1606;&#1602;&#1575;&#1604; : {{ $headerGsm2 }}</div>
                <div>&#1606;&#1602;&#1575;&#1604; : {{ $headerGsm3 }}</div>
                <div class="serial">&#1585;&#1602;&#1605; &#1575;&#1604;&#1578;&#1602;&#1585;&#1610;&#1585; : <span>{{ $safeReportNumber }}</span></div>
            </td>
        </tr>
    </table>
    <div class="thick-rule"></div>

    <table class="topbar">
        <tr>
            <td>
                <div class="report-title">Open Contracts Report</div>
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
            <td><div class="card"><div class="label">Open contracts</div><div class="value">{{ $summary['open_contracts'] ?? 0 }}</div></div></td>
            <td><div class="card"><div class="label">Ending 24h</div><div class="value">{{ $summary['ending_24_hours'] ?? 0 }}</div></div></td>
            <td><div class="card"><div class="label">Ending 48h</div><div class="value">{{ $summary['ending_48_hours'] ?? 0 }}</div></div></td>
            <td><div class="card"><div class="label">Ending 72h</div><div class="value">{{ $summary['ending_72_hours'] ?? 0 }}</div></div></td>
            <td><div class="card"><div class="label">Overdue</div><div class="value">{{ $summary['overdue_contracts'] ?? 0 }}</div></div></td>
            <td><div class="card"><div class="label">Outstanding</div><div class="value">{{ $summary['formatted_total_outstanding'] ?? '$0.00' }}</div></div></td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">Active Contracts <span class="ar">&#1575;&#1604;&#1593;&#1602;&#1608;&#1583; &#1575;&#1604;&#1606;&#1588;&#1591;&#1577; |</span></div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Contract</th>
                    <th>Reservation</th>
                    <th>Client</th>
                    <th>Vehicle</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Payment</th>
                    <th>Outstanding</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contracts as $row)
                    <tr>
                        <td>
                            <strong>{{ $row['contract_number'] ?? '-' }}</strong>
                            <div><span class="status {{ ! empty($row['is_overdue']) ? 'status-danger' : '' }}">{{ $row['remaining_label'] ?? '-' }}</span></div>
                        </td>
                        <td>{{ $row['reservation_number'] ?? '-' }}</td>
                        <td>
                            <strong>{{ $row['client_name'] ?? '-' }}</strong>
                            <div class="muted">{{ $row['client_email'] ?? '-' }}</div>
                        </td>
                        <td>
                            <strong>{{ $row['car_name'] ?? '-' }}</strong>
                            <div class="muted">{{ $row['license_plate'] ?? '-' }}</div>
                        </td>
                        <td>{{ $row['start_date'] ?? '-' }}</td>
                        <td>{{ $row['end_date'] ?? '-' }}</td>
                        <td><span class="status">{{ $row['payment_status_label'] ?? '-' }}</span></td>
                        <td class="money">{{ $row['formatted_outstanding_amount'] ?? '$0.00' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align: center; color: #64748b;">No active contracts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Ending Soon / Overdue <span class="ar">&#1578;&#1606;&#1576;&#1610;&#1607;&#1575;&#1578; &#1575;&#1604;&#1593;&#1602;&#1608;&#1583; |</span></div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Contract</th>
                    <th>Client</th>
                    <th>Vehicle</th>
                    <th>End date</th>
                    <th>Remaining</th>
                    <th>Outstanding</th>
                </tr>
            </thead>
            <tbody>
                @forelse($endingSoon as $row)
                    <tr>
                        <td><strong>{{ $row['contract_number'] ?? '-' }}</strong></td>
                        <td>{{ $row['client_name'] ?? '-' }}</td>
                        <td>{{ $row['car_name'] ?? '-' }}</td>
                        <td>{{ $row['end_date'] ?? '-' }}</td>
                        <td><span class="status {{ ! empty($row['is_overdue']) ? 'status-danger' : '' }}">{{ $row['remaining_label'] ?? '-' }}</span></td>
                        <td class="money">{{ $row['formatted_outstanding_amount'] ?? '$0.00' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align: center; color: #64748b;">No contracts ending soon.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="footer">Generated by Car4u.</div>
</div>
</body>
</html>
