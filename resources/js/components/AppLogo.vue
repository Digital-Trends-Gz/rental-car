<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<any>();
const hasCustomLogo = computed(() => !!(page.props.tenant_site_settings?.logo_url || page.props.app_branding?.logo_url));
</script>

<template>
    <AppLogoIcon />
    <div v-if="!hasCustomLogo" class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{ page.props.name }}</span>
    </div>
</template>
