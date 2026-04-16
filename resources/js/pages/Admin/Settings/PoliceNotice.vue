<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useTrans } from '@/composables/useTrans';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed } from 'vue';

type LocalizedText = { en: string | null; ar: string | null };

const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
    };
    settings: {
        pdf_header: {
            company_name: LocalizedText;
            cr_number: string | null;
            po_box: string | null;
            pc: string | null;
            country: LocalizedText;
            gsm_1: string | null;
            gsm_2: string | null;
            gsm_3: string | null;
            registry_label: LocalizedText;
        };
        police_notice: {
            company_name: LocalizedText;
            registry_label: LocalizedText;
            subject: LocalizedText;
            greeting: LocalizedText;
            intro: LocalizedText;
            office_line: LocalizedText;
            company_address: LocalizedText;
            company_phone: LocalizedText;
            vehicle_section_title: LocalizedText;
            renter_section_title: LocalizedText;
            closing_1: LocalizedText;
            closing_2: LocalizedText;
            attachments_title: LocalizedText;
            attachments: LocalizedText;
            signature_name_label: LocalizedText;
            signature_title_label: LocalizedText;
            signature_date_label: LocalizedText;
            footer_note: LocalizedText;
        };
    };
    actions: {
        update: string;
    };
}>();

const { locale } = useTrans();
const page = usePage<any>();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const form = useForm({
    pdf_header: {
        company_name: {
            en: props.settings.pdf_header?.company_name?.en ?? '',
            ar: props.settings.pdf_header?.company_name?.ar ?? '',
        },
        cr_number: props.settings.pdf_header?.cr_number ?? '',
        po_box: props.settings.pdf_header?.po_box ?? '',
        pc: props.settings.pdf_header?.pc ?? '',
        country: {
            en: props.settings.pdf_header?.country?.en ?? '',
            ar: props.settings.pdf_header?.country?.ar ?? '',
        },
        gsm_1: props.settings.pdf_header?.gsm_1 ?? '',
        gsm_2: props.settings.pdf_header?.gsm_2 ?? '',
        gsm_3: props.settings.pdf_header?.gsm_3 ?? '',
        registry_label: {
            en: props.settings.pdf_header?.registry_label?.en ?? '',
            ar: props.settings.pdf_header?.registry_label?.ar ?? '',
        },
    },
    police_notice: {
        company_name: {
            en: props.settings.police_notice?.company_name?.en ?? '',
            ar: props.settings.police_notice?.company_name?.ar ?? '',
        },
        registry_label: {
            en: props.settings.police_notice?.registry_label?.en ?? '',
            ar: props.settings.police_notice?.registry_label?.ar ?? '',
        },
        subject: {
            en: props.settings.police_notice?.subject?.en ?? '',
            ar: props.settings.police_notice?.subject?.ar ?? '',
        },
        greeting: {
            en: props.settings.police_notice?.greeting?.en ?? '',
            ar: props.settings.police_notice?.greeting?.ar ?? '',
        },
        intro: {
            en: props.settings.police_notice?.intro?.en ?? '',
            ar: props.settings.police_notice?.intro?.ar ?? '',
        },
        office_line: {
            en: props.settings.police_notice?.office_line?.en ?? '',
            ar: props.settings.police_notice?.office_line?.ar ?? '',
        },
        company_address: {
            en: props.settings.police_notice?.company_address?.en ?? '',
            ar: props.settings.police_notice?.company_address?.ar ?? '',
        },
        company_phone: {
            en: props.settings.police_notice?.company_phone?.en ?? '',
            ar: props.settings.police_notice?.company_phone?.ar ?? '',
        },
        vehicle_section_title: {
            en: props.settings.police_notice?.vehicle_section_title?.en ?? '',
            ar: props.settings.police_notice?.vehicle_section_title?.ar ?? '',
        },
        renter_section_title: {
            en: props.settings.police_notice?.renter_section_title?.en ?? '',
            ar: props.settings.police_notice?.renter_section_title?.ar ?? '',
        },
        closing_1: {
            en: props.settings.police_notice?.closing_1?.en ?? '',
            ar: props.settings.police_notice?.closing_1?.ar ?? '',
        },
        closing_2: {
            en: props.settings.police_notice?.closing_2?.en ?? '',
            ar: props.settings.police_notice?.closing_2?.ar ?? '',
        },
        attachments_title: {
            en: props.settings.police_notice?.attachments_title?.en ?? '',
            ar: props.settings.police_notice?.attachments_title?.ar ?? '',
        },
        attachments: {
            en: props.settings.police_notice?.attachments?.en ?? '',
            ar: props.settings.police_notice?.attachments?.ar ?? '',
        },
        signature_name_label: {
            en: props.settings.police_notice?.signature_name_label?.en ?? '',
            ar: props.settings.police_notice?.signature_name_label?.ar ?? '',
        },
        signature_title_label: {
            en: props.settings.police_notice?.signature_title_label?.en ?? '',
            ar: props.settings.police_notice?.signature_title_label?.ar ?? '',
        },
        signature_date_label: {
            en: props.settings.police_notice?.signature_date_label?.en ?? '',
            ar: props.settings.police_notice?.signature_date_label?.ar ?? '',
        },
        footer_note: {
            en: props.settings.police_notice?.footer_note?.en ?? '',
            ar: props.settings.police_notice?.footer_note?.ar ?? '',
        },
    },
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const formErrorList = computed(() => Object.values(form.errors ?? {}).filter((value): value is string => typeof value === 'string' && value.length > 0));

function submit() {
    form.put(props.actions.update, { preserveScroll: true });
}
</script>

<template>
    <Head :title="localize('Police Notice Profile', 'ملف إشعار الشرطة')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Police Notice Profile', 'ملف إشعار الشرطة') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Editable content used in the police notice PDF.', 'النصوص القابلة للتعديل والمستخدمة في كتاب الشرطة PDF.') }}
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جاري الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                </Button>
            </div>

            <div v-if="flashSuccess" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ flashError }}
            </div>
            <div v-if="formErrorList.length" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <div class="font-medium">{{ localize('Please fix the following errors:', 'يرجى تصحيح الأخطاء التالية:') }}</div>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="(message, idx) in formErrorList" :key="idx">{{ message }}</li>
                </ul>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('Header Content', 'محتوى الترويسة') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ localize('Company header that appears at the top of the notice.', 'بيانات الشركة التي تظهر في أعلى الإشعار.') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="pdf_header_company_name_en">{{ localize('Company Name (EN)', 'اسم الشركة (EN)') }}</Label>
                            <Input id="pdf_header_company_name_en" v-model="form.pdf_header.company_name.en" />
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_company_name_ar">{{ localize('Company Name (AR)', 'اسم الشركة (AR)') }}</Label>
                            <Input id="pdf_header_company_name_ar" v-model="form.pdf_header.company_name.ar" dir="rtl" />
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_registry_label_ar">{{ localize('Department Label (AR)', 'قسم (AR)') }}</Label>
                            <Input id="pdf_header_registry_label_ar" v-model="form.pdf_header.registry_label.ar" dir="rtl" />
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_registry_label_en">{{ localize('Department Label (EN)', 'قسم (EN)') }}</Label>
                            <Input id="pdf_header_registry_label_en" v-model="form.pdf_header.registry_label.en" />
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_cr_number">{{ localize('C.R Number', 'رقم السجل التجاري') }}</Label>
                            <Input id="pdf_header_cr_number" v-model="form.pdf_header.cr_number" />
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_po_box">{{ localize('P.O Box', 'صندوق البريد') }}</Label>
                            <Input id="pdf_header_po_box" v-model="form.pdf_header.po_box" />
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_pc">{{ localize('P.C', 'الرمز البريدي') }}</Label>
                            <Input id="pdf_header_pc" v-model="form.pdf_header.pc" />
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_country_en">{{ localize('Country (EN)', 'الدولة (EN)') }}</Label>
                            <Input id="pdf_header_country_en" v-model="form.pdf_header.country.en" />
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_country_ar">{{ localize('Country (AR)', 'الدولة (AR)') }}</Label>
                            <Input id="pdf_header_country_ar" v-model="form.pdf_header.country.ar" dir="rtl" />
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_gsm_1">{{ localize('GSM 1', 'رقم 1') }}</Label>
                            <Input id="pdf_header_gsm_1" v-model="form.pdf_header.gsm_1" />
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_gsm_2">{{ localize('GSM 2', 'رقم 2') }}</Label>
                            <Input id="pdf_header_gsm_2" v-model="form.pdf_header.gsm_2" />
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_gsm_3">{{ localize('GSM 3', 'رقم 3') }}</Label>
                            <Input id="pdf_header_gsm_3" v-model="form.pdf_header.gsm_3" />
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('Notice Content', 'محتوى الإشعار') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ localize('Text that appears inside the police notice letter.', 'النصوص الظاهرة داخل كتاب الشرطة.') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="police_notice_company_name_en">{{ localize('Company Name in Notice (EN)', 'اسم الشركة في الإشعار (EN)') }}</Label>
                            <Input id="police_notice_company_name_en" v-model="form.police_notice.company_name.en" />
                        </div>
                        <div class="space-y-2">
                            <Label for="police_notice_company_name_ar">{{ localize('Company Name in Notice (AR)', 'اسم الشركة في الإشعار (AR)') }}</Label>
                            <Input id="police_notice_company_name_ar" v-model="form.police_notice.company_name.ar" dir="rtl" />
                        </div>

                        <div class="space-y-2">
                            <Label for="police_notice_registry_label_en">{{ localize('Registry Label (EN)', 'اسم القسم (EN)') }}</Label>
                            <Input id="police_notice_registry_label_en" v-model="form.police_notice.registry_label.en" />
                        </div>
                        <div class="space-y-2">
                            <Label for="police_notice_registry_label_ar">{{ localize('Registry Label (AR)', 'اسم القسم (AR)') }}</Label>
                            <Input id="police_notice_registry_label_ar" v-model="form.police_notice.registry_label.ar" dir="rtl" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="police_notice_subject_ar">{{ localize('Subject (AR)', 'الموضوع (AR)') }}</Label>
                            <textarea id="police_notice_subject_ar" v-model="form.police_notice.subject.ar" rows="2" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <Label for="police_notice_subject_en">{{ localize('Subject (EN)', 'الموضوع (EN)') }}</Label>
                            <textarea id="police_notice_subject_en" v-model="form.police_notice.subject.en" rows="2" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="police_notice_greeting_ar">{{ localize('Greeting (AR)', 'التحية (AR)') }}</Label>
                            <Input id="police_notice_greeting_ar" v-model="form.police_notice.greeting.ar" dir="rtl" />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <Label for="police_notice_intro_ar">{{ localize('Intro (AR)', 'التمهيد (AR)') }}</Label>
                            <textarea id="police_notice_intro_ar" v-model="form.police_notice.intro.ar" rows="4" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <Label for="police_notice_intro_en">{{ localize('Intro (EN)', 'التمهيد (EN)') }}</Label>
                            <textarea id="police_notice_intro_en" v-model="form.police_notice.intro.en" rows="4" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        </div>

                        <div class="space-y-2">
                            <Label for="police_notice_office_line_ar">{{ localize('Office Line (AR)', 'اسم الشركة / المكتب (AR)') }}</Label>
                            <Input id="police_notice_office_line_ar" v-model="form.police_notice.office_line.ar" dir="rtl" />
                        </div>
                        <div class="space-y-2">
                            <Label for="police_notice_office_line_en">{{ localize('Office Line (EN)', 'Office Line (EN)') }}</Label>
                            <Input id="police_notice_office_line_en" v-model="form.police_notice.office_line.en" />
                        </div>

                        <div class="space-y-2">
                            <Label for="police_notice_company_address_ar">{{ localize('Company Address (AR)', 'عنوان الشركة (AR)') }}</Label>
                            <Input id="police_notice_company_address_ar" v-model="form.police_notice.company_address.ar" dir="rtl" />
                        </div>
                        <div class="space-y-2">
                            <Label for="police_notice_company_address_en">{{ localize('Company Address (EN)', 'Company Address (EN)') }}</Label>
                            <Input id="police_notice_company_address_en" v-model="form.police_notice.company_address.en" />
                        </div>

                        <div class="space-y-2">
                            <Label for="police_notice_company_phone_ar">{{ localize('Company Phone (AR)', 'هاتف الشركة (AR)') }}</Label>
                            <Input id="police_notice_company_phone_ar" v-model="form.police_notice.company_phone.ar" />
                        </div>
                        <div class="space-y-2">
                            <Label for="police_notice_company_phone_en">{{ localize('Company Phone (EN)', 'Company Phone (EN)') }}</Label>
                            <Input id="police_notice_company_phone_en" v-model="form.police_notice.company_phone.en" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="police_notice_vehicle_section_title_ar">{{ localize('Vehicle Section Title (AR)', 'عنوان بيانات المركبة (AR)') }}</Label>
                            <Input id="police_notice_vehicle_section_title_ar" v-model="form.police_notice.vehicle_section_title.ar" dir="rtl" />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <Label for="police_notice_vehicle_section_title_en">{{ localize('Vehicle Section Title (EN)', 'Vehicle Section Title (EN)') }}</Label>
                            <Input id="police_notice_vehicle_section_title_en" v-model="form.police_notice.vehicle_section_title.en" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="police_notice_renter_section_title_ar">{{ localize('Renter Section Title (AR)', 'عنوان بيانات المستأجر (AR)') }}</Label>
                            <Input id="police_notice_renter_section_title_ar" v-model="form.police_notice.renter_section_title.ar" dir="rtl" />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <Label for="police_notice_renter_section_title_en">{{ localize('Renter Section Title (EN)', 'Renter Section Title (EN)') }}</Label>
                            <Input id="police_notice_renter_section_title_en" v-model="form.police_notice.renter_section_title.en" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="police_notice_closing_1_ar">{{ localize('Closing Paragraph 1 (AR)', 'الفقرة الختامية 1 (AR)') }}</Label>
                            <textarea id="police_notice_closing_1_ar" v-model="form.police_notice.closing_1.ar" rows="4" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <Label for="police_notice_closing_1_en">{{ localize('Closing Paragraph 1 (EN)', 'Closing Paragraph 1 (EN)') }}</Label>
                            <textarea id="police_notice_closing_1_en" v-model="form.police_notice.closing_1.en" rows="4" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="police_notice_closing_2_ar">{{ localize('Closing Paragraph 2 (AR)', 'الفقرة الختامية 2 (AR)') }}</Label>
                            <textarea id="police_notice_closing_2_ar" v-model="form.police_notice.closing_2.ar" rows="4" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <Label for="police_notice_closing_2_en">{{ localize('Closing Paragraph 2 (EN)', 'Closing Paragraph 2 (EN)') }}</Label>
                            <textarea id="police_notice_closing_2_en" v-model="form.police_notice.closing_2.en" rows="4" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        </div>

                        <div class="space-y-2">
                            <Label for="police_notice_attachments_title_ar">{{ localize('Attachments Title (AR)', 'عنوان المرفقات (AR)') }}</Label>
                            <Input id="police_notice_attachments_title_ar" v-model="form.police_notice.attachments_title.ar" dir="rtl" />
                        </div>
                        <div class="space-y-2">
                            <Label for="police_notice_attachments_title_en">{{ localize('Attachments Title (EN)', 'Attachments Title (EN)') }}</Label>
                            <Input id="police_notice_attachments_title_en" v-model="form.police_notice.attachments_title.en" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="police_notice_attachments_ar">{{ localize('Attachments (AR)', 'المرفقات (AR)') }}</Label>
                            <textarea id="police_notice_attachments_ar" v-model="form.police_notice.attachments.ar" rows="5" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p class="text-xs text-muted-foreground">{{ localize('Write each attachment on a new line.', 'اكتب كل مرفق في سطر جديد.') }}</p>
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <Label for="police_notice_attachments_en">{{ localize('Attachments (EN)', 'Attachments (EN)') }}</Label>
                            <textarea id="police_notice_attachments_en" v-model="form.police_notice.attachments.en" rows="5" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p class="text-xs text-muted-foreground">{{ localize('Write each attachment on a new line.', 'اكتب كل مرفق في سطر جديد.') }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="police_notice_signature_name_label_ar">{{ localize('Signature Name Label (AR)', 'اسم المفوض (AR)') }}</Label>
                            <Input id="police_notice_signature_name_label_ar" v-model="form.police_notice.signature_name_label.ar" dir="rtl" />
                        </div>
                        <div class="space-y-2">
                            <Label for="police_notice_signature_name_label_en">{{ localize('Signature Name Label (EN)', 'Signature Name Label (EN)') }}</Label>
                            <Input id="police_notice_signature_name_label_en" v-model="form.police_notice.signature_name_label.en" />
                        </div>

                        <div class="space-y-2">
                            <Label for="police_notice_signature_title_label_ar">{{ localize('Signature Title Label (AR)', 'الصفة (AR)') }}</Label>
                            <Input id="police_notice_signature_title_label_ar" v-model="form.police_notice.signature_title_label.ar" dir="rtl" />
                        </div>
                        <div class="space-y-2">
                            <Label for="police_notice_signature_title_label_en">{{ localize('Signature Title Label (EN)', 'Signature Title Label (EN)') }}</Label>
                            <Input id="police_notice_signature_title_label_en" v-model="form.police_notice.signature_title_label.en" />
                        </div>

                        <div class="space-y-2">
                            <Label for="police_notice_signature_date_label_ar">{{ localize('Signature Date Label (AR)', 'التاريخ (AR)') }}</Label>
                            <Input id="police_notice_signature_date_label_ar" v-model="form.police_notice.signature_date_label.ar" dir="rtl" />
                        </div>
                        <div class="space-y-2">
                            <Label for="police_notice_signature_date_label_en">{{ localize('Signature Date Label (EN)', 'Signature Date Label (EN)') }}</Label>
                            <Input id="police_notice_signature_date_label_en" v-model="form.police_notice.signature_date_label.en" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="police_notice_footer_note_ar">{{ localize('Footer Note (AR)', 'ملاحظة أسفل الصفحة (AR)') }}</Label>
                            <textarea id="police_notice_footer_note_ar" v-model="form.police_notice.footer_note.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <Label for="police_notice_footer_note_en">{{ localize('Footer Note (EN)', 'Footer Note (EN)') }}</Label>
                            <textarea id="police_notice_footer_note_en" v-model="form.police_notice.footer_note.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        </div>
                    </div>
                </section>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? localize('Saving...', 'جاري الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                    </Button>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
