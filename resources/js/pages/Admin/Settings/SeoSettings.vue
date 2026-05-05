<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SeoGeneralSettings from './Seo/SeoGeneralSettings.vue';
import SeoPreviewsSection from './Seo/SeoPreviewsSection.vue';
import SeoContentAnalysis from './Seo/SeoContentAnalysis.vue';
import SeoSocialIntegration from './Seo/SeoSocialIntegration.vue';
import SeoSitemapManagement from './Seo/SeoSitemapManagement.vue';
import SeoRobotsManagement from './Seo/SeoRobotsManagement.vue';
import SeoRedirectManager from './Seo/SeoRedirectManager.vue';

type LocalizedText = { en: string | null; ar: string | null };
type SeoPageKey = 'home' | 'fleet' | 'about' | 'contact' | 'car' | 'booking_checkout' | 'booking_confirmation';
type ActiveTab = 'overview' | 'general' | 'pages' | 'previews' | 'analysis' | 'social' | 'technical';
type SitemapPage = {
    path: string;
    priority: number;
    changeFreq: 'always' | 'hourly' | 'daily' | 'weekly' | 'monthly' | 'yearly' | 'never';
    lastmod?: string;
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
                home: { title: LocalizedText; description: LocalizedText; canonical_url: string | null; robots?: string | null };
                fleet: { title: LocalizedText; description: LocalizedText; canonical_url: string | null; robots?: string | null };
                about: { title: LocalizedText; description: LocalizedText; canonical_url: string | null; robots?: string | null };
                contact: { title: LocalizedText; description: LocalizedText; canonical_url: string | null; robots?: string | null };
                car: { title: LocalizedText; description: LocalizedText; canonical_url: string | null; robots?: string | null };
                booking_checkout: { title: LocalizedText; description: LocalizedText; canonical_url: string | null; robots?: string | null };
                booking_confirmation: { title: LocalizedText; description: LocalizedText; canonical_url: string | null; robots?: string | null };
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
const defaultSitemapDate = () => new Date().toISOString().split('T')[0];

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
            home: {
                title: {
                    en: props.settings.seo?.pages?.home?.title?.en ?? '',
                    ar: props.settings.seo?.pages?.home?.title?.ar ?? '',
                },
                description: {
                    en: props.settings.seo?.pages?.home?.description?.en ?? '',
                    ar: props.settings.seo?.pages?.home?.description?.ar ?? '',
                },
                canonical_url: props.settings.seo?.pages?.home?.canonical_url ?? '',
                robots: props.settings.seo?.pages?.home?.robots ?? '',
            },
            fleet: {
                title: {
                    en: props.settings.seo?.pages?.fleet?.title?.en ?? '',
                    ar: props.settings.seo?.pages?.fleet?.title?.ar ?? '',
                },
                description: {
                    en: props.settings.seo?.pages?.fleet?.description?.en ?? '',
                    ar: props.settings.seo?.pages?.fleet?.description?.ar ?? '',
                },
                canonical_url: props.settings.seo?.pages?.fleet?.canonical_url ?? '',
                robots: props.settings.seo?.pages?.fleet?.robots ?? '',
            },
            about: {
                title: {
                    en: props.settings.seo?.pages?.about?.title?.en ?? '',
                    ar: props.settings.seo?.pages?.about?.title?.ar ?? '',
                },
                description: {
                    en: props.settings.seo?.pages?.about?.description?.en ?? '',
                    ar: props.settings.seo?.pages?.about?.description?.ar ?? '',
                },
                canonical_url: props.settings.seo?.pages?.about?.canonical_url ?? '',
                robots: props.settings.seo?.pages?.about?.robots ?? '',
            },
            contact: {
                title: {
                    en: props.settings.seo?.pages?.contact?.title?.en ?? '',
                    ar: props.settings.seo?.pages?.contact?.title?.ar ?? '',
                },
                description: {
                    en: props.settings.seo?.pages?.contact?.description?.en ?? '',
                    ar: props.settings.seo?.pages?.contact?.description?.ar ?? '',
                },
                canonical_url: props.settings.seo?.pages?.contact?.canonical_url ?? '',
                robots: props.settings.seo?.pages?.contact?.robots ?? '',
            },
            car: {
                title: {
                    en: props.settings.seo?.pages?.car?.title?.en ?? '',
                    ar: props.settings.seo?.pages?.car?.title?.ar ?? '',
                },
                description: {
                    en: props.settings.seo?.pages?.car?.description?.en ?? '',
                    ar: props.settings.seo?.pages?.car?.description?.ar ?? '',
                },
                canonical_url: props.settings.seo?.pages?.car?.canonical_url ?? '',
                robots: props.settings.seo?.pages?.car?.robots ?? '',
            },
            booking_checkout: {
                title: {
                    en: props.settings.seo?.pages?.booking_checkout?.title?.en ?? '',
                    ar: props.settings.seo?.pages?.booking_checkout?.title?.ar ?? '',
                },
                description: {
                    en: props.settings.seo?.pages?.booking_checkout?.description?.en ?? '',
                    ar: props.settings.seo?.pages?.booking_checkout?.description?.ar ?? '',
                },
                canonical_url: props.settings.seo?.pages?.booking_checkout?.canonical_url ?? '',
                robots: props.settings.seo?.pages?.booking_checkout?.robots ?? '',
            },
            booking_confirmation: {
                title: {
                    en: props.settings.seo?.pages?.booking_confirmation?.title?.en ?? '',
                    ar: props.settings.seo?.pages?.booking_confirmation?.title?.ar ?? '',
                },
                description: {
                    en: props.settings.seo?.pages?.booking_confirmation?.description?.en ?? '',
                    ar: props.settings.seo?.pages?.booking_confirmation?.description?.ar ?? '',
                },
                canonical_url: props.settings.seo?.pages?.booking_confirmation?.canonical_url ?? '',
                robots: props.settings.seo?.pages?.booking_confirmation?.robots ?? '',
            },
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
const previewLogoUrl = computed(() => (form.seo.defaults.og_image || props.settings.logo_url || '').trim());
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

const localizedSeoText = (value: LocalizedText | undefined | null): string => {
    if (!value) return '';
    const preferred = locale.value === 'ar' ? value.ar : value.en;
    const fallback = locale.value === 'ar' ? value.en : value.ar;
    return String(preferred || fallback || '').trim();
};

const seoPagePath = (pageKey: SeoPageKey): string => {
    if (pageKey === 'home') return '/';
    if (pageKey === 'car') return '/fleet/sample-car';
    if (pageKey === 'booking_checkout') return '/booking/sample-reservation/checkout';
    if (pageKey === 'booking_confirmation') return '/booking/sample-reservation';
    return `/${pageKey}`;
};

const seoPageDefaultTitle = (pageKey: SeoPageKey): string => {
    const suffix = localizedSeoText(form.seo.defaults.title_suffix) || previewName.value;
    if (pageKey === 'home') return previewName.value;

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
};

const seoPageDefaultDescription = (pageKey: SeoPageKey): string => {
    const shared = localizedSeoText(form.seo.defaults.default_description);
    if (shared) return shared;

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
};

const seoPreviewCardsData = computed(() => {
    const pages: SeoPageKey[] = ['home', 'fleet', 'about', 'contact', 'car', 'booking_checkout', 'booking_confirmation'];
    const englishLabels: Record<SeoPageKey, string> = {
        home: 'Home Page',
        fleet: 'Fleet Page',
        about: 'About Page',
        contact: 'Contact Page',
        car: 'Car Details Page',
        booking_checkout: 'Booking Checkout Page',
        booking_confirmation: 'Booking Confirmation Page',
    };
    const arabicLabels: Record<SeoPageKey, string> = {
        home: 'الصفحة الرئيسية',
        fleet: 'صفحة الأسطول',
        about: 'صفحة من نحن',
        contact: 'صفحة اتصل بنا',
        car: 'صفحة السيارة',
        booking_checkout: 'صفحة إتمام الحجز',
        booking_confirmation: 'صفحة تأكيد الحجز',
    };

    return pages.map((pageKey) => {
        const title = localizedSeoText(form.seo.pages[pageKey].title) || seoPageDefaultTitle(pageKey);
        const description = localizedSeoText(form.seo.pages[pageKey].description) || seoPageDefaultDescription(pageKey);
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
                failLabel: localize('Recommended title length is 30-60 characters', 'الطول الموصى به للعنوان هو 30-60 حرفًا'),
            },
            {
                ok: description.length >= 70 && description.length <= 160,
                label: localize('Description length looks good', 'طول الوصف مناسب'),
                failLabel: localize('Recommended description length is 70-160 characters', 'الطول الموصى به للوصف هو 70-160 حرفًا'),
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
        ];

        return {
            key: pageKey,
            label: localize(englishLabels[pageKey], arabicLabels[pageKey]),
            title,
            description,
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
        const labels = seoBlockingPages.value.map((page) => page.label).join(', ');
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

const navigationTabs: Array<{ id: ActiveTab; label: string; icon: string; description: string }> = [
    { id: 'overview', label: localize('Dashboard', 'لوحة التحكم'), icon: '📊', description: localize('Overview', 'نظرة عامة') },
    { id: 'general', label: localize('General Settings', 'إعدادات عامة'), icon: '⚙️', description: localize('Defaults', 'الافتراضي') },
    { id: 'pages', label: localize('Page Settings', 'إعدادات الصفحات'), icon: '📄', description: localize('Per-page', 'لكل صفحة') },
    { id: 'previews', label: localize('Previews', 'المعاينات'), icon: '👁️', description: localize('Visual previews', 'معاينات بصرية') },
    { id: 'analysis', label: localize('Content Analysis', 'تحليل المحتوى'), icon: '📈', description: localize('Keywords & Readability', 'الكلمات والقراءة') },
    { id: 'social', label: localize('Social & Debuggers', 'التواصل والمصححات'), icon: '🔗', description: localize('External tools', 'أدوات خارجية') },
    { id: 'technical', label: localize('Technical Settings', 'الإعدادات التقنية'), icon: '🔧', description: localize('Sitemap, Robots, Redirects', 'Sitemap, Robots, إعادات التوجيه') },
];
</script>

<template>
    <Head :title="localize('SEO Settings', 'إعدادات SEO')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <!-- Header -->
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('SEO Settings', 'إعدادات SEO') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Manage and optimize all aspects of your SEO in one place.', 'أدر وحسّن جميع جوانب SEO الخاصة بك في مكان واحد.') }}
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

            <!-- Messages -->
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

            <!-- Navigation -->
            <div class="rounded-lg border bg-muted/20 p-3">
                <div class="flex gap-2 overflow-x-auto">
                    <button 
                        v-for="tab in navigationTabs" 
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        :class="[
                            'flex-shrink-0 flex items-center gap-2 px-4 py-2 rounded-md transition-colors whitespace-nowrap',
                            activeTab === tab.id 
                                ? 'bg-primary text-primary-foreground' 
                                : 'bg-background hover:bg-muted'
                        ]"
                    >
                        <span>{{ tab.icon }}</span>
                        <span class="text-sm font-medium">{{ tab.label }}</span>
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <form class="space-y-6" @submit.prevent="submit">
                <!-- Overview Tab -->
                <div v-if="activeTab === 'overview'" class="space-y-6">
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

                    <section class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
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

                <!-- General Settings Tab -->
                <div v-if="activeTab === 'general'">
                    <SeoGeneralSettings 
                        :form="form" 
                        :errors="form.errors"
                    />
                </div>

                <!-- Page Settings Tab -->
                <div v-if="activeTab === 'pages'" class="space-y-4">
                    <section class="rounded-lg border p-5 space-y-4">
                        <div>
                            <h2 class="text-lg font-semibold">{{ localize('Page SEO Fields', 'حقول SEO للصفحات') }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ localize('Manage each page separately. Use :car and :reservation placeholders where relevant.', 'أدر كل صفحة بشكل مستقل. استخدم المتغيرين :car و :reservation عند الحاجة.') }}
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div v-for="pageKey in (['home', 'fleet', 'about', 'contact', 'car', 'booking_checkout', 'booking_confirmation'] as const)" :key="pageKey" class="rounded-lg border p-4 space-y-3">
                                <h3 class="font-semibold">{{ pageKey }}</h3>
                                <div class="space-y-2">
                                    <Label>{{ localize('Title (EN)', 'العنوان (EN)') }}</Label>
                                    <Input v-model="form.seo.pages[pageKey].title.en" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Title (AR)', 'العنوان (AR)') }}</Label>
                                    <Input v-model="form.seo.pages[pageKey].title.ar" dir="rtl" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Description (EN)', 'الوصف (EN)') }}</Label>
                                    <textarea v-model="form.seo.pages[pageKey].description.en" rows="2" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Description (AR)', 'الوصف (AR)') }}</Label>
                                    <textarea v-model="form.seo.pages[pageKey].description.ar" rows="2" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Canonical URL', 'رابط Canonical') }}</Label>
                                    <Input v-model="form.seo.pages[pageKey].canonical_url" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Robots', 'Robots') }}</Label>
                                    <select v-model="form.seo.pages[pageKey].robots" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                        <option value="">{{ localize('Use global default', 'استخدم الافتراضي العام') }}</option>
                                        <option value="index,follow">index,follow</option>
                                        <option value="noindex,follow">noindex,follow</option>
                                        <option value="index,nofollow">index,nofollow</option>
                                        <option value="noindex,nofollow">noindex,nofollow</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Previews Tab -->
                <div v-if="activeTab === 'previews'">
                    <SeoPreviewsSection 
                        :preview-cards="seoPreviewCardsData"
                        :tenant-name="previewName"
                    />
                </div>

                <!-- Content Analysis Tab -->
                <div v-if="activeTab === 'analysis'">
                    <SeoContentAnalysis 
                        :pages="Object.entries(form.seo.pages).map(([key, value]) => ({
                            key: key as SeoPageKey,
                            title: value.title,
                            description: value.description,
                        }))"
                    />
                </div>

                <!-- Social Integration Tab -->
                <div v-if="activeTab === 'social'">
                    <SeoSocialIntegration 
                        :previews="seoPreviewCardsData.map((card) => ({
                            path: card.path,
                            label: card.label,
                        }))"
                        :tenant-name="previewName"
                    />
                </div>

                <!-- Technical Settings Tab (Sitemap, Robots, Redirects) -->
                <div v-if="activeTab === 'technical'" class="space-y-6">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <!-- Sitemap -->
                        <div class="lg:col-span-2">
                            <SeoSitemapManagement 
                                :base-url="seoPreviewBaseUrl"
                                v-model="form.seo.technical.sitemap"
                            />
                        </div>

                        <!-- Robots.txt -->
                        <div>
                            <SeoRobotsManagement 
                                :base-url="seoPreviewBaseUrl"
                                v-model="form.seo.technical.robots"
                            />
                        </div>

                        <!-- Redirects -->
                        <div>
                            <SeoRedirectManager v-model="form.seo.technical.redirects" />
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
