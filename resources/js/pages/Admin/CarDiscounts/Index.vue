<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    discounts: {
        data: Array<{
            id: number;
            name: string;
            car: string;
            type: string;
            value: number;
            priority: number;
            is_active: boolean;
            starts_at: string | null;
            ends_at: string | null;
            edit_url: string;
            delete_url: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    cars: Array<{ id: number; label: string }>;
    filters: {
        search?: string;
        status?: string;
        car_id?: number | null;
    };
    indexUrl: string;
    createUrl: string;
}>();

const { locale, t } = useTrans();
const translationRoot = 'dashboard.admin.car_discounts';
const translationKeyFor = (value: string) =>
    value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 90);
const localize = (en: string, ar: string, ur: string = en) => {
    const key = `${translationRoot}.${translationKeyFor(en)}`;
    const translated = t(key);

    if (translated !== key) {
        return translated;
    }

    if (locale.value === 'ar') return ar;
    if (locale.value === 'ur') return ur;
    return en;
};

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');
const carId = ref(props.filters.car_id ? String(props.filters.car_id) : 'all');
const hasFilters = computed(() => search.value !== '' || status.value !== 'all' || carId.value !== 'all');

function applyFilters() {
    router.get(
        props.indexUrl,
        {
            search: search.value || null,
            status: status.value,
            car_id: carId.value === 'all' ? null : Number(carId.value),
        },
        { preserveState: true, replace: true },
    );
}

function clearFilters() {
    search.value = '';
    status.value = 'all';
    carId.value = 'all';
    applyFilters();
}

function deleteDiscount(url: string) {
    if (!confirm(localize('Delete this automatic discount?', 'حذف هذا الخصم التلقائي؟', 'یہ خودکار ڈسکاؤنٹ حذف کریں؟'))) {
        return;
    }

    router.delete(url);
}

function formatValue(type: string, value: number): string {
    return type === 'percentage' ? `${value}%` : `$${value.toFixed(2)}`;
}
</script>

<template>
    <Head :title="localize('Automatic Discounts', 'الخصومات التلقائية', 'خودکار رعایتیں')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Automatic Discounts', 'الخصومات التلقائية', 'خودکار رعایتیں') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Auto-apply discounts for booking based on car and conditions.', 'تُطبَّق الخصومات تلقائيًا على الحجز حسب السيارة والشروط.', 'کار اور شرائط کے مطابق بکنگ پر خودکار رعایتیں لاگو ہوں گی.') }}
                    </p>
                </div>
                <Link :href="createUrl">
                    <Button>{{ localize('+ New Auto Discount', '+ خصم تلقائي جديد', '+ نیا خودکار ڈسکاؤنٹ') }}</Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Input
                    v-model="search"
                    class="max-w-md"
                    :placeholder="localize('Search by name...', 'ابحث بالاسم...', 'نام سے تلاش کریں...')"
                    @keyup.enter="applyFilters"
                />
                <select v-model="status" class="h-10 rounded-md border border-input bg-background px-3 text-sm" @change="applyFilters">
                    <option value="all">{{ localize('All statuses', 'كل الحالات', 'تمام حالتیں') }}</option>
                    <option value="active">{{ localize('Active', 'نشط', 'فعال') }}</option>
                    <option value="inactive">{{ localize('Inactive', 'غير نشط', 'غیر فعال') }}</option>
                </select>
                <select v-model="carId" class="h-10 rounded-md border border-input bg-background px-3 text-sm" @change="applyFilters">
                    <option value="all">{{ localize('All cars', 'كل السيارات', 'تمام گاڑیاں') }}</option>
                    <option v-for="car in cars" :key="car.id" :value="String(car.id)">{{ car.label }}</option>
                </select>
                <Button @click="applyFilters">{{ localize('Search', 'بحث', 'تلاش') }}</Button>
                <Button v-if="hasFilters" variant="outline" @click="clearFilters">{{ localize('Clear', 'مسح', 'صاف کریں') }}</Button>
            </div>

            <div class="overflow-x-auto rounded-lg border bg-card">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b bg-muted/30 text-left text-xs uppercase text-muted-foreground">
                            <th class="px-4 py-3">{{ localize('Name', 'الاسم', 'نام') }}</th>
                            <th class="px-4 py-3">{{ localize('Scope', 'النطاق', 'دائرہ') }}</th>
                            <th class="px-4 py-3">{{ localize('Value', 'القيمة', 'قیمت') }}</th>
                            <th class="px-4 py-3">{{ localize('Priority', 'الأولوية', 'ترجیح') }}</th>
                            <th class="px-4 py-3">{{ localize('Status', 'الحالة', 'حالت') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="discount in discounts.data" :key="discount.id" class="border-b last:border-b-0">
                            <td class="px-4 py-3 text-sm font-medium">{{ discount.name }}</td>
                            <td class="px-4 py-3 text-sm">{{ discount.car }}</td>
                            <td class="px-4 py-3 text-sm">{{ formatValue(discount.type, discount.value) }}</td>
                            <td class="px-4 py-3 text-sm">{{ discount.priority }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                    :class="discount.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'"
                                >
                                    {{ discount.is_active ? localize('Active', 'نشط', 'فعال') : localize('Inactive', 'غير نشط', 'غیر فعال') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm">
                                <Link :href="discount.edit_url" class="mr-3 text-primary hover:underline">
                                    {{ localize('Edit', 'تعديل', 'ترمیم') }}
                                </Link>
                                <button type="button" class="text-red-600 hover:underline" @click="deleteDiscount(discount.delete_url)">
                                    {{ localize('Delete', 'حذف', 'حذف کریں') }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="discounts.data.length === 0">
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-muted-foreground">
                                {{ localize('No automatic discounts found.', 'لا توجد خصومات تلقائية.', 'کوئی خودکار رعایتیں نہیں ملیں۔') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </AdminLayout>
</template>
