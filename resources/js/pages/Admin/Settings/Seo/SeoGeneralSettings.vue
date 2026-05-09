<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type LocalizedText = Record<string, string | null>;

const props = defineProps<{
    form: {
        seo: {
            defaults: {
                title_suffix: LocalizedText;
                default_description: LocalizedText;
                og_image: string;
                og_image_alt: LocalizedText;
                robots: string;
            };
        };
        seo_og_image_temp_folders: string[];
        seo_og_image_removed_files: number[];
    };
    errors: Record<string, string>;
    selectedLocale: string;
    selectedLocaleLabel: string;
    seoOgImageFiles?: Array<{ id: number; url: string }>;
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);
</script>

<template>
    <section class="rounded-lg border p-5 space-y-4">
        <div>
            <h2 class="text-lg font-semibold">{{ localize('General SEO Settings', 'إعدادات SEO العامة') }}</h2>
            <p class="text-sm text-muted-foreground">
                {{ localize('Default values used across public pages when page-specific SEO is empty.', 'القيم الافتراضية المستخدمة عبر الصفحات العامة عندما تكون حقول SEO الخاصة بالصفحة فارغة.') }}
            </p>
        </div>

        <div class="rounded-lg border bg-muted/30 p-4 text-sm">
            <span class="font-medium">{{ localize('Editing language:', 'لغة التحرير:') }}</span>
            {{ selectedLocaleLabel }}
            <span class="text-muted-foreground">({{ selectedLocale.toUpperCase() }})</span>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
                <Label :for="`seo_title_suffix_${selectedLocale}`">
                    {{ localize('Title Suffix', 'لاحقة العنوان') }}
                </Label>
                <Input
                    :id="`seo_title_suffix_${selectedLocale}`"
                    v-model="form.seo.defaults.title_suffix[selectedLocale]"
                    :dir="selectedLocale === 'ar' ? 'rtl' : 'ltr'"
                />
                <p v-if="errors[`seo.defaults.title_suffix.${selectedLocale}`]" class="text-sm text-red-600">
                    {{ errors[`seo.defaults.title_suffix.${selectedLocale}`] }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="seo_robots">{{ localize('Default Robots Directive', 'تعليمات Robots الافتراضية') }}</Label>
                <select
                    id="seo_robots"
                    v-model="form.seo.defaults.robots"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                    <option value="index,follow">{{ localize('Index and Follow (Default)', 'الفهرسة والمتابعة (افتراضي)') }}</option>
                    <option value="noindex,follow">{{ localize('No Index, Follow', 'بدون فهرسة، متابعة') }}</option>
                    <option value="index,nofollow">{{ localize('Index, No Follow', 'الفهرسة، بدون متابعة') }}</option>
                    <option value="noindex,nofollow">{{ localize('No Index, No Follow', 'بدون فهرسة، بدون متابعة') }}</option>
                </select>
                <p v-if="errors['seo.defaults.robots']" class="text-sm text-red-600">
                    {{ errors['seo.defaults.robots'] }}
                </p>
            </div>

            <div class="space-y-2 md:col-span-2">
                <Label :for="`seo_default_description_${selectedLocale}`">
                    {{ localize('Default Description', 'الوصف الافتراضي') }}
                </Label>
                <textarea
                    :id="`seo_default_description_${selectedLocale}`"
                    v-model="form.seo.defaults.default_description[selectedLocale]"
                    rows="3"
                    :dir="selectedLocale === 'ar' ? 'rtl' : 'ltr'"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
                <p v-if="errors[`seo.defaults.default_description.${selectedLocale}`]" class="text-sm text-red-600">
                    {{ errors[`seo.defaults.default_description.${selectedLocale}`] }}
                </p>
            </div>

            <div class="space-y-2 md:col-span-2">
                <Label>{{ localize('Open Graph Image Upload', 'رفع صورة Open Graph') }}</Label>
                <FileUpload
                    v-model="form.seo_og_image_temp_folders"
                    :initial-files="seoOgImageFiles || []"
                    :allow-multiple="false"
                    :max-files="1"
                    collection="seo_og_image"
                    theme="light"
                    width="100%"
                    @file-removed="(data: { type: string; fileId?: number }) => {
                        if (data.type === 'existing' && data.fileId) {
                            form.seo_og_image_removed_files = [...new Set([...(form.seo_og_image_removed_files || []), data.fileId])];
                        }
                    }"
                />
                <p class="text-xs text-muted-foreground">
                    {{ localize('Upload an image for og:image, or paste a URL below as a fallback.', 'ارفع صورة لـ og:image أو الصق رابطًا أدناه كخيار احتياطي.') }}
                </p>
            </div>

            <div class="space-y-2 md:col-span-2">
                <Label for="seo_og_image">{{ localize('Open Graph Image URL', 'رابط صورة Open Graph') }}</Label>
                <Input
                    id="seo_og_image"
                    v-model="form.seo.defaults.og_image"
                    placeholder="https://example.com/og-image.jpg"
                />
                <p v-if="errors['seo.defaults.og_image']" class="text-sm text-red-600">
                    {{ errors['seo.defaults.og_image'] }}
                </p>
            </div>

            <div class="space-y-2 md:col-span-2">
                <Label :for="`seo_og_image_alt_${selectedLocale}`">
                    {{ localize('Open Graph Image Alt', 'النص البديل لصورة Open Graph') }}
                </Label>
                <Input
                    :id="`seo_og_image_alt_${selectedLocale}`"
                    v-model="form.seo.defaults.og_image_alt[selectedLocale]"
                    :dir="selectedLocale === 'ar' ? 'rtl' : 'ltr'"
                    :placeholder="selectedLocale === 'ar' ? 'شعار الشركة أو صورة معاينة الصفحة' : 'Company logo or social preview image'"
                />
                <p class="text-xs text-muted-foreground">
                    {{ localize('Used for og:image:alt and Twitter image accessibility metadata.', 'يُستخدم في og:image:alt وبيانات إمكانية الوصول لصورة Twitter.') }}
                </p>
                <p v-if="errors[`seo.defaults.og_image_alt.${selectedLocale}`]" class="text-sm text-red-600">
                    {{ errors[`seo.defaults.og_image_alt.${selectedLocale}`] }}
                </p>
            </div>
        </div>
    </section>
</template>

