<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ref, watch, computed } from 'vue';
import { create, show, index } from '@/routes/admin/clients';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
  clients: {
    data: Array<{
      id: number
      name: string
      email: string
      is_active: boolean
      reservations_count: number
      payments_count: number
      created_at?: string
      branch?: { id: number; name: string } | null
      client_status?: {
        status: 'good' | 'info' | 'warning' | 'danger'
        label: string
        can_book: boolean
        flags_count: number
        blocking_flags: string[]
      }
    }>
    links: Array<{ url: string | null; label: string; active: boolean }>
  }
  filters: {
    search?: string
    status?: string
    branch_id?: number | null
  }
  statuses: Record<string, { label: string; count: number; color: string }>
  branches: Array<{ id: number; name: string }>
  canAccessAllBranches: boolean
}>()
const { t, locale } = useTrans();
const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const statusColors = computed(() => {
  const colors: Record<string, { bg: string; text: string; dot: string }> = {};
  for (const [status, data] of Object.entries(props.statuses || {})) {
    const hex = (data as any).color?.replace('#', '') || '6B7280';
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    colors[status] = {
      bg: `rgba(${r}, ${g}, ${b}, 0.1)`,
      text: (data as any).color,
      dot: (data as any).color,
    };
  }
  return colors;
});

const getStatusColor = (status: string) => {
  return statusColors.value[status] || {
    bg: 'rgba(107, 114, 128, 0.1)',
    text: '#6B7280',
    dot: '#6B7280',
  };
};

const clientStatusColor = (status?: string) => {
  const colors: Record<string, { bg: string; text: string; dot: string }> = {
    good: { bg: 'rgba(16, 185, 129, 0.12)', text: '#047857', dot: '#10B981' },
    info: { bg: 'rgba(59, 130, 246, 0.12)', text: '#1D4ED8', dot: '#3B82F6' },
    warning: { bg: 'rgba(245, 158, 11, 0.14)', text: '#B45309', dot: '#F59E0B' },
    danger: { bg: 'rgba(239, 68, 68, 0.12)', text: '#B91C1C', dot: '#EF4444' },
  };

  return colors[status || ''] || { bg: 'rgba(107, 114, 128, 0.1)', text: '#6B7280', dot: '#6B7280' };
};

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const branchFilter = ref(props.filters?.branch_id ? String(props.filters.branch_id) : 'all');

function doSearch() {
  if (!subdomain.value) return;

  router.get(
    index(subdomain.value).url,
    {
      search: search.value,
      status: statusFilter.value === 'all' ? null : statusFilter.value,
      branch_id: branchFilter.value === 'all' ? null : Number(branchFilter.value),
    },
    {
      preserveState: true,
      replace: true,
    },
  );
}

watch(search, (v, ov) => {
  if (v === '' && ov !== '') doSearch();
});

const navigateToClient = (id: number) => {
  if (!subdomain.value) return;
  router.visit(show([subdomain.value, id]).url);
};
</script>

