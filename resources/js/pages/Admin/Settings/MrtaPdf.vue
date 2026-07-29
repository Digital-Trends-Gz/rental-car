<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useTrans } from '@/composables/useTrans';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { computed, ref, watch } from 'vue';

type MrtaPdfSettings = {
    primary_color: string;
    liva_logo_text: string;
    liva_logo_ar: string;
    liva_contact_email: string;
    liva_contact_website: string;
    insurance_section_title_en: string;
    insurance_section_title_ar: string;
    footer_ar: string;
    footer_en: string;
};

const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
    };
    settings: {
        mrta_pdf?: Partial<MrtaPdfSettings>;
    };
    mrtaPdfDefaults: MrtaPdfSettings;
    mrtaLogoFiles: {
        oman: Array<{ id: number; url: string }>;
        rop: Array<{ id: number; url: string }>;
        liva: Array<{ id: number; url: string }>;
    };
    previewUrl: string | null;
    actions: {
        update: string;
    };
}>();

const { t, locale } = useTrans();
const page = usePage<any>();
const translationRoot = 'dashboard.admin.settings.mrta_pdf';
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
const settingValue = (key: keyof MrtaPdfSettings): string => String(props.settings.mrta_pdf?.[key] ?? props.mrtaPdfDefaults[key] ?? '');

const form = useForm({
    mrta_pdf: {
        primary_color: settingValue('primary_color'),
        liva_logo_text: settingValue('liva_logo_text'),
        liva_logo_ar: settingValue('liva_logo_ar'),
        liva_contact_email: settingValue('liva_contact_email'),
        liva_contact_website: settingValue('liva_contact_website'),
        insurance_section_title_en: settingValue('insurance_section_title_en'),
        insurance_section_title_ar: settingValue('insurance_section_title_ar'),
        footer_ar: settingValue('footer_ar'),
        footer_en: settingValue('footer_en'),
    },
    mrta_oman_logo_temp_folders: [] as string[],
    mrta_oman_logo_removed_files: [] as number[],
    mrta_rop_logo_temp_folders: [] as string[],
    mrta_rop_logo_removed_files: [] as number[],
    mrta_liva_logo_temp_folders: [] as string[],
    mrta_liva_logo_removed_files: [] as number[],
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const formErrorList = computed(() => Object.values(form.errors ?? {}).filter((value): value is string => typeof value === 'string' && value.length > 0));
const previewNonce = ref(0);
const acceptedImageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
const omanLogoUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const ropLogoUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const bottomLogoUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const omanLogoTempFolders = ref<string[]>([]);
const ropLogoTempFolders = ref<string[]>([]);
const bottomLogoTempFolders = ref<string[]>([]);
const omanLogoRemovedFileIds = ref<number[]>([]);
const ropLogoRemovedFileIds = ref<number[]>([]);
const bottomLogoRemovedFileIds = ref<number[]>([]);

watch(
    () => form.mrta_pdf,
    () => {
        previewNonce.value += 1;
    },
    { deep: true }
);

watch(
    omanLogoTempFolders,
    (value) => {
        form.mrta_oman_logo_temp_folders = [...value];
    },
    { deep: true }
);

watch(
    ropLogoTempFolders,
    (value) => {
        form.mrta_rop_logo_temp_folders = [...value];
    },
    { deep: true }
);

watch(
    bottomLogoTempFolders,
    (value) => {
        form.mrta_liva_logo_temp_folders = [...value];
    },
    { deep: true }
);

const previewSrc = computed(() => {
    if (!props.previewUrl) {
        return '';
    }

    const separator = props.previewUrl.includes('?') ? '&' : '?';

    return `${props.previewUrl}${separator}preview_nonce=${previewNonce.value}`;
});

function handleOmanLogoRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        omanLogoRemovedFileIds.value.push(data.fileId);
        form.mrta_oman_logo_removed_files = [...new Set(omanLogoRemovedFileIds.value)];
    }
}

function handleRopLogoRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        ropLogoRemovedFileIds.value.push(data.fileId);
        form.mrta_rop_logo_removed_files = [...new Set(ropLogoRemovedFileIds.value)];
    }
}

function handleBottomLogoRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        bottomLogoRemovedFileIds.value.push(data.fileId);
        form.mrta_liva_logo_removed_files = [...new Set(bottomLogoRemovedFileIds.value)];
    }
}

function resetUploadState() {
    omanLogoTempFolders.value = [];
    form.mrta_oman_logo_temp_folders = [];
    form.mrta_oman_logo_removed_files = [];
    omanLogoRemovedFileIds.value = [];
    omanLogoUploadRef.value?.resetFiles();

    ropLogoTempFolders.value = [];
    form.mrta_rop_logo_temp_folders = [];
    form.mrta_rop_logo_removed_files = [];
    ropLogoRemovedFileIds.value = [];
    ropLogoUploadRef.value?.resetFiles();

    bottomLogoTempFolders.value = [];
    form.mrta_liva_logo_temp_folders = [];
    form.mrta_liva_logo_removed_files = [];
    bottomLogoRemovedFileIds.value = [];
    bottomLogoUploadRef.value?.resetFiles();
}

function submit() {
    form.put(props.actions.update, {
        preserveScroll: true,
        onSuccess: resetUploadState,
    });
}
</script>

