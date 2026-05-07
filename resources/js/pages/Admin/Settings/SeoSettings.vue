<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SeoContentAnalysis from './Seo/SeoContentAnalysis.vue';
import SeoGeneralSettings from './Seo/SeoGeneralSettings.vue';
import SeoPreviewsSection from './Seo/SeoPreviewsSection.vue';
import SeoRedirectManager from './Seo/SeoRedirectManager.vue';
import SeoRobotsManagement from './Seo/SeoRobotsManagement.vue';
import SeoSitemapManagement from './Seo/SeoSitemapManagement.vue';
import SeoSocialIntegration from './Seo/SeoSocialIntegration.vue';

type LocalizedText = { en: string | null; ar: string | null };
type SeoPageKey = 'home' | 'fleet' | 'about' | 'contact' | 'car' | 'booking_checkout' | 'booking_confirmation';
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
    canonical_url: string | null;
    robots?: string | null;
    focus_keyword?: LocalizedText;
};
type PreviewCard = {
    key: SeoPageKey;
    label: string;
    title: string;
    description: string;
    focusKeyword: string;
    path: string;
    robots: string;
    ogImage: string;
    twitterCardType: string;
    alternates: Array<{ locale: string; url: string }>;
    slug: string;
    score: number;
    checks: Array<{ ok: boolean; label: string; failLabel: string }>;
};

const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
    };
    settings: {
        site_name: string | null;
        logo_url: string | null;
        enabled_locales?: string[];
        seo: {
            defaults: {
                title_suffix: LocalizedText;
                default_description: LocalizedText;
                og_image: string | null;
                robots: string | null;
            };
            pages: {
                home: SeoPageSettings;
                fleet: SeoPageSettings;
                about: SeoPageSettings;
                contact: SeoPageSettings;
                car: SeoPageSettings;
                booking_checkout: SeoPageSettings;
                booking_confirmation: SeoPageSettings;
            };
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
    };
    actions: {
        update: string;
        website: string;
        seo_audit: string;
    };
}>();

const { locale } = useTrans();
const page = usePage<any>();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const activeTab = ref<ActiveTab>('overview');
const activePageTab = ref<SeoPageKey>('home');
const defaultSitemapDate = () => new Date().toISOString().split('T')[0];

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const form = useForm({
    seo: {
        defaults: {
            title_suffix: {
                en: props.settings.seo?.defaults?.title_suffix?.en ?? '',
                ar: props.settings.seo?.defaults?.title_suffix?.ar ?? '',
            },
            default_description: {
                en: props.settings.seo?.defaults?.default_description?.en ?? '',
                ar: props.settings.seo?.defaults?.default_description?.ar ?? '',
            },
            og_image: props.settings.seo?.defaults?.og_image ?? '',
            robots: props.settings.seo?.defaults?.robots ?? 'index,follow',
        },
        pages: {
            home: createPageFormState(props.settings.seo?.pages?.home),
            fleet: createPageFormState(props.settings.seo?.pages?.fleet),
            about: createPageFormState(props.settings.seo?.pages?.about),
            contact: createPageFormState(props.settings.seo?.pages?.contact),
            car: createPageFormState(props.settings.seo?.pages?.car),
            booking_checkout: createPageFormState(props.settings.seo?.pages?.booking_checkout),
            booking_confirmation: createPageFormState(props.settings.seo?.pages?.booking_confirmation),
        },
        technical: {
            sitemap: {
                pages: Array.isArray(props.settings.seo?.technical?.sitemap?.pages) && props.settings.seo.technical.sitemap.pages.length > 0
                    ? props.settings.seo.technical.sitemap.pages
                    : [
                        { path: '/', priority: 1.0, changeFreq: 'weekly', lastmod: defaultSitemapDate() },
                        { path: '/fleet', priority: 0.9, changeFreq: 'weekly', lastmod: defaultSitemapDate() },
                        { path: '/about', priority: 0.8, changeFreq: 'monthly', lastmod: defaultSitemapDate() },
                        { path: '/contact', priority: 0.8, changeFreq: 'monthly', lastmod: defaultSitemapDate() },
                    ],
            },
            robots: {
                allowAll: props.settings.seo?.technical?.robots?.allowAll ?? true,
                disallowPaths: props.settings.seo?.technical?.robots?.disallowPaths ?? ['/admin', '/private', '/api/internal'],
                crawlDelay: Number(props.settings.seo?.technical?.robots?.crawlDelay ?? 1),
                requestRate: Number(props.settings.seo?.technical?.robots?.requestRate ?? 30),
                sitemapUrl: props.settings.seo?.technical?.robots?.sitemapUrl ?? '/sitemap.xml',
            },
            redirects: {
                items: props.settings.seo?.technical?.redirects?.items ?? [],
            },
        },
    },
});

