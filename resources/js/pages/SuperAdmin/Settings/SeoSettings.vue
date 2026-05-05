<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SeoSitemapManagement from '@/pages/Admin/Settings/Seo/SeoSitemapManagement.vue';
import SeoRobotsManagement from '@/pages/Admin/Settings/Seo/SeoRobotsManagement.vue';
import SeoRedirectManager from '@/pages/Admin/Settings/Seo/SeoRedirectManager.vue';
import { ref } from 'vue';

type LocalizedText = { en: string | null; ar: string | null };
type SeoPageKey = 'home' | 'fleet' | 'about' | 'contact' | 'car' | 'booking_checkout' | 'booking_confirmation';
type ActiveTab = 'defaults' | 'pages' | 'technical';

const props = defineProps<{
    settings: {
        defaults: {
            title_suffix: LocalizedText;
            default_description: LocalizedText;
            og_image: string | null;
            robots: string | null;
        };
        pages: Record<SeoPageKey, { title: LocalizedText; description: LocalizedText; canonical_url: string | null; robots?: string | null }>;
        technical?: {
            sitemap?: { pages?: Array<{ path: string; priority: number; changeFreq: 'always' | 'hourly' | 'daily' | 'weekly' | 'monthly' | 'yearly' | 'never'; lastmod?: string }> };
            robots?: { allowAll?: boolean; disallowPaths?: string[]; crawlDelay?: number; requestRate?: number; sitemapUrl?: string };
            redirects?: { items?: Array<{ id: string; fromPath: string; toPath: string; statusCode: 301 | 302 | 307 | 308; isPermanent: boolean; isActive: boolean }> };
        };
    };
    actions: { update: string };
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);
const activeTab = ref<ActiveTab>('defaults');
const fallbackDate = () => new Date().toISOString().split('T')[0];

const form = useForm({
    seo: {
        defaults: {
            title_suffix: {
                en: props.settings.defaults?.title_suffix?.en ?? '',
                ar: props.settings.defaults?.title_suffix?.ar ?? '',
            },
            default_description: {
                en: props.settings.defaults?.default_description?.en ?? '',
                ar: props.settings.defaults?.default_description?.ar ?? '',
            },
            og_image: props.settings.defaults?.og_image ?? '',
            robots: props.settings.defaults?.robots ?? 'index,follow',
        },
        pages: props.settings.pages,
        technical: {
            sitemap: {
                pages: Array.isArray(props.settings.technical?.sitemap?.pages) && props.settings.technical?.sitemap?.pages?.length
                    ? props.settings.technical.sitemap.pages
                    : [
                        { path: '/', priority: 1.0, changeFreq: 'weekly', lastmod: fallbackDate() },
                        { path: '/fleet', priority: 0.9, changeFreq: 'weekly', lastmod: fallbackDate() },
                        { path: '/about', priority: 0.8, changeFreq: 'monthly', lastmod: fallbackDate() },
                        { path: '/contact', priority: 0.8, changeFreq: 'monthly', lastmod: fallbackDate() },
                    ],
            },
            robots: {
                allowAll: props.settings.technical?.robots?.allowAll ?? true,
                disallowPaths: props.settings.technical?.robots?.disallowPaths ?? ['/superadmin', '/admin'],
                crawlDelay: Number(props.settings.technical?.robots?.crawlDelay ?? 1),
                requestRate: Number(props.settings.technical?.robots?.requestRate ?? 30),
                sitemapUrl: props.settings.technical?.robots?.sitemapUrl ?? '/sitemap.xml',
            },
            redirects: {
                items: props.settings.technical?.redirects?.items ?? [],
            },
        },
    },
});

const pageKeys: SeoPageKey[] = ['home', 'fleet', 'about', 'contact', 'car', 'booking_checkout', 'booking_confirmation'];

function submit() {
    form.put(props.actions.update, { preserveScroll: true });
}
</script>

