<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    tickets: {
        data: Array<{
            id: number;
            ticket_number: string;
            subject: string;
            status: string;
            created_at: string;
            tenant: { id: number; name: string; slug: string } | null;
            requester: { name: string; email: string } | null;
            assigned_to: { id: number; name: string; email: string } | null;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search?: string;
        status?: string;
        tenant_id?: number | null;
        queue?: string;
    };
    statuses: Array<{ value: string; label: string; color: string }>;
    tenants: Array<{ id: number; name: string }>;
    queues: Array<{ value: string; label: string }>;
    urls: {
        index: string;
    };
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');
const tenantId = ref(props.filters.tenant_id ? String(props.filters.tenant_id) : 'all');
const queue = ref(props.filters.queue ?? 'available');

function applyFilters() {
    router.get(
        props.urls.index,
        {
            search: search.value || null,
            status: status.value === 'all' ? null : status.value,
            tenant_id: tenantId.value === 'all' ? null : Number(tenantId.value),
            queue: queue.value === 'available' ? null : queue.value,
        },
        { preserveState: true, replace: true },
    );
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString();
}
</script>

<template>
    <Head :title="localize('Tenant Support', 'دعم المستأجرين')" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div>
                <h1 class="text-2xl font-semibold">{{ localize('Tenant Support Inbox', 'صندوق دعم المستأجرين') }}</h1>
                <p class="text-sm text-muted-foreground">{{ localize('Tickets opened by tenant admins to contact platform support.', 'التذاكر المفتوحة من مديري المستأجرين للتواصل مع دعم المنصة.') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Input v-model="search" :placeholder="localize('Search by ticket, tenant, or user...', 'ابحث برقم التذكرة أو المستأجر أو المستخدم...')" class="max-w-md" @keyup.enter="applyFilters" />
                <select v-model="status" class="h-10 rounded-md border border-input bg-background px-3 text-sm" @change="applyFilters">
                    <option value="all">{{ localize('All statuses', 'كل الحالات') }}</option>
                    <option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
                <select v-model="tenantId" class="h-10 rounded-md border border-input bg-background px-3 text-sm" @change="applyFilters">
                    <option value="all">{{ localize('All tenants', 'كل المستأجرين') }}</option>
                    <option v-for="tenant in tenants" :key="tenant.id" :value="String(tenant.id)">{{ tenant.name }}</option>
                </select>
                <select v-model="queue" class="h-10 rounded-md border border-input bg-background px-3 text-sm" @change="applyFilters">
                    <option v-for="item in queues" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
                <Button @click="applyFilters">{{ localize('Search', 'بحث') }}</Button>
            </div>

            <div class="overflow-x-auto rounded-lg border bg-card">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b bg-muted/30 text-left text-xs uppercase text-muted-foreground">
                            <th class="px-4 py-3">{{ localize('Ticket', 'التذكرة') }}</th>
                            <th class="px-4 py-3">{{ localize('Tenant', 'المستأجر') }}</th>
                            <th class="px-4 py-3">{{ localize('Requester', 'مقدم الطلب') }}</th>
                            <th class="px-4 py-3">{{ localize('Subject', 'الموضوع') }}</th>
                            <th class="px-4 py-3">{{ localize('Status', 'الحالة') }}</th>
                            <th class="px-4 py-3">{{ localize('Assigned To', 'مسندة إلى') }}</th>
                            <th class="px-4 py-3">{{ localize('Created', 'تاريخ الإنشاء') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ticket in tickets.data" :key="ticket.id" class="border-b last:border-b-0">
                            <td class="px-4 py-3 text-sm font-medium">{{ ticket.ticket_number }}</td>
                            <td class="px-4 py-3 text-sm">
                                {{ ticket.tenant?.name || '-' }}
                                <div class="text-xs text-muted-foreground">{{ ticket.tenant?.slug || '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ ticket.requester?.name || '-' }}
                                <div class="text-xs text-muted-foreground">{{ ticket.requester?.email || '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ ticket.subject }}</td>
                            <td class="px-4 py-3 text-sm">
                                {{ statuses.find((item) => item.value === ticket.status)?.label || ticket.status }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ ticket.assigned_to?.name || localize('Unassigned', 'غير مسندة') }}
                                <div class="text-xs text-muted-foreground">{{ ticket.assigned_to?.email || '' }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-muted-foreground">{{ formatDate(ticket.created_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`${urls.index}/${ticket.id}`" class="text-sm font-medium text-primary hover:underline">{{ localize('Open', 'فتح') }}</Link>
                            </td>
                        </tr>
                        <tr v-if="tickets.data.length === 0">
                            <td colspan="8" class="px-4 py-6 text-center text-sm text-muted-foreground">{{ localize('No tenant support tickets found.', 'لا توجد تذاكر دعم مستأجرين.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </SuperAdminLayout>
</template>
