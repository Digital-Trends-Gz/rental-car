<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTrans } from '@/composables/useTrans';

type PlateFormatSetting = {
    code: string;
    name: string;
    country: string;
    mask: string;
    example: string;
    is_active: boolean;
};

type PlateFormatRow = PlateFormatSetting & {
    key: string;
};

const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
    };
    settings: PlateFormatSetting[];
    actions: {
        update: string;
    };
}>();

const { locale, t } = useTrans();
const translationRoot = 'dashboard.admin.settings.plate_formats';
const translationKeyFor = (value: string) =>
    value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 90);
const localize = (en: string, ar: string) => {
    const key = `${translationRoot}.${translationKeyFor(en)}`;
    const translated = t(key);

    if (translated !== key) {
        return translated;
    }

    return locale.value === 'ar' ? ar : en;
};

function createPlateFormatRow(format: Partial<PlateFormatSetting> = {}, index = 0): PlateFormatRow {
    const code = String(format.code ?? '');

    return {
        key: code ? `plate-format-${code}` : `plate-format-${index}-${Math.random().toString(36).slice(2, 8)}`,
        code,
        name: String(format.name ?? ''),
        country: String(format.country ?? ''),
        mask: String(format.mask ?? ''),
        example: String(format.example ?? ''),
        is_active: format.is_active ?? true,
    };
}

const form = useForm({
    settings: {
        plate_formats: props.settings.length
            ? props.settings.map((format, index) => createPlateFormatRow(format, index))
            : [createPlateFormatRow()],
    },
});

const pageTitle = computed(() => localize('Plate Formats', 'أنماط اللوحات'));
const pageDescription = computed(() =>
    localize(
        'Define the plate formats that this tenant can use when creating vehicles.',
        'عرّف أنماط لوحات السيارات التي يمكن لهذا المستأجر استخدامها عند إنشاء السيارة.',
    ),
);

function addFormat() {
    form.settings.plate_formats.push(createPlateFormatRow({}, form.settings.plate_formats.length));
}

function removeFormat(index: number) {
    form.settings.plate_formats.splice(index, 1);
    if (form.settings.plate_formats.length === 0) {
        addFormat();
    }
}

function submit() {
    form.put(props.actions.update, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ pageTitle }}</h1>
                    <p class="text-sm text-muted-foreground">{{ pageDescription }}</p>
                </div>

                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                </Button>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between gap-4">
                        <div>
                            <CardTitle>{{ localize('Plate format rules', 'قواعد أنماط اللوحات') }}</CardTitle>
                            <CardDescription>
                                {{ localize('Use # for digits, A for letters, and X for alphanumeric characters. Spaces are allowed inside the mask.', 'استخدم # للأرقام، و A للحروف، و X للحروف والأرقام. يمكن استخدام المسافات داخل النمط.') }}
                            </CardDescription>
                        </div>
                        <Button type="button" variant="outline" @click="addFormat">
                            {{ localize('Add Format', 'إضافة نمط') }}
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="form.settings.plate_formats.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                            {{ localize('No plate formats added yet.', 'لم يتم إضافة أي أنماط لوحات بعد.') }}
                        </div>

                        <div
                            v-for="(format, index) in form.settings.plate_formats"
                            :key="format.key"
                            class="rounded-lg border p-4"
                        >
                            <div class="flex items-center justify-between gap-4">
                                <div class="font-medium">{{ localize('Format', 'النمط') }} #{{ index + 1 }}</div>
                                <Button type="button" variant="ghost" class="text-destructive hover:text-destructive" @click="removeFormat(index)">
                                    {{ localize('Remove', 'حذف') }}
                                </Button>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                <div class="space-y-2">
                                    <Label :for="`plate-format-name-${index}`">{{ localize('Name', 'الاسم') }}</Label>
                                    <Input :id="`plate-format-name-${index}`" v-model="format.name" :placeholder="localize('Oman 5-digit standard', 'النمط العماني القياسي')" />
                                    <InputError :message="form.errors[`settings.plate_formats.${index}.name`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`plate-format-country-${index}`">{{ localize('Country', 'الدولة') }}</Label>
                                    <Input :id="`plate-format-country-${index}`" v-model="format.country" :placeholder="localize('Oman', 'عمان')" />
                                    <InputError :message="form.errors[`settings.plate_formats.${index}.country`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`plate-format-mask-${index}`">{{ localize('Mask', 'النمط') }}</Label>
                                    <Input :id="`plate-format-mask-${index}`" v-model="format.mask" :placeholder="localize('12345 A', '12345 A')" />
                                    <InputError :message="form.errors[`settings.plate_formats.${index}.mask`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`plate-format-example-${index}`">{{ localize('Example', 'مثال') }}</Label>
                                    <Input :id="`plate-format-example-${index}`" v-model="format.example" :placeholder="localize('12345 AB', '12345 AB')" />
                                    <InputError :message="form.errors[`settings.plate_formats.${index}.example`]" />
                                </div>

                                <div class="flex items-center gap-3 md:col-span-2 lg:col-span-1">
                                    <input :id="`plate-format-active-${index}`" v-model="format.is_active" type="checkbox" class="h-4 w-4 rounded border-input" />
                                    <Label :for="`plate-format-active-${index}`">{{ localize('Active', 'مفعّل') }}</Label>
                                </div>
                            </div>

                            <div class="mt-4 rounded-md bg-muted/40 p-3 text-xs text-muted-foreground">
                                <div>{{ localize('Current code', 'الرمز الحالي') }}: <span class="font-medium text-foreground">{{ format.code || localize('Will be generated automatically', 'سيتم إنشاؤه تلقائيًا') }}</span></div>
                                <div class="mt-1">{{ localize('Example mask patterns: 12345 A, 12345 AB, 12XX 345', 'أمثلة على الأنماط: 12345 A، 12345 AB، 12XX 345') }}</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end">
                    <Button :disabled="form.processing" type="submit">
                        {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                    </Button>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
