<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/composables/useTrans';
import { Image as ImageIcon } from 'lucide-vue-next';

type DamageReport = {
    id: number;
    report_number: string;
    report_type?: string;
    status: string;
    inspected_at: string | null;
    items_count: number;
    total_estimated_cost: number;
    after_return_items_count?: number;
    after_return_total_estimated_cost?: number;
    summary: string | null;
    edit_url: string;
    destroy_url: string;
};

type OptionItem = { value: string; label: string };

const props = defineProps<{
    contract: {
        id: number;
        contract_number: string;
        status: string;
        renter_name?: string | null;
        car_details?: string | null;
        plate_number?: string | null;
        start_date?: string | null;
        end_date?: string | null;
        vehicle_odometer?: number | null;
        vehicle_fuel_level?: string | null;
        vehicle_condition_before?: string | null;
        vehicle_condition_after?: string | null;
        daily_rate?: number | null;
        allowed_km_per_day?: number | null;
        allowed_km_per_week?: number | null;
        allowed_km_per_month?: number | null;
        reservation?: {
            id: number;
            reservation_number: string;
            status: string;
            status_label: string;
            status_color: string;
            user_name?: string | null;
            car?: string | null;
            pickup_time?: string | null;
            return_time?: string | null;
            return_location?: string | null;
            return_location_fee?: number | null;
        } | null;
        branch_name?: string | null;
        damage_reports: DamageReport[];
        handover_photos?: {
            delivery?: Array<Record<string, any>>;
            return?: Array<Record<string, any>>;
        };
    };
    report: {
        id: number | null;
        report_number: string;
        status: string;
        payment_status?: string | null;
        actual_return_time: string | null;
        return_location: string | null;
        return_odometer: number | null;
        return_fuel_level: string | null;
        vehicle_condition_after: string | null;
        has_damage?: boolean | null;
        damage_report_id: number | null;
        extra_kilometers: number | null;
        kilometer_rate: number | null;
        cleaning_fee: number | null;
        fuel_fee: number | null;
        fuel_credit: number | null;
        late_hours: number | null;
        late_hour_rate: number | null;
        damage_fee: number | null;
        maintenance_fee: number | null;
        other_fee: number | null;
        discount: number | null;
        total_extra_charges: number | null;
        notes: string | null;
    };
    defaults: {
        return_location_fee: number | null;
        cleaning_fee: number | null;
        fuel_fee: number | null;
        fuel_credit: number | null;
        late_hour_rate: number | null;
        kilometer_rate: number | null;
        late_hours: number | null;
        damage_fee: number | null;
    };
    settings: {
        return_time_policy: {
            mode?: string;
            fixed_time?: string;
        };
        pickup_return_locations: Array<{
            name: string;
            pickup_fee?: number | null;
            return_fee?: number | null;
            pickup_free?: boolean;
            return_free?: boolean;
            is_active?: boolean;
        }>;
        kilometer_pricing: Array<{
            from_km?: number | string | null;
            to_km?: number | string | null;
            price?: number | string | null;
        }>;
        fuel_pricing: Array<{
            fuel_level?: string | null;
            price?: number | string | null;
        }>;
        late_return: {
            mode?: string;
            hourly_fee?: number | null;
            after_hours?: number | null;
        };
    };
    options: {
        fuelLevels: OptionItem[];
        vehicleConditions: OptionItem[];
    };
    actions: {
        index: string;
        store: string;
        print?: string | null;
    };
    handoverPhotos?: {
        delivery?: Array<Record<string, any>>;
        return?: Array<Record<string, any>>;
    };
    permissions?: {
        can_edit_return_report?: boolean;
    };
}>();


const { t, locale } = useTrans();
const arabicTranslations: Record<string, string> = {
    'Return Status Report': 'تقرير حالة الإرجاع',
    'Record the car return, extra charges, and linked damage report in one place.': 'سجل إرجاع السيارة والرسوم الإضافية وتقرير الضرر المرتبط في مكان واحد.',
    'Print Invoice': 'طباعة الفاتورة',
    'Back': 'رجوع',
    'Contract Summary': 'ملخص العقد',
    'The return report is linked to this contract and reservation.': 'هذا التقرير مرتبط بهذا العقد والحجز.',
    'Contract No.': 'رقم العقد',
    'Reservation': 'الحجز',
    'Client': 'العميل',
    'Car': 'السيارة',
    'Branch': 'الفرع',
    'Reservation Status': 'حالة الحجز',
    'Contract Start': 'بداية العقد',
    'Contract End': 'نهاية العقد',
    'This return report is marked paid and locked. You can print it, but editing is disabled.': 'تم تسجيل هذا التقرير كمدفوع ومقفل. يمكنك طباعته لكن لا يمكن تعديله.',
    'You can view this return report, but your role does not allow editing it.': 'يمكنك عرض تقرير الإرجاع، لكن الدور الحالي لا يسمح بتعديله.',
    'Return Details': 'بيانات الإرجاع',
    'Record the actual return state for this contract.': 'سجل حالة الإرجاع الفعلية لهذا العقد.',
    'Actual Return Time': 'وقت الإرجاع الفعلي',
    'Return Location': 'موقع الإرجاع',
    'Select return location': 'اختر موقع الإرجاع',
    'Default return fee:': 'رسوم الإرجاع الافتراضية:',
    'Free': 'مجاني',
    'Return Odometer': 'عداد الإرجاع',
    'Return Fuel Level': 'مستوى الوقود عند الإرجاع',
    'Select fuel level': 'اختر مستوى الوقود',
    'Vehicle Condition After Return': 'حالة السيارة بعد الإرجاع',
    'Has Damage?': 'هل يوجد ضرر؟',
    'Yes, there is damage': 'نعم، يوجد ضرر',
    'No damage': 'لا يوجد ضرر',
    'No damage report will be created for this return.': 'لن يتم إنشاء تقرير ضرر لهذا الإرجاع.',
    'Linked Damage Report': 'تقرير الضرر المرتبط',
    'None': 'بدون',
    'items': 'عناصر',
    'Damage report selection is disabled when there is no damage.': 'يتم تعطيل اختيار تقرير الضرر عند عدم وجود ضرر.',
    'Payment Status': 'حالة الدفع',
    'Not Paid': 'غير مدفوعة',
    'Paid': 'مدفوعة',
    'Set Paid to create the extra payment and lock the report after saving.': 'اختر مدفوعة لإنشاء الدفعة الإضافية وقفل التقرير بعد الحفظ.',
    'Contract vs Return': 'مقارنة العقد مع الإرجاع',
    'Use this section to compare the original contract values with the actual return values.': 'استخدم هذا القسم لمقارنة قيم العقد الأصلية مع قيم الإرجاع الفعلية.',
    'Compare the contract odometer and expected return time against the actual return values.': 'قارن عداد العقد ووقت الإرجاع المتوقع مع القيم الفعلية عند الإرجاع.',
    'Contract Odometer': 'عداد العقد',
    'Expected Return Time': 'وقت الإرجاع المتوقع',
    'Extra Kilometers': 'الكيلومترات الزائدة',
    'Late Hours': 'ساعات التأخير',
    'Extra Charges': 'الرسوم الإضافية',
    'These charges are auto-calculated from the tenant reservation settings and the actual return data.': 'يتم حساب هذه الرسوم تلقائيًا من إعدادات الحجز وبيانات الإرجاع الفعلية.',
    'Kilometer Rate': 'سعر الكيلومتر',
    'Cleaning Fee': 'رسوم التنظيف',
    'Fuel Fee': 'رسوم الوقود',
    'Fuel Credit': 'رصيد الوقود',
    'Fuel returned higher than pickup, credit applies to the customer.': 'تمت إعادة الوقود أكثر من وقت الاستلام، وسيتم احتساب رصيد لصالح العميل.',
    'Late Hour Rate': 'سعر ساعة التأخير',
    'Default from tenant reservation settings.': 'القيمة الافتراضية من إعدادات الحجز الخاصة بالمستأجر.',
    'Damage Fee': 'رسوم الضرر',
    'Selected after-return damage total:': 'إجمالي ضرر ما بعد الإرجاع المحدد:',
    'Maintenance Fee': 'رسوم الصيانة',
    'Other Fee': 'رسوم أخرى',
    'Discount': 'الخصم',
    'Discount is capped at the payable subtotal.': 'يتم تطبيق الخصم حتى قيمة الإجمالي المستحق فقط.',
    'After-Return Damage Reports': 'تقارير الضرر بعد الإرجاع',
    'Only damage reports created after delivery are used for return charges.': 'يتم استخدام تقارير الضرر التي تم إنشاؤها بعد التسليم فقط لرسوم الإرجاع.',
    'No after-return damage reports have been created yet for this contract.': 'لم يتم إنشاء أي تقرير ضرر بعد الإرجاع لهذا العقد بعد.',
    'Report': 'التقرير',
    'Items': 'العناصر',
    'Estimated Cost': 'التكلفة التقديرية',
    'Status': 'الحالة',
    'Summary': 'الملخص',
    'Actions': 'الإجراءات',
    'No summary': 'لا يوجد ملخص',
    'Open': 'فتح',
    'Delete': 'حذف',
    'Final Summary': 'الملخص النهائي',
    'The report total will be used for the extra cash payment.': 'سيتم استخدام إجمالي التقرير كدفعة نقدية إضافية.',
    'Allowed Kilometers': 'الكيلومترات المسموحة',
    'Late extra days:': 'أيام التأخير الإضافية:',
    'Actual Kilometers Driven': 'الكيلومترات الفعلية المقطوعة',
    'Contract odometer to return odometer': 'من عداد العقد إلى عداد الإرجاع',
    'Extra Kilometer Charges': 'رسوم الكيلومترات الإضافية',
    'Extra kilometers after allowance': 'الكيلومترات الزائدة بعد السماح',
    'Late Return Fee': 'رسوم التأخير',
    'hours': 'ساعات',
    'Applied after all charges and credits.': 'يطبق بعد كل الرسوم والأرصدة.',
    'Total Extra Charges': 'إجمالي الرسوم الإضافية',
    'Fuel credit deducted from the total.': 'تم خصم رصيد الوقود من الإجمالي.',
    'Credit due to customer': 'الرصيد المستحق للعميل',
    'Notes': 'ملاحظات',
    'Saving...': 'جاري الحفظ...',
    'Save Return Report': 'حفظ تقرير الإرجاع',
    'Cancel': 'إلغاء',
    'Car was clean at delivery, returned dirty - cleaning fee applies': 'السيارة كانت نظيفة عند التسليم وعادت متسخة - تطبق رسوم التنظيف',
    'Car was already not clean at delivery - no cleaning fee': 'السيارة كانت غير نظيفة عند التسليم - لا توجد رسوم تنظيف',
    'Car was clean at delivery and return - no cleaning fee': 'السيارة كانت نظيفة عند التسليم والإرجاع - لا توجد رسوم تنظيف',
    'Delivery': 'التسليم',
    'Return': 'الإرجاع',
    'Fuel gain:': 'زيادة الوقود:',
    'Fuel loss:': 'نقص الوقود:',
    'Loss': 'نقص',
    'Gain': 'زيادة',
    'No fuel difference': 'لا يوجد فرق بالوقود',
    'Contract': 'العقد',
    'day(s)': 'يوم/أيام',
    'hour(s)': 'ساعة/ساعات',
};
const localize = (en: string, ar: string) => (locale.value === 'ar' ? (arabicTranslations[en] ?? ar) : en);

