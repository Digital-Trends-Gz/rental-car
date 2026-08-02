<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type ThirdPartyDetails = {
    name?: string | null;
    phone?: string | null;
    plate_number?: string | null;
    details?: string | null;
    [key: string]: string | number | boolean | null | undefined;
};
type MrtaParty = Record<string, string | null | undefined>;
type MrtaWitness = { name?: string | null; address?: string | null; phone?: string | null };
type MrtaPayload = {
    accident_types: string[];
    first_party: MrtaParty;
    second_party: MrtaParty;
    witnesses: MrtaWitness[];
    accident_causes: string[];
    vehicle_damages: Record<string, string | null | undefined>;
    insurance: Record<string, string | number | boolean | null | undefined>;
    signatures: Record<string, string | null | undefined>;
};

const props = defineProps<{
    report: {
        id: number;
        accident_number: string;
        status: string;
        status_label: string;
        status_color: string;
        accident_context: 'contract' | 'employee' | 'branch' | string;
        accident_context_label: string;
        responsibility: string | null;
        responsibility_label: string;
        location_type: string | null;
        location_type_label: string;
        contract_number: string | null;
        reservation_number: string | null;
        renter_name: string | null;
        renter_phone: string | null;
        renter_id_number: string | null;
        car: string;
        branch: string | null;
        reported_by: string | null;
        reported_by_email: string | null;
        employee_name: string | null;
        employee_email: string | null;
        accident_at: string | null;
        location: string | null;
        latitude: string | number | null;
        longitude: string | number | null;
        description: string;
        police_report_number: string | null;
        has_injuries: boolean;
        third_party_involved: boolean;
        third_party_details: ThirdPartyDetails | null;
        mrta: MrtaPayload | null;
        mrta_pdf_url: string;
        notes: string | null;
        photos: Array<{
            id: number;
            photo_type: string | null;
            file_name: string | null;
            mime_type: string | null;
            size: number | null;
            notes: string | null;
            url: string | null;
        }>;
        created_at: string | null;
    };
    indexUrl: string;
}>();

const { t } = useTrans();
const translationRoot = 'dashboard.admin.accident_reports';
const translate = (key: string, params: Record<string, string | number> = {}) => t(`${translationRoot}.${key}`, params);

const isImage = (mimeType: string | null) => Boolean(mimeType?.startsWith('image/'));
const yesNo = (value: boolean) => translate(value ? 'yes' : 'no');
const nonEmpty = (value: unknown) => value !== null && value !== undefined && String(value).trim() !== '';
const emptyParty = {} as MrtaParty;
const emptyStringRecord = {} as Record<string, string | null | undefined>;
const emptyMixedRecord = {} as Record<string, string | number | boolean | null | undefined>;

const mrta = computed(() => {
    const value = props.report.mrta || ({} as Partial<MrtaPayload>);

    return {
        accident_types: Array.isArray(value.accident_types) ? value.accident_types : [],
        first_party: value.first_party && typeof value.first_party === 'object' ? value.first_party : emptyParty,
        second_party: value.second_party && typeof value.second_party === 'object' ? value.second_party : emptyParty,
        witnesses: Array.isArray(value.witnesses) ? value.witnesses : [],
        accident_causes: Array.isArray(value.accident_causes) ? value.accident_causes : [],
        vehicle_damages: value.vehicle_damages && typeof value.vehicle_damages === 'object' ? value.vehicle_damages : emptyStringRecord,
        insurance: value.insurance && typeof value.insurance === 'object' ? value.insurance : emptyMixedRecord,
        signatures: value.signatures && typeof value.signatures === 'object' ? value.signatures : emptyStringRecord,
    };
});

const thirdPartyRows = computed(() => {
    const details = props.report.third_party_details;

    if (!details) {
        return [];
    }

    return Object.entries(details)
        .filter(([, value]) => value !== null && value !== undefined && String(value).trim() !== '')
        .map(([key, value]) => ({
            key,
            label: thirdPartyLabel(key),
            value: String(value),
        }));
});

