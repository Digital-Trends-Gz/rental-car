<!DOCTYPE html>
<html lang="{{ $locale }}" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __('contracts.pdf.document_title', ['number' => $contract->contract_number]) }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; color: #0f1c42; font-family: 'Cairo', Arial, sans-serif; font-size: 11px; line-height: 1.25; background: #fff; font-weight: 600; }
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
        
        /* Header section */
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
        
        /* Main Grid */
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
        
        /* Inline split elements (for horizontal rows) */
        .inline-field { display: inline-flex; align-items: flex-end; }
        .inline-field .lbl { font-size: 9px; color: #1a326b; }
        .inline-field .lbl-ar { font-size: 10px; color: #1a326b; }
        .inline-field .val { border-bottom: 1px dotted #1a326b; flex-grow: 1; padding: 0 4px; min-width: 60px; text-align: center; }

        .rates-table { width: 100%; border: 1px solid #1a326b; margin-top: 4px; text-align: center; }
        .rates-table th { background: #f0f4f8; font-size: 8px; border: 1px solid #1a326b; padding: 2px; }
        .rates-table td { font-size: 10px; border: 1px solid #1a326b; padding: 2px; }

        .rules-list { margin: 0; padding-left: 12px; font-size: 8.5px; line-height: 1.4; color: #1a326b; }
        .rules-list li { margin-bottom: 2px; }
        .fine-text { display: flex; justify-content: space-between; }
        .fine-text .ar { font-size: 9.5px; text-align: right; direction: rtl; unicode-bidi: plaintext; }

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
        
        .signatures { display: flex; justify-content: space-between; margin-top: 8px; padding: 0 6px; }
        .sign-block { text-align: center; font-size: 9px; color: #1a326b; width: 25%; }
        .sign-block .ar { font-size: 10px; display: block; direction: rtl; unicode-bidi: plaintext; }
        .sign-line { border-bottom: 1px dotted #1a326b; min-height: 14px; margin-bottom: 2px; }

        .footer-end { text-align: center; font-size: 9px; font-weight: 800; color: #1a326b; padding-top: 6px; }
        
        .damage-container { text-align: center; padding: 4px 0; }
        .damage-img { width: 100%; max-width: 280px; max-height: 120px; object-fit: contain; }
        .fuel-box { border: 1px solid #1a326b; width: 12px; height: 12px; display: inline-block; vertical-align: middle; text-align: center; font-size: 8px; line-height: 10px; font-weight: 800; }
        .status-grid {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-size: 8px;
            margin-top: 4px;
        }
        .status-block {
            width: 48%;
            text-align: right;
            direction: rtl;
            unicode-bidi: plaintext;
        }
        .status-option {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 2px;
            direction: rtl;
            unicode-bidi: plaintext;
            white-space: nowrap;
        }
        .status-option .fuel-box {
            width: 9px;
            height: 9px;
            flex: 0 0 9px;
            margin: 0;
        }
    </style>
</head>
<body>
@php
    $diagramTitle = __('contracts.pdf.sections.vehicle_damage_diagram');
    $currentDamageTitle = __('contracts.pdf.sections.current_car_damages');
    $diagramEmpty = __('contracts.pdf.empty.no_current_damages');
    
    $drivers = collect([]);
    if ($contract->primaryDriver) { $drivers->push($contract->primaryDriver); }
    foreach ($contract->additionalDrivers as $driver) { $drivers->push($driver); }
    $primaryDriver = $contract->primaryDriver;
    $reservation = $contract->reservation;
    $reservationCar = $reservation?->car;
    $reservationUser = $reservation?->user;
    
    $siteSettings = $contract->tenant?->siteSetting ? \App\Models\TenantSiteSetting::forTenant($contract->tenant) : [];
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
    $headerRegistryLabelAr = data_get($pdfHeader, 'registry_label.ar') ?: 'رقم';
    
    $currency = $contract->currency ?: config('app.currency', 'OMR');
    $dailyRate = $contract->price_per_day ?? $reservation?->daily_rate ?? $reservationCar?->price_per_day;
    $weeklyRate = $contract->price_per_week ?? $reservationCar?->price_per_week;
    $monthlyRate = $contract->price_per_month ?? $reservationCar?->price_per_month;
    $allowedKmDay = $contract->allowed_km_per_day ?? $reservationCar?->allowed_km_per_day;
    $allowedKmWeek = $contract->allowed_km_per_week ?? $reservationCar?->allowed_km_per_week;
    $allowedKmMonth = $contract->allowed_km_per_month ?? $reservationCar?->allowed_km_per_month;
    
    $subtotal = $reservation?->subtotal ?? $contract->total_amount;
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
    $returnMileage = $contract->return_odometer;
    $returnFuelLevel = $contract->return_fuel_level;
    $vehicleConditionBefore = $contract->vehicle_condition_before;
    $vehicleConditionAfter = $contract->vehicle_condition_after;
    $actualReturnTime = $contract->actual_return_time;
    
    $vehType = trim(($reservationCar?->make ?? '').' '.($reservationCar?->model ?? ''));
@endphp
<div class="page">
    <div class="sheet">
        <!-- HEADER -->
        <table class="header-table">
            <tr>
                <td class="header-left">
                    <div>C.R : {{ $headerCrNumber }}</div>
                    <div>P.O Box : {{ $headerPoBox }}</div>
                    <div>P.C : {{ $headerPc }}</div>
                    <div>{{ $headerCountryEn }}</div>
                    <div>GSM : {{ $headerGsm1 }}</div>
                    <div>GSM : {{ $headerGsm2 }}</div>
                    <div>GSM : {{ $headerGsm3 }}</div>
                    <div class="serial-no">{{ $headerRegistryLabelEn }} <span>{{ $contract->contract_number }}</span></div>
                </td>
                <td class="header-center">
                    @if(!empty($companyLogo))
                        <img src="{{ $companyLogo }}" style="max-height: 48px; object-fit: contain; margin-bottom:2px;" alt="Logo" />
                    @endif
                    <div class="company-name-en">{{ strtoupper($headerCompanyNameEn) }}</div>
                    <div class="company-name-ar ar center-name">{{ $headerCompanyNameAr }}</div>
                </td>
                <td class="header-right ar" style="font-size: 9px; line-height: 1.4; font-weight: 700;">
                    <div>{{ $headerRegistryLabelAr }} : </div>
                    <div>ص.ب : {{ $headerPoBox }}</div>
                    <div>الرمز البريدي : {{ $headerPc }}</div>
                    <div>{{ $headerCountryAr }}</div>
                    <div>نقال : {{ $headerGsm1 }}</div>
                    <div>نقال : {{ $headerGsm2 }}</div>
                    <div>نقال : {{ $headerGsm3 }}</div>
                </td>
            </tr>
        </table>
        
        <div class="contract-title-row">
            <div class="contract-title-en">CAR RENTAL CONTRACT</div>
            <div class="contract-title-ar ar">عقد إيجار سيارة</div>
        </div>

        <!-- MAIN GRID SECTION -->
        <table class="grid-table">
            <!-- ROW 1: PASSPORT & RENTER DETAILS -->
            <tr>
                <!-- LEFT HALF: Passport / ID & Driving License -->
                <td style="width: 50%; border-right: 2px solid #1a326b;">
                    <!-- Passport Section -->
                    <div class="cell-title">
                        <span>PASSPORT/ID DETAILS</span>
                        <span class="ar">بيانات جواز السفر والبطاقة</span>
                    </div>
                    <div class="field-row">
                        <div class="field-label">Civil No</div>
                        <div class="field-val-dotted" style="width: 48%">{{ $lineValue($primaryDriver?->identity_number ?? $primaryDriver?->residency_number ?? $contract->renter_id_number) }}</div>
                        <div class="field-label-ar ar">الرقم المدني :</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label">Exp. Date</div>
                        <div class="field-val-dotted" style="width: 48%">{{ optional($primaryDriver?->identity_expiry_date)->format('Y-m-d') ?? '' }}</div>
                        <div class="field-label-ar ar">تاريخ الانتهاء :</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label">Passport No</div>
                        <div class="field-val-dotted" style="width: 48%">{{ $lineValue($passportNumber) }}</div>
                        <div class="field-label-ar ar">رقم الجواز :</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label">Exp. Date</div>
                        <div class="field-val-dotted" style="width: 48%">{{ $lineValue($passportExpiry) }}</div>
                        <div class="field-label-ar ar">تاريخ الانتهاء :</div>
                    </div>
                    <div style="display: flex; margin-bottom: 8px; align-items: flex-end;">
                        <span style="font-size: 9px; font-weight: 700;">Sponsor</span>
                        <span class="field-val-dotted" style="width: 38%; margin-left: 2px;">{{ $lineValue($primaryDriver?->sponsor) }}</span>
                        <span class="ar" style="font-size: 9px; font-weight: 700; margin-left: 2px;">الكفيل</span>
                        <span style="font-size: 9px; font-weight: 700; margin-left: 8px;">Exp. Date</span>
                        <span class="field-val-dotted" style="width: 20%; margin-left: 2px;"></span>
                        <span class="ar" style="font-size: 9px; font-weight: 700; margin-left: 2px;">تاريخ الانتهاء</span>
                    </div>
                    
                    <!-- Driving License Section -->
                    <div class="cell-title" style="margin-top: 6px;">
                        <span>DRIVING LICENSE DETAILS</span>
                        <span class="ar">بيانات رخصة القيادة</span>
                    </div>
                    <div class="field-row">
                        <div class="field-label">Issue Date</div>
                        <div class="field-val-dotted" style="width: 48%">{{ $lineValue($licenseIssueDate) }}</div>
                        <div class="field-label-ar ar">تاريخ الإصدار :</div>
                    </div>
                    <div class="field-row" style="margin-bottom: 6px;">
                        <div class="field-label">Exp. Date</div>
                        <div class="field-val-dotted" style="width: 48%">{{ optional($primaryDriver?->license_expiry_date)->format('Y-m-d') ?? '' }}</div>
                        <div class="field-label-ar ar">تاريخ الانتهاء :</div>
                    </div>
                    
                    <div class="field-row">
                        <div class="field-label">License No</div>
                        <div class="field-val-dotted" style="width: 48%">{{ $lineValue($primaryDriver?->license_number) }}</div>
                        <div class="field-label-ar ar">رقم الرخصة :</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label">Issue Place</div>
                        <div class="field-val-dotted" style="width: 48%">{{ $lineValue($primaryDriver?->place_of_issue) }}</div>
                        <div class="field-label-ar ar">مكان الإصدار :</div>
                    </div>
                </td>
                
                <!-- RIGHT HALF: Renter Details -->
                <td style="width: 50%;">
                    <div class="cell-title">
                        <span>RENTER DETAILS</span>
                        <span class="ar">تفاصيل المستأجر</span>
                    </div>
                    <div class="field-row">
                        <div class="field-label" style="width: 20%;">Renter Name</div>
                        <div class="field-val-dotted" style="width: 60%;">{{ $lineValue($contract->renter_name ?: $primaryDriver?->full_name ?: $reservationUser?->name) }}</div>
                        <div class="field-label-ar ar" style="width: 20%;">اسم المستأجر :</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label" style="width: 20%;">Nationality</div>
                        <div class="field-val-dotted" style="width: 60%;">{{ $lineValue($primaryDriver?->nationality) }}</div>
                        <div class="field-label-ar ar" style="width: 20%;">الجنسية :</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label" style="width: 20%;">Tel</div>
                        <div class="field-val-dotted" style="width: 60%;">{{ $lineValue($contract->renter_phone ?: $primaryDriver?->phone) }}</div>
                        <div class="field-label-ar ar" style="width: 20%;">هاتف :</div>
                    </div>
                    <div class="field-row">
                        <div class="field-label" style="width: 20%;">Address</div>
                        <div class="field-val-dotted" style="width: 60%;">{{ $lineValue($reservation?->pickup_location ?? $contactAddress) }}</div>
                        <div class="field-label-ar ar" style="width: 20%;">العنوان :</div>
                    </div>
                    <div style="display: flex; margin-top: 8px; align-items: flex-end;">
                        <span style="font-size: 9px; font-weight: 700;">Visa No</span>
                        <span class="field-val-dotted" style="width: 35%; margin-left: 2px;">{{ $lineValue($visaNumber) }}</span>
                        <span class="ar" style="font-size: 9px; font-weight: 700; margin-left: 6px;">رقم التأشيرة :</span>
                        <span style="font-size: 9px; font-weight: 700; margin-left: 8px;">Exp. Date</span>
                        <span class="field-val-dotted" style="width: 20%; margin-left: 2px;">{{ $lineValue($visaExpiry) }}</span>
                        <span class="ar" style="font-size: 9px; font-weight: 700; margin-left: 2px;">تاريخ الانتهاء</span>
                    </div>
                </td>
            </tr>

            <!-- ROW 2: CAR DETAILS -->
            <tr>
                <td colspan="2" style="border-top: 2px solid #1a326b;">
                    <div class="cell-title" style="float: left; width: 100px; margin-bottom: 0;">
                        <span>CAR DETAILS</span>
                    </div>
                    <div class="cell-title ar" style="float: right; width: 100px; text-align: right; margin-bottom: 0px;">
                        بيانات المركبة
                    </div>
                    <div style="clear: both; display: flex; align-items: flex-end; justify-content: space-between; padding-top: 6px;">
                        <div class="inline-field" style="width: 35%;">
                            <span class="lbl" style="font-weight: 700;">Vehicle Type</span>
                            <span class="val">{{ $lineValue($vehType) }}</span>
                            <span class="lbl-ar ar" style="font-weight: 700;">نوع السيارة :</span>
                        </div>
                        <div class="inline-field" style="width: 30%;">
                            <span class="lbl" style="font-weight: 700;">Plate No</span>
                            <span class="val">{{ $lineValue($reservationCar?->license_plate ?? $contract->plate_number) }}</span>
                            <span class="lbl-ar ar" style="font-weight: 700;">رقم اللوحة :</span>
                        </div>
                        <div class="inline-field" style="width: 30%;">
                            <span class="lbl" style="font-weight: 700;">Vehicle Color</span>
                            <span class="val">{{ $lineValue($reservationCar?->color ?? $contract->car_details) }}</span>
                            <span class="lbl-ar ar" style="font-weight: 700;">لون السيارة :</span>
                        </div>
                    </div>
                </td>
            </tr>

            <!-- ROW 3: Diagram and Rates -->
            <tr>
                <!-- Diagram -->
                <td style="width: 50%; border-right: 2px solid #1a326b; padding: 4px;">
                    <div style="display: flex; justify-content: space-between; font-size: 9px; font-weight: 700; color: #1a326b; margin-bottom: 2px;">
                        <span>العودة</span>
                        <span>المغادرة</span>
                        <span>الفحص النظري</span>
                    </div>
                    <div class="damage-container">
                        @if(!empty($damageDiagram['data_uri']))
                            <img src="{{ $damageDiagram['data_uri'] }}" class="damage-img" alt="Damage" />
                        @else
                            <div style="height: 100px; display: flex; align-items:center; justify-content:center; color: #aaa;">{{ $diagramEmpty }}</div>
                        @endif
                    </div>
                    <div class="status-grid">
                        <div class="status-block">
                            <div class="ar">حالة المركبة بعد</div>
                            <div class="status-option"><span class="fuel-box">{{ $vehicleConditionAfter === 'clean' ? '✓' : '' }}</span><span>نظيف / Clean</span></div>
                            <div class="status-option"><span class="fuel-box">{{ $vehicleConditionAfter === 'not_clean' ? '✓' : '' }}</span><span>غير نظيف / Not Clean</span></div>
                        </div>
                        <div class="status-block">
                            <div class="ar">حالة المركبة قبل</div>
                            <div class="status-option"><span class="fuel-box">{{ $vehicleConditionBefore === 'clean' ? '✓' : '' }}</span><span>نظيف / Clean</span></div>
                            <div class="status-option"><span class="fuel-box">{{ $vehicleConditionBefore === 'not_clean' ? '✓' : '' }}</span><span>غير نظيف / Not Clean</span></div>
                        </div>
                    </div>
                </td>
                
                <!-- Rates & Allowed KM -->
                <td style="width: 50%;">
                    <div class="cell-title">
                        <span>Rental Rate & Allowed KM</span>
                        <span class="ar">سعر الإيجار والكيلومترات المجانية</span>
                    </div>
                    <table class="rates-table">
                        <tr>
                            <th>Per Month<br><span class="ar">السعر الشهري</span></th>
                            <th>Per Week<br><span class="ar">السعر الأسبوعي</span></th>
                            <th>Per Day<br><span class="ar">السعر اليومي</span></th>
                        </tr>
                        <tr>
                            <td style="height: 20px;">
                                {{ $monthlyRate !== null ? number_format((float) $monthlyRate, 2) : '' }} 
                                <br><span class="ar">ر.ع</span> - OMR
                            </td>
                            <td>
                                {{ $weeklyRate !== null ? number_format((float) $weeklyRate, 2) : '' }} 
                                <br><span class="ar">ر.ع</span> - OMR
                            </td>
                            <td>
                                {{ $dailyRate !== null ? number_format((float) $dailyRate, 2) : '' }} 
                                <br><span class="ar">ر.ع</span> - OMR
                            </td>
                        </tr>
                        <tr>
                            <td style="height: 20px;">{{ $lineValue($allowedKmMonth) }} <br><span class="ar">كم</span> - KM</td>
                            <td>{{ $lineValue($allowedKmWeek) }} <br><span class="ar">كم</span> - KM</td>
                            <td>{{ $lineValue($allowedKmDay) }} <br><span class="ar">كم</span> - KM</td>
                        </tr>
                    </table>
                    
                    <div style="background: #1a326b; color: #fff; padding: 4px; border-radius: 2px; text-align: center; margin-top: 8px;">
                        <div class="ar" style="font-size: 10px;">الزيادة في عدد الكيلومترات المتفق عليها تحسب بمبلغ 50 بيسة لكل كيلومتر إضافي للصالون و100 بيسة لكل كيلومتر إضافي للدفع الرباعي</div>
                        <div style="font-size: 8px; font-weight: 500; margin-top: 2px;">Excess mileage will charge 50 Bz per kilometer for salon and 100 Bz per Kilometer for 4x4.</div>
                    </div>
                </td>
            </tr>

            <!-- ROW 4: RENTAL PERIOD -->
            <tr>
                <td colspan="2" style="border-top: 2px solid #1a326b;">
                    <div class="cell-title" style="margin-bottom: 2px;">
                        <span>RENTAL PERIOD</span>
                        <span class="ar" style="font-size: 9px; font-weight: 500; color: #1a326b;">مدة التأجير - لتجديد مدة التأجير يجب الحضور شخصيًا وإحضار المركبة ودفع كامل مبلغ مدة الإيجار</span>
                    </div>
                    <div style="text-align: center; font-size: 8px; margin-bottom: 6px;">
                        Non-renewal of the contract term there is a fine of 5 OMR per day
                        <span class="ar" style="margin-left: 4px;">عدم تجديد مدة العقد هناك غرامة بقيمة 5 ريال لكل يوم</span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <div class="inline-field" style="width: 30%;">
                            <span class="lbl">Return Time</span>
                            <span class="val">{{ $returnTime }}</span>
                            <span class="lbl-ar ar">وقت العودة :</span>
                        </div>
                        <div class="inline-field" style="width: 30%;">
                            <span class="lbl">To</span>
                            <span class="val">{{ $returnDate }}</span>
                            <span class="lbl-ar ar">إلى :</span>
                        </div>
                        <div class="inline-field" style="width: 30%;">
                            <span class="lbl">From</span>
                            <span class="val">{{ $pickupDate }}</span>
                            <span class="lbl-ar ar">من :</span>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <div class="inline-field" style="width: 100%;">
                            <span class="lbl">Quantity of gasoline in the car at the time of delivery</span>
                            <span class="val">{{ $lineValue($contract->vehicle_fuel_level) }}</span>
                            <span class="lbl-ar ar">كمية البنزين الموجودة بالسيارة وقت الاستلام</span>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <div class="inline-field" style="width: 30%;">
                            <span class="lbl">Out Mileage</span>
                            <span class="val">{{ $lineValue($contract->vehicle_odometer) }}</span>
                            <span class="lbl-ar ar">الكيلومترات عند المغادرة :</span>
                        </div>
                        <div class="inline-field" style="width: 30%;">
                            <span class="lbl">Out Time</span>
                            <span class="val">{{ $pickupTime }}</span>
                            <span class="lbl-ar ar">وقت المغادرة :</span>
                        </div>
                        <div class="inline-field" style="width: 30%;">
                            <span class="lbl">Out Date</span>
                            <span class="val">{{ $pickupDate }}</span>
                            <span class="lbl-ar ar">تاريخ المغادرة :</span>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <div class="inline-field" style="width: 30%;">
                            <span class="lbl">Return Mileage</span>
                            <span class="val">{{ $lineValue($returnMileage) }}</span>
                            <span class="lbl-ar ar">الكيلومترات عند العودة :</span>
                        </div>
                        <div class="inline-field" style="width: 30%;">
                            <span class="lbl">Return Time</span>
                            <span class="val">{{ $actualReturnTime ? $actualReturnTime->format('H:i') : '' }}</span>
                            <span class="lbl-ar ar">وقت العودة :</span>
                        </div>
                        <div class="inline-field" style="width: 30%;">
                            <span class="lbl">Return Date</span>
                            <span class="val">{{ $actualReturnTime ? $actualReturnTime->format('Y-m-d') : '' }}</span>
                            <span class="lbl-ar ar">تاريخ العودة :</span>
                        </div>
                    </div>
                    <div style="display: flex; margin-bottom: 4px;">
                        <div class="inline-field" style="width: 40%;">
                            <span class="lbl">Return Fuel</span>
                            <span class="val">{{ $lineValue($returnFuelLevel) }}</span>
                            <span class="lbl-ar ar">الوقود عند العودة :</span>
                        </div>
                    </div>
                </td>
            </tr>

            <!-- ROW 5: RULES & ACKNOWLEDGEMENT -->
            <tr>
                <!-- LEFT: Rules -->
                <td style="width: 50%; border-top: 2px solid #1a326b; border-right: 2px solid #1a326b;">
                    <ul class="rules-list">
                        <li>
                            <div class="fine-text"><span>* Smoking inside the Vehicle Fine 20 OMR.</span> <span class="ar">غرامة التدخين داخل المركبة 20 ريال عماني *</span></div>
                        </li>
                        <li>
                            <div class="fine-text"><span>* In case of the Vehicle returned unclean, 2 OMR will be charged.</span> <span class="ar">في حالة رجوع المركبة غير نظيفة يدفع 2 ريال عماني *</span></div>
                        </li>
                        <li>
                            <div class="fine-text"><span>* Delay for Vehicle return will charge 1 OMR for salon, 2 OMR for 4x4 per hours and delays more than 4 hours full day rent will charge.</span> <span class="ar">التأخير عن موعد تسليم المركبة يحسب لكل ساعة: 1 ريال عماني للصالون و2 ريال عماني للدفع الرباعي، والتأخير لأكثر من 4 ساعات يحسب إيجار يوم كامل *</span></div>
                        </li>
                        <li>
                            <div class="fine-text"><span>* If the monthly or weekly rent is agreed and the vehicle is returned before the end of the period, The contract will change to daily.</span> <span class="ar">إذا اتفق على الإيجار الشهري أو الأسبوعي وأُعيدت المركبة قبل نهاية المدة فإن العقد يتحول إلى إيجار يومي *</span></div>
                        </li>
                        <li>
                            <div class="fine-text"><span>* In the event of any car accident, the renter must pay 600 riyals immediately, without any conditions.</span> <span class="ar">في حال حدوث أي حادث للمركبة يدفع المستأجر 600 ريال فورًا دون أي شروط *</span></div>
                        </li>
                    </ul>
                </td>
                
                <!-- RIGHT: Acknowledgement & Signatures -->
                <td style="width: 50%; border-top: 2px solid #1a326b; padding: 0;">
                    <div class="ack-box">
                        <div class="ack-box-title">
                            <span>Acknowledgement and Undertaking</span>
                            <span class="ar">إقرار وتعهد</span>
                        </div>
                        <div class="ack-divider"></div>
                        <div class="ack-text-ar ar">أقر أنني قرأت البنود الواردة أعلاه وخلف العقد وقبلت البنود بكامل إرادتي والتزمت بتنفيذها وقد استلمت المركبة في حالة جيدة وعلى ذلك أوقع</div>
                        <div class="ack-text-en">I have read the terms and conditions mentioned above and behind the contract. And I have accepted the conditions in full will and I'm committed to implement them. I have received the vehicle in good condition.</div>
                    </div>
                    
                    <!-- Added a small warning block from the image before signatures -->
                    <div style="background: #1a326b; color: #fff; padding: 2px 4px; border-radius: 2px; text-align: center; margin: 4px 6px; font-size: 8px;">
                         تنبيه هام IMPORTANT NOTICE: ممنوع التدخين داخل السيارة
                         <br> الغرامة 20 ريال عماني في حال التدخين داخل السيارة. A fine of 20 riyals is charged for each vehicle.
                    </div>
                    
                    <div class="signatures">
                        <div class="sign-block" style="width: 25%;">
                            <div class="sign-line"></div>
                            <span class="ar">توقيع المستأجر</span><br>Renter Signature
                        </div>
                        <div class="sign-block" style="width: 20%;">
                            <div class="sign-line"></div>
                            <span class="ar">اليوم</span><br>Day
                        </div>
                        <div class="sign-block" style="width: 25%;">
                            <div class="sign-line">{{ $generatedAt->format('Y-m-d') }}</div>
                            <span class="ar">التاريخ</span><br>Date
                        </div>
                        <div class="sign-block" style="width: 25%;">
                            <div class="sign-line"></div>
                            <span class="ar">توقيع المسؤول</span><br>Incharge Signature
                        </div>
                    </div>
                </td>
            </tr>
        </table>
        
        <div class="footer-end">
            <span style="margin-right: 12px;">THE CONTRACT IS ONLY CLOSED BY PAYING ALL THE AMOUNTS DUE AND HANDING OVER THE VEHICLE AND THE KEY</span>
            <span class="ar" style="font-size: 11px;">العقد لا يغلق إلا بدفع جميع المبالغ المستحقة وتسليم المركبة والمفتاح</span>
        </div>
        
    </div>
</div>
</body>
</html>
