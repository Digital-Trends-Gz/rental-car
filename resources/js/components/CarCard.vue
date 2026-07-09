<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { usePage } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { show as tenantFleetShow } from '@/routes/tenant/fleet';
import { Calendar, MapPin } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Car {
    id: number;
    make: string;
    model: string;
    year: number;
    price_per_day: string;
    description: string;
    fuel_type: string;
    image_url: string;
    status?: string;
    tenant_slug?: string | null;
    tenant_name?: string | null;
    tenant_logo_url?: string | null;
    tenant_primary_color?: string | null;
    tenant_secondary_color?: string | null;
    location_text?: string | null;
}

interface Props {
    car: Car;
}

const page = usePage<any>();
const { t } = useTrans();
const currentTenant = computed(() => page.props.current_tenant);
const appBranding = computed(() => page.props.app_branding ?? {});
const tenantLogoFailed = ref(false);

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

const cardTheme = (car: Car) => {
    const appPrimary = appBranding.value.primary_color || '#3b82f6';
    const appSecondary = appBranding.value.secondary_color || '#6d28d9';
    const primary = currentTenant.value
        ? (car.tenant_primary_color || appPrimary)
        : appPrimary;
    const secondary = currentTenant.value
        ? (car.tenant_secondary_color || appSecondary)
        : appSecondary;

    return {
        primary,
        secondary,
        gradient: `linear-gradient(90deg, ${primary}, ${secondary})`,
    };
};

const currentLocalePrefix = (): string => {
    const locale = String(page.props.locale || '').trim();
    if (!locale) {
        return '';
    }

    const pathname = window.location.pathname;
    const prefixed = `/${locale}`;
    return pathname === prefixed || pathname.startsWith(`${prefixed}/`) ? prefixed : '';
};

const bookCar = (car: Car) => {
    const slug = car.tenant_slug || page.props.current_tenant?.slug;
    const localePrefix = currentLocalePrefix();

    if (!slug) {
        router.get(`${localePrefix}/fleet` || '/fleet');
        return;
    }

    const bookingUrl = new URL(
        tenantFleetShow.url({
            subdomain: slug,
            car: car.id,
        }),
        window.location.origin,
    );

    const locale = String(page.props.locale || '').trim();
    if (locale) {
        bookingUrl.pathname = `/${locale}${bookingUrl.pathname}`;
    } else if (localePrefix) {
        bookingUrl.pathname = `${localePrefix}${bookingUrl.pathname}`;
    }

    window.location.href = bookingUrl.toString();
};

defineProps<Props>();
</script>

<template>
    <div
        :style="{
            '--card-primary': cardTheme(car).primary,
            '--card-secondary': cardTheme(car).secondary,
            '--card-gradient': cardTheme(car).gradient,
        }"
        class="group relative flex flex-col overflow-hidden rounded-2xl border border-border bg-white shadow-lg transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl"
    >
        <!-- Car Image -->
        <div
            class="relative h-56 overflow-hidden bg-gradient-to-br from-gray-50 to-gray-100"
        >
            <img
                :src="car.image_url"
                :alt="`${car.make} ${car.model}`"
                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
            />

            <!-- Price Badge -->
            <div
                class="absolute right-4 top-4 rounded-2xl px-4 py-3 shadow-lg"
                :style="{ background: 'var(--card-gradient)', boxShadow: '0 12px 24px -12px var(--card-primary)' }"
            >
                <span class="text-lg font-extrabold leading-none text-white">${{ car.price_per_day }}</span>
                <span class="ml-1 text-sm font-medium text-primary-foreground/90">{{ t('car_card.per_day') }}</span>
            </div>

            <div
                v-if="car.status && car.status !== 'available'"
                class="absolute left-4 top-4 rounded-full bg-black/70 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-white backdrop-blur"
            >
                {{ car.status }}
            </div>
        </div>

        <!--  Car Details -->
        <div class="flex flex-1 flex-col gap-4 p-5">
            <!-- Header -->
            <div class="space-y-3">
                <h3
                    class="min-h-[4rem] text-[1.55rem] font-bold leading-tight tracking-tight text-foreground transition-colors"
                    :style="{ color: cardTheme(car).primary }"
                >
                    {{ car.make }} {{ car.model }} - {{ car.year }}
                </h3>

                <div class="grid gap-2 text-xs font-medium text-muted-foreground">
                    <div class="flex min-w-0 items-center gap-2 rounded-xl bg-slate-50 px-2.5 py-2">
                        <div
                            v-if="car.tenant_logo_url && !tenantLogoFailed"
                            class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-white p-0.5"
                        >
                            <img
                                :src="car.tenant_logo_url"
                                :alt="car.tenant_name || 'Tenant logo'"
                                class="h-full w-full object-contain"
                                @error="tenantLogoFailed = true"
                            >
                        </div>
                        <div
                            v-else
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-[9px] font-semibold uppercase"
                            :style="{
                                backgroundColor: withOpacity(cardTheme(car).primary, 0.12),
                                color: cardTheme(car).primary,
                            }"
                        >
                            {{ (car.tenant_name || 'T').trim().charAt(0) }}
                        </div>
                        <span class="min-w-0 truncate">{{ car.tenant_name || 'Tenant' }}</span>
                    </div>
                    <div class="flex min-w-0 items-start gap-2 rounded-xl bg-slate-50 px-2.5 py-2">
                        <MapPin :size="14" class="mt-0.5 shrink-0" :style="{ color: cardTheme(car).primary }" />
                        <span class="line-clamp-2 min-w-0 break-words leading-snug">
                            {{ car.location_text || 'Location not set' }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex min-w-0 items-center gap-1.5 capitalize text-sm text-foreground">
                        <svg
                            class="h-4 w-4 shrink-0"
                            :style="{ color: cardTheme(car).primary }"
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
                        <span class="truncate font-medium">{{ car.fuel_type }}</span>
                    </div>
                    <div class="shrink-0 rounded-full bg-slate-400 px-3 py-1 text-[11px] leading-none text-white">
                        <p>{{ t('car_card.gps_included') }}</p>
                    </div>
                    <div class="shrink-0 rounded-full bg-slate-400 px-3 py-1 text-[11px] leading-none text-white">
                        <p>{{ t('car_card.insurance_included') }}</p>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <p class="line-clamp-2 text-sm leading-relaxed text-muted-foreground">
                {{ car.description }}
            </p>

            <button
                @click="bookCar(car)"
                class="group/btn mt-auto w-full cursor-pointer rounded-xl px-6 py-4 font-semibold shadow-lg transition-all duration-200 focus:ring-2 focus:ring-offset-2 focus:outline-none"
                :style="{
                    background: 'var(--card-gradient)',
                    boxShadow: `0 12px 24px -12px ${cardTheme(car).primary}`,
                }"
            >
                <span class="flex items-center justify-center gap-2 text-white">
                    <Calendar :size="18" class="transition-transform group-hover/btn:scale-110" />
                    {{ car.status && car.status !== 'available' ? 'Check Availability' : t('car_card.book_now') }}
                </span>
            </button>
        </div>
    </div>
</template>