const mrtaPartyFields = computed(() => [
    { key: 'vehicle_no', label: translate('mrta.party_fields.vehicle_no') },
    { key: 'driver_name', label: translate('mrta.party_fields.driver_name') },
    { key: 'address_tel', label: translate('mrta.party_fields.address_tel') },
    { key: 'driving_license_no_category', label: translate('mrta.party_fields.driving_license_no_category') },
    { key: 'sex_nationality', label: translate('mrta.party_fields.sex_nationality') },
    { key: 'insurance_company', label: translate('mrta.party_fields.insurance_company') },
    { key: 'insurance_type', label: translate('mrta.party_fields.insurance_type') },
    { key: 'insurance_policy_no', label: translate('mrta.party_fields.insurance_policy_no') },
]);

const accidentTypeLabels: Record<string, () => string> = {
    stationary_object: () => translate('mrta.accident_types.stationary_object'),
    vehicle_collision: () => translate('mrta.accident_types.vehicle_collision'),
    roll_over: () => translate('mrta.accident_types.roll_over'),
};

const causeLabels: Record<string, () => string> = {
    over_speed: () => translate('mrta.causes.over_speed'),
    negligence: () => translate('mrta.causes.negligence'),
    fatigue: () => translate('mrta.causes.fatigue'),
    overtaking: () => translate('mrta.causes.overtaking'),
    weather_conditions: () => translate('mrta.causes.weather_conditions'),
    sudden_halt: () => translate('mrta.causes.sudden_halt'),
    no_safety_distance: () => translate('mrta.causes.no_safety_distance'),
    wrong_action: () => translate('mrta.causes.wrong_action'),
    vehicle_defects: () => translate('mrta.causes.vehicle_defects'),
    road_defects: () => translate('mrta.causes.road_defects'),
    using_gsm: () => translate('mrta.causes.using_gsm'),
};

const accidentTypeText = computed(() => mrta.value.accident_types.map((value) => accidentTypeLabels[value]?.() ?? value).join(', ') || '-');
const accidentCauseText = computed(() => mrta.value.accident_causes.map((value) => causeLabels[value]?.() ?? value).join(', ') || '-');
const witnessRows = computed(() => mrta.value.witnesses.filter((witness) => Object.values(witness || {}).some(nonEmpty)));

function thirdPartyLabel(key: string): string {
    const translated = translate(`third_party_fields.${key}`);

    return translated === `${translationRoot}.third_party_fields.${key}` ? key.replaceAll('_', ' ') : translated;
}
</script>