// Ensure options is always defined from props
const options = props.options ?? { fuelLevels: [], vehicleConditions: [] };

function fuelLevelStorageValue(value: string | null | undefined) {
    const normalized = String(value ?? '').trim().toLowerCase();

    const map: Record<string, string> = {
        empty: 'empty',
        '0': 'empty',
        '0/4': 'empty',
        '0%': 'empty',
        'empty tank': 'empty',
        'فارغ': 'empty',
        'فاضي': 'empty',
        quarter: 'quarter',
        '1/4': 'quarter',
        '1-4': 'quarter',
        '1 4': 'quarter',
        '1/4 tank': 'quarter',
        'quarter tank': 'quarter',
        'ربع': 'quarter',
        'ربع الخزان': 'quarter',
        'ربع تانكي': 'quarter',
        half: 'half',
        '1/2': 'half',
        '1-2': 'half',
        '1 2': 'half',
        '1/2 tank': 'half',
        'half tank': 'half',
        'نصف': 'half',
        'نصف الخزان': 'half',
        'نصف تانكي': 'half',
        three_quarters: 'three_quarters',
        '3/4': 'three_quarters',
        '3-4': 'three_quarters',
        '3 4': 'three_quarters',
        '3/4 tank': 'three_quarters',
        'three-quarters': 'three_quarters',
        'three quarters': 'three_quarters',
        'ثلاثة ارباع': 'three_quarters',
        'ثلاثة أرباع': 'three_quarters',
        'ثلاثة أرباع الخزان': 'three_quarters',
        full: 'full',
        '1': 'full',
        '100': 'full',
        '100%': 'full',
        'full tank': 'full',
        'ممتلئ': 'full',
        'فل': 'full',
    };

    return map[normalized] ?? normalized;
}

const form = useForm({
    actual_return_time: props.report.actual_return_time ?? '',
    payment_status: props.report.payment_status ?? 'not_paid',
    return_location: props.report.return_location ?? props.contract.reservation?.return_location ?? '',
    return_odometer: props.report.return_odometer ?? props.contract.vehicle_odometer ?? '',
    return_fuel_level: fuelLevelStorageValue(props.report.return_fuel_level ?? props.contract.vehicle_fuel_level ?? ''),
    vehicle_condition_after: props.report.vehicle_condition_after ?? props.contract.vehicle_condition_after ?? 'clean',
    has_damage: props.report.has_damage ?? props.contract.damage_reports.some((report) => report.report_type === 'after_return'),
    damage_report_id: props.report.damage_report_id ?? '',
    extra_kilometers: props.report.extra_kilometers ?? 0,
    kilometer_rate: props.report.kilometer_rate ?? props.defaults.kilometer_rate ?? 0,
    cleaning_fee: props.report.cleaning_fee ?? 0, // Will be auto-calculated by watch based on condition comparison
    fuel_fee: props.report.fuel_fee ?? props.defaults.fuel_fee ?? 0,
    fuel_credit: props.report.fuel_credit ?? 0,
    late_hours: props.report.late_hours ?? props.defaults.late_hours ?? 0,
    late_hour_rate: props.report.late_hour_rate ?? props.defaults.late_hour_rate ?? props.settings.late_return.hourly_fee ?? 0,
    damage_fee: props.report.damage_fee ?? 0, // Only auto-calculated from after-return damage reports
    maintenance_fee: props.report.maintenance_fee ?? 0,
    other_fee: props.report.other_fee ?? 0,
    discount: props.report.discount ?? 0,
    notes: props.report.notes ?? '',
});

const isLocked = computed(() => props.report.id !== null && (props.report.payment_status ?? 'not_paid') === 'paid');
const canEditReturnReport = computed(() => Boolean(props.permissions?.can_edit_return_report) && !isLocked.value);

function handoverPhotoValueLabel(value: unknown) {
    if (value === null || value === undefined || value === '') {
        return localize('N/A', 'غير متوفر');
    }

    const normalized = fuelLevelStorageValue(String(value));
    const fuelOption = options.fuelLevels.find((item) => item.value === normalized);

    return fuelOption?.label ?? String(value);
}

function photoHasExtractedValue(photo: Record<string, any> | null | undefined) {
    return photo?.extracted_value !== null && photo?.extracted_value !== undefined && photo?.extracted_value !== '';
}

