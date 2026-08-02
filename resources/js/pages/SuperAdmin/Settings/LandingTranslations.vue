<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type LocaleMeta = {
    code: string;
    name: string;
    native: string;
};

type TranslationRow = {
    key: string;
    default: string;
    values: Record<string, string>;
};

type RowForm = {
    key: string;
    values: Record<string, string>;
};

type Props = {
    settings: Record<string, unknown>;
    supported_locales: LocaleMeta[];
    enabled_locales: string[];
    rows: TranslationRow[];
    sections: string[];
    filters: {
        search: string;
        section: string;
        per_page: number;
    };
    pagination: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
        from: number | null;
        to: number | null;
    };
    actions: {
        update: string;
        auto_translate: string;
    };
};

const props = defineProps<Props>();

const page = usePage<any>();
const search = ref(props.filters?.search || '');
const sectionFilter = ref(props.filters?.section || 'all');
const perPage = ref(Number(props.filters?.per_page || props.pagination?.per_page || 50));
const focusedLocale = ref(props.supported_locales.some((locale) => locale.code === 'ar')
    ? 'ar'
    : props.supported_locales[0]?.code || 'en');
const autoTranslating = ref(false);
const autoTranslateMessage = ref('');
const autoTranslateError = ref('');

const form = useForm<{
    enabled_locales: string[];
    rows: RowForm[];
}>({
    enabled_locales: Array.isArray(props.enabled_locales) && props.enabled_locales.length > 0
        ? [...props.enabled_locales]
        : props.supported_locales.map((locale) => locale.code),
    rows: props.rows.map((row) => ({
        key: row.key,
        values: { ...row.values },
    })),
});

const localeCodes = computed(() => props.supported_locales.map((item) => item.code));
const localeMetaByCode = computed<Record<string, LocaleMeta>>(() =>
    props.supported_locales.reduce<Record<string, LocaleMeta>>((acc, item) => {
        acc[item.code] = item;
        return acc;
    }, {}),
);

const rowsWithForm = computed(() =>
    props.rows.map((row, index) => ({
        ...row,
        section: row.key.split('.')[0] || 'other',
        formRow: form.rows[index] as RowForm,
    })),
);

