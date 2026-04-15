<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    repair: {
        id?: number;
        damage_case_id: number | null;
        maintenance_workshop_id: number | null;
        status: string;
        opened_at: string | null;
        started_at: string | null;
        completed_at: string | null;
        estimated_cost: number | null;
        actual_cost: number | null;
        notes: string | null;
        completion_notes: string | null;
    };
    damageCases: Array<{ id: number; label: string; status: string }>;
    workshops: Array<{ id: number; label: string }>;
    statuses: Array<{ value: string; label: string; color: string }>;
    indexUrl: string;
    submitUrl: string;
    method: 'post' | 'put';
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);
const isEdit = computed(() => !!props.repair?.id);

const form = useForm({
    car_damage_case_id: props.repair?.damage_case_id ? String(props.repair.damage_case_id) : '',
    maintenance_workshop_id: props.repair?.maintenance_workshop_id ? String(props.repair.maintenance_workshop_id) : '',
    status: props.repair?.status ?? 'open',
    opened_at: props.repair?.opened_at ?? '',
    started_at: props.repair?.started_at ?? '',
    completed_at: props.repair?.completed_at ?? '',
    estimated_cost: props.repair?.estimated_cost ?? '',
    actual_cost: props.repair?.actual_cost ?? '',
    notes: props.repair?.notes ?? '',
    completion_notes: props.repair?.completion_notes ?? '',
});

function submit() {
    if (props.method === 'put') {
        form.put(props.submitUrl, { preserveScroll: true });
        return;
    }

    form.post(props.submitUrl, { preserveScroll: true });
}
</script>

<template>
    <Head :title="isEdit ? localize('Edit Damage Repair', 'تعديل إصلاح الضرر') : localize('Create Damage Repair', 'إنشاء إصلاح ضرر')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{ isEdit ? localize('Edit Damage Repair', 'تعديل إصلاح الضرر') : localize('Create Damage Repair', 'إنشاء إصلاح ضرر') }}
                </h1>
                <Link :href="indexUrl">
                    <Button variant="outline">{{ localize('Back', 'رجوع') }}</Button>
                </Link>
            </div>

            <div class="max-w-4xl">
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2 md:col-span-2">
                            <Label for="car_damage_case_id">{{ localize('Damage Case', 'الضرر') }}</Label>
                            <select
                                id="car_damage_case_id"
                                v-model="form.car_damage_case_id"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option value="" disabled>{{ localize('Select damage case', 'اختر الضرر') }}</option>
                                <option v-for="item in damageCases" :key="item.id" :value="String(item.id)">
                                    {{ item.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.car_damage_case_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="status">{{ localize('Status', 'الحالة') }}</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option v-for="item in statuses" :key="item.value" :value="item.value">
                                    {{ item.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>

                        <div class="space-y-2">
                            <Label for="maintenance_workshop_id">{{ localize('Workshop', 'الورشة') }}</Label>
                            <select
                                id="maintenance_workshop_id"
                                v-model="form.maintenance_workshop_id"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="">{{ localize('Select workshop', 'اختر الورشة') }}</option>
                                <option v-for="item in workshops" :key="item.id" :value="String(item.id)">
                                    {{ item.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.maintenance_workshop_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="opened_at">{{ localize('Opened At', 'تاريخ الفتح') }}</Label>
                            <Input id="opened_at" v-model="form.opened_at" type="datetime-local" />
                            <InputError :message="form.errors.opened_at" />
                        </div>

                        <div class="space-y-2">
                            <Label for="started_at">{{ localize('Started At', 'تاريخ البدء') }}</Label>
                            <Input id="started_at" v-model="form.started_at" type="datetime-local" />
                            <InputError :message="form.errors.started_at" />
                        </div>

                        <div class="space-y-2">
                            <Label for="completed_at">{{ localize('Completed At', 'تاريخ الإنهاء') }}</Label>
                            <Input id="completed_at" v-model="form.completed_at" type="datetime-local" />
                            <InputError :message="form.errors.completed_at" />
                        </div>

                        <div class="space-y-2">
                            <Label for="estimated_cost">{{ localize('Estimated Cost', 'التكلفة التقديرية') }}</Label>
                            <Input id="estimated_cost" v-model="form.estimated_cost" min="0" step="0.01" type="number" />
                            <InputError :message="form.errors.estimated_cost" />
                        </div>

                        <div class="space-y-2">
                            <Label for="actual_cost">{{ localize('Actual Cost', 'التكلفة الفعلية') }}</Label>
                            <Input id="actual_cost" v-model="form.actual_cost" min="0" step="0.01" type="number" />
                            <InputError :message="form.errors.actual_cost" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="notes">{{ localize('Notes', 'ملاحظات') }}</Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="4"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                            />
                            <InputError :message="form.errors.notes" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="completion_notes">{{ localize('Completion Notes', 'ملاحظات الإنهاء') }}</Label>
                            <textarea
                                id="completion_notes"
                                v-model="form.completion_notes"
                                rows="4"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                            />
                            <InputError :message="form.errors.completion_notes" />
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <Button :disabled="form.processing" type="submit">
                            {{
                                form.processing
                                    ? localize('Saving...', 'جارٍ الحفظ...')
                                    : isEdit
                                      ? localize('Save Changes', 'حفظ التغييرات')
                                      : localize('Create Repair', 'إنشاء الإصلاح')
                            }}
                        </Button>
                        <Link :href="indexUrl">
                            <Button type="button" variant="outline">{{ localize('Cancel', 'إلغاء') }}</Button>
                        </Link>
                    </div>
                </form>
            </div>
        </main>
    </AdminLayout>
</template>
