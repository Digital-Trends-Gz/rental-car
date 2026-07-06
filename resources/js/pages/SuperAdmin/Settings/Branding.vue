<script setup lang="ts">
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    settings: {
        app_name: string;
        logo_url: string | null;
        favicon_url: string | null;
        register_hero_images: Record<string, string | null>;
        primary_color: string;
        secondary_color: string;
    };
    logoFiles: Array<{ id: number; url: string }>;
    faviconFiles: Array<{ id: number; url: string }>;
    registerHeroFiles: Record<string, Array<{ id: number; url: string }>>;
    supportedLocales: Array<{ code: string; name: string; native: string }>;
    actions: {
        update: string;
    };
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const form = useForm({
    app_name: props.settings.app_name ?? '',
    logo_url: props.settings.logo_url ?? '',
    favicon_url: props.settings.favicon_url ?? '',
    primary_color: props.settings.primary_color ?? '#3b82f6',
    secondary_color: props.settings.secondary_color ?? '#6d28d9',
    logo_temp_folders: [] as string[],
    logo_removed_files: [] as number[],
    favicon_temp_folders: [] as string[],
    favicon_removed_files: [] as number[],
    register_hero_temp_folders: {} as Record<string, string[]>,
    register_hero_removed_files: {} as Record<string, number[]>,
});

const fileUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const logoTempFolders = ref<string[]>([]);
const logoRemovedFileIds = ref<number[]>([]);

const faviconUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const faviconTempFolders = ref<string[]>([]);
const faviconRemovedFileIds = ref<number[]>([]);
const registerHeroUploadRefs = ref<Record<string, InstanceType<typeof FileUpload> | null>>({});
const registerHeroTempFolders = ref<Record<string, string[]>>(
    Object.fromEntries((props.supportedLocales || []).map((locale) => [locale.code, []])),
);
const registerHeroRemovedFileIds = ref<Record<string, number[]>>(
    Object.fromEntries((props.supportedLocales || []).map((locale) => [locale.code, []])),
);

watch(
    logoTempFolders,
    (value) => {
        form.logo_temp_folders = [...value];
    },
    { deep: true },
);

watch(
    faviconTempFolders,
    (value) => {
        form.favicon_temp_folders = [...value];
    },
    { deep: true },
);

watch(
    registerHeroTempFolders,
    (value) => {
        form.register_hero_temp_folders = JSON.parse(JSON.stringify(value));
    },
    { deep: true },
);

const previewName = computed(() => form.app_name || 'Car4u');
const uploadedLogoUrl = computed(() => props.logoFiles?.[0]?.url || null);
const previewLogo = computed(() => uploadedLogoUrl.value || form.logo_url || '/logo/logo.png');
const registerHeroPreview = (locale: string) =>
    props.registerHeroFiles?.[locale]?.[0]?.url || props.settings.register_hero_images?.[locale] || null;
const previewGradient = computed(
    () => `linear-gradient(135deg, ${form.primary_color || '#3b82f6'}, ${form.secondary_color || '#6d28d9'})`,
);

const handleLogoFileRemoved = (data: { type: string; fileId?: number }) => {
    if (data.type === 'existing' && data.fileId) {
        logoRemovedFileIds.value.push(data.fileId);
        form.logo_removed_files = [...new Set(logoRemovedFileIds.value)];
    }
};

const handleFaviconFileRemoved = (data: { type: string; fileId?: number }) => {
    if (data.type === 'existing' && data.fileId) {
        faviconRemovedFileIds.value.push(data.fileId);
        form.favicon_removed_files = [...new Set(faviconRemovedFileIds.value)];
    }
};

const setRegisterHeroUploadRef = (locale: string, el: InstanceType<typeof FileUpload> | null) => {
    registerHeroUploadRefs.value[locale] = el;
};

const handleRegisterHeroFileRemoved = (locale: string, data: { type: string; fileId?: number }) => {
    if (data.type === 'existing' && data.fileId) {
        const current = registerHeroRemovedFileIds.value[locale] || [];
        registerHeroRemovedFileIds.value[locale] = [...new Set([...current, data.fileId])];
        form.register_hero_removed_files = JSON.parse(JSON.stringify(registerHeroRemovedFileIds.value));
    }
};

