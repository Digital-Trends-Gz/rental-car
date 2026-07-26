<script setup lang="ts">
import authHero from '@/assets/auth-hero.jpg';
import InputError from '@/components/InputError.vue';
import AuthLanguageSwitcher from '@/components/AuthLanguageSwitcher.vue';
import { useBrandTheme } from '@/composables/useBrandTheme';
import { useTrans } from '@/composables/useTrans';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as mainRegisterStore } from '@/routes/register';
import { store as tenantRegisterStore } from '@/routes/tenant/register/index.ts';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { Home } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const { direction, locale, t } = useTrans();
const { themeVars } = useBrandTheme();

const page = usePage<any>();
const currentTenant = computed(() => page.props.current_tenant);
const availableLocaleCodes = computed<string[]>(() =>
    Array.isArray(page.props.available_locales) ? page.props.available_locales.map(String) : [],
);

type RegisterPrefill = {
    name?: string | null;
    email?: string | null;
    custom_domain?: string | null;
    country_iso2?: string | null;
    phone_country_code?: string | null;
    phone_national?: string | null;
    phone?: string | null;
    commercial_registration_number?: string | null;
    tax_number?: string | null;
    civil_number?: string | null;
    partner_seats?: number | string | null;
};

type CountryOption = {
    iso2: string;
    name_en: string;
    name_ar: string;
    dial_code: string;
};

const props = withDefaults(defineProps<{ prefill?: RegisterPrefill; countries?: CountryOption[] }>(), {
    prefill: () => ({}),
    countries: () => [],
});

const registerAction = computed(() => {
    const slug = currentTenant.value?.slug;
    return (slug ? tenantRegisterStore.form(slug) : mainRegisterStore.form()) as any;
});
const baseProtocol = computed(() =>
    typeof window !== 'undefined' ? window.location.protocol : 'https:',
);
const buildUrl = (host: string, path: string) =>
    `${baseProtocol.value}//${host}${path}`;
const localizedPath = (path: string) => {
    const normalizedPath = path.startsWith('/') ? path : `/${path}`;
    const localeCode = String(page.props.locale || '').trim();

    if (!localeCode || !availableLocaleCodes.value.includes(localeCode)) {
        return normalizedPath;
    }

    return normalizedPath === '/' ? `/${localeCode}` : `/${localeCode}${normalizedPath}`;
};

const loginUrl = computed(() => {
    const slug = currentTenant.value?.slug;
    const loginPath = localizedPath(slug ? '/login' : '/tenant/login');

    return slug
        ? buildUrl(`${slug}.${page.props.app_url_base}`, loginPath)
        : buildUrl(page.props.app_url_base, loginPath);
});
const homeUrl = computed(() => localizedPath('/'));
const localizedPublicPath = (path: string) => localizedPath(path);
const termsUrl = computed(() => localizedPublicPath('/terms-of-use'));

const isArabic = computed(() => page.props.locale === 'ar');
const isRtl = computed(() => direction.value === 'rtl');
const registerHeroImage = computed(() => {
    const images = page.props.app_branding?.register_hero_images || {};
    const locale = String(page.props.locale || 'en');

    return images[locale] || images.en || authHero;
});

const initial = computed(() => ({
    name: props.prefill?.name ?? '',
    email: props.prefill?.email ?? '',
    custom_domain: props.prefill?.custom_domain ?? '',
    civil_number: props.prefill?.civil_number ?? '',
    country_iso2: props.prefill?.country_iso2 ?? '',
    phone_country_code: props.prefill?.phone_country_code ?? '',
    phone_national: props.prefill?.phone_national ?? '',
    phone: props.prefill?.phone ?? '',
    commercial_registration_number: props.prefill?.commercial_registration_number ?? '',
    tax_number: props.prefill?.tax_number ?? '',
    partner_seats: props.prefill?.partner_seats ?? 0,
}));

const selectedCountryIso2 = ref(initial.value.country_iso2);
const phoneCountryCode = ref(initial.value.phone_country_code);
const phoneNational = ref(initial.value.phone_national || initial.value.phone);

const selectedCountry = computed(() =>
    (props.countries || []).find((country) => country.iso2 === selectedCountryIso2.value),
);

watch(
    selectedCountryIso2,
    (value) => {
        if (!value) {
            phoneCountryCode.value = '';
            return;
        }

        phoneCountryCode.value = selectedCountry.value?.dial_code ?? '';
    },
    { immediate: true },
);
</script>

