<script setup lang="ts">
import FlashToastStack from '@/components/FlashToastStack.vue';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <AppSidebarLayout>
        <FlashToastStack
            :success="page.props.flash?.success ?? null"
            :error="page.props.flash?.error ?? null"
            :restricted-action="page.props.flash?.restricted_action ?? null"
            :trigger-key="page.url"
        />
        <slot />
    </AppSidebarLayout>
</template>
