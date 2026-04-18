<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    coupons: {
        data: Array<{
            id: number;
            name: string;
            code: string;
            car: string;
            type: string;
            value: number;
            is_active: boolean;
            usage_limit: number | null;
            used_count: number;
            starts_at: string | null;
            ends_at: string | null;
            edit_url: string;
            delete_url: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    cars: Array<{ id: number; label: string }>;
    filters: {
        search?: string;
        status?: string;
        car_id?: number | null;
    };
    indexUrl: string;
    createUrl: string;
}>();

const { t } = useTrans();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');
const carId = ref(props.filters.car_id ? String(props.filters.car_id) : 'all');

const hasFilters = computed(() => search.value !== '' || status.value !== 'all' || carId.value !== 'all');

function applyFilters() {
    router.get(
        props.indexUrl,
        {
            search: search.value || null,
            status: status.value,
            car_id: carId.value === 'all' ? null : Number(carId.value),
        },
        { preserveState: true, replace: true },
    );
}

function clearFilters() {
    search.value = '';
    status.value = 'all';
    carId.value = 'all';
    applyFilters();
}

function deleteCoupon(deleteUrl: string) {
    if (!confirm(t('dashboard.admin.coupons.index.delete_confirm'))) {
        return;
    }

    router.delete(deleteUrl);
}

function formatValue(type: string, value: number): string {
    if (type === 'percentage') {
        return `${value}%`;
    }

    return `$${value.toFixed(2)}`;
}
</script>

<template>
    <Head :title="t('dashboard.admin.coupons.index.head_title')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ t('dashboard.admin.coupons.index.title') }}</h1>
                    <p class="text-sm text-muted-foreground">{{ t('dashboard.admin.coupons.index.subtitle') }}</p>
                </div>
                <Link :href="createUrl">
                    <Button>{{ t('dashboard.admin.coupons.index.new_coupon') }}</Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Input v-model="search" class="max-w-md" :placeholder="t('dashboard.admin.coupons.index.search_placeholder')" @keyup.enter="applyFilters" />
                <select v-model="status" class="h-10 rounded-md border border-input bg-background px-3 text-sm" @change="applyFilters">
                    <option value="all">{{ t('dashboard.admin.coupons.index.all_statuses') }}</option>
                    <option value="active">{{ t('dashboard.admin.coupons.index.active') }}</option>
                    <option value="inactive">{{ t('dashboard.admin.coupons.index.inactive') }}</option>
                </select>
                <select v-model="carId" class="h-10 rounded-md border border-input bg-background px-3 text-sm" @change="applyFilters">
                    <option value="all">{{ t('dashboard.admin.coupons.index.all_cars') }}</option>
                    <option v-for="car in cars" :key="car.id" :value="String(car.id)">
                        {{ car.label }}
                    </option>
                </select>
                <Button @click="applyFilters">{{ t('dashboard.admin.coupons.index.search') }}</Button>
                <Button v-if="hasFilters" variant="outline" @click="clearFilters">{{ t('dashboard.admin.coupons.index.clear') }}</Button>
            </div>

            <div class="overflow-x-auto rounded-lg border bg-card">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b bg-muted/30 text-left text-xs uppercase text-muted-foreground">
                            <th class="px-4 py-3">{{ t('dashboard.admin.coupons.index.table.code') }}</th>
                            <th class="px-4 py-3">{{ t('dashboard.admin.coupons.index.table.name') }}</th>
                            <th class="px-4 py-3">{{ t('dashboard.admin.coupons.index.table.scope') }}</th>
                            <th class="px-4 py-3">{{ t('dashboard.admin.coupons.index.table.value') }}</th>
                            <th class="px-4 py-3">{{ t('dashboard.admin.coupons.index.table.usage') }}</th>
                            <th class="px-4 py-3">{{ t('dashboard.admin.coupons.index.table.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="coupon in coupons.data" :key="coupon.id" class="border-b last:border-b-0">
                            <td class="px-4 py-3 text-sm font-semibold">{{ coupon.code }}</td>
                            <td class="px-4 py-3 text-sm">{{ coupon.name }}</td>
                            <td class="px-4 py-3 text-sm">{{ coupon.car }}</td>
                            <td class="px-4 py-3 text-sm">{{ formatValue(coupon.type, coupon.value) }}</td>
                            <td class="px-4 py-3 text-sm">
                                {{ coupon.used_count }} / {{ coupon.usage_limit ?? '∞' }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                    :class="coupon.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'"
                                >
                                    {{ coupon.is_active ? t('dashboard.admin.coupons.index.active') : t('dashboard.admin.coupons.index.inactive') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm">
                                <Link :href="coupon.edit_url" class="mr-3 text-primary hover:underline">{{ t('dashboard.admin.coupons.index.edit') }}</Link>
                                <button type="button" class="text-red-600 hover:underline" @click="deleteCoupon(coupon.delete_url)">{{ t('dashboard.admin.coupons.index.delete') }}</button>
                            </td>
                        </tr>
                        <tr v-if="coupons.data.length === 0">
                            <td colspan="7" class="px-4 py-6 text-center text-sm text-muted-foreground">{{ t('dashboard.admin.coupons.index.empty') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </AdminLayout>
</template>