const sectionOptions = computed(() => {
    return (props.sections || []).map((section) => ({
        value: section,
        label: section
            .split('_')
            .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
            .join(' '),
    }));
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const formErrors = computed(() =>
    Object.values(form.errors ?? {}).filter((value): value is string => typeof value === 'string' && value.length > 0),
);

let searchTimer: ReturnType<typeof setTimeout> | null = null;

function syncFormRows(): void {
    form.rows = props.rows.map((row) => ({
        key: row.key,
        values: { ...row.values },
    }));
}

function reloadRows(pageNumber = 1): void {
    router.get(window.location.pathname, {
        search: search.value.trim() || undefined,
        section: sectionFilter.value === 'all' ? undefined : sectionFilter.value,
        per_page: perPage.value,
        page: pageNumber,
    }, {
        preserveScroll: true,
        preserveState: false,
        replace: true,
    });
}

function queueReload(): void {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => reloadRows(1), 350);
}

function goToPage(pageNumber: number): void {
    const nextPage = Math.min(Math.max(1, pageNumber), props.pagination.last_page || 1);
    if (nextPage === props.pagination.current_page) {
        return;
    }

    reloadRows(nextPage);
}

watch(() => props.rows, syncFormRows);
watch(search, queueReload);
watch(sectionFilter, () => reloadRows(1));
watch(perPage, () => reloadRows(1));

function isEmpty(value: unknown): boolean {
    return String(value ?? '').trim() === '';
}

function copyDefaultToLocale(row: (typeof rowsWithForm.value)[number], localeCode: string): void {
    row.formRow.values[localeCode] = String(row.default || '');
}

function clearLocaleValue(row: (typeof rowsWithForm.value)[number], localeCode: string): void {
    row.formRow.values[localeCode] = '';
}

function fillMissingForFocusedLocale(): void {
    const localeCode = focusedLocale.value;
    if (!localeCode) {
        return;
    }

    rowsWithForm.value.forEach((row) => {
        if (isEmpty(row.formRow.values[localeCode])) {
            row.formRow.values[localeCode] = String(row.default || '');
        }
    });
}

function clearFocusedLocale(): void {
    const localeCode = focusedLocale.value;
    if (!localeCode) {
        return;
    }

    rowsWithForm.value.forEach((row) => {
        row.formRow.values[localeCode] = '';
    });
}

function submit(): void {
    if (!Array.isArray(form.enabled_locales) || form.enabled_locales.length === 0) {
        form.enabled_locales = [...localeCodes.value];
    }

    form.put(props.actions.update, {
        preserveScroll: true,
    });
}

async function autoFillArabic(): Promise<void> {
    if (!localeCodes.value.includes('ar')) {
        autoTranslateError.value = 'Arabic is not enabled for this landing page.';
        return;
    }

    autoTranslating.value = true;
    autoTranslateMessage.value = '';
    autoTranslateError.value = '';

    try {
        const response = await fetch(props.actions.auto_translate, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': String(page.props.csrf_token ?? ''),
            },
            body: JSON.stringify({
                target_locale: 'ar',
            }),
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload?.ok) {
            throw new Error(payload?.message || 'Unable to auto-fill Arabic translations.');
        }

        const translations = payload.translations ?? {};
        let updated = 0;

        form.rows.forEach((row) => {
            const translatedValue = String(translations[row.key] ?? '').trim();
            if (translatedValue === '' || !isEmpty(row.values.ar)) {
                return;
            }

            row.values.ar = translatedValue;
            updated += 1;
        });

        autoTranslateMessage.value = updated > 0
            ? `Filled ${updated} Arabic translation${updated === 1 ? '' : 's'}.`
            : 'No empty Arabic fields were found.';
    } catch (error) {
        autoTranslateError.value = error instanceof Error
            ? error.message
            : 'Unable to auto-fill Arabic translations.';
    } finally {
        autoTranslating.value = false;
    }
}
</script>

