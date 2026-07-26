<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import ClientLayout from '@/layouts/ClientLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { computed } from 'vue';
import { index, print } from '@/routes/client/reservations';

const props = defineProps<{
    reservation: any;
    statusMeta: Array<{ value: string; label: string; color: string }>;
    paymentStatusMeta: Array<{ value: string; label: string }>;
    currency: { symbol: string; code: string };
}>();

const { t, locale } = useTrans();

const reservationStatusLabels: Record<string, string> = {
    pending: t('client_pages.reservations.statuses.pending'),
    confirmed: t('client_pages.reservations.statuses.confirmed'),
    active: t('client_pages.reservations.statuses.active'),
    completed: t('client_pages.reservations.statuses.completed'),
    cancelled: t('client_pages.reservations.statuses.cancelled'),
    canceled: t('client_pages.reservations.statuses.cancelled'),
    no_show: t('client_pages.reservations.statuses.no_show'),
};

const paymentStatusLabels: Record<string, string> = {
    pending: t('client_pages.reservations.payment_statuses.pending'),
    completed: t('client_pages.reservations.payment_statuses.completed'),
    failed: t('client_pages.reservations.payment_statuses.failed'),
    refunded: t('client_pages.reservations.payment_statuses.refunded'),
    cancelled: t('client_pages.reservations.payment_statuses.cancelled'),
    canceled: t('client_pages.reservations.payment_statuses.cancelled'),
};

const paymentMethodLabels: Record<string, string> = {
    cash: t('client_pages.reservations.payment_methods.cash'),
    card: t('client_pages.reservations.payment_methods.card'),
    credit_card: t('client_pages.reservations.payment_methods.card'),
    bank_transfer: t('client_pages.reservations.payment_methods.bank_transfer'),
    online: t('client_pages.reservations.payment_methods.online'),
    stripe: t('client_pages.reservations.payment_methods.stripe'),
    myfatoorah: t('client_pages.reservations.payment_methods.myfatoorah'),
};

const statusMap = computed(() => {
    const map: Record<string, { label: string; color: string }> = {};
    for (const s of props.statusMeta || []) {
        map[s.value] = { label: s.label, color: s.color };
    }
    return map;
});

const pageTitle = computed(() =>
    t('client_pages.reservations.show.head_title', {
        number: props.reservation?.reservation_number || '',
    }),
);

function getStatusStyle(status: string) {
    const meta = statusMap.value[status];
    if (!meta) {
        return {
            bg: 'rgba(107,114,128,0.1)',
        text: '#6B7280',
        dot: '#6B7280',
        label: reservationStatusLabels[status] || status,
        };
    }

    const hex = meta.color.replace('#', '');
    const r = parseInt(hex.slice(0, 2), 16);
    const g = parseInt(hex.slice(2, 4), 16);
    const b = parseInt(hex.slice(4, 6), 16);

    return {
        bg: `rgba(${r}, ${g}, ${b}, 0.1)`,
        text: meta.color,
        dot: meta.color,
        label: reservationStatusLabels[status] || meta.label,
    };
}

function fmtDate(d?: string) {
    return d ? new Date(d).toLocaleDateString(locale.value) : '-';
}

function fmtDateTime(d?: string) {
    return d ? new Date(d).toLocaleString(locale.value) : '-';
}

function fmtMoney(n?: number | string) {
    const v = Number(n ?? 0);
    return `${props.currency.symbol}${v.toFixed(2)}`;
}
</script>

