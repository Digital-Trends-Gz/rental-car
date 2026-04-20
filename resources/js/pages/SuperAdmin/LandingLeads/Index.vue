<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { useTrans } from '@/composables/useTrans';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    tickets: {
        data: Array<{
            id: number;
            ticket_number: string;
            guest_name: string | null;
            guest_email: string | null;
            subject: string;
            status: string;
            created_at: string;
            last_message: string | null;
            last_message_at: string | null;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search?: string;
        status?: string;
    };
    statuses: Array<{ value: string; label: string; color: string }>;
    urls: {
        index: string;
    };
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');

function applyFilters() {
    router.get(
        props.urls.index,
        {
            search: search.value || null,
            status: status.value === 'all' ? null : status.value,
        },
        { preserveState: true, replace: true },
    );
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString();
}
</script>

<template>
    <Head :title="localize('Landing Leads', 'الرسائل الواردة من الصفحة')" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div>
                <h1 class="text-2xl font-semibold">{{ localize('Landing Leads Inbox', 'صندوق رسائل الصفحة العامة') }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ localize('Messages submitted from the public landing page contact form.', 'الرسائل المرسلة من نموذج التواصل في الصفحة العامة.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Input
                    v-model="search"
                    :placeholder="localize('Search by ticket, name, email, or subject...', 'ابحث برقم التذكرة أو الاسم أو البريد أو الموضوع...')"
                    class="max-w-md"
                    @keyup.enter="applyFilters"
                />
                <select v-model="status" class="h-10 rounded-md border border-input bg-background px-3 text-sm" @change="applyFilters">
                    <option value="all">{{ localize('All statuses', 'كل الحالات') }}</option>
                    <option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
                <Button @click="applyFilters">{{ localize('Search', 'بحث') }}</Button>
            </div>

            <div class="overflow-x-auto rounded-lg border bg-card">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b bg-muted/30 text-left text-xs uppercase text-muted-foreground">
                            <th class="px-4 py-3">{{ localize('Ticket', 'التذكرة') }}</th>
                            <th class="px-4 py-3">{{ localize('Guest', 'الزائر') }}</th>
                            <th class="px-4 py-3">{{ localize('Subject', 'الموضوع') }}</th>
                            <th class="px-4 py-3">{{ localize('Status', 'الحالة') }}</th>
                            <th class="px-4 py-3">{{ localize('Created', 'تاريخ الإنشاء') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ticket in tickets.data" :key="ticket.id" class="border-b last:border-b-0">
                            <td class="px-4 py-3 text-sm font-medium">{{ ticket.ticket_number }}</td>
                            <td class="px-4 py-3 text-sm">
                                {{ ticket.guest_name || '-' }}
                                <div class="text-xs text-muted-foreground">{{ ticket.guest_email || '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ ticket.subject }}
                                <div class="mt-1 line-clamp-1 text-xs text-muted-foreground">{{ ticket.last_message || '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ statuses.find((item) => item.value === ticket.status)?.label || ticket.status }}
                            </td>
                            <td class="px-4 py-3 text-sm text-muted-foreground">{{ formatDate(ticket.created_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`${urls.index}/${ticket.id}`" class="text-sm font-medium text-primary hover:underline">
                                    {{ localize('Open', 'فتح') }}
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="tickets.data.length === 0">
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-muted-foreground">
                                {{ localize('No landing leads found.', 'لا توجد رسائل واردة من الصفحة العامة.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </SuperAdminLayout>
</template>
