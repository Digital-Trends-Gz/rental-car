<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <title>Reservation Invoice {{ $reservation->reservation_number }}</title>
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
            color: #111827;
            font-family: cairo, "TahomaPdf", "DejaVu Sans", Arial, sans-serif;
            font-size: 9.5px;
            line-height: 1.28;
            background: #fff;
            direction: ltr;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { vertical-align: top; }
        .page { width: 100%; border: 2px solid #17306f; padding: 2px; }
        .blue { color: #17306f; }
        .muted { color: #667085; }
        .strong { font-weight: 900; }
        .right { text-align: right; }
        .center { text-align: center; }
        .ar,
        .company-ar,
        .title-line .ar {
            font-family: cairo, "TahomaPdf", "DejaVu Sans", Arial, sans-serif;
            direction: rtl;
            unicode-bidi: plaintext;
            text-align: right;
            font-size: 1em;
            line-height: 1.18;
        }
        .header td { padding: 3px 6px; color: #17306f; font-weight: 900; }
        .head-left, .head-right { width: 23%; font-size: 8px; line-height: 1.35; }
        .head-main {
            width: 54%;
            text-align: center;
            border-left: 1px solid #d8dbe5;
            border-right: 1px solid #d8dbe5;
        }
        .header{
            border-bottom: 4px solid #17306f;
          
        }
        .logo { max-height: 36px; max-width: 175px; object-fit: contain; }
        .company-en { font-size: 21px; line-height: 1.02; font-weight: 900; letter-spacing: .5px; }
        .company-ar { font-size: 20px; line-height: 1.05; font-weight: 900; text-align: center; }
        .title-line { font-size: 13px; line-height: 1.1; font-weight: 900; }
        .title-line .ar { display: inline-block; margin-left: 8px; font-size: 13px; }
        .title-inline { width: auto; margin: 0 auto; }
        .title-inline td { padding: 0 4px; color: #17306f; font-size: 13px; line-height: 1.1; font-weight: 900; white-space: nowrap; }
        .title-inline .title-ar { direction: rtl; unicode-bidi: plaintext; text-align: right; font-family: cairo, "TahomaPdf", "DejaVu Sans", Arial, sans-serif; }
        .serial { margin-top: 4px; font-size: 9px; }
        .serial span { color: #d6202a; font-size: 11px; font-weight: 900; }
        .invoice-bar {
            border-top: 4px solid #17306f;
            border-bottom: 1px solid #d8dbe5;
            padding: 10px 14px;
            background: #f8fafc;
        }
        .invoice-title { font-size: 20px; font-weight: 900; color: #111827; }
        .invoice-meta { text-align: right; font-size: 10px; color: #475467; }
        .badge {
            display: inline-block;
            border-radius: 999px;
            padding: 3px 9px;
            font-size: 9px;
            font-weight: 900;
        }
        .content { padding: 12px 14px 16px; }
        .section {
            border: 1px solid #d9e0ea;
            border-radius: 6px;
            margin-bottom: 9px;
            page-break-inside: avoid;
        }
        .section-title {
            background: #f4f7fb;
            border-bottom: 1px solid #d9e0ea;
            padding: 7px 10px;
            color: #17306f;
            font-size: 11px;
            font-weight: 900;
        }
        .section-body { padding: 9px 10px; }
        .info td { padding: 4px 6px; }
        .label { color: #667085; font-size: 9px; }
        .value { color: #111827; font-size: 10.5px; font-weight: 900; }
        .summary-card {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 8px;
            min-height: 52px;
        }
        .payments th {
            color: #17306f;
            background: #f8fafc;
            border-bottom: 1px solid #d9e0ea;
            padding: 7px 6px;
            text-align: left;
            font-size: 9px;
        }
        .payments td {
            border-bottom: 1px solid #eef2f7;
            padding: 7px 6px;
        }
        .totals td { padding: 5px 6px; }
        .totals .total-row td {
            border-top: 2px solid #17306f;
            padding-top: 8px;
            font-size: 13px;
            font-weight: 900;
            color: #17306f;
        }
        .note-box {
            min-height: 42px;
            border: 1px dashed #d0d5dd;
            border-radius: 5px;
            padding: 8px;
            color: #475467;
        }
    </style>
</head>
<body>
@php
    $siteSettings = $siteSettings ?? [];
    $pdfHeader = data_get($siteSettings, 'pdf_header', []);
    $contactPhone = data_get($siteSettings, 'contact.phone') ?? $reservation->car?->branch?->phone_1 ?? $reservation->car?->branch?->phone ?? '-';
    $contactWhatsapp = data_get($siteSettings, 'contact.whatsapp') ?? '-';
    $headerCompanyNameEn = data_get($pdfHeader, 'company_name.en') ?: ($companyName ?? config('app.name'));
    $headerCompanyNameAr = data_get($pdfHeader, 'company_name.ar') ?: $headerCompanyNameEn;
    $headerCrNumber = data_get($pdfHeader, 'cr_number') ?? '';
    $headerPoBox = data_get($pdfHeader, 'po_box') ?? '';
    $headerPc = data_get($pdfHeader, 'pc') ?? '';
    $headerCountryEn = data_get($pdfHeader, 'country.en') ?: 'Sultanate of Oman';
    $headerCountryAr = data_get($pdfHeader, 'country.ar') ?: 'سلطنة عمان';
    $headerGsm1 = data_get($pdfHeader, 'gsm_1') ?: $contactWhatsapp;
    $headerGsm2 = data_get($pdfHeader, 'gsm_2') ?: $contactPhone;
    $headerGsm3 = data_get($pdfHeader, 'gsm_3') ?: '';
    $headerRegistryLabelEn = data_get($pdfHeader, 'registry_label.en') ?: 'No.';

    $statusValue = $reservation->status instanceof \App\Enums\ReservationStatus ? $reservation->status->value : (string) $reservation->status;
    $statusMap = collect($statusMeta)->keyBy('value');
    $meta = $statusMap[$statusValue] ?? null;
    $hex = $meta['color'] ?? '#17306f';
    $label = $meta['label'] ?? ucfirst(str_replace('_', ' ', $statusValue));
    $paidAmount = (float) $reservation->payments->filter(fn ($payment) => (($payment->status->value ?? $payment->status) === \App\Enums\PaymentStatus::COMPLETED->value))->sum('amount');
    $balanceDue = max(0, (float) $reservation->total_amount - $paidAmount);
    $discountType = $reservation->discount_type ?: 'fixed';
    $discountLabel = $discountType === 'percentage'
        ? number_format((float) $reservation->discount_value, 2).'%'
        : $currency.number_format((float) ($reservation->discount_value ?? $reservation->discount_amount), 2);
@endphp
<div class="page">
    <table class="header">
        <tr>
            <td class="head-left">
                <div>{{ $headerCountryEn }}</div>
                <div>C.R : {{ $headerCrNumber ?: '-' }}</div>
                <div>P.O Box : {{ $headerPoBox ?: '-' }}</div>
                <div>P.C : {{ $headerPc ?: '-' }}</div>
                <div>GSM : {{ $headerGsm1 ?: '-' }}</div>
                <div>GSM : {{ $headerGsm2 ?: '-' }}</div>
                <div>GSM : {{ $headerGsm3 ?: '-' }}</div>
                <div class="serial">{{ $headerRegistryLabelEn }} <span>{{ $reservation->reservation_number }}</span></div>
            </td>
            <td class="head-main">
                @if(!empty($companyLogo))
                    <img src="{{ $companyLogo }}" class="logo" alt="Logo" />
                @endif
                <div class="company-en">{{ strtoupper($headerCompanyNameEn) }}</div>
                <div class="company-ar ar center">{{ $headerCompanyNameAr }}</div>
                <table class="title-inline">
                    <tr>
                        <td>RESERVATION INVOICE</td>
                        <td class="title-ar">فاتورة حجز</td>
                    </tr>
                </table>
            </td>
            <td class="head-right ar">
                <div>{{ $headerCountryAr }}</div>
                <div>رقم السجل التجاري : {{ $headerCrNumber ?: '-' }}</div>
                <div>ص.ب : {{ $headerPoBox ?: '-' }}</div>
                <div>الرمز البريدي : {{ $headerPc ?: '-' }}</div>
                <div>نقال : {{ $headerGsm1 ?: '-' }}</div>
                <div>نقال : {{ $headerGsm2 ?: '-' }}</div>
                <div>نقال : {{ $headerGsm3 ?: '-' }}</div>
                <div class="serial">رقم الحجز : <span>{{ $reservation->reservation_number }}</span></div>

            </td>
        </tr>
    </table>



    <div class="content">
        <table style="margin-bottom: 10px;">
            <tr>
                <td style="width: 50%; padding-right: 5px;">
                    <div class="section">
                        <div class="section-title">Client Details</div>
                        <div class="section-body">
                            <table class="info">
                                <tr>
                                    <td class="label" style="width: 32%;">Name</td>
                                    <td class="value">{{ $reservation->user->name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Email</td>
                                    <td class="value">{{ $reservation->user->email ?? '-' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </td>
                <td style="width: 50%; padding-left: 5px;">
                    <div class="section">
                        <div class="section-title">Vehicle Details</div>
                        <div class="section-body">
                            <table class="info">
                                <tr>
                                    <td class="label" style="width: 32%;">Car</td>
                                    <td class="value">
                                        @if($reservation->car)
                                            {{ $reservation->car->year }} {{ $reservation->car->make }} {{ $reservation->car->model }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label">Plate</td>
                                    <td class="value">{{ $reservation->car->license_plate ?? '-' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="section">
            <div class="section-title">Reservation Details</div>
            <div class="section-body">
                <table>
                    <tr>
                        <td style="width: 20%; padding: 4px;"><div class="summary-card"><div class="label">Pickup</div><div class="value">{{ optional($reservation->start_date)->format('Y-m-d') }} {{ optional($reservation->pickup_time)->format('H:i') }}</div></div></td>
                        <td style="width: 20%; padding: 4px;"><div class="summary-card"><div class="label">Return</div><div class="value">{{ optional($reservation->end_date)->format('Y-m-d') }} {{ optional($reservation->return_time)->format('H:i') }}</div></div></td>
                        <td style="width: 20%; padding: 4px;"><div class="summary-card"><div class="label">Duration</div><div class="value">{{ (int) $reservation->total_days }} day{{ (int) $reservation->total_days === 1 ? '' : 's' }}</div></div></td>
                        <td style="width: 20%; padding: 4px;"><div class="summary-card"><div class="label">Pickup Location</div><div class="value">{{ $reservation->pickup_location ?? '-' }}</div></div></td>
                        <td style="width: 20%; padding: 4px;"><div class="summary-card"><div class="label">Return Location</div><div class="value">{{ $reservation->return_location ?? '-' }}</div></div></td>
                    </tr>
                </table>
                @if($statusValue === \App\Enums\ReservationStatus::CANCELLED->value)
                    <div class="note-box" style="margin-top: 8px;">
                        <span class="strong">Cancelled:</span> {{ optional($reservation->cancelled_at)->format('Y-m-d H:i') ?? '-' }}
                        <br>
                        <span class="strong">Reason:</span> {{ $reservation->cancellation_reason ?? '-' }}
                    </div>
                @endif
            </div>
        </div>

        <div class="section">
            <div class="section-title">Payments</div>
            <div class="section-body">
                @if($reservation->payments->count() === 0)
                    <div class="muted">No payments recorded.</div>
                @else
                    <table class="payments">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Payment #</th>
                                <th style="width: 15%;">Amount</th>
                                <th style="width: 18%;">Method</th>
                                <th style="width: 18%;">Status</th>
                                <th style="width: 24%;">Processed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reservation->payments as $payment)
                                <tr>
                                    <td>{{ $payment->payment_number }}</td>
                                    <td>{{ $currency }}{{ number_format((float) $payment->amount, 2) }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', (string) ($payment->payment_method->value ?? $payment->payment_method))) }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', (string) ($payment->status->value ?? $payment->status))) }}</td>
                                    <td>{{ optional($payment->processed_at)->format('Y-m-d H:i') ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <table>
            <tr>
                <td style="width: 58%; padding-right: 5px;">
                    <div class="section">
                        <div class="section-title">Notes</div>
                        <div class="section-body">
                            <div class="note-box">{{ $reservation->notes ?: 'No notes recorded.' }}</div>
                        </div>
                    </div>
                </td>
                <td style="width: 42%; padding-left: 5px;">
                    <div class="section">
                        <div class="section-title">Totals</div>
                        <div class="section-body">
                            <table class="totals">
                                <tr>
                                    <td class="label">Daily Rate</td>
                                    <td class="right value">{{ $currency }}{{ number_format((float) $reservation->daily_rate, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Subtotal</td>
                                    <td class="right value">{{ $currency }}{{ number_format((float) $reservation->subtotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Tax</td>
                                    <td class="right value">{{ $currency }}{{ number_format((float) $reservation->tax_amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Return Location Fee</td>
                                    <td class="right value">{{ $currency }}{{ number_format((float) ($reservation->return_location_fee ?? 0), 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Discount <span class="muted">({{ $discountLabel }})</span></td>
                                    <td class="right value">-{{ $currency }}{{ number_format((float) $reservation->discount_amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Paid</td>
                                    <td class="right value">{{ $currency }}{{ number_format($paidAmount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Balance Due</td>
                                    <td class="right value">{{ $currency }}{{ number_format($balanceDue, 2) }}</td>
                                </tr>
                                <tr class="total-row">
                                    <td>Total</td>
                                    <td class="right">{{ $currency }}{{ number_format((float) $reservation->total_amount, 2) }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
