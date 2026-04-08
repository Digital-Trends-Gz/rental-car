<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    violation: {
        id: number;
        car_id: number;
        reservation_id: number | null;
        violation_type_id: number | null;
        issued_to_user_id: number | null;
        branch_owner_user_id?: number | null;
        violation_number: string | null;
        violation_date: string | null;
        type: string;
        amount: number;
        status: string;
        due_date: string | null;
        paid_at: string | null;
        payment_reference: string | null;
        authority: string | null;
        location: string | null;
        description: string | null;
        notes: string | null;
    } | null;
    cars: Array<{ id: number; label: string; branch_id: number | null }>;
    branchOwners: Array<{ id: number; label: string; branch_id: number | null }>;
    violationTypes: Array<{ id: number; label: string }>;
    reservations: Array<{
        id: number;
        label: string;
        car_id: number | null;
        user_id: number | null;
        user_label: string | null;
    }>;
    statuses: Array<{ value: string; label: string; color: string }>;
    indexUrl: string;
    submitUrl: string;
    method: 'post' | 'put';
}>();

const isEdit = computed(() => !!props.violation);
const { t, locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);
const pageTitle = computed(() =>
    isEdit.value
        ? t('dashboard.admin.car_violations.edit.head_title_edit')
        : t('dashboard.admin.car_violations.edit.head_title_create'),
);

const form = useForm({
    car_id: props.violation?.car_id ? String(props.violation.car_id) : '',
    reservation_id: props.violation?.reservation_id
        ? String(props.violation.reservation_id)
        : '',
    violation_type_id: props.violation?.violation_type_id
        ? String(props.violation.violation_type_id)
        : '',
    issued_to_user_id: props.violation?.issued_to_user_id
        ? String(props.violation.issued_to_user_id)
        : '',
    branch_owner_user_id: props.violation?.branch_owner_user_id
        ? String(props.violation.branch_owner_user_id)
        : '',
    violation_number: props.violation?.violation_number ?? '',
    violation_date: props.violation?.violation_date ?? '',
    amount: props.violation?.amount ?? '',
    status: props.violation?.status ?? 'pending',
    due_date: props.violation?.due_date ?? '',
    paid_at: props.violation?.paid_at ?? '',
    payment_reference: props.violation?.payment_reference ?? '',
    authority: props.violation?.authority ?? '',
    location: props.violation?.location ?? '',
    description: props.violation?.description ?? '',
    notes: props.violation?.notes ?? '',
});

const reservationSearch = ref('');
const reservationMenuOpen = ref(false);
const branchOwnerSearch = ref('');
const branchOwnerMenuOpen = ref(false);
const dueDateTouched = ref(!!props.violation?.due_date);

const filteredReservations = computed(() => {
    if (!form.car_id) {
        return props.reservations;
    }

    return props.reservations.filter(
        (item) => String(item.car_id ?? '') === form.car_id,
    );
});

const selectedCar = computed(() =>
    props.cars.find((item) => String(item.id) === form.car_id) ?? null,
);

const selectedReservation = computed(() =>
    filteredReservations.value.find(
        (item) => String(item.id) === form.reservation_id,
    ) ?? null,
);

const filteredBranchOwners = computed(() => {
    if (!selectedCar.value) {
        return props.branchOwners;
    }

    return props.branchOwners.filter((item) => {
        return (
            item.branch_id === null ||
            String(item.branch_id ?? '') === String(selectedCar.value?.branch_id ?? '')
        );
    });
});

const selectedIssuedToLabel = computed(() => {
    if (selectedReservation.value?.user_label) {
        return selectedReservation.value.user_label;
    }

    const branchOwner = filteredBranchOwners.value.find(
        (item) => String(item.id) === form.branch_owner_user_id,
    );

    return branchOwner?.label ?? localize('Not specified', 'غير محدد');
});

const filteredReservationsBySearch = computed(() => {
    const term = reservationSearch.value.trim().toLowerCase();

    if (!term) {
        return filteredReservations.value;
    }

    return filteredReservations.value.filter((item) =>
        [item.label, item.user_label]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(term)),
    );
});

const filteredBranchOwnersBySearch = computed(() => {
    const term = branchOwnerSearch.value.trim().toLowerCase();

    if (!term) {
        return filteredBranchOwners.value;
    }

    return filteredBranchOwners.value.filter((item) =>
        item.label.toLowerCase().includes(term),
    );
});

