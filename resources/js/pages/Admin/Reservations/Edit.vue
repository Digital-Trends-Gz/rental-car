<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { index } from '@/routes/admin/reservations';
import { update } from '@/routes/admin/reservations';
import { store as storeClient } from '@/routes/admin/clients';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    reservation: any | null;
    clients: Array<{ id: number; name: string; email: string }>;
    cars: Array<{ id: number; label: string; license_plate: string; branch_name?: string | null; price_per_day: number }>;
    carDamagesByCar: Record<number, Array<{
        id: number;
        zone_label: string;
        view_side_label: string;
        damage_type_label: string;
        severity_label: string;
        quantity: number;
        notes: string | null;
        first_detected_at: string | null;
    }>>;
    enums: {
        statuses: Array<{ value: string; label: string; color: string }>;
    };
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const statuses = computed(() => props.enums.statuses || []);
const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);
const isEdit = computed(() => Boolean(props.reservation));
const clients = ref([...(props.clients || [])]);
const clientOptions = computed(() =>
    clients.value.map((client) => ({
        value: String(client.id),
        label: `${client.name} (${client.email})`,
    })),
);
const carOptions = computed(() =>
    (props.cars || []).map((carOption) => ({
        value: String(carOption.id),
        label: `${carOption.label} | ${carOption.license_plate}${carOption.branch_name ? ` | ${carOption.branch_name}` : ''}`,
    })),
);
const pageTitle = computed(() =>
    isEdit.value
        ? `${localize('Edit Reservation', 'طھط¹ط¯ظٹظ„ ط§ظ„ط­ط¬ط²')} ${props.reservation?.reservation_number || ''}`.trim()
        : localize('Create Reservation', 'ط¥ظ†ط´ط§ط، ط­ط¬ط²'),
);
const locationOptions = computed(() => {
    const baseOptions = [
        localize('Downtown Office', 'ظ…ظƒطھط¨ ظˆط³ط· ط§ظ„ظ…ط¯ظٹظ†ط©'),
        localize('Airport Terminal 1', 'ظ…ط·ط§ط± - طµط§ظ„ط© 1'),
        localize('Airport Terminal 2', 'ظ…ط·ط§ط± - طµط§ظ„ط© 2'),
        localize('Central Station', 'ظ…ط­ط·ط© ط§ظ„ظ…ط±ظƒط²ظٹط©'),
        localize('Mall Plaza', 'ظ…ظˆظ„ ط¨ظ„ط§ط²ط§'),
        localize('Hotel District', 'ظ…ظ†ط·ظ‚ط© ط§ظ„ظپظ†ط§ط¯ظ‚'),
        localize('Business District', 'ط§ظ„ظ…ظ†ط·ظ‚ط© ط§ظ„طھط¬ط§ط±ظٹط©'),
    ];

    const currentValues = [
        props.reservation?.pickup_location,
        props.reservation?.return_location,
        form.pickup_location,
        form.return_location,
    ]
        .filter((value): value is string => Boolean(value && value.trim()))
        .map((value) => value.trim());

    return Array.from(new Set([...currentValues, ...baseOptions]));
});

const selectedCarDamageCases = computed(() => {
    const selectedCarId = isEdit.value
        ? Number(props.reservation?.car?.id || 0)
        : Number(form.car_id || 0);

    return selectedCarId > 0 ? (props.carDamagesByCar[selectedCarId] ?? []) : [];
});

const formatDateForInput = (dateString: string | null | undefined): string => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toISOString().split('T')[0];
};

interface AvailabilityCalendarRange {
    start_date: string;
    end_date: string;
}

interface AvailabilityCalendar {
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
    blocked_ranges: AvailabilityCalendarRange[];
}

const form = useForm({
    user_id: props.reservation?.user?.id || '',
    car_id: props.reservation?.car?.id || '',
    start_date: formatDateForInput(props.reservation?.start_date) || '',
    end_date: formatDateForInput(props.reservation?.end_date) || '',
    pickup_time: props.reservation?.pickup_time || '09:00',
    return_time: props.reservation?.return_time || '18:00',
    pickup_location: props.reservation?.pickup_location || '',
    return_location: props.reservation?.return_location || '',
    discount_amount: props.reservation?.discount_amount || 0,
    deposit_amount: 0,
    notes: props.reservation?.notes || '',
    status: props.reservation?.status || 'confirmed',
    cancellation_reason: props.reservation?.cancellation_reason || '',
});

