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

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const isImage = (mimeType: string | null) => Boolean(mimeType?.startsWith('image/'));
const yesNo = (value: boolean) => (value ? localize('Yes', '\u0646\u0639\u0645') : localize('No', '\u0644\u0627'));

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

function thirdPartyLabel(key: string): string {
    const labels: Record<string, string> = {
        name: localize('Name', '\u0627\u0644\u0627\u0633\u0645'),
        phone: localize('Phone', '\u0627\u0644\u0647\u0627\u062a\u0641'),
        plate_number: localize('Plate number', '\u0631\u0642\u0645 \u0627\u0644\u0644\u0648\u062d\u0629'),
        details: localize('Details', '\u0627\u0644\u062a\u0641\u0627\u0635\u064a\u0644'),
    };

    return labels[key] ?? key.replaceAll('_', ' ');
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
                        {{ localize('Accident report details', '\u062a\u0641\u0627\u0635\u064a\u0644 \u0628\u0644\u0627\u063a \u0627\u0644\u062d\u0627\u062f\u062b') }}
                    </p>
                </div>

                <Link :href="indexUrl">
                    <Button variant="outline">{{ localize('Back', '\u0631\u062c\u0648\u0639') }}</Button>
                </Link>
            </div>

            <section class="grid grid-cols-1 gap-4 rounded-lg border bg-white p-6 shadow-sm md:grid-cols-4">
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Status', '\u0627\u0644\u062d\u0627\u0644\u0629') }}</div>
                    <span class="mt-1 inline-flex rounded-full px-2 py-1 text-xs font-medium text-white" :style="{ backgroundColor: report.status_color }">
                        {{ report.status_label }}
                    </span>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Workflow', '\u0646\u0648\u0639 \u0627\u0644\u062d\u0627\u062f\u062b') }}</div>
                    <div class="font-medium">{{ report.accident_context_label }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Responsibility', '\u0627\u0644\u0645\u0633\u0624\u0648\u0644\u064a\u0629') }}</div>
                    <div class="font-medium">{{ report.responsibility_label || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Location type', '\u0646\u0648\u0639 \u0627\u0644\u0645\u0648\u0642\u0639') }}</div>
                    <div class="font-medium">{{ report.location_type_label || '-' }}</div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 rounded-lg border bg-white p-6 shadow-sm md:grid-cols-3">
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Car', '\u0627\u0644\u0633\u064a\u0627\u0631\u0629') }}</div>
                    <div class="font-medium">{{ report.car }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Branch', '\u0627\u0644\u0641\u0631\u0639') }}</div>
                    <div class="font-medium">{{ report.branch || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Reported by', '\u0623\u062f\u062e\u0644\u0647') }}</div>
                    <div class="font-medium">{{ report.reported_by || '-' }}</div>
                    <div v-if="report.reported_by_email" class="text-xs text-muted-foreground">{{ report.reported_by_email }}</div>
                </div>

                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Contract', '\u0627\u0644\u0639\u0642\u062f') }}</div>
                    <div class="font-medium">{{ report.contract_number || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Reservation', '\u0627\u0644\u062d\u062c\u0632') }}</div>
                    <div class="font-medium">{{ report.reservation_number || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Employee in custody', '\u0627\u0644\u0645\u0648\u0638\u0641 \u0627\u0644\u0645\u0633\u0624\u0648\u0644') }}</div>
                    <div class="font-medium">{{ report.employee_name || '-' }}</div>
                    <div v-if="report.employee_email" class="text-xs text-muted-foreground">{{ report.employee_email }}</div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 rounded-lg border bg-white p-6 shadow-sm md:grid-cols-3">
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Client', '\u0627\u0644\u0639\u0645\u064a\u0644') }}</div>
                    <div class="font-medium">{{ report.renter_name || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Phone', '\u0627\u0644\u0647\u0627\u062a\u0641') }}</div>
                    <div>{{ report.renter_phone || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('ID number', '\u0631\u0642\u0645 \u0627\u0644\u0647\u0648\u064a\u0629') }}</div>
                    <div>{{ report.renter_id_number || '-' }}</div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 rounded-lg border bg-white p-6 shadow-sm md:grid-cols-3">
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Accident time', '\u0648\u0642\u062a \u0627\u0644\u062d\u0627\u062f\u062b') }}</div>
                    <div class="font-medium">{{ report.accident_at || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Location', '\u0627\u0644\u0645\u0648\u0642\u0639') }}</div>
                    <div>{{ report.location || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Police report number', '\u0631\u0642\u0645 \u062a\u0642\u0631\u064a\u0631 \u0627\u0644\u0634\u0631\u0637\u0629') }}</div>
                    <div>{{ report.police_report_number || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Latitude', '\u062e\u0637 \u0627\u0644\u0639\u0631\u0636') }}</div>
                    <div>{{ report.latitude || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Longitude', '\u062e\u0637 \u0627\u0644\u0637\u0648\u0644') }}</div>
                    <div>{{ report.longitude || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Created at', '\u062a\u0627\u0631\u064a\u062e \u0627\u0644\u0625\u0646\u0634\u0627\u0621') }}</div>
                    <div>{{ report.created_at || '-' }}</div>
                </div>
            </section>

            <section class="rounded-lg border bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-lg font-semibold">{{ localize('Description', '\u0648\u0635\u0641 \u0627\u0644\u062d\u0627\u062f\u062b') }}</h2>
                <p class="whitespace-pre-wrap text-sm">{{ report.description }}</p>
                <div v-if="report.notes" class="mt-4 rounded-md bg-muted/40 p-3 text-sm">
                    <strong>{{ localize('Notes:', '\u0645\u0644\u0627\u062d\u0638\u0627\u062a:') }}</strong>
                    {{ report.notes }}
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 rounded-lg border bg-white p-6 shadow-sm md:grid-cols-2">
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Has injuries', '\u064a\u0648\u062c\u062f \u0625\u0635\u0627\u0628\u0627\u062a') }}</div>
                    <div class="font-medium">{{ yesNo(report.has_injuries) }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Third party involved', '\u064a\u0648\u062c\u062f \u0637\u0631\u0641 \u062b\u0627\u0644\u062b') }}</div>
                    <div class="font-medium">{{ yesNo(report.third_party_involved) }}</div>
                </div>
                <div v-if="thirdPartyRows.length" class="md:col-span-2">
                    <div class="text-sm text-muted-foreground">{{ localize('Third party details', '\u0628\u064a\u0627\u0646\u0627\u062a \u0627\u0644\u0637\u0631\u0641 \u0627\u0644\u062b\u0627\u0644\u062b') }}</div>
                    <dl class="mt-2 grid grid-cols-1 gap-3 rounded-md bg-muted/40 p-3 text-sm md:grid-cols-3">
                        <div v-for="row in thirdPartyRows" :key="row.key">
                            <dt class="text-xs text-muted-foreground">{{ row.label }}</dt>
                            <dd class="font-medium">{{ row.value }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="rounded-lg border bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold">{{ localize('Photos', '\u0627\u0644\u0635\u0648\u0631') }}</h2>
                <div v-if="report.photos.length === 0" class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                    {{ localize('No photos uploaded.', '\u0644\u0645 \u064a\u062a\u0645 \u0631\u0641\u0639 \u0635\u0648\u0631.') }}
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
                            {{ localize('Open file', '\u0641\u062a\u062d \u0627\u0644\u0645\u0644\u0641') }}
                        </div>
                        <div class="text-sm font-medium">{{ photo.file_name || localize('File', '\u0645\u0644\u0641') }}</div>
                        <div class="text-xs text-muted-foreground">{{ photo.photo_type || '-' }}</div>
                        <div v-if="photo.notes" class="mt-1 text-xs">{{ photo.notes }}</div>
                    </a>
                </div>
            </section>
        </main>
    </AdminLayout>
</template>
