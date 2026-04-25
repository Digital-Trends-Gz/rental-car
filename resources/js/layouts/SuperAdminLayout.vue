<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();
const message = ref<string | null>(page.props.flash?.error ?? page.props.flash?.restricted_action ?? null);
const tone = ref<'error' | 'warning'>(page.props.flash?.error ? 'error' : 'warning');
const messageClass = computed(() => (tone.value === 'error' ? 'bg-red-600/95 text-white' : 'bg-yellow-500/90 text-white'));

watch(
    () => [page.props.flash?.error, page.props.flash?.restricted_action],
    ([error, restrictedAction]) => {
        const nextMessage = (error ?? restrictedAction ?? null) as string | null;
        message.value = nextMessage;
        tone.value = error ? 'error' : 'warning';
        if (nextMessage) {
            setTimeout(() => (message.value = null), 10000);
        }
    },
);
</script>

<template>
    <AppSidebarLayout>
        <div
            v-if="message"
            :class="['fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium shadow-lg', messageClass]"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-4 w-4 shrink-0"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L4.34 16c-.77 1.333.192 3 1.732 3z"
                />
            </svg>
            <span>{{ message }}</span>
        </div>
        <slot />
    </AppSidebarLayout>
</template>
