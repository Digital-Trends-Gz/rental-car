<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { useBrandTheme } from '@/composables/useBrandTheme';
import { login as mainLogin, register as mainRegister, home as mainHome, fleet as mainFleet, about as mainAbout, contact as mainContact } from '@/routes';
import { login as tenantLogin, register as tenantRegister, home as tenantHome, fleet as tenantFleet, about as tenantAbout, contact as tenantContact } from '@/routes/tenant/index.ts';
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
import { Apple, ArrowUp, Check, ChevronDown, Facebook, Instagram, Languages, Linkedin, Menu, MessageCircle, Play, Smartphone, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const $page = usePage<any>();
const { t, locale, direction } = useTrans();
const props = withDefaults(defineProps<{ shellVariant?: 'tenant' | 'landing'; showLocaleSwitcher?: boolean }>(), {
    shellVariant: 'tenant',
    showLocaleSwitcher: true,
});
const currentTenant = computed(() => $page.props.current_tenant);
const tenantSiteSettings = computed(() => $page.props.tenant_site_settings ?? null);
const availableLocales = computed<string[]>(() =>
    Array.isArray($page.props?.available_locales) && $page.props.available_locales.length
        ? $page.props.available_locales
        : Array.isArray($page.props?.availableLocales) && $page.props.availableLocales.length
            ? $page.props.availableLocales
            : ['en']
);
const isTenant = computed(() => !!currentTenant.value);
const isLandingShell = computed(() => props.shellVariant === 'landing');
const role = computed(() => $page.props.auth.user?.role);
const mobileOpen = ref(false);
const showScrollTop = ref(false);
const appBranding = computed(() => $page.props.app_branding ?? {});
const landingSettings = computed(() => $page.props.landingSettings ?? {});
const translatedLandingValue = (key: string, fallback = '') => {
    const value = t(key);

    return value === key ? fallback : value;
};
const hiddenLandingNavHrefs = new Set(['#how-it-works', '#faq']);
const landingPagesWithoutClientsNav = new Set([
    '/applications',
    '/car-rental-apps',
    '/fleet',
    '/plans',
    '/pricing-plans',
    '/privacy-policy',
    '/security-policy',
    '/terms-conditions',
    '/terms-of-use',
]);
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

const fallbackLocaleNames: Record<string, string> = {
    en: 'English',
    ar: 'Arabic',
    ur: 'Urdu',
};

const localeDisplayName = (localeCode: string) => {
    const normalizedLocale = String(localeCode || '').toLowerCase();
    const translatedName = translatedLandingValue(`locale_switcher.language_names.${normalizedLocale}`);
    const configuredName = String(
        landingSettings.value?.locale_switcher?.language_names?.[normalizedLocale] || '',
    ).trim();

    return translatedName || configuredName || fallbackLocaleNames[normalizedLocale] || normalizedLocale.toUpperCase();
};

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

    if (value.startsWith('/')) {
        return localizedLandingPath(value);
    }

    return value;
};
const normalizeInternalPath = (path: string) => {
    let rawPath = String(path || '/').trim() || '/';

    const mainDomain = 'real-rent-car-main.test';
    const mainDomainRegex = new RegExp(`^(?:https?:)?//(?:www\\.)?${mainDomain.replace('.', '\\.')}`, 'i');
    
    if (mainDomainRegex.test(rawPath)) {
        rawPath = rawPath.replace(mainDomainRegex, '');
    }

    if (/^[a-z][a-z0-9+.-]*:/i.test(rawPath) || rawPath.startsWith('//')) {
        try {
            const origin = typeof window !== 'undefined' ? window.location.origin : '';
            if (origin) {
                const parsedUrl = new URL(rawPath, origin);
                if (parsedUrl.origin === origin) {
                    return `${parsedUrl.pathname}${parsedUrl.search}${parsedUrl.hash}` || '/';
                }
            }
        } catch {
            return rawPath;
        }

        return rawPath;
    }

    return rawPath.startsWith('/') ? rawPath : `/${rawPath}`;
};
const localizedLandingPath = (path: string) => {
    const normalizedPath = normalizeInternalPath(path);

    if (/^[a-z][a-z0-9+.-]*:/i.test(normalizedPath) || normalizedPath.startsWith('//')) {
        return normalizedPath;
    }

    const currentPath = String($page.url || '/');
    const currentFirstSegment = currentPath.split('/').filter(Boolean)[0] || '';
    const normalizedLocales = availableLocales.value.map((item) => String(item || '').toLowerCase());
    const urlLocale = normalizedLocales.includes(currentFirstSegment.toLowerCase()) ? currentFirstSegment : '';
    const activeLocale = urlLocale || String(locale.value || '').trim().toLowerCase().split('-')[0];

    if (
        activeLocale &&
        normalizedLocales.includes(activeLocale) &&
        (normalizedPath === `/${activeLocale}` || normalizedPath.startsWith(`/${activeLocale}/`))
    ) {
        return normalizedPath;
    }

    if (activeLocale && activeLocale !== 'en' && normalizedLocales.includes(activeLocale)) {
        return normalizedPath === '/' ? `/${activeLocale}` : `/${activeLocale}${normalizedPath}`;
    }

    return normalizedPath;
};
const translatedLabel = (key: string, fallback: string) => {
    const value = t(key);

    return value === key ? fallback : value;
};
const activeLocaleBase = computed(() => String(locale.value || 'en').toLowerCase().split('-')[0]);
const localizedFallback = (fallback: string, localized: Partial<Record<string, string>> = {}) =>
    localized[activeLocaleBase.value] || fallback;
