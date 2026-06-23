<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Vehicle Profitability Report</title>
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
            font-size: 10px;
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
            vertical-align: top;
            padding: 2px 4px;
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
        .topbar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .topbar td {
            padding: 0;
            vertical-align: top;
        }
        .title {
            font-size: 22px;
            font-weight: 900;
            color: #111827;
        }
        .meta {
            text-align: right;
            color: #475569;
            line-height: 1.55;
            font-weight: 700;
        }
        .badge {
            display: inline-block;
            margin-top: 4px;
            padding: 3px 8px;
            border-radius: 999px;
            background: #dbeafe;
            color: #2563eb;
            font-weight: 800;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .grid td {
            width: 25%;
            padding: 4px;
        }
        .card {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px;
            min-height: 58px;
            background: #f8fafc;
        }
        .label {
            color: #64748b;
            font-size: 9px;
            font-weight: 700;
        }
        .value {
            margin-top: 6px;
            color: #0f172a;
            font-size: 15px;
            font-weight: 900;
        }
        .section {
            margin-top: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .section-title {
            padding: 7px 10px;
            background: #f1f5f9;
            color: #17306f;
            font-size: 13px;
            font-weight: 900;
            border-bottom: 1px solid #cbd5e1;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th,
        .data-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .data-table th {
            background: #f8fafc;
            color: #475569;
            text-align: left;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .data-table tr:last-child td {
            border-bottom: 0;
        }
        .money {
            font-weight: 900;
            color: #0f172a;
            white-space: nowrap;
        }
        .profit {
            color: #047857;
        }
        .loss {
            color: #b91c1c;
        }
        .muted {
            color: #64748b;
            font-size: 8px;
        }
        .footer {
            margin-top: 12px;
            color: #64748b;
            text-align: center;
            font-size: 8px;
        }
    </style>
</head>
<body>
@php
    $report = $vehicleProfitabilityReport ?? ['summary' => [], 'cars' => [], 'top_profitable' => [], 'least_profitable' => []];
    $summary = $report['summary'] ?? [];
    $cars = collect($report['cars'] ?? []);
    $topCars = collect($report['top_profitable'] ?? []);
    $leastCars = collect($report['least_profitable'] ?? []);
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
    $safeReportNumber = $reportNumber ?? 'VPR-'.now()->format('Ymd-Hi');
    $profitClass = fn ($row) => (float) ($row['net_profit'] ?? 0) < 0 ? 'loss' : 'profit';
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
                <div class="title-line">VEHICLE PROFITABILITY REPORT <span class="ar">تقرير ربحية السيارات</span></div>
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
                <div class="title">Vehicle Profitability Report</div>
                <span class="badge">{{ $periodLabel }}</span>
            </td>
            <td class="meta">
                <div>Generated: {{ $generatedAt->format('Y-m-d H:i') }}</div>
                <div>Branch: {{ $branchName }}</div>
                <div>Date Range: {{ $dateRange['start']->format('Y-m-d') }} to {{ $dateRange['end']->format('Y-m-d') }}</div>
            </td>
        </tr>
    </table>

    <table class="grid">
        <tr>
            <td><div class="card"><div class="label">Total revenue</div><div class="value">{{ $summary['formatted_total_revenue'] ?? '$0.00' }}</div></div></td>
            <td><div class="card"><div class="label">Total costs</div><div class="value">{{ $summary['formatted_total_costs'] ?? '$0.00' }}</div></div></td>
            <td><div class="card"><div class="label">Net profit</div><div class="value">{{ $summary['formatted_net_profit'] ?? '$0.00' }}</div></div></td>
            <td><div class="card"><div class="label">Average revenue per car</div><div class="value">{{ $summary['formatted_average_revenue_per_car'] ?? '$0.00' }}</div></div></td>
        </tr>
        <tr>
            <td><div class="card"><div class="label">Cars in report</div><div class="value">{{ $cars->count() }}</div></div></td>
            <td><div class="card"><div class="label">Profitable cars</div><div class="value">{{ $summary['profitable_cars'] ?? 0 }}</div></div></td>
            <td><div class="card"><div class="label">Loss-making cars</div><div class="value">{{ $summary['loss_making_cars'] ?? 0 }}</div></div></td>
            <td><div class="card"><div class="label">Visibility</div><div class="value">{{ $canViewFinancials ? 'Amounts visible' : 'Hidden' }}</div></div></td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">Vehicle Profitability Details</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Car</th>
                    <th>Plate</th>
                    <th>Status</th>
                    <th>Revenue</th>
                    <th>Damage</th>
                    <th>Maintenance</th>
                    <th>Violations</th>
                    <th>Total costs</th>
                    <th>Net profit</th>
                    <th>Reservations</th>
                    <th>Utilization</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cars as $car)
                    <tr>
                        <td>
                            <strong>{{ $car['car_name'] ?? '-' }}</strong>
                            <div class="muted">ID: {{ $car['car_id'] ?? '-' }}</div>
                        </td>
                        <td>{{ $car['license_plate'] ?? '-' }}</td>
                        <td>{{ $car['status'] ?? '-' }}</td>
                        <td class="money">{{ $car['formatted_revenue'] ?? '$0.00' }}</td>
                        <td class="money">{{ $car['formatted_damage_cost'] ?? '$0.00' }}</td>
                        <td class="money">{{ $car['formatted_maintenance_cost'] ?? '$0.00' }}</td>
                        <td class="money">{{ $car['formatted_violation_cost'] ?? '$0.00' }}</td>
                        <td class="money">{{ $car['formatted_total_costs'] ?? '$0.00' }}</td>
                        <td class="money {{ $profitClass($car) }}">{{ $car['formatted_net_profit'] ?? '$0.00' }}</td>
                        <td>{{ $car['reservations_count'] ?? 0 }}</td>
                        <td>{{ $car['utilization_rate'] ?? 0 }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" style="text-align: center; color: #64748b;">No vehicle profitability data for this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <table class="grid" style="margin-top: 10px;">
        <tr>
            <td colspan="2">
                <div class="section">
                    <div class="section-title">Top profitable cars</div>
                    <table class="data-table">
                        @forelse($topCars as $car)
                            <tr>
                                <td>{{ $car['car_name'] ?? '-' }}</td>
                                <td class="money profit">{{ $car['formatted_net_profit'] ?? '$0.00' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2">No data.</td></tr>
                        @endforelse
                    </table>
                </div>
            </td>
            <td colspan="2">
                <div class="section">
                    <div class="section-title">Least profitable cars</div>
                    <table class="data-table">
                        @forelse($leastCars as $car)
                            <tr>
                                <td>{{ $car['car_name'] ?? '-' }}</td>
                                <td class="money {{ $profitClass($car) }}">{{ $car['formatted_net_profit'] ?? '$0.00' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2">No data.</td></tr>
                        @endforelse
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">Generated by Car4u.</div>
</div>
</body>
</html>
