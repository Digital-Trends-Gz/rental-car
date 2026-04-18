<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { computed } from 'vue';
import { useTrans } from '@/composables/useTrans';

type ReservationItem = {
  id: number
  reservation_number: string
  status: string
  status_label: string
  client_name?: string | null
  start_date: string
  end_date: string
  pickup_time?: string | null
  return_time?: string | null
}

const props = defineProps<{
  car: {
    id: number
    make: string
    model: string
    year: number
    license_plate: string
    branch_name?: string | null
  }
  month: {
    value: string
    label: string
    starts_at: string
    ends_at: string
    grid_starts_at: string
    grid_ends_at: string
    previous: string
    next: string
  }
  view: {
    value: 'month' | 'next_30_days' | 'booked_only'
    window_starts_at: string
    window_ends_at: string
  }
  reservations: ReservationItem[]
}>();

const page = usePage<any>();
const { locale } = useTrans();
const subdomain = computed(() => page.props.current_tenant?.slug);
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const weekdayLabels = computed(() => {
  if (locale.value === 'ar') {
    return ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
  }
  return ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
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

function formatLongDate(value: string): string {
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(parseDate(value));
}

const calendarDays = computed(() => {
  const days: Array<{
    iso: string
    dayNumber: number
    inCurrentMonth: boolean
    reservations: ReservationItem[]
  }> = [];

  const start = parseDate(props.month.grid_starts_at);
  const end = parseDate(props.month.grid_ends_at);
  const currentMonthPrefix = `${props.month.value}-`;

  for (let cursor = start; cursor <= end; cursor = addDays(cursor, 1)) {
    const iso = formatDate(cursor);
    days.push({
      iso,
      dayNumber: cursor.getDate(),
      inCurrentMonth: iso.startsWith(currentMonthPrefix),
      reservations: props.reservations.filter((reservation) => reservation.start_date <= iso && reservation.end_date >= iso),
    });
  }

  return days;
});

const nextThirtyDays = computed(() => {
  const days: Array<{
    iso: string
    label: string
    reservations: ReservationItem[]
  }> = [];

  const start = parseDate(props.view.window_starts_at);
  const end = parseDate(props.view.window_ends_at);

  for (let cursor = start; cursor <= end; cursor = addDays(cursor, 1)) {
    const iso = formatDate(cursor);
    days.push({
      iso,
      label: formatLongDate(iso),
      reservations: props.reservations.filter((reservation) => reservation.start_date <= iso && reservation.end_date >= iso),
    });
  }

  return days;
});

const bookedReservations = computed(() =>
  [...props.reservations].sort((first, second) => {
    const firstValue = `${first.start_date} ${first.pickup_time ?? '00:00'}`
    const secondValue = `${second.start_date} ${second.pickup_time ?? '00:00'}`
    return firstValue.localeCompare(secondValue)
  }),
);

const currentViewLabel = computed(() => {
  if (props.view.value === 'next_30_days') return localize('Next 30 Days', 'الـ 30 يوماً القادمة');
  if (props.view.value === 'booked_only') return localize(`Booked Days in ${props.month.label}`, `الأيام المحجوزة في ${props.month.label}`);
  return props.month.label;
});

function statusClass(status: string): string {
  if (status === 'active') return 'bg-amber-100 text-amber-800 border-amber-200';
  if (status === 'confirmed') return 'bg-emerald-100 text-emerald-800 border-emerald-200';
  if (status === 'pending') return 'bg-blue-100 text-blue-800 border-blue-200';
  if (status === 'completed') return 'bg-slate-100 text-slate-700 border-slate-200';
  if (status === 'cancelled') return 'bg-red-100 text-red-700 border-red-200';
  return 'bg-gray-100 text-gray-700 border-gray-200';
}

function openCalendar(filters: { month?: string; view?: 'month' | 'next_30_days' | 'booked_only' }) {
  router.get(`/admin/cars/${props.car.id}/calendar`, {
    month: filters.month ?? props.month.value,
    view: filters.view ?? props.view.value,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
}
</script>

<template>
  <Head :title="`Car Calendar - ${car.year} ${car.make} ${car.model}`" />

  <AdminLayout>
    <main class="flex-1 space-y-6 p-8">
      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold">{{ localize('Car Reservation Calendar', 'تقويم حجوزات السيارة') }}</h1>
          <p class="text-sm text-muted-foreground">
            {{ car.year }} {{ car.make }} {{ car.model }} | {{ car.license_plate }}
            <span v-if="car.branch_name">| {{ car.branch_name }}</span>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <Link v-if="subdomain" href="/admin/cars">
            <Button variant="outline">{{ localize('Back to Cars', 'العودة إلى السيارات') }}</Button>
          </Link>
        </div>
      </div>

      <div class="space-y-4 rounded-lg border bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center gap-2">
          <Button
            :variant="view.value === 'month' ? 'default' : 'outline'"
            @click="openCalendar({ view: 'month' })"
          >
            {{ localize('Month', 'الشهر') }}
          </Button>
          <Button
            :variant="view.value === 'next_30_days' ? 'default' : 'outline'"
            @click="openCalendar({ view: 'next_30_days' })"
          >
            {{ localize('Next 30 Days', 'الـ 30 يوماً القادمة') }}
          </Button>
          <Button
            :variant="view.value === 'booked_only' ? 'default' : 'outline'"
            @click="openCalendar({ view: 'booked_only' })"
          >
            {{ localize('Booked Only', 'المحجوز فقط') }}
          </Button>
        </div>

        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div class="flex items-center gap-2">
            <Button variant="outline" @click="openCalendar({ month: month.previous })">{{ localize('Previous', 'السابق') }}</Button>
            <div class="min-w-40 text-center text-lg font-semibold">{{ currentViewLabel }}</div>
            <Button variant="outline" @click="openCalendar({ month: month.next })">{{ localize('Next', 'التالي') }}</Button>
          </div>

          <div class="flex items-center gap-2">
            <input
              type="month"
              :value="month.value"
              class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
              @change="openCalendar({ month: ($event.target as HTMLInputElement).value })"
            >
          </div>
        </div>
      </div>

      <div v-if="view.value === 'month'" class="space-y-6">
        <div class="grid grid-cols-7 gap-3">
          <div
            v-for="label in weekdayLabels"
            :key="label"
            class="rounded-md bg-muted px-3 py-2 text-center text-sm font-medium text-muted-foreground"
          >
            {{ label }}
          </div>

          <div
            v-for="day in calendarDays"
            :key="day.iso"
            class="min-h-36 rounded-lg border p-3"
            :class="day.inCurrentMonth ? 'bg-white' : 'bg-slate-50 text-slate-400'"
          >
            <div class="mb-2 flex items-center justify-between">
              <span class="text-sm font-semibold">{{ day.dayNumber }}</span>
              <span
                v-if="day.reservations.length"
                class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
              >
                {{ day.reservations.length }} {{ localize('reserved', 'محجوز') }}
              </span>
            </div>

            <div class="space-y-2">
              <div
                v-for="reservation in day.reservations"
                :key="`${day.iso}-${reservation.id}`"
                class="rounded-md border px-2 py-1 text-xs"
                :class="statusClass(reservation.status)"
              >
                <div class="font-semibold">{{ reservation.reservation_number }}</div>
                <div class="truncate">{{ reservation.client_name || localize('Client', 'العميل') }}</div>
                <div>{{ reservation.status_label }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="view.value === 'next_30_days'" class="rounded-lg border bg-white p-4 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold">{{ localize('Operational Window', 'النافذة التشغيلية') }}</h2>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
          <div
            v-for="day in nextThirtyDays"
            :key="day.iso"
            class="rounded-lg border p-4"
          >
            <div class="mb-3 flex items-center justify-between">
              <div class="font-semibold">{{ day.label }}</div>
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="day.reservations.length ? 'bg-primary/10 text-primary' : 'bg-slate-100 text-slate-600'"
              >
                {{ day.reservations.length ? `${day.reservations.length} ${localize('booked', 'محجوز')}` : localize('Available', 'متاح') }}
              </span>
            </div>

            <div v-if="day.reservations.length" class="space-y-2">
              <div
                v-for="reservation in day.reservations"
                :key="`${day.iso}-${reservation.id}`"
                class="rounded-md border px-3 py-2 text-sm"
                :class="statusClass(reservation.status)"
              >
                <div class="font-semibold">{{ reservation.reservation_number }}</div>
                <div>{{ reservation.client_name || localize('Client', 'العميل') }}</div>
                <div class="text-xs">{{ reservation.status_label }}</div>
              </div>
            </div>

            <div v-else class="text-sm text-muted-foreground">
              {{ localize('No reservations on this day.', 'لا توجد حجوزات في هذا اليوم.') }}
            </div>
          </div>
        </div>
      </div>

      <div v-else class="rounded-lg border bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-lg font-semibold">{{ localize('Booked Reservations in', 'الحجوزات المحجوزة في') }} {{ month.label }}</h2>

        <div v-if="bookedReservations.length === 0" class="text-sm text-muted-foreground">
          {{ localize('No reservations for this car in the selected month.', 'لا توجد حجوزات لهذه السيارة في الشهر المحدد.') }}
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="reservation in bookedReservations"
            :key="reservation.id"
            class="flex flex-col gap-2 rounded-md border p-3 md:flex-row md:items-center md:justify-between"
          >
            <div>
              <div class="font-medium">{{ reservation.reservation_number }}</div>
              <div class="text-sm text-muted-foreground">
                {{ reservation.client_name || localize('Client', 'العميل') }} | {{ reservation.start_date }} {{ reservation.pickup_time || '' }} - {{ reservation.end_date }} {{ reservation.return_time || '' }}
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span class="rounded-full border px-2 py-1 text-xs font-medium" :class="statusClass(reservation.status)">
                {{ reservation.status_label }}
              </span>
              <Link v-if="subdomain" :href="`/admin/reservations/${reservation.id}`">
                <Button variant="outline" size="sm">{{ localize('Open Reservation', 'فتح الحجز') }}</Button>
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div v-if="view.value !== 'booked_only'" class="rounded-lg border bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-lg font-semibold">{{ localize('Reservations in', 'الحجوزات في') }} {{ currentViewLabel }}</h2>

        <div v-if="bookedReservations.length === 0" class="text-sm text-muted-foreground">
          {{ localize('No reservations for this car in the selected range.', 'لا توجد حجوزات لهذه السيارة في النطاق المحدد.') }}
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="reservation in bookedReservations"
            :key="reservation.id"
            class="flex flex-col gap-2 rounded-md border p-3 md:flex-row md:items-center md:justify-between"
          >
            <div>
              <div class="font-medium">{{ reservation.reservation_number }}</div>
              <div class="text-sm text-muted-foreground">
                {{ reservation.client_name || localize('Client', 'العميل') }} | {{ reservation.start_date }} {{ reservation.pickup_time || '' }} - {{ reservation.end_date }} {{ reservation.return_time || '' }}
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span class="rounded-full border px-2 py-1 text-xs font-medium" :class="statusClass(reservation.status)">
                {{ reservation.status_label }}
              </span>
              <Link v-if="subdomain" :href="`/admin/reservations/${reservation.id}`">
                <Button variant="outline" size="sm">{{ localize('Open Reservation', 'فتح الحجز') }}</Button>
              </Link>
            </div>
          </div>
        </div>
      </div>
    </main>
  </AdminLayout>
</template>
