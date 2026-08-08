<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { computed } from 'vue';
import { index, edit, print } from '@/routes/admin/reservations';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
  reservation: any
  statusMeta: Array<{ value: string; label: string; color: string }>
  paymentStatusMeta: Array<{ value: string; label: string }>
  currency: { symbol: string; code: string }
}>()
const page = usePage<any>()
const { t } = useTrans()
const subdomain = computed(() => page.props.current_tenant?.slug)
const showTranslationRoot = 'dashboard.admin.reservations.show'
const fallbackInterpolate = (text: string, params: Record<string, string | number> = {}) =>
  text.replace(/:([a-zA-Z0-9_]+)/g, (_match, key: string) => (params[key] !== undefined ? String(params[key]) : `:${key}`))
const tr = (key: string, fallback: string, params: Record<string, string | number> = {}) => {
  const fullKey = `${showTranslationRoot}.${key}`
  const translated = t(fullKey, params)

  return translated === fullKey ? fallbackInterpolate(fallback, params) : translated
}
const tenantFeatureFlags = computed<Record<string, boolean>>(
  () => page.props.current_tenant?.subscription_plan?.feature_flags || {},
)
const hasFeature = (feature: string) => {
  const flags = tenantFeatureFlags.value || {}
  if (Object.keys(flags).length === 0) {
    return true
  }
  return Boolean(flags[feature])
}
const reservation = computed(() => props.reservation)
const pricingLabel = computed(() => {
  const raw = reservation.value?.pricing_label
  const map: Record<string, string> = {
    'Daily Rate': tr('fields.daily_rate', 'Daily Rate'),
    'Weekly Rate': tr('fields.weekly_rate', 'Weekly Rate'),
    'Monthly Rate': tr('fields.monthly_rate', 'Monthly Rate'),
  }
  return map[raw] ?? raw || tr('fields.daily_rate', 'Daily Rate')
})
const isLocked = computed(() => Boolean(reservation.value?.is_locked))
const canCreateContract = computed(() => Boolean(reservation.value?.can_create_contract))
const contractBlockMessage = computed(() => reservation.value?.contract_block_message || '')
const canCollectFinalCash = computed(
  () => hasFeature('cash_payments') && Boolean(reservation.value?.can_collect_final_cash) && !isLocked.value,
)

const statusMap = computed(() => {
  const map: Record<string, { label: string; color: string }> = {}
  for (const s of props.statusMeta || []) map[s.value] = { label: s.label, color: s.color }
  return map
})

function getStatusStyle(status: string) {
  const meta = statusMap.value[status]
  if (!meta) return { bg: 'rgba(107,114,128,0.1)', text: '#6B7280', dot: '#6B7280', label: status }
  const statusKey = String(status || '').toLowerCase().trim().replace(/\s+/g, '_')
  const statusTranslationKey = `dashboard.admin.reservation_statuses.${statusKey}`
  const translatedLabel = t(statusTranslationKey)
  const hex = meta.color.replace('#', '')
  const r = parseInt(hex.slice(0, 2), 16)
  const g = parseInt(hex.slice(2, 4), 16)
  const b = parseInt(hex.slice(4, 6), 16)
  return {
    bg: `rgba(${r}, ${g}, ${b}, 0.1)`,
    text: meta.color,
    dot: meta.color,
    label: translatedLabel === statusTranslationKey ? meta.label : translatedLabel,
  }
}

function fmtDate(d?: string) {
  return d ? new Date(d).toLocaleDateString() : '-'
}
function fmtMoney(n?: number | string) {
  const v = Number(n ?? 0)
  return `${props.currency.symbol}${v.toFixed(2)}`
}
function formatDays(days?: number | string) {
  return tr('duration_days', ':count days', { count: Number(days ?? 0) })
}
function translatedEnum(root: string, value?: string) {
  const key = String(value || '').toLowerCase().trim().replace(/\s+/g, '_')
  const fullKey = `${showTranslationRoot}.${root}.${key}`
  const translated = t(fullKey)

  return translated === fullKey ? (value || '-') : translated
}

function collectFinalCash() {
  if (!subdomain.value || !reservation.value?.id) {
    return
  }

  if (!window.confirm(tr('confirm_collect_final_cash', 'Record the remaining balance as a cash payment and complete this reservation?'))) {
    return
  }

  router.post(`/admin/reservations/${reservation.value.id}/cash-payment`)
}
</script>

