<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { useTrans } from '@/composables/useTrans';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { computed, reactive } from 'vue';

type SubscriptionRow = {
    id: number;
    tenant_name: string | null;
    tenant_slug: string | null;
    plan_name: string | null;
    type: string;
    stripe_status: string;
    payment_method: string | null;
    amount_paid: number | null;
    currency: string | null;
    user_name: string;
    user_email: string;
    paid_at: string | null;
    trial_ends_at: string | null;
    ends_at: string | null;
    stripe_id: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    subscriptions: {
        data: SubscriptionRow[];
        links: PaginationLink[];
        total: number;
        from: number | null;
        to: number | null;
    };
    statuses: string[];
    billingTypes: string[];
    filters: {
        search: string;
        status: string;
        billing_type: string;
    };
}>();

const { t, locale } = useTrans();

const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    billing_type: props.filters.billing_type ?? '',
});

const numberLocale = computed(() => (locale.value === 'ar' ? 'ar' : 'en-US'));

const applyFilters = () => {
    router.get(
        '/superadmin/revenue/subscription',
        {
            search: filters.search || undefined,
            status: filters.status || undefined,
            billing_type: filters.billing_type || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const resetFilters = () => {
    filters.search = '';
    filters.status = '';
    filters.billing_type = '';
    applyFilters();
};

const formatDateTime = (value: string | null) => {
    if (!value) return '-';

    return new Date(value).toLocaleString(numberLocale.value, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDate = (value: string | null) => {
    if (!value) return '-';

    return new Date(value).toLocaleDateString(numberLocale.value, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatBillingType = (type: string) => {
    if (type === 'one_time') return 'One-time';
    if (type === 'default') return 'Subscription';
    return type;
};

const formatPaymentMethod = (paymentMethod: string | null, type: string) => {
    if (paymentMethod && paymentMethod.trim() !== '') return paymentMethod;
    return type === 'one_time' ? 'Credit Card (One-time)' : 'Credit Card';
};

const formatAmount = (amount: number | null, currency: string | null) => {
    if (amount === null || Number.isNaN(Number(amount))) return '-';

    const resolvedCurrency = (currency || 'USD').toUpperCase();

    try {
        return new Intl.NumberFormat(numberLocale.value, {
            style: 'currency',
            currency: resolvedCurrency,
        }).format(Number(amount));
    } catch {
        return `${Number(amount).toFixed(2)} ${resolvedCurrency}`;
    }
};

const statusClass = (status: string) => {
    const key = status.toLowerCase();
    if (['active', 'paid', 'trialing'].includes(key)) return 'bg-green-100 text-green-700';
    if (['past_due', 'unpaid', 'incomplete'].includes(key)) return 'bg-amber-100 text-amber-700';
    if (['canceled', 'cancelled', 'incomplete_expired'].includes(key)) return 'bg-red-100 text-red-700';
    return 'bg-gray-100 text-gray-700';
};
</script>

<template>
    <Head :title="t('dashboard.sidebar.super_admin.subscription')" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ t('dashboard.sidebar.super_admin.subscription') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        All subscription records across all tenants.
                    </p>
                </div>
                <div class="text-sm text-muted-foreground">
                    Total: {{ props.subscriptions.total }}
                </div>
            </div>

            <form @submit.prevent="applyFilters" class="grid gap-3 rounded-md border p-4 md:grid-cols-4">
                <input
                    v-model="filters.search"
                    type="text"
                    :placeholder="`${t('dashboard.common.search')}...`"
                    class="h-10 rounded-md border px-3 text-sm"
                />
                <select v-model="filters.status" class="h-10 rounded-md border px-3 text-sm">
                    <option value="">All statuses</option>
                    <option v-for="status in props.statuses" :key="status" :value="status">
                        {{ status }}
                    </option>
                </select>
                <select v-model="filters.billing_type" class="h-10 rounded-md border px-3 text-sm">
                    <option value="">All billing types</option>
                    <option v-for="type in props.billingTypes" :key="type" :value="type">
                        {{ formatBillingType(type) }}
                    </option>
                </select>
                <div class="flex items-center gap-2">
                    <Button type="submit" class="w-full md:w-auto">
                        {{ t('dashboard.common.search') }}
                    </Button>
                    <Button type="button" variant="outline" @click="resetFilters" class="w-full md:w-auto">
                        Reset
                    </Button>
                </div>
            </form>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-border">
                    <thead>
                        <tr class="bg-muted/30">
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ t('dashboard.common.tenant') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ t('dashboard.common.plan') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">Billing</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ t('dashboard.common.method') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ t('dashboard.common.amount') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ t('dashboard.super_admin.users.index.user') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ t('dashboard.common.date') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">Trial End</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ t('dashboard.common.status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">Stripe ID</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-white">
                        <tr v-for="subscription in props.subscriptions.data" :key="subscription.id" class="hover:bg-muted/20">
                            <td class="px-4 py-3 text-sm">
                                <div class="font-medium">{{ subscription.tenant_name || '-' }}</div>
                                <div class="text-xs text-muted-foreground">{{ subscription.tenant_slug || '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ subscription.plan_name || '-' }}</td>
                            <td class="px-4 py-3 text-sm">{{ formatBillingType(subscription.type) }}</td>
                            <td class="px-4 py-3 text-sm">{{ formatPaymentMethod(subscription.payment_method, subscription.type) }}</td>
                            <td class="px-4 py-3 text-sm">{{ formatAmount(subscription.amount_paid, subscription.currency) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="font-medium">{{ subscription.user_name }}</div>
                                <div class="text-xs text-muted-foreground">{{ subscription.user_email }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ formatDateTime(subscription.paid_at) }}</td>
                            <td class="px-4 py-3 text-sm">{{ formatDate(subscription.trial_ends_at) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClass(subscription.stripe_status)">
                                    {{ subscription.stripe_status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs font-mono text-muted-foreground">{{ subscription.stripe_id }}</td>
                        </tr>
                        <tr v-if="props.subscriptions.data.length === 0">
                            <td colspan="10" class="px-4 py-8 text-center text-sm text-muted-foreground">
                                No subscriptions found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="props.subscriptions.links?.length" class="flex flex-wrap gap-2">
                <Link
                    v-for="(link, index) in props.subscriptions.links"
                    :key="index"
                    :href="link.url || '#'"
                    preserve-scroll
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
    </SuperAdminLayout>
</template>
