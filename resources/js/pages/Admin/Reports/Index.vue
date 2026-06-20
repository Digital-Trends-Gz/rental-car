<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import { Chart, registerables } from 'chart.js';
import { computed, onMounted, ref, watch } from 'vue';
import { useTrans } from '@/composables/useTrans';
import { index as reportsIndex } from '@/routes/admin/reports';

const activeAlertDetails = ref<any>(null);
const isModalOpen = ref(false);

const openAlertDetails = (alert: any) => {
    if (alert.value > 0 && alert.items && alert.items.length > 0) {
        activeAlertDetails.value = alert;
        isModalOpen.value = true;
    }
};

Chart.register(...registerables);

// Types
interface KPI {
    value: number;
    formatted: string;
    label: string;
}

interface CarState {
    value: number;
    formatted: string;
    label: string;
    color: string;
}

interface ChartData {
    labels: string[];
    datasets: Array<{
        label: string;
        data: number[];
        backgroundColor: string;
        borderColor: string;
        borderWidth: number;
    }>;
    dailyTotals: number[];
    statusColors: Record<string, string>;
    statusLabels: Record<string, string>;
    dateRange: {
        start: string;
        end: string;
    };
}

interface CarPerformance {
    id: number;
    car_name: string;
    license_plate: string;
    status: string;
    status_color: string;
    total_reservations: number;
    total_revenue: number;
    formatted_revenue: string;
    total_days: number;
    utilization_rate: number;
    average_per_reservation: number;
}

interface PeriodOption {
    value: string;
    label: string;
}

interface ReportMetric {
    label: string;
    value: number;
    formatted: string;
    color: string;
}

interface ReportAlert {
    key: string;
    label: string;
    label_ar?: string;
    description: string;
    description_ar?: string;
    value: number;
    severity: 'danger' | 'warning' | 'info' | 'success';
    formatted_amount?: string;
    href?: string;
    items?: any[];
}

interface ExecutiveReport {
    financial: ReportMetric[];
    operations: ReportMetric[];
    alerts: ReportAlert[];
    exports?: {
        pdf?: string | false;
        excel?: string | false;
    };
}

interface FinancialReportSection {
    title: {
        en: string;
        ar: string;
    };
    items: Array<{
        en: string;
        ar: string;
        formatted?: string;
        count?: number;
    }>;
}

interface PageProps {
    kpis: {
        totalRevenue: KPI;
        platformVisits: KPI;
        activeReservations: KPI;
        newClients: KPI;
    };
    carsState: {
        totalCars: CarState;
        availableCars: CarState;
        rentedCars: CarState;
        unavailableCars: CarState;
    };
    reservationsChart: ChartData;
    carsPerformance: CarPerformance[];
    financialSummary: ReportMetric[];
    financialReportSections: FinancialReportSection[];
    financialAlerts: ReportAlert[];
    operationsSummary: ReportMetric[];
    fleetInsights: ReportMetric[];
    reservationsReport?: any;
    reservationsReportExports?: ReportExportUrls;
    actionAlerts: ReportAlert[];
    executiveReport?: ExecutiveReport;
    currentPeriod: string;
    periodOptions: PeriodOption[];
    branches: Array<{ id: number; name: string }>;
    canAccessAllBranches: boolean;
    selectedBranchId: number | null;
    canViewFinancials: boolean;
}

const page = usePage<PageProps>();
const rawPage = usePage<any>();
const { t, locale } = useTrans();

const localize = (en: string, ar: string, ur: string) => {
    if (locale.value === 'ar') return ar;
    if (locale.value === 'ur') return ur;
    return en;
};

const translateLabel = (label: string) => {
    const normalized = label.trim().toLowerCase();

    const labels: Record<string, string> = {
        'all branches': localize('All branches', 'كل الفروع', 'تمام شاخیں'),
        'this month': localize('This Month', 'هذا الشهر', 'اس مہینے'),
        'last month': localize('Last Month', 'الشهر الماضي', 'پچھلے مہینے'),
        'this year': localize('This Year', 'هذه السنة', 'اس سال'),
        'last year': localize('Last Year', 'السنة الماضية', 'گزشتہ سال'),
        'new clients': localize('New Clients', 'عملاء جدد', 'نئے کلائنٹس'),
        'active reservations': localize('Active Reservations', 'الحجوزات النشطة', 'فعال بکنگز'),
        'platform visits': localize('Platform Visits', 'زيارات المنصة', 'پلیٹ فارم وزٹس'),
        'total revenue': localize('Total Revenue', 'إجمالي الإيرادات', 'کل آمدنی'),
        'total cars': localize('Total Cars', 'إجمالي السيارات', 'کل گاڑیاں'),
        'available cars': localize('Available Cars', 'السيارات المتاحة', 'دستیاب گاڑیاں'),
        'rented cars': localize('Rented Cars', 'السيارات المؤجرة', 'کرائے پر گاڑیاں'),
        'unavailable cars': localize('Unavailable Cars', 'السيارات غير المتاحة', 'غیر دستیاب گاڑیاں'),
        pending: localize('Pending', 'قيد الانتظار', 'زیر التواء'),
        confirmed: localize('Confirmed', 'مؤكد', 'تصدیق شدہ'),
        active: localize('Active', 'نشط', 'فعال'),
        completed: localize('Completed', 'مكتمل', 'مکمل'),
        cancelled: localize('Cancelled', 'ملغي', 'منسوخ'),
        'no show': localize('No show', 'لم يحضر', 'حاضر نہیں ہوا'),
    };

    return labels[normalized] ?? label;
};
const selectedPeriod = ref(page.props.currentPeriod);
const selectedBranchId = ref<number | null>(page.props.selectedBranchId ?? null);
const subdomain = computed(() => rawPage.props.current_tenant?.slug);
const reservationChart = ref<Chart | null>(null);
const chartCanvas = ref<HTMLCanvasElement | null>(null);
const hasFinancialAccess = computed(() => !!page.props.canViewFinancials);

// Table sorting
const sortField = ref<keyof CarPerformance>('total_revenue');
const sortDirection = ref<'asc' | 'desc'>('desc');

// Computed properties for easier access
const kpis = computed(() => page.props.kpis);
const carsState = computed(() => page.props.carsState);
const chartData = computed(() => page.props.reservationsChart);
const periodOptions = computed(() => page.props.periodOptions);
const branches = computed(() => page.props.branches || []);
const canAccessAllBranches = computed(() => !!page.props.canAccessAllBranches);
const localizedPeriodOptions = computed(() =>
    periodOptions.value.map((option) => ({
        ...option,
        label: translateLabel(option.label),
    })),
);
const localizedChartDatasets = computed(() =>
    (chartData.value?.datasets ?? []).map((dataset) => ({
        ...dataset,
        label: translateLabel(dataset.label),
    })),
);
const financialSummary = computed(() => page.props.financialSummary ?? []);
const financialReportSections = computed(() => page.props.financialReportSections ?? []);
const financialAlerts = computed(() => page.props.financialAlerts ?? []);
const financialReportExports = computed(() => page.props.financialReportExports);
const isArabic = computed(() => locale.value === 'ar');
const financialSectionTitle = (section: FinancialReportSection): string => isArabic.value ? section.title.ar : section.title.en;
const financialItemTitle = (item: FinancialReportSection['items'][number]): string => isArabic.value ? item.ar : item.en;
const financialRecordLabel = (count?: number): string => {
    const safeCount = Number(count ?? 0);

    return isArabic.value ? `${safeCount} سجلات` : `${safeCount} records`;
};
const reservationsReport = computed(() => page.props.reservationsReport);
const reservationsReportExports = computed(() => page.props.reservationsReportExports);
const formatFinancialAmount = (value?: number | null): string => {
    return value ? value.toLocaleString() : '0';
};
const operationsSummary = computed(() => page.props.operationsSummary ?? []);
const fleetInsights = computed(() => page.props.fleetInsights ?? []);
const actionAlerts = computed(() => page.props.actionAlerts ?? []);
const fleetReport = computed(() => page.props.fleetReport);
const fleetReportExports = computed(() => page.props.fleetReportExports);
const showCarRankingsModal = ref(false);
const executiveReport = computed<ExecutiveReport>(() => page.props.executiveReport ?? {
    financial: financialSummary.value,
    operations: operationsSummary.value,
    alerts: actionAlerts.value,
    exports: { pdf: false, excel: false },
});