const formErrorList = computed(() => Object.values(form.errors ?? {}).filter((value): value is string => typeof value === 'string' && value.length > 0));
const previewName = computed(() => props.settings.site_name || props.tenant.name);
const enabledSeoLocales = computed(() => {
    const locales = Array.isArray(props.settings.enabled_locales) ? props.settings.enabled_locales : ['en', 'ar'];

    return locales
        .map((value) => String(value).trim())
        .filter((value, index, array) => value !== '' && array.indexOf(value) === index);
});
const seoPreviewBaseUrl = computed(() => {
    if (typeof window !== 'undefined' && window.location?.origin) {
        return window.location.origin;
    }

    return 'https://example.com';
});

const tabItems = computed(() => [
    { id: 'overview' as const, label: localize('Dashboard', 'لوحة SEO'), icon: '📊', description: localize('Health', 'الحالة') },
    { id: 'general' as const, label: localize('General Settings', 'الإعدادات العامة'), icon: '⚙️', description: localize('Defaults', 'الافتراضيات') },
    { id: 'pages' as const, label: localize('Page Settings', 'إعدادات الصفحات'), icon: '📄', description: localize('Per-page', 'لكل صفحة') },
    { id: 'previews' as const, label: localize('Previews', 'المعاينات'), icon: '👁️', description: localize('SERP & Social', 'البحث والسوشال') },
    { id: 'analysis' as const, label: localize('Content Analysis', 'تحليل المحتوى'), icon: '📈', description: localize('Yoast-like', 'شبيه Yoast') },
    { id: 'social' as const, label: localize('Social & Debuggers', 'السوشال وأدوات الفحص'), icon: '🔗', description: localize('External tools', 'أدوات خارجية') },
    { id: 'technical' as const, label: localize('Technical SEO', 'SEO التقني'), icon: '🛠️', description: localize('Sitemap / Robots / Redirects', 'Sitemap / Robots / Redirects') },
]);

const pageSettingTabs = computed(() => (
    ['home', 'fleet', 'about', 'contact', 'car', 'booking_checkout', 'booking_confirmation'] as const
).map((key) => ({
    key,
    label: seoPageLabel(key),
})));

const activePageSettings = computed(() => form.seo.pages[activePageTab.value]);
const activePreviewCard = computed(() => seoPreviewCardsData.value.find((preview) => preview.key === activePageTab.value) ?? seoPreviewCardsData.value[0]);

