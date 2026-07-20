<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AuthLanguageSwitcher from '@/components/AuthLanguageSwitcher.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { withLocalePrefix } from '@/lib/utils';
import { register as mainRegister } from '@/routes';
import { store as mainLoginStore } from '@/routes/login';
import { request as mainPasswordRequest } from '@/routes/password';
import { register as tenantRegister } from '@/routes/tenant';
import { store as tenantLoginStore } from '@/routes/tenant/login';
import { request as tenantPasswordRequest } from '@/routes/tenant/password';
import { Form, Head, usePage } from '@inertiajs/vue3';
import {
    LoaderCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const page = usePage<any>();
const { t } = useTrans();
const currentTenant = computed(() => page.props.current_tenant);
const baseProtocol = computed(() =>
    typeof window !== 'undefined' ? window.location.protocol : 'https:',
);
const buildUrl = (host: string, path: string) =>
    `${baseProtocol.value}//${host}${path}`;

const loginAction = computed(() => {
    const slug = currentTenant.value?.slug;
    return (slug ? tenantLoginStore.form(slug) : mainLoginStore.form()) as any;
});
const localizedAuthUrl = (url: string) =>
    withLocalePrefix(url, page.props.locale, page.props.available_locales);
const registerUrl = computed(() => {
    const slug = currentTenant.value?.slug;
    return localizedAuthUrl(slug ? tenantRegister(slug).url : mainRegister().url);
});
const forgotPasswordUrl = computed(() => {
    const slug = currentTenant.value?.slug;
    return localizedAuthUrl(slug ? tenantPasswordRequest(slug).url : mainPasswordRequest().url);
});

</script>

<template>
    <HomeLayout :show-locale-switcher="false">
        <Head :title="t('auth.login_title')" />

        <div
            class="relative flex min-h-[90vh] items-center justify-center px-4 sm:px-6 lg:px-8"
        >
            <div class="absolute top-4 ltr:right-4 rtl:left-4 z-50">
                <AuthLanguageSwitcher />
            </div>

            <div class="w-full max-w-md space-y-8">
                <!-- Header -->
                <div class="text-center">
                    <h1 class="mb-2 text-3xl font-bold text-gray-900">
                        {{ t('auth.welcome_back') }}
                    </h1>
                    <p class="text-gray-600">
                        {{ t('auth.sign_in_continue') }}
                    </p>
                </div>

                <!-- Status Message -->
                <div
                    v-if="status"
                    class="rounded-lg border border-green-200 bg-green-50 p-4 text-center"
                >
                    <p class="text-sm font-medium text-green-800">
                        {{ status }}
                    </p>
                </div>

                <!-- Login Form -->
                <div
                    class="rounded-xl border border-gray-200 bg-white p-8 shadow-sm"
                >
                    <Form
                        v-bind="loginAction"
                        :reset-on-success="['password']"
                        v-slot="{ errors, processing }"
                        class="space-y-6"
                    >
                        <!-- Email Field -->
                        <div>
                            <Label
                                for="email"
                                class="mb-2 block text-sm font-semibold text-gray-900"
                            >
                                {{ t('auth.email') }}
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autofocus
                                :tabindex="1"
                                autocomplete="email"
                                :placeholder="t('auth.placeholder_email')"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-primary focus:ring-2 focus:ring-primary"
                            />
                            <InputError :message="errors.email" class="mt-1" />
                        </div>

                        <!-- Password Field -->
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <Label
                                    for="password"
                                    class="block text-sm font-semibold text-gray-900"
                                >
                                    {{ t('auth.password') }}
                                </Label>
                                <TextLink
                                    v-if="canResetPassword"
                                    :href="forgotPasswordUrl"
                                    class="text-sm font-medium text-primary hover:text-primary/80"
                                    :tabindex="5"
                                >
                                    {{ t('auth.forgot_password') }}
                                </TextLink>
                            </div>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                required
                                :tabindex="2"
                                autocomplete="current-password"
                                :placeholder="t('auth.placeholder_password')"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-primary focus:ring-2 focus:ring-primary"
                            />
                            <InputError
                                :message="errors.password"
                                class="mt-1"
                            />
                        </div>

                        <!-- Remember Me -->
                        <div class="flex items-center">
                            <Label
                                for="remember"
                                class="flex cursor-pointer items-center space-x-3"
                            >
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    :tabindex="3"
                                    class="rounded border-gray-300 text-primary focus:ring-primary"
                                />
                                <span class="text-sm text-gray-700">{{
                                    t('auth.remember_me')
                                }}</span>
                            </Label>
                        </div>

                        <!-- Submit Button -->
                        <Button
                            type="submit"
                            class="flex w-full items-center justify-center rounded-lg bg-primary px-4 py-3 font-semibold text-primary-foreground transition-colors duration-200 hover:bg-primary/90"
                            :tabindex="4"
                            :disabled="processing"
                            data-test="login-button"
                        >
                            <LoaderCircle
                                v-if="processing"
                                class="mr-2 h-5 w-5 animate-spin"
                            />
                            {{
                                processing
                                    ? t('auth.signing_in')
                                    : t('auth.sign_in')
                            }}
                        </Button>

                        <!-- Sign Up Link -->
                        <div class="border-t border-gray-200 pt-4 text-center">
                            <p class="text-sm text-gray-600">
                                {{ t('auth.dont_have_account') }}
                                <TextLink
                                    :href="registerUrl"
                                    :tabindex="5"
                                    class="ml-1 font-semibold text-primary hover:text-primary/80"
                                >
                                    {{ t('auth.create_one') }}
                                </TextLink>
                            </p>
                        </div>
                    </Form>

                    <template v-if="currentTenant">
                        <div class="relative flex items-center py-6">
                            <div class="flex-grow border-t border-gray-200"></div>
                            <span class="flex-shrink-0 px-4 text-xs font-medium text-gray-500 uppercase">{{ t('auth.or_continue_with') }}</span>
                            <div class="flex-grow border-t border-gray-200"></div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <a
                                :href="buildUrl(page.props.app_url_base, `/auth/google/redirect?tenant=${currentTenant.slug}`)"
                                class="flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
                            >
                                <svg class="mr-2 h-5 w-5" viewBox="0 0 24 24">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                                </svg>
                                {{ t('auth.google') }}
                            </a>
                            <a
                                :href="buildUrl(page.props.app_url_base, `/auth/apple/redirect?tenant=${currentTenant.slug}`)"
                                class="flex h-11 items-center justify-center rounded-lg border border-primary bg-primary font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90"
                            >
                                <svg class="mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M16.365 21.492c-1.373.91-2.073.932-3.418.932-1.36 0-2.457-.145-3.358-.888-4.706-3.896-7.143-12.001-2.924-17.159C8.381 2.27 10.64 1 12.87 1c1.372 0 2.515.548 3.518.548 1.033 0 2.455-.662 4.098-.598 2.053.082 3.868.868 4.908 2.39-4.226 2.593-3.483 8.653.861 10.4-1.127 3.238-3.085 6.425-5.61 8.8-1.026.969-2.19 1.496-3.235 1.496a7.41 7.41 0 0 1-1.045-.544zM16.124 6.78c-.2-2.14 1.5-4.04 3.79-4.38.38 2.39-1.9 4.31-3.79 4.38z" />
                                </svg>
                                {{ t('auth.apple') }}
                            </a>
                        </div>
                    </template>
                </div>

                <!-- Additional Info -->
                <div class="text-center">
                    <p class="text-xs text-gray-500">
                        {{ t('auth.terms_notice') }}
                    </p>
                </div>
            </div>
        </div>
    </HomeLayout>
</template>
