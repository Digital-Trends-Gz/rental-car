<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Tag, Edit, Trash2, CheckCircle, XCircle } from 'lucide-vue-next';
import { type Plan } from '@/types';
import { useTrans } from '@/composables/useTrans';
import { computed } from 'vue';

const props = defineProps<{
    plans: Plan[];
}>();
const { t, locale } = useTrans();

const customPricingLabel = computed(() =>
    locale.value.toLowerCase().startsWith('ar')
        ? 'مخصص'
        : t('plans_page.custom_pricing_label'),
);

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString(locale === 'ar' ? 'ar' : 'en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatLimit = (value: number | null | undefined) => {
    return value === null || value === undefined ? t('dashboard.super_admin.plans.index.unlimited') : String(value);
};

const formatTenantUsage = (count: number | null | undefined) => {
    const total = count ?? 0;
    return total === 0
        ? t('dashboard.super_admin.plans.index.no_tenants')
        : `${total} ${t('dashboard.super_admin.plans.index.tenants')}`;
};

const deletePlan = (planId: number, planName: string) => {
    if (confirm(`Are you sure you want to delete the plan "${planName}"?`)) {
        router.delete(`/superadmin/plans/${planId}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="t('dashboard.super_admin.plans.index.head_title')" />
    <SuperAdminLayout>
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ t('dashboard.super_admin.plans.index.title') }}</h1>
                <Link href="/superadmin/plans/create">
                    <Button>+ {{ t('dashboard.super_admin.plans.index.create_plan') }}</Button>
                </Link>
            </div>

            <p class="text-muted-foreground text-sm">
                {{ t('dashboard.super_admin.plans.index.subtitle') }}
            </p>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.super_admin.plans.index.plan') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.super_admin.plans.index.prices') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.super_admin.plans.index.limits') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.super_admin.plans.index.features') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.created') }}</th>
                            <th class="px-4 py-3 text-right">{{ t('dashboard.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="plan in plans" :key="plan.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                        <Tag class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ plan.name }}</div>
                                        <div class="text-xs text-muted-foreground line-clamp-1 max-w-xs">{{ plan.description }}</div>
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ formatTenantUsage(plan.tenants_count) }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-700">
                                {{ plan.sort_order }}
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="plan.custom_pricing">
                                    <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-700">
                                        {{ customPricingLabel }}
                                    </span>
                                </div>
                                <div v-else class="text-sm space-y-1">
                                    <div v-if="plan.monthly_price">
                                        <span class="font-medium text-xs uppercase text-gray-400">{{ t('dashboard.super_admin.plans.index.monthly') }}:</span>
                                        ${{ plan.monthly_price }}
                                    </div>
                                    <div v-if="plan.yearly_price">
                                        <span class="font-medium text-xs uppercase text-gray-400">{{ t('dashboard.super_admin.plans.index.yearly') }}:</span>
                                        ${{ plan.yearly_price }}
                                    </div>
                                    <div v-if="plan.one_time_price">
                                        <span class="font-medium text-xs uppercase text-gray-400">{{ t('dashboard.super_admin.plans.index.one_time') }}:</span>
                                        ${{ plan.one_time_price }}
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="space-y-1 text-xs text-gray-600">
                                    <div>Employees: {{ formatLimit(plan.max_employees) }}</div>
                                    <div>Branches: {{ formatLimit(plan.max_branches) }}</div>
                                    <div>Cars: {{ formatLimit(plan.max_cars) }}</div>
                                    <div>Reservations/month: {{ formatLimit(plan.max_reservations_per_month) }}</div>
                                    <div>Contracts/month: {{ formatLimit(plan.max_contracts) }}</div>
                                    <div>OpenAI/day: {{ formatLimit(plan.openai_requests_per_day) }}</div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-xs text-gray-600">
                                    {{ plan.features?.length || 0 }} {{ t('dashboard.super_admin.plans.index.features') }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="plan.is_active" class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">
                                    <CheckCircle class="h-3 w-3" />
                                    {{ t('dashboard.common.active') }}
                                </span>
                                <span v-else class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700">
                                    <XCircle class="h-3 w-3" />
                                    {{ t('dashboard.common.inactive') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                {{ formatDate(plan.created_at) }}
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <Link :href="`/superadmin/plans/${plan.id}/edit`">
                                    <Button variant="outline" size="sm">
                                        <Edit class="h-4 w-4 mr-1" />
                                        {{ t('dashboard.common.edit') }}
                                    </Button>
                                </Link>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    @click="deletePlan(plan.id, plan.name)"
                                >
                                    <Trash2 class="h-4 w-4 mr-1" />
                                    {{ t('dashboard.common.delete') }}
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="plans.length === 0">
                            <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                                {{ t('dashboard.super_admin.plans.index.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </SuperAdminLayout>
</template>
