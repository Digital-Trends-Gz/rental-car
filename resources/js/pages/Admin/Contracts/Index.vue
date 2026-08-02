<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ref } from 'vue';

type StatusPayload = {
    value: string;
    label: string;
    color: string;
    total_due?: number;
    paid_amount?: number;
    balance_due?: number;
};

const props = defineProps<{
    contracts: {
        data: Array<{
            id: number;
            contract_number: string;
            status: string;
            contract_status?: StatusPayload | null;
            reservation_status?: StatusPayload | null;
            finance_status?: StatusPayload | null;
            car_status?: StatusPayload | null;
            reservation_number?: string | null;
            renter_name?: string | null;
            branch_name?: string | null;
            start_date?: string | null;
            end_date?: string | null;
            total_amount?: string | number | null;
            currency?: string | null;
            has_start_contract: boolean;
            has_end_contract: boolean;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search?: string;
        status?: string;
        branch_id?: number | null;
    };
    statuses: string[];
    branches: Array<{ id: number; name: string }>;
    canAccessAllBranches: boolean;
    actions: {
        index: string;
        create: string;
    };
}>();

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? 'all');
const branchId = ref<string>(
    props.filters?.branch_id ? String(props.filters.branch_id) : 'all',
);
const { t } = useTrans();

const submitFilters = () => {
    router.get(
        props.actions.index,
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            branch_id: branchId.value === 'all' ? undefined : branchId.value,
        },
        { preserveState: true, replace: true },
    );
};

const badgeStyle = (status?: StatusPayload | null) => {
    const color = status?.color || '#6B7280';

    return {
        color,
        backgroundColor: `${color}1f`,
        borderColor: `${color}4d`,
    };
};

const normalizeTranslationKey = (value?: string | null): string =>
    String(value ?? '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');

const translateWithFallback = (key: string, fallback: string): string => {
    const translated = t(key);

    return translated === key ? fallback : translated;
};

const translatedStatus = (
    group:
        | 'contract_statuses'
        | 'reservation_statuses'
        | 'finance_statuses'
        | 'car_statuses',
    status?: StatusPayload | null,
    fallback?: string | null,
): string => {
    const rawValue = status?.value || fallback || status?.label;
    const key = normalizeTranslationKey(rawValue);

    if (!key) {
        return fallback || status?.label || '-';
    }

    return translateWithFallback(
        `dashboard.admin.${group}.${key}`,
        status?.label || fallback || key,
    );
};

const translatedContractStatusFilter = (value: string): string =>
    translatedStatus('contract_statuses', {
        value,
        label: value,
        color: '',
    });
</script>