<template>
    <Head :title="report.accident_number" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ report.accident_number }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ translate('details.description') }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a :href="`${report.mrta_pdf_url}?download=1`" target="_blank">
                        <Button>{{ translate('details.download_mrta_pdf') }}</Button>
                    </a>
                    <Link :href="indexUrl">
                        <Button variant="outline">{{ translate('details.back') }}</Button>
                    </Link>
                </div>
            </div>

            <section class="grid grid-cols-1 gap-4 rounded-lg border bg-white p-6 shadow-sm md:grid-cols-4">
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('table.status') }}</div>
                    <span class="mt-1 inline-flex rounded-full px-2 py-1 text-xs font-medium text-white" :style="{ backgroundColor: report.status_color }">
                        {{ report.status_label }}
                    </span>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('details.workflow') }}</div>
                    <div class="font-medium">{{ report.accident_context_label }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('details.responsibility') }}</div>
                    <div class="font-medium">{{ report.responsibility_label || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('details.location_type') }}</div>
                    <div class="font-medium">{{ report.location_type_label || '-' }}</div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 rounded-lg border bg-white p-6 shadow-sm md:grid-cols-3">
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('table.car') }}</div>
                    <div class="font-medium">{{ report.car }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('details.branch') }}</div>
                    <div class="font-medium">{{ report.branch || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('details.reported_by') }}</div>
                    <div class="font-medium">{{ report.reported_by || '-' }}</div>
                    <div v-if="report.reported_by_email" class="text-xs text-muted-foreground">{{ report.reported_by_email }}</div>
                </div>

                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('table.contract') }}</div>
                    <div class="font-medium">{{ report.contract_number || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('details.reservation') }}</div>
                    <div class="font-medium">{{ report.reservation_number || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('details.employee_in_custody') }}</div>
                    <div class="font-medium">{{ report.employee_name || '-' }}</div>
                    <div v-if="report.employee_email" class="text-xs text-muted-foreground">{{ report.employee_email }}</div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 rounded-lg border bg-white p-6 shadow-sm md:grid-cols-3">
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('details.client') }}</div>
                    <div class="font-medium">{{ report.renter_name || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('details.phone') }}</div>
                    <div>{{ report.renter_phone || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('details.id_number') }}</div>
                    <div>{{ report.renter_id_number || '-' }}</div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 rounded-lg border bg-white p-6 shadow-sm md:grid-cols-3">
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('details.accident_time') }}</div>
                    <div class="font-medium">{{ report.accident_at || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('table.location') }}</div>
                    <div>{{ report.location || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('details.police_report_number') }}</div>
                    <div>{{ report.police_report_number || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('details.latitude') }}</div>
                    <div>{{ report.latitude || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('details.longitude') }}</div>
                    <div>{{ report.longitude || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('details.created_at') }}</div>
                    <div>{{ report.created_at || '-' }}</div>
                </div>
            </section>

            <section class="rounded-lg border bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-lg font-semibold">{{ translate('details.accident_description') }}</h2>
                <p class="whitespace-pre-wrap text-sm">{{ report.description }}</p>
                <div v-if="report.notes" class="mt-4 rounded-md bg-muted/40 p-3 text-sm">
                    <strong>{{ translate('details.notes') }}</strong>
                    {{ report.notes }}
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 rounded-lg border bg-white p-6 shadow-sm md:grid-cols-2">
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('details.has_injuries') }}</div>
                    <div class="font-medium">{{ yesNo(report.has_injuries) }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ translate('details.third_party_involved') }}</div>
                    <div class="font-medium">{{ yesNo(report.third_party_involved) }}</div>
                </div>
                <div v-if="thirdPartyRows.length" class="md:col-span-2">
                    <div class="text-sm text-muted-foreground">{{ translate('details.third_party_details') }}</div>
                    <dl class="mt-2 grid grid-cols-1 gap-3 rounded-md bg-muted/40 p-3 text-sm md:grid-cols-3">
                        <div v-for="row in thirdPartyRows" :key="row.key">
                            <dt class="text-xs text-muted-foreground">{{ row.label }}</dt>
                            <dd class="font-medium">{{ row.value }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="rounded-lg border bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold">{{ translate('mrta.title') }}</h2>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="rounded-md bg-muted/40 p-3">
                        <div class="text-sm text-muted-foreground">{{ translate('mrta.type_of_accident') }}</div>
                        <div class="font-medium">{{ accidentTypeText }}</div>
                    </div>
                    <div class="rounded-md bg-muted/40 p-3">
                        <div class="text-sm text-muted-foreground">{{ translate('mrta.causes_of_accident') }}</div>
                        <div class="font-medium">{{ accidentCauseText }}</div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <div class="rounded-md border p-4">
                        <h3 class="mb-3 font-semibold">{{ translate('mrta.first_party') }}</h3>
                        <dl class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                            <div v-for="field in mrtaPartyFields" :key="`first-${field.key}`">
                                <dt class="text-xs text-muted-foreground">{{ field.label }}</dt>
                                <dd class="font-medium">{{ mrta.first_party[field.key] || '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div class="rounded-md border p-4">
                        <h3 class="mb-3 font-semibold">{{ translate('mrta.second_party') }}</h3>
                        <dl class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                            <div v-for="field in mrtaPartyFields" :key="`second-${field.key}`">
                                <dt class="text-xs text-muted-foreground">{{ field.label }}</dt>
                                <dd class="font-medium">{{ mrta.second_party[field.key] || '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="rounded-md border p-4">
                        <h3 class="mb-3 font-semibold">{{ translate('mrta.witnesses') }}</h3>
                        <div v-if="!witnessRows.length" class="text-sm text-muted-foreground">-</div>
                        <div v-else class="space-y-3">
                            <div v-for="(witness, index) in witnessRows" :key="index" class="grid grid-cols-1 gap-2 rounded-md bg-muted/30 p-3 text-sm md:grid-cols-3">
                                <div><span class="text-xs text-muted-foreground">{{ translate('third_party_fields.name') }}</span><br>{{ witness.name || '-' }}</div>
                                <div><span class="text-xs text-muted-foreground">{{ translate('mrta.address') }}</span><br>{{ witness.address || '-' }}</div>
                                <div><span class="text-xs text-muted-foreground">{{ translate('details.phone') }}</span><br>{{ witness.phone || '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-md border p-4">
                        <h3 class="mb-3 font-semibold">{{ translate('mrta.vehicle_damages') }}</h3>
                        <dl class="grid grid-cols-1 gap-3 text-sm">
                            <div>
                                <dt class="text-xs text-muted-foreground">{{ translate('mrta.first_vehicle_damages') }}</dt>
                                <dd class="whitespace-pre-wrap font-medium">{{ mrta.vehicle_damages.first_party_notes || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">{{ translate('mrta.second_vehicle_damages') }}</dt>
                                <dd class="whitespace-pre-wrap font-medium">{{ mrta.vehicle_damages.second_party_notes || '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="mt-4 rounded-md border p-4">
                    <h3 class="mb-3 font-semibold">{{ translate('mrta.insurance_use') }}</h3>
                    <dl class="grid grid-cols-1 gap-3 text-sm md:grid-cols-3">
                        <div>
                            <dt class="text-xs text-muted-foreground">{{ translate('mrta.policy_no') }}</dt>
                            <dd class="font-medium">{{ mrta.insurance.policy_no || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">{{ translate('mrta.insurance_type') }}</dt>
                            <dd class="font-medium">{{ mrta.insurance.type || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">{{ translate('mrta.claim_no') }}</dt>
                            <dd class="font-medium">{{ mrta.insurance.claim_no || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">{{ translate('mrta.company_will_repair_damages') }}</dt>
                            <dd class="font-medium">{{ yesNo(Boolean(mrta.insurance.company_will_repair)) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">{{ translate('mrta.technical_opinion_required') }}</dt>
                            <dd class="font-medium">{{ yesNo(Boolean(mrta.insurance.technical_opinion_required)) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">{{ translate('mrta.signatory_name') }}</dt>
                            <dd class="font-medium">{{ mrta.insurance.signatory_name || '-' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="rounded-lg border bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold">{{ translate('details.photos') }}</h2>
                <div v-if="report.photos.length === 0" class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                    {{ translate('details.no_photos_uploaded') }}
                </div>
                <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <a
                        v-for="photo in report.photos"
                        :key="photo.id"
                        :href="photo.url || '#'"
                        target="_blank"
                        class="rounded-md border p-3 hover:bg-muted/30"
                    >
                        <img v-if="isImage(photo.mime_type)" :src="photo.url || ''" class="mb-3 h-40 w-full rounded object-cover" />
                        <div v-else class="mb-3 flex h-40 items-center justify-center rounded bg-muted text-sm">
                            {{ translate('details.open_file') }}
                        </div>
                        <div class="text-sm font-medium">{{ photo.file_name || translate('details.file') }}</div>
                        <div class="text-xs text-muted-foreground">{{ photo.photo_type || '-' }}</div>
                        <div v-if="photo.notes" class="mt-1 text-xs">{{ photo.notes }}</div>
                    </a>
                </div>
            </section>
        </main>
    </AdminLayout>
</template>