const seoPreviewCardsData = computed<PreviewCard[]>(() => {
    const pages: SeoPageKey[] = ['home', 'fleet', 'about', 'contact', 'car', 'booking_checkout', 'booking_confirmation'];

    return pages.map((pageKey) => {
        const title = localizedSeoText(form.seo.pages[pageKey].title) || seoPageDefaultTitle(pageKey);
        const description = localizedSeoText(form.seo.pages[pageKey].description) || seoPageDefaultDescription(pageKey);
        const focusKeyword = localizedSeoText(form.seo.pages[pageKey].focus_keyword);
        const path = form.seo.pages[pageKey].canonical_url || `${seoPreviewBaseUrl.value}${seoPagePath(pageKey)}`;
        const pageRobots = (form.seo.pages[pageKey].robots || '').trim();
        const robots = pageRobots || (
            pageKey === 'booking_checkout' || pageKey === 'booking_confirmation'
                ? 'noindex,nofollow'
                : (form.seo.defaults.robots || 'index,follow')
        );
        const canonicalValue = (form.seo.pages[pageKey].canonical_url || '').trim();
        const canonicalLooksValid = canonicalValue === '' || /^https?:\/\/\S+$/i.test(canonicalValue);
        const alternateUrls = enabledSeoLocales.value.map((localeKey) => ({
            locale: localeKey,
            url: path,
        }));
        alternateUrls.push({ locale: 'x-default', url: path });
        const pathname = (() => {
            try {
                return new URL(path, seoPreviewBaseUrl.value).pathname;
            } catch {
                return seoPagePath(pageKey);
            }
        })();
        const normalizedSlug = pathname.replace(/\/+/g, '/').replace(/^\/|\/$/g, '');
        const slugLooksValid = normalizedSlug !== '' && /^[a-z0-9/_-]+$/i.test(normalizedSlug) && !/\s/.test(normalizedSlug);
        const hreflangLooksValid = alternateUrls.length === enabledSeoLocales.value.length + 1 && enabledSeoLocales.value.length > 0;
        const checks = [
            {
                ok: title.length >= 30 && title.length <= 60,
                label: localize('Title length looks good', 'طول العنوان مناسب'),
                failLabel: localize('Recommended title length is 30-60 characters', 'الطول الموصى به للعنوان هو 30-60 حرفاً'),
            },
            {
                ok: description.length >= 70 && description.length <= 160,
                label: localize('Description length looks good', 'طول الوصف مناسب'),
                failLabel: localize('Recommended description length is 70-160 characters', 'الطول الموصى به للوصف هو 70-160 حرفاً'),
            },
            {
                ok: Boolean((form.seo.defaults.og_image || props.settings.logo_url || '').trim()),
                label: localize('Open Graph image is set', 'صورة Open Graph مضبوطة'),
                failLabel: localize('Set an Open Graph image for sharing previews', 'حدد صورة Open Graph لمعاينات المشاركة'),
            },
            {
                ok: canonicalLooksValid,
                label: localize('Canonical URL is valid', 'رابط Canonical صحيح'),
                failLabel: localize('Canonical URL must start with http:// or https://', 'رابط Canonical يجب أن يبدأ بـ http:// أو https://'),
            },
            {
                ok: slugLooksValid,
                label: localize('Slug format looks clean', 'تنسيق الرابط المختصر سليم'),
                failLabel: localize('Slug should use clean URL segments without spaces', 'يجب أن يستخدم الرابط المختصر مقاطع نظيفة بدون مسافات'),
            },
            {
                ok: hreflangLooksValid,
                label: localize('hreflang alternates are available for enabled locales', 'روابط hreflang متوفرة للغات المفعلة'),
                failLabel: localize('hreflang alternates are missing for one or more enabled locales', 'روابط hreflang مفقودة لإحدى اللغات المفعلة أو أكثر'),
            },
            {
                ok: focusKeyword.length > 0,
                label: localize('Focus keyword is defined', 'تم تحديد الكلمة المفتاحية الرئيسية'),
                failLabel: localize('Set a focus keyword to evaluate this page like Yoast', 'حدد كلمة مفتاحية رئيسية لتقييم الصفحة بشكل أقرب إلى Yoast'),
            },
            {
                ok: focusKeyword.length === 0 || includesNormalized(title, focusKeyword),
                label: localize('Focus keyword appears in the title', 'الكلمة المفتاحية موجودة في العنوان'),
                failLabel: localize('Add the focus keyword to the page title', 'أضف الكلمة المفتاحية إلى عنوان الصفحة'),
            },
            {
                ok: focusKeyword.length === 0 || includesNormalized(description, focusKeyword),
                label: localize('Focus keyword appears in the description', 'الكلمة المفتاحية موجودة في الوصف'),
                failLabel: localize('Add the focus keyword to the page description', 'أضف الكلمة المفتاحية إلى وصف الصفحة'),
            },
            {
                ok: focusKeyword.length === 0 || includesNormalized(normalizedSlug, focusKeyword),
                label: localize('Focus keyword appears in the slug', 'الكلمة المفتاحية موجودة في الرابط المختصر'),
                failLabel: localize('Try to include the focus keyword in the slug when possible', 'حاول تضمين الكلمة المفتاحية في الرابط المختصر متى كان ذلك مناسباً'),
            },
        ];

        return {
            key: pageKey,
            label: seoPageLabel(pageKey),
            title,
            description,
            focusKeyword,
            path,
            robots,
            ogImage: (form.seo.defaults.og_image || props.settings.logo_url || '').trim(),
            twitterCardType: 'summary_large_image',
            alternates: alternateUrls,
            slug: normalizedSlug,
            score: checks.filter((check) => check.ok).length,
            checks,
        };
    });
});

