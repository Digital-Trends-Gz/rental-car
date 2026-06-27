<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Collections Report</title>
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
            font-size: 10px;
            color: #111827;
            background: #ffffff;
        }
        .ar { direction: rtl; unicode-bidi: plaintext; font-family: cairo, "DejaVu Sans", Arial, sans-serif; }
        .page { border: 2px solid #17306f; padding: 4px 8px 8px; }
        .document-header { width: 100%; border-collapse: collapse; color: #17306f; margin-bottom: 2px; }
        .document-header td { vertical-align: top; padding: 2px 4px; }
        .head-left, .head-right { width: 24%; font-size: 7px; line-height: 1.25; font-weight: 700; }
        .head-right { text-align: right; }
        .head-main { width: 52%; text-align: center; border-left: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; }
        .logo { max-height: 30px; max-width: 150px; object-fit: contain; margin-bottom: 1px; }
        .company-en { font-size: 18px; line-height: 1.02; font-weight: 900; color: #17306f; text-transform: uppercase; }
        .company-ar { font-size: 14px; line-height: 1.05; font-weight: 900; color: #17306f; }
        .title-line { font-size: 11px; line-height: 1.1; font-weight: 900; color: #17306f; margin-top: 1px; }
        .title-line .ar { display: inline-block; margin-left: 8px; font-size: 11px; }
        .serial { margin-top: 3px; font-size: 8px; }
        .serial span { color: #dc2626; font-size: 10px; font-weight: 900; }
        .thick-rule { height: 4px; background: #17306f; margin: 1px 0 4px; }
        .topbar { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .topbar td { padding: 0; vertical-align: top; }
        .title { font-size: 22px; font-weight: 900; color: #111827; }
        .meta { text-align: right; color: #475569; line-height: 1.55; font-weight: 700; }
        .badge { display: inline-block; margin-top: 4px; padding: 3px 8px; border-radius: 999px; background: #dbeafe; color: #2563eb; font-weight: 800; }
        .grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .grid td { width: 20%; padding: 4px; }
        .card { border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px; min-height: 58px; background: #f8fafc; }
        .label { color: #64748b; font-size: 9px; font-weight: 700; }
        .value { margin-top: 6px; color: #0f172a; font-size: 15px; font-weight: 900; }
        .section { margin-top: 10px; border: 1px solid #cbd5e1; border-radius: 7px; overflow: hidden; page-break-inside: avoid; break-inside: avoid; }
        .section.breakable { page-break-inside: auto; break-inside: auto; overflow: visible; }
        .section-title { padding: 7px 10px; background: #f1f5f9; color: #17306f; font-size: 13px; font-weight: 900; border-bottom: 1px solid #cbd5e1; }
        table.data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .data-table th { background: #f8fafc; color: #475569; text-align: left; font-size: 8px; text-transform: uppercase; }
        .data-table tr { page-break-inside: avoid; break-inside: avoid; }
        .data-table tr:last-child td { border-bottom: 0; }
        .money { font-weight: 900; color: #0f172a; white-space: nowrap; }
        .muted { color: #64748b; font-size: 8px; }
        .footer { margin-top: 12px; color: #64748b; text-align: center; font-size: 8px; }
    </style>
</head>
<body>
@php
    $report = $collectionsReport ?? ['summary' => [], 'aging_buckets' => [], 'debtors' => []];
    $summary = $report['summary'] ?? [];
    $buckets = collect($report['aging_buckets'] ?? []);
    $debtors = collect($report['debtors'] ?? []);
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
    $safeReportNumber = $reportNumber ?? 'CLR-'.now()->format('Ymd-Hi');
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
                <div class="title-line">COLLECTIONS REPORT <span class="ar">تقرير الديون والتحصيل</span></div>
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
                <div class="title">Collections Report</div>
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
            <td><div class="card"><div class="label">Debtors</div><div class="value">{{ $summary['debtors_count'] ?? 0 }}</div></div></td>
            <td><div class="card"><div class="label">Items</div><div class="value">{{ $summary['items_count'] ?? 0 }}</div></div></td>
            <td><div class="card"><div class="label">Overdue</div><div class="value">{{ $summary['overdue_items_count'] ?? 0 }}</div></div></td>
            <td><div class="card"><div class="label">Current</div><div class="value">{{ $summary['current_items_count'] ?? 0 }}</div></div></td>
            <td><div class="card"><div class="label">Outstanding</div><div class="value">{{ $summary['formatted_total_outstanding'] ?? '$0.00' }}</div></div></td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">Aging Classification</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Bucket</th>
                    <th>Arabic</th>
                    <th>Count</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($buckets as $bucket)
                    <tr>
                        <td><strong>{{ $bucket['label'] ?? '-' }}</strong></td>
                        <td class="ar">{{ $bucket['label_ar'] ?? '-' }}</td>
                        <td>{{ $bucket['count'] ?? 0 }}</td>
                        <td class="money">{{ $bucket['formatted_amount'] ?? '$0.00' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section breakable">
        <div class="section-title">Debtors</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Reference</th>
                    <th>Amount</th>
                    <th>Due Date</th>
                    <th>Late Days</th>
                    <th>Bucket</th>
                    <th>Car</th>
                </tr>
            </thead>
            <tbody>
                @forelse($debtors as $row)
                    <tr>
                        <td>
                            <strong>{{ $row['customer_name'] ?? '-' }}</strong>
                            <div class="muted">{{ $row['customer_email'] ?? '-' }}</div>
                        </td>
                        <td>
                            {{ $row['reference'] ?? '-' }}
                            <div class="muted">{{ $row['source_label'] ?? '-' }}</div>
                        </td>
                        <td class="money">{{ $row['formatted_amount'] ?? '$0.00' }}</td>
                        <td>{{ $row['due_date'] ?? '-' }}</td>
                        <td>{{ $row['days_overdue'] ?? 0 }}</td>
                        <td>{{ $row['bucket_label'] ?? '-' }}</td>
                        <td>
                            {{ $row['car_name'] ?? '-' }}
                            <div class="muted">{{ $row['license_plate'] ?? '-' }}</div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center;color:#64748b;">No debtors found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="footer">Generated by Car4u.</div>
</div>
</body>
</html>
