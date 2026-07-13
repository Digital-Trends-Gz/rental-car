<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    discountRequests: {
        data: Array<{
            id: number;
            base_amount: number;
            discount_type: string;
            discount_value: number;
            discount_amount: number;
            final_amount: number;
            reason: string;
            status: string;
            review_note?: string | null;
            created_at?: string | null;
            reviewed_at?: string | null;
            reservation?: { id: number; reservation_number: string; url: string } | null;
            contract?: { id: number; contract_number: string; url: string } | null;
            return_report?: { id: number; report_number: string; url: string } | null;
            client?: { id: number; name: string; email: string } | null;
            employee?: { id: number; name: string; email: string } | null;
            reviewed_by?: { id: number; name: string; email: string } | null;
            car?: { name: string; license_plate: string } | null;
            branch_name?: string | null;
            approve_url: string;
            reject_url: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    statuses: Record<string, { label: string }>;
    filters: { search?: string; status?: string; branch_id?: number | null };
    branches: Array<{ id: number; name: string }>;
    canAccessAllBranches: boolean;
    canViewFinancials: boolean;
    indexUrl: string;
    currency: { symbol: string; code: string };
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'pending');
const branchId = ref(props.filters.branch_id ? String(props.filters.branch_id) : 'all');
const hasFinancialAccess = computed(() => Boolean(props.canViewFinancials));

function applyFilters() {
    router.get(
        props.indexUrl,
        {
            search: search.value || null,
            status: status.value,
            branch_id: branchId.value === 'all' ? null : Number(branchId.value),
        },
        { preserveState: true, replace: true },
    );
}

function clearFilters() {
    search.value = '';
    status.value = 'pending';
    branchId.value = 'all';
    applyFilters();
}

function approveRequest(url: string) {
    if (!confirm(localize('Approve this discount request?', 'الموافقة على طلب الخصم؟'))) {
        return;
    }

    router.post(url, {}, { preserveScroll: true });
}

function rejectRequest(url: string) {
    const note = prompt(localize('Rejection note', 'ملاحظة الرفض'));
    if (note === null) {
        return;
    }

    router.post(url, { review_note: note }, { preserveScroll: true });
}

function money(value: number) {
    if (!hasFinancialAccess.value) {
        return '*******';
    }

    return `${props.currency.symbol}${Number(value ?? 0).toFixed(2)}`;
}

function discountValue(type: string, value: number) {
    if (!hasFinancialAccess.value) {
        return '*******';
    }

    return type === 'percentage'
        ? `${Number(value ?? 0).toFixed(2)}%`
        : money(value);
}

function formatDate(value?: string | null) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString(locale.value === 'ar' ? 'ar' : 'en-US');
}

function statusClass(value: string) {
    if (value === 'approved') return 'bg-emerald-100 text-emerald-700';
    if (value === 'rejected') return 'bg-red-100 text-red-700';
    if (value === 'cancelled') return 'bg-gray-100 text-gray-700';
    return 'bg-amber-100 text-amber-700';
}
</script>

