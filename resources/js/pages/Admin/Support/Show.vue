<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { close } from '@/routes/admin/support';
import { index } from '@/routes/admin/support';
import { reply } from '@/routes/admin/support';

interface Message {
    id: number;
    message: string;
    is_admin: boolean;
    created_at: string;
}

interface Ticket {
    id: number;
    subject: string;
    status: TicketStatusType;
    created_at: string;
    guest_name?: string;
    guest_email?: string;
    message?: string;
    messages: Message[];
    user?: {
        name: string;
    };
}

const TicketStatus = {
    OPEN: 'open',
    IN_PROGRESS: 'in_progress',
    CLOSED: 'closed',
} as const;

type TicketStatusType = (typeof TicketStatus)[keyof typeof TicketStatus];

const props = defineProps<{
    ticket: Ticket;
    isGuest?: boolean;
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const form = useForm<{ message: string }>({
    message: '',
});

const statusColors: Record<TicketStatusType, string> = {
    [TicketStatus.OPEN]: 'bg-blue-100 text-blue-800',
    [TicketStatus.IN_PROGRESS]: 'bg-yellow-100 text-yellow-800',
    [TicketStatus.CLOSED]: 'bg-gray-100 text-gray-800',
} as const;

const statusLabels: Record<TicketStatusType, string> = {
    [TicketStatus.OPEN]: localize('Open', 'مفتوحة'),
    [TicketStatus.IN_PROGRESS]: localize('In Progress', 'قيد المعالجة'),
    [TicketStatus.CLOSED]: localize('Closed', 'مغلقة'),
} as const;

const canSend = computed(() => form.message.trim().length > 0 && !form.processing);

const messagesEndRef = ref<HTMLElement | null>(null);
const scrollToBottom = async () => {
    await nextTick();
    messagesEndRef.value?.scrollIntoView({ behavior: 'smooth', block: 'end' });
};

const submitReply = async () => {
    if (props.isGuest) return;
    if (!form.message || form.message.trim().length === 0) return;

    try {
        await form.post(reply(props.ticket.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('message');
                router.reload({ only: ['ticket'] });
                scrollToBottom();
            },
            onError: (errors) => {
                console.error('Failed to send message:', errors);
            },
        });
    } catch (error) {
        console.error('An error occurred while sending the message:', error);
    }
};

const formatDate = (dateString: string): string => new Date(dateString).toLocaleString();

onMounted(() => {
    scrollToBottom();
});

watch(
    () => props.ticket.messages?.length,
    () => scrollToBottom(),
);

const btnProcessing = ref(false);
function closeTicket() {
    btnProcessing.value = true;
    router.post(close(props.ticket.id).url);
}
</script>

<template>
    <Head :title="`${localize('Ticket', 'التذكرة')} #${ticket.id}`" />
    <AdminLayout>
        <div class="p-4">
            <div class="mb-6 w-full rounded-lg bg-white p-6 shadow">
                <div class="mb-4 flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            {{ ticket.subject }}
                        </h1>
                        <div class="mt-2 flex items-center">
                            <span
                                class="rounded-full px-3 py-1 text-xs font-medium"
                                :class="statusColors[ticket.status as TicketStatusType] || 'bg-gray-100 text-gray-800'"
                            >
                                {{ statusLabels[ticket.status as TicketStatusType] || ticket.status }}
                            </span>
                            <span class="ml-2 text-sm text-gray-500">
                                #{{ ticket.id }} • {{ formatDate(ticket.created_at) }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-2">
                        <Link :href="index().url">
                            <Button variant="outline">{{ localize('Back', 'رجوع') }}</Button>
                        </Link>
                        <Button
                            v-if="!isGuest && ticket.status !== 'closed'"
                            variant="secondary"
                            class="ml-2"
                            :disabled="btnProcessing"
                            @click="closeTicket"
                        >
                            {{ btnProcessing ? localize('Closing...', 'جارٍ الإغلاق...') : localize('Close Ticket', 'إغلاق التذكرة') }}
                        </Button>
                    </div>
                </div>

                <div v-if="isGuest" class="mt-4 border-t pt-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <p class="text-sm font-medium text-gray-500">{{ localize('Name', 'الاسم') }}</p>
                            <p class="mt-1 text-sm text-gray-900">{{ ticket.guest_name }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">{{ localize('Email', 'البريد الإلكتروني') }}</p>
                            <p class="mt-1 text-sm text-gray-900">{{ ticket.guest_email }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-sm font-medium text-gray-500">{{ localize('Message', 'الرسالة') }}</p>
                            <div class="mt-1 rounded-md bg-gray-50 p-3">
                                <p class="text-sm whitespace-pre-line text-gray-800">
                                    {{ ticket.messages[0].message }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!isGuest" class="h-2/3 space-y-4 overflow-scroll rounded-md border-2 p-2">
                <div class="space-y-4">
                    <div
                        v-if="!ticket.messages || ticket.messages.length === 0"
                        class="rounded-md border border-dashed p-6 text-center text-sm text-gray-500"
                    >
                        {{ localize('No messages yet. Start the conversation below.', 'لا توجد رسائل بعد. ابدأ المحادثة من الأسفل.') }}
                    </div>
                    <div
                        v-for="message in ticket.messages"
                        :key="message.id"
                        :class="['flex', message.is_admin ? 'justify-end' : 'justify-start']"
                    >
                        <div
                            :class="[
                                'max-w-3xl rounded-lg px-4 py-2',
                                message.is_admin ? 'rounded-tr-none bg-blue-500 text-white' : 'rounded-tl-none bg-gray-200 text-gray-800',
                            ]"
                        >
                            <p class="whitespace-pre-line">{{ message.message }}</p>
                            <p class="mt-1 text-right text-xs opacity-75">
                                {{ formatDate(message.created_at) }}
                                <span v-if="message.is_admin" class="ml-1">• {{ localize('Admin (you)', 'المدير (أنت)') }}</span>
                                <span v-else class="ml-1">• {{ ticket.user?.name || localize('Client', 'العميل') }}</span>
                            </p>
                        </div>
                    </div>
                    <div ref="messagesEndRef"></div>
                </div>
            </div>

            <form v-if="!isGuest && ticket.status !== 'closed'" class="mt-6" @submit.prevent="submitReply">
                <div class="flex space-x-2">
                    <div class="flex-1">
                        <label for="message" class="sr-only">{{ localize('Reply to ticket', 'الرد على التذكرة') }}</label>
                        <textarea
                            id="message"
                            v-model="form.message"
                            rows="3"
                            class="w-full rounded-lg border-1 border-gray-300 p-2"
                            :placeholder="localize('Type your reply here... (Ctrl+Enter to send)', 'اكتب ردك هنا... (Ctrl+Enter للإرسال)')"
                            required
                            :aria-label="localize('Type your reply here', 'اكتب ردك هنا')"
                            @keydown.ctrl.enter.prevent="submitReply"
                        ></textarea>
                        <p v-if="form.errors.message" class="mt-1 text-sm text-red-600">
                            {{ form.errors.message }}
                        </p>
                    </div>
                    <button
                        type="submit"
                        class="mb-2 w-20 cursor-pointer self-end rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!canSend"
                    >
                        <span v-if="form.processing">{{ localize('Sending...', 'جارٍ الإرسال...') }}</span>
                        <span v-else>{{ localize('Send', 'إرسال') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
