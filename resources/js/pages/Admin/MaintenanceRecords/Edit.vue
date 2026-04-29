<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    record: {
        id: number;
        car_id: number;
        maintenance_type_id: number | null;
        maintenance_workshop_id: number | null;
        status: string;
        scheduled_date: string | null;
        started_at: string | null;
        completed_at: string | null;
        cost: number | null;
        odometer: number | null;
        workshop_name: string | null;
        notes: string | null;
    } | null;
    initialCarId: number | null;
    cars: Array<{ id: number; label: string }>;
    maintenanceTypes: Array<{
        id: number;
        name: string;
        workshops: Array<{
            id: number;
            name: string;
            phone: string | null;
            city: string | null;
            country: string | null;
            label: string;
        }>;
    }>;
    statuses: Array<{ value: string; label: string; color: string }>;
    indexUrl: string;
    submitUrl: string;
    method: 'post' | 'put';
}>();

const isEdit = computed(() => !!props.record);
const { t } = useTrans();

const form = useForm({
    car_id: props.record?.car_id ? String(props.record.car_id) : props.initialCarId ? String(props.initialCarId) : '',
    maintenance_type_id: props.record?.maintenance_type_id ? String(props.record.maintenance_type_id) : '',
    maintenance_workshop_id: props.record?.maintenance_workshop_id ? String(props.record.maintenance_workshop_id) : '',
    status: props.record?.status ?? 'scheduled',
    scheduled_date: props.record?.scheduled_date ?? '',
    started_at: props.record?.started_at ?? '',
    completed_at: props.record?.completed_at ?? '',
    cost: props.record?.cost ?? '',
    odometer: props.record?.odometer ?? '',
    notes: props.record?.notes ?? '',
});

const workshopSearch = ref(props.record?.workshop_name ?? '');
const workshopMenuOpen = ref(false);

function formatDateTimeLocalInput(value: Date): string {
    const pad = (number: number) => String(number).padStart(2, '0');

    return [
        value.getFullYear(),
        pad(value.getMonth() + 1),
        pad(value.getDate()),
    ].join('-') + `T${pad(value.getHours())}:${pad(value.getMinutes())}`;
}

const completedAtMin = computed(() => {
    if (!form.started_at) {
        return '';
    }

    const startedAt = new Date(form.started_at);
    if (Number.isNaN(startedAt.getTime())) {
        return '';
    }

    startedAt.setMinutes(startedAt.getMinutes() + 1);
    return formatDateTimeLocalInput(startedAt);
});

const availableWorkshops = computed(() => {
    const selectedType = props.maintenanceTypes.find((type) => String(type.id) === form.maintenance_type_id);
    return selectedType?.workshops ?? [];
});

const filteredWorkshops = computed(() => {
    const term = workshopSearch.value.trim().toLowerCase();
    if (!term) {
        return availableWorkshops.value;
    }

    return availableWorkshops.value.filter((workshop) =>
        [workshop.name, workshop.phone, workshop.city, workshop.country, workshop.label]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(term)),
    );
});

const selectedWorkshop = computed(() => {
    if (!form.maintenance_workshop_id) {
        return null;
    }

    return availableWorkshops.value.find((workshop) => String(workshop.id) === form.maintenance_workshop_id) ?? null;
});

watch(
    () => form.maintenance_type_id,
    () => {
        if (!selectedWorkshop.value) {
            form.maintenance_workshop_id = '';
            workshopSearch.value = '';
            return;
        }

        workshopSearch.value = selectedWorkshop.value.label;
    },
);

watch(
    () => form.maintenance_workshop_id,
    () => {
        if (selectedWorkshop.value) {
            workshopSearch.value = selectedWorkshop.value.label;
            return;
        }

        if (!workshopMenuOpen.value) {
            workshopSearch.value = '';
        }
    },
);

function selectWorkshop(workshop: { id: number; label: string }) {
    form.maintenance_workshop_id = String(workshop.id);
    workshopSearch.value = workshop.label;
    workshopMenuOpen.value = false;
}

function clearWorkshop() {
    form.maintenance_workshop_id = '';
    workshopSearch.value = '';
}