const translatedLandingLabel = (key: string, fallback: string, localized: Partial<Record<string, string>> = {}) => {
    const localizedValue = localized[activeLocaleBase.value];
    if (localizedValue) {
        return localizedValue;
    }

    const translated = translatedLabel(`welcome.${key}`, '');

    return translated || fallback;
};
const footerNavLabel = (key: string, fallback: string, localized: Partial<Record<string, string>> = {}) => {
    const configured = String(landingSettings.value?.footer?.[key] || '').trim();
    if (configured) {
        return configured;
    }

    const translated = translatedLandingLabel(`footer_${key.replace(/^nav_/, '')}`, fallback, localized);

    return translated || fallback;
};
const footerLabels = {
    cars: () => footerNavLabel('nav_cars', 'Cars', { ar: '\u0645\u0639\u0631\u0636 \u0627\u0644\u0633\u064a\u0627\u0631\u0627\u062a' }),
    features: () => footerNavLabel('nav_features', 'Features', { ar: '\u0627\u0644\u0645\u0645\u064a\u0632\u0627\u062a' }),
    application: () => footerNavLabel('nav_application', 'Application', { ar: '\u062a\u0637\u0628\u064a\u0642\u0627\u062a \u0627\u0644\u0645\u0648\u0628\u0627\u064a\u0644' }),
    plans: () => footerNavLabel('nav_plans', 'Plans', { ar: '\u062e\u0637\u0637 \u0627\u0644\u0627\u0634\u062a\u0631\u0627\u0643' }),
    privacy: () => footerNavLabel('nav_privacy', 'Privacy', { ar: '\u0627\u0644\u062e\u0635\u0648\u0635\u064a\u0629' }),
    terms: () => footerNavLabel('nav_terms', 'Terms', { ar: '\u0627\u0644\u0634\u0631\u0648\u0637' }),
    securityPolicy: () => footerNavLabel('nav_security_policy', 'Security Policy', { ar: '\u0633\u064a\u0627\u0633\u0629 \u0627\u0644\u0623\u0645\u0627\u0646' }),
};
const navigationNavLabel = (key: string, fallback: string) =>
    String(landingSettings.value?.navigation?.[key] || '').trim() || fallback;
