<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    car: {
        id: number;
        year: number | string;
        make: string;
        model: string;
        license_plate: string;
        branch_name?: string | null;
    };
    documents: Array<{
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
        status_key: string;
        days_remaining?: number | null;
        front_image_url?: string | null;
        back_image_url?: string | null;
    }>;
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const typeLabel = (type: string) => {
    if (type === 'license') return localize('Car License', 'رخصة السيارة');
    if (type === 'insurance') return localize('Car Insurance', 'تأمين السيارة');
    if (type === 'purchase_contract') return localize('Purchase Contract', 'عقد الشراء');
    return type;
};

const statusLabel = (status: string) => {
    if (status === 'expired') return localize('Expired', 'منتهي');
    if (status === 'expiring_soon') return localize('Expiring Soon', 'قريب الانتهاء');
    if (status === 'new') return localize('New', 'جديد');
    if (status === 'inactive') return localize('Inactive', 'غير نشط');
    return localize('Active', 'فعّال');
};

const statusClasses = (status: string) => {
    if (status === 'expired') return 'bg-red-100 text-red-700';
    if (status === 'expiring_soon') return 'bg-amber-100 text-amber-700';
    if (status === 'new') return 'bg-blue-100 text-blue-700';
    if (status === 'inactive') return 'bg-gray-100 text-gray-600';
    return 'bg-green-100 text-green-700';
};

const destroyDocument = (documentId: number) => {
    if (!confirm(localize('Delete this document?', 'حذف هذا المستند؟'))) return;

    router.delete(`/admin/cars/${props.car.id}/documents/${documentId}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="localize('Car Documents', 'وثائق السيارة')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-sm text-muted-foreground">
                        {{ car.year }} {{ car.make }} {{ car.model }} • {{ car.license_plate }}
                    </div>
                    <h1 class="text-2xl font-semibold">{{ localize('Car Documents', 'وثائق السيارة') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Manage license, insurance, and purchase contract documents for this car.', 'إدارة وثائق الرخصة والتأمين وعقد الشراء لهذه السيارة.') }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link :href="`/admin/cars/${car.id}/edit`">
                        <Button variant="outline">{{ localize('Back to car', 'العودة للسيارة') }}</Button>
                    </Link>
                    <Link :href="`/admin/cars/${car.id}/documents/create`">
                        <Button>+ {{ localize('New Document', 'مستند جديد') }}</Button>
                    </Link>
                </div>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ localize('Type', 'النوع') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ localize('Number', 'الرقم') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ localize('Issuer', 'جهة الإصدار') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ localize('Purchase', 'الشراء') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ localize('Expiry', 'الانتهاء') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ localize('Status', 'الحالة') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ localize('Front', 'الأمام') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ localize('Back', 'الخلف') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="document in documents" :key="document.id">
                            <td class="px-4 py-3">{{ typeLabel(document.type) }}</td>
                            <td class="px-4 py-3">{{ document.document_number || '-' }}</td>
                            <td class="px-4 py-3">{{ document.issuer || '-' }}</td>
                            <td class="px-4 py-3">{{ document.purchase_date || '-' }}</td>
                            <td class="px-4 py-3">{{ document.expiry_date || '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClasses(document.status_key)">
                                    {{ statusLabel(document.status_key) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <a
                                    v-if="document.front_image_url"
                                    :href="document.front_image_url"
                                    target="_blank"
                                    class="text-sm text-primary underline"
                                >
                                    {{ localize('Open image', 'فتح الصورة') }}
                                </a>
                                <span v-else>-</span>
                            </td>
                            <td class="px-4 py-3">
                                <a
                                    v-if="document.back_image_url"
                                    :href="document.back_image_url"
                                    target="_blank"
                                    class="text-sm text-primary underline"
                                >
                                    {{ localize('Open image', 'فتح الصورة') }}
                                </a>
                                <span v-else>-</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link :href="`/admin/cars/${car.id}/documents/${document.id}/edit`">
                                        <Button size="sm" variant="outline">{{ localize('Edit', 'تعديل') }}</Button>
                                    </Link>
                                    <Button size="sm" variant="destructive" @click="destroyDocument(document.id)">
                                        {{ localize('Delete', 'حذف') }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="documents.length === 0">
                            <td colspan="9" class="px-4 py-8 text-center text-sm text-muted-foreground">
                                {{ localize('No car documents found yet.', 'لا توجد وثائق سيارة بعد.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </AdminLayout>
</template>
