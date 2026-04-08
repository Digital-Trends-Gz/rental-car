<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { index, store, update } from '@/routes/admin/cars';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Car {
    id: number;
    make: string;
    model: string;
    year: number | string;
    license_plate: string;
    branch_id: number | string;
    color: string;
    price_per_day: number | string;
    mileage: number | string;
    transmission: string;
    seats: number | string;
    fuel_type: string;
    description: string;
    status: string;
}

interface ImageFile {
    id: number;
    url: string;
}

interface AdditionalPhotoFileGroup {
    type: string;
    files: ImageFile[];
}

interface AdditionalPhotoRow {
    key: string;
    type: string;
    original_type: string;
    temp_folders: string[];
    existing_files: ImageFile[];
    removed_file_ids: number[];
}

interface Branch {
    id: number;
    name: string;
}

interface CatalogOption {
    value: string;
    label: string;
}

interface ModelOption extends CatalogOption {
    years: CatalogOption[];
}

interface MakeOption extends CatalogOption {
    models: ModelOption[];
}

interface ColorEnum {
    name: string;
    value: string;
    hex: string;
}

interface StatusEnum {
    value: string;
    label: string;
    color: string;
}

interface Enums {
    colors: ColorEnum[];
    fuelTypes: string[];
    statuses: StatusEnum[];
}

const props = defineProps<{
    car: Car | null;
    imageFiles: ImageFile[];
    additionalPhotoFiles: AdditionalPhotoFileGroup[];
    catalog: {
        years: CatalogOption[];
        makes: MakeOption[];
    };
    branches: Branch[];
    canAccessAllBranches: boolean;
    enums: Enums;
}>();

const page = usePage<any>();
const subdomain = computed<string | undefined>(() => page.props.current_tenant?.slug);
const isEdit = computed(() => !!props.car);
const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const carColors = computed(() =>
    props.enums.colors.map((color) => ({
        ...color,
        value: color.value.toLowerCase(),
        name: color.name.charAt(0).toUpperCase() + color.name.slice(1),
    })),
);

const fuelTypes = computed(() =>
    props.enums.fuelTypes.map((fuel) => ({
        value: fuel.toLowerCase(),
        label: fuel.charAt(0).toUpperCase() + fuel.slice(1),
    })),
);

const statuses = computed(() => props.enums.statuses);

const makeOptions = computed<MakeOption[]>(() => {
    const options = [...props.catalog.makes];
    const currentMake = safeStr(form.make).trim();

    if (currentMake && !options.some((option) => option.value === currentMake)) {
        options.unshift({
            value: currentMake,
            label: currentMake,
            models: [],
        });
    }

    return options;
});

const yearOptions = computed<CatalogOption[]>(() => {
    const selectedMake = makeOptions.value.find((option) => option.value === form.make);
    const selectedModel = selectedMake?.models.find((option) => option.value === form.model);
    const options = [...(selectedModel?.years?.length ? selectedModel.years : props.catalog.years)];
    const currentYear = safeStr(form.year).trim();

    if (currentYear && !options.some((option) => option.value === currentYear)) {
        options.unshift({
            value: currentYear,
            label: currentYear,
        });
    }

    return options;
});

const modelOptions = computed<CatalogOption[]>(() => {
    const selectedMake = makeOptions.value.find((option) => option.value === form.make);
    const options = [...(selectedMake?.models ?? [])];
    const currentModel = safeStr(form.model).trim();

    if (currentModel && !options.some((option) => option.value === currentModel)) {
        options.unshift({
            value: currentModel,
            label: currentModel,
        });
    }

    return options;
});

function safeStr(value: unknown, fallback = ''): string {
    if (value === null || value === undefined) return fallback;
    return String(value);
}

function safeNum(value: unknown, fallback = ''): string {
    if (value === null || value === undefined) return fallback;
    return String(value);
}

function safeLower(value: unknown, fallback: string): string {
    if (value === null || value === undefined || value === '') return fallback;
    return String(value).toLowerCase();
}