const selectedCar = computed(() => {
    const selectedCarId = Number(form.car_id || props.reservation?.car?.id || 0);
    return props.cars.find((car) => Number(car.id) === selectedCarId) ?? props.reservation?.car ?? null;
});

const availabilityCalendar = ref<AvailabilityCalendar | null>(null);
const availabilityLoading = ref(false);
const availabilityError = ref('');
const availabilityWindowStart = ref('');
const showCreateClientDialog = ref(false);
const creatingClient = ref(false);

const clientForm = useForm({
    name: '',
    email: '',
    civil_number: '',
    password: '',
    password_confirmation: '',
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
        form.setError('end_date', localize('The selected range includes unavailable days.', 'ط§ظ„ظ†ط·ط§ظ‚ ط§ظ„ظ…ط­ط¯ط¯ ظٹط­طھظˆظٹ ط¹ظ„ظ‰ ط£ظٹط§ظ… ط؛ظٹط± ظ…طھط§ط­ط©.'));
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

async function loadAvailabilityCalendar(windowStart?: string) {
    const carId = Number(form.car_id || 0);

    if (!carId) {
        availabilityCalendar.value = null;
        availabilityError.value = '';
        availabilityWindowStart.value = '';
        return;
    }

    availabilityLoading.value = true;
    availabilityError.value = '';

    try {
        const query = new URLSearchParams();
        const nextWindowStart = windowStart || availabilityWindowStart.value || '';

        if (nextWindowStart) {
            query.set('window_start', nextWindowStart);
        }

        const response = await fetch(
            `/admin/cars/${carId}/availability-calendar${query.toString() ? `?${query.toString()}` : ''}`,
            { headers: { Accept: 'application/json' } },
        );
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data?.message || localize('Unable to load the car availability calendar.', 'طھط¹ط°ط± طھط­ظ…ظٹظ„ طھظ‚ظˆظٹظ… طھظˆظپط± ط§ظ„ط³ظٹط§ط±ط©.'));
        }

        availabilityCalendar.value = data?.availabilityCalendar ?? null;
        availabilityWindowStart.value = availabilityCalendar.value?.window.starts_at || nextWindowStart || '';
    } catch (error: any) {
        availabilityCalendar.value = null;
        availabilityError.value = error?.message || localize('Unable to load the car availability calendar.', 'طھط¹ط°ط± طھط­ظ…ظٹظ„ طھظ‚ظˆظٹظ… طھظˆظپط± ط§ظ„ط³ظٹط§ط±ط©.');
    } finally {
        availabilityLoading.value = false;
    }
}

function openAvailabilityWindow(windowStart: string) {
    availabilityWindowStart.value = windowStart;
    void loadAvailabilityCalendar(windowStart);
}

function openCreateClientDialog() {
    clientForm.clearErrors();
    clientForm.reset();
    showCreateClientDialog.value = true;
}

async function submitCreateClient() {
    if (!subdomain.value) {
        return;
    }

    clientForm.clearErrors();
    creatingClient.value = true;

    try {
        const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '';
        const payload = new FormData();
        payload.append('inline', '1');
        payload.append('name', clientForm.name);
        payload.append('email', clientForm.email);
        payload.append('civil_number', clientForm.civil_number);
        payload.append('password', clientForm.password);
        payload.append('password_confirmation', clientForm.password_confirmation);

        const response = await fetch(storeClient(subdomain.value).url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: payload,
        });

        const data = await response.json();

        if (!response.ok || !data?.ok) {
            if (response.status === 422 && data?.errors) {
                Object.entries(data.errors).forEach(([field, messages]) => {
                    const message = Array.isArray(messages) ? messages[0] : String(messages);
                    clientForm.setError(field, message);
                });
                return;
            }

            throw new Error(data?.message || localize('Unable to create client.', 'طھط¹ط°ط± ط¥ظ†ط´ط§ط، ط§ظ„ط¹ظ…ظٹظ„.'));
        }

        const newClient = {
            id: Number(data.client.id),
            name: String(data.client.name || ''),
            email: String(data.client.email || ''),
        };

        if (!clients.value.some((client) => Number(client.id) === newClient.id)) {
            clients.value = [...clients.value, newClient];
        }

        form.user_id = String(newClient.id);
        showCreateClientDialog.value = false;
        clientForm.reset();
    } catch (error: any) {
        clientForm.setError('name', error?.message || localize('Unable to create client.', 'طھط¹ط°ط± ط¥ظ†ط´ط§ط، ط§ظ„ط¹ظ…ظٹظ„.'));
    } finally {
        creatingClient.value = false;
    }
}

