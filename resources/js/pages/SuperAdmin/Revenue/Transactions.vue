<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

type RevenueRow = {
    row_id: string;
    source: 'booking' | 'subscription';
    source_id: number;
    tenant_id: number | null;
    tenant_name: string | null;
    tenant_slug: string | null;
    user_name: string | null;
    user_email: string | null;
    status: string;
    payment_method: string;
    amount: number;
    currency: string;
    reference: string;
    context_reference: string;
    plan_name: string;
    paid_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    rows: {
        data: RevenueRow[];
        links: PaginationLink[];
        total: number;
        from: number | null;
        to: number | null;
    };
    summary: {
        total_rows: number;
        booking_revenue: number;
        subscription_revenue: number;
        total_revenue: number;
    };
    revenueByCurrency: Array<{
        currency: string;
        revenue: number;
    }>;
    statuses: string[];
    tenants: Array<{ id: number; name: string; slug: string }>;
    filters: {
        search?: string | null;
        source?: string | null;
        status?: string | null;
        tenant_id?: number | null;
        date_from?: string | null;
        date_to?: string | null;
    };
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string, ur: string = en) => (locale.value === 'ar' ? ar : locale.value === 'ur' ? ur : en);

const filters = reactive({
    search: props.filters.search ?? '',
    source: props.filters.source ?? 'all',
    status: props.filters.status ?? '',
    tenant_id: props.filters.tenant_id ? String(props.filters.tenant_id) : '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

const numberLocale = computed(() => (locale.value === 'ar' ? 'ar' : 'en-US'));

const queryPayload = computed(() => ({
    search: filters.search || undefined,
    source: filters.source && filters.source !== 'all' ? filters.source : undefined,
    status: filters.status || undefined,
    tenant_id: filters.tenant_id || undefined,
    date_from: filters.date_from || undefined,
    date_to: filters.date_to || undefined,
}));

const applyFilters = () => {
    router.get('/superadmin/revenue/transactions', queryPayload.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filters.search = '';
    filters.source = 'all';
    filters.status = '';
    filters.tenant_id = '';
    filters.date_from = '';
    filters.date_to = '';
    applyFilters();
};

const exportCsvUrl = computed(() => {
    const params = new URLSearchParams();
    Object.entries(queryPayload.value).forEach(([key, value]) => {
        if (value !== undefined && value !== null && String(value).trim() !== '') {
            params.set(key, String(value));
        }
    });

    const query = params.toString();
    return `/superadmin/revenue/transactions/export/csv${query ? `?${query}` : ''}`;
});

const exportPdfUrl = computed(() => {
    const params = new URLSearchParams();
    Object.entries(queryPayload.value).forEach(([key, value]) => {
        if (value !== undefined && value !== null && String(value).trim() !== '') {
            params.set(key, String(value));
        }
    });

    const query = params.toString();
    return `/superadmin/revenue/transactions/export/pdf${query ? `?${query}` : ''}`;
});

const formatAmount = (amount: number, currency: string) => {
    const resolvedCurrency = (currency || 'USD').toUpperCase();
    try {
        return new Intl.NumberFormat(numberLocale.value, {
            style: 'currency',
            currency: resolvedCurrency,
        }).format(Number(amount ?? 0));
    } catch {
        return `${Number(amount ?? 0).toFixed(2)} ${resolvedCurrency}`;
    }
};

const formatDateTime = (value: string | null) => {
    if (!value) return '-';
    return new Date(value).toLocaleString(numberLocale.value, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const sourceLabel = (source: string) => {
    return source === 'booking'
        ? localize('Booking Payment', 'دفعة الحجز', 'بکنگ ادائیگی')
        : localize('Subscription', 'الاشتراك', 'سبسکرپشن');
};

const statusClass = (status: string) => {
    const key = String(status || '').toLowerCase();
    if (['completed', 'paid', 'active', 'trialing'].includes(key)) return 'bg-green-100 text-green-700';
    if (['pending', 'incomplete', 'past_due'].includes(key)) return 'bg-amber-100 text-amber-700';
    if (['failed', 'canceled', 'cancelled', 'unpaid', 'incomplete_expired'].includes(key)) return 'bg-red-100 text-red-700';
    return 'bg-gray-100 text-gray-700';
};
</script>

<template>
    <Head :title="localize('Financial Transactions Report', 'تقرير المعاملات المالية', 'مالی لین دین کی رپورٹ')" />

    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Financial Transactions Report', 'تقرير المعاملات المالية', 'مالی لین دین کی رپورٹ') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Money, payment, and subscription report across all tenant websites.', 'تقرير عن الأموال والمدفوعات والاشتراكات عبر جميع مواقع المستأجرين.', 'تمام کرایہ دار ویب سائٹس کے مالی، ادائیگی، اور سبسکرپشن ریکارڈ کی رپورٹ۔') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="exportCsvUrl">
                        <Button type="button" variant="outline">{{ localize('Export Excel (CSV)', 'تصدير Excel (CSV)', 'ایکسل برآمد کریں (CSV)') }}</Button>
                    </a>
                    <a :href="exportPdfUrl">
                        <Button type="button">{{ localize('Export PDF', 'تصدير PDF', 'PDF برآمد کریں') }}</Button>
                    </a>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-md border p-4">
                    <div class="text-xs uppercase text-muted-foreground">{{ localize('Rows', 'الصفوف', 'قطاریں') }}</div>
                    <div class="mt-1 text-2xl font-semibold">{{ props.summary.total_rows }}</div>
                </div>
                <div class="rounded-md border p-4">
                    <div class="text-xs uppercase text-muted-foreground">{{ localize('Booking Revenue', 'إيرادات الحجوزات', 'بکنگ آمدنی') }}</div>
                    <div class="mt-1 text-2xl font-semibold">{{ formatAmount(props.summary.booking_revenue, 'USD') }}</div>
                </div>
                <div class="rounded-md border p-4">
                    <div class="text-xs uppercase text-muted-foreground">{{ localize('Subscription Revenue', 'إيرادات الاشتراكات', 'سبسکرپشن آمدنی') }}</div>
                    <div class="mt-1 text-2xl font-semibold">{{ formatAmount(props.summary.subscription_revenue, 'USD') }}</div>
                </div>
                <div class="rounded-md border p-4">
                    <div class="text-xs uppercase text-muted-foreground">{{ localize('Total Revenue', 'إجمالي الإيرادات', 'کل آمدنی') }}</div>
                    <div class="mt-1 text-2xl font-semibold">{{ props.summary.total_revenue.toFixed(2) }}</div>
                    <div class="text-xs text-muted-foreground">{{ localize('Mixed currencies total', 'إجمالي العملات المتعددة', 'مختلف کرنسیوں کا مجموعی') }}</div>
                </div>
            </div>

            <div v-if="props.revenueByCurrency?.length" class="rounded-md border p-4">
                <div class="mb-2 text-sm font-medium">{{ localize('Revenue by Currency', 'الإيرادات حسب العملة', 'کرنسی کے لحاظ سے آمدنی') }}</div>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="item in props.revenueByCurrency"
                        :key="item.currency"
                        class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-medium"
                    >
                        {{ item.currency }}: {{ Number(item.revenue || 0).toFixed(2) }}
                    </span>
                </div>
            </div>

            <form @submit.prevent="applyFilters" class="grid gap-3 rounded-md border p-4 md:grid-cols-6">
                <input
                    v-model="filters.search"
                    type="text"
                    :placeholder="localize('Search tenant, user, reference...', 'ابحث عن المستأجر أو المستخدم أو المرجع...', 'کرایہ دار، صارف، یا حوالہ تلاش کریں...')"
                    class="h-10 rounded-md border px-3 text-sm md:col-span-2"
                />

                <select v-model="filters.source" class="h-10 rounded-md border px-3 text-sm">
                    <option value="all">{{ localize('All sources', 'كل المصادر', 'تمام ذرائع') }}</option>
                    <option value="booking">{{ localize('Booking payments', 'مدفوعات الحجوزات', 'بکنگ ادائیگیاں') }}</option>
                    <option value="subscription">{{ localize('Subscriptions', 'الاشتراكات', 'سبسکرپشنز') }}</option>
                </select>

                <select v-model="filters.status" class="h-10 rounded-md border px-3 text-sm">
                    <option value="">{{ localize('All statuses', 'كل الحالات', 'تمام حالتیں') }}</option>
                    <option v-for="status in props.statuses" :key="status" :value="status">
                        {{ status }}
                    </option>
                </select>

                <select v-model="filters.tenant_id" class="h-10 rounded-md border px-3 text-sm">
                    <option value="">{{ localize('All tenants', 'كل المستأجرين', 'تمام کرایہ دار') }}</option>
                    <option v-for="tenant in props.tenants" :key="tenant.id" :value="String(tenant.id)">
                        {{ tenant.name }} ({{ tenant.slug }})
                    </option>
                </select>

                <div class="flex items-center gap-2 md:col-span-6">
                    <input v-model="filters.date_from" type="date" class="h-10 rounded-md border px-3 text-sm" />
                    <input v-model="filters.date_to" type="date" class="h-10 rounded-md border px-3 text-sm" />
                    <Button type="submit">{{ localize('Search', 'بحث', 'تلاش') }}</Button>
                    <Button type="button" variant="outline" @click="resetFilters">{{ localize('Reset', 'إعادة ضبط', 'ری سیٹ') }}</Button>
                </div>
            </form>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-border">
                    <thead>
                        <tr class="bg-muted/30">
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ localize('Source', 'المصدر', 'ذریعہ') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ localize('Date', 'التاريخ', 'تاریخ') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ localize('Tenant', 'المستأجر', 'کرایہ دار') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ localize('User', 'المستخدم', 'صارف') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ localize('Status', 'الحالة', 'حالت') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ localize('Method', 'الطريقة', 'طریقہ') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ localize('Amount', 'المبلغ', 'رقم') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ localize('Reference', 'المرجع', 'حوالہ') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ localize('Context', 'السياق', 'سیاق') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ localize('Plan', 'الخطة', 'پلان') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-white">
                        <tr v-for="row in props.rows.data" :key="row.row_id" class="hover:bg-muted/20">
                            <td class="px-4 py-3 text-sm">
                                <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium">
                                    {{ sourceLabel(row.source) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ formatDateTime(row.paid_at) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="font-medium">{{ row.tenant_name || '-' }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.tenant_slug || '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div class="font-medium">{{ row.user_name || '-' }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.user_email || '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClass(row.status)">
                                {{ row.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ row.payment_method || '-' }}</td>
                            <td class="px-4 py-3 text-sm font-medium">{{ formatAmount(row.amount, row.currency) }}</td>
                            <td class="px-4 py-3 text-xs font-mono text-muted-foreground">{{ row.reference || '-' }}</td>
                            <td class="px-4 py-3 text-sm">{{ row.context_reference || '-' }}</td>
                            <td class="px-4 py-3 text-sm">{{ row.plan_name || '-' }}</td>
                        </tr>
                        <tr v-if="props.rows.data.length === 0">
                            <td colspan="10" class="px-4 py-8 text-center text-sm text-muted-foreground">
                                {{ localize('No records found.', 'لا توجد سجلات.', 'کوئی ریکارڈ نہیں ملا۔') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="props.rows.links?.length" class="flex flex-wrap gap-2">
                <Link
                    v-for="(link, index) in props.rows.links"
                    :key="index"
                    :href="link.url || '#'"
                    preserve-scroll
                    :class="[
                        'rounded px-3 py-1 text-sm',
                        link.active ? 'bg-primary text-primary-foreground' : 'bg-gray-100 text-gray-700',
                        !link.url && 'pointer-events-none opacity-50',
                    ]"
                >
                    <span v-html="link.label" />
                </Link>
            </nav>
        </main>
    </SuperAdminLayout>
</template>
