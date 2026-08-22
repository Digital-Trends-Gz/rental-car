<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useTrans } from '@/composables/useTrans';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Bell, ChevronDown, Languages, UserIcon } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage<any>();
const { locale, direction } = useTrans();
const currentUser = computed(() => page.props?.auth?.user ?? null);

const availableLocales = computed<string[]>(() =>
    Array.isArray(page.props?.available_locales) && page.props.available_locales.length
        ? page.props.available_locales
        : ['en']
);

const isRtl = computed(() => direction.value === 'rtl');
const notificationsAlign = computed<'start' | 'end'>(() => (isRtl.value ? 'start' : 'end'));
const showLocaleDropdown = computed(() => availableLocales.value.length > 1);

const normalizedRedirectPath = computed(() => {
    const currentPath = String(page.url || '/');
    const escapedLocales = availableLocales.value.map((item) => item.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const localeRegex = new RegExp(`^\\/(${escapedLocales.join('|')})(?=\\/|$)`);
    const strippedPath = currentPath.replace(localeRegex, '') || '/';

    return strippedPath.startsWith('/') ? strippedPath : `/${strippedPath}`;
});

const localeSwitcherOrigin = computed(() => {
    const tenantSlug = String(page.props.current_tenant?.slug || '').trim();
    const baseHost = String(page.props?.app_url_base || '').trim();
    const protocol = typeof window !== 'undefined' ? window.location.protocol : 'https:';

    if (tenantSlug && baseHost) {
        return `${protocol}//${tenantSlug}.${baseHost}`;
    }

    return typeof window !== 'undefined' ? window.location.origin : '';
});

const localeSwitcherUrl = (targetLocale: string) => {
    const path = `/locale/${targetLocale}?redirect=${encodeURIComponent(normalizedRedirectPath.value)}`;

    return `${localeSwitcherOrigin.value}${path}`;
};

const localeDisplayName = (localeCode: string) =>
    ({
        en: 'English',
        ar: 'Arabic',
        ur: 'Urdu',
    })[String(localeCode || '').toLowerCase()] || String(localeCode || '').toUpperCase();

const nextLocale = computed(() => {
    const locales = availableLocales.value;
    if (locales.length <= 1) {
        return locales[0] || 'en';
    }

    const currentIndex = locales.indexOf(String(locale.value || locales[0]));
    const nextIndex = currentIndex >= 0 ? (currentIndex + 1) % locales.length : 0;

    return locales[nextIndex] || locales[0] || 'en';
});

type UiNotification = {
    id: string;
    title: string;
    message: string;
    url: string;
    read_at: string | null;
    created_at: string | null;
    kind?: string;
};

const notifications = ref<UiNotification[]>(
    Array.isArray(page.props?.auth?.notifications) ? page.props.auth.notifications : [],
);
const unreadCount = ref<number>(Number(page.props?.auth?.notifications_unread_count ?? 0));

watch(
    () => page.props?.auth?.notifications,
    (value) => {
        notifications.value = Array.isArray(value) ? value : [];
    },
);

watch(
    () => page.props?.auth?.notifications_unread_count,
    (value) => {
        unreadCount.value = Number(value ?? 0);
    },
);

const localePrefix = computed(() => {
    const currentPath = String(page.url || '/');
    const escapedLocales = availableLocales.value.map((item) => item.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const localeRegex = new RegExp(`^\\/(${escapedLocales.join('|')})(?=\\/|$)`);
    const match = currentPath.match(localeRegex);
    return match ? `/${match[1]}` : '';
});

const csrfToken = computed(() => page.props?.csrf_token || '');
const notificationsBaseUrl = computed(() => `${localePrefix.value}/notifications`);

async function markAsRead(notificationId: string) {
    const target = notifications.value.find((n) => n.id === notificationId);
    if (!target || target.read_at) return;

    try {
        const response = await fetch(`${notificationsBaseUrl.value}/${notificationId}/read`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken.value,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({}),
        });

        if (!response.ok) return;

        target.read_at = new Date().toISOString();
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    } catch {
        // no-op: keep current UI if request fails
    }
}

async function markAllAsRead() {
    if (unreadCount.value <= 0) return;

    try {
        const response = await fetch(`${notificationsBaseUrl.value}/read-all`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken.value,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({}),
        });

        if (!response.ok) return;

        notifications.value = notifications.value.map((item) => ({
            ...item,
            read_at: item.read_at || new Date().toISOString(),
        }));
        unreadCount.value = 0;
    } catch {
        // no-op: keep current UI if request fails
    }
}
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div :class="[isRtl ? 'mr-auto' : 'ml-auto', 'flex items-center gap-2']">
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" class="relative h-8 w-8">
                        <Bell class="h-4 w-4" />
                        <span
                            v-if="unreadCount > 0"
                            :class="[
                                'absolute -top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white',
                                isRtl ? '-left-1' : '-right-1',
                            ]"
                        >
                            {{ unreadCount > 99 ? '99+' : unreadCount }}
                        </span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent :align="notificationsAlign" class="w-96 p-0">
                    <div class="flex items-center justify-between border-b px-3 py-2">
                        <p class="text-sm font-semibold">Notifications</p>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-7 px-2 text-xs"
                            :disabled="unreadCount <= 0"
                            @click="markAllAsRead"
                        >
                            Mark all read
                        </Button>
                    </div>

                    <div class="max-h-96 overflow-y-auto">
                        <div
                            v-for="item in notifications"
                            :key="item.id"
                            class="border-b px-3 py-2 last:border-b-0"
                            :class="item.read_at ? 'bg-white' : 'bg-blue-50/40'"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ item.title }}</p>
                                    <p class="line-clamp-2 text-xs text-muted-foreground">{{ item.message }}</p>
                                    <p class="mt-1 text-[11px] text-muted-foreground">
                                        {{ item.created_at ?? '' }}
                                    </p>
                                </div>
                                <div class="shrink-0 space-y-1 text-right">
                                    <a
                                        v-if="item.url"
                                        :href="item.url"
                                        class="block text-xs font-medium text-primary hover:underline"
                                        @click="markAsRead(item.id)"
                                    >
                                        Open
                                    </a>
                                    <button
                                        v-if="!item.read_at"
                                        type="button"
                                        class="block text-xs text-muted-foreground hover:text-foreground"
                                        @click="markAsRead(item.id)"
                                    >
                                        Mark read
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="notifications.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">
                            No notifications.
                        </div>
                    </div>
                </DropdownMenuContent>
            </DropdownMenu>

            <DropdownMenu v-if="showLocaleDropdown">
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        class="h-8 gap-1 rounded-md border border-sidebar-border/70 px-2 text-xs font-semibold text-muted-foreground hover:text-foreground"
                    >
                        <Languages class="h-4 w-4" />
                        <span class="hidden sm:inline">{{ localeDisplayName(String(locale || '')) }}</span>
                        <ChevronDown class="hidden h-3.5 w-3.5 sm:inline" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent :align="notificationsAlign" class="min-w-40">
                    <DropdownMenuItem v-for="localeCode in availableLocales" :key="localeCode" as-child>
                        <a
                            :href="localeSwitcherUrl(localeCode)"
                            class="flex w-full items-center justify-between gap-2"
                        >
                            <span>{{ localeDisplayName(localeCode) }}</span>
                        </a>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <a
                v-else-if="availableLocales.length > 1"
                :href="localeSwitcherUrl(nextLocale)"
                :title="localeDisplayName(nextLocale)"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-sidebar-border/70 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground sm:hidden"
            >
                <Languages class="h-4 w-4" />
            </a>

            <div
                v-else-if="availableLocales.length > 0"
                class="hidden items-center gap-1 rounded-md border border-sidebar-border/70 px-2 py-1 sm:flex"
            >
                <Languages class="h-4 w-4 text-muted-foreground" />
                <a
                    v-for="localeCode in availableLocales"
                    :key="localeCode"
                    :href="localeSwitcherUrl(localeCode)"
                    class="rounded px-2 py-0.5 text-xs font-semibold transition-colors"
                    :class="
                        locale === localeCode
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                >
                    {{ localeDisplayName(localeCode) }}
                </a>
            </div>

            <DropdownMenu v-if="currentUser">
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" class="h-8 w-8 rounded-full border border-sidebar-border/70">
                        <UserIcon class="h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent :align="notificationsAlign" class="min-w-56 rounded-lg p-1" :side-offset="4">
                    <UserMenuContent :user="currentUser" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
