<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { useTrans } from '@/composables/useTrans';
import { computed } from 'vue';

const props = defineProps<{
    car: {
        id: number;
        make: string;
        model: string;
        year: number;
        license_plate: string;
        status: string;
        status_label: string;
        status_color: string;
        branch_name: string | null;
        price_per_day: number | null;
        mileage: number | null;
        fuel_type: string | null;
        transmission: string | null;
        seats: number | null;
        color: string | null;
        description: string | null;
        image_url: string;
        additional_photos: Record<string, { id: number | null; url: string | null; alt: string }>;
    };
    summary: {
        reservations_count: number;
        contracts_count: number;
        maintenances_count: number;
        damage_repairs_count: number;
        documents_count: number;
        damage_reports_count: number;
        violations_count: number;
        discounts_count: number;
    };
    reservations: Array<{
        id: number;
        number: string;
        client_name: string | null;
        client_email: string | null;
        status: string;
        status_label: string;
        start_date: string | null;
        end_date: string | null;
        total_amount: number | null;
        show_url: string;
        contract: null | {
            id: number;
            number: string;
            status: string | null;
            show_url: string;
        };
    }>;
    contracts: Array<{
        id: number;
        number: string;
        status: string | null;
        contract_date: string | null;
        renter_name: string | null;
        reservation_number: string | null;
        show_url: string;
    }>;
    maintenances: Array<{
        id: number;
        type: string;
        status: string;
        status_label: string;
        scheduled_date: string | null;
        started_at: string | null;
        completed_at: string | null;
        cost: number | null;
        workshop_name: string | null;
        edit_url: string;
    }>;
    damageRepairs: Array<{
        id: number;
        repair_number: string;
        damage_zone: string;
        damage_type: string;
        workshop_name: string | null;
        status: string;
        status_label: string;
        opened_at: string | null;
        completed_at: string | null;
        estimated_cost: number | null;
        actual_cost: number | null;
        edit_url: string;
    }>;
    documents: Array<{
        id: number;
        type: string;
        type_label: string;
        number: string | null;
        issuer: string | null;
        issue_date: string | null;
        expiry_date: string | null;
        days_remaining: number | null;
        status_key: string;
        front_image_url: string | null;
        back_image_url: string | null;
        edit_url: string;
    }>;
    damageReports: Array<{
        id: number;
        number: string;
        report_type: string;
        report_type_label: string;
        status: string;
        status_label: string;
        inspected_at: string | null;
        items_count: number;
        reservation_id: number | null;
        contract_id: number | null;
        edit_url: string;
    }>;
    violations: Array<{
        id: number;
        number: string | null;
        type: string | null;
        amount: number | null;
        status: string;
        status_label: string;
        violation_date: string | null;
        due_date: string | null;
        authority: string | null;
        location: string | null;
        edit_url: string;
    }>;
    discounts: Array<{
        id: number;
        name: string;
        type: string;
        value: number | null;
        starts_at: string | null;
        ends_at: string | null;
        is_active: boolean;
        edit_url: string;
    }>;
    actions: {
        create_maintenance_url: string;
        maintenance_index_url: string;
        create_damage_repair_url: string;
        damage_repairs_index_url: string;
        photo_histories_index_url: string;
        create_photo_history_url: string;
    };
}>();

const { locale, t } = useTrans();
const page = usePage<any>();
const showTranslationRoot = 'dashboard.admin.cars.show';

const translationKeyFor = (value: string) =>
    `${showTranslationRoot}.${value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 90)}`;

const localize = (en: string, ar: string) => {
    const key = translationKeyFor(en);
    const translated = t(key);

    if (translated !== key) {
        return translated;
    }

    return locale.value === 'ar' ? ar : en;
};
const tenantFeatureFlags = computed<Record<string, boolean>>(
    () => page.props.current_tenant?.subscription_plan?.feature_flags || {},
);
const hasFeature = (feature: string) => {
    const flags = tenantFeatureFlags.value || {};

    if (Object.keys(flags).length === 0) {
        return true;
    }

    return Boolean(flags[feature]);
};

