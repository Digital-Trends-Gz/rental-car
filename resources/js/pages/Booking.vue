<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { book } from '@/routes/tenant/fleet';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { login } from '@/routes/tenant';

interface Car {
    id: number;
    make: string;
    model: string;
    price_per_day: string;
    image_url: string;
    images: { url: string; alt: string }[];
    fuel_type: string;
    transmission: string;
    year: string;
    description: string;
    status: string;
}

interface AvailabilityRange {
    start_date: string;
    end_date: string;
}

interface ReservationLocationSetting {
    name?: string;
    pickup_fee?: number | string;
    return_fee?: number | string;
    pickup_free?: boolean;
    return_free?: boolean;
    is_active?: boolean;
}

const $page = usePage<any>();
const { t } = useTrans();
const car = computed<Car>(() => $page.props.car as Car);
const currentTenant = computed(() => $page.props.current_tenant);
const tenantSiteSettings = computed(() => $page.props.tenant_site_settings ?? null);
const reservationSettings = computed<Record<string, any> | null>(() => tenantSiteSettings.value?.reservation_settings ?? null);
const hasCoupons = computed(() => Boolean($page.props.hasCoupons));
const availabilityCalendar = computed<{
    window_starts_at: string;
    window_ends_at: string;
    today: string;
    window: {
        starts_at: string;
        ends_at: string;
        label: string;
        previous: string;
        next: string;
    };
    blocked_ranges: AvailabilityRange[];
} | null>(() => $page.props.availabilityCalendar ?? null);

const fallbackLocationOptions = [
    'Downtown Office',
    'Airport Terminal 1',
    'Airport Terminal 2',
    'Central Station',
    'Mall Plaza',
    'Hotel District',
    'Business District',
];

const normalizeLocationName = (value: string | null | undefined): string => String(value ?? '').trim();
const formatMoney = (value: number): string => (Number.isFinite(value) ? Math.max(0, value).toFixed(2) : '0.00');

const locationOptions = computed(() => {
    const configured = (reservationSettings.value?.pickup_return_locations ?? []) as ReservationLocationSetting[];
    const activeConfigured = configured
        .filter((location) => location?.is_active !== false && normalizeLocationName(location?.name) !== '')
        .map((location) => normalizeLocationName(location.name));

    const currentValues = [form.pickup_location, form.return_location]
        .filter((value): value is string => Boolean(normalizeLocationName(value)))
        .map((value) => normalizeLocationName(value));

    const sourceOptions = activeConfigured.length > 0 ? activeConfigured : fallbackLocationOptions;

    return Array.from(new Set([...currentValues, ...sourceOptions]));
});

const selectedReturnLocation = computed<ReservationLocationSetting | null>(() => {
    const needle = normalizeLocationName(form.return_location).toLowerCase();
    if (needle === '') {
        return null;
    }

    return ((reservationSettings.value?.pickup_return_locations ?? []) as ReservationLocationSetting[])
        .find((location) => location?.is_active !== false && normalizeLocationName(location?.name).toLowerCase() === needle) ?? null;
});

const form = useForm({
    start_date: '',
    end_date: '',
    pickup_location: '',
    return_location: '',
    coupon_code: '',
});

const showAvailabilityDialog = ref(false);
const selectedImageIndex = ref(0);
const couponApplying = ref(false);
const couponMessage = ref('');
const autoDiscount = ref(0);
const autoDiscountName = ref('');
const couponDiscount = ref(0);
const couponAppliedCode = ref('');

const availabilityErrorMessage = computed(() => {
    return form.errors.start_date || form.errors.end_date || '';
});

function parseDate(value: string): Date {
    return new Date(`${value}T00:00:00`);
}

function formatDate(value: Date): string {
    return value.toISOString().slice(0, 10);
}

function addDays(value: Date, days: number): Date {
    const next = new Date(value);
    next.setDate(next.getDate() + days);
    return next;
}

function formatShortDate(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
    }).format(parseDate(value));
}

function formatWeekday(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        weekday: 'short',
    }).format(parseDate(value));
}

