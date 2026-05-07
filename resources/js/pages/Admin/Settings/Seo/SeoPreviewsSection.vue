<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { ref, computed } from 'vue';

interface LocalizedText {
    en: string | null;
    ar: string | null;
}

type SeoPageKey = 'home' | 'fleet' | 'about' | 'contact' | 'car' | 'booking_checkout' | 'booking_confirmation';

interface PreviewCard {
    key: SeoPageKey;
    label: string;
    title: string;
    description: string;
    path: string;
    robots: string;
    ogImage: string;
    ogImageAlt: string;
    twitterCardType: string;
    slug: string;
    score: number;
    checks: Array<{ ok: boolean; label: string; failLabel: string }>;
    alternates: Array<{ locale: string; url: string }>;
}

const props = defineProps<{
    previewCards: PreviewCard[];
    tenantName: string;
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const seoCopyMessage = ref('');

function copySeoMetaSummary(preview: PreviewCard) {
    const summary = [
        `${preview.label}`,
        `Title: ${preview.title}`,
        `Description: ${preview.description}`,
        `Canonical: ${preview.path}`,
        `Slug: ${preview.slug}`,
        `Robots: ${preview.robots}`,
        `OG Image: ${preview.ogImage || 'N/A'}`,
        `OG Image Alt: ${preview.ogImageAlt || 'N/A'}`,
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

function renderedMetaTags(preview: PreviewCard) {
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
        ...(preview.ogImageAlt ? [`<meta property="og:image:alt" content="${preview.ogImageAlt}">`] : []),
        `<meta name="twitter:card" content="${preview.twitterCardType}">`,
        `<meta name="twitter:title" content="${preview.title}">`,
        `<meta name="twitter:description" content="${preview.description}">`,
        `<meta name="twitter:image" content="${preview.ogImage || ''}">`,
        ...(preview.ogImageAlt ? [`<meta name="twitter:image:alt" content="${preview.ogImageAlt}">`] : []),
    ];
}

function copyRenderedMetaTags(preview: PreviewCard) {
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
</script>

<template>
    <div class="space-y-6">
        <!-- Copy Message -->
        <div v-if="seoCopyMessage" class="rounded-md border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800">
            {{ seoCopyMessage }}
        </div>

        <!-- Search Preview Section -->
        <section class="rounded-lg border p-5 space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">{{ localize('Search Result Preview', 'معاينة نتائج البحث') }}</h2>
                    <p class="text-sm text-muted-foreground">{{ localize('Google-style preview for each SEO page.', 'معاينة تشبه نتائج Google لكل صفحة SEO.') }}</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div v-for="preview in previewCards" :key="preview.key" class="rounded-lg border bg-background p-4">
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

        <!-- Open Graph Preview Section -->
        <section class="rounded-lg border p-5 space-y-4">
            <div>
                <h2 class="text-lg font-semibold">{{ localize('Open Graph Preview', 'معاينة Open Graph') }}</h2>
                <p class="text-sm text-muted-foreground">{{ localize('Preview how your pages appear on social media platforms.', 'معاينة كيفية ظهور صفحاتك على منصات وسائل التواصل الاجتماعي.') }}</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div v-for="preview in previewCards" :key="`${preview.key}-og`" class="overflow-hidden rounded-xl border bg-background">
                    <div class="aspect-[1.91/1] bg-slate-100">
                        <img v-if="preview.ogImage" :src="preview.ogImage" :alt="preview.ogImageAlt || preview.label" class="h-full w-full object-contain" />
                    </div>
                    <div class="space-y-1 p-4">
                        <div class="truncate text-xs uppercase tracking-wide text-muted-foreground">{{ preview.path }}</div>
                        <div class="font-semibold">{{ preview.title }}</div>
                        <div class="text-sm text-slate-600">{{ preview.description }}</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Twitter Card Preview Section -->
        <section class="rounded-lg border p-5 space-y-4">
            <div>
                <h2 class="text-lg font-semibold">{{ localize('Twitter / X Card Preview', 'معاينة بطاقة Twitter / X') }}</h2>
                <p class="text-sm text-muted-foreground">{{ localize('Preview how your pages appear on Twitter/X.', 'معاينة كيفية ظهور صفحاتك على Twitter/X.') }}</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div v-for="preview in previewCards" :key="`${preview.key}-twitter`" class="overflow-hidden rounded-2xl border bg-background shadow-sm">
                    <div class="aspect-[2/1] bg-slate-100">
                        <img v-if="preview.ogImage" :src="preview.ogImage" :alt="preview.ogImageAlt || preview.label" class="h-full w-full object-contain" />
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

        <!-- Rendered Meta Tags Section -->
        <section class="rounded-lg border p-5 space-y-4">
            <div>
                <h2 class="text-lg font-semibold">{{ localize('Rendered Meta Tags', 'وسوم الميتا الفعلية') }}</h2>
                <p class="text-sm text-muted-foreground">
                    {{ localize('These are the actual tags that social platforms and search engines will read from each page.', 'هذه هي الوسوم الفعلية التي ستقرأها منصات المشاركة ومحركات البحث من كل صفحة.') }}
                </p>
            </div>

            <div class="grid gap-4">
                <div v-for="preview in previewCards" :key="`${preview.key}-meta`" class="rounded-lg border bg-background p-4 space-y-3">
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
    </div>
</template>