function createAdditionalPhotoRow(type = '', files: ImageFile[] = []): AdditionalPhotoRow {
    return {
        key: `${type || 'new'}-${Math.random().toString(36).slice(2, 10)}`,
        type,
        original_type: type,
        temp_folders: [],
        existing_files: files,
        removed_file_ids: [],
    };
}

const additionalPhotoTypeOptions = computed(() => [
    { value: 'right', label: localize('Right', 'ط§ظ„ظٹظ…ظٹظ†') },
    { value: 'left', label: localize('Left', 'ط§ظ„ظٹط³ط§ط±') },
    { value: 'front', label: localize('Front', 'ط§ظ„ط£ظ…ط§ظ…') },
    { value: 'rear', label: localize('Rear', 'ط§ظ„ط®ظ„ظپ') },
    { value: 'inside', label: localize('Inside', 'ط§ظ„ط¯ط§ط®ظ„') },
]);

const form = useForm({
    make: safeStr(props.car?.make),
    model: safeStr(props.car?.model),
    year: safeNum(props.car?.year),
    license_plate: safeStr(props.car?.license_plate),
    branch_id: safeStr(props.car?.branch_id),
    color: safeLower(props.car?.color, 'white'),
    price_per_day: safeNum(props.car?.price_per_day),
    mileage: safeNum(props.car?.mileage),
    transmission: safeStr(props.car?.transmission, 'automatic'),
    seats: safeNum(props.car?.seats),
    fuel_type: safeLower(props.car?.fuel_type, 'gasoline'),
    description: safeStr(props.car?.description),
    status: safeStr(props.car?.status, 'available'),
    image: [] as string[],
    image_temp_folders: [] as string[],
    image_removed_files: [] as number[],
    additional_photos: props.additionalPhotoFiles.map((item) => createAdditionalPhotoRow(item.type, item.files)),
    deleted_additional_photo_types: [] as string[],
});

watch(
    () => form.make,
    (nextMake, previousMake) => {
        if (nextMake === previousMake) {
            return;
        }

        const validModelValues = new Set(
            (makeOptions.value.find((option) => option.value === nextMake)?.models ?? []).map((option) => option.value),
        );

        if (form.model && validModelValues.size > 0 && !validModelValues.has(form.model)) {
            form.model = '';
        }

        const selectedModel = makeOptions.value
            .find((option) => option.value === nextMake)
            ?.models.find((option) => option.value === form.model);
        const validYearValues = new Set((selectedModel?.years?.length ? selectedModel.years : props.catalog.years).map((option) => option.value));

        if (form.year && validYearValues.size > 0 && !validYearValues.has(form.year)) {
            form.year = '';
        }
    },
);

watch(
    () => form.model,
    (nextModel, previousModel) => {
        if (nextModel === previousModel) {
            return;
        }

        const validYearValues = new Set(yearOptions.value.map((option) => option.value));

        if (form.year && validYearValues.size > 0 && !validYearValues.has(form.year)) {
            form.year = '';
        }
    },
);

watch(
    () => props.car,
    (car) => {
        if (!car) {
            return;
        }

        if (!form.make) form.make = safeStr(car.make);
        if (!form.model) form.model = safeStr(car.model);
        if (!form.year) form.year = safeNum(car.year);
    },
    { immediate: true },
);

const fileUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const tempFolders = ref<string[]>([]);
const removedFileIds = ref<number[]>([]);

watch(
    tempFolders,
    (value) => {
        form.image_temp_folders = [...value];
    },
    { deep: true },
);

function handleFileRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId !== undefined) {
        removedFileIds.value.push(data.fileId);
        form.image_removed_files = [...removedFileIds.value];
    }
}

function availablePhotoTypes(currentType = '') {
    const usedTypes = new Set(
        form.additional_photos
            .map((item) => item.type)
            .filter((type) => type && type !== currentType),
    );

    return additionalPhotoTypeOptions.value.filter((option) => !usedTypes.has(option.value) || option.value === currentType);
}

function addAdditionalPhoto() {
    if (form.additional_photos.length >= additionalPhotoTypeOptions.value.length) {
        return;
    }

    form.additional_photos.push(createAdditionalPhotoRow());
}

