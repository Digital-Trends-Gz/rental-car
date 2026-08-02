<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Brain,
    CalendarDays,
    Car,
    DollarSign,
    FileWarning,
    RefreshCcw,
    Save,
    TrendingUp,
    UserRound,
    FileDown,
    TrendingDown,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useTrans } from '@/composables/useTrans';

type Severity = 'danger' | 'warning' | 'info' | 'success';

type Summary = {
    critical_count: number;
    unprofitable_cars_count: number;
    repeated_damage_cars_count: number;
    high_risk_customers_count: number;
    problem_contracts_count: number;
    pricing_opportunities_count: number;
    uncollected_losses: number | string;
    formatted_uncollected_losses: string;
};

type CarInsight = {
    car_id: number;
    car_name: string;
    license_plate?: string | null;
    formatted_revenue?: string;
    formatted_costs?: string;
    formatted_net_profit?: string;
    profit_margin?: number;
    utilization_days?: number;
    damage_reports_count?: number;
    damage_items_count?: number;
    accidents_count?: number;
    recommendation: string;
};

type CustomerInsight = {
    customer_id: number;
    name: string;
    email?: string | null;
    score: number;
    severity: Severity;
    reservations_count: number;
    overdue_contracts_count: number;
    damage_reports_count: number;
    formatted_unpaid_amount: string;
    recommendation: string;
};

type PriceOpportunity = {
    car_id: number;
    car_name: string;
    license_plate?: string | null;
    formatted_current_price: string;
    suggested_increase_percent: number;
    utilization_days: number;
    profit_margin: number;
    recommendation: string;
};

type DemandDay = {
    day: string;
    reservations_count: number;
    rental_days: number;
    recommendation: string;
};

type LossItem = {
    key: string;
    label: string;
    formatted_amount: string;
};

type ProblemContract = {
    contract_id: number;
    contract_number: string;
    customer_name?: string | null;
    car_name?: string | null;
    end_date?: string | null;
    days_late: number;
    score: number;
    severity: Severity;
    formatted_unpaid_return_charges: string;
    recommendation: string;
};

type InsightsPayload = {
    generated_at: string;
    period: { start: string; end: string };
    summary: Summary;
    unprofitable_cars: CarInsight[];
    repeated_damage_cars: CarInsight[];
    high_risk_customers: CustomerInsight[];
    price_opportunities: PriceOpportunity[];
    demand_days: DemandDay[];
    uncollected_losses: LossItem[];
    problem_contracts: ProblemContract[];
    market_study: {
        status: string;
        title: string;
        description: string;
    };
};

type SavedReport = {
    id: number;
    period: string;
    locale?: string | null;
    period_start?: string | null;
    period_end?: string | null;
    status: string;
    provider?: string | null;
    model?: string | null;
    branch_name?: string | null;
    created_by_name?: string | null;
    generated_at?: string | null;
    completed_at?: string | null;
    created_at?: string | null;
    has_ai_result: boolean;
    error_message?: string | null;
    ai_result?: {
        language?: string;
        executive_summary: string;
        market_summary: string;
        risk_level: string;
        risks: AiResultItem[];
        opportunities: AiResultItem[];
        pricing_recommendations: AiResultItem[];
        collection_actions: AiResultItem[];
        action_plan: Array<{ priority: string; action: string; owner: string; metric_to_watch: string }>;
        sources: Array<{ title: string; url: string }>;
    } | null;
};

type AiResultItem = {
    title: string;
    severity: string;
    reason: string;
    recommendation: string;
    expected_impact: string;
};

const props = defineProps<{
    insights: InsightsPayload;
    latestReport?: SavedReport | null;
    savedReports: SavedReport[];
    currentPeriod: string;
    periodOptions: Array<{ value: string; label: string }>;
    branches: Array<{ id: number; name: string }>;
    canAccessAllBranches: boolean;
    selectedBranchId?: number | null;
    canViewFinancials: boolean;
    openAiStatus: { connected: boolean; phase: string };
    mom: {
        critical_change: number;
        customers_change: number;
        pricing_change: number;
        losses_change_percent: number | null;
    };
}>();

