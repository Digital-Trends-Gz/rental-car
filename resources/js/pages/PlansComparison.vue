<script setup lang="ts">
import SeoHead from '@/components/SeoHead.vue';
import { useTrans } from '@/composables/useTrans';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { register as mainRegister } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Check, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';

type PricingMeta = {
    original_amount?: number | null;
    final_amount?: number | null;
    savings_amount?: number;
    savings_percentage?: number;
    has_discount?: boolean;
    is_custom?: boolean;
};

type Plan = {
    id: number;
    name: string;
    description?: string | null;
    features?: string[] | null;
    feature_flags?: Record<string, boolean> | null;
    custom_pricing?: boolean;
    is_most_value?: boolean;
    monthly_price?: string | number | null;
    max_employees?: number | null;
    max_branches?: number | null;
    max_cars?: number | null;
    max_contracts?: number | null;
    pricing_meta?: Record<string, PricingMeta>;
};

type ComparisonRow = {
    label: string;
    tone?: string;
    values: string[];
};

type ComparisonSection = {
    title: string;
    rows: ComparisonRow[];
};

type PlansPage = {
    enabled?: boolean;
    hero_enabled?: boolean;
    summary_enabled?: boolean;
    comparison_enabled?: boolean;
    addons_enabled?: boolean;
    policy_enabled?: boolean;
    footer_enabled?: boolean;
    hero_badge: string;
    hero_title: string;
    hero_description: string;
    monthly_label: string;
    current_price_label: string;
    official_price_label: string;
    launch_discount_label: string;
    most_value_label: string;
    custom_price_label: string;
    custom_price_caption: string;
    custom_price_badge: string;
    unlimited_label: string;
    table_title: string;
    table_description: string;
    table_note: string;
    feature_column_label: string;
    comparison_sections: ComparisonSection[];
    addons_title: string;
    addons: string[];
    trial_title: string;
    trial_items: string[];
    policy_title: string;
    policy_paragraphs: string[];
    footer_text: string;
};

const props = defineProps<{
    plansPage: PlansPage;
    plans: Plan[];
    landingSettings: Record<string, unknown>;
    seo?: {
        title: string;
        description?: string | null;
        canonical_url?: string | null;
        robots?: string | null;
        og_title?: string | null;
        og_description?: string | null;
        og_image?: string | null;
        alternates?: Array<{ locale: string; url: string }>;
    } | null;
}>();

const page = usePage<any>();
const { t } = useTrans();
const locale = computed(() => String(page.props.locale || 'en'));
const isRtl = computed(() => ['ar', 'ur'].includes(locale.value.toLowerCase().split('-')[0]));
const registerUrl = mainRegister().url;
const visiblePlans = computed(() => (props.plans || []).slice(0, 4));
const showHero = computed(() => props.plansPage.hero_enabled !== false);
const showSummary = computed(() => props.plansPage.summary_enabled !== false);
const showComparison = computed(() => props.plansPage.comparison_enabled !== false);
const showAddons = computed(() => props.plansPage.addons_enabled !== false);
const showPolicy = computed(() => props.plansPage.policy_enabled !== false);
const showFooter = computed(() => props.plansPage.footer_enabled !== false);

const money = (value: unknown) => Number(value || 0).toFixed(2);
const pricingFor = (plan: Plan) => plan.pricing_meta?.monthly || {};
const isPopular = (plan: Plan) => Boolean(plan.is_most_value);
const customPriceBadge = computed(
    () => props.plansPage.custom_price_badge || t('plans_page.custom_pricing_badge'),
);

const formatNumber = (value: number) => new Intl.NumberFormat(locale.value).format(value);

const pluralize = (value: number, singular: string, plural: string) => (value === 1 ? singular : plural);

const limitWithPrefix = (value: number | null | undefined, singular: string, plural: string) =>
    value === null || value === undefined ? `${props.plansPage.unlimited_label} ${plural}` : `Up to ${formatNumber(value)} ${pluralize(value, singular, plural)}`;

