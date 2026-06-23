<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Head, Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Trash } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    car: {
        id: number;
        make: string;
        model: string;
        year: number;
        license_plate: string;
        image_url: string | null;
        branch_name: string | null;
    };
    histories: Array<{
        id: number;
        reason: string;
        notes: string | null;
        user_name: string | null;
        created_at: string;
        photos_count: number;
        edit_url: string;
    }>;
}>();

const { t, locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const reasons = computed(() => [
    { value: 'before_delivery', label: localize('Before Delivery', 'قبل التسليم') },
    { value: 'after_return', label: localize('After Return', 'بعد الاستلام') },
    { value: 'new_damage', label: localize('New Damage', 'ضرر جديد') },
    { value: 'after_cleaning', label: localize('After Cleaning', 'بعد التنظيف') },
    { value: 'after_maintenance', label: localize('After Maintenance', 'بعد الصيانة') },
]);

const getReasonLabel = (reason: string) => {
    const found = reasons.value.find(r => r.value === reason);
    return found ? found.label : reason;
};

const getReasonColor = (reason: string) => {
    switch (reason) {
        case 'before_delivery': return 'bg-blue-100 text-blue-800';
        case 'after_return': return 'bg-green-100 text-green-800';
        case 'new_damage': return 'bg-red-100 text-red-800';
        case 'after_cleaning': return 'bg-teal-100 text-teal-800';
        case 'after_maintenance': return 'bg-purple-100 text-purple-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const deleteRecord = (id: number) => {
    if (confirm(localize('Are you sure you want to delete this record?', 'هل أنت متأكد من حذف هذا السجل؟'))) {
        router.delete(`/admin/cars/${props.car.id}/photo-histories/${id}`);
    }
};
</script>

<template>
    <Head :title="`${localize('Photo Histories', 'سجلات الصور')} - ${car.make} ${car.model}`" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ localize('Photo Histories', 'سجلات الصور') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ car.make }} {{ car.model }} ({{ car.year }}) - {{ car.license_plate }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link :href="`/admin/cars/${car.id}`">
                        <Button variant="outline">{{ localize('Back to Car', 'العودة للسيارة') }}</Button>
                    </Link>
                    <Link :href="`/admin/cars/${car.id}/photo-histories/create`">
                        <Button>{{ localize('New Record', 'سجل جديد') }}</Button>
                    </Link>
                </div>
            </div>

            <div class="mx-auto max-w-7xl">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg border">
                    <div v-if="histories.length === 0" class="p-12 text-center text-gray-500">
                        {{ localize('No photo histories found.', 'لا توجد سجلات صور.') }}
                    </div>
                    
                    <ul v-else class="divide-y divide-gray-200">
                        <li v-for="history in histories" :key="history.id" class="p-6 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', getReasonColor(history.reason)]">
                                            {{ getReasonLabel(history.reason) }}
                                        </span>
                                        <span class="text-sm font-medium text-gray-900">{{ history.photos_count }} {{ localize('Photos', 'صور') }}</span>
                                    </div>
                                    <div v-if="history.notes" class="text-sm text-gray-600 mt-1">
                                        {{ history.notes }}
                                    </div>
                                    <div class="text-xs text-gray-400 mt-2 flex items-center gap-2">
                                        <span>{{ history.user_name || localize('System', 'النظام') }}</span>
                                        <span>&bull;</span>
                                        <span>{{ history.created_at }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <Link :href="history.edit_url">
                                        <Button variant="outline" size="sm">{{ localize('Open', 'فتح') }}</Button>
                                    </Link>
                                    <Button variant="ghost" size="icon" class="text-red-500 hover:text-red-700 hover:bg-red-50" @click="deleteRecord(history.id)">
                                        <Trash class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </main>
    </AdminLayout>
</template>