watch(
    () => form.car_id,
    () => {
        if (
            form.reservation_id &&
            !filteredReservations.value.some(
                (item) => String(item.id) === form.reservation_id,
            )
        ) {
            form.reservation_id = '';
        }

        if (
            form.branch_owner_user_id &&
            !filteredBranchOwners.value.some(
                (item) => String(item.id) === form.branch_owner_user_id,
            )
        ) {
            form.branch_owner_user_id = '';
        }
    },
);

watch(
    () => form.reservation_id,
    () => {
        if (selectedReservation.value?.user_id) {
            form.issued_to_user_id = String(selectedReservation.value.user_id);
            form.branch_owner_user_id = '';
            reservationSearch.value = selectedReservation.value.label;
            return;
        }

        if (form.branch_owner_user_id) {
            form.issued_to_user_id = form.branch_owner_user_id;
            return;
        }

        form.issued_to_user_id = '';
        if (!reservationMenuOpen.value) {
            reservationSearch.value = '';
        }
    },
    { immediate: true },
);

watch(
    () => form.branch_owner_user_id,
    () => {
        if (!form.reservation_id) {
            form.issued_to_user_id = form.branch_owner_user_id || '';
        }

        const branchOwner = filteredBranchOwners.value.find(
            (item) => String(item.id) === form.branch_owner_user_id,
        );

        if (branchOwner) {
            branchOwnerSearch.value = branchOwner.label;
            return;
        }

        if (!branchOwnerMenuOpen.value) {
            branchOwnerSearch.value = '';
        }
    },
    { immediate: true },
);

watch(
    () => form.violation_date,
    (newValue, oldValue) => {
        if (!newValue) {
            if (!dueDateTouched.value) {
                form.due_date = '';
            }
            return;
        }

        const previousAutoValue = oldValue || '';
        if (!dueDateTouched.value || form.due_date === previousAutoValue || form.due_date === '') {
            form.due_date = newValue;
        }
    },
    { immediate: true },
);

watch(
    () => form.due_date,
    (newValue) => {
        if (newValue && newValue !== form.violation_date) {
            dueDateTouched.value = true;
        }

        if (newValue === '' || newValue === form.violation_date) {
            dueDateTouched.value = false;
        }
    },
);

function selectReservation(reservation: (typeof props.reservations)[number]) {
    form.reservation_id = String(reservation.id);
    reservationSearch.value = reservation.label;
    reservationMenuOpen.value = false;
}

function clearReservation() {
    form.reservation_id = '';
    reservationSearch.value = '';
}

function handleReservationBlur() {
    window.setTimeout(() => {
        reservationMenuOpen.value = false;

        if (!selectedReservation.value) {
            reservationSearch.value = '';
        }
    }, 150);
}

function selectBranchOwner(branchOwner: (typeof props.branchOwners)[number]) {
    form.branch_owner_user_id = String(branchOwner.id);
    branchOwnerSearch.value = branchOwner.label;
    branchOwnerMenuOpen.value = false;
}

function clearBranchOwner() {
    form.branch_owner_user_id = '';
    branchOwnerSearch.value = '';
}

function handleBranchOwnerBlur() {
    window.setTimeout(() => {
        branchOwnerMenuOpen.value = false;

        if (!form.branch_owner_user_id) {
            branchOwnerSearch.value = '';
        }
    }, 150);
}

function submit() {
    if (props.method === 'put') {
        form.put(props.submitUrl, { preserveScroll: true });
        return;
    }

    form.post(props.submitUrl, { preserveScroll: true });
}
</script>

