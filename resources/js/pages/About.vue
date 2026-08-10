<script setup lang="ts">
import SeoHead from '@/components/SeoHead.vue';
import { useTrans } from '@/composables/useTrans';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { contact as mainContact, fleet as mainFleet } from '@/routes';
import { contact as tenantContact, fleet as tenantFleet } from '@/routes/tenant/index.ts';

const page = usePage<any>();
const { t, locale } = useTrans();
const currentTenant = computed(() => page.props.current_tenant);
const tenantSiteSettings = computed(() => page.props.tenant_site_settings ?? null);
const availableLocales = computed<string[]>(() =>
    Array.isArray(page.props?.available_locales) && page.props.available_locales.length
        ? page.props.available_locales
        : Array.isArray(page.props?.availableLocales) && page.props.availableLocales.length
            ? page.props.availableLocales
            : ['en']
);
const aboutContent = computed(() => tenantSiteSettings.value?.about ?? null);
const seo = computed(() => page.props.seo ?? null);
const valueIconPaths: Record<string, string> = {
    reliability: 'M353.8 118.1L330.2 70.3C326.3 62 314.1 61.7 309.8 70.3L286.2 118.1L233.9 125.6C224.6 127 220.6 138.5 227.5 145.4L265.5 182.4L256.5 234.5C255.1 243.8 264.7 251 273.3 246.7L320.2 221.9L366.8 246.3C375.4 250.6 385.1 243.4 383.6 234.1L374.6 182L412.6 145.4C419.4 138.6 415.5 127.1 406.2 125.6L353.9 118.1zM288 320C261.5 320 240 341.5 240 368L240 528C240 554.5 261.5 576 288 576L352 576C378.5 576 400 554.5 400 528L400 368C400 341.5 378.5 320 352 320L288 320zM80 384C53.5 384 32 405.5 32 432L32 528C32 554.5 53.5 576 80 576L144 576C170.5 576 192 554.5 192 528L192 432C192 405.5 170.5 384 144 384L80 384zM448 496L448 528C448 554.5 469.5 576 496 576L560 576C586.5 576 608 554.5 608 528L608 496C608 469.5 586.5 448 560 448L496 448C469.5 448 448 469.5 448 496z',
    transparency: 'M320 576C461.4 576 576 461.4 576 320C576 295.6 572.6 271.9 566.2 249.5C584.8 213.4 563.5 165.9 519.5 159.5C472.6 101.2 400.6 64 320 64C239.4 64 167.4 101.3 120.5 159.5C76.5 165.9 55.2 213.4 73.8 249.5C67.4 271.9 64 295.5 64 320C64 461.4 178.6 576 320 576zM450.7 388.9C462.6 385.2 474.6 395.2 470.3 407C447.9 468.3 389 512.1 320 512.1C251 512.1 192.1 468.2 169.7 406.9C165.4 395.1 177.4 385.1 189.3 388.8C228.5 401 273 407.9 320 407.9C367 407.9 411.5 401 450.7 388.8zM419.1 157.9C424.4 147.2 439.6 147.2 444.9 157.9L465.8 200.3L512.5 207.1C524.3 208.8 529 223.3 520.5 231.6L486.7 264.6L494.7 311.2C496.7 322.9 484.4 331.9 473.8 326.4L432 304.4L390.2 326.4C379.7 331.9 367.3 323 369.3 311.2L377.3 264.6L343.5 231.6C335 223.3 339.7 208.8 351.5 207.1L398.2 200.3L419.1 157.9zM220.9 157.9L241.8 200.3L288.5 207.1C300.3 208.8 305 223.3 296.5 231.6L262.7 264.6L270.7 311.2C272.7 322.9 260.4 331.9 249.8 326.4L208 304.4L166.2 326.4C155.7 331.9 143.3 323 145.3 311.2L153.3 264.6L119.5 231.6C111 223.3 115.7 208.8 127.5 207.1L174.2 200.3L195.1 157.9C200.4 147.2 215.6 147.2 220.9 157.9z',
    excellence: 'M320.3 192L235.7 51.1C229.2 40.3 215.6 36.4 204.4 42L117.8 85.3C105.9 91.2 101.1 105.6 107 117.5L176.6 256.6C146.5 290.5 128.3 335.1 128.3 384C128.3 490 214.3 576 320.3 576C426.3 576 512.3 490 512.3 384C512.3 335.1 494 290.5 464 256.6L533.6 117.5C539.5 105.6 534.7 91.2 522.9 85.3L436.2 41.9C425 36.3 411.3 40.3 404.9 51L320.3 192zM351.1 334.5C352.5 337.3 355.1 339.2 358.1 339.6L408.2 346.9C415.9 348 418.9 357.4 413.4 362.9L377.1 398.3C374.9 400.5 373.9 403.5 374.4 406.6L383 456.5C384.3 464.1 376.3 470 369.4 466.4L324.6 442.8C321.9 441.4 318.6 441.4 315.9 442.8L271.1 466.4C264.2 470 256.2 464.2 257.5 456.5L266.1 406.6C266.6 403.6 265.6 400.5 263.4 398.3L227.1 362.9C221.5 357.5 224.6 348.1 232.3 346.9L282.4 339.6C285.4 339.2 288.1 337.2 289.4 334.5L311.8 289.1C315.2 282.1 325.1 282.1 328.6 289.1L351 334.5z',
};
const whyChooseIconPaths: Record<string, string> = {
    star: 'M341.5 45.1C337.4 37.1 329.1 32 320.1 32C311.1 32 302.8 37.1 298.7 45.1L225.1 189.3L65.2 214.7C56.3 216.1 48.9 222.4 46.1 231C43.3 239.6 45.6 249 51.9 255.4L166.3 369.9L141.1 529.8C139.7 538.7 143.4 547.7 150.7 553C158 558.3 167.6 559.1 175.7 555L320.1 481.6L464.4 555C472.4 559.1 482.1 558.3 489.4 553C496.7 547.7 500.4 538.8 499 529.8L473.7 369.9L588.1 255.4C594.5 249 596.7 239.6 593.9 231C591.1 222.4 583.8 216.1 574.8 214.7L415 189.3L341.5 45.1z',
    headset: 'M320 128C241 128 175.3 185.3 162.3 260.7C171.6 257.7 181.6 256 192 256L208 256C234.5 256 256 277.5 256 304L256 400C256 426.5 234.5 448 208 448L192 448C139 448 96 405 96 352L96 288C96 164.3 196.3 64 320 64C443.7 64 544 164.3 544 288L544 456.1C544 522.4 490.2 576.1 423.9 576.1L336 576L304 576C277.5 576 256 554.5 256 528C256 501.5 277.5 480 304 480L336 480C362.5 480 384 501.5 384 528L384 528L424 528C463.8 528 496 495.8 496 456L496 435.1C481.9 443.3 465.5 447.9 448 447.9L432 447.9C405.5 447.9 384 426.4 384 399.9L384 303.9C384 277.4 405.5 255.9 432 255.9L448 255.9C458.4 255.9 468.3 257.5 477.7 260.6C464.7 185.3 399.1 127.9 320 127.9z',
    calendar_check: 'M416 64C433.7 64 448 78.3 448 96L448 128L480 128C515.3 128 544 156.7 544 192L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 192C96 156.7 124.7 128 160 128L192 128L192 96C192 78.3 206.3 64 224 64C241.7 64 256 78.3 256 96L256 128L384 128L384 96C384 78.3 398.3 64 416 64zM438 225.7C427.3 217.9 412.3 220.3 404.5 231L285.1 395.2L233 343.1C223.6 333.7 208.4 333.7 199.1 343.1C189.8 352.5 189.7 367.7 199.1 377L271.1 449C276.1 454 283 456.5 289.9 456C296.8 455.5 303.3 451.9 307.4 446.2L443.3 259.2C451.1 248.5 448.7 233.5 438 225.7z',
    hand_coins: 'M320 48C306.7 48 296 58.7 296 72L296 84L294.2 84C257.6 84 228 113.7 228 150.2C228 183.6 252.9 211.8 286 215.9L347 223.5C352.1 224.1 356 228.5 356 233.7C356 239.4 351.4 243.9 345.8 243.9L272 244C256.5 244 244 256.5 244 272C244 287.5 256.5 300 272 300L296 300L296 312C296 325.3 306.7 336 320 336C333.3 336 344 325.3 344 312L344 300L345.8 300C382.4 300 412 270.3 412 233.8C412 200.4 387.1 172.2 354 168.1L293 160.5C287.9 159.9 284 155.5 284 150.3C284 144.6 288.6 140.1 294.2 140.1L360 140C375.5 140 388 127.5 388 112C388 96.5 375.5 84 360 84L344 84L344 72C344 58.7 333.3 48 320 48zM141.3 405.5L98.7 448L64 448C46.3 448 32 462.3 32 480L32 544C32 561.7 46.3 576 64 576L384.5 576C413.5 576 441.8 566.7 465.2 549.5L591.8 456.2C609.6 443.1 613.4 418.1 600.3 400.3C587.2 382.5 562.2 378.7 544.4 391.8L424.6 480L312 480C298.7 480 288 469.3 288 456C288 442.7 298.7 432 312 432L384 432C401.7 432 416 417.7 416 400C416 382.3 401.7 368 384 368L231.8 368C197.9 368 165.3 381.5 141.3 405.5z',
    map_pin: 'M576 112C576 100.9 570.3 90.6 560.8 84.8C551.3 79 539.6 78.4 529.7 83.4L413.5 141.5L234.1 81.6C226 78.9 217.3 79.5 209.7 83.3L81.7 147.3C70.8 152.8 64 163.9 64 176L64 528C64 539.1 69.7 549.4 79.2 555.2C88.7 561 100.4 561.6 110.3 556.6L226.4 498.5L399.7 556.3C395.4 549.9 391.2 543.2 387.1 536.4C376.1 518.1 365.2 497.1 357.1 474.6L255.9 440.9L255.9 156.4L383.9 199.1L383.9 298.4C414.9 262.6 460.9 240 511.9 240C534.5 240 556.1 244.4 575.9 252.5L576 112zM392 405.9C392 474.8 456.1 556.3 490.6 595.2C502.2 608.2 521.9 608.2 533.5 595.2C568 556.3 632.1 474.8 632.1 405.9C632.1 340.8 578.4 288 512.1 288C445.8 288 392 340.8 392 405.9z',
    user_shield: 'M256 312C322.3 312 376 258.3 376 192C376 125.7 322.3 72 256 72C189.7 72 136 125.7 136 192C136 258.3 189.7 312 256 312zM226.3 368C127.8 368 48 447.8 48 546.3C48 562.7 61.3 576 77.7 576L329.2 576C293 533.4 272 478.5 272 420.4L272 389.3C272 382 273 374.8 274.9 368L226.3 368zM477.3 552.5L464 558.8L464 370.7L560 402.7L560 422.3C560 478.1 527.8 528.8 477.3 552.6zM453.9 323.5L341.9 360.8C328.8 365.2 320 377.4 320 391.2L320 422.3C320 496.7 363 564.4 430.2 596L448.7 604.7C453.5 606.9 458.7 608.1 463.9 608.1C469.1 608.1 474.4 606.9 479.1 604.7L497.6 596C565 564.3 608 496.6 608 422.2L608 391.1C608 377.3 599.2 365.1 586.1 360.7L474.1 323.4C467.5 321.2 460.4 321.2 453.9 323.4z',
};
const defaultWhyChooseIcons: Record<string, string> = {
    premium_fleet: 'star',
    support: 'headset',
    flexible_booking: 'calendar_check',
    competitive_pricing: 'hand_coins',
    multiple_locations: 'map_pin',
    safety_first: 'user_shield',
};
const fallbackValueItems = computed(() => [
    {
        icon: 'reliability',
        title: t('about.values.reliability.title'),
        description: t('about.values.reliability.desc'),
    },
    {
        icon: 'transparency',
        title: t('about.values.transparency.title'),
        description: t('about.values.transparency.desc'),
    },
    {
        icon: 'excellence',
        title: t('about.values.excellence.title'),
        description: t('about.values.excellence.desc'),
    },
]);
const valueItems = computed(() => {
    const items = Array.isArray(aboutContent.value?.values) ? aboutContent.value.values : [];
    const normalized = items
        .map((item: any, index: number) => ({
            icon: item?.icon || 'reliability',
            title: tenantTranslation(`tenant_about.values.${index}.title`, localizedText(item?.title, '')),
            description: tenantTranslation(`tenant_about.values.${index}.description`, localizedText(item?.description, '')),
        }))
        .filter((item: any) => item.title || item.description);

    return normalized.length ? normalized : fallbackValueItems.value;
});
const teamMembers = computed(() => [
    {
        key: 'sarah',
        image: aboutContent.value?.team_images?.sarah || '/images/team/sara.webp',
        name: t('about.team.members.sarah.name'),
        role: t('about.team.members.sarah.role'),
        bio: t('about.team.members.sarah.bio'),
    },
    {
        key: 'michael',
        image: aboutContent.value?.team_images?.michael || '/images/team/michael.webp',
        name: t('about.team.members.michael.name'),
        role: t('about.team.members.michael.role'),
        bio: t('about.team.members.michael.bio'),
    },
    {
        key: 'emily',
        image: aboutContent.value?.team_images?.emily || '/images/team/emily.webp',
        name: t('about.team.members.emily.name'),
        role: t('about.team.members.emily.role'),
        bio: t('about.team.members.emily.bio'),
    },
]);
const displayedTeamMembers = computed(() => {
    const items = Array.isArray(aboutContent.value?.team_members) ? aboutContent.value.team_members : [];
    const normalized = items
        .map((item: any, index: number) => ({
            image: item?.image_url || '/images/team/sara.webp',
            name: tenantTranslation(`tenant_about.team_members.${index}.name`, localizedText(item?.title, '')),
            role: tenantTranslation(`tenant_about.team_members.${index}.role`, item?.role || ''),
            bio: tenantTranslation(`tenant_about.team_members.${index}.bio`, localizedText(item?.description, '')),
        }))
        .filter((item: any) => item.name || item.role || item.bio);

    return normalized.length ? normalized : teamMembers.value;
});
const whyChooseContent = computed(() => aboutContent.value?.why_choose ?? null);
const whyChooseTitle = computed(() =>
    tenantTranslation(
        'tenant_about.why_choose.title',
        localizedText(whyChooseContent.value?.title, t('about.why_choose_title')),
    ),
);
const whyChooseItemText = (key: string, field: 'title' | 'description'): string => {
    const fallbackKey = field === 'title' ? 'title' : 'desc';

    return tenantTranslation(
        `tenant_about.why_choose.items.${key}.${field}`,
        localizedText(
            whyChooseContent.value?.items?.[key]?.[field],
            t(`about.why_choose.${key}.${fallbackKey}`),
        ),
    );
};
const whyChooseIconPath = (key: string): string => {
    const icon = String(whyChooseContent.value?.items?.[key]?.icon || '').trim();

    return whyChooseIconPaths[icon] || whyChooseIconPaths[defaultWhyChooseIcons[key]] || whyChooseIconPaths.star;
};
const whyChooseIconUrl = (key: string): string => String(whyChooseContent.value?.items?.[key]?.icon_url || '').trim();
const whyChooseIconColor = (key: string): string => {
    const color = String(whyChooseContent.value?.items?.[key]?.icon_color || '').trim();

    return /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(color) ? color : '#f97316';
};
const whyChooseIconMaskStyle = (key: string) => {
    const url = whyChooseIconUrl(key);

    return {
        backgroundColor: whyChooseIconColor(key),
        mask: `url("${url}") center / contain no-repeat`,
        WebkitMask: `url("${url}") center / contain no-repeat`,
    };
};

