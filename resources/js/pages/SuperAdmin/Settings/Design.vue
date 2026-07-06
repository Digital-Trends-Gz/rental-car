<script setup lang="ts">
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ExternalLink, RefreshCw } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface FeatureCard {
    title: string;
    image_url: string;
    content: string;
}

interface StepItem {
    title: string;
    description: string;
}

interface FaqItem {
    question: string;
    answer: string;
}

interface QuickLinkItem {
    label: string;
    href: string;
}

interface LandingSettings {
    hero: {
        title: string;
        description: string;
        features: string[];
        image_url: string;
    };
    features_section: {
        title: string;
        description: string;
        cards: FeatureCard[];
    };
    getting_started: {
        title: string;
        description: string;
        items: StepItem[];
    };
    plans_section: {
        title: string;
        description: string;
    };
    faq_section: {
        title: string;
        description: string;
        items: FaqItem[];
    };
    contact_section: {
        title: string;
        description: string;
        form_title: string;
        name_label: string;
        name_placeholder: string;
        email_label: string;
        email_placeholder: string;
        subject_label: string;
        subject_placeholder: string;
        message_label: string;
        message_placeholder: string;
        submit_label: string;
        sending_label: string;
        success_message: string;
        error_message: string;
        direct_title: string;
        direct_email_label: string;
        direct_email: string;
        direct_phone_label: string;
        direct_phone: string;
        response_time_label: string;
        response_time: string;
        quick_links_title: string;
        quick_links: QuickLinkItem[];
    };
    footer: {
        title: string;
        description: string;
    };
}

const props = defineProps<{
    settings: LandingSettings;
    previewUrl: string;
    heroFiles: Array<{ id: number; url: string }>;
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const previewNonce = ref(Date.now());

const form = useForm<{
    settings: LandingSettings;
    hero_temp_folders: string[];
    hero_removed_files: number[];
}>({
    settings: JSON.parse(JSON.stringify(props.settings)),
    hero_temp_folders: [] as string[],
    hero_removed_files: [] as number[],
});

const fileUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const heroTempFolders = ref<string[]>([]);
const heroRemovedFileIds = ref<number[]>([]);
const heroSourceMode = ref<'upload' | 'url'>(props.heroFiles?.length ? 'upload' : 'url');
const previewSrc = computed(() => {
    const separator = props.previewUrl.includes('?') ? '&' : '?';
    return `${props.previewUrl}${separator}preview=${previewNonce.value}`;
});
const uploadedHeroUrl = computed(() => props.heroFiles?.[0]?.url || null);
const previewHeroUrl = computed(() => (
    heroSourceMode.value === 'url'
        ? form.settings.hero.image_url || null
        : uploadedHeroUrl.value || form.settings.hero.image_url || null
));
const heroIsVideo = computed(() => isVideoUrl(previewHeroUrl.value));

watch(
    heroTempFolders,
    (value) => {
        form.hero_temp_folders = [...value];
    },
    { deep: true },
);

watch(heroSourceMode, (value) => {
    if (value !== 'url') {
        return;
    }

    const existingIds = (props.heroFiles || []).map((file) => file.id).filter(Boolean);
    heroRemovedFileIds.value = [...new Set([...heroRemovedFileIds.value, ...existingIds])];
    form.hero_removed_files = [...heroRemovedFileIds.value];
});

function isVideoUrl(url: string | null): boolean {
    if (!url) {
        return false;
    }

    return /\.(mp4|webm|ogg|mov)(?:$|[?#])/i.test(url);
}

const handleHeroFileRemoved = (data: { type: string; fileId?: number }) => {
    if (data.type === 'existing' && data.fileId) {
        heroRemovedFileIds.value.push(data.fileId);
        form.hero_removed_files = [...new Set(heroRemovedFileIds.value)];
    }
};

const refreshPreview = () => {
    previewNonce.value = Date.now();
};

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            _method: 'put',
        }))
        .post('/superadmin/settings/design', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                heroTempFolders.value = [];
                form.hero_temp_folders = [];
                form.hero_removed_files = [];
                heroRemovedFileIds.value = [];
                fileUploadRef.value?.resetFiles();
                refreshPreview();
            },
        });
};

