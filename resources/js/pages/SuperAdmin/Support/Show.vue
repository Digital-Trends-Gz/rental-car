<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    ticket: {
        id: number;
        ticket_number: string;
        subject: string;
        status: string;
        created_at: string;
        tenant: { id: number; name: string; slug: string; email: string | null } | null;
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
    agents: Array<{ id: number; name: string; email: string }>;
    urls: {
        index: string;
        assign: string;
        reply: string;
        close: string;
    };
}>();

const page = usePage<any>();
const currentUserId = Number(page.props?.auth?.user?.id ?? 0);
const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const form = useForm({
    message: '',
});

const assignmentForm = useForm({
    assigned_to_user_id: props.ticket.assigned_to?.id ? String(props.ticket.assigned_to.id) : '',
});

const isAssignedToCurrentUser = computed(() => Number(props.ticket.assigned_to?.id ?? 0) === currentUserId);
const assignmentStatusText = computed(() => {
    if (!props.ticket.assigned_to) {
        return localize('This ticket is currently unassigned and available for any super admin employee.', 'هذه التذكرة غير مسندة حاليًا ومتاحة لأي موظف سوبر أدمن.');
    }

    return isAssignedToCurrentUser.value
        ? localize('This ticket is assigned to you.', 'هذه التذكرة مسندة إليك.')
        : `${localize('This ticket is assigned to', 'هذه التذكرة مسندة إلى')} ${props.ticket.assigned_to.name}.`;
});

function submitReply() {
    form.post(props.urls.reply, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function saveAssignment() {
    assignmentForm.post(props.urls.assign, {
        preserveScroll: true,
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

    return message.is_superadmin;
}
</script>

<template>
    <Head :title="`${localize('Tenant Support', 'دعم المستأجرين')} ${ticket.ticket_number}`" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ ticket.subject }}</h1>
                    <p class="text-sm text-muted-foreground">{{ ticket.ticket_number }} • {{ formatDate(ticket.created_at) }}</p>
                    <p class="mt-1 text-sm">
                        <span class="font-medium">{{ localize('Tenant:', 'المستأجر:') }}</span> {{ ticket.tenant?.name || '-' }} ({{ ticket.tenant?.slug || '-' }})
                    </p>
                    <p class="text-sm">
                        <span class="font-medium">{{ localize('Requester:', 'مقدم الطلب:') }}</span> {{ ticket.requester?.name || '-' }} ({{ ticket.requester?.email || '-' }})
                    </p>
                    <p class="text-sm">
                        <span class="font-medium">{{ localize('Assigned To:', 'مسندة إلى:') }}</span> {{ ticket.assigned_to?.name || localize('Unassigned', 'غير مسندة') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="urls.index">
                        <Button variant="outline">{{ localize('Back', 'رجوع') }}</Button>
                    </Link>
                    <Button v-if="ticket.status !== 'closed'" variant="destructive" @click="closeTicket">{{ localize('Close', 'إغلاق') }}</Button>
                </div>
            </div>

            <section class="rounded-lg border bg-card p-5">
                <div class="mb-4">
                    <h2 class="text-base font-semibold">{{ localize('Assignment', 'الإسناد') }}</h2>
                    <p class="text-sm text-muted-foreground">{{ assignmentStatusText }}</p>
                </div>
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="saveAssignment">
                    <div class="min-w-[280px] flex-1 space-y-2">
                        <label for="assigned-to-user" class="text-sm font-medium">{{ localize('Assigned employee', 'الموظف المسند') }}</label>
                        <select
                            id="assigned-to-user"
                            v-model="assignmentForm.assigned_to_user_id"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">{{ localize('Unassigned', 'غير مسندة') }}</option>
                            <option v-for="agent in agents" :key="agent.id" :value="String(agent.id)">
                                {{ agent.name }} ({{ agent.email }})
                            </option>
                        </select>
                        <InputError :message="assignmentForm.errors.assigned_to_user_id" />
                    </div>
                    <Button type="submit" :disabled="assignmentForm.processing">
                        {{ assignmentForm.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Assignment', 'حفظ الإسناد') }}
                    </Button>
                </form>
            </section>

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
                    {{ localize('No messages yet.', 'لا توجد رسائل بعد.') }}
                </div>
            </section>

            <section v-if="ticket.status !== 'closed'" class="rounded-lg border bg-card p-5">
                <form class="space-y-3" @submit.prevent="submitReply">
                    <label for="reply-message" class="text-sm font-medium">{{ localize('Reply', 'الرد') }}</label>
                    <textarea
                        id="reply-message"
                        v-model="form.message"
                        rows="4"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.message" />
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? localize('Sending...', 'جارٍ الإرسال...') : localize('Send Reply', 'إرسال الرد') }}
                    </Button>
                </form>
            </section>
        </main>
    </SuperAdminLayout>
</template>
