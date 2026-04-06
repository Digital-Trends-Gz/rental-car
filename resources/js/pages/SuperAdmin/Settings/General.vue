<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ref } from 'vue';

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
    footer: {
        title: string;
        description: string;
    };
}

interface AiProviderSettings {
    provider: 'openai' | 'google_document_ai';
    openai: {
        api_key: string;
        organization: string;
        project: string;
        base_uri: string;
        model: string;
        temperature: number;
        max_output_tokens: number;
        system_prompt: string;
    };
    google_document_ai: {
        enabled: boolean;
        project_id: string;
        location: string;
        processor_id: string;
        service_account_json: string;
    };
    meta?: {
        has_openai_api_key?: boolean;
        has_google_credentials?: boolean;
    };
}

interface SocialLoginSettings {
    google: {
        enabled: boolean;
        client_id: string;
        client_secret: string;
    };
    apple: {
        enabled: boolean;
        client_id: string;
        client_secret: string;
    };
}

const props = defineProps<{
    settings: LandingSettings;
    aiSettings: {
        enabled: boolean;
        contracts_extraction_enabled: boolean;
    };
    aiProviderSettings: AiProviderSettings;
    socialLoginSettings: SocialLoginSettings;
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const form = useForm<{
    settings: LandingSettings;
    ai: {
        enabled: boolean;
        contracts_extraction_enabled: boolean;
    };
    ai_provider: AiProviderSettings;
    social_login: SocialLoginSettings;
}>({
    settings: JSON.parse(JSON.stringify(props.settings)),
    ai: {
        enabled: !!props.aiSettings?.enabled,
        contracts_extraction_enabled: !!props.aiSettings?.contracts_extraction_enabled,
    },
    ai_provider: JSON.parse(JSON.stringify(props.aiProviderSettings || {})),
    social_login: JSON.parse(JSON.stringify(props.socialLoginSettings || {
        google: { enabled: false, client_id: '', client_secret: '' },
        apple: { enabled: false, client_id: '', client_secret: '' },
    })),
});

const addHeroFeature = () => {
    form.settings.hero.features.push('');
};

const removeHeroFeature = (index: number) => {
    form.settings.hero.features.splice(index, 1);
};

const addFeatureCard = () => {
    form.settings.features_section.cards.push({
        title: '',
        image_url: '',
        content: '',
    });
};

const removeFeatureCard = (index: number) => {
    form.settings.features_section.cards.splice(index, 1);
};

const addStepItem = () => {
    form.settings.getting_started.items.push({
        title: '',
        description: '',
    });
};

const removeStepItem = (index: number) => {
    form.settings.getting_started.items.splice(index, 1);
};

const addFaqItem = () => {
    form.settings.faq_section.items.push({
        question: '',
        answer: '',
    });
};

const removeFaqItem = (index: number) => {
    form.settings.faq_section.items.splice(index, 1);
};

const submit = () => {
    const updateUrl = typeof window !== 'undefined' ? window.location.pathname : '/superadmin/settings/general';

    form.put(updateUrl, {
        preserveScroll: true,
    });
};

const testingAiConnection = ref(false);
const aiConnectionTestState = ref<'idle' | 'success' | 'error'>('idle');
const aiConnectionTestMessage = ref('');

function extractFirstValidationError(errors: unknown): string | null {
    if (!errors || typeof errors !== 'object') {
        return null;
    }

    const values = Object.values(errors as Record<string, unknown>);
    for (const value of values) {
        if (Array.isArray(value) && typeof value[0] === 'string') {
            return value[0];
        }
    }

    return null;
}

async function testAiConnection() {
    if (testingAiConnection.value || typeof window === 'undefined') return;

    testingAiConnection.value = true;
    aiConnectionTestState.value = 'idle';
    aiConnectionTestMessage.value = '';

    const csrfToken = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content');
    const xsrfToken = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    const basePath = window.location.pathname.replace(/\/$/, '');
    const testUrl = `${basePath}/test-ai-connection`;

    try {
        const response = await fetch(testUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                ai_provider: form.ai_provider,
            }),
        });

        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload?.ok) {
            const firstValidationError = extractFirstValidationError(payload?.errors);
            aiConnectionTestState.value = 'error';
            aiConnectionTestMessage.value = firstValidationError ?? payload?.message ?? localize('AI connection test failed.', 'فشل اختبار اتصال الذكاء الاصطناعي.');
            return;
        }

        aiConnectionTestState.value = 'success';
        aiConnectionTestMessage.value = payload?.message ?? localize('AI connection is valid.', 'اتصال الذكاء الاصطناعي صالح.');
    } catch {
        aiConnectionTestState.value = 'error';
        aiConnectionTestMessage.value = localize('Could not test AI connection. Please try again.', 'تعذر اختبار اتصال الذكاء الاصطناعي. حاول مرة أخرى.');
    } finally {
        testingAiConnection.value = false;
    }
}
</script>

