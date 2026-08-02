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
                og_image_alt: LocalizedText;
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

const { locale, t } = useTrans();
const translationRoot = 'dashboard.admin.settings.seo';
const tr = (key: string, params: Record<string, string | number> = {}) => t(`${translationRoot}.${key}`, params);
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

    return `${tr(`page_titles.${pageKey}`)} | ${suffix}`;
};

const fallbackDescription = (pageKey: SeoPageKey): string => {
    const shared = localizedSeoText(props.settings.seo.defaults.default_description);

    if (shared) {
        return shared;
    }

    return tr(`fallback_descriptions.${pageKey}`, { name: previewName.value });
};

const auditPages = computed(() => {
    const pages: SeoPageKey[] = ['home', 'fleet', 'about', 'contact', 'car'];

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
        const ogImageAlt = localizedSeoText(props.settings.seo.defaults.og_image_alt);
        const checks = [
            {
                ok: title.length >= 30 && title.length <= 60,
                pass: tr('title_length_looks_good'),
                fail: tr('recommended_title_length_is_30_60_characters'),
            },
            {
                ok: description.length >= 70 && description.length <= 160,
                pass: tr('description_length_looks_good'),
                fail: tr('recommended_description_length_is_70_160_characters'),
            },
            {
                ok: Boolean((props.settings.seo.defaults.og_image || props.settings.logo_url || '').trim()),
                pass: tr('open_graph_image_is_set'),
                fail: tr('set_an_open_graph_image_for_sharing_previews'),
            },
            {
                ok: !((props.settings.seo.defaults.og_image || props.settings.logo_url || '').trim()) || (ogImageAlt.length >= 8 && ogImageAlt.length <= 125),
                pass: tr('open_graph_image_alt_text_looks_good'),
                fail: tr('add_descriptive_open_graph_image_alt_text_between_8_and_125_characters'),
            },
            {
                ok: canonical === '' || /^https?:\/\/\S+$/i.test(canonical),
                pass: tr('canonical_url_is_valid'),
                fail: tr('canonical_url_must_start_with_http_or_https'),
            },
            {
                ok: slug !== '' && /^[a-z0-9/_-]+$/i.test(slug) && !/\s/.test(slug),
                pass: tr('slug_format_looks_clean'),
                fail: tr('slug_should_use_clean_url_segments_without_spaces'),
            },
            {
                ok: alternates.length === enabledSeoLocales.value.length + 1,
                pass: tr('hreflang_alternates_are_available_for_enabled_locales'),
                fail: tr('hreflang_alternates_are_missing_for_one_or_more_enabled_locales'),
            },
            {
                ok: !publicNoindexWarning,
                pass: tr('indexable_public_page_is_not_blocked_by_robots'),
                fail: tr('public_page_is_set_to_noindex_and_may_disappear_from_search'),
            },
        ];

        return {
            key: pageKey,
            label: tr(`pages.${pageKey}`),
            title,
            description,
            canonical,
            slug,
            robots,
            alternates,
            ogImage: (props.settings.seo.defaults.og_image || props.settings.logo_url || '').trim(),
            ogImageAlt,
            isPublicPage,
            publicNoindexWarning,
            recommended: {
                title: tr('title_length_range'),
                description: tr('description_length_range'),
                robots: isPublicPage ? 'index,follow' : 'noindex,nofollow',
                slug: tr('lowercase_hyphenated_no_spaces'),
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
            label: tr('good'),
            description: tr('most_seo_signals_are_in_good_shape_for_the_selected_language'),
            className: 'bg-emerald-100 text-emerald-700',
        };
    }

    if (ratio >= 0.5) {
        return {
            label: tr('needs_work'),
            description: tr('some_pages_still_need_seo_cleanup_for_the_selected_language'),
            className: 'bg-amber-100 text-amber-700',
        };
    }

    return {
        label: tr('critical'),
        description: tr('seo_coverage_is_weak_for_the_selected_language_and_should_be_fixed_before_publishing_chang'),
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
            og_image_alt: page.ogImageAlt || null,
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
                ['page_key', 'page_label', 'title', 'description', 'canonical', 'slug', 'robots', 'og_image', 'og_image_alt', 'alternates', 'score', 'total_checks', 'warnings'].join(','),
                ...payload.pages.map((page) => ([
                    page.key,
                    `"${String(page.label).replace(/"/g, '""')}"`,
                    `"${String(page.title).replace(/"/g, '""')}"`,
                    `"${String(page.description).replace(/"/g, '""')}"`,
                    `"${String(page.canonical).replace(/"/g, '""')}"`,
                    `"${String(page.slug).replace(/"/g, '""')}"`,
                    `"${String(page.robots).replace(/"/g, '""')}"`,
                    `"${String(page.og_image || '').replace(/"/g, '""')}"`,
                    `"${String(page.og_image_alt || '').replace(/"/g, '""')}"`,
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
                `OG Image Alt: ${page.og_image_alt || 'N/A'}`,
                `Alternates: ${page.alternates.map((alternate) => `${alternate.locale}=${alternate.url}`).join(' | ')}`,
                `Score: ${page.score}/${page.total_checks}`,
                ...page.checks.map((check) => `- ${check.status.toUpperCase()}: ${check.message}`),
                '',
            ]),
        ].join('\n');

    if (typeof window === 'undefined' || typeof document === 'undefined') {
        feedbackMessage.value = tr('export_is_not_available_in_this_environment');
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
    feedbackMessage.value = tr('seo_format_report_exported_successfully', { format: format.toUpperCase() });
}
</script>

<template>
    <Head :title="tr('seo_audit')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ tr('seo_audit') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ tr('review_public_page_seo_quality_hreflang_coverage_and_export_audit_reports') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="props.actions.website">
                        <Button variant="outline">{{ tr('back_to_website_settings') }}</Button>
                    </Link>
                    <Button variant="outline" @click="exportSeoReport('txt')">{{ tr('export_txt') }}</Button>
                    <Button variant="outline" @click="exportSeoReport('csv')">{{ tr('export_csv') }}</Button>
                    <Button @click="exportSeoReport('json')">{{ tr('export_json') }}</Button>
                </div>
            </div>

            <div class="rounded-lg border bg-muted/20 p-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <h2 class="font-semibold">{{ tr('overall_seo_status') }}</h2>
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
                        {{ tr('this_public_page_is_currently_blocked_by_noindex_change_robots_if_you_want_it_indexed') }}
                    </div>

                    <div class="space-y-2 text-sm">
                        <div>
                            <div class="font-medium text-slate-900">{{ page.title }}</div>
                            <div class="text-muted-foreground">{{ page.description }}</div>
                        </div>
                        <div><span class="font-medium">{{ tr('canonical_url') }}:</span> {{ page.canonical }}</div>
                        <div><span class="font-medium">{{ tr('slug') }}:</span> {{ page.slug || '/' }}</div>
                        <div><span class="font-medium">{{ tr('hreflang') }}:</span> {{ page.alternates.map((alternate) => alternate.locale).join(', ') }}</div>
                    </div>

                    <div class="overflow-hidden rounded-md border">
                        <div class="bg-muted/40 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            {{ tr('current_vs_recommended_tags') }}
                        </div>
                        <div class="divide-y text-xs">
                            <div class="grid grid-cols-[120px,1fr,1fr] gap-3 px-3 py-2">
                                <div class="font-medium">{{ tr('field') }}</div>
                                <div class="font-medium">{{ tr('current') }}</div>
                                <div class="font-medium">{{ tr('recommended') }}</div>
                            </div>
                            <div class="grid grid-cols-[120px,1fr,1fr] gap-3 px-3 py-2">
                                <div>{{ tr('title') }}</div>
                                <div>{{ page.title.length }} {{ tr('chars') }}</div>
                                <div>{{ page.recommended.title }}</div>
                            </div>
                            <div class="grid grid-cols-[120px,1fr,1fr] gap-3 px-3 py-2">
                                <div>{{ tr('description') }}</div>
                                <div>{{ page.description.length }} {{ tr('chars') }}</div>
                                <div>{{ page.recommended.description }}</div>
                            </div>
                            <div class="grid grid-cols-[120px,1fr,1fr] gap-3 px-3 py-2">
                                <div>{{ tr('robots') }}</div>
                                <div>{{ page.robots }}</div>
                                <div>{{ page.recommended.robots }}</div>
                            </div>
                            <div class="grid grid-cols-[120px,1fr,1fr] gap-3 px-3 py-2">
                                <div>{{ tr('slug') }}</div>
                                <div>{{ page.slug || '/' }}</div>
                                <div>{{ page.recommended.slug }}</div>
                            </div>
                            <div class="grid grid-cols-[120px,1fr,1fr] gap-3 px-3 py-2">
                                <div>{{ tr('hreflang') }}</div>
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

