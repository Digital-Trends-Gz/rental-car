<script setup lang="ts">
import authHero from '@/assets/auth-hero.jpg';
import InputError from '@/components/InputError.vue';
import AuthLanguageSwitcher from '@/components/AuthLanguageSwitcher.vue';
import { useBrandTheme } from '@/composables/useBrandTheme';
import { useTrans } from '@/composables/useTrans';
import { withLocalePrefix } from '@/lib/utils';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { register as mainRegister } from '@/routes';
import { landing as mainAuthLanding } from '@/routes/auth';
import { request as mainPasswordRequest } from '@/routes/password';
import { landing as tenantAuthLanding } from '@/routes/tenant/auth/index.ts';
import { request as tenantPasswordRequest } from '@/routes/tenant/password/index.ts';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const { t } = useTrans();
const { themeVars } = useBrandTheme();

const ERROR_MESSAGES: Record<string, string> = {
    'This tenant account is inactive. Please contact support.': 'auth.tenant_account_inactive',
    'This tenant subscription has expired. Please contact your administrator.': 'auth.tenant_subscription_expired',
    'Your plan has expired. Please login and renew your subscription.': 'auth.plan_expired',
    'Your trial period has ended. Please contact your administrator.': 'auth.trial_ended',
    'You are not authorized to access this area.': 'auth.unauthorized_access',
};

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const page = usePage<any>();

const translateError = (message?: string) => {
    if (!message) return message;
    const key = ERROR_MESSAGES[message] || (message.startsWith('auth.') ? message : undefined);
    if (key) {
        const translated = t(key);
        return translated !== key ? translated : message;
    }
    return message;
};

const flashError = computed(() => {
    const error = page.props.flash?.error;
    if (!error) return undefined;
    return translateError(error);
});
const currentTenant = computed(() => page.props.current_tenant);
const authSideImage = computed(() => {
    const images = page.props.app_branding?.register_hero_images || {};
    const currentLocale = String(page.props.locale || 'en');

    return images[currentLocale] || images.en || authHero;
});

const loginAction = computed(() => {
    return {
        action: localizedAuthUrl('/tenant/login'),
        method: 'post',
    };
});

const localizedAuthUrl = (url: string) =>
    withLocalePrefix(url, page.props.locale, page.props.available_locales);

const registerUrl = computed(() => {
    return localizedAuthUrl(mainRegister().url);
});

const forgotPasswordUrl = computed(() => {
    const slug = currentTenant.value?.slug;
    return localizedAuthUrl(slug ? tenantPasswordRequest(slug).url : mainPasswordRequest().url);
});

const landingUrl = computed(() => {
    const slug = currentTenant.value?.slug;
    return localizedAuthUrl(slug ? tenantAuthLanding(slug).url : mainAuthLanding().url);
});
</script>

<template>
    <Head :title="t('auth.tenant_login_seo_title')">
        <meta
            name="description"
            :content="t('auth.tenant_login_seo_description')"
        />
    </Head>

    <div class="flex min-h-screen bg-white" :style="themeVars">
        <div class="relative hidden overflow-hidden lg:flex lg:w-1/2">
            <img
                :src="authSideImage"
                alt="Car4U background"
                class="absolute inset-0 h-full w-full object-cover object-center"
            />
        </div>

        <div
            class="relative flex w-full flex-col items-center justify-center px-6 py-8 pt-14 sm:px-8 sm:pt-16 lg:w-1/2"
        >
            <div class="absolute top-4 z-50 ltr:right-4 sm:ltr:right-8 rtl:left-4 sm:rtl:left-8">
                <AuthLanguageSwitcher />
            </div>

            <div class="w-full max-w-md space-y-6">
                <div class="space-y-2">
                    <h1 class="text-3xl font-bold text-gray-900">
                        {{ t('auth.tenant_sign_in') }}
                    </h1>
                    <p class="text-gray-500">
                        {{ t('auth.tenant_welcome') }}
                    </p>
                </div>

                <div
                    v-if="status"
                    class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
                >
                    {{ status }}
                </div>

                <div
                    v-if="flashError"
                    class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ flashError }}
                </div>

                <Form
                    v-bind="loginAction"
                    :reset-on-success="['password']"
                    v-slot="{ errors, processing }"
                    class="space-y-5"
                >
                    <div class="space-y-2">
                        <Label
                            for="email"
                            class="text-sm font-semibold text-gray-800"
                            >{{ t('auth.email') }}</Label
                        >
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            :placeholder="t('auth.placeholder_email')"
                            required
                            autofocus
                            autocomplete="email"
                            class="h-11 border-gray-300"
                        />
                        <InputError :message="translateError(errors.email)" />
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label
                                for="password"
                                class="text-sm font-semibold text-gray-800"
                                >{{ t('auth.password') }}</Label
                            >
                            <Link
                                v-if="canResetPassword"
                                :href="forgotPasswordUrl"
                                class="text-sm font-medium text-primary hover:underline"
                            >
                                {{ t('auth.forgot_password') }}
                            </Link>
                        </div>

                        <Input
                            id="password"
                            name="password"
                            type="password"
                            :placeholder="t('auth.placeholder_password')"
                            required
                            autocomplete="current-password"
                            class="h-11 border-gray-300"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox id="remember" name="remember" />
                        <label for="remember" class="text-sm text-gray-600"
                            >{{ t('auth.remember_me_short') }}</label
                        >
                    </div>

                    <button
                        type="submit"
                        class="h-12 w-full rounded-full bg-primary font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="processing"
                    >
                        {{ processing ? t('auth.signing_in') : t('auth.sign_in') }}
                    </button>
                </Form>

                <p class="text-center text-sm text-gray-600">
                    {{ t('auth.dont_have_account') }}
                    <Link
                        :href="registerUrl"
                        class="ml-1 font-semibold text-primary hover:underline"
                    >
                        {{ t('auth.create_one') }}
                    </Link>
                </p>

         
            </div>
        </div>
    </div>
</template>