const localizedText = (value: any, fallback = ''): string => {
    const currentLocale = String(locale.value || 'en');

    if (typeof value === 'string') {
        return value.trim() !== '' ? value : fallback;
    }

    if (value && typeof value === 'object') {
        const candidate = value[currentLocale] || value.en || value.ar;
        if (typeof candidate === 'string' && candidate.trim() !== '') {
            return candidate;
        }
    }

    return fallback;
};

const tenantTranslation = (key: string, fallback = ''): string => {
    const translated = t(key);

    return translated && translated !== key ? translated : fallback;
};

const localeAwarePathname = (pathname: string) => {
    const normalizedPath = (pathname.startsWith('/') ? pathname : `/${pathname}`) || '/';
    const currentPath = typeof window !== 'undefined' ? window.location.pathname : String(page.url || '/');
    const firstSegment = currentPath.split('/').filter(Boolean)[0] || '';
    const normalizedLocales = availableLocales.value.map((item) => String(item || '').toLowerCase());
    const urlLocale = normalizedLocales.includes(firstSegment.toLowerCase()) ? firstSegment : '';
    const activeLocale = urlLocale || String(locale.value || 'en').toLowerCase().split('-')[0];

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
    localizedPath(currentTenant.value?.slug ? tenantFleet(currentTenant.value.slug).url : mainFleet().url)
);
const contactUrl = computed(() =>
    localizedPath(currentTenant.value?.slug ? tenantContact(currentTenant.value.slug).url : mainContact().url)
);
</script>
<template>
    <SeoHead :seo="seo" />
    <HomeLayout>
        <div class="min-h-screen bg-white">
            <div class="bg-gray-900 py-20 text-white">
                <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div class="text-center">
                        <h1 class="mb-6 text-4xl font-bold md:text-5xl">
                            {{ localizedText(aboutContent?.title, t('about.title')) }}
                        </h1>
                        <p
                            class="mx-auto max-w-3xl text-xl leading-relaxed text-gray-300"
                        >
                            {{ localizedText(aboutContent?.subtitle, t('about.subtitle')) }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
                <div class="mb-20">
                    <div class="grid items-center gap-12 lg:grid-cols-2">
                        <div>
                            <h2 class="mb-6 text-3xl font-bold text-gray-900">
                                {{ localizedText(aboutContent?.story_title, t('about.story_title')) }}
                            </h2>
                            <div
                                class="space-y-4 leading-relaxed text-gray-600"
                            >
                                <p>
                                    {{ localizedText(aboutContent?.story_p1, t('about.story_p1')) }}
                                </p>
                                <p>
                                    {{ localizedText(aboutContent?.story_p2, t('about.story_p2')) }}
                                </p>
                            </div>
                        </div>
                        <div class="rounded-lg bg-gray-100 p-8">
                            <div class="grid grid-cols-2 gap-6 text-center">
                                <div>
                                    <div
                                        class="mb-2 text-3xl font-bold text-orange-500"
                                    >
                                        200+
                                    </div>
                                    <div class="text-gray-600">{{ t('about.stats.vehicles') }}</div>
                                </div>
                                <div>
                                    <div
                                        class="mb-2 text-3xl font-bold text-orange-500"
                                    >
                                        50K+
                                    </div>
                                    <div class="text-gray-600">
                                        {{ t('about.stats.happy_customers') }}
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="mb-2 text-3xl font-bold text-orange-500"
                                    >
                                        15+
                                    </div>
                                    <div class="text-gray-600">{{ t('about.stats.locations') }}</div>
                                </div>
                                <div>
                                    <div
                                        class="mb-2 text-3xl font-bold text-orange-500"
                                    >
                                        9
                                    </div>
                                    <div class="text-gray-600">
                                        {{ t('about.stats.years_experience') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-20">
                    <div class="mb-12 text-center">
                        <h2 class="mb-4 text-3xl font-bold text-gray-900">
                            {{ localizedText(aboutContent?.mission_title, t('about.mission_title')) }}
                        </h2>
                        <p class="mx-auto max-w-2xl text-gray-600">
                            {{ localizedText(aboutContent?.mission_subtitle, t('about.mission_subtitle')) }}
                        </p>
                    </div>

                    <div class="grid gap-8 md:grid-cols-3">
                        <div v-for="item in valueItems" :key="`${item.icon}-${item.title}`" class="p-6 text-center">
                            <div
                                class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-orange-500"
                            >
                                <svg
                                    class="h-8 w-8 fill-white"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 640 640"
                                >
                                    <path
                                        :d="valueIconPaths[item.icon] || valueIconPaths.reliability"
                                    />
                                </svg>
                            </div>
                            <h3
                                class="mb-3 text-xl font-semibold text-gray-900"
                            >
                                {{ item.title }}
                            </h3>
                            <p class="text-gray-600">
                                {{ item.description }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mb-20">
                    <div class="rounded-lg bg-gray-50 p-8 md:p-12">
                        <h2
                            class="mb-8 text-center text-3xl font-bold text-gray-900"
                        >
                            {{ whyChooseTitle }}
                        </h2>

                        <div class="grid gap-8 md:grid-cols-2">
                            <div class="space-y-6">
                                <div class="flex items-start space-x-4">
                                    <span
                                        v-if="whyChooseIconUrl('premium_fleet')"
                                        class="h-8 w-8 shrink-0"
                                        :style="whyChooseIconMaskStyle('premium_fleet')"
                                    />
                                    <svg
                                        v-else
                                        class="h-8 w-8 fill-current"
                                        :style="{ color: whyChooseIconColor('premium_fleet') }"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 640 640"
                                    >
                                        <path :d="whyChooseIconPath('premium_fleet')" />
                                    </svg>
                                    <div>
                                        <h4
                                            class="mb-1 font-semibold text-gray-900"
                                        >
                                            {{ whyChooseItemText('premium_fleet', 'title') }}
                                        </h4>
                                        <p class="text-gray-600">
                                            {{ whyChooseItemText('premium_fleet', 'description') }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-start space-x-4">
                                    <span
                                        v-if="whyChooseIconUrl('support')"
                                        class="h-8 w-8 shrink-0"
                                        :style="whyChooseIconMaskStyle('support')"
                                    />
                                    <svg
                                        v-else
                                        class="h-8 w-8 fill-current"
                                        :style="{ color: whyChooseIconColor('support') }"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 640 640"
                                    >
                                        <path :d="whyChooseIconPath('support')" />
                                    </svg>
                                    <div>
                                        <h4
                                            class="mb-1 font-semibold text-gray-900"
                                        >
                                            {{ whyChooseItemText('support', 'title') }}
                                        </h4>
                                        <p class="text-gray-600">
                                            {{ whyChooseItemText('support', 'description') }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-start space-x-4">
                                    <span
                                        v-if="whyChooseIconUrl('flexible_booking')"
                                        class="h-8 w-8 shrink-0"
                                        :style="whyChooseIconMaskStyle('flexible_booking')"
                                    />
                                    <svg
                                        v-else
                                        class="h-8 w-8 fill-current"
                                        :style="{ color: whyChooseIconColor('flexible_booking') }"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 640 640"
                                    >
                                        <path :d="whyChooseIconPath('flexible_booking')" />
                                    </svg>
                                    <div>
                                        <h4
                                            class="mb-1 font-semibold text-gray-900"
                                        >
                                            {{ whyChooseItemText('flexible_booking', 'title') }}
                                        </h4>
                                        <p class="text-gray-600">
                                            {{ whyChooseItemText('flexible_booking', 'description') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-6">
                                <div class="flex items-start space-x-4">
                                    <span
                                        v-if="whyChooseIconUrl('competitive_pricing')"
                                        class="h-8 w-8 shrink-0"
                                        :style="whyChooseIconMaskStyle('competitive_pricing')"
                                    />
                                    <svg
                                        v-else
                                        class="h-8 w-8 fill-current"
                                        :style="{ color: whyChooseIconColor('competitive_pricing') }"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 640 640"
                                    >
                                        <path :d="whyChooseIconPath('competitive_pricing')" />
                                    </svg>
                                    <div>
                                        <h4
                                            class="mb-1 font-semibold text-gray-900"
                                        >
                                            {{ whyChooseItemText('competitive_pricing', 'title') }}
                                        </h4>
                                        <p class="text-gray-600">
                                            {{ whyChooseItemText('competitive_pricing', 'description') }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-start space-x-4">
                                    <span
                                        v-if="whyChooseIconUrl('multiple_locations')"
                                        class="h-8 w-8 shrink-0"
                                        :style="whyChooseIconMaskStyle('multiple_locations')"
                                    />
                                    <svg
                                        v-else
                                        class="h-8 w-8 fill-current"
                                        :style="{ color: whyChooseIconColor('multiple_locations') }"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 640 640"
                                    >
                                        <path :d="whyChooseIconPath('multiple_locations')" />
                                    </svg>
                                    <div>
                                        <h4
                                            class="mb-1 font-semibold text-gray-900"
                                        >
                                            {{ whyChooseItemText('multiple_locations', 'title') }}
                                        </h4>
                                        <p class="text-gray-600">
                                            {{ whyChooseItemText('multiple_locations', 'description') }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-start space-x-4">
                                    <span
                                        v-if="whyChooseIconUrl('safety_first')"
                                        class="h-8 w-8 shrink-0"
                                        :style="whyChooseIconMaskStyle('safety_first')"
                                    />
                                    <svg
                                        v-else
                                        class="h-8 w-8 fill-current"
                                        :style="{ color: whyChooseIconColor('safety_first') }"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 640 640"
                                    >
                                        <path :d="whyChooseIconPath('safety_first')" />
                                    </svg>
                                    <div>
                                        <h4
                                            class="mb-1 font-semibold text-gray-900"
                                        >
                                            {{ whyChooseItemText('safety_first', 'title') }}
                                        </h4>
                                        <p class="text-gray-600">
                                            {{ whyChooseItemText('safety_first', 'description') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-20">
                    <div class="mb-12 text-center">
                        <h2 class="mb-4 text-3xl font-bold text-gray-900">
                            {{ t('about.team_title') }}
                        </h2>
                        <p class="mx-auto max-w-2xl text-gray-600">
                            {{ t('about.team_subtitle') }}
                        </p>
                    </div>

                    <div class="grid gap-8 md:grid-cols-3">
                        <div v-for="(member, index) in displayedTeamMembers" :key="`${member.name}-${index}`" class="text-center">
                            <img
                                class="mx-auto mb-4 h-32 w-32 overflow-hidden rounded-full bg-gray-200 object-cover"
                                :src="member.image"
                                :alt="member.name"
                            />
                            <h4
                                class="mb-1 text-xl font-semibold text-gray-900"
                            >
                                {{ member.name }}
                            </h4>
                            <p class="mb-2 font-medium text-orange-500">
                                {{ member.role }}
                            </p>
                            <p class="text-sm text-gray-600">
                                {{ member.bio }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-lg bg-gray-900 p-8 text-center text-white md:p-12"
                >
                    <h2 class="mb-4 text-3xl font-bold">
                        {{ localizedText(aboutContent?.cta_title, t('about.cta_title')) }}
                    </h2>
                    <p class="mx-auto mb-8 max-w-2xl text-gray-300">
                        {{ localizedText(aboutContent?.cta_subtitle, t('about.cta_subtitle')) }}
                    </p>
                    <div class="flex flex-col justify-center gap-4 sm:flex-row">
                        <a
                            :href="fleetUrl"
                            class="rounded-lg bg-orange-500 px-8 py-3 font-semibold text-white transition-colors duration-200 hover:bg-orange-600"
                        >
                            {{ localizedText(aboutContent?.cta_browse_text, t('about.cta_browse')) }}
                        </a>
                        <a
                            :href="contactUrl"
                            class="rounded-lg border-2 border-white bg-transparent px-8 py-3 font-semibold text-white transition-colors duration-200 hover:bg-white hover:text-gray-900"
                        >
                            {{ localizedText(aboutContent?.cta_contact_text, t('about.cta_contact')) }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </HomeLayout>
</template>
