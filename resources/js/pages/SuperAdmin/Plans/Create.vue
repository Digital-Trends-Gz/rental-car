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

const props = defineProps<{
    featureFlags: Array<{ key: string; label: string; helper: string }>;
}>();

type FeatureFlagField = {
    key: string;
    label: string;
    helper: string;
};

const featureFlagFields = props.featureFlags as FeatureFlagField[];

const buildFeatureFlags = () => Object.fromEntries(
    featureFlagFields.map((item) => [item.key, true]),
) as Record<string, boolean>;

const form = useForm({
    name: '',
    description: '',
    sort_order: 0,
    features: [''],
    feature_flags: buildFeatureFlags(),
    monthly_price: 0,
    monthly_price_id: '',
    yearly_price: 0,
    yearly_price_id: '',
    one_time_price: 0,
    one_time_price_id: '',
    max_employees: null as number | null,
    max_branches: null as number | null,
    max_cars: null as number | null,
    max_contracts: null as number | null,
    openai_requests_per_day: null as number | null,
    is_active: true,
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

const addFeature = () => {
    form.features.push('');
};

const removeFeature = (index: number) => {
    form.features.splice(index, 1);
    if (form.features.length === 0) {
        form.features.push('');
    }
};

const submit = () => {
    form.post('/superadmin/plans', { preserveScroll: true });
};
</script>

<template>
    <Head title="Create Subscription Plan" />
    <SuperAdminLayout>
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center gap-4">
                <Link href="/superadmin/plans">
                    <Button variant="outline">Back</Button>
                </Link>
                <h1 class="text-2xl font-semibold">Create Subscription Plan</h1>
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
                                    <Input id="monthly_price" v-model.number="form.monthly_price" type="number" step="0.01" required />
                                    <div v-if="form.errors.monthly_price" class="text-sm text-red-600">{{ form.errors.monthly_price }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="monthly_price_id">Price ID (Stripe/Payment Link)</Label>
                                    <Input id="monthly_price_id" v-model="form.monthly_price_id" placeholder="price_..." />
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
                                    <Input id="yearly_price" v-model.number="form.yearly_price" type="number" step="0.01" required />
                                    <div v-if="form.errors.yearly_price" class="text-sm text-red-600">{{ form.errors.yearly_price }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="yearly_price_id">Price ID (Stripe/Payment Link)</Label>
                                    <Input id="yearly_price_id" v-model="form.yearly_price_id" placeholder="price_..." />
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
                                    <Input id="one_time_price" v-model.number="form.one_time_price" type="number" step="0.01" />
                                    <div v-if="form.errors.one_time_price" class="text-sm text-red-600">{{ form.errors.one_time_price }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="one_time_price_id">Price ID</Label>
                                    <Input id="one_time_price_id" v-model="form.one_time_price_id" placeholder="price_..." />
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
                        {{ form.processing ? 'Creating...' : 'Create Plan' }}
                    </Button>
                    <Link href="/superadmin/plans">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                </div>
            </form>
        </main>
    </SuperAdminLayout>
</template>
