<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { computed, reactive, ref, watch } from 'vue';

type Provider = {
    id: number;
    code: string;
    name: string;
    driver: string | null;
    description: string | null;
    is_enabled: boolean;
    is_default: boolean;
    supports_platform_subscriptions: boolean;
    supports_tenant_payments: boolean;
    mode: 'test' | 'live';
    config: Record<string, any>;
    supported_countries: string[];
    supported_currencies: string[];
    sort_order: number;
    last_tested_at: string | null;
    updated_at: string | null;
};

type ProviderConfigField = {
    key: string;
    label: string;
    type?: 'text' | 'password';
    placeholder?: string;
    help?: string;
    advanced?: boolean;
    readonly?: boolean;
};

const props = defineProps<{
    providers: Provider[];
}>();

const { locale } = useTrans();
const page = usePage<any>();
const search = ref('');
const selectedProviderId = ref<number | null>(props.providers[0]?.id ?? null);

const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const providersSorted = computed(() =>
    [...props.providers].sort((a, b) => {
        if ((a.sort_order ?? 0) !== (b.sort_order ?? 0)) {
            return (a.sort_order ?? 0) - (b.sort_order ?? 0);
        }
        return a.name.localeCompare(b.name);
    }),
);

const filteredProviders = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return providersSorted.value;

    return providersSorted.value.filter((provider) =>
        [provider.name, provider.code, provider.driver ?? '', provider.description ?? '']
            .join(' ')
            .toLowerCase()
            .includes(q),
    );
});

const selectedProvider = computed(
    () => props.providers.find((provider) => provider.id === selectedProviderId.value) ?? null,
);

const form = useForm({
    name: '',
    driver: '',
    description: '',
    is_enabled: false,
    is_default: false,
    supports_platform_subscriptions: false,
    supports_tenant_payments: false,
    mode: 'test' as 'test' | 'live',
    sort_order: 0,
    config: {} as Record<string, any>,
    supported_countries: [] as string[],
    supported_currencies: [] as string[],
});

const uiState = reactive({
    countriesCsv: '',
    currenciesCsv: '',
    configJson: '{}',
    providerConfigFields: {} as Record<string, string>,
    showAdvancedJson: false,
});

const providerConfigSchemas = computed<Record<string, ProviderConfigField[]>>(() => ({
    stripe: [
        { key: 'publishable_key', label: localize('Publishable Key', 'المفتاح العام'), placeholder: 'pk_test_...', type: 'text' },
        { key: 'secret_key', label: localize('Secret Key', 'المفتاح السري'), placeholder: 'sk_test_...', type: 'password' },
        { key: 'webhook_secret', label: localize('Webhook Secret', 'سر Webhook'), placeholder: 'whsec_...', type: 'password' },
        { key: 'webhook_path', label: localize('Webhook Path', 'مسار Webhook'), placeholder: 'stripe/webhook', type: 'text' },
    ],
    myfatoorah: [
        { key: 'country', label: localize('Country', 'الدولة'), placeholder: 'OM', type: 'text' },
        { key: 'api_token', label: localize('API Token', 'رمز API'), placeholder: localize('MyFatoorah token', 'رمز MyFatoorah'), type: 'password' },
        { key: 'webhook_secret', label: localize('Webhook Secret (optional)', 'سر Webhook (اختياري)'), placeholder: '', type: 'password' },
        { key: 'payment_method_id', label: localize('Default Payment Method ID (optional)', 'معرف وسيلة الدفع الافتراضية (اختياري)'), placeholder: '2', type: 'text', help: localize('Use only as a fallback when payment methods cannot be loaded dynamically.', 'استخدمه فقط كخيار احتياطي عندما لا يمكن تحميل وسائل الدفع ديناميكيًا.'), advanced: true },
        { key: 'api_base_url', label: localize('API Base URL (override)', 'رابط API الأساسي (تجاوز)'), placeholder: 'https://api.myfatoorah.com', type: 'text', advanced: true },
        { key: 'callback_url', label: localize('Callback URL (override)', 'رابط Callback (تجاوز)'), placeholder: 'https://your-domain.com/...', type: 'text', advanced: true },
        { key: 'error_url', label: localize('Error URL (override)', 'رابط الخطأ (تجاوز)'), placeholder: 'https://your-domain.com/...', type: 'text', advanced: true },
    ],
}));