const reportLabel = (label: string) => {
    const normalized = label.trim().toLowerCase();
    const executiveLabels: Record<string, string> = {
        'executive report': localize('Executive Report', 'التقرير التنفيذي', 'ایگزیکٹو رپورٹ'),
        'most important': localize('Most important', 'الأهم', 'سب سے اہم'),
        'total revenue': localize('Total revenue', 'إجمالي الإيرادات', 'کل آمدنی'),
        'uncollected amounts': localize('Uncollected amounts', 'المبالغ غير المحصلة', 'غیر وصول شدہ رقوم'),
        'outstanding debts': localize('Outstanding debts', 'الديون المستحقة', 'واجب الادا قرض'),
        'late fees': localize('Late fees', 'رسوم التأخير', 'تاخیر فیس'),
        'cleaning fees': localize('Cleaning fees', 'رسوم التنظيف', 'صفائی فیس'),
        'net revenue': localize('Net revenue', 'صافي الإيرادات', 'خالص آمدنی'),
        'delivered cars': localize('Delivered cars', 'السيارات المسلمة', 'حوالہ کی گئی گاڑیاں'),
        'returned cars': localize('Returned cars', 'السيارات المستلمة', 'واپس لی گئی گاڑیاں'),
        'cars out of service': localize('Cars out of service', 'السيارات خارج الخدمة', 'سروس سے باہر گاڑیاں'),
        'contracts ending within 24 hours': localize('Contracts ending within 24 hours', 'عقود تنتهي خلال 24 ساعة', '24 گھنٹوں میں ختم ہونے والے معاہدے'),
        'missing payments': localize('Missing payments', 'مدفوعات ناقصة', 'نامکمل ادائیگیاں'),
        'missing documents': localize('Missing documents', 'وثائق ناقصة', 'نامکمل دستاویزات'),
        'contracts without signature': localize('Contracts without signature', 'عقود بدون توقيع', 'بغیر دستخط معاہدے'),
    };

    if (executiveLabels[normalized]) {
        return executiveLabels[normalized];
    }

    const labels: Record<string, string> = {
        'financial summary': localize('Financial Summary', 'الملخص المالي', 'مالی خلاصہ'),
        'operations summary': localize('Operations Summary', 'ملخص العمليات', 'آپریشنز خلاصہ'),
        'fleet insights': localize('Fleet Insights', 'تحليل الأسطول', 'فلیٹ تجزیہ'),
        'action alerts': localize('Action Alerts', 'تنبيهات تحتاج إجراء', 'عملی انتباہات'),
        'paid revenue': localize('Paid revenue', 'الإيرادات المدفوعة', 'ادا شدہ آمدنی'),
        'pending payments': localize('Pending payments', 'مدفوعات معلقة', 'زیر التواء ادائیگیاں'),
        'return extra charges': localize('Return extra charges', 'رسوم الرجوع الإضافية', 'واپسی اضافی چارجز'),
        'damage fees': localize('Damage fees', 'رسوم الأضرار', 'نقصان فیس'),
        'fuel fees': localize('Fuel fees', 'رسوم الوقود', 'ایندھن فیس'),
        discounts: localize('Discounts', 'الخصومات', 'رعایتیں'),
        'active contracts': localize('Active contracts', 'العقود النشطة', 'فعال معاہدے'),
        'pending contracts': localize('Pending contracts', 'عقود بانتظار التسليم', 'زیر التواء معاہدے'),
        'completed contracts': localize('Completed contracts', 'العقود المكتملة', 'مکمل معاہدے'),
        'overdue contracts': localize('Overdue contracts', 'العقود المتأخرة', 'تاخیر شدہ معاہدے'),
        'new reservations': localize('New reservations', 'الحجوزات الجديدة', 'نئی بکنگز'),
        'cancelled reservations': localize('Cancelled reservations', 'الحجوزات الملغاة', 'منسوخ بکنگز'),
        'fleet utilization': localize('Fleet utilization', 'نسبة تشغيل الأسطول', 'فلیٹ استعمال'),
        'revenue per car': localize('Revenue per car', 'الإيراد لكل سيارة', 'فی گاڑی آمدنی'),
        'damage reports': localize('Damage reports', 'تقارير الأضرار', 'نقصان رپورٹس'),
        'damage items': localize('Damage items', 'بنود الأضرار', 'نقصان آئٹمز'),
        'estimated damage cost': localize('Estimated damage cost', 'تكلفة الأضرار التقديرية', 'متوقع نقصان لاگت'),
        'accident reports': localize('Accident reports', 'بلاغات الحوادث', 'حادثہ رپورٹس'),
        'overdue cars': localize('Overdue cars', 'سيارات متأخرة', 'تاخیر شدہ گاڑیاں'),
        'returns due today': localize('Returns due today', 'مرتجعات اليوم', 'آج واپسی'),
        'pending violations': localize('Pending violations', 'مخالفات معلقة', 'زیر التواء خلاف ورزیاں'),
        'unpaid return reports': localize('Unpaid return reports', 'تقارير رجوع غير مدفوعة', 'غیر ادا شدہ واپسی رپورٹس'),
        'draft damage reports': localize('Draft damage reports', 'تقارير أضرار مسودة', 'ڈرافٹ نقصان رپورٹس'),
        'outstanding return charges': localize('Outstanding return charges', 'رسوم الرجوع غير المسددة', 'غیر ادا شدہ واپسی چارجز'),
        'discounts applied': localize('Discounts applied', 'الخصومات المطبقة', 'لاگو کردہ رعایتیں'),
    };

    return labels[normalized] ?? label;
};

const reportDescription = (description: string) => {
    const normalized = description.trim().toLowerCase();
    const executiveDescriptions: Record<string, string> = {
        'active contracts that need return follow-up soon.': localize(
            'Active contracts that need return follow-up soon.',
            'عقود نشطة تحتاج متابعة الرجوع قريباً.',
            'فعال معاہدے جن کی واپسی جلد فالو اپ چاہیے۔',
        ),
        'payments or return charges that still need collection.': localize(
            'Payments or return charges that still need collection.',
            'مدفوعات أو رسوم رجوع ما زالت بحاجة إلى تحصيل.',
            'ادائیگیاں یا واپسی چارجز جن کی وصولی باقی ہے۔',
        ),
        'active or pending contracts without primary driver documents.': localize(
            'Active or pending contracts without primary driver documents.',
            'عقود نشطة أو معلقة بدون وثائق السائق الأساسي.',
            'فعال یا زیر التوا معاہدے جن میں مرکزی ڈرائیور کی دستاویزات نہیں۔',
        ),
        'contracts that still need mobile terms confirmation.': localize(
            'Contracts that still need mobile terms confirmation.',
            'عقود ما زالت تحتاج تأكيد الشروط من الموبايل.',
            'معاہدے جنہیں موبائل شرائط کی تصدیق ابھی چاہیے۔',
        ),
    };

    if (executiveDescriptions[normalized]) {
        return executiveDescriptions[normalized];
    }

    const descriptions: Record<string, string> = {
        'active contracts past their return date.': localize(
            'Active contracts past their return date.',
            'عقود نشطة تجاوزت تاريخ الرجوع.',
            'فعال معاہدے جن کی واپسی کی تاریخ گزر گئی۔',
        ),
        'active contracts scheduled to return today.': localize(
            'Active contracts scheduled to return today.',
            'عقود نشطة موعد رجوعها اليوم.',
            'فعال معاہدے جو آج واپس ہونے ہیں۔',
        ),
        'violations that still need review or payment.': localize(
            'Violations that still need review or payment.',
            'مخالفات تحتاج مراجعة أو دفع.',
            'خلاف ورزیاں جنہیں جائزہ یا ادائیگی درکار ہے۔',
        ),
        'return reports with outstanding extra charges.': localize(
            'Return reports with outstanding extra charges.',
            'تقارير رجوع عليها رسوم إضافية غير مدفوعة.',
            'واپسی رپورٹس جن پر اضافی چارجز باقی ہیں۔',
        ),
        'damage reports waiting for review or completion.': localize(
            'Damage reports waiting for review or completion.',
            'تقارير أضرار بانتظار المراجعة أو الإكمال.',
            'نقصان رپورٹس جو جائزہ یا تکمیل کی منتظر ہیں۔',
        ),
    };

    return descriptions[normalized] ?? description;
};

const severityClasses = (severity: ReportAlert['severity']) => {
    const classes = {
        danger: 'border-red-200 bg-red-50 text-red-700',
        warning: 'border-amber-200 bg-amber-50 text-amber-700',
        info: 'border-blue-200 bg-blue-50 text-blue-700',
        success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    };

    return classes[severity] ?? classes.info;
};

const displayMoney = (value: number) =>
    hasFinancialAccess.value ? `$${value.toFixed(2)}` : '*******';

