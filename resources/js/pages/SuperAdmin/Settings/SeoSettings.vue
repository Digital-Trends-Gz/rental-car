<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import SeoContentAnalysis from '@/pages/Admin/Settings/Seo/SeoContentAnalysis.vue';
import SeoGeneralSettings from '@/pages/Admin/Settings/Seo/SeoGeneralSettings.vue';
import SeoPreviewsSection from '@/pages/Admin/Settings/Seo/SeoPreviewsSection.vue';
import SeoRedirectManager from '@/pages/Admin/Settings/Seo/SeoRedirectManager.vue';
import SeoRobotsManagement from '@/pages/Admin/Settings/Seo/SeoRobotsManagement.vue';
import SeoSitemapManagement from '@/pages/Admin/Settings/Seo/SeoSitemapManagement.vue';
import SeoSocialIntegration from '@/pages/Admin/Settings/Seo/SeoSocialIntegration.vue';
import { computed, ref } from 'vue';

type LocalizedText = Record<string, string | null>;
type LocaleRow = {
    code: string;
    name: string;
    native: string;
    regional?: string;
    script?: string;
    direction: 'ltr' | 'rtl';
};
type SeoPageKey =
    | 'home'
    | 'fleet'
    | 'applications'
    | 'plans'
    | 'about'
    | 'contact'
    | 'privacy-policy'
    | 'terms-of-use'
    | 'security-policy';
type ActiveTab = 'overview' | 'general' | 'pages' | 'previews' | 'analysis' | 'social' | 'technical';
type SitemapPage = {
    path: string;
    priority: number;
    changeFreq: 'always' | 'hourly' | 'daily' | 'weekly' | 'monthly' | 'yearly' | 'never';
    lastmod?: string;
};
type SeoPageSettings = {
    title: LocalizedText;
    description: LocalizedText;
    focus_keyword?: LocalizedText;
    canonical_url: string | null;
    robots?: string | null;
};
type SeoPages = Partial<Record<SeoPageKey, SeoPageSettings>>;
type PreviewCard = {
    key: SeoPageKey;
    label: string;
    title: string;
    description: string;
    focusKeyword: string;
    path: string;
    robots: string;
    ogImage: string;
    ogImageAlt: string;
    twitterCardType: string;
    alternates: Array<{ locale: string; url: string }>;
    slug: string;
    score: number;
    checks: Array<{ ok: boolean; label: string; failLabel: string }>;
};