const page = usePage<any>();
const { t } = useTrans();
const selectedPeriod = ref(props.currentPeriod);
const selectedBranchId = ref(props.selectedBranchId ? String(props.selectedBranchId) : 'all');
const isGenerating = ref(false);
const isAnalyzing = ref(false);
const isApplying = ref<Record<number, boolean>>({});
const isArabic = computed(() => page.props.locale === 'ar');
const currentLocale = computed(() => String(page.props.locale || 'en'));

const translationRoot = 'dashboard.admin.ai_insights.index';
const translationKeyFor = (value: string) =>
    `${translationRoot}.${value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 90)}`;
const localize = (en: string, ar: string) => {
    const key = translationKeyFor(en);
    const translated = t(key);

    if (translated !== key) {
        return translated;
    }

    return isArabic.value ? ar : en;
};

function adminUrl(path: string) {
    const currentUrl = String(page.url || '');
    const prefix = currentUrl.startsWith('/ar/') ? '/ar' : currentUrl.startsWith('/fr/') ? '/fr' : '';

    return `${prefix}/admin${path}`;
}

function refreshInsights() {
    router.get(
        adminUrl('/ai-insights'),
        {
            period: selectedPeriod.value,
            branch_id: selectedBranchId.value === 'all' ? null : Number(selectedBranchId.value),
            locale: currentLocale.value,
        },
        {
            preserveState: false,
            preserveScroll: false,
        },
    );
}

function generateReport() {
    router.post(
        adminUrl('/ai-insights/generate'),
        {
            period: selectedPeriod.value,
            branch_id: selectedBranchId.value === 'all' ? null : Number(selectedBranchId.value),
            locale: currentLocale.value,
        },
        {
            preserveScroll: true,
            onStart: () => {
                isGenerating.value = true;
            },
            onFinish: () => {
                isGenerating.value = false;
            },
        },
    );
}

function runOpenAiAnalysis(report: SavedReport) {
    router.post(
        adminUrl(`/ai-insights/${report.id}/analyze`),
        {
            locale: currentLocale.value,
        },
        {
            preserveScroll: true,
            onStart: () => {
                isAnalyzing.value = true;
            },
            onFinish: () => {
                isAnalyzing.value = false;
            },
        },
    );
}

function applyPricingOpportunity(carId: number, suggestedIncreasePercent: number) {
    router.post(
        adminUrl('/ai-insights/apply-pricing'),
        {
            car_id: carId,
            increase_percent: suggestedIncreasePercent,
        },
        {
            preserveScroll: true,
            onStart: () => {
                isApplying.value[carId] = true;
            },
            onFinish: () => {
                isApplying.value[carId] = false;
            },
        }
    );
}

function exportPdfReport(reportId: number) {
    window.open(adminUrl(`/ai-insights/${reportId}/pdf`), '_blank');
}

function exportExcelReport(reportId: number) {
    window.open(adminUrl(`/ai-insights/${reportId}/excel`), '_blank');
}

function loadReport(reportId: number) {
    router.get(
        adminUrl('/ai-insights'),
        {
            report_id: reportId,
            locale: currentLocale.value,
        },
        {
            preserveState: false,
            preserveScroll: false,
        },
    );
}


const severityClasses = (severity: Severity | string) => ({
    danger: 'border-red-200 bg-red-50 text-red-800',
    critical: 'border-red-200 bg-red-50 text-red-800',
    warning: 'border-amber-200 bg-amber-50 text-amber-800',
    high: 'border-amber-200 bg-amber-50 text-amber-800',
    medium: 'border-blue-200 bg-blue-50 text-blue-800',
    info: 'border-blue-200 bg-blue-50 text-blue-800',
    low: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
}[severity] || 'border-slate-200 bg-slate-50 text-slate-800');

