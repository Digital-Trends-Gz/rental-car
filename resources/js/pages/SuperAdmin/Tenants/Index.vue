<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import{ ref } from 'vue';
import { Building2, Mail, Phone, CreditCard, CheckCircle, XCircle } from 'lucide-vue-next';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    tenants: {
        data: Array<{
            id: number;
            name: string;
            slug: string;
            domain: string | null;
            email: string | null;
            phone: string | null;
            plan_id: number | null;
            subscription_plan?: { id: number; name: string } | null;
            is_active: boolean;
            users_count?: number;
            cars_count?: number;
            reservations_count?: number;
            created_at: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();
const { t, locale } = useTrans();

const search = ref('');

function doSearch() {
    router.get('/superadmin/tenants', { search: search.value }, {
        preserveState: true,
        replace: true,
    });
}

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString(locale === 'ar' ? 'ar' : 'en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatLimit = (value: number | null | undefined) => {
    return value === null || value === undefined
        ? t('dashboard.super_admin.plans.index.unlimited')
        : String(value);
};

const formatUsage = (used: number | null | undefined, limit: number | null | undefined) => {
    const current = used ?? 0;
    return `${current} / ${formatLimit(limit)}`;
};

const planColors: Record<string, string> = {
    basic: 'bg-gray-100 text-gray-700',
    pro: 'bg-blue-100 text-blue-700',
    enterprise: 'bg-purple-100 text-purple-700',
};
</script>

<template>
    <Head :title="t('dashboard.super_admin.tenants.index.head_title')" />
    <SuperAdminLayout>
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ t('dashboard.super_admin.tenants.index.title') }}</h1>
                <Link href="/superadmin/tenants/create">
                    <Button>
                        + {{ t('dashboard.super_admin.tenants.index.new_tenant') }}
                    </Button>
                </Link>
            </div>

            <div class="flex items-center gap-2">
                <Input
                    v-model="search"
                    :placeholder="t('dashboard.super_admin.tenants.index.search_placeholder')"
                    class="max-w-md"
                    @keyup.enter="doSearch"
                />
                <Button @click="doSearch">{{ t('dashboard.common.search') }}</Button>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.company') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.contact') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.plan') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.super_admin.plans.index.usage_summary') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.created') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="tenant in props.tenants.data" :key="tenant.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                        <Building2 class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ tenant.name }}</div>
                                        <div class="text-sm text-gray-500">{{ tenant.slug }}</div>
                                        <div v-if="tenant.domain" class="text-xs text-gray-500">{{ tenant.domain }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="space-y-1 text-sm">
                                    <div v-if="tenant.email" class="flex items-center gap-2 text-gray-600">
                                        <Mail class="h-3 w-3" />
                                        {{ tenant.email }}
                                    </div>
                                    <div v-if="tenant.phone" class="flex items-center gap-2 text-gray-600">
                                        <Phone class="h-3 w-3" />
                                        {{ tenant.phone }}
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium uppercase"
                                    :class="planColors[(tenant.subscription_plan?.name || '').toLowerCase()] || 'bg-gray-100 text-gray-700'"
                                >
                                    <CreditCard class="h-3 w-3" />
                                    {{ tenant.subscription_plan?.name || 'Unassigned' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="tenant.subscription_plan" class="text-sm space-y-1">
                                    <div>{{ t('dashboard.common.employees') }}: {{ formatUsage(tenant.users_count, tenant.subscription_plan.max_employees) }}</div>
                                    <div>{{ t('dashboard.common.branches') }}: {{ formatUsage(tenant.branches_count, tenant.subscription_plan.max_branches) }}</div>
                                    <div>{{ t('dashboard.common.cars') }}: {{ formatUsage(tenant.cars_count, tenant.subscription_plan.max_cars) }}</div>
                                    <div>{{ t('dashboard.common.contracts') }}: {{ formatUsage(tenant.contracts_count, tenant.subscription_plan.max_contracts) }}</div>
                                    <div class="text-gray-500">{{ t('dashboard.common.reservations') }}: {{ tenant.reservations_count || 0 }}</div>
                                </div>
                                <div v-else class="text-sm text-gray-500">
                                    {{ tenant.users_count || 0 }} {{ t('dashboard.common.users') }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="tenant.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                >
                                    <component :is="tenant.is_active ? CheckCircle : XCircle" class="h-3 w-3" />
                                    {{ tenant.is_active ? t('dashboard.common.active') : t('dashboard.common.inactive') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                {{ formatDate(tenant.created_at) }}
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <Link :href="`/superadmin/tenants/${tenant.id}`">
                                    <Button variant="outline" size="sm">{{ t('dashboard.common.view') }}</Button>
                                </Link>
                                <Link :href="`/superadmin/tenants/${tenant.id}/edit`">
                                    <Button variant="outline" size="sm">{{ t('dashboard.common.edit') }}</Button>
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="props.tenants.data.length === 0">
                            <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                                {{ t('dashboard.super_admin.tenants.index.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="props.tenants.links?.length" class="flex gap-2">
                <Link
                    v-for="(link, i) in props.tenants.links"
                    :key="i"
                    :href="link.url || ''"
                    :class="[
                        'px-3 py-1 rounded text-sm',
                        link.active ? 'bg-primary text-primary-foreground' : 'bg-gray-100 text-gray-700',
                        !link.url && 'pointer-events-none opacity-50'
                    ]"
                >
                    <span v-html="link.label" />
                </Link>
            </nav>
        </main>
    </SuperAdminLayout>
</template>
