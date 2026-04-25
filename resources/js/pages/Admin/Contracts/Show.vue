<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    contract: {
        id: number;
        contract_number: string;
        status: string;
        contract_date?: string | null;
        renter_name?: string | null;
        renter_id_number?: string | null;
        renter_phone?: string | null;
        car_details?: string | null;
        plate_number?: string | null;
        vehicle_odometer?: number | null;
        vehicle_fuel_level?: string | null;
        start_date?: string | null;
        end_date?: string | null;
        total_amount?: string | number | null;
        currency?: string | null;
        price_per_day?: string | number | null;
        daily_rate?: string | number | null;
        notes?: string | null;
        ai_extraction_status?: string | null;
        ai_extracted_data?: Record<string, unknown> | null;
        reservation?: {
            id: number;
            reservation_number: string;
            user_name?: string | null;
            car?: string | null;
        } | null;
        branch_name?: string | null;
        current_damage_cases?: Array<{
            id: number;
            zone_label: string;
            view_side_label: string;
            damage_type_label: string;
            severity_label: string;
            quantity: number;
            notes: string | null;
            first_detected_at: string | null;
        }>;
        damage_reports?: Array<{
            id: number;
            report_number: string;
            report_type: string;
            report_type_label: string;
            status: string;
            inspected_at: string | null;
            items_count: number;
            total_quantity: number;
            items: Array<{
                zone_code: string;
                zone_label: string;
                damage_type: string;
                severity: string;
                quantity: number;
                notes: string | null;
            }>;
            edit_url: string;
        }>;
        extension_requests?: Array<{
            id: number;
            status: string;
            status_label: string;
            new_end_date: string | null;
            extra_days: number;
            extra_amount: number;
            reason: string | null;
            client_notes: string | null;
            requested_at: string | null;
            responded_at: string | null;
        }>;
        has_pending_extension_request?: boolean;
    };
    startRentalDocument?: { id: number; name: string; url: string } | null;
    endRentalDocument?: { id: number; name: string; url: string } | null;
    actions: {
        index: string;
        edit: string;
        damage_create?: string;
        pdf?: string;
        pdf_en?: string;
        pdf_ar?: string;
        request_extend?: string | null;
        extend?: string | null;
    };
}>();

const { t, locale } = useTrans();
const page = usePage<any>();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);
const tenantFeatureFlags = computed<Record<string, boolean>>(
    () => page.props.current_tenant?.subscription_plan?.feature_flags || {},
);
const hasFeature = (feature: string) => {
    const flags = tenantFeatureFlags.value || {};

    if (Object.keys(flags).length === 0) {
        return true;
    }

    return Boolean(flags[feature]);
};
const pageTitle = computed(() =>
    t('dashboard.admin.contracts.show.head_title', {
        number: props.contract.contract_number,
    }),
);
const actions = computed(() => props.actions);
const hasPendingExtensionRequest = computed(() => Boolean(props.contract.has_pending_extension_request));
const canExtendRental = computed(
    () =>
        Boolean(
            hasFeature('force_extend_contract') &&
                actions.value.extend &&
                extensionCurrentEndDate.value &&
                extensionDailyRate.value > 0,
        ),
);
const canRequestExtension = computed(
    () =>
        Boolean(
            hasFeature('extension_request') &&
                actions.value.request_extend &&
                extensionCurrentEndDate.value &&
                extensionDailyRate.value > 0 &&
                !hasPendingExtensionRequest.value,
        ),
);

const showExtendDialog = ref(false);
const extendForm = useForm({
    new_end_date: '',
    notes: '',
});
const showRequestDialog = ref(false);
const requestForm = useForm({
    new_end_date: '',
    notes: '',
});

function parseDate(value: string): Date {
    return new Date(`${value}T00:00:00`);
}

function formatDate(value: Date): string {
    return value.toISOString().slice(0, 10);
}

function addDays(value: Date, days: number): Date {
    const next = new Date(value);
    next.setDate(next.getDate() + days);
    return next;
}

