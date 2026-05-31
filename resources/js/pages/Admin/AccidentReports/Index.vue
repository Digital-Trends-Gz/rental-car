<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status || 'all');
const branchId = ref(props.filters?.branch_id ? String(props.filters.branch_id) : '');

const hasRows = computed(() => props.reports.data.length > 0);

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
    <Head :title="localize('Accident Reports', 'بلاغات الحوادث')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">
                        {{ localize('Accident Reports', 'بلاغات الحوادث') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Track accident reports linked to rental contracts.', 'متابعة بلاغات الحوادث المرتبطة بالعقود.') }}
                    </p>
                </div>
                <Link :href="createUrl">
                    <Button>{{ localize('New Accident Report', 'بلاغ حادث جديد') }}</Button>
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <Input
                    v-model="search"
                    class="md:col-span-2"
                    :placeholder="localize('Search by number, contract, car, location...', 'بحث بالرقم أو العقد أو السيارة أو الموقع...')"
                    @keyup.enter="doSearch"
                />

                <select v-model="status" class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm">
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
            </div>

            <div class="flex items-center gap-2">
                <Button @click="doSearch">{{ localize('Search', 'بحث') }}</Button>
                <Button
                    variant="outline"
                    @click="
                        search = '';
                        status = 'all';
                        branchId = '';
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
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                {{ localize('Number', 'الرقم') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                {{ localize('Contract', 'العقد') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                {{ localize('Car', 'السيارة') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                {{ localize('Location', 'الموقع') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                {{ localize('Status', 'الحالة') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                {{ localize('Date', 'التاريخ') }}
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                {{ localize('Actions', 'إجراءات') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-if="!hasRows">
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                {{ localize('No accident reports found.', 'لا توجد بلاغات حوادث.') }}
                            </td>
                        </tr>
                        <tr v-for="report in reports.data" :key="report.id">
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium">
                                {{ report.accident_number }}
                                <div class="text-xs text-muted-foreground">
                                    {{ report.photos_count }} {{ localize('photos', 'صور') }}
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
                                    {{ report.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ report.accident_at || '-' }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <Link :href="report.show_url" class="text-primary hover:underline">
                                    {{ localize('View', 'عرض') }}
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