<template>
    <Head :title="localize('Landing Settings', 'إعدادات صفحة الهبوط')" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Landing Page Settings', 'إعدادات صفحة الهبوط') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Edit SaaS landing sections shown on the main domain.', 'عدّل أقسام صفحة هبوط المنصة المعروضة على الدومين الرئيسي.') }}
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                </Button>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('AI Automation', 'الأتمتة بالذكاء الاصطناعي') }}</CardTitle>
                        <CardDescription>
                            {{ localize('Super Admin controls whether AI extraction is active for contract files. When disabled, the system only stores uploaded files.', 'يتحكم السوبر أدمن في تفعيل استخراج البيانات بالذكاء الاصطناعي لملفات العقود. عند التعطيل، سيحفظ النظام الملفات المرفوعة فقط.') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <label class="flex items-center gap-3">
                            <input v-model="form.ai.enabled" type="checkbox" class="h-4 w-4" />
                            <span class="text-sm font-medium">{{ localize('Enable AI globally', 'تفعيل الذكاء الاصطناعي على مستوى النظام') }}</span>
                        </label>

                        <label class="flex items-center gap-3">
                            <input
                                v-model="form.ai.contracts_extraction_enabled"
                                type="checkbox"
                                class="h-4 w-4"
                                :disabled="!form.ai.enabled"
                            />
                            <span class="text-sm font-medium">{{ localize('Enable contract extraction AI', 'تفعيل استخراج العقود بالذكاء الاصطناعي') }}</span>
                        </label>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('AI Provider Settings', 'إعدادات مزود الذكاء الاصطناعي') }}</CardTitle>
                        <CardDescription>
                            {{ localize('Configure provider credentials and extraction behavior. Empty secret fields keep existing saved values.', 'اضبط بيانات اعتماد المزود وسلوك الاستخراج. ترك الحقول السرية فارغة سيبقي القيم الحالية.') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Test provider credentials before saving.', 'اختبر بيانات اعتماد المزود قبل الحفظ.') }}
                            </p>
                            <Button type="button" variant="outline" :disabled="testingAiConnection" @click="testAiConnection">
                                {{ testingAiConnection ? localize('Testing...', 'جارٍ الاختبار...') : localize('Test AI Connection', 'اختبار اتصال الذكاء الاصطناعي') }}
                            </Button>
                        </div>

                        <div
                            v-if="aiConnectionTestMessage"
                            class="rounded-md border p-3 text-sm"
                            :class="aiConnectionTestState === 'success'
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                : 'border-red-200 bg-red-50 text-red-700'"
                        >
                            {{ aiConnectionTestMessage }}
                        </div>

                        <div class="space-y-2">
                            <Label for="ai_provider">{{ localize('Provider', 'المزود') }}</Label>
                            <select
                                id="ai_provider"
                                v-model="form.ai_provider.provider"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                            >
                                <option value="openai">OpenAI</option>
                                <option value="google_document_ai">{{ localize('Google Document AI', 'Google Document AI') }}</option>
                            </select>
                            <p v-if="form.errors['ai_provider.provider']" class="text-sm text-red-600">
                                {{ form.errors['ai_provider.provider'] }}
                            </p>
                        </div>

                        <div class="space-y-4 rounded-md border p-4">
                            <h3 class="text-sm font-semibold">OpenAI</h3>

                            <div class="space-y-2">
                                <Label for="openai_api_key">{{ localize('API Key', 'مفتاح API') }}</Label>
                                <Input id="openai_api_key" v-model="form.ai_provider.openai.api_key" type="password" placeholder="sk-..." />
                                <p v-if="props.aiProviderSettings.meta?.has_openai_api_key" class="text-xs text-muted-foreground">
                                    {{ localize('A key is already saved. Leave blank to keep it.', 'يوجد مفتاح محفوظ بالفعل. اترك الحقل فارغًا للاحتفاظ به.') }}
                                </p>
                                <p v-if="form.errors['ai_provider.openai.api_key']" class="text-sm text-red-600">
                                    {{ form.errors['ai_provider.openai.api_key'] }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="openai_organization">{{ localize('Organization (optional)', 'المؤسسة (اختياري)') }}</Label>
                                    <Input id="openai_organization" v-model="form.ai_provider.openai.organization" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="openai_project">{{ localize('Project (optional)', 'المشروع (اختياري)') }}</Label>
                                    <Input id="openai_project" v-model="form.ai_provider.openai.project" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="openai_model">{{ localize('Model', 'النموذج') }}</Label>
                                    <Input id="openai_model" v-model="form.ai_provider.openai.model" placeholder="gpt-4.1-mini" />
                                    <p v-if="form.errors['ai_provider.openai.model']" class="text-sm text-red-600">
                                        {{ form.errors['ai_provider.openai.model'] }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="openai_base_uri">{{ localize('Base URL (optional)', 'الرابط الأساسي (اختياري)') }}</Label>
                                    <Input id="openai_base_uri" v-model="form.ai_provider.openai.base_uri" placeholder="https://api.openai.com/v1" />
                                    <p v-if="form.errors['ai_provider.openai.base_uri']" class="text-sm text-red-600">
                                        {{ form.errors['ai_provider.openai.base_uri'] }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="openai_temperature">{{ localize('Temperature (0-2)', 'الحرارة (0-2)') }}</Label>
                                    <Input id="openai_temperature" v-model="form.ai_provider.openai.temperature" type="number" min="0" max="2" step="0.1" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="openai_tokens">{{ localize('Max Output Tokens', 'الحد الأقصى للرموز الناتجة') }}</Label>
                                    <Input id="openai_tokens" v-model="form.ai_provider.openai.max_output_tokens" type="number" min="1" max="16384" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="openai_system_prompt">{{ localize('System Prompt (optional)', 'رسالة النظام (اختياري)') }}</Label>
                                <Textarea
                                    id="openai_system_prompt"
                                    v-model="form.ai_provider.openai.system_prompt"
                                    rows="4"
                                    :placeholder="localize('Extract key fields from Arabic and English rental contract files as JSON.', 'استخرج الحقول الأساسية من ملفات عقود التأجير العربية والإنجليزية بصيغة JSON.')"
                                />
                            </div>
                        </div>

                        <div class="space-y-4 rounded-md border p-4">
                            <h3 class="text-sm font-semibold">{{ localize('Google Document AI', 'Google Document AI') }}</h3>

                            <label class="flex items-center gap-3">
                                <input v-model="form.ai_provider.google_document_ai.enabled" type="checkbox" class="h-4 w-4" />
                                <span class="text-sm font-medium">{{ localize('Enable Google Document AI OCR', 'تفعيل OCR من Google Document AI') }}</span>
                            </label>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="space-y-2">
                                    <Label for="gdoc_project_id">{{ localize('Project ID', 'معرف المشروع') }}</Label>
                                    <Input id="gdoc_project_id" v-model="form.ai_provider.google_document_ai.project_id" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="gdoc_location">{{ localize('Location', 'المنطقة') }}</Label>
                                    <Input id="gdoc_location" v-model="form.ai_provider.google_document_ai.location" placeholder="us" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="gdoc_processor_id">{{ localize('Processor ID', 'معرف المعالج') }}</Label>
                                    <Input id="gdoc_processor_id" v-model="form.ai_provider.google_document_ai.processor_id" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="gdoc_credentials_json">{{ localize('Service Account JSON', 'JSON حساب الخدمة') }}</Label>
                                <Textarea
                                    id="gdoc_credentials_json"
                                    v-model="form.ai_provider.google_document_ai.service_account_json"
                                    rows="5"
                                    placeholder='{"type":"service_account","project_id":"..."}'
                                />
                                <p v-if="props.aiProviderSettings.meta?.has_google_credentials" class="text-xs text-muted-foreground">
                                    {{ localize('Credentials are already saved. Leave blank to keep them.', 'بيانات الاعتماد محفوظة بالفعل. اترك الحقل فارغًا للاحتفاظ بها.') }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Hero Section', 'قسم البطل') }}</CardTitle>
                        <CardDescription>{{ localize('Title, description, hero features, and image URL.', 'العنوان والوصف ومزايا القسم والرابط الخاص بالصورة.') }}</CardDescription>
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
                            <Textarea id="hero_description" v-model="form.settings.hero.description" rows="3" />
                            <p v-if="form.errors['settings.hero.description']" class="text-sm text-red-600">
                                {{ form.errors['settings.hero.description'] }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="hero_image_url">{{ localize('Image URL', 'رابط الصورة') }}</Label>
                            <Input id="hero_image_url" v-model="form.settings.hero.image_url" placeholder="https://..." />
                            <p v-if="form.errors['settings.hero.image_url']" class="text-sm text-red-600">
                                {{ form.errors['settings.hero.image_url'] }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label>{{ localize('Hero Features', 'مزايا القسم الرئيسي') }}</Label>
                                <Button type="button" variant="outline" size="sm" @click="addHeroFeature">{{ localize('Add Feature', 'إضافة ميزة') }}</Button>
                            </div>
                            <div v-for="(_item, index) in form.settings.hero.features" :key="`hero-feature-${index}`" class="flex items-center gap-2">
                                <Input v-model="form.settings.hero.features[index]" :placeholder="localize('Feature text', 'نص الميزة')" />
                                <Button type="button" variant="destructive" size="sm" @click="removeHeroFeature(index)">{{ localize('Remove', 'حذف') }}</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Features Section', 'قسم المزايا') }}</CardTitle>
                        <CardDescription>{{ localize('Section title/description and feature cards.', 'عنوان ووصف القسم وبطاقات المزايا.') }}</CardDescription>
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
                                class="space-y-2 rounded-md border p-3"
                            >
                                <Input v-model="card.title" :placeholder="localize('Card title', 'عنوان البطاقة')" />
                                <Input v-model="card.image_url" :placeholder="localize('Image URL', 'رابط الصورة')" />
                                <Textarea v-model="card.content" rows="2" :placeholder="localize('Card content', 'محتوى البطاقة')" />
                                <Button type="button" variant="destructive" size="sm" @click="removeFeatureCard(index)">{{ localize('Remove Card', 'حذف البطاقة') }}</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Start in Minutes Section', 'قسم ابدأ خلال دقائق') }}</CardTitle>
                        <CardDescription>{{ localize('Section title/description and quick start features.', 'عنوان ووصف القسم وخطوات البدء السريع.') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="start_title">{{ localize('Title', 'العنوان') }}</Label>
                            <Input id="start_title" v-model="form.settings.getting_started.title" />
                        </div>

                        <div class="space-y-2">
                            <Label for="start_description">{{ localize('Description', 'الوصف') }}</Label>
                            <Textarea id="start_description" v-model="form.settings.getting_started.description" rows="3" />
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <Label>{{ localize('Items', 'العناصر') }}</Label>
                                <Button type="button" variant="outline" size="sm" @click="addStepItem">{{ localize('Add Item', 'إضافة عنصر') }}</Button>
                            </div>

                            <div
                                v-for="(item, index) in form.settings.getting_started.items"
                                :key="`step-item-${index}`"
                                class="space-y-2 rounded-md border p-3"
                            >
                                <Input v-model="item.title" :placeholder="localize('Item title', 'عنوان العنصر')" />
                                <Textarea v-model="item.description" rows="2" :placeholder="localize('Item description', 'وصف العنصر')" />
                                <Button type="button" variant="destructive" size="sm" @click="removeStepItem(index)">{{ localize('Remove Item', 'حذف العنصر') }}</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Plans Section', 'قسم الخطط') }}</CardTitle>
                        <CardDescription>
                            {{ localize('Only heading and description are editable here. Plans are loaded from the plans table.', 'يمكن تعديل العنوان والوصف فقط هنا. أما الخطط فيتم تحميلها من جدول الخطط.') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="plans_title">{{ localize('Title', 'العنوان') }}</Label>
                            <Input id="plans_title" v-model="form.settings.plans_section.title" />
                        </div>

                        <div class="space-y-2">
                            <Label for="plans_description">{{ localize('Description', 'الوصف') }}</Label>
                            <Textarea id="plans_description" v-model="form.settings.plans_section.description" rows="3" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('FAQ Section', 'قسم الأسئلة الشائعة') }}</CardTitle>
                        <CardDescription>{{ localize('Manage questions and answers.', 'إدارة الأسئلة والأجوبة.') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="faq_title">{{ localize('Title', 'العنوان') }}</Label>
                            <Input id="faq_title" v-model="form.settings.faq_section.title" />
                        </div>

                        <div class="space-y-2">
                            <Label for="faq_description">{{ localize('Description', 'الوصف') }}</Label>
                            <Textarea id="faq_description" v-model="form.settings.faq_section.description" rows="3" />
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <Label>{{ localize('FAQ Items', 'عناصر الأسئلة الشائعة') }}</Label>
                                <Button type="button" variant="outline" size="sm" @click="addFaqItem">{{ localize('Add FAQ', 'إضافة سؤال') }}</Button>
                            </div>

                            <div
                                v-for="(item, index) in form.settings.faq_section.items"
                                :key="`faq-item-${index}`"
                                class="space-y-2 rounded-md border p-3"
                            >
                                <Input v-model="item.question" :placeholder="localize('Question', 'السؤال')" />
                                <Textarea v-model="item.answer" rows="3" :placeholder="localize('Answer', 'الإجابة')" />
                                <Button type="button" variant="destructive" size="sm" @click="removeFaqItem(index)">{{ localize('Remove FAQ', 'حذف السؤال') }}</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Footer Section', 'قسم التذييل') }}</CardTitle>
                        <CardDescription>{{ localize('Footer title and description text.', 'عنوان ووصف التذييل.') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="footer_title">{{ localize('Title', 'العنوان') }}</Label>
                            <Input id="footer_title" v-model="form.settings.footer.title" />
                        </div>

                        <div class="space-y-2">
                            <Label for="footer_description">{{ localize('Description', 'الوصف') }}</Label>
                            <Textarea id="footer_description" v-model="form.settings.footer.description" rows="3" />
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                    </Button>
                </div>
            </form>
        </main>
    </SuperAdminLayout>
</template>
