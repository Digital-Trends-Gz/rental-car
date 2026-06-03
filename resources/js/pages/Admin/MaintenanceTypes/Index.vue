<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { computed, ref, watch } from 'vue';
import { useTrans } from '@/composables/useTrans';

const { t } = useTrans();

const props = defineProps<{
    maintenanceTypes: {
        data: Array<{
            id: number;
            name: string;
            description: string | null;
            is_active: boolean;
            sort_order: number;
            workshops_count: number;
            edit_url: string;
            destroy_url: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search?: string;
    };
    indexUrl: string;
    createUrl: string;
}>();

const search = ref(props.filters?.search || '');

const hasRows = computed(() => props.maintenanceTypes.data.length > 0);

function doSearch() {
    router.get(
        props.indexUrl,
        { search: search.value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

watch(search, (newValue, oldValue) => {
    if (newValue === '' && oldValue !== '') {
        doSearch();
    }
});

function destroyType(url: string, name: string) {
    const confirmed = window.confirm(`${t('dashboard.common.delete')} "${name}"?`);
    if (!confirmed) return;

    router.delete(url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('dashboard.admin.maintenance_types.index.head_title')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ t('dashboard.admin.maintenance_types.index.title') }}</h1>
                <Link :href="createUrl">
                    <Button>+ {{ t('dashboard.admin.maintenance_types.index.new_type') }}</Button>
                </Link>
            </div>

            <div class="flex items-center gap-2">
                <Input
                    v-model="search"
                    class="max-w-md"
                    :placeholder="t('dashboard.admin.maintenance_types.index.search_placeholder')"
                    @keyup.enter="doSearch"
                />
                <Button @click="doSearch">{{ t('dashboard.admin.maintenance_types.index.search') }}</Button>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('dashboard.admin.maintenance_types.table.name') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('dashboard.admin.maintenance_types.table.description') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('dashboard.admin.maintenance_types.table.status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('dashboard.admin.maintenance_types.table.workshops') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('dashboard.admin.maintenance_types.table.sort') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="row in maintenanceTypes.data" :key="row.id">
                            <td class="px-4 py-3 font-medium">{{ row.name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ row.description || '-' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded px-2 py-1 text-xs font-medium"
                                    :class="row.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ row.is_active ? t('dashboard.admin.maintenance_types.status.active') : t('dashboard.admin.maintenance_types.status.inactive') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ row.workshops_count }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ row.sort_order }}</td>
                            <td class="space-x-2 px-4 py-3 text-right">
                                <Link :href="row.edit_url">
                                    <Button size="sm" variant="outline">{{ t('dashboard.common.edit') }}</Button>
                                </Link>
                                <Button size="sm" variant="destructive" @click="destroyType(row.destroy_url, row.name)">{{ t('dashboard.common.delete') }}</Button>
                            </td>
                        </tr>
                        <tr v-if="!hasRows">
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ t('dashboard.admin.maintenance_types.index.empty') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="maintenanceTypes.links?.length" class="flex gap-2">
                <Link
                    v-for="(link, i) in maintenanceTypes.links"
                    :key="i"
                    :href="link.url || ''"
                    :class="[
                        'rounded px-3 py-1 text-sm',
                        link.active ? 'bg-primary text-primary-foreground' : 'bg-gray-100 text-gray-700',
                        !link.url && 'pointer-events-none opacity-50',
                    ]"
                >
                    <span v-html="link.label" />
                </Link>
            </nav>
        </main>
    </AdminLayout>
</template>
