<script setup lang="ts">
import authHero from '@/assets/auth-hero.jpg';
import {
    home as mainHome,
    register as mainRegister,
    tenantLogin as mainTenantLogin,
} from '@/routes';
import {
    home as tenantHome,
    register as tenantRegister,
    tenantLogin as tenantTenantLogin,
} from '@/routes/tenant';
import { withLocalePrefix } from '@/lib/utils';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<any>();
const currentTenant = computed(() => page.props.current_tenant);
const localizedUrl = (url: string) =>
    withLocalePrefix(url, page.props.locale, page.props.available_locales);

const homeUrl = computed(() => {
    const slug = currentTenant.value?.slug;
    return localizedUrl(slug ? tenantHome(slug).url : mainHome().url);
});

const loginUrl = computed(() => {
    const slug = currentTenant.value?.slug;
    return localizedUrl(slug ? tenantTenantLogin(slug).url : mainTenantLogin().url);
});

const registerUrl = computed(() => {
    const slug = currentTenant.value?.slug;
    return localizedUrl(slug ? tenantRegister(slug).url : mainRegister().url);
});
</script>

<template>
    <Head title="Auth Landing" />

    <main class="relative min-h-screen overflow-hidden bg-slate-950 text-white">
        <div class="absolute inset-0 opacity-25">
            <img
                :src="authHero"
                alt="Auth background"
                class="h-full w-full object-cover"
            />
        </div>
        <div
            class="absolute inset-0 bg-gradient-to-b from-slate-950/80 via-slate-900/85 to-slate-950"
        />

        <div
            class="relative mx-auto flex min-h-screen w-full max-w-6xl flex-col justify-center px-6 py-12"
        >
            <div class="mb-8 flex justify-end">
                <Link
                    :href="homeUrl"
                    class="text-sm font-medium text-slate-200 hover:text-white"
                    >Back to website</Link
                >
            </div>

            <div class="grid items-center gap-10 lg:grid-cols-2">
                <div class="space-y-6">
                    <p
                        class="inline-flex rounded-full border border-blue-400/40 bg-blue-500/10 px-3 py-1 text-xs font-semibold tracking-wider text-blue-200 uppercase"
                    >
                        Account Access
                    </p>
                    <h1 class="text-4xl leading-tight font-bold sm:text-5xl">
                        Manage your rentals from one secure place.
                    </h1>
                    <p class="max-w-xl text-base text-slate-200 sm:text-lg">
                        Continue with your account to track reservations, view
                        invoices, and manage your fleet access in real time.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <Link
                            :href="loginUrl"
                            class="inline-flex h-11 items-center justify-center rounded-full bg-gradient-to-r from-blue-600 to-blue-500 px-6 text-sm font-semibold text-white shadow-lg transition hover:brightness-110"
                        >
                            Login
                        </Link>
                        <Link
                            :href="registerUrl"
                            class="inline-flex h-11 items-center justify-center rounded-full border border-slate-300/50 px-6 text-sm font-semibold text-slate-100 transition hover:border-white"
                        >
                            Create Account
                        </Link>
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-slate-200/20 bg-white/10 p-6 shadow-2xl backdrop-blur"
                >
                    <h2 class="text-2xl font-semibold">Start here</h2>
                    <p class="mt-2 text-sm text-slate-200">
                        Choose login if you already have an account, or create a
                        new one in under a minute.
                    </p>

                    <div class="mt-6 space-y-3 text-sm text-slate-100">
                        <div
                            class="rounded-lg border border-white/15 bg-white/5 px-4 py-3"
                        >
                            1. Login to access your dashboard and reservations.
                        </div>
                        <div
                            class="rounded-lg border border-white/15 bg-white/5 px-4 py-3"
                        >
                            2. Register a new account if you are a first-time
                            customer.
                        </div>
                        <div
                            class="rounded-lg border border-white/15 bg-white/5 px-4 py-3"
                        >
                            3. Use your tenant domain for company-specific
                            access.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>
