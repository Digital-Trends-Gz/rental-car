<script setup lang="ts">
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTrans } from '@/composables/useTrans';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    plans: Array<{ id: number; name: string }>;
    logoFiles: Array<{ id: number; url: string }>;
}>();

const { t } = useTrans();

const form = useForm({
    name: '',
    slug: '',
    domain: '',
    email: '',
    phone: '',
    plan_id: props.plans[0]?.id ? String(props.plans[0].id) : '',
    admin_name: '',
    admin_email: '',
    admin_password: '',
    admin_password_confirmation: '',
    logo_temp_folders: [] as string[],
});

const slugManuallyEdited = ref(false);
const fileUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const logoTempFolders = ref<string[]>([]);

const slugify = (value: string) =>
    value
        .toLowerCase()
        .trim()
        .replace(/\s+/g, '-')
        .replace(/[^\w-]+/g, '')
        .replace(/-{2,}/g, '-')
        .replace(/^-+|-+$/g, '');

watch(
    () => form.name,
    (newName, oldName) => {
        const previousAutoSlug = slugify(oldName ?? '');

        if (!slugManuallyEdited.value || !form.slug || form.slug === previousAutoSlug) {
            form.slug = slugify(newName);
        }
    },
);

watch(
    () => form.slug,
    (newSlug) => {
        if (!newSlug) {
            slugManuallyEdited.value = false;
            return;
        }

        slugManuallyEdited.value = newSlug !== slugify(form.name);
    },
);

watch(
    logoTempFolders,
    (value) => {
        form.logo_temp_folders = [...value];
    },
    { deep: true },
);

const previewLogoUrl = computed(() => props.logoFiles?.[0]?.url || '/logo/logo.png');

const submit = () => {
    form.post('/superadmin/tenants', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            logoTempFolders.value = [];
            form.logo_temp_folders = [];
            fileUploadRef.value?.resetFiles();
        },
    });
};
</script>

