<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useTrans } from '@/composables/useTrans';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { computed, ref, watch } from 'vue';

type LocalizedText = {
    en: string | null;
    ar: string | null;
};

type ContractPdfTextSet = {
    mileage_notice: LocalizedText;
    rental_period_notice: LocalizedText;
    smoking_notice: LocalizedText;
    unclean_notice: LocalizedText;
    delay_notice: LocalizedText;
    period_change_notice: LocalizedText;
    accident_notice: LocalizedText;
    acknowledgement_title: LocalizedText;
    acknowledgement_body: LocalizedText;
    mobile_signature_text: string;
    important_notice: LocalizedText;
    closing_notice: LocalizedText;
};

type ContractPdfDefaults = Record<Exclude<keyof ContractPdfTextSet, 'mobile_signature_text'>, { en: string; ar: string }> & {
    mobile_signature_text: string;
};

const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
    };
    settings: {
        contract_pdf?: Partial<ContractPdfTextSet> & {
            incharge_signature_image?: string | null;
        };
    };
    contractPdfDefaults: ContractPdfDefaults;
    contractSignatureFiles: Array<{ id: number; url: string }>;
    previewUrl: string | null;
    actions: {
        update: string;
    };
}>();

const { t, locale } = useTrans();
const page = usePage<any>();
const translationRoot = 'dashboard.admin.settings.contract_pdf';
const translationKeyFor = (text: string) =>
    text
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 80);
const localize = (en: string, ar: string) => {
    const fullKey = `${translationRoot}.${translationKeyFor(en)}`;
    const translated = t(fullKey);

    if (translated !== fullKey) {
        return translated;
    }

    return locale.value === 'ar' ? ar : en;
};

const resolvedText = (key: keyof ContractPdfTextSet, lang: 'en' | 'ar'): string => {
    if (key === 'mobile_signature_text') {
        return String(props.settings.contract_pdf?.mobile_signature_text ?? props.contractPdfDefaults.mobile_signature_text ?? '').trim();
    }

    const current = props.settings.contract_pdf?.[key]?.[lang];
    const fallback = props.contractPdfDefaults[key]?.[lang] ?? '';

    return String(current ?? fallback).trim();
};

const form = useForm({
    contract_pdf: {
        mileage_notice: {
            en: resolvedText('mileage_notice', 'en'),
            ar: resolvedText('mileage_notice', 'ar'),
        },
        rental_period_notice: {
            en: resolvedText('rental_period_notice', 'en'),
            ar: resolvedText('rental_period_notice', 'ar'),
        },
        smoking_notice: {
            en: resolvedText('smoking_notice', 'en'),
            ar: resolvedText('smoking_notice', 'ar'),
        },
        unclean_notice: {
            en: resolvedText('unclean_notice', 'en'),
            ar: resolvedText('unclean_notice', 'ar'),
        },
        delay_notice: {
            en: resolvedText('delay_notice', 'en'),
            ar: resolvedText('delay_notice', 'ar'),
        },
        period_change_notice: {
            en: resolvedText('period_change_notice', 'en'),
            ar: resolvedText('period_change_notice', 'ar'),
        },
        accident_notice: {
            en: resolvedText('accident_notice', 'en'),
            ar: resolvedText('accident_notice', 'ar'),
        },
        acknowledgement_title: {
            en: resolvedText('acknowledgement_title', 'en'),
            ar: resolvedText('acknowledgement_title', 'ar'),
        },
        acknowledgement_body: {
            en: resolvedText('acknowledgement_body', 'en'),
            ar: resolvedText('acknowledgement_body', 'ar'),
        },
        mobile_signature_text: resolvedText('mobile_signature_text', 'en'),
        important_notice: {
            en: resolvedText('important_notice', 'en'),
            ar: resolvedText('important_notice', 'ar'),
        },
        closing_notice: {
            en: resolvedText('closing_notice', 'en'),
            ar: resolvedText('closing_notice', 'ar'),
        },
    },
    contract_incharge_signature_temp_folders: [] as string[],
    contract_incharge_signature_removed_files: [] as number[],
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const formErrorList = computed(() => Object.values(form.errors ?? {}).filter((value): value is string => typeof value === 'string' && value.length > 0));
const previewNonce = ref(0);
const acceptedSignatureTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
const inchargeSignatureUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const inchargeSignatureTempFolders = ref<string[]>([]);
const inchargeSignatureRemovedFileIds = ref<number[]>([]);

watch(
    () => form.contract_pdf,
    () => {
        previewNonce.value += 1;
    },
    { deep: true }
);

watch(
    inchargeSignatureTempFolders,
    (value) => {
        form.contract_incharge_signature_temp_folders = [...value];
    },
    { deep: true }
);

const previewSrc = computed(() => {
    if (!props.previewUrl) {
        return '';
    }

    const previewState = encodeURIComponent(JSON.stringify({
        contract_pdf: form.contract_pdf,
    }));
    const separator = props.previewUrl.includes('?') ? '&' : '?';

    return `${props.previewUrl}${separator}preview=1&preview_state=${previewState}&preview_nonce=${previewNonce.value}`;
});
function submit() {
    form.put(props.actions.update, {
        preserveScroll: true,
        onSuccess: resetUploadState,
    });
}

function handleInchargeSignatureRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        inchargeSignatureRemovedFileIds.value.push(data.fileId);
        form.contract_incharge_signature_removed_files = [...new Set(inchargeSignatureRemovedFileIds.value)];
    }
}

