<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { type Plan, type Discount } from '@/types';

const props = defineProps<{
    discount: Discount;
    plans: Plan[];
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string, ur: string = en) => (locale.value === 'ar' ? ar : locale.value === 'ur' ? ur : en);

const form = useForm({
    plan_id: props.discount.plan_id.toString(),
    name: props.discount.name,
    code: props.discount.code || '',
    type: props.discount.type,
    value: Number(props.discount.value),
    start_date: props.discount.start_date.split('T')[0],
    end_date: props.discount.end_date.split('T')[0],
    is_active: props.discount.is_active,
});

const submit = () => {
    form.put(`/superadmin/discounts/${props.discount.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head :title="localize('Edit Discount', 'تعديل الخصم', 'ڈسکاؤنٹ میں ترمیم')" />
    <SuperAdminLayout>
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center gap-4">
                <Link href="/superadmin/discounts">
                    <Button variant="outline">{{ localize('Back', 'رجوع', 'واپس') }}</Button>
                </Link>
                <h1 class="text-2xl font-semibold">{{ localize('Edit Subscription Discount', 'تعديل خصم الاشتراك', 'سبسکرپشن ڈسکاؤنٹ میں ترمیم') }}</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>{{ localize('Discount Details', 'تفاصيل الخصم', 'ڈسکاؤنٹ کی تفصیلات') }}</CardTitle>
                                <CardDescription>{{ localize('Basic information about the discount.', 'المعلومات الأساسية عن الخصم.', 'ڈسکاؤنٹ کے بارے میں بنیادی معلومات۔') }}</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="plan_id">{{ localize('Target Plan *', 'الخطة المستهدفة *', 'ہدف پلان *') }}</Label>
                                    <select
                                        id="plan_id"
                                        v-model="form.plan_id"
                                        class="flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-50"
                                        required
                                    >
                                        <option value="" disabled>{{ localize('Select a plan', 'اختر خطة', 'ایک پلان منتخب کریں') }}</option>
                                        <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                            {{ plan.name }}
                                        </option>
                                    </select>
                                    <div v-if="form.errors.plan_id" class="text-sm text-red-600">{{ form.errors.plan_id }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="name">{{ localize('Discount Name *', 'اسم الخصم *', 'ڈسکاؤنٹ کا نام *') }}</Label>
                                    <Input id="name" v-model="form.name" required :placeholder="localize('e.g. Summer Special', 'مثال: خصم الصيف', 'مثلاً: سمر اسپیشل')" />
                                    <div v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="code">{{ localize('Discount Code (Optional)', 'رمز الخصم (اختياري)', 'ڈسکاؤنٹ کوڈ (اختیاری)') }}</Label>
                                    <Input id="code" v-model="form.code" :placeholder="localize('e.g. SUMMER50', 'مثال: SUMMER50', 'مثلاً: SUMMER50')" />
                                    <div v-if="form.errors.code" class="text-sm text-red-600">{{ form.errors.code }}</div>
                                </div>
                                <div class="flex items-center justify-between space-x-2 py-2">
                                    <div class="space-y-0.5">
                                        <Label for="is_active">{{ localize('Active Status', 'الحالة النشطة', 'فعال حالت') }}</Label>
                                        <p class="text-xs text-muted-foreground">{{ localize('Whether this discount is currently active.', 'ما إذا كان هذا الخصم نشطًا حاليًا.', 'یہ ڈسکاؤنٹ فی الحال فعال ہے یا نہیں۔') }}</p>
                                    </div>
                                    <Switch
                                        id="is_active"
                                        :checked="form.is_active"
                                        @update:checked="(val: boolean) => form.is_active = val"
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div class="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>{{ localize('Discount Configuration', 'إعدادات الخصم', 'ڈسکاؤنٹ کنفیگریشن') }}</CardTitle>
                                <CardDescription>{{ localize('Set the discount value and type.', 'حدد قيمة الخصم ونوعه.', 'ڈسکاؤنٹ کی قیمت اور قسم طے کریں۔') }}</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="type">{{ localize('Discount Type *', 'نوع الخصم *', 'ڈسکاؤنٹ کی قسم *') }}</Label>
                                    <select
                                        id="type"
                                        v-model="form.type"
                                        class="flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-50"
                                        required
                                    >
                                        <option value="percentage">{{ localize('Percentage (%)', 'نسبة مئوية (%)', 'فیصد (%)') }}</option>
                                        <option value="fixed">{{ localize('Fixed Amount ($)', 'مبلغ ثابت ($)', 'مقرر رقم ($)') }}</option>
                                    </select>
                                    <div v-if="form.errors.type" class="text-sm text-red-600">{{ form.errors.type }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="value">{{ localize('Value *', 'القيمة *', 'قیمت *') }}</Label>
                                    <Input id="value" v-model.number="form.value" type="number" step="0.01" required />
                                    <div v-if="form.errors.value" class="text-sm text-red-600">{{ form.errors.value }}</div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{{ localize('Validity Period', 'فترة الصلاحية', 'میعادِ نفاذ') }}</CardTitle>
                                <CardDescription>{{ localize('Define when the discount is applicable.', 'حدد متى يكون الخصم ساريًا.', 'بتائیں کہ ڈسکاؤنٹ کب قابلِ اطلاق ہے۔') }}</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="start_date">{{ localize('Start Date *', 'تاريخ البدء *', 'آغاز کی تاریخ *') }}</Label>
                                    <Input id="start_date" v-model="form.start_date" type="date" required />
                                    <div v-if="form.errors.start_date" class="text-sm text-red-600">{{ form.errors.start_date }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="end_date">{{ localize('End Date *', 'تاريخ الانتهاء *', 'اختتام کی تاریخ *') }}</Label>
                                    <Input id="end_date" v-model="form.end_date" type="date" required />
                                    <div v-if="form.errors.end_date" class="text-sm text-red-600">{{ form.errors.end_date }}</div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? localize('Updating...', 'جارٍ التحديث...', 'اپ ڈیٹ کیا جا رہا ہے...') : localize('Update Discount', 'تحديث الخصم', 'ڈسکاؤنٹ اپ ڈیٹ کریں') }}
                    </Button>
                    <Link href="/superadmin/discounts">
                        <Button type="button" variant="outline">{{ localize('Cancel', 'إلغاء', 'منسوخ کریں') }}</Button>
                    </Link>
                </div>
            </form>
        </main>
    </SuperAdminLayout>
</template>
