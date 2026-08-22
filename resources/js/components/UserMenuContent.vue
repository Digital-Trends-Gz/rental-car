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
import type { User } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { LogOut } from 'lucide-vue-next';
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
const csrfToken = computed(() => String(page.props?.csrf_token || ''));
const logoutUrl = computed(() => {
    const currentPath = stripLocalePrefix(String(page.url || '/'));

    if (typeof window !== 'undefined') {
        if (currentPath.startsWith('/superadmin')) {
            return `${window.location.origin}/superadmin/logout`;
        }

        return `${window.location.origin}/logout`;
    }

    if (currentPath.startsWith('/superadmin')) {
        return superadminLogout.url();
    }

    const slug = page.props?.current_tenant?.slug;
    if (slug) {
        return tenantLogout.url(slug);
    }

    return logout.url();
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
        <form class="w-full" :action="logoutUrl" method="post" @submit="handleLogout">
            <input type="hidden" name="_token" :value="csrfToken" />
            <button class="flex w-full items-center" type="submit" data-test="logout-button">
                <LogOut class="mr-2 h-4 w-4" />
                Log out
            </button>
        </form>
    </DropdownMenuItem>
</template>
