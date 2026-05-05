<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useTrans } from '@/composables/useTrans';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type LocalizedText = { en: string | null; ar: string | null };

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
        };
    };
    actions: {
        website: string;
        seo_audit: string;
    };
}>();

type SeoPageKey = 'home' | 'fleet' | 'about' | 'contact' | 'car' | 'booking_checkout' | 'booking_confirmation';

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);
const feedbackMessage = ref('');

const previewName = computed(() => props.settings.site_name || props.tenant.name);
const enabledSeoLocales = computed(() => {
    const locales = Array.isArray(props.settings.enabled_locales) ? props.settings.enabled_locales : ['en', 'ar'];

    return locales
        .map((value) => String(value).trim())
        .filter((value, index, array) => value !== '' && array.indexOf(value) === index);
});

const previewBaseUrl = computed(() => {
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

const pagePath = (pageKey: SeoPageKey): string => {
    if (pageKey === 'home') return '/';
    if (pageKey === 'car') return '/fleet/sample-car';
    if (pageKey === 'booking_checkout') return '/booking/sample-reservation/checkout';
    if (pageKey === 'booking_confirmation') return '/booking/sample-reservation';

    return `/${pageKey}`;
};

const fallbackTitle = (pageKey: SeoPageKey): string => {
    const suffix = localizedSeoText(props.settings.seo.defaults.title_suffix) || previewName.value;

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

const fallbackDescription = (pageKey: SeoPageKey): string => {
    const shared = localizedSeoText(props.settings.seo.defaults.default_description);

    if (shared) {
        return shared;
    }

    const descriptions: Record<SeoPageKey, string> = {
        home: localize(`Discover ${previewName.value} and reserve your next rental car online.`, `اكتشف ${previewName.value} واحجز سيارتك القادمة عبر الإنترنت.`),
        fleet: localize(`Browse available rental vehicles from ${previewName.value}.`, `استعرض السيارات المتاحة من ${previewName.value}.`),
        about: localize(`Learn more about ${previewName.value} and its car rental services.`, `تعرف أكثر على ${previewName.value} وخدمات تأجير السيارات.`),
        contact: localize(`Get in touch with ${previewName.value} for bookings and support.`, `تواصل مع ${previewName.value} للحجوزات والدعم.`),
        car: localize(`View rental car details and pricing from ${previewName.value}.`, `اطلع على تفاصيل السيارة وسعر الإيجار لدى ${previewName.value}.`),
        booking_checkout: localize(`Choose your payment provider and complete your booking securely with ${previewName.value}.`, `اختر مزود الدفع وأكمل الحجز بأمان مع ${previewName.value}.`),
        booking_confirmation: localize(`Review your confirmed booking and reservation details from ${previewName.value}.`, `راجع تفاصيل الحجز المؤكد ومعلومات الحجز لدى ${previewName.value}.`),
    };

    return descriptions[pageKey];
};

const auditPages = computed(() => {
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
        const title = localizedSeoText(props.settings.seo.pages[pageKey].title) || fallbackTitle(pageKey);
        const description = localizedSeoText(props.settings.seo.pages[pageKey].description) || fallbackDescription(pageKey);
        const canonical = props.settings.seo.pages[pageKey].canonical_url || `${previewBaseUrl.value}${pagePath(pageKey)}`;
        const pageRobots = (props.settings.seo.pages[pageKey].robots || '').trim();
        const robots = pageRobots || (
            pageKey === 'booking_checkout' || pageKey === 'booking_confirmation'
                ? 'noindex,nofollow'
                : (props.settings.seo.defaults.robots || 'index,follow')
        );
        const alternates = enabledSeoLocales.value.map((localeKey) => ({
            locale: localeKey,
            url: canonical,
        }));
        alternates.push({ locale: 'x-default', url: canonical });
        const pathname = (() => {
            try {
                return new URL(canonical, previewBaseUrl.value).pathname;
            } catch {
                return pagePath(pageKey);
            }
        })();
        const slug = pathname.replace(/\/+/g, '/').replace(/^\/|\/$/g, '');
        const isPublicPage = pageKey !== 'booking_checkout' && pageKey !== 'booking_confirmation';
        const publicNoindexWarning = isPublicPage && /noindex/i.test(robots);
        const checks = [
            {
                ok: title.length >= 30 && title.length <= 60,
                pass: localize('Title length looks good', 'طول العنوان مناسب'),
                fail: localize('Recommended title length is 30-60 characters', 'الطول الموصى به للعنوان هو 30-60 حرفًا'),
            },
            {
                ok: description.length >= 70 && description.length <= 160,
                pass: localize('Description length looks good', 'طول الوصف مناسب'),
                fail: localize('Recommended description length is 70-160 characters', 'الطول الموصى به للوصف هو 70-160 حرفًا'),
            },
            {
                ok: Boolean((props.settings.seo.defaults.og_image || props.settings.logo_url || '').trim()),
                pass: localize('Open Graph image is set', 'صورة Open Graph مضبوطة'),
                fail: localize('Set an Open Graph image for sharing previews', 'حدد صورة Open Graph لمعاينات المشاركة'),
            },
            {
                ok: canonical === '' || /^https?:\/\/\S+$/i.test(canonical),
                pass: localize('Canonical URL is valid', 'رابط Canonical صحيح'),
                fail: localize('Canonical URL must start with http:// or https://', 'رابط Canonical يجب أن يبدأ بـ http:// أو https://'),
            },
            {
                ok: slug !== '' && /^[a-z0-9/_-]+$/i.test(slug) && !/\s/.test(slug),
                pass: localize('Slug format looks clean', 'تنسيق الرابط المختصر سليم'),
                fail: localize('Slug should use clean URL segments without spaces', 'يجب أن يستخدم الرابط المختصر مقاطع نظيفة بدون مسافات'),
            },
            {
                ok: alternates.length === enabledSeoLocales.value.length + 1,
                pass: localize('hreflang alternates are available', 'روابط hreflang متوفرة'),
                fail: localize('hreflang alternates are incomplete', 'روابط hreflang غير مكتملة'),
            },
            {
                ok: !publicNoindexWarning,
                pass: localize('Indexable public page is not blocked by robots', 'الصفحة العامة قابلة للأرشفة وغير محجوبة'),
                fail: localize('Public page is set to noindex and may disappear from search', 'الصفحة العامة مضبوطة على noindex وقد تختفي من نتائج البحث'),
            },
        ];

        return {
            key: pageKey,
            label: localize(englishLabels[pageKey], arabicLabels[pageKey]),
            title,
            description,
            canonical,
            slug,
            robots,
            alternates,
            ogImage: (props.settings.seo.defaults.og_image || props.settings.logo_url || '').trim(),
            isPublicPage,
            publicNoindexWarning,
            recommended: {
                title: '30-60 chars',
                description: '70-160 chars',
                robots: isPublicPage ? 'index,follow' : 'noindex,nofollow',
                slug: localize('Lowercase, hyphenated, no spaces', 'أحرف صغيرة وشرطات وبدون مسافات'),
                hreflang: `${enabledSeoLocales.value.join(', ')}, x-default`,
            },
            score: checks.filter((check) => check.ok).length,
            checks,
        };
    });
});

const overallStatus = computed(() => {
    const totalChecks = auditPages.value.reduce((sum, page) => sum + page.checks.length, 0);
    const passedChecks = auditPages.value.reduce((sum, page) => sum + page.score, 0);
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
        description: localize('SEO coverage is weak and needs attention.', 'تغطية SEO ضعيفة وتحتاج معالجة.'),
        className: 'bg-red-100 text-red-700',
    };
});