function handleWorkshopBlur() {
    window.setTimeout(() => {
        workshopMenuOpen.value = false;

        if (!selectedWorkshop.value) {
            workshopSearch.value = '';
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
    <Head
        :title="
            isEdit
                ? t('dashboard.admin.maintenance_records.edit.head_title_edit')
                : t('dashboard.admin.maintenance_records.edit.head_title_create')
        "
    />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{
                        isEdit
                            ? t('dashboard.admin.maintenance_records.edit.title_edit')
                            : t('dashboard.admin.maintenance_records.edit.title_create')
                    }}
                </h1>
                <Link :href="indexUrl">
                    <Button variant="outline">{{ t('dashboard.admin.common.back') }}</Button>
                </Link>
            </div>

            <div class="max-w-3xl">
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="car_id">{{ t('dashboard.admin.maintenance_records.edit.fields.car') }}</Label>
                            <select
                                id="car_id"
                                v-model="form.car_id"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option value="" disabled>{{ t('dashboard.admin.maintenance_records.edit.placeholders.select_car') }}</option>
                                <option v-for="car in cars" :key="car.id" :value="String(car.id)">
                                    {{ car.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.car_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="maintenance_type_id">{{ t('dashboard.admin.maintenance_records.edit.fields.maintenance_type') }}</Label>
                            <select
                                id="maintenance_type_id"
                                v-model="form.maintenance_type_id"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="">{{ t('dashboard.admin.maintenance_records.edit.placeholders.select_type') }}</option>
                                <option v-for="type in maintenanceTypes" :key="type.id" :value="String(type.id)">
                                    {{ type.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.maintenance_type_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="status">{{ t('dashboard.admin.maintenance_records.edit.fields.status') }}</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option v-for="statusItem in statuses" :key="statusItem.value" :value="statusItem.value">
                                    {{ statusItem.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>

                        <div class="space-y-2">
                            <Label for="scheduled_date">{{ t('dashboard.admin.maintenance_records.edit.fields.scheduled_date') }}</Label>
                            <Input id="scheduled_date" v-model="form.scheduled_date" type="date" />
                            <InputError :message="form.errors.scheduled_date" />
                        </div>

                        <div class="space-y-2">
                            <Label for="started_at">{{ t('dashboard.admin.maintenance_records.edit.fields.started_at') }}</Label>
                            <Input id="started_at" v-model="form.started_at" type="datetime-local" />
                            <InputError :message="form.errors.started_at" />
                        </div>

                        <div class="space-y-2">
                            <Label for="completed_at">{{ t('dashboard.admin.maintenance_records.edit.fields.completed_at') }}</Label>
                            <Input id="completed_at" v-model="form.completed_at" type="datetime-local" :min="completedAtMin" />
                            <InputError :message="form.errors.completed_at" />
                        </div>

                        <div class="space-y-2">
                            <Label for="cost">{{ t('dashboard.admin.maintenance_records.edit.fields.cost') }}</Label>
                            <Input id="cost" v-model="form.cost" min="0" step="0.01" type="number" />
                            <InputError :message="form.errors.cost" />
                        </div>

                        <div class="space-y-2">
                            <Label for="odometer">{{ t('dashboard.admin.maintenance_records.edit.fields.odometer') }}</Label>
                            <Input id="odometer" v-model="form.odometer" min="0" step="1" type="number" />
                            <InputError :message="form.errors.odometer" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="maintenance_workshop_search">{{ t('dashboard.admin.maintenance_records.edit.fields.workshop') }}</Label>
                            <div class="relative">
                                <Input
                                    id="maintenance_workshop_search"
                                    v-model="workshopSearch"
                                    :disabled="!form.maintenance_type_id || availableWorkshops.length === 0"
                                    :placeholder="
                                        !form.maintenance_type_id
                                            ? t('dashboard.admin.maintenance_records.edit.placeholders.select_type_first')
                                            : availableWorkshops.length === 0
                                              ? t('dashboard.admin.maintenance_records.edit.placeholders.no_workshops_for_type')
                                              : t('dashboard.admin.maintenance_records.edit.placeholders.search_workshop')
                                    "
                                    autocomplete="off"
                                    @focus="workshopMenuOpen = true"
                                    @blur="handleWorkshopBlur"
                                    @input="
                                        workshopMenuOpen = true;
                                        form.maintenance_workshop_id = '';
                                    "
                                />

                                <button
                                    v-if="form.maintenance_workshop_id"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-muted-foreground hover:text-foreground"
                                    type="button"
                                    @mousedown.prevent
                                    @click="clearWorkshop"
                                >
                                    {{ t('dashboard.admin.common.clear') }}
                                </button>

                                <div
                                    v-if="workshopMenuOpen && form.maintenance_type_id && availableWorkshops.length > 0"
                                    class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-background shadow-lg"
                                >
                                    <button
                                        v-for="workshop in filteredWorkshops"
                                        :key="workshop.id"
                                        class="flex w-full flex-col items-start px-3 py-2 text-left text-sm hover:bg-muted"
                                        type="button"
                                        @mousedown.prevent="selectWorkshop(workshop)"
                                    >
                                        <span class="font-medium">{{ workshop.name }}</span>
                                        <span class="text-xs text-muted-foreground">{{ workshop.label }}</span>
                                    </button>

                                    <div v-if="filteredWorkshops.length === 0" class="px-3 py-2 text-sm text-muted-foreground">
                                        {{ t('dashboard.admin.maintenance_records.edit.empty_workshops') }}
                                    </div>
                                </div>
                            </div>
                            <InputError :message="form.errors.maintenance_workshop_id" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="notes">{{ t('dashboard.admin.maintenance_records.edit.fields.notes') }}</Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="4"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                            />
                            <InputError :message="form.errors.notes" />
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <Button :disabled="form.processing" type="submit">
                            {{
                                form.processing
                                    ? t('dashboard.admin.common.saving')
                                    : isEdit
                                        ? t('dashboard.admin.common.save_changes')
                                        : t('dashboard.admin.maintenance_records.edit.create_record')
                            }}
                        </Button>
                        <Link :href="indexUrl">
                            <Button type="button" variant="outline">{{ t('dashboard.admin.common.cancel') }}</Button>
                        </Link>
                    </div>
                </form>
            </div>
        </main>
    </AdminLayout>
</template>