// Sorted and limited cars performance
const sortedCarsPerformance = computed(() => {
    const sorted = [...page.props.carsPerformance].sort((a, b) => {
        const aValue = a[sortField.value];
        const bValue = b[sortField.value];

        if (typeof aValue === 'string' && typeof bValue === 'string') {
            return sortDirection.value === 'asc'
                ? aValue.localeCompare(bValue)
                : bValue.localeCompare(aValue);
        }

        const numA = Number(aValue);
        const numB = Number(bValue);

        return sortDirection.value === 'asc' ? numA - numB : numB - numA;
    });

    return sorted.slice(0, 10); // Limit to 10 cars
});

// Handle period change
const handlePeriodChange = () => {
    if (!subdomain.value) return;
    router.get(
        reportsIndex(subdomain.value).url,
        { period: selectedPeriod.value, branch_id: selectedBranchId.value },
        {
            preserveState: false,
            preserveScroll: false,
            only: [
                'kpis',
                'carsState',
                'reservationsChart',
                'carsPerformance',
                'financialSummary',
                'financialReportSections',
                'financialReportExports',
                'financialAlerts',
                'reservationsReport',
                'reservationsReportExports',
                'operationsSummary',
                'fleetInsights',
                'actionAlerts',
                'executiveReport',
                'currentPeriod',
                'selectedBranchId',
            ],
        },
    );
};

const handleBranchChange = () => {
    handlePeriodChange();
};

// Handle table sorting
const sortTable = (field: keyof CarPerformance) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'desc';
    }
};

// Create reservations chart as stacked bar chart
const createReservationsChart = () => {
    if (!chartCanvas.value || !chartData.value) return;

    // Destroy existing chart
    if (reservationChart.value) {
        reservationChart.value.destroy();
    }

    const ctx = chartCanvas.value.getContext('2d');
    if (!ctx) return;

    reservationChart.value = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.value.labels,
            datasets: localizedChartDatasets.value,
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        pointStyle: 'rect',
                    },
                },

                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: 'white',
                    bodyColor: 'white',
                    borderColor: 'rgba(255, 255, 255, 0.2)',
                    borderWidth: 1,
                    callbacks: {
                        title: function (tooltipItems) {
                            return `${t('dashboard.common.date')}: ${tooltipItems[0].label}`;
                        },
                        afterBody: function (tooltipItems) {
                            const dayIndex = tooltipItems[0].dataIndex;
                            const total = chartData.value.dailyTotals[dayIndex];
                            return [``, `${t('dashboard.admin.reports.total_reservations')}: ${total}`];
                        },
                        label: function (context) {
                            const label = context.dataset.label || '';
                            const value = context.parsed.y;
                            return `${label}: ${value}`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    stacked: true,
                    grid: {
                        display: false,
                    },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 0,
                    },
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        callback: function (value) {
                            if (Number.isInteger(value)) {
                                return value;
                            }
                            return '';
                        },
                    },
                    title: {
                        display: true,
                        text: t('dashboard.admin.reports.number_of_reservations'),
                        font: {
                            size: 12,
                            weight: 'bold',
                        },
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)',
                    },
                },
            },
            interaction: {
                intersect: false,
                mode: 'index',
            },
            animation: {
                duration: 1000,
                easing: 'easeInOutQuart',
            },
            elements: {
                bar: {
                    borderRadius: 2,
                    borderSkipped: false,
                },
            },
        },
    });
};

// Watch for data changes to recreate chart
watch(() => [chartData.value, selectedPeriod.value, locale.value], createReservationsChart, { deep: true });

// Watch for period changes in props
watch(
    () => page.props.currentPeriod,
    (newPeriod) => {
        selectedPeriod.value = newPeriod;
    },
);
watch(
    () => page.props.selectedBranchId,
    (newBranchId) => {
        selectedBranchId.value = newBranchId ?? null;
    },
);

onMounted(() => {
    createReservationsChart();
});
</script>

