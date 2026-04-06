<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const { t } = useTrans();

const props = defineProps<{
    roles: Array<{ id: number; name: string; display_name: string | null; description: string | null }>;
}>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role_ids: [] as number[],
});

const submit = () => {
    form.post('/superadmin/users', { preserveScroll: true });
};
</script>

<template>
    <Head :title="t('dashboard.super_admin.users.create.head_title')" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center gap-4">
                <Link href="/superadmin/users">
                    <Button variant="outline">{{ t('dashboard.super_admin.common.back') }}</Button>
                </Link>
                <h1 class="text-2xl font-semibold">{{ t('dashboard.super_admin.users.create.title') }}</h1>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <Card class="max-w-2xl">
                    <CardHeader>
                        <CardTitle>{{ t('dashboard.super_admin.users.create.card_title') }}</CardTitle>
                        <CardDescription>{{ t('dashboard.super_admin.users.create.card_description') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">{{ t('dashboard.super_admin.users.create.name') }} *</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                :placeholder="t('dashboard.super_admin.users.create.name_placeholder')"
                                required
                            />
                            <div v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</div>
                        </div>

                        <div class="space-y-2">
                            <Label for="email">{{ t('dashboard.super_admin.users.create.email') }} *</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                :placeholder="t('dashboard.super_admin.users.create.email_placeholder')"
                                required
                            />
                            <div v-if="form.errors.email" class="text-sm text-red-600">{{ form.errors.email }}</div>
                        </div>

                        <div class="space-y-2">
                            <Label for="password">{{ t('dashboard.super_admin.users.create.password') }} *</Label>
                            <Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                :placeholder="t('dashboard.super_admin.users.create.password_placeholder')"
                                required
                                autocomplete="new-password"
                            />
                            <div v-if="form.errors.password" class="text-sm text-red-600">{{ form.errors.password }}</div>
                        </div>

                        <div class="space-y-2">
                            <Label for="password_confirmation">{{ t('dashboard.super_admin.users.create.password_confirmation') }} *</Label>
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                :placeholder="t('dashboard.super_admin.users.create.password_placeholder')"
                                required
                                autocomplete="new-password"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card class="max-w-2xl">
                    <CardHeader>
                        <CardTitle>{{ t('dashboard.super_admin.users.create.roles_title') }}</CardTitle>
                        <CardDescription>{{ t('dashboard.super_admin.users.create.roles_description') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="role in props.roles"
                            :key="role.id"
                            class="flex items-center space-x-2"
                        >
                            <input
                                :id="`role-${role.id}`"
                                v-model="form.role_ids"
                                :value="role.id"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                            />
                            <label :for="`role-${role.id}`" class="cursor-pointer text-sm font-normal">
                                {{ role.display_name || role.name }}
                                <span v-if="role.description" class="text-gray-500"> — {{ role.description }}</span>
                            </label>
                        </div>

                        <p v-if="props.roles.length === 0" class="text-sm text-gray-500">
                            {{ t('dashboard.super_admin.users.create.no_roles') }}
                        </p>

                        <div class="mt-4 rounded bg-gray-100 p-3 font-mono text-xs">
                            <strong>{{ t('dashboard.super_admin.users.create.selected_role_ids') }}:</strong>
                            {{ form.role_ids.join(', ') || t('dashboard.super_admin.users.create.none') }}
                        </div>
                    </CardContent>
                </Card>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? t('dashboard.super_admin.common.creating') : t('dashboard.super_admin.users.create.create') }}
                    </Button>
                    <Link href="/superadmin/users">
                        <Button type="button" variant="outline">{{ t('dashboard.super_admin.common.cancel') }}</Button>
                    </Link>
                </div>
            </form>
        </main>
    </SuperAdminLayout>
</template>