<template>
  <Head :title="tr('head_title', 'Reservation :number', { number: reservation?.reservation_number || '' })" />
  <AdminLayout>
    <main class="flex-1 p-8 space-y-6">
        <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">
          {{ tr('title', 'Reservation :number', { number: reservation?.reservation_number || '' }) }}
        </h1>
        <div class="flex gap-2">
          <Link v-if="reservation.contract?.id" :href="`/admin/contracts/${reservation.contract.id}`">
            <Button variant="outline">{{ tr('actions.show_contract', 'Show Contract') }}</Button>
          </Link>
          <Link v-else-if="canCreateContract" :href="`/admin/contracts/create?reservation_id=${reservation.id}`">
            <Button variant="outline">{{ tr('actions.create_contract', 'Create Contract') }}</Button>
          </Link>
          <Button v-else variant="outline" disabled>{{ tr('actions.create_contract', 'Create Contract') }}</Button>
          <Link v-if="subdomain" :href="index(subdomain).url">
            <Button variant="outline">{{ tr('actions.back', 'Back') }}</Button>
          </Link>
          <Link v-if="subdomain && !isLocked" :href="edit([subdomain, reservation.id]).url">
            <Button variant="outline">{{ tr('actions.edit', 'Edit') }}</Button>
          </Link>
          <a v-if="subdomain" :href="print([subdomain, reservation.id]).url" target="_blank" rel="noopener">
            <Button variant="secondary">{{ tr('actions.print', 'Print') }}</Button>
          </a>
        </div>
      </div>

      <div v-if="isLocked" class="rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
        {{ tr('locked_message', 'This reservation is locked because its return report is marked paid.') }}
      </div>

      <div v-if="!reservation.contract?.id && contractBlockMessage" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        {{ contractBlockMessage }}
      </div>

      <!-- Header ribbon -->
      <div class="rounded-md border p-4 flex items-center justify-between">
        <div class="space-y-1">
          <div class="text-sm text-muted-foreground">{{ tr('fields.status', 'Status') }}</div>
          <div>
            <span
              class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-medium"
              :style="{
                backgroundColor: getStatusStyle(reservation.status).bg,
                color: getStatusStyle(reservation.status).text,
              }"
            >
              <span class="size-2 rounded-full" :style="{ backgroundColor: getStatusStyle(reservation.status).dot }" />
              {{ getStatusStyle(reservation.status).label }}
            </span>
          </div>
        </div>
        <div class="text-right">
          <div class="text-sm text-muted-foreground">{{ tr('fields.total', 'Total') }}</div>
          <div class="text-xl font-semibold">{{ fmtMoney(reservation.total_amount) }}</div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <!-- Client -->
        <div class="rounded-md border">
          <div class="border-b px-4 py-3 font-medium">{{ tr('sections.client', 'Client') }}</div>
          <div class="p-4 space-y-1">
            <div class="text-sm">{{ tr('fields.name', 'Name') }}</div>
            <div class="font-medium">{{ reservation.user?.name || '—' }}</div>
            <div class="text-sm mt-3">{{ tr('fields.email', 'Email') }}</div>
            <div class="font-medium">{{ reservation.user?.email || '—' }}</div>
          </div>
        </div>

        <!-- Car -->
        <div class="rounded-md border">
          <div class="border-b px-4 py-3 font-medium">{{ tr('sections.car', 'Car') }}</div>
          <div class="p-4 space-y-1">
            <div class="text-sm">{{ tr('fields.car', 'Car') }}</div>
            <div class="font-medium">
              {{ reservation.car ? `${reservation.car.year} ${reservation.car.make} ${reservation.car.model}` : '—' }}
            </div>
            <div class="text-sm mt-3">{{ tr('fields.plate', 'Plate') }}</div>
            <div class="font-medium">{{ reservation.car?.license_plate || '—' }}</div>
          </div>
        </div>

        <!-- Reservation Details -->
        <div class="rounded-md border md:col-span-2">
          <div class="border-b px-4 py-3 font-medium">{{ tr('sections.reservation_details', 'Reservation Details') }}</div>
          <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <div class="text-sm text-muted-foreground">{{ tr('fields.start_date', 'Start Date') }}</div>
              <div class="font-medium">{{ fmtDate(reservation.start_date) }} {{ reservation.pickup_time }}</div>
            </div>
            <div>
              <div class="text-sm text-muted-foreground">{{ tr('fields.end_date', 'End Date') }}</div>
              <div class="font-medium">{{ fmtDate(reservation.end_date) }} {{ reservation.return_time }}</div>
            </div>
            <div>
              <div class="text-sm text-muted-foreground">{{ tr('fields.duration', 'Duration') }}</div>
              <div class="font-medium">{{ formatDays(reservation.total_days) }}</div>
            </div>
            <div>
              <div class="text-sm text-muted-foreground">{{ tr('fields.pickup_location', 'Pickup Location') }}</div>
              <div class="font-medium">{{ reservation.pickup_location || '—' }}</div>
            </div>
            <div>
              <div class="text-sm text-muted-foreground">{{ tr('fields.return_location', 'Return Location') }}</div>
              <div class="font-medium">{{ reservation.return_location || '—' }}</div>
            </div>
            <div v-if="reservation.status === 'cancelled'">
              <div class="text-sm text-muted-foreground">{{ tr('fields.cancelled_at', 'Cancelled At') }}</div>
              <div class="font-medium">{{ reservation.cancelled_at ? new Date(reservation.cancelled_at).toLocaleString() : '—' }}</div>
              <div class="text-sm text-muted-foreground mt-2">{{ tr('fields.reason', 'Reason') }}</div>
              <div class="font-medium">{{ reservation.cancellation_reason || '—' }}</div>
            </div>
          </div>
        </div>

        <!-- Amounts -->
        <div class="rounded-md border">
          <div class="border-b px-4 py-3 font-medium">{{ tr('sections.amounts', 'Amounts') }}</div>
          <div class="p-4 space-y-2">
            <div class="flex items-center justify-between">
              <div class="text-sm">{{ pricingLabel }}</div>
              <div class="font-medium">{{ fmtMoney(reservation.pricing_rate ?? reservation.daily_rate) }}</div>
            </div>
            <div class="flex items-center justify-between">
              <div class="text-sm">{{ tr('fields.subtotal', 'Subtotal') }}</div>
              <div class="font-medium">{{ fmtMoney(reservation.subtotal) }}</div>
            </div>
            <div class="flex items-center justify-between">
              <div class="text-sm">{{ tr('fields.tax', 'Tax') }}</div>
              <div class="font-medium">{{ fmtMoney(reservation.tax_amount) }}</div>
            </div>
            <div class="flex items-center justify-between">
              <div class="text-sm">{{ tr('fields.discount', 'Discount') }}</div>
              <div class="font-medium">-{{ fmtMoney(reservation.discount_amount) }}</div>
            </div>
            <div class="flex items-center justify-between">
              <div class="text-sm">{{ tr('fields.amount_paid', 'Amount Paid') }}</div>
              <div class="font-medium">{{ fmtMoney(reservation.amount_paid) }}</div>
            </div>
            <div class="flex items-center justify-between">
              <div class="text-sm">{{ tr('fields.balance_due', 'Balance Due') }}</div>
              <div class="font-medium">{{ fmtMoney(reservation.balance_due) }}</div>
            </div>
            <div class="border-t pt-2 flex items-center justify-between">
              <div class="text-sm">{{ tr('fields.total', 'Total') }}</div>
              <div class="text-lg font-semibold">{{ fmtMoney(reservation.total_amount) }}</div>
            </div>
            <div v-if="canCollectFinalCash" class="pt-2">
              <Button class="w-full" @click="collectFinalCash">{{ tr('actions.collect_final_cash', 'Collect Final Cash') }}</Button>
            </div>
          </div>
        </div>

        <!-- Payments -->
        <div class="rounded-md border md:col-span-2">
          <div class="border-b px-4 py-3 font-medium">{{ tr('sections.payments', 'Payments') }}</div>
          <div class="p-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ tr('fields.amount', 'Amount') }}</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ tr('fields.method', 'Method') }}</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ tr('fields.status', 'Status') }}</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ tr('fields.processed', 'Processed') }}</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="p in (reservation.payments || [])" :key="p.id">
                  <td class="px-4 py-2">{{ p.payment_number }}</td>
                  <td class="px-4 py-2">{{ fmtMoney(p.amount) }}</td>
                  <td class="px-4 py-2">{{ translatedEnum('payment_methods', p.payment_method) }}</td>
                  <td class="px-4 py-2">{{ translatedEnum('payment_statuses', p.status) }}</td>
                  <td class="px-4 py-2">{{ p.processed_at ? new Date(p.processed_at).toLocaleString() : '—' }}</td>
                </tr>
                <tr v-if="!reservation.payments || reservation.payments.length === 0">
                  <td colspan="5" class="px-4 py-4 text-center text-gray-500">{{ tr('empty.payments', 'No payments recorded.') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </AdminLayout>
</template>
