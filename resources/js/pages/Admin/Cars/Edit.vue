<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
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
    price_per_week: number | string;
    price_per_month: number | string;
    allowed_km_per_day: number | string;
    allowed_km_per_week: number | string;
    allowed_km_per_month: number | string;
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
const statusOptions = computed(() => statuses.value.map((status) => ({ value: status.value, label: status.label })));
const branchOptions = computed(() => props.branches.map((branch) => ({ value: String(branch.id), label: branch.name })));
const transmissionOptions = computed(() => [
    { value: 'automatic', label: localize('Automatic', 'أوتوماتيك') },
    { value: 'manual', label: localize('Manual', 'يدوي') },
]);
const fuelTypeOptions = computed(() => fuelTypes.value.map((fuel) => ({ value: fuel.value, label: fuel.label })));

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
    { value: 'right', label: localize('Right', 'اليمين') },
    { value: 'left', label: localize('Left', 'اليسار') },
    { value: 'front', label: localize('Front', 'الأمام') },
    { value: 'rear', label: localize('Rear', 'الخلف') },
    { value: 'inside', label: localize('Inside', 'الداخل') },
]);

const form = useForm({
    make: safeStr(props.car?.make),
    model: safeStr(props.car?.model),
    year: safeNum(props.car?.year),
    license_plate: safeStr(props.car?.license_plate),
    branch_id: safeStr(props.car?.branch_id),
    color: safeLower(props.car?.color, 'white'),
    price_per_day: safeNum(props.car?.price_per_day),
    price_per_week: safeNum(props.car?.price_per_week),
    price_per_month: safeNum(props.car?.price_per_month),
    allowed_km_per_day: safeNum(props.car?.allowed_km_per_day),
    allowed_km_per_week: safeNum(props.car?.allowed_km_per_week),
    allowed_km_per_month: safeNum(props.car?.allowed_km_per_month),
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
    if (form.processing) return isEdit.value ? localize('Saving...', 'جارٍ الحفظ...') : localize('Creating...', 'جارٍ الإنشاء...');
    return isEdit.value ? localize('Save Changes', 'حفظ التغييرات') : localize('Create Car', 'إنشاء سيارة');
});

