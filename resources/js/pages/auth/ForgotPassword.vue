<script setup lang="ts">
import authHero from '@/assets/auth-hero.jpg';
import AuthLanguageSwitcher from '@/components/AuthLanguageSwitcher.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import {
    PinInput,
    PinInputGroup,
    PinInputSlot,
} from '@/components/ui/pin-input';
import { tenantLogin as mainTenantLogin } from '@/routes';
import { login as tenantLoginRoute } from '@/routes/tenant/index.ts';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Mail } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const page = usePage<any>();
const { t } = useTrans();
const currentTenant = computed(() => page.props.current_tenant);
const authSideImage = computed(() => {
    const images = page.props.app_branding?.register_hero_images || {};
    const currentLocale = String(page.props.locale || 'en');

    return images[currentLocale] || images.en || authHero;
});

const loginHref = computed(() =>
    currentTenant.value?.slug
        ? tenantLoginRoute(currentTenant.value.slug)
        : mainTenantLogin(),
);

const email = ref('');
const otp = ref<number[]>([]);
const password = ref('');
const passwordConfirmation = ref('');
const testOtp = ref<string | null>(null);
const status = ref<string>('');
const otpSent = ref(false);
const otpVerified = ref(false);
const activeStep = ref<1 | 2 | 3>(1);
const loadingStep = ref<'send' | 'verify' | 'reset' | null>(null);

type ErrorBag = Partial<
    Record<
        'email' | 'otp' | 'password' | 'password_confirmation' | 'general',
        string
    >
>;

const errors = reactive<ErrorBag>({});

const otpValue = computed(() => otp.value.join(''));
const otpComplete = computed(() => otpValue.value.length === 6);
const progressWidth = computed(() =>
    activeStep.value === 1 ? '0%' : activeStep.value === 2 ? '50%' : '100%',
);
const forgotPasswordText = (key: string): string =>
    t(`auth.forgot_password_page.${key}`);
const activeStepTitle = computed(() => {
    if (activeStep.value === 1) {
        return forgotPasswordText('step_email_title');
    }

    if (activeStep.value === 2) {
        return forgotPasswordText('step_otp_title');
    }

    return forgotPasswordText('step_password_title');
});

const resolveHref = (href: unknown): string =>
    typeof href === 'string' ? href : (href as { url?: string } | null)?.url ?? '';

const clearErrors = (): void => {
    errors.email = undefined;
    errors.otp = undefined;
    errors.password = undefined;
    errors.password_confirmation = undefined;
    errors.general = undefined;
};

const validateEmail = (): boolean => {
    const value = email.value.trim();

    if (!value) {
        errors.email = forgotPasswordText('email_required');
        return false;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        errors.email = forgotPasswordText('email_invalid');
        return false;
    }

    return true;
};

const validateOtp = (): boolean => {
    if (!otpSent.value) {
        errors.general = forgotPasswordText('send_otp_first');
        return false;
    }

    if (!otpComplete.value) {
        errors.otp = forgotPasswordText('otp_required');
        return false;
    }

    return true;
};

const validatePasswords = (): boolean => {
    const currentPassword = password.value.trim();
    const confirmation = passwordConfirmation.value.trim();

    if (!otpVerified.value) {
        errors.general = forgotPasswordText('verify_otp_first');
        return false;
    }

    if (!currentPassword) {
        errors.password = forgotPasswordText('password_required');
        return false;
    }

    if (currentPassword.length < 8) {
        errors.password = forgotPasswordText('password_min');
        return false;
    }

    if (!confirmation) {
        errors.password_confirmation = forgotPasswordText('password_confirmation_required');
        return false;
    }

    if (currentPassword !== confirmation) {
        errors.password_confirmation = forgotPasswordText('password_mismatch');
        return false;
    }

    return true;
};

const setErrorBag = (payload: any): void => {
    clearErrors();

    const bag = payload?.errors ?? {};

    for (const [key, value] of Object.entries(bag)) {
        const message = Array.isArray(value) ? value[0] : String(value);

        if (
            key === 'email' ||
            key === 'otp' ||
            key === 'password' ||
            key === 'password_confirmation'
        ) {
            errors[key] = message;
        }
    }

    if (payload?.message && !Object.keys(bag).length) {
        errors.general = String(payload.message);
    }
};

const parseResponse = async (response: Response): Promise<any> => {
    const contentType = response.headers.get('content-type') ?? '';

    if (contentType.includes('application/json')) {
        return await response.json();
    }

    return {
        message: await response.text(),
    };
};

