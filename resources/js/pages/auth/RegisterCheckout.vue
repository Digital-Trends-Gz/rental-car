<script setup lang="ts">
import AuthLanguageSwitcher from '@/components/AuthLanguageSwitcher.vue';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface PlanSummary {
    id: number;
    name: string;
    description: string | null;
    features: string[];
    pricing_meta?: Record<
        string,
        {
            original_amount: number | null;
            final_amount: number | null;
            savings_amount: number;
            savings_percentage: number;
            has_discount: boolean;
        }
    >;
}

interface RegistrationSummary {
    name: string;
    email: string;
    phone: string | null;
    custom_domain: string | null;
}

interface PaymentProviderOption {
    code: string;
    name: string;
    mode: 'test' | 'live';
    is_default: boolean;
    description?: string | null;
}

interface ProviderPaymentMethodOption {
    id: number;
    name: string;
    name_ar?: string | null;
    name_en?: string | null;
    is_direct?: boolean;
    service_charge?: number | null;
    total_amount?: number | null;
    currency?: string | null;
    image_url?: string | null;
}

const { t, locale } = useTrans();

const props = defineProps<{
    registration: RegistrationSummary;
    plan: PlanSummary;
    billingCycle: 'monthly' | 'yearly' | 'one_time';
    amount: number;
    originalAmount: number;
    currencyCode: string;
    urls: {
        register: string;
        plans: string;
        checkoutStore: string;
    };
    paymentProviders?: PaymentProviderOption[];
    selectedPaymentProvider?: string | null;
    providerPaymentMethods?: Record<string, ProviderPaymentMethodOption[]>;
    selectedProviderPaymentMethodId?: number | null;
}>();

const form = useForm<{
    accept_terms: boolean;
    payment_provider_code: string;
    payment_method_id: number | null;
}>({
    accept_terms: false,
    payment_provider_code:
        props.selectedPaymentProvider ||
        props.paymentProviders?.[0]?.code ||
        'stripe',
    payment_method_id: props.selectedProviderPaymentMethodId ?? null,
});

const billingCycleLabel = computed(() => {
    if (props.billingCycle === 'yearly') {
        return t('plans_page.yearly');
    }

    if (props.billingCycle === 'one_time') {
        return t('plans_page.one_time');
    }

    return t('plans_page.monthly');
});

const submit = () => {
    form.post(props.urls.checkoutStore);
};

const activePricing = computed(() => {
    return props.plan.pricing_meta?.[props.billingCycle] || null;
});



const selectedProvider = computed(
    () =>
        (props.paymentProviders || []).find(
            (provider) => provider.code === form.payment_provider_code,
        ) || null,
);

const selectedProviderMethods = computed(() => {
    if (!form.payment_provider_code) return [];
    return (props.providerPaymentMethods?.[form.payment_provider_code] ||
        []) as ProviderPaymentMethodOption[];
});

watch(
    () => form.payment_provider_code,
    (providerCode) => {
        if (providerCode !== 'myfatoorah') {
            form.payment_method_id = null;
            return;
        }

        const methods = (props.providerPaymentMethods?.myfatoorah ||
            []) as ProviderPaymentMethodOption[];
        if (methods.length === 0) {
            return;
        }

        const currentId = Number(form.payment_method_id || 0);
        if (!methods.some((method) => method.id === currentId)) {
            form.payment_method_id = methods[0].id;
        }
    },
    { immediate: true },
);

const errorMessages = computed(() => {
    return Object.values(form.errors).filter(
        (message): message is string =>
            typeof message === 'string' && message.length > 0,
    );
});
</script>