const handoverPhotoGroups = computed(() => {
    const source = props.handoverPhotos
        ?? props.contract.handover_photos
        ?? {};

    return {
        delivery: Array.isArray((source as Record<string, any>).delivery) ? (source as Record<string, any>).delivery : [],
        return: Array.isArray((source as Record<string, any>).return) ? (source as Record<string, any>).return : [],
    };
});

function findReturnReadingPhoto(photoType: 'odometer' | 'fuel') {
    return [...handoverPhotoGroups.value.return]
        .reverse()
        .find((photo) => String(photo?.photo_type || '').toLowerCase() === photoType && (photoHasExtractedValue(photo) || photo?.url))
        ?? null;
}

const returnOdometerPhoto = computed(() => findReturnReadingPhoto('odometer'));
const returnFuelPhoto = computed(() => findReturnReadingPhoto('fuel'));

function readingPhotoTooltip(photo: Record<string, any> | null, fallback: string) {
    if (!photo) {
        return fallback;
    }

    const value = photoHasExtractedValue(photo)
        ? handoverPhotoValueLabel(photo.extracted_value)
        : localize('No extracted value', 'لا توجد قيمة مستخرجة');

    return `${fallback}: ${value}`;
}

function applyReturnReadingPhotoValue(photo: Record<string, any> | null, target: 'odometer' | 'fuel') {
    if (!photo) {
        return;
    }

    if (photoHasExtractedValue(photo) && canEditReturnReport.value) {
        const value = String(photo.extracted_value ?? '').trim();

        if (target === 'odometer') {
            form.return_odometer = value;
        } else {
            form.return_fuel_level = fuelLevelStorageValue(value);
        }
    }

    if (photo.url) {
        window.open(String(photo.url), '_blank', 'noopener,noreferrer');
    }
}

const selectedDamageReport = computed<DamageReport | null>(() => {
    if (!form.has_damage) {
        return null;
    }

    const selectedId = Number(form.damage_report_id || 0);
    if (!selectedId) {
        return null;
    }

    return afterReturnDamageReports.value.find((report) => report.id === selectedId) ?? null;
});

const hasDamageOptions = [
    { value: true, label: localize('Yes, there is damage', 'نعم، يوجد ضرر') },
    { value: false, label: localize('No damage', 'لا يوجد ضرر') },
];

const afterReturnDamageReports = computed(() => {
    return props.contract.damage_reports.filter((report) => report.report_type === 'after_return');
});

function deleteDamageReport(report: DamageReport) {
    const confirmed = window.confirm(
        localize(
            `Delete damage report ${report.report_number}?`,
            `حذف تقرير الضرر ${report.report_number}؟`,
        ),
    );

    if (!confirmed) {
        return;
    }

    router.delete(report.destroy_url, {
        preserveScroll: true,
    });
}

// Cleaning fee logic: only charge if car was clean at delivery but returned dirty
const cleaningFeeShouldApply = computed(() => {
    const before = props.contract.vehicle_condition_before ?? '';
    const after = form.vehicle_condition_after ?? '';
    // Charge only if: delivered clean (clean) AND returned not clean (not_clean)
    return before === 'clean' && after === 'not_clean';
});

const cleaningFeeDescription = computed(() => {
    const before = props.contract.vehicle_condition_before ?? '';
    const after = form.vehicle_condition_after ?? '';
    
    if (!before || !after) {
        return '';
    }
    
    const beforeLabel = options.vehicleConditions.find(c => c.value === before)?.label ?? before;
    const afterLabel = options.vehicleConditions.find(c => c.value === after)?.label ?? after;
    
    if (before === 'clean' && after === 'not_clean') {
        return localize('Car was clean at delivery, returned dirty - cleaning fee applies', 'السيارة كانت نظيفة عند التسليم وعادت متسخة - تنطبق رسوم التنظيف');
    }
    
    if (before === 'not_clean' && after === 'not_clean') {
        return localize('Car was already not clean at delivery - no cleaning fee', 'السيارة كانت غير نظيفة بالفعل عند التسليم - لا توجد رسوم تنظيف');
    }
    
    if (before === 'clean' && after === 'clean') {
        return localize('Car was clean at delivery and return - no cleaning fee', 'السيارة كانت نظيفة عند التسليم والإرجاع - لا توجد رسوم تنظيف');
    }
    
    return `${localize('Delivery', 'التسليم')}: ${beforeLabel} -> ${localize('Return', 'الإرجاع')}: ${afterLabel}`;
});

const selectedReturnLocation = computed(() => {
    return (
        props.settings.pickup_return_locations.find(
            (location) => location.name === form.return_location && location.is_active !== false,
        ) ?? null
    );
});

const fuelLevelOrder: Record<string, number> = {
    empty: 0,
    quarter: 1,
    half: 2,
    three_quarters: 3,
    full: 4,
};

const fuelLossLevel = computed(() => {
    const startLevel = fuelLevelStorageValue(props.contract.vehicle_fuel_level ?? '');
    const returnLevel = fuelLevelStorageValue(form.return_fuel_level ?? '');

    const start = fuelLevelOrder[startLevel] ?? null;
    const end = fuelLevelOrder[returnLevel] ?? null;

    if (start === null || end === null || start <= end) {
        return null;
    }

    const lossSteps = start - end;
    return (Object.entries(fuelLevelOrder).find(([, value]) => value === lossSteps)?.[0] ?? null);
});

const fuelLossDescription = computed(() => {
    const startLevel = fuelLevelStorageValue(props.contract.vehicle_fuel_level ?? '');
    const returnLevel = fuelLevelStorageValue(form.return_fuel_level ?? '');
    if (!startLevel || !returnLevel) {
        return '';
    }

    const startLabel = options.fuelLevels.find((item) => item.value === startLevel)?.label ?? startLevel;
    const returnLabel = options.fuelLevels.find((item) => item.value === returnLevel)?.label ?? returnLevel;
    const start = fuelLevelOrder[startLevel] ?? null;
    const end = fuelLevelOrder[returnLevel] ?? null;
    if (start === null || end === null || start <= end) {
        const gainLabel = fuelGainLevel.value
            ? (options.fuelLevels.find((item) => item.value === fuelGainLevel.value)?.label ?? fuelGainLevel.value)
            : '';

        return gainLabel
            ? `${startLabel} -> ${returnLabel} (${localize('Fuel gain:', 'زيادة الوقود:')} ${gainLabel})`
            : `${startLabel} -> ${returnLabel}`;
    }

    const lossSteps = start - end;
    const lossLabel = options.fuelLevels.find((item) => item.value === (Object.entries(fuelLevelOrder).find(([, value]) => value === lossSteps)?.[0] ?? ''))?.label ?? '';

    return lossLabel
        ? `${startLabel} -> ${returnLabel} (${localize('Fuel loss:', 'نقص الوقود:')} ${lossLabel})`
        : `${startLabel} -> ${returnLabel}`;
});

const fuelComparisonSummary = computed(() => {
    const startLevel = fuelLevelStorageValue(props.contract.vehicle_fuel_level ?? '');
    const returnLevel = fuelLevelStorageValue(form.return_fuel_level ?? '');
    if (!startLevel || !returnLevel) {
        return '';
    }

    const startLabel = options.fuelLevels.find((item) => item.value === startLevel)?.label ?? startLevel;
    const returnLabel = options.fuelLevels.find((item) => item.value === returnLevel)?.label ?? returnLevel;
    const lossLabel = fuelLossLevel.value
        ? (options.fuelLevels.find((item) => item.value === fuelLossLevel.value)?.label ?? fuelLossLevel.value)
        : null;
    const gainLabel = fuelGainLevel.value
        ? (options.fuelLevels.find((item) => item.value === fuelGainLevel.value)?.label ?? fuelGainLevel.value)
        : null;
    const fuelState = lossLabel
        ? `${localize('Loss', 'نقص')}: ${lossLabel}`
        : gainLabel
            ? `${localize('Gain', 'زيادة')}: ${gainLabel}`
            : localize('No fuel difference', 'لا يوجد فرق بالوقود');

    return `${localize('Contract', 'العقد')}: ${startLabel} | ${localize('Return', 'الإرجاع')}: ${returnLabel} | ${fuelState}`;
});