<template>
    <Head :title="t('dashboard.admin.contracts.index.head_title')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{ t('dashboard.admin.contracts.index.title') }}
                </h1>
                <Link :href="actions.create">
                    <Button>{{
                        t('dashboard.admin.contracts.index.create_contract')
                    }}</Button>
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <Input
                    v-model="search"
                    :placeholder="
                        t('dashboard.admin.contracts.index.search_placeholder')
                    "
                    @keyup.enter="submitFilters"
                />
                <select
                    v-model="status"
                    class="rounded-md border border-input bg-transparent px-3 py-2"
                    @change="submitFilters"
                >
                    <option value="all">
                        {{ t('dashboard.admin.contracts.index.all_statuses') }}
                    </option>
                    <option v-for="item in statuses" :key="item" :value="item">
                        {{ translatedContractStatusFilter(item) }}
                    </option>
                </select>

                <select
                    v-if="canAccessAllBranches"
                    v-model="branchId"
                    class="rounded-md border border-input bg-transparent px-3 py-2"
                    @change="submitFilters"
                >
                    <option value="all">
                        {{ t('dashboard.admin.contracts.index.all_branches') }}
                    </option>
                    <option
                        v-for="branch in branches"
                        :key="branch.id"
                        :value="String(branch.id)"
                    >
                        {{ branch.name }}
                    </option>
                </select>

                <Button variant="outline" @click="submitFilters">{{
                    t('dashboard.admin.contracts.index.filter')
                }}</Button>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{
                                    t(
                                        'dashboard.admin.contracts.index.table.contract_number',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{
                                    t(
                                        'dashboard.admin.contracts.index.table.reservation',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{
                                    t(
                                        'dashboard.admin.contracts.index.table.renter',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{
                                    t(
                                        'dashboard.admin.contracts.index.table.branch',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{
                                    translateWithFallback(
                                        'dashboard.admin.contracts.index.table.contract_status',
                                        'Contract Status',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{
                                    translateWithFallback(
                                        'dashboard.admin.contracts.index.table.reservation_status',
                                        'Reservation Status',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{
                                    translateWithFallback(
                                        'dashboard.admin.contracts.index.table.finance_status',
                                        'Finance Status',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{
                                    translateWithFallback(
                                        'dashboard.admin.contracts.index.table.car_status',
                                        'Car Status',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{
                                    t(
                                        'dashboard.admin.contracts.index.table.files',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{
                                    t(
                                        'dashboard.admin.contracts.index.table.actions',
                                    )
                                }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="item in contracts.data" :key="item.id">
                            <td class="px-4 py-3 font-medium">
                                {{ item.contract_number }}
                            </td>
                            <td class="px-4 py-3">
                                {{ item.reservation_number || '-' }}
                            </td>
                            <td class="px-4 py-3">
                                {{ item.renter_name || '-' }}
                            </td>
                            <td class="px-4 py-3">
                                {{ item.branch_name || '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold"
                                    :style="badgeStyle(item.contract_status)"
                                >
                                    {{
                                        translatedStatus(
                                            'contract_statuses',
                                            item.contract_status,
                                            item.status,
                                        )
                                    }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="item.reservation_status"
                                    class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold"
                                    :style="badgeStyle(item.reservation_status)"
                                >
                                    {{
                                        translatedStatus(
                                            'reservation_statuses',
                                            item.reservation_status,
                                        )
                                    }}
                                </span>
                                <span v-else>-</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="space-y-1">
                                    <span
                                        class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold"
                                        :style="badgeStyle(item.finance_status)"
                                    >
                                        {{
                                            translatedStatus(
                                                'finance_statuses',
                                                item.finance_status,
                                            )
                                        }}
                                    </span>
                                    <div
                                        v-if="
                                            Number(
                                                item.finance_status
                                                    ?.balance_due ?? 0,
                                            ) > 0
                                        "
                                        class="text-xs text-gray-500"
                                    >
                                        {{
                                            translateWithFallback(
                                                'dashboard.admin.contracts.index.balance',
                                                'Balance',
                                            )
                                        }}:
                                        {{
                                            item.finance_status?.balance_due
                                        }}
                                        {{ item.currency || '' }}
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="item.car_status"
                                    class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold"
                                    :style="badgeStyle(item.car_status)"
                                >
                                    {{
                                        translatedStatus(
                                            'car_statuses',
                                            item.car_status,
                                        )
                                    }}
                                </span>
                                <span v-else>-</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{
                                    t(
                                        'dashboard.admin.contracts.index.start_file',
                                    )
                                }}:
                                {{
                                    item.has_start_contract
                                        ? t(
                                              'dashboard.admin.contracts.index.yes',
                                          )
                                        : t(
                                              'dashboard.admin.contracts.index.no',
                                          )
                                }}
                                /
                                {{
                                    t(
                                        'dashboard.admin.contracts.index.end_file',
                                    )
                                }}:
                                {{
                                    item.has_end_contract
                                        ? t(
                                              'dashboard.admin.contracts.index.yes',
                                          )
                                        : t(
                                              'dashboard.admin.contracts.index.no',
                                          )
                                }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <Link :href="`${actions.index}/${item.id}`">
                                        <Button size="sm" variant="outline">{{
                                            t(
                                                'dashboard.admin.contracts.index.view',
                                            )
                                        }}</Button>
                                    </Link>
                                    <Link
                                        :href="`${actions.index}/${item.id}/edit`"
                                    >
                                        <Button size="sm" variant="outline">{{
                                            t(
                                                'dashboard.admin.contracts.index.edit',
                                            )
                                        }}</Button>
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="contracts.data.length === 0">
                            <td
                                colspan="10"
                                class="px-4 py-8 text-center text-gray-500"
                            >
                                {{ t('dashboard.admin.contracts.index.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap gap-2">
                <template v-for="link in contracts.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded border px-3 py-1 text-sm"
                        :class="
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-white'
                        "
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="rounded border px-3 py-1 text-sm text-gray-400"
                        v-html="link.label"
                    />
                </template>
            </div>
        </main>
    </AdminLayout>
</template>
