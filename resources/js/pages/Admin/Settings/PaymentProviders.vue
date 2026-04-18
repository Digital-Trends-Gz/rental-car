<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useTrans } from '@/composables/useTrans';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type PlatformProvider = {
    id: number;
    code: 'stripe' | 'myfatoorah';
    name: string;
    description: string | null;
    is_enabled: boolean;
    mode: 'test' | 'live';
    config: Record<string, any>;
    supported_countries: string[];
    supported_currencies: string[];
};

const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
        settings: {
            default_provider: string | null;
            stripe: { enabled: boolean };
            myfatoorah: {
                enabled: boolean;
                country: string;
                api_token: string;
                api_base_url: string;
                payment_method_id: string;
                callback_url: string;
                error_url: string;
                webhook_secret: string;
            };
        };
        stripe_connect: {
            stripe_account_id: string | null;
            stripe_charges_enabled: boolean;
            stripe_payouts_enabled: boolean;
            stripe_details_submitted: boolean;
            stripe_currency: string | null;
        };
    };
    platformProviders: PlatformProvider[];
    actions: {
        update: string;
        stripe_connect: string;
    };
}>();

const page = usePage<any>();
const { locale } = useTrans();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const localize = (en: string, ar: string, ur: string = en) => (locale.value === 'ar' ? ar : locale.value === 'ur' ? ur : en);

const providerMap = computed<Record<string, PlatformProvider>>(() =>
    Object.fromEntries((props.platformProviders || []).map((provider) => [provider.code, provider])),
);

const stripePlatform = computed(() => providerMap.value.stripe ?? null);
const myfatoorahPlatform = computed(() => providerMap.value.myfatoorah ?? null);
const showMyFatoorahAdvanced = ref(false);

const myfatoorahPlatformMode = computed<'test' | 'live'>(() => myfatoorahPlatform.value?.mode === 'live' ? 'live' : 'test');
const myfatoorahAutoBaseUrl = computed(() => myfatoorahPlatformMode.value === 'live'
    ? 'https://api.myfatoorah.com'
    : 'https://apitest.myfatoorah.com');

const form = useForm({
    default_provider: props.tenant.settings?.default_provider ?? null,
    stripe: {
        enabled: !!props.tenant.settings?.stripe?.enabled,
    },
    myfatoorah: {
        enabled: !!props.tenant.settings?.myfatoorah?.enabled,
        country: props.tenant.settings?.myfatoorah?.country ?? 'KW',
        api_token: props.tenant.settings?.myfatoorah?.api_token ?? '',
        api_base_url: props.tenant.settings?.myfatoorah?.api_base_url ?? '',
        payment_method_id: props.tenant.settings?.myfatoorah?.payment_method_id ?? '',
        callback_url: props.tenant.settings?.myfatoorah?.callback_url ?? '',
        error_url: props.tenant.settings?.myfatoorah?.error_url ?? '',
        webhook_secret: props.tenant.settings?.myfatoorah?.webhook_secret ?? '',
    },
});

