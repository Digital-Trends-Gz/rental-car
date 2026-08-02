<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    ticket: {
        id: number;
        ticket_number: string;
        subject: string;
        status: string;
        created_at: string;
        tenant: { id: number; name: string; slug: string } | null;
        requester: { id: number; name: string; email: string } | null;
        assigned_to: { id: number; name: string; email: string } | null;
        messages: Array<{
            id: number;
            message: string;
            user_id: number | null;
            user_name: string;
            is_superadmin: boolean;
            created_at: string;
        }>;
    };
    urls: {
        index: string;
        reply: string;
        close: string;
    };
}>();

const form = useForm({
    message: '',
});

const page = usePage<any>();
const currentUserId = Number(page.props?.auth?.user?.id ?? 0);
const { t } = useTrans();
const translationRoot = 'dashboard.admin.support_platform';
const translate = (key: string) => t(`${translationRoot}.${key}`);

function submitReply() {
    form.post(props.urls.reply, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function closeTicket() {
    form.post(props.urls.close);
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString();
}

function isMine(message: { user_id: number | null; is_superadmin: boolean }): boolean {
    if (currentUserId > 0 && message.user_id) {
        return currentUserId === message.user_id;
    }

    return !message.is_superadmin;
}
</script>

<template>
    <Head :title="`${translate('platform_ticket')} ${ticket.ticket_number}`" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ ticket.subject }}</h1>
                    <p class="text-sm text-muted-foreground">{{ ticket.ticket_number }} • {{ formatDate(ticket.created_at) }}</p>
                    <p class="mt-2 text-sm text-foreground">
                        <span class="font-medium">{{ translate('employee_name') }}</span>
                        {{ ticket.assigned_to?.name || translate('unassigned') }}
                    </p>
                    <p class="text-sm text-foreground">
                        <span class="font-medium">{{ translate('employee_email') }}</span>
                        {{ ticket.assigned_to?.email || '-' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="urls.index">
                        <Button variant="outline">{{ translate('back') }}</Button>
                    </Link>
                    <Button v-if="ticket.status !== 'closed'" variant="destructive" @click="closeTicket">{{ translate('close') }}</Button>
                </div>
            </div>

            <section class="space-y-4 rounded-lg border bg-card p-5">
                <div
                    v-for="message in ticket.messages"
                    :key="message.id"
                    class="flex"
                    :class="isMine(message) ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-3xl rounded-lg px-4 py-3 text-sm"
                        :class="isMine(message) ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground'"
                    >
                        <div class="whitespace-pre-line">{{ message.message }}</div>
                        <div class="mt-2 text-xs opacity-75">
                            {{ message.user_name }} • {{ formatDate(message.created_at) }}
                        </div>
                    </div>
                </div>

                <div v-if="ticket.messages.length === 0" class="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground">
                    {{ translate('no_messages_yet') }}
                </div>
            </section>

            <section v-if="ticket.status !== 'closed'" class="rounded-lg border bg-card p-5">
                <form class="space-y-3" @submit.prevent="submitReply">
                    <label for="reply-message" class="text-sm font-medium">{{ translate('reply') }}</label>
                    <textarea
                        id="reply-message"
                        v-model="form.message"
                        rows="4"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.message" />
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? translate('sending') : translate('send_reply') }}
                    </Button>
                </form>
            </section>
        </main>
    </AdminLayout>
</template>