<template>
    <Head :title="t('dashboard.super_admin.tenants.create.head_title')" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center gap-4">
                <Link href="/superadmin/tenants">
                    <Button variant="outline">{{ t('dashboard.super_admin.common.back') }}</Button>
                </Link>
                <h1 class="text-2xl font-semibold">{{ t('dashboard.super_admin.tenants.create.title') }}</h1>
            </div>

            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>{{ t('dashboard.super_admin.tenants.create.card_title') }}</CardTitle>
                    <CardDescription>{{ t('dashboard.super_admin.tenants.create.card_description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="space-y-2">
                            <Label for="name">{{ t('dashboard.super_admin.tenants.form.company_name') }} *</Label>
                            <Input id="name" v-model="form.name" type="text" :placeholder="t('dashboard.super_admin.tenants.form.company_name_placeholder')" required />
                            <div v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</div>
                        </div>

                        <div class="space-y-2">
                            <Label for="slug">{{ t('dashboard.super_admin.tenants.form.subdomain') }} *</Label>
                            <div class="flex items-center gap-1">
                                <Input id="slug" v-model="form.slug" type="text" :placeholder="t('dashboard.super_admin.tenants.form.subdomain_placeholder')" required />
                                <span class="whitespace-nowrap text-sm text-muted-foreground">.{{ $page.props.app_url_base || 'localhost' }}</span>
                            </div>
                            <p class="text-xs text-muted-foreground">{{ t('dashboard.super_admin.tenants.form.tenant_url_help') }}</p>
                            <div v-if="form.errors.slug" class="text-sm text-red-600">{{ form.errors.slug }}</div>
                        </div>

                        <div class="space-y-2">
                            <Label for="email">{{ t('dashboard.super_admin.tenants.form.contact_email') }} *</Label>
                            <Input id="email" v-model="form.email" type="email" :placeholder="t('dashboard.super_admin.tenants.form.contact_email_placeholder')" required />
                            <div v-if="form.errors.email" class="text-sm text-red-600">{{ form.errors.email }}</div>
                        </div>

                        <div class="space-y-2">
                            <Label for="domain">{{ t('dashboard.super_admin.tenants.form.custom_domain') }}</Label>
                            <Input id="domain" v-model="form.domain" type="text" :placeholder="t('dashboard.super_admin.tenants.form.custom_domain_placeholder')" />
                            <p class="text-xs text-muted-foreground">{{ t('dashboard.super_admin.tenants.form.custom_domain_help') }}</p>
                            <div v-if="form.errors.domain" class="text-sm text-red-600">{{ form.errors.domain }}</div>
                        </div>

                        <div class="space-y-2">
                            <Label for="phone">{{ t('dashboard.super_admin.tenants.form.phone_number') }}</Label>
                            <Input id="phone" v-model="form.phone" type="tel" :placeholder="t('dashboard.super_admin.tenants.form.phone_placeholder')" />
                            <div v-if="form.errors.phone" class="text-sm text-red-600">{{ form.errors.phone }}</div>
                        </div>

                        <div class="space-y-3">
                            <Label>{{ t('dashboard.super_admin.tenants.form.tenant_logo') }}</Label>
                            <FileUpload
                                ref="fileUploadRef"
                                v-model="logoTempFolders"
                                :initial-files="logoFiles || []"
                                :allow-multiple="false"
                                :max-files="1"
                                collection="logo"
                                theme="light"
                                width="100%"
                            />
                            <p class="text-xs text-muted-foreground">{{ t('dashboard.super_admin.tenants.form.tenant_logo_help_create') }}</p>
                            <div class="rounded-lg border bg-muted/30 p-4">
                                <div class="mb-2 text-xs uppercase text-muted-foreground">{{ t('dashboard.super_admin.tenants.form.preview') }}</div>
                                <img :src="previewLogoUrl" alt="Tenant logo preview" class="h-14 object-contain" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="plan_id">{{ t('dashboard.super_admin.tenants.form.subscription_plan') }} *</Label>
                            <Select v-model="form.plan_id" required>
                                <SelectTrigger>
                                    <SelectValue :placeholder="t('dashboard.super_admin.tenants.form.select_plan')" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="planOption in props.plans" :key="planOption.id" :value="String(planOption.id)">
                                        {{ planOption.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="props.plans.length === 0" class="text-xs text-amber-600">
                                {{ t('dashboard.super_admin.tenants.form.no_plans') }}
                            </p>
                            <div v-if="form.errors.plan_id" class="text-sm text-red-600">{{ form.errors.plan_id }}</div>
                        </div>

                        <div class="mt-6 space-y-4 border-t pt-6">
                            <h3 class="text-lg font-medium">{{ t('dashboard.super_admin.tenants.form.tenant_admin_login') }}</h3>
                            <p class="text-sm text-muted-foreground">
                                {{ t('dashboard.super_admin.tenants.form.tenant_admin_login_help') }}
                            </p>
                            <div class="space-y-2">
                                <Label for="admin_name">{{ t('dashboard.super_admin.tenants.form.admin_name') }} *</Label>
                                <Input id="admin_name" v-model="form.admin_name" type="text" :placeholder="t('dashboard.super_admin.tenants.form.admin_name_placeholder')" required />
                                <div v-if="form.errors.admin_name" class="text-sm text-red-600">{{ form.errors.admin_name }}</div>
                            </div>
                            <div class="space-y-2">
                                <Label for="admin_email">{{ t('dashboard.super_admin.tenants.form.admin_email') }} *</Label>
                                <Input id="admin_email" v-model="form.admin_email" type="email" :placeholder="t('dashboard.super_admin.tenants.form.admin_email_placeholder')" required />
                                <div v-if="form.errors.admin_email" class="text-sm text-red-600">{{ form.errors.admin_email }}</div>
                            </div>
                            <div class="space-y-2">
                                <Label for="admin_password">{{ t('dashboard.super_admin.tenants.form.admin_password') }} *</Label>
                                <Input id="admin_password" v-model="form.admin_password" type="password" :placeholder="t('dashboard.super_admin.tenants.form.password_placeholder')" required autocomplete="new-password" />
                                <div v-if="form.errors.admin_password" class="text-sm text-red-600">{{ form.errors.admin_password }}</div>
                            </div>
                            <div class="space-y-2">
                                <Label for="admin_password_confirmation">{{ t('dashboard.super_admin.tenants.form.confirm_password') }} *</Label>
                                <Input id="admin_password_confirmation" v-model="form.admin_password_confirmation" type="password" :placeholder="t('dashboard.super_admin.tenants.form.password_placeholder')" required autocomplete="new-password" />
                            </div>
                        </div>

                        <div class="flex gap-2 pt-4">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? t('dashboard.super_admin.common.creating') : t('dashboard.super_admin.tenants.create.create') }}
                            </Button>
                            <Link href="/superadmin/tenants">
                                <Button type="button" variant="outline">{{ t('dashboard.super_admin.common.cancel') }}</Button>
                            </Link>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </main>
    </SuperAdminLayout>
</template>
