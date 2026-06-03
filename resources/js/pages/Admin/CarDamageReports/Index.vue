<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    reports: {
        data: Array<{
            id: number;
            report_number: string;
            car: string;
            report_type: string;
            report_type_label: string;
            status: string;
            inspected_at: string | null;
            branch: string;
            contract_number: string | null;
            reservation_number: string | null;
            items_count: number;
            total_quantity: number;
            total_estimated_cost: number;
            edit_url: string;
            destroy_url: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    reportTypes: Array<{ value: string; label: string }>;
    branches: Array<{ id: number; name: string }>;
    cars: Array<{ id: number; label: string }>;
    canAccessAllBranches: boolean;
    filters: {
        search?: string;
        report_type?: string;
        branch_id?: number | null;
        car_id?: number | null;
    };
    indexUrl: string;
    contractsIndexUrl: string;
}>();

const search = ref(props.filters?.search ?? '');
const reportType = ref(props.filters?.report_type ?? 'all');
const branchId = ref(
    props.filters?.branch_id ? String(props.filters.branch_id) : '',
);
const carId = ref(props.filters?.car_id ? String(props.filters.car_id) : '');
const hasRows = computed(() => props.reports.data.length > 0);
const { t } = useTrans();

function doSearch() {
    router.get(
        props.indexUrl,
        {
            search: search.value || undefined,
            report_type:
                reportType.value === 'all' ? undefined : reportType.value,
            branch_id: branchId.value || undefined,
            car_id: carId.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

watch(search, (value, oldValue) => {
    if (value === '' && oldValue !== '') {
        doSearch();
    }
});

function deleteReport(url: string, numberText: string) {
    if (
        !window.confirm(
            t('dashboard.admin.damage_reports.index.delete_confirm', {
                number: numberText,
            }),
        )
    ) {
        return;
    }

    router.delete(url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('dashboard.admin.damage_reports.index.head_title')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">
                        {{ t('dashboard.admin.damage_reports.index.title') }}
                    </h1>
                    <p class="text-sm text-slate-500">
                        {{ t('dashboard.admin.damage_reports.index.subtitle') }}
                    </p>
                </div>
                <Link :href="contractsIndexUrl">
                    <Button>{{
                        t('dashboard.admin.damage_reports.index.open_contracts')
                    }}</Button>
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                <Input
                    v-model="search"
                    class="md:col-span-2"
                    :placeholder="
                        t('dashboard.admin.damage_reports.index.search_placeholder')
                    "
                    @keyup.enter="doSearch"
                />

                <select
                    v-model="reportType"
                    class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                    <option value="all">
                        {{
                            t(
                                'dashboard.admin.damage_reports.index.all_report_types',
                            )
                        }}
                    </option>
                    <option
                        v-for="item in reportTypes"
                        :key="item.value"
                        :value="item.value"
                    >
                        {{ item.label }}
                    </option>
                </select>

                <select
                    v-if="canAccessAllBranches"
                    v-model="branchId"
                    class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                    <option value="">
                        {{
                            t(
                                'dashboard.admin.damage_reports.index.all_branches',
                            )
                        }}
                    </option>
                    <option
                        v-for="branch in branches"
                        :key="branch.id"
                        :value="String(branch.id)"
                    >
                        {{ branch.name }}
                    </option>
                </select>

                <select
                    v-model="carId"
                    class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                    <option value="">
                        {{
                            t('dashboard.admin.damage_reports.index.all_cars')
                        }}
                    </option>
                    <option
                        v-for="car in cars"
                        :key="car.id"
                        :value="String(car.id)"
                    >
                        {{ car.label }}
                    </option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <Button @click="doSearch">{{ t('dashboard.common.search') }}</Button>
                <Button
                    variant="outline"
                    @click="
                        search = '';
                        reportType = 'all';
                        branchId = '';
                        carId = '';
                        doSearch();
                    "
                >
                    {{ t('dashboard.admin.damage_reports.index.clear') }}
                </Button>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'dashboard.admin.damage_reports.index.table.report',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'dashboard.admin.damage_reports.index.table.car',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'dashboard.admin.damage_reports.index.table.type',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'dashboard.admin.damage_reports.index.table.linked',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'dashboard.admin.damage_reports.index.table.damage_qty',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'dashboard.admin.damage_reports.index.table.date',
                                    )
                                }}
                            </th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="row in reports.data" :key="row.id">
                            <td class="px-4 py-3 text-sm">
                                <div class="font-medium">
                                    {{ row.report_number }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ row.status }} | {{ row.branch }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ row.car }}</td>
                            <td class="px-4 py-3 text-sm">
                                {{ row.report_type_label }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div>
                                    {{
                                        t(
                                            'dashboard.admin.damage_reports.index.linked_contract',
                                        )
                                    }}:
                                    {{ row.contract_number || '-' }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{
                                        t(
                                            'dashboard.admin.damage_reports.index.linked_reservation',
                                        )
                                    }}:
                                    {{ row.reservation_number || '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div class="font-medium">
                                    {{ row.total_quantity }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{
                                        t(
                                            'dashboard.admin.damage_reports.index.entries_count',
                                            {
                                                count: row.items_count,
                                            },
                                        )
                                    }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ row.inspected_at || '-' }}
                            </td>
                            <td class="space-x-2 px-4 py-3 text-right">
                                <Link :href="row.edit_url">
                                    <Button size="sm" variant="outline">
                                        {{ t('dashboard.admin.common.edit') }}
                                    </Button>
                                </Link>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    @click="
                                        deleteReport(
                                            row.destroy_url,
                                            row.report_number,
                                        )
                                    "
                                >
                                    {{ t('dashboard.admin.common.delete') }}
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!hasRows">
                            <td
                                colspan="7"
                                class="px-4 py-6 text-center text-gray-500"
                            >
                                {{ t('dashboard.admin.damage_reports.index.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="reports.links?.length" class="flex gap-2">
                <Link
                    v-for="(link, i) in reports.links"
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