const props = defineProps<{
    settings: {
        defaults: {
            title_suffix: LocalizedText;
            default_description: LocalizedText;
            og_image: string | null;
            og_image_alt: LocalizedText;
            robots: string | null;
        };
        pages: SeoPages;
        technical?: {
            sitemap?: {
                pages?: SitemapPage[];
            };
            robots?: {
                allowAll?: boolean;
                disallowPaths?: string[];
                crawlDelay?: number;
                requestRate?: number;
                sitemapUrl?: string;
            };
            redirects?: {
                items?: Array<{
                    id: string;
                    fromPath: string;
                    toPath: string;
                    statusCode: 301 | 302 | 307 | 308;
                    isPermanent: boolean;
                    isActive: boolean;
                }>;
            };
        };
    };
    locales: LocaleRow[];
    defaultLocale: string;
    seoOgImageFiles?: Array<{ id: number; url: string }>;
    actions: { update: string };
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const SEO_PAGE_KEYS: SeoPageKey[] = [
    'home',
    'fleet',
    'applications',
    'plans',
    'about',
    'contact',
    'privacy-policy',
    'terms-of-use',
    'security-policy',
];

const SEO_PAGE_PATHS: Record<SeoPageKey, string> = {
    home: '/',
    fleet: '/fleet',
    applications: '/car-rental-apps',
    plans: '/pricing-plans',
    about: '/about',
    contact: '/contact',
    'privacy-policy': '/privacy-policy',
    'terms-of-use': '/terms-of-use',
    'security-policy': '/security-policy',
};

const fallbackLocales: LocaleRow[] = [
    { code: 'en', name: 'English', native: 'English', direction: 'ltr' },
    { code: 'ar', name: 'Arabic', native: 'العربية', direction: 'rtl' },
];

const localeRows = computed<LocaleRow[]>(() => {
    const rows = Array.isArray(props.locales) ? props.locales.filter((row) => String(row.code || '').trim() !== '') : [];
    return rows.length > 0 ? rows : fallbackLocales;
});

function resolveLocaleCode(code: string): string | null {
    const normalized = String(code || '').trim().toLowerCase();
    const match = localeRows.value.find((row) => String(row.code || '').trim().toLowerCase() === normalized);
    return match?.code ?? null;
}

function createLocalizedState(value?: LocalizedText | null): LocalizedText {
    const result: LocalizedText = {};
    for (const row of localeRows.value) {
        result[row.code] = String(value?.[row.code] ?? '').trim();
    }
    return result;
}

function createPageState(pageSettings?: SeoPageSettings): SeoPageSettings {
    return {
        title: createLocalizedState(pageSettings?.title),
        description: createLocalizedState(pageSettings?.description),
        focus_keyword: createLocalizedState(pageSettings?.focus_keyword),
        canonical_url: pageSettings?.canonical_url ?? '',
        robots: pageSettings?.robots ?? '',
    };
}

function createPageStates(pages?: SeoPages): Record<SeoPageKey, SeoPageSettings> {
    return SEO_PAGE_KEYS.reduce((result, pageKey) => {
        result[pageKey] = createPageState(pages?.[pageKey]);
        return result;
    }, {} as Record<SeoPageKey, SeoPageSettings>);
}

function defaultSitemapPages(): SitemapPage[] {
    const today = new Date().toISOString().split('T')[0];

    return SEO_PAGE_KEYS.map((pageKey) => {
        const isPrimaryPage = pageKey === 'home' || pageKey === 'fleet';
        const isPolicyPage = ['privacy-policy', 'terms-of-use', 'security-policy'].includes(pageKey);

        return {
            path: SEO_PAGE_PATHS[pageKey],
            priority: pageKey === 'home' ? 1.0 : (isPrimaryPage ? 0.9 : (isPolicyPage ? 0.5 : 0.8)),
            changeFreq: isPolicyPage ? 'yearly' : (isPrimaryPage ? 'weekly' : 'monthly'),
            lastmod: today,
        };
    });
}

const activeTab = ref<ActiveTab>('overview');
const activePageTab = ref<SeoPageKey>('home');
const seoFileUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);

const selectedSeoLocale = ref(
    resolveLocaleCode(locale.value)
    || resolveLocaleCode(props.defaultLocale)
    || localeRows.value[0]?.code
    || 'en',
);

const selectedSeoLocaleRow = computed(() =>
    localeRows.value.find((row) => row.code === selectedSeoLocale.value)
    || localeRows.value.find((row) => row.code.toLowerCase() === selectedSeoLocale.value.toLowerCase())
    || localeRows.value[0]
    || fallbackLocales[0],
);
const selectedSeoLocaleLabel = computed(() => selectedSeoLocaleRow.value.native || selectedSeoLocaleRow.value.name || selectedSeoLocale.value.toUpperCase());
const selectedSeoLocaleDirection = computed(() => selectedSeoLocaleRow.value.direction || 'ltr');
const isSelectedLocaleRtl = computed(() => selectedSeoLocaleDirection.value === 'rtl');

function localeCardLabel(row: LocaleRow): string {
    return locale.value === 'ar'
        ? (row.native || row.name || row.code.toUpperCase())
        : (row.name || row.native || row.code.toUpperCase());
}

const previewName = computed(() => localize('Main Site', 'الموقع الرئيسي'));
const seoPreviewBaseUrl = computed(() => (typeof window !== 'undefined' && window.location?.origin) ? window.location.origin : 'https://example.com');

