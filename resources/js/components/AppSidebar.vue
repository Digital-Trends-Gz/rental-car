<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import SuperAdminNav from '@/components/SuperAdminNav.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTrans } from '@/composables/useTrans';
import { home } from '@/routes';
import { index as branchesIndex } from '@/routes/admin/branches/index';
import { index as carsIndex } from '@/routes/admin/cars/index';
import { index as clientsIndex } from '@/routes/admin/clients/index';
import { index as contractsIndex } from '@/routes/admin/contracts/index';
import { index as employeesIndex } from '@/routes/admin/employees/index';
import { index as paymentsIndex } from '@/routes/admin/payments/index';
import { index as reportsIndex } from '@/routes/admin/reports/index';
import { index as reservationsIndex } from '@/routes/admin/reservations/index';
import { index as rolesIndex } from '@/routes/admin/roles/index';
import { index as supportIndex } from '@/routes/admin/support/index';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart,
    Calendar,
    CalendarDays,
    Car,
    CreditCard,
    FileText,
    LayoutDashboard,
    LifeBuoy,
    MapPin,
    Percent,
    Shield,
    ShieldAlert,
    Tag,
    User,
    Users,
    Wrench,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<any>();
const { t } = useTrans();
const availableLocales = computed<string[]>(() =>
    Array.isArray(page.props?.available_locales) &&
    page.props.available_locales.length
        ? page.props.available_locales
        : ['en'],
);
const isRtl = computed(() => page.props.direction === 'rtl');
const stripLocalePrefix = (path: string) => {
    const escapedLocales = availableLocales.value.map((locale: string) =>
        locale.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'),
    );
    const localeRegex = new RegExp(
        `^\\/(?:${escapedLocales.join('|')})(?=\\/|$)`,
    );

    return path.replace(localeRegex, '') || '/';
};
const isSuperAdmin = computed(() =>
    stripLocalePrefix(String(page.url || '/')).startsWith('/superadmin'),
);
const currentTenant = computed(() => page.props.current_tenant);
const tenantFeatureFlags = computed<Record<string, boolean>>(
    () => currentTenant.value?.subscription_plan?.feature_flags || {},
);
const hasFeatureFlags = computed(
    () => Object.keys(tenantFeatureFlags.value || {}).length > 0,
);
const localePrefix = computed(() => {
    const currentPath = String(page.url || '/');
    const escapedLocales = availableLocales.value.map((locale) =>
        locale.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'),
    );
    const localeRegex = new RegExp(
        `^\\/(${escapedLocales.join('|')})(?=\\/|$)`,
    );
    const match = currentPath.match(localeRegex);
    return match ? `/${match[1]}` : '';
});
const adminHref = (path: string) => `${localePrefix.value}/admin${path}`;
const authPermissions = computed<string[]>(() =>
    Array.isArray(page.props?.auth?.permissions)
        ? page.props.auth.permissions
        : [],
);
const hasTenantFeature = (feature?: string) =>
    !feature || !hasFeatureFlags.value || Boolean(tenantFeatureFlags.value[feature]);

