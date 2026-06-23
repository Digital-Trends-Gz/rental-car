<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import CarDamageInspector from '@/components/CarDamageInspector.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type ViewSide = 'front' | 'rear' | 'left' | 'right' | 'top';
type DamageTiming = 'before_pickup' | 'after_return';

interface DamageItem {
    id?: number | null;
    zone_code: string;
    view_side: ViewSide;
    damage_type: string;
    severity: string;
    damage_timing: DamageTiming;
    quantity: number;
    marker_x: number | null;
    marker_y: number | null;
    estimated_cost: number | null;
    source_type?: string;
    notes: string;
}

interface ZoneOption {
    code: string;
    label: string;
}

interface ZoneView {
    code: string;
    label: string;
    view_side: ViewSide;
    x: number;
    y: number;
    width: number;
    height: number;
}

const props = defineProps<{
    report: {
        id?: number;
        report_number: string;
        car_id?: number | null;
        contract_id?: number | null;
        reservation_id?: number | null;
        report_type: string;
        status: string;
        inspected_at?: string | null;
        odometer?: number | null;
        summary?: string | null;
        items: DamageItem[];
    };
    cars: Array<{ id: number; label: string; branch_id?: number | null }>;
    contracts: Array<{
        id: number;
        label: string;
        reservation_id?: number | null;
        branch_id?: number | null;
    }>;
    reservations: Array<{ id: number; label: string; car_id?: number | null }>;
    reportTypes: Array<{ value: string; label: string }>;
    statuses: Array<{ value: string; label: string }>;
    damageTypes: Array<{ value: string; label: string }>;
    severityLevels: Array<{ value: string; label: string }>;
    damageTimings: Array<{ value: string; label: string }>;
    zoneOptions: ZoneOption[];
    zoneViews: ZoneView[];
    zoneLabelMap: Record<string, string>;
    currentCarDamages: Array<{
        id: number;
        zone_label: string;
        view_side_label: string;
        damage_type_label: string;
        severity_label: string;
        damage_timing: string;
        damage_timing_label: string;
        quantity: number;
        notes: string | null;
        first_detected_at: string | null;
    }>;
    indexUrl: string;
    submitUrl: string;
    method: 'post' | 'put';
}>();

const { t } = useTrans();

const selectedView = ref<ViewSide>(
    (props.report.items[0]?.view_side as ViewSide | undefined) ?? 'left',
);
const editingIndex = ref<number | null>(null);
const itemError = ref('');

const form = useForm({
    report_number: props.report.report_number ?? '',
    contract_id: props.report.contract_id
        ? String(props.report.contract_id)
        : '',
    report_type: props.report.report_type ?? 'before_delivery',
    status: props.report.status ?? 'draft',
    inspected_at: props.report.inspected_at ?? '',
    odometer: props.report.odometer ? String(props.report.odometer) : '',
    summary: props.report.summary ?? '',
    items: (props.report.items ?? []).map((item) => ({
        id: item.id ?? null,
        zone_code: item.zone_code,
        view_side: item.view_side,
        damage_type: item.damage_type,
        severity: item.severity,
        damage_timing: (item as DamageItem & { damage_timing?: DamageTiming }).damage_timing ?? (props.report.report_type === 'after_return' ? 'after_return' : 'before_pickup'),
        quantity: Number(item.quantity || 1),
        marker_x: item.marker_x ?? null,
        marker_y: item.marker_y ?? null,
        estimated_cost: item.estimated_cost ?? null,
        source_type: item.source_type ?? 'employee',
        notes: item.notes ?? '',
    })),
});

const carById = computed<Record<string, { id: number; label: string }>>(() =>
    props.cars.reduce<Record<string, { id: number; label: string }>>(
        (acc, car) => {
            acc[String(car.id)] = { id: car.id, label: car.label };
            return acc;
        },
        {},
    ),
);

const reservationById = computed<
    Record<string, { id: number; label: string; car_id?: number | null }>
