<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useTrans } from '@/composables/useTrans';
import { index } from '@/routes/admin/payments';
import { show as showReservation } from '@/routes/admin/reservations';

const props = defineProps<{
    payments: {
        data: Array<{
            id: number;
            payment_number: string;
            amount: number | string;
            currency?: string;
            base_amount?: number | string;
            base_currency?: string;
            exchange_rate?: number | string | null;
            has_currency_conversion?: boolean;
            payment_method: string;
            status: string;
            processed_at?: string | null;
            user?: { id: number; name: string; email: string } | null;
            reservation?: { id: number; reservation_number: string } | null;
            branch_name?: string | null;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    statuses: Record<string, { label: string; count: number; color: string }>;
    filters: { search?: string; status?: string; branch_id?: number | null };
    branches: Array<{ id: number; name: string }>;
    canAccessAllBranches: boolean;
    canViewFinancials: boolean;
    currency: { symbol: string; code: string };
}>();
const { t, raw, locale } = useTrans();
const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);
const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const branchFilter = ref(props.filters?.branch_id ? String(props.filters.branch_id) : 'all');
const authPermissions = computed<string[]>(() =>
    Array.isArray(page.props?.auth?.permissions) ? page.props.auth.permissions : [],
);
const hasFinancialAccess = computed(() => !!props.canViewFinancials);
const hasDebtorsAccess = computed(() => authPermissions.value.includes('tenant-view-debtors'));
const translateStatus = (status: string, fallback?: string) =>
    raw(`dashboard.admin.payments.index.statuses.${status}`, fallback || status);
const translatePaymentMethod = (method: string, fallback?: string) =>
    raw(`dashboard.admin.payments.index.payment_methods.${method}`, fallback || method);

function adminUrl(path: string) {
    const currentUrl = String(page.url || '');
    const prefix = currentUrl.startsWith('/ar/') ? '/ar' : currentUrl.startsWith('/fr/') ? '/fr' : '';

    return `${prefix}/admin${path}`;
}

function doSearch() {
    if (!subdomain.value) return;

    router.get(index(subdomain.value).url, {
        search: search.value,
        status: statusFilter.value === 'all' ? null : statusFilter.value,
        branch_id: branchFilter.value === 'all' ? null : Number(branchFilter.value),
    }, {
        preserveState: true,
        replace: true,
    });
}

function fmtMoney(n?: number | string) {
    if (!hasFinancialAccess.value) {
        return '*******';
    }

    const v = Number(n ?? 0);
    return `${props.currency.symbol}${v.toFixed(2)}`;
}

function fmtCurrencyAmount(n?: number | string, currency?: string) {
    if (!hasFinancialAccess.value) {
        return '*******';
    }

    const v = Number(n ?? 0);
    return `${currency || props.currency.code || ''} ${v.toFixed(2)}`.trim();
}

function fmtRate(n?: number | string | null) {
    const v = Number(n ?? 1);
    return Number.isFinite(v) ? v.toFixed(8).replace(/\.?0+$/, '') : '1';
}

// Generate status colors based on the colors from the backend
const statusColors = computed(() => {
    const colors: Record<string, { bg: string; text: string; dot: string }> =
        {};

    for (const [status, data] of Object.entries(props.statuses || {})) {
        // Convert hex to RGB for the background with opacity
        const hex = data.color.replace('#', '');
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);

        colors[status] = {
            bg: `rgba(${r}, ${g}, ${b}, 0.1)`,
            text: `text-[${data.color}]`,
            dot: data.color,
        };
    }

    return colors;
});

const getStatusColor = (status: string) => {
    return (
        statusColors.value[status] || {
            bg: 'rgba(107, 114, 128, 0.1)',
            text: 'text-gray-500',
            dot: '#6B7280',
        }
    );
};
</script>

