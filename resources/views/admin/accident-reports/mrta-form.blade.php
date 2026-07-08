@php
    $types = $payload['accident_types'] ?? [];
    $causes = $payload['accident_causes'] ?? [];
    $first = $payload['first_party'] ?? [];
    $second = $payload['second_party'] ?? [];
    $witnesses = array_pad($payload['witnesses'] ?? [], 2, []);
    $damages = $payload['vehicle_damages'] ?? [];
    $insurance = $payload['insurance'] ?? [];
    $signatures = $payload['signatures'] ?? [];
    $pdfSettings = \App\Core\MrtaPdfSettings::normalize($mrtaPdfSettings ?? null);
    $primaryColor = $pdfSettings['primary_color'];
    $fileDataUri = function (string $path, ?string $mime = null): ?string {
        if (! is_file($path)) {
            return null;
        }

        $mime ??= pathinfo($path, PATHINFO_EXTENSION) === 'svg'
            ? 'image/svg+xml'
            : (mime_content_type($path) ?: 'application/octet-stream');

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    };
    $pdfImageSource = function (?string $url) use ($fileDataUri): ?string {
        $url = trim((string) ($url ?? ''));
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, 'data:')) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $source = $fileDataUri(storage_path('app/public/'.substr($path, strlen('storage/'))));
            if ($source) {
                return $source;
            }
        }

        $source = $fileDataUri(public_path($path));
        if ($source) {
            return $source;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url('/'.ltrim($url, '/'));
    };
    $omanLogoUrl = $pdfImageSource($pdfSettings['oman_logo_url']);
    $ropLogoUrl = $pdfImageSource($pdfSettings['rop_logo_url']);
    $livaLogoUrl = $pdfImageSource($pdfSettings['liva_logo_url']);

    $checked = fn (array $values, string $value): string => in_array($value, $values, true) ? 'is-checked' : '';
    $value = fn (array $data, string $key): string => (string) data_get($data, $key, '');
    $carView = fn (string $name): ?string => $fileDataUri(public_path("images/car-damage-views/{$name}.svg"), 'image/svg+xml');
    $cairoPath = collect([
        ...glob(storage_path('app/dompdf-fonts/cairo_normal_*.ttf')),
        ...glob(storage_path('app/dompdf-fonts/cairopdf_normal_*.ttf')),
        ...glob(storage_path('fonts/cairo_normal_*.ttf')),
        ...glob(storage_path('fonts/cairo_600_*.ttf')),
    ])->first();
    $cairo = $cairoPath ? $fileDataUri($cairoPath, 'font/truetype') : null;

    $partyRows = [
        ['vehicle_no', 'Vehicle No.', 'رقم المركبة'],
        ['driver_name', "Driver's Name", 'سائق المركبة'],
        ['address_tel', 'Address / Tel. No.', 'العنوان / الهاتف'],
        ['driving_license_no_category', 'Driving License No. / Category:', 'رقم الرخصة / الفئة'],
        ['sex_nationality', 'Sex / Nationality:', 'الجنسية / الجنس'],
        ['insurance_company', 'Insurance Company:', 'شركة التأمين'],
        ['insurance_type', 'Type of Insurance:', 'نوع الوثيقة'],
        ['insurance_policy_no', 'Insurance Policy No.', 'رقم الوثيقة'],
    ];

    $causePairs = [
        ['over_speed', 'Over-speed', 'السرعة'],
        ['negligence', 'Negligence', 'الإهمال'],
        ['fatigue', 'Fatigue', 'الإرهاق'],
        ['overtaking', 'Overtaking', 'التجاوز'],
        ['weather_conditions', 'Weather Conditions', 'الطقس'],
        ['sudden_halt', 'Sudden Hault', 'الوقوف المفاجئ'],
        ['no_safety_distance', 'No safety distance', 'عدم ترك مسافة الأمان'],
        ['wrong_action', 'Wrong action', 'سوء التصرف'],
        ['vehicle_defects', 'Vehicle defects', 'عيوب المركبة'],
        ['road_defects', 'Road defects', 'عيوب الطريق'],
        ['using_gsm', 'Using GSM', 'الهاتف النقال'],
    ];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Minor Road Traffic Accident Form</title>
    <style>
        @if($cairo)
        @font-face {
            font-family: "CairoPdf";
            src: url("{{ $cairo }}") format("truetype");
            font-style: normal;
            font-weight: 400 900;
        }
        @endif

        @page { margin: 2mm 7mm 5mm; size: A4 portrait; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #111;
            font-family: "CairoPdf", Cairo, Tahoma, "DejaVu Sans", Arial, sans-serif;
            font-size: 10.4px;
            line-height: 1.1;
        }
        .ar {
            direction: rtl;
            unicode-bidi: isolate;
            font-family: "CairoPdf", Cairo, Tahoma, "DejaVu Sans", Arial, sans-serif;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .strong { font-weight: 800; }
        .page { width: 100%; }
        .top-rule { border-top: 2px solid #222; margin: -2mm -7mm 5px; }
        table { width: 100%; border-collapse: collapse; }

        .header td { height: 86px; padding: 0; vertical-align: middle; }
        .logo-cell { width: 27%; }
        .title-cell {
            width: 46%;
            background: {{ $primaryColor }};
            text-align: center;
            color: #111;
            font-weight: 900;
            padding: 14px 8px 10px;
        }
        .title-ar { display: block; font-size: 21px; line-height: 1.06; margin-bottom: 5px; }
        .title-en { display: block; font-family: Arial, sans-serif; font-size: 18px; line-height: 1.08; }

        .oman-mark { position: relative; width: 116px; height: 66px; margin-left: 18px; }
        .oman-mark .red,
        .oman-mark .green {
            position: absolute;
            display: block;
            border-radius: 50%;
            border: 9px solid transparent;
            transform: rotate(-17deg);
        }
        .oman-mark .red {
            left: 0;
            top: 13px;
            width: 94px;
            height: 40px;
            border-left-color: #e21d2d;
            border-top-color: #e21d2d;
        }
        .oman-mark .green {
            left: 45px;
            top: 8px;
            width: 68px;
            height: 33px;
            border-right-color: #009a44;
            border-top-color: #009a44;
        }
        .oman-mark .swords {
            position: absolute;
            left: 54px;
            top: 30px;
            font-family: "Times New Roman", serif;
            font-size: 24px;
            line-height: 1;
            transform: rotate(-12deg);
        }
        .rop-mark {
            width: 68px;
            height: 68px;
            margin-left: auto;
            margin-right: 24px;
            border: 2px solid #395caa;
            border-radius: 50%;
            color: #395caa;
            text-align: center;
            font-family: Arial, sans-serif;
            font-weight: 800;
            padding-top: 9px;
        }
        .rop-mark .swords { display: block; font-size: 29px; line-height: 25px; }
        .rop-mark .text { display: block; font-size: 11px; line-height: 12px; letter-spacing: .5px; }

        .line-table { margin-top: 12px; border-collapse: separate;
    border-spacing: 0 10px; /* 10px مسافة عمودية بين الصفوف */ }
        .line-table td { height: 18px; padding: 0 3px 2px; vertical-align: bottom; }
        .line-label { white-space: nowrap; font-size: 10px; }
        .line-fill { border-bottom: 1px solid #333; height: 16px; padding: 0 7px; font-weight: 800; }

        .logo-image { display: block; max-width: 122px; max-height: 72px; object-fit: contain; }
        .logo-image.left { margin-left: 14px; }
        .logo-image.right { margin-left: auto; margin-right: 24px; }

        .section-title {
            margin: 10px 0 5px;
            color: {{ $primaryColor }};
            font-size: 13px;
            font-weight: 900;
            text-align: center;
        }
        .accident-types td { padding: 3px 4px; vertical-align: middle; font-size: 10px; }
        .ar-check { white-space: nowrap; }
        .check,
        .check-sm {
            display: inline-block;
            vertical-align: middle;
            border: 1px solid #444;
            background: #fff;
            text-align: center;
            font-family: Arial, sans-serif;
            font-weight: 900;
        }
        .check { width: 18px; height: 18px; line-height: 17px; margin: 0 6px; }
        .check-sm { width: 14px; height: 14px; line-height: 13px; margin: 0 5px; }
        .is-checked::after { content: "X"; }

        .party { margin-top: 7px; border: 0; }
        .party th,
        .party td { border: 0; }
        .bar th {
            height: 24px;
            padding: 2px 5px;
            background: {{ $primaryColor }};
            color: #111;
            font-size: 10px;
            font-weight: 900;
            vertical-align: middle;
            border-left: 1px solid #333;
        }
        .bar th:first-child { border-left: 0; }
        .party td { height: 24px; padding: 2px 5px; vertical-align: middle; font-weight: 800; }
        .party tr:not(.bar) td { border-bottom: 1px solid #555; }
        .party .label-en { width: 18%; font-weight: 400; border-right: 0; }
        .party .value { width: 32%; text-align: center; }
        .party tr:not(.bar) .value:nth-child(2) { border-right: 1px solid #555; }
        .party tr:not(.bar) .value:nth-child(3) { border-right: 0; }
        .party .label-ar { width: 18%; font-weight: 800; white-space: nowrap; line-height: 1.05; border-left: 0; }

        .witness { margin-top: 6px; border: 1px solid #555; }
        .witness th,
        .witness td { height: 21px; border: 1px solid #555; padding: 2px 5px; }
        .witness th { font-size: 10px; font-weight: 900; }
        .witness td { font-weight: 800; }
        .witness .label-en { font-weight: 700; }

        .damage { margin-top: 7px; }
        .damage td { padding: 0 4px; vertical-align: top; text-align: center; }
        .side-title { margin-bottom: 2px; font-size: 10.3px; font-weight: 900; line-height: 1.04; }
        .middle-title {
            padding-top: 18px;
            color: {{ $primaryColor }};
            font-size: 16px;
            font-weight: 900;
            line-height: 1.04;
        }
        .cars { height: 78px; padding-top: 4px; line-height: 0; }
        .cars img { display: inline-block; object-fit: contain; vertical-align: middle; }
        .car-front,
        .car-rear { width: 31px; height: 18px; margin: 0 10px; }
        .car-top { width: 118px; height: 30px; margin: 0 5px; }
        .car-side { width: 118px; height: 28px; margin: 4px 10px 0; }
        .damage-line {
            min-height: 13px;
            margin: 0 10px;
            border-bottom: 1px solid #777;
            font-size: 8.8px;
            font-weight: 800;
        }

        .causes { margin-top: 1px; }
        .causes td { width: 25%; padding: 0 5px; vertical-align: top; }
        .cause-head { color: {{ $primaryColor }}; font-size: 11px; font-weight: 900; height: 15px; }
        .cause-item { height: 17px; white-space: nowrap; font-size: 10px; }

        .signature-box { margin-top: 8px; border: 1px solid {{ $primaryColor }}; padding: 7px 8px 7px; }
        .signature-box td { padding: 0 5px; vertical-align: bottom; text-align: center; }
        .sig-line { height: 17px; margin-top: 7px; border-bottom: 1px solid {{ $primaryColor }}; font-weight: 800; line-height: 17px; }
        .sig-label { font-size: 10px; font-weight: 900; line-height: 1; }
        .sig-label .ar { display: block; margin-bottom: 0; }

        .liva { margin-top: 9px; }
        .liva td { vertical-align: top; }
        .liva-title {
            color: {{ $primaryColor }};
            font-size: 15px;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 2px;
        }
        .policy-box td {
            height: 22px;
            border: 1px solid #555;
            padding: 1px 4px;
            text-align: center;
            font-weight: 800;
        }
        .liva-checks { margin-top: 5px; }
        .liva-checks td { height: 18px; padding: 0 2px; font-size: 10px; }

        .bottom { margin-top: 10px; }
        .bottom td { vertical-align: bottom; text-align: center; }
        .liva-logo-text {
            color: {{ $primaryColor }};
            font-family: Arial, sans-serif;
            font-size: 52px;
            line-height: 39px;
            font-weight: 900;
            letter-spacing: -4px;
            text-align: left;
        }
        .liva-logo-ar { color: {{ $primaryColor }}; font-size: 13px; font-weight: 900; text-align: left; }
        .liva-logo-image { display: block; max-width: 92px; max-height: 62px; object-fit: contain; }
        .liva-contact { margin-top: 2px; font-size: 8px; line-height: 1.12; text-align: left; }
        .stamp { height: 25px; margin: 0 18px 3px; border-bottom: 1px solid #333; font-weight: 800; }
        .footer { margin-top: 8px; text-align: center; font-size: 8px; line-height: 1.12; }
    </style>
</head>
<body>
<div class="top-rule"></div>
<div class="page">
    <table class="header">
        <tr>
            <td class="logo-cell">
                @if($omanLogoUrl)
                    <img class="logo-image left" src="{{ $omanLogoUrl }}" alt="">
                @else
                    <div class="oman-mark" aria-hidden="true">
                        <span class="red"></span>
                        <span class="green"></span>
                        <span class="swords">⚔</span>
                    </div>
                @endif
            </td>
            <td class="title-cell">
                <span class="title-ar ar">إستمارة حادث مرور (بسيط)</span>
                <span class="title-en">MINOR ROAD TRAFFIC ACCIDENT FORM</span>
            </td>
            <td class="logo-cell">
                @if($ropLogoUrl)
                    <img class="logo-image right" src="{{ $ropLogoUrl }}" alt="">
                @else
                    <div class="rop-mark" aria-hidden="true">
                        <span class="swords">⚔</span>
                        <span class="text">R.O.P</span>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <table class="line-table">
        <tr>
            <td class="line-label" style="width:5%;">Time:</td>
            <td class="line-fill" style="width:18%;">{{ $payload['time'] }}</td>
            <td class="line-label ar right" style="width:10%;">وقت الحادث</td>
            <td style="width:28%;"></td>
            <td class="line-label" style="width:5%;">Date:</td>
            <td class="line-fill" style="width:16%;">{{ $payload['date'] }}</td>
            <td class="line-label ar right" style="width:18%;">تاريخ الحادث</td>
        </tr>
        <tr>
            <td class="line-label" colspan="2">Accident Location:</td>
            <td class="line-fill" colspan="4">{{ $payload['location'] }}</td>
            <td class="line-label ar right">موقع الحادث</td>
        </tr>
    </table>

    <div class="section-title">Type of Accident <span class="ar" style="margin-left:16px;">نوع الحادث</span></div>
    <table class="accident-types">
        <tr>
            <td style="width:19%;"><span class="check {{ $checked($types, 'stationary_object') }}"></span>Collision against<br>a stationary object</td>
            <td style="width:23%;"><span class="check {{ $checked($types, 'vehicle_collision') }}"></span>Collision between vehicles</td>
            <td class="ar right ar-check" style="width:24%;"><span class="check {{ $checked($types, 'vehicle_collision') }}"></span>إصطدام بين مركبتين أو أكثر</td>
            <td class="ar right ar-check" style="width:24%;"><span class="check {{ $checked($types, 'stationary_object') }}"></span>إصطدام بجسم ثابت</td>
        </tr>
        <tr>
            <td><span class="check {{ $checked($types, 'roll_over') }}"></span>Roll-over</td>
            <td></td>
            <td></td>
            <td class="ar right ar-check"><span class="check {{ $checked($types, 'roll_over') }}"></span>تدهور</td>
        </tr>
    </table>

    <table class="party">
        <tr class="bar">
            <th style="width:18%;">Details</th>
            <th style="width:32%;">Second Party (Faulty Party) <span class="ar">الطرف الثاني (المتسبب)</span></th>
            <th style="width:32%;">First Party <span class="ar">الطرف الأول</span></th>
            <th class="ar right" style="width:18%;">البيانات</th>
        </tr>
        @foreach($partyRows as [$key, $en, $ar])
            <tr>
                <td class="label-en">{{ $en }}</td>
                <td class="value">{{ $value($second, $key) }}</td>
                <td class="value">{{ $value($first, $key) }}</td>
                <td class="label-ar ar right">{{ $ar }}</td>
            </tr>
        @endforeach
    </table>

    <table class="witness">
        <tr>
            <th style="width:10%;">Witness</th>
            <th style="width:40%;">First Witness <span class="ar">الشاهد الأول</span></th>
            <th style="width:40%;">Second Witness <span class="ar">الشاهد الثاني</span></th>
            <th class="ar right" style="width:10%;">الشهود</th>
        </tr>
        @foreach([['name', 'Name', 'الاسم'], ['address', 'Address', 'العنوان'], ['phone', 'Tel. No:', 'رقم الهاتف']] as [$key, $en, $ar])
            <tr>
                <td class="label-en">{{ $en }}</td>
                <td class="center">{{ data_get($witnesses, "0.$key") }}</td>
                <td class="center">{{ data_get($witnesses, "1.$key") }}</td>
                <td class="ar right strong">{{ $ar }}</td>
            </tr>
        @endforeach
    </table>

    <table class="damage">
        <tr>
            <td style="width:38%;">
                <div class="side-title"><span class="ar">المركبة الثانية (المتسبب)</span><br>Second Vehicle (Faulty Driver)</div>
                <div class="cars">
                    <img class="car-front" src="{{ $carView('front') }}" alt=""><img class="car-top" src="{{ $carView('top') }}" alt=""><img class="car-rear" src="{{ $carView('rear') }}" alt=""><br>
                    <img class="car-side" src="{{ $carView('left') }}" alt=""><img class="car-side" src="{{ $carView('right') }}" alt="">
                </div>
                <div class="damage-line">{{ data_get($damages, 'second_party_notes') }}</div>
            </td>
            <td style="width:24%;">
                <div class="middle-title"><span class="ar">الأضرار بالمركبات</span><br>Damages to the Vehicle</div>
            </td>
            <td style="width:38%;">
                <div class="side-title"><span class="ar">المركبة الأولى</span><br>First Vehicle</div>
                <div class="cars">
                    <img class="car-front" src="{{ $carView('front') }}" alt=""><img class="car-top" src="{{ $carView('top') }}" alt=""><img class="car-rear" src="{{ $carView('rear') }}" alt=""><br>
                    <img class="car-side" src="{{ $carView('left') }}" alt=""><img class="car-side" src="{{ $carView('right') }}" alt="">
                </div>
                <div class="damage-line">{{ data_get($damages, 'first_party_notes') }}</div>
            </td>
        </tr>
    </table>

    <table class="causes">
        <tr>
            <td>
                <div class="cause-head">Causes of Accident</div>
                @foreach(array_slice($causePairs, 0, 5) as [$key, $en])
                    <div class="cause-item"><span class="check-sm {{ $checked($causes, $key) }}"></span>{{ $en }}</div>
                @endforeach
            </td>
            <td style="padding-top:12px;">
                @foreach(array_slice($causePairs, 5) as [$key, $en])
                    <div class="cause-item"><span class="check-sm {{ $checked($causes, $key) }}"></span>{{ $en }}</div>
                @endforeach
            </td>
            <td class="ar right" style="padding-top:12px;">
                @foreach(array_slice($causePairs, 5) as [$key, $en, $ar])
                    <div class="cause-item"><span class="check-sm {{ $checked($causes, $key) }}"></span>{{ $ar }}</div>
                @endforeach
            </td>
            <td class="ar right">
                <div class="cause-head ar">أسباب الحادث</div>
                @foreach(array_slice($causePairs, 0, 5) as [$key, $en, $ar])
                    <div class="cause-item"><span class="check-sm {{ $checked($causes, $key) }}"></span>{{ $ar }}</div>
                @endforeach
            </td>
        </tr>
    </table>

    <div class="signature-box">
        <table>
            <tr>
                <td style="width:44%;">
                    <div class="sig-label"><span class="ar">توقيع الطرف الثاني</span><br>Second Party Signature</div>
                    <div class="sig-line">{{ data_get($signatures, 'second_party_name') }}</div>
                </td>
                <td style="width:12%;"></td>
                <td style="width:44%;">
                    <div class="sig-label"><span class="ar">توقيع الطرف الأول</span><br>First Party Signature</div>
                    <div class="sig-line">{{ data_get($signatures, 'first_party_name') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="liva">
        <tr>
            <td style="width:36%; padding-right:6px;">
                <div class="liva-title">{{ $pdfSettings['insurance_section_title_en'] }}</div>
                <div>The vehicle involved in the accident is<br>insured with us vide Insurance Policy No.:</div>
                <div style="margin-top:4px;">Type of Insurance</div>
                <div style="margin-top:4px;">Claim No.</div>
            </td>
            <td style="width:28%; padding-top:12px;">
                <table class="policy-box">
                    <tr><td>{{ data_get($insurance, 'policy_no') }}</td></tr>
                    <tr><td>{{ data_get($insurance, 'type') }}</td></tr>
                    <tr><td>{{ data_get($insurance, 'claim_no') }}</td></tr>
                </table>
            </td>
            <td class="ar right" style="width:36%; padding-left:6px;">
                <div class="liva-title">{{ $pdfSettings['insurance_section_title_ar'] }}</div>
                <div>المركبة المتسببة في الحادث مؤمنة لدينا بموجب<br>الوثيقة رقم:</div>
                <div style="margin-top:4px;">نوع التأمين</div>
                <div style="margin-top:4px;">رقم المطالبة</div>
            </td>
        </tr>
    </table>

    <table class="liva-checks">
        <tr>
            <td style="width:50%;"><span class="check-sm {{ data_get($insurance, 'company_will_repair') ? 'is-checked' : '' }}"></span>The Company will repair the damages as per the insurance policy.</td>
            <td class="ar right" style="width:50%;"><span class="check-sm {{ data_get($insurance, 'company_will_repair') ? 'is-checked' : '' }}"></span>بموجبه سوف تقوم الشركة بإصلاح المركبة المتضررة</td>
        </tr>
        <tr>
            <td><span class="check-sm {{ data_get($insurance, 'technical_opinion_required') ? 'is-checked' : '' }}"></span>Therefore, technical opinion is required</td>
            <td class="ar right"><span class="check-sm {{ data_get($insurance, 'technical_opinion_required') ? 'is-checked' : '' }}"></span>نطلب رأياً فنياً حول أسباب الحادث</td>
        </tr>
    </table>

    <table class="bottom">
        <tr>
            <td style="width:22%; text-align:left; padding-left:8px;">
                @if($livaLogoUrl)
                    <img class="liva-logo-image" src="{{ $livaLogoUrl }}" alt="">
                @else
                    <!-- <div class="liva-logo-text">{{ $pdfSettings['liva_logo_text'] }}</div>
                    <div class="liva-logo-ar ar">{{ $pdfSettings['liva_logo_ar'] }}</div> -->
                @endif
            </td>
            <td style="width:39%;">
                <div class="stamp"></div>
                <strong class="ar">التوقيع والختم</strong><br><strong>Signature &amp; Stamp</strong>
            </td>
            <td style="width:39%;">
                <div class="stamp">{{ data_get($insurance, 'signatory_name') }}</div>
                <strong class="ar">اسم المخول بالتوقيع</strong><br><strong>Name of the Signatory</strong>
            </td>
        </tr>
    </table>

    <div class="footer">
        <span class="ar">{{ $pdfSettings['footer_ar'] }}</span><br>
        {{ $pdfSettings['footer_en'] }}
    </div>
</div>
</body>
</html>
