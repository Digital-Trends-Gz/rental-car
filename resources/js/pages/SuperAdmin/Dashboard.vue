<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { useTrans } from '@/composables/useTrans';
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Building2, Users, Car, DollarSign, TrendingUp, Clock } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    stats: {
        total_tenants: number;
        active_tenants: number;
        total_users: number;
        total_reservations: number;
        total_revenue: number;
    };
    recentTenants: Array<{
        id: number;
        name: string;
        email: string;
        plan_id: number | null;
        subscription_plan?: { id: number; name: string } | null;
        is_active: boolean;
        created_at: string;
    }>;
    monthlyRevenue?: Array<{
        month: string;
        revenue: number;
    }>;
    recentSubscriptions?: Array<{
        id: number;
        tenant_name: string | null;
        payment_method: string | null;
        amount_paid: number | null;
        currency: string | null;
        user_name: string;
        paid_at: string | null;
        trial_ends_at: string | null;
        type: string;
        stripe_status: string;
    }>;
    recentLandingContacts?: Array<{
        id: number;
        ticket_number: string;
        guest_name: string | null;
        guest_email: string | null;
        subject: string;
        status: string | null;
        created_at: string;
        last_message: string | null;
        last_message_at: string | null;
    }>;
    trafficSources?: Array<{
        source: string;
        visits: number;
    }>;
    recentSaasVisits?: Array<{
        id: number;
        landing_path: string;
        source: string;
        medium: string | null;
        campaign: string | null;
        visited_at: string;
    }>;
    urls?: {
        landing_leads_index?: string;
    };
}>();
const { t, locale } = useTrans();
const numberLocale = computed(() => (locale.value === 'ar' ? 'ar' : 'en-US'));
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat(numberLocale.value, {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString(numberLocale.value, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatDateTime = (date: string) => {
    return new Date(date).toLocaleString(numberLocale.value, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatSubscriptionMethod = (paymentMethod: string | null, type: string) => {
    if (paymentMethod && paymentMethod.trim() !== '') {
        return paymentMethod;
    }

    return type === 'one_time'
        ? localize('Credit Card (One-Time)', 'بطاقة ائتمان (دفعة واحدة)')
        : localize('Credit Card', 'بطاقة ائتمان');
};

const formatSubscriptionAmount = (amount: number | null, currency: string | null) => {
    if (amount === null || Number.isNaN(Number(amount))) {
        return '-';
    }

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
</script>

<template>
    <Head :title="t('dashboard.super_admin.head_title')" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold">{{ t('dashboard.super_admin.title') }}</h1>
                    <p class="text-muted-foreground">{{ t('dashboard.super_admin.subtitle') }}</p>
                </div>
                <Link href="/superadmin/tenants/create">
                    <button class="rounded-md bg-primary px-4 py-2 text-primary-foreground hover:bg-primary/90">
                        + {{ t('dashboard.super_admin.new_tenant') }}
                    </button>
                </Link>
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">{{ t('dashboard.super_admin.cards.total_tenants') }}</CardTitle>
                        <Building2 class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ props.stats.total_tenants }}</div>
                        <p class="text-xs text-muted-foreground">{{ t('dashboard.super_admin.cards.active_tenants', { count: props.stats.active_tenants }) }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">{{ t('dashboard.super_admin.cards.total_users') }}</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ props.stats.total_users }}</div>
                        <p class="text-xs text-muted-foreground">{{ t('dashboard.super_admin.cards.across_all_tenants') }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">{{ t('dashboard.super_admin.cards.total_reservations') }}</CardTitle>
                        <Car class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ props.stats.total_reservations }}</div>
                        <p class="text-xs text-muted-foreground">{{ t('dashboard.super_admin.cards.all_time_bookings') }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">{{ t('dashboard.super_admin.cards.total_revenue') }}</CardTitle>
                        <DollarSign class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatCurrency(props.stats.total_revenue) }}</div>
                        <p class="text-xs text-muted-foreground">{{ t('dashboard.super_admin.cards.platform_wide') }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">{{ t('dashboard.super_admin.cards.growth_rate') }}</CardTitle>
                        <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">+12.5%</div>
                        <p class="text-xs text-muted-foreground">{{ t('dashboard.super_admin.cards.vs_last_month') }}</p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>{{ t('dashboard.super_admin.recent_tenants.title') }}</CardTitle>
                            <CardDescription>{{ t('dashboard.super_admin.recent_tenants.subtitle') }}</CardDescription>
                        </div>
                        <Link href="/superadmin/tenants" class="text-sm text-primary hover:underline">
                            {{ t('dashboard.super_admin.recent_tenants.view_all') }} ->
                        </Link>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div
                            v-for="tenant in props.recentTenants"
                            :key="tenant.id"
                            class="flex items-center justify-between rounded-lg border p-4 transition-colors hover:bg-muted/50"
                        >
                            <div class="flex items-center gap-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                    <Building2 class="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <div class="font-medium">{{ tenant.name }}</div>
                                    <div class="text-sm text-muted-foreground">{{ tenant.email }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="tenant.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'"
                                    >
                                        {{ tenant.is_active ? t('dashboard.super_admin.status.active') : t('dashboard.super_admin.status.inactive') }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                                        {{ tenant.subscription_plan?.name || localize('Unassigned', 'غير معيّن') }}
                                    </span>
                                </div>
                                <div class="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                                    <Clock class="h-3 w-3" />
                                    {{ formatDate(tenant.created_at) }}
                                </div>
                            </div>
                        </div>

                        <div v-if="!props.recentTenants || props.recentTenants.length === 0" class="py-8 text-center text-muted-foreground">
                            {{ t('dashboard.super_admin.recent_tenants.empty') }}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('dashboard.sidebar.super_admin.subscription') }}</CardTitle>
                    <CardDescription>{{ localize('Latest tenant subscription payments', 'أحدث مدفوعات اشتراكات المستأجرين') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="props.recentSubscriptions && props.recentSubscriptions.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-border">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                        {{ t('dashboard.common.tenant') }}
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                        {{ t('dashboard.common.method') }}
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                        {{ t('dashboard.common.amount') }}
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                        {{ t('dashboard.super_admin.users.index.user') }}
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                        {{ t('dashboard.common.date') }}
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                        {{ localize('Trial End Date', 'تاريخ انتهاء الفترة التجريبية') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="subscription in props.recentSubscriptions" :key="subscription.id">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium">
                                        {{ subscription.tenant_name || '-' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        {{ formatSubscriptionMethod(subscription.payment_method, subscription.type) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        {{ formatSubscriptionAmount(subscription.amount_paid, subscription.currency) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        {{ subscription.user_name }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        {{ subscription.paid_at ? formatDateTime(subscription.paid_at) : '-' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        {{ subscription.trial_ends_at ? formatDate(subscription.trial_ends_at) : '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="py-8 text-center text-muted-foreground">{{ localize('No subscriptions found yet.', 'لا توجد اشتراكات حتى الآن.') }}</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <CardTitle>{{ localize('Landing contact leads', 'الرسائل الواردة من الصفحة') }}</CardTitle>
                            <CardDescription>{{ localize('Messages sent from the public landing page contact form.', 'الرسائل المرسلة من نموذج التواصل في الصفحة العامة.') }}</CardDescription>
                        </div>
                        <Link v-if="props.urls?.landing_leads_index" :href="props.urls.landing_leads_index" class="text-sm font-medium text-primary hover:underline">
                            {{ localize('View inbox', 'عرض الصندوق') }}
                        </Link>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="props.recentLandingContacts && props.recentLandingContacts.length > 0" class="space-y-4">
                        <div
                            v-for="lead in props.recentLandingContacts"
                            :key="lead.id"
                            class="rounded-lg border p-4 transition-colors hover:bg-muted/50"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-foreground">{{ lead.guest_name || localize('Guest', 'زائر') }}</span>
                                        <span class="text-xs text-muted-foreground">{{ lead.guest_email || '-' }}</span>
                                    </div>
                                    <div class="mt-1 text-sm text-muted-foreground">
                                        {{ lead.ticket_number }} • {{ formatDateTime(lead.created_at) }}
                                    </div>
                                </div>
                                <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">
                                    {{ lead.status || localize('new', 'جديد') }}
                                </span>
                            </div>

                            <div class="mt-3">
                                <p class="text-sm font-medium text-foreground">{{ lead.subject }}</p>
                                <p class="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                    {{ lead.last_message || localize('No message body provided.', 'لم يتم إرسال نص الرسالة.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="py-8 text-center text-muted-foreground">
                        {{ localize('No landing page messages received yet.', 'لم يتم استلام أي رسائل من الصفحة العامة حتى الآن.') }}
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('SaaS Traffic Sources', 'مصادر زيارات منصة SaaS') }}</CardTitle>
                        <CardDescription>{{ localize('Where visitors reached the main SaaS landing page from in the last 30 days.', 'من أين وصل الزوار إلى الصفحة الرئيسية للمنصة خلال آخر 30 يومًا.') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="props.trafficSources && props.trafficSources.length > 0" class="space-y-3">
                            <div
                                v-for="source in props.trafficSources"
                                :key="source.source"
                                class="flex items-center justify-between rounded-lg border p-3"
                            >
                                <span class="truncate text-sm font-medium">{{ source.source }}</span>
                                <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">
                                    {{ source.visits }} {{ localize('visits', 'زيارة') }}
                                </span>
                            </div>
                        </div>
                        <div v-else class="py-8 text-center text-muted-foreground">
                            {{ localize('No landing page visits tracked yet.', 'لا توجد زيارات مسجلة للصفحة الرئيسية حتى الآن.') }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Recent SaaS Visits', 'أحدث زيارات منصة SaaS') }}</CardTitle>
                        <CardDescription>{{ localize('Latest visits to the public SaaS home page with their tracked source.', 'أحدث زيارات الصفحة العامة للمنصة مع مصدر الزيارة المسجل.') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="props.recentSaasVisits && props.recentSaasVisits.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-border">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            {{ localize('Source', 'المصدر') }}
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            {{ localize('Medium', 'الوسيط') }}
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            {{ localize('Campaign', 'الحملة') }}
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            {{ localize('Path', 'المسار') }}
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            {{ localize('Date', 'التاريخ') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr v-for="visit in props.recentSaasVisits" :key="visit.id">
                                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium">
                                            {{ visit.source }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                                            {{ visit.medium || '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                                            {{ visit.campaign || '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                                            {{ visit.landing_path }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                                            {{ formatDateTime(visit.visited_at) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="py-8 text-center text-muted-foreground">
                            {{ localize('No landing page visits tracked yet.', 'لا توجد زيارات مسجلة للصفحة الرئيسية حتى الآن.') }}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </main>
    </SuperAdminLayout>
</template>
