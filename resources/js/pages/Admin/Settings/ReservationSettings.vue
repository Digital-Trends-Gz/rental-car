<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTrans } from '@/composables/useTrans';

type ReturnTimeMode = 'fixed_time' | 'same_pickup' | 'set_during_reservation';
type LateReturnMode = 'hourly' | 'daily_after_threshold';

type ReservationSettings = {
    return_time_policy: {
        mode: ReturnTimeMode;
        fixed_time: string;
    };
    pickup_return_locations: Array<{
        name: string;
        pickup_fee: number | string;
        return_fee: number | string;
        pickup_free: boolean;
        return_free: boolean;
        is_active: boolean;
    }>;
    kilometer_pricing: Array<{
        from_km: number | string | null;
        to_km: number | string | null;
        price: number | string;
    }>;
    fuel_pricing: Array<{
        fuel_level: string;
        price: number | string;
    }>;
    late_return: {
        mode: LateReturnMode;
        hourly_fee: number | string;
        after_hours: number | string;
    };
    cleaning_fee: number | string;
};

const props = defineProps<{
    settings: ReservationSettings;
    actions: {
        update: string;
    };
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const form = useForm({
    settings: JSON.parse(JSON.stringify(props.settings)) as ReservationSettings,
});

const returnTimeModes = [
    { value: 'fixed_time', label: localize('Fixed time', 'ساعة ثابتة') },
    { value: 'same_pickup', label: localize('Same as pickup time', 'نفس ساعة الأخذ') },
    { value: 'set_during_reservation', label: localize('Choose during reservation', 'يتم تحديده أثناء الحجز') },
] as const;

const lateReturnModes = [
    { value: 'hourly', label: localize('Charge per hour', 'احتساب بالساعة') },
    { value: 'daily_after_threshold', label: localize('Charge one full day after threshold', 'احتساب يوم كامل بعد حد الساعات') },
] as const;

const fuelLevelOptions = [
    { value: 'empty', label: localize('Empty', 'فارغ') },
    { value: 'quarter', label: localize('1/4 Tank', 'ربع تانك') },
    { value: 'half', label: localize('1/2 Tank', 'نصف تانك') },
    { value: 'three_quarters', label: localize('3/4 Tank', 'ثلاثة أرباع تانك') },
    { value: 'full', label: localize('Full', 'ممتلئ') },
] as const;

const pageTitle = computed(() => localize('Reservation Settings', 'إعدادات الحجز'));

function blankLocation() {
    return {
        name: '',
        pickup_fee: 0,
        return_fee: 0,
        pickup_free: false,
        return_free: false,
        is_active: true,
    };
}

function blankKilometerTier() {
    return {
        from_km: null,
        to_km: null,
        price: 0,
    };
}

function blankFuelRule() {
    return {
        fuel_level: 'empty',
        price: 0,
    };
}

function addLocation() {
    form.settings.pickup_return_locations.push(blankLocation());
}

function removeLocation(index: number) {
    form.settings.pickup_return_locations.splice(index, 1);
}

function addKilometerTier() {
    form.settings.kilometer_pricing.push(blankKilometerTier());
}

function removeKilometerTier(index: number) {
    form.settings.kilometer_pricing.splice(index, 1);
}

function addFuelRule() {
    form.settings.fuel_pricing.push(blankFuelRule());
}

function removeFuelRule(index: number) {
    form.settings.fuel_pricing.splice(index, 1);
}

function submit() {
    form.put(props.actions.update, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ pageTitle }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Configure return time, location fees, kilometer tiers, fuel pricing, late return charges, and cleaning fees.', 'اضبط قواعد وقت الإرجاع، ورسوم المواقع، وشريحة الكيلومترات، وتسعير الوقود، ورسوم التأخير، والتنظيف.') }}
                    </p>
                </div>

                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                </Button>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Return Time Policy', 'سياسة وقت الإرجاع') }}</CardTitle>
                        <CardDescription>
                            {{ localize('Choose how return time is handled when creating a reservation.', 'اختر طريقة التعامل مع وقت الإرجاع عند إنشاء الحجز.') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="return_time_mode">{{ localize('Policy', 'الطريقة') }}</Label>
                                <select id="return_time_mode" v-model="form.settings.return_time_policy.mode" class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                    <option v-for="mode in returnTimeModes" :key="mode.value" :value="mode.value">{{ mode.label }}</option>
                                </select>
                                <InputError :message="form.errors['settings.return_time_policy.mode']" />
                            </div>

                            <div v-if="form.settings.return_time_policy.mode === 'fixed_time'" class="space-y-2">
                                <Label for="return_time_fixed_time">{{ localize('Fixed Return Time', 'ساعة الإرجاع الثابتة') }}</Label>
                                <Input id="return_time_fixed_time" v-model="form.settings.return_time_policy.fixed_time" type="time" />
                                <p class="text-xs text-muted-foreground">
                                    {{ localize('The day ends at this time for reservations using the fixed return time policy.', 'ينتهي يوم الحجز عند هذه الساعة عند استخدام سياسة وقت إرجاع ثابت.') }}
                                </p>
                                <InputError :message="form.errors['settings.return_time_policy.fixed_time']" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between gap-4">
                        <div>
                            <CardTitle>{{ localize('Pickup & Return Locations', 'مواقع الاستلام والإرجاع') }}</CardTitle>
                            <CardDescription>
                                {{ localize('Add multiple locations with optional pickup and return fees. Each fee can also be free.', 'أضف أكثر من موقع مع رسوم استلام وإرجاع اختيارية ويمكن أن تكون مجانية.') }}
                            </CardDescription>
                        </div>
                        <Button type="button" variant="outline" @click="addLocation">
                            {{ localize('Add Location', 'إضافة موقع') }}
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="form.settings.pickup_return_locations.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                            {{ localize('No locations added yet.', 'لم يتم إضافة أي موقع بعد.') }}
                        </div>

                        <div v-for="(location, index) in form.settings.pickup_return_locations" :key="`location-${index}`" class="rounded-lg border p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div class="font-medium">{{ localize('Location', 'الموقع') }} #{{ index + 1 }}</div>
                                <Button type="button" variant="ghost" class="text-red-600 hover:text-red-700" @click="removeLocation(index)">
                                    {{ localize('Remove', 'حذف') }}
                                </Button>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                <div class="space-y-2">
                                    <Label :for="`location-name-${index}`">{{ localize('Name', 'الاسم') }}</Label>
                                    <Input :id="`location-name-${index}`" v-model="location.name" />
                                    <InputError :message="form.errors[`settings.pickup_return_locations.${index}.name`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`location-pickup-fee-${index}`">{{ localize('Pickup Fee', 'رسوم الاستلام') }}</Label>
                                    <Input :id="`location-pickup-fee-${index}`" v-model="location.pickup_fee" type="number" min="0" step="0.01" :disabled="location.pickup_free" />
                                    <InputError :message="form.errors[`settings.pickup_return_locations.${index}.pickup_fee`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`location-return-fee-${index}`">{{ localize('Return Fee', 'رسوم الإرجاع') }}</Label>
                                    <Input :id="`location-return-fee-${index}`" v-model="location.return_fee" type="number" min="0" step="0.01" :disabled="location.return_free" />
                                    <InputError :message="form.errors[`settings.pickup_return_locations.${index}.return_fee`]" />
                                </div>

                                <div class="flex items-center gap-3">
                                    <input :id="`location-pickup-free-${index}`" v-model="location.pickup_free" type="checkbox" class="h-4 w-4 rounded border-input" />
                                    <Label :for="`location-pickup-free-${index}`">{{ localize('Pickup free', 'الاستلام مجاني') }}</Label>
                                </div>

                                <div class="flex items-center gap-3">
                                    <input :id="`location-return-free-${index}`" v-model="location.return_free" type="checkbox" class="h-4 w-4 rounded border-input" />
                                    <Label :for="`location-return-free-${index}`">{{ localize('Return free', 'الإرجاع مجاني') }}</Label>
                                </div>

                                <div class="flex items-center gap-3">
                                    <input :id="`location-active-${index}`" v-model="location.is_active" type="checkbox" class="h-4 w-4 rounded border-input" />
                                    <Label :for="`location-active-${index}`">{{ localize('Active', 'مفعّل') }}</Label>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between gap-4">
                        <div>
                            <CardTitle>{{ localize('Kilometer Pricing', 'تسعير الكيلومترات') }}</CardTitle>
                            <CardDescription>
                                {{ localize('Add tiers from and to kilometer ranges with a price for each range.', 'أضف شرائح من وإلى الكيلومترات مع سعر لكل شريحة.') }}
                            </CardDescription>
                        </div>
                        <Button type="button" variant="outline" @click="addKilometerTier">
                            {{ localize('Add Tier', 'إضافة شريحة') }}
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="form.settings.kilometer_pricing.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                            {{ localize('No kilometer tiers added yet.', 'لم تتم إضافة أي شريحة بعد.') }}
                        </div>

                        <div v-for="(tier, index) in form.settings.kilometer_pricing" :key="`tier-${index}`" class="rounded-lg border p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div class="font-medium">{{ localize('Tier', 'الشريحة') }} #{{ index + 1 }}</div>
                                <Button type="button" variant="ghost" class="text-red-600 hover:text-red-700" @click="removeKilometerTier(index)">
                                    {{ localize('Remove', 'حذف') }}
                                </Button>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-3">
                                <div class="space-y-2">
                                    <Label :for="`tier-from-${index}`">{{ localize('From KM', 'من كم') }}</Label>
                                    <Input :id="`tier-from-${index}`" v-model="tier.from_km" type="number" min="0" step="1" />
                                    <InputError :message="form.errors[`settings.kilometer_pricing.${index}.from_km`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`tier-to-${index}`">{{ localize('To KM', 'إلى كم') }}</Label>
                                    <Input :id="`tier-to-${index}`" v-model="tier.to_km" type="number" min="0" step="1" />
                                    <InputError :message="form.errors[`settings.kilometer_pricing.${index}.to_km`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`tier-price-${index}`">{{ localize('Price', 'السعر') }}</Label>
                                    <Input :id="`tier-price-${index}`" v-model="tier.price" type="number" min="0" step="0.01" />
                                    <InputError :message="form.errors[`settings.kilometer_pricing.${index}.price`]" />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between gap-4">
                        <div>
                            <CardTitle>{{ localize('Fuel Pricing', 'تسعير الوقود') }}</CardTitle>
                            <CardDescription>
                                {{ localize('Set a price per fuel level for fuel-based calculations.', 'حدد سعرًا لكل مستوى وقود لاستخدامه في الحسابات.') }}
                            </CardDescription>
                        </div>
                        <Button type="button" variant="outline" @click="addFuelRule">
                            {{ localize('Add Rule', 'إضافة قاعدة') }}
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="form.settings.fuel_pricing.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                            {{ localize('No fuel rules added yet.', 'لم تتم إضافة أي قاعدة للوقود بعد.') }}
                        </div>

                        <div v-for="(rule, index) in form.settings.fuel_pricing" :key="`fuel-${index}`" class="rounded-lg border p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div class="font-medium">{{ localize('Fuel Rule', 'قاعدة وقود') }} #{{ index + 1 }}</div>
                                <Button type="button" variant="ghost" class="text-red-600 hover:text-red-700" @click="removeFuelRule(index)">
                                    {{ localize('Remove', 'حذف') }}
                                </Button>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label :for="`fuel-level-${index}`">{{ localize('Fuel Level', 'مستوى الوقود') }}</Label>
                                    <select :id="`fuel-level-${index}`" v-model="rule.fuel_level" class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                        <option v-for="option in fuelLevelOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                    <InputError :message="form.errors[`settings.fuel_pricing.${index}.fuel_level`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`fuel-price-${index}`">{{ localize('Price', 'السعر') }}</Label>
                                    <Input :id="`fuel-price-${index}`" v-model="rule.price" type="number" min="0" step="0.01" />
                                    <InputError :message="form.errors[`settings.fuel_pricing.${index}.price`]" />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div class="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Late Return Charges', 'رسوم التأخير في الإرجاع') }}</CardTitle>
                            <CardDescription>
                                {{ localize('Choose whether late return is charged per hour or converted to a new day after a threshold.', 'اختر هل يتم احتساب التأخير بالساعة أو يتحول إلى يوم جديد بعد حد معين.') }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="late_return_mode">{{ localize('Mode', 'الطريقة') }}</Label>
                                <select id="late_return_mode" v-model="form.settings.late_return.mode" class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                    <option v-for="mode in lateReturnModes" :key="mode.value" :value="mode.value">{{ mode.label }}</option>
                                </select>
                                <InputError :message="form.errors['settings.late_return.mode']" />
                            </div>

                            <div class="space-y-2">
                                <Label for="late_return_hourly_fee">{{ localize('Hourly Fee', 'رسوم الساعة') }}</Label>
                                <Input id="late_return_hourly_fee" v-model="form.settings.late_return.hourly_fee" type="number" min="0" step="0.01" />
                                <InputError :message="form.errors['settings.late_return.hourly_fee']" />
                            </div>

                            <div class="space-y-2">
                                <Label for="late_return_after_hours">{{ localize('Threshold Hours', 'عدد الساعات قبل التحويل ليوم جديد') }}</Label>
                                <Input id="late_return_after_hours" v-model="form.settings.late_return.after_hours" type="number" min="0" step="1" />
                                <InputError :message="form.errors['settings.late_return.after_hours']" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Cleaning Fee', 'رسوم التنظيف') }}</CardTitle>
                            <CardDescription>
                                {{ localize('Set a fixed cleaning fee to apply when needed.', 'حدد رسوم تنظيف ثابتة لتطبيقها عند الحاجة.') }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="cleaning_fee">{{ localize('Cleaning Fee', 'رسوم التنظيف') }}</Label>
                                <Input id="cleaning_fee" v-model="form.settings.cleaning_fee" type="number" min="0" step="0.01" />
                                <InputError :message="form.errors['settings.cleaning_fee']" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="flex justify-end">
                    <Button :disabled="form.processing" type="submit">
                        {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                    </Button>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
