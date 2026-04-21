<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTrans } from '@/composables/useTrans';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { fleet as mainFleet, register as mainRegister } from '@/routes';
import { show as tenantFleetShow } from '@/routes/tenant/fleet';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Calendar, Check, ChevronDown, Languages, Menu, Search, X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import heroMockup from '@/assets/hero-mockup.png';
import { type Plan } from '@/types';

interface FeatureCard {
    title: string;
    image_url: string;
    content: string;
}

interface StepItem {
    title: string;
    description: string;
}

interface FaqItem {
    question: string;
    answer: string;
}

interface TenantLogo {
    id: number;
    name: string;
    slug: string;
    logo_url: string | null;
}

interface FeaturedCar {
    id: number;
    tenant_id: number;
    tenant_slug?: string | null;
    branch_id: number | null;
    make: string;
    model: string;
    year: number;
    price_per_day: string;
    description: string;
    fuel_type: string;
    status?: string;
    image_url: string;
    tenant_name?: string | null;
    tenant_logo_url?: string | null;
    tenant_primary_color?: string | null;
    tenant_secondary_color?: string | null;
    location_text?: string | null;
}

interface LandingSettings {
    hero: {
        title: string;
        description: string;
        features: string[];
        image_url: string;
    };
    features_section: {
        title: string;
        description: string;
        cards: FeatureCard[];
    };
    getting_started: {
        title: string;
        description: string;
        items: StepItem[];
    };
    plans_section: {
        title: string;
        description: string;
    };
    faq_section: {
        title: string;
        description: string;
        items: FaqItem[];
    };
    footer: {
        title: string;
        description: string;
    };
}

const props = defineProps<{
    landingSettings: LandingSettings;
    plans: Plan[];
    tenantLogos: TenantLogo[];
    featuredCars: FeaturedCar[];
    carSearch: string;
    contactSubmitUrl: string;
}>();

const page = usePage<any>();
const { t } = useTrans();
const appName = computed(() => page.props.name || 'Real Rent Car');
const locale = computed(() => String(page.props.locale || 'en'));
const availableLocales = computed<string[]>(() =>
    Array.isArray(page.props?.available_locales) && page.props.available_locales.length
        ? page.props.available_locales
        : ['en']
);
const appBranding = computed(() => page.props.app_branding ?? {});
const hasAppLogo = computed(() => !!appBranding.value?.logo_url);

