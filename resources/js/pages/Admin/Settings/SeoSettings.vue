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

type LocalizedText = Record<string, string | null>;
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
    ogImageAlt: string;
    twitterCardType: string;
    alternates: Array<{ locale: string; url: string }>;
    slug: string;
    score: number;
    checks: Array<{ ok: boolean; label: string; failLabel: string }>;
};

const SEO_PAGE_KEYS: SeoPageKey[] = ['home', 'fleet', 'about', 'contact', 'car'];

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
                og_image_alt: LocalizedText;
                robots: string | null;
            };
            pages: Record<SeoPageKey, SeoPageSettings>;
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
    seoOgImageFiles?: Array<{ id: number; url: string }>;
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
const previewName = computed(() => props.settings.site_name || props.tenant.name);
const enabledSeoLocales = computed(() => {
    const locales = Array.isArray(props.settings.enabled_locales) ? props.settings.enabled_locales : ['en'];

    return locales
        .map((value) => String(value).trim())
        .filter((value, index, array) => value !== '' && array.indexOf(value) === index);
});
const selectedSeoLocale = ref(enabledSeoLocales.value.includes(locale.value) ? locale.value : (enabledSeoLocales.value[0] || 'en'));

function localeLabel(localeKey: string): string {
    const labels: Record<string, { en: string; ar: string }> = {
        en: { en: 'English', ar: 'الإنجليزية' },
        ar: { en: 'Arabic', ar: 'العربية' },
        fr: { en: 'French', ar: 'الفرنسية' },
        ur: { en: 'Urdu', ar: 'الأردية' },
        tr: { en: 'Turkish', ar: 'التركية' },
    };

    const resolved = labels[localeKey];
    if (resolved) {
        return localize(resolved.en, resolved.ar);
    }

    return localeKey.toUpperCase();
}

function createLocalizedState(value?: LocalizedText | null): LocalizedText {
    const result: LocalizedText = {};

    for (const localeKey of enabledSeoLocales.value) {
        result[localeKey] = String(value?.[localeKey] ?? '').trim();
    }

    return result;
}

function createPageFormState(pageSettings?: SeoPageSettings): SeoPageSettings {
    return {
        title: createLocalizedState(pageSettings?.title),
        description: createLocalizedState(pageSettings?.description),
        canonical_url: pageSettings?.canonical_url ?? '',
        robots: pageSettings?.robots ?? '',
        focus_keyword: createLocalizedState(pageSettings?.focus_keyword),
    };
}

