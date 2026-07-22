<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { logout } from '@/routes';
import { logout as superadminLogout } from '@/routes/superadmin';
import { logout as tenantLogout } from '@/routes/tenant/index.ts';
import { edit } from '@/routes/profile';
import type { User } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, UserIcon } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    user: User;
}

const handleLogout = () => {
    router.flushAll();
};

const page = usePage<any>();
const availableLocales = computed<string[]>(() =>
    Array.isArray(page.props?.available_locales) && page.props.available_locales.length
        ? page.props.available_locales
        : ['en'],
);
const stripLocalePrefix = (path: string) => {
    const escapedLocales = availableLocales.value.map((locale: string) =>
        locale.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'),
    );
    const localeRegex = new RegExp(`^\\/(?:${escapedLocales.join('|')})(?=\\/|$)`);

    return path.replace(localeRegex, '') || '/';
};
const logoutRoute = computed(() => {
    const currentPath = stripLocalePrefix(String(page.url || '/'));

    if (currentPath.startsWith('/superadmin')) {
        return superadminLogout();
    }

    const slug = page.props?.current_tenant?.slug;
    if (slug) {
        return tenantLogout(slug);
    }

    return logout();
});

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <!-- <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="edit()" prefetch as="button">
                <UserIcon class="mr-2 h-4 w-4" />
                Account
            </Link>
        </DropdownMenuItem> -->
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full"
            :href="logoutRoute"
            method="post"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
