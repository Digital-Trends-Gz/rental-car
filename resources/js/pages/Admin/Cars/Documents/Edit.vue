<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    car: {
        id: number;
        year: number | string;
        make: string;
        model: string;
        license_plate: string;
        branch_name?: string | null;
    };
    document: {
        id: number;
        type: string;
        document_number?: string | null;
        issuer?: string | null;
        issue_date?: string | null;
        purchase_date?: string | null;
        expiry_date?: string | null;
        cost?: string | number | null;
        notes?: string | null;
        is_active: boolean;
        status_key?: string;
        days_remaining?: number | null;
    } | null;
    frontImageFiles: Array<{ id: number; url: string }>;
    backImageFiles: Array<{ id: number; url: string }>;
    documentTypes: Array<{ value: string; label: string }>;
}>();

const isEdit = computed(() => !!props.document);
const { t } = useTrans();
const translationRoot = 'dashboard.admin.cars.documents';
const translate = (key: string) => t(`${translationRoot}.${key}`);

const localizedDocumentTypes = computed(() =>
    props.documentTypes.map((item) => ({
        ...item,
        label:
            item.value === 'license'
                ? translate('types.license')
                : item.value === 'insurance'
                  ? translate('types.insurance')
                  : item.value === 'purchase_contract'
                    ? translate('types.purchase_contract')
                    : item.label,
    })),
);

const photoAllowedFileTypes = ['image/jpeg', 'image/png'];

function formatDateInput(value: Date): string {
    const offset = value.getTimezoneOffset() * 60000;
    return new Date(value.getTime() - offset).toISOString().slice(0, 10);
}

function addDaysToDateInput(value: string, days: number): string {
    const next = new Date(`${value}T00:00:00`);
    next.setDate(next.getDate() + days);
    return formatDateInput(next);
}

const form = useForm({
    type: props.document?.type ?? 'license',
    document_number: props.document?.document_number ?? '',
    issuer: props.document?.issuer ?? '',
    issue_date: props.document?.issue_date ?? '',
    purchase_date: props.document?.purchase_date ?? '',
    expiry_date: props.document?.expiry_date ?? '',
    cost: props.document?.cost ? String(props.document.cost) : '',
    notes: props.document?.notes ?? '',
    is_active: props.document?.is_active ?? true,
    front_image_temp_folders: [] as string[],
    front_image_removed_files: [] as number[],
    back_image_temp_folders: [] as string[],
    back_image_removed_files: [] as number[],
});

function onFrontImageUpdate(tempFoldersList: string[]) {
    form.front_image_temp_folders = tempFoldersList;
}

function onFrontImageRemoved(payload: { fileId?: number; type: string }) {
    if (payload.type === 'existing' && payload.fileId) {
        if (!form.front_image_removed_files.includes(payload.fileId)) {
            form.front_image_removed_files.push(payload.fileId);
        }
    }
}

function onBackImageUpdate(tempFoldersList: string[]) {
    form.back_image_temp_folders = tempFoldersList;
}

function onBackImageRemoved(payload: { fileId?: number; type: string }) {
    if (payload.type === 'existing' && payload.fileId) {
        if (!form.back_image_removed_files.includes(payload.fileId)) {
            form.back_image_removed_files.push(payload.fileId);
        }
    }
}

const isPurchaseContract = computed(() => form.type === 'purchase_contract');
const expiryDateMin = computed(() => {
    if (isPurchaseContract.value || !form.issue_date) {
        return '';
    }

    return addDaysToDateInput(form.issue_date, 1);
});

