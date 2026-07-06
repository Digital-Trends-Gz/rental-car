<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ref } from 'vue';
import { testAiConnection as testAiConnectionRoute, testMailConnection as testMailConnectionRoute } from '@/routes/superadmin/settings/general';

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
    document_extraction_daily_limit: number | null;
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
const page = usePage();

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

const submit = () => {
    const updateUrl = typeof window !== 'undefined' ? window.location.pathname : '/superadmin/settings/general';

    form.transform((data) => ({
        ...data,
        _method: 'put',
    })).post(updateUrl, {
        preserveScroll: true,
        forceFormData: true,
    });
};

const testingAiConnection = ref(false);
const aiConnectionTestState = ref<'idle' | 'success' | 'error'>('idle');
const aiConnectionTestMessage = ref('');
const testingMailConnection = ref(false);
const mailConnectionTestState = ref<'idle' | 'success' | 'error'>('idle');
const mailConnectionTestMessage = ref('');

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

    const csrfToken = String(page.props.csrf_token ?? '');
    const xsrfToken = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    try {
        const response = await fetch(testAiConnectionRoute.url(), {
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
            aiConnectionTestMessage.value = firstValidationError ?? payload?.message ?? localize('AI connection test failed.', 'ظپط´ظ„ ط§ط®طھط¨ط§ط± ط§طھطµط§ظ„ ط§ظ„ط°ظƒط§ط، ط§ظ„ط§طµط·ظ†ط§ط¹ظٹ.');
            return;
        }

        aiConnectionTestState.value = 'success';
        aiConnectionTestMessage.value = payload?.message ?? localize('AI connection is valid.', 'ط§طھطµط§ظ„ ط§ظ„ط°ظƒط§ط، ط§ظ„ط§طµط·ظ†ط§ط¹ظٹ طµط§ظ„ط­.');
    } catch {
        aiConnectionTestState.value = 'error';
        aiConnectionTestMessage.value = localize('Could not test AI connection. Please try again.', 'طھط¹ط°ط± ط§ط®طھط¨ط§ط± ط§طھطµط§ظ„ ط§ظ„ط°ظƒط§ط، ط§ظ„ط§طµط·ظ†ط§ط¹ظٹ. ط­ط§ظˆظ„ ظ…ط±ط© ط£ط®ط±ظ‰.');
    } finally {
        testingAiConnection.value = false;
    }
}

async function testMailConnection() {
    if (testingMailConnection.value || typeof window === 'undefined') return;

    testingMailConnection.value = true;
    mailConnectionTestState.value = 'idle';
    mailConnectionTestMessage.value = '';

    const csrfToken = String(page.props.csrf_token ?? '');
    const xsrfToken = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    try {
        const response = await fetch(testMailConnectionRoute.url(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({}),
        });

        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload?.ok) {
            const firstValidationError = extractFirstValidationError(payload?.errors);
            mailConnectionTestState.value = 'error';
            mailConnectionTestMessage.value = firstValidationError ?? payload?.message ?? localize('Mail test failed.', 'ظپط´ظ„ ط§ط®طھط¨ط§ط± ط§ظ„ط¨ط±ظٹط¯ ط§ظ„ط¥ظ„ظƒطھط±ظˆظ†ظٹ.');
            return;
        }

        mailConnectionTestState.value = 'success';
        mailConnectionTestMessage.value = payload?.message ?? localize('Test email sent successfully.', 'طھظ… ط¥ط±ط³ط§ظ„ ط§ظ„ط¨ط±ظٹط¯ ط§ظ„طھط¬ط±ظٹط¨ظٹ ط¨ظ†ط¬ط§ط­.');
    } catch {
        mailConnectionTestState.value = 'error';
        mailConnectionTestMessage.value = localize('Could not test mail connection. Please try again.', 'طھط¹ط°ط± ط§ط®طھط¨ط§ط± ط§طھطµط§ظ„ ط§ظ„ط¨ط±ظٹط¯ ط§ظ„ط¥ظ„ظƒطھط±ظˆظ†ظٹ. ط­ط§ظˆظ„ ظ…ط±ط© ط£ط®ط±ظ‰.');
    } finally {
        testingMailConnection.value = false;
    }
}
</script>

