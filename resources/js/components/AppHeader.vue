<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { useTrans } from '@/composables/useTrans';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Bell, Check, ChevronDown, FileCheck, Languages, LifeBuoy, Menu, UserRound } from 'lucide-vue-next';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import { toUrl, urlIsActive } from '@/lib/utils';
import { index as reservationsIndex } from "@/routes/client/reservations/index";
import { edit as profileEdit } from '@/routes/client/profile';
import { index as supportIndex } from '@/routes/client/support/index';
import { home } from '@/routes';
import type { BreadcrumbItem, NavItem } from '@/types';
import { InertiaLinkProps, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItem[];
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const { t, locale } = useTrans();
const auth = computed(() => page.props.auth);
const notifications = ref<Array<{
    id: string;
    title: string;
    message: string;
    url: string;
    read_at: string | null;
    created_at: string | null;
    kind?: string;
}>>(Array.isArray(page.props?.auth?.notifications) ? page.props.auth.notifications : []);
const unreadCount = ref<number>(Number(page.props?.auth?.notifications_unread_count ?? 0));

const isCurrentRoute = computed(
    () => (url: NonNullable<InertiaLinkProps['href']>) =>
        urlIsActive(url, page.url),
);

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
    const locales = Array.isArray(page.props?.available_locales) && page.props.available_locales.length
        ? page.props.available_locales
        : ['en'];
    const escapedLocales = locales.map((item: string) => item.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const localeRegex = new RegExp(`^\\/(${escapedLocales.join('|')})(?=\\/|$)`);
    const match = currentPath.match(localeRegex);
    return match ? `/${match[1]}` : '';
});

const availableLocales = computed<string[]>(() =>
    Array.isArray(page.props?.available_locales) && page.props.available_locales.length
        ? page.props.available_locales
        : ['en'],
);