const seoBlockingPages = computed(() => seoPreviewCardsData.value.filter((preview) => preview.score === 0));
const seoHealthStatus = computed(() => {
    const totalChecks = seoPreviewCardsData.value.reduce((sum, preview) => sum + preview.checks.length, 0);
    const passedChecks = seoPreviewCardsData.value.reduce((sum, preview) => sum + preview.score, 0);
    const ratio = totalChecks > 0 ? passedChecks / totalChecks : 0;

    if (ratio >= 0.85) {
        return {
            label: localize('Good', 'جيد'),
            description: localize('Most SEO signals are in good shape.', 'معظم إشارات SEO في وضع جيد.'),
            className: 'bg-emerald-100 text-emerald-700',
        };
    }

    if (ratio >= 0.5) {
        return {
            label: localize('Needs Work', 'يحتاج تحسين'),
            description: localize('Some pages still need SEO cleanup.', 'بعض الصفحات ما زالت تحتاج تحسين SEO.'),
            className: 'bg-amber-100 text-amber-700',
        };
    }

    return {
        label: localize('Critical', 'حرج'),
        description: localize('SEO coverage is weak and should be fixed before publishing changes.', 'تغطية SEO ضعيفة ويجب إصلاحها قبل اعتماد التغييرات.'),
        className: 'bg-red-100 text-red-700',
    };
});

const seoSaveBlockedMessage = ref('');
const pageSettingHint = computed(() => {
    if (activePageTab.value === 'car') {
        return localize('Use :car in the title or description to inject the current car name.', 'استخدم :car داخل العنوان أو الوصف لإدراج اسم السيارة الحالية.');
    }

    if (activePageTab.value === 'booking_checkout' || activePageTab.value === 'booking_confirmation') {
        return localize('Use :reservation in the title or description to inject the reservation number.', 'استخدم :reservation داخل العنوان أو الوصف لإدراج رقم الحجز.');
    }

    return localize('Configure this page independently from the rest of the site.', 'اضبط هذه الصفحة بشكل مستقل عن بقية الموقع.');
});

function createPageFormState(page?: SeoPageSettings) {
    return {
        title: {
            en: page?.title?.en ?? '',
            ar: page?.title?.ar ?? '',
        },
        description: {
            en: page?.description?.en ?? '',
            ar: page?.description?.ar ?? '',
        },
        canonical_url: page?.canonical_url ?? '',
        robots: page?.robots ?? '',
        focus_keyword: {
            en: page?.focus_keyword?.en ?? '',
            ar: page?.focus_keyword?.ar ?? '',
        },
    };
}

function localizedSeoText(value: LocalizedText | undefined | null): string {
    if (!value) {
        return '';
    }

    const preferred = locale.value === 'ar' ? value.ar : value.en;
    const fallback = locale.value === 'ar' ? value.en : value.ar;

    return String(preferred || fallback || '').trim();
}

function seoPagePath(pageKey: SeoPageKey): string {
    if (pageKey === 'home') return '/';
    if (pageKey === 'car') return '/fleet/sample-car';
    if (pageKey === 'booking_checkout') return '/booking/sample-reservation/checkout';
    if (pageKey === 'booking_confirmation') return '/booking/sample-reservation';

    return `/${pageKey}`;
}

