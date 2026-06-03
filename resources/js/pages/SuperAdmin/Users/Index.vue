<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { UserCircle, Mail, Shield, KeyRound, Trash2 } from 'lucide-vue-next';
import Auth from '@/actions/App/Http/Controllers/Auth';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            // add other user properties as needed
        };
    };
    users: {
        data: Array<{
            id: number;
            name: string;
            email: string;
            role: string;
            created_at: string;
            roles?: Array<{ id: number; name: string; display_name: string | null }>;
            permissions?: Array<{ id: number; name: string; display_name: string | null }>;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();
const { t, locale } = useTrans();

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString(locale === 'ar' ? 'ar' : 'en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const deleteUser = (userId: number, userName: string) => {
    if (confirm(`Are you sure you want to delete the user "${userName}"? This action cannot be undone.`)) {
        router.delete(`/superadmin/users/${userId}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="t('dashboard.super_admin.users.index.head_title')" />
    <SuperAdminLayout>
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ t('dashboard.super_admin.users.index.title') }}</h1>
                <Link href="/superadmin/users/create">
                    <Button>+ {{ t('dashboard.super_admin.users.index.add_user') }}</Button>
                </Link>
            </div>

            <p class="text-muted-foreground text-sm">
                {{ t('dashboard.super_admin.users.index.subtitle') }}
            </p>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.super_admin.users.index.user') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.email') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.super_admin.roles.index.role') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.super_admin.roles.index.permissions') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.created') }}</th>
                            <th class="px-4 py-3 text-right">{{ t('dashboard.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="user in props.users.data" :key="user.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                        <UserCircle class="h-5 w-5 text-primary" />
                                    </div>
                                    <span class="font-medium">{{ user.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 text-sm text-gray-600">
                                    <Mail class="h-3 w-3" />
                                    {{ user.email }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="role in (user.roles || [])"
                                        :key="role.id"
                                        class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700"
                                    >
                                        <Shield class="h-3 w-3 mr-1" />
                                        {{ role.display_name || role.name }}
                                    </span>
                                    <span v-if="!(user.roles?.length)" class="text-sm text-muted-foreground">—</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1 max-w-xs">
                                    <span
                                        v-for="perm in (user.permissions || [])"
                                        :key="perm.id"
                                        class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700"
                                    >
                                        <KeyRound class="h-3 w-3 mr-1" />
                                        {{ perm.display_name || perm.name }}
                                    </span>
                                    <span v-if="!(user.permissions?.length)" class="text-sm text-muted-foreground">—</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                {{ formatDate(user.created_at) }}
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <Link :href="`/superadmin/users/${user.id}/edit`">
                                    <Button variant="outline" size="sm">{{ t('dashboard.common.edit') }}</Button>
                                </Link>
                                
                                <Button
                                    v-if="user.id !== auth.user.id"
                                    variant="destructive"
                                    size="sm"
                                    @click="deleteUser(user.id, user.name)"
                                >
                                    <Trash2 class="h-4 w-4 mr-1" />
                                    {{ t('dashboard.common.delete') }}
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="props.users.data.length === 0">
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                {{ t('dashboard.super_admin.users.index.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="props.users.links?.length" class="flex gap-2 flex-wrap">
                <Link
                    v-for="(link, i) in props.users.links"
                    :key="i"
                    :href="link.url || '#'"
                    :class="[
                        'px-3 py-1 rounded text-sm',
                        link.active ? 'bg-primary text-primary-foreground' : 'bg-gray-100 text-gray-700',
                        !link.url && 'pointer-events-none opacity-50'
                    ]"
                >
                    <span v-html="link.label" />
                </Link>
            </nav>
        </main>
    </SuperAdminLayout>
</template>