function resetUploadState() {
    inchargeSignatureTempFolders.value = [];
    inchargeSignatureRemovedFileIds.value = [];
    form.contract_incharge_signature_temp_folders = [];
    form.contract_incharge_signature_removed_files = [];
    inchargeSignatureUploadRef.value?.resetFiles();
}
</script>

<template>
    <Head :title="localize('Contract PDF Settings', 'إعدادات PDF العقد')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-semibold">{{ localize('Contract PDF Settings', 'إعدادات PDF العقد') }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ localize('Edit the contract text blocks here and watch the preview update instantly before saving.', 'عدّل نصوص العقد هنا وشاهد المعاينة تتحدث مباشرة قبل الحفظ.') }}
                </p>
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

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(360px,0.85fr)]">
                <form class="space-y-6" @submit.prevent="submit">
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Mileage and period notes', 'ملاحظات الكيلومترات ومدة الإيجار') }}</CardTitle>
                            <CardDescription>{{ localize('These lines appear above the contract rules and rental period area.', 'هذه السطور تظهر فوق بنود العقد ومنطقة مدة الإيجار.') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-3">
                                <div class="space-y-2">
                                    <Label>{{ localize('Excess mileage notice (EN)', 'نص زيادة الكيلومترات (EN)') }}</Label>
                                    <Textarea v-model="form.contract_pdf.mileage_notice.en" rows="3" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Excess mileage notice (AR)', 'نص زيادة الكيلومترات (AR)') }}</Label>
                                    <Textarea v-model="form.contract_pdf.mileage_notice.ar" rows="3" dir="rtl" />
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="space-y-2">
                                    <Label>{{ localize('Rental period notice (EN)', 'نص مدة الإيجار (EN)') }}</Label>
                                    <Textarea v-model="form.contract_pdf.rental_period_notice.en" rows="3" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Rental period notice (AR)', 'نص مدة الإيجار (AR)') }}</Label>
                                    <Textarea v-model="form.contract_pdf.rental_period_notice.ar" rows="3" dir="rtl" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Rules and penalties', 'البنود والغرامات') }}</CardTitle>
                            <CardDescription>{{ localize('These rules are shown in the terms list inside the contract.', 'هذه البنود تظهر ضمن قائمة الشروط داخل العقد.') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-5">
                            <div class="grid gap-4">
                                <div class="space-y-2">
                                    <Label>{{ localize('Smoking rule (EN)', 'غرامة التدخين (EN)') }}</Label>
                                    <Textarea v-model="form.contract_pdf.smoking_notice.en" rows="2" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Smoking rule (AR)', 'غرامة التدخين (AR)') }}</Label>
                                    <Textarea v-model="form.contract_pdf.smoking_notice.ar" rows="2" dir="rtl" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Unclean return rule (EN)', 'غرامة إعادة المركبة غير النظيفة (EN)') }}</Label>
                                    <Textarea v-model="form.contract_pdf.unclean_notice.en" rows="2" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Unclean return rule (AR)', 'غرامة إعادة المركبة غير النظيفة (AR)') }}</Label>
                                    <Textarea v-model="form.contract_pdf.unclean_notice.ar" rows="2" dir="rtl" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Delay rule (EN)', 'غرامة التأخير (EN)') }}</Label>
                                    <Textarea v-model="form.contract_pdf.delay_notice.en" rows="3" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Delay rule (AR)', 'غرامة التأخير (AR)') }}</Label>
                                    <Textarea v-model="form.contract_pdf.delay_notice.ar" rows="3" dir="rtl" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Period conversion rule (EN)', 'تحويل العقد إلى يومي (EN)') }}</Label>
                                    <Textarea v-model="form.contract_pdf.period_change_notice.en" rows="3" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Period conversion rule (AR)', 'تحويل العقد إلى يومي (AR)') }}</Label>
                                    <Textarea v-model="form.contract_pdf.period_change_notice.ar" rows="3" dir="rtl" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Accident rule (EN)', 'غرامة الحادث (EN)') }}</Label>
                                    <Textarea v-model="form.contract_pdf.accident_notice.en" rows="3" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Accident rule (AR)', 'غرامة الحادث (AR)') }}</Label>
                                    <Textarea v-model="form.contract_pdf.accident_notice.ar" rows="3" dir="rtl" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Acknowledgement text', 'نص الإقرار') }}</CardTitle>
                            <CardDescription>{{ localize('Shown in the signature area before the final notice.', 'يظهر في منطقة التوقيع قبل التنبيه النهائي.') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label>{{ localize('Acknowledgement title (EN)', 'عنوان الإقرار (EN)') }}</Label>
                                <Textarea v-model="form.contract_pdf.acknowledgement_title.en" rows="2" />
                            </div>
                            <div class="space-y-2">
                                <Label>{{ localize('Acknowledgement title (AR)', 'عنوان الإقرار (AR)') }}</Label>
                                <Textarea v-model="form.contract_pdf.acknowledgement_title.ar" rows="2" dir="rtl" />
                            </div>
                            <div class="space-y-2">
                                <Label>{{ localize('Acknowledgement body (EN)', 'نص الإقرار (EN)') }}</Label>
                                <Textarea v-model="form.contract_pdf.acknowledgement_body.en" rows="4" />
                            </div>
                            <div class="space-y-2">
                                <Label>{{ localize('Acknowledgement body (AR)', 'نص الإقرار (AR)') }}</Label>
                                <Textarea v-model="form.contract_pdf.acknowledgement_body.ar" rows="4" dir="rtl" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Mobile signature note', 'ملاحظة التوقيع عبر الجوال') }}</CardTitle>
                            <CardDescription>{{ localize('This text is shown on the mobile signature step before confirmation.', 'هذا النص يظهر في صفحة التوقيع على الجوال قبل التأكيد.') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-2">
                            <Label>{{ localize('Mobile note', 'ملاحظة الجوال') }}</Label>
                            <Textarea v-model="form.contract_pdf.mobile_signature_text" rows="4" />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Company signature image', 'صورة توقيع الشركة') }}</CardTitle>
                            <CardDescription>
                                {{ localize('Upload the incharge signature that appears in the contract PDF signature area.', 'ارفع توقيع المسؤول الذي يظهر في منطقة التوقيع داخل ملف العقد.') }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="space-y-2">
                                <Label>{{ localize('Incharge signature', 'توقيع المسؤول') }}</Label>
                                <FileUpload
                                    ref="inchargeSignatureUploadRef"
                                    v-model="inchargeSignatureTempFolders"
                                    :initial-files="props.contractSignatureFiles"
                                    :allowed-file-types="acceptedSignatureTypes"
                                    :max-file-size="1024 * 1024 * 5"
                                    collection="contract_incharge_signature"
                                    @file-removed="handleInchargeSignatureRemoved"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ localize('Use a transparent PNG/WebP for the cleanest result.', 'يفضل استخدام صورة PNG/WebP بخلفية شفافة للحصول على أفضل نتيجة.') }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Notice and footer', 'التنبيه والتذييل') }}</CardTitle>
                            <CardDescription>{{ localize('Final callout block and the closing contract sentence.', 'كتلة التنبيه النهائية وجملة إغلاق العقد.') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label>{{ localize('Important notice (EN)', 'التنبيه الهام (EN)') }}</Label>
                                <Textarea v-model="form.contract_pdf.important_notice.en" rows="3" />
                            </div>
                            <div class="space-y-2">
                                <Label>{{ localize('Important notice (AR)', 'التنبيه الهام (AR)') }}</Label>
                                <Textarea v-model="form.contract_pdf.important_notice.ar" rows="3" dir="rtl" />
                            </div>
                            <div class="space-y-2">
                                <Label>{{ localize('Closing notice (EN)', 'نص إغلاق العقد (EN)') }}</Label>
                                <Textarea v-model="form.contract_pdf.closing_notice.en" rows="3" />
                            </div>
                            <div class="space-y-2">
                                <Label>{{ localize('Closing notice (AR)', 'نص إغلاق العقد (AR)') }}</Label>
                                <Textarea v-model="form.contract_pdf.closing_notice.ar" rows="3" dir="rtl" />
                            </div>
                        </CardContent>
                    </Card>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? localize('Saving...', 'جاري الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                        </Button>
                    </div>
                </form>

                <div class="space-y-4">
                    <Card class="sticky top-6 overflow-hidden">
                        <CardHeader class="border-b">
                            <CardTitle>{{ localize('Live Preview', 'معاينة مباشرة') }}</CardTitle>
                            <CardDescription>
                                {{ localize('This preview mirrors the classic contract PDF text blocks and updates as you type.', 'هذه المعاينة تعكس كتل النص في عقد PDF الكلاسيكي وتتحدث أثناء الكتابة.') }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="bg-[#f4f7fb] p-3">
                            <div v-if="previewSrc" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                                <iframe
                                    :src="previewSrc"
                                    class="h-[calc(100vh-14rem)] min-h-[920px] w-full bg-white"
                                    title="Contract live preview"
                                />
                            </div>
                            <div
                                v-else
                                class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-muted-foreground"
                            >
                                {{ localize('No contract is available for preview yet. Create one contract first.', 'لا يوجد عقد متاح للمعاينة بعد. أنشئ عقدًا أولًا.') }}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </main>
    </AdminLayout>
</template>