<template>
    <Head :title="localize('MRTA PDF Settings', 'إعدادات ملف الحادث PDF')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('MRTA PDF Settings', 'إعدادات ملف الحادث PDF') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Control the logos, color, insurance text, contact details, and footer used in the accident PDF.', 'تحكم بالصور واللون ونصوص التأمين ومعلومات التواصل والفوتر داخل ملف الحادث.') }}
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جاري الحفظ...') : localize('Save Settings', 'حفظ الإعدادات') }}
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

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(420px,0.9fr)]">
                <form class="space-y-6" @submit.prevent="submit">
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Color and logos', 'اللون والصور') }}</CardTitle>
                            <CardDescription>{{ localize('Upload images for the PDF. If a logo is empty, the PDF uses the built-in drawn mark.', 'ارفع صور الشعارات للملف. إذا تركت شعارًا فارغًا سيستخدم الملف الرسم الافتراضي.') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-[180px_1fr]">
                                <div class="space-y-2">
                                    <Label for="primary_color">{{ localize('Main Color', 'اللون الرئيسي') }}</Label>
                                    <div class="flex gap-2">
                                        <Input id="primary_color_picker" v-model="form.mrta_pdf.primary_color" type="color" class="h-10 w-14 p-1" />
                                        <Input id="primary_color" v-model="form.mrta_pdf.primary_color" placeholder="#f15a24" />
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label>{{ localize('Oman top-left logo', 'شعار عمان أعلى اليسار') }}</Label>
                                <FileUpload
                                    ref="omanLogoUploadRef"
                                    v-model="omanLogoTempFolders"
                                    :initial-files="props.mrtaLogoFiles.oman"
                                    :allowed-file-types="acceptedImageTypes"
                                    :max-file-size="1024 * 1024 * 5"
                                    collection="mrta_oman_logo"
                                    @file-removed="handleOmanLogoRemoved"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>{{ localize('ROP top-right logo', 'شعار شرطة عمان أعلى اليمين') }}</Label>
                                <FileUpload
                                    ref="ropLogoUploadRef"
                                    v-model="ropLogoTempFolders"
                                    :initial-files="props.mrtaLogoFiles.rop"
                                    :allowed-file-types="acceptedImageTypes"
                                    :max-file-size="1024 * 1024 * 5"
                                    collection="mrta_rop_logo"
                                    @file-removed="handleRopLogoRemoved"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>{{ localize('Bottom insurance logo', 'شعار التأمين في الأسفل') }}</Label>
                                <FileUpload
                                    ref="bottomLogoUploadRef"
                                    v-model="bottomLogoTempFolders"
                                    :initial-files="props.mrtaLogoFiles.liva"
                                    :allowed-file-types="acceptedImageTypes"
                                    :max-file-size="1024 * 1024 * 5"
                                    collection="mrta_liva_logo"
                                    @file-removed="handleBottomLogoRemoved"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Insurance section text', 'نصوص قسم التأمين') }}</CardTitle>
                            <CardDescription>{{ localize('These values control the visible Insurance title, fallback logo text, and contact details.', 'هذه القيم تتحكم بعنوان التأمين ونص الشعار البديل ومعلومات التواصل.') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="insurance_title_en">{{ localize('Insurance title (EN)', 'عنوان التأمين (EN)') }}</Label>
                                <Input id="insurance_title_en" v-model="form.mrta_pdf.insurance_section_title_en" />
                            </div>
                            <div class="space-y-2">
                                <Label for="insurance_title_ar">{{ localize('Insurance title (AR)', 'عنوان التأمين (AR)') }}</Label>
                                <Input id="insurance_title_ar" v-model="form.mrta_pdf.insurance_section_title_ar" dir="rtl" />
                            </div>
                            <div class="space-y-2">
                                <Label for="liva_logo_text">{{ localize('Fallback logo text', 'نص الشعار البديل') }}</Label>
                                <Input id="liva_logo_text" v-model="form.mrta_pdf.liva_logo_text" />
                            </div>
                            <div class="space-y-2">
                                <Label for="liva_logo_ar">{{ localize('Fallback logo Arabic text', 'نص الشعار العربي البديل') }}</Label>
                                <Input id="liva_logo_ar" v-model="form.mrta_pdf.liva_logo_ar" dir="rtl" />
                            </div>
                            <div class="space-y-2">
                                <Label for="liva_contact_email">{{ localize('Contact email', 'البريد الإلكتروني') }}</Label>
                                <Input id="liva_contact_email" v-model="form.mrta_pdf.liva_contact_email" />
                            </div>
                            <div class="space-y-2">
                                <Label for="liva_contact_website">{{ localize('Website', 'الموقع') }}</Label>
                                <Input id="liva_contact_website" v-model="form.mrta_pdf.liva_contact_website" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Footer text', 'نص الفوتر') }}</CardTitle>
                            <CardDescription>{{ localize('Shown at the very bottom of the generated PDF.', 'يظهر في أسفل ملف PDF.') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="footer_ar">{{ localize('Footer Arabic', 'الفوتر العربي') }}</Label>
                                <Textarea id="footer_ar" v-model="form.mrta_pdf.footer_ar" rows="3" dir="rtl" />
                            </div>
                            <div class="space-y-2">
                                <Label for="footer_en">{{ localize('Footer English', 'الفوتر الإنجليزي') }}</Label>
                                <Textarea id="footer_en" v-model="form.mrta_pdf.footer_en" rows="3" />
                            </div>
                        </CardContent>
                    </Card>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? localize('Saving...', 'جاري الحفظ...') : localize('Save Settings', 'حفظ الإعدادات') }}
                        </Button>
                    </div>
                </form>

                <Card class="h-fit">
                    <CardHeader>
                        <CardTitle>{{ localize('Preview', 'المعاينة') }}</CardTitle>
                        <CardDescription>{{ localize('Preview uses the latest accident report after saving.', 'المعاينة تستخدم آخر بلاغ حادث بعد الحفظ.') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <iframe
                            v-if="previewSrc"
                            :src="previewSrc"
                            class="h-[760px] w-full rounded-md border"
                            title="MRTA PDF preview"
                        ></iframe>
                        <div v-else class="rounded-md border border-dashed p-6 text-sm text-muted-foreground">
                            {{ localize('Create an accident report first to enable PDF preview.', 'أنشئ بلاغ حادث أولاً لتفعيل المعاينة.') }}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </main>
    </AdminLayout>
</template>
