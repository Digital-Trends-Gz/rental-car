<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    discount: {
        id: number;
        car_id: number | null;
        name: string;
        description: string | null;
        type: string;
        value: number;
        max_discount_amount: number | null;
        min_total_amount: number | null;
        min_days: number | null;
        starts_at: string | null;
        ends_at: string | null;
        priority: number;
        is_active: boolean;
    } | null;
    cars: Array<{ id: number; label: string }>;
    types: Array<{ value: string; label: string }>;
    indexUrl: string;
    submitUrl: string;
    method: 'post' | 'put';
}>();

const { locale, t } = useTrans();
const translationRoot = 'dashboard.admin.car_discounts';
const translationKeyFor = (value: string) =>
    value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 90);
const localize = (en: string, ar: string, ur: string = en) => {
    const key = `${translationRoot}.${translationKeyFor(en)}`;
    const translated = t(key);

    if (translated !== key) {
        return translated;
    }

    if (locale.value === 'ar') return ar;
    if (locale.value === 'ur') return ur;
    return en;
};

const isEdit = computed(() => !!props.discount);

const form = useForm({
    car_id: props.discount?.car_id ?? '',
    name: props.discount?.name ?? '',
    description: props.discount?.description ?? '',
    type: props.discount?.type ?? 'percentage',
    value: props.discount?.value ?? '',
    max_discount_amount: props.discount?.max_discount_amount ?? '',
    min_total_amount: props.discount?.min_total_amount ?? '',
    min_days: props.discount?.min_days ?? '',
    starts_at: props.discount?.starts_at ?? '',
    ends_at: props.discount?.ends_at ?? '',
    priority: props.discount?.priority ?? 0,
    is_active: props.discount?.is_active ?? true,
});

function submit() {
    if (props.method === 'put') {
        form.put(props.submitUrl);
        return;
    }

    form.post(props.submitUrl);
}

function typeLabel(value: string, label: string) {
    if (value === 'percentage') {
        return localize('Percentage', 'نسبة مئوية', 'فیصد');
    }

    if (value === 'fixed') {
        return localize('Fixed Amount', 'مبلغ ثابت', 'مقرر رقم');
    }

    return label;
}
</script>

