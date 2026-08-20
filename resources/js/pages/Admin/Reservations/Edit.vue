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
    is_locked?: boolean;
    clients: Array<{ id: number; name: string; email: string; outstanding_return_debt?: number }>;
    cars: Array<{
        id: number;
        label: string;
        license_plate: string;
        branch_name?: string | null;
        price_per_day: number;
        price_per_week?: number | null;
        price_per_month?: number | null;
    }>;
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
        allStatuses?: Array<{ value: string; label: string; color: string }>;
    };
}>();

const { t, locale } = useTrans();
const editTranslationRoot = 'dashboard.admin.reservations.edit';
const editTranslationKeyFor = (text: string) =>
    text
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 80);
const fallbackInterpolate = (text: string, params: Record<string, string | number> = {}) =>
    text.replace(/:([a-zA-Z0-9_]+)/g, (_match, key: string) => (params[key] !== undefined ? String(params[key]) : `:${key}`));
const tr = (key: string, fallback: string, params: Record<string, string | number> = {}) => {
    const fullKey = `${editTranslationRoot}.${key}`;
    const translated = t(fullKey, params);

    return translated === fullKey ? fallbackInterpolate(fallback, params) : translated;
};
const localize = (en: string, ar: string, params: Record<string, string | number> = {}) => {
    const key = editTranslationKeyFor(en);
    const fullKey = `${editTranslationRoot}.${key}`;
    const translated = t(fullKey, params);

    return translated === fullKey ? fallbackInterpolate(locale.value === 'ar' ? ar : en, params) : translated;
};

const statuses = computed(() => props.enums.statuses || []);
const allStatuses = computed(() => props.enums.allStatuses || props.enums.statuses || []);
const statusMetaMap = computed<Record<string, { value: string; label: string; color: string }>>(() =>
    Object.fromEntries((allStatuses.value || []).map((status) => [status.value, status])),
);
const isSystemManagedStatus = computed(() => form.status === 'completed_wait_contract');
const reservationStatusLabel = (status: { value: string; label: string }) => {
    const key = `dashboard.admin.reservation_statuses.${status.value}`;
    const translated = t(key);

    return translated === key ? status.label : translated;
};
const currentStatusLabel = computed(() => {
    const status = statusMetaMap.value[form.status];

    return status ? reservationStatusLabel(status) : form.status;
});
const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);
const tenantFeatureFlags = computed<Record<string, boolean>>(
    () => page.props.current_tenant?.subscription_plan?.feature_flags || {},
);
const reservationSettings = computed<Record<string, any> | null>(
    () => page.props.tenant_site_settings?.reservation_settings ?? null,
);
const returnTimePolicy = computed<Record<string, any>>(
    () => reservationSettings.value?.return_time_policy || { mode: 'fixed_time', fixed_time: '18:00' },
);
const hasFeature = (feature: string) => {
    const flags = tenantFeatureFlags.value || {};

    if (Object.keys(flags).length === 0) {
        return true;
    }

    return Boolean(flags[feature]);
};
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
        ? `${localize('Edit Reservation', 'تعديل الحجز')} ${props.reservation?.reservation_number || ''}`.trim()
        : localize('Create Reservation', 'إنشاء حجز'),
);
const isLocked = computed(() => Boolean(props.is_locked));
const formatMoney = (value: number): string => {
    if (!Number.isFinite(value)) {
        return '0.00';
    }

    return Math.max(0, value).toFixed(2);
};

const toNumber = (value: unknown): number => {
    const parsed = Number(value ?? 0);
    return Number.isFinite(parsed) ? parsed : 0;
};

const calculateDiscountAmount = (type: string, value: unknown, subtotal: number): number => {
    const discountValue = Math.max(0, toNumber(value));
    const cappedSubtotal = Math.max(0, subtotal);

    if (type === 'percentage') {
        return Math.min(cappedSubtotal, cappedSubtotal * (Math.min(discountValue, 100) / 100));
    }

    return Math.min(cappedSubtotal, discountValue);
};

