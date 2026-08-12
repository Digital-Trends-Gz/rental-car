<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { index, store, update } from '@/routes/admin/employees';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    employee: any | null;
    branches: Array<{ id: number; name: string }>;
    roles: Array<{ id: number; name?: string; display_name: string }>;
    permissions: Array<{ id: number; display_name: string; description: string }>;
    canManageRolesAndPermissions: boolean;
}>();

const { t } = useTrans();
const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);

const isEdit = computed(() => !!props.employee);

// Initialize form with default values
const form = useForm({
    name: props.employee?.name ?? '',
    email: props.employee?.email ?? '',
    civil_number: props.employee?.civil_number ?? '',
    branch_id: props.employee?.branch_id ?? '',
    is_active: props.employee?.is_active ?? true,
    role_ids: props.employee?.role_ids ?? [],
    permission_ids: props.employee?.permission_ids ?? [],
    password: '',
    password_confirmation: '',
});

function submit() {
    if (!subdomain.value) return;
    
    if (isEdit.value) {
        form.put(update([subdomain.value, props.employee.id]).url);
    } else {
        form.post(store(subdomain.value).url, {
            onSuccess: () => {
                form.reset();
            },
        });
    }
}

function toggleArrayValue(target: number[], id: number, checked: boolean) {
    const idx = target.indexOf(id);

    if (checked && idx === -1) {
        target.push(id);
        return;
    }

    if (!checked && idx > -1) {
        target.splice(idx, 1);
    }
}

function onRoleCheckboxChange(id: number, event: Event) {
    const checked = (event.target as HTMLInputElement).checked;
    toggleArrayValue(form.role_ids, id, checked);
}

function onPermissionCheckboxChange(id: number, event: Event) {
    const checked = (event.target as HTMLInputElement).checked;
    toggleArrayValue(form.permission_ids, id, checked);
}

function onActiveCheckboxChange(event: Event) {
    form.is_active = (event.target as HTMLInputElement).checked;
}

function roleDisplayName(role: { name?: string; display_name: string }) {
    if (role.name === 'tenant-partner') {
        return t('dashboard.admin.employees.form.tenant_partner_role');
    }

    return role.display_name;
}
</script>

