<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { index, store, update } from '@/routes/admin/roles';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    role: any | null;
    permissions: Array<{ id: number; name: string; display_name: string; description: string; module?: string; action?: string; legacy?: string | null }>;
}>();

const { t } = useTrans();
const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);

const isEdit = computed(() => !!props.role);
const permissionsByName = computed<Record<string, number>>(() =>
    props.permissions.reduce((acc, permission) => {
        acc[permission.name] = permission.id;
        return acc;
    }, {} as Record<string, number>),
);

const rolePresets = [
    {
        key: 'manager',
        label: 'Manager',
        description: 'Broad access to operations, payments, debtors, and reports.',
        display_name: 'Manager',
        slug: 'manager',
        permissionNames: [
            'tenant-cars.view',
            'tenant-cars.create',
            'tenant-cars.update',
            'tenant-reservations.view',
            'tenant-reservations.create',
            'tenant-reservations.update',
            'tenant-contracts.view',
            'tenant-contracts.create',
            'tenant-contracts.update',
            'tenant-contracts.handover',
            'tenant-clients.view',
            'tenant-clients.create',
            'tenant-clients.update',
            'tenant-payments.view',
            'tenant-debtors.view',
            'tenant-financials.view',
            'tenant-reports.view',
        ],
    },
    {
        key: 'accountant',
        label: 'Accountant',
        description: 'Payment handling and financial visibility without operational access.',
        display_name: 'Accountant',
        slug: 'accountant',
        permissionNames: [
            'tenant-payments.view',
            'tenant-debtors.view',
            'tenant-payments.collect',
            'tenant-financials.view',
            'tenant-reports.view',
            'tenant-reports.export',
        ],
    },
    {
        key: 'collector',
        label: 'Collector',
        description: 'Debtor follow-up and collection work.',
        display_name: 'Collector',
        slug: 'collector',
        permissionNames: [
            'tenant-debtors.view',
            'tenant-payments.collect',
            'tenant-financials.view',
        ],
    },
    {
        key: 'cashier',
        label: 'Cashier',
        description: 'Register payments with minimal access.',
        display_name: 'Cashier',
        slug: 'cashier',
        permissionNames: [
            'tenant-payments.view',
            'tenant-payments.collect',
        ],
    },
] as const;

const groupedPermissions = computed(() => {
    const groups = new Map<string, typeof props.permissions>();

    props.permissions.forEach((permission) => {
        const module = permission.module || 'Other';
        if (!groups.has(module)) {
            groups.set(module, []);
        }
        groups.get(module)?.push(permission);
    });

    return Array.from(groups.entries()).map(([module, permissions]) => ({
        module,
        permissions,
    }));
});

// Initialize form with default values
const form = useForm({
    display_name: props.role?.display_name ?? '',
    description: props.role?.description ?? '',
    permission_ids: props.role?.permission_ids ?? [],
});

function togglePermission(id: number) {
    const index = form.permission_ids.indexOf(id);
    if (index > -1) {
        form.permission_ids.splice(index, 1);
    } else {
        form.permission_ids.push(id);
    }
}

function applyPreset(preset: (typeof rolePresets)[number]) {
    form.display_name = preset.display_name;
    form.description = preset.description;
    form.permission_ids = preset.permissionNames
        .map((name) => permissionsByName.value[name])
        .filter((id): id is number => typeof id === 'number');
}

function submit() {
    if (!subdomain.value) return;

    if (isEdit.value) {
        form.put(update([subdomain.value, props.role.id]).url);
    } else {
        form.post(store(subdomain.value).url, {
            onSuccess: () => {
                form.reset();
            },
        });
    }
}
</script>