const contractMileageAllowance = computed(() => {
    const startDate = props.contract.start_date ? new Date(props.contract.start_date) : null;
    const endDate = props.contract.end_date ? new Date(props.contract.end_date) : null;

    if (!startDate || !endDate || Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
        return 0;
    }

    const rentalDays = Math.max(1, Math.ceil((endDate.getTime() - startDate.getTime()) / 86400000) + 1);
    const extraLateDays = Number(lateChargeDays.value || 0);
    const daily = Number(props.contract.allowed_km_per_day || 0);
    if (daily > 0) {
        return roundMoney((rentalDays + extraLateDays) * daily);
    }

    const weekly = Number(props.contract.allowed_km_per_week || 0);
    if (weekly > 0) {
        return roundMoney(Math.max(1, Math.ceil((rentalDays + extraLateDays) / 7)) * weekly);
    }

    const monthly = Number(props.contract.allowed_km_per_month || 0);
    if (monthly > 0) {
        return roundMoney(Math.max(1, Math.ceil((rentalDays + extraLateDays) / 30)) * monthly);
    }

    return 0;
});

const actualKilometersDriven = computed(() => {
    const returnOdometer = Number(form.return_odometer || 0);
    const startOdometer = Number(props.contract.vehicle_odometer || 0);
    if (returnOdometer <= 0) {
        return 0;
    }

    return roundMoney(Math.max(0, returnOdometer - startOdometer));
});

const computedExtraKilometers = computed(() => {
    const drivenKilometers = Number(actualKilometersDriven.value || 0);
    const allowedKilometers = Number(contractMileageAllowance.value || 0);

    if (drivenKilometers <= 0) {
        return 0;
    }

    return roundMoney(Math.max(0, drivenKilometers - allowedKilometers));
});

const expectedReturnTimeLabel = computed(() => {
    if (!props.contract.end_date) {
        return '-';
    }

    const policy = props.settings.return_time_policy?.mode || 'fixed_time';
    const expectedTime = (() => {
        if (policy === 'same_pickup') {
            return props.contract.reservation?.pickup_time || props.settings.return_time_policy?.fixed_time || '18:00';
        }

        if (policy === 'set_during_reservation') {
            return props.contract.reservation?.return_time || props.settings.return_time_policy?.fixed_time || '18:00';
        }

        return props.settings.return_time_policy?.fixed_time || '18:00';
    })();

    return `${props.contract.end_date} ${expectedTime}`;
});

const defaultKilometerRate = computed(() => {
    const kilometers = Number(computedExtraKilometers.value || 0);
    if (kilometers <= 0) {
        return 0;
    }

    const tiers = props.settings.kilometer_pricing || [];
    const sorted = [...tiers].sort((a, b) => {
        const aFrom = a.from_km === null || a.from_km === '' ? Number.MAX_SAFE_INTEGER : Number(a.from_km);
        const bFrom = b.from_km === null || b.from_km === '' ? Number.MAX_SAFE_INTEGER : Number(b.from_km);
        return aFrom - bFrom;
    });

    for (const tier of sorted) {
        const from = tier.from_km === null || tier.from_km === '' ? null : Number(tier.from_km);
        const to = tier.to_km === null || tier.to_km === '' ? null : Number(tier.to_km);
        const price = Number(tier.price || 0);

        if (price <= 0) {
            continue;
        }

        if ((from === null || kilometers >= from) && (to === null || kilometers <= to)) {
            return roundMoney(price);
        }
    }

    const fallback = sorted.at(-1);
    return roundMoney(Number(fallback?.price || 0));
});

const defaultFuelFee = computed(() => {
    const lossLevel = fuelLossLevel.value;
    if (!lossLevel) {
        return 0;
    }

    const rule = props.settings.fuel_pricing.find((item) => item.fuel_level === lossLevel);
    return roundMoney(Number(rule?.price || 0));
});

const fuelGainLevel = computed(() => {
    const startLevel = fuelLevelStorageValue(props.contract.vehicle_fuel_level ?? '');
    const returnLevel = fuelLevelStorageValue(form.return_fuel_level ?? '');

    const start = fuelLevelOrder[startLevel] ?? null;
    const end = fuelLevelOrder[returnLevel] ?? null;

    if (start === null || end === null || end <= start) {
        return null;
    }

    const gainSteps = end - start;
    return (Object.entries(fuelLevelOrder).find(([, value]) => value === gainSteps)?.[0] ?? null);
});

const defaultFuelCredit = computed(() => {
    const gainLevel = fuelGainLevel.value;
    if (!gainLevel) {
        return 0;
    }

    const rule = props.settings.fuel_pricing.find((item) => item.fuel_level === gainLevel);
    return roundMoney(Number(rule?.price || 0));
});

const defaultLateHourRate = computed(() => Number(props.settings.late_return.hourly_fee || props.defaults.late_hour_rate || 0));

const computedLateHours = computed(() => {
    const actualReturn = form.actual_return_time;
    if (!actualReturn || !props.contract.end_date) {
        return 0;
    }

    const actual = new Date(actualReturn.replace(' ', 'T'));
    if (Number.isNaN(actual.getTime())) {
        return 0;
    }

    const policy = props.settings.return_time_policy?.mode || 'fixed_time';
    const expectedTime = (() => {
        if (policy === 'same_pickup') {
            return props.contract.reservation?.pickup_time || props.settings.return_time_policy?.fixed_time || '18:00';
        }

        if (policy === 'set_during_reservation') {
            return props.contract.reservation?.return_time || props.settings.return_time_policy?.fixed_time || '18:00';
        }

        return props.settings.return_time_policy?.fixed_time || '18:00';
    })();

    const expected = new Date(`${props.contract.end_date}T${expectedTime.length === 5 ? `${expectedTime}:00` : expectedTime}`);
    if (Number.isNaN(expected.getTime()) || actual.getTime() <= expected.getTime()) {
        return 0;
    }

    return roundMoney((actual.getTime() - expected.getTime()) / 3600000);
});

const lateChargeDays = computed(() => {
    const lateHours = Number(computedLateHours.value || 0);
    const lateSettings = props.settings.late_return ?? {};
    const mode = lateSettings.mode || 'hourly';
    const threshold = Number(lateSettings.after_hours || 0);

    if (mode !== 'daily_after_threshold' || lateHours <= threshold) {
        return 0;
    }

    return Math.max(1, Math.ceil((lateHours - threshold) / 24));
});

const lateFeePreview = computed(() => {
    const lateHours = Number(form.late_hours || 0);
    const hourlyRate = Number(form.late_hour_rate || props.settings.late_return.hourly_fee || 0);
    const lateSettings = props.settings.late_return ?? {};
    const mode = lateSettings.mode || 'hourly';
    const threshold = Number(lateSettings.after_hours || 0);
    const dailyRate = Number(props.contract.daily_rate || 0);

    if (lateHours <= 0 || hourlyRate <= 0) {
        return 0;
    }

    if (mode === 'daily_after_threshold' && threshold > 0 && lateHours > threshold) {
        const excessHours = lateHours - threshold;
        const fullDays = Math.floor(excessHours / 24);
        const remainingHours = roundMoney(excessHours - (fullDays * 24));
        const dayRate = dailyRate > 0 ? dailyRate : hourlyRate * 24;

        return roundMoney((fullDays * dayRate) + (remainingHours * hourlyRate));
    }

    return roundMoney(lateHours * hourlyRate);
});