function openExtendDialog() {
    const currentEndDate = props.contract.end_date || '';
    const defaultDate = currentEndDate ? formatDate(addDays(parseDate(currentEndDate), 1)) : '';
    extendForm.clearErrors();
    extendForm.new_end_date = defaultDate;
    extendForm.notes = '';
    showExtendDialog.value = true;
}

function openRequestDialog() {
    const currentEndDate = props.contract.end_date || '';
    const defaultDate = currentEndDate ? formatDate(addDays(parseDate(currentEndDate), 1)) : '';
    requestForm.clearErrors();
    requestForm.new_end_date = defaultDate;
    requestForm.notes = '';
    showRequestDialog.value = true;
}

const extensionDailyRate = computed(() => Number(props.contract.daily_rate ?? props.contract.price_per_day ?? 0));
const extensionCurrentTotal = computed(() => Number(props.contract.total_amount ?? 0));
const extensionCurrentEndDate = computed(() => props.contract.end_date || '');
const extensionPreview = computed(() => {
    return buildPreview(extensionCurrentEndDate.value, extendForm.new_end_date, extensionCurrentTotal.value);
});
const requestExtensionPreview = computed(() => {
    return buildPreview(extensionCurrentEndDate.value, requestForm.new_end_date, extensionCurrentTotal.value);
});

function buildPreview(currentEndDate: string, newEndDate: string, currentTotal: number) {
    if (!currentEndDate || !newEndDate) {
        return null;
    }

    const start = parseDate(currentEndDate);
    const end = parseDate(newEndDate);
    const extraDays = Math.max(0, Math.round((end.getTime() - start.getTime()) / 86400000));

    if (extraDays <= 0) {
        return {
            extraDays,
            extraAmount: 0,
            newTotal: currentTotal,
        };
    }

    const extraAmount = extraDays * extensionDailyRate.value;

    return {
        extraDays,
        extraAmount,
        newTotal: currentTotal + extraAmount,
    };
}

function submitExtension() {
    if (!actions.value.extend || !canExtendRental.value) {
        return;
    }

    extendForm.post(actions.value.extend, {
        preserveScroll: true,
        onSuccess: () => {
            showExtendDialog.value = false;
            extendForm.reset();
        },
    });
}

function submitRequestExtension() {
    if (!actions.value.request_extend || !canRequestExtension.value) {
        return;
    }

    requestForm.post(actions.value.request_extend, {
        preserveScroll: true,
        onSuccess: () => {
            showRequestDialog.value = false;
            requestForm.reset();
        },
    });
}
</script>

