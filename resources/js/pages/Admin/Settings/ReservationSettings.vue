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
type DiscountAutoApprovalType = 'fixed' | 'percentage';

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
    employee_discount_auto_approval: {
        enabled: boolean;
        type: DiscountAutoApprovalType;
        value: number | string;
    };
};

const props = defineProps<{
    settings: ReservationSettings;
    actions: {
        update: string;
    };
}>();

const { t } = useTrans();
const translationRoot = 'dashboard.admin.reservation_settings';

const interpolateFallback = (text: string, params: Record<string, string | number> = {}) =>
    text.replace(/:([A-Za-z0-9_]+)/g, (_match, key: string) => (params[key] !== undefined ? String(params[key]) : `:${key}`));

const tr = (key: string, fallback: string, params: Record<string, string | number> = {}) => {
    const fullKey = `${translationRoot}.${key}`;
    const translated = t(fullKey, params);

    return translated === fullKey ? interpolateFallback(fallback, params) : translated;
};

const form = useForm({
    settings: JSON.parse(JSON.stringify(props.settings)) as ReservationSettings,
});

const returnTimeModes = [
    { value: 'fixed_time', label: tr('options.return_time_modes.fixed_time', 'Fixed time') },
    { value: 'same_pickup', label: tr('options.return_time_modes.same_pickup', 'Same as pickup time') },
    { value: 'set_during_reservation', label: tr('options.return_time_modes.set_during_reservation', 'Choose during reservation') },
] as const;

const lateReturnModes = [
    { value: 'hourly', label: tr('options.late_return_modes.hourly', 'Charge per hour') },
    { value: 'daily_after_threshold', label: tr('options.late_return_modes.daily_after_threshold', 'Charge one full day after threshold') },
] as const;

const discountAutoApprovalTypes = [
    { value: 'percentage', label: tr('options.discount_auto_approval_types.percentage', 'Percentage') },
    { value: 'fixed', label: tr('options.discount_auto_approval_types.fixed', 'Fixed amount') },
] as const;

const fuelLevelOptions = [
    { value: 'empty', label: tr('options.fuel_levels.empty', 'Empty') },
    { value: 'quarter', label: tr('options.fuel_levels.quarter', '1/4 Tank') },
    { value: 'half', label: tr('options.fuel_levels.half', '1/2 Tank') },
    { value: 'three_quarters', label: tr('options.fuel_levels.three_quarters', '3/4 Tank') },
    { value: 'full', label: tr('options.fuel_levels.full', 'Full') },
] as const;

