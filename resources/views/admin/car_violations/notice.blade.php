<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $violation->violation_number ?: ('VIOL-'.$violation->id) }} - Police Notice</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #0f1c42;
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.25;
            background: #fff;
            font-weight: 600;
        }
        .page { width: 100%; padding: 4px 6px; }
        .sheet { border: 2px solid #1a326b; background: #fff; padding: 2px; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 3px 5px; }
        .ar {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            direction: rtl;
            unicode-bidi: plaintext;
            text-align: right;
        }

        .header-table { margin-bottom: 2px; }
        .header-table td { padding: 0 4px; vertical-align: top; }
        .header-left { width: 25%; font-size: 9px; line-height: 1.4; font-weight: 700; }
        .header-center { width: 50%; text-align: center; }
        .header-right { width: 25%; text-align: right; }

        .company-name-en { font-size: 18px; font-weight: 800; color: #1a326b; letter-spacing: 1px; }
        .company-name-ar { font-size: 20px; font-weight: 800; color: #1a326b; margin-top: -4px; }
        .company-name-ar.center-name {
            margin-top: 0;
            margin-bottom: 2px;
            display: block;
            text-align: center;
            direction: rtl;
            unicode-bidi: plaintext;
        }

        .contract-title-row { text-align: center; margin-top: -6px; margin-bottom: 4px; }
        .contract-title-en { font-size: 14px; font-weight: 800; color: #1a326b; display: inline-block; }
        .contract-title-ar { font-size: 16px; font-weight: 800; color: #1a326b; display: inline-block; margin-left: 8px; }

        .serial-no { color: #1a326b; font-size: 12px; font-weight: 800; margin-top: 4px; }
        .serial-no span { color: #d02027; margin-left: 4px; font-size: 14px; }

        .grid-table { border: 2px solid #1a326b; }
        .grid-table td { border: 1px solid #1a326b; }
        .cell-title { display: flex; justify-content: space-between; align-items: center; font-weight: 800; font-size: 10px; color: #1a326b; margin-bottom: 4px; }
        .cell-title .ar { font-size: 11px; }

        .field-row { display: flex; justify-content: space-between; margin-bottom: 3px; align-items: flex-end; }
        .field-label { font-size: 9px; font-weight: 700; color: #1a326b; width: 25%; }
        .field-label-ar {
            font-size: 10px;
            font-weight: 700;
            color: #1a326b;
            width: 25%;
            text-align: right;
            direction: rtl;
            unicode-bidi: plaintext;
        }
        .field-val-dotted { border-bottom: 1px dotted #1a326b; width: 50%; padding: 0 4px; text-align: center; color: #000; font-size: 10px; min-height: 14px; }

        .ack-box {
            background: #1a326b;
            color: #fff;
            padding: 6px 3px 3px;
            text-align: center;
            margin: 4px 4px 3px 4px;
            overflow: hidden;
            box-sizing: border-box;
        }
        .ack-box-title {
            font-size: 9px;
            font-weight: 800;
            display: flex;
            justify-content: space-between;
            padding: 0 4px;
            line-height: 1.05;
            gap: 6px;
        }
        .ack-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.55);
            margin: 3px 6px 4px;
        }
        .ack-text-ar {
            text-align: center;
            font-size: 8px;
            padding-top: 0;
            line-height: 1.12;
            direction: rtl;
            unicode-bidi: plaintext;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        .ack-text-en {
            text-align: center;
            font-size: 6.5px;
            line-height: 1.12;
            font-weight: 500;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .signatures {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .signatures td {
            width: 50%;
            text-align: center;
            font-size: 9px;
            color: #1a326b;
            padding: 6px;
            vertical-align: top;
        }
        .sign-line { border-bottom: 1px dotted #1a326b; min-height: 14px; margin-bottom: 2px; }

        .footer-end { text-align: center; font-size: 9px; font-weight: 800; color: #1a326b; padding-top: 6px; }

        .notice-meta { width: 100%; border: 1px solid #1a326b; margin-top: 4px; margin-bottom: 4px; }
        .notice-meta td { border: 1px solid #1a326b; padding: 4px 6px; font-size: 9px; }
        .notice-meta .label { color: #6b7280; font-weight: 700; font-size: 8px; }
        .notice-meta .value { color: #0f1c42; font-weight: 800; font-size: 10px; }

        .message-box { border: 1px solid #1a326b; padding: 5px 6px; margin: 4px 4px 0; }
        .message-box .en { font-size: 8px; line-height: 1.2; }
        .message-box .ar { font-size: 8.5px; line-height: 1.2; direction: rtl; unicode-bidi: plaintext; text-align: right; margin-top: 3px; }
    </style>
</head>
<body>
@php
    $reservation = $reservation ?? null;
    $contract = $contract ?? null;
    $car = $car ?? null;
    $renter = $renter ?? null;
    $branch = $branch ?? null;

    $formatDate = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : '-';
    $carLabel = $car ? trim(($car->year ? $car->year.' ' : '').($car->make ?? '').' '.($car->model ?? '')) : '-';
    $plateNumber = $contract?->plate_number ?: $car?->license_plate ?: '-';
    $renterName = $contract?->renter_name ?: ($renter?->name ?? '-');
    $renterPhone = $contract?->renter_phone ?: '-';
    $renterId = $contract?->renter_id_number ?: '-';
    $rentalPeriod = collect([
        optional($contract?->start_date)?->toDateString(),
        optional($contract?->end_date)?->toDateString(),
    ])->filter()->implode(' - ');
    $rentalPeriod = $rentalPeriod !== '' ? $rentalPeriod : '-';
    $violationStatus = $violation->status instanceof \App\Enums\CarViolationStatus ? $violation->status->label() : ucfirst((string) $violation->status);
@endphp

<div class="page">
    <div class="sheet">
        <table class="header-table">
            <tr>
                <td class="header-left">
                    <div>C.R : {{ $branch->cr_number ?? '-' }}</div>
                    <div>P.O Box : {{ $branch->po_box ?? '-' }}</div>
                    <div>P.C : {{ $branch->postal_code ?? '-' }}</div>
                    <div>Sultanate of Oman</div>
                    <div>GSM 1 : {{ $branch->phone_1 ?? '-' }}</div>
                    <div>GSM 2 : {{ $branch->phone_2 ?? '-' }}</div>
                    <div>GSM 3 : {{ $branch->whatsapp ?? '-' }}</div>
                    <div class="serial-no">No. <span>{{ $violation->violation_number ?: ('VIOL-'.$violation->id) }}</span></div>
                </td>
                <td class="header-center">
                    @if(!empty($companyLogo))
                        <img src="{{ $companyLogo }}" style="max-height: 48px; object-fit: contain; margin-bottom:2px;" alt="Logo" />
                    @endif
                    <div class="company-name-en">{{ strtoupper($companyName) }}</div>
                    <div class="company-name-ar ar center-name">{{ $companyName }}</div>
                </td>
                <td class="header-right ar" style="font-size: 9px; line-height: 1.4; font-weight: 700;">
                    <div>س.ت : {{ $branch->cr_number ?? '-' }}</div>
                    <div>ص.ب : {{ $branch->po_box ?? '-' }}</div>
                    <div>الرمز البريدي : {{ $branch->postal_code ?? '-' }}</div>
                    <div>سلطنة عمان</div>
                    <div>نقال 1 : {{ $branch->phone_1 ?? '-' }}</div>
                    <div>نقال 2 : {{ $branch->phone_2 ?? '-' }}</div>
                    <div>نقال 3 : {{ $branch->whatsapp ?? '-' }}</div>
                </td>
            </tr>
        </table>

        <div class="contract-title-row">
            <div class="contract-title-en">OFFICIAL NOTICE TO POLICE</div>
            <div class="contract-title-ar ar">إشعار رسمي إلى الشرطة</div>
        </div>

        <table class="notice-meta">
            <tr>
                <td>
                    <div class="label">Violation No.</div>
                    <div class="value">{{ $violation->violation_number ?: ('VIOL-'.$violation->id) }}</div>
                </td>
                <td>
                    <div class="label">Violation Date</div>
                    <div class="value">{{ $formatDate($violation->violation_date) }}</div>
                </td>
                <td>
                    <div class="label">Authority</div>
                    <div class="value">{{ $violation->authority ?? '-' }}</div>
                </td>
                <td>
                    <div class="label">Location</div>
                    <div class="value">{{ $violation->location ?? '-' }}</div>
                </td>
            </tr>
        </table>

        <table class="grid-table">
            <tr>
                <td style="width: 50%;">
                    <div class="cell-title">
                        <span>Rental / Violation Details</span>
                        <span class="ar">تفاصيل الإيجار والمخالفة</span>
                    </div>
                    <div class="field-row">
                        <div class="field-label">Contract No.</div>
                        <div class="field-val-dotted">{{ $contract?->contract_number ?? '-' }}</div>
                        <div class="field-label-ar ar">رقم العقد:</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label">Reservation No.</div>
                        <div class="field-val-dotted">{{ $reservation?->reservation_number ?? '-' }}</div>
                        <div class="field-label-ar ar">رقم الحجز:</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label">Vehicle</div>
                        <div class="field-val-dotted">{{ $carLabel }}</div>
                        <div class="field-label-ar ar">السيارة:</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label">Plate No.</div>
                        <div class="field-val-dotted">{{ $plateNumber }}</div>
                        <div class="field-label-ar ar">رقم اللوحة:</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label">Renter Name</div>
                        <div class="field-val-dotted">{{ $renterName }}</div>
                        <div class="field-label-ar ar">اسم المستأجر:</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label">Renter Phone</div>
                        <div class="field-val-dotted">{{ $renterPhone }}</div>
                        <div class="field-label-ar ar">هاتف المستأجر:</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label">Renter ID / Passport</div>
                        <div class="field-val-dotted">{{ $renterId }}</div>
                        <div class="field-label-ar ar">الرقم المدني / الجواز:</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label">Rental Period</div>
                        <div class="field-val-dotted">{{ $rentalPeriod }}</div>
                        <div class="field-label-ar ar">فترة الإيجار:</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label">Amount</div>
                        <div class="field-val-dotted">{{ number_format((float) $violation->amount, 2) }}</div>
                        <div class="field-label-ar ar">المبلغ:</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label">Status</div>
                        <div class="field-val-dotted">{{ $violationStatus }}</div>
                        <div class="field-label-ar ar">الحالة:</div>
                    </div>
                </td>
                <td style="width: 50%;">
                    <div class="cell-title">
                        <span class="ar">تفاصيل الإيجار والمخالفة</span>
                        <span>Rental / Violation Details</span>
                    </div>
                    <div class="field-row">
                        <div class="field-label-ar ar">رقم العقد:</div>
                        <div class="field-val-dotted">{{ $contract?->contract_number ?? '-' }}</div>
                        <div class="field-label">Contract No.</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label-ar ar">رقم الحجز:</div>
                        <div class="field-val-dotted">{{ $reservation?->reservation_number ?? '-' }}</div>
                        <div class="field-label">Reservation No.</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label-ar ar">السيارة:</div>
                        <div class="field-val-dotted">{{ $carLabel }}</div>
                        <div class="field-label">Vehicle</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label-ar ar">رقم اللوحة:</div>
                        <div class="field-val-dotted">{{ $plateNumber }}</div>
                        <div class="field-label">Plate No.</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label-ar ar">اسم المستأجر:</div>
                        <div class="field-val-dotted">{{ $renterName }}</div>
                        <div class="field-label">Renter Name</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label-ar ar">هاتف المستأجر:</div>
                        <div class="field-val-dotted">{{ $renterPhone }}</div>
                        <div class="field-label">Renter Phone</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label-ar ar">الرقم المدني / الجواز:</div>
                        <div class="field-val-dotted">{{ $renterId }}</div>
                        <div class="field-label">Renter ID / Passport</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label-ar ar">فترة الإيجار:</div>
                        <div class="field-val-dotted">{{ $rentalPeriod }}</div>
                        <div class="field-label">Rental Period</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label-ar ar">المبلغ:</div>
                        <div class="field-val-dotted">{{ number_format((float) $violation->amount, 2) }}</div>
                        <div class="field-label">Amount</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label-ar ar">الحالة:</div>
                        <div class="field-val-dotted">{{ $violationStatus }}</div>
                        <div class="field-label">Status</div>
                    </div>
                </td>
            </tr>
        </table>

        @if(filled($violation->description))
            <div class="ack-box" style="margin-top: 4px;">
                <div class="ack-box-title">
                    <span>Violation Description</span>
                    <span class="ar">وصف المخالفة</span>
                </div>
                <div class="ack-divider"></div>
                <div class="ack-text-en">{{ $violation->description }}</div>
                <div class="ack-text-ar">{{ $violation->description }}</div>
            </div>
        @endif

        <div class="ack-box" style="margin-top: 4px;">
            <div class="ack-box-title">
                <span>Official Message</span>
                <span class="ar">رسالة رسمية</span>
            </div>
            <div class="ack-divider"></div>
            <div class="ack-text-en">
                To whom it may concern, this notice confirms that the above violation was recorded while the vehicle was under rental for the customer named above during the rental period shown in this notice. The rental contract and reservation details are listed for reference, together with the vehicle and renter information.
            </div>
            <div class="ack-text-ar">
                نفيدكم علماً بأن المخالفة الموضحة أعلاه قد تم رصدها أثناء فترة تأجير المركبة للمستأجر المذكور أعلاه، وذلك خلال مدة الإيجار الموضحة في هذا الإشعار. وقد أُرفقت بيانات العقد والحجز وبيانات المركبة والمستأجر للرجوع إليها عند الحاجة.
            </div>
        </div>

        <table class="signatures">
            <tr>
                <td>
                    <div class="small">Issued By</div>
                    <div style="color:#0f1c42; font-weight:800;">{{ $branch?->name ?? $companyName }}</div>
                    <div class="sign-line"></div>
                    <div class="small">Authorized Signature / توقيع المفوض</div>
                </td>
                <td class="ar">
                    <div class="small">صدر بواسطة</div>
                    <div style="color:#0f1c42; font-weight:800;">{{ $branch?->name ?? $companyName }}</div>
                    <div class="sign-line"></div>
                    <div class="small">توقيع المفوض / Authorized Signature</div>
                </td>
            </tr>
        </table>

        <div class="footer-end">Generated at {{ now()->format('Y-m-d H:i') }}</div>
    </div>
</div>
</body>
</html>