<template>
    <Head :title="pageTitle" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ pageTitle }}</h1>
                <div class="flex flex-wrap gap-2">
                    <Link :href="actions.index">
                        <Button variant="outline">{{
                            t('dashboard.admin.common.back')
                        }}</Button>
                    </Link>
                    <Button
                        v-if="hasFeature('pdf_export') && actions.pdf_en"
                        as="a"
                        :href="actions.pdf_en"
                        variant="outline"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {{ t('dashboard.admin.contracts.show.pdf_en') }}
                    </Button>
                    <Button
                        v-if="hasFeature('pdf_export') && actions.pdf_ar"
                        as="a"
                        :href="actions.pdf_ar"
                        variant="outline"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {{ t('dashboard.admin.contracts.show.pdf_ar') }}
                    </Button>
                    <Button
                        v-else-if="hasFeature('pdf_export') && actions.pdf"
                        as="a"
                        :href="actions.pdf"
                        variant="outline"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {{ t('dashboard.admin.contracts.show.download_pdf') }}
                    </Button>
                    <Link :href="actions.edit">
                        <Button variant="outline">{{
                            t('dashboard.admin.common.edit')
                        }}</Button>
                    </Link>
                    <Button
                        v-if="canRequestExtension"
                        type="button"
                        variant="secondary"
                        @click="openRequestDialog"
                    >
                        {{ localize('Extension Request', 'طلب تمديد') }}
                    </Button>
                    <Button
                        v-if="canExtendRental"
                        type="button"
                        @click="openExtendDialog"
                    >
                        {{ localize('Force Extend', 'تمديد الإيجار') }}
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="rounded-md border p-4">
                    <h2 class="mb-3 font-semibold">
                        {{ t('dashboard.admin.contracts.show.sections.details') }}
                    </h2>
                    <div class="space-y-2 text-sm">
                        <div>
                            <strong
                                >{{ t('dashboard.admin.contracts.show.fields.status') }}:</strong
                            >
                            {{ contract.status }}
                        </div>
                        <div>
                            <strong
                                >{{ t('dashboard.admin.contracts.show.fields.date') }}:</strong
                            >
                            {{ contract.contract_date || '-' }}
                        </div>
                        <div>
                            <strong
                                >{{ t('dashboard.admin.contracts.show.fields.branch') }}:</strong
                            >
                            {{ contract.branch_name || '-' }}
                        </div>
                        <div>
                            <strong
                                >{{ t('dashboard.admin.contracts.show.fields.amount') }}:</strong
                            >
                            {{ contract.total_amount || '0.00' }}
                            {{ contract.currency || '' }}
                        </div>
                        <div>
                            <strong>Daily Rate:</strong>
                            {{ extensionDailyRate || '-' }}
                            {{ contract.currency || '' }}
                        </div>
                    </div>
                </div>

                <div class="rounded-md border p-4">
                    <h2 class="mb-3 font-semibold">
                        {{ t('dashboard.admin.contracts.show.sections.renter') }}
                    </h2>
                    <div class="space-y-2 text-sm">
                        <div>
                            <strong
                                >{{ t('dashboard.admin.contracts.show.fields.name') }}:</strong
                            >
                            {{ contract.renter_name || '-' }}
                        </div>
                        <div>
                            <strong
                                >{{ t('dashboard.admin.contracts.show.fields.id') }}:</strong
                            >
                            {{ contract.renter_id_number || '-' }}
                        </div>
                        <div>
                            <strong
                                >{{ t('dashboard.admin.contracts.show.fields.phone') }}:</strong
                            >
                            {{ contract.renter_phone || '-' }}
                        </div>
                    </div>
                </div>

                <div class="rounded-md border p-4">
                    <h2 class="mb-3 font-semibold">
                        {{ t('dashboard.admin.contracts.show.sections.vehicle') }}
                    </h2>
                    <div class="space-y-2 text-sm">
                        <div>
                            <strong
                                >{{ t('dashboard.admin.contracts.show.fields.details') }}:</strong
                            >
                            {{ contract.car_details || '-' }}
                        </div>
                        <div>
                            <strong
                                >{{ t('dashboard.admin.contracts.show.fields.plate') }}:</strong
                            >
                            {{ contract.plate_number || '-' }}
                        </div>
                        <div>
                            <strong>Vehicle Odometer:</strong>
                            {{ contract.vehicle_odometer ?? '-' }}
                        </div>
                        <div>
                            <strong>Fuel In Vehicle:</strong>
                            {{ contract.vehicle_fuel_level || '-' }}
                        </div>
                        <div>
                            <strong
                                >{{ t('dashboard.admin.contracts.show.fields.start') }}:</strong
                            >
                            {{ contract.start_date || '-' }}
                        </div>
                        <div>
                            <strong
                                >{{ t('dashboard.admin.contracts.show.fields.end') }}:</strong
                            >
                            {{ contract.end_date || '-' }}
                        </div>
                    </div>
                </div>

                <div class="rounded-md border p-4">
                    <h2 class="mb-3 font-semibold">
                        {{
                            t(
                                'dashboard.admin.contracts.show.sections.reservation_link',
                            )
                        }}
                    </h2>
                    <div class="space-y-2 text-sm">
                        <div>
                            <strong
                                >{{
                                    t(
                                        'dashboard.admin.contracts.show.fields.reservation_number',
                                    )
                                }}:</strong
                            >
                            {{ contract.reservation?.reservation_number || '-' }}
                        </div>
                        <div>
                            <strong
                                >{{ t('dashboard.admin.contracts.show.fields.client') }}:</strong
                            >
                            {{ contract.reservation?.user_name || '-' }}
                        </div>
                        <div>
                            <strong
                                >{{ t('dashboard.admin.contracts.show.fields.car') }}:</strong
                            >
                            {{ contract.reservation?.car || '-' }}
                        </div>
                    </div>
                </div>

                <div
                    v-if="contract.extension_requests?.length"
                    class="rounded-md border p-4 md:col-span-2"
                >
                    <h2 class="mb-3 font-semibold">
                        {{ localize('Extension Requests', 'طلبات التمديد') }}
                    </h2>
                    <div class="space-y-3">
                        <div
                            v-for="request in contract.extension_requests"
                            :key="request.id"
                            class="rounded-md border p-3 text-sm"
                        >
                            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                <div class="space-y-1">
                                    <div class="font-medium">
                                        {{ request.status_label }}
                                    </div>
                                    <div class="text-muted-foreground">
                                        {{ localize('New end date', 'تاريخ الانتهاء الجديد') }}: {{ request.new_end_date || '-' }}
                                    </div>
                                    <div class="text-muted-foreground">
                                        {{ localize('Extra days', 'الأيام الإضافية') }}: {{ request.extra_days }}
                                    </div>
                                    <div class="text-muted-foreground">
                                        {{ localize('Extra amount', 'المبلغ الإضافي') }}:
                                        {{ contract.currency || '' }} {{ request.extra_amount.toFixed(2) }}
                                    </div>
                                    <div v-if="request.reason" class="text-muted-foreground">
                                        {{ localize('Reason', 'السبب') }}: {{ request.reason }}
                                    </div>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ request.requested_at || '-' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-md border p-4 md:col-span-2">
                    <h2 class="mb-3 font-semibold">
                        {{
                            t(
                                'dashboard.admin.contracts.show.sections.current_car_damages',
                            )
                        }}
                    </h2>
                    <div
                        v-if="contract.current_damage_cases?.length"
                        class="overflow-x-auto"
                    >
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-3 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                    >
                                        {{
                                            t(
                                                'dashboard.admin.contracts.show.table.zone',
                                            )
                                        }}
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                    >
                                        {{
                                            t(
                                                'dashboard.admin.contracts.show.table.view',
                                            )
                                        }}
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                    >
                                        {{
                                            t(
                                                'dashboard.admin.contracts.show.table.type',
                                            )
                                        }}
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                    >
                                        {{
                                            t(
                                                'dashboard.admin.contracts.show.table.severity',
                                            )
                                        }}
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                    >
                                        {{
                                            t(
                                                'dashboard.admin.contracts.show.table.qty',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr
                                    v-for="damage in contract.current_damage_cases"
                                    :key="damage.id"
                                >
                                    <td class="px-3 py-2">
                                        {{ damage.zone_label }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ damage.view_side_label }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ damage.damage_type_label }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ damage.severity_label }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ damage.quantity }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-sm text-gray-500">
                        {{
                            t(
                                'dashboard.admin.contracts.show.no_current_damages',
                            )
                        }}
                    </div>
                </div>

                <div class="rounded-md border p-4 md:col-span-2">
                    <div class="mb-3 flex items-center justify-between gap-4">
                        <h2 class="font-semibold">
                            {{
                                t(
                                    'dashboard.admin.contracts.show.sections.damage_reports',
                                )
                            }}
                        </h2>
                        <Link
                            v-if="actions.damage_create"
                            :href="actions.damage_create"
                        >
                            <Button size="sm">{{
                                t(
                                    'dashboard.admin.contracts.show.new_damage_report',
                                )
                            }}</Button>
                        </Link>
                    </div>

                    <div
                        v-if="contract.damage_reports?.length"
                        class="space-y-4"
                    >
                        <div
                            v-for="report in contract.damage_reports"
                            :key="report.id"
                            class="rounded border p-4 text-sm"
                        >
                            <div
                                class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
                            >
                                <div>
                                    <div class="font-medium">
                                        {{ report.report_number }}
                                    </div>
                                    <div class="text-gray-500">
                                        {{ report.report_type_label }} |
                                        {{ report.status }} |
                                        {{
                                            report.inspected_at ||
                                            t(
                                                'dashboard.admin.contracts.show.no_date',
                                            )
                                        }}
                                    </div>
                                </div>
                                <div class="text-gray-600">
                                    {{
                                        t(
                                            'dashboard.admin.contracts.show.entries_count',
                                            {
                                                count: report.items_count,
                                            },
                                        )
                                    }}
                                    |
                                    {{
                                        t(
                                            'dashboard.admin.contracts.show.total_quantity',
                                            {
                                                count: report.total_quantity,
                                            },
                                        )
                                    }}
                                </div>
                                <a
                                    :href="report.edit_url"
                                    class="text-blue-600 hover:text-blue-700"
                                >
                                    {{
                                        t(
                                            'dashboard.admin.contracts.show.open_report',
                                        )
                                    }}
                                </a>
                            </div>

                            <div class="mt-4 overflow-x-auto">
                                <table
                                    class="min-w-full divide-y divide-gray-200"
                                >
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th
                                                class="px-3 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                            >
                                                {{
                                                    t(
                                                        'dashboard.admin.contracts.show.table.zone',
                                                    )
                                                }}
                                            </th>
                                            <th
                                                class="px-3 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                            >
                                                {{
                                                    t(
                                                        'dashboard.admin.contracts.show.table.type',
                                                    )
                                                }}
                                            </th>
                                            <th
                                                class="px-3 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                            >
                                                {{
                                                    t(
                                                        'dashboard.admin.contracts.show.table.severity',
                                                    )
                                                }}
                                            </th>
                                            <th
                                                class="px-3 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                            >
                                                {{
                                                    t(
                                                        'dashboard.admin.contracts.show.table.qty',
                                                    )
                                                }}
                                            </th>
                                            <th
                                                class="px-3 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                            >
                                                {{
                                                    t(
                                                        'dashboard.admin.contracts.show.table.notes',
                                                    )
                                                }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-gray-100 bg-white"
                                    >
                                        <tr
                                            v-for="(
                                                item, itemIndex
                                            ) in report.items"
                                            :key="`${report.id}-${itemIndex}`"
                                        >
                                            <td class="px-3 py-2">
                                                {{ item.zone_label }}
                                            </td>
                                            <td class="px-3 py-2">
                                                {{ item.damage_type }}
                                            </td>
                                            <td class="px-3 py-2">
                                                {{ item.severity }}
                                            </td>
                                            <td class="px-3 py-2">
                                                {{ item.quantity }}
                                            </td>
                                            <td class="px-3 py-2 text-gray-600">
                                                {{ item.notes || '-' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-500">
                        {{
                            t(
                                'dashboard.admin.contracts.show.no_damage_reports',
                            )
                        }}
                    </div>
                </div>

                <div class="rounded-md border p-4 md:col-span-2">
                    <h2 class="mb-3 font-semibold">
                        {{
                            t(
                                'dashboard.admin.contracts.show.sections.legacy_files',
                            )
                        }}
                    </h2>
                    <div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                        <div class="rounded border p-3">
                            <div class="mb-1 font-medium">
                                {{
                                    t(
                                        'dashboard.admin.contracts.show.start_rental_contract',
                                    )
                                }}
                            </div>
                            <a
                                v-if="startRentalDocument?.url"
                                :href="startRentalDocument.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-blue-600 hover:text-blue-700"
                            >
                                {{ startRentalDocument.name }}
                            </a>
                            <div v-else class="text-gray-500">
                                {{
                                    t(
                                        'dashboard.admin.contracts.show.no_file_uploaded',
                                    )
                                }}
                            </div>
                        </div>

                        <div class="rounded border p-3">
                            <div class="mb-1 font-medium">
                                {{
                                    t(
                                        'dashboard.admin.contracts.show.end_rental_contract',
                                    )
                                }}
                            </div>
                            <a
                                v-if="endRentalDocument?.url"
                                :href="endRentalDocument.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-blue-600 hover:text-blue-700"
                            >
                                {{ endRentalDocument.name }}
                            </a>
                            <div v-else class="text-gray-500">
                                {{
                                    t(
                                        'dashboard.admin.contracts.show.no_file_uploaded',
                                    )
                                }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-md border p-4 md:col-span-2">
                    <h2 class="mb-3 font-semibold">
                        {{
                            t(
                                'dashboard.admin.contracts.show.sections.ai_extraction',
                            )
                        }}
                    </h2>
                    <div class="text-sm">
                        <strong
                            >{{ t('dashboard.admin.contracts.show.fields.status') }}:</strong
                        >
                        {{
                            contract.ai_extraction_status ||
                            t('dashboard.admin.contracts.show.disabled')
                        }}
                    </div>
                    <pre
                        v-if="contract.ai_extracted_data"
                        class="mt-3 overflow-auto rounded bg-gray-100 p-3 text-xs"
                        >{{
                            JSON.stringify(contract.ai_extracted_data, null, 2)
                        }}</pre
                    >
                    <div v-else class="mt-2 text-sm text-gray-500">
                        {{
                            t(
                                'dashboard.admin.contracts.show.no_extracted_data',
                            )
                        }}
                    </div>
                </div>

                <div
                    v-if="contract.notes"
                    class="rounded-md border p-4 md:col-span-2"
                >
                    <h2 class="mb-2 font-semibold">
                        {{ t('dashboard.admin.contracts.show.sections.notes') }}
                    </h2>
                    <p class="text-sm whitespace-pre-line">
                        {{ contract.notes }}
                    </p>
                </div>
            </div>
        </main>

        <Dialog v-model:open="showExtendDialog">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{ localize('Force Extend', 'تمديد الإيجار') }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ localize('Choose a new end date. The system will update the contract immediately, create the extra cash payment, and notify the client.', 'اختر تاريخ انتهاء جديدًا. سيحسب النظام المبلغ الإضافي ويُنشئ دفعة نقدية مكتملة تلقائيًا.') }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-md border bg-muted/30 p-3 text-sm">
                            <div class="text-muted-foreground">{{ localize('Current End Date', 'تاريخ الانتهاء الحالي') }}</div>
                            <div class="font-medium">{{ contract.end_date || '-' }}</div>
                        </div>
                        <div class="rounded-md border bg-muted/30 p-3 text-sm">
                            <div class="text-muted-foreground">{{ localize('Daily Rate', 'السعر اليومي') }}</div>
                            <div class="font-medium">
                                {{ contract.currency || '' }} {{ extensionDailyRate.toFixed(2) }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <Label for="extend-new-end-date">{{ localize('New End Date', 'تاريخ الانتهاء الجديد') }}</Label>
                        <Input
                            id="extend-new-end-date"
                            v-model="extendForm.new_end_date"
                            type="date"
                            class="mt-1"
                        />
                        <InputError :message="extendForm.errors.new_end_date" class="mt-1" />
                    </div>

                    <div>
                        <Label for="extend-notes">{{ localize('Reason / Notes', 'ملاحظات') }}</Label>
                        <Textarea
                            id="extend-notes"
                            v-model="extendForm.notes"
                            rows="3"
                            class="mt-1"
                            :placeholder="localize('Enter the reason for the forced extension', 'ملاحظات اختيارية للتمديد')"
                        />
                        <InputError :message="extendForm.errors.notes" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-md border bg-muted/30 p-3 text-sm">
                            <div class="text-muted-foreground">{{ localize('Extra Days', 'الأيام الإضافية') }}</div>
                            <div class="font-medium">
                                {{ extensionPreview ? extensionPreview.extraDays : '-' }}
                            </div>
                        </div>
                        <div class="rounded-md border bg-muted/30 p-3 text-sm">
                            <div class="text-muted-foreground">{{ localize('Extension Amount', 'مبلغ التمديد') }}</div>
                            <div class="font-medium">
                                {{
                                    extensionPreview
                                        ? `${contract.currency || ''} ${extensionPreview.extraAmount.toFixed(2)}`
                                        : '-'
                                }}
                            </div>
                        </div>
                        <div class="rounded-md border bg-muted/30 p-3 text-sm">
                            <div class="text-muted-foreground">{{ localize('New Total', 'الإجمالي الجديد') }}</div>
                            <div class="font-medium">
                                {{
                                    extensionPreview
                                        ? `${contract.currency || ''} ${extensionPreview.newTotal.toFixed(2)}`
                                        : '-'
                                }}
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="mt-4 gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="outline">
                            {{ localize('Cancel', 'إلغاء') }}
                        </Button>
                    </DialogClose>
                    <Button type="button" :disabled="extendForm.processing" @click="submitExtension">
                        {{
                            extendForm.processing
                                ? localize('Saving...', 'جاري الحفظ...')
                                : localize('Force Extend', 'تمديد الإيجار')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showRequestDialog">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{ localize('Extension Request', 'طلب تمديد') }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ localize('Send a pending request to the client. The client will approve or reject it from their dashboard.', 'أرسل طلب تمديد معلق للعميل. سيقوم العميل بالموافقة أو الرفض من لوحته.') }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-md border bg-muted/30 p-3 text-sm">
                            <div class="text-muted-foreground">{{ localize('Current End Date', 'تاريخ الانتهاء الحالي') }}</div>
                            <div class="font-medium">{{ contract.end_date || '-' }}</div>
                        </div>
                        <div class="rounded-md border bg-muted/30 p-3 text-sm">
                            <div class="text-muted-foreground">{{ localize('Daily Rate', 'السعر اليومي') }}</div>
                            <div class="font-medium">
                                {{ contract.currency || '' }} {{ extensionDailyRate.toFixed(2) }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <Label for="request-extension-new-end-date">{{ localize('New End Date', 'تاريخ الانتهاء الجديد') }}</Label>
                        <Input
                            id="request-extension-new-end-date"
                            v-model="requestForm.new_end_date"
                            type="date"
                            class="mt-1"
                        />
                        <InputError :message="requestForm.errors.new_end_date" class="mt-1" />
                    </div>

                    <div>
                        <Label for="request-extension-notes">{{ localize('Reason / Notes', 'السبب / الملاحظات') }}</Label>
                        <Textarea
                            id="request-extension-notes"
                            v-model="requestForm.notes"
                            rows="3"
                            class="mt-1"
                            :placeholder="localize('Enter a reason for requesting the extension', 'أدخل سبب طلب التمديد')"
                        />
                        <InputError :message="requestForm.errors.notes" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-md border bg-muted/30 p-3 text-sm">
                            <div class="text-muted-foreground">{{ localize('Extra Days', 'الأيام الإضافية') }}</div>
                            <div class="font-medium">
                                {{ requestExtensionPreview ? requestExtensionPreview.extraDays : '-' }}
                            </div>
                        </div>
                        <div class="rounded-md border bg-muted/30 p-3 text-sm">
                            <div class="text-muted-foreground">{{ localize('Extension Amount', 'مبلغ التمديد') }}</div>
                            <div class="font-medium">
                                {{
                                    requestExtensionPreview
                                        ? `${contract.currency || ''} ${requestExtensionPreview.extraAmount.toFixed(2)}`
                                        : '-'
                                }}
                            </div>
                        </div>
                        <div class="rounded-md border bg-muted/30 p-3 text-sm">
                            <div class="text-muted-foreground">{{ localize('New Total', 'الإجمالي الجديد') }}</div>
                            <div class="font-medium">
                                {{
                                    requestExtensionPreview
                                        ? `${contract.currency || ''} ${requestExtensionPreview.newTotal.toFixed(2)}`
                                        : '-'
                                }}
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="mt-4 gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="outline">
                            {{ localize('Cancel', 'إلغاء') }}
                        </Button>
                    </DialogClose>
                    <Button type="button" :disabled="requestForm.processing" @click="submitRequestExtension">
                        {{
                            requestForm.processing
                                ? localize('Sending...', 'جارٍ الإرسال...')
                                : localize('Send Request', 'إرسال الطلب')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
