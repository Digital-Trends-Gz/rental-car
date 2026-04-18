<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { show } from '@/routes/admin/reservations';
import { index } from '@/routes/admin/reservations';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    reservations: {
        data: Array<{
            id: number;
            reservation_number: string;
            user: { id: number; name: string; email: string } | null;
            car: {
                id: number;
                make: string;
                model: string;
                year: number;
                license_plate: string;
            } | null;
            start_date: string;
            end_date: string;
            total_days: number;
            total_amount: number | string;
            status: string;
            branch_name?: string | null;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search?: string;
        status?: string;
        branch_id?: number | null;
    };
    statuses: Record<string, { label: string; count: number; color: string }>;
    branches: Array<{ id: number; name: string }>;
    canAccessAllBranches: boolean;
    currency: { symbol: string; code: string }
}>();
const { t, locale } = useTrans();
const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);

// Generate status colors based on the colors from the backend (mirrors Cars)
const statusColors = computed(() => {
    const colors: Record<string, { bg: string; text: string; dot: string }> =
        {};
    for (const [status, data] of Object.entries(props.statuses || {})) {
        const hex = (data as any).color?.replace('#', '') || '6B7280';
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);
        colors[status] = {
            bg: `rgba(${r}, ${g}, ${b}, 0.1)`,
            text: `text-[${(data as any).color}]`,
            dot: (data as any).color,
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

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const branchFilter = ref(props.filters?.branch_id ? String(props.filters.branch_id) : 'all');

const navigateToReservation = (id: number) => {
    if (!subdomain.value) return;
    router.visit(show([subdomain.value, id]).url);
};

function doSearch() {
    if (!subdomain.value) return;

    router.get(
        index(subdomain.value).url,
        {
            search: search.value,
            status: statusFilter.value === 'all' ? null : statusFilter.value,
            branch_id: branchFilter.value === 'all' ? null : Number(branchFilter.value),
        },
        {
            preserveState: true,
            replace: true,
        },
    );
}

watch(search, (v, ov) => {
    if (v === '' && ov !== '') doSearch();
});
</script>

<template>
    <Head :title="t('dashboard.admin.reservations.index.head_title')" />
    <AdminLayout>
        <!-- Main -->
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ t('dashboard.admin.reservations.index.title') }}</h1>
                <Link v-if="subdomain" href="/admin/reservations/create">
                    <Button>{{ t('dashboard.admin.reservations.index.create_reservation') }}</Button>
                </Link>
            </div>

            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-2">
                    <Input
                        v-model="search"
                        :placeholder="t('dashboard.admin.reservations.index.search_placeholder')"
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
                        <option value="all">{{ t('dashboard.admin.reservations.index.all_branches') }}</option>
                        <option v-for="branch in props.branches" :key="branch.id" :value="String(branch.id)">
                            {{ branch.name }}
                        </option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="flex flex-wrap items-center gap-2">
                    <label class="inline-flex items-center">
                        <input
                            type="radio"
                            class="hidden"
                            v-model="statusFilter"
                            value="all"
                            @change="doSearch"
                        />
                        <span
                            class="cursor-pointer rounded-full px-3 py-1.5 text-sm transition-colors"
                            :class="{
                                'bg-primary text-primary-foreground':
                                    statusFilter === 'all',
                                'bg-muted text-muted-foreground hover:bg-muted/80':
                                    statusFilter !== 'all',
                            }"
                        >
                            {{ t('dashboard.common.all') }} ({{
                                Object.values(statuses).reduce(
                                    (acc: number, curr: any) =>
                                        acc + (curr as any).count,
                                    0,
                                )
                            }})
                        </span>
                    </label>

                    <template v-for="(status, key) in statuses" :key="key">
                        <label class="inline-flex items-center">
                            <input
                                type="radio"
                                class="hidden"
                                v-model="statusFilter"
                                :value="key"
                                @change="doSearch"
                            />
                            <span
                                class="flex cursor-pointer items-center gap-1.5 rounded-full px-3 py-1.5 text-sm transition-colors"
                                :class="{
                                    'bg-primary text-primary-foreground':
                                        statusFilter === key,
                                    'bg-muted text-muted-foreground hover:bg-muted/80':
                                        statusFilter !== key,
                                }"
                            >
                                <span
                                    class="h-2 w-2 rounded-full"
                                    :style="{
                                        backgroundColor: (status as any).color,
                                    }"
                                ></span>
                                {{ (status as any).label }} ({{
                                    (status as any).count
                                }})
                            </span>
                        </label>
                    </template>
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
                                {{ t('dashboard.common.car') }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{ t('dashboard.admin.employees.table.branch') }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{ t('dashboard.common.dates') }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{ t('dashboard.common.total') }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{ t('dashboard.common.status') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="res in props.reservations.data"
                            :key="res.id"
                            @click="navigateToReservation(res.id)"
                            class="cursor-pointer transition-colors hover:bg-gray-50"
                        >
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ res.reservation_number }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ res.user?.name || '—' }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ res.user?.email }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{
                                        res.car
                                            ? `${res.car.year} ${res.car.make} ${res.car.model}`
                                            : '—'
                                    }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ res.car?.license_plate }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                {{ res.branch_name || t('dashboard.admin.employees.table.no_branch') }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{
                                        new Date(
                                            res.start_date,
                                        ).toLocaleDateString(
                                            locale === 'ar' ? 'ar' : 'en-US',
                                        )
                                    }}
                                    →
                                    {{
                                        new Date(
                                            res.end_date,
                                        ).toLocaleDateString(
                                            locale === 'ar' ? 'ar' : 'en-US',
                                        )
                                    }}
                                </div>
                                <!-- duration in days-->
                                <div class="text-xs text-muted-foreground">
                                    {{ res.total_days }} {{ t('dashboard.common.days') }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                {{ props.currency.symbol }} {{ Number(res.total_amount).toFixed(2) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-medium"
                                    :style="{
                                        backgroundColor: getStatusColor(
                                            res.status,
                                        ).bg,
                                        color: getStatusColor(res.status).text,
                                    }"
                                >
                                    <span
                                        class="size-2 rounded-full"
                                        :style="{
                                            backgroundColor: getStatusColor(
                                                res.status,
                                            ).dot,
                                        }"
                                    />
                                    {{
                                        statuses[res.status]?.label ||
                                        res.status
                                    }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="props.reservations.data.length === 0">
                            <td
                                colspan="8"
                                class="px-4 py-6 text-center text-gray-500"
                            >
                                {{ t('dashboard.admin.reservations.index.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="props.reservations.links?.length" class="flex gap-2">
                <Link
                    v-for="(link, i) in props.reservations.links"
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
