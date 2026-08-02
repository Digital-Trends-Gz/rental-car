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
import { AlertCircle } from 'lucide-vue-next';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { create, edit } from '@/routes/admin/cars';
import { index } from '@/routes/admin/cars';
import { destroy } from '@/routes/admin/cars';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
  cars: {
    data: Array<{
      id: number
      make: string
      model: string
      year: number
      license_plate: string
      price_per_day: string | number
      status: string
      status_label?: string
      status_color?: string
      image_url?: string
      branch_id?: number | null
      branch_name?: string | null
    }>
    links: Array<{ url: string | null; label: string; active: boolean }>
  }
  filters: { 
    search?: string
    status?: string 
    branch_id?: number | null
  }
  statuses: Record<string, {
    label: string
    count: number
    color: string
  }>
  branches: Array<{ id: number; name: string }>
  canAccessAllBranches: boolean
  currency: { symbol: string; code: string }
}>()
const { t } = useTrans();
const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);


// Generate status colors based on the colors from the backend
const statusColors = computed(() => {
  const colors: Record<string, { bg: string; text: string; dot: string }> = {};
  
  for (const [status, data] of Object.entries(props.statuses || {})) {
    // Convert hex to RGB for the background with opacity
    const hex = data.color.replace('#', '');
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    
    colors[status] = {
      bg: `rgba(${r}, ${g}, ${b}, 0.1)`,
      text: `text-[${data.color}]`,
      dot: data.color,
    };
  }
  
  return colors;
});

const getStatusColor = (status: string) => {
  return statusColors.value[status] || { 
    bg: 'rgba(107, 114, 128, 0.1)', 
    text: 'text-gray-500', 
    dot: '#6B7280' 
  };
}

const statusLabel = (key: string, fallback: string) => {
  const adminKey = `dashboard.admin.car_statuses.${key}`;
  const adminTranslated = t(adminKey);

  if (adminTranslated !== adminKey) {
    return adminTranslated;
  }

  const dashboardKey = `dashboard.car_statuses.${key}`;
  const dashboardTranslated = t(dashboardKey);

  return dashboardTranslated === dashboardKey ? fallback : dashboardTranslated;
}

const carStatusLabel = (car: { status: string; status_label?: string }) => {
  return statusLabel(car.status, car.status_label || car.status);
}

const search = ref(props.filters?.search || '')

const statusFilter = ref(props.filters?.status || 'all')
const branchFilter = ref(props.filters?.branch_id ? String(props.filters.branch_id) : 'all')

