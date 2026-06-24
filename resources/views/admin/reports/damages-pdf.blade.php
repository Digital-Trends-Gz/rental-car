@php
    $normalizeLines = function ($value): array {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($line) => trim((string) $line) !== ''));
        }

        return array_values(array_filter(
            preg_split('/\r\n|\r|\n/', (string) $value),
            fn ($line) => trim((string) $line) !== ''
        ));
    };

    $leftLines = $normalizeLines(data_get($pdfHeader ?? [], 'left', [
        'Sultanate of Oman',
        'C.R : 30078535',
        'P.O Box : -',
        'GSM : -',
    ]));
    $rightLines = $normalizeLines(data_get($pdfHeader ?? [], 'right', [
        'سلطنة عمان',
        'رقم السجل التجاري : 30078535',
        'ص.ب : -',
        'الهاتف : -',
    ]));
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
    $safeReportNumber = $reportNumber ?? 'DMR-' . now()->format('Ymd-Hi');
    $logo = $companyLogo ?? null;
    $report = $damagesReport ?? [];
    $summary = $report['summary'] ?? [];
    $byCar = $report['by_car'] ?? [];
    $employees = $report['employees'] ?? [];
    $registeredBy = $employees['registered_by'] ?? [];
    $closedBy = $employees['closed_by'] ?? [];
    $photos = $report['photos'] ?? [];
    $beforePhotos = $photos['before'] ?? [];
    $afterPhotos = $photos['after'] ?? [];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Damages Report</title>
    <style>
  @font-face {
            font-family: CairoPdf;
            src: url("{{ file_exists(storage_path('fonts/cairo_normal_a5cea5fc45f6bf5f483d9f082575cfe3.ttf')) ? 'data:font/truetype;base64,'.base64_encode(file_get_contents(storage_path('fonts/cairo_normal_a5cea5fc45f6bf5f483d9f082575cfe3.ttf'))) : '' }}") format("truetype");
            font-weight: 400;
            font-style: normal;
        }
        @font-face {
            font-family: CairoPdf;
            src: url("{{ file_exists(storage_path('fonts/cairo_bold_23a9b2dc30935e892c606fbbafd14072.ttf')) ? 'data:font/truetype;base64,'.base64_encode(file_get_contents(storage_path('fonts/cairo_bold_23a9b2dc30935e892c606fbbafd14072.ttf'))) : '' }}") format("truetype");
            font-weight: 700 900;
            font-style: normal;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111827;
            background: #ffffff;
            font-family: 'CairoPdf', DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }

        .page {
            border: 3px solid #15377d;
            margin: 12px;
            padding: 10px 14px 18px;
            min-height: 1080px;
        }

        .header {
            display: table;
            width: 100%;
            border-bottom: 7px solid #15377d;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }

        .head-col {
            display: table-cell;
            width: 28%;
            vertical-align: top;
            color: #15377d;
            font-size: 10px;
            font-weight: 700;
        }

        .head-col.right {
            direction: rtl;
            text-align: right;
        }

        .head-center {
            display: table-cell;
            width: 44%;
            text-align: center;
            vertical-align: middle;
            border-left: 1px solid #b8c3d7;
            border-right: 1px solid #b8c3d7;
            color: #15377d;
        }

        .logo {
            max-height: 42px;
            max-width: 110px;
            margin-bottom: 5px;
        }

        .company {
            font-size: 30px;
            font-weight: 800;
            letter-spacing: .5px;
            line-height: 1.05;
        }

        .subtitle {
            font-size: 17px;
            font-weight: 800;
        }

        .report-number {
            margin-top: 8px;
            color: #dc2626;
            font-weight: 800;
        }

        .ar {
            direction: rtl;
            unicode-bidi: plaintext;
            font-family: 'CairoPdf', DejaVu Sans, Tahoma, Arial, sans-serif;
        }

        .document-header {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 4px solid #17306f;
            color: #17306f;
            margin-bottom: 14px;
        }

        .page > .header {
            display: none !important;
        }

        .document-header td {
            padding: 3px 6px;
            color: #17306f;
            font-weight: 900;
            vertical-align: top;
        }

        .document-header .head-left,
        .document-header .head-right {
            width: 23%;
            font-size: 8px;
            line-height: 1.35;
        }

        .document-header .head-right {
            text-align: right;
        }

        .document-header .head-main {
            width: 54%;
            text-align: center;
            vertical-align: middle;
            border-left: 1px solid #d8dbe5;
            border-right: 1px solid #d8dbe5;
        }

        .document-header .logo {
            max-height: 36px;
            max-width: 175px;
            object-fit: contain;
            margin-bottom: 2px;
        }

        .company-en {
            color: #17306f;
            font-size: 21px;
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: .5px;
            text-transform: uppercase;
        }

        .company-ar {
            color: #17306f;
            font-size: 20px;
            line-height: 1.05;
            font-weight: 900;
            text-align: center;
        }

        .title-inline {
            width: auto;
            margin: 0 auto;
            border-collapse: collapse;
        }

        .title-inline td {
            padding: 0 4px;
            color: #17306f;
            font-size: 13px;
            line-height: 1.1;
            font-weight: 900;
            white-space: nowrap;
        }

        .title-inline .title-ar {
            direction: rtl;
            unicode-bidi: plaintext;
            text-align: right;
            font-family: 'CairoPdf', DejaVu Sans, Tahoma, Arial, sans-serif;
        }

        .serial {
            margin-top: 4px;
            font-size: 9px;
        }

        .serial span {
            color: #d6202a;
            font-size: 11px;
            font-weight: 900;
        }

        .meta {
            display: table;
            width: 100%;
            margin-bottom: 14px;
        }

        .meta-title {
            display: table-cell;
            width: 55%;
            vertical-align: top;
        }

        .meta-info {
            display: table-cell;
            width: 45%;
            text-align: right;
            color: #475569;
            font-weight: 700;
        }

        h1 {
            margin: 0;
            font-size: 28px;
            color: #111827;
        }

        .badge {
            display: inline-block;
            margin-top: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-weight: 800;
            font-size: 11px;
        }

        .grid {
            display: table;
            width: 100%;
            border-spacing: 10px;
            margin: 0 -10px 10px;
        }

        .cell {
            display: table-cell;
            width: 25%;
            padding: 14px;
            border: 1px solid #d8e0ec;
            border-radius: 10px;
            background: #f8fafc;
        }

        .metric-label {
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .metric-value {
            margin-top: 7px;
            color: #15377d;
            font-size: 22px;
            font-weight: 900;
        }

        .section {
            margin-top: 12px;
            border: 1px solid #d8e0ec;
            border-radius: 9px;
            overflow: hidden;
        }

        .section-title {
            padding: 10px 14px;
            background: #f1f5f9;
            color: #15377d;
            font-size: 16px;
            font-weight: 900;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            padding: 9px 10px;
            text-align: left;
            background: #f8fafc;
            color: #475569;
            border-bottom: 1px solid #d8e0ec;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
        }

        td {
            padding: 9px 10px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .amount {
            color: #111827;
            font-size: 14px;
            font-weight: 900;
        }

        .muted {
            color: #64748b;
            font-size: 10px;
        }

        .photo {
            width: 72px;
            height: 54px;
            object-fit: cover;
            border: 1px solid #d8e0ec;
            border-radius: 6px;
        }

        .two-cols {
            display: table;
            width: 100%;
            border-spacing: 10px;
            margin: 0 -10px;
        }

        .two-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .footer {
            margin-top: 18px;
            text-align: center;
            color: #94a3b8;
            font-size: 11px;
        }
    </style>
</head>
<body>
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
                @if ($logo)
                    <img class="logo" src="{{ $logo }}" alt="Logo">
                @endif
                <div class="company-en">{{ strtoupper($headerCompanyNameEn) }}</div>
                <div class="company-ar ar">{{ $headerCompanyNameAr }}</div>
                <table class="title-inline">
                    <tr>
                        <td>DAMAGES REPORT</td>
                        <td class="title-ar">تقرير الأضرار</td>
                    </tr>
                </table>
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

    <div class="header">
        <div class="head-col">
            @foreach ($leftLines as $line)
                <div>{{ $line }}</div>
            @endforeach
            <div class="report-number">No. {{ $reportNumber ?? 'DMR-' . now()->format('Ymd-Hi') }}</div>
        </div>
        <div class="head-center">
            @if ($logo)
                <img class="logo" src="{{ $logo }}" alt="Logo">
            @endif
            <div class="company">{{ $companyName ?? config('app.name') }}</div>
            <div class="subtitle">Damages Report | تقرير الأضرار</div>
        </div>
        <div class="head-col right">
            @foreach ($rightLines as $line)
                <div>{{ $line }}</div>
            @endforeach
            <div class="report-number">رقم التقرير: {{ $reportNumber ?? 'DMR-' . now()->format('Ymd-Hi') }}</div>
        </div>
    </div>

    <div class="meta">
        <div class="meta-title">
            <h1>Damages Report</h1>
            <span class="badge">{{ $periodLabel ?? 'Selected period' }}</span>
        </div>
        <div class="meta-info">
            <div>Generated: {{ $generatedAt ?? now()->format('Y-m-d H:i') }}</div>
            <div>Branch: {{ $branchName ?? 'All branches' }}</div>
            <div>Date Range: {{ ($dateRange['start'] ?? now())->format('Y-m-d') }} to {{ ($dateRange['end'] ?? now())->format('Y-m-d') }}</div>
        </div>
    </div>

    <div class="grid">
        <div class="cell">
            <div class="metric-label">Damage reports</div>
            <div class="metric-value">{{ $summary['total_reports'] ?? 0 }}</div>
        </div>
        <div class="cell">
            <div class="metric-label">Damage items</div>
            <div class="metric-value">{{ $summary['total_items'] ?? 0 }}</div>
        </div>
        <div class="cell">
            <div class="metric-label">Damage cost</div>
            <div class="metric-value">{{ $summary['formatted_total_cost'] ?? '$0.00' }}</div>
        </div>
        <div class="cell">
            <div class="metric-label">Open / Closed</div>
            <div class="metric-value">{{ $summary['open_reports'] ?? 0 }} / {{ $summary['closed_reports'] ?? 0 }}</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">By Vehicle | حسب السيارة</div>
        <table>
            <thead>
            <tr>
                <th>Vehicle</th>
                <th>Plate</th>
                <th>Reports</th>
                <th>Items</th>
                <th>Open</th>
                <th>Closed</th>
                <th>Cost</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($byCar as $car)
                <tr>
                    <td><strong>{{ $car['car_name'] ?? '-' }}</strong></td>
                    <td>{{ $car['license_plate'] ?? '-' }}</td>
                    <td>{{ $car['reports_count'] ?? 0 }}</td>
                    <td>{{ $car['items_count'] ?? 0 }}</td>
                    <td>{{ $car['open_reports'] ?? 0 }}</td>
                    <td>{{ $car['closed_reports'] ?? 0 }}</td>
                    <td class="amount">{{ $car['formatted_total_cost'] ?? '$0.00' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">No damage records for this period.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="two-cols">
        <div class="two-col">
            <div class="section">
                <div class="section-title">Registered By | من سجل الضرر</div>
                <table>
                    <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Reports</th>
                        <th>Items</th>
                        <th>Cost</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($registeredBy as $employee)
                        <tr>
                            <td>{{ $employee['employee_name'] ?? '-' }}</td>
                            <td>{{ $employee['reports_count'] ?? 0 }}</td>
                            <td>{{ $employee['items_count'] ?? 0 }}</td>
                            <td class="amount">{{ $employee['formatted_total_cost'] ?? '$0.00' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">No records.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="two-col">
            <div class="section">
                <div class="section-title">Closed By | من أغلق الضرر</div>
                <table>
                    <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Reports</th>
                        <th>Items</th>
                        <th>Cost</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($closedBy as $employee)
                        <tr>
                            <td>{{ $employee['employee_name'] ?? '-' }}</td>
                            <td>{{ $employee['reports_count'] ?? 0 }}</td>
                            <td>{{ $employee['items_count'] ?? 0 }}</td>
                            <td class="amount">{{ $employee['formatted_total_cost'] ?? '$0.00' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">No closed damage records.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="two-cols">
        <div class="two-col">
            <div class="section">
                <div class="section-title">Before Delivery Photos | صور قبل التسليم</div>
                <table>
                    <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Report</th>
                        <th>Vehicle</th>
                        <th>Items</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($beforePhotos as $row)
                        <tr>
                            <td>
                                @if (!empty($row['first_photo_url']))
                                    <img class="photo" src="{{ $row['first_photo_url'] }}" alt="Damage">
                                @else
                                    <span class="muted">No photo</span>
                                @endif
                            </td>
                            <td>{{ $row['report_number'] ?? '-' }}</td>
                            <td>{{ $row['car_name'] ?? '-' }}<div class="muted">{{ $row['license_plate'] ?? '-' }}</div></td>
                            <td>{{ $row['items_count'] ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">No before-delivery photos.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="two-col">
            <div class="section">
                <div class="section-title">After Return Photos | صور بعد الرجوع</div>
                <table>
                    <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Report</th>
                        <th>Vehicle</th>
                        <th>Items</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($afterPhotos as $row)
                        <tr>
                            <td>
                                @if (!empty($row['first_photo_url']))
                                    <img class="photo" src="{{ $row['first_photo_url'] }}" alt="Damage">
                                @else
                                    <span class="muted">No photo</span>
                                @endif
                            </td>
                            <td>{{ $row['report_number'] ?? '-' }}</td>
                            <td>{{ $row['car_name'] ?? '-' }}<div class="muted">{{ $row['license_plate'] ?? '-' }}</div></td>
                            <td>{{ $row['items_count'] ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">No after-return photos.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="footer">Generated by Car4u.</div>
</div>
</body>
</html>