<template>
    <Head :title="pageTitle" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ pageTitle }}</h1>
                <Link :href="indexUrl">
                    <Button variant="outline">{{
                        t('dashboard.admin.common.back')
                    }}</Button>
                </Link>
            </div>

            <div class="max-w-4xl">
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="car_id">{{
                                t('dashboard.admin.car_violations.edit.fields.car')
                            }}</Label>
                            <select
                                id="car_id"
                                v-model="form.car_id"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option value="" disabled>
                                    {{
                                        t(
                                            'dashboard.admin.car_violations.edit.select_car',
                                        )
                                    }}
                                </option>
                                <option
                                    v-for="car in cars"
                                    :key="car.id"
                                    :value="String(car.id)"
                                >
                                    {{ car.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.car_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="reservation_id">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.reservation_optional',
                                )
                            }}</Label>
                            <div class="relative">
                                <Input
                                    id="reservation_id"
                                    v-model="reservationSearch"
                                    :disabled="!form.car_id"
                                    :placeholder="
                                        !form.car_id
                                            ? localize('Select car first', 'اختر السيارة أولاً')
                                            : localize('Search reservation...', 'ابحث عن الحجز...')
                                    "
                                    autocomplete="off"
                                    @focus="reservationMenuOpen = true"
                                    @blur="handleReservationBlur"
                                    @input="
                                        reservationMenuOpen = true;
                                        form.reservation_id = '';
                                    "
                                />

                                <button
                                    v-if="form.reservation_id"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-muted-foreground hover:text-foreground"
                                    type="button"
                                    @mousedown.prevent
                                    @click="clearReservation"
                                >
                                    {{ localize('Clear', 'مسح') }}
                                </button>

                                <div
                                    v-if="reservationMenuOpen && form.car_id"
                                    class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-background shadow-lg"
                                >
                                    <button
                                        class="flex w-full items-start px-3 py-2 text-left text-sm hover:bg-muted"
                                        type="button"
                                        @mousedown.prevent="clearReservation(); reservationMenuOpen = false"
                                    >
                                        {{ localize('No reservation (Branch owner)', 'بدون حجز (مسؤول الفرع)') }}
                                    </button>

                                    <button
                                        v-for="reservation in filteredReservationsBySearch"
                                        :key="reservation.id"
                                        class="flex w-full flex-col items-start px-3 py-2 text-left text-sm hover:bg-muted"
                                        type="button"
                                        @mousedown.prevent="selectReservation(reservation)"
                                    >
                                        <span class="font-medium">{{ reservation.label }}</span>
                                        <span v-if="reservation.user_label" class="text-xs text-muted-foreground">{{ reservation.user_label }}</span>
                                    </button>

                                    <div
                                        v-if="filteredReservationsBySearch.length === 0"
                                        class="px-3 py-2 text-sm text-muted-foreground"
                                    >
                                        {{ localize('No reservations found.', 'لا توجد حجوزات مطابقة.') }}
                                    </div>
                                </div>
                            </div>
                            <InputError :message="form.errors.reservation_id" />
                        </div>

                        <div class="space-y-2">
                            <Label v-if="form.reservation_id">{{
                                localize('Reservation User', 'مستخدم الحجز')
                            }}</Label>
                            <Label v-else for="branch_owner_user_id">{{
                                localize('Branch Owner', 'مسؤول الفرع')
                            }}</Label>

                            <div
                                v-if="form.reservation_id"
                                class="h-10 w-full rounded-md border border-input bg-muted px-3 py-2 text-sm"
                            >
                                {{ selectedIssuedToLabel }}
                            </div>

                            <div v-else class="relative">
                                <Input
                                    id="branch_owner_user_id"
                                    v-model="branchOwnerSearch"
                                    :disabled="!form.car_id"
                                    :placeholder="
                                        !form.car_id
                                            ? localize('Select car first', 'اختر السيارة أولاً')
                                            : localize('Search branch owner...', 'ابحث عن مسؤول الفرع...')
                                    "
                                    autocomplete="off"
                                    @focus="branchOwnerMenuOpen = true"
                                    @blur="handleBranchOwnerBlur"
                                    @input="
                                        branchOwnerMenuOpen = true;
                                        form.branch_owner_user_id = '';
                                    "
                                />

                                <button
                                    v-if="form.branch_owner_user_id"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-muted-foreground hover:text-foreground"
                                    type="button"
                                    @mousedown.prevent
                                    @click="clearBranchOwner"
                                >
                                    {{ localize('Clear', 'مسح') }}
                                </button>

                                <div
                                    v-if="branchOwnerMenuOpen && form.car_id"
                                    class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-background shadow-lg"
                                >
                                    <button
                                        v-for="branchOwner in filteredBranchOwnersBySearch"
                                        :key="branchOwner.id"
                                        class="flex w-full flex-col items-start px-3 py-2 text-left text-sm hover:bg-muted"
                                        type="button"
                                        @mousedown.prevent="selectBranchOwner(branchOwner)"
                                    >
                                        <span class="font-medium">{{ branchOwner.label }}</span>
                                    </button>

                                    <div
                                        v-if="filteredBranchOwnersBySearch.length === 0"
                                        class="px-3 py-2 text-sm text-muted-foreground"
                                    >
                                        {{ localize('No branch owners found.', 'لا يوجد مسؤولو فروع مطابقون.') }}
                                    </div>
                                </div>
                            </div>
                            <InputError :message="form.errors.branch_owner_user_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="violation_number">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.violation_number',
                                )
                            }}</Label>
                            <Input
                                id="violation_number"
                                v-model="form.violation_number"
                                :placeholder="
                                    t(
                                        'dashboard.admin.car_violations.edit.placeholders.unique_number',
                                    )
                                "
                            />
                            <InputError
                                :message="form.errors.violation_number"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="violation_date">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.violation_date',
                                )
                            }}</Label>
                            <Input
                                id="violation_date"
                                v-model="form.violation_date"
                                required
                                type="date"
                            />
                            <InputError :message="form.errors.violation_date" />
                        </div>

                        <div class="space-y-2">
                            <Label for="violation_type_id">{{
                                t('dashboard.admin.car_violations.edit.fields.type')
                            }}</Label>
                            <select
                                id="violation_type_id"
                                v-model="form.violation_type_id"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option value="" disabled>
                                    {{
                                        t(
                                            'dashboard.admin.car_violations.edit.placeholders.type',
                                        )
                                    }}
                                </option>
                                <option
                                    v-for="violationType in violationTypes"
                                    :key="violationType.id"
                                    :value="String(violationType.id)"
                                >
                                    {{ violationType.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.violation_type_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="amount">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.amount',
                                )
                            }}</Label>
                            <Input
                                id="amount"
                                v-model="form.amount"
                                min="0"
                                step="0.01"
                                required
                                type="number"
                            />
                            <InputError :message="form.errors.amount" />
                        </div>

                        <div class="space-y-2">
                            <Label for="status">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.status',
                                )
                            }}</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option
                                    v-for="statusItem in statuses"
                                    :key="statusItem.value"
                                    :value="statusItem.value"
                                >
                                    {{ statusItem.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>

                        <div class="space-y-2">
                            <Label for="authority">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.authority',
                                )
                            }}</Label>
                            <Input
                                id="authority"
                                v-model="form.authority"
                                :placeholder="
                                    t(
                                        'dashboard.admin.car_violations.edit.placeholders.authority',
                                    )
                                "
                            />
                            <InputError :message="form.errors.authority" />
                        </div>

                        <div class="space-y-2">
                            <Label for="location">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.location',
                                )
                            }}</Label>
                            <Input id="location" v-model="form.location" />
                            <InputError :message="form.errors.location" />
                        </div>

                        <div class="space-y-2">
                            <Label for="due_date">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.due_date',
                                )
                            }}</Label>
                            <Input id="due_date" v-model="form.due_date" type="date" />
                            <InputError :message="form.errors.due_date" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="description">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.description',
                                )
                            }}</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="md:col-span-2 border-t pt-2">
                            <h3 class="text-sm font-semibold text-foreground">
                                {{ localize('Payment Details', 'تفاصيل الدفع') }}
                            </h3>
                        </div>

                        <div class="space-y-2">
                            <Label for="paid_at">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.paid_at',
                                )
                            }}</Label>
                            <Input
                                id="paid_at"
                                v-model="form.paid_at"
                                type="datetime-local"
                            />
                            <InputError :message="form.errors.paid_at" />
                        </div>

                        <div class="space-y-2">
                            <Label for="payment_reference">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.payment_reference',
                                )
                            }}</Label>
                            <Input
                                id="payment_reference"
                                v-model="form.payment_reference"
                            />
                            <InputError
                                :message="form.errors.payment_reference"
                            />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="notes">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.notes',
                                )
                            }}</Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="3"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                            />
                            <InputError :message="form.errors.notes" />
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <Button :disabled="form.processing" type="submit">
                            {{
                                form.processing
                                    ? t(
                                          'dashboard.admin.car_violations.edit.saving',
                                      )
                                    : isEdit
                                      ? t('dashboard.admin.common.save_changes')
                                      : t(
                                            'dashboard.admin.car_violations.edit.create_violation',
                                        )
                            }}
                        </Button>
                        <Link :href="indexUrl">
                            <Button type="button" variant="outline">{{
                                t('dashboard.admin.common.cancel')
                            }}</Button>
                        </Link>
                    </div>
                </form>
            </div>
        </main>
    </AdminLayout>
</template>