const normalizedRedirectPath = computed(() => {
    const currentPath = String(page.url || '/');
    const escapedLocales = availableLocales.value.map((item) => item.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const localeRegex = new RegExp(`^\\/(${escapedLocales.join('|')})(?=\\/|$)`);
    const strippedPath = currentPath.replace(localeRegex, '') || '/';

    return strippedPath.startsWith('/') ? strippedPath : `/${strippedPath}`;
});

const localeSwitcherUrl = (targetLocale: string) =>
    `/locale/${targetLocale}?redirect=${encodeURIComponent(normalizedRedirectPath.value)}`;

const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const hexToRgb = (hex: string): [number, number, number] | null => {
    const normalized = hex.trim().replace('#', '');

    if (/^[0-9a-fA-F]{3}$/.test(normalized)) {
        const expanded = normalized.split('').map((char) => char + char).join('');
        const value = Number.parseInt(expanded, 16);
        return [(value >> 16) & 255, (value >> 8) & 255, value & 255];
    }

    if (/^[0-9a-fA-F]{6}$/.test(normalized)) {
        const value = Number.parseInt(normalized, 16);
        return [(value >> 16) & 255, (value >> 8) & 255, value & 255];
    }

    return null;
};

const withOpacity = (color: string, alpha: number) => {
    const rgb = hexToRgb(color);
    return rgb ? `rgba(${rgb[0]}, ${rgb[1]}, ${rgb[2]}, ${alpha})` : color;
};

const carTheme = (car: FeaturedCar) => {
    const primary = appBranding.value.primary_color || '#3b82f6';
    const secondary = appBranding.value.secondary_color || '#6d28d9';

    return {
        primary,
        secondary,
        gradient: `linear-gradient(90deg, ${primary}, ${secondary})`,
    };
};

const navLinks = computed(() => {
    const fallback = [
        { label: 'Cars', href: '#cars' },
        { label: 'Features', href: '#features' },
        { label: 'Start in Minutes', href: '#how-it-works' },
        { label: 'Clients', href: '#clients' },
        { label: 'Plans', href: '#pricing' },
        { label: 'FAQ', href: '#faq' },
        { label: 'Contact', href: '#contact' },
    ];

    const configuredLinks = Array.isArray(props.landingSettings.navigation?.links)
        ? props.landingSettings.navigation.links
        : [];

    if (!configuredLinks.length) {
        return fallback;
    }

    return configuredLinks
        .map((link, index) => ({
            label: String(link?.label || fallback[index]?.label || ''),
            href: String(link?.href || fallback[index]?.href || '#'),
        }))
        .filter((link) => link.label !== '');
});

const mobileOpen = ref(false);
const scrolled = ref(false);
const yearly = ref(false);
const clientsRail = ref<HTMLElement | null>(null);
const clientsAutoplay = ref<number | null>(null);
const brokenTenantLogos = ref<Record<number, boolean>>({});
const currentYear = new Date().getFullYear();
const registerUrl = mainRegister().url;
const navigationCtaLabel = computed(() => props.landingSettings.navigation?.cta_label || 'Start Free Trial');
const heroImage = computed(() => props.landingSettings.hero.image_url || heroMockup);
const carSearch = ref(props.carSearch ?? '');
const fleetUrl = mainFleet().url;

const contactForm = useForm({
    name: '',
    email: '',
    subject: '',
    message: '',
});
const contactNotice = ref<string | null>(null);
const contactNoticeTone = ref<'success' | 'error' | null>(null);

const onScroll = () => {
    scrolled.value = window.scrollY > 10;
};

const toggleMenu = () => {
    mobileOpen.value = !mobileOpen.value;
};

const closeMenu = () => {
    mobileOpen.value = false;
};

const toggleYearly = () => {
    yearly.value = !yearly.value;
};

const planPrice = (plan: Plan) => {
    if (yearly.value) {
        if (plan.yearly_price !== null && plan.yearly_price !== undefined) {
            return Number(plan.yearly_price);
        }

        return Math.round(Number(plan.monthly_price) * 0.8);
    }

    return Number(plan.monthly_price);
};

const money = (value: number) => {
    return Number(value).toFixed(2);
};

const tenantInitial = (name: string) => {
    return name?.trim()?.charAt(0)?.toUpperCase() || 'T';
};

const hasTenantLogo = (tenant: TenantLogo) => {
    return !!tenant.logo_url && !brokenTenantLogos.value[tenant.id];
};

const markTenantLogoBroken = (tenantId: number) => {
    brokenTenantLogos.value[tenantId] = true;
};

const formatCarPrice = (value: string) => {
    const amount = Number(value);
    return Number.isFinite(amount) ? amount.toFixed(2) : value;
};

const formatStatus = (status?: string) => {
    if (!status) {
        return localize('Available', 'متاح');
    }

    return status
        .split(/[_-]/g)
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
};

const featuredCarUrl = (car: FeaturedCar) => {
    if (!car.tenant_slug) {
        return fleetUrl;
    }

    return tenantFleetShow.url({
        subdomain: car.tenant_slug,
        car: car.id,
    });
};

const bookFeaturedCar = (car: FeaturedCar) => {
    window.location.href = featuredCarUrl(car);
};

const searchCars = () => {
    router.get(
        window.location.pathname,
        {
            car_search: carSearch.value.trim() || null,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const clearCarSearch = () => {
    carSearch.value = '';
    searchCars();
};

const startClientsAutoplay = () => {
    const rail = clientsRail.value;
    if (!rail || rail.scrollWidth <= rail.clientWidth) {
        return;
    }

    if (clientsAutoplay.value !== null) {
        window.clearInterval(clientsAutoplay.value);
    }

    clientsAutoplay.value = window.setInterval(() => {
        const element = clientsRail.value;
        if (!element) {
            return;
        }

        const maxScrollLeft = element.scrollWidth - element.clientWidth;
        const step = Math.max(Math.floor(element.clientWidth * 0.45), 180);

        if (element.scrollLeft >= maxScrollLeft - 8) {
            element.scrollTo({ left: 0, behavior: 'smooth' });
            return;
        }

        element.scrollBy({ left: step, behavior: 'smooth' });
    }, 2400);
};

const submitContact = () => {
    contactNotice.value = null;
    contactNoticeTone.value = null;

    contactForm.post(props.contactSubmitUrl, {
        preserveScroll: true,
        onSuccess() {
            contactForm.reset();
            contactNoticeTone.value = 'success';
            contactNotice.value = localize(
                'Thanks. We received your message and will review it shortly.',
                'شكرًا. لقد استلمنا رسالتك وسنراجعها قريبًا.',
            );
        },
        onError() {
            contactNoticeTone.value = 'error';
            contactNotice.value = localize(
                'Please check the form and try again.',
                'يرجى التحقق من النموذج والمحاولة مرة أخرى.',
            );
        },
    });
};

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll);
    nextTick(startClientsAutoplay);
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);

    if (clientsAutoplay.value !== null) {
        window.clearInterval(clientsAutoplay.value);
        clientsAutoplay.value = null;
    }
});
</script>

