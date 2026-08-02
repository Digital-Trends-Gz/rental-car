<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

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

const { t } = useTrans();
const translationRoot = 'dashboard.admin.cars.documents';
const translate = (key: string) => t(`${translationRoot}.${key}`);

const typeLabel = (type: string) => {
    if (type === 'license') return translate('types.license');
    if (type === 'insurance') return translate('types.insurance');
    if (type === 'purchase_contract') return translate('types.purchase_contract');
    return type;
};

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

const destroyDocument = (documentId: number) => {
    if (!confirm(translate('delete_confirm'))) return;

    router.delete(`/admin/cars/${props.car.id}/documents/${documentId}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="translate('title')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-sm text-muted-foreground">
                        {{ car.year }} {{ car.make }} {{ car.model }} - {{ car.license_plate }}
                    </div>
                    <h1 class="text-2xl font-semibold">{{ translate('title') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ translate('description') }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link :href="`/admin/cars/${car.id}/edit`">
                        <Button variant="outline">{{ translate('back_to_car') }}</Button>
                    </Link>
                    <Link :href="`/admin/cars/${car.id}/documents/create`">
                        <Button>+ {{ translate('new_document') }}</Button>
                    </Link>
                </div>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ translate('table.type') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ translate('table.number') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ translate('table.issuer') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ translate('table.purchase') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ translate('table.expiry') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ translate('table.status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ translate('table.front') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ translate('table.back') }}</th>
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
                                    {{ translate('open_image') }}
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
                                    {{ translate('open_image') }}
                                </a>
                                <span v-else>-</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link :href="`/admin/cars/${car.id}/documents/${document.id}/edit`">
                                        <Button size="sm" variant="outline">{{ translate('edit') }}</Button>
                                    </Link>
                                    <Button size="sm" variant="destructive" @click="destroyDocument(document.id)">
                                        {{ translate('delete') }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="documents.length === 0">
                            <td colspan="9" class="px-4 py-8 text-center text-sm text-muted-foreground">
                                {{ translate('empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </AdminLayout>
</template>
