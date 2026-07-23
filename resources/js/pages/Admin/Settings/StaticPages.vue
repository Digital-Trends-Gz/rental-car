<script setup lang="ts">
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type PageKey = 'privacy_policy' | 'terms_of_use' | 'security_policy';
type LocalizedMap = Record<string, string>;
type NullableLocalizedMap = Record<string, string | null>;

type StaticPageSettings = Record<PageKey, {
    title: NullableLocalizedMap;
    content: NullableLocalizedMap;
}>;

type LocaleOption = {
    code: string;
    name: string;
    native: string;
    direction: 'ltr' | 'rtl';
};

const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
    };
    settings: {
        static_pages?: Partial<StaticPageSettings>;
        default_static_pages?: Partial<Record<PageKey, NullableLocalizedMap>>;
    };
    locales: LocaleOption[];
    actions: {
        update: string;
    };
}>();

const { locale } = useTrans();
const page = usePage<any>();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const pageSections = [
    {
        key: 'privacy_policy',
        label: 'Privacy Policy',
        labelAr: 'سياسة الخصوصية',
        description: 'Privacy and data handling page for this tenant website.',
        descriptionAr: 'صفحة الخصوصية والتعامل مع البيانات الخاصة بموقع هذا التاجر.',
        path: '/privacy-policy',
    },
    {
        key: 'terms_of_use',
        label: 'Terms of Use',
        labelAr: 'شروط الاستخدام',
        description: 'Terms shown to visitors and customers using this tenant website.',
        descriptionAr: 'الشروط التي تظهر للزوار والعملاء عند استخدام موقع هذا التاجر.',
        path: '/terms-of-use',
    },
    {
        key: 'security_policy',
        label: 'Security Policy',
        labelAr: 'سياسة الأمان',
        description: 'Security, account safety, and responsible-use content.',
        descriptionAr: 'محتوى الأمان وسلامة الحسابات والاستخدام المسؤول.',
        path: '/security-policy',
    },
] as const;

const normalizedLocales = computed<LocaleOption[]>(() => {
    if (Array.isArray(props.locales) && props.locales.length > 0) {
        return props.locales;
    }

    return [{ code: 'en', name: 'English', native: 'English', direction: 'ltr' }];
});

const activeLocaleCode = ref(normalizedLocales.value[0]?.code ?? 'en');
const activeLocale = computed(() => normalizedLocales.value.find((item) => item.code === activeLocaleCode.value) ?? normalizedLocales.value[0]);

const makeLocalizedMap = (value?: NullableLocalizedMap): LocalizedMap =>
    Object.fromEntries(normalizedLocales.value.map((item) => [item.code, value?.[item.code] ?? '']));

