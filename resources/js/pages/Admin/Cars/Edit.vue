<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { useTrans } from '@/composables/useTrans';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { index, store, update } from '@/routes/admin/cars';
import { store as branchStore } from '@/routes/admin/branches';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Car {
    id: number;
    make: string;
    model: string;
    year: number | string;
    license_plate: string;
    license_plate_format?: string | null;
    license_expiry_date?: string | null;
    insurance_expiry_date?: string | null;


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
    engine_power?: number | string | null;
    fuel_type: string;
    description: string;
    description_translations?: Record<string, string | null> | null;
    status: string;
    status_task_time?: string | null;
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

interface UsageLimit {
    current: number;
    limit: number | null;
    remaining: number | null;
    at_limit: boolean;
    message: string | null;
}

interface CountryOption {
    value: string;
    label: string;
}

interface CatalogOption {
    value: string;
    label: string;
}

interface PlateFormatOption {
    value: string;
    label: string;
    mask?: string | null;
    example?: string | null;
    is_active?: boolean;
}

interface ModelOption extends CatalogOption {
    years: CatalogOption[];
    specs?: {
        fuel_type?: string | null;
        transmission?: string | null;
        seats?: number | string | null;
        engine_power?: number | string | null;
    } | null;
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

interface SupportedLocale {
    code: string;
    name: string;
    native: string;
    direction: 'ltr' | 'rtl';
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
    countries: CountryOption[];
    plateFormats?: PlateFormatOption[];
    selectedPlateFormat?: string | null;
    canAccessAllBranches: boolean;
    branchUsage?: UsageLimit;
    supportedLocales: SupportedLocale[];
    enums: Enums;
}>();

const page = usePage<any>();
const subdomain = computed<string | undefined>(() => page.props.current_tenant?.slug);
const isEdit = computed(() => !!props.car);
const { locale, t } = useTrans();
const formTranslationRoot = 'dashboard.admin.cars.form';
const formTranslationKeyFor = (value: string) =>
    `${formTranslationRoot}.${value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 90)}`;
const localize = (en: string, ar: string) => {
    const key = formTranslationKeyFor(en);
    const translated = t(key);

    if (translated !== key) {
        return translated;
    }

    return locale.value === 'ar' ? ar : en;
};
const translateCarValue = (value: string, fallback: string) => {
    const normalized = String(value || '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
    const key = `dashboard.admin.cars.show.values.${normalized}`;
    const translated = t(key);

    return translated === key ? fallback : translated;
};
const supportedLocales = computed<SupportedLocale[]>(() =>
    props.supportedLocales?.length
        ? props.supportedLocales
        : [{ code: 'en', name: 'English', native: 'English', direction: 'ltr' }],
);

const carColors = computed(() =>
    props.enums.colors.map((color) => ({
        ...color,
        value: color.value.toLowerCase(),
        name: translateCarValue(color.value, color.name.charAt(0).toUpperCase() + color.name.slice(1)),
    })),
);

const fuelTypes = computed(() =>
    props.enums.fuelTypes.map((fuel) => ({
        value: fuel.toLowerCase(),
        label: translateCarValue(fuel, fuel.charAt(0).toUpperCase() + fuel.slice(1)),
    })),
);

const statuses = computed(() => props.enums.statuses);
const statusOptions = computed(() => statuses.value.map((status) => ({ value: status.value, label: translateCarValue(status.value, status.label) })));
const requiresStatusTaskTime = computed(() => ['cleaning', 'maintenance'].includes(String(form.status)));
const availableBranches = ref<Branch[]>(Array.isArray(props.branches) ? [...props.branches] : []);
const branchOptions = computed(() => availableBranches.value.map((branch) => ({ value: String(branch.id), label: branch.name })));
const canCreateBranch = computed(() => !props.branchUsage?.at_limit);
const branchLimitMessage = computed(() => props.branchUsage?.message || t('dashboard.admin.branches.plan_limit_reached_fallback'));
const plateFormatOptions = computed<PlateFormatOption[]>(() => Array.isArray(props.plateFormats) ? props.plateFormats : []);
const selectedPlateFormat = computed(() => form.license_plate_format || props.selectedPlateFormat || '');
const selectedPlateFormatOption = computed(() => plateFormatOptions.value.find((option) => option.value === selectedPlateFormat.value) ?? null);
const plateFormatHelper = computed(() => {
    const selected = selectedPlateFormatOption.value;
    if (!selected) return '';

    const parts = [
        selected.mask ? `${localize('Mask', 'النمط')}: ${selected.mask}` : '',
        selected.example ? `${localize('Example', 'مثال')}: ${selected.example}` : '',
    ].filter(Boolean);

    return parts.join(' | ');
});
const plateFormatPlaceholder = computed(() => selectedPlateFormatOption.value?.example || localize('Enter license plate', 'أدخل رقم اللوحة'));
const transmissionOptions = computed(() => [
    { value: 'automatic', label: localize('Automatic', 'أوتوماتيك') },
    { value: 'manual', label: localize('Manual', 'يدوي') },
]);
const fuelTypeOptions = computed(() => fuelTypes.value.map((fuel) => ({ value: fuel.value, label: fuel.label })));
const countryOptions = computed(() => props.countries.map((country) => ({ value: country.value, label: country.label })));
const availableCatalog = ref({
    years: [...props.catalog.years],
    makes: [...props.catalog.makes],
});

const makeOptions = computed<MakeOption[]>(() => {
    const options = [...availableCatalog.value.makes];
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
    const options = [...(selectedModel?.years?.length ? selectedModel.years : availableCatalog.value.years)];
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

function safeDateStr(value: unknown, fallback = ''): string {
    if (value === null || value === undefined || value === '') return fallback;
    const str = String(value);
    if (str.includes('T')) return str.split('T')[0];
    if (str.includes(' ')) return str.split(' ')[0];
    return str;
}

function safeNum(value: unknown, fallback = ''): string {
    if (value === null || value === undefined) return fallback;
    return String(value);
}

function safeLower(value: unknown, fallback: string): string {
    if (value === null || value === undefined || value === '') return fallback;
    return String(value).toLowerCase();
}

function localizedTextRecord(values?: Record<string, string | null> | null, fallback = ''): Record<string, string> {
    return Object.fromEntries(
        supportedLocales.value.map((localeMeta) => [
            localeMeta.code,
            safeStr(values?.[localeMeta.code], localeMeta.code === 'en' ? fallback : ''),
        ]),
    );
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
    license_plate_format: safeStr(props.car?.license_plate_format, props.selectedPlateFormat ?? ''),
    license_expiry_date: safeDateStr(props.car?.license_expiry_date),
    insurance_expiry_date: safeDateStr(props.car?.insurance_expiry_date),

    branch_id: safeStr(props.car?.branch_id),
    color: safeLower(props.car?.color, 'white'),
    price_per_day: safeNum(props.car?.price_per_day),
    price_per_week: safeNum(props.car?.price_per_week),
    price_per_month: safeNum(props.car?.price_per_month),
    allowed_km_per_day: safeNum(props.car?.allowed_km_per_day),
    allowed_km_per_week: safeNum(props.car?.allowed_km_per_week),
    allowed_km_per_month: safeNum(props.car?.allowed_km_per_month),
    mileage: safeNum(props.car?.mileage),
    transmission: safeStr(props.car?.transmission),
    seats: safeNum(props.car?.seats),
    engine_power: safeNum(props.car?.engine_power),
    fuel_type: safeLower(props.car?.fuel_type, ''),
    description: safeStr(props.car?.description),
    description_translations: localizedTextRecord(props.car?.description_translations, safeStr(props.car?.description)),
    status: safeStr(props.car?.status),
    status_task_time: safeStr(props.car?.status_task_time),
    image: [] as string[],
    image_temp_folders: [] as string[],
    image_removed_files: [] as number[],
    additional_photos: props.additionalPhotoFiles.map((item) => createAdditionalPhotoRow(item.type, item.files)),
    deleted_additional_photo_types: [] as string[],
});

const showBranchModal = ref(false);
const branchSubmitting = ref(false);
const showCatalogModal = ref(false);
const catalogSubmitting = ref(false);
const branchForm = useForm({
    name: '',
    country: '',
    city: '',
    street_name: '',
    phone_1: '',
    email: '',
    cr_number: '',
    manager_name: '',
    manager_civil_number: '',
});
const catalogEntryForm = useForm({
    make: '',
    model: '',
    year: '',
    fuel_type: '',
    transmission: '',
    seats: '',
    engine_power: '',
});

const selectedCatalogModel = computed<ModelOption | null>(() => {
    const selectedMake = makeOptions.value.find((option) => option.value === form.make);
    return selectedMake?.models.find((option) => option.value === form.model) ?? null;
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
        const validYearValues = new Set((selectedModel?.years?.length ? selectedModel.years : availableCatalog.value.years).map((option) => option.value));

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

        applyCatalogSpecs(selectedCatalogModel.value);
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

function dispatchToast(tone: 'success' | 'error' | 'warning' | 'info', message: string) {
    window.dispatchEvent(new CustomEvent('flash-toast', { detail: { tone, message } }));
}

function resetBranchForm() {
    branchForm.clearErrors();
    branchForm.reset();
    branchForm.name = '';
    branchForm.country = '';
    branchForm.city = '';
    branchForm.street_name = '';
    branchForm.phone_1 = '';
    branchForm.email = '';
    branchForm.cr_number = '';
    branchForm.manager_name = '';
    branchForm.manager_civil_number = '';
}

function appendBranch(branch: { id: number; name: string }) {
    if (availableBranches.value.some((item) => Number(item.id) === Number(branch.id))) {
        return;
    }

    availableBranches.value = [...availableBranches.value, { id: branch.id, name: branch.name }];
}

function applyCatalogSpecs(model: ModelOption | null) {
    const specs = model?.specs;

    if (!specs) {
        return;
    }

    if (specs.fuel_type) form.fuel_type = String(specs.fuel_type).toLowerCase();
    if (specs.transmission) form.transmission = String(specs.transmission);
    if (specs.seats) form.seats = String(specs.seats);
    if (specs.engine_power) form.engine_power = String(specs.engine_power);
}

function resetCatalogEntryForm() {
    catalogEntryForm.clearErrors();
    catalogEntryForm.reset();
    catalogEntryForm.make = safeStr(form.make);
    catalogEntryForm.model = '';
    catalogEntryForm.year = safeStr(form.year);
    catalogEntryForm.fuel_type = safeStr(form.fuel_type);
    catalogEntryForm.transmission = safeStr(form.transmission);
    catalogEntryForm.seats = safeStr(form.seats);
    catalogEntryForm.engine_power = safeStr(form.engine_power);
}

function openCatalogModal() {
    resetCatalogEntryForm();
    showCatalogModal.value = true;
}

function adminCatalogEntryUrl() {
    const localeCode = String(page.props.locale || '').trim();
    const path = typeof window !== 'undefined' ? window.location.pathname : '';
    const localePrefix = localeCode && path.startsWith(`/${localeCode}/`) ? `/${localeCode}` : '';

    return `${localePrefix}/admin/cars/catalog-entries`;
}

async function createCatalogEntryFromModal() {
    catalogEntryForm.clearErrors();
    catalogSubmitting.value = true;

    try {
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
        const formData = new FormData();

        formData.append('make', catalogEntryForm.make);
        formData.append('model', catalogEntryForm.model);
        formData.append('year', catalogEntryForm.year);
        formData.append('fuel_type', catalogEntryForm.fuel_type);
        formData.append('transmission', catalogEntryForm.transmission);
        formData.append('seats', catalogEntryForm.seats);
        formData.append('engine_power', catalogEntryForm.engine_power);

        const response = await fetch(adminCatalogEntryUrl(), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: formData,
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 422 && payload?.errors && typeof payload.errors === 'object') {
                Object.entries(payload.errors as Record<string, string[]>).forEach(([field, messages]) => {
                    catalogEntryForm.setError(field, Array.isArray(messages) ? String(messages[0] || '') : String(messages || ''));
                });
                return;
            }

            dispatchToast('error', String(payload?.message || localize('Vehicle model creation failed.', 'فشل إنشاء موديل السيارة.')));
            return;
        }

        if (payload?.catalog) {
            availableCatalog.value = {
                years: Array.isArray(payload.catalog.years) ? payload.catalog.years : availableCatalog.value.years,
                makes: Array.isArray(payload.catalog.makes) ? payload.catalog.makes : availableCatalog.value.makes,
            };
        }

        if (payload?.entry) {
            form.make = safeStr(payload.entry.make);
            form.model = safeStr(payload.entry.model);
            if (payload.entry.year) form.year = safeStr(payload.entry.year);
            if (payload.entry.fuel_type) form.fuel_type = safeStr(payload.entry.fuel_type).toLowerCase();
            if (payload.entry.transmission) form.transmission = safeStr(payload.entry.transmission);
            if (payload.entry.seats) form.seats = safeStr(payload.entry.seats);
            if (payload.entry.engine_power) form.engine_power = safeStr(payload.entry.engine_power);
        }

        dispatchToast('success', localize('Vehicle model saved successfully.', 'تم حفظ موديل السيارة بنجاح.'));
        showCatalogModal.value = false;
        resetCatalogEntryForm();
    } catch (error) {
        dispatchToast('error', error instanceof Error ? error.message : localize('Vehicle model creation failed.', 'فشل إنشاء موديل السيارة.'));
    } finally {
        catalogSubmitting.value = false;
    }
}

async function createBranchFromModal() {
    if (!subdomain.value) {
        dispatchToast('error', localize('Unable to create branch right now.', 'تعذر إنشاء الفرع الآن.'));
        return;
    }

    if (!canCreateBranch.value) {
        dispatchToast('error', branchLimitMessage.value);
        showBranchModal.value = false;
        return;
    }

    branchForm.clearErrors();
    branchSubmitting.value = true;

    try {
        const url = branchStore({ subdomain: subdomain.value }).url + '?inline=1';
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
        const formData = new FormData();

        formData.append('inline', '1');
        formData.append('name', branchForm.name);
        formData.append('country', branchForm.country);
        formData.append('city', branchForm.city);
        formData.append('street_name', branchForm.street_name);
        formData.append('phone_1', branchForm.phone_1);
        formData.append('email', branchForm.email);
        formData.append('cr_number', branchForm.cr_number);
        formData.append('manager_name', branchForm.manager_name);
        formData.append('manager_civil_number', branchForm.manager_civil_number);

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: formData,
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 422 && payload?.errors && typeof payload.errors === 'object') {
                Object.entries(payload.errors as Record<string, string[]>).forEach(([field, messages]) => {
                    branchForm.setError(field, Array.isArray(messages) ? String(messages[0] || '') : String(messages || ''));
                });
                return;
            }

            dispatchToast('error', String(payload?.message || localize('Branch creation failed.', 'فشل إنشاء الفرع.')));
            return;
        }

        if (payload?.branch) {
            appendBranch(payload.branch);
            form.branch_id = String(payload.branch.id);
        }

        dispatchToast('success', String(payload?.message || localize('Branch created successfully.', 'تم إنشاء الفرع بنجاح.')));
        showBranchModal.value = false;
        resetBranchForm();
    } catch (error) {
        dispatchToast('error', error instanceof Error ? error.message : localize('Branch creation failed.', 'فشل إنشاء الفرع.'));
    } finally {
        branchSubmitting.value = false;
    }
}

function submit(saveAsDraft = false) {
    if (!subdomain.value) {
        console.warn('No subdomain found; aborting submit.');
        return;
    }

    if (saveAsDraft) {
        form.status = 'draft';
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

            <form class="space-y-6" @submit.prevent="submit(false)">
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

                        <div v-if="requiresStatusTaskTime">
                            <Label for="status_task_time">{{ localize('Task Time', 'وقت المهمة') }}</Label>
                            <Input
                                id="status_task_time"
                                v-model="form.status_task_time"
                                type="time"
                                dir="ltr"
                            />
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ localize('Used in today tasks timeline for cleaning or maintenance.', 'يستخدم في جدول مهام اليوم للتنظيف أو الصيانة.') }}
                            </p>
                            <InputError :message="form.errors.status_task_time" class="mt-1" />
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
                        <div class="mb-1 flex items-center justify-between gap-3">
                            <Label for="model">{{ localize('Model', 'الموديل') }}</Label>
                            <Button type="button" variant="outline" size="sm" @click="openCatalogModal">
                                {{ localize('New Model', 'موديل جديد') }}
                            </Button>
                        </div>
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

                    <div class="space-y-2">
                        <Label for="license_plate_format">{{ localize('License Plate Format', 'نمط لوحة السيارة') }}</Label>
                        <SearchableSelect
                            v-model="form.license_plate_format"
                            :options="plateFormatOptions"
                            :placeholder="localize('Select format', 'اختر النمط')"
                            :search-placeholder="localize('Search format...', 'ابحث عن النمط...')"
                            :empty-text="localize('No plate formats found.', 'لا توجد أنماط لوحات.')"
                        />
                        <InputError :message="form.errors.license_plate_format" class="mt-1" />
                        <p v-if="plateFormatHelper" class="text-xs text-muted-foreground">{{ plateFormatHelper }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="license_plate">{{ localize('License Plate', 'رقم اللوحة') }}</Label>
                        <Input id="license_plate" v-model="form.license_plate" :placeholder="plateFormatPlaceholder" />
                        <InputError :message="form.errors.license_plate" class="mt-1" />
                    </div>
                    <div class="space-y-2">
                        <Label for="license_expiry_date">{{ localize('License Expiry Date', 'تاريخ انتهاء الرخصة') }} *</Label>
                        <Input id="license_expiry_date" v-model="form.license_expiry_date" type="date" required />
                        <InputError :message="form.errors.license_expiry_date" class="mt-1" />
                    </div>

                    <div class="space-y-2">
                        <Label for="insurance_expiry_date">{{ localize('Insurance Expiry Date', 'تاريخ انتهاء التأمين') }} *</Label>
                        <Input id="insurance_expiry_date" v-model="form.insurance_expiry_date" type="date" required />
                        <InputError :message="form.errors.insurance_expiry_date" class="mt-1" />
                    </div>



                    <div>
                        <div class="mb-1 flex items-center justify-between gap-3">
                            <Label for="branch_id">{{ localize('Branch', 'الفرع') }}</Label>
                            <Button type="button" variant="outline" size="sm" :disabled="!canCreateBranch" @click="showBranchModal = true">
                                {{ localize('New Branch', 'فرع جديد') }}
                            </Button>
                        </div>
                        <p v-if="!canCreateBranch" class="mb-2 text-xs text-destructive">{{ branchLimitMessage }}</p>
                        <SearchableSelect
                            v-model="form.branch_id"
                            :options="branchOptions"
                            :placeholder="localize('Select branch', 'اختر الفرع')"
                            :search-placeholder="localize('Search branch...', 'ابحث عن الفرع...')"
                            :empty-text="localize('No branches found.', 'لا توجد فروع.')"
                            :disabled="!canAccessAllBranches && availableBranches.length <= 1"
                        />
                        <InputError :message="form.errors.branch_id" class="mt-1" />
                    </div>

                    <div>
                        <Label for="mileage">{{ localize('Mileage (km)', 'المسافة المقطوعة (كم)') }}</Label>
                        <Input id="mileage" v-model="form.mileage" type="text" :placeholder="localize('e.g., 15000', 'مثال: 15000')" />
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
                        <Label for="engine_power">{{ localize('Engine Power (HP)', 'قدرة المحرك (حصان)') }}</Label>
                        <Input
                            id="engine_power"
                            v-model="form.engine_power"
                            type="number"
                            min="0"
                            :placeholder="localize('e.g., 150', 'مثال: 150')"
                        />
                        <InputError :message="form.errors.engine_power" class="mt-1" />
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

                    <div class="md:col-span-2 rounded-xl border border-border bg-slate-50/70 p-4">
                        <div class="mb-4">
                            <h2 class="text-base font-semibold text-foreground">
                                {{ localize('Public description translations', 'ترجمات وصف السيارة في الموقع') }}
                            </h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t('dashboard.admin.cars.form.public_description_translations_help') }}
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div v-for="localeMeta in supportedLocales" :key="localeMeta.code" class="space-y-2">
                                <Label :for="`description_translation_${localeMeta.code}`">
                                    {{ localeMeta.name }} ({{ localeMeta.code.toUpperCase() }})
                                </Label>
                                <textarea
                                    :id="`description_translation_${localeMeta.code}`"
                                    v-model="form.description_translations[localeMeta.code]"
                                    rows="3"
                                    :dir="localeMeta.direction"
                                    class="w-full rounded-md border border-input bg-white px-3 py-2 text-sm dark:bg-input/30"
                                    :placeholder="localeMeta.code === 'en' ? form.description : localize('Translated car description', 'وصف السيارة المترجم')"
                                />
                                <InputError :message="form.errors[`description_translations.${localeMeta.code}`]" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Button type="button" variant="outline" :disabled="form.processing" @click="submit(true)">
                        {{ localize('Save Draft', 'حفظ كمسودة') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ submitLabel }}
                    </Button>
                    <Link v-if="subdomain" :href="index(subdomain).url">
                        <Button type="button" variant="outline">{{ localize('Cancel', 'إلغاء') }}</Button>
                    </Link>
                </div>
            </form>
        </main>

        <Dialog v-model:open="showCatalogModal">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ localize('Add Vehicle Model', 'إضافة موديل سيارة') }}</DialogTitle>
                    <DialogDescription>
                        {{ localize('Save a model and its default specifications for this tenant only.', 'احفظ موديلًا ومواصفاته الافتراضية لهذا التيننت فقط.') }}
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="createCatalogEntryFromModal">
                    <div>
                        <Label for="catalog-make-modal">{{ localize('Make', 'الشركة المصنعة') }}</Label>
                        <SearchableSelect
                            v-model="catalogEntryForm.make"
                            :options="makeOptions"
                            :placeholder="localize('Select make', 'اختر الشركة المصنعة')"
                            :search-placeholder="localize('Search make...', 'ابحث عن الشركة المصنعة...')"
                            :empty-text="localize('No makes found.', 'لا توجد شركات مصنعة.')"
                        />
                        <InputError :message="catalogEntryForm.errors.make" class="mt-1" />
                    </div>

                    <div>
                        <Label for="catalog-model-modal">{{ localize('Model', 'الموديل') }}</Label>
                        <Input id="catalog-model-modal" v-model="catalogEntryForm.model" :placeholder="localize('e.g., Tucson', 'مثال: Tucson')" />
                        <InputError :message="catalogEntryForm.errors.model" class="mt-1" />
                    </div>

                    <div>
                        <Label for="catalog-year-modal">{{ localize('Year', 'السنة') }}</Label>
                        <SearchableSelect
                            v-model="catalogEntryForm.year"
                            :options="availableCatalog.years"
                            :placeholder="localize('Select year', 'اختر السنة')"
                            :search-placeholder="localize('Search year...', 'ابحث عن السنة...')"
                            :empty-text="localize('No years found.', 'لا توجد سنوات.')"
                            clearable
                        />
                        <InputError :message="catalogEntryForm.errors.year" class="mt-1" />
                    </div>

                    <div>
                        <Label for="catalog-fuel-modal">{{ localize('Fuel Type', 'نوع الوقود') }}</Label>
                        <SearchableSelect
                            v-model="catalogEntryForm.fuel_type"
                            :options="fuelTypeOptions"
                            :placeholder="localize('Select fuel type', 'اختر نوع الوقود')"
                            :search-placeholder="localize('Search fuel type...', 'ابحث عن نوع الوقود...')"
                            :empty-text="localize('No fuel types found.', 'لا توجد أنواع وقود.')"
                            clearable
                        />
                        <InputError :message="catalogEntryForm.errors.fuel_type" class="mt-1" />
                    </div>

                    <div>
                        <Label for="catalog-transmission-modal">{{ localize('Transmission', 'ناقل الحركة') }}</Label>
                        <SearchableSelect
                            v-model="catalogEntryForm.transmission"
                            :options="transmissionOptions"
                            :placeholder="localize('Select transmission', 'اختر ناقل الحركة')"
                            :search-placeholder="localize('Search transmission...', 'ابحث عن ناقل الحركة...')"
                            :empty-text="localize('No transmission types found.', 'لا توجد أنواع ناقل حركة.')"
                            clearable
                        />
                        <InputError :message="catalogEntryForm.errors.transmission" class="mt-1" />
                    </div>

                    <div>
                        <Label for="catalog-seats-modal">{{ localize('Seats', 'عدد المقاعد') }}</Label>
                        <Input id="catalog-seats-modal" v-model="catalogEntryForm.seats" type="number" min="1" max="20" :placeholder="localize('e.g., 5', 'مثال: 5')" />
                        <InputError :message="catalogEntryForm.errors.seats" class="mt-1" />
                    </div>

                    <div>
                        <Label for="catalog-engine-power-modal">{{ localize('Engine Power (HP)', 'قدرة المحرك (حصان)') }}</Label>
                        <Input id="catalog-engine-power-modal" v-model="catalogEntryForm.engine_power" type="number" min="1" :placeholder="localize('e.g., 150', 'مثال: 150')" />
                        <InputError :message="catalogEntryForm.errors.engine_power" class="mt-1" />
                    </div>

                    <DialogFooter class="md:col-span-2">
                        <Button type="button" variant="outline" :disabled="catalogSubmitting" @click="showCatalogModal = false">
                            {{ localize('Cancel', 'إلغاء') }}
                        </Button>
                        <Button type="submit" :disabled="catalogSubmitting">
                            {{ catalogSubmitting ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Model', 'حفظ الموديل') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-if="canCreateBranch" v-model:open="showBranchModal">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ localize('Create Branch', 'إنشاء فرع') }}</DialogTitle>
                    <DialogDescription>
                        {{ localize('Create a branch without leaving the car form.', 'أنشئ فرعًا بدون مغادرة نموذج السيارة.') }}
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="createBranchFromModal">
                    <div>
                        <Label for="branch-name-modal">{{ localize('Branch Name', 'اسم الفرع') }}</Label>
                        <Input id="branch-name-modal" v-model="branchForm.name" :placeholder="localize('e.g., Downtown Branch', 'مثال: فرع المركز')" />
                        <InputError :message="branchForm.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <Label for="branch-country-modal">{{ localize('Country', 'الدولة') }}</Label>
                        <SearchableSelect
                            v-model="branchForm.country"
                            :options="countryOptions"
                            :placeholder="localize('Select country', 'اختر الدولة')"
                            :search-placeholder="localize('Search country...', 'ابحث عن الدولة...')"
                            :empty-text="localize('No countries found.', 'لا توجد دول.')"
                        />
                        <InputError :message="branchForm.errors.country" class="mt-1" />
                    </div>

                    <div>
                        <Label for="branch-city-modal">{{ localize('City', 'المدينة') }}</Label>
                        <Input id="branch-city-modal" v-model="branchForm.city" :placeholder="localize('e.g., Gaza', 'مثال: غزة')" />
                        <InputError :message="branchForm.errors.city" class="mt-1" />
                    </div>

                    <div>
                        <Label for="branch-street-modal">{{ localize('Street Name', 'اسم الشارع') }}</Label>
                        <Input id="branch-street-modal" v-model="branchForm.street_name" :placeholder="localize('Street name', 'اسم الشارع')" />
                        <InputError :message="branchForm.errors.street_name" class="mt-1" />
                    </div>

                    <div>
                        <Label for="branch-cr-modal">{{ localize('CR Number', 'رقم السجل التجاري') }}</Label>
                        <Input id="branch-cr-modal" v-model="branchForm.cr_number" :placeholder="localize('Commercial registration number', 'رقم السجل التجاري')" />
                        <InputError :message="branchForm.errors.cr_number" class="mt-1" />
                    </div>

                    <div>
                        <Label for="branch-manager-name-modal">{{ localize('Manager Name', 'اسم المدير') }}</Label>
                        <Input id="branch-manager-name-modal" v-model="branchForm.manager_name" :placeholder="localize('Manager name', 'اسم المدير')" />
                        <InputError :message="branchForm.errors.manager_name" class="mt-1" />
                    </div>

                    <div>
                        <Label for="branch-manager-civil-modal">{{ localize('Manager Civil Number', 'رقم المدير المدني') }}</Label>
                        <Input id="branch-manager-civil-modal" v-model="branchForm.manager_civil_number" :placeholder="localize('Civil number', 'رقم المدير المدني')" />
                        <InputError :message="branchForm.errors.manager_civil_number" class="mt-1" />
                    </div>

                    <div>
                        <Label for="branch-phone-modal">{{ localize('Phone', 'الهاتف') }}</Label>
                        <Input id="branch-phone-modal" v-model="branchForm.phone_1" dir="ltr" class="text-left" :placeholder="localize('Phone number', 'رقم الهاتف')" />
                        <InputError :message="branchForm.errors.phone_1" class="mt-1" />
                    </div>

                    <div>
                        <Label for="branch-email-modal">{{ localize('Email', 'البريد الإلكتروني') }}</Label>
                        <Input id="branch-email-modal" v-model="branchForm.email" type="email" :placeholder="localize('Email address', 'البريد الإلكتروني')" />
                        <InputError :message="branchForm.errors.email" class="mt-1" />
                    </div>

                    <DialogFooter class="md:col-span-2">
                        <Button type="button" variant="outline" :disabled="branchSubmitting" @click="showBranchModal = false">
                            {{ localize('Cancel', 'إلغاء') }}
                        </Button>
                        <Button type="submit" :disabled="branchSubmitting">
                            {{ branchSubmitting ? localize('Creating...', 'جارٍ الإنشاء...') : localize('Create Branch', 'إنشاء فرع') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>



