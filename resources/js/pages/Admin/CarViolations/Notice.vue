<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed } from 'vue';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    violation: {
        id: number;
        violation_number: string;
        violation_date: string | null;
        authority: string | null;
        location: string | null;
        amount: number;
        status: string;
        type: string | null;
        car: string;
        plate_number: string;
        car_color: string | null;
        contract_number: string | null;
        contract_date: string | null;
        reservation_number: string | null;
        renter_name: string;
        renter_phone: string | null;
        renter_id: string | null;
        rental_period: string;
        branch_name: string | null;
    };
    defaults: {
        office_line: string;
        license_number: string;
        address: string;
        phone: string;
        department: string;
        country_en: string;
        country_ar: string;
    };
    actions: {
        save_defaults: string;
        download_pdf: string;
        print_pdf: string;
        back_url: string;
    };
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const form = useForm({
    police_notice: {
        office_line: {
            ar: props.defaults.office_line ?? '',
        },
        company_address: {
            ar: props.defaults.address ?? '',
        },
        company_phone: {
            ar: props.defaults.phone ?? '',
        },
    },
    pdf_header: {
        cr_number: props.defaults.license_number ?? '',
        registry_label: {
            ar: props.defaults.department ?? '',
        },
        country: {
            en: props.defaults.country_en ?? 'Sultanate of Oman',
            ar: props.defaults.country_ar ?? 'سلطنة عمان',
        },
    },
});

const errorList = computed(() =>
    Object.values(form.errors ?? {}).filter((value): value is string => typeof value === 'string' && value.length > 0),
);

function buildQuery(data: any, prefix = '', params = new URLSearchParams()) {
    if (data === null || data === undefined) return params;

    if (Array.isArray(data)) {
        data.forEach((value, index) => buildQuery(value, `${prefix}[${index}]`, params));
        return params;
    }

    if (typeof data === 'object') {
        Object.entries(data).forEach(([key, value]) => {
            const nextPrefix = prefix ? `${prefix}[${key}]` : key;
            buildQuery(value, nextPrefix, params);
        });
        return params;
    }

    if (prefix) {
        params.append(prefix, String(data));
    }

    return params;
}

function openNotice(url: string) {
    const params = buildQuery(form.data());
    const query = params.toString();
    window.open(query ? `${url}?${query}` : url, '_blank', 'noopener,noreferrer');
}

function saveDefaults() {
    form.put(props.actions.save_defaults, { preserveScroll: true });
}
</script>

<template>
    <Head :title="localize(`Violation Police - ${violation.violation_number}`, `إشعار مخالفة - ${violation.violation_number}`)" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Violation Police Notice', 'إشعار مخالفة الشرطة') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Edit only the header values used in the police notice before printing or downloading the PDF.', 'عدّل فقط قيم الترويسة المستخدمة في إشعار الشرطة قبل الطباعة أو تنزيل ملف PDF.') }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="actions.back_url">{{ localize('Back to Violations', 'العودة إلى المخالفات') }}</Link>
                    </Button>
                    <Button variant="secondary" @click="saveDefaults" :disabled="form.processing">
                        {{ localize('Save Defaults', 'حفظ القيم الافتراضية') }}
                    </Button>
                    <Button variant="outline" @click="openNotice(actions.print_pdf)">
                        {{ localize('Print PDF', 'طباعة PDF') }}
                    </Button>
                    <Button @click="openNotice(actions.download_pdf)">{{ localize('Download PDF', 'تنزيل PDF') }}</Button>
                </div>
            </div>

            <div v-if="errorList.length" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <div class="font-medium">{{ localize('Please fix the following errors:', 'يرجى إصلاح الأخطاء التالية:') }}</div>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="(message, idx) in errorList" :key="idx">{{ message }}</li>
                </ul>
            </div>

            <section class="rounded-lg border bg-card p-5 shadow-sm space-y-4">
                <div>
                    <h2 class="text-lg font-semibold">{{ localize('Notice Header Fields', 'حقول ترويسة الإشعار') }}</h2>
                    <p class="text-sm text-muted-foreground">{{ localize('Edit the notice header values that are used in the PDF.', 'عدّل قيم الترويسة المستخدمة في ملف PDF.') }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2 md:col-span-2">
                        <Label>{{ localize('1. We are a company / office', '1. نحن شركة / مكتب') }}</Label>
                        <Input v-model="form.police_notice.office_line.ar" dir="rtl" />
                    </div>

                    <div class="space-y-2">
                        <Label>{{ localize('2. License Number', '2. رقم الترخيص') }}</Label>
                        <Input v-model="form.pdf_header.cr_number" dir="ltr" />
                    </div>

                    <div class="space-y-2">
                        <Label>3. Country (English)</Label>
                        <Input v-model="form.pdf_header.country.en" dir="ltr" />
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>{{ localize('4. Address', '4. العنوان') }}</Label>
                        <Input v-model="form.police_notice.company_address.ar" dir="rtl" />
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>{{ localize('5. Phone Number', '5. رقم الهاتف') }}</Label>
                        <Input v-model="form.police_notice.company_phone.ar" dir="ltr" />
                    </div>

                    <div class="space-y-2">
                        <Label>6. Country (Arabic)</Label>
                        <Input v-model="form.pdf_header.country.ar" dir="rtl" />
                    </div>

                    <div class="space-y-2">
                        <Label>{{ localize('7. Department', '7. قسم') }}</Label>
                        <Input v-model="form.pdf_header.registry_label.ar" dir="rtl" />
                    </div>
                </div>
            </section>
        </main>
    </AdminLayout>
</template>
