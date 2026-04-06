<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { index } from '@/routes/admin/reservations';
import { update } from '@/routes/admin/reservations';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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
const pageTitle = computed(() =>
    isEdit.value
        ? `${localize('Edit Reservation', 'تعديل الحجز')} ${props.reservation?.reservation_number || ''}`.trim()
        : localize('Create Reservation', 'إنشاء حجز'),
);
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
    notes: props.reservation?.notes || '',
    status: props.reservation?.status || 'confirmed',
    cancellation_reason: props.reservation?.cancellation_reason || '',
});

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

            <form class="space-y-6" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div v-if="!isEdit">
                        <Label for="user_id">{{ localize('Client', 'العميل') }}</Label>
                        <select
                            id="user_id"
                            v-model="form.user_id"
                            class="mt-1 block w-full rounded-md border border-gray-300 py-2 pr-10 pl-3 text-base focus:border-blue-500 focus:ring-blue-500 focus:outline-none sm:text-sm"
                        >
                            <option value="" disabled>{{ localize('Select client', 'اختر العميل') }}</option>
                            <option v-for="client in clients" :key="client.id" :value="client.id">
                                {{ client.name }} ({{ client.email }})
                            </option>
                        </select>
                        <InputError :message="form.errors.user_id" class="mt-1" />
                    </div>

                    <div v-if="!isEdit">
                        <Label for="car_id">{{ localize('Car', 'السيارة') }}</Label>
                        <select
                            id="car_id"
                            v-model="form.car_id"
                            class="mt-1 block w-full rounded-md border border-gray-300 py-2 pr-10 pl-3 text-base focus:border-blue-500 focus:ring-blue-500 focus:outline-none sm:text-sm"
                        >
                            <option value="" disabled>{{ localize('Select car', 'اختر السيارة') }}</option>
                            <option v-for="carOption in cars" :key="carOption.id" :value="carOption.id">
                                {{ carOption.label }} | {{ carOption.license_plate }}{{ carOption.branch_name ? ` | ${carOption.branch_name}` : '' }}
                            </option>
                        </select>
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
                        <Input id="end_date" v-model="form.end_date" type="date" />
                        <InputError :message="form.errors.end_date" class="mt-1" />
                    </div>

                    <div>
                        <Label for="pickup_time">{{ localize('Pickup Time', 'وقت الاستلام') }}</Label>
                        <Input id="pickup_time" v-model="form.pickup_time" type="time" />
                        <InputError :message="form.errors.pickup_time" class="mt-1" />
                    </div>

                    <div>
                        <Label for="return_time">{{ localize('Return Time', 'وقت الإرجاع') }}</Label>
                        <Input id="return_time" v-model="form.return_time" type="time" />
                        <InputError :message="form.errors.return_time" class="mt-1" />
                    </div>

                    <div>
                        <Label for="pickup_location">{{ localize('Pickup Location', 'موقع الاستلام') }}</Label>
                        <Input id="pickup_location" v-model="form.pickup_location" :placeholder="localize('Main Office', 'المكتب الرئيسي')" />
                        <InputError :message="form.errors.pickup_location" class="mt-1" />
                    </div>

                    <div>
                        <Label for="return_location">{{ localize('Return Location', 'موقع الإرجاع') }}</Label>
                        <Input id="return_location" v-model="form.return_location" :placeholder="localize('Main Office', 'المكتب الرئيسي')" />
                        <InputError :message="form.errors.return_location" class="mt-1" />
                    </div>

                    <div>
                        <Label for="discount_amount">{{ localize('Discount', 'الخصم') }}</Label>
                        <Input id="discount_amount" v-model="form.discount_amount" type="number" step="0.01" min="0" />
                        <InputError :message="form.errors.discount_amount" class="mt-1" />
                    </div>

                    <div>
                        <Label for="status">{{ localize('Status', 'الحالة') }}</Label>
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
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : isEdit ? localize('Save Changes', 'حفظ التغييرات') : localize('Create Reservation', 'إنشاء حجز') }}
                    </Button>
                    <Link v-if="subdomain" :href="index(subdomain).url">
                        <Button type="button" variant="outline">{{ localize('Cancel', 'إلغاء') }}</Button>
                    </Link>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
