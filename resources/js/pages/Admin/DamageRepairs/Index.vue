<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    repairs: {
        data: Array<{
            id: number;
            repair_number: string;
            car: string;
            branch: string;
            damage_zone: string;
            damage_type: string;
            workshop_name: string;
            status: string;
            status_label: string;
            status_color: string;
            opened_at: string | null;
            completed_at: string | null;
            estimated_cost: number | null;
            actual_cost: number | null;
            edit_url: string;
            destroy_url: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    statuses: Array<{ value: string; label: string; color: string }>;
    branches: Array<{ id: number; name: string }>;
    cars: Array<{ id: number; label: string }>;
    canAccessAllBranches: boolean;
    filters: {
        search?: string;
        status?: string;
        branch_id?: number | null;
        car_id?: number | null;
    };
    indexUrl: string;
    createUrl: string;
}>();

const { t, locale } = useTrans();
const translationRoot = 'dashboard.admin.damage_repairs';
const translate = (key: string, params: Record<string, string | number> = {}) => t(`${translationRoot}.${key}`, params);
const translateStatus = (statusValue: string, fallback: string) => {
    const key = `${translationRoot}.statuses.${statusValue}`;
    const translated = t(key);

    return translated === key ? fallback : translated;
};

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? 'all');
const branchId = ref(props.filters?.branch_id ? String(props.filters.branch_id) : '');
const carId = ref(props.filters?.car_id ? String(props.filters.car_id) : '');

function doSearch() {
    router.get(
        props.indexUrl,
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
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

function destroyRepair(url: string, numberText: string) {
    if (!window.confirm(translate('delete_confirmation', { number: numberText }))) {
        return;
    }

    router.delete(url, { preserveScroll: true });
}

function money(value: number | null) {
    if (value === null) return '-';
    return value.toLocaleString(locale.value === 'ar' ? 'ar' : 'en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

const hasRows = computed(() => props.repairs.data.length > 0);
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
                    <p class="text-sm text-slate-500">
                        {{ translate('description') }}
                    </p>
                </div>

                <Link :href="createUrl">
                    <Button>{{ translate('new_repair') }}</Button>
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                <Input
                    v-model="search"
                    class="md:col-span-2"
                    :placeholder="translate('search_placeholder')"
                    @keyup.enter="doSearch"
                />

                <select
                    v-model="status"
                    class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
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

                <select
                    v-model="carId"
                    class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                    <option value="">{{ translate('all_cars') }}</option>
                    <option v-for="car in cars" :key="car.id" :value="String(car.id)">
                        {{ car.label }}
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
                        carId = '';
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
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase">{{ translate('table.repair') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase">{{ translate('table.car') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase">{{ translate('table.damage') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase">{{ translate('table.workshop') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase">{{ translate('table.status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase">{{ translate('table.opened') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase">{{ translate('table.completed') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase">{{ translate('table.cost') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="row in repairs.data" :key="row.id">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ row.repair_number }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.branch }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ row.car }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div>{{ row.damage_zone }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.damage_type }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ row.workshop_name }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium text-white"
                                    :style="{ backgroundColor: row.status_color }"
                                >
                                    {{ translateStatus(row.status, row.status_label) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ row.opened_at || '-' }}</td>
                            <td class="px-4 py-3 text-sm">{{ row.completed_at || '-' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div>{{ translate('estimated') }}: {{ money(row.estimated_cost) }}</div>
                                <div>{{ translate('actual') }}: {{ money(row.actual_cost) }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Link :href="row.edit_url">
                                        <Button size="sm" variant="outline">{{ translate('edit') }}</Button>
                                    </Link>
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        @click="destroyRepair(row.destroy_url, row.repair_number)"
                                    >
                                        {{ translate('delete') }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!hasRows">
                            <td class="px-4 py-8 text-center text-sm text-muted-foreground" colspan="9">
                                {{ translate('empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="repairs.links.length > 3" class="flex flex-wrap gap-2">
                <Link
                    v-for="link in repairs.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    :class="[
                        'rounded border px-3 py-1 text-sm',
                        link.active ? 'bg-primary text-primary-foreground' : 'bg-background',
                        !link.url ? 'pointer-events-none opacity-50' : '',
                    ]"
                    v-html="link.label"
                />
            </div>
        </main>
    </AdminLayout>
</template>
