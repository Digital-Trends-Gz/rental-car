<script setup lang="ts">
import AuthLanguageSwitcher from '@/components/AuthLanguageSwitcher.vue';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type BillingCycle = 'monthly' | 'yearly' | 'one_time';

interface PlanOption {
    id: number;
    name: string;
    description: string | null;
    features: string[] | null;
    custom_pricing: boolean;
    monthly_price: number | string;
    monthly_price_id: string | null;
    yearly_price: number | string;
    yearly_price_id: string | null;
    one_time_price: number | string | null;
    one_time_price_id: string | null;
    pricing_meta?: {
        monthly?: PricingMeta;
        yearly?: PricingMeta;
        one_time?: PricingMeta;
    };
}

interface PricingMeta {
    original_amount: number | null;
    final_amount: number | null;
    savings_amount: number;
    savings_percentage: number;
    has_discount: boolean;
    discount: {
        name: string;
        type: 'percentage' | 'fixed';
        value: number;
    } | null;
}

const { t, locale, direction } = useTrans();

const props = defineProps<{
    plans: PlanOption[];
    selection: {
        plan_id: number | null;
        billing_cycle: BillingCycle;
    };
    urls: {
        register: string;
        plansStore: string;
        checkout: string;
    };
}>();

const form = useForm<{
    plan_id: number | null;
    billing_cycle: BillingCycle;
}>({
    plan_id: props.selection.plan_id ?? props.plans[0]?.id ?? null,
    billing_cycle: props.selection.billing_cycle ?? 'monthly',
});

const selectedPlan = computed(() => {
    return props.plans.find((plan) => plan.id === form.plan_id) ?? null;
});

const isRtl = computed(() => direction.value === 'rtl');

const isSelectedPlanCustom = computed(() => Boolean(selectedPlan.value?.custom_pricing));

const customPricingLabel = computed(() =>
    locale.value.toLowerCase().startsWith('ar')
        ? 'مخصص'
        : t('plans_page.custom_pricing_label'),
);

const customPricingHelp = computed(() =>
    locale.value.toLowerCase().startsWith('ar')
        ? 'هذه الخطة بتسعير مخصص. يرجى التواصل معنا للمتابعة.'
        : t('plans_page.custom_pricing_help'),
);

const priceFor = (plan: PlanOption): number => {
    const pricing = pricingFor(plan);
    if (typeof pricing?.final_amount === 'number') {
        return pricing.final_amount;
    }

    if (form.billing_cycle === 'yearly') {
        return Number(plan.yearly_price);
    }

    if (form.billing_cycle === 'one_time') {
        return Number(plan.one_time_price ?? plan.monthly_price);
    }

    return Number(plan.monthly_price);
};

const pricingFor = (plan: PlanOption): PricingMeta | undefined => {
    return plan.pricing_meta?.[form.billing_cycle];
};



const supportsCycle = (plan: PlanOption, cycle: BillingCycle): boolean => {
    if (plan.custom_pricing) {
        return true;
    }

    if (cycle === 'monthly') {
        return Number(plan.monthly_price) > 0;
    }

    if (cycle === 'yearly') {
        return Number(plan.yearly_price) > 0;
    }

    if (cycle === 'one_time') {
        return plan.one_time_price !== null && Number(plan.one_time_price) > 0;
    }

    return true;
};

const submit = () => {
    if (isSelectedPlanCustom.value) {
        return;
    }

    form.post(props.urls.plansStore);
};
</script>