const selectedProviderConfigFields = computed<ProviderConfigField[]>(() => {
    if (!selectedProvider.value) return [];
    return providerConfigSchemas.value[selectedProvider.value.code] ?? [];
});

const basicProviderConfigFields = computed(() => selectedProviderConfigFields.value.filter((field) => !field.advanced));
const advancedProviderConfigFields = computed(() => selectedProviderConfigFields.value.filter((field) => field.advanced));

const showAdvancedProviderFields = ref(false);

const isMyFatoorahSelected = computed(() => selectedProvider.value?.code === 'myfatoorah');

const myFatoorahDefaultApiBaseUrl = computed(() => {
    return form.mode === 'live' ? 'https://api.myfatoorah.com' : 'https://apitest.myfatoorah.com';
});

function loadProviderIntoForm(provider: Provider | null) {
    if (!provider) {
        form.reset();
        uiState.countriesCsv = '';
        uiState.currenciesCsv = '';
        uiState.configJson = '{}';
        uiState.providerConfigFields = {};
        uiState.showAdvancedJson = false;
        showAdvancedProviderFields.value = false;
        return;
    }

    form.defaults({
        name: provider.name,
        driver: provider.driver ?? '',
        description: provider.description ?? '',
        is_enabled: provider.is_enabled,
        is_default: provider.is_default,
        supports_platform_subscriptions: provider.supports_platform_subscriptions,
        supports_tenant_payments: provider.supports_tenant_payments,
        mode: provider.mode ?? 'test',
        sort_order: provider.sort_order ?? 0,
        config: provider.config ?? {},
        supported_countries: provider.supported_countries ?? [],
        supported_currencies: provider.supported_currencies ?? [],
    });
    form.reset();
    form.clearErrors();

    uiState.countriesCsv = (provider.supported_countries ?? []).join(', ');
    uiState.currenciesCsv = (provider.supported_currencies ?? []).join(', ');
    uiState.configJson = JSON.stringify(provider.config ?? {}, null, 2);
    hydrateProviderConfigInputs(provider.code, provider.config ?? {});
    uiState.showAdvancedJson = false;
    showAdvancedProviderFields.value = false;

    if (provider.code === 'myfatoorah') {
        const current = (uiState.providerConfigFields.api_base_url ?? '').trim();
        if (current === '') {
            uiState.providerConfigFields.api_base_url = provider.mode === 'live'
                ? 'https://api.myfatoorah.com'
                : 'https://apitest.myfatoorah.com';
        }
    }
}

watch(
    selectedProvider,
    (provider) => {
        loadProviderIntoForm(provider);
    },
    { immediate: true },
);

function parseCsv(value: string): string[] {
    return value
        .split(',')
        .map((item) => item.trim())
        .filter((item) => item !== '');
}

function hydrateProviderConfigInputs(providerCode: string, config: Record<string, any>) {
    const fields = providerConfigSchemas.value[providerCode] ?? [];
    const nextState: Record<string, string> = {};

    for (const field of fields) {
        const value = config[field.key];
        nextState[field.key] = value === null || value === undefined ? '' : String(value);
    }

    uiState.providerConfigFields = nextState;
}

function mergeProviderSpecificInputsIntoConfig(parsedConfig: Record<string, any>): Record<string, any> {
    const provider = selectedProvider.value;
    if (!provider) return parsedConfig;

    const fields = providerConfigSchemas.value[provider.code] ?? [];
    const merged = { ...parsedConfig };

    for (const field of fields) {
        const raw = (uiState.providerConfigFields[field.key] ?? '').trim();
        merged[field.key] = raw === '' ? null : raw;
    }

    return merged;
}