function removeAdditionalPhoto(index: number) {
    const item = form.additional_photos[index];

    if (!item) {
        return;
    }

    const removedType = item.original_type || item.type;

    if (removedType && !form.deleted_additional_photo_types.includes(removedType)) {
        form.deleted_additional_photo_types.push(removedType);
    }

    form.additional_photos.splice(index, 1);
}

function onAdditionalPhotoTypeChange(index: number, newType: string) {
    const item = form.additional_photos[index];

    if (!item) {
        return;
    }

    if (item.original_type && item.original_type !== newType && !form.deleted_additional_photo_types.includes(item.original_type)) {
        form.deleted_additional_photo_types.push(item.original_type);
        item.existing_files = [];
        item.removed_file_ids = [];
        item.temp_folders = [];
    }

    item.type = newType;
}

function handleAdditionalPhotoFileRemoved(index: number, data: { type: string; fileId?: number }) {
    const item = form.additional_photos[index];

    if (!item) {
        return;
    }

    if (data.type === 'existing' && data.fileId !== undefined && !item.removed_file_ids.includes(data.fileId)) {
        item.removed_file_ids.push(data.fileId);
    }
}

function isMultiPhotoType(type: string): boolean {
    return type === 'inside';
}

function additionalPhotoMaxFiles(type: string): number {
    return isMultiPhotoType(type) ? 10 : 1;
}

function submit() {
    if (!subdomain.value) {
        console.warn('No subdomain found; aborting submit.');
        return;
    }

    if (isEdit.value) {
        if (!props.car?.id) {
            console.warn('Edit mode but car.id is missing; aborting submit.');
            return;
        }

        form.additional_photos = form.additional_photos.filter((item) => item.type || item.temp_folders.length || item.existing_files.length || item.removed_file_ids.length);
        form.put(update([subdomain.value, props.car.id]).url);
    } else {
        form.image = [...tempFolders.value];
        form.additional_photos = form.additional_photos.filter((item) => item.type || item.temp_folders.length);
        form.post(store(subdomain.value).url, {
            onSuccess: () => {
                form.reset();
                tempFolders.value = [];
                fileUploadRef.value?.resetFiles();
                form.additional_photos = [];
                form.deleted_additional_photo_types = [];
            },
        });
    }
}

const submitLabel = computed(() => {
    if (form.processing) return isEdit.value ? localize('Saving...', 'ط¬ط§ط±ظچ ط§ظ„ط­ظپط¸...') : localize('Creating...', 'ط¬ط§ط±ظچ ط§ظ„ط¥ظ†ط´ط§ط،...');
    return isEdit.value ? localize('Save Changes', 'ط­ظپط¸ ط§ظ„طھط؛ظٹظٹط±ط§طھ') : localize('Create Car', 'ط¥ظ†ط´ط§ط، ط³ظٹط§ط±ط©');
});