function seoPageDefaultTitle(pageKey: SeoPageKey): string {
    const suffix = localizedSeoText(form.seo.defaults.title_suffix) || previewName.value;

    if (pageKey === 'home') {
        return previewName.value;
    }

    const labels: Record<SeoPageKey, string> = {
        home: localize('Home', 'الرئيسية'),
        fleet: localize('Fleet', 'الأسطول'),
        about: localize('About', 'من نحن'),
        contact: localize('Contact', 'اتصل بنا'),
        car: localize('Car Rental', 'تأجير سيارة'),
        booking_checkout: localize('Booking Checkout', 'إتمام الحجز'),
        booking_confirmation: localize('Booking Confirmation', 'تأكيد الحجز'),
    };

    return `${labels[pageKey]} | ${suffix}`;
}

function seoPageDefaultDescription(pageKey: SeoPageKey): string {
    const shared = localizedSeoText(form.seo.defaults.default_description);

    if (shared) {
        return shared;
    }

    const descriptions: Record<SeoPageKey, string> = {
        home: localize(`Discover ${previewName.value} and reserve your next rental car online.`, `اكتشف ${previewName.value} واحجز سيارتك القادمة عبر الإنترنت.`),
        fleet: localize(`Browse available rental vehicles from ${previewName.value}.`, `استعرض السيارات المتاحة من ${previewName.value}.`),
        about: localize(`Learn more about ${previewName.value} and its car rental services.`, `تعرّف أكثر على ${previewName.value} وخدمات تأجير السيارات.`),
        contact: localize(`Get in touch with ${previewName.value} for bookings and support.`, `تواصل مع ${previewName.value} للحجوزات والدعم.`),
        car: localize(`View rental car details and pricing from ${previewName.value}.`, `اطلع على تفاصيل السيارة وسعر الإيجار لدى ${previewName.value}.`),
        booking_checkout: localize(`Choose your payment provider and complete your booking securely with ${previewName.value}.`, `اختر مزود الدفع وأكمل الحجز بأمان مع ${previewName.value}.`),
        booking_confirmation: localize(`Review your confirmed booking and reservation details from ${previewName.value}.`, `راجع تفاصيل الحجز المؤكد ومعلومات الحجز لدى ${previewName.value}.`),
    };

    return descriptions[pageKey];
}