<template>
    <Head :title="t('checkout.title')" />

    <main class="relative min-h-screen bg-slate-50 py-10">
        <div class="absolute top-4 z-50 ltr:right-4 rtl:left-4">
            <AuthLanguageSwitcher />
        </div>

        <div class="mx-auto max-w-5xl px-4">
            <div class="mb-6">
                <Link
                    :href="urls.plans"
                    class="text-sm font-medium text-slate-700 hover:underline"
                >
                    {{ t('checkout.back_to_plans') }}
                </Link>
            </div>

            <div class="mb-8">
                <p class="text-sm font-semibold text-blue-700">
                    {{ t('checkout.step_3_of_3') }}
                </p>
                <h1 class="mt-2 text-3xl font-bold text-slate-900">
                    {{ t('checkout.complete_payment') }}
                </h1>
                <p class="mt-2 text-slate-600">
                    {{ t('checkout.complete_payment_subtitle') }}
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-5">
                <div class="space-y-6 lg:col-span-3">
                    <section class="rounded-2xl border bg-white p-5">
                        <h2 class="mb-4 text-lg font-semibold text-slate-900">
                            {{ t('checkout.company_details') }}
                        </h2>
                        <div class="space-y-2 text-sm">
                            <p>
                                <span class="font-semibold"
                                    >{{ t('checkout.company') }}:</span
                                >
                                {{ registration.name }}
                            </p>
                            <p>
                                <span class="font-semibold"
                                    >{{ t('checkout.email') }}:</span
                                >
                                {{ registration.email }}
                            </p>
                            <p v-if="registration.phone">
                                <span class="font-semibold"
                                    >{{ t('checkout.phone') }}:</span
                                >
                                {{ registration.phone }}
                            </p>
                            <p v-if="registration.custom_domain">
                                <span class="font-semibold"
                                    >{{ t('checkout.custom_domain') }}:</span
                                >
                                {{ registration.custom_domain }}
                            </p>
                        </div>
                        <div class="mt-4">
                            <Link
                                :href="urls.register"
                                class="text-sm font-medium text-blue-700 hover:underline"
                            >
                                {{ t('checkout.edit_details') }}
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-2xl border bg-white p-5">
                        <h2 class="mb-4 text-lg font-semibold text-slate-900">
                            {{ t('checkout.payment_checkout') }}
                        </h2>
                        <form @submit.prevent="submit" class="space-y-4">
                            <div
                                v-if="errorMessages.length"
                                class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                            >
                                <p
                                    v-for="(message, index) in errorMessages"
                                    :key="`checkout-error-${index}`"
                                >
                                    {{ message }}
                                </p>
                            </div>

                            <div
                                v-if="(paymentProviders || []).length > 0"
                                class="space-y-2"
                            >
                                <label
                                    class="text-sm font-medium text-slate-900"
                                    >{{ t('checkout.payment_provider') }}</label
                                >
                                <div class="grid gap-2">
                                    <label
                                        v-for="provider in paymentProviders ||
                                        []"
                                        :key="provider.code"
                                        class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition"
                                        :class="
                                            form.payment_provider_code ===
                                            provider.code
                                                ? 'border-blue-500 bg-blue-50/50'
                                                : 'border-slate-200'
                                        "
                                    >
                                        <input
                                            v-model="form.payment_provider_code"
                                            :value="provider.code"
                                            type="radio"
                                            class="mt-1"
                                        />
                                        <div>
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="font-medium text-slate-900"
                                                    >{{ provider.name }}</span
                                                >
                                                <span
                                                    class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600 uppercase"
                                                >
                                                    {{ provider.mode }}
                                                </span>
                                                <span
                                                    v-if="provider.is_default"
                                                    class="rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-700"
                                                >
                                                    {{ t('checkout.default') }}
                                                </span>
                                            </div>
                                            <p
                                                v-if="provider.description"
                                                class="text-xs text-slate-500"
                                            >
                                                {{ provider.description }}
                                            </p>
                                        </div>
                                    </label>
                                </div>
                                <p
                                    v-if="form.errors.payment_provider_code"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.payment_provider_code }}
                                </p>
                            </div>

                            <div
                                v-if="
                                    form.payment_provider_code === 'myfatoorah'
                                "
                                class="space-y-2"
                            >
                                <label
                                    class="text-sm font-medium text-slate-900"
                                >
                                    {{ t('checkout.payment_method_mf') }}
                                </label>

                                <div
                                    v-if="selectedProviderMethods.length > 0"
                                    class="grid gap-2"
                                >
                                    <label
                                        v-for="method in selectedProviderMethods"
                                        :key="`mf-method-${method.id}`"
                                        class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition"
                                        :class="
                                            Number(form.payment_method_id) ===
                                            method.id
                                                ? 'border-blue-500 bg-blue-50/50'
                                                : 'border-slate-200'
                                        "
                                    >
                                        <input
                                            v-model="form.payment_method_id"
                                            :value="method.id"
                                            type="radio"
                                            class="mt-1"
                                        />
                                        <div class="flex-1">
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <img
                                                    v-if="method.image_url"
                                                    :src="method.image_url"
                                                    :alt="method.name"
                                                    class="h-5 w-5 rounded object-contain"
                                                />
                                                <span
                                                    class="font-medium text-slate-900"
                                                    >{{ method.name }}</span
                                                >
                                                <span
                                                    v-if="method.is_direct"
                                                    class="rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700"
                                                >
                                                    {{ t('checkout.direct') }}
                                                </span>
                                            </div>
                                            <p
                                                v-if="
                                                    method.total_amount != null
                                                "
                                                class="text-xs text-slate-500"
                                            >
                                                {{ t('checkout.total') }}:
                                                {{
                                                    method.currency ||
                                                    currencyCode
                                                }}
                                                {{
                                                    Number(
                                                        method.total_amount,
                                                    ).toFixed(2)
                                                }}
                                            </p>
                                        </div>
                                    </label>
                                </div>

                                <p
                                    v-else
                                    class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700"
                                >
                                    {{ t('checkout.myfatoorah_error') }}
                                </p>

                                <p
                                    v-if="form.errors.payment_method_id"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.payment_method_id }}
                                </p>
                            </div>

                            <p class="text-sm text-slate-600">
                                {{ t('checkout.redirect_notice') }}
                            </p>

                            <label class="mt-3 flex items-start gap-2 text-sm">
                                <input
                                    v-model="form.accept_terms"
                                    type="checkbox"
                                    class="mt-0.5"
                                />
                                <span>{{ t('checkout.terms_confirm') }}</span>
                            </label>
                            <p
                                v-if="form.errors.accept_terms"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.accept_terms }}
                            </p>

                            <Button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full"
                            >
                                {{
                                    form.processing
                                        ? t('checkout.redirecting')
                                        : t('checkout.continue_to', {
                                              provider:
                                                  selectedProvider?.name ||
                                                  t(
                                                      'checkout.payment_provider',
                                                  ),
                                          })
                                }}
                            </Button>
                        </form>
                    </section>
                </div>

                <aside class="lg:col-span-2">
                    <section class="rounded-2xl border bg-white p-5">
                        <h2 class="mb-4 text-lg font-semibold text-slate-900">
                            {{ t('checkout.order_summary') }}
                        </h2>
                        <p class="text-sm text-slate-500">{{ plan.name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ billingCycleLabel }}
                        </p>
                        <p class="mt-2 text-sm text-slate-700">
                            {{ plan.description }}
                        </p>

                        <ul class="mt-4 space-y-2 text-sm text-slate-700">
                            <li v-for="feature in plan.features" :key="feature">
                                - {{ feature }}
                            </li>
                        </ul>

                        <div class="mt-6 border-t pt-4">
                            <p class="text-sm text-slate-500">
                                {{ t('checkout.total') }}
                            </p>
                            <p
                                v-if="
                                    activePricing?.has_discount &&
                                    originalAmount > amount
                                "
                                class="mb-1 text-sm text-slate-400 line-through"
                            >
                                {{ currencyCode }}
                                {{ Number(originalAmount).toFixed(2) }}
                            </p>
                            <p class="text-3xl font-bold text-slate-900">
                                {{ currencyCode }}
                                {{ Number(amount).toFixed(2) }}
                            </p>
                            <p
                                v-if="activePricing?.has_discount"
                                class="mt-2 inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700"
                            >
                                {{ t('landing.discount_save') }}&nbsp;{{
                                    Number(
                                        activePricing.savings_amount,
                                    ).toFixed(2)
                                }}
                                (<template v-if="locale === 'ar'">
                                    {{ t('landing.discount_off') }}
                                    {{
                                        Math.round(
                                            Number(
                                                activePricing.savings_percentage,
                                            ),
                                        )
                                    }}%
                                </template>
                                <template v-else>
                                    {{
                                        Math.round(
                                            Number(
                                                activePricing.savings_percentage,
                                            ),
                                        )
                                    }}% {{ t('landing.discount_off') }}
                                </template>)
                            </p>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </main>
</template>
