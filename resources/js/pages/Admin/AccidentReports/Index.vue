<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Building2, CarFront, UserRound } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    reports: {
        data: Array<{
            id: number;
            accident_number: string;
            status: string;
            status_label: string;
            status_color: string;
            contract_number: string | null;
            reservation_number: string | null;
            renter_name: string | null;
            car: string;
            branch: string;
            location: string | null;
            accident_at: string | null;
            photos_count: number;
            show_url: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    statuses: Array<{ value: string; label: string; color: string }>;
    branches: Array<{ id: number; name: string }>;
    canAccessAllBranches: boolean;
    filters: {
        search?: string;
        status?: string;
        branch_id?: number | null;
    };
    indexUrl: string;
    createUrl: string;
}>();

const { t } = useTrans();
const translationRoot = 'dashboard.admin.accident_reports';
const translate = (key: string, params: Record<string, string | number> = {}) => t(`${translationRoot}.${key}`, params);
const translateStatus = (statusValue: string, fallback: string) => {
    const key = `${translationRoot}.statuses.${statusValue}`;
    const translated = t(key);

    return translated === key ? fallback : translated;
};

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status || 'all');
const branchId = ref(props.filters?.branch_id ? String(props.filters.branch_id) : '');

const hasRows = computed(() => props.reports.data.length > 0);
const workflowCards = computed(() => [
    {
        key: 'contract',
        icon: UserRound,
        title: translate('workflow.customer_accidents.title'),
        description: translate('workflow.customer_accidents.description'),
        value: props.reports.data.length,
        state: translate('workflow.active'),
    },
    {
        key: 'employee',
        icon: CarFront,
        title: translate('workflow.employee_custody.title'),
        description: translate('workflow.employee_custody.description'),
        value: 0,
        state: translate('workflow.next'),
    },
    {
        key: 'branch',
        icon: Building2,
        title: translate('workflow.office_and_gate.title'),
        description: translate('workflow.office_and_gate.description'),
        value: 0,
        state: translate('workflow.next'),
    },
]);

function doSearch() {
    router.get(
        props.indexUrl,
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            branch_id: branchId.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

watch(search, (newValue, oldValue) => {
    if (newValue === '' && oldValue !== '') {
        doSearch();
    }
});
</script>

<template>
    <Head :title="translate('title')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">
                        {{ translate('title') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ translate('description') }}
                    </p>
                </div>
                <Link :href="createUrl">
                    <Button>{{ translate('new_report') }}</Button>
                </Link>
            </div>

            <section class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                <div
                    v-for="card in workflowCards"
                    :key="card.key"
                    class="rounded-md border bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                <component :is="card.icon" class="h-5 w-5" />
                            </span>
                            <div>
                                <h2 class="font-semibold">{{ card.title }}</h2>
                                <p class="mt-1 text-sm leading-6 text-muted-foreground">{{ card.description }}</p>
                            </div>
                        </div>
                        <span class="rounded-md border px-2 py-1 text-xs font-medium text-muted-foreground">
                            {{ card.state }}
                        </span>
                    </div>
                    <div class="mt-4 text-2xl font-semibold">{{ card.value }}</div>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <Input
                    v-model="search"
                    class="md:col-span-2"
                    :placeholder="translate('search_placeholder')"
                    @keyup.enter="doSearch"
                />

                <select v-model="status" class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm">
                    <option value="all">{{ translate('all_statuses') }}</option>
                    <option v-for="item in statuses" :key="item.value" :value="item.value">
                        {{ translateStatus(item.value, item.label) }}
                    </option>
                </select>

                <select
                    v-if="canAccessAllBranches"
                    v-model="branchId"
                    class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                    <option value="">{{ translate('all_branches') }}</option>
                    <option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">
                        {{ branch.name }}
                    </option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <Button @click="doSearch">{{ translate('search') }}</Button>
                <Button
                    variant="outline"
                    @click="
                        search = '';
                        status = 'all';
                        branchId = '';
                        doSearch();
                    "
                >
                    {{ translate('clear') }}
                </Button>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                {{ translate('table.number') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                {{ translate('table.contract') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                {{ translate('table.car') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                {{ translate('table.location') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                {{ translate('table.status') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                {{ translate('table.date') }}
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                {{ translate('table.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-if="!hasRows">
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                {{ translate('empty') }}
                            </td>
                        </tr>
                        <tr v-for="report in reports.data" :key="report.id">
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium">
                                {{ report.accident_number }}
                                <div class="text-xs text-muted-foreground">
                                    {{ translate('photos_count', { count: report.photos_count }) }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div>{{ report.contract_number || '-' }}</div>
                                <div class="text-xs text-muted-foreground">{{ report.reservation_number || '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ report.car }}</td>
                            <td class="px-4 py-3 text-sm">{{ report.location || '-' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium text-white" :style="{ backgroundColor: report.status_color }">
                                    {{ translateStatus(report.status, report.status_label) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ report.accident_at || '-' }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <Link :href="report.show_url" class="text-primary hover:underline">
                                    {{ translate('view') }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="reports.links?.length" class="flex flex-wrap gap-2">
                <Link
                    v-for="link in reports.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="rounded border px-3 py-1 text-sm"
                    :class="{ 'bg-primary text-primary-foreground': link.active, 'pointer-events-none opacity-50': !link.url }"
                    v-html="link.label"
                />
            </div>
        </main>
    </AdminLayout>
</template>