const csrfToken = (): string =>
    String(page.props.csrf_token || '')
    || (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content
    || '';

const postJson = async (
    path: 'forgot-password' | 'verify-otp' | 'reset-password',
    payload: Record<string, unknown>,
): Promise<any> => {
    const token = csrfToken();

    const response = await fetch(`/api/auth/${path}`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'Accept-Language': String(page.props.locale || 'en'),
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });

    const data = await parseResponse(response);

    if (!response.ok) {
        if (response.status === 422) {
            setErrorBag(data);
        } else {
            clearErrors();
            errors.general = data?.message ?? forgotPasswordText('request_failed');
        }

        throw data;
    }

    return data;
};

const sendOtp = async (): Promise<void> => {
    clearErrors();

    if (!validateEmail()) {
        activeStep.value = 1;
        return;
    }

    loadingStep.value = 'send';

    try {
        const data = await postJson('forgot-password', {
            email: email.value.trim(),
        });

        otpSent.value = true;
        otpVerified.value = false;
        activeStep.value = 2;
        otp.value = [];
        status.value =
            data?.message ?? forgotPasswordText('otp_sent_status');
        testOtp.value =
            data?.test_otp !== null && data?.test_otp !== undefined
                ? String(data.test_otp)
                : null;
    } finally {
        loadingStep.value = null;
    }
};

const verifyOtp = async (): Promise<void> => {
    clearErrors();

    if (!validateOtp()) {
        return;
    }

    loadingStep.value = 'verify';

    try {
        const data = await postJson('verify-otp', {
            email: email.value.trim(),
            otp: otpValue.value,
        });

        otpVerified.value = true;
        activeStep.value = 3;
        status.value = data?.message ?? forgotPasswordText('otp_verified_status');
    } finally {
        loadingStep.value = null;
    }
};

const resetPassword = async (): Promise<void> => {
    clearErrors();

    if (!validatePasswords()) {
        return;
    }

    loadingStep.value = 'reset';

    try {
        const data = await postJson('reset-password', {
            email: email.value.trim(),
            password: password.value,
            password_confirmation: passwordConfirmation.value,
        });

        status.value = data?.message ?? forgotPasswordText('password_reset_status');
        router.visit(resolveHref(loginHref.value));
    } finally {
        loadingStep.value = null;
    }
};

const restartWizard = (): void => {
    clearErrors();
    otp.value = [];
    password.value = '';
    passwordConfirmation.value = '';
    testOtp.value = null;
    status.value = '';
    otpSent.value = false;
    otpVerified.value = false;
    activeStep.value = 1;
};
</script>