const form = useForm({
    seo_og_image_temp_folders: [] as string[],
    seo_og_image_removed_files: [] as number[],
    seo: {
        defaults: {
            title_suffix: createLocalizedState(props.settings.defaults?.title_suffix),
            default_description: createLocalizedState(props.settings.defaults?.default_description),
            og_image: props.settings.defaults?.og_image ?? '',
            og_image_alt: createLocalizedState(props.settings.defaults?.og_image_alt),
            robots: props.settings.defaults?.robots ?? 'index,follow',
        },
        pages: createPageStates(props.settings.pages),
        technical: {
            sitemap: {
                pages: Array.isArray(props.settings.technical?.sitemap?.pages) && props.settings.technical?.sitemap?.pages?.length
                    ? props.settings.technical.sitemap.pages.filter((page) => Object.values(SEO_PAGE_PATHS).includes(page.path))
                    : defaultSitemapPages(),
            },
            robots: {
                allowAll: props.settings.technical?.robots?.allowAll ?? true,
                disallowPaths: props.settings.technical?.robots?.disallowPaths ?? ['/superadmin', '/admin'],
                crawlDelay: Number(props.settings.technical?.robots?.crawlDelay ?? 1),
                requestRate: Number(props.settings.technical?.robots?.requestRate ?? 30),
                sitemapUrl: props.settings.technical?.robots?.sitemapUrl ?? '/sitemap.xml',
            },
            redirects: {
                items: props.settings.technical?.redirects?.items ?? [],
            },
        },
    },
});

const seoOgImageUrl = computed(() => props.seoOgImageFiles?.[0]?.url || null);
const selectedPage = computed(() => form.seo.pages[activePageTab.value]);

function localizedSeoText(value: LocalizedText | undefined | null, localeKey = selectedSeoLocale.value): string {
    if (!value) {
        return '';
    }

    const preferred = String(value[localeKey] || '').trim();
    if (preferred !== '') {
        return preferred;
    }

    const firstAvailable = Object.values(value).find((item) => String(item || '').trim() !== '');
    return String(firstAvailable || '').trim();
}

function seoPagePath(pageKey: SeoPageKey): string {
    return pageKey === 'home' ? '/' : '/fleet';
}

function seoPageLabel(pageKey: SeoPageKey): string {
    const labels: Record<SeoPageKey, { en: string; ar: string }> = {
        home: { en: 'Landing Page', ar: 'الصفحة الرئيسية' },
        fleet: { en: 'Fleet Page', ar: 'صفحة الأسطول' },
    };

    return localize(labels[pageKey].en, labels[pageKey].ar);
}

function seoPageDefaultTitle(pageKey: SeoPageKey, localeKey = selectedSeoLocale.value): string {
    const suffix = localizedSeoText(form.seo.defaults.title_suffix, localeKey) || previewName.value;
    if (pageKey === 'home') {
        return previewName.value;
    }

    return `${localize('Fleet', 'الأسطول')} | ${suffix}`;
}

function seoPageDefaultDescription(pageKey: SeoPageKey, localeKey = selectedSeoLocale.value): string {
    const shared = localizedSeoText(form.seo.defaults.default_description, localeKey);
    if (shared) {
        return shared;
    }

    return pageKey === 'home'
        ? localize(`Discover ${previewName.value} and reserve your next rental car online.`, `اكتشف ${previewName.value} واحجز سيارتك عبر الإنترنت.`)
        : localize(`Browse available rental vehicles from ${previewName.value}.`, `استعرض السيارات المتاحة في ${previewName.value}.`);
}

function seoPageDefaultFocusKeyword(pageKey: SeoPageKey, localeKey = selectedSeoLocale.value): string {
    const values: Record<SeoPageKey, string> = {
        home: localeKey === 'ar' ? 'تأجير سيارات' : 'car rental',
        fleet: localeKey === 'ar' ? 'أسطول السيارات' : 'fleet cars',
    };

    return values[pageKey];
}

function seoPageDefaultSlug(pageKey: SeoPageKey): string {
    return pageKey === 'home' ? '/' : '/fleet';
}

