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
import { AlertCircle, MapPin, Phone, Mail, Plus } from 'lucide-vue-next';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { create, index, destroy } from '@/routes/admin/branches';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    branches: {
      data: Array<{
      id: number
      name: string
      address: string
      phone: string
      email: string
      cr_number?: string | null
      manager_name?: string | null
      manager_civil_number?: string | null
    }>
    links: Array<{ url: string | null; label: string; active: boolean }>
  }
  filters: { 
    search?: string
  }
}>()

const { t } = useTrans();
const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);

const search = ref(props.filters?.search || '')

function doSearch() {
  if (!subdomain.value) return;
  router.get(index(subdomain.value).url, { 
    search: search.value,
  }, {
    preserveState: true,
    replace: true,
  })
}

watch(search, (v, ov) => {
  if (v === '' && ov !== '') doSearch()
})

const showDeleteDialog = ref(false);
const branchToDelete = ref<number | null>(null);

const openDeleteDialog = (id: number) => {
  branchToDelete.value = id;
  showDeleteDialog.value = true;
};

const destroyBranch = () => {
  if (!branchToDelete.value || !subdomain.value) return;
  
  router.delete(destroy([subdomain.value, branchToDelete.value]).url, {
    preserveScroll: true,
    onSuccess: () => {
      showDeleteDialog.value = false;
      branchToDelete.value = null;
    },
  });
};
</script>

<template>
    <Head :title="t('dashboard.admin.branches.head_title')" />
    <AdminLayout>
        <!-- Main -->
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ t('dashboard.admin.branches.title') }}</h1>
                <Link v-if="subdomain" :href="create(subdomain).url">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        {{ t('dashboard.admin.branches.new_branch') }}
                    </Button>
                </Link>
            </div>

            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-2">
                    <Input
                      v-model="search"
                      :placeholder="t('dashboard.admin.branches.search_placeholder')"
                      class="max-w-md"
                      @keyup.enter="doSearch"
                    />
                    <Button @click="doSearch">{{ t('dashboard.common.search') }}</Button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.branches.table.name') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.branches.table.cr_number') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.branches.table.manager_name') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.branches.table.manager_civil_number') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.branches.table.address') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.branches.table.phone') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.branches.table.email') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="branch in props.branches.data" :key="branch.id">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ branch.name }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ branch.cr_number || '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ branch.manager_name || '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ branch.manager_civil_number || '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <div class="flex items-center gap-1">
                                    <MapPin class="h-3.5 w-3.5 text-gray-400" />
                                    {{ branch.address }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <div class="flex items-center gap-1">
                                    <Phone class="h-3.5 w-3.5 text-gray-400" />
                                    {{ branch.phone }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <div class="flex items-center gap-1">
                                    <Mail class="h-3.5 w-3.5 text-gray-400" />
                                    {{ branch.email }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <Link v-if="subdomain" :href="`/admin/branches/${branch.id}/edit`">
                                    <Button variant="outline" size="sm">{{ t('dashboard.admin.common.edit') }}</Button>
                                </Link>
                                <Button variant="destructive" size="sm" @click="openDeleteDialog(branch.id)">{{ t('dashboard.admin.common.delete') }}</Button>
                            </td>
                        </tr>
                        <tr v-if="props.branches.data.length === 0">
                            <td colspan="8" class="px-4 py-6 text-center text-gray-500">{{ t('dashboard.admin.branches.empty') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="props.branches.links?.length" class="flex gap-2">
                <Link
                    v-for="(link, i) in props.branches.links"
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
                {{ t('dashboard.admin.branches.delete_dialog.title') }}
              </DialogTitle>
              <DialogDescription>
                {{ t('dashboard.admin.branches.delete_dialog.description') }}
              </DialogDescription>
            </DialogHeader>
            
            <Alert variant="destructive" class="mt-4">
              <AlertCircle class="h-4 w-4" />
              <AlertDescription>
                {{ t('dashboard.admin.branches.delete_dialog.warning') }}
              </AlertDescription>
            </Alert>
            
            <DialogFooter class="mt-4">
              <DialogClose as-child>
                <Button variant="outline">{{ t('dashboard.admin.common.cancel') }}</Button>
              </DialogClose>
              <Button 
                type="button" 
                variant="destructive"
                @click="destroyBranch"
                :disabled="!branchToDelete"
              >
                {{ t('dashboard.admin.branches.delete_dialog.confirm') }}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
