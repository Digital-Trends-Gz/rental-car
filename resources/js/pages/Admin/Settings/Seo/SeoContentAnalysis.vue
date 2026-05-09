<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { computed } from 'vue';

type LocalizedText = Record<string, string | null>;
type SeoPageKey = 'home' | 'fleet' | 'about' | 'contact' | 'car' | 'booking_checkout' | 'booking_confirmation';

interface PageData {
    key: SeoPageKey;
    label: string;
    title: LocalizedText;
    description: LocalizedText;
    focusKeyword?: LocalizedText;
    slug: string;
}

const props = defineProps<{
    pages: PageData[];
    selectedLocale: string;
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

function localizedText(value?: LocalizedText | null): string {
    if (!value) {
        return '';
    }

    const preferred = String(value[props.selectedLocale] || '').trim();
    if (preferred !== '') {
        return preferred;
    }

    const firstAvailable = Object.values(value).find((item) => String(item || '').trim() !== '');

    return String(firstAvailable || '').trim();
}

function normalizeText(value: string): string {
    return String(value || '')
        .toLowerCase()
        .trim()
        .replace(/[^\p{L}\p{N}\s/_-]+/gu, ' ')
        .replace(/\s+/g, ' ');
}

function countOccurrences(text: string, phrase: string): number {
    const haystack = normalizeText(text);
    const needle = normalizeText(phrase);

    if (needle === '' || haystack === '') {
        return 0;
    }

    return haystack.split(needle).length - 1;
}

function analyzeKeywordDensity(text: string): Array<{ word: string; count: number; density: number }> {
    if (!text) return [];

    const words = normalizeText(text)
        .split(/\s+/)
        .filter((word) => word.length > 3);

    const englishStopWords = ['this', 'that', 'with', 'from', 'your', 'have', 'will', 'into', 'them', 'they', 'then', 'were', 'been', 'than'];
    const arabicStopWords = ['هذا', 'هذه', 'هناك', 'على', 'إلى', 'من', 'في', 'عن', 'عند', 'مع', 'تم', 'كل', 'بعد', 'قبل'];
    const currentStopWords = props.selectedLocale === 'ar' ? arabicStopWords : englishStopWords;
    const filteredWords = words.filter((word) => !currentStopWords.includes(word));

    const frequency: Record<string, number> = {};
    filteredWords.forEach((word) => {
        frequency[word] = (frequency[word] || 0) + 1;
    });

    return Object.entries(frequency)
        .map(([word, count]) => ({
            word,
            count,
            density: filteredWords.length > 0 ? (count / filteredWords.length) * 100 : 0,
        }))
        .sort((a, b) => b.count - a.count)
        .slice(0, 10);
}

function analyzeReadability(text: string): { score: number; level: string; isGood: boolean } {
    if (!text.length) {
        return {
            score: 0,
            level: localize('Not enough text', 'لا يوجد نص كافٍ'),
            isGood: false,
        };
    }

    const sentences = text.split(/[.!?؟]+/).filter((sentence) => sentence.trim().length > 0).length || 1;
    const words = text.split(/\s+/).filter((word) => word.trim().length > 0).length || 1;
    const longWords = text.split(/\s+/).filter((word) => normalizeText(word).length >= 6).length;
    const score = Math.max(0, Math.min(100, 100 - ((words / sentences) * 1.8 + (longWords / words) * 100 * 0.35)));

    if (score >= 65) {
        return { score: Math.round(score), level: localize('Easy', 'سهل'), isGood: true };
    }

    if (score >= 45) {
        return { score: Math.round(score), level: localize('Moderate', 'متوسط'), isGood: true };
    }

    if (score >= 25) {
        return { score: Math.round(score), level: localize('Difficult', 'صعب'), isGood: false };
    }

    return { score: Math.round(score), level: localize('Very Difficult', 'صعب جدا'), isGood: false };
}

const pageAnalysis = computed(() => {
    return props.pages.map((page) => {
        const title = localizedText(page.title);
        const description = localizedText(page.description);
        const focusKeyword = localizedText(page.focusKeyword);
        const keywordOccurrences = countOccurrences(`${title} ${description}`, focusKeyword);
        const descriptionWords = normalizeText(description).split(/\s+/).filter(Boolean).length || 1;
        const focusDensity = focusKeyword ? Number(((keywordOccurrences / descriptionWords) * 100).toFixed(1)) : 0;
        const checks = [
            {
                ok: focusKeyword.length > 0,
                label: localize('Focus keyword is set', 'تم تحديد الكلمة المفتاحية'),
                failLabel: localize('Set a focus keyword first', 'حدد الكلمة المفتاحية أولا'),
            },
            {
                ok: focusKeyword.length === 0 || normalizeText(title).includes(normalizeText(focusKeyword)),
                label: localize('Keyword appears in title', 'الكلمة المفتاحية موجودة في العنوان'),
                failLabel: localize('Keyword missing from title', 'الكلمة المفتاحية غير موجودة في العنوان'),
            },
            {
                ok: focusKeyword.length === 0 || normalizeText(description).includes(normalizeText(focusKeyword)),
                label: localize('Keyword appears in description', 'الكلمة المفتاحية موجودة في الوصف'),
                failLabel: localize('Keyword missing from description', 'الكلمة المفتاحية غير موجودة في الوصف'),
            },
            {
                ok: focusKeyword.length === 0 || normalizeText(page.slug).includes(normalizeText(focusKeyword)),
                label: localize('Keyword appears in slug', 'الكلمة المفتاحية موجودة في الرابط المختصر'),
                failLabel: localize('Keyword missing from slug', 'الكلمة المفتاحية غير موجودة في الرابط المختصر'),
            },
            {
                ok: focusKeyword.length === 0 || (focusDensity >= 0.5 && focusDensity <= 3.5),
                label: localize('Keyword density is balanced', 'كثافة الكلمة المفتاحية متوازنة'),
                failLabel: localize('Keyword density is too low or too high', 'كثافة الكلمة المفتاحية منخفضة أو مرتفعة جدا'),
            },
        ];

        return {
            pageKey: page.key,
            label: page.label,
            focusKeyword,
            focusDensity,
            keywordOccurrences,
            titleKeywords: analyzeKeywordDensity(title),
            descriptionReadability: analyzeReadability(description),
            titleReadability: analyzeReadability(title),
            checks,
        };
    });
});
</script>

<template>
    <div class="space-y-6">
        <section class="rounded-lg border p-5 space-y-4">
            <div>
                <h2 class="text-lg font-semibold">{{ localize('Content Analysis', 'تحليل المحتوى') }}</h2>
                <p class="text-sm text-muted-foreground">
                    {{ localize('Yoast-style checks for the currently selected SEO language.', 'فحوصات شبيهة بـ Yoast للغة SEO المحددة حاليا.') }}
                </p>
            </div>

            <div class="grid gap-4">
                <div v-for="analysis in pageAnalysis" :key="analysis.pageKey" class="rounded-lg border p-4 space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-semibold">{{ analysis.label }}</h3>
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Focus keyword', 'الكلمة المفتاحية') }}:
                                <span class="font-medium text-foreground">{{ analysis.focusKeyword || localize('Not set', 'غير محددة') }}</span>
                            </p>
                        </div>
                        <div class="rounded-md bg-muted px-3 py-2 text-right text-sm">
                            <div class="font-semibold">{{ analysis.keywordOccurrences }}</div>
                            <div class="text-xs text-muted-foreground">{{ localize('matches', 'مطابقات') }}</div>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
                        <div class="space-y-3">
                            <div class="rounded-lg border bg-muted/40 p-3">
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-medium">{{ localize('Focus Keyword Checks', 'فحوصات الكلمة المفتاحية') }}</h4>
                                    <span class="text-xs text-muted-foreground">
                                        {{ localize('Density', 'الكثافة') }}: {{ analysis.focusDensity }}%
                                    </span>
                                </div>
                                <div class="space-y-2">
                                    <div v-for="(check, idx) in analysis.checks" :key="`${analysis.pageKey}-check-${idx}`" class="flex items-start gap-2 text-sm">
                                        <span class="mt-1 h-2 w-2 rounded-full flex-shrink-0" :class="check.ok ? 'bg-emerald-500' : 'bg-amber-500'" />
                                        <span :class="check.ok ? 'text-emerald-700' : 'text-amber-700'">
                                            {{ check.ok ? check.label : check.failLabel }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border p-3 space-y-2">
                                <h4 class="text-sm font-medium">{{ localize('Top Title Keywords', 'أهم كلمات العنوان') }}</h4>
                                <div v-if="analysis.titleKeywords.length > 0" class="grid gap-2">
                                    <div v-for="(keyword, idx) in analysis.titleKeywords" :key="idx" class="flex items-center justify-between gap-3 rounded bg-muted px-3 py-2 text-sm">
                                        <span>{{ keyword.word }}</span>
                                        <div class="flex items-center gap-2">
                                            <div class="h-1.5 w-24 rounded bg-slate-200">
                                                <div class="h-full rounded bg-blue-500" :style="{ width: `${Math.min(keyword.density * 12, 100)}%` }" />
                                            </div>
                                            <span class="text-xs text-muted-foreground">{{ keyword.density.toFixed(1) }}%</span>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-muted-foreground">{{ localize('No clear keywords found yet.', 'لم يتم العثور على كلمات رئيسية واضحة بعد.') }}</p>
                            </div>
                        </div>

                        <div class="grid gap-3">
                            <div class="rounded-lg border bg-muted/40 p-3 space-y-1">
                                <div class="text-xs text-muted-foreground">{{ localize('Title Readability', 'سهولة قراءة العنوان') }}</div>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-2xl font-bold" :class="analysis.titleReadability.isGood ? 'text-emerald-600' : 'text-amber-600'">
                                        {{ analysis.titleReadability.score }}
                                    </span>
                                    <span class="text-sm" :class="analysis.titleReadability.isGood ? 'text-emerald-600' : 'text-amber-600'">
                                        {{ analysis.titleReadability.level }}
                                    </span>
                                </div>
                            </div>

                            <div class="rounded-lg border bg-muted/40 p-3 space-y-1">
                                <div class="text-xs text-muted-foreground">{{ localize('Description Readability', 'سهولة قراءة الوصف') }}</div>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-2xl font-bold" :class="analysis.descriptionReadability.isGood ? 'text-emerald-600' : 'text-amber-600'">
                                        {{ analysis.descriptionReadability.score }}
                                    </span>
                                    <span class="text-sm" :class="analysis.descriptionReadability.isGood ? 'text-emerald-600' : 'text-amber-600'">
                                        {{ analysis.descriptionReadability.level }}
                                    </span>
                                </div>
                            </div>

                            <div class="rounded-lg border bg-blue-50 p-3 text-sm text-blue-800">
                                {{ localize('This analysis is generated live from the currently selected language content. Save after the result looks correct.', 'هذا التحليل يتم إنشاؤه مباشرة من محتوى اللغة المحددة حاليا. احفظ بعد أن تصبح النتيجة مناسبة.') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
