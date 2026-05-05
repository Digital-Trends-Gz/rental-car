<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed } from 'vue';

interface LocalizedText {
    en: string | null;
    ar: string | null;
}

const props = defineProps<{
    form: {
        seo: {
            defaults: {
                title_suffix: LocalizedText;
                default_description: LocalizedText;
                og_image: string;
                robots: string;
            };
        };
    };
    errors: Record<string, string>;
}>();

const emit = defineEmits<{
    update: [];
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

        <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
                <Label for="seo_title_suffix_en">{{ localize('Title Suffix (EN)', 'لاحقة العنوان (EN)') }}</Label>
                <Input 
                    id="seo_title_suffix_en" 
                    v-model="form.seo.defaults.title_suffix.en" 
                />
                <p v-if="errors['seo.defaults.title_suffix.en']" class="text-sm text-red-600">
                    {{ errors['seo.defaults.title_suffix.en'] }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="seo_title_suffix_ar">{{ localize('Title Suffix (AR)', 'لاحقة العنوان (AR)') }}</Label>
                <Input 
                    id="seo_title_suffix_ar" 
                    v-model="form.seo.defaults.title_suffix.ar" 
                    dir="rtl" 
                />
                <p v-if="errors['seo.defaults.title_suffix.ar']" class="text-sm text-red-600">
                    {{ errors['seo.defaults.title_suffix.ar'] }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="seo_default_description_en">{{ localize('Default Description (EN)', 'الوصف الافتراضي (EN)') }}</Label>
                <textarea 
                    id="seo_default_description_en" 
                    v-model="form.seo.defaults.default_description.en" 
                    rows="3" 
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" 
                />
                <p v-if="errors['seo.defaults.default_description.en']" class="text-sm text-red-600">
                    {{ errors['seo.defaults.default_description.en'] }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="seo_default_description_ar">{{ localize('Default Description (AR)', 'الوصف الافتراضي (AR)') }}</Label>
                <textarea 
                    id="seo_default_description_ar" 
                    v-model="form.seo.defaults.default_description.ar" 
                    rows="3" 
                    dir="rtl" 
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" 
                />
                <p v-if="errors['seo.defaults.default_description.ar']" class="text-sm text-red-600">
                    {{ errors['seo.defaults.default_description.ar'] }}
                </p>
            </div>

            <div class="space-y-2">
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
        </div>
    </section>
</template>
