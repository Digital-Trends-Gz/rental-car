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
    currency: { symbol: string; code: string };
}>();
const { t, locale } = useTrans();
const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);
const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const branchFilter = ref(props.filters?.branch_id ? String(props.filters.branch_id) : 'all');
const isArabic = computed(() => page.props.locale === 'ar');
const localize = (en: string, ar: string) => (isArabic.value ? ar : en);

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
    const v = Number(n ?? 0);
    return `${props.currency.symbol}${v.toFixed(2)}`;
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
                <Link :href="adminUrl('/payments/debtors')">
                    <Button variant="outline">{{ localize('Debtors', 'المديونين') }}</Button>
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
                        <option value="all">All branches</option>
                        <option v-for="branch in props.branches" :key="branch.id" :value="String(branch.id)">
                            {{ branch.name }}
                        </option>
                    </select>
                    <select
                        v-model="statusFilter"
                        class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                        @change="doSearch"
                    >
                        <option value="all">{{ t('dashboard.common.all') }} status</option>
                        <option v-for="(status, key) in props.statuses" :key="key" :value="key">
                            {{ status.label }} ({{ status.count }})
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
                                {{ fmtMoney(p.amount) }}
                            </td>
                            <td class="px-4 py-3">{{ p.payment_method }}</td>
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
                                    {{ p.status }}
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
