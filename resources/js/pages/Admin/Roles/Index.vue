<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { ref, computed } from 'vue';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { AlertCircle, Shield, Plus, Lock } from 'lucide-vue-next';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { create, index, destroy } from '@/routes/admin/roles';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
  roles: Array<{
    id: number
    name: string
    display_name: string
    description: string
    permissions_count: number
  }>
}>()

const { t } = useTrans();
const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);

const showDeleteDialog = ref(false);
const roleToDelete = ref<number | null>(null);

const openDeleteDialog = (id: number) => {
  roleToDelete.value = id;
  showDeleteDialog.value = true;
};

const destroyRole = () => {
  if (!roleToDelete.value || !subdomain.value) return;
  
  router.delete(destroy([subdomain.value, roleToDelete.value]).url, {
    preserveScroll: true,
    onSuccess: () => {
      showDeleteDialog.value = false;
      roleToDelete.value = null;
    },
  });
};

function roleDisplayName(role: { name: string; display_name: string }) {
  if (role.name === 'tenant-partner') {
    return t('dashboard.admin.employees.form.tenant_partner_role');
  }

  return role.display_name;
}

function roleDescription(role: { name: string; description: string | null }) {
  if (role.name === 'tenant-partner') {
    return t('dashboard.admin.employees.form.tenant_partner_role_description');
  }

  return role.description || '-';
}
</script>

<template>
    <Head :title="t('dashboard.admin.roles.head_title')" />
    <AdminLayout>
        <!-- Main -->
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ t('dashboard.admin.roles.title') }}</h1>
                <Link v-if="subdomain" :href="create(subdomain).url">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        {{ t('dashboard.admin.roles.new_role') }}
                    </Button>
                </Link>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.roles.table.display_name') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.roles.table.description') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.roles.table.permissions_count') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="role in props.roles" :key="role.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center">
                                        <Shield class="h-4 w-4 text-indigo-600" />
                                    </div>
                                    <div class="font-medium text-gray-900">{{ roleDisplayName(role) }}</div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ roleDescription(role) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full w-fit">
                                    <Lock class="h-3 w-3" />
                                    {{ role.permissions_count }} {{ t('dashboard.admin.roles.form.permissions') }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <Link v-if="subdomain" :href="`/admin/roles/${role.id}/edit`">
                                    <Button variant="outline" size="sm">{{ t('dashboard.admin.common.edit') }}</Button>
                                </Link>
                                <Button variant="destructive" size="sm" @click="openDeleteDialog(role.id)">{{ t('dashboard.admin.common.delete') }}</Button>
                            </td>
                        </tr>
                        <tr v-if="props.roles.length === 0">
                            <td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ t('dashboard.admin.roles.empty') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteDialog">
          <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
              <DialogTitle class="flex items-center gap-2">
                <AlertCircle class="h-5 w-5 text-destructive" />
                {{ t('dashboard.admin.roles.delete_dialog.title') }}
              </DialogTitle>
              <DialogDescription>
                {{ t('dashboard.admin.roles.delete_dialog.description') }}
              </DialogDescription>
            </DialogHeader>
            
            <Alert variant="destructive" class="mt-4">
              <AlertCircle class="h-4 w-4" />
              <AlertDescription>
                {{ t('dashboard.admin.roles.delete_dialog.warning') }}
              </AlertDescription>
            </Alert>
            
            <DialogFooter class="mt-4">
              <DialogClose as-child>
                <Button variant="outline">{{ t('dashboard.admin.common.cancel') }}</Button>
              </DialogClose>
              <Button 
                type="button" 
                variant="destructive"
                @click="destroyRole"
                :disabled="!roleToDelete"
              >
                {{ t('dashboard.admin.roles.delete_dialog.confirm') }}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
