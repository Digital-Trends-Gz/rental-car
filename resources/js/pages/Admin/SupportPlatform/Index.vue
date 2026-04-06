<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    tickets: {
        data: Array<{
            id: number;
            ticket_number: string;
            subject: string;
            status: string;
            created_at: string;
            last_message: string | null;
            last_message_at: string | null;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    statusOptions: Array<{ value: string; label: string }>;
    urls: {
        index: string;
        store: string;
    };
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const form = useForm({
    subject: '',
    message: '',
});

function submit() {
    form.post(props.urls.store, {
        onSuccess: () => {
            form.reset();
        },
    });
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString();
}

const statusClass: Record<string, string> = {
    new: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-amber-100 text-amber-700',
    closed: 'bg-emerald-100 text-emerald-700',
};
</script>

<template>
    <Head :title="localize('Platform Support', 'دعم المنصة')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div>
                <h1 class="text-2xl font-semibold">{{ localize('Platform Support', 'دعم المنصة') }}</h1>
                <p class="text-sm text-muted-foreground">{{ localize('Create tickets for Super Admin and track responses.', 'أنشئ تذاكر للسوبر أدمن وتابع الردود عليها.') }}</p>
            </div>

            <section class="rounded-lg border bg-card p-5">
                <h2 class="mb-4 text-lg font-medium">{{ localize('New Ticket', 'تذكرة جديدة') }}</h2>
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <Label for="subject">{{ localize('Subject', 'الموضوع') }}</Label>
                        <Input id="subject" v-model="form.subject" />
                        <InputError :message="form.errors.subject" class="mt-1" />
                    </div>

                    <div>
                        <Label for="message">{{ localize('Message', 'الرسالة') }}</Label>
                        <textarea
                            id="message"
                            v-model="form.message"
                            rows="5"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="form.errors.message" class="mt-1" />
                    </div>

                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? localize('Sending...', 'جارٍ الإرسال...') : localize('Create Ticket', 'إنشاء تذكرة') }}
                    </Button>
                </form>
            </section>

            <section class="rounded-lg border bg-card">
                <div class="border-b px-5 py-4">
                    <h2 class="text-lg font-medium">{{ localize('My Platform Tickets', 'تذاكر المنصة الخاصة بي') }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b bg-muted/30 text-left text-xs uppercase text-muted-foreground">
                                <th class="px-4 py-3">{{ localize('Ticket', 'التذكرة') }}</th>
                                <th class="px-4 py-3">{{ localize('Subject', 'الموضوع') }}</th>
                                <th class="px-4 py-3">{{ localize('Status', 'الحالة') }}</th>
                                <th class="px-4 py-3">{{ localize('Last Message', 'آخر رسالة') }}</th>
                                <th class="px-4 py-3">{{ localize('Created', 'تاريخ الإنشاء') }}</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ticket in tickets.data" :key="ticket.id" class="border-b last:border-b-0">
                                <td class="px-4 py-3 text-sm font-medium">{{ ticket.ticket_number }}</td>
                                <td class="px-4 py-3 text-sm">{{ ticket.subject }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusClass[ticket.status] || 'bg-muted text-foreground'">
                                        {{ statusOptions.find((s) => s.value === ticket.status)?.label || ticket.status }}
                                    </span>
                                </td>
                                <td class="max-w-md px-4 py-3 text-sm text-muted-foreground">{{ ticket.last_message || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-muted-foreground">{{ formatDate(ticket.created_at) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="`${urls.index}/${ticket.id}`" class="text-sm font-medium text-primary hover:underline">{{ localize('Open', 'فتح') }}</Link>
                                </td>
                            </tr>
                            <tr v-if="tickets.data.length === 0">
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-muted-foreground">{{ localize('No tickets yet.', 'لا توجد تذاكر بعد.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AdminLayout>
</template>