<template>
    <AdminLayout>
        <div class="space-y-6 px-8 py-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold text-gray-900">
                    {{ t('dashboard.admin.reports.title') }}
                </h2>

                <!-- Period Selector -->
                <div class="flex items-center space-x-2">
                    <template v-if="canAccessAllBranches">
                        <label
                            for="branch"
                            class="text-sm font-medium text-gray-700"
                        >
                            {{ t('dashboard.admin.employees.table.branch') }}:
                        </label>
                        <select
                            id="branch"
                            v-model="selectedBranchId"
                            @change="handleBranchChange"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        >
                            <option :value="null">{{ translateLabel('All branches') }}</option>
                            <option
                                v-for="branch in branches"
                                :key="branch.id"
                                :value="branch.id"
                            >
                                {{ branch.name }}
                            </option>
                        </select>
                    </template>
                    <label
                        for="period"
                        class="text-sm font-medium text-gray-700"
                    >
                        {{ t('dashboard.admin.reports.period') }}:
                    </label>
                    <select
                        id="period"
                        v-model="selectedPeriod"
                        @change="handlePeriodChange"
                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                        <option
                            v-for="option in localizedPeriodOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- High-level KPIs -->
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Total Revenue -->
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-md bg-green-500"
                                >
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"
                                        ></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt
                                        class="truncate text-sm font-medium text-gray-500"
                                    >
                                        {{ translateLabel(kpis.totalRevenue.label) }}
                                    </dt>
                                    <dd
                                        class="text-lg font-medium text-gray-900"
                                    >
                                        {{ kpis.totalRevenue.formatted }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Platform Visits -->
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-md bg-blue-500"
                                >
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                        ></path>
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                        ></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt
                                        class="truncate text-sm font-medium text-gray-500"
                                    >
                                        {{ translateLabel(kpis.platformVisits.label) }}
                                    </dt>
                                    <dd
                                        class="text-lg font-medium text-gray-900"
                                    >
                                        {{ kpis.platformVisits.formatted }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Reservations -->
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-md bg-yellow-500"
                                >
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                        ></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt
                                        class="truncate text-sm font-medium text-gray-500"
                                    >
                                        {{ translateLabel(kpis.activeReservations.label) }}
                                    </dt>
                                    <dd
                                        class="text-lg font-medium text-gray-900"
                                    >
                                        {{ kpis.activeReservations.formatted }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- New Clients -->
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-md bg-purple-500"
                                >
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                                        ></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt
                                        class="truncate text-sm font-medium text-gray-500"
                                    >
                                        {{ translateLabel(kpis.newClients.label) }}
                                    </dt>
                                    <dd
                                        class="text-lg font-medium text-gray-900"
                                    >
                                        {{ kpis.newClients.formatted }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cars State -->
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Total Cars -->
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-md"
                                    :style="{
                                        backgroundColor:
                                            carsState.totalCars.color,
                                    }"
                                >
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                                        ></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt
                                        class="truncate text-sm font-medium text-gray-500"
                                    >
                                        {{ translateLabel(carsState.totalCars.label) }}
                                    </dt>
                                    <dd
                                        class="text-lg font-medium text-gray-900"
                                    >
                                        {{ carsState.totalCars.formatted }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Available Cars -->
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-md"
                                    :style="{
                                        backgroundColor:
                                            carsState.availableCars.color,
                                    }"
                                >
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M5 13l4 4L19 7"
                                        ></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt
                                        class="truncate text-sm font-medium text-gray-500"
                                    >
                                        {{ translateLabel(carsState.availableCars.label) }}
                                    </dt>
                                    <dd
                                        class="text-lg font-medium text-gray-900"
                                    >
                                        {{ carsState.availableCars.formatted }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rented Cars -->
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-md"
                                    :style="{
                                        backgroundColor:
                                            carsState.rentedCars.color,
                                    }"
                                >
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                        ></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt
                                        class="truncate text-sm font-medium text-gray-500"
                                    >
                                        {{ translateLabel(carsState.rentedCars.label) }}
                                    </dt>
                                    <dd
                                        class="text-lg font-medium text-gray-900"
                                    >
                                        {{ carsState.rentedCars.formatted }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Unavailable Cars -->
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-md"
                                    :style="{
                                        backgroundColor:
                                            carsState.unavailableCars.color,
                                    }"
                                >
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L5.636 5.636"
                                        ></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt
                                        class="truncate text-sm font-medium text-gray-500"
                                    >
                                        {{ translateLabel(carsState.unavailableCars.label) }}
                                    </dt>
                                    <dd
                                        class="text-lg font-medium text-gray-900"
                                    >
                                        {{
                                            carsState.unavailableCars.formatted
                                        }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Executive Report -->
            <section class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow">
                <div class="border-b border-blue-100 bg-gradient-to-r from-blue-50 to-white px-6 py-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-blue-100 px-4 py-2 text-sm font-bold text-blue-800">
                                {{ reportLabel('Most important') }}
                            </span>
                            <h2 class="text-2xl font-bold text-blue-900">
                                1. {{ reportLabel('Executive Report') }}
                            </h2>
                        </div>
                        <p class="max-w-2xl text-sm leading-6 text-gray-600">
                            {{
                                localize(
                                    'A daily or weekly owner-level report that summarizes money, operations, and urgent follow-up items.',
                                    'تقرير يومي أو أسبوعي لصاحب المكتب يلخص المال والتشغيل والتنبيهات التي تحتاج متابعة.',
                                    'مالک کے لیے روزانہ یا ہفتہ وار رپورٹ جو مالیات، آپریشنز اور فوری فالو اپ کو خلاصہ کرتی ہے۔',
                                )
                            }}
                        </p>
                    </div>
                </div>

              

                <div class="grid grid-cols-1 gap-6 border-t border-slate-200 bg-slate-50 p-6 xl:grid-cols-[1.4fr_1fr_1fr]">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-xl font-bold text-gray-900">
                            {{ reportLabel('Financial Summary') }}
                        </h3>
                        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div
                                v-for="metric in executiveReport.financial"
                                :key="metric.label"
                                class="rounded-lg border border-white bg-white p-4 shadow-sm"
                            >
                                <div class="mb-3 h-1.5 rounded-full" :style="{ backgroundColor: metric.color }"></div>
                                <p class="text-sm font-semibold text-gray-500">
                                    {{ reportLabel(metric.label) }}
                                </p>
                                <p class="mt-2 text-2xl font-bold text-gray-950">
                                    {{ metric.formatted }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-xl font-bold text-gray-900">
                            {{ reportLabel('Operations Summary') }}
                        </h3>
                        <div class="mt-5 space-y-3">
                            <div
                                v-for="metric in executiveReport.operations"
                                :key="metric.label"
                                class="flex items-center justify-between gap-4 rounded-lg border border-gray-100 px-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: metric.color }"></span>
                                    <span class="text-sm font-semibold text-gray-600">
                                        {{ reportLabel(metric.label) }}
                                    </span>
                                </div>
                                <span class="text-lg font-bold text-gray-950">
                                    {{ metric.formatted }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-xl font-bold text-gray-900">
                            {{ reportLabel('Action Alerts') }}
                        </h3>
                        <div class="mt-5 space-y-3">
                            <a
                                v-for="alert in executiveReport.alerts"
                                :key="alert.key"
                                :href="alert.href || '#'"
                                @click.prevent="openAlertDetails(alert)"
                                class="block rounded-lg border p-4 transition-all duration-200"
                                :class="[severityClasses(alert.severity), alert.value > 0 ? 'cursor-pointer hover:shadow-md hover:scale-[1.01] transform' : 'cursor-default opacity-80']"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-bold">
                                            {{ reportLabel(alert.label) }}
                                        </p>
                                        <p class="mt-1 text-sm opacity-80">
                                            {{ reportDescription(alert.description) }}
                                        </p>
                                        <p
                                            v-if="alert.formatted_amount"
                                            class="mt-2 text-sm font-semibold"
                                        >
                                            {{ alert.formatted_amount }}
                                        </p>
                                    </div>
                                    <span class="rounded-full bg-white/70 px-3 py-1 text-sm font-bold">
                                        {{ alert.value }}
                                    </span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="mx-6 mb-6 flex items-center justify-between rounded-xl border border-blue-100 bg-blue-50 px-5 py-4">
                    <p class="text-sm font-semibold text-blue-900">
                        {{
                            localize(
                                'Download the executive report using the same period and branch filters.',
                                'نزّل التقرير التنفيذي بنفس فلتر الفترة والفرع الحالي.',
                                'ایگزیکٹو رپورٹ اسی مدت اور برانچ فلٹر کے ساتھ ڈاؤن لوڈ کریں۔',
                            )
                        }}
                    </p>
                    <div class="flex gap-2">
                        <a
                            v-if="executiveReport.exports?.pdf"
                            :href="executiveReport.exports.pdf"
                            class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800"
                        >
                            PDF
                        </a>
                        <a
                            v-if="executiveReport.exports?.excel"
                            :href="executiveReport.exports.excel"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700"
                        >
                            Excel
                        </a>
                    </div>
                </div>
            </section>
                  <section class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow">
                <div class="border-b border-blue-100 bg-gradient-to-r from-blue-50 to-white px-6 py-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-blue-100 px-4 py-2 text-sm font-bold text-blue-800">
                                {{ reportLabel('Most important') }}
                            </span>
                            <h2 class="text-2xl font-bold text-blue-900">
                                2.  {{ localize('Financial Report', 'التقرير المالي', 'مالی رپورٹ') }}
                            </h2>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <a
                                v-if="financialReportExports?.pdf"
                                :href="financialReportExports.pdf"
                                target="_blank"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-all hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                            >
                                <svg class="h-5 w-5 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                {{ localize('Export PDF', 'تصدير PDF', 'پی ڈی ایف ایکسپورٹ') }}
                            </a>
                            <a
                                v-if="financialReportExports?.excel"
                                :href="financialReportExports.excel"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-all hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                            >
                                <svg class="h-5 w-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                {{ localize('Export Excel', 'تصدير Excel', 'ایکسل ایکسپورٹ') }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="px-6 pt-6">
                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-blue-50/40">
                        <div class="grid gap-5 p-6 lg:grid-cols-2">
                            <div
                                v-for="section in financialReportSections"
                                :key="section.title.en"
                                class="group rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm ring-1 ring-transparent transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-lg hover:ring-blue-100"
                                :dir="isArabic ? 'rtl' : 'ltr'"
                            >
                                <div class="mb-5 flex items-center justify-between gap-4 border-b border-slate-100 pb-4">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-50 text-blue-700 ring-1 ring-blue-100 transition group-hover:bg-blue-600 group-hover:text-white">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 10v-1m8-4a8 8 0 11-16 0 8 8 0 0116 0z" />
                                            </svg>
                                        </span>
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                                {{ localize('Financial section', 'قسم مالي', 'Financial section') }}
                                            </p>
                                            <h4 class="text-xl font-extrabold text-slate-950">
                                                {{ financialSectionTitle(section) }}
                                            </h4>
                                        </div>
                                    </div>
                                </div>

                                <ul class="mt-4 space-y-4">
                                    <li
                                        v-for="item in section.items"
                                        :key="item.en"
                                        class="flex items-center justify-between gap-4 rounded-2xl border border-slate-100 bg-slate-50/80 px-4 py-3 transition hover:border-blue-100 hover:bg-blue-50/60"
                                    >
                                        <div class="flex min-w-0 items-center gap-3">
                                            <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-blue-500 shadow-[0_0_0_4px_rgba(59,130,246,0.12)]"></span>
                                            <div class="min-w-0">
                                                <span class="block truncate text-base font-bold text-slate-900">
                                                    {{ financialItemTitle(item) }}
                                                </span>
                                                <span class="mt-0.5 block text-xs font-medium text-slate-500">
                                                    {{ financialRecordLabel(item.count) }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="shrink-0 rounded-xl bg-white px-3 py-2 text-end shadow-sm ring-1 ring-slate-100">
                                            <p class="font-mono text-lg font-black tracking-tight text-blue-900">
                                                {{ item.formatted ?? '$0.00' }}
                                            </p>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Financial Alerts – clickable cards showing real data -->
                        <div class="grid gap-4 px-6 pb-6 sm:grid-cols-2 xl:grid-cols-4" dir="rtl">
                            <button
                                v-for="alert in financialAlerts"
                                :key="alert.key"
                                type="button"
                                @click="openAlertDetails(alert)"
                                class="group relative flex flex-col rounded-xl border p-5 text-right shadow-sm transition-all duration-200"
                                :class="[
                                    alert.severity === 'success' ? 'border-emerald-200 bg-emerald-50 hover:bg-emerald-100' :
                                    alert.severity === 'warning' ? 'border-amber-200 bg-amber-50 hover:bg-amber-100' :
                                    alert.severity === 'danger'  ? 'border-red-200 bg-red-50 hover:bg-red-100' :
                                    'border-blue-200 bg-blue-50 hover:bg-blue-100',
                                    alert.value > 0 ? 'cursor-pointer hover:shadow-md hover:scale-[1.02] transform' : 'cursor-default opacity-80'
                                ]"
                            >
                                <!-- Top row: count badge + arrow -->
                                <div class="mb-3 flex items-center justify-between">
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-bold"
                                        :class="[
                                            alert.severity === 'success' ? 'bg-emerald-200 text-emerald-800' :
                                            alert.severity === 'warning' ? 'bg-amber-200 text-amber-800' :
                                            alert.severity === 'danger'  ? 'bg-red-200 text-red-800' :
                                            'bg-blue-200 text-blue-800'
                                        ]"
                                    >
                                        {{ alert.value }}
                                    </span>
                                    <svg
                                        v-if="alert.value > 0"
                                        class="h-4 w-4 opacity-40 transition-transform duration-200 group-hover:-translate-x-0.5"
                                        :class="[
                                            alert.severity === 'success' ? 'text-emerald-600' :
                                            alert.severity === 'warning' ? 'text-amber-600' :
                                            alert.severity === 'danger'  ? 'text-red-600' :
                                            'text-blue-600'
                                        ]"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                    </svg>
                                </div>

                                <!-- Label -->
                                <p
                                    class="text-base font-bold leading-tight"
                                    :class="[
                                        alert.severity === 'success' ? 'text-emerald-800' :
                                        alert.severity === 'warning' ? 'text-amber-800' :
                                        alert.severity === 'danger'  ? 'text-red-800' :
                                        'text-blue-800'
                                    ]"
                                >
                                    {{ locale === 'ar' && alert.label_ar ? alert.label_ar : reportLabel(alert.label) }}
                                </p>

                                <!-- Description -->
                                <p
                                    class="mt-1 text-xs leading-5 opacity-70"
                                    :class="[
                                        alert.severity === 'success' ? 'text-emerald-700' :
                                        alert.severity === 'warning' ? 'text-amber-700' :
                                        alert.severity === 'danger'  ? 'text-red-700' :
                                        'text-blue-700'
                                    ]"
                                >
                                    {{ locale === 'ar' && alert.description_ar ? alert.description_ar : alert.description }}
                                </p>

                                <!-- Amount -->
                                <p
                                    v-if="alert.formatted_amount"
                                    class="mt-3 text-xl font-extrabold tracking-tight"
                                    :class="[
                                        alert.severity === 'success' ? 'text-emerald-900' :
                                        alert.severity === 'warning' ? 'text-amber-900' :
                                        alert.severity === 'danger'  ? 'text-red-900' :
                                        'text-blue-900'
                                    ]"
                                >
                                    {{ alert.formatted_amount }}
                                </p>

                                <!-- Click hint -->
                                <p
                                    v-if="alert.value > 0 && alert.items && alert.items.length > 0"
                                    class="mt-2 text-xs font-semibold opacity-60"
                                    :class="[
                                        alert.severity === 'success' ? 'text-emerald-700' :
                                        alert.severity === 'warning' ? 'text-amber-700' :
                                        alert.severity === 'danger'  ? 'text-red-700' :
                                        'text-blue-700'
                                    ]"
                                >
                                    {{ localize('Click to view details', 'انقر لعرض التفاصيل', 'تفصیلات دیکھنے کے لیے کلک کریں') }}
                                </p>
                            </button>
                        </div>
                    </section>
                </div>

                
            </section>

            <!-- 3. Reservations Report -->
            <section v-if="reservationsReport" class="overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow mt-8">
                <div class="border-b border-indigo-100 bg-gradient-to-r from-indigo-50 to-white px-6 py-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-indigo-100 px-4 py-2 text-sm font-bold text-indigo-800">
                                {{ reportLabel('Most important') }}
                            </span>
                            <h2 class="text-2xl font-bold text-indigo-900">
                                3. {{ localize('Reservations Report', 'تقرير الحجوزات', 'بکنگز رپورٹ') }}
                            </h2>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <a
                                v-if="reservationsReportExports?.pdf"
                                :href="reservationsReportExports.pdf"
                                target="_blank"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-all hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                            >
                                <svg class="h-5 w-5 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                {{ localize('Export PDF', 'تصدير PDF', 'پی ڈی ایف ایکسپورٹ') }}
                            </a>
                            <a
                                v-if="reservationsReportExports?.excel"
                                :href="reservationsReportExports.excel"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-all hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                            >
                                <svg class="h-5 w-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                {{ localize('Export Excel', 'تصدير Excel', 'ایکسل ایکسپورٹ') }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Summary Metrics (يحتوي على) -->
                    <div class="mb-8 grid grid-cols-2 gap-4 md:grid-cols-5">
                        <!-- Total -->
                        <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-center">
                            <p class="text-sm font-medium text-indigo-600">{{ localize('Total Reservations', 'عدد الحجوزات', 'کل بکنگز') }}</p>
                            <p class="mt-2 text-2xl font-bold text-indigo-900">{{ reservationsReport.summary.total }}</p>
                        </div>
                        <!-- Confirmed -->
                        <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-center">
                            <p class="text-sm font-medium text-blue-600">{{ localize('Confirmed', 'الحجوزات المؤكدة', 'تصدیق شدہ') }}</p>
                            <p class="mt-2 text-2xl font-bold text-blue-900">{{ reservationsReport.summary.confirmed }}</p>
                        </div>
                        <!-- Canceled -->
                        <div class="rounded-xl border border-red-100 bg-red-50 p-4 text-center">
                            <p class="text-sm font-medium text-red-600">{{ localize('Canceled', 'الحجوزات الملغاة', 'منسوخ') }}</p>
                            <p class="mt-2 text-2xl font-bold text-red-900">{{ reservationsReport.summary.canceled }}</p>
                        </div>
                        <!-- No Show -->
                        <div class="rounded-xl border border-amber-100 bg-amber-50 p-4 text-center">
                            <p class="text-sm font-medium text-amber-600">{{ localize('No Show', 'No Show', 'حاضر نہیں ہوا') }}</p>
                            <p class="mt-2 text-2xl font-bold text-amber-900">{{ reservationsReport.summary.no_show }}</p>
                        </div>
                        <!-- Completed -->
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 text-center">
                            <p class="text-sm font-medium text-emerald-600">{{ localize('Completed', 'الحجوزات المكتملة', 'مکمل') }}</p>
                            <p class="mt-2 text-2xl font-bold text-emerald-900">{{ reservationsReport.summary.completed }}</p>
                        </div>
                    </div>

                    <!-- KPIs -->
                    <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                            <div class="rounded-full bg-indigo-100 p-3">
                                <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">{{ localize('Average Value', 'متوسط قيمة الحجز', 'اوسط مالیت') }}</p>
                                <p class="text-lg font-bold text-gray-900">{{ reservationsReport.kpis.average_value.formatted }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                            <div class="rounded-full bg-red-100 p-3">
                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">{{ localize('Cancellation Rate', 'نسبة الإلغاء', 'منسوخی کی شرح') }}</p>
                                <p class="text-lg font-bold text-gray-900">{{ reservationsReport.kpis.cancellation_rate.formatted }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                            <div class="rounded-full bg-amber-100 p-3">
                                <svg class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">{{ localize('No-Show Rate', 'نسبة No Show', 'No Show کی شرح') }}</p>
                                <p class="text-lg font-bold text-gray-900">{{ reservationsReport.kpis.no_show_rate.formatted }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 4. Fleet Report -->
            <section v-if="fleetReport" class="overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow mt-8">
                <div class="border-b border-indigo-100 bg-gradient-to-r from-indigo-50 to-white px-6 py-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-indigo-100 px-4 py-2 text-sm font-bold text-indigo-800">
                                {{ reportLabel('Most important') }}
                            </span>
                            <h2 class="text-2xl font-bold text-indigo-900">
                                4. {{ localize('Fleet Report', 'تقرير الأسطول', 'فلیٹ رپورٹ') }}
                            </h2>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <a
                                v-if="fleetReportExports?.pdf"
                                :href="fleetReportExports.pdf"
                                target="_blank"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-all hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                            >
                                <svg class="h-5 w-5 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                {{ localize('Export PDF', 'تصدير PDF', 'پی ڈی ایف ایکسپورٹ') }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Utilization -->
                        <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
                            <div class="border-b border-gray-100 bg-gray-50 px-5 py-3">
                                <h3 class="font-bold text-gray-900">{{ localize('Utilization', 'الاستخدام', 'استعمال') }}</h3>
                            </div>
                            <div class="divide-y divide-gray-100 p-5">
                                <div class="flex justify-between py-2">
                                    <span class="text-gray-600">{{ localize('Fleet utilization', 'نسبة تشغيل الأسطول', 'فلیٹ کا استعمال') }}</span>
                                    <span class="font-bold text-gray-900">{{ fleetReport.utilization.utilization_rate }}%</span>
                                </div>
                                <div class="flex justify-between py-2">
                                    <span class="text-gray-600">{{ localize('Rented days per car', 'عدد الأيام المؤجرة لكل سيارة', 'کرائے کے دن فی گاڑی') }}</span>
                                    <span class="font-bold text-gray-900">{{ fleetReport.utilization.rented_days_per_car }}</span>
                                </div>
                                <div class="flex justify-between py-2">
                                    <span class="text-gray-600">{{ localize('Idle days', 'عدد الأيام المتوقفة', 'فارغ دن') }}</span>
                                    <span class="font-bold text-gray-900">{{ fleetReport.utilization.idle_days }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Top Cars -->
                        <div class="rounded-xl border border-gray-100 bg-white shadow-sm relative">
                            <div class="border-b border-gray-100 bg-gray-50 px-5 py-3 flex justify-between items-center">
                                <h3 class="font-bold text-gray-900">{{ localize('Top Cars', 'أفضل السيارات', 'بہترین گاڑیاں') }}</h3>
                                <button @click="showCarRankingsModal = true" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                    {{ localize('View All', 'عرض الكل', 'سب دیکھیں') }}
                                </button>
                            </div>
                            <div class="divide-y divide-gray-100 p-5">
                                <div class="flex justify-between py-2" v-if="fleetReport.top_cars.revenue">
                                    <span class="text-gray-600">{{ localize('Highest revenue', 'أعلى إيراد', 'سب سے زیادہ آمدنی') }}</span>
                                    <span class="font-bold text-gray-900">{{ fleetReport.top_cars.revenue.name }} ({{ fleetReport.top_cars.revenue.value }})</span>
                                </div>
                                <div class="flex justify-between py-2" v-if="fleetReport.top_cars.utilization">
                                    <span class="text-gray-600">{{ localize('Highest utilization', 'أعلى استخدام', 'سب سے زیادہ استعمال') }}</span>
                                    <span class="font-bold text-gray-900">{{ fleetReport.top_cars.utilization.name }} ({{ fleetReport.top_cars.utilization.value }})</span>
                                </div>
                            </div>
                        </div>

                        <!-- Worst Cars -->
                        <div class="rounded-xl border border-gray-100 bg-white shadow-sm relative">
                            <div class="border-b border-gray-100 bg-gray-50 px-5 py-3 flex justify-between items-center">
                                <h3 class="font-bold text-gray-900">{{ localize('Worst Cars', 'أسوأ السيارات', 'بدترین گاڑیاں') }}</h3>
                                <button @click="showCarRankingsModal = true" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                    {{ localize('View All', 'عرض الكل', 'سب دیکھیں') }}
                                </button>
                            </div>
                            <div class="divide-y divide-gray-100 p-5">
                                <div class="flex justify-between py-2" v-if="fleetReport.worst_cars.utilization">
                                    <span class="text-gray-600">{{ localize('Lowest utilization', 'أقل استخدام', 'سب سے کم استعمال') }}</span>
                                    <span class="font-bold text-gray-900">{{ fleetReport.worst_cars.utilization.name }} ({{ fleetReport.worst_cars.utilization.value }})</span>
                                </div>
                                <div class="flex justify-between py-2" v-if="fleetReport.worst_cars.revenue">
                                    <span class="text-gray-600">{{ localize('Lowest revenue', 'أقل إيراد', 'سب سے کم آمدنی') }}</span>
                                    <span class="font-bold text-gray-900">{{ fleetReport.worst_cars.revenue.name }} ({{ fleetReport.worst_cars.revenue.value }})</span>
                                </div>
                            </div>
                        </div>

                        <!-- Fleet Status -->
                        <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
                            <div class="border-b border-gray-100 bg-gray-50 px-5 py-3">
                                <h3 class="font-bold text-gray-900">{{ localize('Fleet Status', 'حالة الأسطول', 'فلیٹ کی حیثیت') }}</h3>
                            </div>
                            <div class="divide-y divide-gray-100 p-5">
                                <div class="flex justify-between py-1">
                                    <span class="text-gray-600">{{ localize('Available', 'متاحة', 'دستیاب') }}</span>
                                    <span class="font-bold text-gray-900">{{ fleetReport.status_counts.available }}</span>
                                </div>
                                <div class="flex justify-between py-1">
                                    <span class="text-gray-600">{{ localize('Rented', 'مؤجرة', 'کرائے پر') }}</span>
                                    <span class="font-bold text-gray-900">{{ fleetReport.status_counts.rented }}</span>
                                </div>
                                <div class="flex justify-between py-1">
                                    <span class="text-gray-600">{{ localize('Reserved', 'محجوزة', 'مختص') }}</span>
                                    <span class="font-bold text-gray-900">{{ fleetReport.status_counts.reserved }}</span>
                                </div>
                                <div class="flex justify-between py-1">
                                    <span class="text-gray-600">{{ localize('Maintenance', 'صيانة', 'مرمت') }}</span>
                                    <span class="font-bold text-gray-900">{{ fleetReport.status_counts.maintenance }}</span>
                                </div>
                                <div class="flex justify-between py-1">
                                    <span class="text-gray-600">{{ localize('Out of service', 'خارج الخدمة', 'سروس سے باہر') }}</span>
                                    <span class="font-bold text-gray-900">{{ fleetReport.status_counts.out_of_service }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Financial Summary -->
            <div v-if="false" class="rounded-xl bg-white p-6 shadow">
                <div class="mb-5 flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ reportLabel('Financial Summary') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{
                                localize(
                                    'Revenue, payments, return charges, and discounts for the selected period.',
                                    'الإيرادات والمدفوعات ورسوم الرجوع والخصومات للفترة المحددة.',
                                    'منتخب مدت کے لیے آمدنی، ادائیگیاں، واپسی چارجز اور رعایتیں۔',
                                )
                            }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    <div
                        v-for="metric in financialSummary"
                        :key="metric.label"
                        class="rounded-lg border border-gray-100 bg-gray-50 p-4"
                    >
                        <div class="mb-3 h-1.5 rounded-full" :style="{ backgroundColor: metric.color }"></div>
                        <p class="text-sm font-medium text-gray-500">
                            {{ reportLabel(metric.label) }}
                        </p>
                        <p class="mt-2 text-xl font-bold text-gray-900">
                            {{ metric.formatted }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Operations, Fleet, Alerts -->
            <div v-if="false" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="rounded-xl bg-white p-6 shadow">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ reportLabel('Operations Summary') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{
                            localize(
                                'Contracts and reservation activity in the selected period.',
                                'حركة العقود والحجوزات خلال الفترة المحددة.',
                                'منتخب مدت میں معاہدوں اور بکنگز کی سرگرمی۔',
                            )
                        }}
                    </p>
                    <div class="mt-5 space-y-3">
                        <div
                            v-for="metric in operationsSummary"
                            :key="metric.label"
                            class="flex items-center justify-between rounded-lg border border-gray-100 px-4 py-3"
                        >
                            <div class="flex items-center gap-3">
                                <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: metric.color }"></span>
                                <span class="text-sm font-medium text-gray-600">{{ reportLabel(metric.label) }}</span>
                            </div>
                            <span class="text-lg font-bold text-gray-900">{{ metric.formatted }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-white p-6 shadow">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ reportLabel('Fleet Insights') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{
                            localize(
                                'Utilization, damage activity, and accident tracking.',
                                'نسبة التشغيل ونشاط الأضرار والحوادث.',
                                'استعمال، نقصان کی سرگرمی، اور حادثات کی نگرانی۔',
                            )
                        }}
                    </p>
                    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-1">
                        <div
                            v-for="metric in fleetInsights"
                            :key="metric.label"
                            class="rounded-lg border border-gray-100 p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-medium text-gray-500">
                                    {{ reportLabel(metric.label) }}
                                </p>
                                <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: metric.color }"></span>
                            </div>
                            <p class="mt-2 text-xl font-bold text-gray-900">
                                {{ metric.formatted }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-white p-6 shadow">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ reportLabel('Action Alerts') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{
                            localize(
                                'Items that need review, follow-up, or payment.',
                                'عناصر تحتاج مراجعة أو متابعة أو دفع.',
                                'وہ آئٹمز جنہیں جائزہ، فالو اپ یا ادائیگی درکار ہے۔',
                            )
                        }}
                    </p>
                    <div class="mt-5 space-y-3">
                        <a
                            v-for="alert in actionAlerts"
                            :key="alert.key"
                            :href="alert.href || '#'"
                            @click.prevent="openAlertDetails(alert)"
                            class="block rounded-lg border p-4 transition-all duration-200"
                            :class="[severityClasses(alert.severity), alert.value > 0 ? 'cursor-pointer hover:shadow-md hover:scale-[1.01] transform' : 'cursor-default opacity-80']"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-semibold">
                                        {{ reportLabel(alert.label) }}
                                    </p>
                                    <p class="mt-1 text-sm opacity-80">
                                        {{ reportDescription(alert.description) }}
                                    </p>
                                    <p
                                        v-if="alert.formatted_amount"
                                        class="mt-2 text-sm font-semibold"
                                    >
                                        {{ alert.formatted_amount }}
                                    </p>
                                </div>
                                <span class="rounded-full bg-white/70 px-3 py-1 text-sm font-bold">
                                    {{ alert.value }}
                                </span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Reservations Chart -->
            <div class="rounded-lg bg-white shadow">
                <div class="p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ t('dashboard.admin.reports.daily_reservations_created') }}
                        </h3>
                        <div class="text-sm text-gray-500">
                            {{ chartData.dateRange.start }} {{ t('dashboard.common.to') }}
                            {{ chartData.dateRange.end }}
                        </div>
                    </div>

                    <!-- Chart container -->
                    <div class="relative h-96">
                        <canvas ref="chartCanvas"></canvas>
                    </div>
                </div>
            </div>

            <!-- Cars Performance Table -->
            <div class="rounded-lg bg-white shadow">
                <div class="p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ t('dashboard.admin.reports.top_cars_performance') }}
                        </h3>
                        <div class="text-sm text-gray-500">
                            {{ t('dashboard.admin.reports.click_headers') }}
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        scope="col"
                                        class="cursor-pointer px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase hover:bg-gray-100"
                                        @click="sortTable('car_name')"
                                    >
                                        <div
                                            class="flex items-center space-x-1"
                                        >
                                            <span>{{ t('dashboard.common.car') }}</span>
                                            <svg
                                                v-if="sortField === 'car_name'"
                                                class="h-4 w-4"
                                                :class="
                                                    sortDirection === 'asc'
                                                        ? 'rotate-180 transform'
                                                        : ''
                                                "
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 9l-7 7-7-7"
                                                ></path>
                                            </svg>
                                        </div>
                                    </th>
                                    <th
                                        scope="col"
                                        class="cursor-pointer px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase hover:bg-gray-100"
                                        @click="sortTable('status')"
                                    >
                                        <div
                                            class="flex items-center space-x-1"
                                        >
                                            <span>{{ t('dashboard.common.status') }}</span>
                                            <svg
                                                v-if="sortField === 'status'"
                                                class="h-4 w-4"
                                                :class="
                                                    sortDirection === 'asc'
                                                        ? 'rotate-180 transform'
                                                        : ''
                                                "
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 9l-7 7-7-7"
                                                ></path>
                                            </svg>
                                        </div>
                                    </th>
                                    <th
                                        scope="col"
                                        class="cursor-pointer px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase hover:bg-gray-100"
                                        @click="sortTable('total_reservations')"
                                    >
                                        <div
                                            class="flex items-center space-x-1"
                                        >
                                            <span>{{ t('dashboard.common.reservations') }}</span>
                                            <svg
                                                v-if="
                                                    sortField ===
                                                    'total_reservations'
                                                "
                                                class="h-4 w-4"
                                                :class="
                                                    sortDirection === 'asc'
                                                        ? 'rotate-180 transform'
                                                        : ''
                                                "
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 9l-7 7-7-7"
                                                ></path>
                                            </svg>
                                        </div>
                                    </th>
                                    <th
                                        scope="col"
                                        class="cursor-pointer px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase hover:bg-gray-100"
                                        @click="sortTable('total_revenue')"
                                    >
                                        <div
                                            class="flex items-center space-x-1"
                                        >
                                            <span>{{ t('dashboard.admin.reports.total_revenue') }}</span>
                                            <svg
                                                v-if="
                                                    sortField ===
                                                    'total_revenue'
                                                "
                                                class="h-4 w-4"
                                                :class="
                                                    sortDirection === 'asc'
                                                        ? 'rotate-180 transform'
                                                        : ''
                                                "
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 9l-7 7-7-7"
                                                ></path>
                                            </svg>
                                        </div>
                                    </th>
                                    <th
                                        scope="col"
                                        class="cursor-pointer px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase hover:bg-gray-100"
                                        @click="sortTable('utilization_rate')"
                                    >
                                        <div
                                            class="flex items-center space-x-1"
                                        >
                                            <span>{{ t('dashboard.admin.reports.utilization_rate') }}</span>
                                            <svg
                                                v-if="
                                                    sortField ===
                                                    'utilization_rate'
                                                "
                                                class="h-4 w-4"
                                                :class="
                                                    sortDirection === 'asc'
                                                        ? 'rotate-180 transform'
                                                        : ''
                                                "
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 9l-7 7-7-7"
                                                ></path>
                                            </svg>
                                        </div>
                                    </th>
                                    <th
                                        scope="col"
                                        class="cursor-pointer px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase hover:bg-gray-100"
                                        @click="
                                            sortTable('average_per_reservation')
                                        "
                                    >
                                        <div
                                            class="flex items-center space-x-1"
                                        >
                                            <span>{{ t('dashboard.admin.reports.avg_per_reservation') }}</span>
                                            <svg
                                                v-if="
                                                    sortField ===
                                                    'average_per_reservation'
                                                "
                                                class="h-4 w-4"
                                                :class="
                                                    sortDirection === 'asc'
                                                        ? 'rotate-180 transform'
                                                        : ''
                                                "
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 9l-7 7-7-7"
                                                ></path>
                                            </svg>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="car in sortedCarsPerformance"
                                    :key="car.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div
                                            class="text-sm font-medium text-gray-900"
                                        >
                                            {{ car.car_name }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ car.license_plate }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="inline-flex rounded-full px-2 py-1 text-xs font-semibold text-white"
                                            :style="{
                                                backgroundColor:
                                                    car.status_color,
                                            }"
                                        >
                                            {{ car.status }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm whitespace-nowrap text-gray-900"
                                    >
                                        {{ car.total_reservations }}
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm whitespace-nowrap text-gray-900"
                                    >
                                        {{ car.formatted_revenue }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div
                                                class="mr-2 h-2 flex-1 rounded-full bg-gray-200"
                                            >
                                                <div
                                                    class="h-2 rounded-full bg-blue-500"
                                                    :style="{
                                                        width: `${Math.min(car.utilization_rate, 100)}%`,
                                                    }"
                                                ></div>
                                            </div>
                                            <span
                                                class="min-w-0 text-sm text-gray-900"
                                            >
                                                {{ car.utilization_rate }}%
                                            </span>
                                        </div>
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm whitespace-nowrap text-gray-900"
                                    >
                                        {{ displayMoney(car.average_per_reservation) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Details Modal -->
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="isModalOpen && activeAlertDetails"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                    @click.self="isModalOpen = false"
                >
                    <div
                        class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl border border-slate-100 flex flex-col max-h-[85vh] overflow-hidden"
                        dir="rtl"
                    >
                        <!-- Modal Header -->
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                            <div class="flex items-center gap-3">
                                <span
                                    class="rounded-full px-3 py-1 text-sm font-bold"
                                    :class="severityClasses(activeAlertDetails.severity)"
                                >
                                    {{ activeAlertDetails.value }}
                                </span>
                                <h3 class="text-xl font-bold text-slate-800">
                                    {{ reportLabel(activeAlertDetails.label) }}
                                </h3>
                            </div>
                            <button
                                @click="isModalOpen = false"
                                class="p-2 rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Modal Body / Content -->
                        <div class="p-6 overflow-y-auto flex-1">
                            <!-- Table for Contracts -->
                            <div
                                v-if="['contracts_ending_24h', 'overdue_cars', 'overdue_contracts', 'returns_due_today', 'missing_documents', 'contracts_without_signature'].includes(activeAlertDetails.key)"
                                class="overflow-x-auto rounded-lg border border-slate-200"
                            >
                                <table class="min-w-full divide-y divide-slate-200 text-right">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">رقم العقد</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">المستأجر</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">السيارة</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">الفرع</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">تاريخ البدء</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">تاريخ الانتهاء</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">القيمة الإجمالية</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">إجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        <tr v-for="item in activeAlertDetails.items" :key="item.id" class="hover:bg-slate-50/80 transition-colors">
                                            <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ item.contract_number }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-600">{{ item.renter_name }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-600">
                                                <div v-if="item.car">
                                                    <div class="font-medium text-slate-800">{{ item.car.make }} {{ item.car.model }}</div>
                                                    <div class="text-xs text-slate-500">{{ item.car.license_plate }}</div>
                                                </div>
                                                <span v-else>-</span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-slate-600">{{ item.branch_name }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-600">{{ item.start_date }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-600">{{ item.end_date }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-900 font-semibold">{{ item.total_amount }} {{ item.currency }}</td>
                                            <td class="px-6 py-4 text-sm">
                                                <Link
                                                    :href="`/admin/contracts/${item.id}`"
                                                    class="inline-flex items-center justify-center rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100 transition-colors"
                                                >
                                                    عرض العقد
                                                </Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Table for Payments / Return Charges (including financial alerts) -->
                            <div
                                v-else-if="['missing_payments', 'unpaid_return_reports', 'paid_revenue', 'pending_payments', 'outstanding_return_charges'].includes(activeAlertDetails.key)"
                                class="overflow-x-auto rounded-lg border border-slate-200"
                            >
                                <table class="min-w-full divide-y divide-slate-200 text-right">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">المرجع</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">نوع المطالبة</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">المستأجر / العميل</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">السيارة</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">التاريخ</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">المبلغ</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">الحالة</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">إجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        <tr v-for="item in activeAlertDetails.items" :key="item.id" class="hover:bg-slate-50/80 transition-colors">
                                            <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ item.reference }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-600">
                                                <span v-if="item.type === 'payment' && item.status === 'completed'" class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-700">دفعة مكتملة</span>
                                                <span v-else-if="item.type === 'payment'" class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-xs text-amber-700">دفعة معلقة</span>
                                                <span v-else class="inline-flex rounded-full bg-red-50 px-2 py-1 text-xs text-red-700">رسوم تسليم إضافية</span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-slate-600">{{ item.renter_name }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-600">
                                                <div v-if="item.car">
                                                    <div class="font-medium text-slate-800">{{ item.car.make }} {{ item.car.model }}</div>
                                                    <div class="text-xs text-slate-500">{{ item.car.license_plate }}</div>
                                                </div>
                                                <span v-else>-</span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-slate-600">{{ item.date }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-900 font-semibold">{{ item.amount }} {{ item.currency }}</td>
                                            <td class="px-6 py-4 text-sm">
                                                <span
                                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                                    :style="{
                                                        color: item.status === 'completed' ? '#10B981' : '#EF4444',
                                                        backgroundColor: item.status === 'completed' ? '#10B9811f' : '#EF44441f',
                                                    }"
                                                >
                                                    {{ item.status }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <Link
                                                    v-if="item.type === 'payment'"
                                                    :href="`/admin/payments`"
                                                    class="inline-flex items-center justify-center rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100 transition-colors"
                                                >
                                                    الذهاب للمدفوعات
                                                </Link>
                                                <Link
                                                    v-else
                                                    :href="`/admin/contracts/${item.contract_id}/return-status-report`"
                                                    class="inline-flex items-center justify-center rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100 transition-colors"
                                                >
                                                    تقرير الرجوع
                                                </Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Table for Discounts -->
                            <div
                                v-else-if="activeAlertDetails.key === 'discounts_applied'"
                                class="overflow-x-auto rounded-lg border border-slate-200"
                            >
                                <table class="min-w-full divide-y divide-slate-200 text-right">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">المرجع</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">العميل</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">السيارة</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">التاريخ</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">مبلغ الخصم</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">إجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        <tr v-for="item in activeAlertDetails.items" :key="item.id" class="hover:bg-slate-50/80 transition-colors">
                                            <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ item.reference }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-600">{{ item.renter_name || '-' }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-600">
                                                <div v-if="item.car">
                                                    <div class="font-medium text-slate-800">{{ item.car.make }} {{ item.car.model }}</div>
                                                    <div class="text-xs text-slate-500">{{ item.car.license_plate }}</div>
                                                </div>
                                                <span v-else>-</span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-slate-600">{{ item.date }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-900 font-semibold">
                                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">
                                                    - {{ item.amount }} {{ item.currency }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <Link
                                                    :href="`/admin/reservations/${item.id}`"
                                                    class="inline-flex items-center justify-center rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100 transition-colors"
                                                >
                                                    عرض الحجز
                                                </Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Table for Violations -->
                            <div
                                v-else-if="activeAlertDetails.key === 'pending_violations'"
                                class="overflow-x-auto rounded-lg border border-slate-200"
                            >
                                <table class="min-w-full divide-y divide-slate-200 text-right">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">رقم المخالفة</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">السيارة</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">نوع المخالفة</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">التاريخ</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">المبلغ</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">المدين</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">إجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        <tr v-for="item in activeAlertDetails.items" :key="item.id" class="hover:bg-slate-50/80 transition-colors">
                                            <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ item.violation_number || '-' }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-600">
                                                <div v-if="item.car">
                                                    <div class="font-medium text-slate-800">{{ item.car.make }} {{ item.car.model }}</div>
                                                    <div class="text-xs text-slate-500">{{ item.car.license_plate }}</div>
                                                </div>
                                                <span v-else>-</span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-slate-600">{{ item.type }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-600">{{ item.date }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-900 font-semibold">{{ item.amount }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-600">{{ item.issued_to }}</td>
                                            <td class="px-6 py-4 text-sm">
                                                <Link
                                                    :href="`/admin/car-violations`"
                                                    class="inline-flex items-center justify-center rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100 transition-colors"
                                                >
                                                    عرض المخالفات
                                                </Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Table for Damage Reports -->
                            <div
                                v-else-if="activeAlertDetails.key === 'draft_damage_reports'"
                                class="overflow-x-auto rounded-lg border border-slate-200"
                            >
                                <table class="min-w-full divide-y divide-slate-200 text-right">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">رقم التقرير</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">السيارة</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">نوع الفحص</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">التاريخ</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">العقد المرتبط</th>
                                            <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">إجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        <tr v-for="item in activeAlertDetails.items" :key="item.id" class="hover:bg-slate-50/80 transition-colors">
                                            <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ item.report_number }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-600">
                                                <div v-if="item.car">
                                                    <div class="font-medium text-slate-800">{{ item.car.make }} {{ item.car.model }}</div>
                                                    <div class="text-xs text-slate-500">{{ item.car.license_plate }}</div>
                                                </div>
                                                <span v-else>-</span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-slate-600">{{ item.report_type }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-600">{{ item.date }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-600">{{ item.contract_number || '-' }}</td>
                                            <td class="px-6 py-4 text-sm">
                                                <Link
                                                    :href="`/admin/car-damage-reports/${item.id}/edit`"
                                                    class="inline-flex items-center justify-center rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100 transition-colors"
                                                >
                                                    تعديل التقرير
                                                </Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
                            <Link
                                :href="activeAlertDetails.href"
                                class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow hover:bg-blue-700 transition-colors"
                            >
                                عرض الصفحة الكاملة المصدر
                            </Link>
                            <button
                                @click="isModalOpen = false"
                                class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 transition-colors"
                            >
                                إغلاق
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Car Rankings Modal -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div v-if="showCarRankingsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
                    <div class="relative w-full max-w-4xl max-h-[90vh] flex flex-col bg-white rounded-2xl shadow-2xl overflow-hidden">
                        
                        <!-- Modal Header -->
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                            <h3 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                                {{ localize('Car Rankings', 'ترتيب السيارات', 'گاڑیوں کی درجہ بندی') }}
                            </h3>
                            <button @click="showCarRankingsModal = false" class="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        
                        <!-- Modal Body -->
                        <div class="p-6 overflow-y-auto flex-1">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                
                                <!-- Revenue Rankings -->
                                <div>
                                    <h4 class="font-bold text-gray-900 mb-4 border-b pb-2 flex justify-between">
                                        <span>{{ localize('By Revenue', 'حسب الإيراد', 'آمدنی کے لحاظ سے') }}</span>
                                    </h4>
                                    <div class="overflow-x-auto rounded-xl border border-gray-100">
                                        <table class="min-w-full divide-y divide-gray-100">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">#</th>
                                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ localize('Car', 'السيارة', 'گاڑی') }}</th>
                                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ localize('Revenue', 'الإيراد', 'آمدنی') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 bg-white">
                                                <tr v-for="(car, index) in fleetReport?.rankings.revenue" :key="car.id" class="hover:bg-gray-50">
                                                    <td class="px-4 py-3 text-sm text-gray-500">{{ index + 1 }}</td>
                                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ car.car_name }}</td>
                                                    <td class="px-4 py-3 text-sm font-bold text-green-600">{{ car.formatted_revenue }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Utilization Rankings -->
                                <div>
                                    <h4 class="font-bold text-gray-900 mb-4 border-b pb-2 flex justify-between">
                                        <span>{{ localize('By Utilization', 'حسب الاستخدام (الأيام)', 'استعمال کے لحاظ سے') }}</span>
                                    </h4>
                                    <div class="overflow-x-auto rounded-xl border border-gray-100">
                                        <table class="min-w-full divide-y divide-gray-100">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">#</th>
                                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ localize('Car', 'السيارة', 'گاڑی') }}</th>
                                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ localize('Days', 'الأيام المؤجرة', 'دن') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 bg-white">
                                                <tr v-for="(car, index) in fleetReport?.rankings.utilization" :key="car.id" class="hover:bg-gray-50">
                                                    <td class="px-4 py-3 text-sm text-gray-500">{{ index + 1 }}</td>
                                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ car.car_name }}</td>
                                                    <td class="px-4 py-3 text-sm font-bold text-blue-600">{{ car.total_days }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                            </div>
                        </div>

                    </div>
                </div>
            </Transition>
        </AdminLayout>
    </template>
