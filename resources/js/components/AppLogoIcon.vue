<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();

const page = usePage<any>();
const logoUrl = computed(() => page.props.app_branding?.logo_url || '/logo/logo.png');
const logoAlt = computed(() => page.props.name || 'Website logo');
</script>

<template>
   <img :src="logoUrl" :alt="logoAlt" class="h-12 object-contain" />
</template>