function doSearch() {
  if (!subdomain.value) return;

  router.get(index(subdomain.value).url, { 
    search: search.value,
    status: statusFilter.value === 'all' ? null : statusFilter.value,
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
const carToDelete = ref<number | null>(null);

const openDeleteDialog = (id: number) => {
  carToDelete.value = id;
  showDeleteDialog.value = true;
};

const destroyCar = () => {
  if (!carToDelete.value) return;
  if (!subdomain.value) return;
  
  router.delete(destroy([subdomain.value, carToDelete.value]).url, {
    preserveScroll: true,
    onSuccess: () => {
      showDeleteDialog.value = false;
      carToDelete.value = null;
    },
  });
};
</script>

<template>
    <Head :title="t('dashboard.admin.cars.head_title')" />
    <AdminLayout>
        <!-- Main -->
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ t('dashboard.admin.cars.title') }}</h1>
                <Link v-if="subdomain" :href="create(subdomain).url">
                    <Button >
                        + {{ t('dashboard.admin.cars.new_car') }}
                    </Button>
                </Link>
            </div>

            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-2">
                    <Input
                      v-model="search"
                      :placeholder="t('dashboard.admin.cars.search_placeholder')"
                      class="max-w-md"
                      @keyup.enter="doSearch"
                    />
                    <Button @click="doSearch">{{ t('dashboard.admin.cars.search_button') }}</Button>
                    <select
                      v-if="props.canAccessAllBranches"
                      v-model="branchFilter"
                      class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                      @change="doSearch"
                    >
                      <option value="all">{{ t('dashboard.admin.cars.all_branches') }}</option>
                      <option v-for="branch in props.branches" :key="branch.id" :value="String(branch.id)">
                        {{ branch.name }}
                      </option>
                    </select>
                </div>
                
                <!-- Status Filter -->
                <div class="flex flex-wrap items-center gap-2">
                    <label class="inline-flex items-center">
                        <input 
                            type="radio" 
                            class="hidden" 
                            v-model="statusFilter" 
                            value="all"
                            @change="doSearch"
                        >
                        <span 
                            class="px-3 py-1.5 text-sm rounded-full cursor-pointer transition-colors"
                            :class="{
                                'bg-primary text-primary-foreground': statusFilter === 'all',
                                'bg-muted text-muted-foreground hover:bg-muted/80': statusFilter !== 'all'
                            }"
                        >
                            {{ t('dashboard.admin.cars.all') }} ({{ Object.values(statuses).reduce((acc, curr) => acc + curr.count, 0) }})
                        </span>
                    </label>
                    
                    <template v-for="(status, key) in statuses" :key="key">
                        <label class="inline-flex items-center">
                            <input 
                                type="radio" 
                                class="hidden" 
                                v-model="statusFilter" 
                                :value="key"
                                @change="doSearch"
                            >
                            <span 
                                class="px-3 py-1.5 text-sm rounded-full cursor-pointer transition-colors flex items-center gap-1.5"
                                :class="{
                                    'bg-primary text-primary-foreground': statusFilter === key,
                                    'bg-muted text-muted-foreground hover:bg-muted/80': statusFilter !== key
                                }"
                            >
                                <span 
                                    class="w-2 h-2 rounded-full" 
                                    :style="{ backgroundColor: status.color }"
                                ></span>
                                {{ statusLabel(String(key), status.label) }} ({{ status.count }})
                            </span>
                        </label>
                    </template>
                </div>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.cars.table.image') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.cars.table.car') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.cars.table.plate') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.employees.table.branch') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.cars.table.price_per_day') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.cars.table.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="car in props.cars.data" :key="car.id">
                            <td class="px-4 py-3">
                                <img :src="car.image_url" :alt="t('dashboard.admin.cars.table.car_image_alt')" class="h-12 w-16 object-cover rounded" />
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ car.year }} {{ car.make }} {{ car.model }}</div>
                            </td>
                            <td class="px-4 py-3">{{ car.license_plate }}</td>
                            <td class="px-4 py-3">{{ car.branch_name || t('dashboard.admin.employees.table.no_branch') }}</td>
                            <td class="px-4 py-3">{{ currency.symbol }}{{ Number(car.price_per_day).toFixed(2) }}</td>
                            <td class="px-4 py-3">
                                <span
                                  class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-medium"
                                  :style="{
                                    backgroundColor: getStatusColor(car.status).bg,
                                    color: getStatusColor(car.status).text
                                  }"
                                >
                                  <span 
                                    class="size-2 rounded-full" 
                                    :style="{ backgroundColor: getStatusColor(car.status).dot }"
                                  />
                                  {{ carStatusLabel(car) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <Link :href="`/admin/cars/${car.id}`">
                                    <Button variant="outline" size="sm">{{ t('dashboard.admin.cars.actions.view') }}</Button>
                                </Link>
                                <Link :href="`/admin/cars/${car.id}/documents`">
                                    <Button variant="outline" size="sm">{{ t('dashboard.admin.cars.actions.documents') }}</Button>
                                </Link>
                                <Link v-if="subdomain" :href="`/admin/cars/${car.id}/calendar`">
                                    <Button variant="outline" size="sm">{{ t('dashboard.admin.cars.actions.calendar') }}</Button>
                                </Link>
                                <Link v-if="subdomain" :href="edit([subdomain, car.id]).url">
                                    <Button variant="outline" size="sm">{{ t('dashboard.admin.common.edit') }}</Button>
                                </Link>
                                <Button variant="destructive" size="sm" @click="openDeleteDialog(car.id)">{{ t('dashboard.admin.common.delete') }}</Button>
                            </td>
                        </tr>
                        <tr v-if="props.cars.data.length === 0">
                            <td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ t('dashboard.admin.cars.empty') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="props.cars.links?.length" class="flex gap-2">
                <Link
                    v-for="(link, i) in props.cars.links"
                    :key="i"
                    :href="link.url || ''"
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
        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteDialog">
          <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
              <DialogTitle class="flex items-center gap-2">
                <AlertCircle class="h-5 w-5 text-destructive" />
                {{ t('dashboard.admin.cars.delete_dialog.title') }}
              </DialogTitle>
              <DialogDescription>
                {{ t('dashboard.admin.cars.delete_dialog.description') }}
              </DialogDescription>
            </DialogHeader>
            
            <Alert variant="destructive" class="mt-4">
              <AlertCircle class="h-4 w-4" />
              <AlertDescription>
                {{ t('dashboard.admin.cars.delete_dialog.warning') }}
              </AlertDescription>
            </Alert>
            
            <DialogFooter class="mt-4">
              <DialogClose as-child>
                <Button variant="outline">{{ t('dashboard.admin.common.cancel') }}</Button>
              </DialogClose>
              <Button 
                type="button" 
                variant="destructive"
                @click="destroyCar"
                :disabled="!carToDelete"
              >
                {{ t('dashboard.admin.cars.delete_dialog.confirm') }}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