function replaceCarSeoPlaceholders(text: string): string {
    return String(text || '').replace(/\s+/g, ' ').trim();
}

function normalizeSeoText(value: string): string {
    return String(value || '')
        .toLowerCase()
        .trim()
        .replace(/[^\p{L}\p{N}\s/_-]+/gu, ' ')
        .replace(/\s+/g, ' ');
}

function includesNormalized(haystack: string, needle: string): boolean {
    const normalizedNeedle = normalizeSeoText(needle);
    if (normalizedNeedle === '') {
        return false;
    }

    return normalizeSeoText(haystack).includes(normalizedNeedle);
}

function buildPreviewCards(localeKey: string): PreviewCard[] {
    return (['home', 'fleet'] as SeoPageKey[]).map((pageKey) => {
        const title = localizedSeoText(form.seo.pages[pageKey].title, localeKey) || seoPageDefaultTitle(pageKey, localeKey);
        const description = localizedSeoText(form.seo.pages[pageKey].description, localeKey) || seoPageDefaultDescription(pageKey, localeKey);
        const focusKeyword = localizedSeoText(form.seo.pages[pageKey].focus_keyword, localeKey) || seoPageDefaultFocusKeyword(pageKey, localeKey);
        const ogImageAlt = localizedSeoText(form.seo.defaults.og_image_alt, localeKey) || previewName.value;
        const path = form.seo.pages[pageKey].canonical_url || `${seoPreviewBaseUrl.value}${seoPageDefaultSlug(pageKey)}`;
        const pageRobots = String(form.seo.pages[pageKey].robots || '').trim();
        const robots = pageRobots || form.seo.defaults.robots || 'index,follow';
        const canonicalValue = String(form.seo.pages[pageKey].canonical_url || '').trim();
        const canonicalLooksValid = canonicalValue === '' || /^https?:\/\/\S+$/i.test(canonicalValue);
        const pathName = (() => {
            try {
                return new URL(path, seoPreviewBaseUrl.value).pathname;
            } catch {
                return seoPageDefaultSlug(pageKey);
            }
        })();
        const normalizedSlug = pathName.replace(/\/+/g, '/').replace(/^\/|\/$/g, '');
        const slugLooksValid = normalizedSlug === '' || /^[a-z0-9/_-]+$/i.test(normalizedSlug);
        const titleHasKeyword = includesNormalized(title, focusKeyword);
        const descriptionHasKeyword = includesNormalized(description, focusKeyword);
        const ogImageSet = String(form.seo.defaults.og_image || '').trim() !== '';

        const checks = [
            {
                ok: String(title || '').trim().length > 0,
                label: localize('Title is set', 'تم تعيين العنوان'),
                failLabel: localize('Set a page title', 'حدد عنوان الصفحة'),
            },
            {
                ok: String(description || '').trim().length > 0,
                label: localize('Description is set', 'تم تعيين الوصف'),
                failLabel: localize('Set a page description', 'حدد وصف الصفحة'),
            },
            {
                ok: String(focusKeyword || '').trim().length > 0,
                label: localize('Focus keyword is set', 'تم تعيين الكلمة المفتاحية'),
                failLabel: localize('Set a focus keyword', 'حدد كلمة مفتاحية'),
            },
            {
                ok: canonicalLooksValid && slugLooksValid,
                label: localize('Canonical URL looks valid', 'الرابط القانوني صالح'),
                failLabel: localize('Check the canonical URL', 'تحقق من الرابط القانوني'),
            },
            {
                ok: ogImageSet,
                label: localize('Open Graph image is set', 'تم تعيين صورة Open Graph'),
                failLabel: localize('Add an Open Graph image', 'أضف صورة Open Graph'),
            },
            {
                ok: titleHasKeyword || descriptionHasKeyword,
                label: localize('Keyword appears in the content', 'الكلمة المفتاحية تظهر في المحتوى'),
                failLabel: localize('Add the keyword to title or description', 'أضف الكلمة المفتاحية إلى العنوان أو الوصف'),
            },
        ];

        const score = checks.filter((check) => check.ok).length;
        const alternates = localeRows.value.map((row) => ({ locale: row.code, url: path }));
        alternates.push({ locale: 'x-default', url: path });

        return {
            key: pageKey,
            label: seoPageLabel(pageKey),
            title,
            description,
            focusKeyword,
            path,
            robots,
            ogImage: form.seo.defaults.og_image || '',
            ogImageAlt,
            twitterCardType: ogImageSet ? 'summary_large_image' : 'summary',
            alternates,
            slug: normalizedSlug,
            score,
            checks,
        };
    });
}

