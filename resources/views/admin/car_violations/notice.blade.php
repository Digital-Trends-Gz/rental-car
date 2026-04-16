<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $violation->violation_number ?: ('VIOL-'.$violation->id) }} - Police Notice</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <style>
        @page {
            size: A4;
            margin: 10mm 9mm 9mm 9mm;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #1f2937;
            font-family: 'Cairo', 'DejaVu Sans', Arial, sans-serif;
            direction: rtl;
            unicode-bidi: embed;
            line-height: 1.35;
            font-size: 11px;
        }

        .page { width: 100%; }

        .top-meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            font-size: 10px;
            color: #1f2937;
        }

        .top-meta td {
            width: 50%;
            padding: 0;
            vertical-align: bottom;
        }

        .top-meta .right { text-align: right; }
        .top-meta .left { text-align: left; direction: ltr; }

        .meta-line {
            display: inline-block;
            min-width: 120px;
            border-bottom: 1px dashed #9ca3af;
            height: 13px;
            vertical-align: bottom;
        }

        .header {
            text-align: center;
            margin: 0 0 4px;
        }

        .logo {
            display: block;
            max-width: 170px;
            max-height: 42px;
            margin: 0 auto 1px;
            object-fit: contain;
        }

        .company-en {
            font-size: 16px;
            font-weight: 800;
            color: #27468b;
            margin: 0;
            line-height: 1.1;
        }

        .company-ar {
            font-size: 14px;
            font-weight: 800;
            color: #27468b;
            margin: 1px 0 0;
            line-height: 1.1;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
            direction: ltr;
            table-layout: fixed;
        }

        .header-table td {
            vertical-align: top;
            padding: 0 4px;
        }

        .header-left,
        .header-right {
            width: 26%;
            font-size: 9px;
            line-height: 1.35;
            font-weight: 700;
            color: #1f2937;
        }

        .header-left {
            direction: ltr;
            text-align: left;
            unicode-bidi: plaintext;
        }

        .header-center {
            width: 48%;
            text-align: center;
        }

        .header-right {
            text-align: right;
        }

        .company-name-en {
            direction: ltr;
            unicode-bidi: plaintext;
        }

        .serial-no {
            margin-top: 3px;
            font-size: 11px;
            font-weight: 800;
            color: #27468b;
        }

        .serial-no span {
            color: #d7262d;
            font-size: 13px;
        }

        .subject {
            text-align: center;
            font-size: 11.5px;
            font-weight: 800;
            color: #27468b;
            margin: 3px 0 5px;
            direction: rtl;
            unicode-bidi: plaintext;
        }

        .department-row {
            text-align: right;
            font-size: 11px;
            margin-bottom: 4px;
            color: #1f2937;
        }

        .line {
            display: inline-block;
            min-width: 90px;
            border-bottom: 1px dashed #9ca3af;
            height: 13px;
            vertical-align: bottom;
        }

        .paragraph {
            margin: 4px 0;
            font-size: 10.5px;
            text-align: right;
        }

        .section-title {
            margin: 7px 0 4px;
            font-size: 11.5px;
            font-weight: 800;
            color: #1f2937;
            text-align: right;
        }

        .grid {
            width: 100%;
            border-collapse: collapse;
            margin: 3px 0 6px;
        }

        .grid td {
            border: 1px solid #cfd8e3;
            padding: 4px 6px;
            font-size: 10px;
            vertical-align: middle;
        }

        .grid .label {
            width: 28%;
            background: #f8fafc;
            font-weight: 700;
            color: #1f2937;
        }

        .company-line {
            margin: 2px 0 4px;
            font-size: 10.5px;
            line-height: 1.45;
        }

        .company-line .line {
            min-width: 115px;
            height: 12px;
        }

        .attachments {
            margin: 4px 0 0;
            padding-right: 18px;
            font-size: 10px;
        }

        .attachments li {
            margin: 2px 0;
        }

        .signature {
            width: 100%;
            border-collapse: collapse;
            margin-top: 7px;
        }

        .signature td {
            width: 50%;
            vertical-align: top;
            padding: 0 6px;
            font-size: 10px;
        }

        .sign-line {
            border-bottom: 1px dashed #9ca3af;
            min-height: 12px;
            margin: 2px 0 4px;
        }

        .footer-note {
            margin-top: 6px;
            padding-top: 4px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 9px;
            color: #6b7280;
        }
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

    $companyNameClean = $companyName ?? config('app.name');
    $companyNameArabicClean = $companyNameArabic ?? $companyNameClean;
    $violationNo = $violation->violation_number ?: ('VIOL-'.$violation->id);
    $serialNumber = 'VIO-'.\Illuminate\Support\Carbon::parse($violation->violation_date ?? now())->format('jnY').'-'.$violation->id;
    $generatedDate = $formatDate($generatedAt ?? now());

    $department = trim((string) data_get($pdfHeader, 'registry_label.ar')) !== ''
        ? trim((string) data_get($pdfHeader, 'registry_label.ar'))
        : 'شرطة غزة';

    $officeLine = trim((string) data_get($policeNotice, 'office_line.ar'));
    if ($officeLine === '') {
        $officeLine = $branch?->name ?? $companyNameArabicClean;
    }

    $licenseNumber = trim((string) data_get($pdfHeader, 'cr_number'));
    $address = trim((string) data_get($policeNotice, 'company_address.ar'));
    $phone = trim((string) data_get($policeNotice, 'company_phone.ar'));

    $headerCr = data_get($pdfHeader, 'cr_number') ?: '-';
    $headerPoBox = data_get($pdfHeader, 'po_box') ?: '-';
    $headerPc = data_get($pdfHeader, 'pc') ?: '-';
    $headerCountry = data_get($pdfHeader, 'country.ar') ?: data_get($pdfHeader, 'country.en') ?: 'سلطنة عمان';
    $headerGsm1 = data_get($pdfHeader, 'gsm_1') ?: '-';
    $headerGsm2 = data_get($pdfHeader, 'gsm_2') ?: '-';
    $headerGsm3 = data_get($pdfHeader, 'gsm_3') ?: '-';

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
    $violationStatus = $violation->status instanceof \App\Enums\CarViolationStatus
        ? $violation->status->label()
        : ucfirst((string) $violation->status);

    $subjectAr = trim((string) data_get($policeNotice, 'subject.ar')) ?: 'إشعار بخصوص مخالفة مرورية على مركبة مؤجرة';
    $greetingAr = trim((string) data_get($policeNotice, 'greeting.ar')) ?: 'تحية طيبة وبعد،';
    $introAr = trim((string) data_get($policeNotice, 'intro.ar')) ?: 'نفيدكم علمًا بأن المركبة الموضحة بياناتها أدناه قد تم تحرير مخالفة مرورية عليها، ونود إفادتكم بأن هذه المركبة كانت في تاريخ وقوع المخالفة مؤجرة للمستأجر المذكور في هذا الكتاب بموجب عقد إيجار رسمي، ولم تكن بحيازة المكتب وقت وقوع المخالفة.';
    $closingAr1 = trim((string) data_get($policeNotice, 'closing_1.ar')) ?: 'وعليه، نرجو من حضراتكم أخذ ما ورد أعلاه بعين الاعتبار، واعتماد أن المركبة كانت بعهدة المستأجر المذكور وقت وقوع المخالفة، وأن مكتبنا غير مسؤول عن استخدام المركبة خلال مدة الإيجار المثبتة بالعقد المرفق.';
    $closingAr2 = trim((string) data_get($policeNotice, 'closing_2.ar')) ?: 'كما نؤكد أن المسؤولية القانونية والمالية المترتبة على استخدام المركبة خلال مدة العقد تقع على المستأجر وفقًا لأحكام عقد الإيجار والأنظمة المعمول بها.';

    $attachmentsTitle = trim((string) data_get($policeNotice, 'attachments_title.ar')) ?: 'ثالثًا: المرفقات';
    $attachments = trim((string) data_get($policeNotice, 'attachments.ar'));
    $attachmentsLines = $attachments !== ''
        ? preg_split('/\r?\n/', $attachments)
        : [
            'نسخة عن عقد الإيجار.',
            'نسخة عن هوية / جواز سفر المستأجر.',
            'نسخة عن رخصة القيادة.',
            'نسخة عن المخالفة إن وجدت.',
            'أي مستندات داعمة أخرى.',
        ];

    $signatureNameLabel = trim((string) data_get($policeNotice, 'signature_name_label.ar')) ?: 'اسم المفوض بالتوقيع:';
    $signatureTitleLabel = trim((string) data_get($policeNotice, 'signature_title_label.ar')) ?: 'الصفة:';
    $signatureDateLabel = trim((string) data_get($policeNotice, 'signature_date_label.ar')) ?: 'التاريخ:';
    $footerNote = trim((string) data_get($policeNotice, 'footer_note.ar')) ?: 'هذا نموذج عام قابل للطباعة والتعديل بحسب بيانات الشركة والمركبة والمستأجر.';
