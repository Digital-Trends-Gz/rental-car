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

const { t, locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const isEditing = computed(() => !!history);

const form = useForm({
    reason: history?.reason || '',
    notes: history?.notes || '',
    photos_temp_folders: [] as string[],
    photos_removed_files: [] as number[],
});

const reasons = computed(() => [
    { value: 'before_delivery', label: localize('Before Delivery', 'قبل التسليم') },
    { value: 'after_return', label: localize('After Return', 'بعد الاستلام') },
    { value: 'new_damage', label: localize('New Damage', 'ضرر جديد') },
    { value: 'after_cleaning', label: localize('After Cleaning', 'بعد التنظيف') },
    { value: 'after_maintenance', label: localize('After Maintenance', 'بعد الصيانة') },
]);

const handleFileRemoved = (data: { type: string; fileId?: number }) => {
    if (data.type === 'existing' && data.fileId) {
        form.photos_removed_files.push(data.fileId);
    }
};

const submit = () => {
    if (isEditing.value) {
        form.put(`/admin/cars/${car.id}/photo-histories/${history.id}`);
    } else {
        form.post(`/admin/cars/${car.id}/photo-histories`);
    }
};
</script>

<template>
    <Head :title="`${isEditing ? localize('Edit Record', 'تعديل السجل') : localize('New Record', 'سجل جديد')} - ${car.make} ${car.model}`" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ isEditing ? localize('Edit Record', 'تعديل السجل') : localize('New Record', 'سجل جديد') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ car.make }} {{ car.model }} ({{ car.year }}) - {{ car.license_plate }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link :href="`/admin/cars/${car.id}/photo-histories`">
                        <Button variant="outline">{{ localize('Back to History', 'العودة للسجلات') }}</Button>
                    </Link>
                </div>
            </div>

            <div class="mx-auto max-w-3xl">
                <Card>
                    <CardContent class="p-6">
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="grid gap-2">
                                <Label htmlFor="reason">{{ localize('Reason', 'السبب') }} <span class="text-red-500">*</span></Label>
                                <select 
                                    id="reason" 
                                    v-model="form.reason" 
                                    class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="" disabled>{{ localize('Select Reason', 'اختر السبب') }}</option>
                                    <option v-for="r in reasons" :key="r.value" :value="r.value">
                                        {{ r.label }}
                                    </option>
                                </select>
                                <p v-if="form.errors.reason" class="text-sm text-red-500">{{ form.errors.reason }}</p>
                            </div>

                            <div class="grid gap-2">
                                <Label>{{ localize('Photos', 'الصور') }}</Label>
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
                                <Label htmlFor="notes">{{ localize('Notes (Optional)', 'ملاحظات (اختياري)') }}</Label>
                                <Textarea 
                                    id="notes" 
                                    v-model="form.notes" 
                                    :placeholder="localize('Enter notes here...', 'اكتب ملاحظات هنا...')" 
                                    rows="4"
                                />
                                <p v-if="form.errors.notes" class="text-sm text-red-500">{{ form.errors.notes }}</p>
                            </div>

                            <div class="flex justify-end gap-3">
                                <Link :href="`/admin/cars/${car.id}/photo-histories`">
                                    <Button type="button" variant="outline">{{ localize('Cancel', 'إلغاء') }}</Button>
                                </Link>
                                <Button type="submit" :disabled="form.processing">
                                    {{ localize('Save', 'حفظ') }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </main>
    </AdminLayout>
</template>