const form = useForm<{
    static_pages: Record<PageKey, {
        title: LocalizedMap;
        content: LocalizedMap;
    }>;
}>({
    static_pages: {
        privacy_policy: {
            title: makeLocalizedMap(props.settings.static_pages?.privacy_policy?.title),
            content: makeLocalizedMap(props.settings.static_pages?.privacy_policy?.content),
        },
        terms_of_use: {
            title: makeLocalizedMap(props.settings.static_pages?.terms_of_use?.title),
            content: makeLocalizedMap(props.settings.static_pages?.terms_of_use?.content),
        },
        security_policy: {
            title: makeLocalizedMap(props.settings.static_pages?.security_policy?.title),
            content: makeLocalizedMap(props.settings.static_pages?.security_policy?.content),
        },
    },
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const formErrorList = computed(() => Object.values(form.errors ?? {}).filter((value): value is string => typeof value === 'string' && value.length > 0));

const localeLabel = (localeOption: LocaleOption) => {
    const native = localeOption.native || localeOption.name || localeOption.code.toUpperCase();
    const name = localeOption.name && localeOption.name !== native ? ` - ${localeOption.name}` : '';

    return `${native}${name}`;
};

const directionFor = (localeCode: string): 'ltr' | 'rtl' =>
    normalizedLocales.value.find((item) => item.code === localeCode)?.direction ?? (['ar', 'ur', 'fa'].includes(localeCode) ? 'rtl' : 'ltr');

const defaultContentFor = (sectionKey: PageKey, localeCode: string): string =>
    String(props.settings.default_static_pages?.[sectionKey]?.[localeCode] ?? '').trim();

const usesDefaultContent = (sectionKey: PageKey, localeCode: string): boolean =>
    String(form.static_pages[sectionKey].content[localeCode] ?? '').trim() === '' && defaultContentFor(sectionKey, localeCode) !== '';

const submit = () => {
    form.put(props.actions.update, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="localize('Static Pages', 'الصفحات الثابتة')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Static Pages', 'الصفحات الثابتة') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Manage legal and policy pages for the tenant website.', 'إدارة صفحات الشروط والسياسات الخاصة بموقع التاجر.') }}
                    </p>
                </div>

                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جاري الحفظ...') : localize('Save Pages', 'حفظ الصفحات') }}
                </Button>
            </div>

            <div v-if="flashSuccess" class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ flashError }}
            </div>
            <div v-if="formErrorList.length" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc space-y-1 ps-5">
                    <li v-for="error in formErrorList" :key="error">{{ error }}</li>
                </ul>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-lg border bg-background p-5">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">{{ localize('Editing Language', 'لغة التعديل') }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ localize('Choose a language, then edit all page titles and content for that language.', 'اختر اللغة ثم عدل عناوين ومحتوى الصفحات لهذه اللغة.') }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-for="localeOption in normalizedLocales"
                                :key="localeOption.code"
                                type="button"
                                :variant="activeLocaleCode === localeOption.code ? 'default' : 'outline'"
                                @click="activeLocaleCode = localeOption.code"
                            >
                                {{ localeLabel(localeOption) }}
                            </Button>
                        </div>
                    </div>
                </section>

                <section class="space-y-5">
                    <div v-for="section in pageSections" :key="section.key" class="rounded-lg border bg-background p-5">
                        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold">{{ localize(section.label, section.labelAr) }}</h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ localize(section.description, section.descriptionAr) }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ section.path }}</p>
                            </div>
                            <Link :href="section.path" class="text-sm font-medium text-primary hover:underline" target="_blank">
                                {{ localize('Open Page', 'فتح الصفحة') }}
                            </Link>
                        </div>

                        <div class="space-y-4" :dir="directionFor(activeLocaleCode)">
                            <div class="space-y-2">
                                <Label :for="`static_${section.key}_${activeLocaleCode}_title`">
                                    {{ localize('Page Title', 'عنوان الصفحة') }} - {{ activeLocale?.code.toUpperCase() }}
                                </Label>
                                <Input
                                    :id="`static_${section.key}_${activeLocaleCode}_title`"
                                    v-model="form.static_pages[section.key].title[activeLocaleCode]"
                                    :dir="directionFor(activeLocaleCode)"
                                />
                                <p v-if="form.errors[`static_pages.${section.key}.title.${activeLocaleCode}`]" class="text-sm text-red-600">
                                    {{ form.errors[`static_pages.${section.key}.title.${activeLocaleCode}`] }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label :for="`static_${section.key}_${activeLocaleCode}_content`">
                                    {{ localize('Page Content', 'محتوى الصفحة') }} - {{ activeLocale?.code.toUpperCase() }}
                                </Label>
                                <RichTextEditor
                                    :id="`static_${section.key}_${activeLocaleCode}_content`"
                                    v-model="form.static_pages[section.key].content[activeLocaleCode]"
                                    :dir="directionFor(activeLocaleCode)"
                                    :placeholder="localize('Write page content...', 'اكتب محتوى الصفحة...')"
                                />
                                <p v-if="form.errors[`static_pages.${section.key}.content.${activeLocaleCode}`]" class="text-sm text-red-600">
                                    {{ form.errors[`static_pages.${section.key}.content.${activeLocaleCode}`] }}
                                </p>
                                <div
                                    v-if="defaultContentFor(section.key, activeLocaleCode)"
                                    class="rounded-md border border-dashed bg-muted/30 p-3 text-sm text-muted-foreground"
                                >
                                    <p class="mb-2 font-medium text-foreground">
                                        {{
                                            usesDefaultContent(section.key, activeLocaleCode)
                                                ? localize('Using Super Admin default content.', 'يتم استخدام المحتوى الافتراضي من السوبر أدمن.')
                                                : localize('Super Admin default content if this field is cleared:', 'المحتوى الافتراضي من السوبر أدمن إذا تركت هذا الحقل فارغًا:')
                                        }}
                                    </p>
                                    <div class="max-h-40 overflow-auto rounded border bg-background p-3" v-html="defaultContentFor(section.key, activeLocaleCode)" />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? localize('Saving...', 'جاري الحفظ...') : localize('Save Pages', 'حفظ الصفحات') }}
                    </Button>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
