<script setup lang="ts">
import { useBrandTheme } from '@/composables/useBrandTheme';
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const { themeVars } = useBrandTheme();
</script>

<template>
    <div :style="themeVars">
        <AppHeaderLayout :breadcrumbs="breadcrumbs">
            <slot />
        </AppHeaderLayout>
    </div>
</template>