const pageTitle = computed(() => tr('title', 'Reservation Settings'));

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
                        {{ tr('description', 'Configure return time, location fees, kilometer tiers, fuel pricing, late return charges, and cleaning fees.') }}
                    </p>
                </div>

                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? tr('actions.saving', 'Saving...') : tr('actions.save_changes', 'Save Changes') }}
                </Button>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ tr('sections.return_time_policy.title', 'Return Time Policy') }}</CardTitle>
                        <CardDescription>
                            {{ tr('sections.return_time_policy.description', 'Choose how return time is handled when creating a reservation.') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="return_time_mode">{{ tr('fields.policy', 'Policy') }}</Label>
                                <select id="return_time_mode" v-model="form.settings.return_time_policy.mode" class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                    <option v-for="mode in returnTimeModes" :key="mode.value" :value="mode.value">{{ mode.label }}</option>
                                </select>
                                <InputError :message="form.errors['settings.return_time_policy.mode']" />
                            </div>

                            <div v-if="form.settings.return_time_policy.mode === 'fixed_time'" class="space-y-2">
                                <Label for="return_time_fixed_time">{{ tr('fields.fixed_return_time', 'Fixed Return Time') }}</Label>
                                <Input id="return_time_fixed_time" v-model="form.settings.return_time_policy.fixed_time" type="time" />
                                <p class="text-xs text-muted-foreground">
                                    {{ tr('help.fixed_return_time', 'The day ends at this time for reservations using the fixed return time policy.') }}
                                </p>
                                <InputError :message="form.errors['settings.return_time_policy.fixed_time']" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between gap-4">
                        <div>
                            <CardTitle>{{ tr('sections.pickup_return_locations.title', 'Pickup & Return Locations') }}</CardTitle>
                            <CardDescription>
                                {{ tr('sections.pickup_return_locations.description', 'Add multiple locations with optional pickup and return fees. Each fee can also be free.') }}
                            </CardDescription>
                        </div>
                        <Button type="button" variant="outline" @click="addLocation">
                            {{ tr('actions.add_location', 'Add Location') }}
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="form.settings.pickup_return_locations.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                            {{ tr('empty.locations', 'No locations added yet.') }}
                        </div>

                        <div v-for="(location, index) in form.settings.pickup_return_locations" :key="`location-${index}`" class="rounded-lg border p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div class="font-medium">{{ tr('fields.location', 'Location') }} #{{ index + 1 }}</div>
                                <Button type="button" variant="ghost" class="text-red-600 hover:text-red-700" @click="removeLocation(index)">
                                    {{ tr('actions.remove', 'Remove') }}
                                </Button>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                <div class="space-y-2">
                                    <Label :for="`location-name-${index}`">{{ tr('fields.name', 'Name') }}</Label>
                                    <Input :id="`location-name-${index}`" v-model="location.name" />
                                    <InputError :message="form.errors[`settings.pickup_return_locations.${index}.name`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`location-pickup-fee-${index}`">{{ tr('fields.pickup_fee', 'Pickup Fee') }}</Label>
                                    <Input :id="`location-pickup-fee-${index}`" v-model="location.pickup_fee" type="number" min="0" step="0.01" :disabled="location.pickup_free" />
                                    <InputError :message="form.errors[`settings.pickup_return_locations.${index}.pickup_fee`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`location-return-fee-${index}`">{{ tr('fields.return_fee', 'Return Fee') }}</Label>
                                    <Input :id="`location-return-fee-${index}`" v-model="location.return_fee" type="number" min="0" step="0.01" :disabled="location.return_free" />
                                    <InputError :message="form.errors[`settings.pickup_return_locations.${index}.return_fee`]" />
                                </div>

                                <div class="flex items-center gap-3">
                                    <input :id="`location-pickup-free-${index}`" v-model="location.pickup_free" type="checkbox" class="h-4 w-4 rounded border-input" />
                                    <Label :for="`location-pickup-free-${index}`">{{ tr('fields.pickup_free', 'Pickup free') }}</Label>
                                </div>

                                <div class="flex items-center gap-3">
                                    <input :id="`location-return-free-${index}`" v-model="location.return_free" type="checkbox" class="h-4 w-4 rounded border-input" />
                                    <Label :for="`location-return-free-${index}`">{{ tr('fields.return_free', 'Return free') }}</Label>
                                </div>

                                <div class="flex items-center gap-3">
                                    <input :id="`location-active-${index}`" v-model="location.is_active" type="checkbox" class="h-4 w-4 rounded border-input" />
                                    <Label :for="`location-active-${index}`">{{ tr('fields.active', 'Active') }}</Label>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between gap-4">
                        <div>
                            <CardTitle>{{ tr('sections.kilometer_pricing.title', 'Kilometer Pricing') }}</CardTitle>
                            <CardDescription>
                                {{ tr('sections.kilometer_pricing.description', 'Add tiers from and to kilometer ranges with a price for each range.') }}
                            </CardDescription>
                        </div>
                        <Button type="button" variant="outline" @click="addKilometerTier">
                            {{ tr('actions.add_tier', 'Add Tier') }}
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="form.settings.kilometer_pricing.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                            {{ tr('empty.kilometer_tiers', 'No kilometer tiers added yet.') }}
                        </div>

                        <div v-for="(tier, index) in form.settings.kilometer_pricing" :key="`tier-${index}`" class="rounded-lg border p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div class="font-medium">{{ tr('fields.tier', 'Tier') }} #{{ index + 1 }}</div>
                                <Button type="button" variant="ghost" class="text-red-600 hover:text-red-700" @click="removeKilometerTier(index)">
                                    {{ tr('actions.remove', 'Remove') }}
                                </Button>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-3">
                                <div class="space-y-2">
                                    <Label :for="`tier-from-${index}`">{{ tr('fields.from_km', 'From KM') }}</Label>
                                    <Input :id="`tier-from-${index}`" v-model="tier.from_km" type="number" min="0" step="1" />
                                    <InputError :message="form.errors[`settings.kilometer_pricing.${index}.from_km`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`tier-to-${index}`">{{ tr('fields.to_km', 'To KM') }}</Label>
                                    <Input :id="`tier-to-${index}`" v-model="tier.to_km" type="number" min="0" step="1" />
                                    <InputError :message="form.errors[`settings.kilometer_pricing.${index}.to_km`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`tier-price-${index}`">{{ tr('fields.price', 'Price') }}</Label>
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
                            <CardTitle>{{ tr('sections.fuel_pricing.title', 'Fuel Pricing') }}</CardTitle>
                            <CardDescription>
                                {{ tr('sections.fuel_pricing.description', 'Set a price per fuel level for fuel-based calculations.') }}
                            </CardDescription>
                        </div>
                        <Button type="button" variant="outline" @click="addFuelRule">
                            {{ tr('actions.add_rule', 'Add Rule') }}
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="form.settings.fuel_pricing.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                            {{ tr('empty.fuel_rules', 'No fuel rules added yet.') }}
                        </div>

                        <div v-for="(rule, index) in form.settings.fuel_pricing" :key="`fuel-${index}`" class="rounded-lg border p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div class="font-medium">{{ tr('fields.fuel_rule', 'Fuel Rule') }} #{{ index + 1 }}</div>
                                <Button type="button" variant="ghost" class="text-red-600 hover:text-red-700" @click="removeFuelRule(index)">
                                    {{ tr('actions.remove', 'Remove') }}
                                </Button>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label :for="`fuel-level-${index}`">{{ tr('fields.fuel_level', 'Fuel Level') }}</Label>
                                    <select :id="`fuel-level-${index}`" v-model="rule.fuel_level" class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                        <option v-for="option in fuelLevelOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                    <InputError :message="form.errors[`settings.fuel_pricing.${index}.fuel_level`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`fuel-price-${index}`">{{ tr('fields.price', 'Price') }}</Label>
                                    <Input :id="`fuel-price-${index}`" v-model="rule.price" type="number" min="0" step="0.01" />
                                    <InputError :message="form.errors[`settings.fuel_pricing.${index}.price`]" />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ tr('sections.employee_discount_auto_approval.title', 'Employee Discount Auto Approval') }}</CardTitle>
                        <CardDescription>
                            {{ tr('sections.employee_discount_auto_approval.description', 'Approve employee discount requests automatically when the calculated discount is within this limit. Larger discounts stay pending for manager review.') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-start gap-3 rounded-md border p-4">
                            <input
                                id="employee_discount_auto_approval_enabled"
                                v-model="form.settings.employee_discount_auto_approval.enabled"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-input"
                            />
                            <div>
                                <Label for="employee_discount_auto_approval_enabled">
                                    {{ tr('fields.enable_automatic_approval', 'Enable automatic approval') }}
                                </Label>
                                <p class="text-xs text-muted-foreground">
                                    {{ tr('help.auto_approval_disabled', 'When disabled, all employee discount requests require manager approval.') }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="employee_discount_auto_approval_type">{{ tr('fields.limit_type', 'Limit Type') }}</Label>
                                <select
                                    id="employee_discount_auto_approval_type"
                                    v-model="form.settings.employee_discount_auto_approval.type"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option
                                        v-for="type in discountAutoApprovalTypes"
                                        :key="type.value"
                                        :value="type.value"
                                    >
                                        {{ type.label }}
                                    </option>
                                </select>
                                <InputError :message="form.errors['settings.employee_discount_auto_approval.type']" />
                            </div>

                            <div class="space-y-2">
                                <Label for="employee_discount_auto_approval_value">
                                    {{ form.settings.employee_discount_auto_approval.type === 'percentage' ? tr('fields.allowed_percentage', 'Allowed Percentage') : tr('fields.allowed_amount', 'Allowed Amount') }}
                                </Label>
                                <Input
                                    id="employee_discount_auto_approval_value"
                                    v-model="form.settings.employee_discount_auto_approval.value"
                                    type="number"
                                    min="0"
                                    :max="form.settings.employee_discount_auto_approval.type === 'percentage' ? 100 : undefined"
                                    step="0.01"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ form.settings.employee_discount_auto_approval.type === 'percentage' ? tr('help.auto_approval_percentage', 'Example: 5 means requests up to 5% are approved automatically.') : tr('help.auto_approval_fixed', 'Requests whose calculated discount amount is within this amount are approved automatically.') }}
                                </p>
                                <InputError :message="form.errors['settings.employee_discount_auto_approval.value']" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div class="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ tr('sections.late_return_charges.title', 'Late Return Charges') }}</CardTitle>
                            <CardDescription>
                                {{ tr('sections.late_return_charges.description', 'Choose whether late return is charged per hour or converted to a new day after a threshold.') }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="late_return_mode">{{ tr('fields.mode', 'Mode') }}</Label>
                                <select id="late_return_mode" v-model="form.settings.late_return.mode" class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                    <option v-for="mode in lateReturnModes" :key="mode.value" :value="mode.value">{{ mode.label }}</option>
                                </select>
                                <InputError :message="form.errors['settings.late_return.mode']" />
                            </div>

                            <div class="space-y-2">
                                <Label for="late_return_hourly_fee">{{ tr('fields.hourly_fee', 'Hourly Fee') }}</Label>
                                <Input id="late_return_hourly_fee" v-model="form.settings.late_return.hourly_fee" type="number" min="0" step="0.01" />
                                <InputError :message="form.errors['settings.late_return.hourly_fee']" />
                            </div>

                            <div class="space-y-2">
                                <Label for="late_return_after_hours">{{ tr('fields.threshold_hours', 'Threshold Hours') }}</Label>
                                <Input id="late_return_after_hours" v-model="form.settings.late_return.after_hours" type="number" min="0" step="1" />
                                <InputError :message="form.errors['settings.late_return.after_hours']" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ tr('sections.cleaning_fee.title', 'Cleaning Fee') }}</CardTitle>
                            <CardDescription>
                                {{ tr('sections.cleaning_fee.description', 'Set a fixed cleaning fee to apply when needed.') }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="cleaning_fee">{{ tr('fields.cleaning_fee', 'Cleaning Fee') }}</Label>
                                <Input id="cleaning_fee" v-model="form.settings.cleaning_fee" type="number" min="0" step="0.01" />
                                <InputError :message="form.errors['settings.cleaning_fee']" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="flex justify-end">
                    <Button :disabled="form.processing" type="submit">
                        {{ form.processing ? tr('actions.saving', 'Saving...') : tr('actions.save_changes', 'Save Changes') }}
                    </Button>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
