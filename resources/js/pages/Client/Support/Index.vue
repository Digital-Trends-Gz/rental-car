<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import ClientLayout from '@/layouts/ClientLayout.vue';
import { create, show } from '@/routes/client/support';
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    tickets: {
        data: Array<{
            id: number;
            subject: string;
            message: string;
            status: string;
            user?: { id: number; name: string; email: string };
            guest_name?: string;
            guest_email?: string;
            created_at: string;
            updated_at: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

const { t, locale } = useTrans();

const statusLabels: Record<string, string> = {
    open: t('client_pages.support.index.statuses.open'),
    in_progress: t('client_pages.support.index.statuses.in_progress'),
    closed: t('client_pages.support.index.statuses.closed'),
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString(locale.value, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

function goToTicket(id: number) {
    router.visit(show(id).url);
}

function goToCreateTicket() {
    router.visit(create().url);
}
</script>

<template>
    <Head :title="t('client_pages.support.index.head_title')" />
    <ClientLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{ t('client_pages.support.index.title') }}
                </h1>
                <Button @click="goToCreateTicket">{{
                    t('client_pages.support.index.new_ticket')
                }}</Button>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'client_pages.support.index.table.ticket_number',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'client_pages.support.index.table.subject',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'client_pages.support.index.table.status',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'client_pages.support.index.table.created',
                                    )
                                }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="ticket in props.tickets.data"
                            :key="ticket.id"
                            class="cursor-pointer hover:bg-gray-50"
                            @click="goToTicket(ticket.id)"
                        >
                            <td class="px-4 py-3 text-sm text-gray-900">
                                #{{ ticket.id }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <div class="font-medium">
                                    {{ ticket.subject }}
                                </div>
                                <div class="line-clamp-1 text-xs text-gray-500">
                                    {{ ticket.message }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                {{ statusLabels[ticket.status] || ticket.status }}
                            </td>
                            <td
                                class="px-4 py-3 text-sm whitespace-nowrap text-gray-500"
                            >
                                {{ formatDate(ticket.created_at) }}
                            </td>
                        </tr>
                        <tr v-if="props.tickets.data.length === 0">
                            <td
                                colspan="4"
                                class="px-4 py-6 text-center text-gray-500"
                            >
                                {{ t('client_pages.support.index.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="props.tickets.links?.length > 3"
                class="flex justify-center"
            >
                <div class="flex gap-1">
                    <template v-for="(link, i) in props.tickets.links" :key="i">
                        <a
                            v-if="link.url"
                            :href="link.url"
                            class="rounded-md px-3 py-1 text-sm"
                            :class="{
                                'bg-primary text-primary-foreground': link.active,
                                'bg-gray-100 text-gray-700 hover:bg-gray-200':
                                    !link.active,
                            }"
                            v-html="link.label"
                        ></a>
                        <span
                            v-else
                            class="px-3 py-1 text-gray-400"
                            v-html="link.label"
                        ></span>
                    </template>
                </div>
            </nav>
        </main>
    </ClientLayout>
</template>
