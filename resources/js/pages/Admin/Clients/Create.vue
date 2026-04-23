<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { index, store } from '@/routes/admin/clients';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    branches: Array<{ id: number; name: string }>;
    canAccessAllBranches: boolean;
}>();

const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);

const form = useForm({
    name: '',
    email: '',
    civil_number: '',
    branch_id: props.branches[0]?.id ?? '',
    password: '',
    password_confirmation: '',
});

function submit() {
    if (!subdomain.value) {
        return;
    }

    form.post(store(subdomain.value).url, {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'email', 'civil_number', 'password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Create Client" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">Create Client</h1>
                <Link v-if="subdomain" :href="index(subdomain).url">
                    <Button variant="outline">Back</Button>
                </Link>
            </div>

            <form class="max-w-2xl" @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle>Client details</CardTitle>
                        <CardDescription>Create a client account for the current tenant.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Full Name *</Label>
                            <Input id="name" v-model="form.name" type="text" required placeholder="Client name" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="email">Email *</Label>
                            <Input id="email" v-model="form.email" type="email" required placeholder="client@example.com" />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="civil_number">Civil Number *</Label>
                            <Input
                                id="civil_number"
                                v-model="form.civil_number"
                                type="text"
                                required
                                placeholder="Civil number"
                            />
                            <InputError :message="form.errors.civil_number" />
                        </div>

                        <div v-if="props.branches.length > 0" class="space-y-2">
                            <Label for="branch_id">Branch</Label>
                            <select
                                id="branch_id"
                                v-model="form.branch_id"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="">No branch</option>
                                <option v-for="branch in props.branches" :key="branch.id" :value="branch.id">
                                    {{ branch.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.branch_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password">Password *</Label>
                            <Input id="password" v-model="form.password" type="password" required autocomplete="new-password" />
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password_confirmation">Confirm Password *</Label>
                            <Input id="password_confirmation" v-model="form.password_confirmation" type="password" required autocomplete="new-password" />
                            <InputError :message="form.errors.password_confirmation" />
                        </div>
                    </CardContent>
                </Card>

                <div class="mt-6 flex gap-3">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Creating...' : 'Create Client' }}
                    </Button>
                    <Link v-if="subdomain" :href="index(subdomain).url">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
