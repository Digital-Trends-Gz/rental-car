<script setup lang="ts">
import { CheckCircle2, AlertTriangle, Info, X } from 'lucide-vue-next';
import { useTrans } from '@/composables/useTrans';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

type ToastTone = 'success' | 'error' | 'warning' | 'info';

interface ToastItem {
    id: number;
    tone: ToastTone;
    message: string;
}

const { t } = useTrans();

function formatMessage(msg: string): string {
    if (!msg) return msg;
    if (msg.startsWith('auth.') || msg.startsWith('dashboard.') || msg.startsWith('validation.')) {
        const translated = t(msg);
        if (translated && translated !== msg) {
            return translated;
        }
    }
    return msg;
}

const props = withDefaults(
    defineProps<{
        success?: string | null;
        error?: string | null;
        warning?: string | null;
        restrictedAction?: string | null;
        triggerKey?: string | number;
        timeoutMs?: number;
        positionClass?: string;
    }>(),
    {
        success: null,
        error: null,
        warning: null,
        restrictedAction: null,
        triggerKey: '',
        timeoutMs: 4500,
        positionClass: 'right-4 top-4 sm:right-6 sm:top-6',
    },
);

const toasts = ref<ToastItem[]>([]);
const timers = new Map<number, ReturnType<typeof setTimeout>>();
let nextId = 1;
let lastSignature = '';

function dismiss(id: number) {
    const timer = timers.get(id);
    if (timer) {
        clearTimeout(timer);
        timers.delete(id);
    }

    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function pushToast(tone: ToastTone, message: string) {
    const id = nextId++;
    toasts.value = [{ id, tone, message: formatMessage(message) }, ...toasts.value].slice(0, 4);

    const timer = setTimeout(() => dismiss(id), props.timeoutMs);
    timers.set(id, timer);
}

function iconForTone(tone: ToastTone) {
    if (tone === 'success') return CheckCircle2;
    if (tone === 'error') return AlertTriangle;
    if (tone === 'warning') return AlertTriangle;
    return Info;
}

function panelClass(tone: ToastTone) {
    if (tone === 'success') return 'border-emerald-200 bg-emerald-50 text-emerald-900';
    if (tone === 'error') return 'border-red-200 bg-red-50 text-red-900';
    if (tone === 'warning') return 'border-amber-200 bg-amber-50 text-amber-900';
    return 'border-sky-200 bg-sky-50 text-sky-900';
}

watch(
    () => [props.triggerKey, props.success, props.error, props.warning, props.restrictedAction],
    ([triggerKey, success, error, warning, restrictedAction]) => {
        const message = (success ?? error ?? warning ?? restrictedAction ?? '') as string;
        if (!message) {
            return;
        }

        const tone: ToastTone = success ? 'success' : error ? 'error' : 'warning';
        const signature = `${String(triggerKey)}|${tone}|${message}`;

        if (signature === lastSignature) {
            return;
        }

        lastSignature = signature;
        pushToast(tone, message);
    },
    { immediate: true },
);

function handleToastEvent(event: Event) {
    const customEvent = event as CustomEvent<{ tone?: ToastTone; message?: string }>;
    const tone = customEvent.detail?.tone ?? 'info';
    const message = customEvent.detail?.message?.trim() ?? '';

    if (!message) {
        return;
    }

    pushToast(tone, message);
}

onMounted(() => {
    window.addEventListener('flash-toast', handleToastEvent as EventListener);
});

onBeforeUnmount(() => {
    window.removeEventListener('flash-toast', handleToastEvent as EventListener);
    timers.forEach((timer) => clearTimeout(timer));
    timers.clear();
});
</script>

<template>
    <div class="pointer-events-none fixed z-50 w-[calc(100vw-2rem)] max-w-sm" :class="props.positionClass">
        <TransitionGroup name="toast-stack" tag="div" class="flex flex-col gap-3">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto overflow-hidden rounded-xl border shadow-lg backdrop-blur-sm"
                :class="panelClass(toast.tone)"
            >
                <div class="flex items-start gap-3 px-4 py-3">
                    <component :is="iconForTone(toast.tone)" class="mt-0.5 h-5 w-5 shrink-0" />
                    <p class="flex-1 text-sm font-medium leading-5">
                        {{ toast.message }}
                    </p>
                    <button
                        type="button"
                        class="rounded-md p-1 transition hover:bg-black/5"
                        @click="dismiss(toast.id)"
                        aria-label="Dismiss toast"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-stack-enter-active,
.toast-stack-leave-active {
    transition: opacity 180ms ease, transform 180ms ease;
}

.toast-stack-enter-from,
.toast-stack-leave-to {
    opacity: 0;
    transform: translateX(1rem) scale(0.98);
}

.toast-stack-move {
    transition: transform 180ms ease;
}
</style>
