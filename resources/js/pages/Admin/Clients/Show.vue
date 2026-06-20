<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { AlertCircle } from 'lucide-vue-next';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { index } from '@/routes/admin/clients';
import { suspend } from '@/routes/admin/clients';
import { activate } from '@/routes/admin/clients';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
  client: { id: number; name: string; email: string; is_active: boolean; created_at?: string; status?: string; status_label?: string };
  clientStatus?: {
    overall_status: 'good' | 'info' | 'warning' | 'danger';
    overall_label: string;
    can_book: boolean;
    flags_count: number;
    blocking_flags: string[];
    flags: Array<{
      id?: number;
      type: string;
      severity: 'info' | 'warning' | 'danger';
      label: string;
      description: string;
      source: string;
      blocks_booking: boolean;
    }>;
  };
  stats: { total_reservations: number; total_payments: number; total_spent: number; total_documents?: number };
  reservations: {
    data: Array<{
      id: number;
      reservation_number: string;
      start_date: string;
      end_date: string;
      total_days?: number;
      total_amount: number | string;
      status: string;
      car?: { year: number; make: string; model: string; license_plate: string } | null;
    }>;
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
  payments: {
    data: Array<{
      id: number;
      payment_number: string;
      amount: number | string;
      currency?: string;
      payment_method: string;
      status: string;
      processed_at?: string | null;
      reservation?: { id: number; reservation_number: string } | null;
    }>;
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
  notes?: Array<{
    id: number;
    note: string;
    created_at?: string | null;
    creator?: { id: number; name: string } | null;
  }>;
  currency: { symbol: string; code: string };
  actions?: { documents?: string; store_note?: string };
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const showSuspendDialog = ref(false);
const processingSuspend = ref(false);

const showActivateDialog = ref(false);
const processingActivate = ref(false);
const showNoteDialog = ref(false);
const noteForm = useForm({
  note: '',
});

function fmtMoney(n?: number | string) {
  const v = Number(n ?? 0);
  return `${props.currency.symbol}${v.toFixed(2)}`;
}

function suspendClient() {
  processingSuspend.value = true;
  router.patch(suspend(props.client.id), {}, {
    preserveScroll: true,
    onFinish: () => {
      processingSuspend.value = false;
    },
    onSuccess: () => {
      showSuspendDialog.value = false;
    },
  });
}

function activateClient() {
  processingActivate.value = true;
  router.patch(activate(props.client.id), {}, {
    preserveScroll: true,
    onFinish: () => {
      processingActivate.value = false;
    },
    onSuccess: () => {
      showActivateDialog.value = false;
    },
  });
}

function submitClientNote() {
  if (!props.actions?.store_note) {
    return;
  }

  noteForm.post(props.actions.store_note, {
    preserveScroll: true,
    onSuccess: () => {
      noteForm.reset();
      showNoteDialog.value = false;
    },
  });
}

const statusStyle = computed(() => {
  const status = props.clientStatus?.overall_status || (props.client.is_active ? 'good' : 'danger');
  const palette: Record<string, string> = {
    good: '#10B981',
    info: '#3B82F6',
    warning: '#F59E0B',
    danger: '#EF4444',
  };
  const hex = palette[status] || '#6B7280';
  const toRgb = (h: string) => [parseInt(h.slice(1, 3), 16), parseInt(h.slice(3, 5), 16), parseInt(h.slice(5, 7), 16)];
  const [r, g, b] = toRgb(hex);
  return {
    bg: `rgba(${r}, ${g}, ${b}, 0.1)`,
    dot: hex,
    text: hex,
    label: props.clientStatus?.overall_label || (props.client.is_active ? localize('Active', 'نشط') : localize('Suspended', 'موقوف')),
  };
});

const flagStyle = (severity: string) => {
  const colors: Record<string, string> = {
    danger: 'border-red-200 bg-red-50 text-red-700',
    warning: 'border-amber-200 bg-amber-50 text-amber-700',
    info: 'border-blue-200 bg-blue-50 text-blue-700',
  };

  return colors[severity] || 'border-gray-200 bg-gray-50 text-gray-700';
};
</script>

<template>
  <Head :title="localize(`Client ${client.name}`, `العميل ${client.name}`)" />
  <AdminLayout>
    <main class="flex-1 space-y-6 p-8">
      <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-2">
          <div>
            <h1 class="text-2xl font-semibold">{{ client.name }}</h1>
            <div class="text-sm text-muted-foreground">{{ client.email }}</div>
          </div>
          <span
            class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-medium"
            :style="{ backgroundColor: statusStyle.bg, color: statusStyle.text }"
          >
            <span class="size-2 rounded-full" :style="{ backgroundColor: statusStyle.dot }" />
            {{ statusStyle.label }}
          </span>
        </div>
        <div class="flex items-center gap-2">
          <Button variant="outline" @click="showNoteDialog = true">
            {{ localize('Add Note', 'إضافة ملاحظة') }}
          </Button>
          <Button v-if="client.is_active" variant="destructive" @click="showSuspendDialog = true">
            {{ localize('Suspend User', 'إيقاف المستخدم') }}
          </Button>
          <Button v-else @click="showActivateDialog = true">
            {{ localize('Activate User', 'تفعيل المستخدم') }}
          </Button>
          <Link :href="index()">
            <Button variant="outline">{{ localize('Back', 'رجوع') }}</Button>
          </Link>
        </div>
      </div>

      <div class="rounded-md border p-4">
        <div class="flex items-center justify-between gap-4">
          <div>
            <div class="text-sm text-muted-foreground">{{ localize('Client Notes', 'ملاحظات العميل') }}</div>
            <div class="text-xl font-semibold">{{ notes?.length || 0 }}</div>
          </div>
          <Button @click="showNoteDialog = true">{{ localize('Add Note', 'إضافة ملاحظة') }}</Button>
        </div>

        <div v-if="notes?.length" class="mt-4 space-y-3">
          <div
            v-for="note in notes"
            :key="note.id"
            class="rounded-md border border-dashed bg-muted/30 p-3"
          >
            <div class="whitespace-pre-line text-sm leading-6">{{ note.note }}</div>
            <div class="mt-2 text-xs text-muted-foreground">
              {{ note.creator?.name || localize('Admin', 'الموظف') }}
              <span v-if="note.created_at"> - {{ new Date(note.created_at).toLocaleString() }}</span>
            </div>
          </div>
        </div>

        <div v-else class="mt-4 rounded-md border border-dashed p-4 text-sm text-muted-foreground">
          {{ localize('No notes added for this client yet.', 'لا توجد ملاحظات مضافة لهذا العميل بعد.') }}
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-md border p-4">
          <div class="text-sm text-muted-foreground">{{ localize('Total Spent', 'إجمالي الإنفاق') }}</div>
          <div class="text-xl font-semibold">{{ fmtMoney(stats.total_spent) }}</div>
        </div>
        <div class="rounded-md border p-4">
          <div class="text-sm text-muted-foreground">{{ localize('Reservations', 'الحجوزات') }}</div>
          <div class="text-xl font-semibold">{{ stats.total_reservations }}</div>
        </div>
        <div class="rounded-md border p-4">
          <div class="text-sm text-muted-foreground">{{ localize('Payments', 'المدفوعات') }}</div>
          <div class="text-xl font-semibold">{{ stats.total_payments }}</div>
        </div>
      </div>

      <div class="rounded-md border p-4">
        <div class="flex items-center justify-between gap-4">
          <div>
            <div class="text-sm text-muted-foreground">{{ localize('Client Documents', 'مستندات العميل') }}</div>
            <div class="text-xl font-semibold">{{ stats.total_documents ?? 0 }}</div>
          </div>
          <Link v-if="actions?.documents" :href="actions.documents">
            <Button variant="outline">{{ localize('Manage Documents', 'إدارة المستندات') }}</Button>
          </Link>
        </div>
      </div>

      <div class="rounded-md border p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="text-sm text-muted-foreground">{{ localize('Customer Status', 'حالة العميل') }}</div>
            <div class="mt-1 text-xl font-semibold">{{ clientStatus?.overall_label || statusStyle.label }}</div>
            <div class="mt-1 text-sm text-muted-foreground">
              {{
                clientStatus?.can_book
                  ? localize('This client can create new bookings.', 'يمكن لهذا العميل إنشاء حجوزات جديدة.')
                  : localize('This client has blocking issues before booking.', 'يوجد على هذا العميل ملاحظات مانعة قبل الحجز.')
              }}
            </div>
          </div>
          <span
            class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-medium"
            :style="{ backgroundColor: statusStyle.bg, color: statusStyle.text }"
          >
            <span class="size-2 rounded-full" :style="{ backgroundColor: statusStyle.dot }" />
            {{ clientStatus?.overall_label || statusStyle.label }}
          </span>
        </div>

        <div v-if="clientStatus?.flags?.length" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
          <div
            v-for="flag in clientStatus.flags"
            :key="`${flag.source}-${flag.type}-${flag.id || flag.description}`"
            class="rounded-md border p-3"
            :class="flagStyle(flag.severity)"
          >
            <div class="flex items-center justify-between gap-2">
              <div class="font-semibold">{{ flag.label }}</div>
              <span v-if="flag.blocks_booking" class="rounded-full bg-white/70 px-2 py-0.5 text-xs">
                {{ localize('Blocks booking', 'يمنع الحجز') }}
              </span>
            </div>
            <div class="mt-1 text-sm opacity-90">{{ flag.description }}</div>
          </div>
        </div>

        <div v-else class="mt-4 rounded-md border border-dashed p-4 text-sm text-muted-foreground">
          {{ localize('No customer status notes.', 'لا توجد ملاحظات على حالة العميل.') }}
        </div>
      </div>

      <div class="rounded-md border">
        <div class="border-b px-4 py-3 font-medium">{{ localize('Past Reservations', 'الحجوزات السابقة') }}</div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">#</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ localize('Car', 'السيارة') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ localize('Dates', 'التواريخ') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ localize('Total', 'الإجمالي') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ localize('Status', 'الحالة') }}</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
              <tr v-for="r in reservations.data" :key="r.id">
                <td class="px-4 py-3">{{ r.reservation_number }}</td>
                <td class="px-4 py-3">
                  <div class="font-medium">
                    {{ r.car ? `${r.car.year} ${r.car.make} ${r.car.model}` : '—' }}
                  </div>
                  <div class="text-xs text-muted-foreground">{{ r.car?.license_plate }}</div>
                </td>
                <td class="px-4 py-3">
                  <div class="font-medium">
                    {{ new Date(r.start_date).toLocaleDateString() }} - {{ new Date(r.end_date).toLocaleDateString() }}
                  </div>
                </td>
                <td class="px-4 py-3">{{ fmtMoney(r.total_amount) }}</td>
                <td class="px-4 py-3">{{ r.status }}</td>
                <td class="px-4 py-3 text-right">
                  <Link :href="`/admin/reservations/${r.id}`">
                    <Button variant="outline" size="sm">{{ localize('View', 'عرض') }}</Button>
                  </Link>
                </td>
              </tr>
              <tr v-if="reservations.data.length === 0">
                <td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ localize('No reservations.', 'لا توجد حجوزات.') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <nav v-if="reservations.links?.length" class="flex gap-2 px-4 py-3">
          <Link
            v-for="(link, i) in reservations.links"
            :key="i"
            :href="link.url || ''"
            :class="[
              'rounded px-3 py-1 text-sm',
              link.active ? 'bg-primary text-primary-foreground' : 'bg-gray-100 text-gray-700',
              !link.url && 'pointer-events-none opacity-50',
            ]"
          >
            <span v-html="link.label" />
          </Link>
        </nav>
      </div>

      <div class="rounded-md border">
        <div class="border-b px-4 py-3 font-medium">{{ localize('Payments', 'المدفوعات') }}</div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">#</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ localize('Reservation', 'الحجز') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ localize('Amount', 'المبلغ') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ localize('Method', 'الطريقة') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ localize('Status', 'الحالة') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ localize('Processed', 'المعالجة') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
              <tr v-for="p in payments.data" :key="p.id">
                <td class="px-4 py-3">{{ p.payment_number }}</td>
                <td class="px-4 py-3">
                  <div class="font-medium">{{ p.reservation?.reservation_number || '—' }}</div>
                </td>
                <td class="px-4 py-3">{{ fmtMoney(p.amount) }}</td>
                <td class="px-4 py-3">{{ p.payment_method }}</td>
                <td class="px-4 py-3">{{ p.status }}</td>
                <td class="px-4 py-3">{{ p.processed_at ? new Date(p.processed_at).toLocaleString() : '—' }}</td>
              </tr>
              <tr v-if="payments.data.length === 0">
                <td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ localize('No payments.', 'لا توجد مدفوعات.') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <nav v-if="payments.links?.length" class="flex gap-2 px-4 py-3">
          <Link
            v-for="(link, i) in payments.links"
            :key="i"
            :href="link.url || ''"
            :class="[
              'rounded px-3 py-1 text-sm',
              link.active ? 'bg-primary text-primary-foreground' : 'bg-gray-100 text-gray-700',
              !link.url && 'pointer-events-none opacity-50',
            ]"
          >
            <span v-html="link.label" />
          </Link>
        </nav>
      </div>
    </main>

    <Dialog v-model:open="showSuspendDialog">
      <DialogContent class="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <AlertCircle class="h-5 w-5 text-destructive" />
            {{ localize('Suspend User', 'إيقاف المستخدم') }}
          </DialogTitle>
          <DialogDescription>
            {{ localize('Are you sure you want to suspend this user? They will not be able to log in until re-activated.', 'هل أنت متأكد من إيقاف هذا المستخدم؟ لن يتمكن من تسجيل الدخول حتى إعادة تفعيله.') }}
          </DialogDescription>
        </DialogHeader>
        <Alert variant="destructive" class="mt-4">
          <AlertCircle class="h-4 w-4" />
          <AlertDescription>
            {{ localize('This action can be reverted later by an admin, but the user will be blocked immediately.', 'يمكن التراجع عن هذا الإجراء لاحقًا من قبل المسؤول، لكن سيتم حظر المستخدم فورًا.') }}
          </AlertDescription>
        </Alert>
        <DialogFooter class="mt-4">
          <DialogClose as-child>
            <Button variant="outline">{{ localize('Cancel', 'إلغاء') }}</Button>
          </DialogClose>
          <Button type="button" variant="destructive" :disabled="processingSuspend" @click="suspendClient">
            {{ processingSuspend ? localize('Suspending...', 'جارٍ الإيقاف...') : localize('Suspend User', 'إيقاف المستخدم') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="showActivateDialog">
      <DialogContent class="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <AlertCircle class="h-5 w-5 text-destructive" />
            {{ localize('Activate User', 'تفعيل المستخدم') }}
          </DialogTitle>
          <DialogDescription>
            {{ localize('Are you sure you want to activate this user? They will be able to log in again.', 'هل أنت متأكد من تفعيل هذا المستخدم؟ سيتمكن من تسجيل الدخول مرة أخرى.') }}
          </DialogDescription>
        </DialogHeader>
        <DialogFooter class="mt-4">
          <DialogClose as-child>
            <Button variant="outline">{{ localize('Cancel', 'إلغاء') }}</Button>
          </DialogClose>
          <Button type="button" variant="destructive" :disabled="processingActivate" @click="activateClient">
            {{ processingActivate ? localize('Activating...', 'جارٍ التفعيل...') : localize('Activate User', 'تفعيل المستخدم') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="showNoteDialog">
      <DialogContent class="sm:max-w-[520px]">
        <DialogHeader>
          <DialogTitle>{{ localize('Add Client Note', 'إضافة ملاحظة للعميل') }}</DialogTitle>
          <DialogDescription>
            {{ localize('Write an internal note that will appear on this client profile.', 'اكتب ملاحظة داخلية تظهر في ملف هذا العميل.') }}
          </DialogDescription>
        </DialogHeader>

        <form class="mt-4 space-y-3" @submit.prevent="submitClientNote">
          <Textarea
            v-model="noteForm.note"
            rows="6"
            :placeholder="localize('Example: customer needs manual review before next booking.', 'مثال: العميل يحتاج مراجعة قبل الحجز القادم.')"
          />
          <div v-if="noteForm.errors.note" class="text-sm text-destructive">{{ noteForm.errors.note }}</div>

          <DialogFooter>
            <DialogClose as-child>
              <Button type="button" variant="outline">{{ localize('Cancel', 'إلغاء') }}</Button>
            </DialogClose>
            <Button type="submit" :disabled="noteForm.processing">
              {{ noteForm.processing ? localize('Saving...', 'جار الحفظ...') : localize('Save Note', 'حفظ الملاحظة') }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </AdminLayout>
</template>
