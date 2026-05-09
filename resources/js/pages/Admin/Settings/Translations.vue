<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useTrans } from '@/composables/useTrans';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type LocaleMeta = {
    code: string;
    name: string;
    native: string;
};

type TranslationRow = {
    key: string;
    defaults: Record<string, string>;
    values: Record<string, string>;
};

const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
    };
    default_locale?: string;
    supported_locales: LocaleMeta[];
    enabled_locales: string[];
    rows: TranslationRow[];
    actions: {
        update: string;
    };
}>();

const page = usePage<any>();
const { locale } = useTrans();
const search = ref('');
const focusedLocale = ref<string>('');
const onlyCustomized = ref(false);
const onlyEmptyForFocusedLocale = ref(false);
const localize = (en: string, ar: string, ur: string = en) => (locale.value === 'ar' ? ar : locale.value === 'ur' ? ur : en);
const localeCodes = computed(() => props.supported_locales.map((item) => item.code));
const localeMetaByCode = computed(() =>
    props.supported_locales.reduce<Record<string, LocaleMeta>>((acc, item) => {
        acc[item.code] = item;
        return acc;
    }, {})
);

const form = useForm({
    default_locale: props.default_locale || props.enabled_locales[0] || localeCodes.value[0] || 'en',
    enabled_locales: Array.isArray(props.enabled_locales) && props.enabled_locales.length
        ? [...props.enabled_locales]
        : [...localeCodes.value],
    rows: props.rows.map((row) => ({
        key: row.key,
        values: { ...row.values },
    })),
});

if (!focusedLocale.value) {
    focusedLocale.value = localeCodes.value[0] || 'en';
}

const enabledLocaleOptions = computed(() =>
    localeCodes.value.filter((localeCode) => form.enabled_locales.includes(localeCode)),
);

watch(
    () => form.enabled_locales.slice(),
    (enabledLocales) => {
        const normalized = enabledLocales.filter((localeCode, index, array) => localeCode !== '' && array.indexOf(localeCode) === index);
        if (normalized.length === 0) {
            return;
        }

        if (!normalized.includes(form.default_locale)) {
            form.default_locale = normalized[0] || localeCodes.value[0] || 'en';
        }
    },
    { immediate: true },
);

const rowsWithDefaults = computed(() =>
    props.rows.map((row, index) => ({
        ...row,
        formRow: form.rows[index],
    }))
);