const seoPreviewCardsData = computed<PreviewCard[]>(() => buildPreviewCards(selectedSeoLocale.value));
const activePreviewCard = computed(() => seoPreviewCardsData.value.find((card) => card.key === activePageTab.value) || seoPreviewCardsData.value[0] || null);
const pageSettingTabs = computed(() => (['home', 'fleet'] as SeoPageKey[]).map((key) => ({ key, label: seoPageLabel(key) })));

const seoReadinessByLocale = computed(() => {
    return localeRows.value.map((row) => {
        const cards = buildPreviewCards(row.code);
        const totalChecks = cards.reduce((sum, preview) => sum + preview.checks.length, 0);
        const passedChecks = cards.reduce((sum, preview) => sum + preview.score, 0);
        const percentage = totalChecks > 0 ? Math.round((passedChecks / totalChecks) * 100) : 0;

        return {
            locale: row.code,
            label: localeCardLabel(row),
            percentage,
            passedChecks,
            totalChecks,
            pagesReady: cards.filter((card) => card.score === card.checks.length).length,
            totalPages: cards.length,
            className: percentage >= 85 ? 'bg-emerald-100 text-emerald-700' : (percentage >= 50 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'),
        };
    });
});

const seoHealthStatus = computed(() => {
    const current = seoReadinessByLocale.value.find((item) => item.locale === selectedSeoLocale.value) || seoReadinessByLocale.value[0];
    const percentage = current?.percentage ?? 0;

    if (percentage >= 85) {
        return {
            label: localize('Great', 'ممتاز'),
            className: 'bg-emerald-100 text-emerald-700',
            description: localize('The selected language is in good shape.', 'اللغة المحددة جاهزة بشكل جيد.'),
        };
    }

    if (percentage >= 50) {
        return {
            label: localize('Needs Work', 'يحتاج تحسين'),
            className: 'bg-amber-100 text-amber-700',
            description: localize('Some pages still need SEO cleanup for the selected language.', 'بعض الصفحات ما زالت تحتاج تحسين SEO للغة المحددة.'),
        };
    }

    return {
        label: localize('Poor', 'ضعيف'),
        className: 'bg-red-100 text-red-700',
        description: localize('SEO content is missing in many places for this language.', 'محتوى SEO مفقود في عدة أماكن لهذه اللغة.'),
    };
});

function exportSeoReport() {
    const current = seoReadinessByLocale.value.find((item) => item.locale === selectedSeoLocale.value) || seoReadinessByLocale.value[0];
    if (!current) {
        return;
    }

    const report = [
        `${previewName.value} SEO Report`,
        `Selected Locale: ${selectedSeoLocale.value}`,
        `Health: ${current.percentage}%`,
        `Checks Passed: ${current.passedChecks}/${current.totalChecks}`,
        `Pages Ready: ${current.pagesReady}/${current.totalPages}`,
        '',
        'Locale Breakdown:',
        ...seoReadinessByLocale.value.map((item) => `- ${item.locale.toUpperCase()} (${item.label}): ${item.percentage}% (${item.passedChecks}/${item.totalChecks})`),
    ].join('\n');

    if (typeof navigator !== 'undefined' && navigator.clipboard) {
        navigator.clipboard.writeText(report).catch(() => undefined);
    }
}

function submit() {
    form.put(props.actions.update, {
        preserveScroll: true,
        onSuccess: () => {
            form.seo_og_image_temp_folders = [];
            form.seo_og_image_removed_files = [];
            seoFileUploadRef.value?.resetFiles();
        },
    });
}
</script>

<template>
    <Head :title="localize('Main Site SEO', 'SEO الموقع الرئيسي')" />

    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Main Site SEO', 'SEO الموقع الرئيسي') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Manage SEO for landing and fleet only.', 'أدر SEO للصفحة الرئيسية والأسطول فقط.') }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button :disabled="form.processing" @click="exportSeoReport">
                        {{ localize('Export SEO Report', 'تصدير تقرير SEO') }}
                    </Button>
                    <Button :disabled="form.processing" @click="submit">
                        {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save SEO Changes', 'حفظ تغييرات SEO') }}
                    </Button>
                </div>
            </div>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <button
                    v-for="readiness in seoReadinessByLocale"
                    :key="readiness.locale"
                    type="button"
                    class="rounded-xl border bg-white p-4 text-left shadow-sm transition-colors hover:border-primary hover:bg-muted/40"
                    :class="selectedSeoLocale === readiness.locale ? 'border-primary ring-1 ring-primary/20' : 'border-border'"
                    @click="selectedSeoLocale = readiness.locale"
                >
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <div class="text-lg font-semibold">{{ readiness.label }}</div>
                            <div class="text-sm text-muted-foreground">{{ readiness.locale.toUpperCase() }}</div>
                        </div>
                        <span class="rounded-full px-3 py-1 text-sm font-semibold" :class="readiness.className">
                            {{ readiness.percentage }}%
                        </span>
                    </div>
                    <div class="space-y-1 text-sm text-muted-foreground">
                        <div>{{ readiness.passedChecks }}/{{ readiness.totalChecks }} {{ localize('checks passed', 'فحص ناجح') }}</div>
                        <div>{{ readiness.pagesReady }}/{{ readiness.totalPages }} {{ localize('pages fully ready', 'صفحات جاهزة بالكامل') }}</div>
                    </div>
                </button>
            </section>

            <div class="rounded-lg border bg-muted/20 p-3">
                <div class="flex flex-wrap gap-2">
                    <Button type="button" :variant="activeTab === 'overview' ? 'default' : 'outline'" @click="activeTab = 'overview'">
                        {{ localize('Dashboard', 'لوحة SEO') }}
                    </Button>
                    <Button type="button" :variant="activeTab === 'general' ? 'default' : 'outline'" @click="activeTab = 'general'">
                        {{ localize('General Settings', 'الإعدادات العامة') }}
                    </Button>
                    <Button type="button" :variant="activeTab === 'pages' ? 'default' : 'outline'" @click="activeTab = 'pages'">
                        {{ localize('Page Settings', 'إعدادات الصفحات') }}
                    </Button>
                    <Button type="button" :variant="activeTab === 'previews' ? 'default' : 'outline'" @click="activeTab = 'previews'">
                        {{ localize('Previews', 'المعاينات') }}
                    </Button>
                    <Button type="button" :variant="activeTab === 'analysis' ? 'default' : 'outline'" @click="activeTab = 'analysis'">
                        {{ localize('Content Analysis', 'تحليل المحتوى') }}
                    </Button>
                    <Button type="button" :variant="activeTab === 'social' ? 'default' : 'outline'" @click="activeTab = 'social'">
                        {{ localize('Social & Debuggers', 'السوشال وأدوات الفحص') }}
                    </Button>
                    <Button type="button" :variant="activeTab === 'technical' ? 'default' : 'outline'" @click="activeTab = 'technical'">
                        {{ localize('Technical SEO', 'SEO التقني') }}
                    </Button>
                </div>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-lg border p-5 space-y-4">
                    <div class="rounded-lg border bg-muted/30 p-4 text-sm">
                        <span class="font-medium">{{ localize('Editing language:', 'لغة التحرير:') }}</span>
                        {{ selectedSeoLocaleLabel }}
                        <span class="text-muted-foreground">({{ selectedSeoLocale.toUpperCase() }})</span>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="row in localeRows"
                            :key="row.code"
                            type="button"
                            :variant="selectedSeoLocale === row.code ? 'default' : 'outline'"
                            @click="selectedSeoLocale = row.code"
                        >
                            {{ localeCardLabel(row) }}
                            <span class="ml-2 text-xs opacity-70">{{ row.code.toUpperCase() }}</span>
                        </Button>
                    </div>
                </section>

                <section v-if="activeTab === 'overview'" class="space-y-4">
                    <section class="rounded-lg border bg-muted/20 p-4">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="space-y-1">
                                <h2 class="font-semibold">{{ localize('Overall SEO Status', 'الحالة العامة لـ SEO') }}</h2>
                                <p class="text-sm text-muted-foreground">{{ seoHealthStatus.description }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-sm font-semibold" :class="seoHealthStatus.className">
                                {{ seoHealthStatus.label }}
                            </span>
                        </div>
                    </section>

                    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="readiness in seoReadinessByLocale" :key="readiness.locale" class="rounded-lg border p-4">
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-medium">{{ readiness.label }}</div>
                                    <div class="text-xs text-muted-foreground">{{ readiness.locale.toUpperCase() }}</div>
                                </div>
                                <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="readiness.className">
                                    {{ readiness.percentage }}%
                                </span>
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ readiness.passedChecks }}/{{ readiness.totalChecks }} {{ localize('checks passed', 'فحص ناجح') }}
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ readiness.pagesReady }}/{{ readiness.totalPages }} {{ localize('pages fully ready', 'صفحات جاهزة بالكامل') }}
                            </div>
                        </div>
                    </section>
                </section>

                <section v-if="activeTab === 'general'">
                    <SeoGeneralSettings
                        :form="form"
                        :errors="form.errors"
                        :selected-locale="selectedSeoLocale"
                        :selected-locale-label="selectedSeoLocaleLabel"
                        :seo-og-image-files="seoOgImageFiles"
                    />
                </section>

                <section v-if="activeTab === 'pages'" class="space-y-4">
                    <section class="rounded-lg border p-5 space-y-4">
                        <div>
                            <h2 class="text-lg font-semibold">{{ localize('Page SEO Fields', 'حقول SEO للصفحات') }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ localize('Manage title, description, focus keyword, canonical URL, and robots for Landing and Fleet.', 'أدر العنوان والوصف والكلمة المفتاحية والرابط القانوني وrobots للصفحة الرئيسية والأسطول.') }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2 border-b pb-4">
                            <button
                                v-for="tab in pageSettingTabs"
                                :key="tab.key"
                                type="button"
                                class="rounded-full border px-3 py-2 text-sm transition-colors"
                                :class="activePageTab === tab.key ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-muted'"
                                @click="activePageTab = tab.key"
                            >
                                {{ tab.label }}
                            </button>
                        </div>

                        <div class="rounded-lg border bg-muted/30 p-4 text-sm">
                            <span class="font-medium">{{ localize('Editing language:', 'لغة التحرير:') }}</span>
                            {{ selectedSeoLocaleLabel }}
                            <span class="text-muted-foreground">({{ selectedSeoLocale.toUpperCase() }})</span>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2 md:col-span-2">
                                <Label>{{ localize('Title', 'العنوان') }}</Label>
                                <Input
                                    v-model="selectedPage.title[selectedSeoLocale]"
                                    :dir="selectedSeoLocaleDirection"
                                    :placeholder="activePageTab === 'home' ? previewName : localize('Fleet page title', 'عنوان صفحة الأسطول')"
                                />
                                <p v-if="form.errors[`seo.pages.${activePageTab}.title.${selectedSeoLocale}`]" class="text-sm text-red-600">
                                    {{ form.errors[`seo.pages.${activePageTab}.title.${selectedSeoLocale}`] }}
                                </p>
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <Label>{{ localize('Focus Keyword', 'الكلمة المفتاحية') }}</Label>
                                <Input
                                    v-model="selectedPage.focus_keyword[selectedSeoLocale]"
                                    :dir="selectedSeoLocaleDirection"
                                    :placeholder="activePageTab === 'home' ? seoPageDefaultFocusKeyword(activePageTab) : seoPageDefaultFocusKeyword(activePageTab)"
                                />
                                <p v-if="form.errors[`seo.pages.${activePageTab}.focus_keyword.${selectedSeoLocale}`]" class="text-sm text-red-600">
                                    {{ form.errors[`seo.pages.${activePageTab}.focus_keyword.${selectedSeoLocale}`] }}
                                </p>
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <Label>{{ localize('Description', 'الوصف') }}</Label>
                                <textarea
                                    v-model="selectedPage.description[selectedSeoLocale]"
                                    rows="4"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    :dir="selectedSeoLocaleDirection"
                                    :placeholder="seoPageDefaultDescription(activePageTab)"
                                />
                                <p v-if="form.errors[`seo.pages.${activePageTab}.description.${selectedSeoLocale}`]" class="text-sm text-red-600">
                                    {{ form.errors[`seo.pages.${activePageTab}.description.${selectedSeoLocale}`] }}
                                </p>
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <Label>{{ localize('Canonical URL', 'الرابط القانوني') }}</Label>
                                <Input v-model="selectedPage.canonical_url" :placeholder="`${seoPreviewBaseUrl}${seoPagePath(activePageTab)}`" />
                                <p v-if="form.errors[`seo.pages.${activePageTab}.canonical_url`]" class="text-sm text-red-600">
                                    {{ form.errors[`seo.pages.${activePageTab}.canonical_url`] }}
                                </p>
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <Label>{{ localize('Robots', 'تعليمات Robots') }}</Label>
                                <select v-model="selectedPage.robots" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                    <option value="">{{ localize('Use global default', 'استخدم الإعداد الافتراضي العام') }}</option>
                                    <option value="index,follow">index,follow</option>
                                    <option value="noindex,follow">noindex,follow</option>
                                    <option value="index,nofollow">index,nofollow</option>
                                    <option value="noindex,nofollow">noindex,nofollow</option>
                                </select>
                                <p v-if="form.errors[`seo.pages.${activePageTab}.robots`]" class="text-sm text-red-600">
                                    {{ form.errors[`seo.pages.${activePageTab}.robots`] }}
                                </p>
                            </div>
                        </div>
                    </section>
                </section>

                <section v-if="activeTab === 'previews'">
                    <SeoPreviewsSection :preview-cards="seoPreviewCardsData" :tenant-name="previewName" />
                </section>

                <section v-if="activeTab === 'analysis'">
                    <SeoContentAnalysis
                        :pages="seoPreviewCardsData.map((card) => ({
                            key: card.key,
                            label: card.label,
                            title: form.seo.pages[card.key].title,
                            description: form.seo.pages[card.key].description,
                            focusKeyword: form.seo.pages[card.key].focus_keyword,
                            slug: card.slug,
                        }))"
                        :selected-locale="selectedSeoLocale"
                    />
                </section>

                <section v-if="activeTab === 'social'">
                    <SeoSocialIntegration
                        :previews="seoPreviewCardsData.map((card) => ({
                            path: card.path,
                            label: card.label,
                        }))"
                        :tenant-name="previewName"
                    />
                </section>

                <section v-if="activeTab === 'technical'" class="space-y-6">
                    <SeoSitemapManagement v-model="form.seo.technical.sitemap" :base-url="seoPreviewBaseUrl" />
                    <SeoRobotsManagement v-model="form.seo.technical.robots" :base-url="seoPreviewBaseUrl" />
                    <SeoRedirectManager v-model="form.seo.technical.redirects" />
                </section>
            </form>
        </main>
    </SuperAdminLayout>
</template>