<template>
    <Head :title="forgotPasswordText('head_title')" />

    <div class="flex min-h-screen bg-white">
        <div class="relative hidden overflow-hidden lg:flex lg:w-1/2">
            <img
                :src="authSideImage"
                :alt="forgotPasswordText('hero_alt')"
                class="absolute inset-0 h-full w-full object-cover object-center"
            />
        </div>

        <div class="relative flex w-full flex-col items-center justify-center px-6 py-8 pt-14 sm:px-8 sm:pt-16 lg:w-1/2">
            <div class="absolute top-4 z-50 ltr:right-4 sm:ltr:right-8 rtl:left-4 sm:rtl:left-8">
                <AuthLanguageSwitcher />
            </div>

            <div class="w-full max-w-lg space-y-6">
                <div class="space-y-2">
                    <h1 class="text-3xl font-bold text-gray-900">
                        {{ forgotPasswordText('title') }}
                    </h1>
                    <p class="text-gray-500">
                        {{ forgotPasswordText('subtitle') }}
                    </p>
                </div>

                <div
                    v-if="status"
                    class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
                >
                    {{ status }}
                </div>

                <div
                    v-if="errors.general"
                    class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ errors.general }}
                </div>

                <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-blue-700"
                                >
                                    <Mail class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-500">
                                        {{ t('auth.forgot_password_page.step_counter', { step: activeStep, total: 3 }) }}
                                    </p>
                                    <h2 class="text-lg font-semibold text-gray-900">
                                        {{ activeStepTitle }}
                                    </h2>
                                </div>
                            </div>

                            <div class="hidden text-right text-xs font-medium text-gray-500 sm:block">
                                <p :class="activeStep >= 1 ? 'text-blue-700' : ''">{{ forgotPasswordText('step_email_short') }}</p>
                                <p :class="activeStep >= 2 ? 'text-blue-700' : ''">{{ forgotPasswordText('step_otp_short') }}</p>
                                <p :class="activeStep >= 3 ? 'text-blue-700' : ''">{{ forgotPasswordText('step_password_short') }}</p>
                            </div>
                        </div>

                        <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-blue-700 to-blue-500 transition-all duration-300"
                                :style="{ width: progressWidth }"
                            />
                        </div>

                        <div v-if="activeStep === 1" class="space-y-5 pt-2">
                            <div class="space-y-2">
                                <Label for="email" class="text-sm font-semibold text-gray-800">
                                    {{ forgotPasswordText('email_label') }}
                                </Label>
                                <Input
                                    id="email"
                                    v-model="email"
                                    type="email"
                                    :placeholder="forgotPasswordText('email_placeholder')"
                                    autocomplete="email"
                                    class="h-11 border-gray-300"
                                />
                                <InputError :message="errors.email" />
                            </div>

                            <button
                                type="button"
                                class="h-12 w-full rounded-full bg-gradient-to-r from-blue-700 to-blue-500 font-semibold text-white shadow-sm transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="loadingStep === 'send'"
                                @click="sendOtp"
                            >
                                <LoaderCircle
                                    v-if="loadingStep === 'send'"
                                    class="mr-2 inline h-4 w-4 animate-spin"
                                />
                                {{ loadingStep === 'send' ? forgotPasswordText('sending_otp') : forgotPasswordText('send_otp') }}
                            </button>
                        </div>

                        <div v-else-if="activeStep === 2" class="space-y-5 pt-2">
                            <div class="space-y-2">
                                <Label for="otp" class="text-sm font-semibold text-gray-800">
                                    {{ forgotPasswordText('otp_label') }}
                                </Label>

                                <PinInput
                                    id="otp"
                                    v-model="otp"
                                    type="number"
                                    otp
                                    :disabled="!otpSent"
                                >
                                    <PinInputGroup>
                                        <PinInputSlot
                                            v-for="index in 6"
                                            :key="index"
                                            :index="index - 1"
                                        />
                                    </PinInputGroup>
                                </PinInput>

                                <InputError :message="errors.otp" />
                            </div>

                            <button
                                type="button"
                                class="h-12 w-full rounded-full bg-gradient-to-r from-blue-700 to-blue-500 font-semibold text-white shadow-sm transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="loadingStep === 'verify' || !otpSent"
                                @click="verifyOtp"
                            >
                                <LoaderCircle
                                    v-if="loadingStep === 'verify'"
                                    class="mr-2 inline h-4 w-4 animate-spin"
                                />
                                {{ loadingStep === 'verify' ? forgotPasswordText('verifying_otp') : forgotPasswordText('verify_otp') }}
                            </button>
                        </div>

                        <div v-else class="space-y-5 pt-2">
                            <div class="grid gap-4">
                                <div class="space-y-2">
                                    <Label
                                        for="password"
                                        class="text-sm font-semibold text-gray-800"
                                    >
                                        {{ forgotPasswordText('new_password_label') }}
                                    </Label>
                                    <Input
                                        id="password"
                                        v-model="password"
                                        type="password"
                                        autocomplete="new-password"
                                        :placeholder="forgotPasswordText('new_password_placeholder')"
                                        :disabled="!otpVerified"
                                        class="h-11 border-gray-300"
                                    />
                                    <InputError :message="errors.password" />
                                </div>

                                <div class="space-y-2">
                                    <Label
                                        for="password_confirmation"
                                        class="text-sm font-semibold text-gray-800"
                                    >
                                        {{ forgotPasswordText('confirm_password_label') }}
                                    </Label>
                                    <Input
                                        id="password_confirmation"
                                        v-model="passwordConfirmation"
                                        type="password"
                                        autocomplete="new-password"
                                        :placeholder="forgotPasswordText('confirm_password_placeholder')"
                                        :disabled="!otpVerified"
                                        class="h-11 border-gray-300"
                                    />
                                    <InputError :message="errors.password_confirmation" />
                                </div>
                            </div>

                            <button
                                type="button"
                                class="h-12 w-full rounded-full bg-gradient-to-r from-blue-700 to-blue-500 font-semibold text-white shadow-sm transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="loadingStep === 'reset' || !otpVerified"
                                @click="resetPassword"
                            >
                                <LoaderCircle
                                    v-if="loadingStep === 'reset'"
                                    class="mr-2 inline h-4 w-4 animate-spin"
                                />
                                {{ loadingStep === 'reset' ? forgotPasswordText('resetting_password') : forgotPasswordText('reset_password') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="testOtp"
                    class="rounded-2xl border border-dashed border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800"
                >
                    <span class="font-semibold">{{ forgotPasswordText('test_otp_label') }}</span>
                    <code class="ml-2 rounded bg-white px-2 py-0.5 font-mono text-xs">
                        {{ testOtp }}
                    </code>
                </div>

                <div class="flex items-center justify-between text-sm text-gray-600">
                    <Link
                        :href="resolveHref(loginHref)"
                        class="font-semibold text-blue-700 hover:underline"
                    >
                        {{ forgotPasswordText('back_to_login') }}
                    </Link>
                    <button
                        type="button"
                        class="font-medium text-gray-500 hover:text-gray-700"
                        @click="restartWizard"
                    >
                        {{ forgotPasswordText('start_over') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
