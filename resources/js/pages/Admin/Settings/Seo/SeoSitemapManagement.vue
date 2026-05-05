<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed } from 'vue';

interface SitemapPage {
    path: string;
    priority: number;
    changeFreq: 'always' | 'hourly' | 'daily' | 'weekly' | 'monthly' | 'yearly' | 'never';
    lastmod?: string;
}

interface SitemapSettingsPayload {
    pages: SitemapPage[];
}

const props = defineProps<{
    baseUrl: string;
    modelValue: SitemapSettingsPayload;
}>();
const emit = defineEmits<{
    (event: 'update:modelValue', value: SitemapSettingsPayload): void;
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const fallbackDate = () => new Date().toISOString().split('T')[0];

const sitemapPages = computed<SitemapPage[]>({
    get: () => {
        if (Array.isArray(props.modelValue?.pages) && props.modelValue.pages.length > 0) {
            return props.modelValue.pages;
        }

        return [
            { path: '/', priority: 1.0, changeFreq: 'weekly', lastmod: fallbackDate() },
            { path: '/fleet', priority: 0.9, changeFreq: 'weekly', lastmod: fallbackDate() },
            { path: '/about', priority: 0.8, changeFreq: 'monthly', lastmod: fallbackDate() },
            { path: '/contact', priority: 0.8, changeFreq: 'monthly', lastmod: fallbackDate() },
        ];
    },
    set: (value) => {
        emit('update:modelValue', { pages: value });
    },
});

const sitemapXml = computed(() => {
    const urls = sitemapPages.value.map((page) => `  <url>
    <loc>${props.baseUrl}${page.path}</loc>
    <lastmod>${page.lastmod}</lastmod>
    <changefreq>${page.changeFreq}</changefreq>
    <priority>${page.priority.toFixed(1)}</priority>
  </url>`).join('\n');

    return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls}
</urlset>`;
});

function copySitemapXml() {
    if (typeof navigator === 'undefined' || !navigator.clipboard) return;
    navigator.clipboard.writeText(sitemapXml.value);
}

function downloadSitemapXml() {
    if (typeof document === 'undefined' || typeof window === 'undefined') return;
    const blob = new Blob([sitemapXml.value], { type: 'application/xml' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'sitemap.xml';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
}

function addPage() {
    sitemapPages.value = [...sitemapPages.value, {
        path: '',
        priority: 0.5,
        changeFreq: 'weekly',
        lastmod: fallbackDate(),
    }];
}

function removePage(index: number) {
    sitemapPages.value = sitemapPages.value.filter((_, idx) => idx !== index);
}
</script>

<template>
    <div class="space-y-6">
        <section class="rounded-lg border p-5 space-y-4">
            <div>
                <h2 class="text-lg font-semibold">{{ localize('XML Sitemap Management', 'إدارة خريطة الموقع XML') }}</h2>
                <p class="text-sm text-muted-foreground">
                    {{ localize('Manage your XML sitemap to help search engines crawl your site more effectively.', 'أدر خريطة موقعك XML لمساعدة محركات البحث على زحف موقعك بشكل أفضل.') }}
                </p>
            </div>

            <!-- Pages List -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium">{{ localize('Sitemap Entries', 'إدخالات خريطة الموقع') }}</h3>
                    <Button type="button" size="sm" @click="addPage">
                        {{ localize('+ Add Page', '+ إضافة صفحة') }}
                    </Button>
                </div>

                <div class="grid gap-3">
                    <div v-for="(page, idx) in sitemapPages" :key="idx" class="rounded-lg border p-3 space-y-3">
                        <div class="grid gap-3 md:grid-cols-4">
                            <div class="space-y-1">
                                <Label class="text-xs">{{ localize('Path', 'المسار') }}</Label>
                                <Input v-model="page.path" placeholder="/page-slug" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">{{ localize('Priority', 'الأولوية') }}</Label>
                                <select v-model.number="page.priority" class="w-full rounded-md border border-input bg-background px-2 py-1.5 text-sm">
                                    <option v-for="p in [0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1.0]" :key="p" :value="p">
                                        {{ p.toFixed(1) }}
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">{{ localize('Change Frequency', 'تكرار التغيير') }}</Label>
                                <select v-model="page.changeFreq" class="w-full rounded-md border border-input bg-background px-2 py-1.5 text-sm">
                                    <option value="always">Always</option>
                                    <option value="hourly">Hourly</option>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                    <option value="never">Never</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">{{ localize('Last Modified', 'آخر تعديل') }}</Label>
                                <div class="flex gap-1">
                                    <Input v-model="page.lastmod" type="date" class="flex-1" />
                                    <Button type="button" size="sm" variant="destructive" @click="removePage(idx)">
                                        {{ localize('Remove', 'حذف') }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- XML Output -->
            <div class="space-y-2">
                <h3 class="font-medium">{{ localize('Generated XML', 'XML المُنشأة') }}</h3>
                <div class="flex gap-2">
                    <Button type="button" variant="outline" @click="copySitemapXml">
                        {{ localize('Copy XML', 'نسخ XML') }}
                    </Button>
                    <Button type="button" variant="outline" @click="downloadSitemapXml">
                        {{ localize('Download XML', 'تنزيل XML') }}
                    </Button>
                </div>
                <div class="overflow-x-auto rounded-md bg-slate-950 p-3 text-xs text-slate-100">
                    <pre class="whitespace-pre-wrap break-words font-mono">{{ sitemapXml }}</pre>
                </div>
            </div>

            <div class="rounded-lg border bg-blue-50 p-3 space-y-2">
                <h4 class="text-sm font-medium text-blue-900">{{ localize('📋 Setup Instructions', '📋 تعليمات الإعداد') }}</h4>
                <ul class="text-sm text-blue-700 space-y-1 list-disc pl-5">
                    <li>{{ localize('Download the XML file above', 'نزّل ملف XML أعلاه') }}</li>
                    <li>{{ localize('Upload to your public root as sitemap.xml', 'حمّل إلى الجذر العام باسم sitemap.xml') }}</li>
                    <li>{{ localize('Submit to Google Search Console', 'قدّم إلى Google Search Console') }}</li>
                    <li>{{ localize('Add sitemap URL to robots.txt', 'أضف رابط sitemap إلى robots.txt') }}</li>
                </ul>
            </div>
        </section>
    </div>
</template>