<template>
    <Head :title="localize('Discount Requests', 'طلبات الخصم')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Discount Requests', 'طلبات الخصم') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Review employee discount requests before collection.', 'مراجعة طلبات الخصم قبل التحصيل.') }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Input
                    v-model="search"
                    class="max-w-md"
                    :placeholder="localize('Search reservation, client, employee...', 'ابحث بالحجز أو العميل أو الموظف...')"
                    @keyup.enter="applyFilters"
                />
                <select v-model="status" class="h-10 rounded-md border border-input bg-background px-3 text-sm" @change="applyFilters">
                    <option value="all">{{ localize('All statuses', 'كل الحالات') }}</option>
                    <option v-for="(option, key) in statuses" :key="key" :value="key">
                        {{ option.label }}
                    </option>
                </select>
                <select
                    v-if="canAccessAllBranches"
                    v-model="branchId"
                    class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    @change="applyFilters"
                >
                    <option value="all">{{ localize('All branches', 'كل الفروع') }}</option>
                    <option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">
                        {{ branch.name }}
                    </option>
                </select>
                <Button @click="applyFilters">{{ localize('Search', 'بحث') }}</Button>
                <Button variant="outline" @click="clearFilters">{{ localize('Clear', 'مسح') }}</Button>
            </div>

            <div class="overflow-x-auto rounded-md border bg-card">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b bg-muted/40 text-left text-xs uppercase text-muted-foreground">
                            <th class="px-4 py-3">{{ localize('Request', 'الطلب') }}</th>
                            <th class="px-4 py-3">{{ localize('Customer', 'العميل') }}</th>
                            <th class="px-4 py-3">{{ localize('Employee', 'الموظف') }}</th>
                            <th class="px-4 py-3">{{ localize('Amounts', 'المبالغ') }}</th>
                            <th class="px-4 py-3">{{ localize('Reason', 'السبب') }}</th>
                            <th class="px-4 py-3">{{ localize('Status', 'الحالة') }}</th>
                            <th class="px-4 py-3 text-right">{{ localize('Actions', 'الإجراءات') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="request in discountRequests.data" :key="request.id" class="border-b last:border-b-0">
                            <td class="px-4 py-3 align-top text-sm">
                                <div class="font-medium">#{{ request.id }}</div>
                                <Link v-if="request.reservation" :href="request.reservation.url" class="text-primary hover:underline">
                                    {{ request.reservation.reservation_number }}
                                </Link>
                                <div v-if="request.contract">
                                    <Link :href="request.contract.url" class="text-xs text-primary hover:underline">
                                        {{ request.contract.contract_number }}
                                    </Link>
                                </div>
                                <div v-if="request.return_report">
                                    <Link :href="request.return_report.url" class="text-xs text-primary hover:underline">
                                        {{ request.return_report.report_number }}
                                    </Link>
                                </div>
                                <div class="mt-1 text-xs text-muted-foreground">{{ formatDate(request.created_at) }}</div>
                                <div v-if="request.branch_name" class="text-xs text-muted-foreground">{{ request.branch_name }}</div>
                            </td>
                            <td class="px-4 py-3 align-top text-sm">
                                <div class="font-medium">{{ request.client?.name || '-' }}</div>
                                <div class="text-xs text-muted-foreground">{{ request.client?.email }}</div>
                                <div v-if="request.car" class="mt-1 text-xs text-muted-foreground">
                                    {{ request.car.name }} · {{ request.car.license_plate }}
                                </div>
                            </td>
                            <td class="px-4 py-3 align-top text-sm">
                                <div class="font-medium">{{ request.employee?.name || '-' }}</div>
                                <div class="text-xs text-muted-foreground">{{ request.employee?.email }}</div>
                            </td>
                            <td class="px-4 py-3 align-top text-sm">
                                <div>{{ localize('Remaining', 'المتبقي') }}: <span class="font-medium">{{ money(request.base_amount) }}</span></div>
                                <div>{{ localize('Requested', 'المطلوب') }}: <span class="font-medium">{{ discountValue(request.discount_type, request.discount_value) }}</span></div>
                                <div>{{ localize('Discount', 'الخصم') }}: <span class="font-medium text-emerald-700">{{ money(request.discount_amount) }}</span></div>
                                <div>{{ localize('After', 'بعد الخصم') }}: <span class="font-medium">{{ money(request.final_amount) }}</span></div>
                            </td>
                            <td class="max-w-sm px-4 py-3 align-top text-sm">
                                <div class="whitespace-pre-wrap">{{ request.reason }}</div>
                                <div v-if="request.review_note" class="mt-2 text-xs text-muted-foreground">
                                    {{ localize('Review note', 'ملاحظة المراجعة') }}: {{ request.review_note }}
                                </div>
                            </td>
                            <td class="px-4 py-3 align-top text-sm">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(request.status)">
                                    {{ statuses[request.status]?.label || request.status }}
                                </span>
                                <div v-if="request.reviewed_by" class="mt-2 text-xs text-muted-foreground">
                                    {{ request.reviewed_by.name }}
                                </div>
                                <div v-if="request.reviewed_at" class="text-xs text-muted-foreground">
                                    {{ formatDate(request.reviewed_at) }}
                                </div>
                            </td>
                            <td class="px-4 py-3 align-top text-right text-sm">
                                <div v-if="request.status === 'pending'" class="flex justify-end gap-2">
                                    <Button size="sm" @click="approveRequest(request.approve_url)">
                                        {{ localize('Approve', 'موافقة') }}
                                    </Button>
                                    <Button size="sm" variant="outline" @click="rejectRequest(request.reject_url)">
                                        {{ localize('Reject', 'رفض') }}
                                    </Button>
                                </div>
                                <span v-else class="text-xs text-muted-foreground">-</span>
                            </td>
                        </tr>
                        <tr v-if="discountRequests.data.length === 0">
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-muted-foreground">
                                {{ localize('No discount requests found.', 'لا توجد طلبات خصم.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="discountRequests.links?.length" class="flex flex-wrap gap-2">
                <Link
                    v-for="(link, index) in discountRequests.links"
                    :key="index"
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
