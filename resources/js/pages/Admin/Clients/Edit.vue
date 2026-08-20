<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useTrans } from '@/composables/useTrans';
import { index, show, update } from '@/routes/admin/clients';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    client: {
        id: number;
        name: string;
        email: string;
        civil_number?: string | null;
        phone?: string | null;
        whatsapp?: string | null;
        branch_id?: number | null;
        is_active: boolean;
    };
    branches: Array<{ id: number; name: string }>;
    canAccessAllBranches: boolean;
}>();

const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);
const { t } = useTrans();
const tr = (key: string) => t(`dashboard.admin.clients.edit.${key}`);

const form = useForm({
    name: props.client.name ?? '',
    email: props.client.email ?? '',
    civil_number: props.client.civil_number ?? '',
    phone: props.client.phone ?? '',
    whatsapp: props.client.whatsapp ?? '',
    branch_id: props.client.branch_id ?? '',
    password: '',
    password_confirmation: '',
});

function submit() {
    if (!subdomain.value) {
        return;
    }

    form.put(update([subdomain.value, props.client.id]).url, {
        preserveScroll: true,
        onSuccess: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head :title="tr('head_title')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ tr('title') }}</h1>
                <div class="flex items-center gap-2">
                    <Link v-if="subdomain" :href="show([subdomain, props.client.id]).url">
                        <Button variant="outline">{{ tr('back_to_client') }}</Button>
                    </Link>
                    <Link v-if="subdomain" :href="index(subdomain).url">
                        <Button variant="outline">{{ tr('back') }}</Button>
                    </Link>
                </div>
            </div>

            <form class="max-w-2xl" @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ tr('client_details') }}</CardTitle>
                        <CardDescription>{{ tr('description') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">{{ tr('full_name') }}</Label>
                            <Input id="name" v-model="form.name" type="text" required :placeholder="tr('client_name_placeholder')" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="email">{{ tr('email') }}</Label>
                            <Input id="email" v-model="form.email" type="email" required :placeholder="tr('email_placeholder')" />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="civil_number">{{ tr('civil_number') }}</Label>
                            <Input
                                id="civil_number"
                                v-model="form.civil_number"
                                type="text"
                                required
                                :placeholder="tr('civil_number_placeholder')"
                            />
                            <InputError :message="form.errors.civil_number" />
                        </div>

                        <div class="space-y-2">
                            <Label for="phone">{{ tr('phone') }}</Label>
                            <Input id="phone" v-model="form.phone" type="tel" required dir="ltr" class="text-left" :placeholder="tr('phone_placeholder')" />
                            <InputError :message="form.errors.phone" />
                        </div>

                        <div class="space-y-2">
                            <Label for="whatsapp">{{ tr('whatsapp') }}</Label>
                            <Input id="whatsapp" v-model="form.whatsapp" type="tel" dir="ltr" class="text-left" :placeholder="tr('whatsapp_placeholder')" />
                            <InputError :message="form.errors.whatsapp" />
                        </div>

                        <div v-if="props.branches.length > 0" class="space-y-2">
                            <Label for="branch_id">{{ tr('branch') }}</Label>
                            <select
                                id="branch_id"
                                v-model="form.branch_id"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="">{{ tr('no_branch') }}</option>
                                <option v-for="branch in props.branches" :key="branch.id" :value="branch.id">
                                    {{ branch.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.branch_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password">{{ tr('password') }}</Label>
                            <Input id="password" v-model="form.password" type="password" autocomplete="new-password" />
                            <p class="text-xs text-muted-foreground">{{ tr('password_help') }}</p>
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password_confirmation">{{ tr('confirm_password') }}</Label>
                            <Input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" />
                            <InputError :message="form.errors.password_confirmation" />
                        </div>
                    </CardContent>
                </Card>

                <div class="mt-6 flex gap-3">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? tr('saving') : tr('save_changes') }}
                    </Button>
                    <Link v-if="subdomain" :href="show([subdomain, props.client.id]).url">
                        <Button type="button" variant="outline">{{ tr('cancel') }}</Button>
                    </Link>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
