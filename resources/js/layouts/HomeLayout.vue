<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { useBrandTheme } from '@/composables/useBrandTheme';
import { login as mainLogin, register as mainRegister, home as mainHome, fleet as mainFleet, about as mainAbout, contact as mainContact } from '@/routes';
import { login as tenantLogin, register as tenantRegister, home as tenantHome, fleet as tenantFleet, about as tenantAbout, contact as tenantContact } from '@/routes/tenant';
import { useTrans } from '@/composables/useTrans';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index as tenantAdminCarsIndex } from '@/routes/admin/cars/index';
import { index as tenantClientReservationsIndex } from '@/routes/client/reservations/index';
import { dashboard as superAdminDashboard } from '@/routes/superadmin/index';
import { Link, usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, Languages, Menu, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const $page = usePage<any>();
const { t, locale } = useTrans();
const props = withDefaults(defineProps<{ shellVariant?: 'tenant' | 'landing'; showLocaleSwitcher?: boolean }>(), {
    shellVariant: 'tenant',
    showLocaleSwitcher: true,
});
const currentTenant = computed(() => $page.props.current_tenant);
const tenantSiteSettings = computed(() => $page.props.tenant_site_settings ?? null);
const availableLocales = computed<string[]>(() =>
    Array.isArray($page.props?.available_locales) && $page.props.available_locales.length
        ? $page.props.available_locales
        : ['en']
);
const isTenant = computed(() => !!currentTenant.value);
const isLandingShell = computed(() => props.shellVariant === 'landing');
const role = computed(() => $page.props.auth.user?.role);
const mobileOpen = ref(false);
const appBranding = computed(() => $page.props.app_branding ?? {});
const landingSettings = computed(() => $page.props.landingSettings ?? {});
const hiddenLandingNavHrefs = new Set(['#how-it-works', '#faq']);
const currentYear = new Date().getFullYear();

const normalizedRedirectPath = computed(() => {
    const currentPath = String($page.url || '/');
    const escapedLocales = availableLocales.value.map((item) => item.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const localeRegex = new RegExp(`^\\/(${escapedLocales.join('|')})(?=\\/|$)`);
    const strippedPath = currentPath.replace(localeRegex, '') || '/';

    return strippedPath.startsWith('/') ? strippedPath : `/${strippedPath}`;
});

const localeSwitcherUrl = (targetLocale: string) =>
    `/locale/${targetLocale}?redirect=${encodeURIComponent(normalizedRedirectPath.value)}`;

const localeDisplayName = (localeCode: string) =>
    ({
        en: 'English',
        ar: 'Arabic',
        ur: 'Urdu',
    })[String(localeCode || '').toLowerCase()] || String(localeCode || '').toUpperCase();

const routeHelpers = computed(() => {
    if (isTenant.value) {
        return {
            home: tenantHome,
            fleet: tenantFleet,
            about: tenantAbout,
            contact: tenantContact,
            login: tenantLogin,
            register: tenantRegister,
            dashboard: role.value === 'admin' ? tenantAdminCarsIndex : tenantClientReservationsIndex
        };
    }
    return {
        home: mainHome,
        fleet: mainFleet,
        about: mainAbout,
        contact: mainContact,
        login: mainLogin,
        register: mainRegister,
        dashboard: superAdminDashboard
    };
});

const getUrl = (helper: any) => {
    if (typeof helper !== 'function') return '#';
    const slug = currentTenant.value?.slug;
    return slug ? helper(slug).url : helper().url;
};

const tenantBranding = computed(() => tenantSiteSettings.value ?? null);
const appName = computed(() => $page.props.name || 'Car4u');
const siteName = computed(() => tenantBranding.value?.site_name || currentTenant.value?.name || appName.value);
const siteLogoUrl = computed(() => tenantBranding.value?.logo_url || null);
const { primaryColor, secondaryColor, themeVars: globalThemeVars } = useBrandTheme();
const hasAppLogo = computed(() => !!appBranding.value?.logo_url);
const landingHomeUrl = computed(() => {
    const localeCode = String(locale.value || '').trim();
    const currentPath = String($page.url || '/');

    if (localeCode && (currentPath === `/${localeCode}` || currentPath.startsWith(`/${localeCode}/`))) {
        return `/${localeCode}`;
    }

    return mainHome().url;
});
const resolveLandingHref = (href: string) => {
    const value = String(href || '#').trim() || '#';

    if (value.startsWith('#')) {
        return `${landingHomeUrl.value}${value}`;
    }

    if (value.startsWith('/#')) {
        return `${landingHomeUrl.value}${value.slice(1)}`;
    }

    return value;
};
const landingNavLinks = computed(() => {
    const fallback = [
        { label: 'Cars', href: '#cars' },
        { label: 'Features', href: '#features' },
        { label: 'Clients', href: '#clients' },
        { label: 'Plans', href: '#pricing' },
        { label: 'Contact', href: '#contact' },
    ];
    const configuredLinks = Array.isArray(landingSettings.value?.navigation?.links)
        ? landingSettings.value.navigation.links
        : [];
    const links = configuredLinks.length ? configuredLinks : fallback;

    return links
        .map((link: any, index: number) => ({
            label: String(link?.label || fallback[index]?.label || ''),
            href: String(link?.href || fallback[index]?.href || '#'),
        }))
        .filter((link) => link.label !== '' && !hiddenLandingNavHrefs.has(link.href))
        .map((link) => ({
            ...link,
            href: resolveLandingHref(link.href),
        }));
});
const navigationCtaLabel = computed(() => landingSettings.value?.navigation?.cta_label || t('landing.start_free_trial'));
const landingFooterTitle = computed(() => landingSettings.value?.footer?.title || appName.value);
const landingFooterDescription = computed(() =>
    landingSettings.value?.footer?.description || 'Premium car rental service providing luxury and reliable vehicles for your transportation needs.'
);
const toggleLandingMenu = () => {
    mobileOpen.value = !mobileOpen.value;
};
const closeLandingMenu = () => {
    mobileOpen.value = false;
};
const themeVars = computed(() => ({
    ...globalThemeVars.value,
    '--tenant-primary': primaryColor.value,
    '--tenant-secondary': secondaryColor.value,
    '--tenant-gradient': `linear-gradient(90deg, ${primaryColor.value}, ${secondaryColor.value})`,
}));
</script>

<template>
    <template v-if="isLandingShell">
        <div class="min-h-screen bg-background" :style="themeVars">
            <nav class="fixed left-0 right-0 top-0 z-50 border-b border-border bg-background/95 shadow-sm backdrop-blur-lg">
                <div class="section-container relative flex h-16 max-w-7xl items-center justify-center">
                    <Link :href="landingHomeUrl" class="absolute left-4 inline-flex items-center gap-2 text-xl font-bold tracking-tight text-foreground sm:left-6 lg:left-8">
                        <AppLogoIcon class="h-6 w-6" />
                        <span v-if="!hasAppLogo">{{ appName }}</span>
                    </Link>

                    <div class="hidden items-center justify-center md:flex">
                        <div class="flex items-center justify-center gap-8">
                            <a
                                v-for="link in landingNavLinks"
                                :key="link.href"
                                :href="link.href"
                                class="whitespace-nowrap text-base font-medium text-muted-foreground transition-colors hover:text-foreground"
                            >
                                {{ link.label }}
                            </a>
                        </div>
                        <div class="absolute right-4 flex items-center gap-4 sm:right-6 lg:right-8">
                            <DropdownMenu v-if="props.showLocaleSwitcher && availableLocales.length > 1" :modal="false">
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        class="h-9 gap-2 rounded-full border border-border bg-background px-4 text-sm font-semibold text-muted-foreground hover:text-foreground"
                                    >
                                        <Languages class="h-4 w-4" />
                                        <span>{{ localeDisplayName(String(locale || '')) }}</span>
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
                                        </a>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        <Button as-child class="gradient-button rounded-full px-5" size="sm">
                            <Link :href="mainRegister().url">{{ navigationCtaLabel }}</Link>
                        </Button>
                        </div>
                    </div>

                    <button
                        class="ml-auto text-foreground md:hidden"
                        :aria-label="t('landing.toggle_menu')"
                        type="button"
                        @click="toggleLandingMenu"
                    >
                        <X v-if="mobileOpen" :size="22" />
                        <Menu v-else :size="22" />
                    </button>
                </div>

                <div v-if="mobileOpen" class="animate-fade-in border-b border-border bg-background px-4 pb-4 md:hidden">
                    <a
                        v-for="link in landingNavLinks"
                        :key="`mobile-${link.href}`"
                        :href="link.href"
                        class="block py-2 text-sm font-medium text-muted-foreground hover:text-foreground"
                        @click="closeLandingMenu"
                    >
                        {{ link.label }}
                    </a>
                    <DropdownMenu v-if="props.showLocaleSwitcher && availableLocales.length > 1" :modal="false">
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                class="mt-3 h-9 w-full justify-between rounded-xl border border-border bg-muted/50 px-4 text-sm font-semibold text-muted-foreground"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <Languages class="h-4 w-4" />
                                    {{ localeDisplayName(String(locale || '')) }}
                                </span>
                                <ChevronDown class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="min-w-44">
                            <DropdownMenuItem v-for="localeCode in availableLocales" :key="`mobile-${localeCode}`" as-child>
                                <a
                                    :href="localeSwitcherUrl(localeCode)"
                                    class="flex w-full items-center justify-between gap-2"
                                    @click="closeLandingMenu"
                                >
                                    <span>{{ localeDisplayName(localeCode) }}</span>
                                    <Check v-if="locale === localeCode" class="h-4 w-4 text-primary" />
                                </a>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Button as-child class="gradient-button mt-2 w-full rounded-full" size="sm">
                        <Link
                            :href="mainRegister().url"
                            @click="closeLandingMenu"
                        >
                            {{ navigationCtaLabel }}
                        </Link>
                    </Button>
                </div>
            </nav>

            <main class="pt-16">
                <slot />
            </main>

            <footer class="border-t border-border py-10">
                <div class="section-container text-center">
                    <h3 class="text-2xl font-bold text-foreground">{{ landingFooterTitle }}</h3>
                    <p class="mx-auto mt-3 max-w-2xl text-muted-foreground">{{ landingFooterDescription }}</p>
                    <p class="mt-6 text-sm text-muted-foreground">
                        &copy; {{ currentYear }} {{ appName }}. {{ t('landing.footer_rights') }}
                    </p>
                </div>
            </footer>
        </div>
    </template>

    <template v-else>
    <div class="tenant-public-theme" :style="themeVars">
        <header
            class="sticky top-0 z-50 border-b border-gray-100 bg-white/95 shadow-sm backdrop-blur-md"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <nav class="flex h-16 items-center justify-between">
                    <!--  Logo -->
                    <div class="flex flex-col items-center space-x-2">
                        <img v-if="siteLogoUrl" :src="siteLogoUrl" alt="logo" class="h-8 object-contain" />
                        <img v-else src="/logo/logo.png" alt="logo" class="h-6" />
                        <p v-if="!siteLogoUrl" class="font-bold">
                            <template v-if="currentTenant">
                                <span class="truncate max-w-[180px] inline-block align-bottom">{{ siteName }}</span>
                            </template>
                            <template v-else>
                                {{ appName }}
                            </template>
                        </p>
                    </div>

                    <!--  Navigation -->
                    <div class="hidden items-center space-x-8 md:flex">
                        <Link 
                            :href="getUrl(routeHelpers.home)" 
                            :class="{ 'text-orange-500': $page.url === '/', 'text-gray-700': $page.url !== '/' }" 
                            class="font-medium transition-colors hover:text-orange-500"
                        >
                            {{ t('nav.home') }}
                        </Link>
                        <Link 
                            :href="getUrl(routeHelpers.fleet)" 
                            :class="{ 'text-orange-500': $page.url.startsWith('/fleet'), 'text-gray-700': !$page.url.startsWith('/fleet') }" 
                            class="font-medium transition-colors hover:text-orange-500"
                        >
                            {{ t('nav.fleet') }}
                        </Link>
                        <Link 
                            :href="getUrl(routeHelpers.about)" 
                            :class="{ 'text-orange-500': $page.url === '/about', 'text-gray-700': $page.url !== '/about' }" 
                            class="font-medium transition-colors hover:text-orange-500"
                        >
                            {{ t('nav.about') }}
                        </Link>
                        <Link 
                            :href="getUrl(routeHelpers.contact)" 
                            :class="{ 'text-orange-500': $page.url === '/contact', 'text-gray-700': $page.url !== '/contact' }" 
                            class="font-medium transition-colors hover:text-orange-500"
                        >
                            {{ t('nav.contact') }}
                        </Link>
                    </div>

                    <!-- Auth Buttons -->
                    <div class="flex items-center space-x-3">
                        <div v-if="props.showLocaleSwitcher && availableLocales.length > 0" class="hidden items-center rounded-lg border border-gray-200 bg-white p-1 md:flex">
                            <a
                                v-for="localeCode in availableLocales"
                                :key="localeCode"
                                :href="localeSwitcherUrl(localeCode)"
                                class="rounded-md px-2 py-1 text-xs font-semibold transition-colors"
                                :class="locale === localeCode ? 'text-white' : 'text-gray-600 hover:text-orange-600'"
                                :style="locale === localeCode ? { backgroundColor: 'var(--tenant-primary)' } : undefined"
                            >
                                {{ localeDisplayName(localeCode) }}
                            </a>
                        </div>
                        <Link
                            v-if="$page.props.auth.user"
                            :href="getUrl(routeHelpers.dashboard)"
                            class="inline-flex items-center rounded-xl bg-gray-50 px-6 py-2.5 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-100 hover:shadow-md"
                        >
                            <svg
                                class="mr-2 h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
                                ></path>
                            </svg>
                            {{ t('nav.dashboard') }}
                        </Link>
                        <template v-else>
                            <Link
                                :href="getUrl(routeHelpers.login)"
                                class="inline-flex items-center px-6 py-2.5 text-sm font-semibold text-gray-700 transition-colors duration-200 hover:text-orange-600"
                            >
                                {{ t('nav.sign_in') }}
                            </Link>
                            <Link
                                :href="getUrl(routeHelpers.register)"
                                class="inline-flex items-center rounded-xl px-6 py-2.5 text-sm font-semibold text-white shadow-lg transition-all duration-200 hover:scale-105 hover:shadow-xl"
                                :style="{ background: 'var(--tenant-gradient)' }"
                            >
                                {{ t('nav.get_started') }}
                            </Link>
                        </template>
                    </div>
                </nav>
            </div>
        </header>

        <slot />

        <!--  Footer -->
        <footer class="bg-gray-900 py-16 text-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-12 md:grid-cols-4">
                    <div class="space-y-6">
                        <div class="flex items-center space-x-2">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-orange-500 to-orange-600"
                            >
                                <svg
                                    class="h-6 w-6 text-white"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z"
                                    ></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">
                                    <span>{{ siteName }}</span>
                                </h3>
                                <p class="text-xs font-medium text-gray-400">
                                    {{ t('footer.premium_cars') }}
                                </p>
                            </div>
                        </div>
                        <p class="leading-relaxed text-gray-400">
                            {{ tenantSiteSettings?.footer?.description?.[locale] || tenantSiteSettings?.footer?.description?.en || t('footer.description') }}
                        </p>
                    </div>

                    <div class="space-y-6">
                        <h4 class="text-lg font-semibold">{{ t('footer.services') }}</h4>
                        <ul class="space-y-3 text-gray-400">
                            <li>
                                <a
                                    href="#"
                                    class="transition-colors hover:text-orange-500"
                                    >{{ t('footer.luxury_car_rental') }}</a
                                >
                            </li>
                            <li>
                                <a
                                    href="#"
                                    class="transition-colors hover:text-orange-500"
                                    >{{ t('footer.long_term_rental') }}</a
                                >
                            </li>
                            <li>
                                <a
                                    href="#"
                                    class="transition-colors hover:text-orange-500"
                                    >{{ t('footer.corporate_solutions') }}</a
                                >
                            </li>
                            <li>
                                <a
                                    href="#"
                                    class="transition-colors hover:text-orange-500"
                                    >{{ t('footer.airport_transfers') }}</a
                                >
                            </li>
                        </ul>
                    </div>

                    <div class="space-y-6">
                        <h4 class="text-lg font-semibold">{{ t('footer.support') }}</h4>
                        <ul class="space-y-3 text-gray-400">
                            <li>
                                <a
                                    :href="getUrl(routeHelpers.contact)"
                                    class="transition-colors hover:text-orange-500"
                                    >{{ t('footer.contact_us') }}</a
                                >
                            </li>
                            <li>
                                <a
                                    href="#"
                                    class="transition-colors hover:text-orange-500"
                                    >{{ t('footer.help_center') }}</a
                                >
                            </li>
                            <li>
                                <a
                                    href="#"
                                    class="transition-colors hover:text-orange-500"
                                    >{{ t('footer.terms') }}</a
                                >
                            </li>
                            <li>
                                <a
                                    href="#"
                                    class="transition-colors hover:text-orange-500"
                                    >{{ t('footer.privacy') }}</a
                                >
                            </li>
                        </ul>
                    </div>

                    <div class="space-y-6">
                        <h4 class="text-lg font-semibold">{{ t('footer.contact_info') }}</h4>
                        <div class="space-y-3 text-gray-400">
                            <div class="flex items-center space-x-3">
                                <svg
                                    class="h-5 w-5"
                                    :style="{ color: 'var(--tenant-primary)' }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
                                    ></path>
                                </svg>
                                <span class="ltr-value inline-block">{{ tenantSiteSettings?.contact?.phone || '+1 (555) 123-4567' }}</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <svg
                                    class="h-5 w-5"
                                    :style="{ color: 'var(--tenant-primary)' }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                    ></path>
                                </svg>
                                <span>{{ tenantSiteSettings?.contact?.email || 'hello@realrent.com' }}</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <svg
                                    class="h-5 w-5"
                                    :style="{ color: 'var(--tenant-primary)' }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                                    ></path>
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                    ></path>
                                </svg>
                                <span>{{ tenantSiteSettings?.contact?.address?.[locale] || tenantSiteSettings?.contact?.address?.en || '123 Business Ave, City' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-2 border-t border-gray-800 pt-8">
                   
                        <p class="text-gray-400 text-center">
                            &copy; {{ new Date().getFullYear() }} {{ siteName }}. {{ t('footer.rights') }}
                        </p>
                       
                </div>
            </div>
        </footer>
    </div>
</template>
</template>

<style>
.tenant-public-theme {
    --tenant-primary-50: color-mix(in srgb, var(--tenant-primary) 8%, white);
    --tenant-primary-100: color-mix(in srgb, var(--tenant-primary) 14%, white);
    --tenant-primary-200: color-mix(in srgb, var(--tenant-primary) 24%, white);
}

/* Text colors */
.tenant-public-theme .text-orange-500,
.tenant-public-theme .text-orange-600,
.tenant-public-theme .text-orange-700 {
    color: var(--tenant-primary) !important;
}

.tenant-public-theme .text-orange-100 {
    color: color-mix(in srgb, var(--tenant-primary) 35%, white) !important;
}

.tenant-public-theme [class*='hover:text-orange-']:hover {
    color: var(--tenant-secondary) !important;
}

.tenant-public-theme [class*='group-hover:text-orange-'] {
    transition-property: color, fill, stroke;
}

.tenant-public-theme .group:hover [class*='group-hover:text-orange-'],
.tenant-public-theme .group\/btn:hover [class*='group-hover:text-orange-'] {
    color: var(--tenant-secondary) !important;
}

/* Background colors */
.tenant-public-theme .bg-orange-50 {
    background-color: var(--tenant-primary-50) !important;
}

.tenant-public-theme .bg-orange-100 {
    background-color: var(--tenant-primary-100) !important;
}

.tenant-public-theme .bg-orange-200 {
    background-color: var(--tenant-primary-200) !important;
}

.tenant-public-theme .bg-orange-500,
.tenant-public-theme .bg-orange-600 {
    background-color: var(--tenant-primary) !important;
}

.tenant-public-theme [class*='hover:bg-orange-']:hover {
    background-color: var(--tenant-secondary) !important;
}

/* Border colors */
.tenant-public-theme .border-orange-200,
.tenant-public-theme .border-orange-300,
.tenant-public-theme .border-orange-500 {
    border-color: var(--tenant-primary) !important;
}

.tenant-public-theme .border-t-orange-500 {
    border-top-color: var(--tenant-primary) !important;
}

.tenant-public-theme [class*='hover:border-orange-']:hover {
    border-color: var(--tenant-primary) !important;
}

.tenant-public-theme [class*='focus:border-orange-']:focus {
    border-color: var(--tenant-primary) !important;
}

/* Ring / outline */
.tenant-public-theme .ring-orange-200,
.tenant-public-theme .ring-orange-500 {
    --tw-ring-color: color-mix(in srgb, var(--tenant-primary) 35%, white) !important;
}

.tenant-public-theme [class*='focus:ring-orange-']:focus {
    --tw-ring-color: color-mix(in srgb, var(--tenant-primary) 30%, white) !important;
}

/* SVG colors */
.tenant-public-theme .fill-orange-500 {
    fill: var(--tenant-primary) !important;
}

.tenant-public-theme .stroke-orange-500,
.tenant-public-theme .stroke-orange-600 {
    stroke: var(--tenant-primary) !important;
}

/* Gradient utilities (Tailwind uses CSS vars) */
.tenant-public-theme [class*='from-orange-'] {
    --tw-gradient-from: var(--tenant-primary) !important;
    --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to) !important;
}

.tenant-public-theme [class*='via-orange-'] {
    --tw-gradient-via: var(--tenant-primary) !important;
    --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-via),
        var(--tw-gradient-to) !important;
}

.tenant-public-theme [class*='to-orange-'] {
    --tw-gradient-to: var(--tenant-secondary) !important;
}

.tenant-public-theme [class*='hover:from-orange-']:hover {
    --tw-gradient-from: var(--tenant-primary) !important;
    --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to) !important;
}

.tenant-public-theme [class*='hover:to-orange-']:hover {
    --tw-gradient-to: var(--tenant-secondary) !important;
}

/* Public CTA buttons that are dark by default (CarCard, etc.) should also follow tenant theme */
.tenant-public-theme button[class*='from-slate-700'][class*='to-slate-900'],
.tenant-public-theme a[class*='from-slate-700'][class*='to-slate-900'] {
    --tw-gradient-from: var(--tenant-primary) !important;
    --tw-gradient-to: var(--tenant-secondary) !important;
    --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to) !important;
}

.tenant-public-theme button[class*='from-slate-700'][class*='to-slate-900']:hover,
.tenant-public-theme a[class*='from-slate-700'][class*='to-slate-900']:hover {
    --tw-gradient-from: var(--tenant-secondary) !important;
    --tw-gradient-to: var(--tenant-primary) !important;
}

.tenant-public-theme [class*='focus:ring-orange-']:focus-visible {
    outline-color: var(--tenant-primary) !important;
}
</style>
