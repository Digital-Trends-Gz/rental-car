<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { useTrans } from '@/composables/useTrans';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type LocalizedText = Record<string, string>;

interface LocaleOption {
    code: string;
    name: string;
    native: string;
    direction: 'ltr' | 'rtl';
}

interface StaticPageContentSettings {
    support: LocalizedText;
    privacy_policy: LocalizedText;
    terms_conditions: LocalizedText;
    security_policy: LocalizedText;
    tenant_pages: TenantPageContentSettings;
}

interface TenantPageContentSettings {
    privacy_policy: LocalizedText;
    terms_of_use: LocalizedText;
    security_policy: LocalizedText;
}

interface ContentSection {
    key: keyof Omit<StaticPageContentSettings, 'tenant_pages'>;
    title: string;
    titleAr: string;
    description: string;
    descriptionAr: string;
    placeholder: string;
    placeholderAr: string;
    rows: number;
}

interface TenantContentSection {
    key: keyof TenantPageContentSettings;
    title: string;
    titleAr: string;
    description: string;
    descriptionAr: string;
    placeholder: string;
    placeholderAr: string;
}

const props = defineProps<{
    settings: StaticPageContentSettings;
    locales: LocaleOption[];
    actions: {
        update: string;
    };
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const normalizedLocales = computed<LocaleOption[]>(() => {
    if (props.locales.length > 0) {
        return props.locales;
    }

    return [
        {
            code: 'en',
            name: 'English',
            native: 'English',
            direction: 'ltr',
        },
    ];
});

const activeLocaleCode = ref(normalizedLocales.value[0]?.code ?? 'en');

const activeLocale = computed<LocaleOption>(() => {
    return normalizedLocales.value.find((localeOption) => localeOption.code === activeLocaleCode.value) ?? normalizedLocales.value[0];
});

const makeLocalizedText = (value?: LocalizedText): LocalizedText =>
    Object.fromEntries(normalizedLocales.value.map((localeOption) => [localeOption.code, value?.[localeOption.code] ?? '']));

const form = useForm<{
    settings: StaticPageContentSettings;
}>({
    settings: {
        support: makeLocalizedText(props.settings.support),
        privacy_policy: makeLocalizedText(props.settings.privacy_policy),
        terms_conditions: makeLocalizedText(props.settings.terms_conditions),
        security_policy: makeLocalizedText(props.settings.security_policy),
        tenant_pages: {
            privacy_policy: makeLocalizedText(props.settings.tenant_pages?.privacy_policy),
            terms_of_use: makeLocalizedText(props.settings.tenant_pages?.terms_of_use),
            security_policy: makeLocalizedText(props.settings.tenant_pages?.security_policy),
        },
    },
});

const sections: ContentSection[] = [
    {
        key: 'support',
        title: 'Support Page',
        titleAr: 'الدعم',
        description: 'Write the support page content shown to visitors.',
        descriptionAr: 'اكتب محتوى صفحة الدعم التي تظهر للزوار.',
        placeholder: 'Support instructions, contact details, and help text...',
        placeholderAr: 'تعليمات الدعم وبيانات التواصل ونص المساعدة...',
        rows: 10,
    },
    {
        key: 'privacy_policy',
        title: 'Privacy Policy Page',
        titleAr: 'سياسة الخصوصية',
        description: 'Write the privacy policy content.',
        descriptionAr: 'اكتب محتوى سياسة الخصوصية.',
        placeholder: 'Privacy policy content...',
        placeholderAr: 'محتوى سياسة الخصوصية...',
        rows: 12,
    },
    {
        key: 'terms_conditions',
        title: 'Terms and Conditions Page',
        titleAr: 'الشروط والأحكام',
        description: 'Write the terms and conditions content.',
        descriptionAr: 'اكتب محتوى الشروط والأحكام.',
        placeholder: 'Terms and conditions content...',
        placeholderAr: 'محتوى الشروط والأحكام...',
        rows: 12,
    },
    {
        key: 'security_policy',
        title: 'Security Policy Page',
        titleAr: 'سياسة الأمان',
        description: 'Write the security policy content for data protection, account safety, and responsible use.',
        descriptionAr: 'اكتب محتوى سياسة الأمان وحماية البيانات وسلامة الحسابات.',
        placeholder: 'Security policy content...',
        placeholderAr: 'محتوى سياسة الأمان...',
        rows: 12,
    },
];

const tenantSections: TenantContentSection[] = [
    {
        key: 'privacy_policy',
        title: 'Tenant Privacy Policy Default',
        titleAr: 'المحتوى الافتراضي لسياسة الخصوصية للمكاتب',
        description: 'Default privacy policy used on tenant websites when the tenant does not override it.',
        descriptionAr: 'المحتوى الافتراضي الذي يظهر في مواقع المكاتب إذا لم يضع المكتب محتوى خاص به.',
        placeholder: 'Tenant privacy policy default content...',
        placeholderAr: 'المحتوى الافتراضي لسياسة الخصوصية للمكاتب...',
    },
    {
        key: 'terms_of_use',
        title: 'Tenant Terms of Use Default',
        titleAr: 'المحتوى الافتراضي لشروط الاستخدام للمكاتب',
        description: 'Default terms of use used on tenant websites when the tenant does not override it.',
        descriptionAr: 'شروط الاستخدام الافتراضية التي تظهر في مواقع المكاتب إذا لم يضع المكتب محتوى خاص به.',
        placeholder: 'Tenant terms of use default content...',
        placeholderAr: 'المحتوى الافتراضي لشروط الاستخدام للمكاتب...',
    },
    {
        key: 'security_policy',
        title: 'Tenant Security Policy Default',
        titleAr: 'المحتوى الافتراضي لسياسة الأمان للمكاتب',
        description: 'Default security policy used on tenant websites when the tenant does not override it.',
        descriptionAr: 'سياسة الأمان الافتراضية التي تظهر في مواقع المكاتب إذا لم يضع المكتب محتوى خاص به.',
        placeholder: 'Tenant security policy default content...',
        placeholderAr: 'المحتوى الافتراضي لسياسة الأمان للمكاتب...',
    },
];

const localeLabel = (localeOption?: LocaleOption) => {
    if (!localeOption) {
        return '';
    }

    const native = localeOption.native || localeOption.name || localeOption.code.toUpperCase();
    const name = localeOption.name && localeOption.name !== native ? ` - ${localeOption.name}` : '';

    return `${native}${name}`;
};

const errorKey = (section: ContentSection) => `settings.${section.key}.${activeLocaleCode.value}`;

const tenantErrorKey = (section: TenantContentSection) => `settings.tenant_pages.${section.key}.${activeLocaleCode.value}`;

const submit = () => {
    form.put(props.actions.update, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="localize('Static Pages Content', 'محتوى الصفحات الثابتة')" />

    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">
                        {{ localize('Static Pages Content', 'محتوى الصفحات الثابتة') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            localize(
                                'Manage translated content for support, privacy policy, terms of use, and security policy pages.',
                                'إدارة محتوى مترجم لصفحات الدعم وسياسة الخصوصية والشروط والأحكام.'
                            )
                        }}
                    </p>
                </div>

                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جاري الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                </Button>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <Card>
                    <CardContent class="flex flex-wrap items-center gap-2 pt-6">
                        <span class="text-sm font-medium text-muted-foreground">
                            {{ localize('Editing Language', 'لغة التعديل') }}
                        </span>
                        <Button
                            v-for="localeOption in normalizedLocales"
                            :key="localeOption.code"
                            type="button"
                            size="sm"
                            :variant="activeLocaleCode === localeOption.code ? 'default' : 'outline'"
                            @click="activeLocaleCode = localeOption.code"
                        >
                            {{ localeLabel(localeOption) }}
                            <span class="text-xs uppercase opacity-75">({{ localeOption.code }})</span>
                        </Button>
                    </CardContent>
                </Card>

                <Card v-for="section in sections" :key="section.key">
                    <CardHeader>
                        <CardTitle>{{ localize(section.title, section.titleAr) }}</CardTitle>
                        <CardDescription>
                            {{ localize(section.description, section.descriptionAr) }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <Label :for="`${section.key}_${activeLocaleCode}`">
                            {{ localeLabel(activeLocale) }}
                            <span class="text-xs uppercase text-muted-foreground">({{ activeLocaleCode }})</span>
                        </Label>
                        <RichTextEditor
                            :id="`${section.key}_${activeLocaleCode}`"
                            v-model="form.settings[section.key][activeLocaleCode]"
                            :dir="activeLocale?.direction ?? 'ltr'"
                            :placeholder="activeLocale?.direction === 'rtl' ? section.placeholderAr : section.placeholder"
                        />
                        <p v-if="form.errors[errorKey(section)]" class="text-sm text-red-600">
                            {{ form.errors[errorKey(section)] }}
                        </p>
                    </CardContent>
                </Card>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? localize('Saving...', 'جاري الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                    </Button>
                </div>
            </form>
        </main>
    </SuperAdminLayout>
</template>