<template>
    <Head :title="t('dashboard.admin.payments.index.head_title')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ t('dashboard.admin.payments.index.title') }}</h1>
                <Link v-if="hasDebtorsAccess" :href="adminUrl('/payments/debtors')">
                    <Button variant="outline">{{ t('dashboard.admin.payments.index.debtors') }}</Button>
                </Link>
            </div>

            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-2">
                    <Input
                        v-model="search"
                        :placeholder="t('dashboard.common.search')"
                        class="max-w-md"
                        @keyup.enter="doSearch"
                    />
                    <Button @click="doSearch">{{ t('dashboard.common.search') }}</Button>
                    <select
                        v-if="props.canAccessAllBranches"
                        v-model="branchFilter"
                        class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                        @change="doSearch"
                    >
                        <option value="all">{{ t('dashboard.admin.payments.index.all_branches') }}</option>
                        <option v-for="branch in props.branches" :key="branch.id" :value="String(branch.id)">
                            {{ branch.name }}
                        </option>
                    </select>
                    <select
                        v-model="statusFilter"
                        class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                        @change="doSearch"
                    >
                        <option value="all">{{ t('dashboard.admin.payments.index.all_statuses') }}</option>
                        <option v-for="(status, key) in props.statuses" :key="key" :value="key">
                            {{ translateStatus(String(key), status.label) }} ({{ status.count }})
                        </option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                #
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{ t('dashboard.common.client') }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{ t('dashboard.common.reservation') }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{ t('dashboard.admin.employees.table.branch') }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{ t('dashboard.common.amount') }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{ t('dashboard.common.method') }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{ t('dashboard.common.status') }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{ t('dashboard.common.processed') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="p in props.payments.data" :key="p.id">
                            <td class="px-4 py-3">{{ p.payment_number }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ p.user?.name || '—' }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ p.user?.email }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    v-if="p.reservation && subdomain"
                                    :href="showReservation([subdomain, p.reservation.id]).url"
                                    class="text-blue-600 hover:underline"
                                >
                                    {{ p.reservation.reservation_number }}
                                </Link>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3">{{ p.branch_name || t('dashboard.admin.employees.table.no_branch') }}</td>
                            <td class="px-4 py-3 font-semibold text-green-800">
                                <div>{{ fmtCurrencyAmount(p.amount, p.currency) }}</div>
                                <div
                                    v-if="p.has_currency_conversion"
                                    class="mt-1 max-w-[220px] text-xs font-normal leading-5 text-muted-foreground"
                                >
                                    <div>
                                        {{ t('dashboard.admin.payments.index.converted_to') }}
                                        <span class="font-medium text-gray-700">
                                            {{ fmtCurrencyAmount(p.base_amount, p.base_currency) }}
                                        </span>
                                    </div>
                                    <div>
                                        {{ t('dashboard.admin.payments.index.rate') }}:
                                        1 {{ p.currency }} =
                                        {{ fmtRate(p.exchange_rate) }}
                                        {{ p.base_currency }}
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ translatePaymentMethod(p.payment_method) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-medium"
                                    :style="{
                                        backgroundColor: getStatusColor(
                                            p.status,
                                        ).bg,
                                        color: getStatusColor(p.status).text,
                                    }"
                                >
                                    <span
                                        class="size-2 rounded-full"
                                        :style="{
                                            backgroundColor: getStatusColor(
                                                p.status,
                                            ).dot,
                                        }"
                                    />
                                    {{ translateStatus(p.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                {{
                                    p.processed_at
                                        ? new Date(
                                              p.processed_at,
                                          ).toLocaleString(locale === 'ar' ? 'ar' : 'en-US')
                                        : '—'
                                }}
                            </td>
                        </tr>
                        <tr v-if="props.payments.data.length === 0">
                            <td
                                colspan="8"
                                class="px-4 py-6 text-center text-gray-500"
                            >
                                {{ t('dashboard.admin.payments.index.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="props.payments.links?.length" class="flex gap-2">
                <Link
                    v-for="(link, i) in props.payments.links"
                    :key="i"
                    :href="link.url || ''"
                    :class="[
                        'rounded px-3 py-1 text-sm',
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-gray-100 text-gray-700',
                        !link.url && 'pointer-events-none opacity-50',
                    ]"
                >
                    <span v-html="link.label" />
                </Link>
            </nav>
        </main>
    </AdminLayout>
</template>
