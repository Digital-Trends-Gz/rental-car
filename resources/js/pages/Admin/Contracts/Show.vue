<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

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
    };
}>();

const { t } = useTrans();
const pageTitle = computed(() =>
    t('dashboard.admin.contracts.show.head_title', {
        number: props.contract.contract_number,
    }),
);
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
                        v-if="actions.pdf_en"
                        as="a"
                        :href="actions.pdf_en"
                        variant="outline"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {{ t('dashboard.admin.contracts.show.pdf_en') }}
                    </Button>
                    <Button
                        v-if="actions.pdf_ar"
                        as="a"
                        :href="actions.pdf_ar"
                        variant="outline"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {{ t('dashboard.admin.contracts.show.pdf_ar') }}
                    </Button>
                    <Button
                        v-else-if="actions.pdf"
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
    </AdminLayout>
</template>
