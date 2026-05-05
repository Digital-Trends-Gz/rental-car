<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type LocalizedText = { en: string | null; ar: string | null };
type SeoPageKey = 'home' | 'fleet' | 'about' | 'contact' | 'car' | 'booking_checkout' | 'booking_confirmation';

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
                home: { title: LocalizedText; description: LocalizedText; canonical_url: string | null };
                fleet: { title: LocalizedText; description: LocalizedText; canonical_url: string | null };
                about: { title: LocalizedText; description: LocalizedText; canonical_url: string | null };
                contact: { title: LocalizedText; description: LocalizedText; canonical_url: string | null };
                car: { title: LocalizedText; description: LocalizedText; canonical_url: string | null };
                booking_checkout: { title: LocalizedText; description: LocalizedText; canonical_url: string | null };
                booking_confirmation: { title: LocalizedText; description: LocalizedText; canonical_url: string | null };
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
    if (!value) {
        return '';
    }

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
};

const seoPageDefaultDescription = (pageKey: SeoPageKey): string => {
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
        const robots = pageKey === 'booking_checkout' || pageKey === 'booking_confirmation'
            ? 'noindex,nofollow'
            : (form.seo.defaults.robots || 'index,follow');
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
const seoCopyMessage = ref('');

function copySeoMetaSummary(preview: {
    label: string;
    title: string;
    description: string;
    path: string;
    robots: string;
    ogImage: string;
    twitterCardType: string;
    slug: string;
}) {
    const summary = [
        `${preview.label}`,
        `Title: ${preview.title}`,
        `Description: ${preview.description}`,
        `Canonical: ${preview.path}`,
        `Slug: ${preview.slug}`,
        `Robots: ${preview.robots}`,
        `OG Image: ${preview.ogImage || 'N/A'}`,
        `Twitter Card: ${preview.twitterCardType}`,
    ].join('\n');

    if (typeof navigator === 'undefined' || !navigator.clipboard) {
        seoCopyMessage.value = localize('Clipboard is not available in this browser.', 'الحافظة غير متاحة في هذا المتصفح.');
        return;
    }

    navigator.clipboard.writeText(summary)
        .then(() => {
            seoCopyMessage.value = localize(`Copied SEO summary for ${preview.label}.`, `تم نسخ ملخص SEO لصفحة ${preview.label}.`);
        })
        .catch(() => {
            seoCopyMessage.value = localize('Could not copy SEO summary.', 'تعذر نسخ ملخص SEO.');
        });
}

function renderedMetaTags(preview: {
    title: string;
    description: string;
    path: string;
    robots: string;
    ogImage: string;
    twitterCardType: string;
    alternates: Array<{ locale: string; url: string }>;
}) {
    return [
        `<title>${preview.title}</title>`,
        `<meta name="description" content="${preview.description}">`,
        `<meta name="robots" content="${preview.robots}">`,
        `<link rel="canonical" href="${preview.path}">`,
        ...preview.alternates.map((alternate) => `<link rel="alternate" hreflang="${alternate.locale}" href="${alternate.url}">`),
        `<meta property="og:title" content="${preview.title}">`,
        `<meta property="og:description" content="${preview.description}">`,
        `<meta property="og:url" content="${preview.path}">`,
        `<meta property="og:image" content="${preview.ogImage || ''}">`,
        `<meta name="twitter:card" content="${preview.twitterCardType}">`,
        `<meta name="twitter:title" content="${preview.title}">`,
        `<meta name="twitter:description" content="${preview.description}">`,
        `<meta name="twitter:image" content="${preview.ogImage || ''}">`,
    ];
}

function copyRenderedMetaTags(preview: {
    label: string;
    title: string;
    description: string;
    path: string;
    robots: string;
    ogImage: string;
    twitterCardType: string;
    alternates: Array<{ locale: string; url: string }>;
}) {
    const output = renderedMetaTags(preview).join('\n');

    if (typeof navigator === 'undefined' || !navigator.clipboard) {
        seoCopyMessage.value = localize('Clipboard is not available in this browser.', 'الحافظة غير متاحة في هذا المتصفح.');
        return;
    }

    navigator.clipboard.writeText(output)
        .then(() => {
            seoCopyMessage.value = localize(`Copied rendered meta tags for ${preview.label}.`, `تم نسخ وسوم الميتا الفعلية لصفحة ${preview.label}.`);
        })
        .catch(() => {
            seoCopyMessage.value = localize('Could not copy rendered meta tags.', 'تعذر نسخ وسوم الميتا الفعلية.');
        });
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
        seoCopyMessage.value = localize('SEO report export is not available in this environment.', 'تصدير تقرير SEO غير متاح في هذه البيئة.');
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
    seoCopyMessage.value = localize('SEO report exported successfully.', 'تم تصدير تقرير SEO بنجاح.');
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
</script>

<template>
    <Head :title="localize('SEO Settings', 'إعدادات SEO')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('SEO Settings', 'إعدادات SEO') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Manage page titles, descriptions, canonical URLs, and social previews in one dedicated screen.', 'أدر عناوين الصفحات والأوصاف وروابط canonical ومعاينات المشاركة من شاشة مستقلة واحدة.') }}
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

                <div v-if="seoCopyMessage" class="rounded-md border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800">
                    {{ seoCopyMessage }}
                </div>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('SEO Defaults', 'إعدادات SEO الافتراضية') }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ localize('Default values used across public pages when page-specific SEO is empty.', 'القيم الافتراضية المستخدمة عبر الصفحات العامة عندما تكون حقول SEO الخاصة بالصفحة فارغة.') }}
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="seo_title_suffix_en">{{ localize('Title Suffix (EN)', 'لاحقة العنوان (EN)') }}</Label>
                            <Input id="seo_title_suffix_en" v-model="form.seo.defaults.title_suffix.en" />
                            <p v-if="form.errors['seo.defaults.title_suffix.en']" class="text-sm text-red-600">{{ form.errors['seo.defaults.title_suffix.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="seo_title_suffix_ar">{{ localize('Title Suffix (AR)', 'لاحقة العنوان (AR)') }}</Label>
                            <Input id="seo_title_suffix_ar" v-model="form.seo.defaults.title_suffix.ar" dir="rtl" />
                            <p v-if="form.errors['seo.defaults.title_suffix.ar']" class="text-sm text-red-600">{{ form.errors['seo.defaults.title_suffix.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="seo_default_description_en">{{ localize('Default Description (EN)', 'الوصف الافتراضي (EN)') }}</Label>
                            <textarea id="seo_default_description_en" v-model="form.seo.defaults.default_description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['seo.defaults.default_description.en']" class="text-sm text-red-600">{{ form.errors['seo.defaults.default_description.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="seo_default_description_ar">{{ localize('Default Description (AR)', 'الوصف الافتراضي (AR)') }}</Label>
                            <textarea id="seo_default_description_ar" v-model="form.seo.defaults.default_description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['seo.defaults.default_description.ar']" class="text-sm text-red-600">{{ form.errors['seo.defaults.default_description.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="seo_og_image">{{ localize('Open Graph Image URL', 'رابط صورة Open Graph') }}</Label>
                            <Input id="seo_og_image" v-model="form.seo.defaults.og_image" placeholder="https://example.com/og-image.jpg" />
                            <p v-if="form.errors['seo.defaults.og_image']" class="text-sm text-red-600">{{ form.errors['seo.defaults.og_image'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="seo_robots">{{ localize('Robots', 'تعليمات Robots') }}</Label>
                            <Input id="seo_robots" v-model="form.seo.defaults.robots" placeholder="index,follow" />
                            <p v-if="form.errors['seo.defaults.robots']" class="text-sm text-red-600">{{ form.errors['seo.defaults.robots'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">{{ localize('Search Preview', 'معاينة نتائج البحث') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ localize('Google-style preview for each SEO page.', 'معاينة تشبه نتائج Google لكل صفحة SEO.') }}</p>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div v-for="preview in seoPreviewCardsData" :key="preview.key" class="rounded-lg border bg-background p-4">
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-medium">{{ preview.label }}</div>
                                    <div class="text-xs text-muted-foreground">{{ preview.robots }}</div>
                                </div>
                                <Button type="button" size="sm" variant="outline" @click="copySeoMetaSummary(preview)">
                                    {{ localize('Copy', 'نسخ') }}
                                </Button>
                            </div>
                            <div class="space-y-1">
                                <div class="truncate text-sm text-emerald-700">{{ preview.path }}</div>
                                <div class="text-lg font-medium text-blue-700">{{ preview.title }}</div>
                                <div class="text-sm text-slate-600">{{ preview.description }}</div>
                            </div>
                            <div class="mt-3 space-y-1 border-t pt-3 text-xs">
                                <div><span class="font-medium">Slug:</span> {{ preview.slug || '/' }}</div>
                                <div><span class="font-medium">hreflang:</span> {{ preview.alternates.map((alternate) => alternate.locale).join(', ') }}</div>
                                <div class="pt-1">
                                    <div v-for="(check, index) in preview.checks" :key="`${preview.key}-check-${index}`" class="flex items-start gap-2 py-0.5">
                                        <span class="mt-0.5 h-2.5 w-2.5 rounded-full" :class="check.ok ? 'bg-emerald-500' : 'bg-amber-500'" />
                                        <span :class="check.ok ? 'text-emerald-700' : 'text-amber-700'">
                                            {{ check.ok ? check.label : check.failLabel }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('Open Graph Preview', 'معاينة Open Graph') }}</h2>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div v-for="preview in seoPreviewCardsData" :key="`${preview.key}-og`" class="overflow-hidden rounded-xl border bg-background">
                            <div class="aspect-[1.91/1] bg-slate-100">
                                <img v-if="preview.ogImage" :src="preview.ogImage" :alt="preview.label" class="h-full w-full object-contain" />
                            </div>
                            <div class="space-y-1 p-4">
                                <div class="truncate text-xs uppercase tracking-wide text-muted-foreground">{{ preview.path }}</div>
                                <div class="font-semibold">{{ preview.title }}</div>
                                <div class="text-sm text-slate-600">{{ preview.description }}</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('Twitter / X Card Preview', 'معاينة بطاقة Twitter / X') }}</h2>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div v-for="preview in seoPreviewCardsData" :key="`${preview.key}-twitter`" class="overflow-hidden rounded-2xl border bg-background shadow-sm">
                            <div class="aspect-[2/1] bg-slate-100">
                                <img v-if="preview.ogImage" :src="preview.ogImage" :alt="preview.label" class="h-full w-full object-contain" />
                            </div>
                            <div class="space-y-1 p-4">
                                <div class="text-xs text-muted-foreground">{{ preview.path }}</div>
                                <div class="font-semibold">{{ preview.title }}</div>
                                <div class="text-sm text-slate-600">{{ preview.description }}</div>
                                <div class="pt-1 text-xs text-muted-foreground">{{ preview.twitterCardType }}</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('Rendered Meta Tags', 'وسوم الميتا الفعلية') }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ localize('These are the actual tags that social platforms and search engines will read from each page.', 'هذه هي الوسوم الفعلية التي ستقرأها منصات المشاركة ومحركات البحث من كل صفحة.') }}
                        </p>
                    </div>

                    <div class="grid gap-4">
                        <div v-for="preview in seoPreviewCardsData" :key="`${preview.key}-meta`" class="rounded-lg border bg-background p-4 space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold">{{ preview.label }}</h3>
                                    <p class="text-xs text-muted-foreground">{{ preview.path }}</p>
                                </div>
                                <Button type="button" size="sm" variant="outline" @click="copyRenderedMetaTags(preview)">
                                    {{ localize('Copy Meta Tags', 'نسخ وسوم الميتا') }}
                                </Button>
                            </div>

                            <div class="overflow-x-auto rounded-md bg-slate-950 p-3 text-xs text-slate-100">
                                <pre class="whitespace-pre-wrap break-words font-mono">{{ renderedMetaTags(preview).join('\n') }}</pre>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('Page SEO Fields', 'حقول SEO للصفحات') }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ localize('Manage each page separately. Use :car and :reservation placeholders where relevant.', 'أدر كل صفحة بشكل مستقل. استخدم المتغيرين :car و :reservation عند الحاجة.') }}
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('Home Page SEO', 'SEO الصفحة الرئيسية') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_home_title_en">{{ localize('Title (EN)', 'العنوان (EN)') }}</Label>
                                <Input id="seo_home_title_en" v-model="form.seo.pages.home.title.en" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_home_title_ar">{{ localize('Title (AR)', 'العنوان (AR)') }}</Label>
                                <Input id="seo_home_title_ar" v-model="form.seo.pages.home.title.ar" dir="rtl" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_home_description_en">{{ localize('Description (EN)', 'الوصف (EN)') }}</Label>
                                <textarea id="seo_home_description_en" v-model="form.seo.pages.home.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_home_description_ar">{{ localize('Description (AR)', 'الوصف (AR)') }}</Label>
                                <textarea id="seo_home_description_ar" v-model="form.seo.pages.home.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_home_canonical">{{ localize('Canonical URL', 'رابط Canonical') }}</Label>
                                <Input id="seo_home_canonical" v-model="form.seo.pages.home.canonical_url" />
                            </div>
                        </div>

                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('Fleet Page SEO', 'SEO صفحة الأسطول') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_fleet_title_en">{{ localize('Title (EN)', 'العنوان (EN)') }}</Label>
                                <Input id="seo_fleet_title_en" v-model="form.seo.pages.fleet.title.en" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_fleet_title_ar">{{ localize('Title (AR)', 'العنوان (AR)') }}</Label>
                                <Input id="seo_fleet_title_ar" v-model="form.seo.pages.fleet.title.ar" dir="rtl" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_fleet_description_en">{{ localize('Description (EN)', 'الوصف (EN)') }}</Label>
                                <textarea id="seo_fleet_description_en" v-model="form.seo.pages.fleet.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_fleet_description_ar">{{ localize('Description (AR)', 'الوصف (AR)') }}</Label>
                                <textarea id="seo_fleet_description_ar" v-model="form.seo.pages.fleet.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_fleet_canonical">{{ localize('Canonical URL', 'رابط Canonical') }}</Label>
                                <Input id="seo_fleet_canonical" v-model="form.seo.pages.fleet.canonical_url" />
                            </div>
                        </div>

                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('About Page SEO', 'SEO صفحة من نحن') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_about_title_en">{{ localize('Title (EN)', 'العنوان (EN)') }}</Label>
                                <Input id="seo_about_title_en" v-model="form.seo.pages.about.title.en" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_about_title_ar">{{ localize('Title (AR)', 'العنوان (AR)') }}</Label>
                                <Input id="seo_about_title_ar" v-model="form.seo.pages.about.title.ar" dir="rtl" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_about_description_en">{{ localize('Description (EN)', 'الوصف (EN)') }}</Label>
                                <textarea id="seo_about_description_en" v-model="form.seo.pages.about.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_about_description_ar">{{ localize('Description (AR)', 'الوصف (AR)') }}</Label>
                                <textarea id="seo_about_description_ar" v-model="form.seo.pages.about.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_about_canonical">{{ localize('Canonical URL', 'رابط Canonical') }}</Label>
                                <Input id="seo_about_canonical" v-model="form.seo.pages.about.canonical_url" />
                            </div>
                        </div>

                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('Contact Page SEO', 'SEO صفحة اتصل بنا') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_contact_title_en">{{ localize('Title (EN)', 'العنوان (EN)') }}</Label>
                                <Input id="seo_contact_title_en" v-model="form.seo.pages.contact.title.en" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_contact_title_ar">{{ localize('Title (AR)', 'العنوان (AR)') }}</Label>
                                <Input id="seo_contact_title_ar" v-model="form.seo.pages.contact.title.ar" dir="rtl" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_contact_description_en">{{ localize('Description (EN)', 'الوصف (EN)') }}</Label>
                                <textarea id="seo_contact_description_en" v-model="form.seo.pages.contact.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_contact_description_ar">{{ localize('Description (AR)', 'الوصف (AR)') }}</Label>
                                <textarea id="seo_contact_description_ar" v-model="form.seo.pages.contact.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_contact_canonical">{{ localize('Canonical URL', 'رابط Canonical') }}</Label>
                                <Input id="seo_contact_canonical" v-model="form.seo.pages.contact.canonical_url" />
                            </div>
                        </div>

                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('Car Details Page SEO', 'SEO صفحة السيارة') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_car_title_en">{{ localize('Title (EN)', 'العنوان (EN)') }}</Label>
                                <Input id="seo_car_title_en" v-model="form.seo.pages.car.title.en" placeholder="Use :car as placeholder" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_car_title_ar">{{ localize('Title (AR)', 'العنوان (AR)') }}</Label>
                                <Input id="seo_car_title_ar" v-model="form.seo.pages.car.title.ar" dir="rtl" placeholder="استخدم :car كمتغير" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_car_description_en">{{ localize('Description (EN)', 'الوصف (EN)') }}</Label>
                                <textarea id="seo_car_description_en" v-model="form.seo.pages.car.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_car_description_ar">{{ localize('Description (AR)', 'الوصف (AR)') }}</Label>
                                <textarea id="seo_car_description_ar" v-model="form.seo.pages.car.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_car_canonical">{{ localize('Canonical URL', 'رابط Canonical') }}</Label>
                                <Input id="seo_car_canonical" v-model="form.seo.pages.car.canonical_url" />
                            </div>
                        </div>

                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('Booking Checkout SEO', 'SEO صفحة إتمام الحجز') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_checkout_title_en">{{ localize('Title (EN)', 'العنوان (EN)') }}</Label>
                                <Input id="seo_checkout_title_en" v-model="form.seo.pages.booking_checkout.title.en" placeholder="Use :reservation as placeholder" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_checkout_title_ar">{{ localize('Title (AR)', 'العنوان (AR)') }}</Label>
                                <Input id="seo_checkout_title_ar" v-model="form.seo.pages.booking_checkout.title.ar" dir="rtl" placeholder="استخدم :reservation كمتغير" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_checkout_description_en">{{ localize('Description (EN)', 'الوصف (EN)') }}</Label>
                                <textarea id="seo_checkout_description_en" v-model="form.seo.pages.booking_checkout.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_checkout_description_ar">{{ localize('Description (AR)', 'الوصف (AR)') }}</Label>
                                <textarea id="seo_checkout_description_ar" v-model="form.seo.pages.booking_checkout.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_checkout_canonical">{{ localize('Canonical URL', 'رابط Canonical') }}</Label>
                                <Input id="seo_checkout_canonical" v-model="form.seo.pages.booking_checkout.canonical_url" />
                            </div>
                        </div>

                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('Booking Confirmation SEO', 'SEO صفحة تأكيد الحجز') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_confirmation_title_en">{{ localize('Title (EN)', 'العنوان (EN)') }}</Label>
                                <Input id="seo_confirmation_title_en" v-model="form.seo.pages.booking_confirmation.title.en" placeholder="Use :reservation as placeholder" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_confirmation_title_ar">{{ localize('Title (AR)', 'العنوان (AR)') }}</Label>
                                <Input id="seo_confirmation_title_ar" v-model="form.seo.pages.booking_confirmation.title.ar" dir="rtl" placeholder="استخدم :reservation كمتغير" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_confirmation_description_en">{{ localize('Description (EN)', 'الوصف (EN)') }}</Label>
                                <textarea id="seo_confirmation_description_en" v-model="form.seo.pages.booking_confirmation.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_confirmation_description_ar">{{ localize('Description (AR)', 'الوصف (AR)') }}</Label>
                                <textarea id="seo_confirmation_description_ar" v-model="form.seo.pages.booking_confirmation.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_confirmation_canonical">{{ localize('Canonical URL', 'رابط Canonical') }}</Label>
                                <Input id="seo_confirmation_canonical" v-model="form.seo.pages.booking_confirmation.canonical_url" />
                            </div>
                        </div>
                    </div>
                </section>
            </form>
        </main>
    </AdminLayout>
</template>