const bookingLimit = (value: number | null | undefined) =>
    value === null || value === undefined ? `${props.plansPage.unlimited_label} bookings` : `${formatNumber(value)} monthly bookings`;

const branchLimit = (value: number | null | undefined) => {
    if (value === null || value === undefined) {
        return 'Multiple branches';
    }

    if (value === 1) {
        return 'One branch';
    }

    if (value === 2) {
        return 'Two branches';
    }

    return `Up to ${formatNumber(value)} branches`;
};

const comparisonValue = (planIndex: number, rowIndex: number) => props.plansPage.comparison_sections?.[0]?.rows?.[rowIndex]?.values?.[planIndex];

const comparisonLimit = (planIndex: number, rowIndex: number, suffix: string) => {
    const value = comparisonValue(planIndex, rowIndex);

    if (!value) {
        return null;
    }

    if (value.toLowerCase().includes('unlimited')) {
        return `${props.plansPage.unlimited_label} ${suffix}`;
    }

    return `${value} ${suffix}`;
};

const comparisonBranchLimit = (planIndex: number) => {
    const value = comparisonValue(planIndex, 3);

    if (!value) {
        return null;
    }

    if (value.toLowerCase().includes('unlimited')) {
        return 'Multiple branches';
    }

    return value;
};

const planLimits = (plan: Plan, planIndex: number) => {
    const explicitFeatures = (plan.features || [])
        .map((feature) => String(feature || '').trim())
        .filter((feature) => feature !== '');
    const onlyGenericUnlimited =
        explicitFeatures.length > 0 &&
        explicitFeatures.every((feature) => feature.toLowerCase() === 'unlimited');

    if (explicitFeatures.length > 0 && !onlyGenericUnlimited) {
        return explicitFeatures;
    }

    return [
        comparisonLimit(planIndex, 0, 'cars') || limitWithPrefix(plan.max_cars, 'car', 'cars'),
        comparisonLimit(planIndex, 1, 'users') || limitWithPrefix(plan.max_employees, 'user', 'users'),
        comparisonLimit(planIndex, 2, 'monthly bookings') || bookingLimit(plan.max_contracts),
        comparisonBranchLimit(planIndex) || branchLimit(plan.max_branches),
    ];
};

const discountLabel = (plan: Plan) => {
    const percentage = Math.round(Number(pricingFor(plan).savings_percentage || 0));

    if (percentage <= 0) {
        return props.plansPage.current_price_label;
    }

    return isRtl.value ? `${t('landing.discount_off')} ${percentage}%` : `${percentage}% ${t('landing.discount_off')}`;
};

const cellClass = (value: string) => {
    const normalized = String(value || '').toLowerCase();

    if (['yes', '✓'].includes(normalized) || normalized.startsWith('yes ')) {
        return 'text-emerald-600 font-black';
    }

    if (['no', 'x', '×'].includes(normalized)) {
        return 'text-red-500 font-black';
    }

    if (normalized.includes('limited') || normalized.includes('basic') || normalized.includes('add-on') || normalized.includes('simple')) {
        return 'text-amber-600 font-extrabold';
    }

    if (normalized.includes('custom') || normalized.includes('contract') || normalized.includes('unlimited')) {
        return 'text-blue-800 font-black';
    }

    return 'text-slate-700';
};
</script>

