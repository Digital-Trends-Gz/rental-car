<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed, ref, watch } from 'vue';

interface RobotsSettingsPayload {
    allowAll: boolean;
    disallowPaths: string[];
    crawlDelay: number;
    requestRate: number;
    sitemapUrl: string;
}

const props = defineProps<{
    baseUrl: string;
    modelValue: RobotsSettingsPayload;
}>();
const emit = defineEmits<{
    (event: 'update:modelValue', value: RobotsSettingsPayload): void;
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const robotsRules = ref<RobotsSettingsPayload>({
    allowAll: props.modelValue?.allowAll ?? true,
    disallowPaths: Array.isArray(props.modelValue?.disallowPaths) ? [...props.modelValue.disallowPaths] : ['/admin', '/private', '/api/internal'],
    crawlDelay: Number(props.modelValue?.crawlDelay ?? 1),
    requestRate: Number(props.modelValue?.requestRate ?? 30),
    sitemapUrl: props.modelValue?.sitemapUrl || '/sitemap.xml',
});

watch(
    () => props.modelValue,
    (value) => {
        robotsRules.value = {
            allowAll: value?.allowAll ?? true,
            disallowPaths: Array.isArray(value?.disallowPaths) ? [...value.disallowPaths] : ['/admin', '/private', '/api/internal'],
            crawlDelay: Number(value?.crawlDelay ?? 1),
            requestRate: Number(value?.requestRate ?? 30),
            sitemapUrl: value?.sitemapUrl || '/sitemap.xml',
        };
    },
    { deep: true }
);

watch(
    robotsRules,
    (value) => emit('update:modelValue', { ...value, disallowPaths: [...value.disallowPaths] }),
    { deep: true }
);

const robotsTxt = computed(() => {
    let content = '';

    if (robotsRules.value.allowAll) {
        content += 'User-agent: *\n';
        content += 'Allow: /\n';
    } else {
        content += 'User-agent: *\n';
        robotsRules.value.disallowPaths.forEach((path) => {
            content += `Disallow: ${path}\n`;
        });
    }

    if (robotsRules.value.crawlDelay > 0) {
        content += `Crawl-delay: ${robotsRules.value.crawlDelay}\n`;
    }

    if (robotsRules.value.requestRate > 0) {
        content += `Request-rate: ${robotsRules.value.requestRate}/1m\n`;
    }

    content += '\n# Sitemaps\n';
    content += `Sitemap: ${props.baseUrl}${robotsRules.value.sitemapUrl}\n`;

    return content;
});

function copyRobotsTxt() {
    if (typeof navigator === 'undefined' || !navigator.clipboard) return;
    navigator.clipboard.writeText(robotsTxt.value);
}

function downloadRobotsTxt() {
    if (typeof document === 'undefined' || typeof window === 'undefined') return;
    const blob = new Blob([robotsTxt.value], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'robots.txt';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
}

function addDisallowPath() {
    robotsRules.value.disallowPaths.push('');
}

function removeDisallowPath(index: number) {
    robotsRules.value.disallowPaths.splice(index, 1);
}
</script>

<template>
    <div class="space-y-6">
        <section class="rounded-lg border p-5 space-y-4">
            <div>
                <h2 class="text-lg font-semibold">{{ localize('Robots.txt Management', 'إدارة Robots.txt') }}</h2>
                <p class="text-sm text-muted-foreground">
                    {{ localize('Control how search engines crawl and index your site.', 'تحكم في كيفية زحف محركات البحث لموقعك وفهرسته.') }}
                </p>
            </div>

            <!-- Allow/Disallow Toggle -->
            <div class="rounded-lg border p-4 space-y-3">
                <div class="space-y-2">
                    <Label class="font-medium">{{ localize('Crawling Policy', 'سياسة الزحف') }}</Label>
                    <div class="flex gap-3">
                        <button
                            @click="robotsRules.allowAll = true"
                            :class="[
                                'flex-1 rounded-md border px-3 py-2 text-sm font-medium transition-colors',
                                robotsRules.allowAll
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                    : 'border-border bg-background text-foreground hover:bg-muted',
                            ]"
                        >
                            {{ localize('Allow All', 'السماح بالكل') }}
                        </button>
                        <button
                            @click="robotsRules.allowAll = false"
                            :class="[
                                'flex-1 rounded-md border px-3 py-2 text-sm font-medium transition-colors',
                                !robotsRules.allowAll
                                    ? 'border-amber-500 bg-amber-50 text-amber-700'
                                    : 'border-border bg-background text-foreground hover:bg-muted',
                            ]"
                        >
                            {{ localize('Block Paths', 'منع المسارات') }}
                        </button>
                    </div>
                </div>

                <!-- Disallow Paths -->
                <div v-if="!robotsRules.allowAll" class="space-y-2">
                    <Label class="text-sm">{{ localize('Disallowed Paths', 'المسارات الممنوعة') }}</Label>
                    <div class="space-y-2">
                        <div v-for="(path, idx) in robotsRules.disallowPaths" :key="idx" class="flex gap-2">
                            <Input
                                v-model="robotsRules.disallowPaths[idx]"
                                placeholder="/admin"
                                class="flex-1"
                            />
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                @click="removeDisallowPath(idx)"
                            >
                                {{ localize('Remove', 'حذف') }}
                            </Button>
                        </div>
                    </div>
                    <Button type="button" size="sm" @click="addDisallowPath">
                        {{ localize('+ Add Path', '+ إضافة مسار') }}
                    </Button>
                </div>
            </div>

            <!-- Crawl Delay & Request Rate -->
            <div class="rounded-lg border p-4 space-y-3">
                <h3 class="font-medium">{{ localize('Crawl Settings', 'إعدادات الزحف') }}</h3>
                <div class="grid gap-3 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>{{ localize('Crawl Delay (seconds)', 'تأخير الزحف (ثانية)') }}</Label>
                        <Input
                            v-model.number="robotsRules.crawlDelay"
                            type="number"
                            min="0"
                            max="60"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ localize('Wait between requests. Higher = fewer requests.', 'انتظر بين الطلبات. أعلى = طلبات أقل.') }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label>{{ localize('Request Rate (per minute)', 'معدل الطلب (لكل دقيقة)') }}</Label>
                        <Input
                            v-model.number="robotsRules.requestRate"
                            type="number"
                            min="0"
                            max="1000"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ localize('Max requests per minute. 0 = unlimited.', 'الحد الأقصى للطلبات لكل دقيقة. 0 = غير محدود.') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Sitemap URL -->
            <div class="rounded-lg border p-4 space-y-2">
                <Label>{{ localize('Sitemap URL', 'رابط خريطة الموقع') }}</Label>
                <Input v-model="robotsRules.sitemapUrl" placeholder="/sitemap.xml" />
            </div>

            <!-- Generated Content -->
            <div class="space-y-2">
                <h3 class="font-medium">{{ localize('Generated robots.txt', 'robots.txt المُنشأة') }}</h3>
                <div class="flex gap-2">
                    <Button type="button" variant="outline" @click="copyRobotsTxt">
                        {{ localize('Copy Text', 'نسخ النص') }}
                    </Button>
                    <Button type="button" variant="outline" @click="downloadRobotsTxt">
                        {{ localize('Download File', 'تنزيل الملف') }}
                    </Button>
                </div>
                <div class="overflow-x-auto rounded-md bg-slate-950 p-3 text-xs text-slate-100">
                    <pre class="whitespace-pre-wrap break-words font-mono">{{ robotsTxt }}</pre>
                </div>
            </div>

            <div class="rounded-lg border bg-blue-50 p-3 space-y-2">
                <h4 class="text-sm font-medium text-blue-900">{{ localize('📋 Setup Instructions', '📋 تعليمات الإعداد') }}</h4>
                <ul class="text-sm text-blue-700 space-y-1 list-disc pl-5">
                    <li>{{ localize('Download or copy the content above', 'نزّل أو انسخ المحتوى أعلاه') }}</li>
                    <li>{{ localize('Save as robots.txt in your public root directory', 'احفظ باسم robots.txt في مجلد الجذر العام') }}</li>
                    <li>{{ localize('Verify in Google Search Console', 'تحقق في Google Search Console') }}</li>
                    <li>{{ localize('Monitor crawl stats and errors', 'راقب إحصائيات الزحف والأخطاء') }}</li>
                </ul>
            </div>
        </section>
    </div>
</template>
