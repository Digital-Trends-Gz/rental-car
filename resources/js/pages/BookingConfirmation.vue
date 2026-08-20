<script setup lang="ts">
import SeoHead from '@/components/SeoHead.vue';
import { useTrans } from '@/composables/useTrans';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { usePage } from '@inertiajs/vue3';
import { fleet } from '@/routes/tenant/index.ts';
import { index as reservationsIndex } from '@/routes/client/reservations';
import { computed } from 'vue';

interface Reservation {
    id: number;
    reservation_number: string;
    start_date: string;
    end_date: string;
    pickup_location: string;
    return_location: string;
    driver_license: string;
    phone: string;
    additional_notes?: string;
    subtotal?: string | number;
    tax_amount?: string | number;
    discount_amount?: string | number;
    auto_discount_amount?: string | number;
    coupon_code?: string | null;
    total_amount: string;
    status: string;
    created_at: string;
    car: {
        make: string;
        model: string;
        year: number;
        image_url: string;
        description: string;
        fuel_type: string;
    };
    user: {
        name: string;
        email: string;
    };
}

interface PageProps {
    reservation: Reservation;
    seo?: {
        title?: string | null;
        description?: string | null;
        canonical_url?: string | null;
        robots?: string | null;
        og_title?: string | null;
        og_description?: string | null;
        og_image?: string | null;
        alternates?: Array<{ locale: string; url: string }>;
        schemas?: Array<Record<string, unknown>>;
    } | null;
    current_tenant: {
        slug: string;
        name: string;
    };
    currency?: {
        code?: string | null;
        symbol?: string | null;
    } | null;
}

const $page = usePage<PageProps>();
const { t, locale } = useTrans();
const reservation = $page.props.reservation;
const currentTenant = $page.props.current_tenant;
const currency = computed(() => $page.props.currency ?? null);
const seo = computed(() => $page.props.seo ?? null);

function toAmount(value: unknown): number {
    const parsed = Number(value ?? 0);
    return Number.isFinite(parsed) ? parsed : 0;
}

