<script setup lang="ts">
import CarCard from '@/components/CarCard.vue';
import SeoHead from '@/components/SeoHead.vue';
import { useTrans } from '@/composables/useTrans';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { usePage } from '@inertiajs/vue3';
import { about as mainAbout, fleet as mainFleet } from '@/routes';
import { about as tenantAbout, fleet as tenantFleet } from '@/routes/tenant/index.ts';
import { computed } from 'vue';

interface Car {
    id: number;
    make: string;
    model: string;
    year: number;
    price_per_day: string;
    description: string;
    fuel_type: string;
    image_url: string;
    color?: string;
    status?: string;
    license_plate?: string;
    image?: string;
}

const $page = usePage<any>();
const { t } = useTrans();
const homeCars = $page.props.homeCars as Car[];
const currentTenant = computed(() => $page.props.current_tenant);
const tenantSiteSettings = computed(() => $page.props.tenant_site_settings ?? null);
const availableLocales = computed<string[]>(() =>
    Array.isArray($page.props?.available_locales) && $page.props.available_locales.length
        ? $page.props.available_locales
        : Array.isArray($page.props?.availableLocales) && $page.props.availableLocales.length
            ? $page.props.availableLocales
            : ['en']
);
const locale = computed(() => String($page.props.locale || 'en'));
const isRtl = computed(() => ['ar', 'ur', 'fa'].includes(locale.value));
const questionMark = computed(() => (isRtl.value ? '؟' : '?'));
const primaryColor = computed(() => tenantSiteSettings.value?.primary_color || '#f97316');
const secondaryColor = computed(() => tenantSiteSettings.value?.secondary_color || '#ea580c');
const accentGradient = computed(() => `linear-gradient(90deg, ${primaryColor.value}, ${secondaryColor.value})`);
const seo = computed(() => $page.props.seo ?? null);

function localizedText(node: any, fallback: string): string {
    if (!node) return fallback;

    const byLocale = node?.[locale.value];
    if (typeof byLocale === 'string' && byLocale.trim() !== '') return byLocale;

    const en = node?.en;
    if (typeof en === 'string' && en.trim() !== '') return en;

    return fallback;
}

const heroTitle = computed(() => localizedText(tenantSiteSettings.value?.hero?.title, ''));
const heroDescription = computed(() => localizedText(tenantSiteSettings.value?.hero?.description, ''));
const heroButtonText = computed(() => localizedText(tenantSiteSettings.value?.hero?.button_text, ''));
const tenantTranslation = (key: string, fallback = ''): string => {
    const translated = t(key);

    return translated && translated !== key ? translated : fallback;
};
const translatedHeroTitle = computed(() => tenantTranslation('tenant_website.hero.title', heroTitle.value));
const translatedHeroDescription = computed(() => tenantTranslation('tenant_website.hero.description', heroDescription.value));
const translatedHeroButtonText = computed(() => tenantTranslation('tenant_website.hero.button_text', heroButtonText.value));
const translatedHeroTitleParts = computed(() => {
    const title = translatedHeroTitle.value.trim();

    if (title === '') {
        return { start: '', highlight: '' };
    }

    const words = title.split(/\s+/);

    if (words.length === 1) {
        return { start: '', highlight: title };
    }

    return {
        start: words.slice(0, -1).join(' '),
        highlight: words[words.length - 1] || '',
    };
});
const homeWhyChooseContent = computed(() => tenantSiteSettings.value?.home?.why_choose ?? null);
const homeWhyChooseTitleStart = computed(() =>
    tenantTranslation('tenant_home.why_choose.title_start', localizedText(homeWhyChooseContent.value?.title_start, t('welcome.why_choose_start'))),
);
const homeWhyChooseTitleHighlight = computed(() =>
    tenantTranslation('tenant_home.why_choose.title_highlight', localizedText(homeWhyChooseContent.value?.title_highlight, t('welcome.why_choose_highlight'))),
);
const homeWhyChooseDescription = computed(() =>
    tenantTranslation('tenant_home.why_choose.description', localizedText(homeWhyChooseContent.value?.description, t('welcome.why_choose_desc'))),
);
const homeWhyChooseFallbackItems = computed(() => [
    {
        icon_url: '',
        icon_color: '#ffffff',
        icon_path: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        title: t('welcome.feature_quality_title'),
        description: t('welcome.feature_quality_desc'),
    },
    {
        icon_url: '',
        icon_color: '#ffffff',
        icon_path: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        title: t('welcome.feature_support_title'),
        description: t('welcome.feature_support_desc'),
    },
    {
        icon_url: '',
        icon_color: '#ffffff',
        icon_path: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1',
        title: t('welcome.feature_value_title'),
        description: t('welcome.feature_value_desc'),
    },
]);
const homeWhyChooseItems = computed(() => {
    const items = Array.isArray(homeWhyChooseContent.value?.items)
        ? homeWhyChooseContent.value.items
        : Object.values(homeWhyChooseContent.value?.items || {});
    const normalized = items
        .map((item: any, index: number) => ({
            icon_url: String(item?.icon_url || '').trim(),
            icon_color: String(item?.icon_color || primaryColor.value).trim(),
            icon_path: homeWhyChooseFallbackItems.value[index % homeWhyChooseFallbackItems.value.length]?.icon_path,
            title: localizedText(item?.title, ''),
            description: localizedText(item?.description, ''),
        }))
        .filter((item: any) => item.title || item.description || item.icon_url);

    return normalized.length ? normalized : homeWhyChooseFallbackItems.value;
});
const homeWhyChooseItemText = (item: any, index: number, field: 'title' | 'description'): string =>
    tenantTranslation(`tenant_home.why_choose.items.${index}.${field}`, String(item?.[field] || ''));