<template>

    <Head :title="isEdit ? t('dashboard.admin.roles.edit_role') : t('dashboard.admin.roles.new_role')" />
    <AdminLayout>
        <!-- Main -->
        <main class="flex-1 space-y-6 p-8 text-indigo-900">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{ isEdit ? t('dashboard.admin.roles.edit_role') : t('dashboard.admin.roles.new_role') }}
                </h1>
                <Link v-if="subdomain" :href="index(subdomain).url">
                    <Button variant="outline">{{ t('dashboard.admin.common.back') }}</Button>
                </Link>
            </div>

            <div class="max-w-4xl">
                <form class="space-y-8" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-6 bg-white p-6 rounded-lg border shadow-sm">
                        <div>
                            <Label class="mb-2 block">{{ t('dashboard.admin.roles.form.presets') || 'Role presets' }}</Label>
                            <div class="grid gap-3 md:grid-cols-2">
                                <button
                                    v-for="preset in rolePresets"
                                    :key="preset.key"
                                    type="button"
                                    class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-left transition hover:border-indigo-200 hover:bg-indigo-50"
                                    @click="applyPreset(preset)"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="font-semibold">{{ preset.label }}</div>
                                        <span class="rounded-full bg-white px-2 py-0.5 text-xs text-gray-500">{{ preset.slug }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-600">{{ preset.description }}</p>
                                </button>
                            </div>
                        </div>

                        <!-- Display Name -->
                        <div>
                            <Label for="display_name">{{ t('dashboard.admin.roles.form.display_name') }}</Label>
                            <Input id="display_name" v-model="form.display_name" required class="mt-1" />
                            <InputError :message="form.errors.display_name" class="mt-1" />
                        </div>

                        <!-- Description -->
                        <div>
                            <Label for="description">{{ t('dashboard.admin.roles.form.description') }}</Label>
                            <Input id="description" v-model="form.description" class="mt-1" />
                            <InputError :message="form.errors.description" class="mt-1" />
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-lg border shadow-sm space-y-4">
                        <div class="flex flex-col space-y-1">
                            <h3 class="text-lg font-medium">{{ t('dashboard.admin.roles.form.permissions') }}</h3>
                            <p class="text-sm text-gray-500">{{ t('dashboard.admin.roles.form.permissions_help') }}</p>
                        </div>

                        <div class="space-y-5 border-t pt-4">
                            <section v-for="group in groupedPermissions" :key="group.module" class="space-y-3">
                                <h4 class="text-sm font-semibold text-gray-700">{{ group.module }}</h4>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                                    <div v-for="permission in group.permissions" :key="permission.id"
                                        class="flex items-start space-x-3 rounded-md border border-transparent p-3 transition-colors hover:border-indigo-100 hover:bg-indigo-50">
                                        <input :id="`perm-${permission.id}`" type="checkbox"
                                            :checked="form.permission_ids.includes(permission.id)"
                                            @change="togglePermission(permission.id)"
                                            class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        <div class="space-y-1">
                                            <label :for="`perm-${permission.id}`"
                                                class="cursor-pointer text-sm font-semibold leading-none">
                                                {{ permission.display_name }}
                                            </label>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600">{{ permission.action || 'Access' }}</span>
                                                <span v-if="!permission.legacy" class="rounded bg-amber-50 px-1.5 py-0.5 text-[11px] text-amber-700">Legacy</span>
                                            </div>
                                            <p class="text-xs italic text-gray-500">{{ permission.description }}</p>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                        <InputError :message="form.errors.permission_ids" class="mt-1" />
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <Button type="submit" :disabled="form.processing" size="lg">
                            {{
                                form.processing
                                    ? isEdit
                                        ? t('dashboard.admin.common.saving')
                                        : t('dashboard.admin.common.creating')
                                    : isEdit
                                        ? t('dashboard.admin.common.save_changes')
                                        : t('dashboard.admin.roles.new_role')
                            }}
                        </Button>
                        <Link v-if="subdomain" :href="index(subdomain).url">
                            <Button type="button" variant="outline" size="lg">{{ t('dashboard.admin.common.cancel')
                                }}</Button>
                        </Link>
                    </div>
                </form>
            </div>
        </main>
    </AdminLayout>
</template>