const openPreview = () => {
    window.open(previewSrc.value, '_blank', 'noopener,noreferrer');
};

const addHeroFeature = () => form.settings.hero.features.push('');
const removeHeroFeature = (index: number) => form.settings.hero.features.splice(index, 1);

const addFeatureCard = () => {
    form.settings.features_section.cards.push({
        title: '',
        image_url: '',
        content: '',
    });
};
const removeFeatureCard = (index: number) => form.settings.features_section.cards.splice(index, 1);

const addStepItem = () => {
    form.settings.getting_started.items.push({
        title: '',
        description: '',
    });
};
const removeStepItem = (index: number) => form.settings.getting_started.items.splice(index, 1);

const addFaqItem = () => {
    form.settings.faq_section.items.push({
        question: '',
        answer: '',
    });
};
const removeFaqItem = (index: number) => form.settings.faq_section.items.splice(index, 1);

const addQuickLink = () => {
    form.settings.contact_section.quick_links.push({
        label: '',
        href: '#',
    });
};
const removeQuickLink = (index: number) => form.settings.contact_section.quick_links.splice(index, 1);
</script>

<template>
    <Head :title="localize('Design Settings', 'إعدادات التصميم')" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Design Settings', 'إعدادات التصميم') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Edit the public landing page and preview the design from inside super admin.', 'عدّل صفحة الهبوط العامة واعرض المعاينة من داخل لوحة السوبر أدمن.') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button type="button" variant="outline" @click="refreshPreview">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        {{ localize('Refresh Preview', 'تحديث المعاينة') }}
                    </Button>
                    <Button type="button" variant="outline" @click="openPreview">
                        <ExternalLink class="mr-2 h-4 w-4" />
                        {{ localize('Open Full Preview', 'فتح المعاينة الكاملة') }}
                    </Button>
                    <Button :disabled="form.processing" @click="submit">
                        {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Design', 'حفظ التصميم') }}
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.05fr)_minmax(420px,0.95fr)]">
                <form class="space-y-6" @submit.prevent="submit">
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Hero', 'القسم الرئيسي') }}</CardTitle>
                            <CardDescription>{{ localize('Main title, description, image, and quick highlights.', 'العنوان الرئيسي والوصف والصورة وأبرز النقاط السريعة.') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="hero_title">{{ localize('Title', 'العنوان') }}</Label>
                                <Input id="hero_title" v-model="form.settings.hero.title" />
                                <p v-if="form.errors['settings.hero.title']" class="text-sm text-red-600">
                                    {{ form.errors['settings.hero.title'] }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="hero_description">{{ localize('Description', 'الوصف') }}</Label>
                                <Textarea id="hero_description" v-model="form.settings.hero.description" rows="4" />
                                <p v-if="form.errors['settings.hero.description']" class="text-sm text-red-600">
                                    {{ form.errors['settings.hero.description'] }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label>{{ localize('Hero Media Source', 'مصدر وسائط القسم الرئيسي') }}</Label>
                                <Select v-model="heroSourceMode">
                                    <SelectTrigger>
                                        <SelectValue :placeholder="localize('Select source', 'اختر المصدر')" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="upload">{{ localize('Upload image or video', 'رفع صورة أو فيديو') }}</SelectItem>
                                        <SelectItem value="url">{{ localize('External URL', 'رابط خارجي') }}</SelectItem>
                                    </SelectContent>
                                </Select>

                                <div v-if="heroSourceMode === 'upload'" class="space-y-2">
                                    <FileUpload
                                        ref="fileUploadRef"
                                        v-model="heroTempFolders"
                                        :initial-files="heroFiles || []"
                                        :allow-multiple="false"
                                        :max-files="1"
                                        :max-file-size="1024 * 1024 * 50"
                                        :allowed-file-types="['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime']"
                                        collection="hero"
                                        theme="light"
                                        width="100%"
                                        @file-removed="handleHeroFileRemoved"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        {{ localize('Upload one image or video for the hero section. A new upload replaces the previous file.', 'ارفع صورة أو فيديو واحد للقسم الرئيسي. أي رفع جديد سيستبدل الملف السابق.') }}
                                    </p>
                                </div>

                                <div v-else class="space-y-2">
                                    <Input id="hero_image_url" v-model="form.settings.hero.image_url" placeholder="https://..." />
                                </div>

                                <div v-if="previewHeroUrl" class="overflow-hidden rounded-lg border bg-muted/20">
                                    <video
                                        v-if="heroIsVideo"
                                        :src="previewHeroUrl"
                                        class="h-44 w-full object-cover"
                                        controls
                                        muted
                                        playsinline
                                    />
                                    <img
                                        v-else
                                        :src="previewHeroUrl"
                                        alt="hero preview"
                                        class="h-44 w-full object-cover"
                                    />
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>{{ localize('Hero Features', 'مزايا القسم الرئيسي') }}</Label>
                                    <Button type="button" variant="outline" size="sm" @click="addHeroFeature">{{ localize('Add Feature', 'إضافة ميزة') }}</Button>
                                </div>
                                <div
                                    v-for="(_item, index) in form.settings.hero.features"
                                    :key="`hero-feature-${index}`"
                                    class="flex items-center gap-2"
                                >
                                    <Input v-model="form.settings.hero.features[index]" :placeholder="localize('Feature text', 'نص الميزة')" />
                                    <Button type="button" variant="destructive" size="sm" @click="removeHeroFeature(index)">{{ localize('Remove', 'حذف') }}</Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Features Section', 'قسم المزايا') }}</CardTitle>
                            <CardDescription>{{ localize('Section intro and the feature cards shown on the landing page.', 'مقدمة القسم وبطاقات المزايا المعروضة في صفحة الهبوط.') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="features_title">{{ localize('Title', 'العنوان') }}</Label>
                                <Input id="features_title" v-model="form.settings.features_section.title" />
                            </div>

                            <div class="space-y-2">
                                <Label for="features_description">{{ localize('Description', 'الوصف') }}</Label>
                                <Textarea id="features_description" v-model="form.settings.features_section.description" rows="3" />
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>{{ localize('Feature Cards', 'بطاقات المزايا') }}</Label>
                                    <Button type="button" variant="outline" size="sm" @click="addFeatureCard">{{ localize('Add Card', 'إضافة بطاقة') }}</Button>
                                </div>
                                <div
                                    v-for="(card, index) in form.settings.features_section.cards"
                                    :key="`feature-card-${index}`"
                                    class="space-y-3 rounded-lg border p-4"
                                >
                                    <div class="space-y-2">
                                        <Label>{{ localize('Card Title', 'عنوان البطاقة') }}</Label>
                                        <Input v-model="card.title" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ localize('Image URL', 'رابط الصورة') }}</Label>
                                        <Input v-model="card.image_url" placeholder="https://..." />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ localize('Content', 'المحتوى') }}</Label>
                                        <Textarea v-model="card.content" rows="3" />
                                    </div>
                                    <div class="flex justify-end">
                                        <Button type="button" variant="destructive" size="sm" @click="removeFeatureCard(index)">{{ localize('Remove Card', 'حذف البطاقة') }}</Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Getting Started', 'البدء') }}</CardTitle>
                            <CardDescription>{{ localize('Control the section that explains the setup steps.', 'تحكم في القسم الذي يشرح خطوات الإعداد.') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label>{{ localize('Section Title', 'عنوان القسم') }}</Label>
                                <Input v-model="form.settings.getting_started.title" />
                            </div>
                            <div class="space-y-2">
                                <Label>{{ localize('Section Description', 'وصف القسم') }}</Label>
                                <Textarea v-model="form.settings.getting_started.description" rows="3" />
                            </div>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>{{ localize('Steps', 'الخطوات') }}</Label>
                                    <Button type="button" variant="outline" size="sm" @click="addStepItem">{{ localize('Add Step', 'إضافة خطوة') }}</Button>
                                </div>
                                <div
                                    v-for="(item, index) in form.settings.getting_started.items"
                                    :key="`step-${index}`"
                                    class="space-y-3 rounded-lg border p-4"
                                >
                                    <div class="space-y-2">
                                        <Label>{{ localize('Step Title', 'عنوان الخطوة') }}</Label>
                                        <Input v-model="item.title" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ localize('Step Description', 'وصف الخطوة') }}</Label>
                                        <Textarea v-model="item.description" rows="2" />
                                    </div>
                                    <div class="flex justify-end">
                                        <Button type="button" variant="destructive" size="sm" @click="removeStepItem(index)">{{ localize('Remove Step', 'حذف الخطوة') }}</Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Plans & FAQ', 'الخطط والأسئلة الشائعة') }}</CardTitle>
                            <CardDescription>{{ localize('Pricing section heading plus FAQ content.', 'عنوان قسم التسعير مع محتوى الأسئلة الشائعة.') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>{{ localize('Plans Title', 'عنوان الخطط') }}</Label>
                                    <Input v-model="form.settings.plans_section.title" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Plans Description', 'وصف الخطط') }}</Label>
                                    <Textarea v-model="form.settings.plans_section.description" rows="2" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label>{{ localize('FAQ Title', 'عنوان الأسئلة الشائعة') }}</Label>
                                <Input v-model="form.settings.faq_section.title" />
                            </div>
                            <div class="space-y-2">
                                <Label>{{ localize('FAQ Description', 'وصف الأسئلة الشائعة') }}</Label>
                                <Textarea v-model="form.settings.faq_section.description" rows="2" />
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>{{ localize('FAQ Items', 'عناصر الأسئلة الشائعة') }}</Label>
                                    <Button type="button" variant="outline" size="sm" @click="addFaqItem">{{ localize('Add FAQ', 'إضافة سؤال') }}</Button>
                                </div>
                                <div
                                    v-for="(faq, index) in form.settings.faq_section.items"
                                    :key="`faq-${index}`"
                                    class="space-y-3 rounded-lg border p-4"
                                >
                                    <div class="space-y-2">
                                        <Label>{{ localize('Question', 'السؤال') }}</Label>
                                        <Input v-model="faq.question" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ localize('Answer', 'الإجابة') }}</Label>
                                        <Textarea v-model="faq.answer" rows="3" />
                                    </div>
                                    <div class="flex justify-end">
                                        <Button type="button" variant="destructive" size="sm" @click="removeFaqItem(index)">{{ localize('Remove FAQ', 'حذف السؤال') }}</Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Contact Section', 'قسم التواصل') }}</CardTitle>
                            <CardDescription>{{ localize('Edit the English source text for the contact form, direct contact card, and quick links.', 'عدّل النص الإنجليزي الأساسي لنموذج التواصل وبطاقة التواصل المباشر والروابط السريعة.') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>{{ localize('Section Title', 'عنوان القسم') }}</Label>
                                    <Input v-model="form.settings.contact_section.title" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Form Card Title', 'عنوان بطاقة النموذج') }}</Label>
                                    <Input v-model="form.settings.contact_section.form_title" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label>{{ localize('Section Description', 'وصف القسم') }}</Label>
                                <Textarea v-model="form.settings.contact_section.description" rows="2" />
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>{{ localize('Name Label', 'عنوان حقل الاسم') }}</Label>
                                    <Input v-model="form.settings.contact_section.name_label" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Name Placeholder', 'تلميح حقل الاسم') }}</Label>
                                    <Input v-model="form.settings.contact_section.name_placeholder" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Email Label', 'عنوان حقل البريد') }}</Label>
                                    <Input v-model="form.settings.contact_section.email_label" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Email Placeholder', 'تلميح حقل البريد') }}</Label>
                                    <Input v-model="form.settings.contact_section.email_placeholder" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Subject Label', 'عنوان حقل الموضوع') }}</Label>
                                    <Input v-model="form.settings.contact_section.subject_label" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Subject Placeholder', 'تلميح حقل الموضوع') }}</Label>
                                    <Input v-model="form.settings.contact_section.subject_placeholder" />
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>{{ localize('Message Label', 'عنوان حقل الرسالة') }}</Label>
                                    <Input v-model="form.settings.contact_section.message_label" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Message Placeholder', 'تلميح حقل الرسالة') }}</Label>
                                    <Textarea v-model="form.settings.contact_section.message_placeholder" rows="2" />
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>{{ localize('Submit Label', 'نص زر الإرسال') }}</Label>
                                    <Input v-model="form.settings.contact_section.submit_label" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Sending Label', 'نص حالة الإرسال') }}</Label>
                                    <Input v-model="form.settings.contact_section.sending_label" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Success Message', 'رسالة النجاح') }}</Label>
                                    <Textarea v-model="form.settings.contact_section.success_message" rows="2" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Error Message', 'رسالة الخطأ') }}</Label>
                                    <Textarea v-model="form.settings.contact_section.error_message" rows="2" />
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>{{ localize('Direct Contact Title', 'عنوان التواصل المباشر') }}</Label>
                                    <Input v-model="form.settings.contact_section.direct_title" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Quick Links Title', 'عنوان الروابط السريعة') }}</Label>
                                    <Input v-model="form.settings.contact_section.quick_links_title" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Email Label', 'عنوان البريد') }}</Label>
                                    <Input v-model="form.settings.contact_section.direct_email_label" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Email Address', 'البريد') }}</Label>
                                    <Input v-model="form.settings.contact_section.direct_email" type="email" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Phone Label', 'عنوان الهاتف') }}</Label>
                                    <Input v-model="form.settings.contact_section.direct_phone_label" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Phone Number', 'رقم الهاتف') }}</Label>
                                    <Input v-model="form.settings.contact_section.direct_phone" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Response Time Label', 'عنوان زمن الاستجابة') }}</Label>
                                    <Input v-model="form.settings.contact_section.response_time_label" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Response Time', 'زمن الاستجابة') }}</Label>
                                    <Input v-model="form.settings.contact_section.response_time" />
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>{{ localize('Quick Links', 'الروابط السريعة') }}</Label>
                                    <Button type="button" variant="outline" size="sm" @click="addQuickLink">{{ localize('Add Link', 'إضافة رابط') }}</Button>
                                </div>
                                <div
                                    v-for="(link, index) in form.settings.contact_section.quick_links"
                                    :key="`contact-link-${index}`"
                                    class="grid gap-3 rounded-lg border p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
                                >
                                    <div class="space-y-2">
                                        <Label>{{ localize('Label', 'النص') }}</Label>
                                        <Input v-model="link.label" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ localize('Href', 'الرابط') }}</Label>
                                        <Input v-model="link.href" placeholder="#cars" />
                                    </div>
                                    <div class="flex items-end">
                                        <Button type="button" variant="destructive" size="sm" @click="removeQuickLink(index)">{{ localize('Remove', 'حذف') }}</Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Footer', 'التذييل') }}</CardTitle>
                            <CardDescription>{{ localize('Final call to action shown at the bottom of the landing page.', 'الدعوة النهائية لاتخاذ الإجراء في أسفل صفحة الهبوط.') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label>{{ localize('Footer Title', 'عنوان التذييل') }}</Label>
                                <Input v-model="form.settings.footer.title" />
                            </div>
                            <div class="space-y-2">
                                <Label>{{ localize('Footer Description', 'وصف التذييل') }}</Label>
                                <Textarea v-model="form.settings.footer.description" rows="3" />
                            </div>
                        </CardContent>
                    </Card>
                </form>

                <div class="space-y-4">
                    <Card class="sticky top-6 overflow-hidden">
                        <CardHeader class="border-b">
                            <CardTitle>{{ localize('Live Preview', 'معاينة مباشرة') }}</CardTitle>
                            <CardDescription>
                                {{ localize('The preview renders the public landing page on the main domain. Save changes to refresh it.', 'تعرض المعاينة صفحة الهبوط العامة على الدومين الرئيسي. احفظ التغييرات لتحديثها.') }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-0">
                            <iframe
                                :key="previewSrc"
                                :src="previewSrc"
                                class="h-[calc(100vh-16rem)] min-h-[720px] w-full bg-white"
                                :title="localize('Landing page preview', 'معاينة صفحة الهبوط')"
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </main>
    </SuperAdminLayout>
</template>
