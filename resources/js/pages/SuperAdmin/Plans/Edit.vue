<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Plus, Trash2 } from 'lucide-vue-next';
import { Switch } from '@/components/ui/switch';
import { computed } from 'vue';
import { type Plan } from '@/types';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    plan: Plan;
    featureFlags: Array<{ key: string; label: string; helper: string }>;
    supportedLocales: Array<{ code: string; name: string; native: string; direction: 'ltr' | 'rtl' }>;
    planTranslations: Record<string, {
        name: string;
        description: string;
        sort_order: number;
        features: string[];
    }>;
}>();

const { t } = useTrans();

type FeatureFlagField = {
    key: string;
    label: string;
    helper: string;
};

const featureFlagFields = props.featureFlags as FeatureFlagField[];
const initialFeatures = props.plan.features?.length ? [...props.plan.features] : [''];

const buildTranslations = () => Object.fromEntries(
    props.supportedLocales.map((locale) => {
        const translation = props.planTranslations?.[locale.code] ?? {};
        const translatedFeatures = Array.isArray(translation.features) ? translation.features : [];

        return [
            locale.code,
            {
                name: String(translation.name ?? props.plan.name ?? ''),
                description: String(translation.description ?? props.plan.description ?? ''),
                sort_order: Number(translation.sort_order ?? props.plan.sort_order ?? 0),
                features: initialFeatures.map((feature, index) => String(translatedFeatures[index] ?? feature ?? '')),
            },
        ];
    }),
) as Record<string, { name: string; description: string; sort_order: number; features: string[] }>;

const buildFeatureFlags = () => {
    const current = props.plan.feature_flags || {};

    return Object.fromEntries(
        featureFlagFields.map((item) => [item.key, Boolean(current[item.key] ?? true)]),
    ) as Record<string, boolean>;
};

const form = useForm({
    name: props.plan.name,
    description: props.plan.description || '',
    sort_order: props.plan.sort_order ?? 0,
    features: [...initialFeatures],
    translations: buildTranslations(),
    custom_pricing: Boolean(props.plan.custom_pricing),
    feature_flags: buildFeatureFlags(),
    monthly_price: Number(props.plan.monthly_price),
    monthly_price_id: props.plan.monthly_price_id || '',
    yearly_price: Number(props.plan.yearly_price),
    yearly_price_id: props.plan.yearly_price_id || '',
    one_time_price: props.plan.one_time_price ? Number(props.plan.one_time_price) : 0,
    one_time_price_id: props.plan.one_time_price_id || '',
    max_employees: props.plan.max_employees ?? null,
    max_branches: props.plan.max_branches ?? null,
    max_cars: props.plan.max_cars ?? null,
    max_contracts: props.plan.max_contracts ?? null,
    openai_requests_per_day: props.plan.openai_requests_per_day ?? null,
    is_active: props.plan.is_active,
    is_most_value: Boolean(props.plan.is_most_value),
});

const limitFields = [
    { key: 'max_employees', label: 'Max Employees', placeholder: 'Unlimited', helper: 'Leave blank for no limit.' },
    { key: 'max_branches', label: 'Max Branches', placeholder: 'Unlimited', helper: 'Leave blank for no limit.' },
    { key: 'max_cars', label: 'Max Cars', placeholder: 'Unlimited', helper: 'Leave blank for no limit.' },
    { key: 'max_contracts', label: 'Max Rental Contracts', placeholder: 'Unlimited', helper: 'Leave blank for no limit.' },
    { key: 'openai_requests_per_day', label: 'OpenAI Requests / Day', placeholder: 'Unlimited', helper: 'Leave blank for no limit.' },
] as const;
type LimitFieldKey = (typeof limitFields)[number]['key'];

const isLimitEnabled = (field: LimitFieldKey) => form[field] !== null && form[field] !== undefined;

