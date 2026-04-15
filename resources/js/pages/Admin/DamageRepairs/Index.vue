<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useTrans } from '@/composables/useTrans';

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

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

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
    if (!window.confirm(localize(`Delete damage repair ${numberText}?`, `هل تريد حذف إصلاح الضرر ${numberText}؟`))) {
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
    <Head :title="localize('Damage Repairs', 'إصلاحات الأضرار')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">
                        {{ localize('Damage Repairs', 'إصلاحات الأضرار') }}
                    </h1>
                    <p class="text-sm text-slate-500">
                        {{
                            localize(
                                'Track car damage maintenance until the damage is fully repaired.',
                                'تابع إصلاح أضرار السيارات حتى يتم إنهاء الضرر بالكامل.',
                            )
                        }}
                    </p>
                </div>

                <Link :href="createUrl">
                    <Button>{{ localize('New Repair', 'إصلاح جديد') }}</Button>
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                <Input
                    v-model="search"
                    class="md:col-span-2"
                    :placeholder="localize('Search by repair number, car, workshop...', 'ابحث برقم الإصلاح أو السيارة أو الورشة...')"
                    @keyup.enter="doSearch"
                />

                <select
                    v-model="status"
                    class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                    <option value="all">{{ localize('All statuses', 'كل الحالات') }}</option>
                    <option v-for="item in statuses" :key="item.value" :value="item.value">
                        {{ item.label }}
                    </option>
                </select>

                <select
                    v-if="canAccessAllBranches"
                    v-model="branchId"
                    class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                    <option value="">{{ localize('All branches', 'كل الفروع') }}</option>
                    <option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">
                        {{ branch.name }}
                    </option>
                </select>

                <select
                    v-model="carId"
                    class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                    <option value="">{{ localize('All cars', 'كل السيارات') }}</option>
                    <option v-for="car in cars" :key="car.id" :value="String(car.id)">
                        {{ car.label }}
                    </option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <Button @click="doSearch">{{ localize('Search', 'بحث') }}</Button>
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
                    {{ localize('Clear', 'مسح') }}
                </Button>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase">{{ localize('Repair', 'الإصلاح') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase">{{ localize('Car', 'السيارة') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase">{{ localize('Damage', 'الضرر') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase">{{ localize('Workshop', 'الورشة') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase">{{ localize('Status', 'الحالة') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase">{{ localize('Opened', 'الفتح') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase">{{ localize('Completed', 'الإنهاء') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase">{{ localize('Cost', 'التكلفة') }}</th>
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
                                    {{ row.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ row.opened_at || '-' }}</td>
                            <td class="px-4 py-3 text-sm">{{ row.completed_at || '-' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div>{{ localize('Estimated', 'تقديري') }}: {{ money(row.estimated_cost) }}</div>
                                <div>{{ localize('Actual', 'فعلي') }}: {{ money(row.actual_cost) }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Link :href="row.edit_url">
                                        <Button size="sm" variant="outline">{{ localize('Edit', 'تعديل') }}</Button>
                                    </Link>
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        @click="destroyRepair(row.destroy_url, row.repair_number)"
                                    >
                                        {{ localize('Delete', 'حذف') }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!hasRows">
                            <td class="px-4 py-8 text-center text-sm text-muted-foreground" colspan="9">
                                {{ localize('No damage repairs found.', 'لا توجد إصلاحات أضرار.') }}
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
