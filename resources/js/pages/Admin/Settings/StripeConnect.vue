<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
        stripe_account_id: string | null;
        stripe_onboarded_at: string | null;
        stripe_details_submitted: boolean;
        stripe_charges_enabled: boolean;
        stripe_payouts_enabled: boolean;
        stripe_currency: string | null;
    };
    stripe: {
        platform_configured: boolean;
        can_accept_checkout: boolean;
    };
    actions: {
        connect: string;
        refresh: string;
        login_link: string;
    };
}>();

const page = usePage<any>();
const { t } = useTrans();
const translationRoot = 'dashboard.admin.stripe_connect';
const translate = (key: string) => t(`${translationRoot}.${key}`);

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

function connectStripe() {
    router.post(props.actions.connect);
}

function openStripeDashboard() {
    router.post(props.actions.login_link);
}
</script>

<template>
    <Head :title="translate('title')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ translate('title') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ translate('description') }}
                    </p>
                </div>
            </div>

            <div v-if="flashSuccess" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ flashError }}
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="rounded-lg border p-5 lg:col-span-2">
                    <h2 class="mb-4 text-lg font-semibold">{{ translate('connection_status') }}</h2>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="rounded-md border p-4">
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">{{ translate('tenant') }}</div>
                            <div class="mt-1 font-medium">{{ tenant.name }}</div>
                            <div class="text-sm text-muted-foreground">{{ tenant.slug }}</div>
                        </div>

                        <div class="rounded-md border p-4">
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">{{ translate('stripe_account_id') }}</div>
                            <div class="mt-1 break-all font-mono text-sm">
                                {{ tenant.stripe_account_id || translate('not_connected') }}
                            </div>
                        </div>

                        <div class="rounded-md border p-4">
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">{{ translate('charges_enabled') }}</div>
                            <div class="mt-1 font-medium" :class="tenant.stripe_charges_enabled ? 'text-emerald-600' : 'text-amber-600'">
                                {{ tenant.stripe_charges_enabled ? translate('yes') : translate('no') }}
                            </div>
                        </div>

                        <div class="rounded-md border p-4">
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">{{ translate('payouts_enabled') }}</div>
                            <div class="mt-1 font-medium" :class="tenant.stripe_payouts_enabled ? 'text-emerald-600' : 'text-amber-600'">
                                {{ tenant.stripe_payouts_enabled ? translate('yes') : translate('no') }}
                            </div>
                        </div>

                        <div class="rounded-md border p-4">
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">{{ translate('details_submitted') }}</div>
                            <div class="mt-1 font-medium" :class="tenant.stripe_details_submitted ? 'text-emerald-600' : 'text-amber-600'">
                                {{ tenant.stripe_details_submitted ? translate('yes') : translate('no') }}
                            </div>
                        </div>

                        <div class="rounded-md border p-4">
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">{{ translate('default_currency') }}</div>
                            <div class="mt-1 font-medium uppercase">{{ tenant.stripe_currency || translate('not_set') }}</div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 rounded-lg border p-5">
                    <h2 class="text-lg font-semibold">{{ translate('actions') }}</h2>

                    <div class="rounded-md border p-3 text-sm">
                        <div class="font-medium">{{ translate('platform_stripe') }}</div>
                        <div :class="stripe.platform_configured ? 'text-emerald-600' : 'text-red-600'">
                            {{ stripe.platform_configured ? translate('configured') : translate('not_configured') }}
                        </div>
                    </div>

                    <div class="rounded-md border p-3 text-sm">
                        <div class="font-medium">{{ translate('checkout_ready') }}</div>
                        <div :class="stripe.can_accept_checkout ? 'text-emerald-600' : 'text-amber-600'">
                            {{ stripe.can_accept_checkout ? translate('ready_for_booking_payments') : translate('not_ready_yet') }}
                        </div>
                    </div>

                    <Button class="w-full" :disabled="!stripe.platform_configured" @click="connectStripe">
                        {{ tenant.stripe_account_id ? translate('continue_stripe_onboarding') : translate('connect_stripe') }}
                    </Button>

                    <a
                        :href="actions.refresh"
                        class="block w-full rounded-md border px-4 py-2 text-center text-sm"
                    >
                        {{ translate('refresh_onboarding_link') }}
                    </a>

                    <Button
                        type="button"
                        variant="outline"
                        class="w-full"
                        :disabled="!tenant.stripe_account_id"
                        @click="openStripeDashboard"
                    >
                        {{ translate('open_stripe_express_dashboard') }}
                    </Button>
                </div>
            </div>
        </main>
    </AdminLayout>
</template>