const setLimitEnabled = (field: LimitFieldKey, enabled: boolean) => {
    form[field] = enabled ? (form[field] ?? 1) : null;
};

const isFeatureEnabled = (field: string) => Boolean(form.feature_flags[field]);

const setFeatureEnabled = (field: string, enabled: boolean) => {
    form.feature_flags[field] = enabled;
};

const enabledFeatureCount = computed(() => Object.values(form.feature_flags).filter(Boolean).length);

const summaryFields = [
    { key: 'tenants_count', label: 'Tenants using this plan', value: props.plan.tenants_count ?? 0, highlight: false },
    { key: 'max_employees', label: 'Employees limit', value: props.plan.max_employees, highlight: true },
    { key: 'max_branches', label: 'Branches limit', value: props.plan.max_branches, highlight: true },
    { key: 'max_cars', label: 'Cars limit', value: props.plan.max_cars, highlight: true },
    { key: 'max_contracts', label: 'Contracts limit', value: props.plan.max_contracts, highlight: true },
    { key: 'openai_requests_per_day', label: 'OpenAI requests / day', value: props.plan.openai_requests_per_day, highlight: true },
] as const;

const formatLimit = (value: number | null | undefined) => {
    return value === null || value === undefined
        ? t('dashboard.super_admin.plans.index.unlimited')
        : String(value);
};

const formatSummaryValue = (fieldKey: string, value: number | null | undefined) => {
    if (fieldKey === 'tenants_count') {
        return `${value ?? 0} ${t('dashboard.super_admin.plans.index.tenants')}`;
    }

    return formatLimit(value);
};

const addFeature = () => {
    form.features.push('');
    Object.values(form.translations).forEach((translation) => translation.features.push(''));
};

const removeFeature = (index: number) => {
    form.features.splice(index, 1);
    Object.values(form.translations).forEach((translation) => translation.features.splice(index, 1));
    if (form.features.length === 0) {
        form.features.push('');
        Object.values(form.translations).forEach((translation) => translation.features.push(''));
    }
};