<template>
    <Head :title="localize('Main Site SEO Settings', 'إعدادات SEO للموقع الأساسي')" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Main Site SEO Settings', 'إعدادات SEO للموقع الأساسي') }}</h1>
                    <p class="text-sm text-muted-foreground">{{ localize('Manage SEO for the main domain pages.', 'إدارة SEO لصفحات الدومين الرئيسي.') }}</p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save SEO Changes', 'حفظ تغييرات SEO') }}
                </Button>
            </div>

            <div class="rounded-lg border bg-muted/20 p-3">
                <div class="flex gap-2">
                    <Button type="button" :variant="activeTab === 'defaults' ? 'default' : 'outline'" @click="activeTab = 'defaults'">{{ localize('Defaults', 'الإعدادات الافتراضية') }}</Button>
                    <Button type="button" :variant="activeTab === 'pages' ? 'default' : 'outline'" @click="activeTab = 'pages'">{{ localize('Pages', 'الصفحات') }}</Button>
                    <Button type="button" :variant="activeTab === 'technical' ? 'default' : 'outline'" @click="activeTab = 'technical'">{{ localize('Technical', 'الإعدادات التقنية') }}</Button>
                </div>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section v-if="activeTab === 'defaults'" class="rounded-lg border p-5 space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Title Suffix (EN)</Label>
                            <Input v-model="form.seo.defaults.title_suffix.en" />
                        </div>
                        <div class="space-y-2">
                            <Label>Title Suffix (AR)</Label>
                            <Input v-model="form.seo.defaults.title_suffix.ar" dir="rtl" />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <Label>Default Description (EN)</Label>
                            <textarea v-model="form.seo.defaults.default_description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <Label>Default Description (AR)</Label>
                            <textarea v-model="form.seo.defaults.default_description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <Label>Open Graph Image URL</Label>
                            <Input v-model="form.seo.defaults.og_image" />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <Label>Default Robots</Label>
                            <Input v-model="form.seo.defaults.robots" />
                        </div>
                    </div>
                </section>

                <section v-if="activeTab === 'pages'" class="rounded-lg border p-5 space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div v-for="pageKey in pageKeys" :key="pageKey" class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ pageKey }}</h3>
                            <div class="space-y-2"><Label>Title (EN)</Label><Input v-model="form.seo.pages[pageKey].title.en" /></div>
                            <div class="space-y-2"><Label>Title (AR)</Label><Input v-model="form.seo.pages[pageKey].title.ar" dir="rtl" /></div>
                            <div class="space-y-2"><Label>Description (EN)</Label><textarea v-model="form.seo.pages[pageKey].description.en" rows="2" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" /></div>
                            <div class="space-y-2"><Label>Description (AR)</Label><textarea v-model="form.seo.pages[pageKey].description.ar" rows="2" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" /></div>
                            <div class="space-y-2"><Label>Canonical URL</Label><Input v-model="form.seo.pages[pageKey].canonical_url" /></div>
                            <div class="space-y-2">
                                <Label>Robots</Label>
                                <select v-model="form.seo.pages[pageKey].robots" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                    <option value="">{{ localize('Use global default', 'استخدم الافتراضي العام') }}</option>
                                    <option value="index,follow">index,follow</option>
                                    <option value="noindex,follow">noindex,follow</option>
                                    <option value="index,nofollow">index,nofollow</option>
                                    <option value="noindex,nofollow">noindex,nofollow</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="activeTab === 'technical'" class="space-y-6">
                    <SeoSitemapManagement :base-url="typeof window !== 'undefined' ? window.location.origin : 'https://example.com'" v-model="form.seo.technical.sitemap" />
                    <SeoRobotsManagement :base-url="typeof window !== 'undefined' ? window.location.origin : 'https://example.com'" v-model="form.seo.technical.robots" />
                    <SeoRedirectManager v-model="form.seo.technical.redirects" />
                </section>
            </form>
        </main>
    </SuperAdminLayout>
</template>