const normalizedRedirectPath = computed(() => {
    const currentPath = String(page.url || '/');
    const escapedLocales = availableLocales.value.map((item: string) => item.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
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

const fallbackLocaleNames: Record<string, string> = {
    en: 'English',
    ar: 'Arabic',
    ur: 'Urdu',
};

const localeDisplayName = (localeCode: string) => {
    const normalizedLocale = String(localeCode || '').toLowerCase().split('-')[0];
    const key = `locale_switcher.language_names.${normalizedLocale}`;
    const translatedName = t(key);

    return translatedName === key
        ? fallbackLocaleNames[normalizedLocale] || normalizedLocale.toUpperCase()
        : translatedName;
};

const siteHomeUrl = computed(() => `${localePrefix.value || ''}/`);

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
        // no-op
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
        // no-op
    }
}

const activeItemStyles = computed(
    () => (url: NonNullable<InertiaLinkProps['href']>) =>
        isCurrentRoute.value(toUrl(url))
            ? 'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100'
            : '',
);

const mainNavItems = computed<NavItem[]>(() => {
    const slug = page.props.current_tenant?.slug;
    if (!slug) return [];

    return [
        {
            title: t('client_pages.layout.nav.reservations'),
            href: reservationsIndex(slug).url,
            icon: FileCheck,
        },
        {
            title: t('client_pages.layout.nav.support'),
            href: supportIndex(slug).url,
            icon: LifeBuoy,
        },
        {
            title: t('client_pages.layout.nav.profile'),
            href: profileEdit(slug).url,
            icon: UserRound,
        },
    ];
});
</script>

<template>
    <div>
        <div class="border-b border-sidebar-border/80">
            <div class="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                <!-- Mobile Menu -->
                <div class="lg:hidden">
                    <Sheet>
                        <SheetTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="mr-2 h-9 w-9"
                            >
                                <Menu class="h-5 w-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-[300px] p-6">
                            <SheetTitle class="sr-only">
                                {{ t('client_pages.layout.nav.navigation_menu') }}
                            </SheetTitle>
                            <SheetHeader class="flex justify-start text-left">
                                <AppLogoIcon
                                    class="size-6 fill-current text-black dark:text-white"
                                />
                            </SheetHeader>
                            <div
                                class="flex h-full flex-1 flex-col justify-between space-y-4 py-6"
                            >
                                <nav class="-mx-3 space-y-1">
                                    <Link
                                        :href="siteHomeUrl"
                                        class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent"
                                    >
                                        {{ t('client_pages.layout.nav.back_to_site') }}
                                    </Link>
                                    <Link
                                        v-for="item in mainNavItems"
                                        :key="item.title"
                                        :href="item.href"
                                        class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent"
                                        :class="activeItemStyles(item.href)"
                                    >
                                        <component
                                            v-if="item.icon"
                                            :is="item.icon"
                                            class="h-5 w-5"
                                        />
                                        {{ item.title }}
                                    </Link>
                                </nav>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                <Link :href="home.url()" class="flex items-center gap-x-2">
                    <AppLogo />
                </Link>

                <!-- Desktop Menu -->
                <div class="hidden h-full lg:flex lg:flex-1">
                    <NavigationMenu class="ml-10 flex h-full items-stretch">
                        <NavigationMenuList
                            class="flex h-full items-stretch space-x-2"
                        >
                            <NavigationMenuItem
                                v-for="(item, index) in mainNavItems"
                                :key="index"
                                class="relative flex h-full items-center"
                            >
                                <Link
                                    :class="[
                                        navigationMenuTriggerStyle(),
                                        activeItemStyles(item.href),
                                        'h-9 cursor-pointer px-3',
                                    ]"
                                    :href="item.href"
                                >
                                    <component
                                        v-if="item.icon"
                                        :is="item.icon"
                                        class="mr-2 h-4 w-4"
                                    />
                                    {{ item.title }}
                                </Link>
                                <div
                                    v-if="isCurrentRoute(item.href)"
                                    class="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white"
                                ></div>
                            </NavigationMenuItem>
                        </NavigationMenuList>
                    </NavigationMenu>
                </div>

                <div class="ml-auto flex items-center space-x-2">
                    <Link :href="siteHomeUrl" class="hidden sm:inline-flex">
                        <Button variant="outline" size="sm">
                            {{ t('client_pages.layout.nav.back_to_site') }}
                        </Button>
                    </Link>

                    <DropdownMenu v-if="availableLocales.length > 1" :modal="false">
                        <DropdownMenuTrigger :as-child="true">
                            <Button variant="ghost" size="sm" class="gap-2">
                                <Languages class="h-4 w-4" />
                                <span class="hidden sm:inline">{{ localeDisplayName(String(locale || '')) }}</span>
                                <ChevronDown class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="min-w-40">
                            <DropdownMenuItem v-for="localeCode in availableLocales" :key="localeCode" as-child>
                                <a
                                    :href="localeSwitcherUrl(localeCode)"
                                    class="flex w-full items-center justify-between gap-2"
                                >
                                    <span>{{ localeDisplayName(localeCode) }}</span>
                                    <Check v-if="locale === localeCode" class="h-4 w-4 text-primary" />
                                </a>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button variant="ghost" size="icon" class="relative size-10 w-auto rounded-full p-1">
                                <Bell class="h-4 w-4" />
                                <span
                                    v-if="unreadCount > 0"
                                    class="absolute -top-1 -right-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white"
                                >
                                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                                </span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-96 p-0">
                            <div class="flex items-center justify-between border-b px-3 py-2">
                                <p class="text-sm font-semibold">
                                    {{ t('client_pages.layout.notifications.title') }}
                                </p>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 px-2 text-xs"
                                    :disabled="unreadCount <= 0"
                                    @click="markAllAsRead"
                                >
                                    {{ t('client_pages.layout.notifications.mark_all_read') }}
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
                                                {{ t('client_pages.layout.notifications.open') }}
                                            </a>
                                            <button
                                                v-if="!item.read_at"
                                                type="button"
                                                class="block text-xs text-muted-foreground hover:text-foreground"
                                                @click="markAsRead(item.id)"
                                            >
                                                {{ t('client_pages.layout.notifications.mark_read') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="notifications.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">
                                    {{ t('client_pages.layout.notifications.empty') }}
                                </div>
                            </div>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary"
                            >
                                <Avatar
                                    class="size-8 overflow-hidden rounded-full"
                                >
                                    <AvatarImage
                                        v-if="auth.user.avatar"
                                        :src="auth.user.avatar"
                                        :alt="auth.user.name"
                                    />
                                    <AvatarFallback
                                        class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ getInitials(auth.user?.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>

        <div
            v-if="props.breadcrumbs.length > 1"
            class="flex w-full border-b border-sidebar-border/70"
        >
            <div
                class="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </div>
</template>