const homeWhyChooseIconColor = (item: any): string => {
    const color = String(item?.icon_color || '').trim();

    return /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(color) ? color : primaryColor.value;
};
const homeWhyChooseIconMaskStyle = (item: any) => {
    const url = String(item?.icon_url || '').trim();

    return {
        backgroundColor: homeWhyChooseIconColor(item),
        mask: `url("${url}") center / 2.5rem 2.5rem no-repeat`,
        WebkitMask: `url("${url}") center / 2.5rem 2.5rem no-repeat`,
    };
};
const normalizeCtaLabel = (label: string) =>
    label
        .replace(/^\s*[←→]\s*/, '')
        .replace(/\s*[←→]\s*$/, '')
        .trim();
const heroButtonLabel = computed(() => normalizeCtaLabel(translatedHeroButtonText.value || t('welcome.browse_fleet')));
const heroButtonLink = computed(() => tenantSiteSettings.value?.hero?.button_link || null);
const heroImageUrl = computed(() => tenantSiteSettings.value?.hero?.image_url || '/images/hero_image.png');
const hasCustomHeroTitle = computed(() => translatedHeroTitle.value.trim() !== '');
const localeAwarePathname = (pathname: string) => {
    const normalizedPath = (pathname.startsWith('/') ? pathname : `/${pathname}`) || '/';
    const currentPath = typeof window !== 'undefined' ? window.location.pathname : String($page.url || '/');
    const firstSegment = currentPath.split('/').filter(Boolean)[0] || '';
    const normalizedLocales = availableLocales.value.map((item) => String(item || '').toLowerCase());
    const urlLocale = normalizedLocales.includes(firstSegment.toLowerCase()) ? firstSegment : '';
    const activeLocale = urlLocale || locale.value.toLowerCase().split('-')[0];

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
const localizedPath = (path: string) => {
    const value = String(path || '/').trim() || '/';

    if (value.startsWith('#')) {
        return value;
    }

    if (/^[a-z][a-z0-9+.-]*:/i.test(value) || value.startsWith('//')) {
        try {
            const parsedUrl = new URL(value, typeof window !== 'undefined' ? window.location.origin : undefined);
            const localizedPathname = localeAwarePathname(parsedUrl.pathname);

            return `${parsedUrl.origin}${localizedPathname}${parsedUrl.search}${parsedUrl.hash}`;
        } catch {
            return value;
        }
    }

    return localeAwarePathname(value);
};
const fleetUrl = computed(() =>
    localizedPath(
        currentTenant.value?.slug
            ? tenantFleet(currentTenant.value.slug).url
            : mainFleet().url
    )
);
const aboutUrl = computed(() =>
    localizedPath(
        currentTenant.value?.slug
            ? tenantAbout(currentTenant.value.slug).url
            : mainAbout().url
    )
);
const heroButtonHref = computed(() => {
    const value = String(heroButtonLink.value || '').trim();

    return value ? localizedPath(value) : fleetUrl.value;
});
</script>

<template>
    <SeoHead :seo="seo" />

    <HomeLayout>
        <main>
            <!--  Hero Section with Light Background -->
            <section
                class="relative overflow-hidden bg-gradient-to-br from-gray-50 via-white to-gray-100 py-12"
            >
                <!-- Background Pattern -->
                <div class="absolute inset-0 opacity-5">
                    <div
                        class="absolute inset-0"
                        style="
                            background-image: radial-gradient(
                                circle at 1px 1px,
                                rgba(0, 0, 0, 0.15) 1px,
                                transparent 0
                            );
                            background-size: 20px 20px;
                        "
                    ></div>
                </div>

                <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid items-center gap-16 lg:grid-cols-2">
                        <!--  Left Content -->
                        <div class="space-y-10">
                            <div class="space-y-6">
                                <div
                                    class="inline-flex items-center rounded-full border px-4 py-2 text-sm font-semibold"
                                    :style="{ backgroundColor: `${primaryColor}14`, color: primaryColor, borderColor: `${primaryColor}40` }"
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
                                            d="M13 10V3L4 14h7v7l9-11h-7z"
                                        ></path>
                                    </svg>
                                    {{ t('welcome.badge') }}
                                </div>

                                <h1
                                    class="text-3xl leading-normal font-bold text-gray-900 lg:text-6xl py-2"
                                >
                                    <template v-if="hasCustomHeroTitle">
                                        <span v-if="translatedHeroTitleParts.start">
                                            {{ translatedHeroTitleParts.start }}
                                        </span>
                                        <span
                                            class="inline-block py-1 bg-clip-text text-transparent"
                                            :class="{ 'ms-2': translatedHeroTitleParts.start }"
                                            :style="{ backgroundImage: accentGradient }"
                                        >
                                            {{ translatedHeroTitleParts.highlight }}
                                        </span>
                                    </template>
                                    <template v-else>
                                        {{ t('welcome.hero_title_start') }}
                                        <span
                                            class="inline-block py-1 bg-clip-text text-transparent"
                                            :style="{ backgroundImage: accentGradient }"
                                        >
                                            {{ t('welcome.hero_title_highlight') }}
                                        </span>
                                    </template>
                                </h1>

                                <p
                                    class="max-w-lg text-lg leading-relaxed text-gray-600"
                                >
                                    {{ translatedHeroDescription || t('welcome.hero_desc') }}
                                </p>
                            </div>

                            <div
                                class="flex flex-col gap-4 sm:flex-row"
                                :dir="isRtl ? 'rtl' : 'ltr'"
                            >
                                <a
                                    :href="heroButtonHref"
                                    class="group inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl px-5 py-2 text-md font-semibold text-white shadow-xl transition-all duration-200 hover:scale-105 hover:shadow-2xl"
                                    :style="{ backgroundImage: accentGradient }"
                                    dir="ltr"
                                >
                                    <svg
                                        class="h-5 w-5 shrink-0 transition-transform"
                                        :class="isRtl ? 'order-first group-hover:-translate-x-1' : 'order-last group-hover:translate-x-1'"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            :d="isRtl ? 'M11 17l-5-5m0 0l5-5m-5 5h12' : 'M13 7l5 5m0 0l-5 5m5-5H6'"
                                        ></path>
                                    </svg>
                                    <span :dir="isRtl ? 'rtl' : 'ltr'">{{ heroButtonLabel }}</span>
                                </a>
                                <a
                                    :href="aboutUrl"
                                    class="inline-flex cursor-pointer items-center justify-center rounded-xl border-2 border-gray-300 bg-white text-md px-5 py-2 font-semibold text-gray-700 transition-all duration-200 hover:shadow-lg"
                                    :style="{ borderColor: `${primaryColor}66`, color: primaryColor }"
                                    :dir="isRtl ? 'rtl' : 'ltr'"
                                >
                                    {{ t('welcome.learn_more') }}
                                </a>
                            </div>

                            <!--  Stats -->
                            <div
                                class="grid grid-cols-3 gap-8 border-t border-gray-200 pt-10"
                            >
                                <div class="text-center">
                                    <div
                                        class="bg-clip-text text-4xl font-bold text-transparent"
                                        :style="{ backgroundImage: accentGradient }"
                                    >
                                        1000+
                                    </div>
                                    <div
                                        class="mt-1 text-sm font-medium text-gray-600"
                                    >
                                        {{ t('welcome.happy_customers') }}
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div
                                        class="bg-clip-text text-4xl font-bold text-transparent"
                                        :style="{ backgroundImage: accentGradient }"
                                    >
                                        150+
                                    </div>
                                    <div
                                        class="mt-1 text-sm font-medium text-gray-600"
                                    >
                                        {{ t('welcome.premium_cars') }}
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div
                                        class="bg-clip-text text-4xl font-bold text-transparent"
                                        :style="{ backgroundImage: accentGradient }"
                                    >
                                        24/7
                                    </div>
                                    <div
                                        class="mt-1 text-sm font-medium text-gray-600"
                                    >
                                        {{ t('welcome.support_24_7') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Image - Optimized for Dark Isometric Image -->
                        <div class="flex justify-center lg:justify-end">
                            <div class="relative">
                                <div
                                    class="absolute -inset-4 rounded-3xl bg-gradient-to-r from-orange-500/20 to-orange-600/20 blur-2xl"
                                ></div>
                                <img
                                    :src="heroImageUrl"
                                    :alt="t('welcome.hero_image_alt')"
                                    class="relative h-auto max-w-full rounded-2xl drop-shadow-2xl"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!--  Featured Cars Section -->
            <section id="fleet" class="bg-white py-24">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mb-20 text-center">
                        <div
                            class="mb-6 inline-flex items-center rounded-full bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-700 ring-1 ring-orange-200"
                        >
                            {{ t('welcome.collection_badge') }}
                        </div>
                        <h2
                            class="mb-6 text-4xl font-bold leading-normal text-gray-900 lg:text-5xl py-2"
                        >
                            {{ t('welcome.fleet_heading_start') }}
                            <span
                                class="inline-block py-1 bg-gradient-to-r from-orange-500 to-orange-600 bg-clip-text text-transparent"
                            >
                                {{ t('welcome.fleet_heading_highlight') }}
                            </span>
                        </h2>
                        <p
                            class="mx-auto max-w-3xl text-xl leading-relaxed text-gray-600"
                        >
                            {{ t('welcome.fleet_desc') }}
                        </p>
                    </div>

                    <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                        <CarCard
                            v-for="car in homeCars"
                            :key="car.id"
                            :car="car"
                        />
                    </div>

                    <div class="mt-16 text-center">
                        <a
                            :href="fleetUrl"
                            class="inline-flex cursor-pointer items-center rounded-xl bg-gradient-to-r from-orange-500 to-orange-600 px-8 py-4 font-semibold text-white shadow-xl transition-all duration-200 hover:scale-105 hover:from-orange-600 hover:to-orange-700 hover:shadow-2xl"
                        >
                            <svg
                                class="mr-2 h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                                ></path>
                            </svg>
                            {{ t('welcome.view_complete_fleet') }}
                        </a>
                    </div>
                </div>
            </section>

            <!--  Features Section -->
            <section id="services" class="bg-gray-50 py-24">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mb-20 text-center">
                        <h2
                            class="mb-6 text-4xl font-bold leading-normal text-gray-900 lg:text-5xl py-2"
                        >
                            {{ homeWhyChooseTitleStart }}
                            <span
                                :class="isRtl
                                    ? 'text-orange-600'
                                    : 'inline-block py-1 bg-gradient-to-r from-orange-500 to-orange-600 bg-clip-text text-transparent'"
                            >
                                {{ homeWhyChooseTitleHighlight }} </span
                            >{{ questionMark }}
                        </h2>
                        <p class="mx-auto max-w-2xl text-xl text-gray-600">{{ homeWhyChooseDescription }}</p>
                    </div>

                    <div class="grid gap-12 md:grid-cols-3">
                        <div v-for="(item, index) in homeWhyChooseItems" :key="`home-why-choose-${index}`" class="group text-center">
                            <div
                                class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 to-orange-600 shadow-xl transition-transform duration-200 group-hover:scale-110"
                            >
                                <span
                                    v-if="item.icon_url"
                                    class="h-10 w-10"
                                    :style="homeWhyChooseIconMaskStyle(item)"
                                />
                                <svg
                                    v-else
                                    class="h-10 w-10 text-white"
                                    :style="{ color: homeWhyChooseIconColor(item) }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        :d="item.icon_path"
                                    ></path>
                                </svg>
                            </div>
                            <h3 class="mb-4 text-2xl font-bold text-gray-900">
                                {{ homeWhyChooseItemText(item, index, 'title') }}
                            </h3>
                            <p class="leading-relaxed text-gray-600">{{ homeWhyChooseItemText(item, index, 'description') }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </HomeLayout>
</template>

<style scoped>
.font-sans {
    font-family:
        Cairo,
        'Inter',
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        Roboto,
        sans-serif;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