const calculateTieredRentalSubtotal = (car: any, days: number): number => {
    let remainingDays = Math.max(1, days);
    const dailyRate = Math.max(0, toNumber(car?.price_per_day ?? props.reservation?.daily_rate ?? 0));
    const weeklyRate = Math.max(0, toNumber(car?.price_per_week));
    const monthlyRate = Math.max(0, toNumber(car?.price_per_month));
    let subtotal = 0;

    const months = Math.floor(remainingDays / 30);
    if (months > 0) {
        subtotal += months * (monthlyRate > 0 ? monthlyRate : dailyRate * 30);
        remainingDays -= months * 30;
    }

    const weeks = Math.floor(remainingDays / 7);
    if (weeks > 0) {
        subtotal += weeks * (weeklyRate > 0 ? weeklyRate : dailyRate * 7);
        remainingDays -= weeks * 7;
    }

    if (remainingDays > 0) {
        subtotal += remainingDays * dailyRate;
    }

    return subtotal;
};

const resolveReturnLocationFee = (location: string): string => {
    const selected = (reservationSettings.value?.pickup_return_locations ?? []).find((item: any) => {
        const name = String(item?.name ?? '').trim().toLowerCase();
        return name !== '' && name === String(location ?? '').trim().toLowerCase() && item?.is_active !== false;
    });

    if (!selected || selected.return_free) {
        return '0.00';
    }

    const fee = Number(selected.return_fee ?? 0);
    return formatMoney(Number.isFinite(fee) ? fee : 0);
};

const locationOptions = computed(() => {
    const configuredLocations = (reservationSettings.value?.pickup_return_locations ?? [])
        .filter((location: any) => location?.is_active !== false && String(location?.name ?? '').trim() !== '')
        .map((location: any) => String(location.name).trim());

    const baseOptions = [
        localize('Downtown Office', 'مكتب وسط المدينة'),
        localize('Airport Terminal 1', 'مطار - صالة 1'),
        localize('Airport Terminal 2', 'مطار - صالة 2'),
        localize('Central Station', 'المحطة المركزية'),
        localize('Mall Plaza', 'مول بلازا'),
        localize('Hotel District', 'منطقة الفنادق'),
        localize('Business District', 'المنطقة التجارية'),
    ];

    const currentValues = [
        props.reservation?.pickup_location,
        props.reservation?.return_location,
        form.pickup_location,
        form.return_location,
    ]
        .filter((value): value is string => Boolean(value && value.trim()))
        .map((value) => value.trim());

    const sourceOptions = configuredLocations.length > 0 ? configuredLocations : baseOptions;

    return Array.from(new Set([...currentValues, ...sourceOptions]));
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
    return_location_fee:
        props.reservation?.return_location_fee !== null &&
        props.reservation?.return_location_fee !== undefined
            ? String(props.reservation.return_location_fee)
            : props.reservation?.return_location
                ? resolveReturnLocationFee(props.reservation.return_location)
                : '',
    discount_type: props.reservation?.discount_type || 'fixed',
    discount_value: props.reservation?.discount_value ?? props.reservation?.discount_amount ?? 0,
    discount_amount: props.reservation?.discount_amount || 0,
    deposit_amount: 0,
    notes: props.reservation?.notes || '',
    status: props.reservation?.status || 'confirmed',
    cancellation_reason: props.reservation?.cancellation_reason || '',
});

const selectedClient = computed(() => {
    const selectedClientId = Number(form.user_id || 0);
    if (!selectedClientId) {
        return null;
    }

    return clients.value.find((client) => Number(client.id) === selectedClientId) ?? null;
});

const selectedClientOutstandingDebt = computed(() => {
    const debt = Number(selectedClient.value?.outstanding_return_debt ?? 0);
    return Number.isFinite(debt) && debt > 0 ? debt : 0;
});

const selectedClientDebtMessage = computed(() => {
    if (selectedClientOutstandingDebt.value <= 0) {
        return '';
    }

    return tr('debt_notice', 'This client has unpaid return charges (:amount). You can continue after manager confirmation.', {
        amount: formatMoney(selectedClientOutstandingDebt.value),
    });
});

const debtAcknowledged = ref(false);
const debtDialogMode = ref<'select' | 'submit'>('submit');
const isRevertingDebtClient = ref(false);
const showDebtConfirmDialog = ref(false);

const selectedCar = computed(() => {
    const selectedCarId = Number(form.car_id || props.reservation?.car?.id || 0);
    return props.cars.find((car) => Number(car.id) === selectedCarId) ?? props.reservation?.car ?? null;
});