const summaryCards = computed(() => [
    {
        label: localize('Critical insights', 'تنبيهات حرجة'),
        value: props.insights.summary.critical_count,
        icon: AlertTriangle,
        tone: 'border-red-200 bg-red-50 text-red-900',
        change: props.mom.critical_change,
        isGoodWhenNegative: true,
        isLoss: false,
    },
    {
        label: localize('Uncollected losses', 'خسائر غير محصلة'),
        value: props.insights.summary.formatted_uncollected_losses,
        icon: DollarSign,
        tone: 'border-amber-200 bg-amber-50 text-amber-900',
        change: props.mom.losses_change_percent,
        isGoodWhenNegative: true,
        isLoss: true,
    },
    {
        label: localize('High-risk customers', 'عملاء عالي الخطورة'),
        value: props.insights.summary.high_risk_customers_count,
        icon: UserRound,
        tone: 'border-orange-200 bg-orange-50 text-orange-900',
        change: props.mom.customers_change,
        isGoodWhenNegative: true,
        isLoss: false,
    },
    {
        label: localize('Pricing opportunities', 'فرص تسعير'),
        value: props.insights.summary.pricing_opportunities_count,
        icon: TrendingUp,
        tone: 'border-emerald-200 bg-emerald-50 text-emerald-900',
        change: props.mom.pricing_change,
        isGoodWhenNegative: false,
        isLoss: false,
    },
]);

const hasData = computed(() =>
    props.insights.unprofitable_cars.length > 0 ||
    props.insights.repeated_damage_cars.length > 0 ||
    props.insights.high_risk_customers.length > 0 ||
    props.insights.problem_contracts.length > 0 ||
    props.insights.price_opportunities.length > 0 ||
    props.insights.demand_days.length > 0,
);
</script>

