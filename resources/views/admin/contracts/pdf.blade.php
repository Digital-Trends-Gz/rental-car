<!DOCTYPE html>
<html lang="{{ $locale }}" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <title>{{ __('contracts.pdf.document_title', ['number' => $contract->contract_number]) }}</title>
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
            background: #fff;
            color: #0f2a63;
            font-family: cairo, "TahomaPdf", "DejaVu Sans", Arial, sans-serif;
            font-size: 8.8px;
            line-height: 1.22;
            font-weight: 700;
            direction: ltr;
        }
        .page { width: 100%; }
        .contract {
            border: 2px solid #17306f;
            padding: 2px;
            page-break-inside: avoid;
        }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .blue { color: #17306f; }
        .ar,
        .ar-label,
        .inline-label.ar,
        .company-ar,
        .title-line .ar {
            font-family: cairo, "TahomaPdf", "DejaVu Sans", Arial, sans-serif;
            direction: rtl;
            unicode-bidi: plaintext;
            text-align: right;
            font-size: 1em;
            line-height: 1.18;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .strong { font-weight: 900; }
        .bordered { border: 2px solid #17306f; }
        .cell { border: 1px solid #17306f; padding: 4px 6px; }
        .section-title {
            font-size: 10px;
            font-weight: 900;
            color: #17306f;
            padding-bottom: 4px;
        }
        .section-title table td { padding: 0; }
        .field td { padding: 2px 3px; }
        .renter-fields td { padding-top: 10px; padding-bottom: 0px; }
        .renter-fields .value {
            min-height: 28px;
            padding-top: 7px;
            padding-bottom: 0px;
            line-height: 1.05;
        }
        .renter-fields .ar-label { font-size: 1.2em; }
        .renter-visa td { padding-top: 10px; padding-bottom: 10px; }
        .renter-visa .inline-value {
            min-height: 28px;
            padding-top: 8px;
            padding-bottom: 1px;
            line-height: 1.05;
        }
        .renter-visa .inline-label.ar { font-size: 1.2em; }
        .field .en { width: 22%; color: #17306f; white-space: nowrap; }
        .field .value {
            width: 52%;
            color: #000;
            border-bottom: 1px dotted #17306f;
            text-align: center;
            min-height: 14px;
            font-weight: 900;
        }
        .field .ar-label { width: 26%; color: #17306f; white-space: nowrap; }
        .header td { padding: 0 4px; }
        .head-left, .head-right { width: 23%; font-size: 8px; line-height: 1.35; }
        .head-main {
            width: 54%;
            text-align: center;
            border-left: 1px solid #d8dbe5;
            border-right: 1px solid #d8dbe5;
        }
        .company-en { font-size: 21px; line-height: 1.02; font-weight: 900; letter-spacing: .5px; }
        .company-ar { font-size: 21px; line-height: 1.02; font-weight: 900; }
        .title-line { font-size: 14px; line-height: 1.08; font-weight: 900; }
        .title-line .ar { display: inline-block; margin-left: 8px; font-size: 14px; }
        .logo { max-height: 38px; max-width: 180px; object-fit: contain; }
        .serial { margin-top: 4px; font-size: 9px; }
        .serial span { color: #d6202a; font-size: 11px; font-weight: 900; }
        .inline-fields td { padding: 2px 3px; vertical-align: bottom; }
        .inline-label { white-space: nowrap; color: #17306f; }
        .inline-value {
            border-bottom: 1px dotted #17306f;
            color: #000;
            text-align: center;
            font-weight: 900;
            min-height: 14px;
        }
        .diagram-cell { width: 50%; height: 190px; }
        .diagram-head td { font-size: 8px; color: #17306f; padding: 0 3px 3px; }
        .damage-wrap { height: 136px; text-align: center; }
        .damage-img { max-width: 290px; max-height: 124px; object-fit: contain; }
        .empty-diagram { color: #999; padding-top: 48px; font-size: 9px; }
        .status-table td { padding: 2px 3px; font-size: 7.5px; }
        .box {
            display: inline-block;
            width: 8px;
            height: 8px;
            border: 1px solid #17306f;
            line-height: 7px;
            text-align: center;
            font-size: 7px;
        }
        .rates th, .rates td {
            border: 1px solid #17306f;
            text-align: center;
            padding: 2px;
        }
        .rates th { background: #eef3f8; font-size: 7.8px; }
        .rates td { font-size: 8.3px; font-weight: 900; padding: 4px 2px; }
        .damage-cases-title {
            font-size: 9px;
            font-weight: 900;
            color: #111;
            padding: 2px 3px 4px;
        }
        .damage-cases-title table td { padding: 0; }
        .damage-cases-title .ar { color: #17306f; }
        .damage-cases {
            width: 100%;
            border-collapse: collapse;
        }
        .damage-cases th,
        .damage-cases td {
            border-bottom: 1px solid #d7dde5;
            color: #111;
            font-size: 7px;
            font-weight: 700;
            padding: 3px 5px;
            text-align: left;
        }
        .damage-cases th {
            background: #f7f8fa;
            color: #6b7280;
            font-size: 6.8px;
            letter-spacing: .7px;
            text-transform: uppercase;
        }
        .damage-cases th .ar {
            display: inline;
            color: #6b7280;
            font-size: 6px;
            letter-spacing: 0;
            line-height: 1.1;
            text-transform: none;
            margin-left: 4px;
        }
        .damage-cases .center-col { text-align: center; }
        .notice {
            background: #17306f;
            color: #fff;
            padding: 6px 6px;
            margin-top: 6px;
            text-align: center;
            font-size: 7.7px;
            line-height: 2.05;
        }
        .period td { padding: 1px 2px; }
        .rules > td { border: 1px solid #17306f; padding: 6px 7px; }
        .rules-list { margin: 0; padding-left: 12px; font-size: 8px; line-height: 1.36; }
        .rules-list li { margin-bottom: 3px; }
        .dual td { width: 50%; padding: 0 3px 1px; }
        .ack-title, .important {
            background: #17306f;
            color: #fff;
            text-align: center;
            padding: 6px;
        }
        .ack-title { margin-bottom: 4px; }
        .ack-title .en { font-size: 8.5px; }
        .ack-title .ar { font-size: 8.5px; }
        .ack-text { font-size: 6.7px; line-height: 2.15; }
        .important { margin-top: 4px; font-size: 7.6px; line-height: 3.0; }
        .sign td { text-align: center; padding: 7px 3px 2px; font-size: 7.8px; }
        .sign-line { border-bottom: 1px dotted #17306f; height: 18px; margin-bottom: 3px; }
        .sign-image {
            max-width: 95px;
            max-height: 16px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        .footer {
            text-align: center;
            color: #17306f;
            font-size: 8.2px;
            font-weight: 900;
            padding: 5px 2px 2px;
        }
        .car_detail .ar.inline-label, .period .ar.inline-label { text-align: center; }
    </style>
</head>
<body>
@php
    $drivers = collect([]);
    if ($contract->primaryDriver) { $drivers->push($contract->primaryDriver); }
    foreach ($contract->additionalDrivers as $driver) { $drivers->push($driver); }
    $primaryDriver = $contract->primaryDriver;
    $reservation = $contract->reservation;
    $reservationCar = $reservation?->car;
    $reservationUser = $reservation?->user;

    $siteSettings = $siteSettings ?? ($contract->tenant?->siteSetting ? \App\Models\TenantSiteSetting::forTenant($contract->tenant) : []);
    $contactPhone = data_get($siteSettings, 'contact.phone') ?? $contract->branch?->phone_1 ?? $contract->branch?->phone ?? '-';
    $contactWhatsapp = data_get($siteSettings, 'contact.whatsapp') ?? '-';
    $contactAddress = data_get($siteSettings, 'contact.address.'.$locale) ?? data_get($siteSettings, 'contact.address.en') ?? data_get($siteSettings, 'contact.address.ar') ?? $contract->branch?->address ?? '-';
    $pdfHeader = data_get($siteSettings, 'pdf_header', []);
    $headerCompanyNameEn = data_get($pdfHeader, 'company_name.en') ?: $companyName;
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
    $contractPdf = data_get($siteSettings, 'contract_pdf', []);
    $contractText = static function (string $key, string $lang) use ($contractPdf) {
        $value = data_get($contractPdf, "{$key}.{$lang}");

        if (filled($value)) {
            return $value;
        }

        return \Illuminate\Support\Facades\Lang::get("contracts.pdf.contract_texts.{$key}.{$lang}", [], $lang);
    };
    $handoverState = is_array($contract->handover_state ?? null) ? $contract->handover_state : [];
    $renterSignature = data_get($handoverState, 'steps.terms_confirmation.payload.signature_image')
        ?? data_get($handoverState, 'phases.delivery.steps.terms_confirmation.payload.signature_image')
        ?? data_get($handoverState, 'delivery.steps.terms_confirmation.payload.signature_image');
    $renterSignatureUrl = $renterSignatureImage ?? (is_array($renterSignature)
        ? (data_get($renterSignature, 'url') ?: data_get($renterSignature, 'file_path'))
        : $renterSignature);
    if ($renterSignatureUrl && !str_starts_with((string) $renterSignatureUrl, 'data:') && !preg_match('/^https?:\/\//i', (string) $renterSignatureUrl)) {
        $renterSignatureUrl = url('/'.ltrim((string) $renterSignatureUrl, '/'));
    }
    $inchargeSignatureUrl = $inchargeSignatureImage ?? data_get($contractPdf, 'incharge_signature_image');
    if ($inchargeSignatureUrl && !str_starts_with((string) $inchargeSignatureUrl, 'data:') && !preg_match('/^https?:\/\//i', (string) $inchargeSignatureUrl)) {
        $inchargeSignatureUrl = url('/'.ltrim((string) $inchargeSignatureUrl, '/'));
    }

    $dailyRate = $contract->price_per_day ?? $reservation?->daily_rate ?? $reservationCar?->price_per_day;
    $weeklyRate = $contract->price_per_week ?? $reservationCar?->price_per_week;
    $monthlyRate = $contract->price_per_month ?? $reservationCar?->price_per_month;
    $allowedKmDay = $contract->allowed_km_per_day ?? $reservationCar?->allowed_km_per_day;
    $allowedKmWeek = $contract->allowed_km_per_week ?? $reservationCar?->allowed_km_per_week;
    $allowedKmMonth = $contract->allowed_km_per_month ?? $reservationCar?->allowed_km_per_month;

    $pickupDate = optional($reservation?->start_date)->format('Y-m-d') ?? optional($contract->start_date)->format('Y-m-d') ?? '';
    $returnDate = optional($reservation?->end_date)->format('Y-m-d') ?? optional($contract->end_date)->format('Y-m-d') ?? '';
    $pickupTime = optional($reservation?->pickup_time)->format('H:i') ?? '';
    $returnTime = optional($reservation?->return_time)->format('H:i') ?? '';

    $lineValue = static fn ($value) => filled($value) ? $value : '';
    $passDoc = optional($primaryDriver?->documents->firstWhere('document_type', 'passport'))->document_number;
    $visaDoc = optional($primaryDriver?->documents->firstWhere('document_type', 'visa'))->document_number;
    $passportNumber = $primaryDriver?->passport_number ?: $passDoc;
    $passportExpiry = optional($primaryDriver?->passport_expiry_date)->format('Y-m-d');
    $visaNumber = $primaryDriver?->visa_number ?: $visaDoc;
    $visaExpiry = optional($primaryDriver?->visa_expiry_date)->format('Y-m-d');
    $licenseIssueDate = optional($primaryDriver?->license_issue_date)->format('Y-m-d');
    $actualReturnTime = $contract->actual_return_time;
    $vehType = trim(($reservationCar?->make ?? '').' '.($reservationCar?->model ?? ''));
    $cleanBefore = $contract->vehicle_condition_before === 'clean';
    $cleanAfter = $contract->vehicle_condition_after === 'clean';
    $damageValueTranslations = [
        'front' => 'الأمام',
        'back' => 'الخلف',
        'left' => 'اليسار',
        'right' => 'اليمين',
        'top' => 'الأعلى',
        'front bumper' => 'الصدام الأمامي',
        'rear bumper' => 'الصدام الخلفي',
        'left front door' => 'الباب الأمامي الأيسر',
        'right front door' => 'الباب الأمامي الأيمن',
        'left rear door' => 'الباب الخلفي الأيسر',
        'right rear door' => 'الباب الخلفي الأيمن',
        'left rear quarter' => 'الربع الخلفي الأيسر',
        'right rear quarter' => 'الربع الخلفي الأيمن',
        'roof' => 'السقف',
        'hood' => 'غطاء المحرك',
        'trunk' => 'الصندوق الخلفي',
        'scratch' => 'خدش',
        'dent' => 'انبعاج',
        'crack' => 'كسر',
        'broken' => 'مكسور',
        'minor' => 'خفيف',
        'moderate' => 'متوسط',
        'major' => 'كبير',
        'severe' => 'شديد',
    ];
    $damageValue = static function ($value) use ($damageValueTranslations) {
        $value = (string) ($value ?? '-');
        if ($value === '-' || $value === '') {
            return '-';
        }

        $translation = $damageValueTranslations[strtolower($value)] ?? null;

        return $translation ? $value.' / '.$translation : $value;
    };
@endphp

<div class="page">
    <div class="contract">
        <table class="header">
            <tr>
                <td class="head-left">
                    <div>{{ $headerCountryEn }}</div>
                    <div>C.R : {{ $headerCrNumber }}</div>
                    <div>P.O Box : {{ $headerPoBox }}</div>
                    <div>P.C : {{ $headerPc }}</div>
                    <div>GSM : {{ $headerGsm1 }}</div>
                    <div>GSM : {{ $headerGsm2 }}</div>
                    <div>GSM : {{ $headerGsm3 }}</div>
                    <div class="serial">{{ $headerRegistryLabelEn }} <span>{{ $contract->contract_number }}</span></div>
                </td>
                <td class="head-main">
                    @if(!empty($companyLogo))
                        <img src="{{ $companyLogo }}" class="logo" alt="Logo" />
                    @endif
                    <div class="company-en">{{ strtoupper($headerCompanyNameEn) }}</div>
                    <div class="company-ar ar center">{{ $headerCompanyNameAr }}</div>
                    <div class="title-line">CAR RENTAL CONTRACT <span class="ar">عقد إيجار سيارة</span></div>
                </td>
                <td class="head-right ar">
                    <div>{{ $headerCountryAr }}</div>
                    <div>رقم السجل التجاري : {{ $headerCrNumber }}</div>
                    <div>ص.ب : {{ $headerPoBox }}</div>
                    <div>الرمز البريدي : {{ $headerPc }}</div>
                    <div>نقال : {{ $headerGsm1 }}</div>
                    <div>نقال : {{ $headerGsm2 }}</div>
                    <div>نقال : {{ $headerGsm3 }}</div>
                    <div class="serial">رقم العقد : <span>{{ $contract->contract_number }}</span></div>

                </td>
            </tr>
        </table>

        <table class="bordered">
            <tr>
                <td class="cell" style="width: 50%;">
                    <div class="section-title"><table><tr><td>PASSPORT/ID DETAILS</td><td class="ar">بيانات جواز السفر والبطاقة</td></tr></table></div>
                    <table class="field">
                        <tr><td class="en">Civil No:</td><td class="value">{{ $lineValue($primaryDriver?->identity_number ?? $primaryDriver?->residency_number ?? $contract->renter_id_number) }}</td><td class="ar ar-label">الرقم المدني :</td></tr>
                        <tr><td class="en">Exp. Date:</td><td class="value">{{ optional($primaryDriver?->identity_expiry_date)->format('Y-m-d') ?? '' }}</td><td class="ar ar-label">تاريخ الانتهاء :</td></tr>
                        <tr><td class="en">Passport No:</td><td class="value">{{ $lineValue($passportNumber) }}</td><td class="ar ar-label">رقم الجواز :</td></tr>
                        <tr><td class="en">Exp. Date:</td><td class="value">{{ $lineValue($passportExpiry) }}</td><td class="ar ar-label">تاريخ الانتهاء :</td></tr>
                        <tr><td class="en">Sponsor:</td><td class="inline-value">{{ $lineValue($primaryDriver?->sponsor) }}</td><td class="ar ar-label">الكفيل</td></tr>
                        <tr> <td class="en">Exp. Date:</td><td class="inline-value"></td><td class="ar ar-label">تاريخ الانتهاء</td></tr>
                      </table>
                  

                    <div class="section-title" style="margin-top: 4px;"><table><tr><td>DRIVING LICENSE DETAILS</td><td class="ar">بيانات رخصة القيادة</td></tr></table></div>
                    <table class="field">
                        <tr><td class="en">Issue Date:</td><td class="value">{{ $lineValue($licenseIssueDate) }}</td><td class="ar ar-label">تاريخ الإصدار :</td></tr>
                        <tr><td class="en">Exp. Date:</td><td class="value">{{ optional($primaryDriver?->license_expiry_date)->format('Y-m-d') ?? '' }}</td><td class="ar ar-label">تاريخ الانتهاء :</td></tr>
                        <tr><td class="en">License No:</td><td class="value">{{ $lineValue($primaryDriver?->license_number) }}</td><td class="ar ar-label">رقم الرخصة :</td></tr>
                        <tr><td class="en">Issue Place:</td><td class="value">{{ $lineValue($primaryDriver?->place_of_issue) }}</td><td class="ar ar-label">مكان الإصدار :</td></tr>
                    </table>
                </td>
                <td class="cell" style="width: 50%;">
                    <div class="section-title"><table><tr><td>RENTER DETAILS</td><td class="ar">تفاصيل المستأجر</td></tr></table></div>
                    <table class="field renter-fields">
                        <tr><td class="en">Renter Name:</td><td class="value">{{ $lineValue($contract->renter_name ?: $primaryDriver?->full_name ?: $reservationUser?->name) }}</td><td class="ar ar-label">اسم المستأجر :</td></tr>
                        <tr><td class="en">Nationality:</td><td class="value">{{ $lineValue($primaryDriver?->nationality) }}</td><td class="ar ar-label">الجنسية :</td></tr>
                        <tr><td class="en">Tel:</td><td class="value">{{ $lineValue($contract->renter_phone ?: $primaryDriver?->phone) }}</td><td class="ar ar-label">هاتف :</td></tr>
                        <tr><td class="en">Address:</td><td class="value">{{ $lineValue($reservation?->pickup_location ?? $contactAddress) }}</td><td class="ar ar-label">العنوان :</td></tr>
                        <tr><td class="en">Visa No:</td><td class="inline-value">{{ $lineValue($visaNumber) }}</td><td class="ar ar-label">رقم التأشيرة :</td></tr>
                        <tr><td class="en">Exp. Date:</td><td class="inline-value">{{ $lineValue($visaExpiry) }}</td><td class="ar ar-label">تاريخ الانتهاء :</td></tr>
                    </table>
                    
                </td>
            </tr>
            <tr>
                <td colspan="2" class="cell">
                    <div class="section-title"><table><tr><td>CAR DETAILS</td><td class="ar">بيانات المركبة</td></tr></table></div>
                    <table class="inline-fields car_detail"><tr>
                        <td class="inline-label">Vehicle Type:</td><td class="inline-value">{{ $lineValue($vehType) }}</td><td class="ar inline-label">نوع السيارة :</td>
                        <td class="inline-label">Plate No:</td><td class="inline-value">{{ $lineValue($reservationCar?->license_plate ?? $contract->plate_number) }}</td><td class="ar inline-label">رقم اللوحة :</td>
                        <td class="inline-label">Vehicle Color:</td><td class="inline-value">{{ $lineValue($reservationCar?->color ?? $contract->car_details) }}</td><td class="ar inline-label">لون السيارة :</td>
                    </tr></table>
                </td>
            </tr>
            <tr>
                <td class="cell diagram-cell">
                    <table class="diagram-head"><tr><td>العودة</td><td class="center">المغادرة</td><td class="right">الفحص النظري</td></tr></table>
                    <div class="damage-wrap">
                        @if(!empty($damageDiagram['data_uri']))
                            <img src="{{ $damageDiagram['data_uri'] }}" class="damage-img" alt="Damage" />
                        @else
                            <div class="empty-diagram">{{ __('contracts.pdf.empty.no_current_damages') }}</div>
                        @endif
                    </div>
                    <table class="status-table"><tr>
                        <td class="ar">حالة المركبة بعد<br><span class="box">{{ $cleanAfter ? '✓' : '' }}</span> نظيف / Clean<br><span class="box">{{ !$cleanAfter ? '✓' : '' }}</span> غير نظيف / Not Clean</td>
                        <td class="ar">حالة المركبة قبل<br><span class="box">{{ $cleanBefore ? '✓' : '' }}</span> نظيف / Clean<br><span class="box">{{ !$cleanBefore ? '✓' : '' }}</span> غير نظيف / Not Clean</td>
                    </tr></table>
                </td>
                <td class="cell">
                    <div class="section-title"><table><tr><td>Rental Rate & Allowed KM</td><td class="ar">سعر الإيجار والكيلومترات المجانية</td></tr></table></div>
                    <table class="rates">
                        <tr><th>Per Month<br><span class="ar">السعر الشهري</span></th><th>Per Week<br><span class="ar">السعر الأسبوعي</span></th><th>Per Day<br><span class="ar">السعر اليومي</span></th></tr>
                        <tr><td>{{ $monthlyRate !== null ? number_format((float) $monthlyRate, 2) : '' }}<br>ر.ع - OMR</td><td>{{ $weeklyRate !== null ? number_format((float) $weeklyRate, 2) : '' }}<br>ر.ع - OMR</td><td>{{ $dailyRate !== null ? number_format((float) $dailyRate, 2) : '' }}<br>ر.ع - OMR</td></tr>
                        <tr><td>{{ $lineValue($allowedKmMonth) }}<br>كم - KM</td><td>{{ $lineValue($allowedKmWeek) }}<br>كم - KM</td><td>{{ $lineValue($allowedKmDay) }}<br>كم - KM</td></tr>
                    </table>
                    <div class="notice">
                        {{ $contractText('mileage_notice', 'ar') }}<br>
                        {{ $contractText('mileage_notice', 'en') }}
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="cell">
                    <div class="damage-cases-title">
                        <table><tr><td>Current Car Damages</td><td class="ar">أضرار السيارة الحالية</td></tr></table>
                    </div>
                    <table class="damage-cases">
                        <thead>
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 24%;">Zone <span class="ar">المنطقة / </span></th>
                                <th style="width: 13%;">View <span class="ar">الجهة / </span></th>
                                <th style="width: 17%;">Type <span class="ar">النوع / </span></th>
                                <th style="width: 15%;">Severity <span class="ar">الشدة / </span></th>
                                <th style="width: 8%;" class="center-col">Qty <span class="ar">العدد / </span></th>
                                <th style="width: 18%;">Notes <span class="ar">ملاحظات / </span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($currentDamageCases as $damage)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $damageValue($damage['zone_label'] ?? '-') }}</td>
                                    <td>{{ $damageValue($damage['view_side_label'] ?? '-') }}</td>
                                    <td>{{ $damageValue($damage['damage_type_label'] ?? '-') }}</td>
                                    <td>{{ $damageValue($damage['severity_label'] ?? '-') }}</td>
                                    <td class="center-col">{{ $damage['quantity'] ?? 1 }}</td>
                                    <td>{{ filled($damage['notes'] ?? null) ? $damage['notes'] : '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="center-col">No current damages are recorded for this vehicle. / لا توجد أضرار حالية مسجلة لهذه السيارة.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="cell">
                    <div class="section-title"><table><tr><td>RENTAL PERIOD</td><td class="ar">مدة التأجير - لتجديد مدة التأجير يجب الحضور شخصياً وإحضار المركبة ودفع كامل مبلغ مدة الإيجار</td></tr></table></div>
                    <div class="center" style="font-size: 7px;">{{ $contractText('rental_period_notice', 'en') }} <span class="ar">{{ $contractText('rental_period_notice', 'ar') }}</span></div>
                    <table class="inline-fields period">
                        <tr><td class="inline-label">Return Time:</td><td class="inline-value">{{ $returnTime }}</td><td class="ar inline-label">وقت العودة :</td><td class="inline-label">To</td><td class="inline-value">{{ $returnDate }}</td><td class="ar inline-label">إلى :</td><td class="inline-label">From</td><td class="inline-value" colspan="3">{{ $pickupDate }}</td><td class="ar inline-label">من :</td></tr>
                        <tr><td class="inline-label" colspan="2">Quantity of gasoline in the car at the time of delivery:</td><td class="inline-value">{{ $lineValue($contract->vehicle_fuel_level) }}</td><td class="ar inline-label" colspan="2">كمية البنزين الموجودة بالسيارة وقت الاستلام</td><td class="inline-label">Return Fuel:</td><td class="inline-value" colspan="2">{{ $lineValue($contract->return_fuel_level) }}</td><td class="ar inline-label">الوقود عند العودة :</td><td colspan="6"></td></tr>
                        <tr><td class="inline-label">Out Mileage:</td><td class="inline-value">{{ $lineValue($contract->vehicle_odometer) }}</td><td class="ar inline-label">الكيلومترات عند المغادرة :</td><td class="inline-label">Out Time:</td><td class="inline-value">{{ $pickupTime }}</td><td class="ar inline-label">وقت المغادرة :</td><td class="inline-label">Out Date</td><td class="inline-value" colspan="2">{{ $pickupDate }}</td><td class="ar inline-label">تاريخ المغادرة :</td></tr>
                        <tr><td class="inline-label">Return Mileage:</td><td class="inline-value">{{ $lineValue($contract->return_odometer) }}</td><td class="ar inline-label">الكيلومترات عند العودة :</td><td class="inline-label">Return Time:</td><td class="inline-value">{{ $actualReturnTime ? $actualReturnTime->format('H:i') : '' }}</td><td class="ar inline-label">وقت العودة :</td><td class="inline-label">Return Date</td><td class="inline-value" colspan="2">{{ $actualReturnTime ? $actualReturnTime->format('Y-m-d') : '' }}</td><td class="ar inline-label">تاريخ العودة :</td></tr>
                       
                    </table>
                </td>
            </tr>
            <tr class="rules">
                <td style="width: 50%;">
                    <ul class="rules-list">
                        <li><table class="dual"><tr><td>{{ $contractText('smoking_notice', 'en') }}</td><td class="ar">{{ $contractText('smoking_notice', 'ar') }}</td></tr></table></li>
                        <li><table class="dual"><tr><td>{{ $contractText('unclean_notice', 'en') }}</td><td class="ar">{{ $contractText('unclean_notice', 'ar') }}</td></tr></table></li>
                        <li><table class="dual"><tr><td>{{ $contractText('delay_notice', 'en') }}</td><td class="ar">{{ $contractText('delay_notice', 'ar') }}</td></tr></table></li>
                        <li><table class="dual"><tr><td>{{ $contractText('period_change_notice', 'en') }}</td><td class="ar">{{ $contractText('period_change_notice', 'ar') }}</td></tr></table></li>
                        <li><table class="dual"><tr><td>{{ $contractText('accident_notice', 'en') }}</td><td class="ar">{{ $contractText('accident_notice', 'ar') }}</td></tr></table></li>
                    </ul>
                </td>
                <td style="width: 50%;">
                    <div class="ack-title">
                        <div class="en">{{ $contractText('acknowledgement_title', 'en') }} <span class="ar">{{ $contractText('acknowledgement_title', 'ar') }}</span></div>
                        <div class="ack-text ar">{{ $contractText('acknowledgement_body', 'ar') }}</div>
                        <div class="ack-text">{{ $contractText('acknowledgement_body', 'en') }}</div>
                    </div>
                    <div class="important">
                        {{ $contractText('important_notice', 'ar') }}<br>
                        {{ $contractText('important_notice', 'en') }}
                    </div>
                    <table class="sign">
                        <tr><td><div class="sign-line">@if($renterSignatureUrl)<img class="sign-image" src="{{ $renterSignatureUrl }}" alt="Renter Signature">@endif</div><span class="ar">توقيع المستأجر</span><br>Renter Signature</td><td><div class="sign-line"></div><span class="ar">اليوم</span><br>Day</td><td><div class="sign-line">{{ $generatedAt->format('Y-m-d') }}</div><span class="ar">التاريخ</span><br>Date</td><td><div class="sign-line">@if($inchargeSignatureUrl)<img class="sign-image" src="{{ $inchargeSignatureUrl }}" alt="Incharge Signature">@endif</div><span class="ar">توقيع المسؤول</span><br>Incharge Signature</td></tr>
                    </table>
                </td>
            </tr>
        </table>
        <div class="footer">
            {{ $contractText('closing_notice', 'en') }}
            <span class="ar">{{ $contractText('closing_notice', 'ar') }}</span>
        </div>
    </div>
</div>
</body>
</html>
