<script setup lang="ts">
import authHero from '@/assets/auth-hero.jpg';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    PinInput,
    PinInputGroup,
    PinInputSlot,
} from '@/components/ui/pin-input';
import { login as mainLogin } from '@/routes';
import tenantLogin from '@/routes/tenant/login';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Mail } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const page = usePage<any>();
const currentTenant = computed(() => page.props.current_tenant);

const loginHref = computed(() =>
    currentTenant.value?.slug
        ? tenantLogin(currentTenant.value.slug)
        : mainLogin(),
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
        errors.email = 'Email is required.';
        return false;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        errors.email = 'Enter a valid email address.';
        return false;
    }

    return true;
};

const validateOtp = (): boolean => {
    if (!otpSent.value) {
        errors.general = 'Send the OTP first.';
        return false;
    }

    if (!otpComplete.value) {
        errors.otp = 'Enter the 6-digit OTP.';
        return false;
    }

    return true;
};

const validatePasswords = (): boolean => {
    const currentPassword = password.value.trim();
    const confirmation = passwordConfirmation.value.trim();

    if (!otpVerified.value) {
        errors.general = 'Verify the OTP first.';
        return false;
    }

    if (!currentPassword) {
        errors.password = 'Password is required.';
        return false;
    }

    if (currentPassword.length < 8) {
        errors.password = 'Password must be at least 8 characters.';
        return false;
    }

    if (!confirmation) {
        errors.password_confirmation = 'Please confirm your password.';
        return false;
    }

    if (currentPassword !== confirmation) {
        errors.password_confirmation = 'Passwords do not match.';
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

const postJson = async (
    path: 'forgot-password' | 'verify-otp' | 'reset-password',
    payload: Record<string, unknown>,
): Promise<any> => {
    const response = await fetch(`/api/auth/${path}`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
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
            errors.general = data?.message ?? 'Request failed.';
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
            data?.message ?? 'OTP sent. Check your email for the code.';
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
        status.value = data?.message ?? 'OTP verified successfully.';
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

        status.value = data?.message ?? 'Password reset successfully.';
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
    <Head title="Reset password" />

    <div class="flex min-h-screen bg-white">
        <div class="relative hidden overflow-hidden lg:flex lg:w-1/2">
            <img
                :src="authHero"
                alt="Car4U background"
                class="absolute inset-0 h-full w-full object-cover"
            />
        </div>

        <div class="flex w-full items-center justify-center p-6 sm:p-8 lg:w-1/2">
            <div class="w-full max-w-lg space-y-6">
                <div class="space-y-2">
                    <h1 class="text-3xl font-bold text-gray-900">
                        Reset your password
                    </h1>
                    <p class="text-gray-500">
                        Follow the three steps below to verify your email and create a new password.
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
                                        Step {{ activeStep }} of 3
                                    </p>
                                    <h2 class="text-lg font-semibold text-gray-900">
                                        {{ activeStep === 1 ? 'Enter email' : activeStep === 2 ? 'Verify OTP' : 'Create password' }}
                                    </h2>
                                </div>
                            </div>

                            <div class="hidden text-right text-xs font-medium text-gray-500 sm:block">
                                <p :class="activeStep >= 1 ? 'text-blue-700' : ''">Email</p>
                                <p :class="activeStep >= 2 ? 'text-blue-700' : ''">OTP</p>
                                <p :class="activeStep >= 3 ? 'text-blue-700' : ''">Password</p>
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
                                    Email
                                </Label>
                                <Input
                                    id="email"
                                    v-model="email"
                                    type="email"
                                    placeholder="Email address..."
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
                                Send OTP
                            </button>
                        </div>

                        <div v-else-if="activeStep === 2" class="space-y-5 pt-2">
                            <div class="space-y-2">
                                <Label for="otp" class="text-sm font-semibold text-gray-800">
                                    OTP code
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
                                Verify OTP
                            </button>
                        </div>

                        <div v-else class="space-y-5 pt-2">
                            <div class="grid gap-4">
                                <div class="space-y-2">
                                    <Label
                                        for="password"
                                        class="text-sm font-semibold text-gray-800"
                                    >
                                        New password
                                    </Label>
                                    <Input
                                        id="password"
                                        v-model="password"
                                        type="password"
                                        autocomplete="new-password"
                                        placeholder="New password"
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
                                        Confirm password
                                    </Label>
                                    <Input
                                        id="password_confirmation"
                                        v-model="passwordConfirmation"
                                        type="password"
                                        autocomplete="new-password"
                                        placeholder="Confirm password"
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
                                Reset password
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="testOtp"
                    class="rounded-2xl border border-dashed border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800"
                >
                    <span class="font-semibold">test_otp:</span>
                    <code class="ml-2 rounded bg-white px-2 py-0.5 font-mono text-xs">
                        {{ testOtp }}
                    </code>
                </div>

                <div class="flex items-center justify-between text-sm text-gray-600">
                    <Link
                        :href="resolveHref(loginHref)"
                        class="font-semibold text-blue-700 hover:underline"
                    >
                        Back to log in
                    </Link>
                    <button
                        type="button"
                        class="font-medium text-gray-500 hover:text-gray-700"
                        @click="restartWizard"
                    >
                        Start over
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