watch(
    () => [selectedProvider.value?.code, form.mode] as const,
    ([providerCode]) => {
        if (providerCode !== 'myfatoorah') return;

        const current = (uiState.providerConfigFields.api_base_url ?? '').trim();
        const knownDefaults = ['https://apitest.myfatoorah.com', 'https://api.myfatoorah.com'];

        if (current === '' || knownDefaults.includes(current)) {
            uiState.providerConfigFields.api_base_url = myFatoorahDefaultApiBaseUrl.value;
        }
    },
    { immediate: true },
);

function submit() {
    if (!selectedProvider.value) return;

    let parsedConfig: Record<string, any> = {};
    try {
        parsedConfig = uiState.configJson.trim() ? JSON.parse(uiState.configJson) : {};
    } catch {
        form.setError('config', localize('Config JSON is invalid.', 'تنسيق JSON للإعدادات غير صالح.'));
        return;
    }

    if (parsedConfig === null || Array.isArray(parsedConfig) || typeof parsedConfig !== 'object') {
        form.setError('config', localize('Config JSON must be an object.', 'يجب أن تكون إعدادات JSON كائنًا.'));
        return;
    }

    parsedConfig = mergeProviderSpecificInputsIntoConfig(parsedConfig);

    if (selectedProvider.value?.code === 'myfatoorah') {
        const callbackUrl = String(parsedConfig.callback_url ?? '').trim();
        const errorUrl = String(parsedConfig.error_url ?? '').trim();

        parsedConfig.api_base_url = String(parsedConfig.api_base_url ?? '').trim() || myFatoorahDefaultApiBaseUrl.value;
        parsedConfig.callback_url = callbackUrl === '' ? null : callbackUrl;
        parsedConfig.error_url = errorUrl === '' ? null : errorUrl;
    }

    uiState.configJson = JSON.stringify(parsedConfig, null, 2);

    form.clearErrors('config');
    form.config = parsedConfig;
    form.supported_countries = parseCsv(uiState.countriesCsv);
    form.supported_currencies = parseCsv(uiState.currenciesCsv).map((item) => item.toUpperCase());

    form.put(`/superadmin/settings/payment-providers/${selectedProvider.value.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="localize('Payment Providers', 'مزودو الدفع')" />

    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Payment Providers', 'مزودو الدفع') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Configure approved gateways for platform subscriptions and tenant payments.', 'قم بإعداد بوابات الدفع المعتمدة لاشتراكات المنصة ومدفوعات المستأجرين.') }}
                    </p>
                </div>
                <Button :disabled="form.processing || !selectedProvider" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                </Button>
            </div>

            <div v-if="flashSuccess" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ flashError }}
            </div>

            <div class="grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Providers', 'المزودون') }}</CardTitle>
                        <CardDescription>{{ localize('Select a provider to edit its settings.', 'اختر مزودًا لتعديل إعداداته.') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="space-y-2">
                            <Label for="provider-search">{{ localize('Search', 'بحث') }}</Label>
                            <Input id="provider-search" v-model="search" :placeholder="localize('Stripe, MyFatoorah...', 'Stripe, MyFatoorah...')" />
                        </div>

                        <div class="max-h-[520px] space-y-2 overflow-auto pr-1">
                            <button
                                v-for="provider in filteredProviders"
                                :key="provider.id"
                                type="button"
                                class="w-full rounded-lg border p-3 text-left transition hover:bg-muted/30"
                                :class="provider.id === selectedProviderId ? 'border-primary bg-primary/5' : 'border-border'"
                                @click="selectedProviderId = provider.id"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <div class="font-medium">{{ provider.name }}</div>
                                        <div class="text-xs text-muted-foreground font-mono">{{ provider.code }}</div>
                                    </div>
                                        <div class="text-right text-xs">
                                            <div :class="provider.is_enabled ? 'text-emerald-600' : 'text-gray-500'">
                                                {{ provider.is_enabled ? localize('Enabled', 'مفعل') : localize('Disabled', 'معطل') }}
                                            </div>
                                            <div v-if="provider.is_default" class="text-amber-600">{{ localize('Default', 'افتراضي') }}</div>
                                        </div>
                                    </div>
                                </button>

                                <div v-if="filteredProviders.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                {{ localize('No providers match your search.', 'لا يوجد مزودون يطابقون البحث.') }}
                                </div>
                        </div>
                    </CardContent>
                </Card>

                <div v-if="selectedProvider" class="space-y-6">
                    <form class="space-y-6" @submit.prevent="submit">
                        <Card>
                            <CardHeader>
                                <CardTitle>{{ localize('General', 'عام') }}</CardTitle>
                                <CardDescription>
                                    {{ localize(`Basic identity and mode settings for ${selectedProvider.name}.`, `إعدادات التعريف الأساسية ووضع التشغيل لـ ${selectedProvider.name}.`) }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-4 md:grid-cols-3">
                                    <div class="space-y-2">
                                        <Label for="provider_name">{{ localize('Name', 'الاسم') }}</Label>
                                        <Input id="provider_name" v-model="form.name" />
                                        <p v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="provider_driver">{{ localize('Driver', 'المشغل') }}</Label>
                                        <Input id="provider_driver" v-model="form.driver" placeholder="myfatoorah" />
                                        <p v-if="form.errors.driver" class="text-sm text-red-600">{{ form.errors.driver }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="provider_mode">{{ localize('Mode', 'الوضع') }}</Label>
                                        <select
                                            id="provider_mode"
                                            v-model="form.mode"
                                            class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        >
                                            <option value="test">{{ localize('Test', 'تجريبي') }}</option>
                                            <option value="live">{{ localize('Live', 'فعلي') }}</option>
                                        </select>
                                        <p v-if="form.errors.mode" class="text-sm text-red-600">{{ form.errors.mode }}</p>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="provider_description">{{ localize('Description', 'الوصف') }}</Label>
                                    <textarea
                                        id="provider_description"
                                        v-model="form.description"
                                        rows="3"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                    <p v-if="form.errors.description" class="text-sm text-red-600">{{ form.errors.description }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{{ localize('Availability & Usage', 'التوفر والاستخدام') }}</CardTitle>
                                <CardDescription>
                                    {{ localize('Control whether this provider is active and where it can be used.', 'تحكم فيما إذا كان هذا المزود مفعلًا وأين يمكن استخدامه.') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                                    <label class="flex items-center gap-2 rounded-md border p-3 text-sm">
                                        <input v-model="form.is_enabled" type="checkbox" />
                                        <span>{{ localize('Enabled', 'مفعل') }}</span>
                                    </label>

                                    <label class="flex items-center gap-2 rounded-md border p-3 text-sm">
                                        <input v-model="form.is_default" type="checkbox" />
                                        <span>{{ localize('Default', 'افتراضي') }}</span>
                                    </label>

                                    <label class="flex items-center gap-2 rounded-md border p-3 text-sm">
                                        <input v-model="form.supports_platform_subscriptions" type="checkbox" />
                                        <span>{{ localize('Platform subscriptions', 'اشتراكات المنصة') }}</span>
                                    </label>

                                    <label class="flex items-center gap-2 rounded-md border p-3 text-sm">
                                        <input v-model="form.supports_tenant_payments" type="checkbox" />
                                        <span>{{ localize('Tenant payments', 'مدفوعات المستأجرين') }}</span>
                                    </label>

                                    <div class="space-y-2 rounded-md border p-3">
                                        <Label for="sort_order">{{ localize('Sort Order', 'ترتيب العرض') }}</Label>
                                        <Input id="sort_order" v-model.number="form.sort_order" type="number" min="0" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{{ localize('Regions & Currencies', 'الدول والعملات') }}</CardTitle>
                                <CardDescription>
                                    {{ localize('Use CSV values to control provider availability by country/currency.', 'استخدم قيم CSV للتحكم في توفر المزود حسب الدولة أو العملة.') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="supported_countries">{{ localize('Supported Countries (CSV)', 'الدول المدعومة (CSV)') }}</Label>
                                        <Input id="supported_countries" v-model="uiState.countriesCsv" placeholder="OM, AE, SA, KW" />
                                        <p v-if="form.errors.supported_countries" class="text-sm text-red-600">
                                            {{ form.errors.supported_countries }}
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="supported_currencies">{{ localize('Supported Currencies (CSV)', 'العملات المدعومة (CSV)') }}</Label>
                                        <Input id="supported_currencies" v-model="uiState.currenciesCsv" placeholder="OMR, AED, USD" />
                                        <p v-if="form.errors.supported_currencies" class="text-sm text-red-600">
                                            {{ form.errors.supported_currencies }}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{{ localize('Credentials & Webhooks', 'بيانات الاعتماد و Webhooks') }}</CardTitle>
                                <CardDescription>
                                    {{ localize('Fill provider-specific keys here. These values are saved into Provider Config JSON automatically.', 'أدخل مفاتيح المزود هنا. سيتم حفظ هذه القيم تلقائيًا داخل JSON إعدادات المزود.') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div v-if="basicProviderConfigFields.length > 0" class="grid gap-4 md:grid-cols-2">
                                    <div v-for="field in basicProviderConfigFields" :key="field.key" class="space-y-2">
                                        <Label :for="`provider-field-${field.key}`">{{ field.label }}</Label>
                                        <Input
                                            :id="`provider-field-${field.key}`"
                                            v-model="uiState.providerConfigFields[field.key]"
                                            :type="field.type ?? 'text'"
                                            :placeholder="field.placeholder || ''"
                                            :readonly="field.readonly === true"
                                        />
                                        <p v-if="field.help" class="text-xs text-muted-foreground">{{ field.help }}</p>
                                    </div>
                                </div>
                                <div v-else class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                    {{ localize('No predefined credential fields for this provider yet. Use the JSON section below.', 'لا توجد حقول اعتماد معرفة مسبقًا لهذا المزود حتى الآن. استخدم قسم JSON أدناه.') }}
                                </div>

                                <div v-if="isMyFatoorahSelected" class="rounded-md border bg-muted/20 p-4 text-sm space-y-2">
                                    <div class="font-medium">{{ localize('Auto Defaults (MyFatoorah)', 'القيم الافتراضية التلقائية (MyFatoorah)') }}</div>
                                    <div class="grid gap-2 md:grid-cols-2 text-xs text-muted-foreground">
                                        <div>
                                            <div class="font-medium text-foreground">{{ localize('API Base URL (auto)', 'رابط API الأساسي (تلقائي)') }}</div>
                                            <div class="font-mono break-all">{{ myFatoorahDefaultApiBaseUrl }}</div>
                                        </div>
                                        <div>
                                            <div class="font-medium text-foreground">{{ localize('Mode', 'الوضع') }}</div>
                                            <div>{{ form.mode === 'live' ? localize('Live', 'فعلي') : localize('Test', 'تجريبي') }}</div>
                                        </div>
                                        <div class="md:col-span-2">
                                            <div class="font-medium text-foreground">{{ localize('Callback / Error URLs', 'روابط Callback / Error') }}</div>
                                            <div>{{ localize('Generated automatically by the system routes during checkout (no manual input required).', 'يتم توليدها تلقائيًا من مسارات النظام أثناء الدفع ولا تحتاج إدخالًا يدويًا.') }}</div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="advancedProviderConfigFields.length > 0" class="rounded-md border p-3 space-y-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <div class="text-sm font-medium">{{ localize('Advanced Provider Fields', 'حقول المزود المتقدمة') }}</div>
                                            <p class="text-xs text-muted-foreground">
                                                {{ localize('Optional overrides (keep hidden unless you need custom behavior).', 'خيارات متقدمة اختيارية. اتركها مخفية ما لم تكن تحتاج سلوكًا مخصصًا.') }}
                                            </p>
                                        </div>
                                        <Button type="button" variant="outline" size="sm" @click="showAdvancedProviderFields = !showAdvancedProviderFields">
                                            {{ showAdvancedProviderFields ? localize('Hide Advanced', 'إخفاء المتقدم') : localize('Show Advanced', 'إظهار المتقدم') }}
                                        </Button>
                                    </div>

                                    <div v-if="showAdvancedProviderFields" class="grid gap-4 md:grid-cols-2">
                                        <div v-for="field in advancedProviderConfigFields" :key="`adv-${field.key}`" class="space-y-2">
                                            <Label :for="`provider-adv-field-${field.key}`">{{ field.label }}</Label>
                                            <Input
                                                :id="`provider-adv-field-${field.key}`"
                                                v-model="uiState.providerConfigFields[field.key]"
                                                :type="field.type ?? 'text'"
                                                :placeholder="field.placeholder || ''"
                                            />
                                            <p v-if="field.help" class="text-xs text-muted-foreground">{{ field.help }}</p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{{ localize('Provider Config', 'إعدادات المزود') }}</CardTitle>
                                <CardDescription>
                                    {{ localize('Provider-specific JSON settings (API token, region, callback settings, profile IDs).', 'إعدادات JSON الخاصة بالمزود مثل رمز API والمنطقة وروابط callback ومعرفات الحسابات.') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex items-center justify-between rounded-md border p-3">
                                    <div>
                                        <div class="text-sm font-medium">{{ localize('Advanced JSON Editor', 'محرر JSON المتقدم') }}</div>
                                        <p class="text-xs text-muted-foreground">
                                            {{ localize('Keep this hidden unless you need custom keys not available in the fields above.', 'أبقِ هذا القسم مخفيًا ما لم تكن تحتاج مفاتيح مخصصة غير متوفرة في الحقول أعلاه.') }}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="uiState.showAdvancedJson = !uiState.showAdvancedJson"
                                    >
                                        {{ uiState.showAdvancedJson ? localize('Hide JSON', 'إخفاء JSON') : localize('Show JSON', 'إظهار JSON') }}
                                    </Button>
                                </div>

                                <div class="space-y-2">
                                    <Label for="provider_config_json">{{ localize('Provider Config (JSON)', 'إعدادات المزود (JSON)') }}</Label>
                                    <textarea
                                        id="provider_config_json"
                                        v-model="uiState.configJson"
                                        rows="10"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs"
                                        :readonly="!uiState.showAdvancedJson"
                                        :class="!uiState.showAdvancedJson ? 'cursor-not-allowed opacity-70' : ''"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        {{ localize('Values are currently stored as JSON and are not encrypted yet.', 'يتم حفظ القيم حاليًا بصيغة JSON ولم تُشفّر بعد.') }}
                                    </p>
                                    <p v-if="!uiState.showAdvancedJson" class="text-xs text-muted-foreground">
                                        {{ localize('This editor is read-only by default. Use the button above to enable editing.', 'هذا المحرر للقراءة فقط افتراضيًا. استخدم الزر أعلاه لتفعيل التعديل.') }}
                                    </p>
                                    <p v-if="form.errors.config" class="text-sm text-red-600">{{ form.errors.config }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <div class="flex justify-end">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <Card v-else>
                    <CardHeader>
                        <CardTitle>{{ localize('No Provider Selected', 'لم يتم اختيار مزود') }}</CardTitle>
                        <CardDescription>{{ localize('Select a provider from the left list to edit it.', 'اختر مزودًا من القائمة اليسرى لتعديل بياناته.') }}</CardDescription>
                    </CardHeader>
                </Card>
            </div>
        </main>
    </SuperAdminLayout>
</template>