>(() =>
    props.reservations.reduce<
        Record<string, { id: number; label: string; car_id?: number | null }>
    >((acc, reservation) => {
        acc[String(reservation.id)] = reservation;
        return acc;
    }, {}),
);

const contractById = computed<
    Record<
        string,
        { id: number; label: string; reservation_id?: number | null }
    >
>(() =>
    props.contracts.reduce<
        Record<
            string,
            { id: number; label: string; reservation_id?: number | null }
        >
    >((acc, contract) => {
        acc[String(contract.id)] = contract;
        return acc;
    }, {}),
);

const damageTypeLabels = computed<Record<string, string>>(() =>
    props.damageTypes.reduce<Record<string, string>>((acc, type) => {
        acc[type.value] = type.label;
        return acc;
    }, {}),
);

const severityLabels = computed<Record<string, string>>(() =>
    props.severityLevels.reduce<Record<string, string>>((acc, severity) => {
        acc[severity.value] = severity.label;
        return acc;
    }, {}),
);

const selectedContract = computed(() =>
    form.contract_id ? (contractById.value[form.contract_id] ?? null) : null,
);
const selectedReservation = computed(() => {
    const reservationId = selectedContract.value?.reservation_id;
    return reservationId
        ? (reservationById.value[String(reservationId)] ?? null)
        : null;
});
const selectedCar = computed(() => {
    const carId = selectedReservation.value?.car_id;
    return carId ? (carById.value[String(carId)] ?? null) : null;
});

const pageTitle = computed(() =>
    props.report.id
        ? t('dashboard.admin.damage_reports.edit.head_title_edit', {
              number: props.report.report_number,
          })
        : t('dashboard.admin.damage_reports.edit.head_title_create'),
);

const submitLabel = computed(() =>
    props.method === 'put'
        ? t('dashboard.admin.damage_reports.edit.save_report')
        : t('dashboard.admin.damage_reports.edit.create_report'),
);

function viewLabel(view: ViewSide) {
    return t(`dashboard.admin.damage_reports.view_sides.${view}`);
}

function emptyDraft(viewSide: ViewSide = selectedView.value): DamageItem {
    return {
        zone_code: '',
        view_side: viewSide,
        damage_type: props.damageTypes[0]?.value ?? 'scratch',
        severity: props.severityLevels[0]?.value ?? 'minor',
        damage_timing: props.report.report_type === 'after_return' ? 'after_return' : 'before_pickup',
        quantity: 1,
        marker_x: null,
        marker_y: null,
        estimated_cost: null,
        notes: '',
    };
}

const itemDraft = ref<DamageItem>(emptyDraft());

function resolveMarker(zoneCode: string, viewSide: ViewSide) {
    const exact = props.zoneViews.find(
        (zone) => zone.code === zoneCode && zone.view_side === viewSide,
    );
    const fallback =
        exact ?? props.zoneViews.find((zone) => zone.code === zoneCode);

    if (!fallback) {
        return { x: null, y: null };
    }

    return {
        x: Number((fallback.x + fallback.width / 2).toFixed(2)),
        y: Number((fallback.y + fallback.height / 2).toFixed(2)),
    };
}

function selectZone(payload: {
    zoneCode: string;
    viewSide: ViewSide;
    x: number;
    y: number;
}) {
    selectedView.value = payload.viewSide;
    itemDraft.value.zone_code = payload.zoneCode;
    itemDraft.value.view_side = payload.viewSide;
    itemDraft.value.marker_x = payload.x;
    itemDraft.value.marker_y = payload.y;
    itemError.value = '';
}