const form = useForm({
    seo_og_image_temp_folders: [] as string[],
    seo_og_image_removed_files: [] as number[],
    seo: {
        defaults: {
            title_suffix: createLocalizedState(props.settings.seo?.defaults?.title_suffix),
            default_description: createLocalizedState(props.settings.seo?.defaults?.default_description),
            og_image: props.settings.seo?.defaults?.og_image ?? '',
            og_image_alt: createLocalizedState(props.settings.seo?.defaults?.og_image_alt),
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
        } as Record<SeoPageKey, SeoPageSettings>,
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
const seoPreviewBaseUrl = computed(() => (typeof window !== 'undefined' && window.location?.origin) ? window.location.origin : 'https://example.com');
const selectedSeoLocaleLabel = computed(() => localeLabel(selectedSeoLocale.value));

const tabItems = computed(() => [
    { id: 'overview' as const, label: localize('Dashboard', 'لوحة SEO'), icon: '●', description: localize('Health', 'الحالة') },
    { id: 'general' as const, label: localize('General Settings', 'الإعدادات العامة'), icon: '●', description: localize('Defaults', 'الافتراضيات') },
    { id: 'pages' as const, label: localize('Page Settings', 'إعدادات الصفحات'), icon: '●', description: localize('Per-page', 'لكل صفحة') },
    { id: 'previews' as const, label: localize('Previews', 'المعاينات'), icon: '●', description: localize('SERP & Social', 'البحث والسوشال') },
    { id: 'analysis' as const, label: localize('Content Analysis', 'تحليل المحتوى'), icon: '●', description: localize('Yoast-like', 'شبيه Yoast') },
    { id: 'social' as const, label: localize('Social & Debuggers', 'السوشال وأدوات الفحص'), icon: '●', description: localize('External tools', 'أدوات خارجية') },
    { id: 'technical' as const, label: localize('Technical SEO', 'SEO التقني'), icon: '●', description: localize('Sitemap / Robots / Redirects', 'Sitemap / Robots / Redirects') },
]);

const pageSettingTabs = computed(() => SEO_PAGE_KEYS.map((key) => ({ key, label: seoPageLabel(key) })));
const activePageSettings = computed(() => form.seo.pages[activePageTab.value]);
const activeTitlePlaceholder = computed(() => {
    const placeholder = seoPageDefaultTitle(activePageTab.value, selectedSeoLocale.value);

    return activePageTab.value === 'car' ? replaceCarSeoPlaceholders(placeholder) : placeholder;
});
const activeDescriptionPlaceholder = computed(() => {
    const placeholder = seoPageDefaultDescription(activePageTab.value, selectedSeoLocale.value);

    return activePageTab.value === 'car' ? replaceCarSeoPlaceholders(placeholder) : placeholder;
});
const activeFocusKeywordPlaceholder = computed(() => {
    if (activePageTab.value === 'car') {
        return selectedSeoLocale.value === 'ar' ? ':make :model :year' : ':make :model :year';
    }

    return selectedSeoLocale.value === 'ar' ? 'تأجير سيارات عمان' : 'car rental oman';
});
const activeCanonicalPlaceholder = computed(() => `${seoPreviewBaseUrl.value}${seoPagePath(activePageTab.value)}`);

function localizedSeoText(value: LocalizedText | undefined | null, localeKey = selectedSeoLocale.value): string {
    if (!value) {
        return '';
    }

    const preferred = String(value[localeKey] || '').trim();
    if (preferred !== '') {
        return preferred;
    }

    for (const fallback of ['en', 'ar']) {
        const resolved = String(value[fallback] || '').trim();
        if (resolved !== '') {
            return resolved;
        }
    }

    const firstAvailable = Object.values(value).find((item) => String(item || '').trim() !== '');

    return String(firstAvailable || '').trim();
}

function seoPagePath(pageKey: SeoPageKey): string {
    if (pageKey === 'home') return '/';
    if (pageKey === 'car') return '/fleet/sample-car';
    if (pageKey === 'booking_checkout') return '/booking/sample-reservation/checkout';
    if (pageKey === 'booking_confirmation') return '/booking/sample-reservation';

    return `/${pageKey}`;
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

function seoPageDefaultTitle(pageKey: SeoPageKey, localeKey = selectedSeoLocale.value): string {
    const suffix = localizedSeoText(form.seo.defaults.title_suffix, localeKey) || previewName.value;

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

function seoPageDefaultDescription(pageKey: SeoPageKey, localeKey = selectedSeoLocale.value): string {
    const shared = localizedSeoText(form.seo.defaults.default_description, localeKey);

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

function replaceCarSeoPlaceholders(text: string): string {
    const replaced = String(text || '').replace(/:car|:make|:model|:year|:model_year|:transmission|:seats|:fuel_type/g, (token) => {
        const samples: Record<string, string> = {
            ':car': '2026 Toyota Corolla',
            ':make': 'Toyota',
            ':model': 'Corolla',
            ':year': '2026',
            ':model_year': '2026',
            ':transmission': localize('Automatic', 'أوتوماتيك'),
            ':seats': '5',
            ':fuel_type': localize('Gasoline', 'بنزين'),
        };

        return samples[token] ?? token;
    });

    return replaced.replace(/\s+/g, ' ').replace(/\s+([,.;:!?])/g, '$1').trim();
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
    return SEO_PAGE_KEYS.map((pageKey) => {
        let title = localizedSeoText(form.seo.pages[pageKey].title, localeKey) || seoPageDefaultTitle(pageKey, localeKey);
        let description = localizedSeoText(form.seo.pages[pageKey].description, localeKey) || seoPageDefaultDescription(pageKey, localeKey);
        const focusKeyword = localizedSeoText(form.seo.pages[pageKey].focus_keyword, localeKey);
        const ogImageAlt = localizedSeoText(form.seo.defaults.og_image_alt, localeKey);

        if (pageKey === 'car') {
            title = replaceCarSeoPlaceholders(title);
            description = replaceCarSeoPlaceholders(description);
        }

        const path = form.seo.pages[pageKey].canonical_url || `${seoPreviewBaseUrl.value}${seoPagePath(pageKey)}`;
        const pageRobots = String(form.seo.pages[pageKey].robots || '').trim();
        const robots = pageRobots || (
            pageKey === 'booking_checkout' || pageKey === 'booking_confirmation'
                ? 'noindex,nofollow'
                : (form.seo.defaults.robots || 'index,follow')
        );
        const canonicalValue = String(form.seo.pages[pageKey].canonical_url || '').trim();
        const canonicalLooksValid = canonicalValue === '' || /^https?:\/\/\S+$/i.test(canonicalValue);
        const alternateUrls = enabledSeoLocales.value.map((enabledLocale) => ({ locale: enabledLocale, url: path }));
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
                failLabel: localize('Recommended title length is 30-60 characters', 'الطول الموصى به للعنوان هو 30-60 حرفا'),
            },
            {
                ok: description.length >= 70 && description.length <= 160,
                label: localize('Description length looks good', 'طول الوصف مناسب'),
                failLabel: localize('Recommended description length is 70-160 characters', 'الطول الموصى به للوصف هو 70-160 حرفا'),
            },
            {
                ok: Boolean((form.seo.defaults.og_image || props.settings.logo_url || '').trim()),
                label: localize('Open Graph image is set', 'صورة Open Graph مضبوطة'),
                failLabel: localize('Set an Open Graph image for sharing previews', 'حدد صورة Open Graph لمعاينات المشاركة'),
            },
            {
                ok: !((form.seo.defaults.og_image || props.settings.logo_url || '').trim()) || (ogImageAlt.length >= 8 && ogImageAlt.length <= 125),
                label: localize('Open Graph image alt text looks good', 'النص البديل لصورة Open Graph مناسب'),
                failLabel: localize('Add descriptive Open Graph image alt text between 8 and 125 characters', 'أضف نصًا بديلاً وصفيًا لصورة Open Graph بين 8 و125 حرفًا'),
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
                failLabel: localize('Try to include the focus keyword in the slug when possible', 'حاول تضمين الكلمة المفتاحية في الرابط المختصر متى كان ذلك مناسبا'),
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
            ogImage: String(form.seo.defaults.og_image || props.settings.logo_url || '').trim(),
            ogImageAlt,
            twitterCardType: 'summary_large_image',
            alternates: alternateUrls,
            slug: normalizedSlug,
            score: checks.filter((check) => check.ok).length,
            checks,
        };
    });
}

const seoPreviewCardsData = computed<PreviewCard[]>(() => buildPreviewCards(selectedSeoLocale.value));
const activePreviewCard = computed(() => seoPreviewCardsData.value.find((preview) => preview.key === activePageTab.value) ?? seoPreviewCardsData.value[0]);
const seoBlockingPages = computed(() => seoPreviewCardsData.value.filter((preview) => preview.score === 0));

const seoReadinessByLocale = computed(() => {
    return enabledSeoLocales.value.map((localeKey) => {
        const cards = buildPreviewCards(localeKey);
        const totalChecks = cards.reduce((sum, preview) => sum + preview.checks.length, 0);
        const passedChecks = cards.reduce((sum, preview) => sum + preview.score, 0);
        const percentage = totalChecks > 0 ? Math.round((passedChecks / totalChecks) * 100) : 0;

        return {
            locale: localeKey,
            label: localeLabel(localeKey),
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
    const current = seoReadinessByLocale.value.find((item) => item.locale === selectedSeoLocale.value);
    const percentage = current?.percentage ?? 0;

    if (percentage >= 85) {
        return {
            label: localize('Good', 'جيد'),
            description: localize('Most SEO signals are in good shape for the selected language.', 'معظم إشارات SEO في وضع جيد للغة المحددة.'),
            className: 'bg-emerald-100 text-emerald-700',
        };
    }

    if (percentage >= 50) {
        return {
            label: localize('Needs Work', 'يحتاج تحسين'),
            description: localize('Some pages still need SEO cleanup for the selected language.', 'بعض الصفحات ما زالت تحتاج تحسين SEO للغة المحددة.'),
            className: 'bg-amber-100 text-amber-700',
        };
    }

    return {
        label: localize('Critical', 'حرج'),
        description: localize('SEO coverage is weak for the selected language and should be fixed before publishing changes.', 'تغطية SEO ضعيفة للغة المحددة ويجب إصلاحها قبل اعتماد التغييرات.'),
        className: 'bg-red-100 text-red-700',
    };
});

const seoSaveBlockedMessage = ref('');
const pageSettingHint = computed(() => {
    if (activePageTab.value === 'car') {
        return localize(
            'Use :car, :make, :model, :year, :model_year, :transmission, :seats, and :fuel_type. Missing car data falls back to generic labels.',
            'استخدم :car و :make و :model و :year و :model_year و :transmission و :seats و :fuel_type. وإذا كانت البيانات ناقصة سيتم استخدام قيم افتراضية عامة.',
        );
    }

    if (activePageTab.value === 'booking_checkout' || activePageTab.value === 'booking_confirmation') {
        return localize('Use :reservation in the title or description to inject the reservation number.', 'استخدم :reservation داخل العنوان أو الوصف لإدراج رقم الحجز.');
    }

    return localize('Configure this page independently from the rest of the site.', 'اضبط هذه الصفحة بشكل مستقل عن بقية الموقع.');
});

function exportSeoReport() {
    const lines = [
        `Tenant: ${props.tenant.name}`,
        `Slug: ${props.tenant.slug}`,
        `Selected SEO Locale: ${selectedSeoLocale.value}`,
        `Overall Status: ${seoHealthStatus.value.label}`,
        `Enabled Locales: ${enabledSeoLocales.value.join(', ')}`,
        '',
        'Locale Readiness:',
        ...seoReadinessByLocale.value.map((item) => `- ${item.locale.toUpperCase()} (${item.label}): ${item.percentage}% (${item.passedChecks}/${item.totalChecks})`),
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
            `OG Image Alt: ${preview.ogImageAlt || 'N/A'}`,
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
    link.download = `${props.tenant.slug || 'tenant'}-seo-report-${selectedSeoLocale.value}.txt`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
}

function submit() {
    if (seoBlockingPages.value.length > 0) {
        const labels = seoBlockingPages.value.map((pageItem) => pageItem.label).join(', ');
        seoSaveBlockedMessage.value = localize(
            `SEO save blocked for ${selectedSeoLocaleLabel.value}. Fix these pages first: ${labels}.`,
            `تم منع الحفظ للغة ${selectedSeoLocaleLabel.value}. أصلح هذه الصفحات أولا: ${labels}.`,
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
                        {{ localize('Manage SEO per language, then review readiness scores for each locale.', 'أدر SEO لكل لغة، ثم راجع درجة الجاهزية لكل لغة بشكل مستقل.') }}
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
                        {{ form.processing ? localize('Saving...', 'جار الحفظ...') : localize('Save SEO Changes', 'حفظ تغييرات SEO') }}
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

                        <div class="space-y-4">
                            <div class="space-y-2">
                                <Label for="seo_locale">{{ localize('SEO Language', 'لغة SEO') }}</Label>
                                <select id="seo_locale" v-model="selectedSeoLocale" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                    <option v-for="localeKey in enabledSeoLocales" :key="localeKey" :value="localeKey">
                                        {{ localeLabel(localeKey) }} ({{ localeKey.toUpperCase() }})
                                    </option>
                                </select>
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
                        </div>
                    </aside>

                    <div class="space-y-6">
                        <section class="rounded-lg border bg-muted/20 p-4">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
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

                        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
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
                            <SeoGeneralSettings
                                :form="form"
                                :errors="form.errors"
                                :selected-locale="selectedSeoLocale"
                                :selected-locale-label="selectedSeoLocaleLabel"
                                :seo-og-image-files="seoOgImageFiles"
                            />
                        </div>

                        <div v-if="activeTab === 'pages'" class="space-y-4">
                            <section class="rounded-lg border p-5 space-y-4">
                                <div>
                                    <h2 class="text-lg font-semibold">{{ localize('Page SEO Fields', 'حقول SEO للصفحات') }}</h2>
                                    <p class="text-sm text-muted-foreground">
                                        {{ localize('Pick a page, then manage title, description, canonical, robots, and focus keyword for the selected language.', 'اختر صفحة ثم اضبط العنوان والوصف وcanonical وrobots والكلمة المفتاحية للغة المحددة.') }}
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
                                            <div class="flex flex-wrap items-center justify-between gap-3">
                                                <div>
                                                    <h3 class="font-semibold">{{ seoPageLabel(activePageTab) }}</h3>
                                                    <p class="mt-1 text-sm text-muted-foreground">{{ pageSettingHint }}</p>
                                                </div>
                                                <div class="rounded-md border bg-muted/30 px-3 py-2 text-sm">
                                                    <span class="font-medium">{{ localize('Editing language:', 'لغة التحرير:') }}</span>
                                                    {{ selectedSeoLocaleLabel }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div class="space-y-2 md:col-span-2">
                                                <Label>{{ localize('Title', 'العنوان') }}</Label>
                                                <Input
                                                    v-model="activePageSettings.title[selectedSeoLocale]"
                                                    :dir="selectedSeoLocale === 'ar' ? 'rtl' : 'ltr'"
                                                    :placeholder="activeTitlePlaceholder"
                                                />
                                                <p v-if="form.errors[`seo.pages.${activePageTab}.title.${selectedSeoLocale}`]" class="text-sm text-red-600">
                                                    {{ form.errors[`seo.pages.${activePageTab}.title.${selectedSeoLocale}`] }}
                                                </p>
                                            </div>

                                            <div class="space-y-2 md:col-span-2">
                                                <Label>{{ localize('Focus Keyword', 'الكلمة المفتاحية') }}</Label>
                                                <Input
                                                    v-model="activePageSettings.focus_keyword[selectedSeoLocale]"
                                                    :dir="selectedSeoLocale === 'ar' ? 'rtl' : 'ltr'"
                                                    :placeholder="activeFocusKeywordPlaceholder"
                                                />
                                                <p v-if="form.errors[`seo.pages.${activePageTab}.focus_keyword.${selectedSeoLocale}`]" class="text-sm text-red-600">
                                                    {{ form.errors[`seo.pages.${activePageTab}.focus_keyword.${selectedSeoLocale}`] }}
                                                </p>
                                            </div>

                                            <div class="space-y-2 md:col-span-2">
                                                <Label>{{ localize('Description', 'الوصف') }}</Label>
                                                <textarea
                                                    v-model="activePageSettings.description[selectedSeoLocale]"
                                                    rows="4"
                                                    :dir="selectedSeoLocale === 'ar' ? 'rtl' : 'ltr'"
                                                    :placeholder="activeDescriptionPlaceholder"
                                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                                />
                                                <p v-if="form.errors[`seo.pages.${activePageTab}.description.${selectedSeoLocale}`]" class="text-sm text-red-600">
                                                    {{ form.errors[`seo.pages.${activePageTab}.description.${selectedSeoLocale}`] }}
                                                </p>
                                            </div>

                                            <div class="space-y-2 md:col-span-2">
                                                <Label>{{ localize('Canonical URL', 'رابط Canonical') }}</Label>
                                                <Input v-model="activePageSettings.canonical_url" :placeholder="activeCanonicalPlaceholder" />
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
                                :selected-locale="selectedSeoLocale"
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