const lateFeeBreakdown = computed(() => {
    const lateHours = Number(form.late_hours || 0);
    const hourlyRate = Number(form.late_hour_rate || props.settings.late_return.hourly_fee || 0);
    const lateSettings = props.settings.late_return ?? {};
    const mode = lateSettings.mode || 'hourly';
    const threshold = Number(lateSettings.after_hours || 0);
    const dailyRate = Number(props.contract.daily_rate || 0);

    if (lateHours <= 0 || hourlyRate <= 0) {
        return '';
    }

    if (mode === 'daily_after_threshold' && threshold > 0 && lateHours > threshold) {
        const excessHours = lateHours - threshold;
        const fullDays = Math.floor(excessHours / 24);
        const remainingHours = roundMoney(excessHours - (fullDays * 24));
        const dayRate = dailyRate > 0 ? dailyRate : hourlyRate * 24;

        const parts = [];
        if (fullDays > 0) {
            parts.push(`${fullDays} ${localize('day(s)', 'يوم/أيام')} × ${dayRate.toFixed(2)}`);
        }
        if (remainingHours > 0) {
            parts.push(`${remainingHours.toFixed(2)} ${localize('hour(s)', 'ساعة/ساعات')} × ${hourlyRate.toFixed(2)}`);
        }

        return parts.join(' + ');
    }

    return `${lateHours.toFixed(2)} ${localize('hour(s)', 'ساعة/ساعات')} × ${hourlyRate.toFixed(2)}`;
});

const extraKilometerCharges = computed(() => {
    return roundMoney(Number(form.extra_kilometers || 0) * Number(form.kilometer_rate || 0));
});

const chargesBeforeDiscount = computed(() => {
    return roundMoney(
        extraKilometerCharges.value +
            Number(form.cleaning_fee || 0) +
            Number(form.fuel_fee || 0) +
            -Number(form.fuel_credit || 0) +
            lateFeePreview.value +
            Number(form.damage_fee || 0) +
            Number(form.maintenance_fee || 0) +
            Number(form.other_fee || 0),
    );
});

const appliedDiscount = computed(() => {
    return Math.min(roundMoney(Number(form.discount || 0)), Math.max(0, chargesBeforeDiscount.value));
});

const totalExtraCharges = computed(() => {
    return roundMoney(chargesBeforeDiscount.value - appliedDiscount.value);
});

watch(
    computedExtraKilometers,
    (value) => {
        form.extra_kilometers = value;
    },
    { immediate: true },
);

watch(
    defaultKilometerRate,
    (value) => {
        form.kilometer_rate = value;
    },
    { immediate: true },
);

watch(
    defaultFuelFee,
    (value) => {
        form.fuel_fee = value;
    },
    { immediate: true },
);

watch(
    defaultFuelCredit,
    (value) => {
        form.fuel_credit = value;
    },
    { immediate: true },
);

watch(
    defaultLateHourRate,
    (value) => {
        form.late_hour_rate = value;
    },
    { immediate: true },
);

watch(
    computedLateHours,
    (value) => {
        form.late_hours = value;
    },
    { immediate: true },
);

watch(
    () => selectedDamageReport.value?.id,
    (newId, oldId) => {
        if (newId && selectedDamageReport.value) {
            // Only charge damage fee from after-return damage items
            form.damage_fee = roundMoney(
                selectedDamageReport.value.after_return_total_estimated_cost ??
                    selectedDamageReport.value.total_estimated_cost,
            );
        } else {
            // Reset to 0 when no damage report is selected
            form.damage_fee = 0;
        }
    },
    { immediate: true },
);

// Auto-update cleaning fee based on vehicle condition comparison
watch(
    [() => props.contract.vehicle_condition_before, () => form.vehicle_condition_after],
    ([before, after]) => {
        // Only apply cleaning fee if: delivered clean AND returned not clean
        if (before === 'clean' && after === 'not_clean') {
            // Keep the default cleaning fee from settings
            form.cleaning_fee = props.defaults.cleaning_fee ?? 0;
        } else {
            // No cleaning fee in all other cases
            form.cleaning_fee = 0;
        }
    },
    { immediate: true },
);

watch(
    () => form.has_damage,
    (hasDamage) => {
        if (!hasDamage) {
            form.damage_report_id = '';
            form.damage_fee = 0;
        }
    },
    { immediate: true },
);

function roundMoney(value: number): number {
    return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
}

