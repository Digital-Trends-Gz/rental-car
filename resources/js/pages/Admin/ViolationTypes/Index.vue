<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    violationTypes: {
        data: Array<{
            id: number;
            name: string;
            description: string | null;
            is_active: boolean;
            sort_order: number;
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
const hasRows = computed(() => props.violationTypes.data.length > 0);

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
    const confirmed = window.confirm(`Delete violation type "${name}"?`);
    if (!confirmed) return;

    router.delete(url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Violation Types" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">Violation Types</h1>
                <Link :href="createUrl">
                    <Button>+ New Type</Button>
                </Link>
            </div>

            <div class="flex items-center gap-2">
                <Input
                    v-model="search"
                    class="max-w-md"
                    placeholder="Search by name or description..."
                    @keyup.enter="doSearch"
                />
                <Button @click="doSearch">Search</Button>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Description</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Sort</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="row in violationTypes.data" :key="row.id">
                            <td class="px-4 py-3 font-medium">{{ row.name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ row.description || '-' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded px-2 py-1 text-xs font-medium"
                                    :class="row.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ row.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ row.sort_order }}</td>
                            <td class="space-x-2 px-4 py-3 text-right">
                                <Link :href="row.edit_url">
                                    <Button size="sm" variant="outline">Edit</Button>
                                </Link>
                                <Button size="sm" variant="destructive" @click="destroyType(row.destroy_url, row.name)">Delete</Button>
                            </td>
                        </tr>
                        <tr v-if="!hasRows">
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">No violation types found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="violationTypes.links?.length" class="flex gap-2">
                <Link
                    v-for="(link, i) in violationTypes.links"
                    :key="i"
                    :href="link.url || ''"
                    :class="[
                        'rounded px-3 py-1 text-sm',
                        link.active ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700',
                        !link.url && 'pointer-events-none opacity-50',
                    ]"
                >
                    <span v-html="link.label" />
                </Link>
            </nav>
        </main>
    </AdminLayout>
</template>