const reservationDurationDays = computed(() => {
    if (!form.start_date || !form.end_date) {
        return 0;
    }

    const start = parseDate(form.start_date);
    const end = parseDate(form.end_date);
    const diff = Math.floor((end.getTime() - start.getTime()) / 86400000) + 1;

    return diff > 0 ? diff : 0;
});

const reservationSubtotalPreview = computed(() => {
    return calculateTieredRentalSubtotal(selectedCar.value, reservationDurationDays.value);
});

const discountAmountPreview = computed(() =>
    calculateDiscountAmount(String(form.discount_type || 'fixed'), form.discount_value, reservationSubtotalPreview.value),
);

watch(
    discountAmountPreview,
    (amount) => {
        form.discount_amount = Number(amount.toFixed(2));
    },
    { immediate: true },
);

const resolvedReturnTime = computed(() => {
    const mode = String(returnTimePolicy.value?.mode ?? 'fixed_time');
    if (mode === 'same_pickup') {
        return form.pickup_time || '09:00';
    }

    if (mode === 'fixed_time') {
        return String(returnTimePolicy.value?.fixed_time || '18:00');
    }

    return form.return_time || '18:00';
});

watch(
    () => form.return_location,
    (location) => {
        if (!location) {
            form.return_location_fee = '';
            return;
        }

        form.return_location_fee = resolveReturnLocationFee(location);
    },
);

watch(
    () => [form.pickup_time, returnTimePolicy.value?.mode, returnTimePolicy.value?.fixed_time],
    () => {
        const mode = String(returnTimePolicy.value?.mode ?? 'fixed_time');
        if (mode === 'same_pickup' || mode === 'fixed_time') {
            form.return_time = resolvedReturnTime.value;
        }
    },
    { immediate: true },
);