function startEdit(index: number) {
    const item = form.items[index];
    if (!item) return;

    editingIndex.value = index;
    selectedView.value = item.view_side as ViewSide;
    itemDraft.value = {
        id: item.id ?? null,
        zone_code: item.zone_code,
        view_side: item.view_side as ViewSide,
        damage_type: item.damage_type,
        severity: item.severity,
        damage_timing: (item as DamageItem & { damage_timing?: DamageTiming }).damage_timing ?? (props.report.report_type === 'after_return' ? 'after_return' : 'before_pickup'),
        quantity: Number(item.quantity || 1),
        marker_x: item.marker_x ?? null,
        marker_y: item.marker_y ?? null,
        estimated_cost: item.estimated_cost ?? null,
        notes: item.notes ?? '',
    };
    itemError.value = '';
}

function resetDraft() {
    editingIndex.value = null;
    itemDraft.value = emptyDraft(selectedView.value);
    itemError.value = '';
}

function saveItem() {
    if (!form.contract_id) {
        itemError.value = t(
            'dashboard.admin.damage_reports.edit.errors.select_contract_first',
        );
        return;
    }

    if (!itemDraft.value.zone_code) {
        itemError.value = t(
            'dashboard.admin.damage_reports.edit.errors.select_zone_first',
        );
        return;
    }

    if (
        !itemDraft.value.damage_type ||
        !itemDraft.value.severity ||
        !itemDraft.value.damage_timing ||
        Number(itemDraft.value.quantity) < 1
    ) {
        itemError.value = t(
            'dashboard.admin.damage_reports.edit.errors.damage_fields_required',
        );
        return;
    }

    const marker = resolveMarker(
        itemDraft.value.zone_code,
        itemDraft.value.view_side,
    );
    const payload: DamageItem = {
        id: itemDraft.value.id ?? null,
        zone_code: itemDraft.value.zone_code,
        view_side: itemDraft.value.view_side,
        damage_type: itemDraft.value.damage_type,
        severity: itemDraft.value.severity,
        damage_timing: itemDraft.value.damage_timing,
        quantity: Number(itemDraft.value.quantity),
        marker_x: itemDraft.value.marker_x ?? marker.x,
        marker_y: itemDraft.value.marker_y ?? marker.y,
        estimated_cost:
            itemDraft.value.estimated_cost !== null &&
            itemDraft.value.estimated_cost !== undefined &&
            itemDraft.value.estimated_cost !== ''
                ? Number(itemDraft.value.estimated_cost)
                : null,
        notes: itemDraft.value.notes ?? '',
    };

    if (editingIndex.value !== null) {
        form.items.splice(editingIndex.value, 1, payload);
    } else {
        form.items.push(payload);
    }

    resetDraft();
}

function removeItem(index: number) {
    form.items.splice(index, 1);

    if (editingIndex.value === index) {
        resetDraft();
    } else if (editingIndex.value !== null && editingIndex.value > index) {
        editingIndex.value -= 1;
    }
}

const totalQuantity = computed(() =>
    form.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0),
);
const totalEstimatedCost = computed(() =>
    form.items.reduce((sum, item) => sum + Number(item.estimated_cost || 0), 0),
);

