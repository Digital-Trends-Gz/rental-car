<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type LocalizedText = { en: string | null; ar: string | null };

const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
    };
    settings: {
        site_name: string | null;
        logo_url: string | null;
        primary_color: string;
        secondary_color: string;
        tax_percentage: number;
        hero: {
            title: LocalizedText;
            description: LocalizedText;
            button_text: LocalizedText;
            button_link: string | null;
        };
        about: {
            title: LocalizedText;
            subtitle: LocalizedText;
            story_title: LocalizedText;
            story_p1: LocalizedText;
            story_p2: LocalizedText;
            mission_title: LocalizedText;
            mission_subtitle: LocalizedText;
            cta_title: LocalizedText;
            cta_subtitle: LocalizedText;
            cta_browse_text: LocalizedText;
            cta_contact_text: LocalizedText;
        };
        contact: {
            phone: string | null;
            email: string | null;
            address: LocalizedText;
        };
        contact_page: {
            title: LocalizedText;
            subtitle: LocalizedText;
            form_title: LocalizedText;
            info_title: LocalizedText;
            hours: LocalizedText;
            quick_links_title: LocalizedText;
        };
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
        footer: {
            description: LocalizedText;
        };
    };
    logoFiles: Array<{ id: number; url: string }>;
    actions: {
        update: string;
    };
}>();

const { locale } = useTrans();
const page = usePage<any>();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const form = useForm({
    site_name: props.settings.site_name ?? '',
    logo_url: props.settings.logo_url ?? '',
    logo_temp_folders: [] as string[],
    logo_removed_files: [] as number[],
    primary_color: props.settings.primary_color || '#f97316',
    secondary_color: props.settings.secondary_color || '#ea580c',
    tax_percentage: props.settings.tax_percentage ?? 7,
    hero: {
        title: {
            en: props.settings.hero?.title?.en ?? '',
            ar: props.settings.hero?.title?.ar ?? '',
        },
        description: {
            en: props.settings.hero?.description?.en ?? '',
            ar: props.settings.hero?.description?.ar ?? '',
        },
        button_text: {
            en: props.settings.hero?.button_text?.en ?? '',
            ar: props.settings.hero?.button_text?.ar ?? '',
        },
        button_link: props.settings.hero?.button_link ?? '',
    },
    about: {
        title: {
            en: props.settings.about?.title?.en ?? '',
            ar: props.settings.about?.title?.ar ?? '',
        },
        subtitle: {
            en: props.settings.about?.subtitle?.en ?? '',
            ar: props.settings.about?.subtitle?.ar ?? '',
        },
        story_title: {
            en: props.settings.about?.story_title?.en ?? '',
            ar: props.settings.about?.story_title?.ar ?? '',
        },
        story_p1: {
            en: props.settings.about?.story_p1?.en ?? '',
            ar: props.settings.about?.story_p1?.ar ?? '',
        },
        story_p2: {
            en: props.settings.about?.story_p2?.en ?? '',
            ar: props.settings.about?.story_p2?.ar ?? '',
        },
        mission_title: {
            en: props.settings.about?.mission_title?.en ?? '',
            ar: props.settings.about?.mission_title?.ar ?? '',
        },
        mission_subtitle: {
            en: props.settings.about?.mission_subtitle?.en ?? '',
            ar: props.settings.about?.mission_subtitle?.ar ?? '',
        },
        cta_title: {
            en: props.settings.about?.cta_title?.en ?? '',
            ar: props.settings.about?.cta_title?.ar ?? '',
        },
        cta_subtitle: {
            en: props.settings.about?.cta_subtitle?.en ?? '',
            ar: props.settings.about?.cta_subtitle?.ar ?? '',
        },
        cta_browse_text: {
            en: props.settings.about?.cta_browse_text?.en ?? '',
            ar: props.settings.about?.cta_browse_text?.ar ?? '',
        },
        cta_contact_text: {
            en: props.settings.about?.cta_contact_text?.en ?? '',
            ar: props.settings.about?.cta_contact_text?.ar ?? '',
        },
    },
    contact: {
        phone: props.settings.contact?.phone ?? '',
        email: props.settings.contact?.email ?? '',
        address: {
            en: props.settings.contact?.address?.en ?? '',
            ar: props.settings.contact?.address?.ar ?? '',
        },
    },
    contact_page: {
        title: {
            en: props.settings.contact_page?.title?.en ?? '',
            ar: props.settings.contact_page?.title?.ar ?? '',
        },
        subtitle: {
            en: props.settings.contact_page?.subtitle?.en ?? '',
            ar: props.settings.contact_page?.subtitle?.ar ?? '',
        },
        form_title: {
            en: props.settings.contact_page?.form_title?.en ?? '',
            ar: props.settings.contact_page?.form_title?.ar ?? '',
        },
        info_title: {
            en: props.settings.contact_page?.info_title?.en ?? '',
            ar: props.settings.contact_page?.info_title?.ar ?? '',
        },
        hours: {
            en: props.settings.contact_page?.hours?.en ?? '',
            ar: props.settings.contact_page?.hours?.ar ?? '',
        },
        quick_links_title: {
            en: props.settings.contact_page?.quick_links_title?.en ?? '',
            ar: props.settings.contact_page?.quick_links_title?.ar ?? '',
        },
    },
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
    footer: {
        description: {
            en: props.settings.footer?.description?.en ?? '',
            ar: props.settings.footer?.description?.ar ?? '',
        },
    },
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const flashRestrictedAction = computed(() => page.props.flash?.restricted_action ?? null);
const formErrorList = computed(() => Object.values(form.errors ?? {}).filter((value): value is string => typeof value === 'string' && value.length > 0));
const previewName = computed(() => form.site_name || props.tenant.name);
const uploadedLogoUrl = computed(() => props.logoFiles?.[0]?.url || null);
const previewLogoUrl = computed(() => uploadedLogoUrl.value || form.logo_url || null);
const primarySecondaryGradient = computed(
    () => `linear-gradient(135deg, ${form.primary_color || '#f97316'}, ${form.secondary_color || '#ea580c'})`,
);

const fileUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const logoTempFolders = ref<string[]>([]);
const logoRemovedFileIds = ref<number[]>([]);
const showAdvancedBranding = ref(false);

watch(
    logoTempFolders,
    (value) => {
        form.logo_temp_folders = [...value];
    },
    { deep: true },
);

watch(
    () => form.errors.logo_url,
    (value) => {
        if (value) {
            showAdvancedBranding.value = true;
        }
    },
);

function handleLogoFileRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        logoRemovedFileIds.value.push(data.fileId);
        form.logo_removed_files = [...new Set(logoRemovedFileIds.value)];
    }
}

