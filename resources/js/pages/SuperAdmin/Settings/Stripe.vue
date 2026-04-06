<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

interface StripeSettings {
    key: string;
    secret: string;
    webhook_secret: string;
    webhook_tolerance: number;
    currency: string;
    currency_locale: string;
    path: string;
    logger: string;
}

const props = defineProps<{
    settings: StripeSettings;
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const form = useForm<{
    settings: StripeSettings;
}>({
    settings: JSON.parse(JSON.stringify(props.settings)),
});

const submit = () => {
    const updateUrl = typeof window !== 'undefined' ? window.location.pathname : '/superadmin/settings/stripe';

    form.put(updateUrl, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="localize('Stripe Settings', 'إعدادات Stripe')" />

    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Stripe Settings', 'إعدادات Stripe') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Configure Stripe and Cashier credentials from dashboard settings.', 'اضبط بيانات Stripe و Cashier من إعدادات اللوحة.') }}
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                </Button>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('API Credentials', 'بيانات API') }}</CardTitle>
                        <CardDescription>{{ localize('Set publishable and secret keys from your Stripe dashboard.', 'اضبط المفتاح العام والمفتاح السري من لوحة Stripe الخاصة بك.') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="stripe_key">{{ localize('Publishable Key', 'المفتاح العام') }}</Label>
                            <Input id="stripe_key" v-model="form.settings.key" placeholder="pk_live_..." />
                            <p v-if="form.errors['settings.key']" class="text-sm text-red-600">
                                {{ form.errors['settings.key'] }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="stripe_secret">{{ localize('Secret Key', 'المفتاح السري') }}</Label>
                            <Input id="stripe_secret" v-model="form.settings.secret" type="password" placeholder="sk_live_..." />
                            <p v-if="form.errors['settings.secret']" class="text-sm text-red-600">
                                {{ form.errors['settings.secret'] }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Webhook', 'Webhook') }}</CardTitle>
                        <CardDescription>{{ localize('Use the same webhook secret and path configured in Stripe.', 'استخدم نفس سر الـ webhook والمسار المضبوطين في Stripe.') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="stripe_webhook_secret">{{ localize('Webhook Secret', 'سر الـ Webhook') }}</Label>
                            <Input id="stripe_webhook_secret" v-model="form.settings.webhook_secret" type="password" placeholder="whsec_..." />
                            <p v-if="form.errors['settings.webhook_secret']" class="text-sm text-red-600">
                                {{ form.errors['settings.webhook_secret'] }}
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="stripe_webhook_tolerance">{{ localize('Webhook Tolerance (seconds)', 'سماحية الـ Webhook (بالثواني)') }}</Label>
                                <Input id="stripe_webhook_tolerance" v-model.number="form.settings.webhook_tolerance" type="number" min="0" />
                                <p v-if="form.errors['settings.webhook_tolerance']" class="text-sm text-red-600">
                                    {{ form.errors['settings.webhook_tolerance'] }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="cashier_path">{{ localize('Cashier Path', 'مسار Cashier') }}</Label>
                                <Input id="cashier_path" v-model="form.settings.path" placeholder="stripe" />
                                <p v-if="form.errors['settings.path']" class="text-sm text-red-600">
                                    {{ form.errors['settings.path'] }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Billing Defaults', 'الإعدادات الافتراضية للفوترة') }}</CardTitle>
                        <CardDescription>{{ localize('Default currency and formatting used by Cashier invoices and totals.', 'العملة والتنسيق الافتراضيان المستخدمان في فواتير Cashier والإجماليات.') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="space-y-2">
                                <Label for="cashier_currency">{{ localize('Currency', 'العملة') }}</Label>
                                <Input id="cashier_currency" v-model="form.settings.currency" placeholder="usd" maxlength="3" />
                                <p v-if="form.errors['settings.currency']" class="text-sm text-red-600">
                                    {{ form.errors['settings.currency'] }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="cashier_currency_locale">{{ localize('Currency Locale', 'لغة العملة') }}</Label>
                                <Input id="cashier_currency_locale" v-model="form.settings.currency_locale" placeholder="en" />
                                <p v-if="form.errors['settings.currency_locale']" class="text-sm text-red-600">
                                    {{ form.errors['settings.currency_locale'] }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="cashier_logger">{{ localize('Logger Channel (optional)', 'قناة السجل (اختياري)') }}</Label>
                                <Input id="cashier_logger" v-model="form.settings.logger" placeholder="stack" />
                                <p v-if="form.errors['settings.logger']" class="text-sm text-red-600">
                                    {{ form.errors['settings.logger'] }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                    </Button>
                </div>
            </form>
        </main>
    </SuperAdminLayout>
</template>
