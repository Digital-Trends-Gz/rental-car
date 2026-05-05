<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { computed } from 'vue';

interface LocalizedText {
    en: string | null;
    ar: string | null;
}

type SeoPageKey = 'home' | 'fleet' | 'about' | 'contact' | 'car' | 'booking_checkout' | 'booking_confirmation';

interface PageData {
    key: SeoPageKey;
    title: LocalizedText;
    description: LocalizedText;
}

const props = defineProps<{
    pages: PageData[];
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

// Keyword Density Analysis
function analyzeKeywordDensity(text: string): Array<{ word: string; count: number; density: number }> {
    if (!text) return [];

    const words = text
        .toLowerCase()
        .replace(/[^\w\s]/g, '')
        .split(/\s+/)
        .filter((word) => word.length > 3); // Only words > 3 chars

    const stopWords = ['the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'her', 'was', 'one', 'our', 'out', 'has', 'his', 'how', 'man', 'own', 'say', 'she', 'too', 'use'];
    const arabicStopWords = ['في', 'من', 'أن', 'هو', 'على', 'إلى', 'أو', 'هل', 'لم', 'كان', 'قد', 'كل', 'التي', 'التي'];

    const currentStopWords = locale.value === 'ar' ? arabicStopWords : stopWords;
    const filteredWords = words.filter((word) => !currentStopWords.includes(word));

    const frequency: Record<string, number> = {};
    filteredWords.forEach((word) => {
        frequency[word] = (frequency[word] || 0) + 1;
    });

    return Object.entries(frequency)
        .map(([word, count]) => ({
            word,
            count,
            density: (count / filteredWords.length) * 100,
        }))
        .sort((a, b) => b.count - a.count)
        .slice(0, 10);
}

// Readability Score (simplified Flesch Reading Ease)
function analyzeReadability(text: string): { score: number; level: string; isGood: boolean } {
    if (!text.length) return { score: 0, level: 'N/A', isGood: false };

    const sentences = text.split(/[.!?]+/).filter((s) => s.trim().length > 0).length || 1;
    const words = text.split(/\s+/).filter((w) => w.trim().length > 0).length;
    const syllables = (text.match(/[aeiou]/gi) || []).length;

    // Simplified Flesch Reading Ease formula
    const score = Math.max(0, Math.min(100, 206.835 - 1.015 * (words / sentences) - 84.6 * (syllables / words)));

    let level = '';
    let isGood = false;

    if (score >= 60) {
        level = localize('Easy to Read', 'سهل القراءة');
        isGood = true;
    } else if (score >= 50) {
        level = localize('Moderate', 'متوسط');
        isGood = true;
    } else if (score >= 30) {
        level = localize('Difficult', 'صعب');
        isGood = false;
    } else {
        level = localize('Very Difficult', 'صعب جداً');
        isGood = false;
    }

    return { score: Math.round(score), level, isGood };
}

const pageAnalysis = computed(() => {
    return props.pages.map((page) => {
        const titleEn = page.title.en || '';
        const titleAr = page.title.ar || '';
        const descriptionEn = page.description.en || '';
        const descriptionAr = page.description.ar || '';

        const titleKeywords = analyzeKeywordDensity(titleEn + ' ' + titleAr);
        const descriptionReadability = analyzeReadability(descriptionEn || descriptionAr);
        const titleReadability = analyzeReadability(titleEn || titleAr);

        return {
            pageKey: page.key,
            titleKeywords,
            descriptionReadability,
            titleReadability,
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
                    {{ localize('Analyze keyword density and readability scores for your SEO content.', 'حلل كثافة الكلمات الرئيسية ودرجات سهولة القراءة لمحتوى SEO الخاص بك.') }}
                </p>
            </div>

            <div class="grid gap-4">
                <div v-for="analysis in pageAnalysis" :key="analysis.pageKey" class="rounded-lg border p-4 space-y-4">
                    <h3 class="font-semibold">{{ analysis.pageKey }}</h3>

                    <!-- Keyword Density -->
                    <div class="space-y-2">
                        <h4 class="text-sm font-medium">{{ localize('Top Keywords', 'أهم الكلمات الرئيسية') }}</h4>
                        <div v-if="analysis.titleKeywords.length > 0" class="grid gap-2">
                            <div v-for="(keyword, idx) in analysis.titleKeywords" :key="idx" class="flex items-center justify-between rounded bg-muted p-2 text-sm">
                                <span>{{ keyword.word }}</span>
                                <div class="flex items-center gap-2">
                                    <div class="w-24 bg-slate-200 rounded h-1.5">
                                        <div class="bg-blue-500 h-full rounded" :style="{ width: keyword.density * 2 + '%' }" />
                                    </div>
                                    <span class="text-xs text-muted-foreground">{{ keyword.density.toFixed(1) }}%</span>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">{{ localize('No keywords found', 'لم يتم العثور على كلمات رئيسية') }}</p>
                    </div>

                    <!-- Readability Score -->
                    <div class="space-y-2">
                        <h4 class="text-sm font-medium">{{ localize('Readability Score', 'درجة سهولة القراءة') }}</h4>
                        <div class="grid gap-2 md:grid-cols-2">
                            <div class="rounded bg-muted p-3 space-y-1">
                                <div class="text-xs text-muted-foreground">{{ localize('Title', 'العنوان') }}</div>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-2xl font-bold" :class="analysis.titleReadability.isGood ? 'text-emerald-600' : 'text-amber-600'">
                                        {{ analysis.titleReadability.score }}
                                    </span>
                                    <span class="text-sm" :class="analysis.titleReadability.isGood ? 'text-emerald-600' : 'text-amber-600'">
                                        {{ analysis.titleReadability.level }}
                                    </span>
                                </div>
                            </div>
                            <div class="rounded bg-muted p-3 space-y-1">
                                <div class="text-xs text-muted-foreground">{{ localize('Description', 'الوصف') }}</div>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-2xl font-bold" :class="analysis.descriptionReadability.isGood ? 'text-emerald-600' : 'text-amber-600'">
                                        {{ analysis.descriptionReadability.score }}
                                    </span>
                                    <span class="text-sm" :class="analysis.descriptionReadability.isGood ? 'text-emerald-600' : 'text-amber-600'">
                                        {{ analysis.descriptionReadability.level }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