const submit = () => {
    form.put(`/superadmin/plans/${props.plan.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Edit Subscription Plan" />
    <SuperAdminLayout>
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center gap-4">
                <Link href="/superadmin/plans">
                    <Button variant="outline">Back</Button>
                </Link>
                <h1 class="text-2xl font-semibold">Edit Subscription Plan</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Plan Details</CardTitle>
                                <CardDescription>Basic information about the plan.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="name">Name *</Label>
                                    <Input id="name" v-model="form.name" required placeholder="e.g. Pro Plan" />
                                    <div v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="description">Description</Label>
                                    <Textarea id="description" v-model="form.description" placeholder="Short description of the plan" />
                                    <div v-if="form.errors.description" class="text-sm text-red-600">{{ form.errors.description }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="sort_order">Display Order *</Label>
                                    <Input id="sort_order" v-model.number="form.sort_order" type="number" min="0" step="1" required />
                                    <p class="text-xs text-muted-foreground">Lower numbers appear first. Use 0 for the first plan.</p>
                                    <div v-if="form.errors.sort_order" class="text-sm text-red-600">{{ form.errors.sort_order }}</div>
                                </div>
                                <div class="flex items-center justify-between space-x-2 py-2">
                                    <div class="space-y-0.5">
                                        <Label for="is_active">Active Status</Label>
                                        <p class="text-xs text-muted-foreground">Whether this plan is available for subscription.</p>
                                    </div>
                                    <Switch
                                        id="is_active"
                                        :checked="form.is_active"
                                        @update:checked="(val: boolean) => form.is_active = val"
                                    />
                                </div>
                                <div class="flex items-center justify-between space-x-2 py-2">
                                    <div class="space-y-0.5">
                                        <Label for="is_most_value">Most Value Badge</Label>
                                        <p class="text-xs text-muted-foreground">Show the Most Value badge for this plan on the landing page.</p>
                                    </div>
                                    <Switch
                                        id="is_most_value"
                                        :checked="form.is_most_value"
                                        @update:checked="(val: boolean) => form.is_most_value = val"
                                    />
                                </div>
                                <div class="flex items-center justify-between space-x-2 py-2">
                                    <div class="space-y-0.5">
                                        <Label for="custom_pricing">Custom Pricing</Label>
                                        <p class="text-xs text-muted-foreground">Show Custom instead of a price and require manual contact.</p>
                                    </div>
                                    <Switch
                                        id="custom_pricing"
                                        :checked="form.custom_pricing"
                                        @update:checked="(val: boolean) => form.custom_pricing = val"
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Features</CardTitle>
                                <CardDescription>List the features included in this plan.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div v-for="(feature, index) in form.features" :key="index" class="flex items-center gap-2">
                                    <Input v-model="form.features[index]" placeholder="e.g. Unlimited users" />
                                    <Button type="button" variant="ghost" size="icon" @click="removeFeature(index)" class="text-red-500">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                                <Button type="button" variant="outline" size="sm" @click="addFeature" class="w-full">
                                    <Plus class="h-4 w-4 mr-2" /> Add Feature
                                </Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Translations</CardTitle>
                                <CardDescription>
                                    Override the plan text and display order per language. Leave fields matching the default plan if no special translation is needed.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-5">
                                <div
                                    v-for="localeMeta in supportedLocales"
                                    :key="localeMeta.code"
                                    class="space-y-4 rounded-lg border p-4"
                                    :dir="localeMeta.direction"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <div class="text-sm font-semibold">{{ localeMeta.name }} ({{ localeMeta.code.toUpperCase() }})</div>
                                            <div class="text-xs text-muted-foreground">{{ localeMeta.native }}</div>
                                        </div>
                                        <div class="w-32 space-y-1">
                                            <Label :for="`translation-${localeMeta.code}-sort`">Display Order</Label>
                                            <Input
                                                :id="`translation-${localeMeta.code}-sort`"
                                                v-model.number="form.translations[localeMeta.code].sort_order"
                                                type="number"
                                                min="0"
                                                step="1"
                                            />
                                            <div
                                                v-if="form.errors[`translations.${localeMeta.code}.sort_order`]"
                                                class="text-xs text-red-600"
                                            >
                                                {{ form.errors[`translations.${localeMeta.code}.sort_order`] }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <Label :for="`translation-${localeMeta.code}-name`">Name</Label>
                                            <Input
                                                :id="`translation-${localeMeta.code}-name`"
                                                v-model="form.translations[localeMeta.code].name"
                                                :placeholder="form.name"
                                            />
                                            <div
                                                v-if="form.errors[`translations.${localeMeta.code}.name`]"
                                                class="text-sm text-red-600"
                                            >
                                                {{ form.errors[`translations.${localeMeta.code}.name`] }}
                                            </div>
                                        </div>
                                        <div class="space-y-2">
                                            <Label :for="`translation-${localeMeta.code}-description`">Description</Label>
                                            <Textarea
                                                :id="`translation-${localeMeta.code}-description`"
                                                v-model="form.translations[localeMeta.code].description"
                                                :placeholder="form.description"
                                            />
                                            <div
                                                v-if="form.errors[`translations.${localeMeta.code}.description`]"
                                                class="text-sm text-red-600"
                                            >
                                                {{ form.errors[`translations.${localeMeta.code}.description`] }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <Label>Features</Label>
                                        <div class="space-y-2">
                                            <Input
                                                v-for="(_, index) in form.features"
                                                :key="`${localeMeta.code}-feature-${index}`"
                                                v-model="form.translations[localeMeta.code].features[index]"
                                                :placeholder="form.features[index] || `Feature ${index + 1}`"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Feature Access</CardTitle>
                                <CardDescription>
                                    Toggle the product modules available for tenants on this plan. Enabled: {{ enabledFeatureCount }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div v-for="field in featureFlagFields" :key="field.key" class="space-y-3 rounded-lg border p-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <Label :for="field.key" class="text-sm font-medium">{{ field.label }}</Label>
                                            <Switch
                                                :checked="isFeatureEnabled(field.key)"
                                                @update:checked="(val: boolean) => setFeatureEnabled(field.key, val)"
                                            />
                                        </div>
                                        <p class="text-xs text-muted-foreground">{{ field.helper }}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{{ t('dashboard.super_admin.plans.index.usage_summary') }}</CardTitle>
                                <CardDescription>
                                    {{ t('dashboard.super_admin.plans.index.usage_summary_description') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div
                                    v-for="field in summaryFields"
                                    :key="field.key"
                                    class="rounded-lg border bg-muted/30 p-3"
                                >
                                    <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        {{ field.label }}
                                    </div>
                                    <div class="mt-1 text-lg font-semibold">
                                        {{ formatSummaryValue(field.key, field.value) }}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div class="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Pricing (Monthly)</CardTitle>
                                <CardDescription>Set the monthly subscription price.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="monthly_price">Price *</Label>
                                    <Input id="monthly_price" v-model.number="form.monthly_price" type="number" step="0.01" :required="!form.custom_pricing" :disabled="form.custom_pricing" />
                                    <div v-if="form.errors.monthly_price" class="text-sm text-red-600">{{ form.errors.monthly_price }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="monthly_price_id">Price ID (Stripe/Payment Link)</Label>
                                    <Input id="monthly_price_id" v-model="form.monthly_price_id" placeholder="price_..." :disabled="form.custom_pricing" />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Pricing (Yearly)</CardTitle>
                                <CardDescription>Set the yearly subscription price.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="yearly_price">Price *</Label>
                                    <Input id="yearly_price" v-model.number="form.yearly_price" type="number" step="0.01" :required="!form.custom_pricing" :disabled="form.custom_pricing" />
                                    <div v-if="form.errors.yearly_price" class="text-sm text-red-600">{{ form.errors.yearly_price }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="yearly_price_id">Price ID (Stripe/Payment Link)</Label>
                                    <Input id="yearly_price_id" v-model="form.yearly_price_id" placeholder="price_..." :disabled="form.custom_pricing" />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Pricing (One-time)</CardTitle>
                                <CardDescription>Optional one-time purchase price.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="one_time_price">Price</Label>
                                    <Input id="one_time_price" v-model.number="form.one_time_price" type="number" step="0.01" :disabled="form.custom_pricing" />
                                    <div v-if="form.errors.one_time_price" class="text-sm text-red-600">{{ form.errors.one_time_price }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="one_time_price_id">Price ID</Label>
                                    <Input id="one_time_price_id" v-model="form.one_time_price_id" placeholder="price_..." :disabled="form.custom_pricing" />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Limits</CardTitle>
                                <CardDescription>Leave a limit blank to make it unlimited for this plan.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div v-for="field in limitFields" :key="field.key" class="space-y-3 rounded-lg border p-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <Label :for="field.key" class="text-sm font-medium">{{ field.label }}</Label>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs text-muted-foreground">Unlimited</span>
                                                <Switch
                                                    :checked="isLimitEnabled(field.key)"
                                                    @update:checked="(val: boolean) => setLimitEnabled(field.key, val)"
                                                />
                                            </div>
                                        </div>
                                        <Input
                                            :id="field.key"
                                            v-model="form[field.key]"
                                            :disabled="!isLimitEnabled(field.key)"
                                            type="number"
                                            min="1"
                                            step="1"
                                            :placeholder="field.placeholder"
                                        />
                                        <p class="text-xs text-muted-foreground">{{ field.helper }}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Updating...' : 'Update Plan' }}
                    </Button>
                    <Link href="/superadmin/plans">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                </div>
            </form>
        </main>
    </SuperAdminLayout>
</template>
