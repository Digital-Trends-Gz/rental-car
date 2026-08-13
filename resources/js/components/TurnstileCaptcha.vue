<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

declare global {
    interface Window {
        turnstile?: {
            render: (element: HTMLElement, options: Record<string, unknown>) => string;
            remove: (widgetId: string) => void;
            reset: (widgetId: string) => void;
        };
        __turnstileOnLoad?: () => void;
    }
}

const props = defineProps<{
    siteKey: string;
    name?: string;
    resetKey?: number;
}>();

const emit = defineEmits<{
    verified: [token: string];
    expired: [];
}>();

const container = ref<HTMLElement | null>(null);
const token = ref('');
const widgetId = ref<string | null>(null);
const inputName = computed(() => props.name || 'cf-turnstile-response');

const render = async () => {
    await nextTick();

    if (!container.value || !props.siteKey || !window.turnstile || widgetId.value) {
        return;
    }

    widgetId.value = window.turnstile.render(container.value, {
        sitekey: props.siteKey,
        callback: (value: string) => {
            token.value = value;
            emit('verified', value);
        },
        'expired-callback': () => {
            token.value = '';
            emit('expired');
        },
        'error-callback': () => {
            token.value = '';
            emit('expired');
        },
    });
};

const loadScript = () => {
    if (window.turnstile) {
        void render();
        return;
    }

    window.__turnstileOnLoad = () => {
        void render();
    };

    if (document.querySelector('script[data-turnstile-script]')) {
        return;
    }

    const script = document.createElement('script');
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=__turnstileOnLoad&render=explicit';
    script.async = true;
    script.defer = true;
    script.dataset.turnstileScript = 'true';
    document.head.appendChild(script);
};

onMounted(loadScript);

watch(() => props.siteKey, () => {
    if (widgetId.value && window.turnstile) {
        window.turnstile.remove(widgetId.value);
    }

    widgetId.value = null;
    token.value = '';
    void render();
});

watch(() => props.resetKey, () => {
    token.value = '';

    if (widgetId.value && window.turnstile) {
        window.turnstile.reset(widgetId.value);
    }
});

onBeforeUnmount(() => {
    if (widgetId.value && window.turnstile) {
        window.turnstile.remove(widgetId.value);
    }
});
</script>

<template>
    <div class="space-y-2">
        <div ref="container" class="min-h-[65px]"></div>
        <input type="hidden" :name="inputName" :value="token" />
    </div>
</template>