function openAvailabilityWindow(windowStart: string) {
    if (!currentTenant.value?.slug) {
        return;
    }

    router.get(`/fleet/${car.value.id}`, { window_start: windowStart }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function isBlockedDate(iso: string): boolean {
    return Boolean(availabilityCalendar.value?.blocked_ranges.some((range) => range.start_date <= iso && range.end_date >= iso));
}

function hasBlockedDateInRange(startIso: string, endIso: string): boolean {
    return Boolean(availabilityCalendar.value?.blocked_ranges.some((range) => range.start_date <= endIso && range.end_date >= startIso));
}

function selectAvailableDate(iso: string) {
    if (!availabilityCalendar.value || iso < availabilityCalendar.value.today || isBlockedDate(iso)) {
        return;
    }

    form.clearErrors('start_date');
    form.clearErrors('end_date');

    if (!form.start_date || form.end_date) {
        form.start_date = iso;
        form.end_date = '';
        return;
    }

    if (iso < form.start_date) {
        form.start_date = iso;
        form.end_date = '';
        return;
    }

    if (hasBlockedDateInRange(form.start_date, iso)) {
        showAvailabilityDialog.value = true;
        form.setError('end_date', 'The selected range includes unavailable days. Please choose free dates only.');
        return;
    }

    form.end_date = iso;
}

const availabilityDays = computed(() => {
    if (!availabilityCalendar.value) {
        return [];
    }

    const start = parseDate(availabilityCalendar.value.window_starts_at);
    const end = parseDate(availabilityCalendar.value.window_ends_at);
    const days: Array<{
        iso: string;
        label: string;
        weekday: string;
        isPast: boolean;
        isBlocked: boolean;
        isSelectedStart: boolean;
        isSelectedEnd: boolean;
    }> = [];

    for (let cursor = start; cursor <= end; cursor = addDays(cursor, 1)) {
        const iso = formatDate(cursor);

        days.push({
            iso,
            label: formatShortDate(iso),
            weekday: formatWeekday(iso),
            isPast: iso < availabilityCalendar.value.today,
            isBlocked: isBlockedDate(iso),
            isSelectedStart: form.start_date === iso,
            isSelectedEnd: form.end_date === iso,
        });
    }

    return days;
});

// Calculate rental details
const rentalDays = computed(() => {
    if (!form.start_date || !form.end_date) return 0;
    const start = new Date(form.start_date);
    const end = new Date(form.end_date);
    const diffTime = end.getTime() - start.getTime();
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
});

const subtotal = computed(() => {
    return rentalDays.value * parseFloat(car.value.price_per_day);
});

const taxPercentage = computed(() => {
    const raw = Number(tenantSiteSettings.value?.tax_percentage ?? 7);
    if (!Number.isFinite(raw)) return 7;
    return Math.min(100, Math.max(0, raw));
});

const formattedTaxPercentage = computed(() => {
    const value = taxPercentage.value;
    return Number.isInteger(value) ? value.toString() : value.toFixed(2).replace(/\.?0+$/, '');
});

const showTax = computed(() => taxPercentage.value > 0);

const tax = computed(() => {
    return subtotal.value * (taxPercentage.value / 100);
});

const returnLocationFee = computed(() => {
    const location = selectedReturnLocation.value;
    if (!location || location.return_free) {
        return 0;
    }

    const rawFee = Number(location.return_fee ?? 0);
    return Number.isFinite(rawFee) ? Math.max(0, rawFee) : 0;
});

const total = computed(() => {
    return Math.max(0, subtotal.value + tax.value + returnLocationFee.value - autoDiscount.value - couponDiscount.value);
});

const canSubmit = computed(() => {
    return (
        form.start_date &&
        form.end_date &&
        form.pickup_location &&
        form.return_location &&
        rentalDays.value > 0
    );
});

const submitBooking = () => {
    const user = $page.props.auth.user;

    if (!user) {
        // Not authenticated: redirect to login
        router.get(login({ subdomain: currentTenant.value.slug }).url);
        return;
    }

    if (user.role === 'client') {
        // Authenticated and role is "client": make booking
        form.post(book.url({ subdomain: currentTenant.value.slug, car: car.value.id }));
        return;
    }

    if (user.role === 'admin') {
        // Authenticated but role is "admin": show alert
        alert(t('booking.alert_admin_cannot_book'));
        return;
    }

    // fallback for any other role
    alert(t('booking.alert_role_not_allowed'));
};

const clearCouponPreview = () => {
    couponDiscount.value = 0;
    couponMessage.value = '';
    couponAppliedCode.value = '';
};

const clearAutoPreview = () => {
    autoDiscount.value = 0;
    autoDiscountName.value = '';
};

const requestPricingPreview = async (couponCode = '') => {
    if (!form.start_date || !form.end_date) {
        clearAutoPreview();
        if (!couponCode) {
            clearCouponPreview();
        }
        return null;
    }

    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '';
    const response = await fetch($page.props.couponPreviewUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            start_date: form.start_date,
            end_date: form.end_date,
            return_location: form.return_location || undefined,
            coupon_code: couponCode || undefined,
        }),
    });

    const data = await response.json();
    if (!response.ok || !data?.ok) {
        throw new Error(data?.message || 'Could not calculate pricing.');
    }

    autoDiscount.value = Number(data.amounts?.auto_discount_amount || 0);
    autoDiscountName.value = String(data.auto_discount?.name || '');

    return data;
};

