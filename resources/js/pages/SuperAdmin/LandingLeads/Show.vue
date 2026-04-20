<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { useTrans } from '@/composables/useTrans';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    ticket: {
        id: number;
        ticket_number: string;
        subject: string;
        status: string;
        created_at: string;
        guest_name: string | null;
        guest_email: string | null;
        messages: Array<{
            id: number;
            message: string;
            user_id: number | null;
            user_name: string;
            is_superadmin: boolean;
            created_at: string;
        }>;
    };
    statuses: Array<{ value: string; label: string }>;
    urls: {
        index: string;
        reply: string;
        status: string;
    };
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const replyForm = useForm({
    message: '',
});

const statusForm = useForm({
    status: props.ticket.status,
});

function submitReply() {
    replyForm.post(props.urls.reply, {
        preserveScroll: true,
        onSuccess: () => replyForm.reset(),
    });
}

function saveStatus() {
    statusForm.put(props.urls.status, {
        preserveScroll: true,
    });
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString();
}
</script>

<template>
    <Head :title="`${localize('Landing Lead', 'رسالة الصفحة العامة')} ${ticket.ticket_number}`" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ ticket.subject }}</h1>
                    <p class="text-sm text-muted-foreground">{{ ticket.ticket_number }} • {{ formatDate(ticket.created_at) }}</p>
                    <p class="mt-1 text-sm">
                        <span class="font-medium">{{ localize('Guest:', 'الزائر:') }}</span>
                        {{ ticket.guest_name || '-' }} ({{ ticket.guest_email || '-' }})
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="urls.index">
                        <Button variant="outline">{{ localize('Back', 'رجوع') }}</Button>
                    </Link>
                </div>
            </div>

            <section class="rounded-lg border bg-card p-5">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 class="text-base font-semibold">{{ localize('Status', 'الحالة') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ localize('Update the current lead status.', 'حدّث حالة الرسالة الحالية.') }}</p>
                    </div>

                    <form class="flex flex-wrap items-end gap-3" @submit.prevent="saveStatus">
                        <div class="space-y-2">
                            <label for="lead-status" class="text-sm font-medium">{{ localize('Lead status', 'حالة الرسالة') }}</label>
                            <select id="lead-status" v-model="statusForm.status" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                                <option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
                            </select>
                            <InputError :message="statusForm.errors.status" />
                        </div>
                        <Button type="submit" :disabled="statusForm.processing">
                            {{ statusForm.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save status', 'حفظ الحالة') }}
                        </Button>
                    </form>
                </div>
            </section>

            <section class="space-y-4 rounded-lg border bg-card p-5">
                <div
                    v-for="message in ticket.messages"
                    :key="message.id"
                    class="flex"
                    :class="message.is_superadmin ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-3xl rounded-lg px-4 py-3 text-sm"
                        :class="message.is_superadmin ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground'"
                    >
                        <div class="whitespace-pre-line">{{ message.message }}</div>
                        <div class="mt-2 text-xs opacity-75">
                            {{ message.user_name }} • {{ formatDate(message.created_at) }}
                        </div>
                    </div>
                </div>

                <div v-if="ticket.messages.length === 0" class="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground">
                    {{ localize('No messages yet.', 'لا توجد رسائل بعد.') }}
                </div>
            </section>

            <section v-if="ticket.status !== 'closed'" class="rounded-lg border bg-card p-5">
                <form class="space-y-3" @submit.prevent="submitReply">
                    <label for="reply-message" class="text-sm font-medium">{{ localize('Reply', 'الرد') }}</label>
                    <Textarea
                        id="reply-message"
                        v-model="replyForm.message"
                        rows="4"
                        :placeholder="localize('Type your reply...', 'اكتب الرد هنا...')"
                    />
                    <InputError :message="replyForm.errors.message" />
                    <Button type="submit" :disabled="replyForm.processing">
                        {{ replyForm.processing ? localize('Sending...', 'جارٍ الإرسال...') : localize('Send Reply', 'إرسال الرد') }}
                    </Button>
                </form>
            </section>
        </main>
    </SuperAdminLayout>
</template>