watch(
    () => form.car_id,
    async (newCarId, oldCarId) => {
        if (!newCarId) {
            availabilityCalendar.value = null;
            availabilityError.value = '';
            availabilityWindowStart.value = '';
            return;
        }

        if (String(newCarId) !== String(oldCarId)) {
            availabilityWindowStart.value = '';
            await loadAvailabilityCalendar();
        }
    },
    { immediate: true },
);

function submit() {
    if (!subdomain.value) return;
    if (isEdit.value) {
        form.put(update([subdomain.value, props.reservation.id]).url);
        return;
    }

    form.post('/admin/reservations');
}
</script>

<template>
    <Head :title="pageTitle" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{ isEdit ? localize('Edit Reservation', 'طھط¹ط¯ظٹظ„ ط§ظ„ط­ط¬ط²') : localize('Create Reservation', 'ط¥ظ†ط´ط§ط، ط­ط¬ط²') }}
                </h1>
                <Link v-if="subdomain" :href="index(subdomain).url">
                    <Button variant="outline">{{ localize('Back', 'ط±ط¬ظˆط¹') }}</Button>
                </Link>
            </div>

            <div v-if="isEdit" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-md border p-4">
                    <div class="text-sm text-muted-foreground">{{ localize('Reservation #', 'ط±ظ‚ظ… ط§ظ„ط­ط¬ط²') }}</div>
                    <div class="font-medium">{{ reservation.reservation_number }}</div>
                </div>
                <div class="rounded-md border p-4">
                    <div class="text-sm text-muted-foreground">{{ localize('Client', 'ط§ظ„ط¹ظ…ظٹظ„') }}</div>
                    <div class="font-medium">
                        {{ reservation.user?.name }} ({{ reservation.user?.email }})
                    </div>
                </div>
                <div class="rounded-md border p-4">
                    <div class="text-sm text-muted-foreground">{{ localize('Car', 'ط§ظ„ط³ظٹط§ط±ط©') }}</div>
                    <div class="font-medium">
                        {{
                            reservation.car
                                ? `${reservation.car.year} ${reservation.car.make} ${reservation.car.model}`
                                : 'â€”'
                        }}
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-8 shadow-lg">
                <div class="mb-6 flex items-center space-x-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-r from-emerald-500 to-emerald-600">
                        <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900">{{ localize('Availability Calendar', 'طھظ‚ظˆظٹظ… ط§ظ„طھظˆظپط±') }}</h3>
                        <p class="text-sm text-gray-500">
                            {{ localize('Green days are free. Red days are already booked. Click a free day to fill rental dates.', 'ط§ظ„ط£ظٹط§ظ… ط§ظ„ط®ط¶ط±ط§ط، ظ…طھط§ط­ط©. ط§ظ„ط£ظٹط§ظ… ط§ظ„ط­ظ…ط±ط§ط، ظ…ط­ط¬ظˆط²ط©. ط§ط¶ط؛ط· ط¹ظ„ظ‰ ظٹظˆظ… ظ…طھط§ط­ ظ„طھط¹ط¨ط¦ط© ط§ظ„طھظˆط§ط±ظٹط®.') }}
                        </p>
                    </div>
                </div>

                <div class="mb-6 flex flex-wrap items-center gap-3 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
                        <span class="text-gray-600">{{ localize('Free', 'ظ…طھط§ط­') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-3 w-3 rounded-full bg-red-400"></span>
                        <span class="text-gray-600">{{ localize('Booked', 'ظ…ط­ط¬ظˆط²') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-3 w-3 rounded-full bg-orange-500"></span>
                        <span class="text-gray-600">{{ localize('Selected', 'ظ…ط­ط¯ط¯') }}</span>
                    </div>
                </div>

                <div v-if="!selectedCar" class="rounded-xl border border-dashed border-gray-200 bg-gray-50 p-6 text-sm text-gray-500">
                    {{ localize('Select a car first to load the availability calendar.', 'ط§ط®طھط± ط§ظ„ط³ظٹط§ط±ط© ط£ظˆظ„ظ‹ط§ ظ„ط¹ط±ط¶ طھظ‚ظˆظٹظ… ط§ظ„طھظˆظپط±.') }}
                </div>

                <div v-else-if="availabilityLoading" class="rounded-xl border border-gray-200 bg-gray-50 p-6 text-sm text-gray-500">
                    {{ localize('Loading availability calendar...', 'ط¬ط§ط±ظٹ طھط­ظ…ظٹظ„ طھظ‚ظˆظٹظ… ط§ظ„طھظˆظپط±...') }}
                </div>

                <div v-else-if="availabilityError" class="rounded-xl border border-red-200 bg-red-50 p-6 text-sm text-red-700">
                    {{ availabilityError }}
                </div>

                <div v-else-if="availabilityCalendar" class="space-y-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div class="flex items-center gap-2">
                            <Button variant="outline" @click="openAvailabilityWindow(availabilityCalendar.window.previous)">
                                {{ localize('Previous', 'ط§ظ„ط³ط§ط¨ظ‚') }}
                            </Button>
                            <div class="min-w-40 text-center text-lg font-semibold text-gray-900">
                                {{ availabilityCalendar.window.label }}
                            </div>
                            <Button variant="outline" @click="openAvailabilityWindow(availabilityCalendar.window.next)">
                                {{ localize('Next', 'ط§ظ„طھط§ظ„ظٹ') }}
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
                                <span v-if="day.isSelectedStart || day.isSelectedEnd">{{ localize('Selected', 'ظ…ط­ط¯ط¯') }}</span>
                                <span v-else-if="day.isBlocked">{{ localize('Booked', 'ظ…ط­ط¬ظˆط²') }}</span>
                                <span v-else-if="day.isPast">{{ localize('Closed', 'ظ…ط؛ظ„ظ‚') }}</span>
                                <span v-else>{{ localize('Free', 'ظ…طھط§ط­') }}</span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div v-if="!isEdit">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <Label for="user_id" class="mb-0">{{ localize('Client', 'ط§ظ„ط¹ظ…ظٹظ„') }}</Label>
                            <Button type="button" variant="outline" size="sm" @click="openCreateClientDialog">
                                {{ localize('Create Client', 'ط¥ظ†ط´ط§ط، ط¹ظ…ظٹظ„') }}
                            </Button>
                        </div>
                        <SearchableSelect
                            id="user_id"
                            v-model="form.user_id"
                            :options="clientOptions"
                            :placeholder="localize('Select client', 'ط§ط®طھط± ط§ظ„ط¹ظ…ظٹظ„')"
                            :search-placeholder="localize('Search client...', 'ط§ط¨ط­ط« ط¹ظ† ط§ظ„ط¹ظ…ظٹظ„...')"
                            :empty-text="localize('No clients found.', 'ظ„ط§ ظٹظˆط¬ط¯ ط¹ظ…ظ„ط§ط،.')"
                        />
                        <InputError :message="form.errors.user_id" class="mt-1" />
                    </div>

                    <div v-if="!isEdit">
                        <Label for="car_id">{{ localize('Car', 'ط§ظ„ط³ظٹط§ط±ط©') }}</Label>
                        <SearchableSelect
                            id="car_id"
                            v-model="form.car_id"
                            :options="carOptions"
                            :placeholder="localize('Select car', 'ط§ط®طھط± ط§ظ„ط³ظٹط§ط±ط©')"
                            :search-placeholder="localize('Search car...', 'ط§ط¨ط­ط« ط¹ظ† ط§ظ„ط³ظٹط§ط±ط©...')"
                            :empty-text="localize('No cars found.', 'ظ„ط§ طھظˆط¬ط¯ ط³ظٹط§ط±ط§طھ.')"
                        />
                        <InputError :message="form.errors.car_id" class="mt-1" />
                        <div v-if="selectedCarDamageCases.length" class="mt-3 rounded-md border p-3">
                            <div class="mb-2 text-sm font-medium">{{ localize('Current Car Damages', 'ط§ظ„ط£ط¶ط±ط§ط± ط§ظ„ط­ط§ظ„ظٹط© ظ„ظ„ط³ظٹط§ط±ط©') }}</div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="border-b text-left text-muted-foreground">
                                            <th class="px-2 py-2">{{ localize('Zone', 'ط§ظ„ظ…ظ†ط·ظ‚ط©') }}</th>
                                            <th class="px-2 py-2">{{ localize('View', 'ط§ظ„ط¬ظ‡ط©') }}</th>
                                            <th class="px-2 py-2">{{ localize('Type', 'ط§ظ„ظ†ظˆط¹') }}</th>
                                            <th class="px-2 py-2">{{ localize('Severity', 'ط§ظ„ط¯ط±ط¬ط©') }}</th>
                                            <th class="px-2 py-2">{{ localize('Qty', 'ط§ظ„ظƒظ…ظٹط©') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="damage in selectedCarDamageCases" :key="damage.id" class="border-b">
                                            <td class="px-2 py-2">{{ damage.zone_label }}</td>
                                            <td class="px-2 py-2">{{ damage.view_side_label }}</td>
                                            <td class="px-2 py-2">{{ damage.damage_type_label }}</td>
                                            <td class="px-2 py-2">{{ damage.severity_label }}</td>
                                            <td class="px-2 py-2">{{ damage.quantity }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div>
                        <Label for="start_date">{{ localize('Start Date', 'طھط§ط±ظٹط® ط§ظ„ط¨ط¯ط§ظٹط©') }}</Label>
                        <Input id="start_date" v-model="form.start_date" type="date" />
                        <InputError :message="form.errors.start_date" class="mt-1" />
                    </div>

                    <div>
                        <Label for="end_date">{{ localize('End Date', 'طھط§ط±ظٹط® ط§ظ„ظ†ظ‡ط§ظٹط©') }}</Label>
                        <Input id="end_date" v-model="form.end_date" type="date" />
                        <InputError :message="form.errors.end_date" class="mt-1" />
                    </div>

                    <div>
                        <Label for="pickup_time">{{ localize('Pickup Time', 'ظˆظ‚طھ ط§ظ„ط§ط³طھظ„ط§ظ…') }}</Label>
                        <Input id="pickup_time" v-model="form.pickup_time" type="time" />
                        <InputError :message="form.errors.pickup_time" class="mt-1" />
                    </div>

                    <div>
                        <Label for="return_time">{{ localize('Return Time', 'ظˆظ‚طھ ط§ظ„ط¥ط±ط¬ط§ط¹') }}</Label>
                        <Input id="return_time" v-model="form.return_time" type="time" />
                        <InputError :message="form.errors.return_time" class="mt-1" />
                    </div>

                                        <div>
                        <Label for="pickup_location">{{ localize('Pickup Location', 'موقع الاستلام') }}</Label>
                        <select
                            id="pickup_location"
                            v-model="form.pickup_location"
                            class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm transition-all duration-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': form.errors.pickup_location }"
                        >
                            <option value="">
                                {{ localize('Select pickup location', 'اختر موقع الاستلام') }}
                            </option>
                            <option v-for="location in locationOptions" :key="location" :value="location">
                                {{ location }}
                            </option>
                        </select>
                        <InputError :message="form.errors.pickup_location" class="mt-1" />
                    </div>

                                        <div>
                        <Label for="return_location">{{ localize('Return Location', 'موقع الإرجاع') }}</Label>
                        <select
                            id="return_location"
                            v-model="form.return_location"
                            class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm transition-all duration-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': form.errors.return_location }"
                        >
                            <option value="">
                                {{ localize('Select return location', 'اختر موقع الإرجاع') }}
                            </option>
                            <option v-for="location in locationOptions" :key="location" :value="location">
                                {{ location }}
                            </option>
                        </select>
                        <InputError :message="form.errors.return_location" class="mt-1" />
                    </div>

                    <div>
                        <Label for="discount_amount">{{ localize('Discount', 'ط§ظ„ط®طµظ…') }}</Label>
                        <Input id="discount_amount" v-model="form.discount_amount" type="number" step="0.01" min="0" />
                        <InputError :message="form.errors.discount_amount" class="mt-1" />
                    </div>

                    <div v-if="!isEdit">
                        <Label for="deposit_amount">{{ localize('Cash Deposit', 'ط§ظ„ط¹ط±ط¨ظˆظ† ط§ظ„ظ†ظ‚ط¯ظٹ') }}</Label>
                        <Input
                            id="deposit_amount"
                            v-model="form.deposit_amount"
                            type="number"
                            step="0.01"
                            min="0"
                            :placeholder="localize('Optional deposit amount', 'ظ…ط¨ظ„ط؛ ط§ظ„ط¹ط±ط¨ظˆظ† ط¥ظ† ظˆط¬ط¯')"
                        />
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ localize('A completed cash payment will be created automatically.', 'ط³ظٹطھظ… ط¥ظ†ط´ط§ط، ط¯ظپط¹ط© ظ†ظ‚ط¯ظٹط© ظ…ظƒطھظ…ظ„ط© طھظ„ظ‚ط§ط¦ظٹظ‹ط§.') }}
                        </p>
                        <InputError :message="form.errors.deposit_amount" class="mt-1" />
                    </div>

                    <div>
                        <Label for="status">{{ localize('Status', 'ط§ظ„ط­ط§ظ„ط©') }}</Label>
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-1 block w-full rounded-md border border-gray-300 py-2 pr-10 pl-3 text-base focus:border-blue-500 focus:ring-blue-500 focus:outline-none sm:text-sm"
                        >
                            <option v-for="s in statuses" :key="s.value" :value="s.value">
                                {{ s.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.status" class="mt-1" />
                    </div>

                    <div class="md:col-span-2">
                        <Label for="notes">{{ localize('Notes', 'ظ…ظ„ط§ط­ط¸ط§طھ') }}</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="4"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2"
                            :placeholder="localize('Internal notes...', 'ظ…ظ„ط§ط­ط¸ط§طھ ط¯ط§ط®ظ„ظٹط©...')"
                        ></textarea>
                        <InputError :message="form.errors.notes" class="mt-1" />
                    </div>

                    <div v-if="form.status === 'cancelled'" class="md:col-span-2">
                        <Label for="cancellation_reason">{{ localize('Cancellation Reason', 'ط³ط¨ط¨ ط§ظ„ط¥ظ„ط؛ط§ط،') }}</Label>
                        <textarea
                            id="cancellation_reason"
                            v-model="form.cancellation_reason"
                            rows="3"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2"
                            :placeholder="localize('Why was this reservation cancelled?', 'ظ„ظ…ط§ط°ط§ طھظ… ط¥ظ„ط؛ط§ط، ظ‡ط°ط§ ط§ظ„ط­ط¬ط²طں')"
                        ></textarea>
                        <InputError :message="form.errors.cancellation_reason" class="mt-1" />
                    </div>
                </div>

                <div v-if="isEdit && selectedCarDamageCases.length" class="rounded-md border p-4">
                    <div class="mb-2 text-sm font-medium">{{ localize('Current Car Damages', 'ط§ظ„ط£ط¶ط±ط§ط± ط§ظ„ط­ط§ظ„ظٹط© ظ„ظ„ط³ظٹط§ط±ط©') }}</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-muted-foreground">
                                    <th class="px-2 py-2">{{ localize('Zone', 'ط§ظ„ظ…ظ†ط·ظ‚ط©') }}</th>
                                    <th class="px-2 py-2">{{ localize('View', 'ط§ظ„ط¬ظ‡ط©') }}</th>
                                    <th class="px-2 py-2">{{ localize('Type', 'ط§ظ„ظ†ظˆط¹') }}</th>
                                    <th class="px-2 py-2">{{ localize('Severity', 'ط§ظ„ط¯ط±ط¬ط©') }}</th>
                                    <th class="px-2 py-2">{{ localize('Qty', 'ط§ظ„ظƒظ…ظٹط©') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="damage in selectedCarDamageCases" :key="damage.id" class="border-b">
                                    <td class="px-2 py-2">{{ damage.zone_label }}</td>
                                    <td class="px-2 py-2">{{ damage.view_side_label }}</td>
                                    <td class="px-2 py-2">{{ damage.damage_type_label }}</td>
                                    <td class="px-2 py-2">{{ damage.severity_label }}</td>
                                    <td class="px-2 py-2">{{ damage.quantity }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? localize('Saving...', 'ط¬ط§ط±ظچ ط§ظ„ط­ظپط¸...') : isEdit ? localize('Save Changes', 'ط­ظپط¸ ط§ظ„طھط؛ظٹظٹط±ط§طھ') : localize('Create Reservation', 'ط¥ظ†ط´ط§ط، ط­ط¬ط²') }}
                    </Button>
                    <Link v-if="subdomain" :href="index(subdomain).url">
                        <Button type="button" variant="outline">{{ localize('Cancel', 'ط¥ظ„ط؛ط§ط،') }}</Button>
                    </Link>
                </div>
            </form>

            <Dialog v-model:open="showCreateClientDialog">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{{ localize('Create Client', 'ط¥ظ†ط´ط§ط، ط¹ظ…ظٹظ„') }}</DialogTitle>
                        <DialogDescription>
                            {{ localize('Add a new client without leaving this reservation page.', 'ط£ط¶ظپ ط¹ظ…ظٹظ„ظ‹ط§ ط¬ط¯ظٹط¯ظ‹ط§ ط¨ط¯ظˆظ† ظ…ط؛ط§ط¯ط±ط© طµظپط­ط© ط§ظ„ط­ط¬ط².') }}
                        </DialogDescription>
                    </DialogHeader>

                    <form class="space-y-4" @submit.prevent="submitCreateClient">
                        <div class="space-y-2">
                            <Label for="client_name">{{ localize('Full Name', 'ط§ظ„ط§ط³ظ… ط§ظ„ظƒط§ظ…ظ„') }}</Label>
                            <Input id="client_name" v-model="clientForm.name" type="text" required />
                            <InputError :message="clientForm.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="client_email">{{ localize('Email', 'ط§ظ„ط¨ط±ظٹط¯ ط§ظ„ط¥ظ„ظƒطھط±ظˆظ†ظٹ') }}</Label>
                            <Input id="client_email" v-model="clientForm.email" type="email" required />
                            <InputError :message="clientForm.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="client_civil_number">{{ localize('Civil Number', 'ط§ظ„ط±ظ‚ظ… ط§ظ„ظ…ط¯ظ†ظٹ') }}</Label>
                            <Input id="client_civil_number" v-model="clientForm.civil_number" type="text" required />
                            <InputError :message="clientForm.errors.civil_number" />
                        </div>

                        <div class="space-y-2">
                            <Label for="client_password">{{ localize('Password', 'ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط±') }}</Label>
                            <Input id="client_password" v-model="clientForm.password" type="password" required />
                            <InputError :message="clientForm.errors.password" />
                        </div>

                        <div class="space-y-2">
                            <Label for="client_password_confirmation">{{ localize('Confirm Password', 'طھط£ظƒظٹط¯ ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط±') }}</Label>
                            <Input id="client_password_confirmation" v-model="clientForm.password_confirmation" type="password" required />
                            <InputError :message="clientForm.errors.password_confirmation" />
                        </div>

                        <DialogFooter class="gap-2">
                            <Button type="submit" :disabled="creatingClient">
                                {{ creatingClient ? localize('Creating...', 'ط¬ط§ط±ظٹ ط§ظ„ط¥ظ†ط´ط§ط،...') : localize('Create Client', 'ط¥ظ†ط´ط§ط، ط¹ظ…ظٹظ„') }}
                            </Button>
                            <Button type="button" variant="outline" @click="showCreateClientDialog = false">
                                {{ localize('Cancel', 'ط¥ظ„ط؛ط§ط،') }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </main>
    </AdminLayout>
</template>