const applyCoupon = async () => {
    if (!form.coupon_code) {
        form.setError('coupon_code', 'Please enter coupon code.');
        return;
    }

    if (!form.start_date || !form.end_date) {
        form.setError('coupon_code', 'Please select rental dates first.');
        return;
    }

    couponApplying.value = true;
    couponMessage.value = '';
    form.clearErrors('coupon_code');

    try {
        const data = await requestPricingPreview(form.coupon_code);
        couponDiscount.value = Number(data?.amounts?.coupon_discount_amount || 0);
        couponAppliedCode.value = String(data?.coupon?.code || form.coupon_code);
        couponMessage.value = `Coupon applied: -$${couponDiscount.value.toFixed(2)}`;
    } catch (error: any) {
        clearCouponPreview();
        form.setError('coupon_code', error?.message || 'Coupon is invalid.');
        try {
            await requestPricingPreview('');
        } catch {
            // ignore refresh errors
        }
    } finally {
        couponApplying.value = false;
    }
};


// Auto-populate return location when pickup is selected
watch(
    () => form.pickup_location,
    (newLocation) => {
        if (newLocation && !form.return_location) {
            form.return_location = newLocation;
        }
    },
);

watch(
    () => [form.start_date, form.end_date],
    async () => {
        if (!form.start_date || !form.end_date) {
            clearAutoPreview();
            clearCouponPreview();
            return;
        }

        try {
            if (couponAppliedCode.value) {
                const data = await requestPricingPreview(couponAppliedCode.value);
                couponDiscount.value = Number(data?.amounts?.coupon_discount_amount || 0);
                couponMessage.value = `Coupon applied: -$${couponDiscount.value.toFixed(2)}`;
                return;
            }

            await requestPricingPreview('');
        } catch {
            clearAutoPreview();
            if (couponAppliedCode.value) {
                clearCouponPreview();
                form.setError('coupon_code', 'Coupon no longer valid for selected dates.');
            }
        }
    },
);

watch(
    () => form.coupon_code,
    async (value) => {
        if (couponAppliedCode.value && value.toUpperCase() !== couponAppliedCode.value.toUpperCase()) {
            clearCouponPreview();
            try {
                await requestPricingPreview('');
            } catch {
                clearAutoPreview();
            }
        }
    },
);

watch(
    () => [form.errors.start_date, form.errors.end_date],
    ([startError, endError]) => {
        const text = `${startError ?? ''} ${endError ?? ''}`.toLowerCase();
        if (text.includes('not available') || text.includes('another date range')) {
            showAvailabilityDialog.value = true;
        }
    },
    { immediate: true },
);