<template>
    <Head title="Sign Up" />

    <div class="flex min-h-screen bg-white" :style="themeVars">
        <div class="relative hidden overflow-hidden lg:flex lg:w-1/2">
            <img
                :src="registerHeroImage"
                alt="Car4U background"
                class="absolute inset-0 h-full w-full object-cover object-center"
            />
        </div>

        <div
            class="relative flex w-full flex-col items-center justify-center px-6 py-8 pt-14 sm:px-8 sm:pt-16 lg:w-1/2"
        >
            <div class="absolute top-4 z-50 ltr:left-4 sm:ltr:left-8 rtl:right-4 sm:rtl:right-8">
                <Link
                    :href="homeUrl"
                    class="flex h-10 w-10 items-center justify-center rounded-full border border-primary/20 bg-primary/10 text-primary shadow-sm transition hover:bg-primary hover:text-primary-foreground"
                    :aria-label="page.props.locale === 'ar' ? 'العودة للرئيسية' : 'Back to home'"
                    :title="page.props.locale === 'ar' ? 'العودة للرئيسية' : 'Back to home'"
                >
                    <Home class="h-5 w-5" />
                </Link>
            </div>

            <div class="absolute top-4 z-50 ltr:right-4 sm:ltr:right-8 rtl:left-4 sm:rtl:left-8">
                <AuthLanguageSwitcher />
            </div>

            <div class="w-full max-w-md space-y-6">

                <!-- ===================== TENANT: Client Registration ===================== -->
                <template v-if="currentTenant">
                    <div class="space-y-2">
                        <h1 class="text-3xl font-bold text-gray-900">{{ t('auth.create_account') }}</h1>
                        <p class="text-gray-500">
                            {{ t('auth.create_account_tenant_desc') }}
                        </p>
                    </div>

                    <Form
                        v-bind="registerAction"
                        :reset-on-success="['password', 'password_confirmation']"
                        v-slot="{ errors, processing }"
                        class="space-y-5"
                    >
                        <!-- Full Name -->
                        <div class="space-y-2">
                            <Label for="name" class="text-sm font-semibold text-gray-800">
                                {{ t('auth.full_name') }}
                            </Label>
                            <Input
                                id="name"
                                name="name"
                                type="text"
                                :placeholder="t('auth.placeholder_full_name')"
                                :default-value="initial.name"
                                required
                                autofocus
                                autocomplete="name"
                                class="h-11 border-gray-300 focus:border-primary focus:ring-primary"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <!-- Email -->
                        <div class="space-y-2">
                            <Label for="email" class="text-sm font-semibold text-gray-800">
                                {{ t('auth.email') }}
                            </Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                :placeholder="t('auth.placeholder_email')"
                                :default-value="initial.email"
                                required
                                autocomplete="email"
                                class="h-11 border-gray-300 focus:border-primary focus:ring-primary"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <!-- Civil Number -->
                        <div class="space-y-2">
                            <Label for="civil_number" class="text-sm font-semibold text-gray-800">
                                {{ t('auth.civil_number') }}
                            </Label>
                            <Input
                                id="civil_number"
                                name="civil_number"
                                type="text"
                                :placeholder="t('auth.civil_number')"
                                :default-value="initial.civil_number"
                                required
                                autocomplete="off"
                                class="h-11 border-gray-300 focus:border-primary focus:ring-primary"
                            />
                            <InputError :message="errors.civil_number" />
                        </div>

                        <!-- Password -->
                        <div class="space-y-2">
                            <Label for="password" class="text-sm font-semibold text-gray-800">
                                {{ t('auth.password') }}
                            </Label>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                :placeholder="t('auth.placeholder_password')"
                                required
                                autocomplete="new-password"
                                class="h-11 border-gray-300 focus:border-primary focus:ring-primary"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <!-- Confirm Password -->
                        <div class="space-y-2">
                            <Label for="password_confirmation" class="text-sm font-semibold text-gray-800">
                                {{ t('auth.confirm_password') }}
                            </Label>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                :placeholder="t('auth.confirm_password')"
                                required
                                autocomplete="new-password"
                                class="h-11 border-gray-300 focus:border-primary focus:ring-primary"
                            />
                            <InputError :message="errors.password_confirmation" />
                        </div>

                        <!-- Submit -->
                        <button
                            type="submit"
                            class="h-12 w-full rounded-lg bg-primary font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="processing"
                        >
                            {{ processing ? t('auth.authenticating') : t('auth.create_account') }}
                        </button>
                    </Form>

                    <div class="relative flex items-center py-2">
                        <div class="flex-grow border-t border-gray-200"></div>
                        <span class="flex-shrink-0 px-4 text-xs font-medium text-gray-500 uppercase">
                            {{ page.props.locale === 'ar' ? 'أو المتابعة باستخدام' : 'Or continue with' }}
                        </span>
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
                            Google
                        </a>
                        <a
                            :href="buildUrl(page.props.app_url_base, `/auth/apple/redirect?tenant=${currentTenant.slug}`)"
                            class="flex h-11 items-center justify-center rounded-lg border border-primary bg-primary font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90"
                        >
                            <svg class="mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 814 1000" xmlns="http://www.w3.org/2000/svg">
                                <path d="M788.1 340.9c-5.8 4.5-108.2 62.2-108.2 190.5 0 148.4 130.3 200.9 134.2 202.2-.6 3.2-20.7 71.9-68.7 141.9-42.8 61.6-87.5 123.1-155.5 123.1s-85.5-39.5-164-39.5c-76 0-103.7 40.8-165.9 40.8s-105-42.4-148.8-107.4C27.4 716 0 621.2 0 530.8c0-191.3 125.2-292.2 248.5-292.2 66.1 0 121.2 43.4 162.7 43.4 39.5 0 101.1-46 176.3-46 28.5 0 130.9 2.6 198.3 99.2zm-234-181.5c31.1-36.9 53.1-88.1 53.1-139.3 0-7.1-.6-14.3-1.9-20.1-50.6 1.9-110.8 33.7-147.1 75.8-28.5 32.4-55.1 83.6-55.1 135.5 0 7.8 1.3 15.6 1.9 18.1 3.2.6 8.4 1.3 13.6 1.3 45.4 0 102.5-30.4 135.5-71.3z"/>
                            </svg>
                            Apple
                        </a>
                    </div>

                    <!-- Sign in link -->
                    <p class="text-center text-sm text-gray-600">
                        {{ t('auth.already_have_account_short') }}
                        <Link
                            :href="loginUrl"
                            class="ml-1 font-semibold text-primary hover:text-primary/80 hover:underline"
                        >
                            {{ t('auth.sign_in_here_short') }}
                        </Link>
                    </p>
                </template>

                <!-- ===================== MAIN DOMAIN: SaaS Registration ===================== -->
                <template v-else>
                    <div class="space-y-2">
                        <h1 class="text-3xl font-bold text-gray-900">{{ t('auth.sign_up') }}</h1>
                        <p class="text-gray-500">
                            {{ t('auth.create_account_desc') }}
                        </p>
                    </div>

                    <Form
                        v-bind="registerAction"
                        :reset-on-success="['password', 'password_confirmation']"
                        v-slot="{ errors, processing }"
                        class="space-y-4"
                    >
                        <div class="space-y-2">
                            <Label
                                for="name"
                                class="text-sm font-semibold text-gray-800"
                                >{{ t('auth.company_name') }}</Label
                            >
                            <Input
                                id="name"
                                name="name"
                                type="text"
                                :placeholder="t('auth.company_name')"
                                :default-value="initial.name"
                                required
                                autofocus
                                autocomplete="name"
                                class="h-11 border-gray-300"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="space-y-4 rounded-2xl border border-gray-200 bg-gray-50/60 p-4">
                            <div class="space-y-1">
                                <h3 class="text-base font-semibold text-gray-900">
                                    {{ t('auth.company_registration_details') }}
                                </h3>
                                <p class="text-xs text-gray-500">
                                    {{ t('auth.company_registration_help') }}
                                </p>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="commercial_registration_number" class="text-sm font-semibold text-gray-800">
                                        {{ t('auth.commercial_registration_no') }}
                                    </Label>
                                    <Input
                                        id="commercial_registration_number"
                                        name="commercial_registration_number"
                                        type="text"
                                        :default-value="initial.commercial_registration_number"
                                        :placeholder="t('auth.commercial_registration_no')"
                                        required
                                        class="h-11 border-gray-300"
                                    />
                                    <InputError :message="errors.commercial_registration_number" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="tax_number" class="text-sm font-semibold text-gray-800">
                                        {{ t('auth.tax_no') }}
                                    </Label>
                                    <Input
                                        id="tax_number"
                                        name="tax_number"
                                        type="text"
                                        :default-value="initial.tax_number"
                                        :placeholder="t('auth.tax_no')"
                                        required
                                        class="h-11 border-gray-300"
                                    />
                                    <InputError :message="errors.tax_number" />
                                </div>

                                <div class="space-y-2 md:col-span-2">
                                    <Label for="civil_number" class="text-sm font-semibold text-gray-800">
                                        {{ t('auth.civil_number') }}
                                    </Label>
                                    <Input
                                        id="civil_number"
                                        name="civil_number"
                                        type="text"
                                        :default-value="initial.civil_number"
                                        :placeholder="t('auth.civil_number')"
                                        required
                                        class="h-11 border-gray-300"
                                    />
                                    <InputError :message="errors.civil_number" />
                                </div>
                            </div>
                        </div>

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
                                :default-value="initial.email"
                                required
                                autocomplete="email"
                                class="h-11 border-gray-300"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label
                                for="partner_seats"
                                class="text-sm font-semibold text-gray-800"
                            >
                                {{ t('auth.partner_accounts') }}
                            </Label>
                            <Input
                                id="partner_seats"
                                name="partner_seats"
                                type="number"
                                min="0"
                                max="10"
                                step="1"
                                :default-value="initial.partner_seats"
                                class="h-11 border-gray-300"
                            />
                            <p class="text-xs text-gray-500">
                                {{ t('auth.partner_accounts_help') }}
                            </p>
                            <InputError :message="errors.partner_seats" />
                        </div>

                        <div class="space-y-2">
                            <Label
                                for="custom_domain"
                                class="text-sm font-semibold text-gray-800"
                            >
                                {{ t('auth.custom_domain') }}
                                <span class="font-normal text-gray-500"
                                    >({{ t('auth.optional') }})</span
                                >
                            </Label>
                            <Input
                                id="custom_domain"
                                name="custom_domain"
                                type="text"
                                placeholder="yourdomain.com"
                                :default-value="initial.custom_domain"
                                class="h-11 border-gray-300"
                            />
                            <InputError :message="errors.custom_domain" />
                        </div>

                        <div class="space-y-2">
                            <Label
                                for="country_iso2"
                                class="text-sm font-semibold text-gray-800"
                            >
                                {{ t('auth.country') }}
                            </Label>
                            <select
                                id="country_iso2"
                                name="country_iso2"
                                v-model="selectedCountryIso2"
                                class="h-11 w-full rounded-md border border-gray-300 bg-white px-3 text-sm"
                            >
                                <option value="">{{ t('auth.choose_country') }}</option>
                                <option
                                    v-for="country in (props.countries || [])"
                                    :key="country.iso2"
                                    :value="country.iso2"
                                >
                                    {{ isArabic ? country.name_ar : country.name_en }} ({{ country.dial_code }})
                                </option>
                            </select>
                            <InputError :message="errors.country_iso2" />
                        </div>

                        <div class="space-y-2">
                            <Label
                                for="phone_national"
                                class="text-sm font-semibold text-gray-800"
                            >
                                {{ t('auth.phone_number') }}
                            </Label>
                            <div
                                class="flex gap-2"
                                :class="isRtl ? 'flex-row-reverse' : 'flex-row'"
                            >
                                <Input
                                    name="phone_country_code"
                                    :model-value="phoneCountryCode"
                                    readonly
                                    placeholder="+___"
                                    class="h-11 w-28 shrink-0 border-gray-300 bg-gray-50 text-left"
                                    dir="ltr"
                                />
                                <Input
                                    id="phone_national"
                                    name="phone_national"
                                    v-model="phoneNational"
                                    type="tel"
                                    placeholder="e.g. 91234567"
                                    class="h-11 min-w-0 flex-1 border-gray-300 text-left"
                                    dir="ltr"
                                />
                            </div>
                            <p class="text-xs text-gray-500">
                                {{ t('auth.phone_help') }}
                            </p>
                            <InputError :message="errors.phone_national || errors.phone" />
                        </div>

                        <div class="space-y-2">
                            <Label
                                for="password"
                                class="text-sm font-semibold text-gray-800"
                                >{{ t('auth.password') }}</Label
                            >
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                :placeholder="t('auth.placeholder_password')"
                                required
                                autocomplete="new-password"
                                class="h-11 border-gray-300"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="space-y-2">
                            <Label
                                for="password_confirmation"
                                class="text-sm font-semibold text-gray-800"
                            >
                                {{ t('auth.repeat_password') }}
                            </Label>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                :placeholder="t('auth.placeholder_password')"
                                required
                                autocomplete="new-password"
                                class="h-11 border-gray-300"
                            />
                            <InputError :message="errors.password_confirmation" />
                        </div>

                        <label
                            for="terms"
                            class="flex items-center gap-2 text-sm text-gray-600"
                        >
                            <input
                                id="terms"
                                type="checkbox"
                                required
                                class="h-4 w-4 rounded border-gray-300 text-blue-700 focus:ring-blue-600"
                            />
                            {{ t('auth.agree_to_terms') }}
                            <Link
                                :href="termsUrl"
                                class="font-medium text-blue-700 hover:underline"
                                >{{ t('auth.terms_of_use') }}</Link
                            >
                        </label>

                        <button
                            type="submit"
                            class="h-12 w-full rounded-full bg-primary font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="processing"
                        >
                            {{ processing ? t('auth.authenticating') : t('auth.sign_up') }}
                        </button>
                    </Form>

                    <p class="text-center text-sm text-gray-600">
                        {{ t('auth.already_have_account_short') }}
                        <Link
                            :href="loginUrl"
                            class="ml-1 font-semibold text-primary hover:underline"
                        >
                            {{ t('auth.sign_in') }} 
                        </Link>
                    </p>
                </template>

            </div>
        </div>
    </div>
</template>
