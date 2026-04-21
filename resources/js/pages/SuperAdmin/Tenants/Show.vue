<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Building2, Mail, Phone, CreditCard, CheckCircle, XCircle, Users, Car, Calendar, DollarSign } from 'lucide-vue-next';

const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
        domain: string | null;
        email: string | null;
        phone: string | null;
        plan_id: number | null;
        subscription_plan?: { id: number; name: string } | null;
        is_active: boolean;
        created_at: string;
        users_count?: number;
        cars_count?: number;
        reservations_count?: number;
        payments_count?: number;
        users?: Array<{ id: number; name: string; email: string; role: string }>;
        reservations?: Array<{ id: number; status: string; start_date: string; end_date: string }>;
        company_owners?: Array<{
            name: string;
            commercial_registration_number: string;
            tax_number: string;
            civil_number: string;
        }>;
    };
}>();

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const planColors: Record<string, string> = {
    basic: 'bg-gray-100 text-gray-700',
    pro: 'bg-blue-100 text-blue-700',
    enterprise: 'bg-purple-100 text-purple-700',
};
</script>

<template>
    <Head :title="`Tenant: ${props.tenant.name}`" />
    <SuperAdminLayout>
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <Link href="/superadmin/tenants">
                        <Button variant="outline">← Back</Button>
                    </Link>
                    <h1 class="text-2xl font-semibold">{{ props.tenant.name }}</h1>
                </div>
                <Link :href="`/superadmin/tenants/${props.tenant.id}/edit`">
                    <Button>Edit Tenant</Button>
                </Link>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Building2 class="h-5 w-5" />
                            Tenant Information
                        </CardTitle>
                        <CardDescription>Company and contact details</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div>
                            <span class="text-sm text-muted-foreground">Company name</span>
                            <p class="font-medium">{{ props.tenant.name }}</p>
                        </div>
                        <div>
                            <span class="text-sm text-muted-foreground">Slug</span>
                            <p class="font-mono text-sm">{{ props.tenant.slug }}</p>
                        </div>
                        <div v-if="props.tenant.domain">
                            <span class="text-sm text-muted-foreground">Custom domain</span>
                            <p class="font-mono text-sm">{{ props.tenant.domain }}</p>
                        </div>
                        <div v-if="props.tenant.email" class="flex items-center gap-2">
                            <Mail class="h-4 w-4 text-muted-foreground" />
                            <a :href="`mailto:${props.tenant.email}`" class="text-primary hover:underline">{{ props.tenant.email }}</a>
                        </div>
                        <div v-if="props.tenant.phone" class="flex items-center gap-2">
                            <Phone class="h-4 w-4 text-muted-foreground" />
                            <span>{{ props.tenant.phone }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <CreditCard class="h-4 w-4 text-muted-foreground" />
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium uppercase"
                                :class="planColors[(props.tenant.subscription_plan?.name || '').toLowerCase()] || 'bg-gray-100 text-gray-700'"
                            >
                                {{ props.tenant.subscription_plan?.name || 'Unassigned' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <component :is="props.tenant.is_active ? CheckCircle : XCircle" class="h-4 w-4" :class="props.tenant.is_active ? 'text-green-600' : 'text-red-600'" />
                            <span :class="props.tenant.is_active ? 'text-green-700' : 'text-red-700'">
                                {{ props.tenant.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="pt-2 text-sm text-muted-foreground">
                            Created {{ formatDate(props.tenant.created_at) }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Statistics</CardTitle>
                        <CardDescription>Counts for this tenant</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex items-center gap-3 rounded-lg border p-3">
                                <Users class="h-8 w-8 text-muted-foreground" />
                                <div>
                                    <p class="text-2xl font-semibold">{{ props.tenant.users_count ?? 0 }}</p>
                                    <p class="text-sm text-muted-foreground">Users</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 rounded-lg border p-3">
                                <Car class="h-8 w-8 text-muted-foreground" />
                                <div>
                                    <p class="text-2xl font-semibold">{{ props.tenant.cars_count ?? 0 }}</p>
                                    <p class="text-sm text-muted-foreground">Cars</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 rounded-lg border p-3">
                                <Calendar class="h-8 w-8 text-muted-foreground" />
                                <div>
                                    <p class="text-2xl font-semibold">{{ props.tenant.reservations_count ?? 0 }}</p>
                                    <p class="text-sm text-muted-foreground">Reservations</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 rounded-lg border p-3">
                                <DollarSign class="h-8 w-8 text-muted-foreground" />
                                <div>
                                    <p class="text-2xl font-semibold">{{ props.tenant.payments_count ?? 0 }}</p>
                                    <p class="text-sm text-muted-foreground">Payments</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Company Owners</CardTitle>
                    <CardDescription>Registration and identity details for all listed owners</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="props.tenant.company_owners?.length" class="grid gap-4 md:grid-cols-2">
                        <div
                            v-for="(owner, index) in props.tenant.company_owners"
                            :key="index"
                            class="rounded-lg border p-4"
                        >
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="font-semibold">Owner {{ index + 1 }}</h3>
                            </div>
                            <dl class="space-y-2 text-sm">
                                <div>
                                    <dt class="text-muted-foreground">Name</dt>
                                    <dd class="font-medium">{{ owner.name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">Commercial Registration No.</dt>
                                    <dd class="font-medium">{{ owner.commercial_registration_number }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">Tax No.</dt>
                                    <dd class="font-medium">{{ owner.tax_number }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">Civil No.</dt>
                                    <dd class="font-medium">{{ owner.civil_number }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">No company owners recorded.</p>
                </CardContent>
            </Card>

            <div class="grid gap-6 md:grid-cols-2">
                <Card v-if="props.tenant.users?.length">
                    <CardHeader>
                        <CardTitle>Recent Users</CardTitle>
                        <CardDescription>Latest users in this tenant</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-2">
                            <li
                                v-for="user in props.tenant.users"
                                :key="user.id"
                                class="flex items-center justify-between rounded border px-3 py-2 text-sm"
                            >
                                <span class="font-medium">{{ user.name }}</span>
                                <span class="text-muted-foreground">{{ user.email }}</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
                <Card v-else>
                    <CardHeader>
                        <CardTitle>Recent Users</CardTitle>
                        <CardDescription>Latest users in this tenant</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm text-muted-foreground">No users yet.</p>
                    </CardContent>
                </Card>

                <Card v-if="props.tenant.reservations?.length">
                    <CardHeader>
                        <CardTitle>Recent Reservations</CardTitle>
                        <CardDescription>Latest bookings</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-2">
                            <li
                                v-for="res in props.tenant.reservations"
                                :key="res.id"
                                class="flex items-center justify-between rounded border px-3 py-2 text-sm"
                            >
                                <span>#{{ res.id }}</span>
                                <span class="capitalize">{{ res.status }}</span>
                                <span class="text-muted-foreground">{{ formatDate(res.start_date) }} – {{ formatDate(res.end_date) }}</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
                <Card v-else>
                    <CardHeader>
                        <CardTitle>Recent Reservations</CardTitle>
                        <CardDescription>Latest bookings</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm text-muted-foreground">No reservations yet.</p>
                    </CardContent>
                </Card>
            </div>
        </main>
    </SuperAdminLayout>
</template>
