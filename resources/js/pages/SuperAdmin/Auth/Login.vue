<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const { t } = useTrans();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/superadmin/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-gray-900 px-4 py-12 sm:px-6 lg:px-8">
        <Head :title="t('dashboard.super_admin.login.head_title')" />

        <div class="w-full max-w-md space-y-8">
            <div class="flex flex-col items-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-500 text-white">
                    <ShieldCheck class="h-8 w-8" />
                </div>
                <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-white">
                    {{ t('dashboard.super_admin.login.title') }}
                </h2>
                <p class="mt-2 text-center text-sm text-gray-400">
                    {{ t('dashboard.super_admin.login.subtitle') }}
                </p>
            </div>

            <form class="mt-8 space-y-6" @submit.prevent="submit">
                <div class="-space-y-px rounded-md shadow-sm">
                    <div class="space-y-2">
                        <Label for="email" class="text-white">{{ t('dashboard.super_admin.login.email') }}</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            :placeholder="t('dashboard.super_admin.login.email_placeholder')"
                            class="border-gray-700 bg-gray-800 text-white focus:border-indigo-500"
                        />
                        <div v-if="form.errors.email" class="mt-1 text-sm text-red-500">
                            {{ form.errors.email }}
                        </div>
                    </div>

                    <div class="space-y-2 pt-4">
                        <Label for="password" class="text-white">{{ t('dashboard.super_admin.login.password') }}</Label>
                        <Input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            :placeholder="t('dashboard.super_admin.login.password_placeholder')"
                            class="border-gray-700 bg-gray-800 text-white focus:border-indigo-500"
                        />
                        <div v-if="form.errors.password" class="mt-1 text-sm text-red-500">
                            {{ form.errors.password }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input
                            id="remember-me"
                            v-model="form.remember"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-700 bg-gray-800 text-indigo-600 focus:ring-indigo-500"
                        />
                        <label for="remember-me" class="ml-2 block text-sm text-gray-400">
                            {{ t('dashboard.super_admin.login.remember_me') }}
                        </label>
                    </div>
                </div>

                <div>
                    <Button
                        type="submit"
                        class="w-full rounded bg-indigo-600 px-4 py-2 font-bold text-white hover:bg-indigo-700"
                        :disabled="form.processing"
                    >
                        <span v-if="form.processing">{{ t('dashboard.super_admin.login.submitting') }}</span>
                        <span v-else>{{ t('dashboard.super_admin.login.submit') }}</span>
                    </Button>
                </div>

                <div class="pt-4 text-center">
                    <Link href="/" class="text-sm text-gray-500 transition-colors hover:text-gray-300">
                        {{ t('dashboard.super_admin.login.return_to_site') }}
                    </Link>
                </div>
            </form>
        </div>
    </div>
</template>
