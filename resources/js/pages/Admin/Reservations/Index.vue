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
    reservationUsage?: { current: number; limit: number | null; at_limit: boolean; message?: string | null };
    canCreateReservation?: boolean;
    lockedBookingRequestsCount?: number;
    lockedBookingRequests?: {
        data: Array<{
            id: number;
            car: { id: number; make: string; model: string; year: number; license_plate: string } | null;
            start_date: string | null;
            end_date: string | null;
            total_days: number | null;
            total_amount: number | string | null;
            currency: string | null;
            created_at: string | null;
            customer_name: string | null;
            customer_email: string | null;
            customer_phone: string | null;
            pickup_location: string | null;
            return_location: string | null;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    canRevealLockedBookingRequests?: boolean;
    currency: { symbol: string; code: string }
}>();
const { t, raw, locale } = useTrans();
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

const statusLabel = (key: string, fallback: string) => {
    const statusKey = key.toLowerCase().trim().replace(/\s+/g, '_');
    const adminKey = `dashboard.admin.reservation_statuses.${statusKey}`;
    const adminTranslated = t(adminKey);

    if (adminTranslated !== adminKey) {
        return adminTranslated;
    }

    const dashboardKey = `dashboard.reservation_statuses.${statusKey}`;
    const dashboardTranslated = t(dashboardKey);

    return dashboardTranslated === dashboardKey ? fallback : dashboardTranslated;
};

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const branchFilter = ref(props.filters?.branch_id ? String(props.filters.branch_id) : 'all');
const convertingBookingRequestId = ref<number | null>(null);
const lockedBookingRequestsMessage = computed(() => raw(
    'dashboard.admin.reservations.index.locked_booking_requests_notice',
    locale.value === 'ar'
        ? ':count طلبات حجز مقفلة بسبب حد الخطة الحالي. تبقى بيانات العملاء مخفية حتى تسمح الخطة بمزيد من الحجوزات.'
        : ':count locked booking request(s) are waiting behind the current plan limit. Customer details remain hidden until the plan allows more reservations.',
));
const lockedBookingRequestsTitle = computed(() => raw(
    'dashboard.admin.reservations.index.locked_booking_requests_title',
    locale.value === 'ar' ? 'طلبات الحجز المقفلة' : 'Locked booking requests',
));
const lockedBookingRequestsHidden = computed(() => raw(
    'dashboard.admin.reservations.index.locked_booking_requests_hidden',
    locale.value === 'ar' ? 'بيانات العميل مخفية حتى تسمح الخطة بمزيد من الحجوزات.' : 'Customer details are hidden until the plan allows more reservations.',
));
const lockedBookingRequestsRevealHint = computed(() => raw(
    'dashboard.admin.reservations.index.locked_booking_requests_reveal_hint',
    locale.value === 'ar' ? 'الخطة الحالية تسمح بعرض بيانات هذه الطلبات.' : 'The current plan allows these request details to be shown.',
));
const lockedBookingLabels = computed(() => ({
    customer: raw('dashboard.common.customer', locale.value === 'ar' ? 'العميل' : 'Customer'),
    car: raw('dashboard.common.car', locale.value === 'ar' ? 'السيارة' : 'Car'),
    period: raw('dashboard.common.period', locale.value === 'ar' ? 'الفترة' : 'Period'),
    locations: raw('dashboard.common.locations', locale.value === 'ar' ? 'المواقع' : 'Locations'),
    total: raw('dashboard.common.total', locale.value === 'ar' ? 'الإجمالي' : 'Total'),
    createdAt: raw('dashboard.common.created_at', locale.value === 'ar' ? 'تاريخ الإنشاء' : 'Created'),
    hidden: raw('dashboard.common.hidden', locale.value === 'ar' ? 'مخفي' : 'Hidden'),
    days: raw('dashboard.common.days', locale.value === 'ar' ? 'أيام' : 'days'),
    actions: raw('dashboard.common.actions', 'Actions'),
    convert: raw('dashboard.admin.reservations.index.convert_locked_booking_request', 'Convert to reservation'),
}));

const paginationLabel = (label: string): string => {
    const normalized = label.replace(/&laquo;|&raquo;/g, '').trim().toLowerCase();

    if (normalized === 'previous') {
        return raw('dashboard.common.pagination.previous', raw('pagination.previous', 'Previous'));
    }

    if (normalized === 'next') {
        return raw('dashboard.common.pagination.next', raw('pagination.next', 'Next'));
    }

    return label;
};

const money = (amount: number | string | null | undefined, currency?: string | null) => {
    const value = Number(amount || 0);
    const code = String(currency || props.currency?.code || '').trim();

    return `${code ? `${code} ` : ''}${value.toFixed(2)}`;
};

const navigateToReservation = (id: number) => {
    if (!subdomain.value) return;
    router.visit(show([subdomain.value, id]).url);
};

const convertLockedBookingRequest = (id: number) => {
    if (!subdomain.value || convertingBookingRequestId.value) return;

    convertingBookingRequestId.value = id;
    router.post(
        `/admin/booking-requests/${id}/convert`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                convertingBookingRequestId.value = null;
            },
        },
    );
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
                <Link v-if="subdomain && props.canCreateReservation !== false" href="/admin/reservations/create">
                    <Button>{{ t('dashboard.admin.reservations.index.create_reservation') }}</Button>
                </Link>
                <Button v-else disabled>{{ t('dashboard.admin.reservations.index.create_reservation') }}</Button>
            </div>
            <div v-if="props.reservationUsage?.at_limit && props.reservationUsage?.message" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">
                {{ props.reservationUsage.message }}
                <span v-if="props.reservationUsage.limit !== null" class="ms-2">
                    ({{ props.reservationUsage.current }} / {{ props.reservationUsage.limit }})
                </span>
            </div>
            <div v-if="Number(props.lockedBookingRequestsCount || 0) > 0" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                {{ lockedBookingRequestsMessage.replace(':count', String(Number(props.lockedBookingRequestsCount || 0))) }}
            </div>
            <section
                v-if="Number(props.lockedBookingRequestsCount || 0) > 0"
                class="overflow-hidden rounded-lg border border-amber-200 bg-white shadow-sm"
            >
                <div class="flex flex-col gap-2 border-b border-amber-100 bg-amber-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-amber-950">{{ lockedBookingRequestsTitle }}</h2>
                        <p class="mt-1 text-sm" :class="props.canRevealLockedBookingRequests ? 'text-emerald-700' : 'text-amber-700'">
                            {{ props.canRevealLockedBookingRequests ? lockedBookingRequestsRevealHint : lockedBookingRequestsHidden }}
                        </p>
                    </div>
                    <span class="w-fit rounded-full bg-white px-3 py-1 text-sm font-semibold text-amber-800 ring-1 ring-amber-200">
                        {{ Number(props.lockedBookingRequestsCount || 0) }}
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">{{ lockedBookingLabels.customer }}</th>
                                <th class="px-4 py-3">{{ lockedBookingLabels.car }}</th>
                                <th class="px-4 py-3">{{ lockedBookingLabels.period }}</th>
                                <th class="px-4 py-3">{{ lockedBookingLabels.locations }}</th>
                                <th class="px-4 py-3">{{ lockedBookingLabels.total }}</th>
                                <th class="px-4 py-3">{{ lockedBookingLabels.createdAt }}</th>
                                <th class="px-4 py-3">{{ lockedBookingLabels.actions }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="request in props.lockedBookingRequests?.data || []" :key="request.id">
                                <td class="px-4 py-3">
                                    <template v-if="props.canRevealLockedBookingRequests">
                                        <div class="font-medium text-gray-900">{{ request.customer_name || '-' }}</div>
                                        <div class="text-gray-500">{{ request.customer_email || '-' }}</div>
                                        <div class="text-gray-500">{{ request.customer_phone || '-' }}</div>
                                    </template>
                                    <span v-else class="rounded bg-gray-100 px-2 py-1 text-gray-500">{{ lockedBookingLabels.hidden }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="request.car" class="font-medium text-gray-900">
                                        {{ request.car.year }} {{ request.car.make }} {{ request.car.model }}
                                    </div>
                                    <div class="text-gray-500">{{ request.car?.license_plate || '-' }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    <div>{{ request.start_date || '-' }} - {{ request.end_date || '-' }}</div>
                                    <div class="text-gray-500">{{ request.total_days || 0 }} {{ lockedBookingLabels.days }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    <template v-if="props.canRevealLockedBookingRequests">
                                        <div>{{ request.pickup_location || '-' }}</div>
                                        <div class="text-gray-500">{{ request.return_location || '-' }}</div>
                                    </template>
                                    <span v-else class="rounded bg-gray-100 px-2 py-1 text-gray-500">{{ lockedBookingLabels.hidden }}</span>
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    {{ money(request.total_amount, request.currency) }}
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ request.created_at || '-' }}</td>
                                <td class="px-4 py-3">
                                    <Button
                                        v-if="props.canRevealLockedBookingRequests"
                                        size="sm"
                                        :disabled="convertingBookingRequestId === request.id"
                                        @click="convertLockedBookingRequest(request.id)"
                                    >
                                        {{ lockedBookingLabels.convert }}
                                    </Button>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <nav v-if="props.lockedBookingRequests?.links?.length" class="flex flex-wrap gap-2 border-t border-amber-100 px-4 py-3">
                    <Link
                        v-for="(link, i) in props.lockedBookingRequests.links"
                        :key="i"
                        :href="link.url || ''"
                        :class="[
                            'rounded px-3 py-1 text-sm',
                            link.active
                                ? 'bg-amber-500 text-white'
                                : 'bg-amber-50 text-amber-800',
                            !link.url && 'pointer-events-none opacity-50',
                        ]"
                        preserve-scroll
                    >
                        {{ paginationLabel(link.label) }}
                    </Link>
                </nav>
            </section>

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
                                {{ statusLabel(String(key), (status as any).label) }} ({{
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
                                        statusLabel(res.status, statuses[res.status]?.label || res.status)
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