const reservationEndDateMin = computed(() => {
    if (!form.start_date) {
        return '';
    }

    return formatDate(addDays(parseDate(form.start_date), 1));
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
    phone: '',
    whatsapp: '',
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

    if (iso === form.start_date) {
        form.setError('end_date', localize('The end date must be after the start date.', 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية.'));
        return;
    }

    if (hasBlockedDateInRange(form.start_date, iso)) {
        form.setError('end_date', localize('The selected range includes unavailable days.', 'النطاق المحدد يحتوي على أيام غير متاحة.'));
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
        isInSelectedRange: boolean;
    }> = [];

    for (let cursor = start; cursor <= end; cursor = addDays(cursor, 1)) {
        const iso = formatDate(cursor);
        const isInSelectedRange = Boolean(
            form.start_date &&
            form.end_date &&
            iso > form.start_date &&
            iso < form.end_date,
        );

        days.push({
            iso,
            label: formatShortDate(iso),
            weekday: formatWeekday(iso),
            isPast: iso < availabilityCalendar.value.today,
            isBlocked: isBlockedDate(iso),
            isSelectedStart: form.start_date === iso,
            isSelectedEnd: form.end_date === iso,
            isInSelectedRange,
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
            throw new Error(data?.message || localize('Unable to load the car availability calendar.', 'تعذر تحميل تقويم توفر السيارة.'));
        }

        availabilityCalendar.value = data?.availabilityCalendar ?? null;
        availabilityWindowStart.value = availabilityCalendar.value?.window.starts_at || nextWindowStart || '';
    } catch (error: any) {
        availabilityCalendar.value = null;
        availabilityError.value = error?.message || localize('Unable to load the car availability calendar.', 'تعذر تحميل تقويم توفر السيارة.');
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
        payload.append('phone', clientForm.phone);
        payload.append('whatsapp', clientForm.whatsapp);
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

            throw new Error(data?.message || localize('Unable to create client.', 'تعذر إنشاء العميل.'));
        }

        const newClient = {
            id: Number(data.client.id),
            name: String(data.client.name || ''),
            email: String(data.client.email || ''),
            outstanding_return_debt: 0,
        };

        if (!clients.value.some((client) => Number(client.id) === newClient.id)) {
            clients.value = [...clients.value, newClient];
        }

        form.user_id = String(newClient.id);
        showCreateClientDialog.value = false;
        clientForm.reset();
    } catch (error: any) {
        clientForm.setError('name', error?.message || localize('Unable to create client.', 'تعذر إنشاء العميل.'));
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

watch(
    () => form.user_id,
    () => {
        if (isRevertingDebtClient.value) {
            isRevertingDebtClient.value = false;
            return;
        }

        const clientId = Number(form.user_id || 0);
        if (clientId <= 0) {
            debtAcknowledged.value = false;
            return;
        }

        const client = clients.value.find((item) => Number(item.id) === clientId);
        const debt = Number(client?.outstanding_return_debt ?? 0);

        if (Number.isFinite(debt) && debt > 0) {
            debtAcknowledged.value = false;
            debtDialogMode.value = 'select';
            showDebtConfirmDialog.value = true;
            return;
        }

        debtAcknowledged.value = false;
    },
);

watch(showDebtConfirmDialog, (open, wasOpen) => {
    if (wasOpen && !open && !debtAcknowledged.value && debtDialogMode.value === 'select' && form.user_id) {
        isRevertingDebtClient.value = true;
        form.user_id = '';
    }
});

function postReservation() {
    form
        .transform((data) => ({
            ...data,
            confirm_client_debt:
                debtAcknowledged.value && selectedClientOutstandingDebt.value > 0 ? 1 : 0,
        }))
        .post('/admin/reservations', {
            onFinish: () => {
                debtAcknowledged.value = false;
            },
        });
}

function submit() {
    if (!subdomain.value) return;
    if (isLocked.value) return;
    if (isEdit.value) {
        form.put(update([subdomain.value, props.reservation.id]).url);
        return;
    }

    if (selectedClientOutstandingDebt.value > 0 && !debtAcknowledged.value) {
        debtDialogMode.value = 'submit';
        showDebtConfirmDialog.value = true;
        return;
    }

    postReservation();
}

function confirmDebtAction() {
    debtAcknowledged.value = true;
    showDebtConfirmDialog.value = false;

    if (debtDialogMode.value === 'submit') {
        postReservation();
    }
}

function cancelDebtAction() {
    showDebtConfirmDialog.value = false;

    if (debtDialogMode.value === 'select' && form.user_id) {
        isRevertingDebtClient.value = true;
        form.user_id = '';
    }
}
</script>

<template>
    <Head :title="pageTitle" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{ isEdit ? localize('Edit Reservation', 'تعديل الحجز') : localize('Create Reservation', 'إنشاء حجز') }}
                </h1>
                <Link v-if="subdomain" :href="index(subdomain).url">
                    <Button variant="outline">{{ localize('Back', 'رجوع') }}</Button>
                </Link>
            </div>

            <div v-if="isEdit" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-md border p-4">
                    <div class="text-sm text-muted-foreground">{{ localize('Reservation #', 'رقم الحجز') }}</div>
                    <div class="font-medium">{{ reservation.reservation_number }}</div>
                </div>
                <div class="rounded-md border p-4">
                    <div class="text-sm text-muted-foreground">{{ localize('Client', 'العميل') }}</div>
                    <div class="font-medium">
                        {{ reservation.user?.name }} ({{ reservation.user?.email }})
                    </div>
                </div>
                <div class="rounded-md border p-4">
                    <div class="text-sm text-muted-foreground">{{ localize('Car', 'السيارة') }}</div>
                    <div class="font-medium">
                        {{
                            reservation.car
                                ? `${reservation.car.year} ${reservation.car.make} ${reservation.car.model}`
                                : '—'
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
                        <h3 class="text-2xl font-bold text-gray-900">{{ localize('Availability Calendar', 'تقويم التوفر') }}</h3>
                        <p class="text-sm text-gray-500">
                            {{ localize('Green days are free. Red days are already booked. Click a free day to fill rental dates.', 'الأيام الخضراء متاحة. الأيام الحمراء محجوزة. اضغط على يوم متاح لتعبئة التواريخ.') }}
                        </p>
                    </div>
                </div>

                <div class="mb-6 flex flex-wrap items-center gap-3 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
                        <span class="text-gray-600">{{ localize('Free', 'متاح') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-3 w-3 rounded-full bg-primary/30"></span>
                        <span class="text-gray-600">{{ localize('Booked', 'محجوز') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-3 w-3 rounded-full bg-primary"></span>
                        <span class="text-gray-600">{{ localize('Selected', 'محدد') }}</span>
                    </div>
                </div>

                <div v-if="!selectedCar" class="rounded-xl border border-dashed border-gray-200 bg-gray-50 p-6 text-sm text-gray-500">
                    {{ localize('Select a car first to load the availability calendar.', 'اختر السيارة أولًا لعرض تقويم التوفر.') }}
                </div>

                <div v-else-if="availabilityLoading" class="rounded-xl border border-gray-200 bg-gray-50 p-6 text-sm text-gray-500">
                    {{ localize('Loading availability calendar...', 'جاري تحميل تقويم التوفر...') }}
                </div>

                <div v-else-if="availabilityError" class="rounded-xl border border-red-200 bg-red-50 p-6 text-sm text-red-700">
                    {{ availabilityError }}
                </div>

                <div v-else-if="availabilityCalendar" class="space-y-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div class="flex items-center gap-2">
                            <Button variant="outline" @click="openAvailabilityWindow(availabilityCalendar.window.previous)">
                                {{ localize('Previous', 'السابق') }}
                            </Button>
                            <div class="min-w-40 text-center text-lg font-semibold text-gray-900">
                                {{ availabilityCalendar.window.label }}
                            </div>
                            <Button variant="outline" @click="openAvailabilityWindow(availabilityCalendar.window.next)">
                                {{ localize('Next', 'التالي') }}
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
                                'border-primary/25 bg-primary/10 text-primary': day.isBlocked && !day.isSelectedStart && !day.isSelectedEnd,
                                'border-primary/25 bg-primary/10 text-primary ring-1 ring-primary/10': day.isInSelectedRange && !day.isPast && !day.isBlocked && !day.isSelectedStart && !day.isSelectedEnd,
                                'border-emerald-200 bg-emerald-50 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-100': !day.isPast && !day.isBlocked && !day.isSelectedStart && !day.isSelectedEnd && !day.isInSelectedRange,
                                'border-primary/40 bg-primary text-primary-foreground shadow-sm': day.isSelectedStart || day.isSelectedEnd,
                            }"
                            :disabled="day.isPast || day.isBlocked"
                            @click="selectAvailableDate(day.iso)"
                        >
                            <div class="text-xs font-semibold uppercase tracking-wide opacity-80">
                                {{ day.weekday }}
                            </div>
                            <div class="mt-1 text-base font-semibold">{{ day.label }}</div>
                            <div class="mt-1 text-[11px]">
                                <span v-if="day.isSelectedStart || day.isSelectedEnd">{{ localize('Selected', 'محدد') }}</span>
                                <span v-else-if="day.isInSelectedRange">{{ localize('Selected', 'محدد') }}</span>
                                <span v-else-if="day.isBlocked">{{ localize('Booked', 'محجوز') }}</span>
                                <span v-else-if="day.isPast">{{ localize('Closed', 'مغلق') }}</span>
                                <span v-else>{{ localize('Free', 'متاح') }}</span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="isLocked" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                {{ localize('This reservation is locked because its return report is marked paid.', 'هذا الحجز مقفل لأن تقرير العودة عليه حالة مدفوعة.') }}
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div v-if="!isEdit && hasFeature('cash_payments')">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <Label for="user_id" class="mb-0">{{ localize('Client', 'العميل') }}</Label>
                            <Button type="button" variant="outline" size="sm" @click="openCreateClientDialog">
                                {{ localize('Create Client', 'إنشاء عميل') }}
                            </Button>
                        </div>
                        <SearchableSelect
                            id="user_id"
                            v-model="form.user_id"
                            :options="clientOptions"
                            :placeholder="localize('Select client', 'اختر العميل')"
                            :search-placeholder="localize('Search client...', 'ابحث عن العميل...')"
                            :empty-text="localize('No clients found.', 'لا يوجد عملاء.')"
                        />
                        <p
                            v-if="selectedClientOutstandingDebt > 0"
                            class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                        >
                            {{ selectedClientDebtMessage }}
                        </p>
                        <InputError :message="form.errors.user_id" class="mt-1" />
                    </div>

                    <div v-if="!isEdit">
                        <Label for="car_id">{{ localize('Car', 'السيارة') }}</Label>
                        <SearchableSelect
                            id="car_id"
                            v-model="form.car_id"
                            :options="carOptions"
                            :placeholder="localize('Select car', 'اختر السيارة')"
                            :search-placeholder="localize('Search car...', 'ابحث عن السيارة...')"
                            :empty-text="localize('No cars found.', 'لا توجد سيارات.')"
                        />
                        <InputError :message="form.errors.car_id" class="mt-1" />
                        <div v-if="selectedCarDamageCases.length" class="mt-3 rounded-md border p-3">
                            <div class="mb-2 text-sm font-medium">{{ localize('Current Car Damages', 'الأضرار الحالية للسيارة') }}</div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="border-b text-left text-muted-foreground">
                                            <th class="px-2 py-2">{{ localize('Zone', 'المنطقة') }}</th>
                                            <th class="px-2 py-2">{{ localize('View', 'الجهة') }}</th>
                                            <th class="px-2 py-2">{{ localize('Type', 'النوع') }}</th>
                                            <th class="px-2 py-2">{{ localize('Severity', 'الدرجة') }}</th>
                                            <th class="px-2 py-2">{{ localize('Qty', 'الكمية') }}</th>
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
                        <Label for="start_date">{{ localize('Start Date', 'تاريخ البداية') }}</Label>
                        <Input id="start_date" v-model="form.start_date" type="date" />
                        <InputError :message="form.errors.start_date" class="mt-1" />
                    </div>

                    <div>
                        <Label for="end_date">{{ localize('End Date', 'تاريخ النهاية') }}</Label>
                        <Input id="end_date" v-model="form.end_date" type="date" :min="reservationEndDateMin" />
                        <InputError :message="form.errors.end_date" class="mt-1" />
                    </div>

                    <div>
                        <Label for="pickup_time">{{ localize('Pickup Time', 'وقت الاستلام') }}</Label>
                        <Input id="pickup_time" v-model="form.pickup_time" type="time" />
                        <InputError :message="form.errors.pickup_time" class="mt-1" />
                    </div>

                    <div>
                        <Label for="return_time">{{ localize('Return Time', 'وقت الإرجاع') }}</Label>
                        <Input
                            id="return_time"
                            v-model="form.return_time"
                            type="time"
                            :disabled="['fixed_time', 'same_pickup'].includes(String(returnTimePolicy.mode || 'fixed_time'))"
                        />
                        <p
                            v-if="String(returnTimePolicy.mode || 'fixed_time') === 'fixed_time'"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            {{ localize('Return time is fixed by tenant settings.', 'وقت الإرجاع ثابت حسب إعدادات المستأجر.') }}
                        </p>
                        <p
                            v-else-if="String(returnTimePolicy.mode || 'fixed_time') === 'same_pickup'"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            {{ localize('Return time follows pickup time.', 'وقت الإرجاع يطابق وقت الاستلام.') }}
                        </p>
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
                        <Label for="return_location_fee">{{ localize('Return Location Fee', 'رسوم موقع الإرجاع') }}</Label>
                        <Input
                            id="return_location_fee"
                            v-model="form.return_location_fee"
                            type="number"
                            step="0.01"
                            min="0"
                        />
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ localize('Defaults from reservation settings and can be overridden for this reservation only.', 'القيمة الافتراضية من إعدادات الحجز ويمكن تعديلها لهذا الحجز فقط.') }}
                        </p>
                        <InputError :message="form.errors.return_location_fee" class="mt-1" />
                    </div>

                    <div>
                        <Label for="discount_type">{{ localize('Discount Type', 'نوع الخصم') }}</Label>
                        <select
                            id="discount_type"
                            v-model="form.discount_type"
                            class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm transition-all duration-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': form.errors.discount_type }"
                        >
                            <option value="fixed">{{ localize('Fixed Amount', 'مبلغ ثابت') }}</option>
                            <option value="percentage">{{ localize('Percentage', 'نسبة مئوية') }}</option>
                        </select>
                        <InputError :message="form.errors.discount_type" class="mt-1" />
                    </div>

                    <div>
                        <Label for="discount_value">
                            {{ form.discount_type === 'percentage' ? localize('Discount Percentage', 'نسبة الخصم') : localize('Discount Amount', 'مبلغ الخصم') }}
                        </Label>
                        <Input
                            id="discount_value"
                            v-model="form.discount_value"
                            type="number"
                            step="0.01"
                            min="0"
                            :max="form.discount_type === 'percentage' ? 100 : undefined"
                        />
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                tr('calculated_discount', 'Calculated discount: :amount', {
                                    amount: formatMoney(discountAmountPreview),
                                })
                            }}
                        </p>
                        <InputError :message="form.errors.discount_value" class="mt-1" />
                        <InputError :message="form.errors.discount_amount" class="mt-1" />
                    </div>

                    <div v-if="!isEdit">
                        <Label for="deposit_amount">{{ localize('Cash Deposit', 'العربون النقدي') }}</Label>
                        <Input
                            id="deposit_amount"
                            v-model="form.deposit_amount"
                            type="number"
                            step="0.01"
                            min="0"
                            :placeholder="localize('Optional deposit amount', 'مبلغ العربون إن وجد')"
                        />
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ localize('A completed cash payment will be created automatically.', 'سيتم إنشاء دفعة نقدية مكتملة تلقائيًا.') }}
                        </p>
                        <InputError :message="form.errors.deposit_amount" class="mt-1" />
                    </div>

                    <div>
                        <Label for="status">{{ localize('Status', 'الحالة') }}</Label>
                        <template v-if="!isSystemManagedStatus">
                            <select
                                id="status"
                                v-model="form.status"
                                class="mt-1 block w-full rounded-md border border-gray-300 py-2 pr-10 pl-3 text-base focus:border-blue-500 focus:ring-blue-500 focus:outline-none sm:text-sm"
                            >
                                <option v-for="s in statuses" :key="s.value" :value="s.value">
                                    {{ reservationStatusLabel(s) }}
                                </option>
                            </select>
                        </template>
                        <div
                            v-else
                            class="mt-1 rounded-md border border-dashed border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                        >
                            <div class="font-medium">{{ currentStatusLabel }}</div>
                            <div class="text-xs text-amber-700">
                                {{ localize('System-managed status. It will be updated automatically.', 'الحالة تُدار تلقائيًا ولا يمكن تعديلها يدويًا.') }}
                            </div>
                        </div>
                        <InputError :message="form.errors.status" class="mt-1" />
                    </div>

                    <div class="md:col-span-2">
                        <Label for="notes">{{ localize('Notes', 'ملاحظات') }}</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="4"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2"
                            :placeholder="localize('Internal notes...', 'ملاحظات داخلية...')"
                        ></textarea>
                        <InputError :message="form.errors.notes" class="mt-1" />
                    </div>

                    <div v-if="form.status === 'cancelled'" class="md:col-span-2">
                        <Label for="cancellation_reason">{{ localize('Cancellation Reason', 'سبب الإلغاء') }}</Label>
                        <textarea
                            id="cancellation_reason"
                            v-model="form.cancellation_reason"
                            rows="3"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2"
                            :placeholder="localize('Why was this reservation cancelled?', 'لماذا تم إلغاء هذا الحجز؟')"
                        ></textarea>
                        <InputError :message="form.errors.cancellation_reason" class="mt-1" />
                    </div>
                </div>

                <div v-if="isEdit && selectedCarDamageCases.length" class="rounded-md border p-4">
                    <div class="mb-2 text-sm font-medium">{{ localize('Current Car Damages', 'الأضرار الحالية للسيارة') }}</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-muted-foreground">
                                    <th class="px-2 py-2">{{ localize('Zone', 'المنطقة') }}</th>
                                    <th class="px-2 py-2">{{ localize('View', 'الجهة') }}</th>
                                    <th class="px-2 py-2">{{ localize('Type', 'النوع') }}</th>
                                    <th class="px-2 py-2">{{ localize('Severity', 'الدرجة') }}</th>
                                    <th class="px-2 py-2">{{ localize('Qty', 'الكمية') }}</th>
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
                    <Button type="submit" :disabled="form.processing || isLocked">
                        {{ form.processing ? localize('Saving...', 'جاري الحفظ...') : isEdit ? localize('Save Changes', 'حفظ التغييرات') : localize('Create Reservation', 'إنشاء حجز') }}
                    </Button>
                    <Link v-if="subdomain" :href="index(subdomain).url">
                        <Button type="button" variant="outline">{{ localize('Cancel', 'إلغاء') }}</Button>
                    </Link>
                </div>
            </form>

            <Dialog v-model:open="showCreateClientDialog">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{{ localize('Create Client', 'إنشاء عميل') }}</DialogTitle>
                        <DialogDescription>
                            {{ localize('Add a new client without leaving this reservation page.', 'أضف عميلًا جديدًا بدون مغادرة صفحة الحجز.') }}
                        </DialogDescription>
                    </DialogHeader>

                    <form class="space-y-4" @submit.prevent="submitCreateClient">
                        <div class="space-y-2">
                            <Label for="client_name">{{ localize('Full Name', 'الاسم الكامل') }}</Label>
                            <Input id="client_name" v-model="clientForm.name" type="text" required />
                            <InputError :message="clientForm.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="client_email">{{ localize('Email', 'البريد الإلكتروني') }}</Label>
                            <Input id="client_email" v-model="clientForm.email" type="email" required />
                            <InputError :message="clientForm.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="client_civil_number">{{ localize('Civil Number', 'الرقم المدني') }}</Label>
                            <Input id="client_civil_number" v-model="clientForm.civil_number" type="text" required />
                            <InputError :message="clientForm.errors.civil_number" />
                        </div>

                        <div class="space-y-2">
                            <Label for="client_phone">{{ localize('Phone Number', 'رقم الهاتف') }}</Label>
                            <Input id="client_phone" v-model="clientForm.phone" type="tel" required dir="ltr" class="text-left" />
                            <InputError :message="clientForm.errors.phone" />
                        </div>

                        <div class="space-y-2">
                            <Label for="client_whatsapp">{{ localize('WhatsApp Number (optional)', 'رقم الواتساب (اختياري)') }}</Label>
                            <Input id="client_whatsapp" v-model="clientForm.whatsapp" type="tel" dir="ltr" class="text-left" />
                            <InputError :message="clientForm.errors.whatsapp" />
                        </div>

                        <div class="space-y-2">
                            <Label for="client_password">{{ localize('Password', 'كلمة المرور') }}</Label>
                            <Input id="client_password" v-model="clientForm.password" type="password" required />
                            <InputError :message="clientForm.errors.password" />
                        </div>

                        <div class="space-y-2">
                            <Label for="client_password_confirmation">{{ localize('Confirm Password', 'تأكيد كلمة المرور') }}</Label>
                            <Input id="client_password_confirmation" v-model="clientForm.password_confirmation" type="password" required />
                            <InputError :message="clientForm.errors.password_confirmation" />
                        </div>

                        <DialogFooter class="gap-2">
                            <Button type="submit" :disabled="creatingClient">
                                {{ creatingClient ? localize('Creating...', 'جاري الإنشاء...') : localize('Create Client', 'إنشاء عميل') }}
                            </Button>
                            <Button type="button" variant="outline" @click="showCreateClientDialog = false">
                                {{ localize('Cancel', 'إلغاء') }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="showDebtConfirmDialog">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{{ localize('Client Has Outstanding Debt', 'العميل مديون') }}</DialogTitle>
                        <DialogDescription>
                            {{
                                debtDialogMode === 'submit'
                                    ? tr('debt_submit_question', 'This client has unpaid return charges totaling :amount. Do you want to create the reservation anyway?', {
                                          amount: formatMoney(selectedClientOutstandingDebt),
                                      })
                                    : tr('debt_continue_question', 'This client has unpaid return charges totaling :amount. Do you want to continue with this client?', {
                                          amount: formatMoney(selectedClientOutstandingDebt),
                                      })
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter class="gap-2">
                        <Button type="button" :disabled="form.processing" @click="confirmDebtAction">
                            {{
                                form.processing
                                    ? localize('Saving...', 'جاري الحفظ...')
                                    : debtDialogMode === 'submit'
                                      ? localize('Yes, Create Reservation', 'نعم، إنشاء الحجز')
                                      : localize('Yes, Continue', 'نعم، المتابعة')
                            }}
                        </Button>
                        <Button type="button" variant="outline" @click="cancelDebtAction">
                            {{
                                debtDialogMode === 'submit'
                                    ? localize('No, Cancel', 'لا، إلغاء')
                                    : localize('No, Choose Another Client', 'لا، اختر عميلاً آخر')
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </main>
    </AdminLayout>
</template>
