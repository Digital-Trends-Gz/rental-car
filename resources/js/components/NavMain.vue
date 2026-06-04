<script setup lang="ts">
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { urlIsActive } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface SidebarNavItem extends NavItem {
    key?: string;
    children?: SidebarNavItem[];
}

const props = defineProps<{
    items: SidebarNavItem[];
}>();

const page = usePage();
const openState = reactive<Record<string, boolean>>({});
const getItemKey = (item: SidebarNavItem) => item.key || String(item.href || item.title);

const isGroupActive = (item: SidebarNavItem) =>
    Boolean(
        item.children?.some((child) => child.href && urlIsActive(child.href, page.url)),
    );

const syncOpenState = () => {
    for (const item of props.items) {
        if (!item.children?.length) continue;

        if (isGroupActive(item)) {
            openState[getItemKey(item)] = true;
        }
    }
};

watch(
    () => page.url,
    () => {
        syncOpenState();
    },
    { immediate: true },
);
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu class="space-y-2">
            <template v-for="item in items" :key="getItemKey(item)">
                <SidebarMenuItem v-if="!item.children?.length">
                    <SidebarMenuButton
                        as-child
                        :is-active="urlIsActive(item.href, page.url)"
                        :size="item.key === 'dashboard' ? 'lg' : 'default'"
                        :tooltip="item.title"
                        :class="item.key === 'dashboard'
                            ? 'rounded-2xl data-[active=true]:!bg-gradient-to-r data-[active=true]:from-sky-500 data-[active=true]:to-cyan-500 data-[active=true]:!text-white data-[active=true]:shadow-lg'
                            : ''"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>

                <SidebarMenuItem v-else>
                    <Collapsible
                        :open="openState[getItemKey(item)] ?? true"
                        class="group/collapsible"
                        @update:open="(value) => (openState[getItemKey(item)] = value)"
                    >
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                :is-active="isGroupActive(item)"
                                :tooltip="item.title"
                            >
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <ChevronDown
                                    class="ml-auto size-4 transition-transform group-data-[state=open]/collapsible:rotate-180"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>

                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem
                                    v-for="child in item.children"
                                    :key="getItemKey(child)"
                                >
                                    <SidebarMenuSubButton
                                        v-if="child.href"
                                        as-child
                                        :is-active="urlIsActive(child.href, page.url)"
                                    >
                                        <Link :href="child.href">
                                            <component :is="child.icon" />
                                            <span>{{ child.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </Collapsible>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
