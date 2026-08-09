<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useTrans } from '@/composables/useTrans';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Car,
    FileText,
    Users,
    DollarSign,
    Calendar,
    Clock,
    CheckCircle2,
    LayoutDashboard,
    RefreshCcw,
    TrendingUp,
    Layers,
    LifeBuoy,
    Siren,
    AlertTriangle,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { create as createCar, index as carsIndex } from '@/routes/admin/cars';
import { create as createClient } from '@/routes/admin/clients';
import { create as createContract, index as contractsIndex } from '@/routes/admin/contracts';
import { create as createReservation, index as reservationsIndex } from '@/routes/admin/reservations';
import { debtors as paymentsDebtors, index as paymentsIndex } from '@/routes/admin/payments';
import { index as carViolationsIndex } from '@/routes/admin/car-violations';
import { index as accidentReportsIndex } from '@/routes/admin/accident-reports';
import { index as supportIndex } from '@/routes/admin/support';

const props = defineProps<{
    stats: {
        total_cars: number;
        available_cars: number;
        active_reservations: number;
        pending_reservations: number;
        pending_violations: number;
        total_reservations: number;
        total_revenue: number;
        total_clients: number;
        cars_to_deliver_today: number;
        cars_to_receive_today: number;
        overdue_cars: number;
    };
    reservationsByStatus: Array<{
        status: string;
        label: string;
        count: number;
        color: string;
    }>;
    fleetStatus: Array<{
        status: string;
        label: string;
        count: number;
        color: string;
    }>;
    monthlyRevenue: Array<{
        month: string;
        revenue: number;
    }>;
    recentReservations: Array<{
        id: number;
        reservation_number: string;
        client_name: string | null;
        car_name: string;
        branch_name: string;
        start_date: string | null;
        end_date: string | null;
        total_amount: number;
        status: string;
        status_color: string;
    }>;
    topCars: Array<{
        id: number;
        name: string;
        price_per_day: number;
        status: string;
        status_label: string;
        status_color: string;
        completed_count: number;
    }>;
    expiringCarDocuments: Array<{
        id: number;
        type: string;
        car_name: string;
        license_plate: string;
        expiry_date: string | null;
        days_remaining: number | null;
        edit_url: string;
    }>;
    expiringContracts: Array<{
        id: number;
        contract_number: string;
        reservation_number: string | null;
        car_name: string;
        license_plate: string;
        client_name: string | null;
        client_email: string | null;
        branch_name: string | null;
        end_date: string | null;
        days_remaining: number | null;
        show_url: string;
    }>;
    recentForcedExtensions: Array<{
        id: number;
        payment_number: string;
        contract_number: string | null;
        reservation_number: string | null;
        car_name: string;
        license_plate: string;
        client_name: string | null;
        client_email: string | null;
        branch_name: string | null;
        amount: number;
        processed_at: string | null;
        note: string;
        show_url: string;
    }>;
    recentPendingViolations: Array<{
        id: number;
        violation_number: string;
        type: string;
        car_name: string;
        license_plate: string;
        branch_name: string;
        violation_date: string | null;
        due_date: string | null;
        amount: number;
        edit_url: string;
    }>;
    dailyTasks: {
        date: string;
        branch_id: number | null;
        type: string;
        progress: {
            total: number;
            completed: number;
            in_progress: number;
            pending: number;
            late: number;
            percentage: number;
        };
        tasks: Array<{
            id: string;
            task_type: string;
            task_type_label: string;
            source_type: string;
            source_id: number;
            reference: string | null;
            title: string;
            description: string;
            scheduled_at: string;
            scheduled_date: string;
            scheduled_time: string;
            location: string | null;
            action_url: string | null;
            status: string;
            status_label: string;
            computed_status: string;
            computed_status_label: string;
            is_late: boolean;
            remaining_minutes: number;
            started_at: string | null;
            completed_at: string | null;
            car: {
                id: number;
                name: string;
                license_plate: string;
                branch_id: number | null;
                branch_name: string;
                image_url: string | null;
            } | null;
            client: {
                id: number;
                name: string;
                email: string;
            } | null;
        }>;
    };
    branches: Array<{ id: number; name: string }>;
    filters: { branch_id: number | null };
    canAccessAllBranches: boolean;
    canViewFinancials: boolean;
}>();

const { t, locale, direction } = useTrans();
const isRtl = computed(() => direction.value === 'rtl');
const translationKeyFor = (text: string) =>
    text
        .toLowerCase()
        .replace(/#/g, ' number ')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_|_$/g, '')
        .replace(/_+/g, '_');
const localize = (en: string, ar: string, urOrParams: string | Record<string, string | number> = {}, maybeParams: Record<string, string | number> = {}) => {
    const hasUrFallback = typeof urOrParams === 'string';
    const ur = hasUrFallback ? urOrParams : en;
    const params = hasUrFallback ? maybeParams : urOrParams;
    const key = `dashboard.admin_page.${translationKeyFor(en)}`;
    const translated = t(key, params);

    if (translated !== key) {
        return translated;
    }

    const fallback = locale.value === 'ar' ? ar : locale.value === 'ur' ? ur : en;

    return fallback.replace(/:([a-zA-Z0-9_]+)/g, (_match, paramKey: string) =>
        params[paramKey] !== undefined ? String(params[paramKey]) : `:${paramKey}`,
    );
};

const normalizedStatusKey = (status: string) => status.toLowerCase().trim().replace(/\s+/g, '_');

const translatedStatusLabel = (group: 'car_statuses' | 'reservation_statuses', status: string, fallback: string) => {
    const key = normalizedStatusKey(status);
    const adminKey = `dashboard.admin.${group}.${key}`;
    const adminTranslated = t(adminKey);

    if (adminTranslated !== adminKey) {
        return adminTranslated;
    }

    const dashboardKey = `dashboard.${group}.${key}`;
    const dashboardTranslated = t(dashboardKey);

    return dashboardTranslated === dashboardKey ? fallback : dashboardTranslated;
};

const carStatusLabel = (status: string, fallback: string) => translatedStatusLabel('car_statuses', status, fallback);
const reservationStatusLabel = (status: string, fallback: string) => translatedStatusLabel('reservation_statuses', status, fallback);

const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug ?? '');
const currency = computed(() => page.props.currency?.symbol ?? '$');
const numberLocale = computed(() => (locale.value === 'ar' ? 'ar' : locale.value === 'ur' ? 'ur-PK' : 'en-US'));
const authPermissions = computed<string[]>(() =>
    Array.isArray(page.props?.auth?.permissions) ? page.props.auth.permissions : [],
);
const hasFinancialAccess = computed(() => !!page.props.canViewFinancials);