function submit() {
    if (!form.myfatoorah.api_base_url?.trim()) {
        form.myfatoorah.api_base_url = myfatoorahAutoBaseUrl.value;
    }

    form.put(props.actions.update, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="localize('Payment Providers', 'مزودو الدفع', 'ادائیگی فراہم کنندگان')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Payment Providers', 'مزودو الدفع', 'ادائیگی فراہم کنندگان') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize(
                            'Manage tenant booking payment providers. Only providers approved by Super Admin can be enabled.',
                            'إدارة مزودي دفع الحجوزات للمستأجر. لا يمكن تفعيل إلا المزودين المعتمدين من المشرف العام.',
                            'کرایہ دار کی بکنگ کے لیے ادائیگی فراہم کنندگان کا انتظام کریں۔ صرف وہ فراہم کنندگان فعال کیے جا سکتے ہیں جنہیں سپر ایڈمن نے منظور کیا ہو.',
                        ) }}
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...', 'محفوظ کیا جا رہا ہے...') : localize('Save Changes', 'حفظ التغييرات', 'تبدیلیاں محفوظ کریں') }}
                </Button>
            </div>

            <div v-if="flashSuccess" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ flashError }}
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-lg border p-5">
                    <h2 class="mb-4 text-lg font-semibold">
                        {{ localize('Provider Availability (Platform Approval)', 'توفر المزود (اعتماد المنصة)', 'فراہم کنندہ کی دستیابی (پلیٹ فارم منظوری)') }}
                    </h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-md border p-4">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <div class="font-medium">Stripe</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ stripePlatform?.description || localize('Stripe Connect / platform-managed Stripe payments', 'Stripe Connect / مدفوعات Stripe المُدارة من المنصة', 'Stripe Connect / پلیٹ فارم کے زیر انتظام Stripe ادائیگیاں') }}
                                    </div>
                                </div>
                                <span
                                    class="rounded px-2 py-1 text-xs"
                                    :class="stripePlatform?.is_enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ stripePlatform?.is_enabled ? localize('Approved by Super Admin', 'معتمد من المشرف العام', 'سپر ایڈمن سے منظور شدہ') : localize('Disabled by Super Admin', 'معطل من المشرف العام', 'سپر ایڈمن کے ذریعے غیر فعال') }}
                                </span>
                            </div>
                            <div class="mt-2 text-xs text-muted-foreground">
                                {{ localize('Mode', 'الوضع', 'موڈ') }}: {{ stripePlatform?.mode || '-' }}
                            </div>
                        </div>

                        <div class="rounded-md border p-4">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <div class="font-medium">MyFatoorah</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ myfatoorahPlatform?.description || localize('Hosted checkout for GCC/MENA', 'دفع مستضاف لمنطقة الخليج/الشرق الأوسط وشمال أفريقيا', 'جی سی سی / مشرقِ وسطیٰ اور شمالی افریقہ کے لیے ہوسٹڈ چیک آؤٹ') }}
                                    </div>
                                </div>
                                <span
                                    class="rounded px-2 py-1 text-xs"
                                    :class="myfatoorahPlatform?.is_enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ myfatoorahPlatform?.is_enabled ? localize('Approved by Super Admin', 'معتمد من المشرف العام', 'سپر ایڈمن سے منظور شدہ') : localize('Disabled by Super Admin', 'معطل من المشرف العام', 'سپر ایڈمن کے ذریعے غیر فعال') }}
                                </span>
                            </div>
                            <div class="mt-2 text-xs text-muted-foreground">
                                {{ localize('Mode', 'الوضع', 'موڈ') }}: {{ myfatoorahPlatform?.mode || '-' }}
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5">
                    <h2 class="mb-4 text-lg font-semibold">
                        {{ localize('Default Booking Provider', 'مزود الحجز الافتراضي', 'ڈیفالٹ بکنگ فراہم کنندہ') }}
                    </h2>
                    <div class="space-y-2">
                        <Label for="default_provider">{{ localize('Default Provider', 'المزود الافتراضي', 'ڈیفالٹ فراہم کنندہ') }}</Label>
                        <select
                            id="default_provider"
                            v-model="form.default_provider"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm md:max-w-md"
                        >
                            <option :value="null">{{ localize('None (manual / fallback)', 'لا شيء (يدوي / احتياطي)', 'کوئی نہیں (دستی / متبادل)') }}</option>
                            <option v-if="stripePlatform?.is_enabled" value="stripe">Stripe</option>
                            <option v-if="myfatoorahPlatform?.is_enabled" value="myfatoorah">MyFatoorah</option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            {{ localize(
                                'This will be used by tenant booking checkout when multiple tenant providers are enabled.',
                                'سيُستخدم هذا في إتمام حجوزات المستأجر عندما تكون عدة مزودات مفعلة.',
                                'جب متعدد کرایہ دار فراہم کنندگان فعال ہوں تو اسے کرایہ دار کی بکنگ چیک آؤٹ میں استعمال کیا جائے گا.',
                            ) }}
                        </p>
                        <p v-if="form.errors.default_provider" class="text-sm text-red-600">{{ form.errors.default_provider }}</p>
                    </div>
                </section>

                <section class="rounded-lg border p-5">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">{{ localize('Stripe (Tenant)', 'Stripe (المستأجر)', 'Stripe (کرایہ دار)') }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ localize(
                                    'Uses Stripe Connect. Manage onboarding and account status in the Stripe Connect page.',
                                    'يستخدم Stripe Connect. قم بإدارة الإعداد وحالة الحساب في صفحة Stripe Connect.',
                                    'Stripe Connect استعمال کرتا ہے۔ آن بورڈنگ اور اکاؤنٹ اسٹیٹس کو Stripe Connect صفحے میں منظم کریں.',
                                ) }}
                            </p>
                        </div>
                        <Link :href="actions.stripe_connect">
                            <Button type="button" variant="outline">{{ localize('Open Stripe Connect', 'فتح Stripe Connect', 'Stripe Connect کھولیں') }}</Button>
                        </Link>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="flex items-center gap-2 rounded-md border p-3 text-sm">
                            <input
                                v-model="form.stripe.enabled"
                                type="checkbox"
                                :disabled="!stripePlatform?.is_enabled"
                            />
                            <span>{{ localize('Enable Stripe for tenant bookings', 'تفعيل Stripe لحجوزات المستأجر', 'کرایہ دار کی بکنگ کے لیے Stripe فعال کریں') }}</span>
                        </label>

                        <div class="rounded-md border p-3 text-sm">
                            <div class="font-medium">{{ localize('Stripe Connect Status', 'حالة Stripe Connect', 'Stripe Connect کی حالت') }}</div>
                            <div class="mt-1 text-xs text-muted-foreground break-all">
                                {{ localize('Account ID', 'معرّف الحساب', 'اکاؤنٹ آئی ڈی') }}: {{ tenant.stripe_connect.stripe_account_id || localize('Not connected', 'غير متصل', 'منسلک نہیں') }}
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                                <span :class="tenant.stripe_connect.stripe_charges_enabled ? 'text-emerald-600' : 'text-amber-600'">
                                    {{ localize('Charges', 'الرسوم', 'چارجز') }}: {{ tenant.stripe_connect.stripe_charges_enabled ? localize('Enabled', 'مفعلة', 'فعال') : localize('Disabled', 'معطلة', 'غیر فعال') }}
                                </span>
                                <span :class="tenant.stripe_connect.stripe_payouts_enabled ? 'text-emerald-600' : 'text-amber-600'">
                                    {{ localize('Payouts', 'المدفوعات', 'ادائیگیاں') }}: {{ tenant.stripe_connect.stripe_payouts_enabled ? localize('Enabled', 'مفعلة', 'فعال') : localize('Disabled', 'معطلة', 'غیر فعال') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <p v-if="!stripePlatform?.is_enabled" class="mt-3 text-sm text-amber-700">
                        {{ localize('Stripe is currently disabled by Super Admin.', 'Stripe معطل حاليًا من المشرف العام.', 'Stripe اس وقت سپر ایڈمن کے ذریعے غیر فعال ہے.') }}
                    </p>
                </section>

                <section class="rounded-lg border p-5">
                    <div class="mb-4">
                        <h2 class="text-lg font-semibold">{{ localize('MyFatoorah (Tenant)', 'MyFatoorah (المستأجر)', 'MyFatoorah (کرایہ دار)') }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ localize(
                                'Store tenant MyFatoorah credentials for booking payments (separate from SaaS subscription payments).',
                                'احفظ بيانات اعتماد MyFatoorah للمستأجر لمدفوعات الحجوزات (بشكل منفصل عن مدفوعات اشتراك SaaS).',
                                'بکنگ ادائیگیوں کے لیے کرایہ دار کے MyFatoorah اسناد محفوظ کریں (SaaS سبسکرپشن ادائیگیوں سے الگ).',
                            ) }}
                        </p>
                    </div>

                    <div class="space-y-4">
                        <label class="flex items-center gap-2 rounded-md border p-3 text-sm">
                            <input
                                v-model="form.myfatoorah.enabled"
                                type="checkbox"
                                :disabled="!myfatoorahPlatform?.is_enabled"
                            />
                            <span>{{ localize('Enable MyFatoorah for tenant bookings', 'تفعيل MyFatoorah لحجوزات المستأجر', 'کرایہ دار کی بکنگ کے لیے MyFatoorah فعال کریں') }}</span>
                        </label>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="mf_country">{{ localize('Country', 'الدولة', 'ملک') }}</Label>
                                <Input id="mf_country" v-model="form.myfatoorah.country" placeholder="KW" />
                                <p v-if="form.errors['myfatoorah.country']" class="text-sm text-red-600">{{ form.errors['myfatoorah.country'] }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label>{{ localize('Environment (from Super Admin)', 'البيئة (من المشرف العام)', 'ماحول (سپر ایڈمن سے)') }}</Label>
                                <div class="h-10 w-full rounded-md border border-input bg-muted/30 px-3 py-2 text-sm flex items-center justify-between">
                                    <span>{{ myfatoorahPlatformMode === 'live' ? localize('Live', 'فعلي', 'لائیو') : localize('Test', 'اختبار', 'ٹیسٹ') }}</span>
                                    <span class="text-xs text-muted-foreground">{{ localize('Provider Mode', 'وضع المزود', 'فراہم کنندہ موڈ') }}</span>
                                </div>
                                <p class="text-xs text-muted-foreground">{{ localize('Tenant uses the platform MyFatoorah mode selected by Super Admin.', 'يستخدم المستأجر وضع MyFatoorah المحدد من المشرف العام.', 'کرایہ دار سپر ایڈمن کے منتخب کردہ MyFatoorah موڈ کو استعمال کرتا ہے.') }}</p>
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <Label for="mf_api_token">{{ localize('API Token', 'رمز API', 'API ٹوکن') }}</Label>
                                <Input id="mf_api_token" v-model="form.myfatoorah.api_token" type="password" :placeholder="localize('MyFatoorah token', 'رمز MyFatoorah', 'MyFatoorah ٹوکن')" />
                                <p v-if="form.errors['myfatoorah.api_token']" class="text-sm text-red-600">{{ form.errors['myfatoorah.api_token'] }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="mf_payment_method_id">{{ localize('Payment Method ID (Required for current booking flow)', 'معرّف طريقة الدفع (مطلوب لسير الحجز الحالي)', 'ادائیگی کے طریقہ کار کی آئی ڈی (موجودہ بکنگ فلو کے لیے ضروری)') }}</Label>
                                <Input id="mf_payment_method_id" v-model="form.myfatoorah.payment_method_id" placeholder="2" />
                                <p class="text-xs text-muted-foreground">
                                    {{ localize(
                                        'Use a valid MyFatoorah method ID (example: Visa/Mastercard). We can remove this later when booking methods are loaded dynamically.',
                                        'استخدم معرّف طريقة MyFatoorah صالحًا (مثال: Visa/Mastercard). يمكننا إزالة ذلك لاحقًا عندما تُحمّل طرق الحجز ديناميكيًا.',
                                        'ایک درست MyFatoorah میتھڈ آئی ڈی استعمال کریں (مثال: Visa/Mastercard)۔ جب بکنگ میتھڈز ڈائنامک طور پر لوڈ ہوں گی تو ہم اسے بعد میں ہٹا سکتے ہیں.',
                                    ) }}
                                </p>
                                <p v-if="form.errors['myfatoorah.payment_method_id']" class="text-sm text-red-600">{{ form.errors['myfatoorah.payment_method_id'] }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="mf_webhook_secret">{{ localize('Webhook Secret (Optional)', 'سر Webhook (اختياري)', 'Webhook سیکرٹ (اختیاری)') }}</Label>
                                <Input id="mf_webhook_secret" v-model="form.myfatoorah.webhook_secret" type="password" placeholder="" />
                                <p v-if="form.errors['myfatoorah.webhook_secret']" class="text-sm text-red-600">{{ form.errors['myfatoorah.webhook_secret'] }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label>{{ localize('API Base URL (Auto)', 'رابط API الأساسي (تلقائي)', 'API بیس URL (خودکار)') }}</Label>
                                <div class="h-10 w-full rounded-md border border-input bg-muted/30 px-3 py-2 text-sm font-mono">
                                    {{ myfatoorahAutoBaseUrl }}
                                </div>
                                <p class="text-xs text-muted-foreground">{{ localize('Auto-selected from Super Admin mode (Test/Live).', 'يتم اختياره تلقائيًا من وضع المشرف العام (اختبار/فعلي).', 'سپر ایڈمن موڈ (ٹیسٹ/لائیو) سے خودکار طور پر منتخب شدہ.') }}</p>
                                <p v-if="form.errors['myfatoorah.api_base_url']" class="text-sm text-red-600">{{ form.errors['myfatoorah.api_base_url'] }}</p>
                            </div>
                        </div>

                        <div class="rounded-md border bg-muted/20 p-3 space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-sm font-medium">{{ localize('Advanced MyFatoorah Options', 'خيارات MyFatoorah المتقدمة', 'MyFatoorah کے جدید اختیارات') }}</div>
                                    <p class="text-xs text-muted-foreground">{{ localize('Use only if you need overrides or a fixed default method.', 'استخدمها فقط إذا كنت تحتاج إلى تجاوزات أو طريقة افتراضية ثابتة.', 'صرف اس وقت استعمال کریں جب آپ کو اووررائیڈز یا ایک مقررہ ڈیفالٹ طریقہ درکار ہو.') }}</p>
                                </div>
                                <Button type="button" variant="outline" size="sm" @click="showMyFatoorahAdvanced = !showMyFatoorahAdvanced">
                                    {{ showMyFatoorahAdvanced ? localize('Hide Advanced', 'إخفاء المتقدم', 'جدید چھپائیں') : localize('Show Advanced', 'إظهار المتقدم', 'جدید دکھائیں') }}
                                </Button>
                            </div>

                            <div v-if="showMyFatoorahAdvanced" class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="mf_api_base_url">{{ localize('API Base URL (Override)', 'رابط API الأساسي (تجاوز)', 'API بیس URL (اووررائیڈ)') }}</Label>
                                    <Input id="mf_api_base_url" v-model="form.myfatoorah.api_base_url" :placeholder="localize('Auto from mode', 'تلقائي من الوضع', 'موڈ کے مطابق خودکار')"/>
                                    <p class="text-xs text-muted-foreground">{{ localize('Leave empty to use the automatic URL shown above.', 'اتركه فارغًا لاستخدام الرابط التلقائي المعروض أعلاه.', 'اوپر دکھایا گیا خودکار URL استعمال کرنے کے لیے اسے خالی چھوڑیں.') }}</p>
                                    <p v-if="form.errors['myfatoorah.api_base_url']" class="text-sm text-red-600">{{ form.errors['myfatoorah.api_base_url'] }}</p>
                                </div>

                                <div class="space-y-2 md:col-span-2">
                                    <Label for="mf_callback_url">{{ localize('Callback URL (Optional Override)', 'رابط Callback (تجاوز اختياري)', 'کال بیک URL (اختیاری اووررائیڈ)') }}</Label>
                                    <Input id="mf_callback_url" v-model="form.myfatoorah.callback_url" :placeholder="localize('Auto-generated by booking route', 'يتم إنشاؤه تلقائيًا من مسار الحجز', 'بکنگ روٹ کے ذریعے خودکار طور پر بنائے گئے')"/>
                                    <p v-if="form.errors['myfatoorah.callback_url']" class="text-sm text-red-600">{{ form.errors['myfatoorah.callback_url'] }}</p>
                                </div>

                                <div class="space-y-2 md:col-span-2">
                                    <Label for="mf_error_url">{{ localize('Error URL (Optional Override)', 'رابط الخطأ (تجاوز اختياري)', 'ایرر URL (اختیاری اووررائیڈ)') }}</Label>
                                    <Input id="mf_error_url" v-model="form.myfatoorah.error_url" :placeholder="localize('Auto-generated by booking route', 'يتم إنشاؤه تلقائيًا من مسار الحجز', 'بکنگ روٹ کے ذریعے خودکار طور پر بنائے گئے')"/>
                                    <p v-if="form.errors['myfatoorah.error_url']" class="text-sm text-red-600">{{ form.errors['myfatoorah.error_url'] }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-md border border-dashed p-3 text-xs text-muted-foreground">
                            {{ localize('Notes:', 'ملاحظات:', 'نوٹس:') }}
                            <div>{{ localize('1. Super Admin must enable MyFatoorah in platform Payment Providers first.', '1. يجب على المشرف العام تفعيل MyFatoorah أولًا ضمن موفري الدفع في المنصة.', '1. سپر ایڈمن کو پہلے پلیٹ فارم Payment Providers میں MyFatoorah فعال کرنا ہوگا.') }}</div>
                            <div>{{ localize('2. Use the correct token for the correct environment (Test/Live) configured by Super Admin.', '2. استخدم الرمز الصحيح للبيئة الصحيحة (اختبار/فعلي) التي يحددها المشرف العام.', '2. سپر ایڈمن کے ذریعے منتخب کردہ درست ماحول (ٹیسٹ/لائیو) کے لیے درست ٹوکن استعمال کریں.') }}</div>
                            <div>{{ localize('3. This page currently stores values inside tenant `settings` JSON.', '3. تحفظ هذه الصفحة القيم حاليًا داخل JSON الخاص بـ `settings` للمستأجر.', '3. یہ صفحہ فی الحال کرایہ دار کے `settings` JSON کے اندر اقدار محفوظ کرتا ہے.') }}</div>
                        </div>
                    </div>
                </section>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...', 'محفوظ کیا جا رہا ہے...') : localize('Save Changes', 'حفظ التغييرات', 'تبدیلیاں محفوظ کریں') }}
                    </Button>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