function seoPageLabel(pageKey: SeoPageKey): string {
    const labels: Record<SeoPageKey, { en: string; ar: string }> = {
        home: { en: 'Home Page', ar: 'الصفحة الرئيسية' },
        fleet: { en: 'Fleet Page', ar: 'صفحة الأسطول' },
        about: { en: 'About Page', ar: 'صفحة من نحن' },
        contact: { en: 'Contact Page', ar: 'صفحة اتصل بنا' },
        car: { en: 'Car Details Page', ar: 'صفحة السيارة' },
        booking_checkout: { en: 'Booking Checkout Page', ar: 'صفحة إتمام الحجز' },
        booking_confirmation: { en: 'Booking Confirmation Page', ar: 'صفحة تأكيد الحجز' },
    };

    return localize(labels[pageKey].en, labels[pageKey].ar);
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

function exportSeoReport() {
    const lines = [
        `Tenant: ${props.tenant.name}`,
        `Slug: ${props.tenant.slug}`,
        `Overall Status: ${seoHealthStatus.value.label}`,
        `Enabled Locales: ${enabledSeoLocales.value.join(', ')}`,
        '',
        ...seoPreviewCardsData.value.flatMap((preview) => [
            `[${preview.label}]`,
            `Title: ${preview.title}`,
            `Description: ${preview.description}`,
            `Focus keyword: ${preview.focusKeyword || 'N/A'}`,
            `Canonical: ${preview.path}`,
            `Slug: ${preview.slug}`,
            `Robots: ${preview.robots}`,
            `OG Image: ${preview.ogImage || 'N/A'}`,
            `Twitter Card: ${preview.twitterCardType}`,
            `Alternates: ${preview.alternates.map((alternate) => `${alternate.locale}=${alternate.url}`).join(' | ')}`,
            `Score: ${preview.score}/${preview.checks.length}`,
            ...preview.checks.map((check) => `- ${check.ok ? 'PASS' : 'WARN'}: ${check.ok ? check.label : check.failLabel}`),
            '',
        ]),
    ].join('\n');

    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    const blob = new Blob([lines], { type: 'text/plain;charset=utf-8' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${props.tenant.slug || 'tenant'}-seo-report.txt`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
}

function submit() {
    if (seoBlockingPages.value.length > 0) {
        const labels = seoBlockingPages.value.map((pageItem) => pageItem.label).join(', ');
        seoSaveBlockedMessage.value = localize(
            `SEO save blocked. Fix these pages first: ${labels}.`,
            `تم منع الحفظ بسبب ضعف SEO في هذه الصفحات: ${labels}.`,
        );
        return;
    }

    form.put(props.actions.update, {
        preserveScroll: true,
        onSuccess: () => {
            seoSaveBlockedMessage.value = '';
        },
    });
}
</script>

<template>
    <Head :title="localize('SEO Settings', 'إعدادات SEO')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('SEO Settings', 'إعدادات SEO') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Manage page titles, descriptions, canonical URLs, focus keywords, and technical SEO from one screen.', 'أدر عناوين الصفحات والأوصاف وروابط canonical والكلمات المفتاحية وSEO التقني من شاشة واحدة.') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="props.actions.website">
                        <Button variant="outline">{{ localize('Back To General Settings', 'الرجوع للإعدادات العامة') }}</Button>
                    </Link>
                    <Link :href="props.actions.seo_audit">
                        <Button variant="outline">{{ localize('Open SEO Audit', 'فتح تدقيق SEO') }}</Button>
                    </Link>
                    <Button :disabled="form.processing" @click="submit">
                        {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save SEO Changes', 'حفظ تغييرات SEO') }}
                    </Button>
                </div>
            </div>

            <div v-if="flashSuccess" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ flashError }}
            </div>
            <div v-if="formErrorList.length" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <div class="font-medium">{{ localize('Please fix the following errors:', 'يرجى تصحيح الأخطاء التالية:') }}</div>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="(message, idx) in formErrorList" :key="idx">{{ message }}</li>
                </ul>
            </div>
            <div v-if="seoSaveBlockedMessage" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                {{ seoSaveBlockedMessage }}
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section class="grid gap-6 xl:grid-cols-[260px_minmax(0,1fr)]">
                    <aside class="rounded-xl border bg-background p-4 xl:sticky xl:top-6 xl:self-start">
                        <div class="mb-4">
                            <h2 class="font-semibold">{{ localize('SEO Navigation', 'تنقل SEO') }}</h2>
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Split by section like a practical Yoast workflow.', 'مقسمة إلى أقسام مثل تدفق عمل Yoast العملي.') }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <button
                                v-for="tab in tabItems"
                                :key="tab.id"
                                type="button"
                                class="w-full rounded-lg border px-3 py-3 text-left transition-colors"
                                :class="activeTab === tab.id ? 'border-primary bg-primary/5 text-primary' : 'border-border hover:bg-muted/60'"
                                @click="activeTab = tab.id"
                            >
                                <div class="flex items-start gap-3">
                                    <span class="text-lg">{{ tab.icon }}</span>
                                    <div class="min-w-0">
                                        <div class="font-medium">{{ tab.label }}</div>
                                        <div class="text-xs text-muted-foreground">{{ tab.description }}</div>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </aside>

                    <div class="space-y-6">
                        <section class="rounded-lg border bg-muted/20 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-1">
                                    <h2 class="font-semibold">{{ localize('Overall SEO Status', 'الحالة العامة لـ SEO') }}</h2>
                                    <p class="text-sm text-muted-foreground">{{ seoHealthStatus.description }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Button type="button" variant="outline" @click="exportSeoReport">
                                        {{ localize('Export SEO Report', 'تصدير تقرير SEO') }}
                                    </Button>
                                    <span class="rounded-full px-3 py-1 text-sm font-semibold" :class="seoHealthStatus.className">
                                        {{ seoHealthStatus.label }}
                                    </span>
                                </div>
                            </div>
                        </section>

                        <div v-if="activeTab === 'overview'" class="space-y-4">
                            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                <div v-for="preview in seoPreviewCardsData" :key="preview.key" class="rounded-lg border p-4">
                                    <div class="mb-3 flex items-start justify-between gap-2">
                                        <div>
                                            <div class="font-medium text-sm">{{ preview.label }}</div>
                                            <div class="text-xs text-muted-foreground">{{ preview.score }}/{{ preview.checks.length }} {{ localize('checks', 'فحوصات') }}</div>
                                        </div>
                                        <div class="text-2xl font-bold" :class="preview.score === preview.checks.length ? 'text-emerald-600' : 'text-amber-600'">
                                            {{ Math.round((preview.score / preview.checks.length) * 100) }}%
                                        </div>
                                    </div>
                                    <div class="mb-3 rounded-md bg-muted px-3 py-2 text-xs">
                                        <div class="font-medium">{{ localize('Focus keyword', 'الكلمة المفتاحية') }}</div>
                                        <div class="text-muted-foreground">{{ preview.focusKeyword || localize('Not set', 'غير محددة') }}</div>
                                    </div>
                                    <div class="space-y-1">
                                        <div v-for="(check, idx) in preview.checks" :key="idx" class="flex items-start gap-2 text-xs">
                                            <span class="mt-0.5 h-1.5 w-1.5 rounded-full flex-shrink-0" :class="check.ok ? 'bg-emerald-500' : 'bg-amber-500'" />
                                            <span :class="check.ok ? 'text-emerald-700' : 'text-amber-700'">
                                                {{ check.ok ? check.label : check.failLabel }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div v-if="activeTab === 'general'">
                            <SeoGeneralSettings :form="form" :errors="form.errors" />
                        </div>

                        <div v-if="activeTab === 'pages'" class="space-y-4">
                            <section class="rounded-lg border p-5 space-y-4">
                                <div>
                                    <h2 class="text-lg font-semibold">{{ localize('Page SEO Fields', 'حقول SEO للصفحات') }}</h2>
                                    <p class="text-sm text-muted-foreground">
                                        {{ localize('Pick a page, then manage title, description, canonical, robots, and focus keyword for that page.', 'اختر صفحة ثم اضبط العنوان والوصف وcanonical وrobots والكلمة المفتاحية الخاصة بها.') }}
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

                                <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                                    <div class="space-y-4">
                                        <div class="rounded-lg border p-4">
                                            <h3 class="font-semibold">{{ seoPageLabel(activePageTab) }}</h3>
                                            <p class="mt-1 text-sm text-muted-foreground">{{ pageSettingHint }}</p>
                                        </div>

                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div class="space-y-2">
                                                <Label>{{ localize('Title (EN)', 'العنوان (EN)') }}</Label>
                                                <Input v-model="activePageSettings.title.en" />
                                            </div>
                                            <div class="space-y-2">
                                                <Label>{{ localize('Title (AR)', 'العنوان (AR)') }}</Label>
                                                <Input v-model="activePageSettings.title.ar" dir="rtl" />
                                            </div>

                                            <div class="space-y-2">
                                                <Label>{{ localize('Focus Keyword (EN)', 'الكلمة المفتاحية (EN)') }}</Label>
                                                <Input v-model="activePageSettings.focus_keyword.en" placeholder="car rental oman" />
                                            </div>
                                            <div class="space-y-2">
                                                <Label>{{ localize('Focus Keyword (AR)', 'الكلمة المفتاحية (AR)') }}</Label>
                                                <Input v-model="activePageSettings.focus_keyword.ar" dir="rtl" placeholder="تأجير سيارات عمان" />
                                            </div>

                                            <div class="space-y-2 md:col-span-2">
                                                <Label>{{ localize('Description (EN)', 'الوصف (EN)') }}</Label>
                                                <textarea v-model="activePageSettings.description.en" rows="4" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                                            </div>
                                            <div class="space-y-2 md:col-span-2">
                                                <Label>{{ localize('Description (AR)', 'الوصف (AR)') }}</Label>
                                                <textarea v-model="activePageSettings.description.ar" rows="4" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                                            </div>

                                            <div class="space-y-2 md:col-span-2">
                                                <Label>{{ localize('Canonical URL', 'رابط Canonical') }}</Label>
                                                <Input v-model="activePageSettings.canonical_url" placeholder="https://example.com/page" />
                                            </div>

                                            <div class="space-y-2 md:col-span-2">
                                                <Label>{{ localize('Robots', 'Robots') }}</Label>
                                                <select v-model="activePageSettings.robots" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                                    <option value="">{{ localize('Use global default', 'استخدم الافتراضي العام') }}</option>
                                                    <option value="index,follow">index,follow</option>
                                                    <option value="noindex,follow">noindex,follow</option>
                                                    <option value="index,nofollow">index,nofollow</option>
                                                    <option value="noindex,nofollow">noindex,nofollow</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="activePreviewCard" class="space-y-4">
                                        <div class="rounded-lg border p-4">
                                            <div class="mb-2 flex items-center justify-between gap-2">
                                                <h3 class="font-semibold">{{ localize('Live Score', 'النتيجة الحالية') }}</h3>
                                                <div class="text-lg font-bold" :class="activePreviewCard.score === activePreviewCard.checks.length ? 'text-emerald-600' : 'text-amber-600'">
                                                    {{ activePreviewCard.score }}/{{ activePreviewCard.checks.length }}
                                                </div>
                                            </div>
                                            <div class="space-y-2">
                                                <div v-for="(check, idx) in activePreviewCard.checks" :key="`${activePreviewCard.key}-live-${idx}`" class="flex items-start gap-2 text-sm">
                                                    <span class="mt-1 h-2 w-2 rounded-full flex-shrink-0" :class="check.ok ? 'bg-emerald-500' : 'bg-amber-500'" />
                                                    <span :class="check.ok ? 'text-emerald-700' : 'text-amber-700'">
                                                        {{ check.ok ? check.label : check.failLabel }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="rounded-lg border p-4 space-y-2">
                                            <h3 class="font-semibold">{{ localize('Resolved Preview', 'المعاينة النهائية') }}</h3>
                                            <div class="text-xs text-emerald-700 break-all">{{ activePreviewCard.path }}</div>
                                            <div class="font-semibold text-blue-700">{{ activePreviewCard.title }}</div>
                                            <div class="text-sm text-slate-600">{{ activePreviewCard.description }}</div>
                                            <div class="text-xs text-muted-foreground">{{ localize('Slug', 'الرابط المختصر') }}: {{ activePreviewCard.slug || '/' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div v-if="activeTab === 'previews'">
                            <SeoPreviewsSection :preview-cards="seoPreviewCardsData" :tenant-name="previewName" />
                        </div>

                        <div v-if="activeTab === 'analysis'">
                            <SeoContentAnalysis
                                :pages="seoPreviewCardsData.map((card) => ({
                                    key: card.key,
                                    label: card.label,
                                    title: form.seo.pages[card.key].title,
                                    description: form.seo.pages[card.key].description,
                                    focusKeyword: form.seo.pages[card.key].focus_keyword,
                                    slug: card.slug,
                                }))"
                            />
                        </div>

                        <div v-if="activeTab === 'social'">
                            <SeoSocialIntegration
                                :previews="seoPreviewCardsData.map((card) => ({
                                    path: card.path,
                                    label: card.label,
                                }))"
                                :tenant-name="previewName"
                            />
                        </div>

                        <div v-if="activeTab === 'technical'" class="space-y-6">
                            <SeoSitemapManagement v-model="form.seo.technical.sitemap" :base-url="seoPreviewBaseUrl" />
                            <SeoRobotsManagement v-model="form.seo.technical.robots" :base-url="seoPreviewBaseUrl" />
                            <SeoRedirectManager v-model="form.seo.technical.redirects" />
                        </div>
                    </div>
                </section>
            </form>
        </main>
    </AdminLayout>
</template>