function money(value: number | null) {
    if (value === null) return '-';
    return value.toLocaleString(locale.value === 'ar' ? 'ar' : 'en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function statusVariant(key: string) {
    return {
        expired: 'destructive',
        expiring_soon: 'secondary',
        active: 'default',
        new: 'outline',
        inactive: 'secondary',
    }[key] ?? 'outline';
}
</script>

<template>
    <Head :title="`${car.year} ${car.make} ${car.model}`" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-semibold">{{ car.year }} {{ car.make }} {{ car.model }}</h1>
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium text-white"
                            :style="{ backgroundColor: car.status_color }"
                        >
                            {{ car.status_label }}
                        </span>
                    </div>
                    <div class="text-sm text-muted-foreground">
                        {{ car.license_plate }}<span v-if="car.branch_name"> • {{ car.branch_name }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Link v-if="hasFeature('car_documents')" :href="`/admin/cars/${car.id}/documents`">
                        <Button variant="outline">{{ localize('Documents', 'الوثائق') }}</Button>
                    </Link>
                    <Link :href="`/admin/cars/${car.id}/calendar`">
                        <Button variant="outline">{{ localize('Calendar', 'التقويم') }}</Button>
                    </Link>
                    <Link :href="`/admin/cars/${car.id}/edit`">
                        <Button>{{ localize('Edit Car', 'تعديل السيارة') }}</Button>
                    </Link>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-4 xl:grid-cols-8">
                <Card><CardHeader class="pb-2"><CardTitle class="text-sm">{{ localize('Reservations', 'الحجوزات') }}</CardTitle></CardHeader><CardContent><div class="text-2xl font-semibold">{{ summary.reservations_count }}</div></CardContent></Card>
                <Card><CardHeader class="pb-2"><CardTitle class="text-sm">{{ localize('Contracts', 'العقود') }}</CardTitle></CardHeader><CardContent><div class="text-2xl font-semibold">{{ summary.contracts_count }}</div></CardContent></Card>
                <Card><CardHeader class="pb-2"><CardTitle class="text-sm">{{ localize('Maintenances', 'الصيانة') }}</CardTitle></CardHeader><CardContent><div class="text-2xl font-semibold">{{ summary.maintenances_count }}</div></CardContent></Card>
                <Card><CardHeader class="pb-2"><CardTitle class="text-sm">{{ localize('Damage Repairs', 'إصلاحات الأضرار') }}</CardTitle></CardHeader><CardContent><div class="text-2xl font-semibold">{{ summary.damage_repairs_count }}</div></CardContent></Card>
                <Card><CardHeader class="pb-2"><CardTitle class="text-sm">{{ localize('Documents', 'الوثائق') }}</CardTitle></CardHeader><CardContent><div class="text-2xl font-semibold">{{ summary.documents_count }}</div></CardContent></Card>
                <Card><CardHeader class="pb-2"><CardTitle class="text-sm">{{ localize('Damage Reports', 'تقارير الأضرار') }}</CardTitle></CardHeader><CardContent><div class="text-2xl font-semibold">{{ summary.damage_reports_count }}</div></CardContent></Card>
                <Card><CardHeader class="pb-2"><CardTitle class="text-sm">{{ localize('Violations', 'المخالفات') }}</CardTitle></CardHeader><CardContent><div class="text-2xl font-semibold">{{ summary.violations_count }}</div></CardContent></Card>
                <Card><CardHeader class="pb-2"><CardTitle class="text-sm">{{ localize('Discounts', 'الخصومات') }}</CardTitle></CardHeader><CardContent><div class="text-2xl font-semibold">{{ summary.discounts_count }}</div></CardContent></Card>
            </div>

            <div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Car Details', 'تفاصيل السيارة') }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <img :src="car.image_url" :alt="`${car.year} ${car.make} ${car.model}`" class="h-56 w-full rounded-lg object-cover" />

                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div><div class="text-muted-foreground">{{ localize('Price / Day', 'السعر / يوم') }}</div><div class="font-medium">{{ money(car.price_per_day) }}</div></div>
                                <div><div class="text-muted-foreground">{{ localize('Mileage', 'الممشى') }}</div><div class="font-medium">{{ car.mileage ?? '-' }}</div></div>
                                <div><div class="text-muted-foreground">{{ localize('Fuel', 'الوقود') }}</div><div class="font-medium">{{ car.fuel_type ?? '-' }}</div></div>
                                <div><div class="text-muted-foreground">{{ localize('Transmission', 'ناقل الحركة') }}</div><div class="font-medium">{{ car.transmission ?? '-' }}</div></div>
                                <div><div class="text-muted-foreground">{{ localize('Seats', 'المقاعد') }}</div><div class="font-medium">{{ car.seats ?? '-' }}</div></div>
                                <div><div class="text-muted-foreground">{{ localize('Color', 'اللون') }}</div><div class="font-medium">{{ car.color ?? '-' }}</div></div>
                            </div>

                            <div v-if="car.description">
                                <div class="mb-1 text-sm text-muted-foreground">{{ localize('Description', 'الوصف') }}</div>
                                <p class="text-sm">{{ car.description }}</p>
                            </div>

                            <div class="space-y-2">
                                <div class="text-sm font-medium">{{ localize('Additional Photos', 'صور إضافية') }}</div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div v-for="(photo, type) in car.additional_photos" :key="type" class="space-y-1">
                                        <div class="text-xs capitalize text-muted-foreground">{{ type }}</div>
                                        <img v-if="photo.url" :src="photo.url" :alt="photo.alt" class="h-24 w-full rounded-md object-cover" />
                                        <div v-else class="flex h-24 items-center justify-center rounded-md border border-dashed text-xs text-muted-foreground">
                                            {{ localize('No photo', 'لا توجد صورة') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-6">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between gap-3">
                            <CardTitle>{{ localize('Photo History', 'سجل الصور') }}</CardTitle>
                            <div class="flex items-center gap-2">
                                <Link :href="actions.photo_histories_index_url">
                                    <Button size="sm" variant="outline">{{ localize('View All', 'عرض الكل') }}</Button>
                                </Link>
                                <Link :href="actions.create_photo_history_url">
                                    <Button size="sm">{{ localize('New Record', 'سجل جديد') }}</Button>
                                </Link>
                            </div>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>{{ localize('Reservations', 'الحجوزات') }}</CardTitle></CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="reservations.length === 0" class="text-sm text-muted-foreground">{{ localize('No reservations for this car.', 'لا توجد حجوزات لهذه السيارة.') }}</div>
                            <div v-for="item in reservations" :key="item.id" class="rounded-lg border p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium">{{ item.number }}</div>
                                        <div class="text-sm text-muted-foreground">{{ item.client_name || '-' }}<span v-if="item.client_email"> • {{ item.client_email }}</span></div>
                                        <div class="text-sm text-muted-foreground">{{ item.start_date || '-' }} → {{ item.end_date || '-' }}</div>
                                        <div v-if="item.contract" class="mt-1 text-xs text-muted-foreground">{{ localize('Contract', 'العقد') }}: {{ item.contract.number }}</div>
                                    </div>
                                    <div class="flex flex-col items-end gap-2">
                                        <Badge variant="outline">{{ item.status_label }}</Badge>
                                        <Link :href="item.show_url"><Button size="sm" variant="outline">{{ localize('Open', 'فتح') }}</Button></Link>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between gap-3">
                            <CardTitle>{{ localize('Damage Repairs', 'إصلاحات الأضرار') }}</CardTitle>
                            <div class="flex items-center gap-2">
                                <Link :href="actions.damage_repairs_index_url">
                                    <Button size="sm" variant="outline">{{ localize('View All', 'عرض الكل') }}</Button>
                                </Link>
                                <Link :href="actions.create_damage_repair_url">
                                    <Button size="sm">{{ localize('New Repair', 'إصلاح جديد') }}</Button>
                                </Link>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="damageRepairs.length === 0" class="text-sm text-muted-foreground">
                                {{ localize('No damage repairs for this car.', 'لا توجد إصلاحات أضرار لهذه السيارة.') }}
                            </div>
                            <div v-for="item in damageRepairs" :key="item.id" class="rounded-lg border p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium">{{ item.repair_number }}</div>
                                        <div class="text-sm text-muted-foreground">{{ item.damage_zone }}<span v-if="item.damage_type"> • {{ item.damage_type }}</span></div>
                                        <div class="text-sm text-muted-foreground">{{ item.workshop_name || localize('No workshop', 'لا توجد ورشة') }}</div>
                                        <div class="text-sm text-muted-foreground">{{ item.opened_at || '-' }}<span v-if="item.completed_at"> • {{ localize('Completed', 'مكتمل') }}: {{ item.completed_at }}</span></div>
                                        <div v-if="item.actual_cost !== null || item.estimated_cost !== null" class="text-sm text-muted-foreground">
                                            {{ localize('Cost', 'التكلفة') }}:
                                            {{ money(item.actual_cost ?? item.estimated_cost) }}
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-end gap-2">
                                        <Badge variant="outline">{{ item.status_label }}</Badge>
                                        <Link :href="item.edit_url"><Button size="sm" variant="outline">{{ localize('Open', 'فتح') }}</Button></Link>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between gap-3">
                            <CardTitle>{{ localize('Maintenance Records', 'سجلات الصيانة') }}</CardTitle>
                            <div class="flex items-center gap-2">
                                <Link :href="actions.maintenance_index_url">
                                    <Button size="sm" variant="outline">{{ localize('View All', 'عرض الكل') }}</Button>
                                </Link>
                                <Link :href="actions.create_maintenance_url">
                                    <Button size="sm">{{ localize('New Record', 'سجل جديد') }}</Button>
                                </Link>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="maintenances.length === 0" class="text-sm text-muted-foreground">{{ localize('No maintenance records for this car.', 'لا توجد سجلات صيانة لهذه السيارة.') }}</div>
                            <div v-for="item in maintenances" :key="item.id" class="rounded-lg border p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium">{{ item.type }}</div>
                                        <div class="text-sm text-muted-foreground">{{ item.workshop_name || localize('No workshop', 'لا توجد ورشة') }}</div>
                                        <div class="text-sm text-muted-foreground">{{ item.scheduled_date || '-' }}</div>
                                        <div v-if="item.cost !== null" class="text-sm text-muted-foreground">{{ localize('Cost', 'التكلفة') }}: {{ money(item.cost) }}</div>
                                    </div>
                                    <div class="flex flex-col items-end gap-2">
                                        <Badge variant="outline">{{ item.status_label }}</Badge>
                                        <Link :href="item.edit_url"><Button size="sm" variant="outline">{{ localize('Open', 'فتح') }}</Button></Link>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>{{ localize('Car Documents', 'وثائق السيارة') }}</CardTitle></CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="documents.length === 0" class="text-sm text-muted-foreground">{{ localize('No car documents for this car.', 'لا توجد وثائق لهذه السيارة.') }}</div>
                            <div v-for="item in documents" :key="item.id" class="rounded-lg border p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium">{{ item.type_label }}</div>
                                        <div class="text-sm text-muted-foreground">{{ item.number || '-' }}<span v-if="item.issuer"> • {{ item.issuer }}</span></div>
                                        <div class="text-sm text-muted-foreground">{{ localize('Expiry', 'الانتهاء') }}: {{ item.expiry_date || '-' }}</div>
                                        <div v-if="item.days_remaining !== null" class="text-sm text-muted-foreground">{{ localize('Days remaining', 'الأيام المتبقية') }}: {{ item.days_remaining }}</div>
                                    </div>
                                    <div class="flex flex-col items-end gap-2">
                                        <Badge :variant="statusVariant(item.status_key) as any">{{ item.status_key }}</Badge>
                                        <Link :href="item.edit_url"><Button size="sm" variant="outline">{{ localize('Open', 'فتح') }}</Button></Link>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>{{ localize('Damage Reports', 'تقارير الأضرار') }}</CardTitle></CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="damageReports.length === 0" class="text-sm text-muted-foreground">{{ localize('No damage reports for this car.', 'لا توجد تقارير أضرار لهذه السيارة.') }}</div>
                            <div v-for="item in damageReports" :key="item.id" class="rounded-lg border p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium">{{ item.number }}</div>
                                        <div class="text-sm text-muted-foreground">{{ item.report_type_label }} • {{ item.status_label }}</div>
                                        <div class="text-sm text-muted-foreground">{{ item.inspected_at || '-' }}</div>
                                        <div class="text-sm text-muted-foreground">{{ localize('Items', 'العناصر') }}: {{ item.items_count }}</div>
                                    </div>
                                    <Link :href="item.edit_url"><Button size="sm" variant="outline">{{ localize('Open', 'فتح') }}</Button></Link>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>{{ localize('Violations', 'المخالفات') }}</CardTitle></CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="violations.length === 0" class="text-sm text-muted-foreground">{{ localize('No violations for this car.', 'لا توجد مخالفات لهذه السيارة.') }}</div>
                            <div v-for="item in violations" :key="item.id" class="rounded-lg border p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium">{{ item.number || '-' }}</div>
                                        <div class="text-sm text-muted-foreground">{{ item.type || '-' }}<span v-if="item.authority"> • {{ item.authority }}</span></div>
                                        <div class="text-sm text-muted-foreground">{{ item.violation_date || '-' }}<span v-if="item.due_date"> • {{ localize('Due', 'الاستحقاق') }}: {{ item.due_date }}</span></div>
                                        <div v-if="item.amount !== null" class="text-sm text-muted-foreground">{{ localize('Amount', 'القيمة') }}: {{ money(item.amount) }}</div>
                                    </div>
                                    <div class="flex flex-col items-end gap-2">
                                        <Badge variant="outline">{{ item.status_label }}</Badge>
                                        <Link :href="item.edit_url"><Button size="sm" variant="outline">{{ localize('Open', 'فتح') }}</Button></Link>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>{{ localize('Contracts', 'العقود') }}</CardTitle></CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="contracts.length === 0" class="text-sm text-muted-foreground">{{ localize('No contracts linked to this car.', 'لا توجد عقود مرتبطة بهذه السيارة.') }}</div>
                            <div v-for="item in contracts" :key="item.id" class="rounded-lg border p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium">{{ item.number }}</div>
                                        <div class="text-sm text-muted-foreground">{{ item.renter_name || '-' }}</div>
                                        <div class="text-sm text-muted-foreground">{{ item.contract_date || '-' }}<span v-if="item.reservation_number"> • {{ item.reservation_number }}</span></div>
                                    </div>
                                    <Link :href="item.show_url"><Button size="sm" variant="outline">{{ localize('Open', 'فتح') }}</Button></Link>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>{{ localize('Discounts', 'الخصومات') }}</CardTitle></CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="discounts.length === 0" class="text-sm text-muted-foreground">{{ localize('No discounts linked to this car.', 'لا توجد خصومات مرتبطة بهذه السيارة.') }}</div>
                            <div v-for="item in discounts" :key="item.id" class="rounded-lg border p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium">{{ item.name }}</div>
                                        <div class="text-sm text-muted-foreground">{{ item.type }}<span v-if="item.value !== null"> • {{ item.value }}</span></div>
                                        <div class="text-sm text-muted-foreground">{{ item.starts_at || '-' }} → {{ item.ends_at || '-' }}</div>
                                    </div>
                                    <div class="flex flex-col items-end gap-2">
                                        <Badge :variant="item.is_active ? 'default' : 'secondary'">{{ item.is_active ? localize('Active', 'نشط') : localize('Inactive', 'غير نشط') }}</Badge>
                                        <Link :href="item.edit_url"><Button size="sm" variant="outline">{{ localize('Open', 'فتح') }}</Button></Link>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </main>
    </AdminLayout>
</template>
