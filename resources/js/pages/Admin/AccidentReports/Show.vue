<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    report: {
        id: number;
        accident_number: string;
        status: string;
        status_label: string;
        status_color: string;
        contract_number: string | null;
        reservation_number: string | null;
        renter_name: string | null;
        renter_phone: string | null;
        renter_id_number: string | null;
        car: string;
        branch: string | null;
        reported_by: string | null;
        accident_at: string | null;
        location: string | null;
        latitude: string | null;
        longitude: string | null;
        description: string;
        police_report_number: string | null;
        has_injuries: boolean;
        third_party_involved: boolean;
        third_party_details: Record<string, string> | null;
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
</script>

<template>
    <Head :title="report.accident_number" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ report.accident_number }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Accident report details', 'تفاصيل بلاغ الحادث') }}
                    </p>
                </div>
                <Link :href="indexUrl">
                    <Button variant="outline">{{ localize('Back', 'رجوع') }}</Button>
                </Link>
            </div>

            <section class="grid grid-cols-1 gap-4 rounded-lg border bg-white p-6 shadow-sm md:grid-cols-4">
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Status', 'الحالة') }}</div>
                    <span class="mt-1 inline-flex rounded-full px-2 py-1 text-xs font-medium text-white" :style="{ backgroundColor: report.status_color }">
                        {{ report.status_label }}
                    </span>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Contract', 'العقد') }}</div>
                    <div class="font-medium">{{ report.contract_number || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Reservation', 'الحجز') }}</div>
                    <div class="font-medium">{{ report.reservation_number || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Car', 'السيارة') }}</div>
                    <div class="font-medium">{{ report.car }}</div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 rounded-lg border bg-white p-6 shadow-sm md:grid-cols-3">
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Client', 'العميل') }}</div>
                    <div class="font-medium">{{ report.renter_name || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Phone', 'الهاتف') }}</div>
                    <div>{{ report.renter_phone || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('ID number', 'رقم الهوية') }}</div>
                    <div>{{ report.renter_id_number || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Accident time', 'وقت الحادث') }}</div>
                    <div>{{ report.accident_at || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Location', 'الموقع') }}</div>
                    <div>{{ report.location || '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Police report number', 'رقم تقرير الشرطة') }}</div>
                    <div>{{ report.police_report_number || '-' }}</div>
                </div>
            </section>

            <section class="rounded-lg border bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-lg font-semibold">{{ localize('Description', 'وصف الحادث') }}</h2>
                <p class="whitespace-pre-wrap text-sm">{{ report.description }}</p>
                <div v-if="report.notes" class="mt-4 rounded-md bg-muted/40 p-3 text-sm">
                    <strong>{{ localize('Notes:', 'ملاحظات:') }}</strong>
                    {{ report.notes }}
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 rounded-lg border bg-white p-6 shadow-sm md:grid-cols-2">
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Has injuries', 'يوجد إصابات') }}</div>
                    <div class="font-medium">{{ report.has_injuries ? localize('Yes', 'نعم') : localize('No', 'لا') }}</div>
                </div>
                <div>
                    <div class="text-sm text-muted-foreground">{{ localize('Third party involved', 'يوجد طرف ثالث') }}</div>
                    <div class="font-medium">{{ report.third_party_involved ? localize('Yes', 'نعم') : localize('No', 'لا') }}</div>
                </div>
                <div v-if="report.third_party_details" class="md:col-span-2">
                    <div class="text-sm text-muted-foreground">{{ localize('Third party details', 'بيانات الطرف الثالث') }}</div>
                    <pre class="mt-2 rounded-md bg-muted/40 p-3 text-sm">{{ report.third_party_details }}</pre>
                </div>
            </section>

            <section class="rounded-lg border bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold">{{ localize('Photos', 'الصور') }}</h2>
                <div v-if="report.photos.length === 0" class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                    {{ localize('No photos uploaded.', 'لم يتم رفع صور.') }}
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
                            {{ localize('Open file', 'فتح الملف') }}
                        </div>
                        <div class="text-sm font-medium">{{ photo.file_name || localize('File', 'ملف') }}</div>
                        <div class="text-xs text-muted-foreground">{{ photo.photo_type || '-' }}</div>
                        <div v-if="photo.notes" class="mt-1 text-xs">{{ photo.notes }}</div>
                    </a>
                </div>
            </section>
        </main>
    </AdminLayout>
</template>
