<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    reports: {
        data: Array<{
            id: number;
            report_number: string;
            amount: number | string;
            currency?: string;
            created_at?: string | null;
            return_report_url: string;
            client?: { id: number; name: string; email: string } | null;
            reservation?: { id: number; reservation_number: string } | null;
            contract?: { id: number; contract_number: string } | null;
            payment?: { id: number; payment_number: string; status: string } | null;
            car?: { id: number; name: string; license_plate?: string | null } | null;
            branch_name?: string | null;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search?: string; branch_id?: number | null };
    branches: Array<{ id: number; name: string }>;
    canAccessAllBranches: boolean;
    summary: {
        clients_count: number;
        reports_count: number;
        total_outstanding: number | string;
    };
    canViewFinancials: boolean;
    currency: { symbol: string; code: string };
}>();

const { locale, t } = useTrans();
const page = usePage<any>();
const search = ref(props.filters?.search || '');
const branchFilter = ref(props.filters?.branch_id ? String(props.filters.branch_id) : 'all');
const authPermissions = computed<string[]>(() =>
    Array.isArray(page.props?.auth?.permissions) ? page.props.auth.permissions : [],
);
const hasFinancialAccess = computed(() => !!props.canViewFinancials);
const hasPaymentsAccess = computed(() => authPermissions.value.includes('tenant-payments.view'));
const translationRoot = 'dashboard.admin.payments.debtors';
const translate = (key: string) => t(`${translationRoot}.${key}`);

function adminUrl(path: string) {
    const match = String(page.url || '').match(/^\/([a-z]{2})(?=\/admin)/);
    const prefix = match ? `/${match[1]}` : '';
    return `${prefix}/admin${path}`;
}

function debtorsUrl() {
    return adminUrl('/payments/debtors');
}

function doSearch() {
    router.get(
        debtorsUrl(),
        {
            search: search.value,
            branch_id: branchFilter.value === 'all' ? null : Number(branchFilter.value),
        },
        {
            preserveState: true,
            replace: true,
        },
    );
}

function fmtMoney(n?: number | string, currencyCode?: string) {
    if (!hasFinancialAccess.value) {
        return '*******';
    }

    const v = Number(n ?? 0);
    const currency = currencyCode || props.currency?.code || '';

    return `${props.currency?.symbol || ''}${v.toFixed(2)}${currency ? ` ${currency}` : ''}`;
}
</script>

<template>
    <Head :title="translate('title')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ translate('title') }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ translate('description') }}
                    </p>
                </div>

                <Link v-if="hasPaymentsAccess" :href="adminUrl('/payments')">
                    <Button variant="outline">{{ translate('all_payments') }}</Button>
                </Link>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg border bg-white p-4">
                    <div class="text-sm text-muted-foreground">{{ translate('debtor_clients') }}</div>
                    <div class="mt-2 text-2xl font-semibold">{{ props.summary.clients_count }}</div>
                </div>
                <div class="rounded-lg border bg-white p-4">
                    <div class="text-sm text-muted-foreground">{{ translate('unpaid_reports') }}</div>
                    <div class="mt-2 text-2xl font-semibold">{{ props.summary.reports_count }}</div>
                </div>
                <div class="rounded-lg border bg-white p-4">
                    <div class="text-sm text-muted-foreground">{{ translate('total_outstanding') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-red-700">
                        {{ fmtMoney(props.summary.total_outstanding) }}
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Input
                    v-model="search"
                    :placeholder="translate('search_placeholder')"
                    class="max-w-md"
                    @keyup.enter="doSearch"
                />
                <Button @click="doSearch">{{ translate('search') }}</Button>
                <select
                    v-if="props.canAccessAllBranches"
                    v-model="branchFilter"
                    class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                    @change="doSearch"
                >
                    <option value="all">{{ translate('all_branches') }}</option>
                    <option v-for="branch in props.branches" :key="branch.id" :value="String(branch.id)">
                        {{ branch.name }}
                    </option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-md border bg-white">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                {{ translate('table.client') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                {{ translate('table.report') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                {{ translate('table.reservation_contract') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                {{ translate('table.car') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                {{ translate('table.branch') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                {{ translate('table.amount') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                {{ translate('table.action') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="report in props.reports.data" :key="report.id">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ report.client?.name || '-' }}</div>
                                <div class="text-xs text-muted-foreground">{{ report.client?.email }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ report.report_number }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ report.created_at ? new Date(report.created_at).toLocaleString(locale === 'ar' ? 'ar' : 'en-US') : '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ report.reservation?.reservation_number || '-' }}</div>
                                <div class="text-xs text-muted-foreground">{{ report.contract?.contract_number || '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ report.car?.name || '-' }}</div>
                                <div class="text-xs text-muted-foreground">{{ report.car?.license_plate }}</div>
                            </td>
                            <td class="px-4 py-3">{{ report.branch_name || '-' }}</td>
                            <td class="px-4 py-3 font-semibold text-red-700">
                                {{ fmtMoney(report.amount, report.currency) }}
                            </td>
                            <td class="px-4 py-3">
                                <Link :href="report.return_report_url" class="text-blue-600 hover:underline">
                                    {{ translate('open_return_report') }}
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="props.reports.data.length === 0">
                            <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                                {{ translate('empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="props.reports.links?.length" class="flex gap-2">
                <Link
                    v-for="(link, i) in props.reports.links"
                    :key="i"
                    :href="link.url || ''"
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
    </AdminLayout>
</template>