<template>
  <Head :title="t('dashboard.admin.clients.index.head_title')" />
  <AdminLayout>
    <main class="flex-1 p-8 space-y-6">
      <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ t('dashboard.admin.clients.index.title') }}</h1>
        <Link v-if="subdomain" :href="create(subdomain).url">
          <Button>Create Client</Button>
        </Link>
      </div>

      <div class="flex flex-col gap-4">
        <div class="flex items-center gap-2">
          <Input
            v-model="search"
            :placeholder="t('dashboard.admin.clients.index.search_placeholder')"
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
            <option value="all">All branches</option>
            <option v-for="branch in props.branches" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option>
          </select>
        </div>

        <!-- Status Filter -->
        <div class="flex flex-wrap items-center gap-2">
          <label class="inline-flex items-center">
            <input type="radio" class="hidden" v-model="statusFilter" value="all" @change="doSearch" />
            <span
              class="px-3 py-1.5 text-sm rounded-full cursor-pointer transition-colors"
              :class="{
                'bg-primary text-primary-foreground': statusFilter === 'all',
                'bg-muted text-muted-foreground hover:bg-muted/80': statusFilter !== 'all',
              }"
            >
              {{ t('dashboard.common.all') }} ({{ Object.values(statuses).reduce((acc: number, curr: any) => acc + (curr as any).count, 0) }})
            </span>
          </label>

          <template v-for="(status, key) in statuses" :key="key">
            <label class="inline-flex items-center">
              <input type="radio" class="hidden" v-model="statusFilter" :value="key" @change="doSearch" />
              <span
                class="px-3 py-1.5 text-sm rounded-full cursor-pointer transition-colors flex items-center gap-1.5"
                :class="{
                  'bg-primary text-primary-foreground': statusFilter === key,
                  'bg-muted text-muted-foreground hover:bg-muted/80': statusFilter !== key,
                }"
              >
                <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: (status as any).color }"></span>
                {{ (status as any).label }} ({{ (status as any).count }})
              </span>
            </label>
          </template>
        </div>
      </div>

      <div class="overflow-x-auto rounded-md border">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.client') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.reservations') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.payments') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.admin.employees.table.branch') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.status') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ localize('Client status', 'حالة العميل') }}</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr
              v-for="c in props.clients.data"
              :key="c.id"
              class="cursor-pointer hover:bg-gray-50 transition-colors"
              @click="navigateToClient(c.id)"
            >
              <td class="px-4 py-3">
                <div class="font-medium">{{ c.name }}</div>
                <div class="text-xs text-muted-foreground">{{ c.email }}</div>
              </td>
              <td class="px-4 py-3">{{ c.reservations_count }}</td>
              <td class="px-4 py-3">{{ c.payments_count }}</td>
              <td class="px-4 py-3">{{ c.branch?.name || t('dashboard.admin.employees.table.no_branch') }}</td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-medium"
                  :style="{
                    backgroundColor: getStatusColor(c.is_active ? 'active' : 'suspended').bg,
                    color: getStatusColor(c.is_active ? 'active' : 'suspended').text,
                  }"
                >
                  <span class="size-2 rounded-full" :style="{ backgroundColor: getStatusColor(c.is_active ? 'active' : 'suspended').dot }" />
                  {{ c.is_active ? t('dashboard.common.active') : t('dashboard.common.suspended') }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-medium"
                  :style="{
                    backgroundColor: clientStatusColor(c.client_status?.status).bg,
                    color: clientStatusColor(c.client_status?.status).text,
                  }"
                >
                  <span class="size-2 rounded-full" :style="{ backgroundColor: clientStatusColor(c.client_status?.status).dot }" />
                  {{ c.client_status?.label || localize('Good', 'جيد') }}
                </span>
                <div v-if="c.client_status?.flags_count" class="mt-1 text-xs text-muted-foreground">
                  {{ c.client_status.flags_count }} {{ localize('note(s)', 'ملاحظة') }}
                </div>
              </td>
              <td class="px-4 py-3 text-right">
                <Link v-if="subdomain" :href="show([subdomain, c.id]).url">
                                    <Button variant="outline" size="sm">{{ t('dashboard.common.view') }}</Button>
                </Link>
              </td>
            </tr>
            <tr v-if="props.clients.data.length === 0">
              <td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ t('dashboard.admin.clients.index.empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <nav v-if="props.clients.links?.length" class="flex gap-2">
        <Link
          v-for="(link, i) in props.clients.links"
          :key="i"
          :href="link.url || ''"
          :class="[
            'px-3 py-1 rounded text-sm',
            link.active ? 'bg-primary text-primary-foreground' : 'bg-gray-100 text-gray-700',
            !link.url && 'pointer-events-none opacity-50',
          ]"
        >
          <span v-html="link.label" />
        </Link>
      </nav>
    </main>
  </AdminLayout>
</template>
