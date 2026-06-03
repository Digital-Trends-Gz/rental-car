<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useTrans } from '@/composables/useTrans';

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
    currency: { symbol: string; code: string };
}>();

const { locale } = useTrans();
const page = usePage<any>();
const search = ref(props.filters?.search || '');
const branchFilter = ref(props.filters?.branch_id ? String(props.filters.branch_id) : 'all');
const isArabic = computed(() => page.props.locale === 'ar');

const localize = (en: string, ar: string) => (isArabic.value ? ar : en);

function adminUrl(path: string) {
    const prefix = String(page.url || '').startsWith('/ar/') ? '/ar' : String(page.url || '').startsWith('/fr/') ? '/fr' : '';
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
    const v = Number(n ?? 0);
    const currency = currencyCode || props.currency?.code || '';

    return `${props.currency?.symbol || ''}${v.toFixed(2)}${currency ? ` ${currency}` : ''}`;
}
</script>

<template>
    <Head :title="localize('Debtors', 'المديونين')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Debtors', 'المديونين') }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ localize('Clients with unpaid return report charges.', 'العملاء الذين لديهم رسوم رجوع غير مدفوعة.') }}
                    </p>
                </div>

                <Link :href="adminUrl('/payments')">
                    <Button variant="outline">{{ localize('All Payments', 'كل الدفعات') }}</Button>
                </Link>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg border bg-white p-4">
                    <div class="text-sm text-muted-foreground">{{ localize('Debtor Clients', 'عدد العملاء المديونين') }}</div>
                    <div class="mt-2 text-2xl font-semibold">{{ props.summary.clients_count }}</div>
                </div>
                <div class="rounded-lg border bg-white p-4">
                    <div class="text-sm text-muted-foreground">{{ localize('Unpaid Reports', 'تقارير غير مدفوعة') }}</div>
                    <div class="mt-2 text-2xl font-semibold">{{ props.summary.reports_count }}</div>
                </div>
                <div class="rounded-lg border bg-white p-4">
                    <div class="text-sm text-muted-foreground">{{ localize('Total Outstanding', 'إجمالي المديونية') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-red-700">
                        {{ fmtMoney(props.summary.total_outstanding) }}
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Input
                    v-model="search"
                    :placeholder="localize('Search by client, report, contract, or reservation', 'بحث باسم العميل أو التقرير أو العقد أو الحجز')"
                    class="max-w-md"
                    @keyup.enter="doSearch"
                />
                <Button @click="doSearch">{{ localize('Search', 'بحث') }}</Button>
                <select
                    v-if="props.canAccessAllBranches"
                    v-model="branchFilter"
                    class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                    @change="doSearch"
                >
                    <option value="all">{{ localize('All branches', 'كل الفروع') }}</option>
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
                                {{ localize('Client', 'العميل') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                {{ localize('Report', 'التقرير') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                {{ localize('Reservation / Contract', 'الحجز / العقد') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                {{ localize('Car', 'السيارة') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                {{ localize('Branch', 'الفرع') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                {{ localize('Amount', 'المبلغ') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                {{ localize('Action', 'إجراء') }}
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
                                    {{ localize('Open return report', 'فتح تقرير الرجوع') }}
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="props.reports.data.length === 0">
                            <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                                {{ localize('No unpaid return charges found.', 'لا توجد مديونيات رجوع غير مدفوعة.') }}
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
