<script setup lang="ts">
import heroMockup from '@/assets/hero-mockup.png';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import SeoHead from '@/components/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/composables/useTrans';
import { fleet as mainFleet, register as mainRegister } from '@/routes';
import { show as tenantFleetShow } from '@/routes/tenant/fleet';
import { type Plan } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Apple,
    BriefcaseBusiness,
    Building2,
    Calendar,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Facebook,
    Instagram,
    Languages,
    Linkedin,
    Menu,
    Search,
    Smartphone,
    Users,
    X,
} from 'lucide-vue-next';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import { A11y, Autoplay, Navigation, Pagination } from 'swiper/modules';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

interface FeatureCard {
    title: string;
    image_url: string;
    icon_background_color?: string;
    content: string;
}

interface StepItem {
    title: string;
    image_url: string;
    description: string;
}

interface FaqItem {
    question: string;
    answer: string;
}

interface QuickLinkItem {
    label: string;
    href: string;
}

interface SocialLinkItem {
    label: string;
    platform: string;
    href: string;
}

interface MobileAppCard {
    title: string;
    subtitle: string;
    description: string;
    image_url: string;
    icon_url: string;
    app_store_url: string;
    google_play_url: string;
    features: string[];
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
    currency?: {
        code?: string;
        symbol?: string;
    } | null;
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
        enabled: boolean;
        title: string;
        description: string;
        features: string[];
        image_url: string;
    };
    cars_section: {
        enabled: boolean;
    };
    locale_switcher?: {
        language_names?: Record<string, string>;
    };
    features_section: {
        enabled: boolean;
        title: string;
        description: string;
        cards: FeatureCard[];
    };
    getting_started: {
        enabled: boolean;
        title: string;
        description: string;
        items: StepItem[];
    };
    mobile_apps_section: {
        enabled: boolean;
        eyebrow: string;
        title: string;
        description: string;
        ios_label: string;
        android_label: string;
        apps: MobileAppCard[];
    };
    clients_section: {
        enabled: boolean;
    };
    plans_section: {
        enabled: boolean;
        title: string;
        description: string;
    };
    faq_section: {
        enabled: boolean;
        title: string;
        description: string;
        items: FaqItem[];
    };
    contact_section: {
        enabled: boolean;
        title: string;
        description: string;
        form_title: string;
        name_label: string;
        name_placeholder: string;
        email_label: string;
        email_placeholder: string;
        subject_label: string;
        subject_placeholder: string;
        message_label: string;
        message_placeholder: string;
        submit_label: string;
        sending_label: string;
        success_message: string;
        error_message: string;
        direct_title: string;
        direct_email_label: string;
        direct_email: string;
        direct_phone_label: string;
        direct_phone: string;
        response_time_label: string;
        response_time: string;
        quick_links_title: string;
        quick_links: QuickLinkItem[];
    };
    footer: {
        enabled: boolean;
        title: string;
        description: string;
        copyright_text: string;
        show_social_links: boolean;
        show_app_buttons: boolean;
        android_label: string;
        android_url: string;
        ios_label: string;
        ios_url: string;
        social_links: SocialLinkItem[];
    };
}

const props = defineProps<{
    landingSettings: LandingSettings;
    plans: Plan[];
    tenantLogos: TenantLogo[];
    featuredCars: FeaturedCar[];
    carSearch: string;
    contactSubmitUrl: string;
    seo?: {
        title: string;
        description?: string | null;
        canonical_url?: string | null;
        robots?: string | null;
        og_title?: string | null;
        og_description?: string | null;
        og_image?: string | null;
        alternates?: Array<{ locale: string; url: string }>;
    } | null;
}>();

const page = usePage<any>();
const { t } = useTrans();
const appName = computed(() => page.props.name || 'Car4u');
const locale = computed(() => String(page.props.locale || 'en'));
const isRtlLocale = computed(() =>
    ['ar', 'ur'].includes(locale.value.toLowerCase().split('-')[0]),
);
const availableLocales = computed<string[]>(() =>
    Array.isArray(page.props?.available_locales) &&
    page.props.available_locales.length
        ? page.props.available_locales
        : ['en'],
);
const appBranding = computed(() => page.props.app_branding ?? {});
const hasAppLogo = computed(() => !!appBranding.value?.logo_url);

