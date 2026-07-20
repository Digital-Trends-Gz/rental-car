<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(defineProps<{
    status?: string;
    content?: Record<string, string>;
}>(), {
    content: () => ({}),
});

const page = usePage<any>();

const availableLocales = computed<string[]>(() =>
    Array.isArray(page.props?.available_locales) && page.props.available_locales.length
        ? page.props.available_locales
        : ['en'],
);

const localePrefix = computed(() => {
    const currentPath = String(page.url || '/');
    const escapedLocales = availableLocales.value.map((item) => item.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const localeRegex = new RegExp(`^\\/(${escapedLocales.join('|')})(?=\\/|$)`);
    const match = currentPath.match(localeRegex);

    return match ? `/${match[1]}` : '';
});

const verificationNotificationPath = computed(() => `${localePrefix.value}/email/verification-notification`);
const logoutPath = computed(() => `${localePrefix.value}/logout`);
const form = useForm({});

const resendVerificationEmail = () => {
    form.post(verificationNotificationPath.value);
};

const text = (key: string, fallback: string): string => {
    const value = props.content?.[key];

    return typeof value === 'string' && value.trim() !== '' ? value : fallback;
};
</script>

<template>
    <AuthLayout
        :title="text('title', 'Verify email')"
        :description="text('description', 'Please verify your email address by clicking on the link we just emailed to you.')"
    >
        <Head :title="text('head_title', 'Email verification')" />

        <div
            v-if="status === 'verification-link-sent'"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ text('status_message', 'A new verification link has been sent to the email address you provided during registration.') }}
        </div>

        <div class="space-y-6 text-center">
            <Button :disabled="form.processing" variant="secondary" @click="resendVerificationEmail">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                {{ form.processing ? text('resending_button', 'Sending...') : text('resend_button', 'Resend verification email') }}
            </Button>

            <TextLink
                :href="logoutPath"
                method="post"
                as="button"
                class="mx-auto block text-sm"
            >
                {{ text('logout_link', 'Log out') }}
            </TextLink>
        </div>
    </AuthLayout>
</template>