function exportSeoReport(format: 'txt' | 'json' | 'csv') {
    const payload = {
        tenant: props.tenant,
        overall_status: overallStatus.value.label,
        enabled_locales: enabledSeoLocales.value,
        pages: auditPages.value.map((page) => ({
            key: page.key,
            label: page.label,
            title: page.title,
            description: page.description,
            canonical: page.canonical,
            slug: page.slug,
            robots: page.robots,
            og_image: page.ogImage || null,
            alternates: page.alternates,
            score: page.score,
            total_checks: page.checks.length,
            checks: page.checks.map((check) => ({
                status: check.ok ? 'pass' : 'warn',
                message: check.ok ? check.pass : check.fail,
            })),
        })),
    };

    const content = format === 'json'
        ? JSON.stringify(payload, null, 2)
        : format === 'csv'
            ? [
                ['page_key', 'page_label', 'title', 'description', 'canonical', 'slug', 'robots', 'og_image', 'alternates', 'score', 'total_checks', 'warnings'].join(','),
                ...payload.pages.map((page) => ([
                    page.key,
                    `"${String(page.label).replace(/"/g, '""')}"`,
                    `"${String(page.title).replace(/"/g, '""')}"`,
                    `"${String(page.description).replace(/"/g, '""')}"`,
                    `"${String(page.canonical).replace(/"/g, '""')}"`,
                    `"${String(page.slug).replace(/"/g, '""')}"`,
                    `"${String(page.robots).replace(/"/g, '""')}"`,
                    `"${String(page.og_image || '').replace(/"/g, '""')}"`,
                    `"${page.alternates.map((alternate) => `${alternate.locale}=${alternate.url}`).join(' | ').replace(/"/g, '""')}"`,
                    page.score,
                    page.total_checks,
                    `"${page.checks.filter((check) => check.status === 'warn').map((check) => check.message).join(' | ').replace(/"/g, '""')}"`,
                ]).join(',')),
            ].join('\n')
            : [
            `Tenant: ${payload.tenant.name}`,
            `Slug: ${payload.tenant.slug}`,
            `Overall Status: ${payload.overall_status}`,
            `Enabled Locales: ${payload.enabled_locales.join(', ')}`,
            '',
            ...payload.pages.flatMap((page) => [
                `[${page.label}]`,
                `Title: ${page.title}`,
                `Description: ${page.description}`,
                `Canonical: ${page.canonical}`,
                `Slug: ${page.slug}`,
                `Robots: ${page.robots}`,
                `OG Image: ${page.og_image || 'N/A'}`,
                `Alternates: ${page.alternates.map((alternate) => `${alternate.locale}=${alternate.url}`).join(' | ')}`,
                `Score: ${page.score}/${page.total_checks}`,
                ...page.checks.map((check) => `- ${check.status.toUpperCase()}: ${check.message}`),
                '',
            ]),
        ].join('\n');

    if (typeof window === 'undefined' || typeof document === 'undefined') {
        feedbackMessage.value = localize('Export is not available in this environment.', 'التصدير غير متاح في هذه البيئة.');
        return;
    }

    const mimeType = format === 'json'
        ? 'application/json;charset=utf-8'
        : format === 'csv'
            ? 'text/csv;charset=utf-8'
            : 'text/plain;charset=utf-8';
    const extension = format === 'json' ? 'json' : format === 'csv' ? 'csv' : 'txt';
    const blob = new Blob([content], { type: mimeType });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${props.tenant.slug || 'tenant'}-seo-audit.${extension}`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    feedbackMessage.value = localize(`SEO ${format.toUpperCase()} report exported successfully.`, `تم تصدير تقرير SEO بصيغة ${format.toUpperCase()} بنجاح.`);
}
</script>

<template>
    <Head :title="localize('SEO Audit', 'تدقيق SEO')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('SEO Audit', 'تدقيق SEO') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Review public-page SEO quality, hreflang coverage, and export audit reports.', 'راجع جودة SEO للصفحات العامة وتغطية hreflang وصدّر تقارير التدقيق.') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="props.actions.website">
                        <Button variant="outline">{{ localize('Back To Website Settings', 'الرجوع لإعدادات الموقع') }}</Button>
                    </Link>
                    <Button variant="outline" @click="exportSeoReport('txt')">{{ localize('Export TXT', 'تصدير TXT') }}</Button>
                    <Button variant="outline" @click="exportSeoReport('csv')">{{ localize('Export CSV', 'تصدير CSV') }}</Button>
                    <Button @click="exportSeoReport('json')">{{ localize('Export JSON', 'تصدير JSON') }}</Button>
                </div>
            </div>

            <div class="rounded-lg border bg-muted/20 p-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <h2 class="font-semibold">{{ localize('Overall SEO Status', 'الحالة العامة لـ SEO') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ overallStatus.description }}</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-sm font-semibold" :class="overallStatus.className">
                        {{ overallStatus.label }}
                    </span>
                </div>
            </div>

            <div v-if="feedbackMessage" class="rounded-md border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800">
                {{ feedbackMessage }}
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div v-for="page in auditPages" :key="page.key" class="rounded-lg border bg-background p-4 space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold">{{ page.label }}</h3>
                            <p class="text-xs text-muted-foreground">{{ page.score }}/{{ page.checks.length }}</p>
                        </div>
                        <span class="rounded-full px-2 py-1 text-xs font-medium" :class="page.score === page.checks.length ? 'bg-emerald-100 text-emerald-700' : page.score > 0 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'">
                            {{ page.robots }}
                        </span>
                    </div>

                    <div v-if="page.publicNoindexWarning" class="rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                        {{ localize('This public page is currently blocked by noindex. Change robots if you want it indexed.', 'هذه الصفحة العامة محجوبة حاليا بواسطة noindex. غيّر robots إذا أردتها مفهرسة.') }}
                    </div>

                    <div class="space-y-2 text-sm">
                        <div>
                            <div class="font-medium text-slate-900">{{ page.title }}</div>
                            <div class="text-muted-foreground">{{ page.description }}</div>
                        </div>
                        <div><span class="font-medium">Canonical:</span> {{ page.canonical }}</div>
                        <div><span class="font-medium">Slug:</span> {{ page.slug || '/' }}</div>
                        <div><span class="font-medium">hreflang:</span> {{ page.alternates.map((alternate) => alternate.locale).join(', ') }}</div>
                    </div>

                    <div class="overflow-hidden rounded-md border">
                        <div class="bg-muted/40 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            {{ localize('Current vs Recommended Tags', 'الوسوم الحالية مقابل الموصى بها') }}
                        </div>
                        <div class="divide-y text-xs">
                            <div class="grid grid-cols-[120px,1fr,1fr] gap-3 px-3 py-2">
                                <div class="font-medium">{{ localize('Field', 'الحقل') }}</div>
                                <div class="font-medium">{{ localize('Current', 'الحالي') }}</div>
                                <div class="font-medium">{{ localize('Recommended', 'الموصى به') }}</div>
                            </div>
                            <div class="grid grid-cols-[120px,1fr,1fr] gap-3 px-3 py-2">
                                <div>{{ localize('Title', 'العنوان') }}</div>
                                <div>{{ page.title.length }} {{ localize('chars', 'حرف') }}</div>
                                <div>{{ page.recommended.title }}</div>
                            </div>
                            <div class="grid grid-cols-[120px,1fr,1fr] gap-3 px-3 py-2">
                                <div>{{ localize('Description', 'الوصف') }}</div>
                                <div>{{ page.description.length }} {{ localize('chars', 'حرف') }}</div>
                                <div>{{ page.recommended.description }}</div>
                            </div>
                            <div class="grid grid-cols-[120px,1fr,1fr] gap-3 px-3 py-2">
                                <div>Robots</div>
                                <div>{{ page.robots }}</div>
                                <div>{{ page.recommended.robots }}</div>
                            </div>
                            <div class="grid grid-cols-[120px,1fr,1fr] gap-3 px-3 py-2">
                                <div>Slug</div>
                                <div>{{ page.slug || '/' }}</div>
                                <div>{{ page.recommended.slug }}</div>
                            </div>
                            <div class="grid grid-cols-[120px,1fr,1fr] gap-3 px-3 py-2">
                                <div>hreflang</div>
                                <div>{{ page.alternates.map((alternate) => alternate.locale).join(', ') }}</div>
                                <div>{{ page.recommended.hreflang }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2 border-t pt-3">
                        <div v-for="(check, index) in page.checks" :key="`${page.key}-${index}`" class="flex items-start gap-2 text-xs">
                            <span class="mt-0.5 h-2.5 w-2.5 rounded-full" :class="check.ok ? 'bg-emerald-500' : 'bg-amber-500'" />
                            <span :class="check.ok ? 'text-emerald-700' : 'text-amber-700'">
                                {{ check.ok ? check.pass : check.fail }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </AdminLayout>
</template>