const fmt = (n: number) =>
    new Intl.NumberFormat(numberLocale.value, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);

const fmtCurrency = (n: number) => (hasFinancialAccess.value ? `${currency.value}${fmt(n)}` : '*******');
const hasPermission = (permission?: string) => !permission || authPermissions.value.includes(permission);

const fmtDate = (d: string | null) =>
    d
        ? new Date(d).toLocaleDateString(numberLocale.value, { month: 'short', day: 'numeric', year: 'numeric' })
        : localize('N/A', 'غير متوفر');

const documentTypeLabel = (type: string) =>
    type === 'license'
        ? localize('Car License', 'رخصة السيارة')
        : localize('Car Insurance', 'تأمين السيارة');

const daysRemainingLabel = (days: number | null) => {
    if (days === null) return localize('Unknown', 'غير معروف');
    if (days === 0) return localize('Expires today', 'تنتهي اليوم');

    return localize('In :days days', 'خلال :days أيام', { days });
};

type TaskType = 'pickup' | 'return' | 'overdue';

type TaskItem = {
    id: number;
    reservation_number?: string | null;
    contract_number?: string | null;
    client_name?: string | null;
    client_email?: string | null;
    start_date?: string | null;
    end_date?: string | null;
    pickup_time?: string | null;
    return_time?: string | null;
    status?: string | null;
    reservation_status?: string | null;
    is_overdue?: boolean;
    days_overdue?: number;
    task_type?: TaskType;
    task_type_label?: string;
    car?: {
        id?: number | null;
        name?: string;
        license_plate?: string;
        branch_name?: string;
        status?: string;
    } | null;
};

const taskType = ref<TaskType>('pickup');
const taskItems = ref<TaskItem[]>([]);
const taskCount = ref(0);
const taskLoading = ref(false);
const taskError = ref('');
const dailyTaskActionKey = ref('');

const taskTypeMeta = computed(() => {
    const meta = {
        pickup: {
            title: localize('Today Pickups', 'استلام اليوم', 'آج کی وصولیاں'),
            numberLabel: localize('Reservation #', 'رقم الحجز', 'ریزرویشن #'),
            dateLabel: localize('Pickup Date', 'تاريخ الاستلام', 'وصولی کی تاریخ'),
        },
        return: {
            title: localize('Today Returns', 'تسليم اليوم', 'آج کی واپسی'),
            numberLabel: localize('Contract #', 'رقم العقد', 'معاہدہ #'),
            dateLabel: localize('Return Date', 'تاريخ التسليم', 'واپسی کی تاریخ'),
        },
        overdue: {
            title: localize('Overdue Returns', 'المتأخرات', 'تاخیر شدہ واپسی'),
            numberLabel: localize('Contract #', 'رقم العقد', 'معاہدہ #'),
            dateLabel: localize('Due Date', 'تاريخ الاستحقاق', 'واجب الادا تاریخ'),
        },
    }[taskType.value];

    return meta;
});

const taskTabs = computed(() => [
    { key: 'pickup' as const, label: localize('Pickup', 'استلام', 'وصولی') },
    { key: 'return' as const, label: localize('Return', 'تسليم', 'واپسی') },
    { key: 'overdue' as const, label: localize('Overdue', 'متأخر', 'تاخیر شدہ') },
]);

const taskTypeLabel = (type?: TaskType | null, fallback?: string | null) => {
    const key = type ?? taskType.value;
    const label = taskTabs.value.find((tab) => tab.key === key)?.label;

    return label || fallback || taskTypeMeta.value.title;
};

const taskRows = computed(() =>
    taskItems.value.map((item, index) => ({
        ...item,
        rowNumber: item.reservation_number ?? item.contract_number ?? `${index + 1}`,
        rowDate: taskType.value === 'pickup' ? item.start_date : item.end_date,
        rowStatus: taskType.value === 'overdue'
            ? item.is_overdue
                ? localize('Overdue :days days', 'متأخر :days يوم', ':days دن تاخیر', { days: item.days_overdue ?? 0 })
                : localize('Due today', 'مستحق اليوم', 'آج واجب الادا')
            : item.status ?? item.reservation_status ?? localize('N/A', 'غير متوفر'),
    })),
);

const loadTaskItems = async () => {
    taskLoading.value = true;
    taskError.value = '';

    try {
        const response = await fetch(`/api/reservations/today-pickups?type=${taskType.value}&per_page=5`, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
        });

        const payload = await response.json().catch(() => null);

        if (!response.ok) {
            throw new Error(payload?.message ?? localize('Failed to load tasks.', 'تعذر تحميل البيانات.'));
        }

        const items = Array.isArray(payload?.items)
            ? payload.items
            : Array.isArray(payload?.reservations)
                ? payload.reservations
                : Array.isArray(payload?.returns)
                    ? payload.returns
                    : [];

        taskItems.value = items;
        taskCount.value = Number(payload?.count ?? items.length ?? 0);
    } catch (error) {
        taskItems.value = [];
        taskCount.value = 0;
        taskError.value = error instanceof Error ? error.message : localize('Failed to load tasks.', 'تعذر تحميل البيانات.');
    } finally {
        taskLoading.value = false;
    }
};

watch(taskType, () => {
    loadTaskItems();
});

onMounted(() => {
    loadTaskItems();
});

const selectedBranch = ref<number | null>(props.filters.branch_id ?? null);
const applyBranchFilter = () => {
    router.get(window.location.pathname, { branch_id: selectedBranch.value ?? undefined }, { preserveState: true });
};