const pageTitle = computed(() => (isEdit.value ? localize('Edit Car', 'طھط¹ط¯ظٹظ„ ط§ظ„ط³ظٹط§ط±ط©') : localize('Create Car', 'ط¥ظ†ط´ط§ط، ط³ظٹط§ط±ط©')));
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ pageTitle }}</h1>
                <Link v-if="subdomain" :href="index(subdomain).url">
                    <Button variant="outline">{{ localize('Back', 'ط±ط¬ظˆط¹') }}</Button>
                </Link>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <div class="flex flex-col gap-6 md:flex-row md:gap-8">
                    <div class="w-full md:w-1/2">
                        <Label>{{ localize('Main Cover Image', 'ط§ظ„طµظˆط±ط© ط§ظ„ط±ط¦ظٹط³ظٹط©') }}</Label>
                        <div class="mt-2">
                            <FileUpload
                                ref="fileUploadRef"
                                v-model="tempFolders"
                                :initial-files="imageFiles ?? []"
                                :allow-multiple="false"
                                :max-files="1"
                                collection="image"
                                theme="light"
                                width="100%"
                                @file-removed="handleFileRemoved"
                            />
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ localize('Upload one main image for cards and list pages.', 'ط§ط±ظپط¹ طµظˆط±ط© ط±ط¦ظٹط³ظٹط© ظˆط§ط­ط¯ط© ظ„ط¨ط·ط§ظ‚ط§طھ ط§ظ„ط³ظٹط§ط±ط© ظˆطµظپط­ط§طھ ط§ظ„ظ‚ظˆط§ط¦ظ….') }}
                            </p>
                        </div>
                    </div>

                    <div class="w-full space-y-4 py-0 md:w-1/2 md:py-6">
                        <div>
                            <Label for="status">{{ localize('Status', 'ط§ظ„ط­ط§ظ„ط©') }}</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="mt-1 block w-full rounded-md border border-gray-300 py-2 pr-10 pl-3 text-base focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm"
                            >
                                <option v-for="s in statuses" :key="s.value" :value="s.value">
                                    {{ s.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.status" class="mt-1" />
                        </div>

                        <div>
                            <Label for="price_per_day">{{ localize('Price Per Day', 'ط§ظ„ط³ط¹ط± ظ„ظƒظ„ ظٹظˆظ…') }}</Label>
                            <Input id="price_per_day" v-model="form.price_per_day" type="number" step="0.01" min="0" :placeholder="localize('e.g., 50.00', 'ظ…ط«ط§ظ„: 50.00')" />
                            <InputError :message="form.errors.price_per_day" class="mt-1" />
                        </div>

                        <div>
                            <Label class="mb-2 block">{{ localize('Color', 'ط§ظ„ظ„ظˆظ†') }}</Label>
                            <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                                <div v-for="color in carColors" :key="color.value" class="flex items-center">
                                    <input
                                        :id="'color-' + color.value"
                                        v-model="form.color"
                                        type="radio"
                                        :value="color.value"
                                        class="peer sr-only"
                                    />
                                    <label
                                        :for="'color-' + color.value"
                                        class="flex w-full cursor-pointer items-center justify-between rounded-md border p-2 text-sm font-medium hover:bg-gray-50 peer-checked:border-blue-500 peer-checked:ring-1 peer-checked:ring-blue-500 dark:hover:bg-gray-800"
                                        :title="color.name"
                                    >
                                        <span>{{ color.name }}</span>
                                        <span class="inline-block h-4 w-4 rounded-full border border-gray-300" :style="{ backgroundColor: color.hex }" />
                                    </label>
                                </div>
                            </div>
                            <InputError :message="form.errors.color" class="mt-1" />
                        </div>
                    </div>
                </div>

                <section class="space-y-4 rounded-xl border border-border/70 bg-card p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">{{ localize('Another Photos', 'طµظˆط± ط¥ط¶ط§ظپظٹط©') }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ localize('Add one typed photo for each side such as right, left, front, rear, or inside.', 'ط£ط¶ظپ طµظˆط±ط© ظˆط§ط­ط¯ط© ظ…ط­ط¯ط¯ط© ط§ظ„ظ†ظˆط¹ ظ„ظƒظ„ ط¬ظ‡ط© ظ…ط«ظ„ ط§ظ„ظٹظ…ظٹظ† ط£ظˆ ط§ظ„ظٹط³ط§ط± ط£ظˆ ط§ظ„ط£ظ…ط§ظ… ط£ظˆ ط§ظ„ط®ظ„ظپ ط£ظˆ ط§ظ„ط¯ط§ط®ظ„.') }}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form.additional_photos.length >= additionalPhotoTypeOptions.length"
                            @click="addAdditionalPhoto"
                        >
                            {{ localize('Add Photo Type', 'ط¥ط¶ط§ظپط© ظ†ظˆط¹ طµظˆط±ط©') }}
                        </Button>
                    </div>

                    <div v-if="form.additional_photos.length === 0" class="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                        {{ localize('No additional typed photos added yet.', 'ظ„ط§ طھظˆط¬ط¯ طµظˆط± ط¥ط¶ط§ظپظٹط© ظ…ط­ط¯ط¯ط© ط§ظ„ظ†ظˆط¹ ط­طھظ‰ ط§ظ„ط¢ظ†.') }}
                    </div>

                    <div
                        v-for="(item, index) in form.additional_photos"
                        :key="item.key"
                        class="grid gap-4 rounded-lg border border-border/60 p-4 md:grid-cols-[220px,1fr,auto]"
                    >
                        <div class="space-y-2">
                            <Label :for="`additional-photo-type-${index}`">{{ localize('Photo Type', 'ظ†ظˆط¹ ط§ظ„طµظˆط±ط©') }}</Label>
                            <select
                                :id="`additional-photo-type-${index}`"
                                :value="item.type"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                                :disabled="Boolean(item.original_type && item.existing_files.length)"
                                @change="onAdditionalPhotoTypeChange(index, String(($event.target as HTMLSelectElement).value))"
                            >
                                <option value="" disabled>{{ localize('Select type', 'ط§ط®طھط± ط§ظ„ظ†ظˆط¹') }}</option>
                                <option
                                    v-for="option in availablePhotoTypes(item.type)"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors[`additional_photos.${index}.type`]" class="mt-1" />
                            <p v-if="item.original_type && item.existing_files.length" class="text-xs text-muted-foreground">
                                {{ localize('Remove the current file first if you want to change its type.', 'ط§ط­ط°ظپ ط§ظ„ظ…ظ„ظپ ط§ظ„ط­ط§ظ„ظٹ ط£ظˆظ„ظ‹ط§ ط¥ط°ط§ ط£ط±ط¯طھ طھط؛ظٹظٹط± ظ†ظˆط¹ظ‡.') }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label>{{ localize('Photo', 'ط§ظ„طµظˆط±ط©') }}</Label>
                            <FileUpload
                                v-model="item.temp_folders"
                                :initial-files="item.existing_files"
                                :allow-multiple="isMultiPhotoType(item.type)"
                                :max-files="additionalPhotoMaxFiles(item.type)"
                                collection="car_additional_photo"
                                theme="light"
                                width="100%"
                                @file-removed="(data: { type: string; fileId?: number }) => handleAdditionalPhotoFileRemoved(index, data)"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{
                                    isMultiPhotoType(item.type)
                                        ? localize('You can upload multiple interior photos.', 'ظٹظ…ظƒظ†ظƒ ط±ظپط¹ ط¹ط¯ط© طµظˆط± ظ„ظ„ط¯ط§ط®ظ„.')
                                        : localize('Choose one clear photo for the selected side.', 'ط§ط®طھط± طµظˆط±ط© ظˆط§ط¶ط­ط© ظˆط§ط­ط¯ط© ظ„ظ„ط¬ظ‡ط© ط§ظ„ظ…ط­ط¯ط¯ط©.')
                                }}
                            </p>
                            <InputError :message="form.errors[`additional_photos.${index}.temp_folders`]" class="mt-1" />
                        </div>

                        <div class="flex items-start justify-end">
                            <Button type="button" variant="ghost" class="text-destructive hover:text-destructive" @click="removeAdditionalPhoto(index)">
                                {{ localize('Remove', 'ط­ط°ظپ') }}
                            </Button>
                        </div>
                    </div>
                </section>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <Label for="make">{{ localize('Make', 'ط§ظ„ط´ط±ظƒط© ط§ظ„ظ…طµظ†ط¹ط©') }}</Label>
                        <select id="make" v-model="form.make" class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30">
                            <option value="" disabled>{{ localize('Select make', 'اختر الشركة المصنعة') }}</option>
                            <option v-for="option in makeOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.make" class="mt-1" />
                    </div>

                    <div>
                        <Label for="model">{{ localize('Model', 'ط§ظ„ظ…ظˆط¯ظٹظ„') }}</Label>
                        <select id="model" v-model="form.model" class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30">
                            <option value="" disabled>{{ localize('Select model', 'اختر الموديل') }}</option>
                            <option v-for="option in modelOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.model" class="mt-1" />
                    </div>

                    <div>
                        <Label for="year">{{ localize('Year', 'ط§ظ„ط³ظ†ط©') }}</Label>
                        <select id="year" v-model="form.year" class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30">
                            <option value="" disabled>{{ localize('Select year', 'اختر السنة') }}</option>
                            <option v-for="option in yearOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.year" class="mt-1" />
                    </div>

                    <div>
                        <Label for="license_plate">{{ localize('License Plate', 'ط±ظ‚ظ… ط§ظ„ظ„ظˆط­ط©') }}</Label>
                        <Input id="license_plate" v-model="form.license_plate" :placeholder="localize('e.g., ABC-1234', 'ظ…ط«ط§ظ„: ABC-1234')" />
                        <InputError :message="form.errors.license_plate" class="mt-1" />
                    </div>

                    <div>
                        <Label for="branch_id">{{ localize('Branch', 'ط§ظ„ظپط±ط¹') }}</Label>
                        <select
                            id="branch_id"
                            v-model="form.branch_id"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                            :disabled="!canAccessAllBranches && branches.length <= 1"
                        >
                            <option value="" disabled>{{ localize('Select branch', 'ط§ط®طھط± ط§ظ„ظپط±ط¹') }}</option>
                            <option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">
                                {{ branch.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.branch_id" class="mt-1" />
                    </div>

                    <div>
                        <Label for="mileage">{{ localize('Mileage (km)', 'ط§ظ„ظ…ط³ط§ظپط© ط§ظ„ظ…ظ‚ط·ظˆط¹ط© (ظƒظ…)') }}</Label>
                        <Input id="mileage" v-model="form.mileage" type="number" min="0" step="1000" :placeholder="localize('e.g., 15000', 'ظ…ط«ط§ظ„: 15000')" />
                        <InputError :message="form.errors.mileage" class="mt-1" />
                    </div>

                    <div>
                        <Label for="transmission">{{ localize('Transmission', 'ظ†ط§ظ‚ظ„ ط§ظ„ط­ط±ظƒط©') }}</Label>
                        <select id="transmission" v-model="form.transmission" class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30">
                            <option value="automatic">{{ localize('Automatic', 'ط£ظˆطھظˆظ…ط§طھظٹظƒ') }}</option>
                            <option value="manual">{{ localize('Manual', 'ظٹط¯ظˆظٹ') }}</option>
                        </select>
                        <InputError :message="form.errors.transmission" class="mt-1" />
                    </div>

                    <div>
                        <Label for="seats">{{ localize('Seats', 'ط¹ط¯ط¯ ط§ظ„ظ…ظ‚ط§ط¹ط¯') }}</Label>
                        <Input id="seats" v-model="form.seats" type="number" min="1" max="20" :placeholder="localize('e.g., 5', 'ظ…ط«ط§ظ„: 5')" />
                        <InputError :message="form.errors.seats" class="mt-1" />
                    </div>

                    <div>
                        <Label for="fuel_type">{{ localize('Fuel Type', 'ظ†ظˆط¹ ط§ظ„ظˆظ‚ظˆط¯') }}</Label>
                        <select id="fuel_type" v-model="form.fuel_type" class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30">
                            <option v-for="fuel in fuelTypes" :key="fuel.value" :value="fuel.value">
                                {{ fuel.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.fuel_type" class="mt-1" />
                    </div>

                    <div class="md:col-span-2">
                        <Label for="description">{{ localize('Description', 'ط§ظ„ظˆطµظپ') }}</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                            :placeholder="localize('Enter a detailed description of the car including features, condition, and any special notes...', 'ط£ط¯ط®ظ„ ظˆطµظپظ‹ط§ طھظپطµظٹظ„ظٹظ‹ط§ ظ„ظ„ط³ظٹط§ط±ط© ظٹط´ظ…ظ„ ط§ظ„ظ…ط²ط§ظٹط§ ظˆط§ظ„ط­ط§ظ„ط© ظˆط£ظٹ ظ…ظ„ط§ط­ط¸ط§طھ ط®ط§طµط©...')"
                        />
                        <InputError :message="form.errors.description" class="mt-1" />
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Button type="submit" :disabled="form.processing">
                        {{ submitLabel }}
                    </Button>
                    <Link v-if="subdomain" :href="index(subdomain).url">
                        <Button type="button" variant="outline">{{ localize('Cancel', 'ط¥ظ„ط؛ط§ط،') }}</Button>
                    </Link>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>

