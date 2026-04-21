<script setup lang="ts">
import authHero from '@/assets/auth-hero.jpg';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as mainRegisterStore } from '@/routes/register';
import { store as tenantRegisterStore } from '@/routes/tenant/register';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage<any>();
const currentTenant = computed(() => page.props.current_tenant);

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
    return (slug ? tenantRegisterStore(slug) : mainRegisterStore()) as any;
});
const baseProtocol = computed(() =>
    typeof window !== 'undefined' ? window.location.protocol : 'https:',
);
const buildUrl = (host: string, path: string) =>
    `${baseProtocol.value}//${host}${path}`;

const loginUrl = computed(() => {
    const slug = currentTenant.value?.slug;
    return slug
        ? buildUrl(`${slug}.${page.props.app_url_base}`, '/tenant/login')
        : buildUrl(page.props.app_url_base, '/tenant/login');
});

const isArabic = computed(() => page.props.locale === 'ar');
const text = (en: string, ar: string) => (isArabic.value ? ar : en);

const initial = computed(() => ({
    name: props.prefill?.name ?? '',
    email: props.prefill?.email ?? '',
    custom_domain: props.prefill?.custom_domain ?? '',
    country_iso2: props.prefill?.country_iso2 ?? '',
    phone_country_code: props.prefill?.phone_country_code ?? '',
    phone_national: props.prefill?.phone_national ?? '',
    phone: props.prefill?.phone ?? '',
    commercial_registration_number: props.prefill?.commercial_registration_number ?? '',
    tax_number: props.prefill?.tax_number ?? '',
    civil_number: props.prefill?.civil_number ?? '',
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

    <div class="flex min-h-screen bg-white">
        <div class="relative hidden overflow-hidden lg:flex lg:w-1/2">
            <div
                class="absolute inset-0 z-10 bg-gradient-to-br from-orange-600/80 to-orange-400/70"
                v-if="currentTenant"
            />
            <div
                class="absolute inset-0 z-10 bg-gradient-to-br from-blue-700/80 to-blue-500/70"
                v-else
            />
            <img
                :src="authHero"
                alt="Professional workspace"
                class="absolute inset-0 h-full w-full object-cover"
            />
        </div>

        <div
            class="flex w-full items-center justify-center p-6 sm:p-8 lg:w-1/2"
        >
            <div class="w-full max-w-md space-y-6">

                <!-- ===================== TENANT: Client Registration ===================== -->
                <template v-if="currentTenant">
                    <div class="space-y-2">
                        <h1 class="text-3xl font-bold text-gray-900">Create Account</h1>
                        <p class="text-gray-500">
                            Join {{ currentTenant.name }} and start your car rental journey.
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
                                Full Name
                            </Label>
                            <Input
                                id="name"
                                name="name"
                                type="text"
                                placeholder="Enter your full name"
                                :default-value="initial.name"
                                required
                                autofocus
                                autocomplete="name"
                                class="h-11 border-gray-300 focus:border-orange-500 focus:ring-orange-500"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <!-- Email -->
                        <div class="space-y-2">
                            <Label for="email" class="text-sm font-semibold text-gray-800">
                                Email Address
                            </Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                placeholder="Enter your email address"
                                :default-value="initial.email"
                                required
                                autocomplete="email"
                                class="h-11 border-gray-300 focus:border-orange-500 focus:ring-orange-500"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <!-- Password -->
                        <div class="space-y-2">
                            <Label for="password" class="text-sm font-semibold text-gray-800">
                                Password
                            </Label>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                placeholder="Create a password"
                                required
                                autocomplete="new-password"
                                class="h-11 border-gray-300 focus:border-orange-500 focus:ring-orange-500"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <!-- Confirm Password -->
                        <div class="space-y-2">
                            <Label for="password_confirmation" class="text-sm font-semibold text-gray-800">
                                Confirm Password
                            </Label>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                placeholder="Confirm your password"
                                required
                                autocomplete="new-password"
                                class="h-11 border-gray-300 focus:border-orange-500 focus:ring-orange-500"
                            />
                            <InputError :message="errors.password_confirmation" />
                        </div>

                        <!-- Submit -->
                        <button
                            type="submit"
                            class="h-12 w-full rounded-lg bg-orange-500 font-semibold text-white shadow-sm transition hover:bg-orange-600 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="processing"
                        >
                            {{ processing ? 'Creating Account...' : 'Create Account' }}
                        </button>
                    </Form>

                    <div class="relative flex items-center py-2">
                        <div class="flex-grow border-t border-gray-200"></div>
                        <span class="flex-shrink-0 px-4 text-xs font-medium text-gray-500 uppercase">Or continue with</span>
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
                            class="flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-black font-semibold text-white shadow-sm transition hover:bg-gray-800"
                        >
                            <svg class="mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M16.365 21.492c-1.373.91-2.073.932-3.418.932-1.36 0-2.457-.145-3.358-.888-4.706-3.896-7.143-12.001-2.924-17.159C8.381 2.27 10.64 1 12.87 1c1.372 0 2.515.548 3.518.548 1.033 0 2.455-.662 4.098-.598 2.053.082 3.868.868 4.908 2.39-4.226 2.593-3.483 8.653.861 10.4-1.127 3.238-3.085 6.425-5.61 8.8-1.026.969-2.19 1.496-3.235 1.496a7.41 7.41 0 0 1-1.045-.544zM16.124 6.78c-.2-2.14 1.5-4.04 3.79-4.38.38 2.39-1.9 4.31-3.79 4.38z" />
                            </svg>
                            Apple
                        </a>
                    </div>

                    <!-- Sign in link -->
                    <p class="text-center text-sm text-gray-600">
                        Already have an account?
                        <Link
                            :href="loginUrl"
                            class="ml-1 font-semibold text-orange-500 hover:text-orange-600 hover:underline"
                        >
                            Sign in here
                        </Link>
                    </p>
                </template>

                <!-- ===================== MAIN DOMAIN: SaaS Registration ===================== -->
                <template v-else>
                    <div class="space-y-2">
                        <h1 class="text-3xl font-bold text-gray-900">Sign Up</h1>
                        <p class="text-gray-500">
                            Create your account to get started.
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
                                >Company Name</Label
                            >
                            <Input
                                id="name"
                                name="name"
                                type="text"
                                placeholder="Your company..."
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
                                    {{ text('Company Registration Details', 'بيانات السجل التجاري') }}
                                </h3>
                                <p class="text-xs text-gray-500">
                                    {{ text('These company details are shared for the registration and can be updated later from tenant management.', 'هذه البيانات تخص الشركة نفسها ويمكن تحديثها لاحقًا من إدارة المستأجر.') }}
                                </p>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="commercial_registration_number" class="text-sm font-semibold text-gray-800">
                                        {{ text('Commercial Registration No.', 'رقم السجل التجاري') }}
                                    </Label>
                                    <Input
                                        id="commercial_registration_number"
                                        name="commercial_registration_number"
                                        type="text"
                                        :default-value="initial.commercial_registration_number"
                                        :placeholder="text('Commercial registration number', 'رقم السجل التجاري')"
                                        required
                                        class="h-11 border-gray-300"
                                    />
                                    <InputError :message="errors.commercial_registration_number" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="tax_number" class="text-sm font-semibold text-gray-800">
                                        {{ text('Tax No.', 'الرقم الضريبي') }}
                                    </Label>
                                    <Input
                                        id="tax_number"
                                        name="tax_number"
                                        type="text"
                                        :default-value="initial.tax_number"
                                        :placeholder="text('Tax number', 'الرقم الضريبي')"
                                        required
                                        class="h-11 border-gray-300"
                                    />
                                    <InputError :message="errors.tax_number" />
                                </div>

                                <div class="space-y-2 md:col-span-2">
                                    <Label for="civil_number" class="text-sm font-semibold text-gray-800">
                                        {{ text('Civil No.', 'الرقم المدني') }}
                                    </Label>
                                    <Input
                                        id="civil_number"
                                        name="civil_number"
                                        type="text"
                                        :default-value="initial.civil_number"
                                        :placeholder="text('Civil number', 'الرقم المدني')"
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
                                >Email</Label
                            >
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                placeholder="Email address..."
                                :default-value="initial.email"
                                required
                                autocomplete="email"
                                class="h-11 border-gray-300"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label
                                for="custom_domain"
                                class="text-sm font-semibold text-gray-800"
                            >
                                Custom Domain
                                <span class="font-normal text-gray-500"
                                    >(optional)</span
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
                                Country
                            </Label>
                            <select
                                id="country_iso2"
                                name="country_iso2"
                                v-model="selectedCountryIso2"
                                class="h-11 w-full rounded-md border border-gray-300 bg-white px-3 text-sm"
                            >
                                <option value="">Select country</option>
                                <option
                                    v-for="country in (props.countries || [])"
                                    :key="country.iso2"
                                    :value="country.iso2"
                                >
                                    {{ country.name_en }} ({{ country.dial_code }})
                                </option>
                            </select>
                            <InputError :message="errors.country_iso2" />
                        </div>

                        <div class="space-y-2">
                            <Label
                                for="phone_national"
                                class="text-sm font-semibold text-gray-800"
                            >
                                Phone Number
                            </Label>
                            <div class="flex gap-2">
                                <Input
                                    name="phone_country_code"
                                    :model-value="phoneCountryCode"
                                    readonly
                                    placeholder="+___"
                                    class="h-11 w-28 border-gray-300 bg-gray-50"
                                />
                                <Input
                                    id="phone_national"
                                    name="phone_national"
                                    v-model="phoneNational"
                                    type="tel"
                                    placeholder="e.g. 91234567"
                                    class="h-11 border-gray-300"
                                />
                            </div>
                            <p class="text-xs text-gray-500">
                                Enter the phone number without the country code.
                            </p>
                            <InputError :message="errors.phone_national || errors.phone" />
                        </div>

                        <div class="space-y-2">
                            <Label
                                for="password"
                                class="text-sm font-semibold text-gray-800"
                                >Password</Label
                            >
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                placeholder="**********"
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
                                Repeat Password
                            </Label>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                placeholder="**********"
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
                            I agree to the
                            <a
                                href="#"
                                class="font-medium text-blue-700 hover:underline"
                                >Terms of Use</a
                            >
                        </label>

                        <button
                            type="submit"
                            class="h-12 w-full rounded-full bg-gradient-to-r from-blue-700 to-blue-500 font-semibold text-white shadow-sm transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="processing"
                        >
                            {{ processing ? 'Creating Account...' : 'Sign Up' }}
                        </button>
                    </Form>

                    <p class="text-center text-sm text-gray-600">
                        Already have an account?
                        <Link
                            :href="loginUrl"
                            class="ml-1 font-semibold text-blue-700 hover:underline"
                        >
                            Sign In ->
                        </Link>
                    </p>
                </template>

            </div>
        </div>
    </div>
</template>