<template>
    <Head :title="isEdit ? t('dashboard.admin.employees.edit_employee') : t('dashboard.admin.employees.new_employee')" />
    <AdminLayout>
        <!-- Main -->
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{ isEdit ? t('dashboard.admin.employees.edit_employee') : t('dashboard.admin.employees.new_employee') }}
                </h1>
                <Link v-if="subdomain" :href="index(subdomain).url">
                    <Button variant="outline">{{ t('dashboard.admin.common.back') }}</Button>
                </Link>
            </div>

            <div class="max-w-2xl">
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Name -->
                        <div class="md:col-span-2">
                            <Label for="name">{{ t('dashboard.admin.employees.table.name') }}</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                required
                            />
                            <InputError :message="form.errors.name" class="mt-1" />
                        </div>

                        <!-- Email -->
                        <div class="md:col-span-2">
                            <Label for="email">{{ t('dashboard.admin.employees.table.email') }}</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                            />
                            <InputError :message="form.errors.email" class="mt-1" />
                        </div>

                        <!-- Civil Number -->
                        <div class="md:col-span-2">
                            <Label for="civil_number">{{ t('dashboard.admin.employees.form.civil_number') }}</Label>
                            <Input
                                id="civil_number"
                                v-model="form.civil_number"
                                type="text"
                                :placeholder="t('dashboard.admin.employees.form.civil_number_placeholder')"
                                required
                            />
                            <InputError :message="form.errors.civil_number" class="mt-1" />
                        </div>

                        <!-- Branch -->
                        <div class="md:col-span-2">
                            <Label for="branch_id">{{ t('dashboard.admin.employees.table.branch') }}</Label>
                            <select
                                id="branch_id"
                                v-model="form.branch_id"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="">{{ t('dashboard.admin.employees.table.no_branch') }}</option>
                                <option v-for="branch in props.branches" :key="branch.id" :value="branch.id">
                                    {{ branch.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.branch_id" class="mt-1" />
                        </div>

                        <!-- Roles -->
                        <div v-if="props.canManageRolesAndPermissions" class="md:col-span-2 space-y-3">
                            <Label>{{ t('dashboard.admin.roles.title') }}</Label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 border rounded-md p-4">
                                <div v-for="role in props.roles" :key="role.id" class="flex items-center space-x-2">
                                    <input
                                        :id="`role-${role.id}`"
                                        type="checkbox"
                                        :checked="form.role_ids.includes(role.id)"
                                        @change="onRoleCheckboxChange(role.id, $event)"
                                        class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                    />
                                    <Label :for="`role-${role.id}`" class="text-sm font-medium leading-none cursor-pointer">
                                        {{ roleDisplayName(role) }}
                                    </Label>
                                </div>
                                <div v-if="props.roles.length === 0" class="col-span-2 text-sm text-gray-500 italic">
                                    {{ t('dashboard.admin.roles.empty') }}
                                </div>
                            </div>
                            <InputError :message="form.errors.role_ids" class="mt-1" />
                        </div>

                        <!-- Direct Permissions -->
                        <div v-if="props.canManageRolesAndPermissions" class="md:col-span-2 space-y-3 border-t pt-6">
                            <div>
                                <Label>{{ t('dashboard.admin.employees.form.direct_permissions') }}</Label>
                                <p class="text-[13px] text-muted-foreground">
                                    {{ t('dashboard.admin.employees.form.direct_permissions_help') }}
                                </p>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 border rounded-md p-4 bg-amber-50/10">
                                <div v-for="permission in props.permissions" :key="permission.id" class="flex items-start space-x-2">
                                    <input
                                        :id="`permission-${permission.id}`"
                                        type="checkbox"
                                        :checked="form.permission_ids.includes(permission.id)"
                                        @change="onPermissionCheckboxChange(permission.id, $event)"
                                        class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                    />
                                    <div class="grid gap-1.5 leading-none">
                                        <Label :for="`permission-${permission.id}`" class="text-sm font-medium leading-none cursor-pointer">
                                            {{ permission.display_name }}
                                        </Label>
                                        <p class="text-[12px] text-muted-foreground">
                                            {{ permission.description }}
                                        </p>
                                    </div>
                                </div>
                                <div v-if="props.permissions.length === 0" class="col-span-2 text-sm text-gray-500 italic">
                                    {{ t('dashboard.admin.roles.empty') }}
                                </div>
                            </div>
                            <InputError :message="form.errors.permission_ids" class="mt-1" />
                        </div>

                        <!-- Password -->
                        <div>
                            <Label for="password">{{ t('dashboard.admin.employees.form.password') }}</Label>
                            <Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                :required="!isEdit"
                            />
                            <p v-if="isEdit" class="text-xs text-muted-foreground mt-1">
                                {{ t('dashboard.admin.employees.form.password_help') }}
                            </p>
                            <InputError :message="form.errors.password" class="mt-1" />
                        </div>

                        <!-- Password Confirmation -->
                        <div>
                            <Label for="password_confirmation">{{ t('dashboard.admin.employees.form.password_confirmation') }}</Label>
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                :required="!isEdit && form.password"
                            />
                            <InputError :message="form.errors.password_confirmation" class="mt-1" />
                        </div>

                        <!-- Is Active -->
                        <div class="md:col-span-2 flex items-center space-x-2">
                            <input
                                id="is_active"
                                type="checkbox"
                                :checked="form.is_active"
                                @change="onActiveCheckboxChange"
                                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                            />
                            <Label for="is_active" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                                {{ t('dashboard.common.active') }}
                            </Label>
                            <InputError :message="form.errors.is_active" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <Button type="submit" :disabled="form.processing">
                            {{
                                form.processing
                                    ? isEdit
                                        ? t('dashboard.admin.common.saving')
                                        : t('dashboard.admin.common.creating')
                                    : isEdit
                                      ? t('dashboard.admin.common.save_changes')
                                      : t('dashboard.admin.employees.new_employee')
                            }}
                        </Button>
                        <Link v-if="subdomain" :href="index(subdomain).url">
                            <Button type="button" variant="outline">{{ t('dashboard.admin.common.cancel') }}</Button>
                        </Link>
                    </div>
                </form>
            </div>
        </main>
    </AdminLayout>
</template>