const filteredRows = computed(() => {
    const query = search.value.trim().toLowerCase();

    return rowsWithDefaults.value.filter((row) => {
        const matchesSearch = !query || row.key.toLowerCase().includes(query) || localeCodes.value.some((locale) =>
            String(row.defaults?.[locale] || '').toLowerCase().includes(query)
            || String(row.formRow?.values?.[locale] || '').toLowerCase().includes(query)
        );

        if (!matchesSearch) {
            return false;
        }

        if (onlyCustomized.value && !isRowCustomized(row)) {
            return false;
        }

        if (onlyEmptyForFocusedLocale.value && !isFocusedLocaleEmpty(row)) {
            return false;
        }

        return true;
    });
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const formErrorList = computed(() => Object.values(form.errors ?? {}).filter((value): value is string => typeof value === 'string' && value.length > 0));

function isEmpty(value: unknown): boolean {
    return String(value ?? '').trim() === '';
}

function isRowCustomized(row: (typeof rowsWithDefaults.value)[number]): boolean {
    return localeCodes.value.some((locale) => !isEmpty(row.formRow?.values?.[locale]));
}

function isFocusedLocaleEmpty(row: (typeof rowsWithDefaults.value)[number]): boolean {
    const locale = focusedLocale.value;
    if (!locale) return true;

    return isEmpty(row.formRow?.values?.[locale]);
}

function copyDefaultToLocale(row: (typeof rowsWithDefaults.value)[number], locale: string) {
    row.formRow.values[locale] = String(row.defaults?.[locale] || '');
}

function clearLocaleValue(row: (typeof rowsWithDefaults.value)[number], locale: string) {
    row.formRow.values[locale] = '';
}

function fillEmptyFromDefaultsForFocusedLocale() {
    const locale = focusedLocale.value;
    if (!locale) return;

    rowsWithDefaults.value.forEach((row) => {
        if (isEmpty(row.formRow.values[locale])) {
            row.formRow.values[locale] = String(row.defaults?.[locale] || '');
        }
    });
}

function clearFocusedLocaleValues() {
    const locale = focusedLocale.value;
    if (!locale) return;

    rowsWithDefaults.value.forEach((row) => {
        row.formRow.values[locale] = '';
    });
}

function submit() {
    if (!Array.isArray(form.enabled_locales) || form.enabled_locales.length === 0) {
        form.enabled_locales = [localeCodes.value[0] || 'en'];
    }

    if (!form.enabled_locales.includes(form.default_locale)) {
        form.default_locale = form.enabled_locales[0] || localeCodes.value[0] || 'en';
    }

    form.put(props.actions.update, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="localize('Translations Settings', 'إعدادات الترجمات', 'ترجمہ کی ترتیبات')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Translations Settings', 'إعدادات الترجمات', 'ترجمہ کی ترتیبات') }}</h1>
                    <p class="text-sm text-muted-foreground">{{ localize('Enable languages and edit words in table format for this tenant website.', 'فعّل اللغات وعدّل الكلمات بصيغة جدول لهذا الموقع الخاص بالمستأجر.', 'اس کرایہ دار ویب سائٹ کے لیے زبانیں فعال کریں اور الفاظ کو جدول کی صورت میں ترمیم کریں.') }}</p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...', 'محفوظ کیا جا رہا ہے...') : localize('Save Changes', 'حفظ التغييرات', 'تبدیلیاں محفوظ کریں') }}
                </Button>
            </div>

            <div v-if="flashSuccess" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ flashError }}
            </div>
            <div v-if="formErrorList.length" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <div class="font-medium">{{ localize('Please fix the following errors:', 'يرجى إصلاح الأخطاء التالية:', 'براہ کرم درج ذیل غلطیاں درست کریں:') }}</div>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="(message, idx) in formErrorList" :key="idx">{{ message }}</li>
                </ul>
            </div>

            <section class="rounded-lg border p-5 space-y-4">
                <div>
                    <h2 class="text-lg font-semibold">{{ localize('Default Language', 'اللغة الافتراضية', 'طے شدہ زبان') }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Visitors will see this language first when opening the tenant website.', 'سیرى الزوار هذه اللغة أولاً عند فتح موقع المستأجر.', 'صارفین ویب سائٹ کھولتے وقت یہ زبان پہلے دیکھیں گے.') }}
                    </p>
                </div>
                <div class="space-y-2">
                    <Label for="default_locale">Default website language</Label>
                    <select
                        id="default_locale"
                        v-model="form.default_locale"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                    >
                        <option
                            v-for="locale in (enabledLocaleOptions.length ? enabledLocaleOptions : localeCodes)"
                            :key="`default-${locale}`"
                            :value="locale"
                        >
                            {{ localeMetaByCode[locale]?.native || localeMetaByCode[locale]?.name || locale.toUpperCase() }}
                            ({{ locale.toUpperCase() }})
                        </option>
                    </select>
                    <p v-if="form.errors.default_locale" class="text-sm text-red-600">
                        {{ form.errors.default_locale }}
                    </p>
                </div>
            </section>

            <section class="rounded-lg border p-5 space-y-4">
                <div>
                    <h2 class="text-lg font-semibold">{{ localize('Language Activation', 'تفعيل اللغات', 'زبانوں کی فعال کاری') }}</h2>
                </div>

                <div class="flex flex-wrap items-center gap-6 rounded-md border p-3">
                    <label v-for="locale in supported_locales" :key="locale.code" class="flex items-center gap-2 text-sm">
                        <input v-model="form.enabled_locales" type="checkbox" :value="locale.code" />
                        {{ locale.native }} ({{ locale.code.toUpperCase() }})
                    </label>
                </div>
                <p class="text-xs text-muted-foreground">{{ localize('At least one language must stay enabled.', 'يجب إبقاء لغة واحدة على الأقل مفعلة.', 'کم از کم ایک زبان فعال رہنی چاہیے.') }}</p>
                <p v-if="form.errors['enabled_locales']" class="text-sm text-red-600">{{ form.errors['enabled_locales'] }}</p>
                <p v-if="form.errors['enabled_locales.0']" class="text-sm text-red-600">{{ form.errors['enabled_locales.0'] }}</p>
            </section>

            <section class="rounded-lg border p-5 space-y-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('Translations Table', 'جدول الترجمات', 'ترجمے کی جدول') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ localize('Edit only the words you need. Empty field uses default system translation.', 'عدّل الكلمات التي تحتاجها فقط. الحقل الفارغ يستخدم ترجمة النظام الافتراضية.', 'صرف ان الفاظ کی ترمیم کریں جن کی ضرورت ہو۔ خالی فیلڈ سسٹم کی ڈیفالٹ ترجمہ استعمال کرتا ہے.') }}</p>
                    </div>
                    <div class="w-full space-y-3 md:w-auto">
                        <div class="w-full md:w-80">
                            <Label class="sr-only" for="translation_search">{{ localize('Search', 'بحث', 'تلاش') }}</Label>
                            <Input id="translation_search" v-model="search" :placeholder="localize('Search key or value...', 'ابحث بالمفتاح أو القيمة...', 'کلید یا قدر تلاش کریں...')" />
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select
                                v-model="focusedLocale"
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm dark:bg-input/30"
                            >
                                <option v-for="locale in localeCodes" :key="`focus-${locale}`" :value="locale">
                                    {{ localize('Focus', 'التركيز', 'فوکس') }}: {{ locale.toUpperCase() }}
                                </option>
                            </select>
                            <label class="inline-flex items-center gap-2 rounded-md border px-2 py-1 text-xs">
                                <input v-model="onlyCustomized" type="checkbox" />
                                {{ localize('Only customized', 'المعدلة فقط', 'صرف حسبِ تخصیص') }}
                            </label>
                            <label class="inline-flex items-center gap-2 rounded-md border px-2 py-1 text-xs">
                                <input v-model="onlyEmptyForFocusedLocale" type="checkbox" />
                                {{ localize('Only empty in focus locale', 'فقط الفارغة في لغة التركيز', 'صرف فوکس زبان میں خالی') }}
                            </label>
                            <Button type="button" variant="outline" size="sm" @click="fillEmptyFromDefaultsForFocusedLocale">
                                {{ localize('Fill Empty From Default', 'املأ الفارغ من الافتراضي', 'خالی کو ڈیفالٹ سے پُر کریں') }}
                            </Button>
                            <Button type="button" variant="outline" size="sm" @click="clearFocusedLocaleValues">
                                {{ localize('Clear Focus Locale', 'مسح لغة التركيز', 'فوکس زبان صاف کریں') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-md border">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="px-3 py-2 font-semibold">{{ localize('Key', 'المفتاح', 'کلید') }}</th>
                                <template v-for="localeCode in localeCodes" :key="`h-${localeCode}`">
                                    <th class="px-3 py-2 font-semibold">
                                        {{ localize('Default', 'الافتراضي', 'ڈیفالٹ') }} {{ localeMetaByCode[localeCode]?.code?.toUpperCase() || localeCode.toUpperCase() }}
                                    </th>
                                    <th class="px-3 py-2 font-semibold">
                                        {{ localize('Edit', 'تحرير', 'ترمیم') }} {{ localeMetaByCode[localeCode]?.code?.toUpperCase() || localeCode.toUpperCase() }}
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in filteredRows" :key="row.key" class="border-t align-top">
                                <td class="px-3 py-2">
                                    <div class="font-mono text-xs">{{ row.key }}</div>
                                </td>
                                <template v-for="localeCode in localeCodes" :key="`${row.key}-${localeCode}`">
                                    <td class="px-3 py-2">
                                        <div
                                            class="max-w-[260px] whitespace-pre-wrap text-xs text-muted-foreground"
                                            :dir="localeCode === 'ar' ? 'rtl' : 'ltr'"
                                        >
                                            {{ row.defaults?.[localeCode] || '' }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="space-y-2">
                                            <Input
                                                v-model="row.formRow.values[localeCode]"
                                                :placeholder="localize('Use default', 'استخدم الافتراضي', 'ڈیفالٹ استعمال کریں')"
                                                :dir="localeCode === 'ar' ? 'rtl' : 'ltr'"
                                            />
                                            <div class="flex gap-2">
                                                <button
                                                    type="button"
                                                    class="text-xs text-primary hover:underline"
                                                    @click="copyDefaultToLocale(row, localeCode)"
                                                >
                                                    {{ localize('Copy default', 'نسخ الافتراضي', 'ڈیفالٹ کاپی کریں') }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="text-xs text-muted-foreground hover:underline"
                                                    @click="clearLocaleValue(row, localeCode)"
                                                >
                                                    {{ localize('Clear', 'مسح', 'صاف کریں') }}
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </template>
                            </tr>
                            <tr v-if="filteredRows.length === 0">
                                <td class="px-3 py-5 text-center text-muted-foreground" :colspan="1 + (localeCodes.length * 2)">
                                    {{ localize('No translation rows found for this search.', 'لم يتم العثور على صفوف ترجمة لهذا البحث.', 'اس تلاش کے لیے کوئی ترجمہ قطار نہیں ملی۔') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AdminLayout>
</template>