const landingNavLabel = (href: string, fallback: string) => {
    const normalizedHref = String(href || '').trim().toLowerCase();
    const baseHref = normalizedHref.replace(/^\/(?:applications|car-rental-apps)$/, '#application')
        .replace(/^\/plans$/, '#pricing')
        .replace(/^\/pricing-plans$/, '#pricing');

    if (baseHref === '#cars' || baseHref === '/#cars') {
        return footerLabels.cars();
    }

    if (baseHref === '#features' || baseHref === '/#features') {
        return footerLabels.features();
    }

    if (baseHref === '#application' || baseHref === '/#application') {
        return footerLabels.application();
    }

    if (baseHref === '#pricing' || baseHref === '/#pricing') {
        return footerLabels.plans();
    }

    if (baseHref === '#clients' || baseHref === '/#clients') {
        return navigationNavLabel('nav_clients', fallback);
    }

    if (baseHref === '#contact' || baseHref === '/#contact') {
        return navigationNavLabel('nav_contact', fallback);
    }

    if (baseHref === '/privacy-policy') {
        return footerLabels.privacy();
    }

    if (baseHref === '/terms-of-use' || baseHref === '/terms-conditions') {
        return footerLabels.terms();
    }

    if (baseHref === '/security-policy') {
        return footerLabels.securityPolicy();
    }

    return fallback;
};
const normalizedLandingPathOnly = computed(() => {
    const path = String(normalizedRedirectPath.value || '/').split(/[?#]/)[0] || '/';

    return path.startsWith('/') ? path : `/${path}`;
});
const shouldHideClientsNav = computed(() =>
    landingPagesWithoutClientsNav.has(normalizedLandingPathOnly.value),
);
const isClientsNavLink = (link: { label: string; href: string }) => {
    const href = String(link.href || '').trim().toLowerCase();

    return href === '#clients' || href === '/#clients';
};
const landingStaticPageLinks = computed(() => [
    {
        label: footerLabels.privacy(),
        href: localizedLandingPath('/privacy-policy'),
    },
    {
        label: footerLabels.terms(),
        href: localizedLandingPath('/terms-of-use'),
    },
    {
        label: footerLabels.securityPolicy(),
        href: localizedLandingPath('/security-policy'),
    },
]);
const landingNavLinks = computed(() => {
    const fallback = [
        { label: 'Cars', href: '#cars' },
        { label: 'Features', href: '#features' },
        { label: 'Application', href: '#application' },
        { label: 'Clients', href: '#clients' },
        { label: 'Plans', href: '#pricing' },
        { label: 'Contact', href: '#contact' },
    ];
    const configuredLinks = Array.isArray(landingSettings.value?.navigation?.links)
        ? landingSettings.value.navigation.links
        : [];
    const links = configuredLinks.length ? configuredLinks : fallback;
    const normalizedLinks = links
        .map((link: any, index: number) => ({
            label: landingNavLabel(
                String(link?.href || fallback[index]?.href || '#'),
                String(link?.label || fallback[index]?.label || ''),
            ),
            href: String(link?.href || fallback[index]?.href || '#')
                .replace(/^\/(?:applications|car-rental-apps)$/, '#application')
                .replace(/^\/plans$/, '#pricing')
                .replace(/^\/pricing-plans$/, '#pricing'),
        }))
        .filter((link) =>
            link.label !== '' &&
            !hiddenLandingNavHrefs.has(link.href) &&
            !(shouldHideClientsNav.value && isClientsNavLink(link))
        )

    if (
        landingSettings.value?.mobile_apps_section?.enabled !== false &&
        !normalizedLinks.some((link) => ['/applications', '/car-rental-apps', '#application'].includes(link.href))
    ) {
        const featuresIndex = normalizedLinks.findIndex((link) => link.href === '#features');
        const applicationLink = { label: footerLabels.application(), href: '#application' };

        if (featuresIndex >= 0) {
            normalizedLinks.splice(featuresIndex + 1, 0, applicationLink);
        } else {
            normalizedLinks.push(applicationLink);
        }
    }

    return normalizedLinks.map((link) => ({
        ...link,
        href: resolveLandingHref(link.href),
    }));
});
const navigationCtaLabel = computed(() => landingSettings.value?.navigation?.cta_label || t('landing.start_free_trial'));
const landingRegisterUrl = computed(() => localizedLandingPath(mainRegister().url));
const landingFooterEnabled = computed(() => landingSettings.value?.footer?.enabled !== false);
const landingFooterCopyright = computed(() =>
    landingSettings.value?.footer?.copyright_text || t('landing.footer_rights') || 'All rights reserved.'
);
const footerDirection = computed(() => (direction.value === 'rtl' ? 'rtl' : 'ltr'));
const appStoreButtonDirection = computed(() => (footerDirection.value === 'rtl' ? 'rtl' : 'ltr'));
const appStoreButtonTextClass = computed(() => (footerDirection.value === 'rtl' ? 'text-right' : 'text-left'));
const landingFooterNavLinks = computed(() => {
    const links: Array<{ label: string; href: string }> = [];

    if (landingSettings.value?.mobile_apps_section?.enabled !== false) {
        links.push({ label: footerLabels.application(), href: resolveLandingHref('#application') });
    }

    if (landingSettings.value?.plans_comparison_page?.enabled !== false) {
        links.push({ label: footerLabels.plans(), href: resolveLandingHref('#pricing') });
    }

    links.push(...landingStaticPageLinks.value);

    return links;
});
const landingFooterNavColumns = computed(() => [
    [{ label: footerLabels.privacy(), href: localizedLandingPath('/privacy-policy') }],
    [{ label: footerLabels.terms(), href: localizedLandingPath('/terms-of-use') }],
    [{ label: footerLabels.securityPolicy(), href: localizedLandingPath('/security-policy') }],
    [
        { label: footerLabels.cars(), href: resolveLandingHref('#cars') },
        { label: footerLabels.features(), href: resolveLandingHref('#features') },
    ],
    [
        { label: footerLabels.application(), href: resolveLandingHref('#application') },
        { label: footerLabels.plans(), href: resolveLandingHref('#pricing') },
    ],
]);
const landingFooterSocialLinks = computed(() => {
    if (landingSettings.value?.footer?.show_social_links === false) {
        return [];
    }

    const links = Array.isArray(landingSettings.value?.footer?.social_links)
        ? landingSettings.value.footer.social_links
        : [];

    return links
        .map((link: any) => ({
            label: String(link?.label || link?.platform || 'Social'),
            platform: String(link?.platform || '').toLowerCase(),
            href: String(link?.href || '').trim(),
        }))
        .filter((link) => link.platform !== '');
});
const landingFooterAppButtons = computed(() => {
    if (landingSettings.value?.footer?.show_app_buttons === false) {
        return [];
    }

    const androidLabel = String(landingSettings.value?.footer?.android_label || '').trim();
    const iosLabel = String(landingSettings.value?.footer?.ios_label || '').trim();
    const androidCaption = String(landingSettings.value?.footer?.android_caption || '').trim();
    const iosCaption = String(landingSettings.value?.footer?.ios_caption || '').trim();

    return [
        {
            key: 'android',
            caption: androidCaption || localizedFallback('Get it on', { ar: '\u062d\u0645\u0644\u0647 \u0645\u0646' }),
            label: !androidLabel || androidLabel.toLowerCase() === 'android' ? 'Google Play' : androidLabel,
            href: String(landingSettings.value?.footer?.android_url || '').trim(),
            iconUrl: String(landingSettings.value?.footer?.android_icon_url || '').trim(),
            icon: Play,
        },
        {
            key: 'ios',
            caption: iosCaption || localizedFallback('Download on the', { ar: '\u0642\u0645 \u0628\u0627\u0644\u062a\u0646\u0632\u064a\u0644 \u0645\u0646' }),
            label: !iosLabel || iosLabel.toLowerCase() === 'ios' ? 'App Store' : iosLabel,
            href: String(landingSettings.value?.footer?.ios_url || '').trim(),
            iconUrl: String(landingSettings.value?.footer?.ios_icon_url || '').trim(),
            icon: Apple,
        },
    ];
});
const socialIcon = (platform: string) => {
    const normalized = String(platform || '').toLowerCase();

    if (normalized === 'instagram') {
        return Instagram;
    }

    if (normalized === 'linkedin') {
        return Linkedin;
    }

    return Facebook;
};
const toggleLandingMenu = () => {
    mobileOpen.value = !mobileOpen.value;
};
const closeLandingMenu = () => {
    mobileOpen.value = false;
};
const handleWindowScroll = () => {
    showScrollTop.value = typeof window !== 'undefined' && window.scrollY > 420;
};
const scrollToTop = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
};
const normalizeWhatsAppNumber = (value: unknown) => {
    const digits = String(value || '').replace(/\D/g, '');

    return digits.startsWith('00') ? digits.slice(2) : digits;
};
const whatsappNumber = computed(() => {
    if (isLandingShell.value) {
        return normalizeWhatsAppNumber(landingSettings.value?.contact_section?.direct_phone);
    }

    return normalizeWhatsAppNumber(
        tenantSiteSettings.value?.contact?.whatsapp ||
            tenantSiteSettings.value?.contact?.phone ||
            currentTenant.value?.phone,
    );
});
const whatsappHref = computed(() =>
    whatsappNumber.value ? `https://wa.me/${whatsappNumber.value}` : null,
);
const themeVars = computed(() => ({
    ...globalThemeVars.value,
    '--tenant-primary': primaryColor.value,
    '--tenant-secondary': secondaryColor.value,
    '--tenant-gradient': `linear-gradient(90deg, ${primaryColor.value}, ${secondaryColor.value})`,
}));

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    handleWindowScroll();
    window.addEventListener('scroll', handleWindowScroll, { passive: true });
});

onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
        return;
    }

    window.removeEventListener('scroll', handleWindowScroll);
});
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
                            <Link :href="landingRegisterUrl">{{ navigationCtaLabel }}</Link>
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
                            :href="landingRegisterUrl"
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

            <footer v-if="landingFooterEnabled" class="border-t border-border bg-slate-50/70 py-7 md:bg-background md:py-4">
                <div class="section-container max-w-7xl">
                    <div class="grid items-center gap-5 md:grid-cols-[8rem_minmax(0,1fr)_auto_8rem]">
                        <Link :href="landingHomeUrl" class="inline-flex items-center justify-center md:justify-start">
                            <AppLogoIcon class="h-14 w-auto md:h-16" />
                        </Link>

                        <nav class="flex flex-wrap items-center justify-center gap-2 text-sm font-semibold text-foreground md:grid md:grid-cols-5 md:gap-x-7 md:gap-y-2 md:font-medium">
                            <div
                                v-for="(column, columnIndex) in landingFooterNavColumns"
                                :key="`footer-column-${columnIndex}`"
                                class="contents md:flex md:min-h-16 md:flex-col md:items-start md:justify-center md:gap-2"
                            >
                                <a
                                    v-for="link in column"
                                    :key="`footer-${link.href}`"
                                    :href="link.href"
                                    class="inline-flex min-h-10 items-center justify-center whitespace-nowrap rounded-full border border-slate-200 bg-white px-4 text-center shadow-sm transition-colors hover:border-primary/30 hover:text-primary md:min-h-0 md:rounded-none md:border-0 md:bg-transparent md:px-0 md:shadow-none"
                                >
                                    {{ link.label }}
                                </a>
                            </div>
                        </nav>

                        <div v-if="landingFooterSocialLinks.length" class="flex items-center justify-center gap-2 md:justify-end">
                            <component
                                :is="link.href ? 'a' : 'span'"
                                v-for="link in landingFooterSocialLinks"
                                :key="`footer-social-${link.platform}`"
                                :href="link.href || undefined"
                                :target="link.href ? '_blank' : undefined"
                                :rel="link.href ? 'noopener noreferrer' : undefined"
                                class="inline-flex h-10 w-12 items-center justify-center rounded-full border border-slate-200 bg-white text-muted-foreground shadow-sm transition hover:border-primary/40 hover:text-primary md:h-8 md:w-14 md:rounded-md md:border-border md:shadow-none"
                                :aria-label="link.label"
                            >
                                <component :is="socialIcon(link.platform)" class="h-4 w-4" />
                            </component>
                        </div>

                        <div v-if="landingFooterAppButtons.length" class="grid grid-cols-2 justify-center gap-2 md:grid-cols-1 md:justify-end">
                            <component
                                :is="button.href ? 'a' : 'span'"
                                v-for="button in landingFooterAppButtons"
                                :key="button.key"
                                :href="button.href || undefined"
                                :target="button.href ? '_blank' : undefined"
                                :rel="button.href ? 'noopener noreferrer' : undefined"
                                class="inline-flex h-11 w-[8.5rem] items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-3 text-slate-950 shadow-sm transition hover:border-blue-200 md:h-9 md:w-[7.75rem] md:rounded-md md:border-slate-100 md:px-1 md:shadow-none"
                                :dir="appStoreButtonDirection"
                            >
                                <span class="leading-tight" :class="appStoreButtonTextClass">
                                    <span class="block text-[0.5rem] font-bold uppercase tracking-wide text-slate-500">
                                        {{ button.caption }}
                                    </span>
                                    <span class="block text-xs font-extrabold">
                                        {{ button.label }}
                                    </span>
                                </span>
                                <img
                                    v-if="button.iconUrl"
                                    :src="button.iconUrl"
                                    alt=""
                                    class="h-4 w-4 shrink-0 object-contain"
                                />
                                <component v-else :is="button.icon" class="h-4 w-4 shrink-0 text-slate-950" />
                            </component>
                        </div>
                    </div>

                    <p
                        class="mt-5 border-t border-slate-200 pt-4 text-center text-xs text-muted-foreground md:mt-3 md:border-0 md:pt-0 md:text-sm"
                        :dir="footerDirection"
                    >
                        <template v-if="footerDirection === 'rtl'">
                            {{ landingFooterCopyright }} &copy; {{ currentYear }} {{ appName }}.
                        </template>
                        <template v-else>
                            &copy; {{ currentYear }} {{ appName }}. {{ landingFooterCopyright }}
                        </template>
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

    <div class="fixed bottom-5 right-4 z-[60] flex flex-col items-center gap-3 sm:right-6">
        <a
            v-if="whatsappHref"
            :href="whatsappHref"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg shadow-emerald-700/20 transition hover:-translate-y-0.5 hover:bg-[#20bd5a] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#25D366]/40"
            aria-label="WhatsApp"
        >
            <MessageCircle class="h-6 w-6" />
        </a>
        <button
            v-show="showScrollTop"
            type="button"
            class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-slate-950 text-white shadow-lg shadow-slate-900/20 transition hover:-translate-y-0.5 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
            aria-label="Scroll to top"
            @click="scrollToTop"
        >
            <ArrowUp class="h-5 w-5" />
        </button>
    </div>
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
