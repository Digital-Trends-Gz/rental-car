<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import FilePondUpload from '@/components/ViltFilePond/FileUpload.vue';
import { computed } from 'vue';

const { car, history, imageFiles } = defineProps<{
    car: {
        id: number;
        make: string;
        model: string;
        year: number;
        license_plate: string;
    };
    history: {
        id: number;
        reason: string;
        notes: string | null;
    } | null;
    imageFiles: Array<{ id: number; url: string }>;
}>();

const { t } = useTrans();

const isEditing = computed(() => !!history);

const form = useForm({
    reason: history?.reason || '',
    notes: history?.notes || '',
    photos_temp_folders: [] as string[],
    photos_removed_files: [] as number[],
});

const reasons = computed(() => [
    { value: 'before_delivery', label: t('dashboard.admin.cars.photo_history.reason_before_delivery') },
    { value: 'after_return', label: t('dashboard.admin.cars.photo_history.reason_after_return') },
    { value: 'new_damage', label: t('dashboard.admin.cars.photo_history.reason_new_damage') },
    { value: 'after_cleaning', label: t('dashboard.admin.cars.photo_history.reason_after_cleaning') },
    { value: 'after_maintenance', label: t('dashboard.admin.cars.photo_history.reason_after_maintenance') },
]);

const handleFileRemoved = (data: { type: string; fileId?: number }) => {
    if (data.type === 'existing' && data.fileId) {
        form.photos_removed_files.push(data.fileId);
    }
};

const submit = () => {
    if (isEditing.value && history) {
        form.put(`/admin/cars/${car.id}/photo-histories/${history.id}`);
    } else {
        form.post(`/admin/cars/${car.id}/photo-histories`);
    }
};
</script>

<template>
    <Head :title="`${isEditing ? t('dashboard.admin.cars.photo_history.edit_record') : t('dashboard.admin.cars.photo_history.new_record')} - ${car.make} ${car.model}`" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ isEditing ? t('dashboard.admin.cars.photo_history.edit_record') : t('dashboard.admin.cars.photo_history.new_record') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ car.make }} {{ car.model }} ({{ car.year }}) - {{ car.license_plate }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link :href="`/admin/cars/${car.id}/photo-histories`">
                        <Button variant="outline">{{ t('dashboard.admin.cars.photo_history.back_to_history') }}</Button>
                    </Link>
                </div>
            </div>

            <div class="mx-auto max-w-3xl">
                <Card>
                    <CardContent class="p-6">
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="grid gap-2">
                                <Label htmlFor="reason">{{ t('dashboard.admin.cars.photo_history.reason') }} <span class="text-red-500">*</span></Label>
                                <select 
                                    id="reason" 
                                    v-model="form.reason" 
                                    class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="" disabled>{{ t('dashboard.admin.cars.photo_history.select_reason') }}</option>
                                    <option v-for="r in reasons" :key="r.value" :value="r.value">
                                        {{ r.label }}
                                    </option>
                                </select>
                                <p v-if="form.errors.reason" class="text-sm text-red-500">{{ form.errors.reason }}</p>
                            </div>

                            <div class="grid gap-2">
                                <Label>{{ t('dashboard.admin.cars.photo_history.photos') }}</Label>
                                <FilePondUpload
                                    v-model="form.photos_temp_folders"
                                    :initial-files="imageFiles"
                                    :allow-multiple="true"
                                    :max-files="20"
                                    collection="photos"
                                    theme="light"
                                    width="100%"
                                    @file-removed="handleFileRemoved"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label htmlFor="notes">{{ t('dashboard.admin.cars.photo_history.notes_optional') }}</Label>
                                <Textarea 
                                    id="notes" 
                                    v-model="form.notes" 
                                    :placeholder="t('dashboard.admin.cars.photo_history.notes_placeholder')" 
                                    rows="4"
                                />
                                <p v-if="form.errors.notes" class="text-sm text-red-500">{{ form.errors.notes }}</p>
                            </div>

                            <div class="flex justify-end gap-3">
                                <Link :href="`/admin/cars/${car.id}/photo-histories`">
                                    <Button type="button" variant="outline">{{ t('dashboard.admin.cars.photo_history.cancel') }}</Button>
                                </Link>
                                <Button type="submit" :disabled="form.processing">
                                    {{ t('dashboard.admin.cars.photo_history.save') }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </main>
    </AdminLayout>
</template>
