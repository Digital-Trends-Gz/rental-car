<?php

return [
    'accepted' => "يجب قبول :attribute.",
    'array' => "يجب أن يكون :attribute مصفوفة.",
    'between' => [
        'numeric' => "يجب أن تكون قيمة :attribute بين :min و :max.",
    ],
    'boolean' => "يجب أن تكون قيمة :attribute صحيحة أو خاطئة.",
    'date' => "حقل :attribute ليس تاريخا صحيحا.",
    'email' => "يجب أن يكون :attribute بريدا إلكترونيا صحيحا.",
    'file' => "يجب أن يكون :attribute ملفا.",
    'in' => "القيمة المحددة في :attribute غير صحيحة.",
    'integer' => "يجب أن يكون :attribute رقما صحيحا.",
    'max' => [
        'array' => "يجب ألا يحتوي :attribute على أكثر من :max عناصر.",
        'file' => "يجب ألا يتجاوز حجم :attribute :max كيلوبايت.",
        'numeric' => "يجب ألا تكون قيمة :attribute أكبر من :max.",
        'string' => "يجب ألا يتجاوز :attribute :max حرفا.",
    ],
    'mimes' => "يجب أن يكون :attribute ملفا من نوع: :values.",
    'numeric' => "يجب أن يكون :attribute رقما.",
    'required' => "حقل :attribute مطلوب.",
    'required_if' => "حقل :attribute مطلوب عندما يكون :other هو :value.",
    'required_unless' => "حقل :attribute مطلوب إلا إذا كان :other هو :values.",
    'string' => "يجب أن يكون :attribute نصا.",

    'custom' => [
        'contract_id' => [
            'required_if' => "حقل العقد مطلوب عندما يكون نوع الحادث مع العميل.",
        ],
        'car_id' => [
            'required_unless' => "حقل السيارة مطلوب عندما لا يكون نوع الحادث مع العميل.",
        ],
        'branch_id' => [
            'required_unless' => "حقل الفرع مطلوب عندما لا يكون نوع الحادث مع العميل.",
        ],
        'employee_id' => [
            'required_if' => "حقل الموظف مطلوب عندما يكون نوع الحادث مع موظف.",
        ],
    ],

    'attributes' => [
        'accident_context' => "نوع الحادث",
        'accident_at' => "وقت الحادث",
        'branch_id' => "الفرع",
        'car_id' => "السيارة",
        'contract_id' => "العقد",
        'description' => "الوصف",
        'employee_id' => "الموظف",
        'has_injuries' => "وجود إصابات",
        'latitude' => "خط العرض",
        'location' => "الموقع",
        'location_type' => "نوع الموقع",
        'longitude' => "خط الطول",
        'notes' => "الملاحظات",
        'photo_notes' => "ملاحظات الصور",
        'photo_types' => "أنواع الصور",
        'photos' => "الصور",
        'police_report_number' => "رقم تقرير الشرطة",
        'responsibility' => "المسؤولية",
        'third_party_details' => "بيانات الطرف الثالث",
        'third_party_involved' => "وجود طرف ثالث",
    ],

    'values' => [
        'accident_context' => [
            'branch' => "عند المكتب أو البوابة",
            'contract' => "مع العميل",
            'employee' => "مع موظف",
        ],
    ],
];
