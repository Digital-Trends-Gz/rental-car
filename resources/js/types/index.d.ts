import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
    permissions: string[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href?: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    permission?: string;
    feature?: string;
    description?: string;
    children?: NavItem[];
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    app_url_base: string;
    current_tenant: Tenant | null;
    locale: string;
    direction: 'ltr' | 'rtl';
    available_locales: string[];
    translations: Record<string, unknown>;
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface Plan {
    id: number;
    name: string;
    description: string | null;
    sort_order: number;
    features: string[] | null;
    feature_flags: Record<string, boolean> | null;
    monthly_price: number;
    monthly_price_id: string | null;
    yearly_price: number;
    yearly_price_id: string | null;
    one_time_price: number | null;
    one_time_price_id: string | null;
    pricing_meta?: {
        monthly?: PlanPricingMeta;
        yearly?: PlanPricingMeta;
        one_time?: PlanPricingMeta;
    };
    max_employees: number | null;
    max_branches: number | null;
    max_cars: number | null;
    max_contracts: number | null;
    openai_requests_per_day: number | null;
    tenants_count?: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface PlanPricingMeta {
    billing_cycle: 'monthly' | 'yearly' | 'one_time';
    original_amount: number | null;
    final_amount: number | null;
    savings_amount: number;
    savings_percentage: number;
    has_discount: boolean;
    discount: {
        id: number;
        name: string;
        type: 'percentage' | 'fixed';
        value: number;
    } | null;
}

export interface Discount {
    id: number;
    plan_id: number;
    name: string;
    code: string | null;
    type: 'percentage' | 'fixed';
    value: number;
    start_date: string;
    end_date: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    plan?: Plan;
}

export interface Tenant {
    id: number;
    name: string;
    slug: string;
    domain: string | null;
    email: string | null;
    phone: string | null;
    plan_id: number | null;
    subscription_plan?: Plan | null;
    is_active: boolean;
    trial_ends_at: string | null;
    users_count?: number;
    branches_count?: number;
    cars_count?: number;
    reservations_count?: number;
    contracts_count?: number;
    created_at: string;
    updated_at: string;
}

export interface Car {
    id: number;
    tenant_id: number;
    make: string;
    model: string;
    year: number;
    license_plate: string;
    color: string;
    price_per_day: number;
    mileage: number;
    transmission: string;
    seats: number;
    fuel_type: string;
    description: string | null;
    status: string;
    image_url: string;
    created_at: string;
    updated_at: string;
    tenant?: Tenant;
}

export interface Reservation {
    id: number;
    tenant_id: number;
    reservation_number: string;
    user_id: number;
    car_id: number;
    start_date: string;
    end_date: string;
    pickup_time: string;
    return_time: string;
    pickup_location: string;
    return_location: string;
    total_days: number;
    daily_rate: number;
    subtotal: number;
    tax_amount: number;
    discount_amount: number;
    total_amount: number;
    status: string;
    created_at: string;
    updated_at: string;
    user?: User;
    car?: Car;
    tenant?: Tenant;
}
