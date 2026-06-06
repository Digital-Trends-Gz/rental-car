<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { useTrans } from '@/composables/useTrans';
import { urlIsActive } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    DollarSign,
    Users,
    Package,
    Settings,
    ChevronDown,
    LayoutDashboard,
    CreditCard,
    Receipt,
    UserCircle,
    Shield,
    Tag,
    Percent,
    CarFront,
    CalendarDays,
    Cog,
    LifeBuoy,
    Palette,
    Mail,
    ShieldAlert,
    Languages,
} from 'lucide-vue-next';

import { type NavItem } from '@/types';

const page = usePage();
const { t } = useTrans();
const authPermissions = computed<string[]>(() =>
    Array.isArray(page.props.auth?.permissions) ? page.props.auth.permissions : [],
);
const normalizedPath = computed(() => String(page.url ?? '').replace(/^\/(ar|en)(?=\/|$)/, '') || '/');
const normalizedRole = computed(() => {
    const role = page.props.auth?.user?.role;

    if (typeof role === 'string') {
        return role;
    }

    if (role && typeof role === 'object') {
        return String(role.value ?? role.name ?? '');
    }

    return '';
});
const hasFullSuperAdminAccess = computed(
    () =>
        normalizedRole.value === 'super_admin' ||
        normalizedPath.value.startsWith('/superadmin'),
);
const hasPermission = (permission?: string) =>
    !permission ||
    hasFullSuperAdminAccess.value ||
    authPermissions.value.includes(permission);

const superAdminNav = computed<NavItem[]>(() => [
    {
        title: t('dashboard.sidebar.super_admin.dashboard'),
        href: '/superadmin',
        icon: LayoutDashboard,
        permission: 'view-dashboard',
    },
    {
        title: t('dashboard.sidebar.super_admin.revenue'),
        icon: DollarSign,
        permission: 'manage-revenue',
        children: [
            { title: t('dashboard.sidebar.super_admin.subscription'), href: '/superadmin/revenue/subscription', icon: CreditCard },
            { title: t('dashboard.sidebar.super_admin.transactions'), href: '/superadmin/revenue/transactions', icon: Receipt },
        ],
    },
    {
        title: t('dashboard.sidebar.super_admin.user_management'),
        icon: Users,
        children: [
            { title: t('dashboard.sidebar.super_admin.users'), href: '/superadmin/users', icon: UserCircle, permission: 'manage-users' },
            { title: t('dashboard.sidebar.super_admin.roles'), href: '/superadmin/roles', icon: Shield, permission: 'manage-roles' },
            { title: t('dashboard.sidebar.super_admin.tenants'), href: '/superadmin/tenants', icon: Users, permission: 'manage-tenants' },
        ].filter(item => hasPermission(item.permission)),
    },
    {
        title: t('dashboard.sidebar.super_admin.product_management'),
        icon: Package,
        permission: 'manage-settings',
        children: [
            { title: t('dashboard.sidebar.super_admin.plans'), href: '/superadmin/plans', icon: Tag },
            { title: t('dashboard.sidebar.super_admin.discounts'), href: '/superadmin/discounts', icon: Percent },
        ],
    },
    {
        title: t('dashboard.sidebar.super_admin.cars'),
        href: '/superadmin/cars',
        icon: CarFront,
        description: t('dashboard.sidebar.super_admin.cars_description'),
        permission: 'manage-cars',
    },
    {
        title: t('dashboard.sidebar.super_admin.reservations'),
        href: '/superadmin/reservations',
        icon: CalendarDays,
        permission: 'manage-reservations',
    },
    {
        title: 'Support',
        icon: LifeBuoy,
        permission: 'manage-tenants',
        children: [
            { title: 'Tenant Support', href: '/superadmin/support/tenants', icon: LifeBuoy, permission: 'manage-tenants' },
            { title: 'Landing Leads', href: '/superadmin/leads/landing', icon: LifeBuoy },
        ].filter(item => hasPermission(item.permission)),
    },
    {
        title: t('dashboard.sidebar.super_admin.settings'),
        icon: Settings,
        permission: 'manage-settings',
        children: [
            { title: t('dashboard.sidebar.super_admin.general_settings'), href: '/superadmin/settings/general', icon: Cog },
            { title: 'Branding', href: '/superadmin/settings/branding', icon: Cog },
            { title: 'Design', href: '/superadmin/settings/design', icon: Palette },
            { title: 'Landing Translations', href: '/superadmin/settings/landing-translations', icon: Languages },
            { title: 'Login Settings', href: '/superadmin/settings/login', icon: Shield },
            { title: 'Payment Providers', href: '/superadmin/settings/payment-providers', icon: CreditCard },
            { title: 'Plate Format Templates', href: '/superadmin/settings/plate-format-templates', icon: Tag },
            { title: 'Languages', href: '/superadmin/settings/languages', icon: Cog },
            { title: 'Emails', href: '/superadmin/settings/emails', icon: Mail },
            { title: 'Security Access', href: '/superadmin/settings/security-access', icon: ShieldAlert },
            { title: 'SEO Settings', href: '/superadmin/settings/seo', icon: Cog },
        ],
    },
]);

const filteredNav = computed(() => {
    return superAdminNav.value.map(item => {
        const newItem = { ...item };
        
        // Filter children based on permissions
        if (newItem.children) {
            newItem.children = newItem.children.filter(child => {
                return hasPermission(child.permission);
            });
        }
        
        return newItem;
    }).filter(item => {
        // If parent has a permission, check it
        if (!hasPermission(item.permission)) {
            return false;
        }
        
        // If it was a group with children but they are all gone, hide the parent
        if (item.children && item.children.length === 0) {
            return false;
        }
        
        return true;
    });
});
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ t('dashboard.sidebar.super_admin_section') }}</SidebarGroupLabel>
        <SidebarMenu>
            <template v-for="item in filteredNav" :key="item.title">
                <!-- Single link (no children) -->
                <SidebarMenuItem v-if="'href' in item && item.href">
                    <SidebarMenuButton
                        as-child
                        :is-active="urlIsActive(item.href, page.url)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                <!-- Group with children -->
                <SidebarMenuItem v-else-if="item.children?.length">
                    <Collapsible class="group/collapsible">
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                :is-active="item.children.some((c) => c.href && urlIsActive(c.href, page.url))"
                                :tooltip="item.title"
                            >
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <ChevronDown
                                    class="ml-auto size-4 transition-transform group-data-[state=open]/collapsible:rotate-180"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                    <SidebarMenuSubItem
                                        v-for="child in item.children"
                                        :key="child.title"
                                    >
                                        <SidebarMenuSubButton
                                            v-if="child.href"
                                            as-child
                                            :is-active="urlIsActive(child.href, page.url)"
                                        >
                                        <Link :href="child.href">
                                            <component :is="child.icon" />
                                            <span>{{ child.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </Collapsible>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