const flash = (tone: 'success' | 'error', message: string) => {
    window.dispatchEvent(new CustomEvent('flash-toast', { detail: { tone, message } }));
};

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const updateDailyTaskStatus = async (task: {
    id: string;
    task_type: string;
    source_type: string;
    source_id: number;
}, action: 'start' | 'complete', options: { reload?: boolean } = {}) => {
    const shouldReload = options.reload ?? true;
    dailyTaskActionKey.value = `${action}:${task.id}`;

    try {
        const response = await fetch(`/api/tasks/${action}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                task_type: task.task_type,
                source_type: task.source_type,
                source_id: task.source_id,
            }),
        });

        const payload = await response.json().catch(() => null);

        if (!response.ok) {
            throw new Error(payload?.message ?? localize('Unable to update task.', 'تعذر تحديث المهمة.'));
        }

        flash(
            'success',
            action === 'start'
                ? localize('Task started.', 'تم بدء المهمة.')
                : localize('Task completed.', 'تم إنجاز المهمة.'),
        );

        if (shouldReload) {
            router.reload({ only: ['dailyTasks'], preserveScroll: true });
        }

        return true;
    } catch (error) {
        flash('error', error instanceof Error ? error.message : localize('Unable to update task.', 'تعذر تحديث المهمة.'));
    } finally {
        dailyTaskActionKey.value = '';
    }
};

const startDailyTask = async (task: {
    id: string;
    task_type: string;
    source_type: string;
    source_id: number;
    action_url?: string | null;
}) => {
    if (!task.action_url) {
        await updateDailyTaskStatus(task, 'start');
        return;
    }

    const started = await updateDailyTaskStatus(task, 'start', { reload: false });
    if (started) {
        window.location.href = task.action_url;
    }
};

const maxRevenue = computed(() => Math.max(...props.monthlyRevenue.map((m) => m.revenue), 1));
const barHeight = (revenue: number) =>
    hasFinancialAccess.value ? Math.max(4, Math.round((revenue / maxRevenue.value) * 160)) : 0;

const totalResCount = computed(() => props.reservationsByStatus.reduce((sum, s) => sum + s.count, 0));
const statusBarWidths = computed(() =>
    props.reservationsByStatus.map((s) => ({
        ...s,
        pct: totalResCount.value > 0 ? Math.round((s.count / totalResCount.value) * 100) : 0,
    })),
);

const quickActions = computed(() => {
    const slug = subdomain.value;
    if (!slug) return [];

    return [
        {
            title: localize('New Reservation', 'حجز جديد'),
            description: localize('Start a new booking flow.', 'ابدأ عملية حجز جديدة.'),
            href: createReservation(slug).url,
            icon: Calendar,
            accent: '#F59E0B',
            bg: 'rgba(245,158,11,0.10)',
        },
        {
            title: localize('New Contract', 'عقد جديد'),
            description: localize('Convert a reservation into a contract.', 'حوّل الحجز إلى عقد.'),
            href: createContract(slug).url,
            icon: FileText,
            accent: '#8B5CF6',
            bg: 'rgba(139,92,246,0.10)',
        },
        {
            title: localize('Add Car', 'إضافة سيارة'),
            description: localize('Register a new vehicle.', 'تسجيل سيارة جديدة.'),
            href: createCar(slug).url,
            icon: Car,
            accent: '#3B82F6',
            bg: 'rgba(59,130,246,0.10)',
        },
        {
            title: localize('Add Client', 'إضافة عميل'),
            description: localize('Create a customer profile.', 'إنشاء ملف عميل.'),
            href: createClient(slug).url,
            icon: Users,
            accent: '#EC4899',
            bg: 'rgba(236,72,153,0.10)',
        },
        {
            title: localize('Payments', 'المدفوعات'),
            description: localize('Review payment records.', 'مراجعة سجلات المدفوعات.'),
            href: paymentsIndex(slug).url,
            icon: DollarSign,
            accent: '#10B981',
            bg: 'rgba(16,185,129,0.10)',
            permission: 'tenant-manage-payments',
        },
        {
            title: localize('Debtors', 'المدينون'),
            description: localize('See balances that still need collection.', 'عرض الأرصدة التي تحتاج تحصيلًا.'),
            href: paymentsDebtors(slug).url,
            icon: Clock,
            accent: '#EF4444',
            bg: 'rgba(239,68,68,0.10)',
            permission: 'tenant-view-debtors',
        },
        {
            title: localize('Accidents', 'الحوادث'),
            description: localize('Review accident reports.', 'مراجعة بلاغات الحوادث.'),
            href: accidentReportsIndex(slug).url,
            icon: Siren,
            accent: '#F97316',
            bg: 'rgba(249,115,22,0.10)',
        },
        {
            title: localize('Violations', 'المخالفات'),
            description: localize('Review car violations.', 'مراجعة مخالفات السيارات.'),
            href: carViolationsIndex(slug).url,
            icon: AlertTriangle,
            accent: '#DC2626',
            bg: 'rgba(220,38,38,0.10)',
        },
        {
            title: localize('Support', 'الدعم'),
            description: localize('Open the support inbox.', 'افتح صندوق الدعم.'),
            href: supportIndex(slug).url,
            icon: LifeBuoy,
            accent: '#14B8A6',
            bg: 'rgba(20,184,166,0.10)',
        },
    ].filter((item) => hasPermission(item.permission));
});

const operationalHighlights = computed(() => {
    const slug = subdomain.value;
    if (!slug) return [];

    return [
        {
            title: localize('Deliver today', 'التسليم اليوم'),
            description: localize('Reservations scheduled to start today.', 'الحجوزات المجدولة لليوم.'),
            count: props.stats.cars_to_deliver_today,
            href: reservationsIndex(slug, { query: { scope: 'today_delivery' } }).url,
            accent: '#F59E0B',
        },
        {
            title: localize('Receive today', 'الاستلام اليوم'),
            description: localize('Contracts ending today.', 'العقود التي تنتهي اليوم.'),
            count: props.stats.cars_to_receive_today,
            href: contractsIndex(slug, { query: { scope: 'today_return' } }).url,
            accent: '#06B6D4',
        },
        {
            title: localize('Overdue cars', 'السيارات المتأخرة'),
            description: localize('Active contracts past the due date.', 'عقود نشطة تجاوزت تاريخ الاستحقاق.'),
            count: props.stats.overdue_cars,
            href: contractsIndex(slug, { query: { scope: 'overdue' } }).url,
            accent: '#EF4444',
        },
        {
            title: localize('Pending violations', 'المخالفات المعلقة'),
            description: localize('Need review or payment.', 'تحتاج مراجعة أو سداد.'),
            count: props.stats.pending_violations,
            href: carViolationsIndex(slug, { query: { status: 'pending' } }).url,
            accent: '#8B5CF6',
        },
        {
            title: localize('Expiring documents', 'وثائق تنتهي قريبًا'),
            description: localize('Due within the next 10 days.', 'تنتهي خلال 10 أيام.'),
            count: props.expiringCarDocuments.length,
            href: carsIndex(slug, { query: { scope: 'expiring_documents' } }).url,
            accent: '#3B82F6',
        },
        {
            title: localize('Ending contracts', 'عقود تنتهي قريبًا'),
            description: localize('Due within the next 7 days.', 'تنتهي خلال 7 أيام.'),
            count: props.expiringContracts.length,
            href: contractsIndex(slug, { query: { scope: 'ending_soon' } }).url,
            accent: '#10B981',
        },
    ];
});

const kpiCards = computed(() => [
    {
        title: localize('Total Cars', 'إجمالي السيارات'),
        value: props.stats.total_cars,
        sub: localize(':count available', ':count متاحة', { count: props.stats.available_cars }),
        icon: Car,
        accent: '#3B82F6',
        bg: 'rgba(59,130,246,0.1)',
    },
    {
        title: localize('Available Cars', 'السيارات المتاحة'),
        value: props.stats.available_cars,
        sub: localize('of :count total', 'من أصل :count', { count: props.stats.total_cars }),
        icon: TrendingUp,
        accent: '#06B6D4',
        bg: 'rgba(6,182,212,0.1)',
    },
    {
        title: localize('Active Reservations', 'الحجوزات النشطة'),
        value: props.stats.active_reservations,
        sub: localize(':count pending', ':count قيد الانتظار', { count: props.stats.pending_reservations }),
        icon: Calendar,
        accent: '#F59E0B',
        bg: 'rgba(245,158,11,0.1)',
    },
    {
        title: localize('Total Reservations', 'إجمالي الحجوزات'),
        value: props.stats.total_reservations,
        sub: localize('All time bookings', 'كل الحجوزات'),
        icon: CheckCircle2,
        accent: '#8B5CF6',
        bg: 'rgba(139,92,246,0.1)',
    },
    {
        title: localize('Cars to Deliver Today', 'السيارات المراد تسليمها اليوم'),
        value: props.stats.cars_to_deliver_today,
        sub: localize('Pickups scheduled for today', 'حجوزات الاستلام اليوم'),
        icon: Calendar,
        accent: '#F59E0B',
        bg: 'rgba(245,158,11,0.1)',
    },
    {
        title: localize('Cars to Receive Today', 'السيارات التي سيتم استلامها اليوم'),
        value: props.stats.cars_to_receive_today,
        sub: localize('Returns due today', 'سيارات يجب استلامها اليوم'),
        icon: RefreshCcw,
        accent: '#06B6D4',
        bg: 'rgba(6,182,212,0.1)',
    },
    {
        title: localize('Overdue Cars', 'السيارات المتأخرة'),
        value: props.stats.overdue_cars,
        sub: localize('Active contracts past due date', 'عقود نشطة متأخرة'),
        icon: Clock,
        accent: '#EF4444',
        bg: 'rgba(239,68,68,0.1)',
    },
    {
        title: localize('Pending Violations', 'المخالفات المعلقة'),
        value: props.stats.pending_violations,
        sub: localize('Need review or payment', 'تحتاج مراجعة أو سداد'),
        icon: Clock,
        accent: '#EF4444',
        bg: 'rgba(239,68,68,0.1)',
    },
    {
        title: localize('Total Revenue', 'إجمالي الإيرادات'),
        value: fmtCurrency(props.stats.total_revenue),
        sub: localize('All completed payments', 'جميع المدفوعات المكتملة'),
        icon: DollarSign,
        accent: '#10B981',
        bg: 'rgba(16,185,129,0.1)',
    },
    {
        title: localize('Total Clients', 'إجمالي العملاء'),
        value: props.stats.total_clients,
        sub: localize('Registered clients', 'العملاء المسجلون'),
        icon: Users,
        accent: '#EC4899',
        bg: 'rgba(236,72,153,0.1)',
    },
]);

const dailyTaskPreview = computed(() => props.dailyTasks?.tasks?.slice(0, 8) ?? []);

const dailyTaskAccent = (task: { computed_status: string; task_type: string }) => {
    if (task.computed_status === 'late') return '#EF4444';
    if (task.computed_status === 'completed') return '#10B981';
    if (task.computed_status === 'in_progress') return '#2563EB';
    if (task.task_type === 'pickup') return '#0F766E';
    if (task.task_type === 'return') return '#F97316';

    return '#6366F1';
};

const dailyTaskRemainingLabel = (minutes: number, isLate: boolean) => {
    const abs = Math.abs(Math.round(minutes));
    const hours = Math.floor(abs / 60);
    const mins = abs % 60;
    const value = hours > 0
        ? localize(':hours h :minutes m', ':hours س :minutes د', { hours, minutes: mins })
        : localize(':minutes m', ':minutes د', { minutes: mins });

    return isLate
        ? localize(':value late', 'متأخرة :value', { value })
        : localize(':value remaining', 'متبقي :value', { value });
};
</script>

<template>
    <Head :title="localize('Dashboard', 'لوحة التحكم')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-6 lg:p-8" :dir="direction" :class="isRtl ? 'text-right' : 'text-left'">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow">
                        <LayoutDashboard class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">{{ localize('Dashboard', 'لوحة التحكم') }}</h1>
                        <p class="text-sm text-muted-foreground">{{ localize('Your rental business at a glance', 'نظرة سريعة على أعمال التأجير الخاصة بك') }}</p>
                    </div>
                </div>

                <div v-if="canAccessAllBranches && branches.length > 1" class="flex items-center gap-2">
                    <select
                        v-model="selectedBranch"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                        @change="applyBranchFilter"
                    >
                        <option :value="null">{{ localize('All Branches', 'كل الفروع') }}</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
            </div>

            <Card class="overflow-hidden border-0 shadow-sm">
                <CardHeader class="border-b bg-muted/20">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <Clock class="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle class="text-lg">{{ localize('Today Tasks', 'مهام اليوم') }}</CardTitle>
                                <p class="text-sm text-muted-foreground">
                                    {{ localize('Pickups, returns, and maintenance ordered by time.', 'تسليم واستلام وصيانة مرتبة حسب الوقت.') }}
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-4 gap-2 text-center sm:min-w-[360px]">
                            <div class="rounded-xl bg-muted/50 px-3 py-2">
                                <div class="text-lg font-bold">{{ dailyTasks.progress.total }}</div>
                                <div class="text-[11px] text-muted-foreground">{{ localize('Total', 'الكل') }}</div>
                            </div>
                            <div class="rounded-xl bg-blue-50 px-3 py-2 text-blue-700">
                                <div class="text-lg font-bold">{{ dailyTasks.progress.in_progress }}</div>
                                <div class="text-[11px]">{{ localize('Working', 'قيد العمل') }}</div>
                            </div>
                            <div class="rounded-xl bg-green-50 px-3 py-2 text-green-700">
                                <div class="text-lg font-bold">{{ dailyTasks.progress.completed }}</div>
                                <div class="text-[11px]">{{ localize('Done', 'منجزة') }}</div>
                            </div>
                            <div class="rounded-xl bg-red-50 px-3 py-2 text-red-700">
                                <div class="text-lg font-bold">{{ dailyTasks.progress.late }}</div>
                                <div class="text-[11px]">{{ localize('Late', 'متأخرة') }}</div>
                            </div>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4 pt-5">
                    <div class="rounded-2xl border bg-background p-4">
                        <div class="mb-2 flex items-center justify-between text-sm">
                            <span class="font-medium">{{ localize('Daily progress', 'تقدم المهام اليومية') }}</span>
                            <span class="font-bold text-primary">{{ dailyTasks.progress.percentage }}%</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-primary transition-all"
                                :style="{ width: `${Math.min(100, Math.max(0, dailyTasks.progress.percentage))}%` }"
                            />
                        </div>
                    </div>

                    <div v-if="dailyTaskPreview.length === 0" class="rounded-2xl border border-dashed py-8 text-center text-sm text-muted-foreground">
                        {{ localize('No operational tasks for today.', 'لا توجد مهام تشغيلية لهذا اليوم.') }}
                    </div>

                    <div v-else class="grid gap-3 lg:grid-cols-2">
                        <div
                            v-for="task in dailyTaskPreview"
                            :key="task.id"
                            class="rounded-2xl border bg-background p-4 shadow-sm"
                            :style="{ borderInlineStart: `4px solid ${dailyTaskAccent(task)}` }"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0" :class="isRtl ? 'text-right' : ''">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :style="{ background: `${dailyTaskAccent(task)}1A`, color: dailyTaskAccent(task) }"
                                        >
                                            {{ task.task_type_label }}
                                        </span>
                                        <span class="text-xs text-muted-foreground">{{ task.scheduled_time }}</span>
                                        <span v-if="task.is_late" class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">
                                            {{ task.computed_status_label }}
                                        </span>
                                    </div>
                                    <div class="mt-2 font-semibold">{{ task.car?.name || task.title }}</div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ task.client?.name || task.reference || task.description }}
                                    </div>
                                    <div class="mt-1 text-xs text-muted-foreground">
                                        {{ task.location || task.car?.branch_name || localize('No location', 'لا يوجد موقع') }}
                                    </div>
                                </div>
                                <div class="shrink-0 text-xs text-muted-foreground" :class="isRtl ? 'text-left' : 'text-right'">
                                    <div class="font-semibold" :style="{ color: dailyTaskAccent(task) }">{{ task.status_label }}</div>
                                    <div v-if="task.status !== 'completed'">{{ dailyTaskRemainingLabel(task.remaining_minutes, task.is_late) }}</div>
                                    <div v-else>{{ localize('Finished', 'تم الإنجاز') }}</div>
                                </div>
                            </div>
                            <div v-if="task.status !== 'completed'" class="mt-4 flex" :class="isRtl ? 'justify-start' : 'justify-end'">
                                <Button
                                    v-if="task.status === 'in_progress'"
                                    size="sm"
                                    class="bg-primary text-primary-foreground hover:bg-primary/90"
                                    :disabled="dailyTaskActionKey === `complete:${task.id}`"
                                    @click="updateDailyTaskStatus(task, 'complete')"
                                >
                                    {{
                                        dailyTaskActionKey === `complete:${task.id}`
                                            ? localize('Saving...', 'جاري الحفظ...')
                                            : localize('Complete task', 'إنهاء المهمة')
                                    }}
                                </Button>
                                <Button
                                    v-else
                                    size="sm"
                                    variant="outline"
                                    :disabled="dailyTaskActionKey === `start:${task.id}`"
                                    @click="startDailyTask(task)"
                                >
                                    {{
                                        dailyTaskActionKey === `start:${task.id}`
                                            ? localize('Saving...', 'جاري الحفظ...')
                                            : localize('Start task', 'بدء المهمة')
                                    }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-4 xl:grid-cols-3">
                <Card class="border-0 shadow-sm xl:col-span-2">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <Layers class="h-4 w-4 text-primary" />
                                <CardTitle class="text-base">{{ localize('Quick Actions', 'إجراءات سريعة') }}</CardTitle>
                            </div>
                            <p class="text-xs text-muted-foreground" :class="isRtl ? 'text-left' : 'text-right'">
                                {{ localize('Jump straight to the most common tasks.', 'انتقل مباشرة إلى أكثر المهام استخدامًا.') }}
                            </p>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                            <Button
                                v-for="action in quickActions"
                                :key="action.title"
                                as-child
                                variant="outline"
                                class="min-h-28 justify-start whitespace-normal border-muted/60 p-5 transition-colors hover:border-primary/30 hover:bg-primary/5"
                                :class="isRtl ? 'text-right' : 'text-left'"
                            >
                                <Link
                                    :href="action.href"
                                    class="flex w-full min-w-0 items-center gap-4"
                                >
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl" :style="{ background: action.bg }">
                                        <component :is="action.icon" class="h-5 w-5" :style="{ color: action.accent }" />
                                    </div>
                                    <div class="min-w-0 flex-1 overflow-hidden">
                                        <div class="font-semibold">{{ action.title }}</div>
                                        <p class="line-clamp-2 break-words text-sm leading-snug text-muted-foreground">{{ action.description }}</p>
                                    </div>
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-0 shadow-sm">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <Clock class="h-4 w-4 text-primary" />
                                <CardTitle class="text-base">{{ localize('Attention Required', 'يحتاج إجراء') }}</CardTitle>
                            </div>
                            <p class="text-xs text-muted-foreground" :class="isRtl ? 'text-left' : 'text-right'">
                                {{ localize('The items above need the most immediate action.', 'هذه العناصر هي الأكثر حاجة لإجراء سريع.') }}
                            </p>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <Link
                            v-for="item in operationalHighlights"
                            :key="item.title"
                            :href="item.href"
                            class="flex items-center justify-between gap-3 rounded-xl border border-muted/60 px-3 py-2.5 transition-colors hover:border-primary/30 hover:bg-muted/40"
                        >
                            <div class="min-w-0">
                                <div class="font-medium">{{ item.title }}</div>
                                <p class="text-xs text-muted-foreground">{{ item.description }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span
                                    class="inline-flex h-8 min-w-8 items-center justify-center rounded-full px-2 text-sm font-bold"
                                    :style="{ background: `${item.accent}20`, color: item.accent }"
                                >
                                    {{ item.count }}
                                </span>
                                <span class="text-xs text-muted-foreground">{{ localize('Open', 'فتح') }} →</span>
                            </div>
                        </Link>
                    </CardContent>
                </Card>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">
                <Card
                    v-for="card in kpiCards"
                    :key="card.title"
                    class="relative min-h-44 overflow-hidden border-0 shadow-sm transition-shadow hover:shadow-md"
                >
                    <div class="absolute inset-x-0 top-0 h-1 rounded-t-xl" :style="{ background: card.accent }" />
                    <CardHeader class="pb-3 pt-6">
                        <div class="flex items-center justify-between gap-6">
                            <CardTitle class="min-w-0 flex-1 text-[.9rem] font-semibold leading-tight text-muted-foreground">{{ card.title }}</CardTitle>
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :style="{ background: card.bg }">
                                <component :is="card.icon" class="h-5 w-5" :style="{ color: card.accent }" />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent :class="isRtl ? 'pe-14 text-right' : 'ps-6'">
                        <div class="text-3xl font-bold leading-none tracking-tight">{{ card.value }}</div>
                        <p class="mt-3 text-[.9rem] leading-snug text-muted-foreground">{{ card.sub }}</p>
                    </CardContent>
                </Card>
            </div>

            <Card class="border-0 shadow-sm">
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <Clock class="h-4 w-4 text-primary" />
                            <CardTitle class="text-base">{{ localize('Expiring Car Documents', 'وثائق السيارات القريبة من الانتهاء') }}</CardTitle>
                        </div>
                        <div class="flex flex-col gap-2" :class="isRtl ? 'items-start text-left' : 'items-end text-right'">
                            <Link href="/admin/cars" class="inline-flex items-center rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90">
                                {{ localize('Review cars', 'مراجعة السيارات') }} →
                            </Link>
                            <p class="text-xs text-muted-foreground">{{ localize('Documents that expire within the next 10 days.', 'الوثائق التي تنتهي خلال العشرة أيام القادمة.') }}</p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="expiringCarDocuments.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        {{ localize('No car documents are expiring soon.', 'لا توجد وثائق سيارات ستنتهي قريبًا.') }}
                    </div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Type', 'النوع') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Car', 'السيارة') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Expiry Date', 'تاريخ الانتهاء') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Remaining', 'المتبقي') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-left' : 'text-right'">{{ localize('Action', 'الإجراء') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="document in expiringCarDocuments"
                                :key="document.id"
                                class="border-b last:border-0 transition-colors hover:bg-muted/40"
                            >
                                <td class="px-4 py-3 font-medium">{{ documentTypeLabel(document.type) }}</td>
                                <td class="px-4 py-3" :class="isRtl ? 'text-right' : ''">
                                    <div class="font-medium">{{ document.car_name || localize('Unknown car', 'سيارة غير معروفة') }}</div>
                                    <div v-if="document.license_plate" class="text-xs text-muted-foreground">{{ document.license_plate }}</div>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ fmtDate(document.expiry_date) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                                        {{ daysRemainingLabel(document.days_remaining) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3" :class="isRtl ? 'text-left' : 'text-right'">
                                    <Link :href="document.edit_url" class="inline-flex items-center rounded-md bg-primary px-2.5 py-1 text-xs font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90">
                                        {{ localize('Open', 'فتح') }}
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <Card class="border-0 shadow-sm">
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <Calendar class="h-4 w-4 text-primary" />
                            <CardTitle class="text-base">{{ localize('Contracts Ending Soon', 'العقود المنتهية قريباً') }}</CardTitle>
                        </div>
                        <div class="flex flex-col gap-2" :class="isRtl ? 'items-start text-left' : 'items-end text-right'">
                            <Link href="/admin/contracts" class="inline-flex items-center rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90">
                                {{ localize('View all', 'عرض الكل') }} →
                            </Link>
                            <p class="text-xs text-muted-foreground">{{ localize('Active contracts ending within the next 7 days.', 'العقود النشطة التي ستنتهي خلال الأيام السبعة القادمة.') }}</p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="expiringContracts.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        {{ localize('No contracts are ending soon.', 'لا توجد عقود ستنتهي قريباً.') }}
                    </div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Contract', 'العقد') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Car', 'السيارة') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Client', 'العميل') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('End Date', 'تاريخ النهاية') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Remaining', 'المتبقي') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-left' : 'text-right'">{{ localize('Action', 'الإجراء') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="contract in expiringContracts"
                                :key="contract.id"
                                class="border-b last:border-0 transition-colors hover:bg-muted/40"
                            >
                                <td class="px-4 py-3" :class="isRtl ? 'text-right' : ''">
                                    <div class="font-medium">{{ contract.contract_number }}</div>
                                    <div v-if="contract.reservation_number" class="text-xs text-muted-foreground">
                                        {{ localize('Reservation', 'الحجز') }} {{ contract.reservation_number }}
                                    </div>
                                </td>
                                <td class="px-4 py-3" :class="isRtl ? 'text-right' : ''">
                                    <div class="font-medium">{{ contract.car_name || localize('Unknown car', 'سيارة غير معروفة') }}</div>
                                    <div v-if="contract.license_plate" class="text-xs text-muted-foreground">{{ contract.license_plate }}</div>
                                    <div v-if="contract.branch_name" class="text-xs text-muted-foreground">{{ contract.branch_name }}</div>
                                </td>
                                <td class="px-4 py-3" :class="isRtl ? 'text-right' : ''">
                                    <div class="font-medium">{{ contract.client_name || localize('Unknown client', 'عميل غير معروف') }}</div>
                                    <div v-if="contract.client_email" class="text-xs text-muted-foreground">{{ contract.client_email }}</div>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ fmtDate(contract.end_date) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                                        {{ daysRemainingLabel(contract.days_remaining) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3" :class="isRtl ? 'text-left' : 'text-right'">
                                    <Link :href="contract.show_url" class="inline-flex items-center rounded-md bg-primary px-2.5 py-1 text-xs font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90">
                                        {{ localize('Open', 'فتح') }}
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <Card class="border-0 shadow-sm">
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <RefreshCcw class="h-4 w-4 text-primary" />
                            <CardTitle class="text-base">{{ localize('Forced Extensions', 'التمديد الإجباري') }}</CardTitle>
                        </div>
                        <div class="flex flex-col gap-2" :class="isRtl ? 'items-start text-left' : 'items-end text-right'">
                            <Link href="/admin/contracts" class="inline-flex items-center rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90">
                                {{ localize('View all', 'عرض الكل') }} →
                            </Link>
                            <p class="text-xs text-muted-foreground">{{ localize('Recent office-driven rental extensions and their recorded payments.', 'آخر تمديدات الإيجار التي أجراها المكتب مع تسجيل الدفعات.') }}</p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="recentForcedExtensions.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        {{ localize('No forced extensions yet.', 'لا توجد تمديدات إجبارية حتى الآن.') }}
                    </div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Payment', 'الدفعة') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Contract', 'العقد') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Client', 'العميل') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Car', 'السيارة') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Extra Amount', 'المبلغ الإضافي') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Processed', 'تم تسجيلها') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-left' : 'text-right'">{{ localize('Action', 'الإجراء') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in recentForcedExtensions"
                                :key="item.id"
                                class="border-b last:border-0 transition-colors hover:bg-muted/40"
                            >
                                <td class="px-4 py-3" :class="isRtl ? 'text-right' : ''">
                                    <div class="font-medium">{{ item.payment_number }}</div>
                                    <div class="text-xs text-muted-foreground">{{ item.branch_name || localize('No branch', 'لا يوجد فرع') }}</div>
                                </td>
                                <td class="px-4 py-3" :class="isRtl ? 'text-right' : ''">
                                    <div class="font-medium">{{ item.contract_number || localize('N/A', 'غير متوفر') }}</div>
                                    <div v-if="item.reservation_number" class="text-xs text-muted-foreground">
                                        {{ localize('Reservation', 'الحجز') }} {{ item.reservation_number }}
                                    </div>
                                </td>
                                <td class="px-4 py-3" :class="isRtl ? 'text-right' : ''">
                                    <div class="font-medium">{{ item.client_name || localize('Unknown client', 'عميل غير معروف') }}</div>
                                    <div v-if="item.client_email" class="text-xs text-muted-foreground">{{ item.client_email }}</div>
                                </td>
                                <td class="px-4 py-3" :class="isRtl ? 'text-right' : ''">
                                    <div class="font-medium">{{ item.car_name || localize('Unknown car', 'سيارة غير معروفة') }}</div>
                                    <div v-if="item.license_plate" class="text-xs text-muted-foreground">{{ item.license_plate }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap font-semibold">
                                    {{ fmtCurrency(item.amount) }}
                                </td>
                                <td class="px-4 py-3 text-xs text-muted-foreground">
                                    {{ fmtDate(item.processed_at) }}
                                </td>
                                <td class="px-4 py-3" :class="isRtl ? 'text-left' : 'text-right'">
                                    <Link :href="item.show_url" class="inline-flex items-center rounded-md bg-primary px-2.5 py-1 text-xs font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90">
                                        {{ localize('Open', 'فتح') }}
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <Card class="border-0 shadow-sm">
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <Clock class="h-4 w-4 text-primary" />
                            <CardTitle class="text-base">{{ localize('Pending Violations', 'المخالفات المعلقة') }}</CardTitle>
                        </div>
                        <div class="flex flex-col gap-2" :class="isRtl ? 'items-start text-left' : 'items-end text-right'">
                            <Link href="/admin/car-violations" class="inline-flex items-center rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90">
                                {{ localize('View all', 'عرض الكل') }} →
                            </Link>
                            <p class="text-xs text-muted-foreground">{{ localize('Open violations that still need action.', 'المخالفات المفتوحة التي ما زالت تحتاج إجراء.') }}</p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="recentPendingViolations.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        {{ localize('No pending violations.', 'لا توجد مخالفات معلقة.') }}
                    </div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Number', 'الرقم') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Car', 'السيارة') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Date', 'التاريخ') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-left' : 'text-right'">{{ localize('Amount', 'المبلغ') }}</th>
                                <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-left' : 'text-right'">{{ localize('Action', 'الإجراء') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="violation in recentPendingViolations"
                                :key="violation.id"
                                class="border-b last:border-0 transition-colors hover:bg-muted/40"
                            >
                                <td class="px-4 py-3" :class="isRtl ? 'text-right' : ''">
                                    <div class="font-medium">{{ violation.violation_number }}</div>
                                    <div class="text-xs text-muted-foreground">{{ violation.type }}</div>
                                </td>
                                <td class="px-4 py-3" :class="isRtl ? 'text-right' : ''">
                                    <div class="font-medium">{{ violation.car_name || localize('Unknown car', 'سيارة غير معروفة') }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        <span v-if="violation.license_plate">{{ violation.license_plate }}</span>
                                        <span v-if="violation.branch_name">
                                            <span v-if="violation.license_plate"> • </span>{{ violation.branch_name }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : ''">
                                    {{ fmtDate(violation.violation_date) }}
                                    <div v-if="violation.due_date">{{ localize('Due', 'الاستحقاق') }}: {{ fmtDate(violation.due_date) }}</div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-semibold" :class="isRtl ? 'text-left' : 'text-right'">
                                    {{ fmtCurrency(violation.amount) }}
                                </td>
                                <td class="px-4 py-3" :class="isRtl ? 'text-left' : 'text-right'">
                                    <Link :href="violation.edit_url" class="inline-flex items-center rounded-md bg-primary px-2.5 py-1 text-xs font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90">
                                        {{ localize('Open', 'فتح') }}
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card class="border-0 shadow-sm">
                    <CardHeader>
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-2">
                                <TrendingUp class="h-4 w-4 text-primary" />
                                <CardTitle class="text-base">{{ localize('Monthly Revenue', 'الإيراد الشهري') }}</CardTitle>
                            </div>
                            <p class="text-xs text-muted-foreground" :class="isRtl ? 'text-left' : 'text-right'">{{ localize('Last 6 months', 'آخر 6 أشهر') }}</p>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="flex h-44 items-end gap-2 px-2">
                            <div
                                v-for="item in monthlyRevenue"
                                :key="item.month"
                                class="group flex flex-1 flex-col items-center gap-1"
                            >
                                <div class="relative">
                                    <div
                                        class="absolute -top-8 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-foreground px-2 py-1 text-xs text-background group-hover:block"
                                    >
                                        {{ fmtCurrency(item.revenue) }}
                                    </div>
                                </div>
                                <div
                                    class="w-full rounded-t-md bg-primary/80 transition-all duration-300 hover:bg-primary"
                                    :style="{ height: barHeight(item.revenue) + 'px' }"
                                />
                                <span class="text-center text-[10px] text-muted-foreground">
                                    {{ item.month.split(' ')[0] }}
                                </span>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground" :class="isRtl ? 'text-left' : 'text-right'">
                            {{ localize('Max', 'الحد الأقصى') }} {{ fmtCurrency(maxRevenue) }}
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-0 shadow-sm">
                    <CardHeader>
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-2">
                                <Layers class="h-4 w-4 text-primary" />
                                <CardTitle class="text-base">{{ localize('Reservations by Status', 'الحجوزات حسب الحالة') }}</CardTitle>
                            </div>
                            <p class="text-xs text-muted-foreground" :class="isRtl ? 'text-left' : 'text-right'">{{ localize(':count total', 'الإجمالي :count', { count: props.stats.total_reservations }) }}</p>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex h-5 w-full overflow-hidden rounded-full">
                            <div
                                v-for="seg in statusBarWidths.filter((s) => s.pct > 0)"
                                :key="seg.status"
                                :style="{ width: seg.pct + '%', background: seg.color }"
                                class="transition-all duration-500"
                                :title="`${reservationStatusLabel(seg.status, seg.label)}: ${seg.count}`"
                            />
                            <div v-if="totalResCount === 0" class="w-full rounded-full bg-muted" />
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div
                                v-for="seg in statusBarWidths"
                                :key="seg.status"
                                class="flex items-center justify-between gap-2 rounded-lg border p-2"
                            >
                                <div class="flex items-center gap-2">
                                    <div class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ background: seg.color }" />
                                    <span class="text-xs capitalize">{{ reservationStatusLabel(seg.status, seg.label) }}</span>
                                </div>
                                <span class="text-sm font-semibold">{{ seg.count }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card class="border-0 shadow-sm">
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Car class="h-4 w-4 text-primary" />
                        <CardTitle class="text-base">{{ localize('Fleet Status', 'حالة الأسطول') }}</CardTitle>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-3">
                        <div
                            v-for="fs in fleetStatus"
                            :key="fs.status"
                            class="flex items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-medium transition-colors"
                            :style="{ borderColor: fs.color, color: fs.color, background: `${fs.color}15` }"
                        >
                            <span class="h-2 w-2 rounded-full" :style="{ background: fs.color }" />
                            {{ carStatusLabel(fs.status, fs.label) }}
                            <span class="ml-1 font-bold">{{ fs.count }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card class="border-0 shadow-sm">
                    <CardHeader>
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-2">
                                <Clock class="h-4 w-4 text-primary" />
                                <CardTitle class="text-base">{{ taskTypeMeta.title }} ({{ taskCount }})</CardTitle>
                            </div>
                            <Link href="/admin/reservations" class="inline-flex items-center rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90">
                                {{ localize('View all', 'عرض الكل') }} →
                            </Link>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                v-for="tab in taskTabs"
                                :key="tab.key"
                                type="button"
                                class="rounded-full border px-4 py-1.5 text-xs font-medium transition-colors"
                                :class="tab.key === taskType
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-primary/20 bg-primary/10 text-primary hover:border-primary/50 hover:bg-primary/15'"
                                @click="taskType = tab.key"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="taskLoading" class="py-8 text-center text-sm text-muted-foreground">
                            {{ localize('Loading tasks...', 'جاري تحميل البيانات...') }}
                        </div>
                        <div v-else-if="taskError" class="px-4 py-8 text-center text-sm text-destructive">
                            {{ taskError }}
                        </div>
                        <div v-else-if="taskRows.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                            {{ localize('No tasks for today.', 'لا توجد مهام لليوم.') }}
                        </div>
                        <table v-else class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ taskTypeMeta.numberLabel }}</th>
                                    <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Client', 'العميل') }}</th>
                                    <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Car', 'السيارة') }}</th>
                                    <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ taskTypeMeta.dateLabel }}</th>
                                    <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-left' : 'text-right'">{{ localize('Task', 'النوع', 'کام') }}</th>
                                    <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Status', 'الحالة', 'حالت') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in taskRows" :key="item.id" class="border-b last:border-0 transition-colors hover:bg-muted/40">
                                    <td class="px-4 py-3 font-medium">{{ item.rowNumber }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ item.client_name ?? localize('N/A', 'غير متوفر') }}</div>
                                        <div v-if="item.client_email" class="text-xs text-muted-foreground">{{ item.client_email }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ item.car?.name || localize('N/A', 'غير متوفر') }}</div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ item.car?.license_plate || localize('No plate', 'لا توجد لوحة') }}
                                        </div>
                                        <div v-if="item.car?.branch_name" class="text-xs text-muted-foreground">
                                            {{ item.car.branch_name }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-muted-foreground">
                                        {{ fmtDate(item.rowDate) }}
                                        <div v-if="item.pickup_time && taskType === 'pickup'">
                                            {{ item.pickup_time }}
                                        </div>
                                        <div v-else-if="taskType === 'overdue' && item.days_overdue !== undefined">
                                            {{ localize(':days days overdue', 'متأخر :days يوم', { days: item.days_overdue }) }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 font-semibold" :class="isRtl ? 'text-left' : 'text-right'">
                                        {{ taskTypeLabel(item.task_type, item.task_type_label) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                            :class="item.is_overdue ? 'bg-red-100 text-red-700' : 'bg-muted text-foreground'"
                                        >
                                            {{ item.rowStatus.replace('_', ' ') }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Card class="border-0 shadow-sm">
                    <CardHeader>
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-2">
                                <Car class="h-4 w-4 text-primary" />
                                <CardTitle class="text-base">{{ localize('Top Performing Cars', 'أفضل السيارات أداءً') }}</CardTitle>
                            </div>
                            <Link href="/admin/cars" class="inline-flex items-center rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90">
                                {{ localize('View all', 'عرض الكل') }} →
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="topCars.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                            {{ localize('No car data yet.', 'لا توجد بيانات سيارات حتى الآن.') }}
                        </div>
                        <table v-else class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">#</th>
                                    <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Car', 'السيارة') }}</th>
                                    <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-right' : 'text-left'">{{ localize('Status', 'الحالة') }}</th>
                                    <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-left' : 'text-right'">{{ localize('Price/Day', 'السعر/اليوم') }}</th>
                                    <th class="px-4 py-2 text-xs text-muted-foreground" :class="isRtl ? 'text-left' : 'text-right'">{{ localize('Bookings', 'الحجوزات') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(car, idx) in topCars"
                                    :key="car.id"
                                    class="border-b last:border-0 transition-colors hover:bg-muted/40"
                                >
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold"
                                            :class="idx === 0 ? 'bg-yellow-400/20 text-yellow-600'
                                                : idx === 1 ? 'bg-gray-300/20 text-gray-600'
                                                : idx === 2 ? 'bg-orange-400/20 text-orange-600'
                                                : 'bg-muted text-muted-foreground'"
                                        >
                                            {{ idx + 1 }}
                                        </span>
                                    </td>
                                    <td class="max-w-[130px] truncate px-4 py-3 font-medium">{{ car.name }}</td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                            :style="{ background: `${car.status_color}20`, color: car.status_color }"
                                        >
                                            {{ carStatusLabel(car.status, car.status_label) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3" :class="isRtl ? 'text-left' : 'text-right'">{{ fmtCurrency(car.price_per_day) }}</td>
                                    <td class="px-4 py-3 font-bold" :class="isRtl ? 'text-left' : 'text-right'">{{ car.completed_count }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </main>
    </AdminLayout>
</template>