function submit() {
    form.put(props.actions.update, {
        preserveScroll: true,
        onSuccess: () => {
            logoTempFolders.value = [];
            form.logo_temp_folders = [];
            form.logo_removed_files = [];
            logoRemovedFileIds.value = [];
            fileUploadRef.value?.resetFiles();
        },
    });
}
</script>

<template>
    <Head :title="localize('Website Settings', 'إعدادات الموقع')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Website Settings', 'إعدادات الموقع') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Customize your tenant website branding and homepage content (Arabic / English).', 'خصص هوية موقع المستأجر ومحتوى الصفحة الرئيسية باللغتين العربية والإنجليزية.') }}
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                </Button>
            </div>

            <div v-if="flashSuccess" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ flashError }}
            </div>
            <div v-if="flashRestrictedAction" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                {{ flashRestrictedAction }}
            </div>
            <div v-if="formErrorList.length" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <div class="font-medium">{{ localize('Please fix the following errors:', 'يرجى تصحيح الأخطاء التالية:') }}</div>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="(message, idx) in formErrorList" :key="idx">{{ message }}</li>
                </ul>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-lg border p-5">
                    <div class="mb-4">
                        <h2 class="text-lg font-semibold">{{ localize('Branding', 'الهوية البصرية') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ localize('Site identity, logo URL, and brand colors.', 'هوية الموقع ورابط الشعار وألوان العلامة التجارية.') }}</p>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2 md:col-span-2">
                                <Label for="site_name">{{ localize('Site Name', 'اسم الموقع') }}</Label>
                                <Input id="site_name" v-model="form.site_name" :placeholder="localize('Tenant website name', 'اسم موقع المستأجر')" />
                                <p v-if="form.errors.site_name" class="text-sm text-red-600">{{ form.errors.site_name }}</p>
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <Label>{{ localize('Logo Upload (System)', 'رفع الشعار (النظام)') }}</Label>
                                <FileUpload
                                    ref="fileUploadRef"
                                    v-model="logoTempFolders"
                                    :initial-files="logoFiles || []"
                                    :allow-multiple="false"
                                    :max-files="1"
                                    collection="logo"
                                    theme="light"
                                    width="100%"
                                    @file-removed="handleLogoFileRemoved"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ localize('Upload logo to your system. New upload replaces the previous logo.', 'ارفع الشعار إلى النظام. أي رفع جديد سيستبدل الشعار السابق.') }}
                                </p>
                            </div>

                            <div class="md:col-span-2 rounded-md border bg-muted/20 p-3 space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-medium">{{ localize('Advanced Branding Options', 'خيارات الهوية المتقدمة') }}</div>
                                        <p class="text-xs text-muted-foreground">{{ localize('Optional fallback logo URL (used only if no uploaded logo exists).', 'رابط شعار احتياطي اختياري ويُستخدم فقط إذا لم يوجد شعار مرفوع.') }}</p>
                                    </div>
                                    <Button type="button" variant="outline" size="sm" @click="showAdvancedBranding = !showAdvancedBranding">
                                        {{ showAdvancedBranding ? localize('Hide Advanced', 'إخفاء المتقدم') : localize('Show Advanced', 'إظهار المتقدم') }}
                                    </Button>
                                </div>

                                <div v-if="showAdvancedBranding" class="space-y-2">
                                    <Label for="logo_url">{{ localize('Fallback Logo URL', 'رابط الشعار الاحتياطي') }}</Label>
                                    <Input id="logo_url" v-model="form.logo_url" placeholder="https://example.com/logo.png" />
                                    <p class="text-xs text-muted-foreground">
                                        {{ localize('This URL is used only when no uploaded logo exists in the system.', 'يُستخدم هذا الرابط فقط عندما لا يوجد شعار مرفوع في النظام.') }}
                                    </p>
                                    <p v-if="form.errors.logo_url" class="text-sm text-red-600">{{ form.errors.logo_url }}</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="primary_color">{{ localize('Primary Color', 'اللون الأساسي') }}</Label>
                                <div class="flex items-center gap-2">
                                    <input id="primary_color" v-model="form.primary_color" type="color" class="h-10 w-14 rounded border border-input bg-white p-1" />
                                    <Input v-model="form.primary_color" placeholder="#f97316" />
                                </div>
                                <p v-if="form.errors.primary_color" class="text-sm text-red-600">{{ form.errors.primary_color }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="secondary_color">{{ localize('Secondary Color', 'اللون الثانوي') }}</Label>
                                <div class="flex items-center gap-2">
                                    <input id="secondary_color" v-model="form.secondary_color" type="color" class="h-10 w-14 rounded border border-input bg-white p-1" />
                                    <Input v-model="form.secondary_color" placeholder="#ea580c" />
                                </div>
                                <p v-if="form.errors.secondary_color" class="text-sm text-red-600">{{ form.errors.secondary_color }}</p>
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <Label for="tax_percentage">{{ localize('Booking Tax Percentage', 'نسبة ضريبة الحجز') }}</Label>
                                <Input
                                    id="tax_percentage"
                                    v-model.number="form.tax_percentage"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="7"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ localize('Set `0` to hide tax in booking page.', 'ضع `0` لإخفاء الضريبة في صفحة الحجز.') }}
                                </p>
                                <p v-if="form.errors.tax_percentage" class="text-sm text-red-600">{{ form.errors.tax_percentage }}</p>
                            </div>
                        </div>

                        <div class="rounded-lg border p-4">
                            <div class="text-sm font-medium mb-3">{{ localize('Preview', 'معاينة') }}</div>
                            <div class="rounded-xl border overflow-hidden bg-white">
                                <div class="h-20" :style="{ background: primarySecondaryGradient }"></div>
                                <div class="p-4 space-y-3">
                                    <div class="flex items-center gap-3">
                                        <img
                                            v-if="previewLogoUrl"
                                            :src="previewLogoUrl"
                                            alt="logo preview"
                                            class="h-10 w-10 rounded object-contain border bg-white p-1"
                                        />
                                        <div
                                            v-else
                                            class="h-10 w-10 rounded flex items-center justify-center text-white font-bold"
                                            :style="{ background: form.primary_color }"
                                        >
                                            {{ previewName.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="font-semibold truncate">{{ previewName }}</div>
                                    </div>
                                    <button
                                        type="button"
                                        class="w-full rounded-md px-3 py-2 text-sm font-semibold text-white"
                                        :style="{ background: primarySecondaryGradient }"
                                    >
                                        {{ localize('CTA Preview', 'معاينة زر الدعوة') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('Hero Section', 'القسم الرئيسي') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ localize('Main banner texts for the tenant homepage.', 'النصوص الرئيسية لواجهة الصفحة الرئيسية الخاصة بالمستأجر.') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="hero_title_en">{{ localize('Hero Title (EN)', 'عنوان القسم الرئيسي (EN)') }}</Label>
                            <Input id="hero_title_en" v-model="form.hero.title.en" placeholder="Rent the perfect car today" />
                            <p v-if="form.errors['hero.title.en']" class="text-sm text-red-600">{{ form.errors['hero.title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="hero_title_ar">{{ localize('Hero Title (AR)', 'عنوان القسم الرئيسي (AR)') }}</Label>
                            <Input id="hero_title_ar" v-model="form.hero.title.ar" placeholder="استأجر السيارة المناسبة اليوم" dir="rtl" />
                            <p v-if="form.errors['hero.title.ar']" class="text-sm text-red-600">{{ form.errors['hero.title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="hero_desc_en">{{ localize('Hero Description (EN)', 'وصف القسم الرئيسي (EN)') }}</Label>
                            <textarea id="hero_desc_en" v-model="form.hero.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['hero.description.en']" class="text-sm text-red-600">{{ form.errors['hero.description.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="hero_desc_ar">{{ localize('Hero Description (AR)', 'وصف القسم الرئيسي (AR)') }}</Label>
                            <textarea id="hero_desc_ar" v-model="form.hero.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['hero.description.ar']" class="text-sm text-red-600">{{ form.errors['hero.description.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="hero_button_text_en">{{ localize('Button Text (EN)', 'نص الزر (EN)') }}</Label>
                            <Input id="hero_button_text_en" v-model="form.hero.button_text.en" placeholder="Browse Fleet" />
                            <p v-if="form.errors['hero.button_text.en']" class="text-sm text-red-600">{{ form.errors['hero.button_text.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="hero_button_text_ar">{{ localize('Button Text (AR)', 'نص الزر (AR)') }}</Label>
                            <Input id="hero_button_text_ar" v-model="form.hero.button_text.ar" placeholder="تصفح السيارات" dir="rtl" />
                            <p v-if="form.errors['hero.button_text.ar']" class="text-sm text-red-600">{{ form.errors['hero.button_text.ar'] }}</p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="hero_button_link">{{ localize('Button Link', 'رابط الزر') }}</Label>
                            <Input id="hero_button_link" v-model="form.hero.button_link" placeholder="/fleet" />
                            <p class="text-xs text-muted-foreground">{{ localize('Example: `/fleet` or `https://...`', 'مثال: `/fleet` أو `https://...`') }}</p>
                            <p v-if="form.errors['hero.button_link']" class="text-sm text-red-600">{{ form.errors['hero.button_link'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('About Page', 'صفحة من نحن') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ localize('Editable content for public About page.', 'محتوى قابل للتعديل لصفحة من نحن العامة.') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="about_title_en">{{ localize('Page Title (EN)', 'عنوان الصفحة (EN)') }}</Label>
                            <Input id="about_title_en" v-model="form.about.title.en" />
                            <p v-if="form.errors['about.title.en']" class="text-sm text-red-600">{{ form.errors['about.title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_title_ar">{{ localize('Page Title (AR)', 'عنوان الصفحة (AR)') }}</Label>
                            <Input id="about_title_ar" v-model="form.about.title.ar" dir="rtl" />
                            <p v-if="form.errors['about.title.ar']" class="text-sm text-red-600">{{ form.errors['about.title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_subtitle_en">{{ localize('Subtitle (EN)', 'العنوان الفرعي (EN)') }}</Label>
                            <textarea id="about_subtitle_en" v-model="form.about.subtitle.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.subtitle.en']" class="text-sm text-red-600">{{ form.errors['about.subtitle.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_subtitle_ar">{{ localize('Subtitle (AR)', 'العنوان الفرعي (AR)') }}</Label>
                            <textarea id="about_subtitle_ar" v-model="form.about.subtitle.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.subtitle.ar']" class="text-sm text-red-600">{{ form.errors['about.subtitle.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_story_title_en">{{ localize('Story Title (EN)', 'عنوان القصة (EN)') }}</Label>
                            <Input id="about_story_title_en" v-model="form.about.story_title.en" />
                            <p v-if="form.errors['about.story_title.en']" class="text-sm text-red-600">{{ form.errors['about.story_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_story_title_ar">{{ localize('Story Title (AR)', 'عنوان القصة (AR)') }}</Label>
                            <Input id="about_story_title_ar" v-model="form.about.story_title.ar" dir="rtl" />
                            <p v-if="form.errors['about.story_title.ar']" class="text-sm text-red-600">{{ form.errors['about.story_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_story_p1_en">{{ localize('Story Paragraph 1 (EN)', 'الفقرة الأولى من القصة (EN)') }}</Label>
                            <textarea id="about_story_p1_en" v-model="form.about.story_p1.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.story_p1.en']" class="text-sm text-red-600">{{ form.errors['about.story_p1.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_story_p1_ar">{{ localize('Story Paragraph 1 (AR)', 'الفقرة الأولى من القصة (AR)') }}</Label>
                            <textarea id="about_story_p1_ar" v-model="form.about.story_p1.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.story_p1.ar']" class="text-sm text-red-600">{{ form.errors['about.story_p1.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_story_p2_en">{{ localize('Story Paragraph 2 (EN)', 'الفقرة الثانية من القصة (EN)') }}</Label>
                            <textarea id="about_story_p2_en" v-model="form.about.story_p2.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.story_p2.en']" class="text-sm text-red-600">{{ form.errors['about.story_p2.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_story_p2_ar">{{ localize('Story Paragraph 2 (AR)', 'الفقرة الثانية من القصة (AR)') }}</Label>
                            <textarea id="about_story_p2_ar" v-model="form.about.story_p2.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.story_p2.ar']" class="text-sm text-red-600">{{ form.errors['about.story_p2.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_mission_title_en">{{ localize('Mission Title (EN)', 'عنوان الرسالة (EN)') }}</Label>
                            <Input id="about_mission_title_en" v-model="form.about.mission_title.en" />
                            <p v-if="form.errors['about.mission_title.en']" class="text-sm text-red-600">{{ form.errors['about.mission_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_mission_title_ar">{{ localize('Mission Title (AR)', 'عنوان الرسالة (AR)') }}</Label>
                            <Input id="about_mission_title_ar" v-model="form.about.mission_title.ar" dir="rtl" />
                            <p v-if="form.errors['about.mission_title.ar']" class="text-sm text-red-600">{{ form.errors['about.mission_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_mission_subtitle_en">{{ localize('Mission Subtitle (EN)', 'وصف الرسالة (EN)') }}</Label>
                            <textarea id="about_mission_subtitle_en" v-model="form.about.mission_subtitle.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.mission_subtitle.en']" class="text-sm text-red-600">{{ form.errors['about.mission_subtitle.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_mission_subtitle_ar">{{ localize('Mission Subtitle (AR)', 'وصف الرسالة (AR)') }}</Label>
                            <textarea id="about_mission_subtitle_ar" v-model="form.about.mission_subtitle.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.mission_subtitle.ar']" class="text-sm text-red-600">{{ form.errors['about.mission_subtitle.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_cta_title_en">{{ localize('CTA Title (EN)', 'عنوان الدعوة للإجراء (EN)') }}</Label>
                            <Input id="about_cta_title_en" v-model="form.about.cta_title.en" />
                            <p v-if="form.errors['about.cta_title.en']" class="text-sm text-red-600">{{ form.errors['about.cta_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_cta_title_ar">{{ localize('CTA Title (AR)', 'عنوان الدعوة للإجراء (AR)') }}</Label>
                            <Input id="about_cta_title_ar" v-model="form.about.cta_title.ar" dir="rtl" />
                            <p v-if="form.errors['about.cta_title.ar']" class="text-sm text-red-600">{{ form.errors['about.cta_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_cta_subtitle_en">{{ localize('CTA Subtitle (EN)', 'وصف الدعوة للإجراء (EN)') }}</Label>
                            <textarea id="about_cta_subtitle_en" v-model="form.about.cta_subtitle.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.cta_subtitle.en']" class="text-sm text-red-600">{{ form.errors['about.cta_subtitle.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_cta_subtitle_ar">{{ localize('CTA Subtitle (AR)', 'وصف الدعوة للإجراء (AR)') }}</Label>
                            <textarea id="about_cta_subtitle_ar" v-model="form.about.cta_subtitle.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.cta_subtitle.ar']" class="text-sm text-red-600">{{ form.errors['about.cta_subtitle.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_cta_browse_text_en">{{ localize('CTA Browse Button (EN)', 'زر التصفح في الدعوة (EN)') }}</Label>
                            <Input id="about_cta_browse_text_en" v-model="form.about.cta_browse_text.en" />
                            <p v-if="form.errors['about.cta_browse_text.en']" class="text-sm text-red-600">{{ form.errors['about.cta_browse_text.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_cta_browse_text_ar">{{ localize('CTA Browse Button (AR)', 'زر التصفح في الدعوة (AR)') }}</Label>
                            <Input id="about_cta_browse_text_ar" v-model="form.about.cta_browse_text.ar" dir="rtl" />
                            <p v-if="form.errors['about.cta_browse_text.ar']" class="text-sm text-red-600">{{ form.errors['about.cta_browse_text.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_cta_contact_text_en">{{ localize('CTA Contact Button (EN)', 'زر التواصل في الدعوة (EN)') }}</Label>
                            <Input id="about_cta_contact_text_en" v-model="form.about.cta_contact_text.en" />
                            <p v-if="form.errors['about.cta_contact_text.en']" class="text-sm text-red-600">{{ form.errors['about.cta_contact_text.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_cta_contact_text_ar">{{ localize('CTA Contact Button (AR)', 'زر التواصل في الدعوة (AR)') }}</Label>
                            <Input id="about_cta_contact_text_ar" v-model="form.about.cta_contact_text.ar" dir="rtl" />
                            <p v-if="form.errors['about.cta_contact_text.ar']" class="text-sm text-red-600">{{ form.errors['about.cta_contact_text.ar'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('Contract PDF Header', 'ترويسة العقد') }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ localize('Editable company header content printed at the top of the contract PDF.', 'محتوى ترويسة الشركة الذي يظهر في أعلى ملف العقد.') }}
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="pdf_header_company_name_en">{{ localize('Company Name (EN)', 'اسم الشركة (EN)') }}</Label>
                            <Input id="pdf_header_company_name_en" v-model="form.pdf_header.company_name.en" />
                            <p v-if="form.errors['pdf_header.company_name.en']" class="text-sm text-red-600">{{ form.errors['pdf_header.company_name.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_company_name_ar">{{ localize('Company Name (AR)', 'اسم الشركة (AR)') }}</Label>
                            <Input id="pdf_header_company_name_ar" v-model="form.pdf_header.company_name.ar" dir="rtl" />
                            <p v-if="form.errors['pdf_header.company_name.ar']" class="text-sm text-red-600">{{ form.errors['pdf_header.company_name.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_cr_number">{{ localize('C.R Number', 'رقم السجل التجاري') }}</Label>
                            <Input id="pdf_header_cr_number" v-model="form.pdf_header.cr_number" />
                            <p v-if="form.errors['pdf_header.cr_number']" class="text-sm text-red-600">{{ form.errors['pdf_header.cr_number'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_po_box">{{ localize('P.O. Box', 'صندوق البريد') }}</Label>
                            <Input id="pdf_header_po_box" v-model="form.pdf_header.po_box" />
                            <p v-if="form.errors['pdf_header.po_box']" class="text-sm text-red-600">{{ form.errors['pdf_header.po_box'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_pc">{{ localize('P.C', 'الرمز البريدي') }}</Label>
                            <Input id="pdf_header_pc" v-model="form.pdf_header.pc" />
                            <p v-if="form.errors['pdf_header.pc']" class="text-sm text-red-600">{{ form.errors['pdf_header.pc'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_country_en">{{ localize('Country (EN)', 'الدولة (EN)') }}</Label>
                            <Input id="pdf_header_country_en" v-model="form.pdf_header.country.en" />
                            <p v-if="form.errors['pdf_header.country.en']" class="text-sm text-red-600">{{ form.errors['pdf_header.country.en'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_country_ar">{{ localize('Country (AR)', 'الدولة (AR)') }}</Label>
                            <Input id="pdf_header_country_ar" v-model="form.pdf_header.country.ar" dir="rtl" />
                            <p v-if="form.errors['pdf_header.country.ar']" class="text-sm text-red-600">{{ form.errors['pdf_header.country.ar'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_gsm_1">{{ localize('GSM 1', 'نقال 1') }}</Label>
                            <Input id="pdf_header_gsm_1" v-model="form.pdf_header.gsm_1" />
                            <p v-if="form.errors['pdf_header.gsm_1']" class="text-sm text-red-600">{{ form.errors['pdf_header.gsm_1'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_gsm_2">{{ localize('GSM 2', 'نقال 2') }}</Label>
                            <Input id="pdf_header_gsm_2" v-model="form.pdf_header.gsm_2" />
                            <p v-if="form.errors['pdf_header.gsm_2']" class="text-sm text-red-600">{{ form.errors['pdf_header.gsm_2'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_gsm_3">{{ localize('GSM 3', 'نقال 3') }}</Label>
                            <Input id="pdf_header_gsm_3" v-model="form.pdf_header.gsm_3" />
                            <p v-if="form.errors['pdf_header.gsm_3']" class="text-sm text-red-600">{{ form.errors['pdf_header.gsm_3'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_registry_label_en">{{ localize('Registry Label (EN)', 'وسم السجل (EN)') }}</Label>
                            <Input id="pdf_header_registry_label_en" v-model="form.pdf_header.registry_label.en" />
                            <p v-if="form.errors['pdf_header.registry_label.en']" class="text-sm text-red-600">{{ form.errors['pdf_header.registry_label.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_registry_label_ar">{{ localize('Registry Label (AR)', 'وسم السجل (AR)') }}</Label>
                            <Input id="pdf_header_registry_label_ar" v-model="form.pdf_header.registry_label.ar" dir="rtl" />
                            <p v-if="form.errors['pdf_header.registry_label.ar']" class="text-sm text-red-600">{{ form.errors['pdf_header.registry_label.ar'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('Contact Page', 'صفحة اتصل بنا') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ localize('Editable titles and business hours for public Contact page.', 'العناوين وساعات العمل القابلة للتعديل في صفحة اتصل بنا العامة.') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="contact_page_title_en">{{ localize('Page Title (EN)', 'عنوان الصفحة (EN)') }}</Label>
                            <Input id="contact_page_title_en" v-model="form.contact_page.title.en" />
                            <p v-if="form.errors['contact_page.title.en']" class="text-sm text-red-600">{{ form.errors['contact_page.title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_title_ar">{{ localize('Page Title (AR)', 'عنوان الصفحة (AR)') }}</Label>
                            <Input id="contact_page_title_ar" v-model="form.contact_page.title.ar" dir="rtl" />
                            <p v-if="form.errors['contact_page.title.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_subtitle_en">{{ localize('Subtitle (EN)', 'العنوان الفرعي (EN)') }}</Label>
                            <textarea id="contact_page_subtitle_en" v-model="form.contact_page.subtitle.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['contact_page.subtitle.en']" class="text-sm text-red-600">{{ form.errors['contact_page.subtitle.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_subtitle_ar">{{ localize('Subtitle (AR)', 'العنوان الفرعي (AR)') }}</Label>
                            <textarea id="contact_page_subtitle_ar" v-model="form.contact_page.subtitle.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['contact_page.subtitle.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.subtitle.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_form_title_en">{{ localize('Form Title (EN)', 'عنوان النموذج (EN)') }}</Label>
                            <Input id="contact_page_form_title_en" v-model="form.contact_page.form_title.en" />
                            <p v-if="form.errors['contact_page.form_title.en']" class="text-sm text-red-600">{{ form.errors['contact_page.form_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_form_title_ar">{{ localize('Form Title (AR)', 'عنوان النموذج (AR)') }}</Label>
                            <Input id="contact_page_form_title_ar" v-model="form.contact_page.form_title.ar" dir="rtl" />
                            <p v-if="form.errors['contact_page.form_title.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.form_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_info_title_en">{{ localize('Sidebar Title (EN)', 'عنوان الشريط الجانبي (EN)') }}</Label>
                            <Input id="contact_page_info_title_en" v-model="form.contact_page.info_title.en" />
                            <p v-if="form.errors['contact_page.info_title.en']" class="text-sm text-red-600">{{ form.errors['contact_page.info_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_info_title_ar">{{ localize('Sidebar Title (AR)', 'عنوان الشريط الجانبي (AR)') }}</Label>
                            <Input id="contact_page_info_title_ar" v-model="form.contact_page.info_title.ar" dir="rtl" />
                            <p v-if="form.errors['contact_page.info_title.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.info_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_hours_en">{{ localize('Business Hours (EN)', 'ساعات العمل (EN)') }}</Label>
                            <textarea id="contact_page_hours_en" v-model="form.contact_page.hours.en" rows="4" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p class="text-xs text-muted-foreground">{{ localize('Use new line for each row.', 'استخدم سطرًا جديدًا لكل صف.') }}</p>
                            <p v-if="form.errors['contact_page.hours.en']" class="text-sm text-red-600">{{ form.errors['contact_page.hours.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_hours_ar">{{ localize('Business Hours (AR)', 'ساعات العمل (AR)') }}</Label>
                            <textarea id="contact_page_hours_ar" v-model="form.contact_page.hours.ar" rows="4" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p class="text-xs text-muted-foreground">{{ localize('Use new line for each row.', 'استخدم سطرًا جديدًا لكل صف.') }}</p>
                            <p v-if="form.errors['contact_page.hours.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.hours.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_quick_links_title_en">{{ localize('Quick Links Title (EN)', 'عنوان الروابط السريعة (EN)') }}</Label>
                            <Input id="contact_page_quick_links_title_en" v-model="form.contact_page.quick_links_title.en" />
                            <p v-if="form.errors['contact_page.quick_links_title.en']" class="text-sm text-red-600">{{ form.errors['contact_page.quick_links_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_quick_links_title_ar">{{ localize('Quick Links Title (AR)', 'عنوان الروابط السريعة (AR)') }}</Label>
                            <Input id="contact_page_quick_links_title_ar" v-model="form.contact_page.quick_links_title.ar" dir="rtl" />
                            <p v-if="form.errors['contact_page.quick_links_title.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.quick_links_title.ar'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('Contact & Footer (MVP)', 'التواصل والتذييل') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ localize('Basic public contact info and footer description.', 'معلومات التواصل العامة ووصف التذييل.') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="contact_phone">{{ localize('Phone', 'الهاتف') }}</Label>
                            <Input id="contact_phone" v-model="form.contact.phone" placeholder="+965 ..." />
                            <p v-if="form.errors['contact.phone']" class="text-sm text-red-600">{{ form.errors['contact.phone'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_email">{{ localize('Email', 'البريد الإلكتروني') }}</Label>
                            <Input id="contact_email" v-model="form.contact.email" type="email" placeholder="hello@example.com" />
                            <p v-if="form.errors['contact.email']" class="text-sm text-red-600">{{ form.errors['contact.email'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_address_en">{{ localize('Address (EN)', 'العنوان (EN)') }}</Label>
                            <Input id="contact_address_en" v-model="form.contact.address.en" />
                            <p v-if="form.errors['contact.address.en']" class="text-sm text-red-600">{{ form.errors['contact.address.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_address_ar">{{ localize('Address (AR)', 'العنوان (AR)') }}</Label>
                            <Input id="contact_address_ar" v-model="form.contact.address.ar" dir="rtl" />
                            <p v-if="form.errors['contact.address.ar']" class="text-sm text-red-600">{{ form.errors['contact.address.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="footer_desc_en">{{ localize('Footer Description (EN)', 'وصف التذييل (EN)') }}</Label>
                            <textarea id="footer_desc_en" v-model="form.footer.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['footer.description.en']" class="text-sm text-red-600">{{ form.errors['footer.description.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="footer_desc_ar">{{ localize('Footer Description (AR)', 'وصف التذييل (AR)') }}</Label>
                            <textarea id="footer_desc_ar" v-model="form.footer.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['footer.description.ar']" class="text-sm text-red-600">{{ form.errors['footer.description.ar'] }}</p>
                        </div>
                    </div>
                </section>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                    </Button>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