const mainNavItems = computed<NavItem[]>(() => {
    const slug = currentTenant.value?.slug;
    if (!slug) return [];

    return [
        {
            title: t('dashboard.sidebar.admin.dashboard') || 'Dashboard',
            href: adminHref('/dashboard'),
            icon: LayoutDashboard,
        },
        {
            title: t('dashboard.sidebar.admin.cars'),
            href: carsIndex(slug).url,
            icon: Car,
            permission: 'tenant-manage-cars',
        },
        {
            title: t('dashboard.sidebar.admin.reservations'),
            href: reservationsIndex(slug).url,
            icon: Calendar,
            permission: 'tenant-manage-reservations',
        },
        {
            title: t('dashboard.sidebar.admin.maintenance_types'),
            href: adminHref('/maintenance-types'),
            icon: Wrench,
            permission: 'tenant-manage-cars',
            feature: 'maintenance_module',
        },
        {
            title: t('dashboard.sidebar.admin.maintenance_records'),
            href: adminHref('/maintenance-records'),
            icon: Wrench,
            permission: 'tenant-manage-cars',
            feature: 'maintenance_module',
        },
        {
            title: t('dashboard.sidebar.admin.violation_types'),
            href: adminHref('/violation-types'),
            icon: AlertTriangle,
            permission: 'tenant-manage-cars',
            feature: 'violations_module',
        },
        {
            title: t('dashboard.sidebar.admin.car_violations'),
            href: adminHref('/car-violations'),
            icon: AlertTriangle,
            permission: 'tenant-manage-cars',
            feature: 'violations_module',
        },
        {
            title: t('dashboard.sidebar.admin.damage_reports'),
            href: adminHref('/car-damage-reports'),
            icon: ShieldAlert,
            permission: 'tenant-manage-cars',
            feature: 'damage_reports',
        },
        {
            title: t('dashboard.sidebar.admin.damage_repairs'),
            href: adminHref('/damage-repairs'),
            icon: Wrench,
            permission: 'tenant-manage-cars',
            feature: 'damage_reports',
        },
        {
            title: t('dashboard.sidebar.admin.contracts'),
            href: contractsIndex(slug).url,
            icon: FileText,
            permission: 'tenant-manage-reservations',
        },
        {
            title: t('dashboard.sidebar.admin.clients'),
            href: clientsIndex(slug).url,
            icon: User,
            permission: 'tenant-manage-clients',
        },
        {
            title: t('dashboard.sidebar.admin.payments'),
            href: paymentsIndex(slug).url,
            icon: CreditCard,
            permission: 'tenant-manage-payments',
        },
        {
            title: t('dashboard.sidebar.admin.coupons'),
            href: adminHref('/coupons'),
            icon: Tag,
            permission: 'tenant-manage-payments',
            feature: 'coupon_system',
        },
        {
            title: t('dashboard.sidebar.admin.auto_discounts'),
            href: adminHref('/car-discounts'),
            icon: Percent,
            permission: 'tenant-manage-payments',
            feature: 'auto_discounts',
        },
        {
            title: t('dashboard.sidebar.admin.reports'),
            href: reportsIndex(slug).url,
            icon: BarChart,
            permission: 'tenant-view-reports',
            feature: 'reports_module',
        },
        {
            title: t('dashboard.sidebar.admin.support'),
            href: supportIndex(slug).url,
            icon: LifeBuoy,
            permission: 'tenant-manage-support',
        },
        {
            title: t('dashboard.sidebar.admin.platform_support'),
            href: adminHref('/support/platform'),
            icon: LifeBuoy,
            permission: 'tenant-manage-support',
        },
        {
            title: t('dashboard.sidebar.admin.branches'),
            href: branchesIndex(slug).url,
            icon: MapPin,
            permission: 'tenant-manage-branches',
        },
        {
            title: t('dashboard.sidebar.admin.employees'),
            href: employeesIndex(slug).url,
            icon: Users,
            permission: 'tenant-manage-employees',
        },
        {
            title: t('dashboard.sidebar.admin.roles'),
            href: rolesIndex(slug).url,
            icon: Shield,
            permission: 'tenant-manage-employees',
        },
        {
            title: t('dashboard.sidebar.admin.payment_providers'),
            href: adminHref('/settings/payment-providers'),
            icon: CreditCard,
            permission: 'tenant-manage-settings',
            feature: 'stripe_connect',
        },
        {
            title: t('dashboard.sidebar.admin.website_settings'),
            href: adminHref('/settings/website'),
            icon: Shield,
            permission: 'tenant-manage-settings',
        },
        {
            title: t('dashboard.sidebar.admin.translations'),
            href: adminHref('/settings/translations'),
            icon: Shield,
            permission: 'tenant-manage-settings',
        },
        {
            title: t('dashboard.sidebar.admin.plate_formats'),
            href: adminHref('/settings/plate-formats'),
            icon: Tag,
            permission: 'tenant-manage-settings',
        },
        {
            title: t('dashboard.sidebar.admin.reservation_settings'),
            href: adminHref('/settings/reservation-settings'),
            icon: CalendarDays,
            permission: 'tenant-manage-settings',
        },
    ].filter(
        (item) =>
            (!item.permission || authPermissions.value.includes(item.permission)) &&
            hasTenantFeature(item.feature),
    );
});
</script>

<template>
    <Sidebar
        :side="isRtl ? 'right' : 'left'"
        collapsible="icon"
        variant="inset"
    >
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link
                            :href="
                                isSuperAdmin
                                    ? '/superadmin'
                                    : typeof home === 'function'
                                      ? home().url
                                      : '/'
                            "
                        >
                            <div
                                v-if="currentTenant && currentTenant.name"
                                class="flex items-center gap-2"
                            >
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-md bg-primary p-1 text-xl font-bold text-primary-foreground"
                                >
                                    {{ currentTenant.name.charAt(0) }}
                                </div>
                                <span class="truncate font-semibold">{{
                                    currentTenant.name
                                }}</span>
                            </div>
                            <AppLogo v-else />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SuperAdminNav v-if="isSuperAdmin" />
            <NavMain v-else :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
