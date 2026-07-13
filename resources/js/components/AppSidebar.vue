<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import SuperAdminNav from '@/components/SuperAdminNav.vue';
import {
    Sidebar,
    SidebarContent,
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
    Brain,
    Calendar,
    CalendarDays,
    Car,
    CreditCard,
    DollarSign,
    FileText,
    LayoutDashboard,
    LifeBuoy,
    MapPin,
    Percent,
    Search,
    Settings,
    Shield,
    ShieldAlert,
    Siren,
    Tag,
    User,
    Users,
    Wrench,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AppLogo from './AppLogo.vue';

interface SidebarNavItem extends NavItem {
    key: string;
    children?: SidebarNavItem[];
}

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
const tenantSiteSettings = computed(() => page.props.tenant_site_settings ?? null);
const sidebarSiteName = computed(
    () => tenantSiteSettings.value?.site_name || currentTenant.value?.name || page.props.name || 'Website',
);
const sidebarLogoUrl = computed(() => tenantSiteSettings.value?.logo_url || null);
const sidebarInitial = computed(() => sidebarSiteName.value.trim().charAt(0).toUpperCase() || 'W');
const sidebarLogoFailed = ref(false);
watch(sidebarLogoUrl, () => {
    sidebarLogoFailed.value = false;
});
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

const filterNavItems = (items: SidebarNavItem[]): SidebarNavItem[] =>
    items
        .map((item) => {
            const children = item.children?.length
                ? filterNavItems(item.children)
                : undefined;

            return {
                ...item,
                children,
            };
        })
        .filter((item) => {
            if (item.permission && !authPermissions.value.includes(item.permission)) {
                return false;
            }

            if (!hasTenantFeature(item.feature)) {
                return false;
            }

            if (item.children) {
                return item.children.length > 0;
            }

            return true;
        });

const mainNavItems = computed<SidebarNavItem[]>(() => {
    const slug = currentTenant.value?.slug;
    if (!slug) return [];

    return filterNavItems([
        {
            key: 'dashboard',
            title: t('dashboard.sidebar.admin.dashboard') || 'Dashboard',
            href: adminHref('/dashboard'),
            icon: LayoutDashboard,
        },
        {
            key: 'bookings-and-contracts',
            title: t('dashboard.sidebar.admin_groups.bookings_and_contracts') || 'Bookings & Contracts',
            icon: CalendarDays,
            children: [
                {
                    key: 'reservations',
                    title: t('dashboard.sidebar.admin.reservations'),
                    href: reservationsIndex(slug).url,
                    icon: Calendar,
                    permission: 'tenant-manage-reservations',
                },
                {
                    key: 'contracts',
                    title: t('dashboard.sidebar.admin.contracts'),
                    href: contractsIndex(slug).url,
                    icon: FileText,
                    permission: 'tenant-manage-reservations',
                },
                {
                    key: 'reservation-settings',
                    title: t('dashboard.sidebar.admin.reservation_settings'),
                    href: adminHref('/settings/reservation-settings'),
                    icon: CalendarDays,
                    permission: 'tenant-manage-settings',
                },
                {
                    key: 'contract-pdf-template',
                    title: t('dashboard.sidebar.admin.contract_pdf') || 'Contract PDF Template',
                    href: adminHref('/settings/contract-pdf'),
                    icon: FileText,
                    permission: 'tenant-manage-settings',
                },
                {
                    key: 'mrta-pdf-template',
                    title: t('dashboard.sidebar.admin.mrta_pdf') || 'MRTA PDF Template',
                    href: adminHref('/settings/mrta-pdf'),
                    icon: FileText,
                    permission: 'tenant-manage-settings',
                },
            ],
        },
        {
            key: 'fleet-management',
            title: t('dashboard.sidebar.admin_groups.fleet_management') || 'Fleet Management',
            icon: Car,
            children: [
                {
                    key: 'cars',
                    title: t('dashboard.sidebar.admin.cars'),
                    href: carsIndex(slug).url,
                    icon: Car,
                    permission: 'tenant-manage-cars',
                },
                {
                    key: 'maintenance-types',
                    title: t('dashboard.sidebar.admin.maintenance_types'),
                    href: adminHref('/maintenance-types'),
                    icon: Wrench,
                    permission: 'tenant-manage-cars',
                    feature: 'maintenance_module',
                },
                {
                    key: 'maintenance-records',
                    title: t('dashboard.sidebar.admin.maintenance_records'),
                    href: adminHref('/maintenance-records'),
                    icon: Wrench,
                    permission: 'tenant-manage-cars',
                    feature: 'maintenance_module',
                },
                {
                    key: 'violation-types',
                    title: t('dashboard.sidebar.admin.violation_types'),
                    href: adminHref('/violation-types'),
                    icon: AlertTriangle,
                    permission: 'tenant-manage-cars',
                    feature: 'violations_module',
                },
                {
                    key: 'car-violations',
                    title: t('dashboard.sidebar.admin.car_violations'),
                    href: adminHref('/car-violations'),
                    icon: AlertTriangle,
                    permission: 'tenant-manage-cars',
                    feature: 'violations_module',
                },
                {
                    key: 'damage-reports',
                    title: t('dashboard.sidebar.admin.damage_reports'),
                    href: adminHref('/car-damage-reports'),
                    icon: ShieldAlert,
                    permission: 'tenant-manage-cars',
                    feature: 'damage_reports',
                },
                {
                    key: 'damage-repairs',
                    title: t('dashboard.sidebar.admin.damage_repairs'),
                    href: adminHref('/damage-repairs'),
                    icon: Wrench,
                    permission: 'tenant-manage-cars',
                    feature: 'damage_reports',
                },
                {
                    key: 'accident-reports',
                    title: t('dashboard.sidebar.admin.accident_reports'),
                    href: adminHref('/accident-reports'),
                    icon: Siren,
                    permission: 'tenant-manage-reservations',
                },
            ],
        },
        {
            key: 'people-and-branches',
            title: t('dashboard.sidebar.admin_groups.people_and_branches') || 'People & Branches',
            icon: Users,
            children: [
                {
                    key: 'clients',
                    title: t('dashboard.sidebar.admin.clients'),
                    href: clientsIndex(slug).url,
                    icon: User,
                    permission: 'tenant-manage-clients',
                },
                {
                    key: 'branches',
                    title: t('dashboard.sidebar.admin.branches'),
                    href: branchesIndex(slug).url,
                    icon: MapPin,
                    permission: 'tenant-manage-branches',
                },
                {
                    key: 'employees',
                    title: t('dashboard.sidebar.admin.employees'),
                    href: employeesIndex(slug).url,
                    icon: Users,
                    permission: 'tenant-manage-employees',
                },
                {
                    key: 'roles-permissions',
                    title: t('dashboard.sidebar.admin.roles_permissions') || 'Roles & Permissions',
                    href: rolesIndex(slug).url,
                    icon: Shield,
                    permission: 'tenant-manage-employees',
                },
            ],
        },
        {
            key: 'payments-and-offers',
            title: t('dashboard.sidebar.admin_groups.payments_and_offers') || 'Payments & Offers',
            icon: DollarSign,
            children: [
                {
                    key: 'payments',
                    title: t('dashboard.sidebar.admin.payments'),
                    href: paymentsIndex(slug).url,
                    icon: CreditCard,
                    permission: 'tenant-manage-payments',
                },
                {
                    key: 'debtors',
                    title: t('dashboard.sidebar.admin.debtors'),
                    href: adminHref('/payments/debtors'),
                    icon: CreditCard,
                    permission: 'tenant-view-debtors',
                },
                {
                    key: 'discount-requests',
                    title: t('dashboard.sidebar.admin.discount_requests') || 'Discount Requests',
                    href: adminHref('/discount-requests'),
                    icon: Percent,
                    permission: 'tenant-manage-payments',
                },
                {
                    key: 'payment-providers',
                    title: t('dashboard.sidebar.admin.payment_providers'),
                    href: adminHref('/settings/payment-providers'),
                    icon: CreditCard,
                    permission: 'tenant-manage-settings',
                    feature: 'stripe_connect',
                },
                {
                    key: 'coupons',
                    title: t('dashboard.sidebar.admin.coupons'),
                    href: adminHref('/coupons'),
                    icon: Tag,
                    permission: 'tenant-manage-payments',
                    feature: 'coupon_system',
                },
                {
                    key: 'auto-discounts',
                    title: t('dashboard.sidebar.admin.auto_discounts'),
                    href: adminHref('/car-discounts'),
                    icon: Percent,
                    permission: 'tenant-manage-payments',
                    feature: 'auto_discounts',
                },
            ],
        },
        {
            key: 'reports-and-growth',
            title: t('dashboard.sidebar.admin_groups.reports_and_growth') || 'Reports & Growth',
            icon: BarChart,
            children: [
                {
                    key: 'reports',
                    title: t('dashboard.sidebar.admin.reports'),
                    href: reportsIndex(slug).url,
                    icon: BarChart,
                    permission: 'tenant-view-reports',
                    feature: 'reports_module',
                },
                {
                    key: 'ai-insights',
                    title: t('dashboard.sidebar.admin.ai_insights'),
                    href: adminHref('/ai-insights'),
                    icon: Brain,
                    permission: 'tenant-view-reports',
                    feature: 'reports_module',
                },
                {
                    key: 'seo-settings',
                    title: t('dashboard.sidebar.admin.seo_settings'),
                    href: adminHref('/settings/seo'),
                    icon: Search,
                    permission: 'tenant-manage-settings',
                },
                {
                    key: 'seo-audit',
                    title: t('dashboard.sidebar.admin.seo_audit'),
                    href: adminHref('/settings/seo-audit'),
                    icon: Search,
                    permission: 'tenant-manage-settings',
                },
            ],
        },
        {
            key: 'support-and-settings',
            title: t('dashboard.sidebar.admin_groups.support_and_settings') || 'Support & Settings',
            icon: LifeBuoy,
            children: [
                {
                    key: 'support',
                    title: t('dashboard.sidebar.admin.support'),
                    href: supportIndex(slug).url,
                    icon: LifeBuoy,
                    permission: 'tenant-manage-support',
                },
                {
                    key: 'platform-support',
                    title: t('dashboard.sidebar.admin.platform_support'),
                    href: adminHref('/support/platform'),
                    icon: LifeBuoy,
                    permission: 'tenant-manage-support',
                },
                {
                    key: 'website-settings',
                    title: t('dashboard.sidebar.admin.website_settings'),
                    href: adminHref('/settings/website'),
                    icon: Settings,
                    permission: 'tenant-manage-settings',
                },
                {
                    key: 'translations',
                    title: t('dashboard.sidebar.admin.translations'),
                    href: adminHref('/settings/translations'),
                    icon: Settings,
                    permission: 'tenant-manage-settings',
                },
                {
                    key: 'plate-formats',
                    title: t('dashboard.sidebar.admin.plate_formats'),
                    href: adminHref('/settings/plate-formats'),
                    icon: Tag,
                    permission: 'tenant-manage-settings',
                },
            ],
        },
    ]);
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
                            <div v-if="currentTenant" class="flex w-full flex-row items-center gap-3">
                                <img
                                    v-if="sidebarLogoUrl && !sidebarLogoFailed"
                                    :src="sidebarLogoUrl"
                                    :alt="sidebarSiteName"
                                    class="h-12 w-24 shrink-0 object-contain"
                                    @error="sidebarLogoFailed = true"
                                />
                                <div
                                    v-else
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-primary p-1 text-xl font-bold text-primary-foreground"
                                >
                                    {{ sidebarInitial }}
                                </div>
                                <span class="min-w-0 truncate text-left font-semibold">
                                    {{ sidebarSiteName }}
                                </span>
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

    </Sidebar>
    <slot />
</template>
