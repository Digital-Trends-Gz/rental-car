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

interface Branch {
    id: number;
    name: string;
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
});

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
        form.put(update([subdomain.value, props.car.id]).url);
    } else {
        form.image = [...tempFolders.value];
        form.post(store(subdomain.value).url, {
            onSuccess: () => {
                form.reset();
                tempFolders.value = [];
                fileUploadRef.value?.resetFiles();
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
                        <Label>{{ localize('Image', 'الصورة') }}</Label>
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
                        </div>
                    </div>

                    <div class="w-full space-y-4 py-0 md:w-1/2 md:py-6">
                        <div>
                            <Label for="status">{{ localize('Status', 'الحالة') }}</Label>
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

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <Label for="make">{{ localize('Make', 'الشركة المصنعة') }}</Label>
                        <Input id="make" v-model="form.make" :placeholder="localize('e.g., Toyota, Honda, Ford, BMW...', 'مثال: Toyota, Honda, Ford, BMW...')" />
                        <InputError :message="form.errors.make" class="mt-1" />
                    </div>

                    <div>
                        <Label for="model">{{ localize('Model', 'الموديل') }}</Label>
                        <Input id="model" v-model="form.model" :placeholder="localize('e.g., Camry, Civic, F-150, X5...', 'مثال: Camry, Civic, F-150, X5...')" />
                        <InputError :message="form.errors.model" class="mt-1" />
                    </div>

                    <div>
                        <Label for="year">{{ localize('Year', 'السنة') }}</Label>
                        <Input id="year" v-model="form.year" type="number" :min="1900" :max="new Date().getFullYear() + 1" :placeholder="localize('e.g., 2023', 'مثال: 2023')" />
                        <InputError :message="form.errors.year" class="mt-1" />
                    </div>

                    <div>
                        <Label for="license_plate">{{ localize('License Plate', 'رقم اللوحة') }}</Label>
                        <Input id="license_plate" v-model="form.license_plate" :placeholder="localize('e.g., ABC-1234', 'مثال: ABC-1234')" />
                        <InputError :message="form.errors.license_plate" class="mt-1" />
                    </div>

                    <div>
                        <Label for="branch_id">{{ localize('Branch', 'الفرع') }}</Label>
                        <select
                            id="branch_id"
                            v-model="form.branch_id"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                            :disabled="!canAccessAllBranches && branches.length <= 1"
                        >
                            <option value="" disabled>{{ localize('Select branch', 'اختر الفرع') }}</option>
                            <option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">
                                {{ branch.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.branch_id" class="mt-1" />
                    </div>

                    <div>
                        <Label for="mileage">{{ localize('Mileage (km)', 'المسافة المقطوعة (كم)') }}</Label>
                        <Input id="mileage" v-model="form.mileage" type="number" min="0" step="1000" :placeholder="localize('e.g., 15000', 'مثال: 15000')" />
                        <InputError :message="form.errors.mileage" class="mt-1" />
                    </div>

                    <div>
                        <Label for="transmission">{{ localize('Transmission', 'ناقل الحركة') }}</Label>
                        <select id="transmission" v-model="form.transmission" class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30">
                            <option value="automatic">{{ localize('Automatic', 'أوتوماتيك') }}</option>
                            <option value="manual">{{ localize('Manual', 'يدوي') }}</option>
                        </select>
                        <InputError :message="form.errors.transmission" class="mt-1" />
                    </div>

                    <div>
                        <Label for="seats">{{ localize('Seats', 'عدد المقاعد') }}</Label>
                        <Input id="seats" v-model="form.seats" type="number" min="1" max="20" :placeholder="localize('e.g., 5', 'مثال: 5')" />
                        <InputError :message="form.errors.seats" class="mt-1" />
                    </div>

                    <div>
                        <Label for="fuel_type">{{ localize('Fuel Type', 'نوع الوقود') }}</Label>
                        <select id="fuel_type" v-model="form.fuel_type" class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30">
                            <option v-for="fuel in fuelTypes" :key="fuel.value" :value="fuel.value">
                                {{ fuel.label }}
                            </option>
                        </select>
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
