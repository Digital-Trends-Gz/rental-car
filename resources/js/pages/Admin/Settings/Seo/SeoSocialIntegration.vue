<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { computed } from 'vue';

interface PreviewCard {
    path: string;
    label: string;
}

const props = defineProps<{
    previews: PreviewCard[];
    tenantName: string;
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const socialDebuggerLinks = computed(() => {
    return props.previews.map((preview) => {
        const url = encodeURIComponent(preview.path);
        return {
            pageLabel: preview.label,
            pageUrl: preview.path,
            debuggers: [
                {
                    name: 'Facebook Sharing Debugger',
                    icon: '📘',
                    url: `https://developers.facebook.com/tools/debug/?url=${url}`,
                    color: 'bg-blue-50 hover:bg-blue-100 text-blue-700',
                },
                {
                    name: 'X (Twitter) Card Validator',
                    icon: '𝕏',
                    url: `https://cards-dev.twitter.com/validator?url=${url}`,
                    color: 'bg-slate-50 hover:bg-slate-100 text-slate-700',
                },
                {
                    name: 'LinkedIn Inspector',
                    icon: '💼',
                    url: `https://www.linkedin.com/inspector/url/${url}/`,
                    color: 'bg-blue-50 hover:bg-blue-100 text-blue-700',
                },
                {
                    name: 'Open Graph Preview',
                    icon: '📱',
                    url: `https://ogp.me/?url=${url}`,
                    color: 'bg-purple-50 hover:bg-purple-100 text-purple-700',
                },
                {
                    name: 'Google Mobile-Friendly Test',
                    icon: '📲',
                    url: `https://search.google.com/test/mobile-friendly?url=${url}`,
                    color: 'bg-red-50 hover:bg-red-100 text-red-700',
                },
                {
                    name: 'Google Rich Results Test',
                    icon: '✨',
                    url: `https://search.google.com/test/rich-results?url=${url}`,
                    color: 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700',
                },
            ],
        };
    });
});
</script>

<template>
    <div class="space-y-6">
        <section class="rounded-lg border p-5 space-y-4">
            <div>
                <h2 class="text-lg font-semibold">{{ localize('Social Integration & Debuggers', 'تكامل الوسائط الاجتماعية والمُصححات') }}</h2>
                <p class="text-sm text-muted-foreground">
                    {{ localize('Test your SEO pages with external social platform debuggers and Google tools.', 'اختبر صفحات SEO الخاصة بك باستخدام أدوات تصحيح منصات الوسائط الاجتماعية الخارجية و Google.') }}
                </p>
            </div>

            <div class="grid gap-6">
                <div v-for="(page, idx) in socialDebuggerLinks" :key="idx" class="rounded-lg border p-4 space-y-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-semibold">{{ page.pageLabel }}</h3>
                            <p class="text-xs text-muted-foreground truncate">{{ page.pageUrl }}</p>
                        </div>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <a 
                            v-for="(dbg, didx) in page.debuggers" 
                            :key="didx"
                            :href="dbg.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded p-3 text-sm font-medium transition-colors"
                            :class="dbg.color"
                        >
                            <div class="flex items-center gap-2">
                                <span>{{ dbg.icon }}</span>
                                <span>{{ dbg.name }}</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-lg border bg-blue-50 p-5">
            <div class="space-y-2">
                <h3 class="font-semibold text-blue-900">{{ localize('💡 Pro Tip', 'نصيحة إحترافية') }}</h3>
                <p class="text-sm text-blue-700">
                    {{ localize(
                        'Use these debuggers to verify how your content appears on social platforms and how Google indexes your pages. Different platforms may cache content differently, so it\'s best to test regularly before major launches.',
                        'استخدم هذه المُصححات للتحقق من كيفية ظهور المحتوى على منصات التواصل الاجتماعي وكيف تقوم Google بفهرسة صفحاتك. قد تقوم منصات مختلفة بتخزين محتوى مختلف، لذا من الأفضل الاختبار بانتظام قبل الإطلاقات الكبيرة.'
                    ) }}
                </p>
            </div>
        </section>
    </div>
</template>
