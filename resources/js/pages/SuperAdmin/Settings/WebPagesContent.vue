<script setup lang="ts">
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
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

interface WebPageContentSettings {
    privacy_policy: LocalizedText;
    terms_of_use: LocalizedText;
    security_policy: LocalizedText;
}

interface ContentSection {
    key: keyof WebPageContentSettings;
    title: string;
    titleAr: string;
    description: string;
    descriptionAr: string;
    placeholder: string;
    placeholderAr: string;
}

const props = defineProps<{
    settings: WebPageContentSettings;
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
    settings: WebPageContentSettings;
}>({
    settings: {
        privacy_policy: makeLocalizedText(props.settings.privacy_policy),
        terms_of_use: makeLocalizedText(props.settings.terms_of_use),
        security_policy: makeLocalizedText(props.settings.security_policy),
    },
});

const sections: ContentSection[] = [
    {
        key: 'privacy_policy',
        title: 'Privacy Policy',
        titleAr: 'سياسة الخصوصية',
        description: 'Website privacy policy content shown on the public web page.',
        descriptionAr: 'محتوى سياسة الخصوصية الذي يظهر في صفحة الويب العامة.',
        placeholder: 'Write the website privacy policy...',
        placeholderAr: 'اكتب سياسة الخصوصية الخاصة بالموقع...',
    },
    {
        key: 'terms_of_use',
        title: 'Terms of Use',
        titleAr: 'سياسة الاستخدام',
        description: 'Website terms of use content shown on the public web page.',
        descriptionAr: 'محتوى سياسة الاستخدام الذي يظهر في صفحة الويب العامة.',
        placeholder: 'Write the website terms of use...',
        placeholderAr: 'اكتب سياسة الاستخدام الخاصة بالموقع...',
    },
    {
        key: 'security_policy',
        title: 'Security Policy',
        titleAr: 'سياسة الأمان',
        description: 'Website security policy content shown on the public web page.',
        descriptionAr: 'محتوى سياسة الأمان الذي يظهر في صفحة الويب العامة.',
        placeholder: 'Write the website security policy...',
        placeholderAr: 'اكتب سياسة الأمان الخاصة بالموقع...',
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

const submit = () => {
    form.put(props.actions.update, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="localize('Web Pages Content', 'محتوى صفحات الويب')" />

    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">
                        {{ localize('Web Pages Content', 'محتوى صفحات الويب') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            localize(
                                'Manage translated website content for privacy policy, terms of use, and security policy pages.',
                                'إدارة محتوى صفحات الويب المترجمة لسياسة الخصوصية وسياسة الاستخدام وسياسة الأمان.'
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