@endphp

<div class="page">
    @php
        $headerCompanyNameEn = $companyNameClean;
        $headerCompanyNameAr = $companyNameArabicClean;
        $headerCrNumber = $headerCr;
        $headerPoBox = $headerPoBox;
        $headerPc = $headerPc;
        $headerGsm1 = $headerGsm1;
        $headerGsm2 = $headerGsm2;
        $headerGsm3 = $headerGsm3;
        $headerRegistryLabelAr = $department;
    @endphp

    <table class="header-table" dir="ltr">
        <tr>
            <td class="header-left">
                <div dir="ltr">C.R : {{ $headerCrNumber }}</div>
                <div dir="ltr">P.O Box : {{ $headerPoBox }}</div>
                <div dir="ltr">P.C : {{ $headerPc }}</div>
                <div dir="ltr">GSM : {{ $headerGsm1 }}</div>
                <div dir="ltr">GSM : {{ $headerGsm2 }}</div>
                <div dir="ltr">GSM : {{ $headerGsm3 }}</div>
            </td>
            <td class="header-center">
                @if(!empty($companyLogo))
                    <img src="{{ $companyLogo }}" class="logo" alt="Logo">
                @endif
                <div class="company-name-en" dir="ltr">{{ strtoupper($headerCompanyNameEn) }}</div>
                <div class="company-name-ar">{{ $headerCompanyNameAr }}</div>
            </td>
            <td class="header-right">
                <div dir="rtl">{{ $headerRegistryLabelAr }} :</div>
                <div class="serial-no" dir="rtl">رقم التسلسي : <span>{{ $serialNumber }}</span></div>
                <div dir="rtl">ص.ب : {{ $headerPoBox }}</div>
                <div dir="rtl">الرمز البريدي : {{ $headerPc }}</div>
                <div dir="rtl">نقال : {{ $headerGsm1 }}</div>
                <div dir="rtl">نقال : {{ $headerGsm2 }}</div>
                <div dir="rtl">نقال : {{ $headerGsm3 }}</div>
            </td>
        </tr>
    </table>

    <div class="subject">عقد / إشعار بخصوص مخالفة مرورية على مركبة مؤجرة</div>

    <div class="department-row" dir="rtl">قسم: <span class="line">{{ $department }}</span></div>

    <p class="paragraph">{{ $greetingAr }}</p>

    <p class="paragraph company-line">
        نحن شركة / مكتب: <span class="line">{{ $officeLine }}</span>
        &nbsp;&nbsp;رقم الترخيص: <span class="line">{{ $licenseNumber }}</span>
        &nbsp;&nbsp;العنوان: <span class="line">{{ $address }}</span>
        &nbsp;&nbsp;رقم الهاتف: <span class="line">{{ $phone }}</span>
    </p>

    <p class="paragraph">{{ $introAr }}</p>

    <div class="section-title">أولاً: بيانات المركبة</div>
    <table class="grid">
        <tr>
            <td class="label">رقم المركبة</td>
            <td>{{ $plateNumber }}</td>
        </tr>
        <tr>
            <td class="label">النوع / الموديل</td>
            <td>{{ $carLabel }}</td>
        </tr>
        <tr>
            <td class="label">اللون</td>
            <td>{{ $car?->color ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">تاريخ المخالفة</td>
            <td>{{ $formatDate($violation->violation_date) }}</td>
        </tr>
        <tr>
            <td class="label">رقم المخالفة</td>
            <td>{{ $violationNo }}</td>
        </tr>
    </table>

    <div class="section-title">ثانيًا: بيانات المستأجر</div>
    <table class="grid">
        <tr>
            <td class="label">الاسم الكامل</td>
            <td>{{ $renterName }}</td>
        </tr>
        <tr>
            <td class="label">رقم الهوية / جواز السفر</td>
            <td>{{ $renterId }}</td>
        </tr>
        <tr>
            <td class="label">رقم الهاتف</td>
            <td>{{ $renterPhone }}</td>
        </tr>
        <tr>
            <td class="label">رقم رخصة القيادة</td>
            <td>{{ $contract?->driver_license_number ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">تاريخ بداية الإيجار</td>
            <td>{{ optional($contract?->start_date)?->toDateString() ?: optional($reservation?->start_date)?->toDateString() ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">تاريخ نهاية الإيجار</td>
            <td>{{ optional($contract?->end_date)?->toDateString() ?: optional($reservation?->end_date)?->toDateString() ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">فترة الإيجار</td>
            <td>{{ $rentalPeriod }}</td>
        </tr>
    </table>

    <p class="paragraph">{{ $closingAr1 }}</p>
    <p class="paragraph">{{ $closingAr2 }}</p>

    <div class="section-title">{{ $attachmentsTitle }}</div>
    <ul class="attachments">
        @foreach($attachmentsLines as $attachmentLine)
            @php($attachmentLine = trim((string) $attachmentLine))
            @if($attachmentLine !== '')
                <li>{{ $attachmentLine }}</li>
            @endif
        @endforeach
    </ul>

    <table class="signature">
        <tr>
            <td>
                <div><strong>{{ $signatureNameLabel }}</strong></div>
                <div class="sign-line"></div>
                <div><strong>{{ $signatureTitleLabel }}</strong></div>
                <div class="sign-line"></div>
                <div><strong>{{ $signatureDateLabel }}</strong> {{ $generatedDate }}</div>
            </td>
            <td>
                <div><strong>اسم الشركة / المكتب:</strong> {{ $officeLine }}</div>
                <div class="sign-line"></div>
                <div><strong>الختم:</strong></div>
                <div class="sign-line"></div>
                <div><strong>{{ $department }}</strong></div>
            </td>
        </tr>
    </table>

    <div class="footer-note">{{ $footerNote }}</div>
</div>
</body>
</html>
