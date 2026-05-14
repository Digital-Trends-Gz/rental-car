<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    PinInput,
    PinInputGroup,
    PinInputSlot,
} from '@/components/ui/pin-input';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { login as mainLogin } from '@/routes';
import { login as tenantLogin } from '@/routes/tenant/login';
import { Head, router, usePage } from '@inertiajs/vue3';
import { KeyRound, LoaderCircle, Mail, ShieldCheck } from 'lucide-vue-next';
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
const loadingStep = ref<'send' | 'verify' | 'reset' | null>(null);

type ErrorBag = Partial<
    Record<'email' | 'otp' | 'password' | 'password_confirmation' | 'general', string>
>;

const errors = reactive<ErrorBag>({});

const otpValue = computed(() => otp.value.join(''));
const otpComplete = computed(() => otpValue.value.length === 6);
const resolveHref = (href: unknown): string =>
    typeof href === 'string' ? href : (href as { url?: string } | null)?.url ?? '';

const clearErrors = (): void => {
    errors.email = undefined;
    errors.otp = undefined;
    errors.password = undefined;
    errors.password_confirmation = undefined;
    errors.general = undefined;
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
    loadingStep.value = 'send';

    try {
        const data = await postJson('forgot-password', {
            email: email.value,
        });

        otpSent.value = true;
        otpVerified.value = false;
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

    if (!otpSent.value) {
        errors.general = 'Send the OTP first.';
        return;
    }

    if (!otpComplete.value) {
        errors.otp = 'Enter the 6-digit OTP.';
        return;
    }

    loadingStep.value = 'verify';

    try {
        const data = await postJson('verify-otp', {
            email: email.value,
            otp: otpValue.value,
        });

        otpVerified.value = true;
        status.value = data?.message ?? 'OTP verified successfully.';
    } finally {
        loadingStep.value = null;
    }
};

const resetPassword = async (): Promise<void> => {
    clearErrors();

    if (!otpVerified.value) {
        errors.otp = 'Verify the OTP first.';
        return;
    }

    loadingStep.value = 'reset';

    try {
        const data = await postJson('reset-password', {
            email: email.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
        });

        status.value = data?.message ?? 'Password reset successfully.';
        router.visit(resolveHref(loginHref.value));
    } finally {
        loadingStep.value = null;
    }
};
</script>

<template>
    <HomeLayout>
        <Head title="Forgot password" />

        <div class="flex min-h-[80vh] items-center justify-center px-4 sm:px-6 lg:px-8">
            <div class="w-full max-w-2xl space-y-6">
                <div class="text-center">
                    <h1 class="mb-2 text-3xl font-bold text-gray-900">
                        Reset your password
                    </h1>
                    <p class="text-gray-600">
                        Send an OTP, verify it, then set a new password from the same page.
                    </p>
                </div>

                <div
                    v-if="status"
                    class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800"
                >
                    {{ status }}
                </div>

                <div
                    v-if="errors.general"
                    class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800"
                >
                    {{ errors.general }}
                </div>

                <div class="grid gap-6">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="rounded-full bg-orange-100 p-2 text-orange-600">
                                <Mail class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">
                                    1. Email
                                </h2>
                                <p class="text-sm text-gray-600">
                                    Enter your account email to receive the OTP.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">Email address</Label>
                            <Input
                                id="email"
                                v-model="email"
                                type="email"
                                autocomplete="email"
                                placeholder="email@example.com"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="mt-4 flex justify-end">
                            <Button :disabled="loadingStep === 'send'" @click="sendOtp">
                                <LoaderCircle
                                    v-if="loadingStep === 'send'"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                Send OTP
                            </Button>
                        </div>

                        <div
                            v-if="testOtp"
                            class="mt-4 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-700"
                        >
                            <span class="font-semibold">test_otp:</span>
                            <code class="ml-2 rounded bg-white px-2 py-0.5 font-mono text-xs">
                                {{ testOtp }}
                            </code>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="rounded-full bg-blue-100 p-2 text-blue-600">
                                <ShieldCheck class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">
                                    2. Verify OTP
                                </h2>
                                <p class="text-sm text-gray-600">
                                    Enter the 6-digit code you received.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-col items-start gap-3">
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

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <p class="text-sm text-gray-600">
                                The OTP must be verified before resetting the password.
                            </p>
                            <Button
                                :disabled="loadingStep === 'verify' || !otpSent"
                                @click="verifyOtp"
                            >
                                <LoaderCircle
                                    v-if="loadingStep === 'verify'"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                Verify OTP
                            </Button>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="rounded-full bg-emerald-100 p-2 text-emerald-600">
                                <KeyRound class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">
                                    3. New password
                                </h2>
                                <p class="text-sm text-gray-600">
                                    Choose a strong password and confirm it.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4">
                            <div class="grid gap-2">
                                <Label for="password">Password</Label>
                                <Input
                                    id="password"
                                    v-model="password"
                                    type="password"
                                    autocomplete="new-password"
                                    placeholder="New password"
                                    :disabled="!otpVerified"
                                />
                                <InputError :message="errors.password" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password_confirmation">
                                    Confirm Password
                                </Label>
                                <Input
                                    id="password_confirmation"
                                    v-model="passwordConfirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    placeholder="Confirm password"
                                    :disabled="!otpVerified"
                                />
                                <InputError :message="errors.password_confirmation" />
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <TextLink :href="loginHref">Back to log in</TextLink>

                            <Button
                                :disabled="loadingStep === 'reset' || !otpVerified"
                                @click="resetPassword"
                            >
                                <LoaderCircle
                                    v-if="loadingStep === 'reset'"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                Reset password
                            </Button>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </HomeLayout>
</template>