function formatMoney(value: number): string {
    const code = String(currency.value?.code || '').toUpperCase();

    if (/^[A-Z]{3}$/.test(code)) {
        try {
            return new Intl.NumberFormat(locale.value || undefined, {
                style: 'currency',
                currency: code,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(value);
        } catch {
            // Fall back to the tenant currency symbol below.
        }
    }

    const symbol = String(currency.value?.symbol || '$');

    return `${symbol}${value.toFixed(2)}`;
}

const amounts = computed(() => {
    const subtotal = toAmount(reservation.subtotal);
    const tax = toAmount(reservation.tax_amount);
    const total = toAmount(reservation.total_amount);
    const autoDiscount = Math.max(0, toAmount(reservation.auto_discount_amount));
    const totalDiscount = Math.max(0, toAmount(reservation.discount_amount));
    const couponDiscount = Math.max(0, totalDiscount - autoDiscount);

    return {
        subtotal,
        tax,
        total,
        autoDiscount,
        totalDiscount,
        couponDiscount,
        couponCode: (reservation.coupon_code ?? '').trim(),
    };
});
</script>

<template>
    <HomeLayout>
        <SeoHead :seo="seo" />
        <div class="min-h-screen bg-white py-12">
            <div class="mx-auto max-w-7xl px-6">
                <!-- Clean success header with minimal styling -->
                <div class="mb-12 text-center">
                    <div
                        class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100"
                    >
                        <svg
                            class="h-8 w-8 text-green-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 13l4 4L19 7"
                            ></path>
                        </svg>
                    </div>
                    <h1 class="mb-2 text-3xl font-bold text-gray-900">
                        {{ t('booking_confirmation.title') }}
                    </h1>
                    <p class="text-gray-600">
                        {{ t('booking_confirmation.reservation_number', { number: reservation.reservation_number }) }}
                    </p>
                </div>

                <!-- Clean two-column layout with proper alignment -->
                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Main Details -->
                    <div class="space-y-8 lg:col-span-2">
                        <!-- Car Information -->
                        <div class="rounded-lg border border-gray-200 p-6">
                            <h2
                                class="mb-6 text-xl font-semibold text-gray-900"
                            >
                                {{ t('booking_confirmation.vehicle_details') }}
                            </h2>
                            <div class="flex items-start space-x-6">
                                <img
                                    :src="reservation.car.image_url"
                                    :alt="`${reservation.car.make} ${reservation.car.model}`"
                                    class="h-24 w-32 rounded-lg object-cover"
                                />
                                <div class="space-y-2">
                                    <h3
                                        class="text-lg font-medium text-gray-900"
                                    >
                                        {{ reservation.car.make }}
                                        {{ reservation.car.model }} - {{ reservation.car.year }}
                                    </h3>
                                    <p class="bg-gray-100 px-2 rounded w-fit">
                                        {{ reservation.car.fuel_type }}
                                    </p>
                                    <p class="text-gray-600">
                                        {{ reservation.car.description }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Rental Details -->
                        <div class="rounded-lg border border-gray-200 p-6">
                            <h2
                                class="mb-6 text-xl font-semibold text-gray-900"
                            >
                                {{ t('booking_confirmation.rental_information') }}
                            </h2>
                            <div class="grid gap-8 md:grid-cols-2">
                                <div>
                                    <h3 class="mb-4 font-medium text-gray-900">
                                        {{ t('booking_confirmation.dates') }}
                                    </h3>
                                    <div class="space-y-3">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600"
                                                >{{ t('booking_confirmation.pickup') }}:</span
                                            >
                                            <span class="font-medium">{{
                                                new Date(
                                                    reservation.start_date,
                                                ).toLocaleDateString(locale.value)
                                            }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600"
                                                >{{ t('booking_confirmation.return') }}:</span
                                            >
                                            <span class="font-medium">{{
                                                new Date(
                                                    reservation.end_date,
                                                ).toLocaleDateString(locale.value)
                                            }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h3 class="mb-4 font-medium text-gray-900">
                                        {{ t('booking_confirmation.locations') }}
                                    </h3>
                                    <div class="space-y-3">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600"
                                                >{{ t('booking_confirmation.pickup') }}:</span
                                            >
                                            <span class="font-medium">{{
                                                reservation.pickup_location
                                            }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600"
                                                >{{ t('booking_confirmation.return') }}:</span
                                            >
                                            <span class="font-medium">{{
                                                reservation.return_location
                                            }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="rounded-lg border border-gray-200 p-6">
                            <h2
                                class="mb-6 text-xl font-semibold text-gray-900"
                            >
                                {{ t('booking_confirmation.contact_details') }}
                            </h2>
                            <div class="grid gap-8 md:grid-cols-2">
                                    <div class="flex gap-2">
                                        <span class="text-gray-600">{{ t('booking_confirmation.name') }}:</span>
                                        <span class="font-medium">{{
                                            reservation.user.name
                                        }}</span>
                                    </div>
                                    <div class="flex gap-2">
                                        <span class="text-gray-600"
                                            >{{ t('booking_confirmation.email') }}:</span
                                        >
                                        <span class="font-medium">{{
                                            reservation.user.email
                                        }}</span>
                                    </div>
                            </div>
                        </div>
                    </div>

                    <!-- Clean sidebar with price summary and next steps -->
                    <div class="space-y-6">
                        <!-- Price Summary -->
                        <div class="rounded-lg border border-gray-200 p-6">
                            <h2
                                class="mb-4 text-xl font-semibold text-gray-900"
                            >
                                {{ t('booking_confirmation.summary') }}
                            </h2>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ t('booking_confirmation.status') }}:</span>
                                    <span
                                        class="rounded-full bg-yellow-100 px-3 py-1 text-sm text-yellow-800 capitalize"
                                    >
                                        {{ reservation.status }}
                                    </span>
                                </div>
                                <div class="border-t pt-3 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">{{ t('booking.subtotal') }}:</span>
                                        <span class="font-medium text-gray-900">{{ formatMoney(amounts.subtotal) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">{{ t('booking.tax') }}:</span>
                                        <span class="font-medium text-gray-900">{{ formatMoney(amounts.tax) }}</span>
                                    </div>
                                    <div v-if="amounts.autoDiscount > 0" class="flex items-center justify-between">
                                        <span class="text-gray-600">Auto Discount:</span>
                                        <span class="font-medium text-emerald-700">-{{ formatMoney(amounts.autoDiscount) }}</span>
                                    </div>
                                    <div v-if="amounts.couponDiscount > 0" class="flex items-center justify-between">
                                        <span class="text-gray-600">
                                            Coupon Discount
                                            <span v-if="amounts.couponCode" class="text-xs text-gray-500">({{ amounts.couponCode }})</span>
                                            :
                                        </span>
                                        <span class="font-medium text-emerald-700">-{{ formatMoney(amounts.couponDiscount) }}</span>
                                    </div>
                                    <div v-if="amounts.totalDiscount > 0" class="flex items-center justify-between">
                                        <span class="text-gray-600">Total Discount:</span>
                                        <span class="font-medium text-emerald-700">-{{ formatMoney(amounts.totalDiscount) }}</span>
                                    </div>
                                </div>
                                <div class="border-t pt-3">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="text-lg font-semibold text-gray-900"
                                            >{{ t('booking_confirmation.total') }}:</span
                                        >
                                        <span
                                            class="text-2xl font-bold text-orange-500"
                                        >
                                            {{ formatMoney(amounts.total) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Next Steps -->
                        <div class="rounded-lg border border-gray-200 p-6">
                            <h2
                                class="mb-4 text-xl font-semibold text-gray-900"
                            >
                                {{ t('booking_confirmation.next_steps') }}
                            </h2>
                            <div class="space-y-4 text-sm text-gray-700">
                                <div class="flex items-start space-x-3">
                                    <span
                                        class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-medium"
                                        >1</span
                                    >
                                    <span
                                        >{{ t('booking_confirmation.step_1') }}</span
                                    >
                                </div>
                                <div class="flex items-start space-x-3">
                                    <span
                                        class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-medium"
                                        >2</span
                                    >
                                    <span
                                        >{{ t('booking_confirmation.step_2') }}</span
                                    >
                                </div>
                                <div class="flex items-start space-x-3">
                                    <span
                                        class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-medium"
                                        >3</span
                                    >
                                    <span
                                        >{{ t('booking_confirmation.step_3') }}</span
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="space-y-3">
                            <a
                                :href="reservationsIndex(currentTenant.slug).url"
                                class="block w-full rounded-lg bg-primary px-6 py-3 text-center font-medium text-primary-foreground transition-colors duration-200 hover:bg-primary/90"
                            >
                                {{ t('booking_confirmation.view_bookings') }}
                            </a>
                            <a
                                :href="fleet.url(currentTenant.slug)"
                                class="block w-full rounded-lg border border-gray-300 bg-white px-6 py-3 text-center font-medium text-gray-900 transition-colors duration-200 hover:bg-gray-50"
                            >
                                {{ t('booking_confirmation.browse_more') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </HomeLayout>
</template>
