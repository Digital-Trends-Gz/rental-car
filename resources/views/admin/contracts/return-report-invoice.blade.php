<html lang="{{ $locale }}" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $report->report_number }} - Additional Charges Invoice</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #0f1c42;
            font-family: 'Cairo', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.35;
            background: #fff;
            font-weight: 600;
        }
        .page { width: 100%; padding: 4px 6px; }
        .sheet { border: 2px solid #1a326b; background: #fff; padding: 2px; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 3px 5px; }
        .ar {
            font-family: 'Cairo', Arial, sans-serif;
            direction: rtl;
            unicode-bidi: plaintext;
            text-align: right;
        }
        .company-logo { max-height: 48px; object-fit: contain; margin-bottom: 2px; }
        .header-table { margin-bottom: 2px; }
        .header-table td { padding: 0 4px; vertical-align: top; }
        .header-left { width: 25%; font-size: 9px; line-height: 1.4; font-weight: 700; }
        .header-center { width: 50%; text-align: center; }
        .header-right { width: 25%; text-align: right; direction: rtl; unicode-bidi: plaintext; }
        .company-name-en { font-size: 18px; font-weight: 800; color: #1a326b; letter-spacing: 1px; }
        .company-name-ar { font-size: 20px; font-weight: 800; color: #1a326b; margin-top: -4px; font-family: 'Cairo', Arial, sans-serif; }
        .company-name-ar.center-name {
            margin-top: 0;
            margin-bottom: 2px;
            display: block;
            text-align: center;
            direction: rtl;
            unicode-bidi: plaintext;
        }
        .contract-title-row { display: none; }
        .invoice-title { text-align: center; margin: 4px 0 8px; }
        .invoice-title .en { font-size: 15px; font-weight: 800; color: #1a326b; }
        .invoice-title .ar {
            font-size: 16px;
            font-weight: 800;
            color: #1a326b;
            direction: rtl;
            unicode-bidi: plaintext;
            font-family: 'Cairo', Arial, sans-serif;
        }
        .meta-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-bottom: 8px; }
        .meta-card { border: 1px solid #1a326b; border-radius: 4px; padding: 6px 8px; }
        .meta-label { color: #1a326b; font-size: 9px; font-weight: 800; }
        .meta-value { font-size: 11px; margin-top: 2px; }
        .section-title {
            margin: 6px 0 4px;
            font-size: 11px;
            font-weight: 800;
            color: #1a326b;
            border-bottom: 1px solid #1a326b;
            padding-bottom: 3px;
        }
        .section-title .en,
        .section-title .ar {
            display: block;
            line-height: 1.1;
        }
        .section-title .ar {
            margin-top: 1px;
            font-size: 10px;
        }
        .charges-table { width: 100%; border: 1px solid #1a326b; margin-top: 4px; }
        .charges-table th,
        .charges-table td { border: 1px solid #1a326b; padding: 4px 5px; font-size: 10px; }
        .charges-table th { background: #f0f4f8; color: #1a326b; font-weight: 800; }
        .summary-table { width: 100%; margin-top: 8px; border: 1px solid #1a326b; }
        .summary-table td { border: 1px solid #1a326b; padding: 4px 6px; }
        .summary-table .label { font-weight: 700; color: #1a326b; }
        .summary-table .total-row td { background: #eff6ff; font-weight: 800; }
        .small { font-size: 9px; color: #6b7280; }
        .bilingual-label .en,
        .bilingual-label .ar {
            display: block;
            line-height: 1.12;
        }
        .bilingual-label .en {
            font-weight: 800;
        }
        .bilingual-label .ar {
            margin-top: 1px;
            font-size: 9px;
            font-weight: 700;
        }
    </style>
</head>
<body>
@php
    $reservation = $contract->reservation;
    $damageReport = $report->damageReport;
    $currency = $currencySymbol ?: config('app.currency_symbol', '$');
    $extraKilometerCharges = $extraKilometerCharges ?? ((float) $report->extra_kilometers * (float) $report->kilometer_rate);
    $lateFee = $lateFee ?? 0;
    $damageFee = $damageFee ?? (float) $report->damage_fee;
    $maintenanceFee = $maintenanceFee ?? (float) $report->maintenance_fee;
    $otherFee = $otherFee ?? (float) $report->other_fee;
    $fuelCredit = (float) ($report->fuel_credit ?? 0);
    $total = (float) $report->total_extra_charges;
@endphp
<div class="page">
    <div class="sheet">
        @include('admin.contracts.partials.pdf-header')

        <div class="invoice-title">
            <div class="en">ADDITIONAL CHARGES INVOICE</div>
            <div class="ar" dir="rtl"><span lang="ar">فاتورة الرسوم الإضافية</span></div>
        </div>

        <div class="meta-grid">
            <div class="meta-card">
                <div class="meta-label">Invoice No.</div>
                <div class="meta-value">{{ $report->report_number }}</div>
            </div>
            <div class="meta-card">
                <div class="meta-label">Contract No.</div>
                <div class="meta-value">{{ $contract->contract_number }}</div>
            </div>
            <div class="meta-card">
                <div class="meta-label">Reservation No.</div>
                <div class="meta-value">{{ $reservation?->reservation_number ?? '—' }}</div>
            </div>
            <div class="meta-card">
                <div class="meta-label">Actual Return Time</div>
                <div class="meta-value">{{ optional($report->actual_return_time)->format('Y-m-d H:i') ?? '—' }}</div>
            </div>
            <div class="meta-card">
                <div class="meta-label">Return Location</div>
                <div class="meta-value">{{ $report->return_location ?? '—' }}</div>
            </div>
            <div class="meta-card">
                <div class="meta-label">Damage Report</div>
                <div class="meta-value">{{ $damageReport?->report_number ?? '—' }}</div>
            </div>
        </div>

        <div class="section-title">
            <span class="en">Charges Breakdown</span>
            <span class="ar" dir="rtl">تفاصيل الرسوم</span>
        </div>
        <table class="charges-table">
            <thead>
                <tr>
                    <th style="width: 44%;">Charge Item</th>
                    <th style="width: 18%;">Qty</th>
                    <th style="width: 18%;">Rate</th>
                    <th style="width: 20%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="bilingual-label">
                        <span class="en">Extra Kilometers</span>
                        <span class="ar" dir="rtl">الكيلومترات الإضافية</span>
                    </td>
                    <td>{{ number_format((float) $report->extra_kilometers, 2) }}</td>
                    <td>{{ $currency }}{{ number_format((float) $report->kilometer_rate, 2) }}</td>
                    <td>{{ $currency }}{{ number_format((float) $extraKilometerCharges, 2) }}</td>
                </tr>
                <tr>
                    <td class="bilingual-label">
                        <span class="en">Cleaning Fee</span>
                        <span class="ar" dir="rtl">رسوم التنظيف</span>
                    </td>
                    <td>1</td>
                    <td>{{ $currency }}{{ number_format((float) $report->cleaning_fee, 2) }}</td>
                    <td>{{ $currency }}{{ number_format((float) $report->cleaning_fee, 2) }}</td>
                </tr>
                <tr>
                    <td class="bilingual-label">
                        <span class="en">Fuel Fee</span>
                        <span class="ar" dir="rtl">رسوم البنزين</span>
                    </td>
                    <td>1</td>
                    <td>{{ $currency }}{{ number_format((float) $report->fuel_fee, 2) }}</td>
                    <td>{{ $currency }}{{ number_format((float) $report->fuel_fee, 2) }}</td>
                </tr>
                @if($fuelCredit > 0)
                    <tr>
                        <td class="bilingual-label">
                            <span class="en">Fuel Credit</span>
                            <span class="ar" dir="rtl">رصيد البنزين</span>
                        </td>
                        <td>1</td>
                        <td>{{ $currency }}{{ number_format($fuelCredit, 2) }}</td>
                        <td>-{{ $currency }}{{ number_format($fuelCredit, 2) }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="bilingual-label">
                        <span class="en">Late Return Fee</span>
                        <span class="ar" dir="rtl">رسوم التأخير</span>
                    </td>
                    <td>{{ number_format((float) $report->late_hours, 2) }} hrs</td>
                    <td>{{ $currency }}{{ number_format((float) $report->late_hour_rate, 2) }}</td>
                    <td>{{ $currency }}{{ number_format((float) $lateFee, 2) }}</td>
                </tr>
                <tr>
                    <td class="bilingual-label">
                        <span class="en">Damage Fee</span>
                        <span class="ar" dir="rtl">رسوم الضرر</span>
                    </td>
                    <td>1</td>
                    <td>{{ $currency }}{{ number_format((float) $damageFee, 2) }}</td>
                    <td>{{ $currency }}{{ number_format((float) $damageFee, 2) }}</td>
                </tr>
                <tr>
                    <td class="bilingual-label">
                        <span class="en">Maintenance Fee</span>
                        <span class="ar" dir="rtl">رسوم الصيانة</span>
                    </td>
                    <td>1</td>
                    <td>{{ $currency }}{{ number_format((float) $maintenanceFee, 2) }}</td>
                    <td>{{ $currency }}{{ number_format((float) $maintenanceFee, 2) }}</td>
                </tr>
                <tr>
                    <td class="bilingual-label">
                        <span class="en">Other Fee</span>
                        <span class="ar" dir="rtl">رسوم أخرى</span>
                    </td>
                    <td>1</td>
                    <td>{{ $currency }}{{ number_format((float) $otherFee, 2) }}</td>
                    <td>{{ $currency }}{{ number_format((float) $otherFee, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <table class="summary-table">
            <tr>
                <td class="label bilingual-label" style="width: 70%;">
                    <span class="en">Total Extra Charges</span>
                    <span class="ar" dir="rtl">إجمالي الرسوم الإضافية</span>
                </td>
                <td style="width: 30%; text-align: right; font-size: 13px; font-weight: 800;">{{ $currency }}{{ number_format($total, 2) }}</td>
            </tr>
            @if($total < 0)
                <tr>
                    <td colspan="2" style="font-size: 10px; color: #0f9d58; font-weight: 800;">
                        Credit due to customer / رصيد مستحق للعميل
                    </td>
                </tr>
            @endif
            @php
                $paymentStatus = (string) ($report->payment_status ?? ($report->payment ? 'paid' : 'not_paid'));
                $paymentMethod = $report->payment && $paymentStatus === 'paid'
                    ? ucfirst(str_replace('_', ' ', (string) ($report->payment->payment_method->value ?? $report->payment->payment_method)))
                    : '-';
            @endphp
            <tr>
                <td class="label bilingual-label">
                    <span class="en">Payment Status</span>
                    <span class="ar" dir="rtl">حالة الدفع</span>
                </td>
                <td style="text-align: right;">{{ $paymentStatus === 'paid' ? 'Paid' : 'Not Paid' }}</td>
            </tr>
            <tr>
                <td class="label bilingual-label">
                    <span class="en">Payment Method</span>
                    <span class="ar" dir="rtl">طريقة الدفع</span>
                </td>
                <td style="text-align: right;">{{ $paymentMethod }}</td>
            </tr>
        </table>

        <div class="small" style="margin-top: 6px;">
            {{ $report->notes ?? '' }}
        </div>
    </div>
</div>
</body>
</html>
