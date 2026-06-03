<script setup lang="ts">
import FlashToastStack from '@/components/FlashToastStack.vue';
import { useBrandTheme } from '@/composables/useBrandTheme';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const { themeVars } = useBrandTheme('#3b82f6', '#6d28d9');
</script>

<template>
    <div :style="themeVars">
        <AppSidebarLayout>
            <FlashToastStack
                :success="page.props.flash?.success ?? null"
                :error="page.props.flash?.error ?? null"
                :restricted-action="page.props.flash?.restricted_action ?? null"
                :trigger-key="page.url"
            />
            <slot />
        </AppSidebarLayout>
    </div>
</template>