<template>
    <Head :title="localize('Landing Settings', 'ط¥ط¹ط¯ط§ط¯ط§طھ طµظپط­ط© ط§ظ„ظ‡ط¨ظˆط·')" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Landing Page Settings', 'ط¥ط¹ط¯ط§ط¯ط§طھ طµظپط­ط© ط§ظ„ظ‡ط¨ظˆط·') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Edit SaaS landing sections shown on the main domain.', 'ط¹ط¯ظ‘ظ„ ط£ظ‚ط³ط§ظ… طµظپط­ط© ظ‡ط¨ظˆط· ط§ظ„ظ…ظ†طµط© ط§ظ„ظ…ط¹ط±ظˆط¶ط© ط¹ظ„ظ‰ ط§ظ„ط¯ظˆظ…ظٹظ† ط§ظ„ط±ط¦ظٹط³ظٹ.') }}
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'ط¬ط§ط±ظچ ط§ظ„ط­ظپط¸...') : localize('Save Changes', 'ط­ظپط¸ ط§ظ„طھط؛ظٹظٹط±ط§طھ') }}
                </Button>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('AI Automation', 'ط§ظ„ط£طھظ…طھط© ط¨ط§ظ„ط°ظƒط§ط، ط§ظ„ط§طµط·ظ†ط§ط¹ظٹ') }}</CardTitle>
                        <CardDescription>
                            {{ localize('Super Admin controls whether AI extraction is active for contract files. When disabled, the system only stores uploaded files.', 'ظٹطھط­ظƒظ… ط§ظ„ط³ظˆط¨ط± ط£ط¯ظ…ظ† ظپظٹ طھظپط¹ظٹظ„ ط§ط³طھط®ط±ط§ط¬ ط§ظ„ط¨ظٹط§ظ†ط§طھ ط¨ط§ظ„ط°ظƒط§ط، ط§ظ„ط§طµط·ظ†ط§ط¹ظٹ ظ„ظ…ظ„ظپط§طھ ط§ظ„ط¹ظ‚ظˆط¯. ط¹ظ†ط¯ ط§ظ„طھط¹ط·ظٹظ„طŒ ط³ظٹط­ظپط¸ ط§ظ„ظ†ط¸ط§ظ… ط§ظ„ظ…ظ„ظپط§طھ ط§ظ„ظ…ط±ظپظˆط¹ط© ظپظ‚ط·.') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <label class="flex items-center gap-3">
                            <input v-model="form.ai.enabled" type="checkbox" class="h-4 w-4" />
                            <span class="text-sm font-medium">{{ localize('Enable AI globally', 'طھظپط¹ظٹظ„ ط§ظ„ط°ظƒط§ط، ط§ظ„ط§طµط·ظ†ط§ط¹ظٹ ط¹ظ„ظ‰ ظ…ط³طھظˆظ‰ ط§ظ„ظ†ط¸ط§ظ…') }}</span>
                        </label>

                        <label class="flex items-center gap-3">
                            <input
                                v-model="form.ai.contracts_extraction_enabled"
                                type="checkbox"
                                class="h-4 w-4"
                                :disabled="!form.ai.enabled"
                            />
                            <span class="text-sm font-medium">{{ localize('Enable contract extraction AI', 'طھظپط¹ظٹظ„ ط§ط³طھط®ط±ط§ط¬ ط§ظ„ط¹ظ‚ظˆط¯ ط¨ط§ظ„ط°ظƒط§ط، ط§ظ„ط§طµط·ظ†ط§ط¹ظٹ') }}</span>
                        </label>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('AI Provider Settings', 'ط¥ط¹ط¯ط§ط¯ط§طھ ظ…ط²ظˆط¯ ط§ظ„ط°ظƒط§ط، ط§ظ„ط§طµط·ظ†ط§ط¹ظٹ') }}</CardTitle>
                        <CardDescription>
                            {{ localize('Configure provider credentials and extraction behavior. Empty secret fields keep existing saved values.', 'ط§ط¶ط¨ط· ط¨ظٹط§ظ†ط§طھ ط§ط¹طھظ…ط§ط¯ ط§ظ„ظ…ط²ظˆط¯ ظˆط³ظ„ظˆظƒ ط§ظ„ط§ط³طھط®ط±ط§ط¬. طھط±ظƒ ط§ظ„ط­ظ‚ظˆظ„ ط§ظ„ط³ط±ظٹط© ظپط§ط±ط؛ط© ط³ظٹط¨ظ‚ظٹ ط§ظ„ظ‚ظٹظ… ط§ظ„ط­ط§ظ„ظٹط©.') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Test provider credentials before saving.', 'ط§ط®طھط¨ط± ط¨ظٹط§ظ†ط§طھ ط§ط¹طھظ…ط§ط¯ ط§ظ„ظ…ط²ظˆط¯ ظ‚ط¨ظ„ ط§ظ„ط­ظپط¸.') }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <Button type="button" variant="outline" :disabled="testingAiConnection" @click="testAiConnection">
                                    {{ testingAiConnection ? localize('Testing...', 'ط¬ط§ط±ظچ ط§ظ„ط§ط®طھط¨ط§ط±...') : localize('Test AI Connection', 'ط§ط®طھط¨ط§ط± ط§طھطµط§ظ„ ط§ظ„ط°ظƒط§ط، ط§ظ„ط§طµط·ظ†ط§ط¹ظٹ') }}
                                </Button>
                                <Button type="button" variant="outline" :disabled="testingMailConnection" @click="testMailConnection">
                                    {{ testingMailConnection ? localize('Sending...', 'ط¬ط§ط±ظچ ط§ظ„ط¥ط±ط³ط§ظ„...') : localize('Send Test Email', 'ط¥ط±ط³ط§ظ„ ط¨ط±ظٹط¯ طھط¬ط±ظٹط¨ظٹ') }}
                                </Button>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-if="aiConnectionTestMessage"
                                class="rounded-md border p-3 text-sm"
                                :class="aiConnectionTestState === 'success'
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                    : 'border-red-200 bg-red-50 text-red-700'"
                            >
                                {{ aiConnectionTestMessage }}
                            </div>
                            <div
                                v-if="mailConnectionTestMessage"
                                class="rounded-md border p-3 text-sm"
                                :class="mailConnectionTestState === 'success'
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                    : 'border-red-200 bg-red-50 text-red-700'"
                            >
                                {{ mailConnectionTestMessage }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="ai_provider">{{ localize('Provider', 'ط§ظ„ظ…ط²ظˆط¯') }}</Label>
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
                                <Label for="openai_api_key">{{ localize('API Key', 'ظ…ظپطھط§ط­ API') }}</Label>
                                <Input id="openai_api_key" v-model="form.ai_provider.openai.api_key" type="password" placeholder="sk-..." />
                                <p v-if="props.aiProviderSettings.meta?.has_openai_api_key" class="text-xs text-muted-foreground">
                                    {{ localize('A key is already saved. Leave blank to keep it.', 'ظٹظˆط¬ط¯ ظ…ظپطھط§ط­ ظ…ط­ظپظˆط¸ ط¨ط§ظ„ظپط¹ظ„. ط§طھط±ظƒ ط§ظ„ط­ظ‚ظ„ ظپط§ط±ط؛ظ‹ط§ ظ„ظ„ط§ط­طھظپط§ط¸ ط¨ظ‡.') }}
                                </p>
                                <p v-if="form.errors['ai_provider.openai.api_key']" class="text-sm text-red-600">
                                    {{ form.errors['ai_provider.openai.api_key'] }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="openai_organization">{{ localize('Organization (optional)', 'ط§ظ„ظ…ط¤ط³ط³ط© (ط§ط®طھظٹط§ط±ظٹ)') }}</Label>
                                    <Input id="openai_organization" v-model="form.ai_provider.openai.organization" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="openai_project">{{ localize('Project (optional)', 'ط§ظ„ظ…ط´ط±ظˆط¹ (ط§ط®طھظٹط§ط±ظٹ)') }}</Label>
                                    <Input id="openai_project" v-model="form.ai_provider.openai.project" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="openai_model">{{ localize('Model', 'ط§ظ„ظ†ظ…ظˆط°ط¬') }}</Label>
                                    <Input id="openai_model" v-model="form.ai_provider.openai.model" placeholder="gpt-4.1-mini" />
                                    <p v-if="form.errors['ai_provider.openai.model']" class="text-sm text-red-600">
                                        {{ form.errors['ai_provider.openai.model'] }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="openai_base_uri">{{ localize('Base URL (optional)', 'ط§ظ„ط±ط§ط¨ط· ط§ظ„ط£ط³ط§ط³ظٹ (ط§ط®طھظٹط§ط±ظٹ)') }}</Label>
                                    <Input id="openai_base_uri" v-model="form.ai_provider.openai.base_uri" placeholder="https://api.openai.com/v1" />
                                    <p v-if="form.errors['ai_provider.openai.base_uri']" class="text-sm text-red-600">
                                        {{ form.errors['ai_provider.openai.base_uri'] }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="openai_temperature">{{ localize('Temperature (0-2)', 'ط§ظ„ط­ط±ط§ط±ط© (0-2)') }}</Label>
                                    <Input id="openai_temperature" v-model="form.ai_provider.openai.temperature" type="number" min="0" max="2" step="0.1" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="openai_tokens">{{ localize('Max Output Tokens', 'ط§ظ„ط­ط¯ ط§ظ„ط£ظ‚طµظ‰ ظ„ظ„ط±ظ…ظˆط² ط§ظ„ظ†ط§طھط¬ط©') }}</Label>
                                    <Input id="openai_tokens" v-model="form.ai_provider.openai.max_output_tokens" type="number" min="1" max="16384" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="openai_system_prompt">{{ localize('System Prompt (optional)', 'ط±ط³ط§ظ„ط© ط§ظ„ظ†ط¸ط§ظ… (ط§ط®طھظٹط§ط±ظٹ)') }}</Label>
                                <Textarea
                                    id="openai_system_prompt"
                                    v-model="form.ai_provider.openai.system_prompt"
                                    rows="4"
                                    :placeholder="localize('Extract key fields from Arabic and English rental contract files as JSON.', 'ط§ط³طھط®ط±ط¬ ط§ظ„ط­ظ‚ظˆظ„ ط§ظ„ط£ط³ط§ط³ظٹط© ظ…ظ† ظ…ظ„ظپط§طھ ط¹ظ‚ظˆط¯ ط§ظ„طھط£ط¬ظٹط± ط§ظ„ط¹ط±ط¨ظٹط© ظˆط§ظ„ط¥ظ†ط¬ظ„ظٹط²ظٹط© ط¨طµظٹط؛ط© JSON.')"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="document_extraction_daily_limit">
                                    {{ localize('Document Extraction Daily Limit', 'ط§ظ„ط­ط¯ ط§ظ„ظٹظˆظ…ظٹ ظ„ط§ط³طھط®ط±ط§ط¬ ط§ظ„ظ…ط³طھظ†ط¯ط§طھ') }}
                                </Label>
                                <Input
                                    id="document_extraction_daily_limit"
                                    v-model.number="form.ai_provider.document_extraction_daily_limit"
                                    type="number"
                                    min="1"
                                    step="1"
                                    placeholder="10"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ localize('Tenants can override this limit in their website settings.', 'ظٹظ…ظƒظ† ظ„ظ„ظ…ط³طھط£ط¬ط±ظٹظ† طھط؛ظٹظٹط± ظ‡ط°ط§ ط§ظ„ط­ط¯ ظ…ظ† ط¥ط¹ط¯ط§ط¯ط§طھ ظ…ظˆظ‚ط¹ظ‡ظ….') }}
                                </p>
                                <p v-if="form.errors['ai_provider.document_extraction_daily_limit']" class="text-sm text-red-600">
                                    {{ form.errors['ai_provider.document_extraction_daily_limit'] }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4 rounded-md border p-4">
                            <h3 class="text-sm font-semibold">{{ localize('Google Document AI', 'Google Document AI') }}</h3>

                            <label class="flex items-center gap-3">
                                <input v-model="form.ai_provider.google_document_ai.enabled" type="checkbox" class="h-4 w-4" />
                                <span class="text-sm font-medium">{{ localize('Enable Google Document AI OCR', 'طھظپط¹ظٹظ„ OCR ظ…ظ† Google Document AI') }}</span>
                            </label>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="space-y-2">
                                    <Label for="gdoc_project_id">{{ localize('Project ID', 'ظ…ط¹ط±ظپ ط§ظ„ظ…ط´ط±ظˆط¹') }}</Label>
                                    <Input id="gdoc_project_id" v-model="form.ai_provider.google_document_ai.project_id" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="gdoc_location">{{ localize('Location', 'ط§ظ„ظ…ظ†ط·ظ‚ط©') }}</Label>
                                    <Input id="gdoc_location" v-model="form.ai_provider.google_document_ai.location" placeholder="us" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="gdoc_processor_id">{{ localize('Processor ID', 'ظ…ط¹ط±ظپ ط§ظ„ظ…ط¹ط§ظ„ط¬') }}</Label>
                                    <Input id="gdoc_processor_id" v-model="form.ai_provider.google_document_ai.processor_id" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="gdoc_credentials_json">{{ localize('Service Account JSON', 'JSON ط­ط³ط§ط¨ ط§ظ„ط®ط¯ظ…ط©') }}</Label>
                                <Textarea
                                    id="gdoc_credentials_json"
                                    v-model="form.ai_provider.google_document_ai.service_account_json"
                                    rows="5"
                                    placeholder='{"type":"service_account","project_id":"..."}'
                                />
                                <p v-if="props.aiProviderSettings.meta?.has_google_credentials" class="text-xs text-muted-foreground">
                                    {{ localize('Credentials are already saved. Leave blank to keep them.', 'ط¨ظٹط§ظ†ط§طھ ط§ظ„ط§ط¹طھظ…ط§ط¯ ظ…ط­ظپظˆط¸ط© ط¨ط§ظ„ظپط¹ظ„. ط§طھط±ظƒ ط§ظ„ط­ظ‚ظ„ ظپط§ط±ط؛ظ‹ط§ ظ„ظ„ط§ط­طھظپط§ط¸ ط¨ظ‡ط§.') }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? localize('Saving...', 'ط¬ط§ط±ظچ ط§ظ„ط­ظپط¸...') : localize('Save Changes', 'ط­ظپط¸ ط§ظ„طھط؛ظٹظٹط±ط§طھ') }}
                    </Button>
                </div>
            </form>
        </main>
    </SuperAdminLayout>
</template>