const previewStatusKey = computed(() => {
    if (!form.is_active) return 'inactive';

    if (isPurchaseContract.value) return 'active';

    if (form.expiry_date) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const expiry = new Date(form.expiry_date);
        expiry.setHours(0, 0, 0, 0);

        const diffDays = Math.floor((expiry.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

        if (diffDays < 0) return 'expired';
        if (diffDays <= 10) return 'expiring_soon';
    }

    return isEdit.value ? (props.document?.status_key ?? 'active') : 'new';
});

const statusLabel = (status: string) => {
    if (status === 'expired') return translate('statuses.expired');
    if (status === 'expiring_soon') return translate('statuses.expiring_soon');
    if (status === 'new') return translate('statuses.new');
    if (status === 'inactive') return translate('statuses.inactive');
    return translate('statuses.active');
};

const statusClasses = (status: string) => {
    if (status === 'expired') return 'bg-red-100 text-red-700';
    if (status === 'expiring_soon') return 'bg-amber-100 text-amber-700';
    if (status === 'new') return 'bg-blue-100 text-blue-700';
    if (status === 'inactive') return 'bg-gray-100 text-gray-600';
    return 'bg-green-100 text-green-700';
};

const pageTitle = computed(() => (isEdit.value ? translate('form.edit_title') : translate('form.create_title')));

const submit = () => {
    const url = isEdit.value
        ? `/admin/cars/${props.car.id}/documents/${props.document!.id}`
        : `/admin/cars/${props.car.id}/documents`;

    if (isEdit.value) {
        form.put(url);
        return;
    }

    form.post(url);
};
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-sm text-muted-foreground">
                        {{ car.year }} {{ car.make }} {{ car.model }} - {{ car.license_plate }}
                    </div>
                    <h1 class="text-2xl font-semibold">
                        {{ pageTitle }}
                    </h1>
                    <div class="mt-2">
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClasses(previewStatusKey)">
                            {{ translate('table.status') }}: {{ statusLabel(previewStatusKey) }}
                        </span>
                    </div>
                </div>

                <Link :href="`/admin/cars/${car.id}/documents`">
                    <Button variant="outline">{{ translate('form.back') }}</Button>
                </Link>
            </div>

            <form class="space-y-6 rounded-lg border bg-card p-6" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <Label for="type">{{ translate('form.document_type') }}</Label>
                        <select id="type" v-model="form.type" class="w-full rounded-md border border-input bg-transparent px-3 py-2">
                            <option v-for="type in localizedDocumentTypes" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.type" class="mt-1" />
                    </div>

                    <div>
                        <Label for="document_number">{{ translate('form.document_number') }}</Label>
                        <Input id="document_number" v-model="form.document_number" />
                        <InputError :message="form.errors.document_number" class="mt-1" />
                    </div>

                    <div>
                        <Label for="issuer">{{ translate('table.issuer') }}</Label>
                        <Input id="issuer" v-model="form.issuer" />
                        <InputError :message="form.errors.issuer" class="mt-1" />
                    </div>

                    <div>
                        <Label for="cost">{{ translate('form.cost') }}</Label>
                        <Input id="cost" v-model="form.cost" type="number" min="0" step="0.01" />
                        <InputError :message="form.errors.cost" class="mt-1" />
                    </div>

                    <div v-if="!isPurchaseContract">
                        <Label for="issue_date">{{ translate('form.issue_date') }}</Label>
                        <Input id="issue_date" v-model="form.issue_date" type="date" />
                        <InputError :message="form.errors.issue_date" class="mt-1" />
                    </div>

                    <div v-if="!isPurchaseContract">
                        <Label for="expiry_date">{{ translate('form.expiry_date') }}</Label>
                        <Input id="expiry_date" v-model="form.expiry_date" type="date" :min="expiryDateMin" />
                        <InputError :message="form.errors.expiry_date" class="mt-1" />
                    </div>

                    <div v-if="isPurchaseContract">
                        <Label for="purchase_date">{{ translate('form.purchase_date') }}</Label>
                        <Input id="purchase_date" v-model="form.purchase_date" type="date" />
                        <InputError :message="form.errors.purchase_date" class="mt-1" />
                    </div>
                </div>

                <div>
                    <Label for="notes">{{ translate('form.notes') }}</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        class="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2"
                    />
                    <InputError :message="form.errors.notes" class="mt-1" />
                </div>

                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <input id="is_active" v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-input" />
                        <Label for="is_active">{{ translate('form.document_is_active') }}</Label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label>{{ translate('form.front_image') }}</Label>
                            <FileUpload
                                :initial-files="frontImageFiles"
                                :allowed-file-types="photoAllowedFileTypes"
                                :allow-multiple="false"
                                :max-files="1"
                                @update:modelValue="onFrontImageUpdate"
                                @fileRemoved="onFrontImageRemoved"
                            />
                            <InputError :message="form.errors.front_image_temp_folders" class="mt-1" />
                        </div>

                        <div>
                            <Label>{{ translate('form.back_image') }}</Label>
                            <FileUpload
                                :initial-files="backImageFiles"
                                :allowed-file-types="photoAllowedFileTypes"
                                :allow-multiple="false"
                                :max-files="1"
                                @update:modelValue="onBackImageUpdate"
                                @fileRemoved="onBackImageRemoved"
                            />
                            <InputError :message="form.errors.back_image_temp_folders" class="mt-1" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <Link :href="`/admin/cars/${car.id}/documents`">
                        <Button type="button" variant="outline">{{ translate('form.cancel') }}</Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing">
                        {{ isEdit ? translate('form.save_changes') : translate('form.create_document') }}
                    </Button>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