const images = computed(() => {
    if (car.value.images && car.value.images.length > 0) {
        return car.value.images;
    }
    return [
        {
            url: car.value.image_url,
            alt: `${car.value.make} ${car.value.model}`,
        },
    ];
});

const currentImage = computed(() => images.value[selectedImageIndex.value] ?? images.value[0]);

watch(
    images,
    (value) => {
        if (selectedImageIndex.value >= value.length) {
            selectedImageIndex.value = 0;
        }
    },
    { immediate: true },
);

</script>
<template>
    <HomeLayout>
        <div
            class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 py-8"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!--  Header -->
                <div class="mb-8">
                    <nav
                        class="mb-6 flex items-center space-x-2 text-sm text-gray-500"
                    >
                        <a
                            href="/fleet"
                            class="font-medium transition-colors duration-200 hover:text-orange-500"
                            >{{ t('booking.fleet') }}</a
                        >
                        <svg
                            class="h-4 w-4 text-gray-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 5l7 7-7 7"
                            ></path>
                        </svg>
                        <span class="font-medium text-gray-900"
                            >{{ car.make }} {{ car.model }}</span
                        >
                    </nav>
                    <div class="flex items-center space-x-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-orange-500 to-orange-600"
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
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                ></path>
                            </svg>
                        </div>
                        <div>
                            <h1
                                class="text-4xl leading-tight font-bold text-gray-900"
                            >
                                {{ t('booking.book_car', { make: car.make, model: car.model }) }}
                            </h1>
                            <p class="mt-1 text-gray-600">
                                {{ t('booking.subtitle') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!--  Car Details Section -->
                    <div class="space-y-8 lg:col-span-2">
                        <!--  Car Images -->
                        <div
                            class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-lg"
                        >
                            <div
                                class="relative h-72 bg-gradient-to-br from-gray-100 to-gray-200 sm:h-96"
                            >
                                <img
                                    :src="currentImage?.url"
                                    :alt="currentImage?.alt || 'car image'"
                                    class="h-full w-full object-cover transition-all duration-500"
                                />
                            </div>
                            <div v-if="images.length > 1" class="grid grid-cols-4 gap-3 border-t border-gray-100 p-4 sm:grid-cols-5">
                                <button
                                    v-for="(image, index) in images"
                                    :key="`${image.url}-${index}`"
                                    type="button"
                                    class="overflow-hidden rounded-lg border-2 bg-white transition"
                                    :class="selectedImageIndex === index ? 'border-orange-500 ring-2 ring-orange-200' : 'border-transparent hover:border-gray-200'"
                                    @click="selectedImageIndex = index"
                                >
                                    <img
                                        :src="image.url"
                                        :alt="image.alt"
                                        class="h-16 w-full object-cover"
                                    />
                                </button>
                            </div>

                            <!--  Car Info -->
                            <div class="p-8">
                                <div
                                    class="mb-6 flex items-start justify-between"
                                >
                                    <div>
                                        <h2
                                            class="mb-2 text-3xl font-bold text-gray-900"
                                        >
                                            {{ car.make }} {{ car.model }}
                                        </h2>
                                        <div
                                            class="flex items-center space-x-6 text-sm text-gray-500"
                                        >
                                            <span
                                                class="flex items-center rounded-full bg-gray-100 px-3 py-1"
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
                                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                                    ></path>
                                                </svg>
                                                {{ car.year }}
                                            </span>
                                            <span
                                                class="flex items-center rounded-full bg-gray-100 px-3 py-1 capitalize"
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
                                                {{ car.fuel_type }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div
                                            class="rounded-xl bg-gradient-to-r from-orange-500 to-orange-600 px-4 py-2 text-white"
                                        >
                                            <span class="text-3xl font-bold"
                                                >${{ car.price_per_day }}</span
                                            >
                                            <span
                                                class="block text-sm text-orange-100"
                                                >{{ t('booking.per_day') }}</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <p class="leading-relaxed text-gray-600">
                                    {{ car.description }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="availabilityCalendar"
                            class="rounded-2xl border border-gray-100 bg-white p-8 shadow-lg"
                        >
                            <div class="mb-6 flex items-center space-x-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-r from-emerald-500 to-emerald-600"
                                >
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                        ></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-bold text-gray-900">
                                        Availability Calendar
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        Green days are free. Red days are already booked. Click a free day to fill your rental dates.
                                    </p>
                                </div>
                            </div>

                            <div class="mb-6 flex flex-wrap items-center gap-3 text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
                                    <span class="text-gray-600">Free</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="h-3 w-3 rounded-full bg-red-400"></span>
                                    <span class="text-gray-600">Booked</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="h-3 w-3 rounded-full bg-orange-500"></span>
                                    <span class="text-gray-600">Selected</span>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <div class="flex items-center gap-2">
                                        <Button variant="outline" @click="openAvailabilityWindow(availabilityCalendar.window.previous)">
                                            Previous
                                        </Button>
                                        <div class="min-w-40 text-center text-lg font-semibold text-gray-900">
                                            {{ availabilityCalendar.window.label }}
                                        </div>
                                        <Button variant="outline" @click="openAvailabilityWindow(availabilityCalendar.window.next)">
                                            Next
                                        </Button>
                                    </div>

                                    <input
                                        type="date"
                                        :value="availabilityCalendar.window.starts_at"
                                        class="h-10 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm"
                                        @change="openAvailabilityWindow(($event.target as HTMLInputElement).value)"
                                    >
                                </div>

                                <div class="grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-5">
                                    <button
                                        v-for="day in availabilityDays"
                                        :key="day.iso"
                                        type="button"
                                        class="min-h-20 rounded-xl border px-3 py-3 text-left text-sm transition-all duration-200"
                                        :class="{
                                            'border-gray-200 bg-white text-gray-400': day.isPast,
                                            'border-red-200 bg-red-50 text-red-600': day.isBlocked && !day.isSelectedStart && !day.isSelectedEnd,
                                            'border-emerald-200 bg-emerald-50 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-100': !day.isPast && !day.isBlocked && !day.isSelectedStart && !day.isSelectedEnd,
                                            'border-orange-300 bg-orange-500 text-white shadow-sm': day.isSelectedStart || day.isSelectedEnd,
                                        }"
                                        :disabled="day.isPast || day.isBlocked"
                                        @click="selectAvailableDate(day.iso)"
                                    >
                                        <div class="text-xs font-semibold uppercase tracking-wide opacity-80">
                                            {{ day.weekday }}
                                        </div>
                                        <div class="mt-1 text-base font-semibold">{{ day.label }}</div>
                                        <div class="mt-1 text-[11px]">
                                            <span v-if="day.isSelectedStart || day.isSelectedEnd">Selected</span>
                                            <span v-else-if="day.isBlocked">Booked</span>
                                            <span v-else-if="day.isPast">Closed</span>
                                            <span v-else>Free</span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!--  Booking Form -->
                        <div
                            class="rounded-2xl border border-gray-100 bg-white p-8 shadow-lg"
                        >
                            <div class="mb-8 flex items-center space-x-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-r from-orange-500 to-orange-600"
                                >
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                        ></path>
                                    </svg>
                                </div>
                                <h3 class="text-2xl font-bold text-gray-900">
                                    {{ t('booking.details_title') }}
                                </h3>
                            </div>

                            <form class="space-y-8">
                                <!--  Rental Dates -->
                                <div class="space-y-4">
                                    <h4
                                        class="flex items-center text-lg font-semibold text-gray-900"
                                    >
                                        <svg
                                            class="mr-2 h-5 w-5 text-orange-500"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                            ></path>
                                        </svg>
                                        {{ t('booking.rental_period') }}
                                    </h4>
                                    <div class="grid gap-6 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <label
                                                class="block text-sm font-semibold text-gray-700"
                                            >
                                                {{ t('booking.pickup_date_required') }}
                                            </label>
                                            <input
                                                v-model="form.start_date"
                                                type="date"
                                                :min="$page.props.minDate"
                                                :max="$page.props.maxDate"
                                                required
                                                class="w-full rounded-xl border-2 border-gray-200 px-4 py-4 text-lg transition-all duration-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                                :class="{
                                                    'border-red-500 focus:border-red-500 focus:ring-red-500':
                                                        form.errors.start_date,
                                                }"
                                            />
                                            <span
                                                v-if="form.errors.start_date"
                                                class="text-sm font-medium text-red-500"
                                            >
                                                {{ form.errors.start_date }}
                                            </span>
                                        </div>

                                        <div class="space-y-2">
                                            <label
                                                class="block text-sm font-semibold text-gray-700"
                                            >
                                                {{ t('booking.return_date_required') }}
                                            </label>
                                            <input
                                                v-model="form.end_date"
                                                type="date"
                                                :min="
                                                    form.start_date ||
                                                    $page.props.minDate
                                                "
                                                :max="$page.props.maxDate"
                                                required
                                                class="w-full rounded-xl border-2 border-gray-200 px-4 py-4 text-lg transition-all duration-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                                :class="{
                                                    'border-red-500 focus:border-red-500 focus:ring-red-500':
                                                        form.errors.end_date,
                                                }"
                                            />
                                            <span
                                                v-if="form.errors.end_date"
                                                class="text-sm font-medium text-red-500"
                                            >
                                                {{ form.errors.end_date }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!--  Locations -->
                                <div class="space-y-4">
                                    <h4
                                        class="flex items-center text-lg font-semibold text-gray-900"
                                    >
                                        <svg
                                            class="mr-2 h-5 w-5 text-orange-500"
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
                                        {{ t('booking.pickup_return_locations') }}
                                    </h4>
                                    <div class="grid gap-6 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <label
                                                class="block text-sm font-semibold text-gray-700"
                                            >
                                                {{ t('booking.pickup_location_required') }}
                                            </label>
                                            <select
                                                v-model="form.pickup_location"
                                                required
                                                class="w-full rounded-xl border-2 border-gray-200 bg-white px-4 py-4 text-lg transition-all duration-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                                :class="{
                                                    'border-red-500 focus:border-red-500 focus:ring-red-500':
                                                        form.errors
                                                            .pickup_location,
                                                }"
                                            >
                                                <option value="">
                                                    {{ t('booking.select_pickup_location') }}
                                                </option>
                                                <option
                                                    v-for="location in locationOptions"
                                                    :key="location"
                                                    :value="location"
                                                >
                                                    {{ location }}
                                                </option>
                                            </select>
                                            <span
                                                v-if="
                                                    form.errors.pickup_location
                                                "
                                                class="text-sm font-medium text-red-500"
                                            >
                                                {{
                                                    form.errors.pickup_location
                                                }}
                                            </span>
                                        </div>

                                        <div class="space-y-2">
                                            <label
                                                class="block text-sm font-semibold text-gray-700"
                                            >
                                                {{ t('booking.return_location_required') }}
                                            </label>
                                            <select
                                                v-model="form.return_location"
                                                required
                                                class="w-full rounded-xl border-2 border-gray-200 bg-white px-4 py-4 text-lg transition-all duration-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                                :class="{
                                                    'border-red-500 focus:border-red-500 focus:ring-red-500':
                                                        form.errors
                                                            .return_location,
                                                }"
                                            >
                                                <option value="">
                                                    {{ t('booking.select_return_location') }}
                                                </option>
                                                <option
                                                    v-for="location in locationOptions"
                                                    :key="location"
                                                    :value="location"
                                                >
                                                    {{ location }}
                                                </option>
                                            </select>
                                            <span
                                                v-if="
                                                    form.errors.return_location
                                                "
                                                class="text-sm font-medium text-red-500"
                                            >
                                                {{
                                                    form.errors.return_location
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Coupon -->
                                <div v-if="hasCoupons" class="space-y-4">
                                    <h4
                                        class="flex items-center text-lg font-semibold text-gray-900"
                                    >
                                        <svg
                                            class="mr-2 h-5 w-5 text-orange-500"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 14l6-6m-5.5 0h.01m4.99 9h.01M7 19h10a2 2 0 002-2v-4a2 2 0 00-2-2h-2l-2-2H7a2 2 0 00-2 2v6a2 2 0 002 2z"
                                            />
                                        </svg>
                                        Coupon Code
                                    </h4>
                                    <div class="flex flex-col gap-3 md:flex-row">
                                        <input
                                            v-model="form.coupon_code"
                                            type="text"
                                            placeholder="e.g. SAVE10"
                                            class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 text-lg uppercase transition-all duration-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                        />
                                        <button
                                            type="button"
                                            class="rounded-xl bg-gray-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-gray-800 disabled:opacity-60"
                                            :disabled="couponApplying"
                                            @click="applyCoupon"
                                        >
                                            {{ couponApplying ? 'Applying...' : 'Apply Coupon' }}
                                        </button>
                                    </div>
                                    <span
                                        v-if="form.errors.coupon_code"
                                        class="text-sm font-medium text-red-500"
                                    >
                                        {{ form.errors.coupon_code }}
                                    </span>
                                    <span
                                        v-else-if="couponMessage"
                                        class="text-sm font-medium text-emerald-600"
                                    >
                                        {{ couponMessage }}
                                    </span>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!--  Price Summary Sidebar -->
                    <div class="lg:col-span-1">
                        <div
                            class="sticky top-4 rounded-2xl border border-gray-100 bg-white p-8 shadow-lg"
                        >
                            <div class="mb-6 flex items-center space-x-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-r from-orange-500 to-orange-600"
                                >
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                                        ></path>
                                    </svg>
                                </div>
                                <h3 class="text-2xl font-bold text-gray-900">
                                    {{ t('booking.summary_title') }}
                                </h3>
                            </div>

                            <div class="mb-8 space-y-6">
                                <div
                                    class="space-y-4 rounded-xl bg-gray-50 p-4"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span class="font-medium text-gray-600"
                                            >{{ t('booking.rental_period') }}</span
                                        >
                                        <span class="font-bold text-gray-900">
                                            {{
                                                rentalDays > 0
                                                    ? t(
                                                        rentalDays > 1
                                                            ? 'booking.days_plural'
                                                            : 'booking.days_singular',
                                                        { count: rentalDays },
                                                    )
                                                    : '-'
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span class="font-medium text-gray-600"
                                            >{{ t('booking.daily_rate') }}</span
                                        >
                                        <span class="font-bold text-gray-900"
                                            >${{ car.price_per_day }}</span
                                        >
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div
                                        class="flex items-center justify-between py-2"
                                    >
                                        <span class="font-medium text-gray-600"
                                            >{{ t('booking.subtotal') }}</span
                                        >
                                        <span
                                            class="text-lg font-bold text-gray-900"
                                        >
                                            ${{
                                                rentalDays > 0
                                                    ? subtotal.toFixed(2)
                                                    : '0.00'
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        v-if="showTax"
                                        class="flex items-center justify-between py-2"
                                    >
                                        <span class="font-medium text-gray-600"
                                            >Tax ({{ formattedTaxPercentage }}%)</span
                                        >
                                        <span
                                            class="text-lg font-bold text-gray-900"
                                        >
                                            ${{
                                                rentalDays > 0
                                                    ? tax.toFixed(2)
                                                    : '0.00'
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        class="flex items-center justify-between py-2"
                                    >
                                        <span class="font-medium text-gray-600">
                                            {{ t('booking.return_location_fee') }}
                                            <span v-if="form.return_location" class="text-xs text-gray-500">
                                                ({{ form.return_location }})
                                            </span>
                                        </span>
                                        <span
                                            class="text-lg font-bold text-gray-900"
                                        >
                                            ${{
                                                rentalDays > 0
                                                    ? returnLocationFee.toFixed(2)
                                                    : '0.00'
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        class="flex items-center justify-between py-2"
                                    >
                                        <span class="font-medium text-gray-600">
                                            Auto Discount
                                            <span v-if="autoDiscountName" class="text-xs text-gray-500">({{ autoDiscountName }})</span>
                                        </span>
                                        <span
                                            class="text-lg font-bold text-emerald-600"
                                        >
                                            -${{
                                                rentalDays > 0
                                                    ? autoDiscount.toFixed(2)
                                                    : '0.00'
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        v-if="hasCoupons"
                                        class="flex items-center justify-between py-2"
                                    >
                                        <span class="font-medium text-gray-600">Coupon Discount</span>
                                        <span
                                            class="text-lg font-bold text-emerald-600"
                                        >
                                            -${{
                                                rentalDays > 0
                                                    ? couponDiscount.toFixed(2)
                                                    : '0.00'
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        class="border-t-2 border-gray-200 pt-4"
                                    >
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-xl font-bold text-gray-900"
                                                >{{ t('booking.total') }}</span
                                            >
                                            <span
                                                class="text-2xl font-bold text-orange-500"
                                            >
                                                ${{
                                                    rentalDays > 0
                                                        ? total.toFixed(2)
                                                        : '0.00'
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!--  Book Now Button -->
                            <button
                                @click="submitBooking"
                                :disabled="!canSubmit || form.processing"
                                :class="{
                                    'transform cursor-pointer bg-gradient-to-r from-orange-500 to-orange-600 text-white shadow-lg hover:scale-[1.01] hover:from-orange-600 hover:to-orange-700 hover:shadow-xl':
                                        canSubmit && !form.processing,
                                    'cursor-not-allowed bg-gray-300 text-gray-500':
                                        !canSubmit || form.processing,
                                }"
                                class="w-full rounded-xl px-6 py-5 text-lg font-bold transition-all duration-300"
                            >
                                <span
                                    v-if="form.processing"
                                    class="flex items-center justify-center"
                                >
                                    <svg
                                        class="mr-3 -ml-1 h-6 w-6 animate-spin text-white"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="4"
                                        ></circle>
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        ></path>
                                    </svg>
                                    {{ t('booking.processing') }}
                                </span>
                                <span
                                    v-else-if="!$page.props.auth.user"
                                    class="flex items-center justify-center"
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
                                            d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"
                                        ></path>
                                    </svg>
                                    {{ t('booking.login_to_book') }}
                                </span>
                                <span
                                    v-else
                                    class="flex items-center justify-center"
                                >
                                    <svg
                                        class="mr-2 h-5 w-5 fill-white"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 640 640"
                                    >
                                        <path
                                            d="M416 64C433.7 64 448 78.3 448 96L448 128L480 128C515.3 128 544 156.7 544 192L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 192C96 156.7 124.7 128 160 128L192 128L192 96C192 78.3 206.3 64 224 64C241.7 64 256 78.3 256 96L256 128L384 128L384 96C384 78.3 398.3 64 416 64zM438 225.7C427.3 217.9 412.3 220.3 404.5 231L285.1 395.2L233 343.1C223.6 333.7 208.4 333.7 199.1 343.1C189.8 352.5 189.7 367.7 199.1 377L271.1 449C276.1 454 283 456.5 289.9 456C296.8 455.5 303.3 451.9 307.4 446.2L443.3 259.2C451.1 248.5 448.7 233.5 438 225.7z"
                                        />
                                    </svg>
                                    {{ t('booking.book_now') }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Dialog v-model:open="showAvailabilityDialog">
            <DialogContent class="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle>Car Not Available</DialogTitle>
                    <DialogDescription>
                        {{ availabilityErrorMessage || 'This car is not available for the selected dates. Please choose another time range.' }}
                    </DialogDescription>
                </DialogHeader>

                <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    Please select different pickup and return dates, then try booking again.
                </div>

                <DialogFooter>
                    <Button type="button" @click="showAvailabilityDialog = false">
                        OK
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </HomeLayout>
</template>