const submit = () => {
    form
         .transform((data) => ({
             ...data,
             _method: 'put',
         }))
        .post(props.actions.update, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                logoTempFolders.value = [];
                form.logo_temp_folders = [];
                form.logo_removed_files = [];
                logoRemovedFileIds.value = [];
                fileUploadRef.value?.resetFiles();

                faviconTempFolders.value = [];
                form.favicon_temp_folders = [];
                form.favicon_removed_files = [];
                faviconRemovedFileIds.value = [];
                faviconUploadRef.value?.resetFiles();

                registerHeroTempFolders.value = Object.fromEntries((props.supportedLocales || []).map((locale) => [locale.code, []]));
                registerHeroRemovedFileIds.value = Object.fromEntries((props.supportedLocales || []).map((locale) => [locale.code, []]));
                form.register_hero_temp_folders = {};
                form.register_hero_removed_files = {};
                Object.values(registerHeroUploadRefs.value).forEach((upload) => upload?.resetFiles());
            },
        });
};
</script>

<template>
    <Head :title="localize('Branding Settings', 'إعدادات الهوية')" />

    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Branding Settings', 'إعدادات الهوية') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Update the global SaaS application name and default logo used across super admin and auth pages.', 'حدّث اسم منصة الـ SaaS العام والشعار الافتراضي المستخدم في صفحات السوبر أدمن وتسجيل الدخول.') }}
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                </Button>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Global Application Identity', 'هوية التطبيق العامة') }}</CardTitle>
                        <CardDescription>
                            {{ localize('These values are used for the main SaaS branding, email display name, and shared layouts.', 'تُستخدم هذه القيم لهوية منصة الـ SaaS الرئيسية واسم العرض في البريد والتخطيطات المشتركة.') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="app_name">{{ localize('SaaS Application Name', 'اسم تطبيق الـ SaaS') }}</Label>
                            <Input id="app_name" v-model="form.app_name" placeholder="Car4u" />
                            <p v-if="form.errors.app_name" class="text-sm text-red-600">
                                {{ form.errors.app_name }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label>{{ localize('Logo Upload', 'رفع الشعار') }}</Label>
                            <FileUpload
                                ref="fileUploadRef"
                                v-model="logoTempFolders"
                                :initial-files="logoFiles || []"
                                :allow-multiple="false"
                                :max-files="1"
                                collection="logo"
                                theme="light"
                                width="100%"
                                @file-removed="handleLogoFileRemoved"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Upload the SaaS logo here. Saved logos are normalized to 491 x 200 pixels while preserving aspect ratio. If no uploaded file exists, the fallback below or /public/logo/logo.png will be used.', 'ارفع شعار منصة الـ SaaS هنا. يتم توحيد الشعارات المحفوظة إلى 491 × 200 بكسل مع الحفاظ على الأبعاد. إذا لم يوجد ملف مرفوع، سيُستخدم الرابط الاحتياطي أدناه أو /public/logo/logo.png.') }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="logo_url">{{ localize('Fallback Logo URL', 'رابط الشعار الاحتياطي') }}</Label>
                            <Input id="logo_url" v-model="form.logo_url" placeholder="https://example.com/logo.png" />
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Optional fallback used only when there is no uploaded logo file.', 'خيار احتياطي يُستخدم فقط عند عدم وجود شعار مرفوع.') }}
                            </p>
                            <p v-if="form.errors.logo_url" class="text-sm text-red-600">
                                {{ form.errors.logo_url }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label>{{ localize('Favicon Upload', 'رفع الأيقونة المفضلة (Favicon)') }}</Label>
                            <FileUpload
                                ref="faviconUploadRef"
                                v-model="faviconTempFolders"
                                :initial-files="faviconFiles || []"
                                :allow-multiple="false"
                                :max-files="1"
                                :allowed-file-types="['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/jpeg', 'image/svg+xml']"
                                collection="favicon"
                                theme="light"
                                width="100%"
                                @file-removed="handleFaviconFileRemoved"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Upload the SaaS favicon here (standard sizes: 16x16, 32x32, or 48x48). Recommended formats: .ico or .png.', 'ارفع أيقونة الموقع المفضلة هنا (المقاسات القياسية: 16x16 أو 32x32 أو 48x48). التنسيقات الموصى بها: .ico أو .png.') }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="favicon_url">{{ localize('Fallback Favicon URL', 'رابط الأيقونة المفضلة الاحتياطي') }}</Label>
                            <Input id="favicon_url" v-model="form.favicon_url" placeholder="https://example.com/favicon.ico" />
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Optional fallback used only when there is no uploaded favicon file.', 'خيار احتياطي يُستخدم فقط عند عدم وجود أيقونة مرفوعة.') }}
                            </p>
                            <p v-if="form.errors.favicon_url" class="text-sm text-red-600">
                                {{ form.errors.favicon_url }}
                            </p>
                        </div>

                        <div class="space-y-4 rounded-xl border p-4">
                            <div class="space-y-1">
                                <h3 class="text-base font-semibold">
                                    {{ localize('Register Page Image', 'صورة صفحة التسجيل') }}
                                </h3>
                                <p class="text-xs text-muted-foreground">
                                    {{ localize('Upload a different left-side registration image for each dashboard language.', 'ارفع صورة مختلفة للجزء الأيسر من صفحة التسجيل حسب لغة لوحة التحكم.') }}
                                </p>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
                                <div
                                    v-for="localeOption in supportedLocales"
                                    :key="`register-hero-${localeOption.code}`"
                                    class="space-y-2 rounded-lg border bg-muted/20 p-3"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <Label>
                                            {{ localeOption.name }}
                                            <span class="text-muted-foreground">({{ localeOption.code.toUpperCase() }})</span>
                                        </Label>
                                        <span class="text-xs text-muted-foreground">{{ localeOption.native }}</span>
                                    </div>
                                    <FileUpload
                                        :ref="(el) => setRegisterHeroUploadRef(localeOption.code, el as InstanceType<typeof FileUpload> | null)"
                                        v-model="registerHeroTempFolders[localeOption.code]"
                                        :initial-files="registerHeroFiles?.[localeOption.code] || []"
                                        :allow-multiple="false"
                                        :max-files="1"
                                        :allowed-file-types="['image/jpeg', 'image/png', 'image/webp']"
                                        :collection="`register_hero_${localeOption.code}`"
                                        theme="light"
                                        width="100%"
                                        @file-removed="(data) => handleRegisterHeroFileRemoved(localeOption.code, data)"
                                    />
                                    <div v-if="registerHeroPreview(localeOption.code)" class="overflow-hidden rounded-md border bg-background">
                                        <img
                                            :src="registerHeroPreview(localeOption.code) || ''"
                                            :alt="`${localeOption.name} register image preview`"
                                            class="h-40 w-full object-cover"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="primary_color">{{ localize('Primary Color', 'اللون الأساسي') }}</Label>
                                <div class="flex items-center gap-3">
                                    <input id="primary_color" v-model="form.primary_color" type="color" class="h-10 w-14 rounded border border-input bg-white p-1" />
                                    <Input v-model="form.primary_color" placeholder="#3b82f6" />
                                </div>
                                <p v-if="form.errors.primary_color" class="text-sm text-red-600">
                                    {{ form.errors.primary_color }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="secondary_color">{{ localize('Secondary Color', 'اللون الثانوي') }}</Label>
                                <div class="flex items-center gap-3">
                                    <input id="secondary_color" v-model="form.secondary_color" type="color" class="h-10 w-14 rounded border border-input bg-white p-1" />
                                    <Input v-model="form.secondary_color" placeholder="#6d28d9" />
                                </div>
                                <p v-if="form.errors.secondary_color" class="text-sm text-red-600">
                                    {{ form.errors.secondary_color }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Preview', 'معاينة') }}</CardTitle>
                        <CardDescription>{{ localize('How the brand appears in the shared application header.', 'كيف تظهر الهوية في رأس التطبيق المشترك.') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="rounded-xl border bg-background p-4">
                            <div class="flex items-center gap-3">
                                <img :src="previewLogo" alt="Brand logo preview" class="h-10 w-10 rounded-md object-contain" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm text-muted-foreground">{{ localize('Application name', 'اسم التطبيق') }}</p>
                                    <p class="truncate text-lg font-semibold">{{ previewName }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-xl border">
                            <div class="h-16 w-full" :style="{ background: previewGradient }"></div>
                        </div>

                        <div class="rounded-xl border bg-muted/30 p-4">
                            <p class="text-sm font-medium">{{ localize('Notes', 'ملاحظات') }}</p>
                            <ul class="mt-2 space-y-2 text-sm text-muted-foreground">
                                <li>{{ localize('The name is used in shared layouts and browser titles.', 'يُستخدم الاسم في التخطيطات المشتركة وعناوين المتصفح.') }}</li>
                                <li>{{ localize('The logo is used globally when a tenant-specific logo is not available.', 'يُستخدم الشعار عالميًا عند عدم توفر شعار خاص بالمستأجر.') }}</li>
                                <li>{{ localize('Tenant websites can still override their own branding separately.', 'لا يزال بإمكان مواقع المستأجرين تخصيص هويتهم بشكل منفصل.') }}</li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </main>
    </SuperAdminLayout>
</template>
