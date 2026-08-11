<script setup lang="ts">
import { cn } from '@/lib/utils';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useAttrs, type HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

const props = defineProps<Props>();
const attrs = useAttrs();

const page = usePage<any>();
const logoUrl = computed(() => page.props.tenant_site_settings?.logo_url || page.props.app_branding?.logo_url || '/logo/logo.png');
const logoAlt = computed(() => page.props.tenant_site_settings?.site_name || page.props.current_tenant?.name || page.props.name || 'Website logo');
const logoClass = computed(() => cn('h-12 w-auto object-contain', props.className, attrs.class as HTMLAttributes['class']));
</script>

<template>
   <img :src="logoUrl" :alt="logoAlt" :class="logoClass" />
</template>