function submit() {
    if (props.method === 'put') {
        form.put(props.submitUrl);
        return;
    }

    form.post(props.submitUrl);
}
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ pageTitle }}</h1>
                <Link :href="indexUrl">
                    <Button variant="outline">{{
                        t('dashboard.admin.common.back')
                    }}</Button>
                </Link>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <div
                    class="grid grid-cols-1 gap-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:grid-cols-2 xl:grid-cols-4"
                >
                    <div>
                        <Label for="report_number">{{
                            t('dashboard.admin.damage_reports.edit.fields.report_number')
                        }}</Label>
                        <Input
                            id="report_number"
                            v-model="form.report_number"
                        />
                        <InputError
                            :message="form.errors.report_number"
                            class="mt-1"
                        />
                    </div>

                    <div>
                        <Label for="contract_id">{{
                            t('dashboard.admin.damage_reports.edit.fields.contract')
                        }}</Label>
                        <select
                            id="contract_id"
                            v-model="form.contract_id"
                            class="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2"
                        >
                            <option value="">{{
                                t(
                                    'dashboard.admin.damage_reports.edit.placeholders.select_contract',
                                )
                            }}</option>
                            <option
                                v-for="contract in contracts"
                                :key="contract.id"
                                :value="String(contract.id)"
                            >
                                {{ contract.label }}
                            </option>
                        </select>
                        <InputError
                            :message="form.errors.contract_id"
                            class="mt-1"
                        />
                    </div>

                    <div>
                        <Label>{{
                            t('dashboard.admin.damage_reports.edit.fields.reservation')
                        }}</Label>
                        <div
                            class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                        >
                            {{
                                selectedReservation?.label ||
                                t(
                                    'dashboard.admin.damage_reports.edit.placeholders.reservation_derived',
                                )
                            }}
                        </div>
                    </div>

                    <div>
                        <Label>{{
                            t('dashboard.admin.damage_reports.edit.fields.car')
                        }}</Label>
                        <div
                            class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                        >
                            {{
                                selectedCar?.label ||
                                t(
                                    'dashboard.admin.damage_reports.edit.placeholders.car_derived',
                                )
                            }}
                        </div>
                    </div>

                    <div>
                        <Label for="report_type">{{
                            t('dashboard.admin.damage_reports.edit.fields.report_type')
                        }}</Label>
                        <select
                            id="report_type"
                            v-model="form.report_type"
                            class="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2"
                        >
                            <option
                                v-for="type in reportTypes"
                                :key="type.value"
                                :value="type.value"
                            >
                                {{ type.label }}
                            </option>
                        </select>
                        <InputError
                            :message="form.errors.report_type"
                            class="mt-1"
                        />
                    </div>

                    <div>
                        <Label for="status">{{
                            t('dashboard.admin.damage_reports.edit.fields.status')
                        }}</Label>
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2"
                        >
                            <option
                                v-for="status in statuses"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </option>
                        </select>
                        <InputError
                            :message="form.errors.status"
                            class="mt-1"
                        />
                    </div>

                    <div>
                        <Label for="inspected_at">{{
                            t(
                                'dashboard.admin.damage_reports.edit.fields.inspection_date',
                            )
                        }}</Label>
                        <Input
                            id="inspected_at"
                            v-model="form.inspected_at"
                            type="datetime-local"
                        />
                        <InputError
                            :message="form.errors.inspected_at"
                            class="mt-1"
                        />
                    </div>

                    <div>
                        <Label for="odometer">{{
                            t('dashboard.admin.damage_reports.edit.fields.odometer')
                        }}</Label>
                        <Input
                            id="odometer"
                            v-model="form.odometer"
                            type="number"
                            min="0"
                        />
                        <InputError
                            :message="form.errors.odometer"
                            class="mt-1"
                        />
                    </div>

                    <div class="xl:col-span-4">
                        <Label for="summary">{{
                            t('dashboard.admin.damage_reports.edit.fields.summary')
                        }}</Label>
                        <textarea
                            id="summary"
                            v-model="form.summary"
                            rows="3"
                            class="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2"
                            :placeholder="
                                t(
                                    'dashboard.admin.damage_reports.edit.placeholders.summary',
                                )
                            "
                        />
                        <InputError
                            :message="form.errors.summary"
                            class="mt-1"
                        />
                    </div>
                </div>

                <div
                    class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(360px,0.9fr)]"
                >
                    <CarDamageInspector
                        :zone-views="zoneViews"
                        :items="form.items"
                        :selected-zone-code="itemDraft.zone_code"
                        :current-view="selectedView"
                        @update:current-view="selectedView = $event"
                        @select-zone="selectZone"
                    />

                    <div
                        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div
                            class="mb-4 flex items-start justify-between gap-4"
                        >
                            <div>
                                <h2 class="text-lg font-semibold">
                                    {{
                                        editingIndex !== null
                                            ? t(
                                                  'dashboard.admin.damage_reports.edit.sections.edit_damage_item',
                                              )
                                            : t(
                                                  'dashboard.admin.damage_reports.edit.sections.add_damage_item',
                                              )
                                    }}
                                </h2>
                                <p class="text-sm text-slate-500">
                                    {{
                                        t(
                                            'dashboard.admin.damage_reports.edit.sections.body_source',
                                        )
                                    }}
                                </p>
                            </div>
                            <Button
                                v-if="editingIndex !== null"
                                type="button"
                                variant="outline"
                                @click="resetDraft"
                                >{{
                                    t(
                                        'dashboard.admin.damage_reports.edit.actions.cancel_edit',
                                    )
                                }}</Button
                            >
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <Label for="zone_code">{{
                                    t('dashboard.admin.damage_reports.edit.fields.zone')
                                }}</Label>
                                <select
                                    id="zone_code"
                                    v-model="itemDraft.zone_code"
                                    class="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2"
                                    @change="
                                        () => {
                                            const marker = resolveMarker(
                                                itemDraft.zone_code,
                                                selectedView,
                                            );
                                            itemDraft.view_side = selectedView;
                                            itemDraft.marker_x = marker.x;
                                            itemDraft.marker_y = marker.y;
                                        }
                                    "
                                >
                                    <option value="">{{
                                        t(
                                            'dashboard.admin.damage_reports.edit.placeholders.select_zone',
                                        )
                                    }}</option>
                                    <option
                                        v-for="zone in zoneOptions"
                                        :key="zone.code"
                                        :value="zone.code"
                                    >
                                        {{ zone.label }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <Label for="damage_type">{{
                                    t(
                                        'dashboard.admin.damage_reports.edit.fields.damage_type',
                                    )
                                }}</Label>
                                <select
                                    id="damage_type"
                                    v-model="itemDraft.damage_type"
                                    class="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2"
                                >
                                    <option
                                        v-for="type in damageTypes"
                                        :key="type.value"
                                        :value="type.value"
                                    >
                                        {{ type.label }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <Label for="severity">{{
                                    t('dashboard.admin.damage_reports.edit.fields.severity')
                                }}</Label>
                                <select
                                    id="severity"
                                    v-model="itemDraft.severity"
                                    class="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2"
                                >
                                    <option
                                        v-for="severity in severityLevels"
                                        :key="severity.value"
                                        :value="severity.value"
                                    >
                                        {{ severity.label }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <Label for="damage_timing">{{
                                    t('dashboard.admin.damage_reports.edit.fields.damage_timing')
                                }}</Label>
                                <select
                                    id="damage_timing"
                                    v-model="itemDraft.damage_timing"
                                    class="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2"
                                >
                                    <option
                                        v-for="timing in damageTimings"
                                        :key="timing.value"
                                        :value="timing.value"
                                    >
                                        {{ timing.label }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <Label for="quantity">{{
                                    t('dashboard.admin.damage_reports.edit.fields.quantity')
                                }}</Label>
                                <Input
                                    id="quantity"
                                    v-model="itemDraft.quantity"
                                    type="number"
                                    min="1"
                                    max="99"
                                />
                            </div>

                            <div>
                                <Label for="estimated_cost">{{
                                    t(
                                        'dashboard.admin.damage_reports.edit.fields.estimated_cost',
                                    )
                                }}</Label>
                                <Input
                                    id="estimated_cost"
                                    v-model="itemDraft.estimated_cost"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                />
                            </div>

                            <div class="md:col-span-2">
                                <Label for="notes">{{
                                    t('dashboard.admin.damage_reports.edit.fields.notes')
                                }}</Label>
                                <textarea
                                    id="notes"
                                    v-model="itemDraft.notes"
                                    rows="3"
                                    class="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2"
                                    :placeholder="
                                        t(
                                            'dashboard.admin.damage_reports.edit.placeholders.notes',
                                        )
                                    "
                                />
                            </div>
                        </div>
                        <p v-if="itemError" class="mt-3 text-sm text-red-600">
                            {{ itemError }}
                        </p>

                        <div class="mt-4 flex gap-2">
                            <Button type="button" @click="saveItem">
                                {{
                                    editingIndex !== null
                                        ? t(
                                              'dashboard.admin.damage_reports.edit.actions.update_item',
                                          )
                                        : t(
                                              'dashboard.admin.damage_reports.edit.actions.add_item',
                                          )
                                }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                @click="resetDraft"
                                >{{
                                    t(
                                        'dashboard.admin.damage_reports.edit.actions.clear',
                                    )
                                }}</Button
                            >
                        </div>

                        <div class="mt-6 rounded-xl bg-slate-50 p-4 text-sm">
                            <div class="font-medium text-slate-900">
                                {{
                                    t(
                                        'dashboard.admin.damage_reports.edit.sections.report_totals',
                                    )
                                }}
                            </div>
                            <div class="mt-2 text-slate-600">
                                {{
                                    t(
                                        'dashboard.admin.damage_reports.edit.totals.damage_entries',
                                        {
                                            count: form.items.length,
                                        },
                                    )
                                }}
                            </div>
                            <div class="text-slate-600">
                                {{
                                    t(
                                        'dashboard.admin.damage_reports.edit.totals.total_quantity',
                                        {
                                            count: totalQuantity,
                                        },
                                    )
                                }}
                            </div>
                            <div class="text-slate-600">
                                {{
                                    t(
                                        'dashboard.admin.damage_reports.edit.totals.estimated_cost',
                                        {
                                            amount: totalEstimatedCost.toFixed(2),
                                        },
                                    )
                                }}
                            </div>
                        </div>

                        <div class="mt-6">
                            <div class="mb-2 text-sm font-medium text-slate-900">
                                {{
                                    t(
                                        'dashboard.admin.damage_reports.edit.sections.current_car_damages',
                                    )
                                }}
                            </div>
                            <div
                                v-if="currentCarDamages.length === 0"
                                class="rounded-xl border border-dashed border-slate-300 px-4 py-4 text-sm text-slate-500"
                            >
                                {{
                                    t(
                                        'dashboard.admin.damage_reports.edit.empty.no_current_damages',
                                    )
                                }}
                            </div>
                            <div v-else class="overflow-x-auto rounded-xl border">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left">{{
                                                t(
                                                    'dashboard.admin.damage_reports.edit.table.zone',
                                                )
                                            }}</th>
                                            <th class="px-3 py-2 text-left">{{
                                                t(
                                                    'dashboard.admin.damage_reports.edit.table.view',
                                                )
                                            }}</th>
                                            <th class="px-3 py-2 text-left">{{
                                                t(
                                                    'dashboard.admin.damage_reports.edit.table.type',
                                                )
                                            }}</th>
                                            <th class="px-3 py-2 text-left">{{
                                                t(
                                                    'dashboard.admin.damage_reports.edit.table.severity',
                                                )
                                            }}</th>
                                            <th class="px-3 py-2 text-left">{{
                                                t(
                                                    'dashboard.admin.damage_reports.edit.table.timing',
                                                )
                                            }}</th>
                                            <th class="px-3 py-2 text-left">{{
                                                t(
                                                    'dashboard.admin.damage_reports.edit.table.qty',
                                                )
                                            }}</th>
                                            <th class="px-3 py-2 text-left">{{
                                                t(
                                                    'dashboard.admin.damage_reports.edit.table.source',
                                                )
                                            }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        <tr v-for="damage in currentCarDamages" :key="damage.id">
                                            <td class="px-3 py-2">{{ damage.zone_label }}</td>
                                            <td class="px-3 py-2">{{ damage.view_side_label }}</td>
                                            <td class="px-3 py-2">{{ damage.damage_type_label }}</td>
                                            <td class="px-3 py-2">{{ damage.severity_label }}</td>
                                            <td class="px-3 py-2">{{ damage.damage_timing_label || '-' }}</td>
                                            <td class="px-3 py-2">{{ damage.quantity }}</td>
                                            <td class="px-3 py-2">
                                                <span v-if="damage.source_type === 'ai'" class="inline-flex items-center rounded-md bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10">AI</span>
                                                <span v-else class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">Employee</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold">{{
                                t(
                                    'dashboard.admin.damage_reports.edit.sections.damage_items',
                                )
                            }}</h2>
                            <p class="text-sm text-slate-500">
                                {{
                                    t(
                                        'dashboard.admin.damage_reports.edit.sections.inspection_snapshot',
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="form.items.length === 0"
                        class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500"
                    >
                        {{
                            t(
                                'dashboard.admin.damage_reports.edit.empty.no_damage_items',
                            )
                        }}
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead>
                                <tr
                                    class="text-left text-xs uppercase tracking-wider text-slate-500"
                                >
                                    <th class="px-3 py-2">{{
                                        t(
                                            'dashboard.admin.damage_reports.edit.table.zone',
                                        )
                                    }}</th>
                                    <th class="px-3 py-2">{{
                                        t(
                                            'dashboard.admin.damage_reports.edit.table.type',
                                        )
                                    }}</th>
                                    <th class="px-3 py-2">{{
                                        t(
                                            'dashboard.admin.damage_reports.edit.table.severity',
                                        )
                                    }}</th>
                                    <th class="px-3 py-2">{{
                                        t(
                                            'dashboard.admin.damage_reports.edit.table.qty',
                                        )
                                    }}</th>
                                    <th class="px-3 py-2">{{
                                        t(
                                            'dashboard.admin.damage_reports.edit.table.cost',
                                        )
                                    }}</th>
                                    <th class="px-3 py-2">{{
                                        t(
                                            'dashboard.admin.damage_reports.edit.table.source',
                                        )
                                    }}</th>
                                    <th class="px-3 py-2">{{
                                        t(
                                            'dashboard.admin.damage_reports.edit.table.notes',
                                        )
                                    }}</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="(item, index) in form.items"
                                    :key="`${item.zone_code}-${index}`"
                                    class="text-sm"
                                >
                                    <td class="px-3 py-3">
                                        <div class="font-medium">
                                            {{
                                                zoneLabelMap[item.zone_code] ||
                                                item.zone_code
                                            }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ viewLabel(item.view_side) }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-3">
                                        {{
                                            damageTypeLabels[item.damage_type] ||
                                            item.damage_type
                                        }}
                                    </td>
                                    <td class="px-3 py-3">
                                        {{
                                            severityLabels[item.severity] ||
                                            item.severity
                                        }}
                                    </td>
                                    <td class="px-3 py-3">
                                        {{ item.quantity }}
                                    </td>
                                    <td class="px-3 py-3">
                                        {{ item.estimated_cost ?? '-' }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <span v-if="item.source_type === 'ai'" class="inline-flex items-center rounded-md bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10">AI</span>
                                        <span v-else class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">Employee</span>
                                    </td>
                                    <td class="px-3 py-3 text-slate-600">
                                        {{ item.notes || '-' }}
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                @click="startEdit(index)"
                                                >{{
                                                    t(
                                                        'dashboard.admin.damage_reports.edit.actions.edit_item',
                                                    )
                                                }}</Button
                                            >
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="destructive"
                                                @click="removeItem(index)"
                                                >{{
                                                    t(
                                                        'dashboard.admin.damage_reports.edit.actions.delete_item',
                                                    )
                                                }}</Button
                                            >
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <InputError :message="form.errors.items" class="mt-3" />
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Button type="submit" :disabled="form.processing">{{
                        submitLabel
                    }}</Button>
                    <Link :href="indexUrl">
                        <Button type="button" variant="outline">{{
                            t('dashboard.admin.common.cancel')
                        }}</Button>
                    </Link>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