<template>
    <Head :title="t('plans_page.choose_plan')" />

    <main
        class="relative min-h-screen bg-slate-50 py-10"
        :dir="direction"
    >
        <div class="absolute top-4 z-50 ltr:right-4 rtl:left-4">
            <AuthLanguageSwitcher />
        </div>

        <div class="mx-auto max-w-6xl px-4">
            <div class="mb-6 text-start">
                <Link
                    :href="urls.register"
                    class="text-sm font-medium text-slate-700 hover:underline"
                >
                    {{ t('plans_page.back_to_registration') }}
                </Link>
            </div>

            <div class="mb-8 text-start">
                <p class="text-sm font-semibold text-blue-700">
                    {{ t('plans_page.step_2_of_3') }}
                </p>
                <h1 class="mt-2 text-3xl font-bold text-slate-900">
                    {{ t('plans_page.choose_your_plan') }}
                </h1>
                <p class="mt-2 text-slate-600">
                    {{ t('plans_page.select_plan_subtitle') }}
                </p>
            </div>

            <form @submit.prevent="submit">
                <div class="mb-8 rounded-2xl border bg-white p-5 text-start">
                    <p class="mb-3 text-sm font-semibold text-slate-800">
                        {{ t('plans_page.billing_cycle') }}
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <label
                            class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm"
                        >
                            <input
                                v-model="form.billing_cycle"
                                type="radio"
                                value="monthly"
                            />
                            {{ t('plans_page.monthly') }}
                        </label>
                        <label
                            class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm"
                        >
                            <input
                                v-model="form.billing_cycle"
                                type="radio"
                                value="yearly"
                            />
                            {{ t('plans_page.yearly') }}
                        </label>
                        <label
                            class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm"
                        >
                            <input
                                v-model="form.billing_cycle"
                                type="radio"
                                value="one_time"
                            />
                            {{ t('plans_page.one_time') }}
                        </label>
                    </div>
                    <p
                        v-if="form.errors.billing_cycle"
                        class="mt-2 text-sm text-red-600"
                    >
                        {{ form.errors.billing_cycle }}
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <button
                        v-for="plan in plans"
                        :key="plan.id"
                        type="button"
                        class="rounded-2xl border bg-white p-5 text-start shadow-sm"
                        :class="[
                            form.plan_id === plan.id
                                ? 'border-blue-600 ring-2 ring-blue-200'
                                : 'border-slate-200',
                            !supportsCycle(plan, form.billing_cycle)
                                ? 'opacity-50'
                                : '',
                        ]"
                        @click="
                            form.plan_id = supportsCycle(
                                plan,
                                form.billing_cycle,
                            )
                                ? plan.id
                                : form.plan_id
                        "
                    >
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-slate-900">
                                {{ plan.name }}
                            </h2>
                            <span
                                v-if="form.plan_id === plan.id"
                                class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700"
                            >
                                {{ t('plans_page.selected') }}
                            </span>
                        </div>
                        <p class="mb-4 min-h-10 text-sm text-slate-600">
                            {{ plan.description }}
                        </p>
                        <div class="mb-4">
                            <div
                                v-if="!plan.custom_pricing && pricingFor(plan)?.has_discount"
                                class="mb-2 inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700"
                            >
                                <template v-if="locale === 'ar'">
                                    {{ t('landing.discount_off') }}
                                    {{
                                        Math.round(
                                            pricingFor(plan)?.savings_percentage ||
                                                0,
                                        )
                                    }}%
                                </template>
                                <template v-else>
                                    {{
                                        Math.round(
                                            pricingFor(plan)?.savings_percentage ||
                                                0,
                                        )
                                    }}% {{ t('landing.discount_off') }}
                                </template>
                            </div>
                            <div v-if="plan.custom_pricing" class="space-y-1">
                                <p class="inline-block select-none bg-gradient-to-r from-blue-600 to-violet-600 bg-clip-text text-4xl font-extrabold text-transparent">
                                    {{ customPricingLabel }}
                                </p>
                            </div>
                            <div v-else class="flex items-end gap-2">
                                <p class="text-3xl font-bold text-slate-900">
                                    ${{ priceFor(plan).toFixed(2) }}
                                </p>
                                <p
                                    v-if="
                                        pricingFor(plan)?.has_discount &&
                                        pricingFor(plan)?.original_amount
                                    "
                                    class="pb-1 text-sm text-slate-400 line-through"
                                >
                                    ${{
                                        Number(
                                            pricingFor(plan)?.original_amount ||
                                                0,
                                        ).toFixed(2)
                                    }}
                                </p>
                            </div>
                            <p
                                v-if="!plan.custom_pricing && pricingFor(plan)?.has_discount"
                                class="mt-1 text-xs font-medium text-emerald-700"
                            >
                                <template v-if="isRtl">
                                    {{ t('landing.discount_save') }}&nbsp;{{
                                        Number(
                                            pricingFor(plan)?.savings_amount || 0,
                                        ).toFixed(2)
                                    }}$
                                </template>
                                <template v-else>
                                    {{ t('landing.discount_save') }}&nbsp;${{
                                        Number(
                                            pricingFor(plan)?.savings_amount || 0,
                                        ).toFixed(2)
                                    }}
                                </template>
                                <span v-if="pricingFor(plan)?.discount?.name"
                                    >&nbsp;{{ t('landing.discount_with') }}&nbsp;{{ pricingFor(plan)?.discount?.name }}</span
                                >
                            </p>
                        </div>

                        <ul class="space-y-2 text-sm text-slate-700">
                            <li
                                v-for="feature in plan.features || []"
                                :key="feature"
                            >
                                - {{ feature }}
                            </li>
                        </ul>

                        <p
                            v-if="!supportsCycle(plan, form.billing_cycle)"
                            class="mt-4 text-xs font-medium text-amber-700"
                        >
                            {{ t('plans_page.not_available') }}
                        </p>
                    </button>
                </div>

                <p v-if="form.errors.plan_id" class="mt-3 text-start text-sm text-red-600">
                    {{ form.errors.plan_id }}
                </p>

                <div class="mt-8 flex items-center gap-3">
                    <Button
                        type="submit"
                        :disabled="form.processing || !selectedPlan || isSelectedPlanCustom"
                    >
                        {{
                            form.processing
                                ? t('plans_page.saving')
                                : t('plans_page.continue_to_payment')
                        }}
                    </Button>
                    <Link
                        :href="urls.register"
                        class="text-sm font-medium text-slate-700 hover:underline"
                    >
                        {{ t('plans_page.edit_details') }}
                    </Link>
                </div>
                <p v-if="isSelectedPlanCustom" class="mt-3 text-start text-sm text-amber-700">
                    {{ customPricingHelp }}
                </p>
            </form>
        </div>
    </main>
</template>
