<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    violations: {
        data: Array<{
            id: number;
            violation_number: string | null;
            car: string;
            type: string;
            amount: number;
            status: string;
            status_label: string;
            status_color: string;
            violation_date: string | null;
            due_date: string | null;
            branch: string;
            issued_to: string;
            edit_url: string;
            destroy_url: string;
            notice_pdf_url: string;
            notice_print_url: string;
            notice_edit_url: string;
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

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status || 'all');
const branchId = ref<string>(
    props.filters?.branch_id ? String(props.filters.branch_id) : '',
);
const carId = ref<string>(
    props.filters?.car_id ? String(props.filters.car_id) : '',
);

const hasRows = computed(() => props.violations.data.length > 0);
const { t } = useTrans();

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

watch(search, (newValue, oldValue) => {
    if (newValue === '' && oldValue !== '') {
        doSearch();
    }
});

function deleteViolation(url: string, numberText: string | null) {
    const label =
        numberText || t('dashboard.admin.car_violations.index.this_violation');
    const confirmed = window.confirm(
        t('dashboard.admin.car_violations.index.delete_confirm', {
            label,
        }),
    );
    if (!confirmed) return;

    router.delete(url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('dashboard.admin.car_violations.index.head_title')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{ t('dashboard.admin.car_violations.index.title') }}
                </h1>
                <Link :href="createUrl">
                    <Button>{{
                        t('dashboard.admin.car_violations.index.new_violation')
                    }}</Button>
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                <Input
                    v-model="search"
                    class="md:col-span-2"
                    :placeholder="
                        t(
                            'dashboard.admin.car_violations.index.search_placeholder',
                        )
                    "
                    @keyup.enter="doSearch"
                />

                <select
                    v-model="status"
                    class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                    <option value="all">
                        {{
                            t(
                                'dashboard.admin.car_violations.index.all_statuses',
                            )
                        }}
                    </option>
                    <option
                        v-for="item in statuses"
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
                                'dashboard.admin.car_violations.index.all_branches',
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
                            t(
                                'dashboard.admin.car_violations.index.all_cars',
                            )
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
                <Button @click="doSearch">{{
                    t('dashboard.common.search')
                }}</Button>
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
                    {{ t('dashboard.admin.car_violations.index.clear') }}
                </Button>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{
                                    t(
                                        'dashboard.admin.car_violations.index.table.number',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{
                                    t(
                                        'dashboard.admin.car_violations.index.table.car',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{
                                    t(
                                        'dashboard.admin.car_violations.index.table.type',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{
                                    t(
                                        'dashboard.admin.car_violations.index.table.amount',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{
                                    t(
                                        'dashboard.admin.car_violations.index.table.status',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{
                                    t(
                                        'dashboard.admin.car_violations.index.table.date',
                                    )
                                }}
                            </th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="row in violations.data" :key="row.id">
                            <td class="px-4 py-3 text-sm font-medium">
                                {{ row.violation_number || '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm">{{ row.car }}</td>
                            <td class="px-4 py-3 text-sm">{{ row.type }}</td>
                            <td class="px-4 py-3 text-sm">
                                ${{ row.amount.toFixed(2) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded px-2 py-1 text-xs font-medium text-white"
                                    :style="{
                                        backgroundColor: row.status_color,
                                    }"
                                >
                                    {{ row.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ row.violation_date || '-' }}
                            </td>
                            <td class="space-x-2 px-4 py-3 text-right">
                                <Button as-child size="sm" variant="secondary">
                                    <Link :href="row.notice_edit_url">
                                        Violation Police
                                    </Link>
                                </Button>
                                <Link :href="row.edit_url">
                                    <Button size="sm" variant="outline">{{
                                        t('dashboard.admin.common.edit')
                                    }}</Button>
                                </Link>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    @click="
                                        deleteViolation(
                                            row.destroy_url,
                                            row.violation_number,
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
                                {{
                                    t(
                                        'dashboard.admin.car_violations.index.empty',
                                    )
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="violations.links?.length" class="flex gap-2">
                <Link
                    v-for="(link, i) in violations.links"
                    :key="i"
                    :href="link.url || ''"
                    :class="[
                        'rounded px-3 py-1 text-sm',
                        link.active
                            ? 'bg-gray-900 text-white'
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
