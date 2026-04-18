<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ref, watch, computed } from 'vue';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  AlertCircle,
  User,
  MapPin,
  Mail,
  Plus,
  ShieldCheck,
  ShieldAlert,
  Shield,
  Key
} from 'lucide-vue-next';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { create, index, destroy, edit } from '@/routes/admin/employees';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
  employees: {
    data: Array<{
      id: number
      name: string
      email: string
      is_active: boolean
      branch?: {
        id: number
        name: string
      }
      roles?: Array<{ id: number; name: string; display_name: string }>
      direct_permissions?: Array<{ id: number; name: string; display_name: string }>
    }>
    links: Array<{ url: string | null; label: string; active: boolean }>
  }
  filters: { 
    search?: string
    branch_id?: number | null
  }
  branches: Array<{ id: number; name: string }>
  canAccessAllBranches: boolean
}>()

const { t } = useTrans();
const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);

const search = ref(props.filters?.search || '')
const branchFilter = ref(props.filters?.branch_id ? String(props.filters.branch_id) : 'all')

function doSearch() {
  if (!subdomain.value) return;
  router.get(index(subdomain.value).url, { 
    search: search.value,
    branch_id: branchFilter.value === 'all' ? null : Number(branchFilter.value),
  }, {
    preserveState: true,
    replace: true,
  })
}

watch(search, (v, ov) => {
  if (v === '' && ov !== '') doSearch()
})

const showDeleteDialog = ref(false);
const employeeToDelete = ref<number | null>(null);

const openDeleteDialog = (id: number) => {
  // Prevent deleting self if we knew the current auth id easily here, 
  // but the controller handles it.
  employeeToDelete.value = id;
  showDeleteDialog.value = true;
};

const destroyEmployee = () => {
  if (!employeeToDelete.value || !subdomain.value) return;
  
  router.delete(destroy([subdomain.value, employeeToDelete.value]).url, {
    preserveScroll: true,
    onSuccess: () => {
      showDeleteDialog.value = false;
      employeeToDelete.value = null;
    },
  });
};
</script>

<template>
    <Head :title="t('dashboard.admin.employees.head_title')" />
    <AdminLayout>
        <!-- Main -->
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ t('dashboard.admin.employees.title') }}</h1>
                <Link v-if="subdomain" :href="create(subdomain).url">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        {{ t('dashboard.admin.employees.new_employee') }}
                    </Button>
                </Link>
            </div>

            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-2">
                    <Input
                      v-model="search"
                      :placeholder="t('dashboard.admin.employees.search_placeholder')"
                      class="max-w-md"
                      @keyup.enter="doSearch"
                    />
                    <Button @click="doSearch">{{ t('dashboard.common.search') }}</Button>
                    <select
                      v-if="props.canAccessAllBranches"
                      v-model="branchFilter"
                      class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                      @change="doSearch"
                    >
                      <option value="all">{{ t('dashboard.admin.employees.all_branches') }}</option>
                      <option v-for="branch in props.branches" :key="branch.id" :value="String(branch.id)">
                        {{ branch.name }}
                      </option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.employees.table.name') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.employees.table.branch') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.employees.table.roles_permissions') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.employees.table.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="employee in props.employees.data" :key="employee.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center">
                                        <User class="h-4 w-4 text-primary" />
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ employee.name }}</div>
                                        <div class="text-xs text-gray-500 flex items-center gap-1">
                                            <Mail class="h-3 w-3" />
                                            {{ employee.email }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <div class="flex items-center gap-1">
                                    <MapPin class="h-3.5 w-3.5 text-gray-400" />
                                    {{ employee.branch ? employee.branch.name : t('dashboard.admin.employees.table.no_branch') }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1 max-w-[200px]">
                                    <!-- Roles -->
                                    <span 
                                        v-for="role in employee.roles" 
                                        :key="role.id"
                                        class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-700 border border-blue-100"
                                    >
                                        <Shield class="h-2.5 w-2.5" />
                                        {{ role.display_name }}
                                    </span>
                                    <!-- Direct Permissions -->
                                    <span 
                                        v-for="perm in employee.direct_permissions" 
                                        :key="perm.id"
                                        class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 border border-amber-100"
                                    >
                                        <Key class="h-2.5 w-2.5" />
                                        {{ perm.display_name }}
                                    </span>
                                    <span v-if="!employee.roles?.length && !employee.direct_permissions?.length" class="text-xs text-gray-400 italic">
                                        —
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span 
                                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="employee.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                                >
                                    <ShieldCheck v-if="employee.is_active" class="h-3 w-3" />
                                    <ShieldAlert v-else class="h-3 w-3" />
                                    {{ employee.is_active ? t('dashboard.common.active') : t('dashboard.common.suspended') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <Link v-if="subdomain" :href="edit([subdomain, employee.id]).url">
                                    <Button variant="outline" size="sm">{{ t('dashboard.admin.common.edit') }}</Button>
                                </Link>
                                <Button variant="destructive" size="sm" @click="openDeleteDialog(employee.id)">{{ t('dashboard.admin.common.delete') }}</Button>
                            </td>
                        </tr>
                        <tr v-if="props.employees.data.length === 0">
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ t('dashboard.admin.employees.empty') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="props.employees.links?.length" class="flex gap-2">
                <Link
                    v-for="(link, i) in props.employees.links"
                    :key="i"
                    :href="link.url || ''"
                    :class="[
                        'px-3 py-1 rounded text-sm',
                        link.active ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700',
                        !link.url && 'pointer-events-none opacity-50'
                    ]"
                >
                  <span v-html="link.label" />
                </Link>
            </nav>
        </main>
        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteDialog">
          <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
              <DialogTitle class="flex items-center gap-2">
                <AlertCircle class="h-5 w-5 text-destructive" />
                {{ t('dashboard.admin.employees.delete_dialog.title') }}
              </DialogTitle>
              <DialogDescription>
                {{ t('dashboard.admin.employees.delete_dialog.description') }}
              </DialogDescription>
            </DialogHeader>
            
            <Alert variant="destructive" class="mt-4">
              <AlertCircle class="h-4 w-4" />
              <AlertDescription>
                {{ t('dashboard.admin.employees.delete_dialog.warning') }}
              </AlertDescription>
            </Alert>
            
            <DialogFooter class="mt-4">
              <DialogClose as-child>
                <Button variant="outline">{{ t('dashboard.admin.common.cancel') }}</Button>
              </DialogClose>
              <Button 
                type="button" 
                variant="destructive"
                @click="destroyEmployee"
                :disabled="!employeeToDelete"
              >
                {{ t('dashboard.admin.employees.delete_dialog.confirm') }}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