<template>
    <Head :title="pageTitle" />
    <ClientLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ pageTitle }}</h1>
                <div class="flex gap-2">
                    <Link :href="index().url">
                        <Button variant="outline">{{
                            t('client_pages.reservations.show.back')
                        }}</Button>
                    </Link>
                    <a
                        :href="print(reservation.id).url"
                        target="_blank"
                        rel="noopener"
                    >
                        <Button variant="secondary">{{
                            t('client_pages.reservations.show.print')
                        }}</Button>
                    </a>
                </div>
            </div>

            <div
                class="flex items-center justify-between rounded-md border p-4"
            >
                <div class="space-y-1">
                    <div class="text-sm text-muted-foreground">
                        {{ t('client_pages.reservations.show.fields.status') }}
                    </div>
                    <div>
                        <span
                            class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-medium"
                            :style="{
                                backgroundColor:
                                    getStatusStyle(reservation.status).bg,
                                color: getStatusStyle(reservation.status).text,
                            }"
                        >
                            <span
                                class="size-2 rounded-full"
                                :style="{
                                    backgroundColor:
                                        getStatusStyle(reservation.status).dot,
                                }"
                            />
                            {{ getStatusStyle(reservation.status).label }}
                        </span>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-muted-foreground">
                        {{ t('client_pages.reservations.show.fields.total') }}
                    </div>
                    <div class="text-xl font-semibold">
                        {{ fmtMoney(reservation.total_amount) }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="rounded-md border">
                    <div class="border-b px-4 py-3 font-medium">
                        {{ t('client_pages.reservations.show.sections.client') }}
                    </div>
                    <div class="space-y-1 p-4">
                        <div class="text-sm">
                            {{ t('client_pages.reservations.show.fields.name') }}
                        </div>
                        <div class="font-medium">
                            {{ reservation.user?.name || '-' }}
                        </div>
                        <div class="mt-3 text-sm">
                            {{ t('client_pages.reservations.show.fields.email') }}
                        </div>
                        <div class="font-medium">
                            {{ reservation.user?.email || '-' }}
                        </div>
                    </div>
                </div>

                <div class="rounded-md border">
                    <div class="border-b px-4 py-3 font-medium">
                        {{ t('client_pages.reservations.show.sections.car') }}
                    </div>
                    <div class="space-y-1 p-4">
                        <div class="text-sm">
                            {{ t('client_pages.reservations.show.fields.car') }}
                        </div>
                        <div class="font-medium">
                            {{
                                reservation.car
                                    ? `${reservation.car.year} ${reservation.car.make} ${reservation.car.model}`
                                    : '-'
                            }}
                        </div>
                        <div class="mt-3 text-sm">
                            {{ t('client_pages.reservations.show.fields.plate') }}
                        </div>
                        <div class="font-medium">
                            {{ reservation.car?.license_plate || '-' }}
                        </div>
                    </div>
                </div>

                <div class="rounded-md border md:col-span-2">
                    <div class="border-b px-4 py-3 font-medium">
                        {{
                            t(
                                'client_pages.reservations.show.sections.reservation_details',
                            )
                        }}
                    </div>
                    <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-3">
                        <div>
                            <div class="text-sm text-muted-foreground">
                                {{
                                    t(
                                        'client_pages.reservations.show.fields.start_date',
                                    )
                                }}
                            </div>
                            <div class="font-medium">
                                {{ fmtDate(reservation.start_date) }}
                                {{ reservation.pickup_time }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">
                                {{
                                    t(
                                        'client_pages.reservations.show.fields.end_date',
                                    )
                                }}
                            </div>
                            <div class="font-medium">
                                {{ fmtDate(reservation.end_date) }}
                                {{ reservation.return_time }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">
                                {{
                                    t(
                                        'client_pages.reservations.show.fields.duration',
                                    )
                                }}
                            </div>
                            <div class="font-medium">
                                {{
                                    t('client_pages.reservations.show.days', {
                                        count: reservation.total_days,
                                    })
                                }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">
                                {{
                                    t(
                                        'client_pages.reservations.show.fields.pickup_location',
                                    )
                                }}
                            </div>
                            <div class="font-medium">
                                {{ reservation.pickup_location || '-' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">
                                {{
                                    t(
                                        'client_pages.reservations.show.fields.return_location',
                                    )
                                }}
                            </div>
                            <div class="font-medium">
                                {{ reservation.return_location || '-' }}
                            </div>
                        </div>
                        <div v-if="reservation.status === 'cancelled'">
                            <div class="text-sm text-muted-foreground">
                                {{
                                    t(
                                        'client_pages.reservations.show.fields.cancelled_at',
                                    )
                                }}
                            </div>
                            <div class="font-medium">
                                {{ fmtDateTime(reservation.cancelled_at) }}
                            </div>
                            <div class="mt-2 text-sm text-muted-foreground">
                                {{
                                    t(
                                        'client_pages.reservations.show.fields.reason',
                                    )
                                }}
                            </div>
                            <div class="font-medium">
                                {{ reservation.cancellation_reason || '-' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-md border">
                    <div class="border-b px-4 py-3 font-medium">
                        {{
                            t(
                                'client_pages.reservations.show.sections.amounts',
                            )
                        }}
                    </div>
                    <div class="space-y-2 p-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm">
                                {{
                                    t(
                                        'client_pages.reservations.show.fields.daily_rate',
                                    )
                                }}
                            </div>
                            <div class="font-medium">
                                {{ fmtMoney(reservation.daily_rate) }}
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="text-sm">
                                {{
                                    t(
                                        'client_pages.reservations.show.fields.subtotal',
                                    )
                                }}
                            </div>
                            <div class="font-medium">
                                {{ fmtMoney(reservation.subtotal) }}
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="text-sm">
                                {{
                                    t(
                                        'client_pages.reservations.show.fields.tax',
                                    )
                                }}
                            </div>
                            <div class="font-medium">
                                {{ fmtMoney(reservation.tax_amount) }}
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="text-sm">
                                {{
                                    t(
                                        'client_pages.reservations.show.fields.discount',
                                    )
                                }}
                            </div>
                            <div class="font-medium">
                                -{{ fmtMoney(reservation.discount_amount) }}
                            </div>
                        </div>
                        <div class="flex items-center justify-between border-t pt-2">
                            <div class="text-sm">
                                {{
                                    t(
                                        'client_pages.reservations.show.fields.total',
                                    )
                                }}
                            </div>
                            <div class="text-lg font-semibold">
                                {{ fmtMoney(reservation.total_amount) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-md border md:col-span-2">
                    <div class="border-b px-4 py-3 font-medium">
                        {{
                            t(
                                'client_pages.reservations.show.sections.payments',
                            )
                        }}
                    </div>
                    <div class="overflow-x-auto p-4">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        #
                                    </th>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        {{
                                            t(
                                                'client_pages.reservations.show.payment_table.amount',
                                            )
                                        }}
                                    </th>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        {{
                                            t(
                                                'client_pages.reservations.show.payment_table.method',
                                            )
                                        }}
                                    </th>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        {{
                                            t(
                                                'client_pages.reservations.show.payment_table.status',
                                            )
                                        }}
                                    </th>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        {{
                                            t(
                                                'client_pages.reservations.show.payment_table.processed',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="p in reservation.payments || []"
                                    :key="p.id"
                                >
                                    <td class="px-4 py-2">
                                        {{ p.payment_number }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ fmtMoney(p.amount) }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{
                                            paymentMethodLabels[p.payment_method] ||
                                            p.payment_method
                                        }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ paymentStatusLabels[p.status] || p.status }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ fmtDateTime(p.processed_at) }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        !reservation.payments ||
                                        reservation.payments.length === 0
                                    "
                                >
                                    <td
                                        colspan="5"
                                        class="px-4 py-4 text-center text-gray-500"
                                    >
                                        {{
                                            t(
                                                'client_pages.reservations.show.no_payments',
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </ClientLayout>
</template>
