<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import ClientLayout from '@/layouts/ClientLayout.vue';
import { Button } from '@/components/ui/button';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { show } from '@/routes/client/reservations';
import { computed } from 'vue';
import { Download } from 'lucide-vue-next';

type ReservationPayment = {
    amount?: number | string | null;
    refunded_amount?: number | string | null;
    status?: string | null;
};

const props = defineProps<{
    reservations: {
        data: Array<{
            id: number;
            reservation_number: string;
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
            contract?: {
                id: number;
                contract_number: string;
            } | null;
            payments?: Array<ReservationPayment>;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    extensionRequests: Array<{
        id: number;
        reservation_id: number;
        reservation_number: string | null;
        contract_number: string | null;
        car_name: string | null;
        license_plate: string | null;
        new_end_date: string | null;
        extra_days: number;
        extra_amount: number;
        reason: string | null;
        status: string;
        status_label: string;
        approve_url: string;
        reject_url: string;
    }>;
    currency: { symbol: string; code: string };
    paymentStatusMeta?: Record<string, { label: string; color: string }>;
}>();

const { t } = useTrans();
const page = usePage<any>();

const reservationStatusLabels: Record<string, string> = {
    pending: t('client_pages.reservations.statuses.pending'),
    confirmed: t('client_pages.reservations.statuses.confirmed'),
    active: t('client_pages.reservations.statuses.active'),
    completed: t('client_pages.reservations.statuses.completed'),
    cancelled: t('client_pages.reservations.statuses.cancelled'),
    canceled: t('client_pages.reservations.statuses.cancelled'),
    no_show: t('client_pages.reservations.statuses.no_show'),
};

const extensionStatusLabels: Record<string, string> = {
    pending: t('client_pages.reservations.index.extension_requests.statuses.pending'),
    approved: t('client_pages.reservations.index.extension_requests.statuses.approved'),
    rejected: t('client_pages.reservations.index.extension_requests.statuses.rejected'),
};

const paymentStatusLabels: Record<string, string> = {
    paid: t('client_pages.reservations.payment_statuses.paid'),
    partial: t('client_pages.reservations.payment_statuses.partial'),
    pending: t('client_pages.reservations.payment_statuses.pending'),
    unpaid: t('client_pages.reservations.payment_statuses.unpaid'),
};

const paymentStatusStyles: Record<string, string> = {
    paid: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    partial: 'border-amber-200 bg-amber-50 text-amber-700',
    pending: 'border-blue-200 bg-blue-50 text-blue-700',
    unpaid: 'border-gray-200 bg-gray-50 text-gray-600',
};

function getReservationPaymentStatus(reservation: {
    total_amount: number | string;
    payments?: Array<ReservationPayment>;
}): 'paid' | 'partial' | 'pending' | 'unpaid' {
    const payments = Array.isArray(reservation.payments) ? reservation.payments : [];
    const totalAmount = Number(reservation.total_amount ?? 0);
    const paidAmount = payments
        .filter((payment) => payment.status === 'completed')
        .reduce((sum, payment) => {
            const amount = Number(payment.amount ?? 0);
            const refundedAmount = Number(payment.refunded_amount ?? 0);

            return sum + Math.max(0, amount - refundedAmount);
        }, 0);

    if (totalAmount <= 0 || paidAmount >= totalAmount) {
        return 'paid';
    }

    if (paidAmount > 0) {
        return 'partial';
    }

    if (payments.some((payment) => payment.status === 'pending')) {
        return 'pending';
    }

    return 'unpaid';
}

const forceExtensionNotification = computed(() => {
    const notifications = Array.isArray(page.props?.auth?.notifications) ? page.props.auth.notifications : [];

    return notifications.find((notification: { kind?: string; message?: string; title?: string; url?: string }) =>
        notification.kind === 'contract_force_extended',
    ) || null;
});

const navigateToReservation = (id: number) => {
    const subdomain = (page.props as any)?.subdomain || window.location.hostname.split('.')[0];
    router.visit(show({ subdomain, id }).url);
};

function downloadContract(reservationId: number, event: Event) {
    event.stopPropagation();
    window.location.href = `/client/reservations/${reservationId}/contract/download`;
}

function approveExtensionRequest(url: string) {
    router.post(url, {}, {
        preserveScroll: true,
        onSuccess: () => router.reload({ preserveScroll: true }),
    });
}

function rejectExtensionRequest(url: string) {
    router.post(url, {}, {
        preserveScroll: true,
        onSuccess: () => router.reload({ preserveScroll: true }),
    });
}
</script>

<template>
    <Head :title="t('client_pages.reservations.index.head_title')" />
    <ClientLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{ t('client_pages.reservations.index.title') }}
                </h1>
            </div>

            <div
                v-if="forceExtensionNotification"
                class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-950 shadow-sm"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold">
                            {{
                                forceExtensionNotification.title ||
                                t('client_pages.reservations.index.force_extension.rental_update')
                            }}
                        </p>
                        <p class="mt-1 text-sm">
                            {{ forceExtensionNotification.message }}
                        </p>
                    </div>
                    <Link
                        v-if="forceExtensionNotification.url"
                        :href="forceExtensionNotification.url"
                        class="text-sm font-medium text-amber-900 underline underline-offset-2"
                    >
                        {{ t('client_pages.reservations.index.force_extension.open') }}
                    </Link>
                </div>
            </div>

            <div
                v-if="props.extensionRequests.length"
                class="space-y-3 rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm"
            >
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-amber-950">
                        {{ t('client_pages.reservations.index.extension_requests.title') }}
                    </h2>
                    <span class="text-sm text-amber-900">
                        {{
                            t('client_pages.reservations.index.extension_requests.pending', {
                                count: props.extensionRequests.length,
                            })
                        }}
                    </span>
                </div>

                <div
                    v-for="request in props.extensionRequests"
                    :key="request.id"
                    class="rounded-lg border border-amber-200 bg-white p-4"
                >
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-1">
                            <div class="text-sm font-semibold text-amber-950">
                                {{
                                    request.contract_number ||
                                    t('client_pages.reservations.index.extension_requests.contract')
                                }}
                                - {{ extensionStatusLabels[request.status] || request.status_label }}
                            </div>
                            <div class="text-sm text-gray-700">
                                {{ request.car_name || '-' }}
                                <span v-if="request.license_plate" class="text-gray-500">
                                    ({{ request.license_plate }})
                                </span>
                            </div>
                            <div class="text-sm text-gray-700">
                                {{
                                    t('client_pages.reservations.index.extension_requests.new_end_date')
                                }}:
                                {{ request.new_end_date || '-' }}
                            </div>
                            <div class="text-sm text-gray-700">
                                {{ t('client_pages.reservations.index.extension_requests.extra') }}:
                                {{ props.currency.symbol }}{{ Number(request.extra_amount).toFixed(2) }}
                                <span class="text-gray-500">
                                    /
                                    {{
                                        t('client_pages.reservations.index.extension_requests.extra_days', {
                                            count: request.extra_days,
                                        })
                                    }}
                                </span>
                            </div>
                            <div v-if="request.reason" class="text-sm text-gray-700">
                                {{ t('client_pages.reservations.index.extension_requests.reason') }}:
                                {{ request.reason }}
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Button type="button" @click="approveExtensionRequest(request.approve_url)">
                                {{ t('client_pages.reservations.index.extension_requests.approve') }}
                            </Button>
                            <Button type="button" variant="outline" @click="rejectExtensionRequest(request.reject_url)">
                                {{ t('client_pages.reservations.index.extension_requests.reject') }}
                            </Button>
                        </div>
                    </div>
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
                                {{
                                    t(
                                        'client_pages.reservations.index.table.car',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'client_pages.reservations.index.table.payment_status',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'client_pages.reservations.index.table.dates',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'client_pages.reservations.index.table.total',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'client_pages.reservations.index.table.status',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="res in props.reservations.data"
                            :key="res.id"
                            class="cursor-pointer transition-colors hover:bg-gray-50"
                            @click="navigateToReservation(res.id)"
                        >
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ res.reservation_number }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{
                                        res.car
                                            ? `${res.car.year} ${res.car.make} ${res.car.model}`
                                            : '-'
                                    }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ res.car?.license_plate }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{
                                        new Date(
                                            res.start_date,
                                        ).toLocaleDateString()
                                    }}
                                    ->
                                    {{
                                        new Date(
                                            res.end_date,
                                        ).toLocaleDateString()
                                    }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{
                                        t(
                                            'client_pages.reservations.index.days',
                                            { count: res.total_days },
                                        )
                                    }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                {{ props.currency.symbol }}
                                {{ Number(res.total_amount).toFixed(2) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium"
                                    :class="paymentStatusStyles[getReservationPaymentStatus(res)]"
                                >
                                    {{ paymentStatusLabels[getReservationPaymentStatus(res)] }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                {{ reservationStatusLabels[res.status] || res.status }}
                            </td>
                            <td class="px-4 py-3">
                                <Button
                                    v-if="res.contract"
                                    @click="downloadContract(res.id, $event)"
                                    size="sm"
                                    variant="outline"
                                    class="gap-2"
                                >
                                    <Download class="h-4 w-4" />
                                    Contract
                                </Button>
                                <span v-else class="text-xs text-gray-400">-</span>
                            </td>
                        </tr>
                        <tr v-if="props.reservations.data.length === 0">
                            <td
                                colspan="7"
                                class="px-4 py-6 text-center text-gray-500"
                            >
                                {{ t('client_pages.reservations.index.empty') }}
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
    </ClientLayout>
</template>