<template>
    <Head :title="isEdit ? localize('Edit Auto Discount', 'تعديل خصم تلقائي', 'خودکار رعایت ترمیم کریں') : localize('Create Auto Discount', 'إنشاء خصم تلقائي', 'نیا خودکار رعایت بنائیں')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">
                    {{ isEdit ? localize('Edit Auto Discount', 'تعديل خصم تلقائي', 'خودکار رعایت ترمیم کریں') : localize('Create Auto Discount', 'إنشاء خصم تلقائي', 'نیا خودکار رعایت بنائیں') }}
                </h1>
                <Link :href="indexUrl">
                    <Button variant="outline">{{ localize('Back', 'رجوع', 'واپس') }}</Button>
                </Link>
            </div>

            <form class="space-y-6 rounded-lg border bg-card p-6" @submit.prevent="submit">
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <Label for="name">{{ localize('Name', 'الاسم', 'نام') }}</Label>
                        <Input id="name" v-model="form.name" />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <Label for="car_id">{{ localize('Car Scope', 'نطاق السيارة', 'کار کا دائرہ') }}</Label>
                        <select id="car_id" v-model="form.car_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">{{ localize('All cars', 'كل السيارات', 'تمام گاڑیاں') }}</option>
                            <option v-for="car in cars" :key="car.id" :value="car.id">{{ car.label }}</option>
                        </select>
                        <InputError :message="form.errors.car_id" class="mt-1" />
                    </div>

                    <div>
                        <Label for="type">{{ localize('Type', 'النوع', 'قسم') }}</Label>
                        <select id="type" v-model="form.type" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                            <option v-for="type in types" :key="type.value" :value="type.value">{{ typeLabel(type.value, type.label) }}</option>
                        </select>
                        <InputError :message="form.errors.type" class="mt-1" />
                    </div>

                    <div>
                        <Label for="value">{{ localize('Value', 'القيمة', 'قیمت') }}</Label>
                        <Input id="value" v-model="form.value" type="number" step="0.01" min="0.01" />
                        <InputError :message="form.errors.value" class="mt-1" />
                    </div>

                    <div>
                        <Label for="max_discount_amount">{{ localize('Max Discount Amount (optional)', 'أقصى مبلغ للخصم (اختياري)', 'زیادہ سے زیادہ ڈسکاؤنٹ رقم (اختیاری)') }}</Label>
                        <Input id="max_discount_amount" v-model="form.max_discount_amount" type="number" step="0.01" min="0.01" />
                        <InputError :message="form.errors.max_discount_amount" class="mt-1" />
                    </div>

                    <div>
                        <Label for="min_total_amount">{{ localize('Min Order Amount (optional)', 'الحد الأدنى لقيمة الطلب (اختياري)', 'کم از کم آرڈر رقم (اختیاری)') }}</Label>
                        <Input id="min_total_amount" v-model="form.min_total_amount" type="number" step="0.01" min="0" />
                        <InputError :message="form.errors.min_total_amount" class="mt-1" />
                    </div>

                    <div>
                        <Label for="min_days">{{ localize('Min Rental Days (optional)', 'الحد الأدنى لأيام الإيجار (اختياري)', 'کم از کم کرایے کے دن (اختیاری)') }}</Label>
                        <Input id="min_days" v-model="form.min_days" type="number" min="1" />
                        <InputError :message="form.errors.min_days" class="mt-1" />
                    </div>

                    <div>
                        <Label for="priority">{{ localize('Priority', 'الأولوية', 'ترجیح') }}</Label>
                        <Input id="priority" v-model="form.priority" type="number" min="0" />
                        <InputError :message="form.errors.priority" class="mt-1" />
                    </div>

                    <div>
                        <Label for="starts_at">{{ localize('Starts At (optional)', 'تاريخ البدء (اختياري)', 'شروع ہونے کی تاریخ (اختیاری)') }}</Label>
                        <Input id="starts_at" v-model="form.starts_at" type="datetime-local" />
                        <InputError :message="form.errors.starts_at" class="mt-1" />
                    </div>

                    <div>
                        <Label for="ends_at">{{ localize('Ends At (optional)', 'تاريخ الانتهاء (اختياري)', 'ختم ہونے کی تاریخ (اختیاری)') }}</Label>
                        <Input id="ends_at" v-model="form.ends_at" type="datetime-local" />
                        <InputError :message="form.errors.ends_at" class="mt-1" />
                    </div>

                    <div class="flex items-center gap-2 pt-7">
                        <input id="is_active" v-model="form.is_active" type="checkbox" />
                        <Label for="is_active">{{ localize('Active', 'نشط', 'فعال') }}</Label>
                        <InputError :message="form.errors.is_active" class="mt-1" />
                    </div>
                </div>

                <div>
                    <Label for="description">{{ localize('Description (optional)', 'الوصف (اختياري)', 'تفصیل (اختیاری)') }}</Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="4"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.description" class="mt-1" />
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing
                                ? localize('Saving...', 'جارٍ الحفظ...', 'محفوظ کیا جا رہا ہے...')
                                : isEdit
                                    ? localize('Save Changes', 'حفظ التغييرات', 'تبدیلیاں محفوظ کریں')
                                    : localize('Create Auto Discount', 'إنشاء خصم تلقائي', 'نیا خودکار رعایت بنائیں')
                        }}
                    </Button>
                    <Link :href="indexUrl">
                        <Button type="button" variant="outline">{{ localize('Cancel', 'إلغاء', 'منسوخ کریں') }}</Button>
                    </Link>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