const normalizedRedirectPath = computed(() => {
    const currentPath = String(page.url || '/');
    const escapedLocales = availableLocales.value.map((item) =>
        item.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'),
    );
    const localeRegex = new RegExp(
        `^\\/(${escapedLocales.join('|')})(?=\\/|$)`,
    );
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
    const configuredName =
        props.landingSettings.locale_switcher?.language_names?.[
            normalizedLocale
        ];

    return (
        configuredName ||
        fallbackLocaleNames[normalizedLocale] ||
        normalizedLocale.toUpperCase()
    );
};



const hexToRgb = (hex: string): [number, number, number] | null => {
    const normalized = hex.trim().replace('#', '');

    if (/^[0-9a-fA-F]{3}$/.test(normalized)) {
        const expanded = normalized
            .split('')
            .map((char) => char + char)
            .join('');
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

const carTheme = () => {
    const primary = appBranding.value.primary_color || '#3b82f6';
    const secondary = appBranding.value.secondary_color || '#6d28d9';

    return {
        primary,
        secondary,
        gradient: `linear-gradient(90deg, ${primary}, ${secondary})`,
    };
};

const hiddenNavHrefs = new Set(['#how-it-works', '#faq']);
const visibleNavHrefs = computed(() => {
    const hrefs = new Set<string>();

    if (props.landingSettings.cars_section?.enabled !== false) {
        hrefs.add('#cars');
    }

    if (props.landingSettings.clients_section?.enabled !== false) {
        hrefs.add('#clients');
    }

    if (props.landingSettings.features_section?.enabled !== false) {
        hrefs.add('#features');
    }

    if (props.landingSettings.mobile_apps_section?.enabled !== false) {
        hrefs.add('#application');
    }

    if (props.landingSettings.plans_section?.enabled !== false) {
        hrefs.add('#pricing');
    }

    if (props.landingSettings.contact_section?.enabled !== false) {
        hrefs.add('#contact');
    }

    return hrefs;
});

const navLinks = computed(() => {
    const fallback = [
        { label: 'Cars', href: '#cars' },
        { label: 'Features', href: '#features' },
        { label: 'Application', href: '#application' },
        { label: 'Clients', href: '#clients' },
        { label: 'Plans', href: '#pricing' },
        { label: 'Contact', href: '#contact' },
    ];

    const configuredLinks = Array.isArray(
        props.landingSettings.navigation?.links,
    )
        ? props.landingSettings.navigation.links
        : [];

    const links = configuredLinks.length ? configuredLinks : fallback;
    const normalizedLinks = links
        .map((link, index) => ({
            label: String(link?.label || fallback[index]?.label || ''),
            href: String(link?.href || fallback[index]?.href || '#'),
        }))
        .filter(
            (link) =>
                link.label !== '' &&
                !hiddenNavHrefs.has(link.href) &&
                visibleNavHrefs.value.has(link.href),
        );

    if (
        visibleNavHrefs.value.has('#application') &&
        !normalizedLinks.some((link) => link.href === '#application')
    ) {
        const featuresIndex = normalizedLinks.findIndex(
            (link) => link.href === '#features',
        );
        const applicationLink = {
            label: 'Application',
            href: '#application',
        };

        if (featuresIndex >= 0) {
            normalizedLinks.splice(featuresIndex + 1, 0, applicationLink);
        } else {
            normalizedLinks.push(applicationLink);
        }
    }

    return normalizedLinks;
});

const mobileOpen = ref(false);
const scrolled = ref(false);
const yearly = ref(false);
const clientsRail = ref<HTMLElement | null>(null);
const clientsAutoplay = ref<number | null>(null);
const brokenTenantLogos = ref<Record<number, boolean>>({});
const brokenCarTenantLogos = ref<Record<number, boolean>>({});
const currentYear = new Date().getFullYear();
const registerUrl = mainRegister().url;
const navigationCtaLabel = computed(
    () => props.landingSettings.navigation?.cta_label || 'Start Free Trial',
);
const heroImage = computed(
    () => props.landingSettings.hero.image_url || heroMockup,
);
const browseCarsHref = computed(() =>
    props.landingSettings.cars_section?.enabled === false ? fleetUrl : '#cars',
);
const heroIsVideo = computed(() =>
    /\.(mp4|webm|ogg|mov)(?:$|[?#])/i.test(heroImage.value),
);
const contactSection = computed(() => props.landingSettings.contact_section);
const contactRecipient = computed(
    () => contactSection.value.direct_email || 'info@car4u.net',
);
const carSearch = ref(props.carSearch ?? '');
const fleetUrl = mainFleet().url;
const featureSwiperModules = [Navigation, Pagination, Autoplay, A11y];
const planSwiperModules = [Navigation, Pagination, Autoplay, A11y];
const mobileAppBackgrounds = [
    'linear-gradient(135deg, #dbeafe 0%, #f8fafc 100%)',
    'linear-gradient(135deg, #ede9fe 0%, #f8fafc 100%)',
    'linear-gradient(135deg, #f3e8ff 0%, #f8fafc 100%)',
];
const mobileAppTitleParts = computed(() => {
    const title = props.landingSettings.mobile_apps_section?.title || '';
    const marker = 'One connected platform';
    const index = title.toLowerCase().indexOf(marker.toLowerCase());

    if (index === -1) {
        return { lead: title, highlight: '' };
    }

    return {
        lead: title.slice(0, index),
        highlight: title.slice(index),
    };
});
const mobileAppStoreHref = (url?: string | null) => {
    const normalized = String(url || '').trim();

    return normalized || '#';
};
const footerSocialIcons = {
    facebook: Facebook,
    instagram: Instagram,
    linkedin: Linkedin,
};
const footerSocialLinks = computed(() =>
    (props.landingSettings.footer.social_links || [])
        .map((link) => ({
            ...link,
            href: String(link.href || '').trim() || '#',
            icon:
                footerSocialIcons[
                    String(link.platform || '').toLowerCase() as keyof typeof footerSocialIcons
                ] || Facebook,
        }))
        .filter((link) => String(link.label || '').trim() !== ''),
);
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

const activePlanCycle = computed<'monthly' | 'yearly'>(() =>
    yearly.value ? 'yearly' : 'monthly',
);

const planPricing = (plan: Plan) => {
    const pricing = plan.pricing_meta?.[activePlanCycle.value];
    if (pricing?.final_amount != null) {
        return pricing;
    }

    const fallbackAmount = yearly.value
        ? Number(plan.yearly_price)
        : Number(plan.monthly_price);

    return {
        original_amount: fallbackAmount,
        final_amount: fallbackAmount,
        savings_amount: 0,
        savings_percentage: 0,
        has_discount: false,
        discount: null,
    };
};

const planPrice = (plan: Plan) => {
    return Number(planPricing(plan).final_amount || 0);
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

const hasCarTenantLogo = (car: FeaturedCar) => {
    return !!car.tenant_logo_url && !brokenCarTenantLogos.value[car.id];
};

const markCarTenantLogoBroken = (carId: number) => {
    brokenCarTenantLogos.value[carId] = true;
};

const formatCarPrice = (value: string) => {
    const amount = Number(value);
    return Number.isFinite(amount) ? amount.toFixed(2) : value;
};

const carCurrencySymbol = (car: FeaturedCar) => car.currency?.symbol || page.props.currency?.symbol || '$';

const formatStatus = (status?: string) => {
    if (!status) {
        return t('landing.cars_status_available');
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
            contactNotice.value = contactSection.value.success_message;
        },
        onError() {
            contactNoticeTone.value = 'error';
            contactNotice.value = contactSection.value.error_message;
        },
    });
};
onMounted(() => {
    // Clean up empty car_search query parameter from URL (e.g. after browser back)
    const url = new URL(window.location.href);
    if (url.searchParams.has('car_search') && !url.searchParams.get('car_search')?.trim()) {
        url.searchParams.delete('car_search');
        window.history.replaceState(window.history.state, '', url.pathname + (url.search || '') + url.hash);
    }

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
    <SeoHead :seo="props.seo || null" />

    <div class="min-h-screen bg-background">
        <nav
            class="fixed top-0 right-0 left-0 z-50 transition-all duration-300"
            :class="
                scrolled
                    ? 'border-b border-border bg-background/95 shadow-sm backdrop-blur-lg'
                    : 'bg-background/90 shadow-sm backdrop-blur-lg'
            "
        >
            <div
                class="section-container relative flex h-16 max-w-7xl items-center justify-center"
            >
                <Link
                    href="/"
                    class="absolute left-4 inline-flex items-center gap-2 text-xl font-bold tracking-tight text-foreground sm:left-6 lg:left-8"
                >
                    <AppLogoIcon class="h-6 w-6" />
                    <span v-if="!hasAppLogo">{{ appName }}</span>
                </Link>

                <div class="hidden items-center justify-center md:flex">
                    <div class="flex items-center justify-center gap-8">
                        <a
                            v-for="link in navLinks"
                            :key="link.href"
                            :href="link.href"
                            class="text-base font-medium whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ link.label }}
                        </a>
                    </div>
                    <div
                        class="absolute right-4 flex items-center gap-4 sm:right-6 lg:right-8"
                    >
                        <DropdownMenu v-if="availableLocales.length > 1" :modal="false">
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
                                <DropdownMenuItem
                                    v-for="localeCode in availableLocales"
                                    :key="localeCode"
                                    as-child
                                >
                                    <a
                                        :href="localeSwitcherUrl(localeCode)"
                                        class="flex w-full items-center justify-between gap-2"
                                    >
                                        <span>{{ localeDisplayName(localeCode) }}</span>
                                    </a>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <Button
                            as-child
                            class="gradient-button rounded-full px-5"
                            size="sm"
                        >
                            <Link :href="registerUrl">{{
                                navigationCtaLabel
                            }}</Link>
                        </Button>
                    </div>
                </div>

                <button
                    class="ml-auto text-foreground md:hidden"
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
                <DropdownMenu v-if="availableLocales.length > 1" :modal="false">
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
                        <DropdownMenuItem
                            v-for="localeCode in availableLocales"
                            :key="`mobile-${localeCode}`"
                            as-child
                        >
                            <a
                                :href="localeSwitcherUrl(localeCode)"
                                class="flex w-full items-center justify-between gap-2"
                                @click="closeMenu"
                            >
                                <span>{{ localeDisplayName(localeCode) }}</span>
                                <Check
                                    v-if="locale === localeCode"
                                    class="h-4 w-4 text-primary"
                                />
                            </a>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <Button
                    as-child
                    class="gradient-button mt-2 w-full rounded-full"
                    size="sm"
                >
                    <Link :href="registerUrl">{{ navigationCtaLabel }}</Link>
                </Button>
            </div>
        </nav>

        <main class="saas-landing-main">
            <section
                v-if="landingSettings.hero.enabled"
                class="relative overflow-hidden pt-32 pb-20 md:pt-40 md:pb-28"
                style="background: var(--gradient-hero)"
            >
                <div class="section-container">
                    <div
                        class="animate-reveal-up mx-auto max-w-3xl text-center"
                    >
                        <h1
                            class="text-4xl leading-[1.1] font-extrabold tracking-tight text-foreground sm:text-5xl lg:text-6xl"
                        >
                            {{ landingSettings.hero.title }}
                        </h1>
                        <p
                            class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-muted-foreground sm:text-xl"
                        >
                            {{ landingSettings.hero.description }}
                        </p>
                        <div
                            class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row"
                        >
                            <Button
                                as-child
                                size="lg"
                                class="gradient-button h-12 rounded-full px-8 text-base"
                            >
                                <Link :href="registerUrl">{{
                                    navigationCtaLabel
                                }}</Link>
                            </Button>
                            <a
                                :href="browseCarsHref"
                                class="inline-flex h-12 items-center justify-center rounded-full border border-input px-8 text-base font-medium hover:bg-accent"
                            >
                                {{ t('landing.browse_cars') }}
                            </a>
                        </div>

                        <div
                            class="mt-8 flex flex-wrap items-center justify-center gap-4"
                        >
                            <div
                                v-for="feature in landingSettings.hero.features"
                                :key="feature"
                                class="rounded-full border border-border bg-background/60 px-4 py-1.5 text-sm text-muted-foreground"
                            >
                                {{ feature }}
                            </div>
                        </div>
                    </div>

                    <div
                        class="animate-reveal-up-delay mx-auto mt-16 max-w-5xl"
                    >
                        <div
                            class="card-elevated overflow-hidden rounded-2xl p-1"
                        >
                            <video
                                v-if="heroIsVideo"
                                :src="heroImage"
                                class="w-full rounded-xl"
                                autoplay
                                muted
                                loop
                                playsinline
                            />
                            <img
                                v-else
                                :src="heroImage"
                                alt="Hero"
                                class="w-full rounded-xl"
                                loading="eager"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="landingSettings.cars_section.enabled"
                id="cars"
                class="section-padding border-b border-border"
            >
                <div class="section-container">
                    <div class="mx-auto mb-10 max-w-3xl text-center">
                        <h2
                            class="text-3xl font-bold text-foreground sm:text-4xl"
                        >
                            {{ t('landing.cars_title') }}
                        </h2>
                        <p class="mt-4 text-lg text-muted-foreground">
                            {{ t('landing.cars_description') }}
                        </p>
                    </div>

                    <div class="mx-auto mb-10 max-w-3xl">
                        <div
                            class="flex items-center gap-2 rounded-[28px] border border-border/70 bg-white p-2 shadow-[0_18px_50px_rgba(15,23,42,0.08)] ring-1 ring-transparent transition-all duration-200 focus-within:border-primary/45 focus-within:ring-primary/15"
                        >
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                            >
                                <Search :size="19" />
                            </div>
                            <input
                                v-model="carSearch"
                                type="search"
                                :placeholder="
                                    t('landing.cars_search_placeholder')
                                "
                                class="min-w-0 flex-1 bg-transparent px-1 text-base font-medium text-foreground outline-none placeholder:text-muted-foreground/65"
                                @keyup.enter="searchCars"
                            />
                            <button
                                v-if="carSearch.trim()"
                                type="button"
                                :aria-label="t('landing.cars_clear')"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                                @click="clearCarSearch"
                            >
                                <X :size="17" />
                            </button>
                            <Button
                                type="button"
                                class="gradient-button h-11 shrink-0 rounded-full px-6 text-base font-semibold"
                                @click="searchCars"
                            >
                                {{ t('landing.cars_search') }}
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="featuredCars.length > 0"
                        class="grid gap-8 md:grid-cols-2 xl:grid-cols-3"
                    >
                        <Card
                            v-for="car in featuredCars"
                            :key="car.id"
                            :title="formatStatus(car.status)"
                            :compact="true"
                            class="group h-full overflow-hidden rounded-[24px] border border-border bg-white shadow-[0_10px_30px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_16px_40px_rgba(15,23,42,0.12)]"
                        >
                            <div
                                class="relative h-64 overflow-hidden bg-secondary/40"
                            >
                                <img
                                    :src="car.image_url"
                                    :alt="`${car.make} ${car.model}`"
                                    class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                                />
                                <div
                                    class="absolute top-4 right-4 rounded-2xl px-4 py-3 shadow-lg"
                                    :style="{
                                        background: carTheme(car).gradient,
                                        boxShadow: `0 12px 24px -12px ${carTheme(car).primary}`,
                                    }"
                                >
                                    <span
                                        class="text-lg leading-none font-extrabold text-white"
                                        >{{ carCurrencySymbol(car) }}{{
                                            formatCarPrice(car.price_per_day)
                                        }}</span
                                    >
                                    <span
                                        class="ml-1 text-sm font-medium text-primary-foreground/90"
                                        >/ {{ t('landing.cars_day') }}</span
                                    >
                                </div>
                            </div>
                            <CardContent class="flex h-full flex-col p-6">
                                <div class="space-y-3">
                                    <h3
                                        class="text-[1.7rem] font-bold tracking-tight text-foreground"
                                    >
                                        {{ car.make }} {{ car.model }} -
                                        {{ car.year }}
                                    </h3>
                                    <div
                                        class="flex items-center justify-between gap-3 text-xs font-medium text-muted-foreground"
                                    >
                                        <div
                                            class="flex min-w-0 items-center gap-2"
                                        >
                                            <div
                                                v-if="hasCarTenantLogo(car)"
                                                class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-white p-0.5"
                                            >
                                                <img
                                                    :src="car.tenant_logo_url"
                                                    :alt="
                                                        car.tenant_name ||
                                                        t(
                                                            'landing.cars_tenant_logo',
                                                        )
                                                    "
                                                    class="h-full w-full object-contain"
                                                    @error="
                                                        markCarTenantLogoBroken(
                                                            car.id,
                                                        )
                                                    "
                                                />
                                            </div>
                                            <div
                                                v-else
                                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-[9px] font-semibold uppercase"
                                                :style="{
                                                    backgroundColor:
                                                        withOpacity(
                                                            carTheme(car)
                                                                .primary,
                                                            0.12,
                                                        ),
                                                    color: carTheme(car)
                                                        .primary,
                                                }"
                                            >
                                                {{
                                                    (car.tenant_name || 'T')
                                                        .trim()
                                                        .charAt(0)
                                                }}
                                            </div>
                                            <span class="truncate">{{
                                                car.tenant_name ||
                                                t('landing.cars_tenant')
                                            }}</span>
                                        </div>
                                        <span
                                            class="inline-flex shrink-0 items-center gap-1 text-[11px] tracking-normal text-muted-foreground normal-case"
                                        >
                                            <svg
                                                class="h-3.5 w-3.5"
                                                :style="{
                                                    color: carTheme(car)
                                                        .primary,
                                                }"
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
                                            {{
                                                car.location_text ||
                                                t(
                                                    'landing.cars_location_not_set',
                                                )
                                            }}
                                        </span>
                                    </div>
                                    <div
                                        class="flex flex-wrap items-center gap-1.5"
                                    >
                                        <div
                                            class="flex items-center gap-1.5 text-sm text-foreground capitalize"
                                        >
                                            <svg
                                                class="h-4 w-4"
                                                :style="{
                                                    color: carTheme(car)
                                                        .primary,
                                                }"
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
                                            <span class="font-medium">{{
                                                car.fuel_type
                                            }}</span>
                                        </div>
                                        <div
                                            class="rounded-lg bg-slate-400 px-2.5 py-1 text-[11px] text-white"
                                        >
                                            <p>
                                                {{ t('car_card.gps_included') }}
                                            </p>
                                        </div>
                                        <div
                                            class="rounded-lg bg-slate-400 px-2.5 py-1 text-[11px] text-white"
                                        >
                                            <p>
                                                {{
                                                    t(
                                                        'car_card.insurance_included',
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <p
                                    class="mt-4 line-clamp-2 text-sm leading-relaxed text-muted-foreground"
                                >
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
                                    <span
                                        class="flex items-center justify-center gap-2 text-white"
                                    >
                                        <Calendar
                                            :size="18"
                                            class="transition-transform group-hover:scale-110"
                                        />
                                        {{ t('landing.cars_book_now') }}
                                    </span>
                                </button>
                            </CardContent>
                        </Card>
                    </div>

                    <div
                        v-else
                        class="rounded-2xl border border-dashed border-border p-10 text-center"
                    >
                        <p class="text-lg font-medium text-foreground">
                            {{ t('landing.cars_empty_title') }}
                        </p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ t('landing.cars_empty_description') }}
                        </p>
                    </div>

                    <div class="mt-14 flex justify-center">
                        <a
                            :href="fleetUrl"
                            class="gradient-button inline-flex items-center justify-center rounded-2xl px-8 py-4 text-base font-semibold"
                        >
                            {{ t('landing.cars_view_complete_fleet') }}
                        </a>
                    </div>
                </div>
            </section>

            <section
                v-if="landingSettings.getting_started.enabled"
                id="how-it-works"
                class="section-padding"
            >
                <div class="section-container">
                    <div class="mx-auto mb-14 max-w-2xl text-center">
                        <h2
                            class="text-3xl font-bold text-foreground sm:text-4xl"
                        >
                            {{ landingSettings.getting_started.title }}
                        </h2>
                        <p class="mt-4 text-lg text-muted-foreground">
                            {{ landingSettings.getting_started.description }}
                        </p>
                    </div>

                    <div class="mx-auto grid max-w-5xl gap-8 md:grid-cols-3">
                        <div
                            v-for="(item, index) in landingSettings
                                .getting_started.items"
                            :key="`${item.title}-${index}`"
                            class="text-center"
                        >
                            <div
                                v-if="item.image_url"
                                class="mx-auto mb-5 h-20 w-20 overflow-hidden rounded-2xl bg-secondary shadow-sm"
                            >
                                <img
                                    :src="item.image_url"
                                    :alt="item.title"
                                    class="h-full w-full object-cover"
                                />
                            </div>
                            <div
                                v-else
                                class="gradient-button mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-full text-lg font-bold"
                            >
                                {{ index + 1 }}
                            </div>
                            <h3
                                class="mb-2 text-lg font-semibold text-foreground"
                            >
                                {{ item.title }}
                            </h3>
                            <p
                                class="text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ item.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="landingSettings.features_section.enabled"
                id="features"
                class="section-padding bg-secondary/30"
            >
                <div class="section-container">
                    <div class="mx-auto mb-14 max-w-2xl text-center">
                        <h2
                            class="text-3xl font-bold text-foreground sm:text-4xl"
                        >
                            {{ landingSettings.features_section.title }}
                        </h2>
                        <p class="mt-4 text-lg text-muted-foreground">
                            {{ landingSettings.features_section.description }}
                        </p>
                    </div>

                    <div class="hidden gap-7 lg:grid lg:grid-cols-3">
                        <div
                            v-for="card in landingSettings.features_section
                                .cards"
                            :key="`desktop-${card.title}-${card.content}`"
                            class="card-elevated flex h-full min-h-[190px] flex-col rounded-xl p-6"
                            :dir="isRtlLocale ? 'rtl' : 'ltr'"
                        >
                            <div
                                class="mb-3 flex items-start gap-3"
                                :class="
                                    isRtlLocale ? 'text-right' : 'text-left'
                                "
                            >
                                <div
                                    v-if="card.image_url"
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg"
                                    :style="{
                                        backgroundColor:
                                            card.icon_background_color ||
                                            '#f3f4f6',
                                    }"
                                >
                                    <img
                                        :src="card.image_url"
                                        :alt="card.title"
                                        class="h-7 w-7 object-contain"
                                    />
                                </div>
                                <h3
                                    class="text-xl font-semibold text-foreground"
                                >
                                    {{ card.title }}
                                </h3>
                            </div>
                            <p
                                class="text-base leading-relaxed text-muted-foreground"
                                :class="
                                    isRtlLocale ? 'text-right' : 'text-left'
                                "
                            >
                                {{ card.content }}
                            </p>
                        </div>
                    </div>

                    <div class="relative lg:hidden">
                        <Swiper
                            :modules="featureSwiperModules"
                            :slides-per-view="1"
                            :space-between="24"
                            :loop="
                                landingSettings.features_section.cards.length >
                                3
                            "
                            :autoplay="{
                                delay: 3500,
                                disableOnInteraction: false,
                                pauseOnMouseEnter: true,
                            }"
                            :pagination="{
                                clickable: true,
                                el: '.features-swiper-pagination',
                            }"
                            :navigation="{
                                prevEl: '.features-swiper-prev',
                                nextEl: '.features-swiper-next',
                            }"
                            :breakpoints="{
                                640: { slidesPerView: 1.35, spaceBetween: 20 },
                                768: { slidesPerView: 2, spaceBetween: 24 },
                                1024: { slidesPerView: 3, spaceBetween: 28 },
                            }"
                            class="features-swiper !pb-14"
                        >
                            <SwiperSlide
                                v-for="card in landingSettings.features_section
                                    .cards"
                                :key="`${card.title}-${card.content}`"
                                class="h-auto"
                            >
                                <div
                                    class="card-elevated flex h-full min-h-[190px] flex-col rounded-xl p-6"
                                    :dir="isRtlLocale ? 'rtl' : 'ltr'"
                                >
                                    <div
                                        class="mb-3 flex items-start gap-3"
                                        :class="
                                            isRtlLocale
                                                ? 'text-right'
                                                : 'text-left'
                                        "
                                    >
                                        <div
                                            v-if="card.image_url"
                                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg"
                                            :style="{
                                                backgroundColor:
                                                    card.icon_background_color ||
                                                    '#f3f4f6',
                                            }"
                                        >
                                            <img
                                                :src="card.image_url"
                                                :alt="card.title"
                                                class="h-7 w-7 object-contain"
                                            />
                                        </div>
                                        <h3
                                            class="text-xl font-semibold text-foreground"
                                        >
                                            {{ card.title }}
                                        </h3>
                                    </div>
                                    <p
                                        class="text-base leading-relaxed text-muted-foreground"
                                        :class="
                                            isRtlLocale
                                                ? 'text-right'
                                                : 'text-left'
                                        "
                                    >
                                        {{ card.content }}
                                    </p>
                                </div>
                            </SwiperSlide>
                        </Swiper>

                        <button
                            type="button"
                            class="features-swiper-prev absolute top-1/2 left-0 z-10 hidden h-11 w-11 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-background text-foreground shadow-lg transition hover:bg-primary hover:text-primary-foreground lg:flex"
                            aria-label="Previous feature"
                        >
                            <ChevronLeft class="h-5 w-5" />
                        </button>
                        <button
                            type="button"
                            class="features-swiper-next absolute top-1/2 right-0 z-10 hidden h-11 w-11 translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-background text-foreground shadow-lg transition hover:bg-primary hover:text-primary-foreground lg:flex"
                            aria-label="Next feature"
                        >
                            <ChevronRight class="h-5 w-5" />
                        </button>
                        <div
                            class="features-swiper-pagination mt-2 flex justify-center"
                        ></div>
                    </div>
                </div>
            </section>

            <section
                v-if="landingSettings.mobile_apps_section.enabled"
                id="application"
                class="section-padding bg-white"
            >
                <div class="section-container">
                    <div class="mx-auto mb-16 max-w-4xl text-center">
                        <div
                            class="mb-4 inline-flex items-center gap-2 rounded-full bg-primary/10 px-4 py-1.5 text-sm font-semibold tracking-[0.18em] text-primary uppercase"
                        >
                            <Smartphone class="h-4 w-4" />
                            {{ landingSettings.mobile_apps_section.eyebrow }}
                        </div>
                        <h2
                            class="text-4xl leading-[1.05] font-extrabold tracking-tight text-foreground sm:text-5xl lg:text-6xl"
                        >
                            {{ mobileAppTitleParts.lead }}
                            <span
                                v-if="mobileAppTitleParts.highlight"
                                class="bg-gradient-to-r from-blue-600 to-violet-600 bg-clip-text text-transparent"
                            >
                                {{ mobileAppTitleParts.highlight }}
                            </span>
                        </h2>
                        <p
                            class="mx-auto mt-6 max-w-3xl text-lg leading-relaxed text-muted-foreground sm:text-xl"
                        >
                            {{
                                landingSettings.mobile_apps_section.description
                            }}
                        </p>
                    </div>

                    <div class="grid gap-8 lg:grid-cols-3">
                        <article
                            v-for="(app, index) in landingSettings
                                .mobile_apps_section.apps"
                            :key="`${app.title}-${index}`"
                            class="group overflow-hidden rounded-2xl border border-border bg-white shadow-[0_18px_55px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_24px_70px_rgba(15,23,42,0.13)]"
                        >
                            <div
                                class="relative flex h-80 items-center justify-center overflow-hidden"
                                :style="{
                                    background:
                                        mobileAppBackgrounds[
                                            index %
                                                mobileAppBackgrounds.length
                                        ],
                                }"
                            >
                                <div
                                    class="absolute inset-8 rounded-full bg-white/45 blur-3xl"
                                ></div>
                                <img
                                    v-if="app.image_url"
                                    :src="app.image_url"
                                    :alt="app.title"
                                    class="relative h-52 w-auto rotate-[-12deg] object-contain drop-shadow-[0_24px_28px_rgba(15,23,42,0.20)] transition-transform duration-300 group-hover:rotate-[-8deg] group-hover:scale-105"
                                    loading="lazy"
                                />
                                <div
                                    v-else
                                    class="relative h-52 w-28 rotate-[-12deg] rounded-[2rem] border-[6px] border-slate-900 bg-slate-950 p-1 shadow-[0_24px_34px_rgba(15,23,42,0.24)] transition-transform duration-300 group-hover:rotate-[-8deg] group-hover:scale-105"
                                >
                                    <div
                                        class="h-full rounded-[1.55rem] bg-white p-2"
                                    >
                                        <div
                                            class="mx-auto mb-2 h-2 w-10 rounded-full bg-slate-900"
                                        ></div>
                                        <div
                                            class="rounded-xl bg-gradient-to-br from-blue-500 to-violet-600 p-2 text-white"
                                        >
                                            <div
                                                class="h-2 w-12 rounded-full bg-white/80"
                                            ></div>
                                            <div
                                                class="mt-3 h-8 rounded-lg bg-white/25"
                                            ></div>
                                        </div>
                                        <div class="mt-3 space-y-2">
                                            <div
                                                class="h-3 rounded-full bg-slate-200"
                                            ></div>
                                            <div
                                                class="h-3 w-3/4 rounded-full bg-slate-200"
                                            ></div>
                                            <div
                                                class="grid grid-cols-2 gap-1 pt-1"
                                            >
                                                <div
                                                    class="h-8 rounded-lg bg-blue-100"
                                                ></div>
                                                <div
                                                    class="h-8 rounded-lg bg-violet-100"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="p-7">
                                <div class="mb-5 flex items-center gap-4">
                                    <div
                                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                                    >
                                        <img
                                            v-if="app.icon_url"
                                            :src="app.icon_url"
                                            :alt="`${app.title} icon`"
                                            class="h-7 w-7 object-contain"
                                            loading="lazy"
                                        />
                                        <Users
                                            v-else-if="index === 0"
                                            class="h-6 w-6"
                                        />
                                        <BriefcaseBusiness
                                            v-else-if="index === 1"
                                            class="h-6 w-6"
                                        />
                                        <Building2
                                            v-else
                                            class="h-6 w-6"
                                        />
                                    </div>
                                    <div>
                                        <h3
                                            class="text-xl font-bold text-foreground"
                                        >
                                            {{ app.title }}
                                        </h3>
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ app.subtitle }}
                                        </p>
                                    </div>
                                </div>

                                <p
                                    class="min-h-[72px] text-base leading-relaxed text-muted-foreground"
                                >
                                    {{ app.description }}
                                </p>

                                <ul class="mt-6 space-y-3">
                                    <li
                                        v-for="feature in app.features"
                                        :key="feature"
                                        class="flex items-center gap-3 text-sm font-medium text-foreground"
                                    >
                                        <Check class="h-4 w-4 text-primary" />
                                        <span>{{ feature }}</span>
                                    </li>
                                </ul>

                                <div class="mt-8 grid grid-cols-2 gap-3">
                                    <a
                                        :href="
                                            mobileAppStoreHref(
                                                app.app_store_url,
                                            )
                                        "
                                        class="inline-flex h-11 items-center justify-center gap-2 rounded-full border border-border bg-white px-4 text-sm font-semibold text-foreground transition hover:border-primary/40 hover:bg-primary/5"
                                        :class="{
                                            'pointer-events-none opacity-60':
                                                !app.app_store_url,
                                        }"
                                        :aria-disabled="!app.app_store_url"
                                    >
                                        <Apple class="h-4 w-4" />
                                        {{
                                            landingSettings.mobile_apps_section
                                                .ios_label
                                        }}
                                    </a>
                                    <a
                                        :href="
                                            mobileAppStoreHref(
                                                app.google_play_url,
                                            )
                                        "
                                        class="inline-flex h-11 items-center justify-center gap-2 rounded-full border border-border bg-white px-4 text-sm font-semibold text-foreground transition hover:border-primary/40 hover:bg-primary/5"
                                        :class="{
                                            'pointer-events-none opacity-60':
                                                !app.google_play_url,
                                        }"
                                        :aria-disabled="!app.google_play_url"
                                    >
                                        <Smartphone class="h-4 w-4" />
                                        {{
                                            landingSettings.mobile_apps_section
                                                .android_label
                                        }}
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section
                v-if="landingSettings.clients_section.enabled"
                id="clients"
                class="section-padding border-y border-border bg-secondary/20"
            >
                <div class="section-container">
                    <div class="mx-auto mb-10 max-w-3xl text-center">
                        <div
                            class="mb-4 inline-flex rounded-full bg-primary/10 px-4 py-1.5 text-sm font-semibold tracking-wide text-primary uppercase"
                        >
                            {{ t('landing.clients_label') }}
                        </div>
                        <h2
                            class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl"
                        >
                            {{ t('landing.clients_title') }}
                        </h2>
                    </div>

                    <div
                        ref="clientsRail"
                        class="flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth px-1 pb-2 [scrollbar-width:none] md:px-1 [&::-webkit-scrollbar]:hidden"
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
                                />
                            </div>
                            <div
                                v-else
                                class="brand-gradient-surface flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white"
                            >
                                {{ tenantInitial(tenant.name) }}
                            </div>
                            <div class="min-w-0">
                                <div
                                    class="truncate text-sm font-medium text-foreground"
                                >
                                    {{ tenant.name }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="landingSettings.plans_section.enabled"
                id="pricing"
                class="section-padding bg-secondary/30"
            >
                <div class="section-container">
                    <div class="mx-auto mb-10 max-w-2xl text-center">
                        <h2
                            class="text-3xl font-bold text-foreground sm:text-4xl"
                        >
                            {{ landingSettings.plans_section.title }}
                        </h2>
                        <p class="mt-4 text-lg text-muted-foreground">
                            {{ landingSettings.plans_section.description }}
                        </p>
                    </div>

                    <div class="mb-12 flex items-center justify-center gap-3">
                        <span
                            class="text-sm font-medium"
                            :class="
                                !yearly
                                    ? 'text-foreground'
                                    : 'text-muted-foreground'
                            "
                            >{{ t('landing.monthly') }}</span
                        >
                        <button
                            class="brand-gradient-surface relative h-6 w-12 rounded-full transition-colors"
                            :aria-label="t('landing.toggle_yearly_pricing')"
                            type="button"
                            @click="toggleYearly"
                        >
                            <span
                                class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-primary-foreground transition-transform"
                                :class="yearly ? 'translate-x-6' : ''"
                            />
                        </button>
                        <span
                            class="text-sm font-medium"
                            :class="
                                yearly
                                    ? 'text-foreground'
                                    : 'text-muted-foreground'
                            "
                            >{{ t('landing.yearly') }}</span
                        >
                    </div>

                    <div class="relative mx-auto max-w-7xl">
                        <Swiper
                            :modules="planSwiperModules"
                            :slides-per-view="1"
                            :space-between="24"
                            :pagination="{
                                clickable: true,
                                el: '.plans-swiper-pagination',
                            }"
                            :navigation="{
                                prevEl: '.plans-swiper-prev',
                                nextEl: '.plans-swiper-next',
                            }"
                            :autoplay="{
                                delay: 4200,
                                disableOnInteraction: false,
                                pauseOnMouseEnter: true,
                            }"
                            :loop="plans.length > 4"
                            :breakpoints="{
                                640: { slidesPerView: 1.25, spaceBetween: 20 },
                                768: { slidesPerView: 2, spaceBetween: 22 },
                                1024: { slidesPerView: 3, spaceBetween: 24 },
                                1280: { slidesPerView: 4, spaceBetween: 24 },
                            }"
                            class="plans-swiper !pb-14"
                        >
                            <SwiperSlide
                                v-for="plan in plans"
                                :key="plan.id"
                                class="!h-auto"
                            >
                                <div
                                    class="card-elevated flex h-full flex-col rounded-xl p-6"
                                >
                                    <h3
                                        class="text-lg font-semibold text-foreground"
                                    >
                                        {{ plan.name }}
                                    </h3>
                                    <p
                                        class="mb-4 text-sm text-muted-foreground"
                                    >
                                        {{ plan.description || '' }}
                                    </p>

                                    <div class="mb-6">
                                        <div
                                            v-if="
                                                planPricing(plan).has_discount
                                            "
                                            class="mb-2 inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700"
                                        >
                                            <template v-if="locale === 'ar'">
                                                {{ t('landing.discount_off') }}
                                                {{
                                                    Math.round(
                                                        planPricing(plan)
                                                            .savings_percentage,
                                                    )
                                                }}%
                                            </template>
                                            <template v-else>
                                                {{
                                                    Math.round(
                                                        planPricing(plan)
                                                            .savings_percentage,
                                                    )
                                                }}% {{ t('landing.discount_off') }}
                                            </template>
                                        </div>
                                        <div class="flex items-end gap-2">
                                            <span
                                                class="text-4xl font-extrabold text-foreground"
                                                >${{
                                                    money(planPrice(plan))
                                                }}</span
                                            >
                                            <span
                                                class="text-sm text-muted-foreground"
                                                >/{{
                                                    yearly
                                                        ? t('landing.yearly')
                                                        : t('landing.monthly')
                                                }}</span
                                            >
                                        </div>
                                        <p
                                            v-if="
                                                planPricing(plan)
                                                    .has_discount &&
                                                planPricing(plan)
                                                    .original_amount
                                            "
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            <span class="line-through">
                                                ${{
                                                    money(
                                                        Number(
                                                            planPricing(plan)
                                                                .original_amount ||
                                                                0,
                                                        ),
                                                    )
                                                }}
                                            </span>
                                            <span
                                                class="ms-2 font-medium text-emerald-700"
                                            >
                                                {{ t('landing.discount_save') }}&nbsp;${{
                                                    money(
                                                        planPricing(plan)
                                                            .savings_amount,
                                                    )
                                                }}
                                            </span>
                                        </p>
                                    </div>

                                    <ul class="mb-8 flex-1 space-y-3">
                                        <li
                                            v-for="feature in plan.features ||
                                            []"
                                            :key="feature"
                                            class="flex items-start gap-2 text-sm text-muted-foreground"
                                        >
                                            <Check
                                                :size="16"
                                                class="mt-0.5 shrink-0 text-primary"
                                            />
                                            {{ feature }}
                                        </li>
                                    </ul>

                                    <Button
                                        as-child
                                        class="gradient-button w-full rounded-full"
                                    >
                                        <Link :href="registerUrl">{{
                                            navigationCtaLabel
                                        }}</Link>
                                    </Button>
                                </div>
                            </SwiperSlide>
                        </Swiper>

                        <button
                            class="plans-swiper-prev absolute top-1/2 left-0 z-10 hidden h-11 w-11 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-background text-foreground shadow-lg transition hover:bg-primary hover:text-primary-foreground lg:flex"
                            type="button"
                            :aria-label="t('pagination.previous')"
                        >
                            <ChevronLeft :size="20" />
                        </button>
                        <button
                            class="plans-swiper-next absolute top-1/2 right-0 z-10 hidden h-11 w-11 translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-background text-foreground shadow-lg transition hover:bg-primary hover:text-primary-foreground lg:flex"
                            type="button"
                            :aria-label="t('pagination.next')"
                        >
                            <ChevronRight :size="20" />
                        </button>
                        <div
                            class="plans-swiper-pagination mt-2 flex justify-center"
                        ></div>
                    </div>
                </div>
            </section>

            <section
                v-if="landingSettings.faq_section.enabled"
                id="faq"
                class="section-padding"
            >
                <div class="section-container">
                    <div class="mx-auto mb-12 max-w-5xl text-center">
                        <h2
                            class="text-3xl font-bold text-foreground sm:text-4xl lg:whitespace-nowrap"
                        >
                            {{ landingSettings.faq_section.title }}
                        </h2>
                        <p class="mx-auto mt-4 max-w-4xl text-lg text-muted-foreground">
                            {{ landingSettings.faq_section.description }}
                        </p>
                    </div>

                    <div class="mx-auto max-w-3xl space-y-3">
                        <details
                            v-for="faq in landingSettings.faq_section.items"
                            :key="`${faq.question}-${faq.answer}`"
                            class="card-elevated faq-item rounded-lg border px-5"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between py-4 font-medium text-foreground"
                            >
                                <span>{{ faq.question }}</span>
                                <ChevronDown
                                    :size="18"
                                    class="faq-chevron text-muted-foreground"
                                />
                            </summary>
                            <p class="pb-4 text-muted-foreground">
                                {{ faq.answer }}
                            </p>
                        </details>
                    </div>
                </div>
            </section>

            <section
                v-if="landingSettings.contact_section.enabled"
                id="contact"
                class="section-padding bg-secondary/30"
            >
                <div class="section-container">
                    <div class="mx-auto mb-10 max-w-2xl text-center">
                        <h2
                            class="text-3xl font-bold text-foreground sm:text-4xl"
                        >
                            {{ contactSection.title }}
                        </h2>
                        <p class="mt-4 text-lg text-muted-foreground">
                            {{ contactSection.description }}
                        </p>
                    </div>

                    <div
                        class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(320px,0.9fr)]"
                    >
                        <Card class="border-border shadow-sm">
                            <CardHeader>
                                <CardTitle>{{
                                    contactSection.form_title
                                }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form
                                    class="space-y-4"
                                    @submit.prevent="submitContact"
                                >
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div class="space-y-2">
                                            <label
                                                class="text-sm font-medium text-foreground"
                                                >{{
                                                    contactSection.name_label
                                                }}</label
                                            >
                                            <Input
                                                v-model="contactForm.name"
                                                :placeholder="
                                                    contactSection.name_placeholder
                                                "
                                            />
                                            <p
                                                v-if="contactForm.errors.name"
                                                class="text-xs text-destructive"
                                            >
                                                {{ contactForm.errors.name }}
                                            </p>
                                        </div>
                                        <div class="space-y-2">
                                            <label
                                                class="text-sm font-medium text-foreground"
                                                >{{
                                                    contactSection.email_label
                                                }}</label
                                            >
                                            <Input
                                                v-model="contactForm.email"
                                                type="email"
                                                :placeholder="
                                                    contactSection.email_placeholder
                                                "
                                            />
                                            <p
                                                v-if="contactForm.errors.email"
                                                class="text-xs text-destructive"
                                            >
                                                {{ contactForm.errors.email }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <label
                                            class="text-sm font-medium text-foreground"
                                            >{{
                                                contactSection.subject_label
                                            }}</label
                                        >
                                        <Input
                                            v-model="contactForm.subject"
                                            :placeholder="
                                                contactSection.subject_placeholder
                                            "
                                        />
                                        <p
                                            v-if="contactForm.errors.subject"
                                            class="text-xs text-destructive"
                                        >
                                            {{ contactForm.errors.subject }}
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <label
                                            class="text-sm font-medium text-foreground"
                                            >{{
                                                contactSection.message_label
                                            }}</label
                                        >
                                        <Textarea
                                            v-model="contactForm.message"
                                            rows="5"
                                            :placeholder="
                                                contactSection.message_placeholder
                                            "
                                        />
                                        <p
                                            v-if="contactForm.errors.message"
                                            class="text-xs text-destructive"
                                        >
                                            {{ contactForm.errors.message }}
                                        </p>
                                    </div>

                                    <Button
                                        type="submit"
                                        class="gradient-button h-12 rounded-full px-6"
                                    >
                                        <span v-if="contactForm.processing">{{
                                            contactSection.sending_label
                                        }}</span>
                                        <span v-else>{{
                                            contactSection.submit_label
                                        }}</span>
                                    </Button>

                                    <p
                                        v-if="contactNotice"
                                        class="text-sm font-medium"
                                        :class="
                                            contactNoticeTone === 'success'
                                                ? 'text-green-600'
                                                : 'text-destructive'
                                        "
                                    >
                                        {{ contactNotice }}
                                    </p>
                                </form>
                            </CardContent>
                        </Card>

                        <div class="space-y-6">
                            <Card class="border-border shadow-sm">
                                <CardHeader>
                                    <CardTitle>{{
                                        contactSection.direct_title
                                    }}</CardTitle>
                                </CardHeader>
                                <CardContent
                                    class="space-y-4 text-sm text-muted-foreground"
                                >
                                    <div>
                                        <p class="font-medium text-foreground">
                                            {{
                                                contactSection.direct_email_label
                                            }}
                                        </p>
                                        <a
                                            class="text-primary hover:underline"
                                            :href="`mailto:${contactRecipient}`"
                                            >{{ contactRecipient }}</a
                                        >
                                    </div>
                                    <div>
                                        <p class="font-medium text-foreground">
                                            {{
                                                contactSection.direct_phone_label
                                            }}
                                        </p>
                                        <p class="ltr-value inline-block">
                                            {{ contactSection.direct_phone }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="font-medium text-foreground">
                                            {{
                                                contactSection.response_time_label
                                            }}
                                        </p>
                                        <p>
                                            {{ contactSection.response_time }}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card class="border-border shadow-sm">
                                <CardHeader>
                                    <CardTitle>{{
                                        contactSection.quick_links_title
                                    }}</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-3 text-sm">
                                    <a
                                        v-for="link in contactSection.quick_links"
                                        :key="`${link.label}-${link.href}`"
                                        :href="link.href"
                                        class="block font-medium text-primary hover:underline"
                                    >
                                        {{ link.label }}
                                    </a>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer
            v-if="landingSettings.footer.enabled"
            class="border-t border-border bg-background py-5"
        >
            <div class="section-container space-y-4">
                <div
                    class="grid items-center gap-5 md:grid-cols-[1fr_auto_1fr]"
                >
                    <Link
                        href="/"
                        class="inline-flex items-center justify-center justify-self-center px-4 transition md:justify-self-start"
                        :aria-label="appName"
                    >
                        <AppLogoIcon class="h-9 w-auto" />
                    </Link>

                    <nav
                        class="flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-sm font-medium text-foreground"
                        :aria-label="t('landing.footer_navigation') || 'Footer navigation'"
                    >
                        <a
                            v-for="link in navLinks"
                            :key="`footer-${link.href}`"
                            :href="link.href"
                            class="whitespace-nowrap transition-colors hover:text-primary"
                        >
                            {{ link.label }}
                        </a>
                    </nav>

                    <div class="flex items-center justify-center gap-8 md:justify-self-end">
                        <div
                            v-if="landingSettings.footer.show_social_links"
                            class="flex items-center gap-2"
                        >
                            <a
                                v-for="social in footerSocialLinks"
                                :key="social.label"
                                :href="social.href"
                                class="inline-flex h-9 w-11 items-center justify-center rounded-md border border-border bg-white text-muted-foreground shadow-sm transition hover:border-primary/40 hover:text-primary"
                                :aria-label="social.label"
                            >
                                <component :is="social.icon" class="h-4 w-4" />
                            </a>
                        </div>

                        <div
                            v-if="landingSettings.footer.show_app_buttons"
                            class="flex flex-col gap-2"
                        >
                            <a
                                :href="mobileAppStoreHref(landingSettings.footer.android_url)"
                                class="inline-flex h-9 min-w-28 items-center justify-center gap-2 rounded-md border border-border bg-white px-3 text-xs font-semibold text-foreground shadow-sm transition hover:border-primary/40 hover:bg-primary/5"
                                :class="{
                                    'pointer-events-none opacity-60':
                                        !landingSettings.footer.android_url,
                                }"
                                :aria-disabled="!landingSettings.footer.android_url"
                            >
                                <Smartphone class="h-4 w-4" />
                                {{
                                    landingSettings.footer.android_label ||
                                    'Google Play'
                                }}
                            </a>
                            <a
                                :href="mobileAppStoreHref(landingSettings.footer.ios_url)"
                                class="inline-flex h-9 min-w-28 items-center justify-center gap-2 rounded-md border border-border bg-white px-3 text-xs font-semibold text-foreground shadow-sm transition hover:border-primary/40 hover:bg-primary/5"
                                :class="{
                                    'pointer-events-none opacity-60':
                                        !landingSettings.footer.ios_url,
                                }"
                                :aria-disabled="!landingSettings.footer.ios_url"
                            >
                                <Apple class="h-4 w-4" />
                                {{
                                    landingSettings.footer.ios_label ||
                                    'App Store'
                                }}
                            </a>
                        </div>
                    </div>
                </div>

                <p class="text-center text-xs text-muted-foreground">
                    &copy; {{ currentYear }} {{ appName }}.
                    {{ landingSettings.footer.copyright_text }}
                </p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
:deep(.saas-landing-main .section-padding) {
    padding-top: 2.5rem;
    padding-bottom: 2.5rem;
}

@media (min-width: 768px) {
    :deep(.saas-landing-main .section-padding) {
        padding-top: 3.5rem;
        padding-bottom: 3.5rem;
    }
}

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

:deep(.features-swiper-pagination .swiper-pagination-bullet),
:deep(.plans-swiper-pagination .swiper-pagination-bullet) {
    width: 0.65rem;
    height: 0.65rem;
    margin: 0 0.25rem;
    background: var(--muted-foreground);
    opacity: 0.35;
}

:deep(.features-swiper-pagination .swiper-pagination-bullet-active),
:deep(.plans-swiper-pagination .swiper-pagination-bullet-active) {
    width: 1.75rem;
    border-radius: 999px;
    background: var(--primary);
    opacity: 1;
}
</style>