<template>
    <Head title="Landing Translations" />

    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Landing Translations</h1>
                    <p class="text-sm text-muted-foreground">
                        Edit landing page text per language. Empty values fall back to the main landing content.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="autoTranslating"
                        @click="autoFillArabic"
                    >
                        {{ autoTranslating ? 'Auto-filling Arabic...' : 'Auto-fill Arabic' }}
                    </Button>
                    <Button type="button" variant="outline" @click="fillMissingForFocusedLocale">
                        Fill Missing for Focused Locale
                    </Button>
                    <Button type="button" variant="outline" @click="clearFocusedLocale">
                        Clear Focused Locale
                    </Button>
                    <Button :disabled="form.processing" @click="submit">
                        {{ form.processing ? 'Saving...' : 'Save Translations' }}
                    </Button>
                </div>
            </div>

            <div v-if="flashSuccess" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ flashError }}
            </div>
            <div v-if="formErrors.length" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <div class="font-medium">Please fix the following errors:</div>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="(message, index) in formErrors" :key="index">{{ message }}</li>
                </ul>
            </div>
            <div v-if="autoTranslateMessage" class="rounded-md border border-sky-200 bg-sky-50 p-3 text-sm text-sky-700">
                {{ autoTranslateMessage }}
            </div>
            <div v-if="autoTranslateError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ autoTranslateError }}
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Language Activation</CardTitle>
                    <CardDescription>
                        Enable the languages that should appear in the landing page language switcher.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap items-center gap-4 rounded-md border p-4">
                        <label
                            v-for="locale in supported_locales"
                            :key="locale.code"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input v-model="form.enabled_locales" type="checkbox" :value="locale.code" />
                            <span>{{ locale.native }} ({{ locale.code.toUpperCase() }})</span>
                        </label>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Keep at least one language enabled.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <CardTitle>Translations Table</CardTitle>
                            <CardDescription>
                                Search by key or value, then edit only the locales you need.
                            </CardDescription>
                        </div>
                        <div class="w-full max-w-md">
                            <Label for="landing-translation-search" class="sr-only">Search</Label>
                            <Input
                                id="landing-translation-search"
                                v-model="search"
                                placeholder="Search key or translation..."
                            />
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <select
                            v-model="sectionFilter"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option value="all">All sections</option>
                            <option
                                v-for="section in sectionOptions"
                                :key="section.value"
                                :value="section.value"
                            >
                                {{ section.label }}
                            </option>
                        </select>
                        <select
                            v-model.number="perPage"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option :value="25">25 per page</option>
                            <option :value="50">50 per page</option>
                            <option :value="100">100 per page</option>
                            <option :value="200">200 per page</option>
                        </select>
                        <select
                            v-model="focusedLocale"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option
                                v-for="locale in localeCodes"
                                :key="`focus-${locale}`"
                                :value="locale"
                            >
                                Focus: {{ localeMetaByCode[locale]?.code?.toUpperCase() || locale.toUpperCase() }}
                            </option>
                        </select>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="max-h-[65vh] w-full overflow-auto rounded-md border">
                        <table class="w-max min-w-[1280px] text-sm">
                            <thead class="sticky top-0 z-10 bg-muted text-left shadow-sm">
                                <tr>
                                    <th class="min-w-[320px] bg-muted px-3 py-2 font-semibold">Key</th>
                                    <th class="min-w-[320px] bg-muted px-3 py-2 font-semibold">Default</th>
                                    <template v-for="localeCode in localeCodes" :key="`h-${localeCode}`">
                                        <th class="min-w-[340px] bg-muted px-3 py-2 font-semibold">
                                            Edit {{ localeMetaByCode[localeCode]?.code?.toUpperCase() || localeCode.toUpperCase() }}
                                        </th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in rowsWithForm" :key="row.key" class="border-t align-top">
                                    <td class="min-w-[320px] max-w-[420px] px-3 py-2">
                                        <div class="overflow-x-auto whitespace-nowrap font-mono text-xs">
                                            {{ row.key }}
                                        </div>
                                    </td>
                                    <td class="min-w-[320px] max-w-[360px] px-3 py-2">
                                        <div class="max-w-[280px] whitespace-pre-wrap text-xs text-muted-foreground">
                                            {{ row.default }}
                                        </div>
                                    </td>
                                    <template v-for="localeCode in localeCodes" :key="`${row.key}-${localeCode}`">
                                        <td class="min-w-[340px] px-3 py-2">
                                            <div class="space-y-2">
                                                <Textarea
                                                    v-model="row.formRow.values[localeCode]"
                                                    :placeholder="`Use default ${localeCode.toUpperCase()}`"
                                                    :dir="localeCode === 'ar' ? 'rtl' : 'ltr'"
                                                    rows="3"
                                                />
                                                <div class="flex gap-2">
                                                    <button
                                                        type="button"
                                                        class="text-xs text-primary hover:underline"
                                                        @click="copyDefaultToLocale(row, localeCode)"
                                                    >
                                                        Copy default
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="text-xs text-muted-foreground hover:underline"
                                                        @click="clearLocaleValue(row, localeCode)"
                                                    >
                                                        Clear
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </template>
                                </tr>
                                <tr v-if="rowsWithForm.length === 0">
                                    <td class="px-3 py-5 text-center text-muted-foreground" :colspan="2 + localeCodes.length">
                                        No translation rows match your search.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            Showing
                            <span class="font-medium text-foreground">{{ pagination.from ?? 0 }}</span>
                            -
                            <span class="font-medium text-foreground">{{ pagination.to ?? 0 }}</span>
                            of
                            <span class="font-medium text-foreground">{{ pagination.total }}</span>
                            translations
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="pagination.current_page <= 1"
                                @click="goToPage(pagination.current_page - 1)"
                            >
                                Previous
                            </Button>
                            <span class="min-w-24 text-center">
                                Page {{ pagination.current_page }} of {{ pagination.last_page }}
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="pagination.current_page >= pagination.last_page"
                                @click="goToPage(pagination.current_page + 1)"
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </main>
    </SuperAdminLayout>
</template>
