<script setup lang="ts">
import AuthLanguageSwitcher from '@/components/AuthLanguageSwitcher.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store as mainPasswordStore } from '@/routes/password/index.ts';
import { store as tenantPasswordStore } from '@/routes/tenant/password/index.ts';
import { Form, Head, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    token: string;
    email: string;
}>();

const { t } = useTrans();
const page = usePage<any>();
const currentTenant = computed(() => page.props.current_tenant);

const passwordAction = computed(() => {
    const slug = currentTenant.value?.slug;
    return (slug ? tenantPasswordStore.form(slug) : mainPasswordStore.form()) as any;
});

const inputEmail = ref(props.email || '');
</script>

<template>
    <AuthLayout
        :title="t('auth.reset_password_page.title')"
        :description="t('auth.reset_password_page.subtitle')"
    >
        <Head :title="t('auth.reset_password_page.head_title')" />

        <Form
            v-bind="passwordAction"
                :transform="(data) => ({ ...data, token, email: inputEmail })"
                :reset-on-success="['password', 'password_confirmation']"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="email">{{ t('auth.reset_password_page.email_label') }}</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autocomplete="email"
                            v-model="inputEmail"
                            class="mt-1 block w-full"
                            readonly
                        />
                        <InputError :message="errors.email" class="mt-2" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">{{ t('auth.reset_password_page.new_password_label') }}</Label>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            class="mt-1 block w-full"
                            autofocus
                            :placeholder="t('auth.reset_password_page.new_password_placeholder')"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">
                            {{ t('auth.reset_password_page.confirm_password_label') }}
                        </Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            autocomplete="new-password"
                            class="mt-1 block w-full"
                            :placeholder="t('auth.reset_password_page.confirm_password_placeholder')"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>

                    <Button
                        type="submit"
                        class="mt-4 w-full"
                        :disabled="processing"
                        data-test="reset-password-button"
                    >
                        <LoaderCircle
                            v-if="processing"
                            class="h-4 w-4 animate-spin"
                        />
                        {{ t('auth.reset_password_page.reset_password') }}
                    </Button>
                </div>
            </Form>
        </AuthLayout>
</template>