function submit() {
    if (!canEditReturnReport.value) {
        return;
    }

    form.transform((data) => ({
        ...data,
        payment_status: data.payment_status || 'not_paid',
        extra_kilometers: Number(data.extra_kilometers || 0),
        kilometer_rate: Number(data.kilometer_rate || 0),
        cleaning_fee: Number(data.cleaning_fee || 0),
        fuel_fee: Number(data.fuel_fee || 0),
        fuel_credit: Number(data.fuel_credit || 0),
        late_hours: Number(data.late_hours || 0),
        late_hour_rate: Number(data.late_hour_rate || 0),
        damage_fee: Number(data.damage_fee || 0),
        maintenance_fee: Number(data.maintenance_fee || 0),
        other_fee: Number(data.other_fee || 0),
        discount: Number(data.discount || 0),
        has_damage: Boolean(data.has_damage),
        damage_report_id: data.has_damage && data.damage_report_id !== '' ? Number(data.damage_report_id) : null,
        return_odometer: data.return_odometer === '' ? null : Number(data.return_odometer),
        return_fuel_level: data.return_fuel_level === '' ? null : fuelLevelStorageValue(data.return_fuel_level),
    })).post(props.actions.store, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="localize('Return Status Report', 'تقرير حالة الإرجاع')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Return Status Report', 'تقرير حالة الإرجاع') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Record the car return, extra charges, and linked damage report in one place.', 'سجل إرجاع السيارة والمصاريف الإضافية وتقارير الضرر المرتبطة في مكان واحد.') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a v-if="actions.print" :href="actions.print" target="_blank" rel="noopener">
                        <Button variant="outline" type="button">{{ localize('Print Invoice', 'طباعة الفاتورة') }}</Button>
                    </a>
                    <Link :href="actions.index">
                        <Button variant="outline">{{ localize('Back', 'رجوع') }}</Button>
                    </Link>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ localize('Contract Summary', 'ملخص العقد') }}</CardTitle>
                    <CardDescription>{{ localize('The return report is linked to this contract and reservation.', 'هذا التقرير مرتبط بهذا العقد والحجز.') }}</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <div class="text-sm text-muted-foreground">{{ localize('Contract No.', 'رقم العقد') }}</div>
                        <div class="font-semibold">{{ contract.contract_number }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-muted-foreground">{{ localize('Reservation', 'الحجز') }}</div>
                        <div class="font-semibold">{{ contract.reservation?.reservation_number ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-muted-foreground">{{ localize('Client', 'العميل') }}</div>
                        <div class="font-semibold">{{ contract.reservation?.user_name ?? contract.renter_name ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-muted-foreground">{{ localize('Car', 'السيارة') }}</div>
                        <div class="font-semibold">{{ contract.reservation?.car ?? contract.car_details ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-muted-foreground">{{ localize('Branch', 'الفرع') }}</div>
                        <div class="font-semibold">{{ contract.branch_name ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-muted-foreground">{{ localize('Reservation Status', 'حالة الحجز') }}</div>
                        <div class="font-semibold">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold"
                                :style="{ backgroundColor: `${contract.reservation?.status_color ?? '#E5E7EB'}20`, color: contract.reservation?.status_color ?? '#111827' }"
                            >
                                {{ contract.reservation?.status_label ?? '-' }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-muted-foreground">{{ localize('Contract Start', 'بداية العقد') }}</div>
                        <div class="font-semibold">{{ contract.start_date ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-muted-foreground">{{ localize('Contract End', 'نهاية العقد') }}</div>
                        <div class="font-semibold">{{ contract.end_date ?? '-' }}</div>
                    </div>
                </CardContent>
            </Card>

            <form class="space-y-6" @submit.prevent="submit">
                <div v-if="isLocked" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ localize('This return report is marked paid and locked. You can print it, but editing is disabled.', 'تم تسجيل هذا التقرير كمدفوع ومقفل. يمكنك طباعته لكن لا يمكن تعديله.') }}
                </div>
                <div v-else-if="!canEditReturnReport" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    {{ localize('You can view this return report, but your role does not allow editing it.', 'يمكنك عرض تقرير الإرجاع، لكن الدور الحالي لا يسمح بتعديله.') }}
                </div>
                <fieldset :disabled="!canEditReturnReport" class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Return Details', 'بيانات الإرجاع') }}</CardTitle>
                        <CardDescription>{{ localize('Record the actual return state for this contract.', 'سجل حالة الإرجاع الفعلية لهذا العقد.') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <Label for="actual_return_time">{{ localize('Actual Return Time', 'وقت الإرجاع الفعلي') }}</Label>
                            <Input id="actual_return_time" v-model="form.actual_return_time" type="datetime-local" class="mt-1" />
                            <InputError :message="form.errors.actual_return_time" class="mt-1" />
                        </div>
                        <div>
                            <Label for="return_location">{{ localize('Return Location', 'مكان الإرجاع') }}</Label>
                            <select id="return_location" v-model="form.return_location" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                                <option value="">{{ localize('Select return location', 'اختر مكان الإرجاع') }}</option>
                                <option v-for="location in settings.pickup_return_locations" :key="location.name" :value="location.name">
                                    {{ location.name }}
                                </option>
                            </select>
                            <p v-if="selectedReturnLocation" class="mt-1 text-xs text-muted-foreground">
                                {{ localize('Default return fee:', 'رسوم الإرجاع الافتراضية:') }} {{ selectedReturnLocation.return_free ? localize('Free', 'مجاني') : `$${Number(selectedReturnLocation.return_fee ?? 0).toFixed(2)}` }}
                            </p>
                            <InputError :message="form.errors.return_location" class="mt-1" />
                        </div>
                        <div>
                            <Label for="return_odometer">{{ localize('Return Odometer', 'عداد العودة') }}</Label>
                            <div class="mt-1 flex gap-2">
                                <Input id="return_odometer" v-model="form.return_odometer" type="number" :min="props.contract.vehicle_odometer ?? 0" />
                                <Button
                                    v-if="returnOdometerPhoto"
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    class="shrink-0"
                                    :disabled="!photoHasExtractedValue(returnOdometerPhoto) && !returnOdometerPhoto?.url"
                                    :title="readingPhotoTooltip(returnOdometerPhoto, localize('Use return odometer image value', 'استخدام قيمة صورة عداد الرجوع'))"
                                    @click="applyReturnReadingPhotoValue(returnOdometerPhoto, 'odometer')"
                                >
                                    <ImageIcon class="h-4 w-4" />
                                </Button>
                            </div>
                            <InputError :message="form.errors.return_odometer" class="mt-1" />
                        </div>
                        <div>
                            <Label for="return_fuel_level">{{ localize('Return Fuel Level', 'كمية البنزين المرجعة') }}</Label>
                            <div class="mt-1 flex gap-2">
                                <select id="return_fuel_level" v-model="form.return_fuel_level" class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                                    <option value="">{{ localize('Select fuel level', 'اختر كمية البنزين') }}</option>
                                    <option v-for="fuelLevel in options.fuelLevels" :key="fuelLevel.value" :value="fuelLevel.value">{{ fuelLevel.label }}</option>
                                </select>
                                <Button
                                    v-if="returnFuelPhoto"
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    class="shrink-0"
                                    :disabled="!photoHasExtractedValue(returnFuelPhoto) && !returnFuelPhoto?.url"
                                    :title="readingPhotoTooltip(returnFuelPhoto, localize('Use return fuel image value', 'استخدام قيمة صورة وقود الرجوع'))"
                                    @click="applyReturnReadingPhotoValue(returnFuelPhoto, 'fuel')"
                                >
                                    <ImageIcon class="h-4 w-4" />
                                </Button>
                            </div>
                            <InputError :message="form.errors.return_fuel_level" class="mt-1" />
                        </div>
                        <div>
                            <Label for="vehicle_condition_after">{{ localize('Vehicle Condition After Return', 'حالة السيارة بعد الإرجاع') }}</Label>
                            <select id="vehicle_condition_after" v-model="form.vehicle_condition_after" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                                <option v-for="condition in options.vehicleConditions" :key="condition.value" :value="condition.value">{{ condition.label }}</option>
                            </select>
                            <InputError :message="form.errors.vehicle_condition_after" class="mt-1" />
                        </div>
                        <div>
                            <Label for="has_damage">{{ localize('Has Damage?', 'هل يوجد ضرر؟') }}</Label>
                            <select id="has_damage" v-model="form.has_damage" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                                <option v-for="option in hasDamageOptions" :key="String(option.value)" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <p v-if="!form.has_damage" class="mt-1 text-xs text-muted-foreground">
                                {{ localize('No damage report will be created for this return.', 'لن يتم إنشاء تقرير ضرر لهذا الإرجاع.') }}
                            </p>
                            <InputError :message="form.errors.has_damage" class="mt-1" />
                        </div>
                        <div>
                            <Label for="damage_report_id">{{ localize('Linked Damage Report', 'تقارير الضرر المرتبطة') }}</Label>
                            <select id="damage_report_id" v-model="form.damage_report_id" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2" :disabled="!form.has_damage">
                                <option value="">{{ localize('None', 'بدون') }}</option>
                                <option v-for="damageReport in afterReturnDamageReports" :key="damageReport.id" :value="damageReport.id">
                                    {{ damageReport.report_number }} - {{ damageReport.items_count }} {{ localize('items', 'عنصر') }} - ${{ Number(damageReport.total_estimated_cost).toFixed(2) }}
                                </option>
                            </select>
                            <p v-if="!form.has_damage" class="mt-1 text-xs text-muted-foreground">
                                {{ localize('Damage report selection is disabled when there is no damage.', 'يتم تعطيل اختيار تقرير الضرر عند عدم وجود ضرر.') }}
                            </p>
                            <InputError :message="form.errors.damage_report_id" class="mt-1" />
                        </div>
                        <div>
                            <Label for="payment_status">{{ localize('Payment Status', 'حالة الدفع') }}</Label>
                            <select id="payment_status" v-model="form.payment_status" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                                <option value="not_paid">{{ localize('Not Paid', 'غير مدفوعة') }}</option>
                                <option value="paid">{{ localize('Paid', 'مدفوعة') }}</option>
                            </select>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ localize('Set Paid to create the extra payment and lock the report after saving.', 'اختر مدفوعة لإنشاء الدفعة الإضافية وقفل التقرير بعد الحفظ.') }}
                            </p>
                            <InputError :message="form.errors.payment_status" class="mt-1" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Contract vs Return', 'مقارنة العقد مع الإرجاع') }}</CardTitle>
                        <CardDescription>{{ localize('Use this section to compare the original contract values with the actual return values.', 'استخدم هذا القسم لمقارنة قيم العقد الأصلية مع قيم الإرجاع الفعلية.') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="md:col-span-2 xl:col-span-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            <div class="font-medium text-slate-900">{{ localize('Compare the contract odometer and expected return time against the actual return values.', 'قارن عداد العقد ووقت الإرجاع المتوقع مع القيم الفعلية عند الإرجاع.') }}</div>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <div class="text-xs text-muted-foreground">{{ localize('Contract Odometer', 'عداد العقد') }}</div>
                                    <div class="mt-1 text-base font-semibold">{{ contract.vehicle_odometer !== undefined && contract.vehicle_odometer !== null ? contract.vehicle_odometer : '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">{{ localize('Return Odometer', 'عداد الإرجاع') }}</div>
                                    <div class="mt-1 text-base font-semibold">{{ form.return_odometer !== undefined && form.return_odometer !== null && form.return_odometer !== '' ? form.return_odometer : '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">{{ localize('Expected Return Time', 'وقت الإرجاع المتوقع') }}</div>
                                    <div class="mt-1 text-base font-semibold">{{ expectedReturnTimeLabel || '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">{{ localize('Actual Return Time', 'وقت الإرجاع الفعلي') }}</div>
                                    <div class="mt-1 text-base font-semibold">{{ form.actual_return_time || '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">{{ localize('Extra Kilometers', 'الكيلومترات الزائدة') }}</div>
                                    <div class="mt-1 text-base font-semibold">{{ typeof computedExtraKilometers === 'number' && !isNaN(computedExtraKilometers) ? computedExtraKilometers.toFixed(2) : '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">{{ localize('Late Hours', 'ساعات التأخير') }}</div>
                                    <div class="mt-1 text-base font-semibold">{{ typeof lateHours === 'number' && !isNaN(lateHours) ? lateHours.toFixed(2) : '-' }}</div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Extra Charges', 'المصاريف الإضافية') }}</CardTitle>
                        <CardDescription>{{ localize('These charges are auto-calculated from the tenant reservation settings and the actual return data.', 'هذه المصاريف يتم حسابها تلقائيًا من إعدادات الحجز الخاصة بالمستأجر ومن بيانات الإرجاع الفعلية.') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <Label for="extra_kilometers">{{ localize('Extra Kilometers', 'الكيلومترات الإضافية') }}</Label>
                            <Input id="extra_kilometers" v-model="form.extra_kilometers" type="number" min="0" step="0.01" class="mt-1" />
                            <InputError :message="form.errors.extra_kilometers" class="mt-1" />
                        </div>
                        <div>
                            <Label for="kilometer_rate">{{ localize('Kilometer Rate', 'سعر الكيلومتر') }}</Label>
                            <Input id="kilometer_rate" v-model="form.kilometer_rate" type="number" min="0" step="0.01" class="mt-1" />
                            <InputError :message="form.errors.kilometer_rate" class="mt-1" />
                        </div>
                        <div>
                            <Label for="cleaning_fee">{{ localize('Cleaning Fee', 'رسوم التنظيف') }}</Label>
                            <Input id="cleaning_fee" v-model="form.cleaning_fee" type="number" min="0" step="0.01" class="mt-1" />
                            <p v-if="cleaningFeeDescription" class="mt-1 text-xs text-muted-foreground">
                                {{ cleaningFeeDescription }}
                            </p>
                            <InputError :message="form.errors.cleaning_fee" class="mt-1" />
                        </div>
                        <div>
                            <Label for="fuel_fee">{{ localize('Fuel Fee', 'رسوم الوقود') }}</Label>
                            <Input id="fuel_fee" v-model="form.fuel_fee" type="number" min="0" step="0.01" class="mt-1" />
                            <p v-if="fuelComparisonSummary" class="mt-1 text-xs text-muted-foreground">
                                {{ fuelComparisonSummary }}
                            </p>
                            <p v-if="fuelLossDescription" class="mt-1 text-xs text-muted-foreground">
                                {{ fuelLossDescription }}
                            </p>
                            <InputError :message="form.errors.fuel_fee" class="mt-1" />
                        </div>
                        <div>
                            <Label for="fuel_credit">{{ localize('Fuel Credit', 'رصيد البنزين') }}</Label>
                            <Input id="fuel_credit" v-model="form.fuel_credit" type="number" min="0" step="0.01" class="mt-1" />
                            <p v-if="fuelGainLevel" class="mt-1 text-xs text-muted-foreground">
                                {{ localize('Fuel returned higher than pickup, credit applies to the customer.', 'تمت إعادة الوقود أكثر من وقت الاستلام، وسيتم احتساب رصيد لصالح العميل.') }}
                            </p>
                            <InputError :message="form.errors.fuel_credit" class="mt-1" />
                        </div>
                        <div>
                            <Label for="late_hours">{{ localize('Late Hours', 'ساعات التأخير') }}</Label>
                            <Input id="late_hours" v-model="form.late_hours" type="number" min="0" step="0.01" class="mt-1" />
                            <InputError :message="form.errors.late_hours" class="mt-1" />
                        </div>
                        <div>
                            <Label for="late_hour_rate">{{ localize('Late Hour Rate', 'سعر ساعة التأخير') }}</Label>
                            <Input id="late_hour_rate" v-model="form.late_hour_rate" type="number" min="0" step="0.01" class="mt-1" />
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ localize('Default from tenant reservation settings.', 'القيمة الافتراضية من إعدادات الحجز الخاصة بالمستأجر.') }}
                            </p>
                            <InputError :message="form.errors.late_hour_rate" class="mt-1" />
                        </div>
                        <div>
                            <Label for="damage_fee">{{ localize('Damage Fee', 'رسوم الضرر') }}</Label>
                            <Input id="damage_fee" v-model="form.damage_fee" type="number" min="0" step="0.01" class="mt-1" />
                            <p v-if="selectedDamageReport && (selectedDamageReport.after_return_total_estimated_cost !== undefined || selectedDamageReport.total_estimated_cost !== undefined)" class="mt-1 text-xs text-muted-foreground">
                                {{ localize('Selected after-return damage total:', 'إجمالي ضرر بعد التسليم المحدد:') }} ${{ Number(selectedDamageReport.after_return_total_estimated_cost ?? selectedDamageReport.total_estimated_cost ?? 0).toFixed(2) }}
                            </p>
                            <InputError :message="form.errors.damage_fee" class="mt-1" />
                        </div>
                        <div>
                            <Label for="maintenance_fee">{{ localize('Maintenance Fee', 'رسوم الصيانة') }}</Label>
                            <Input id="maintenance_fee" v-model="form.maintenance_fee" type="number" min="0" step="0.01" class="mt-1" />
                            <InputError :message="form.errors.maintenance_fee" class="mt-1" />
                        </div>
                        <div>
                            <Label for="other_fee">{{ localize('Other Fee', 'رسوم أخرى') }}</Label>
                            <Input id="other_fee" v-model="form.other_fee" type="number" min="0" step="0.01" class="mt-1" />
                            <InputError :message="form.errors.other_fee" class="mt-1" />
                        </div>
                        <div>
                            <Label for="discount">{{ localize('Discount', 'الخصم') }}</Label>
                            <Input id="discount" v-model="form.discount" type="number" min="0" step="0.01" class="mt-1" />
                            <p v-if="Number(form.discount || 0) > appliedDiscount" class="mt-1 text-xs text-muted-foreground">
                                {{ localize('Discount is capped at the payable subtotal.', 'يتم تطبيق الخصم حتى قيمة الإجمالي المستحق فقط.') }}
                            </p>
                            <InputError :message="form.errors.discount" class="mt-1" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('After-Return Damage Reports', 'تقارير الضرر بعد التسليم') }}</CardTitle>
                        <CardDescription>{{ localize('Only damage reports created after delivery are used for return charges.', 'يتم استخدام تقارير الضرر التي تم إنشاؤها بعد التسليم فقط لرسوم الإرجاع.') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="afterReturnDamageReports.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                            {{ localize('No after-return damage reports have been created yet for this contract.', 'لم يتم إنشاء أي تقرير ضرر بعد التسليم لهذا العقد بعد.') }}
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full min-w-[760px] border-collapse text-sm">
                                <thead>
                                    <tr class="border-b bg-muted/30 text-left">
                                        <th class="px-3 py-2 font-medium">{{ localize('Report', 'التقرير') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ localize('Items', 'العناصر') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ localize('Estimated Cost', 'التكلفة التقديرية') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ localize('Status', 'الحالة') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ localize('Summary', 'الملخص') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ localize('Actions', 'الإجراءات') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="damageReport in afterReturnDamageReports"
                                        :key="damageReport.id"
                                        class="border-b last:border-0"
                                    >
                                        <td class="px-3 py-3 font-medium">
                                            <div>{{ damageReport.report_number }}</div>
                                            <div class="text-xs text-muted-foreground">
                                                {{ damageReport.report_type || 'after_return' }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-3">
                                            {{ damageReport.items_count }} {{ localize('items', 'عنصر') }}
                                        </td>
                                        <td class="px-3 py-3">
                                            ${{ Number(damageReport.total_estimated_cost).toFixed(2) }}
                                        </td>
                                        <td class="px-3 py-3">
                                            {{ damageReport.status }}
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="max-w-[280px] truncate text-muted-foreground">
                                                {{ damageReport.summary || localize('No summary', 'لا يوجد ملخص') }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="flex flex-wrap gap-2">
                                                <Link :href="damageReport.edit_url">
                                                    <Button variant="outline" size="sm">
                                                        {{ localize('Open', 'فتح') }}
                                                    </Button>
                                                </Link>
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    type="button"
                                                    @click="deleteDamageReport(damageReport)"
                                                >
                                                    {{ localize('Delete', 'حذف') }}
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Final Summary', 'الملخص النهائي') }}</CardTitle>
                        <CardDescription>{{ localize('The report total will be used for the extra cash payment.', 'سيُستخدم إجمالي التقرير كدفعة نقدية إضافية.') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-md border bg-muted/20 p-4">
                            <div class="text-sm text-muted-foreground">{{ localize('Allowed Kilometers', 'الكيلومترات المسموحة') }}</div>
                            <div class="mt-1 text-lg font-semibold">{{ Number(contractMileageAllowance).toFixed(2) }}</div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ props.contract.allowed_km_per_day ? `${props.contract.allowed_km_per_day} / day` : '' }}
                                {{ props.contract.allowed_km_per_week ? `${props.contract.allowed_km_per_week} / week` : '' }}
                                {{ props.contract.allowed_km_per_month ? `${props.contract.allowed_km_per_month} / month` : '' }}
                                <span v-if="lateChargeDays > 0">
                                    · {{ localize('Late extra days:', 'أيام التأخير الإضافية:') }} {{ lateChargeDays }}
                                </span>
                            </div>
                        </div>
                        <div class="rounded-md border bg-muted/20 p-4">
                            <div class="text-sm text-muted-foreground">{{ localize('Actual Kilometers Driven', 'الكيلومترات الفعلية المقطوعة') }}</div>
                            <div class="mt-1 text-lg font-semibold">{{ Number(actualKilometersDriven).toFixed(2) }}</div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ localize('Contract odometer to return odometer', 'من عداد العقد إلى عداد الإرجاع') }}
                            </div>
                        </div>
                        <div class="rounded-md border bg-muted/20 p-4">
                            <div class="text-sm text-muted-foreground">{{ localize('Extra Kilometer Charges', 'رسوم الكيلومترات الإضافية') }}</div>
                            <div class="mt-1 text-lg font-semibold">${{ Number(extraKilometerCharges).toFixed(2) }}</div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ localize('Extra kilometers after allowance', 'الكيلومترات الزائدة بعد السماح') }}: {{ Number(form.extra_kilometers || 0).toFixed(2) }}
                            </div>
                        </div>
                        <div class="rounded-md border bg-muted/20 p-4">
                            <div class="text-sm text-muted-foreground">{{ localize('Cleaning Fee', 'رسوم التنظيف') }}</div>
                            <div class="mt-1 text-lg font-semibold">${{ Number(form.cleaning_fee || 0).toFixed(2) }}</div>
                        </div>
                        <div class="rounded-md border bg-muted/20 p-4">
                            <div class="text-sm text-muted-foreground">{{ localize('Fuel Fee', 'رسوم البنزين') }}</div>
                            <div class="mt-1 text-lg font-semibold">${{ Number(form.fuel_fee || 0).toFixed(2) }}</div>
                            <div v-if="fuelLossDescription" class="mt-1 text-xs text-muted-foreground">
                                {{ fuelLossDescription }}
                            </div>
                            <div v-if="Number(form.fuel_credit || 0) > 0" class="mt-2 text-sm text-emerald-600">
                                - ${{ Number(form.fuel_credit || 0).toFixed(2) }} {{ localize('Fuel credit for customer', 'رصيد البنزين لصالح العميل') }}
                            </div>
                        </div>
                        <div class="rounded-md border bg-muted/20 p-4">
                            <div class="text-sm text-muted-foreground">{{ localize('Late Return Fee', 'رسوم التأخير') }}</div>
                            <div class="mt-1 text-lg font-semibold">${{ Number(lateFeePreview).toFixed(2) }}</div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ form.late_hours }} {{ localize('hours', 'ساعات') }}
                            </div>
                            <div v-if="lateFeeBreakdown" class="mt-1 text-xs text-muted-foreground">
                                {{ lateFeeBreakdown }}
                            </div>
                        </div>
                        <div class="rounded-md border bg-muted/20 p-4">
                            <div class="text-sm text-muted-foreground">{{ localize('Damage Fee', 'رسوم الضرر') }}</div>
                            <div class="mt-1 text-lg font-semibold">${{ Number(form.damage_fee || 0).toFixed(2) }}</div>
                        </div>
                        <div class="rounded-md border bg-muted/20 p-4">
                            <div class="text-sm text-muted-foreground">{{ localize('Maintenance Fee', 'رسوم الصيانة') }}</div>
                            <div class="mt-1 text-lg font-semibold">${{ Number(form.maintenance_fee || 0).toFixed(2) }}</div>
                        </div>
                        <div class="rounded-md border bg-muted/20 p-4">
                            <div class="text-sm text-muted-foreground">{{ localize('Other Fee', 'رسوم أخرى') }}</div>
                            <div class="mt-1 text-lg font-semibold">${{ Number(form.other_fee || 0).toFixed(2) }}</div>
                        </div>
                        <div class="rounded-md border bg-muted/20 p-4">
                            <div class="text-sm text-muted-foreground">{{ localize('Discount', 'الخصم') }}</div>
                            <div class="mt-1 text-lg font-semibold text-emerald-600">-${{ Number(appliedDiscount).toFixed(2) }}</div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ localize('Applied after all charges and credits.', 'يطبق بعد كل الرسوم والأرصدة.') }}
                            </div>
                        </div>
                        <div class="rounded-md border border-primary/30 bg-primary/5 p-4">
                            <div class="text-sm text-primary">{{ localize('Total Extra Charges', 'إجمالي الرسوم الإضافية') }}</div>
                            <div class="mt-1 text-2xl font-bold text-primary">${{ Number(totalExtraCharges).toFixed(2) }}</div>
                            <div v-if="Number(form.fuel_credit || 0) > 0" class="mt-1 text-xs text-muted-foreground">
                                {{ localize('Fuel credit deducted from the total.', 'تم خصم رصيد البنزين من الإجمالي.') }}
                            </div>
                            <div v-if="Number(totalExtraCharges) < 0" class="mt-1 text-xs font-semibold text-emerald-600">
                                {{ localize('Credit due to customer', 'الرصيد المستحق للعميل') }}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Notes', 'ملاحظات') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Textarea v-model="form.notes" rows="4" />
                        <InputError :message="form.errors.notes" class="mt-1" />
                    </CardContent>
                </Card>

                </fieldset>
                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing || !canEditReturnReport">
                        {{ form.processing ? localize('Saving...', 'جاري الحفظ...') : localize('Save Return Report', 'حفظ تقرير الإرجاع') }}
                    </Button>
                    <Link :href="actions.index">
                        <Button variant="outline" type="button">{{ localize('Cancel', 'إلغاء') }}</Button>
                    </Link>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