<template>
    <SeoHead v-if="seo" :seo="seo" />
    <Head v-else :title="plansPage.hero_title || 'Plans'" />

    <HomeLayout shell-variant="landing">
        <main class="plans-comparison bg-[#f5f7fb] text-slate-950" :dir="isRtl ? 'rtl' : 'ltr'">
            <div class="mx-auto max-w-7xl px-4 py-14 md:py-16">
                <section v-if="showHero" class="mx-auto max-w-4xl text-center">
                    <div class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-4 py-2 text-sm font-extrabold text-blue-700 ring-1 ring-blue-100">
                        <Sparkles class="h-4 w-4" />
                            {{ plansPage.hero_badge }}
                    </div>
                    <h1 class="mt-5 text-4xl font-black tracking-normal text-slate-950 sm:text-5xl">
                        {{ plansPage.hero_title }}
                    </h1>
                    <p class="mx-auto mt-4 max-w-3xl text-base leading-8 text-slate-500">
                        {{ plansPage.hero_description }}
                    </p>
                </section>

                <section v-if="showSummary" class="mt-10 grid items-stretch gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <article
                        v-for="(plan, planIndex) in visiblePlans"
                        :key="plan.id"
                        class="relative flex h-full min-h-[560px] flex-col overflow-hidden rounded-xl border bg-white p-6 shadow-[0_10px_28px_rgba(15,23,42,0.08)] sm:p-7"
                        :class="isPopular(plan) ? 'border-2 border-blue-600' : 'border-slate-200'"
                    >
                        <div class="min-h-[108px]">
                            <h2 class="text-xl font-bold leading-tight text-slate-950" :class="isPopular(plan) ? 'ltr:pr-32 rtl:pl-32' : ''">
                                {{ plan.name }}
                            </h2>
                            <span
                                v-if="isPopular(plan)"
                                class="absolute top-7 inline-flex rounded-full bg-blue-600/10 px-4 py-2 text-xs font-bold leading-none text-blue-700 ring-1 ring-blue-600/15 ltr:right-7 rtl:left-7"
                            >
                                {{ plansPage.most_value_label }}
                            </span>
                            <p class="mt-2 text-base leading-7 text-slate-500">{{ plan.description }}</p>
                        </div>

                        <div v-if="plan.custom_pricing" class="mb-6 min-h-[135px]">
                            <span class="mb-4 inline-flex min-w-max shrink-0 whitespace-nowrap rounded-full bg-emerald-100 px-4 py-2 text-[10px] font-bold leading-none text-emerald-800">
                                {{ customPriceBadge }}
                            </span>
                            <div class="text-[14px] font-normal leading-5 text-[#737373]">{{ plansPage.custom_price_label }}</div>
                            <p class="mt-5 text-[13px] font-medium text-[#737373]">{{ plansPage.custom_price_caption }}</p>
                        </div>

                        <div v-else class="mb-6 min-h-[135px]">
                            <span
                                v-if="pricingFor(plan).has_discount"
                                class="mb-3 inline-flex rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-bold leading-none text-emerald-700"
                            >
                                {{ discountLabel(plan) }}
                            </span>
                            <div class="flex items-end gap-2">
                                <span class="text-5xl font-extrabold tracking-tight text-slate-950">${{ money(pricingFor(plan).final_amount) }}</span>
                                <span class="pb-1 text-base text-slate-500">/{{ t('landing.monthly') }}</span>
                            </div>
                            <p v-if="pricingFor(plan).has_discount && pricingFor(plan).original_amount" class="mt-2 text-base text-slate-500">
                                <span class="line-through">${{ money(pricingFor(plan).original_amount || plan.monthly_price) }}</span>
                                <span class="ms-2 font-medium text-emerald-700">{{ t('landing.discount_save') }} ${{ money(pricingFor(plan).savings_amount || 0) }}</span>
                            </p>
                        </div>

                        <ul class="mb-8 flex-1 space-y-4">
                            <li v-for="limit in planLimits(plan, planIndex)" :key="`${plan.id}-${limit}`" class="flex items-start gap-3 text-base text-slate-500">
                                <Check :size="18" class="mt-0.5 shrink-0 text-slate-950" />
                                <span>{{ limit }}</span>
                            </li>
                        </ul>

                        <Link
                            :href="registerUrl"
                            class="inline-flex h-12 w-full items-center justify-center rounded-2xl bg-gradient-to-r from-blue-500 to-purple-700 px-6 text-base font-semibold text-white shadow-lg shadow-blue-600/15 transition hover:translate-y-[-1px] hover:shadow-xl"
                        >
                            {{ t('nav.get_started') }}
                        </Link>
                    </article>
                </section>

                <section v-if="showComparison" id="compare" class="mt-8 overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-xl shadow-slate-900/5">
                    <div class="flex flex-col gap-4 border-b border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-2xl font-black">{{ plansPage.table_title }}</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-500">{{ plansPage.table_description }}</p>
                        </div>
                        <span class="w-fit rounded-full border border-orange-200 bg-orange-50 px-4 py-2 text-sm font-bold text-orange-800">
                            {{ plansPage.table_note }}
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[980px] border-collapse text-sm">
                            <thead>
                                <tr>
                                    <th class="w-64 border-b border-slate-200 bg-slate-50 p-4 text-start text-slate-900">{{ plansPage.feature_column_label }}</th>
                                    <th v-for="plan in visiblePlans" :key="plan.id" class="border-b border-slate-200 bg-slate-50 p-4 text-center text-slate-900">
                                        <div class="font-black">{{ plan.name }}</div>
                                        <small v-if="plan.custom_pricing">{{ plansPage.custom_price_label }}</small>
                                        <small v-else>${{ money(pricingFor(plan).final_amount) }} {{ plansPage.current_price_label }}</small>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="section in plansPage.comparison_sections" :key="section.title">
                                    <tr>
                                        <td :colspan="visiblePlans.length + 1" class="bg-blue-50 p-4 text-center text-base font-black text-blue-900">
                                            {{ section.title }}
                                        </td>
                                    </tr>
                                    <tr v-for="row in section.rows" :key="`${section.title}-${row.label}`">
                                        <td class="border-b border-slate-200 bg-slate-50/60 p-4 text-start font-bold text-slate-800">{{ row.label }}</td>
                                        <td
                                            v-for="(plan, index) in visiblePlans"
                                            :key="`${row.label}-${plan.id}`"
                                            class="border-b border-slate-200 p-4 text-center"
                                            :class="cellClass(row.values?.[index] || '')"
                                        >
                                            {{ row.values?.[index] || '-' }}
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section v-if="showAddons" class="mt-8 grid gap-5 md:grid-cols-2">
                    <div class="rounded-[1.375rem] border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5">
                        <h3 class="text-2xl font-black">{{ plansPage.addons_title }}</h3>
                        <ul class="mt-4 space-y-3 text-slate-700">
                            <li v-for="item in plansPage.addons" :key="item" class="flex gap-2">
                                <Check class="mt-1 h-4 w-4 shrink-0 text-blue-600" />
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </div>
                    <div class="rounded-[1.375rem] border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5">
                        <h3 class="text-2xl font-black">{{ plansPage.trial_title }}</h3>
                        <ul class="mt-4 space-y-3 text-slate-700">
                            <li v-for="item in plansPage.trial_items" :key="item" class="flex gap-2">
                                <Check class="mt-1 h-4 w-4 shrink-0 text-blue-600" />
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </div>
                </section>

                <section v-if="showPolicy" class="mt-8 rounded-[1.75rem] bg-gradient-to-br from-slate-950 to-blue-900 p-8 text-white shadow-xl shadow-slate-900/10">
                    <h3 class="text-2xl font-black">{{ plansPage.policy_title }}</h3>
                    <p v-for="paragraph in plansPage.policy_paragraphs" :key="paragraph" class="mt-3 leading-8 text-white/85">
                        {{ paragraph }}
                    </p>
                    <Link :href="registerUrl" class="mt-6 inline-flex h-12 items-center justify-center rounded-md bg-white px-6 text-sm font-extrabold text-blue-700 shadow-lg transition hover:bg-white/90">
                        {{ plansPage.current_price_label }}
                    </Link>
                </section>

                <p v-if="showFooter" class="mt-7 text-center text-sm text-slate-500">
                    {{ plansPage.footer_text }}
                </p>
            </div>
        </main>
    </HomeLayout>
</template>