<template>
    <Head :title="localize('AI Insights', 'تحليلات الذكاء الاصطناعي')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
            <section class="flex flex-col gap-4 border-b border-slate-200 pb-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700">
                            <Brain class="h-5 w-5" />
                        </span>
                        <div>
                            <h1 class="text-2xl font-semibold text-slate-950">
                                {{ localize('AI Insights', 'تحليلات الذكاء الاصطناعي') }}
                            </h1>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ localize('Internal risk, pricing, collections, and fleet signals prepared for OpenAI market analysis.', 'تحليل داخلي للمخاطر والتسعير والتحصيل والأسطول، جاهز لاحقا لدراسة السوق عبر OpenAI.') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <select
                        v-model="selectedPeriod"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-800 shadow-sm"
                        @change="refreshInsights"
                    >
                        <option v-for="period in periodOptions" :key="period.value" :value="period.value">
                            {{ period.label }}
                        </option>
                    </select>

                    <select
                        v-if="canAccessAllBranches"
                        v-model="selectedBranchId"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-800 shadow-sm"
                        @change="refreshInsights"
                    >
                        <option value="all">{{ localize('All branches', 'كل الفروع') }}</option>
                        <option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">
                            {{ branch.name }}
                        </option>
                    </select>

                    <Button type="button" class="gap-2" @click="refreshInsights">
                        <RefreshCcw class="h-4 w-4" />
                        {{ localize('Refresh', 'تحديث') }}
                    </Button>

                    <Button type="button" class="gap-2 bg-slate-950 text-white hover:bg-slate-800" :disabled="isGenerating" @click="generateReport">
                        <Save class="h-4 w-4" />
                        {{ isGenerating ? localize('Generating...', 'جاري التوليد...') : localize('Generate report', 'توليد تقرير') }}
                    </Button>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div class="rounded-lg border border-slate-200 bg-white p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">{{ localize('Latest saved AI report', 'آخر تقرير محفوظ') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ localize('Saved reports keep the internal data snapshot that will be sent to OpenAI in the next phase.', 'التقارير المحفوظة تحتفظ بنسخة بيانات التحليل الداخلي التي سترسل إلى OpenAI في المرحلة التالية.') }}
                            </p>
                        </div>
                        <span
                            v-if="latestReport"
                            class="rounded-md border px-2 py-1 text-xs font-semibold"
                            :class="latestReport.status === 'failed' ? severityClasses('danger') : latestReport.has_ai_result ? severityClasses('success') : severityClasses('info')"
                        >
                            {{
                                latestReport.status === 'failed'
                                    ? localize('AI failed', 'فشل AI')
                                    : latestReport.has_ai_result
                                        ? localize('AI completed', 'اكتمل AI')
                                        : localize('Internal ready', 'الداخلي جاهز')
                            }}
                        </span>
                    </div>

                    <div v-if="latestReport" class="mt-4 grid grid-cols-1 gap-3 text-sm text-slate-700 sm:grid-cols-3">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-400">{{ localize('Period', 'الفترة') }}</p>
                            <p class="mt-1">{{ latestReport.period_start }} → {{ latestReport.period_end }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-400">{{ localize('Model', 'النموذج') }}</p>
                            <p class="mt-1">{{ latestReport.model || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-400">{{ localize('Generated', 'تم التوليد') }}</p>
                            <p class="mt-1">{{ latestReport.generated_at || latestReport.created_at || '-' }}</p>
                        </div>
                    </div>
                    <div v-if="latestReport" class="mt-4 flex flex-wrap gap-3 items-center">
                        <Button
                            type="button"
                            class="gap-2"
                            :disabled="isAnalyzing || !openAiStatus.connected"
                            @click="runOpenAiAnalysis(latestReport)"
                        >
                            <Brain class="h-4 w-4" />
                            {{ isAnalyzing ? localize('Analyzing...', 'جاري التحليل...') : localize('Run OpenAI market study', 'تشغيل دراسة OpenAI') }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="gap-2"
                            @click="exportPdfReport(latestReport.id)"
                        >
                            <FileDown class="h-4 w-4" />
                            {{ localize('Export PDF', 'تصدير PDF') }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="gap-2"
                            @click="exportExcelReport(latestReport.id)"
                        >
                            <FileDown class="h-4 w-4" />
                            {{ localize('Export Excel', 'تصدير Excel') }}
                        </Button>
                        <p v-if="!openAiStatus.connected" class="text-sm text-amber-700">
                            {{ localize('OpenAI is not configured yet.', 'OpenAI غير مفعّل حاليا.') }}
                        </p>
                    </div>
                    <p v-if="latestReport?.error_message" class="mt-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        {{ latestReport.error_message }}
                    </p>
                    <p v-if="!latestReport" class="mt-4 text-sm text-slate-500">
                        {{ localize('No saved report for the current filter yet. Use Generate report to create the first snapshot.', 'لا يوجد تقرير محفوظ للفلاتر الحالية. استخدم توليد تقرير لإنشاء أول نسخة.') }}
                    </p>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5">
                    <h2 class="text-base font-semibold text-slate-950">{{ localize('Recent snapshots', 'آخر النسخ') }}</h2>
                    <div class="mt-4 space-y-3">
                        <button
                            v-for="report in savedReports"
                            :key="report.id"
                            type="button"
                            class="w-full text-left rounded-md border p-3 transition duration-150 block hover:bg-slate-100/70"
                            :class="latestReport && latestReport.id === report.id ? 'border-indigo-500 bg-indigo-50/50' : 'border-slate-100 bg-slate-50'"
                            @click="loadReport(report.id)"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-950">#{{ report.id }}</p>
                                <span
                                    class="text-xs font-semibold px-1.5 py-0.5 rounded"
                                    :class="report.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : report.status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'"
                                >
                                    {{ report.status }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ report.period_start }} → {{ report.period_end }}</p>
                            <p class="mt-1 text-xs text-slate-500 text-right">{{ report.created_by_name || '-' }}</p>
                        </button>
                        <p v-if="savedReports.length === 0" class="text-sm text-slate-500">
                            {{ localize('No saved snapshots yet.', 'لا توجد نسخ محفوظة بعد.') }}
                        </p>
                    </div>
                </div>
            </section>

            <section v-if="latestReport?.ai_result" class="rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">{{ localize('OpenAI analysis result', 'نتيجة تحليل OpenAI') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ latestReport.ai_result.market_summary }}</p>
                        </div>
                        <span class="rounded-md border px-2 py-1 text-xs font-semibold" :class="severityClasses(latestReport.ai_result.risk_level)">
                            {{ latestReport.ai_result.risk_level }}
                        </span>
                    </div>
                    <p class="mt-4 text-sm leading-6 text-slate-700">{{ latestReport.ai_result.executive_summary }}</p>
                </div>

                <div class="grid grid-cols-1 gap-5 p-5 xl:grid-cols-2">
                    <div>
                        <h3 class="font-semibold text-slate-950">{{ localize('Risks', 'المخاطر') }}</h3>
                        <div class="mt-3 space-y-3">
                            <div v-for="item in latestReport.ai_result.risks" :key="item.title" class="rounded-md border border-slate-100 bg-slate-50 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm font-semibold text-slate-950">{{ item.title }}</p>
                                    <span class="rounded border px-2 py-0.5 text-xs" :class="severityClasses(item.severity)">{{ item.severity }}</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-600">{{ item.reason }}</p>
                                <p class="mt-2 text-sm font-medium text-slate-800">{{ item.recommendation }}</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-slate-950">{{ localize('Opportunities', 'الفرص') }}</h3>
                        <div class="mt-3 space-y-3">
                            <div v-for="item in latestReport.ai_result.opportunities" :key="item.title" class="rounded-md border border-slate-100 bg-slate-50 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm font-semibold text-slate-950">{{ item.title }}</p>
                                    <span class="rounded border px-2 py-0.5 text-xs" :class="severityClasses(item.severity)">{{ item.severity }}</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-600">{{ item.reason }}</p>
                                <p class="mt-2 text-sm font-medium text-slate-800">{{ item.recommendation }}</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-slate-950">{{ localize('Pricing recommendations', 'توصيات التسعير') }}</h3>
                        <div class="mt-3 space-y-3">
                            <div v-for="item in latestReport.ai_result.pricing_recommendations" :key="item.title" class="rounded-md border border-slate-100 bg-slate-50 p-3">
                                <p class="text-sm font-semibold text-slate-950">{{ item.title }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ item.reason }}</p>
                                <p class="mt-2 text-sm font-medium text-slate-800">{{ item.recommendation }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="latestReport.ai_result.collection_actions && latestReport.ai_result.collection_actions.length">
                        <h3 class="font-semibold text-slate-950">{{ localize('Collection actions', 'إجراءات التحصيل المقترحة') }}</h3>
                        <div class="mt-3 space-y-3">
                            <div v-for="item in latestReport.ai_result.collection_actions" :key="item.title" class="rounded-md border border-slate-100 bg-slate-50 p-3">
                                <p class="text-sm font-semibold text-slate-950">{{ item.title }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ item.reason }}</p>
                                <p class="mt-2 text-sm font-medium text-slate-800">{{ item.recommendation }}</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-slate-950">{{ localize('Action plan', 'خطة العمل') }}</h3>
                        <div class="mt-3 space-y-3">
                            <div v-for="item in latestReport.ai_result.action_plan" :key="item.action" class="rounded-md border border-slate-100 bg-slate-50 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold text-slate-950">{{ item.action }}</p>
                                    <span class="text-xs font-semibold text-slate-500">{{ item.priority }}</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-600">{{ item.owner }} · {{ item.metric_to_watch }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="latestReport.ai_result.sources.length" class="border-t border-slate-200 p-5">
                    <h3 class="font-semibold text-slate-950">{{ localize('Market sources', 'مصادر السوق') }}</h3>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a
                            v-for="source in latestReport.ai_result.sources"
                            :key="source.url"
                            :href="source.url"
                            target="_blank"
                            rel="noreferrer"
                            class="rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                        >
                            {{ source.title }}
                        </a>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="card in summaryCards"
                    :key="card.label"
                    class="rounded-lg border p-4 flex flex-col justify-between"
                    :class="card.tone"
                >
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium opacity-80">{{ card.label }}</p>
                            <component :is="card.icon" class="h-5 w-5" />
                        </div>
                        <p class="mt-3 text-2xl font-semibold">{{ card.value }}</p>
                    </div>
                    <div class="mt-3 flex items-center gap-1.5 text-xs">
                        <template v-if="card.change !== null && card.change !== undefined">
                            <span v-if="card.change > 0" class="flex items-center gap-0.5" :class="card.isGoodWhenNegative ? 'text-red-700 font-semibold' : 'text-emerald-700 font-semibold'">
                                <TrendingUp class="h-3.5 w-3.5" />
                                +{{ card.change }}{{ card.isLoss ? '%' : '' }}
                            </span>
                            <span v-else-if="card.change < 0" class="flex items-center gap-0.5" :class="card.isGoodWhenNegative ? 'text-emerald-700 font-semibold' : 'text-red-700 font-semibold'">
                                <TrendingDown class="h-3.5 w-3.5" />
                                {{ card.change }}{{ card.isLoss ? '%' : '' }}
                            </span>
                            <span v-else class="text-slate-500">
                                0{{ card.isLoss ? '%' : '' }}
                            </span>
                            <span class="opacity-70 text-slate-600">vs {{ localize('prev period', 'الفترة السابقة') }}</span>
                        </template>
                    </div>
                </div>
            </section>


            <section v-if="!hasData" class="rounded-lg border border-slate-200 bg-white p-8 text-center">
                <Brain class="mx-auto h-10 w-10 text-slate-400" />
                <h2 class="mt-3 text-lg font-semibold text-slate-900">{{ localize('No insights found for this period.', 'لا توجد تحليلات لهذه الفترة.') }}</h2>
                <p class="mt-2 text-sm text-slate-500">
                    {{ localize('Try another period or branch after more operational data is recorded.', 'جرّب فترة أو فرعا آخر بعد تسجيل بيانات تشغيل أكثر.') }}
                </p>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex items-center gap-3 border-b border-slate-200 p-4">
                        <Car class="h-5 w-5 text-red-600" />
                        <div>
                            <h2 class="font-semibold text-slate-950">{{ localize('Unprofitable cars', 'سيارات لا تحقق أرباحا') }}</h2>
                            <p class="text-sm text-slate-500">{{ localize('Revenue minus maintenance, damages, and open violations.', 'الإيراد مطروحا منه الصيانة والأضرار والمخالفات المفتوحة.') }}</p>
                        </div>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <div v-for="car in insights.unprofitable_cars" :key="car.car_id" class="p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-slate-950">{{ car.car_name }}</p>
                                    <p class="text-sm text-slate-500">{{ car.license_plate || '-' }}</p>
                                </div>
                                <p class="text-sm font-semibold text-red-700">{{ car.formatted_net_profit }}</p>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
                                <span>{{ localize('Revenue', 'الإيراد') }}: {{ car.formatted_revenue }}</span>
                                <span>{{ localize('Costs', 'التكاليف') }}: {{ car.formatted_costs }}</span>
                                <span>{{ localize('Margin', 'الهامش') }}: {{ car.profit_margin }}%</span>
                            </div>
                            <p class="mt-3 text-sm text-slate-600">{{ car.recommendation }}</p>
                        </div>
                        <p v-if="insights.unprofitable_cars.length === 0" class="p-4 text-sm text-slate-500">
                            {{ localize('No unprofitable cars detected.', 'لا توجد سيارات خاسرة.') }}
                        </p>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex items-center gap-3 border-b border-slate-200 p-4">
                        <AlertTriangle class="h-5 w-5 text-amber-600" />
                        <div>
                            <h2 class="font-semibold text-slate-950">{{ localize('Repeated damage cars', 'سيارات معرضة لأضرار متكررة') }}</h2>
                            <p class="text-sm text-slate-500">{{ localize('Damage reports, damage items, and accidents in the selected period.', 'تقارير الأضرار وبنودها والحوادث خلال الفترة المحددة.') }}</p>
                        </div>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <div v-for="car in insights.repeated_damage_cars" :key="car.car_id" class="p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-slate-950">{{ car.car_name }}</p>
                                    <p class="text-sm text-slate-500">{{ car.license_plate || '-' }}</p>
                                </div>
                                <span class="rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800">
                                    {{ car.damage_reports_count }} {{ localize('reports', 'تقارير') }}
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
                                <span>{{ localize('Items', 'بنود') }}: {{ car.damage_items_count }}</span>
                                <span>{{ localize('Accidents', 'حوادث') }}: {{ car.accidents_count }}</span>
                                <span>{{ localize('Days used', 'أيام تشغيل') }}: {{ car.utilization_days }}</span>
                            </div>
                            <p class="mt-3 text-sm text-slate-600">{{ car.recommendation }}</p>
                        </div>
                        <p v-if="insights.repeated_damage_cars.length === 0" class="p-4 text-sm text-slate-500">
                            {{ localize('No repeated damage pattern detected.', 'لا يوجد نمط أضرار متكرر.') }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex items-center gap-3 border-b border-slate-200 p-4">
                        <UserRound class="h-5 w-5 text-orange-600" />
                        <div>
                            <h2 class="font-semibold text-slate-950">{{ localize('High-risk customers', 'عملاء عالي الخطورة') }}</h2>
                            <p class="text-sm text-slate-500">{{ localize('Risk score from overdue contracts, unpaid balance, damages, and cancellations.', 'درجة خطورة من التأخير والديون والأضرار والإلغاءات.') }}</p>
                        </div>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <div v-for="customer in insights.high_risk_customers" :key="customer.customer_id" class="p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-slate-950">{{ customer.name }}</p>
                                    <p class="text-sm text-slate-500">{{ customer.email || '-' }}</p>
                                </div>
                                <span class="rounded-md border px-2 py-1 text-xs font-semibold" :class="severityClasses(customer.severity)">
                                    {{ customer.score }}/100
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                                <span>{{ localize('Reservations', 'حجوزات') }}: {{ customer.reservations_count }}</span>
                                <span>{{ localize('Late', 'متأخر') }}: {{ customer.overdue_contracts_count }}</span>
                                <span>{{ localize('Damages', 'أضرار') }}: {{ customer.damage_reports_count }}</span>
                                <span>{{ localize('Unpaid', 'غير محصل') }}: {{ customer.formatted_unpaid_amount }}</span>
                            </div>
                            <p class="mt-3 text-sm text-slate-600">{{ customer.recommendation }}</p>
                        </div>
                        <p v-if="insights.high_risk_customers.length === 0" class="p-4 text-sm text-slate-500">
                            {{ localize('No high-risk customers detected.', 'لا يوجد عملاء عالي الخطورة.') }}
                        </p>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex items-center gap-3 border-b border-slate-200 p-4">
                        <FileWarning class="h-5 w-5 text-red-600" />
                        <div>
                            <h2 class="font-semibold text-slate-950">{{ localize('Problem contracts', 'عقود معرضة للمشاكل') }}</h2>
                            <p class="text-sm text-slate-500">{{ localize('Active or pending contracts with late return or unpaid return charges.', 'عقود نشطة أو معلقة فيها تأخير أو رسوم رجوع غير مدفوعة.') }}</p>
                        </div>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <div v-for="contract in insights.problem_contracts" :key="contract.contract_id" class="p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-slate-950">{{ contract.contract_number }}</p>
                                    <p class="text-sm text-slate-500">{{ contract.customer_name || '-' }} · {{ contract.car_name || '-' }}</p>
                                </div>
                                <span class="rounded-md border px-2 py-1 text-xs font-semibold" :class="severityClasses(contract.severity)">
                                    {{ contract.score }}/100
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
                                <span>{{ localize('End date', 'تاريخ النهاية') }}: {{ contract.end_date || '-' }}</span>
                                <span>{{ localize('Late days', 'أيام التأخير') }}: {{ contract.days_late }}</span>
                                <span>{{ localize('Unpaid', 'غير مدفوع') }}: {{ contract.formatted_unpaid_return_charges }}</span>
                            </div>
                            <p class="mt-3 text-sm text-slate-600">{{ contract.recommendation }}</p>
                        </div>
                        <p v-if="insights.problem_contracts.length === 0" class="p-4 text-sm text-slate-500">
                            {{ localize('No problem contracts detected.', 'لا توجد عقود معرضة للمشاكل.') }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex items-center gap-3 border-b border-slate-200 p-4">
                        <TrendingUp class="h-5 w-5 text-emerald-600" />
                        <h2 class="font-semibold text-slate-950">{{ localize('Price opportunities', 'فرص زيادة الأسعار') }}</h2>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <div v-for="item in insights.price_opportunities" :key="item.car_id" class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-950">{{ item.car_name }}</p>
                                    <p class="text-sm text-slate-500">{{ item.formatted_current_price }} · {{ item.utilization_days }} {{ localize('days', 'يوم') }}</p>
                                </div>
                                <span class="rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-800">
                                    +{{ item.suggested_increase_percent }}%
                                </span>
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-3">
                                <p class="text-sm text-slate-600">{{ item.recommendation }}</p>
                                <Button
                                    type="button"
                                    size="sm"
                                    class="h-7 px-2.5 text-xs bg-emerald-600 hover:bg-emerald-700 text-white"
                                    :disabled="isApplying[item.car_id]"
                                    @click="applyPricingOpportunity(item.car_id, item.suggested_increase_percent)"
                                >
                                    {{ isApplying[item.car_id] ? localize('Applying...', 'جاري التطبيق...') : localize('Apply', 'تطبيق') }}
                                </Button>
                            </div>
                        </div>
                        <p v-if="insights.price_opportunities.length === 0" class="p-4 text-sm text-slate-500">
                            {{ localize('No pricing opportunities detected yet.', 'لا توجد فرص تسعير حاليا.') }}
                        </p>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex items-center gap-3 border-b border-slate-200 p-4">
                        <CalendarDays class="h-5 w-5 text-blue-600" />
                        <h2 class="font-semibold text-slate-950">{{ localize('High-demand days', 'أيام الطلب المرتفع') }}</h2>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <div v-for="day in insights.demand_days" :key="day.day" class="p-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-semibold text-slate-950">{{ day.day }}</p>
                                <span class="text-sm font-semibold text-blue-700">{{ day.reservations_count }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-500">{{ day.rental_days }} {{ localize('rental days', 'أيام تأجير') }}</p>
                            <p class="mt-3 text-sm text-slate-600">{{ day.recommendation }}</p>
                        </div>
                        <p v-if="insights.demand_days.length === 0" class="p-4 text-sm text-slate-500">
                            {{ localize('No demand pattern detected.', 'لا يوجد نمط طلب واضح.') }}
                        </p>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex items-center gap-3 border-b border-slate-200 p-4">
                        <DollarSign class="h-5 w-5 text-amber-600" />
                        <h2 class="font-semibold text-slate-950">{{ localize('Uncollected losses', 'خسائر غير محصلة') }}</h2>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <div v-for="loss in insights.uncollected_losses" :key="loss.key" class="flex items-center justify-between gap-3 p-4">
                            <p class="text-sm font-medium text-slate-700">{{ loss.label }}</p>
                            <p class="text-sm font-semibold text-amber-700">{{ loss.formatted_amount }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </AdminLayout>
</template>