<template>
    <Head :title="landingSettings.hero.title" />

    <div class="min-h-screen bg-background">
        <nav
            class="fixed left-0 right-0 top-0 z-50 transition-all duration-300"
            :class="
                scrolled
                    ? 'border-b border-border bg-background/95 shadow-sm backdrop-blur-lg'
                    : 'bg-background/90 shadow-sm backdrop-blur-lg'
            "
        >
                <div class="section-container flex h-16 items-center justify-between gap-4">
                    <Link href="/" class="inline-flex items-center gap-2 text-xl font-bold tracking-tight text-foreground">
                        <AppLogoIcon class="h-6 w-6" />
                        <span v-if="!hasAppLogo">{{ appName }}</span>
                    </Link>

                <div class="hidden items-center gap-8 md:flex">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                    >
                        {{ link.label }}
                    </a>
                    <DropdownMenu v-if="availableLocales.length > 1">
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                class="h-9 gap-2 rounded-full border border-border bg-background px-4 text-sm font-semibold text-muted-foreground hover:text-foreground"
                            >
                                <Languages class="h-4 w-4" />
                                <span>{{ locale.toUpperCase() }}</span>
                                <ChevronDown class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="min-w-40">
                            <DropdownMenuItem v-for="localeCode in availableLocales" :key="localeCode" as-child>
                                <a
                                    :href="localeSwitcherUrl(localeCode)"
                                    class="flex w-full items-center justify-between gap-2"
                                >
                                    <span>{{ localeCode.toUpperCase() }}</span>
                                    <span v-if="locale === localeCode" class="text-[11px] font-semibold text-primary">
                                        {{ t('language.label') }}
                                    </span>
                                </a>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Button as-child class="gradient-button rounded-full px-5" size="sm">
                        <Link :href="registerUrl">{{ navigationCtaLabel }}</Link>
                    </Button>
                </div>

                <button
                    class="text-foreground md:hidden"
                    :aria-label="t('landing.toggle_menu')"
                    type="button"
                    @click="toggleMenu"
                >
                    <X v-if="mobileOpen" :size="22" />
                    <Menu v-else :size="22" />
                </button>
            </div>

            <div
                v-if="mobileOpen"
                class="animate-fade-in border-b border-border bg-background px-4 pb-4 md:hidden"
            >
                <a
                    v-for="link in navLinks"
                    :key="`mobile-${link.href}`"
                    :href="link.href"
                    class="block py-2 text-sm font-medium text-muted-foreground hover:text-foreground"
                    @click="closeMenu"
                >
                    {{ link.label }}
                </a>
                <DropdownMenu v-if="availableLocales.length > 1">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            class="mt-3 h-9 w-full justify-between rounded-xl border border-border bg-muted/50 px-4 text-sm font-semibold text-muted-foreground"
                        >
                            <span class="inline-flex items-center gap-2">
                                <Languages class="h-4 w-4" />
                                {{ t('language.label') }}
                            </span>
                            <ChevronDown class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="min-w-44">
                        <DropdownMenuItem v-for="localeCode in availableLocales" :key="`mobile-${localeCode}`" as-child>
                            <a
                                :href="localeSwitcherUrl(localeCode)"
                                class="flex w-full items-center justify-between gap-2"
                                @click="closeMenu"
                            >
                                <span>{{ localeCode.toUpperCase() }}</span>
                                <Check v-if="locale === localeCode" class="h-4 w-4 text-primary" />
                            </a>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <Button as-child class="gradient-button mt-2 w-full rounded-full" size="sm">
                    <Link :href="registerUrl">{{ navigationCtaLabel }}</Link>
                </Button>
            </div>
        </nav>

        <main>
            <section class="relative overflow-hidden pb-20 pt-32 md:pb-28 md:pt-40" style="background: var(--gradient-hero)">
                <div class="section-container">
                    <div class="mx-auto max-w-3xl text-center animate-reveal-up">
                        <h1 class="text-4xl font-extrabold leading-[1.1] tracking-tight text-foreground sm:text-5xl lg:text-6xl">
                            {{ landingSettings.hero.title }}
                        </h1>
                        <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-muted-foreground sm:text-xl">
                            {{ landingSettings.hero.description }}
                        </p>
                        <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            <Button as-child size="lg" class="gradient-button h-12 rounded-full px-8 text-base">
                                <Link :href="registerUrl">{{ navigationCtaLabel }}</Link>
                            </Button>
                            <a
                                href="#cars"
                                class="inline-flex h-12 items-center justify-center rounded-full border border-input px-8 text-base font-medium hover:bg-accent"
                            >
                                {{ localize('Browse cars', 'تصفح السيارات') }}
                            </a>
                        </div>

                        <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                            <div
                                v-for="feature in landingSettings.hero.features"
                                :key="feature"
                                class="rounded-full border border-border bg-background/60 px-4 py-1.5 text-sm text-muted-foreground"
                            >
                                {{ feature }}
                            </div>
                        </div>
                    </div>

                    <div class="mx-auto mt-16 max-w-5xl animate-reveal-up-delay">
                        <div class="card-elevated overflow-hidden rounded-2xl p-1">
                            <img
                                :src="heroImage"
                                alt="Hero"
                                class="w-full rounded-xl"
                                loading="eager"
                            >
                        </div>
                    </div>
                </div>
            </section>

            <section id="cars" class="section-padding border-b border-border">
                <div class="section-container">
                    <div class="mx-auto mb-10 max-w-3xl text-center">
                        <h2 class="text-3xl font-bold text-foreground sm:text-4xl">
                            {{ localize('Random cars from tenants', 'سيارات عشوائية من المستأجرين') }}
                        </h2>
                        <p class="mt-4 text-lg text-muted-foreground">
                            {{ localize('Showcase live inventory from different tenants and search by car, model, or tenant name.', 'اعرض السيارات المتاحة من مختلف المستأجرين وابحث بالسيارة أو الموديل أو اسم المستأجر.') }}
                        </p>
                    </div>

                    <div class="mx-auto mb-8 flex max-w-2xl flex-col gap-3 sm:flex-row">
                        <Input
                            v-model="carSearch"
                            :placeholder="localize('Search cars or tenants...', 'ابحث عن سيارة أو مستأجر...')"
                            class="h-12"
                            @keyup.enter="searchCars"
                        />
                        <div class="flex gap-3">
                            <Button type="button" class="h-12 rounded-full px-6" @click="searchCars">
                                <Search :size="16" class="mr-2" />
                                {{ localize('Search', 'بحث') }}
                            </Button>
                            <Button
                                v-if="carSearch.trim()"
                                type="button"
                                variant="outline"
                                class="h-12 rounded-full px-6"
                                @click="clearCarSearch"
                            >
                                {{ localize('Clear', 'مسح') }}
                            </Button>
                        </div>
                    </div>

                    <div v-if="featuredCars.length > 0" class="grid gap-8 md:grid-cols-2 xl:grid-cols-3">
                        <Card
                            v-for="car in featuredCars"
                            :key="car.id"
                            :title="formatStatus(car.status)"
                            :compact="true"
                            class="group h-full overflow-hidden rounded-[24px] border border-border bg-white shadow-[0_10px_30px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_16px_40px_rgba(15,23,42,0.12)]"
                        >
                            <div class="relative h-64 overflow-hidden bg-secondary/40">
                                <img
                                    :src="car.image_url"
                                    :alt="`${car.make} ${car.model}`"
                                    class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                                >
                                <div
                                    class="absolute right-4 top-4 rounded-2xl px-4 py-3 shadow-lg"
                                    :style="{
                                        background: carTheme(car).gradient,
                                        boxShadow: `0 12px 24px -12px ${carTheme(car).primary}`,
                                    }"
                                >
                                    <span class="text-lg font-extrabold leading-none text-white">${{ formatCarPrice(car.price_per_day) }}</span>
                                    <span class="ml-1 text-sm font-medium text-primary-foreground/90">/ {{ localize('day', 'يوم') }}</span>
                                </div>
                            </div>
                        <CardContent class="flex h-full flex-col p-6">
                            <div class="space-y-3">
                                <h3 class="text-[1.7rem] font-bold tracking-tight text-foreground">
                                    {{ car.make }} {{ car.model }} - {{ car.year }}
                                </h3>
                                <div class="flex items-center justify-between gap-3 text-xs font-medium text-muted-foreground">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <div
                                            v-if="car.tenant_logo_url"
                                            class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-white p-0.5"
                                        >
                                            <img
                                                :src="car.tenant_logo_url"
                                                :alt="car.tenant_name || localize('Tenant logo', 'شعار المستأجر')"
                                                class="h-full w-full object-contain"
                                            >
                                        </div>
                                        <div
                                            v-else
                                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-[9px] font-semibold uppercase"
                                            :style="{
                                                backgroundColor: withOpacity(carTheme(car).primary, 0.12),
                                                color: carTheme(car).primary,
                                            }"
                                        >
                                            {{ (car.tenant_name || 'T').trim().charAt(0) }}
                                        </div>
                                        <span class="truncate">{{ car.tenant_name || localize('Tenant', 'المستأجر') }}</span>
                                    </div>
                                    <span class="inline-flex shrink-0 items-center gap-1 text-[11px] normal-case tracking-normal text-muted-foreground">
                                        <svg
                                            class="h-3.5 w-3.5"
                                            :style="{ color: carTheme(car).primary }"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M17.657 16.657L13.414 20.9a1.999 1.999 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"
                                            ></path>
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                            ></path>
                                        </svg>
                                        {{ car.location_text || localize('Location not set', 'الموقع غير محدد') }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <div class="flex items-center gap-1.5 capitalize text-sm text-foreground">
                                        <svg
                                            class="h-4 w-4"
                                            :style="{ color: carTheme(car).primary }"
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
                                            <span class="font-medium">{{ car.fuel_type }}</span>
                                        </div>
                                    <div class="rounded-lg bg-slate-400 px-2.5 py-1 text-[11px] text-white">
                                        <p>{{ t('car_card.gps_included') }}</p>
                                    </div>
                                    <div class="rounded-lg bg-slate-400 px-2.5 py-1 text-[11px] text-white">
                                        <p>{{ t('car_card.insurance_included') }}</p>
                                    </div>
                                </div>
                                </div>
                                <p class="mt-4 line-clamp-2 text-sm leading-relaxed text-muted-foreground">
                                    {{ car.description }}
                                </p>
                                <button
                                    type="button"
                                    @click="bookFeaturedCar(car)"
                                    class="mt-auto w-full cursor-pointer rounded-xl px-6 py-4 font-semibold shadow-lg transition-all duration-200 focus:ring-2 focus:ring-offset-2 focus:outline-none"
                                    :style="{
                                        background: carTheme(car).gradient,
                                        boxShadow: `0 12px 24px -12px ${carTheme(car).primary}`,
                                    }"
                                >
                                    <span class="flex items-center justify-center gap-2 text-white">
                                        <Calendar :size="18" class="transition-transform group-hover:scale-110" />
                                        {{ localize('Book Now', 'احجز الآن') }}
                                    </span>
                                </button>
                            </CardContent>
                        </Card>
                    </div>

                    <div v-else class="rounded-2xl border border-dashed border-border p-10 text-center">
                        <p class="text-lg font-medium text-foreground">
                            {{ localize('No cars matched your search.', 'لا توجد سيارات مطابقة لبحثك.') }}
                        </p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ localize('Try a different make, model, or tenant name.', 'جرّب اسم شركة أو موديل أو مستأجر آخر.') }}
                        </p>
                    </div>

                    <div class="mt-14 flex justify-center">
                        <a
                            :href="fleetUrl"
                        class="gradient-button inline-flex items-center justify-center rounded-2xl px-8 py-4 text-base font-semibold shadow-lg shadow-primary/20 transition-all duration-200 hover:shadow-xl"
                        >
                            {{ localize('View Complete Fleet', 'عرض الأسطول الكامل') }}
                        </a>
                    </div>
                </div>
            </section>

            <section id="features" class="section-padding bg-secondary/30">
                <div class="section-container">
                    <div class="mx-auto mb-14 max-w-2xl text-center">
                        <h2 class="text-3xl font-bold text-foreground sm:text-4xl">{{ landingSettings.features_section.title }}</h2>
                        <p class="mt-4 text-lg text-muted-foreground">{{ landingSettings.features_section.description }}</p>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="card in landingSettings.features_section.cards"
                            :key="`${card.title}-${card.content}`"
                            class="card-elevated rounded-xl p-6"
                        >
                            <img
                                v-if="card.image_url"
                                :src="card.image_url"
                                :alt="card.title"
                                class="mb-4 h-40 w-full rounded-lg object-cover"
                            >
                            <h3 class="mb-2 text-lg font-semibold text-foreground">{{ card.title }}</h3>
                            <p class="text-sm leading-relaxed text-muted-foreground">{{ card.content }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="how-it-works" class="section-padding">
                <div class="section-container">
                    <div class="mx-auto mb-14 max-w-2xl text-center">
                        <h2 class="text-3xl font-bold text-foreground sm:text-4xl">{{ landingSettings.getting_started.title }}</h2>
                        <p class="mt-4 text-lg text-muted-foreground">{{ landingSettings.getting_started.description }}</p>
                    </div>

                    <div class="mx-auto grid max-w-5xl gap-8 md:grid-cols-3">
                        <div v-for="(item, index) in landingSettings.getting_started.items" :key="`${item.title}-${index}`" class="text-center">
                            <div class="gradient-button mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-full text-lg font-bold">
                                {{ index + 1 }}
                            </div>
                            <h3 class="mb-2 text-lg font-semibold text-foreground">{{ item.title }}</h3>
                            <p class="text-sm leading-relaxed text-muted-foreground">{{ item.description }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="clients" class="section-padding border-y border-border bg-secondary/20">
                <div class="section-container">
                    <div class="mx-auto mb-10 max-w-3xl text-center">
                        <div class="mb-4 inline-flex rounded-full bg-primary/10 px-4 py-1.5 text-sm font-semibold uppercase tracking-wide text-primary">
                            {{ localize('Our clients', 'عملاؤنا') }}
                        </div>
                        <h2 class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
                            {{ localize('Trusted by teams worldwide', 'موثوق به من قبل فرق حول العالم') }}
                        </h2>
                    </div>

                    <div
                        ref="clientsRail"
                        class="flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth px-1 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden md:px-1"
                    >
                        <div
                            v-for="tenant in tenantLogos"
                            :key="tenant.id"
                            class="flex w-[220px] shrink-0 snap-start items-center gap-3 rounded-2xl border border-border bg-white px-4 py-5 shadow-[0_10px_25px_rgba(15,23,42,0.06)]"
                        >
                            <div
                                v-if="hasTenantLogo(tenant)"
                                class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border bg-white"
                            >
                                <img
                                    :src="tenant.logo_url"
                                    :alt="tenant.name"
                                    class="h-full w-full object-contain p-1"
                                    @error="markTenantLogoBroken(tenant.id)"
                                >
                            </div>
                            <div
                                v-else
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                            >
                                {{ tenantInitial(tenant.name) }}
                            </div>
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium text-foreground">
                                    {{ tenant.name }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="pricing" class="section-padding bg-secondary/30">
                <div class="section-container">
                    <div class="mx-auto mb-10 max-w-2xl text-center">
                        <h2 class="text-3xl font-bold text-foreground sm:text-4xl">{{ landingSettings.plans_section.title }}</h2>
                        <p class="mt-4 text-lg text-muted-foreground">{{ landingSettings.plans_section.description }}</p>
                    </div>

                    <div class="mb-12 flex items-center justify-center gap-3">
                        <span class="text-sm font-medium" :class="!yearly ? 'text-foreground' : 'text-muted-foreground'">{{ t('landing.monthly') }}</span>
                        <button
                            class="relative h-6 w-12 rounded-full transition-colors"
                            :class="yearly ? 'bg-primary' : 'bg-border'"
                            :aria-label="t('landing.toggle_yearly_pricing')"
                            type="button"
                            @click="toggleYearly"
                        >
                            <span
                                class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-primary-foreground transition-transform"
                                :class="yearly ? 'translate-x-6' : ''"
                            />
                        </button>
                        <span class="text-sm font-medium" :class="yearly ? 'text-foreground' : 'text-muted-foreground'">{{ t('landing.yearly') }}</span>
                    </div>

                    <div class="mx-auto grid max-w-6xl gap-6 md:grid-cols-3">
                        <div
                            v-for="plan in plans"
                            :key="plan.id"
                            class="card-elevated flex flex-col rounded-xl p-6"
                        >
                            <h3 class="text-lg font-semibold text-foreground">{{ plan.name }}</h3>
                            <p class="mb-4 text-sm text-muted-foreground">{{ plan.description || '' }}</p>

                            <div class="mb-6">
                                <span class="text-4xl font-extrabold text-foreground">${{ money(planPrice(plan)) }}</span>
                                <span class="text-sm text-muted-foreground">/{{ yearly ? t('landing.yearly') : t('landing.monthly') }}</span>
                            </div>

                            <ul class="mb-8 flex-1 space-y-3">
                                <li v-for="feature in (plan.features || [])" :key="feature" class="flex items-start gap-2 text-sm text-muted-foreground">
                                    <Check :size="16" class="mt-0.5 shrink-0 text-primary" />
                                    {{ feature }}
                                </li>
                            </ul>

                            <Button as-child class="gradient-button w-full rounded-full">
                                <Link :href="registerUrl">{{ navigationCtaLabel }}</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </section>

            <section id="faq" class="section-padding">
                <div class="section-container mx-auto max-w-3xl">
                    <div class="mb-12 text-center">
                        <h2 class="text-3xl font-bold text-foreground sm:text-4xl">{{ landingSettings.faq_section.title }}</h2>
                        <p class="mt-4 text-lg text-muted-foreground">{{ landingSettings.faq_section.description }}</p>
                    </div>

                    <div class="space-y-3">
                        <details
                            v-for="faq in landingSettings.faq_section.items"
                            :key="`${faq.question}-${faq.answer}`"
                            class="card-elevated faq-item rounded-lg border px-5"
                        >
                            <summary class="flex cursor-pointer list-none items-center justify-between py-4 font-medium text-foreground">
                                <span>{{ faq.question }}</span>
                                <ChevronDown :size="18" class="faq-chevron text-muted-foreground" />
                            </summary>
                            <p class="pb-4 text-muted-foreground">{{ faq.answer }}</p>
                        </details>
                    </div>
                </div>
            </section>

            <section id="contact" class="section-padding bg-secondary/30">
                <div class="section-container">
                    <div class="mx-auto mb-10 max-w-2xl text-center">
                        <h2 class="text-3xl font-bold text-foreground sm:text-4xl">
                            {{ localize('Contact form', 'نموذج التواصل') }}
                        </h2>
                        <p class="mt-4 text-lg text-muted-foreground">
                            {{ localize('Send us a note and our team will follow up by email.', 'أرسل لنا رسالة وسيتابع فريقنا معك عبر البريد الإلكتروني.') }}
                        </p>
                    </div>

                    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(320px,0.9fr)]">
                        <Card class="border-border shadow-sm">
                            <CardHeader>
                                <CardTitle>{{ localize('Tell us what you need', 'أخبرنا بما تحتاجه') }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form class="space-y-4" @submit.prevent="submitContact">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-foreground">{{ localize('Name', 'الاسم') }}</label>
                                            <Input v-model="contactForm.name" :placeholder="localize('Your name', 'اسمك')" />
                                            <p v-if="contactForm.errors.name" class="text-xs text-destructive">{{ contactForm.errors.name }}</p>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-foreground">{{ localize('Email', 'البريد الإلكتروني') }}</label>
                                            <Input v-model="contactForm.email" type="email" :placeholder="localize('you@example.com', 'you@example.com')" />
                                            <p v-if="contactForm.errors.email" class="text-xs text-destructive">{{ contactForm.errors.email }}</p>
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-foreground">{{ localize('Subject', 'الموضوع') }}</label>
                                        <Input v-model="contactForm.subject" :placeholder="localize('How can we help?', 'كيف يمكننا المساعدة؟')" />
                                        <p v-if="contactForm.errors.subject" class="text-xs text-destructive">{{ contactForm.errors.subject }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-foreground">{{ localize('Message', 'الرسالة') }}</label>
                                        <Textarea
                                            v-model="contactForm.message"
                                            rows="5"
                                            :placeholder="localize('Tell us a bit about your fleet or the feature you want to launch.', 'اكتب لنا باختصار عن أسطولك أو الميزة التي تريد إطلاقها.')"
                                        />
                                        <p v-if="contactForm.errors.message" class="text-xs text-destructive">{{ contactForm.errors.message }}</p>
                                    </div>

                                    <Button type="submit" class="gradient-button h-12 rounded-full px-6">
                                        <span v-if="contactForm.processing">{{ localize('Sending...', 'جارٍ الإرسال...') }}</span>
                                        <span v-else>{{ localize('Send message', 'إرسال الرسالة') }}</span>
                                    </Button>

                                    <p
                                        v-if="contactNotice"
                                        class="text-sm font-medium"
                                        :class="contactNoticeTone === 'success' ? 'text-green-600' : 'text-destructive'"
                                    >
                                        {{ contactNotice }}
                                    </p>
                                </form>
                            </CardContent>
                        </Card>

                        <div class="space-y-6">
                            <Card class="border-border shadow-sm">
                                <CardHeader>
                                    <CardTitle>{{ localize('Direct contact', 'التواصل المباشر') }}</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-4 text-sm text-muted-foreground">
                                    <div>
                                        <p class="font-medium text-foreground">{{ localize('Email', 'البريد الإلكتروني') }}</p>
                                        <a class="text-primary hover:underline" :href="`mailto:${contactRecipient}`">{{ contactRecipient }}</a>
                                    </div>
                                    <div>
                                        <p class="font-medium text-foreground">{{ localize('Phone', 'الهاتف') }}</p>
                                        <p>+1 (555) 123-4567</p>
                                    </div>
                                    <div>
                                        <p class="font-medium text-foreground">{{ localize('Response time', 'زمن الاستجابة') }}</p>
                                        <p>{{ localize('Within one business day', 'خلال يوم عمل واحد') }}</p>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card class="border-border shadow-sm">
                                <CardHeader>
                                    <CardTitle>{{ localize('Quick links', 'روابط سريعة') }}</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-3 text-sm">
                                    <a href="#cars" class="block font-medium text-primary hover:underline">
                                        {{ localize('Browse tenant cars', 'تصفح سيارات المستأجرين') }}
                                    </a>
                                    <a href="#pricing" class="block font-medium text-primary hover:underline">
                                        {{ localize('View plans', 'عرض الخطط') }}
                                    </a>
                                    <a href="#faq" class="block font-medium text-primary hover:underline">
                                        {{ localize('Read the FAQ', 'قراءة الأسئلة الشائعة') }}
                                    </a>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-border py-10">
            <div class="section-container text-center">
                <h3 class="text-2xl font-bold text-foreground">{{ landingSettings.footer.title }}</h3>
                <p class="mx-auto mt-3 max-w-2xl text-muted-foreground">{{ landingSettings.footer.description }}</p>
                <p class="mt-6 text-sm text-muted-foreground">&copy; {{ currentYear }} {{ appName }}. {{ t('landing.footer_rights') }}</p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.faq-item .faq-chevron {
    transition: transform 0.2s ease;
}

.faq-item[open] .faq-chevron {
    transform: rotate(180deg);
}

.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