const pageTitle = computed(() => (isEdit.value ? localize('Edit Car', 'تعديل السيارة') : localize('Create Car', 'إنشاء سيارة')));
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ pageTitle }}</h1>
                <Link v-if="subdomain" :href="index(subdomain).url">
                    <Button variant="outline">{{ localize('Back', 'رجوع') }}</Button>
                </Link>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <div class="flex flex-col gap-6 md:flex-row md:gap-8">
                    <div class="w-full md:w-1/2">
                        <Label>{{ localize('Main Cover Image', 'الصورة الرئيسية') }}</Label>
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
                                {{ localize('Upload one main image for cards and list pages.', 'ارفع صورة رئيسية واحدة لبطاقات السيارة وصفحات القوائم.') }}
                            </p>
                        </div>
                    </div>

                    <div class="w-full space-y-4 py-0 md:w-1/2 md:py-6">
                        <div>
                            <Label for="status">{{ localize('Status', 'الحالة') }}</Label>
                            <SearchableSelect
                                v-model="form.status"
                                :options="statusOptions"
                                :placeholder="localize('Select status', 'اختر الحالة')"
                                :search-placeholder="localize('Search status...', 'ابحث عن الحالة...')"
                                :empty-text="localize('No statuses found.', 'لا توجد حالات.')"
                            />
                            <InputError :message="form.errors.status" class="mt-1" />
                        </div>

                        <div>
                            <Label for="price_per_day">{{ localize('Price Per Day', 'السعر لكل يوم') }}</Label>
                            <Input id="price_per_day" v-model="form.price_per_day" type="number" step="0.01" min="0" :placeholder="localize('e.g., 50.00', 'مثال: 50.00')" />
                            <InputError :message="form.errors.price_per_day" class="mt-1" />
                        </div>

                        <div>
                            <Label class="mb-2 block">{{ localize('Color', 'اللون') }}</Label>
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
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('Rental Pricing & Limits', 'أسعار الإيجار وحدود الكيلومترات') }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ localize('Configure daily, weekly, and monthly pricing and mileage limits for this car.', 'ضبط سعر الإيجار اليومي والأسبوعي والشهري وحدود الكيلومترات لهذه السيارة.') }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <Label for="price_per_week">{{ localize('Price Per Week', 'السعر الأسبوعي') }}</Label>
                            <Input id="price_per_week" v-model="form.price_per_week" type="number" step="0.01" min="0" :placeholder="localize('e.g., 300.00', 'مثال: 300.00')" />
                            <InputError :message="form.errors.price_per_week" class="mt-1" />
                        </div>
                        <div>
                            <Label for="price_per_month">{{ localize('Price Per Month', 'السعر الشهري') }}</Label>
                            <Input id="price_per_month" v-model="form.price_per_month" type="number" step="0.01" min="0" :placeholder="localize('e.g., 900.00', 'مثال: 900.00')" />
                            <InputError :message="form.errors.price_per_month" class="mt-1" />
                        </div>
                        <div>
                            <Label for="allowed_km_per_day">{{ localize('Allowed KM Per Day', 'الكيلومترات المسموحة يوميًا') }}</Label>
                            <Input id="allowed_km_per_day" v-model="form.allowed_km_per_day" type="number" min="0" :placeholder="localize('e.g., 200', 'مثال: 200')" />
                            <InputError :message="form.errors.allowed_km_per_day" class="mt-1" />
                        </div>
                        <div>
                            <Label for="allowed_km_per_week">{{ localize('Allowed KM Per Week', 'الكيلومترات المسموحة أسبوعيًا') }}</Label>
                            <Input id="allowed_km_per_week" v-model="form.allowed_km_per_week" type="number" min="0" :placeholder="localize('e.g., 1200', 'مثال: 1200')" />
                            <InputError :message="form.errors.allowed_km_per_week" class="mt-1" />
                        </div>
                        <div>
                            <Label for="allowed_km_per_month">{{ localize('Allowed KM Per Month', 'الكيلومترات المسموحة شهريًا') }}</Label>
                            <Input id="allowed_km_per_month" v-model="form.allowed_km_per_month" type="number" min="0" :placeholder="localize('e.g., 4000', 'مثال: 4000')" />
                            <InputError :message="form.errors.allowed_km_per_month" class="mt-1" />
                        </div>
                    </div>
                </section>

                <section class="space-y-4 rounded-xl border border-border/70 bg-card p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">{{ localize('Another Photos', 'صور إضافية') }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ localize('Add one typed photo for each side such as right, left, front, rear, or inside.', 'أضف صورة محددة النوع لكل جهة مثل اليمين أو اليسار أو الأمام أو الخلف أو الداخل.') }}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form.additional_photos.length >= additionalPhotoTypeOptions.length"
                            @click="addAdditionalPhoto"
                        >
                            {{ localize('Add Photo Type', 'إضافة نوع صورة') }}
                        </Button>
                    </div>

                    <div v-if="form.additional_photos.length === 0" class="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                        {{ localize('No additional typed photos added yet.', 'لا توجد صور إضافية محددة النوع حتى الآن.') }}
                    </div>

                    <div
                        v-for="(item, index) in form.additional_photos"
                        :key="item.key"
                        class="grid gap-4 rounded-lg border border-border/60 p-4 md:grid-cols-[220px,1fr,auto]"
                    >
                        <div class="space-y-2">
                            <Label :for="`additional-photo-type-${index}`">{{ localize('Photo Type', 'نوع الصورة') }}</Label>
                            <SearchableSelect
                                :model-value="item.type"
                                :options="availablePhotoTypes(item.type)"
                                :placeholder="localize('Select type', 'اختر النوع')"
                                :search-placeholder="localize('Search type...', 'ابحث عن النوع...')"
                                :empty-text="localize('No types available.', 'لا توجد أنواع متاحة.')"
                                :disabled="Boolean(item.original_type && item.existing_files.length)"
                                @update:model-value="onAdditionalPhotoTypeChange(index, $event)"
                            />
                            <InputError :message="form.errors[`additional_photos.${index}.type`]" class="mt-1" />
                            <p v-if="item.original_type && item.existing_files.length" class="text-xs text-muted-foreground">
                                {{ localize('Remove the current file first if you want to change its type.', 'احذف الملف الحالي أولًا إذا أردت تغيير نوعه.') }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label>{{ localize('Photo', 'الصورة') }}</Label>
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
                                        ? localize('You can upload multiple interior photos.', 'يمكنك رفع عدة صور للداخل.')
                                        : localize('Choose one clear photo for the selected side.', 'اختر صورة واضحة واحدة للجهة المحددة.')
                                }}
                            </p>
                            <InputError :message="form.errors[`additional_photos.${index}.temp_folders`]" class="mt-1" />
                        </div>

                        <div class="flex items-start justify-end">
                            <Button type="button" variant="ghost" class="text-destructive hover:text-destructive" @click="removeAdditionalPhoto(index)">
                                {{ localize('Remove', 'حذف') }}
                            </Button>
                        </div>
                    </div>
                </section>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <Label for="make">{{ localize('Make', 'الشركة المصنعة') }}</Label>
                        <SearchableSelect
                            v-model="form.make"
                            :options="makeOptions"
                            :placeholder="localize('Select make', 'اختر الشركة المصنعة')"
                            :search-placeholder="localize('Search make...', 'ابحث عن الشركة المصنعة...')"
                            :empty-text="localize('No makes found.', 'لا توجد شركات مصنعة.')"
                        />
                        <InputError :message="form.errors.make" class="mt-1" />
                    </div>

                    <div>
                        <Label for="model">{{ localize('Model', 'الموديل') }}</Label>
                        <SearchableSelect
                            v-model="form.model"
                            :options="modelOptions"
                            :placeholder="localize('Select model', 'اختر الموديل')"
                            :search-placeholder="localize('Search model...', 'ابحث عن الموديل...')"
                            :empty-text="localize('No models found.', 'لا توجد موديلات.')"
                        />
                        <InputError :message="form.errors.model" class="mt-1" />
                    </div>

                    <div>
                        <Label for="year">{{ localize('Year', 'السنة') }}</Label>
                        <SearchableSelect
                            v-model="form.year"
                            :options="yearOptions"
                            :placeholder="localize('Select year', 'اختر السنة')"
                            :search-placeholder="localize('Search year...', 'ابحث عن السنة...')"
                            :empty-text="localize('No years found.', 'لا توجد سنوات.')"
                        />
                        <InputError :message="form.errors.year" class="mt-1" />
                    </div>

                    <div>
                        <Label for="license_plate">{{ localize('License Plate', 'رقم اللوحة') }}</Label>
                        <Input id="license_plate" v-model="form.license_plate" :placeholder="localize('e.g., ABC-1234', 'مثال: ABC-1234')" />
                        <InputError :message="form.errors.license_plate" class="mt-1" />
                    </div>

                    <div>
                        <Label for="branch_id">{{ localize('Branch', 'الفرع') }}</Label>
                        <SearchableSelect
                            v-model="form.branch_id"
                            :options="branchOptions"
                            :placeholder="localize('Select branch', 'اختر الفرع')"
                            :search-placeholder="localize('Search branch...', 'ابحث عن الفرع...')"
                            :empty-text="localize('No branches found.', 'لا توجد فروع.')"
                            :disabled="!canAccessAllBranches && branches.length <= 1"
                        />
                        <InputError :message="form.errors.branch_id" class="mt-1" />
                    </div>

                    <div>
                        <Label for="mileage">{{ localize('Mileage (km)', 'المسافة المقطوعة (كم)') }}</Label>
                        <Input id="mileage" v-model="form.mileage" type="number" min="0" step="1000" :placeholder="localize('e.g., 15000', 'مثال: 15000')" />
                        <InputError :message="form.errors.mileage" class="mt-1" />
                    </div>

                    <div>
                        <Label for="transmission">{{ localize('Transmission', 'ناقل الحركة') }}</Label>
                        <SearchableSelect
                            v-model="form.transmission"
                            :options="transmissionOptions"
                            :placeholder="localize('Select transmission', 'اختر ناقل الحركة')"
                            :search-placeholder="localize('Search transmission...', 'ابحث عن ناقل الحركة...')"
                            :empty-text="localize('No transmission types found.', 'لا توجد أنواع ناقل حركة.')"
                        />
                        <InputError :message="form.errors.transmission" class="mt-1" />
                    </div>

                    <div>
                        <Label for="seats">{{ localize('Seats', 'عدد المقاعد') }}</Label>
                        <Input id="seats" v-model="form.seats" type="number" min="1" max="20" :placeholder="localize('e.g., 5', 'مثال: 5')" />
                        <InputError :message="form.errors.seats" class="mt-1" />
                    </div>

                    <div>
                        <Label for="fuel_type">{{ localize('Fuel Type', 'نوع الوقود') }}</Label>
                        <SearchableSelect
                            v-model="form.fuel_type"
                            :options="fuelTypeOptions"
                            :placeholder="localize('Select fuel type', 'اختر نوع الوقود')"
                            :search-placeholder="localize('Search fuel type...', 'ابحث عن نوع الوقود...')"
                            :empty-text="localize('No fuel types found.', 'لا توجد أنواع وقود.')"
                        />
                        <InputError :message="form.errors.fuel_type" class="mt-1" />
                    </div>

                    <div class="md:col-span-2">
                        <Label for="description">{{ localize('Description', 'الوصف') }}</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                            :placeholder="localize('Enter a detailed description of the car including features, condition, and any special notes...', 'أدخل وصفًا تفصيليًا للسيارة يشمل المزايا والحالة وأي ملاحظات خاصة...')"
                        />
                        <InputError :message="form.errors.description" class="mt-1" />
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Button type="submit" :disabled="form.processing">
                        {{ submitLabel }}
                    </Button>
                    <Link v-if="subdomain" :href="index(subdomain).url">
                        <Button type="button" variant="outline">{{ localize('Cancel', 'إلغاء') }}</Button>
                    </Link>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
