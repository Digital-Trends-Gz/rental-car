<script setup lang="ts">
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { computed, ref, watch } from 'vue';

const { t } = useTrans();

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
        logo_url: string | null;
    };
    settings: {
        document_extraction_daily_limit: number | null;
    };
    plans: Array<{ id: number; name: string }>;
    admin_user: { id: number; name: string; email: string } | null;
    logoFiles: Array<{ id: number; url: string }>;
}>();

const form = useForm({
    name: props.tenant.name,
    slug: props.tenant.slug,
    domain: props.tenant.domain ?? '',
    email: props.tenant.email ?? '',
    phone: props.tenant.phone ?? '',
    plan_id: props.tenant.plan_id ? String(props.tenant.plan_id) : '',
    is_active: props.tenant.is_active,
    document_extraction_daily_limit: props.settings.document_extraction_daily_limit ?? '',
    admin_password: '',
    admin_password_confirmation: '',
    logo_temp_folders: [] as string[],
    logo_removed_files: [] as number[],
});

const fileUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const logoTempFolders = ref<string[]>([]);
const logoRemovedFileIds = ref<number[]>([]);

watch(
    logoTempFolders,
    (value) => {
        form.logo_temp_folders = [...value];
    },
    { deep: true },
);

const handleLogoFileRemoved = (data: { type: string; fileId?: number }) => {
    if (data.type === 'existing' && data.fileId) {
        logoRemovedFileIds.value.push(data.fileId);
        form.logo_removed_files = [...new Set(logoRemovedFileIds.value)];
    }
};

const previewLogoUrl = computed(() => props.logoFiles?.[0]?.url || props.tenant.logo_url || '/logo/logo.png');

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            _method: 'put',
        }))
        .post(`/superadmin/tenants/${props.tenant.id}`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                logoTempFolders.value = [];
                form.logo_temp_folders = [];
                form.logo_removed_files = [];
                logoRemovedFileIds.value = [];
                fileUploadRef.value?.resetFiles();
            },
        });
};
</script>

<template>
    <Head :title="`${t('dashboard.super_admin.tenants.edit.head_title')}: ${props.tenant.name}`" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center gap-4">
                <Link :href="`/superadmin/tenants/${props.tenant.id}`">
                    <Button variant="outline">{{ t('dashboard.super_admin.common.back') }}</Button>
                </Link>
                <h1 class="text-2xl font-semibold">{{ t('dashboard.super_admin.tenants.edit.title') }}</h1>
            </div>

            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>{{ t('dashboard.super_admin.tenants.edit.card_title') }}</CardTitle>
                    <CardDescription>{{ t('dashboard.super_admin.tenants.edit.card_description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="space-y-2">
                            <Label for="name">{{ t('dashboard.super_admin.tenants.form.company_name') }} *</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                :placeholder="t('dashboard.super_admin.tenants.form.company_name_placeholder')"
                                required
                            />
                            <div v-if="form.errors.name" class="text-sm text-red-600">
                                {{ form.errors.name }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="slug">{{ t('dashboard.super_admin.tenants.form.subdomain') }} *</Label>
                            <div class="flex items-center gap-1">
                                <Input
                                    id="slug"
                                    v-model="form.slug"
                                    type="text"
                                    :placeholder="t('dashboard.super_admin.tenants.form.subdomain_placeholder')"
                                    required
                                />
                                <span class="whitespace-nowrap text-sm text-muted-foreground">.{{ $page.props.app_url_base || 'localhost' }}</span>
                            </div>
                            <p class="text-xs text-muted-foreground">{{ t('dashboard.super_admin.tenants.form.tenant_url_help') }}</p>
                            <div v-if="form.errors.slug" class="text-sm text-red-600">
                                {{ form.errors.slug }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="email">{{ t('dashboard.super_admin.tenants.form.contact_email') }} *</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                :placeholder="t('dashboard.super_admin.tenants.form.contact_email_placeholder')"
                                required
                            />
                            <div v-if="form.errors.email" class="text-sm text-red-600">
                                {{ form.errors.email }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="domain">{{ t('dashboard.super_admin.tenants.form.custom_domain') }}</Label>
                            <Input
                                id="domain"
                                v-model="form.domain"
                                type="text"
                                :placeholder="t('dashboard.super_admin.tenants.form.custom_domain_placeholder')"
                            />
                            <p class="text-xs text-muted-foreground">{{ t('dashboard.super_admin.tenants.form.custom_domain_help') }}</p>
                            <div v-if="form.errors.domain" class="text-sm text-red-600">
                                {{ form.errors.domain }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="phone">{{ t('dashboard.super_admin.tenants.form.phone_number') }}</Label>
                            <Input
                                id="phone"
                                v-model="form.phone"
                                type="tel"
                                :placeholder="t('dashboard.super_admin.tenants.form.phone_placeholder')"
                            />
                            <div v-if="form.errors.phone" class="text-sm text-red-600">
                                {{ form.errors.phone }}
                            </div>
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
                                @file-removed="handleLogoFileRemoved"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ t('dashboard.super_admin.tenants.form.tenant_logo_help_edit') }}
                            </p>
                            <div class="rounded-lg border bg-muted/30 p-4">
                                <div class="mb-2 text-xs uppercase text-muted-foreground">{{ t('dashboard.super_admin.tenants.form.preview') }}</div>
                                <img :src="previewLogoUrl" alt="Tenant logo preview" class="h-14 object-contain" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="document_extraction_daily_limit">
                                Daily AI Extraction Limit
                            </Label>
                            <Input
                                id="document_extraction_daily_limit"
                                v-model.number="form.document_extraction_daily_limit"
                                type="number"
                                min="1"
                                step="1"
                                placeholder="10"
                            />
                            <p class="text-xs text-muted-foreground">
                                Leave blank to use the global Super Admin default.
                            </p>
                            <div v-if="form.errors.document_extraction_daily_limit" class="text-sm text-red-600">
                                {{ form.errors.document_extraction_daily_limit }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="plan_id">{{ t('dashboard.super_admin.tenants.form.subscription_plan') }} *</Label>
                            <Select v-model="form.plan_id" required>
                                <SelectTrigger>
                                    <SelectValue :placeholder="t('dashboard.super_admin.tenants.form.select_plan')" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="planOption in props.plans"
                                        :key="planOption.id"
                                        :value="String(planOption.id)"
                                    >
                                        {{ planOption.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="props.plans.length === 0" class="text-xs text-amber-600">
                                {{ t('dashboard.super_admin.tenants.form.no_plans') }}
                            </p>
                            <div v-if="form.errors.plan_id" class="text-sm text-red-600">
                                {{ form.errors.plan_id }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="is_active">{{ t('dashboard.super_admin.tenants.form.status') }}</Label>
                            <Select :model-value="form.is_active ? '1' : '0'" @update:model-value="(val: any) => form.is_active = val === '1'">
                                <SelectTrigger>
                                    <SelectValue :placeholder="t('dashboard.super_admin.tenants.form.select_status')" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">{{ t('dashboard.super_admin.tenants.form.active') }}</SelectItem>
                                    <SelectItem value="0">{{ t('dashboard.super_admin.tenants.form.inactive') }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-sm text-muted-foreground">{{ t('dashboard.super_admin.tenants.form.inactive_help') }}</p>
                            <div v-if="form.errors.is_active" class="text-sm text-red-600">
                                {{ form.errors.is_active }}
                            </div>
                        </div>

                        <div v-if="admin_user" class="mt-6 space-y-4 border-t pt-6">
                            <h3 class="text-lg font-medium">{{ t('dashboard.super_admin.tenants.form.change_admin_password') }}</h3>
                            <p class="text-sm text-muted-foreground">
                                {{ t('dashboard.super_admin.tenants.form.change_admin_password_help', { email: admin_user.email }) }}
                            </p>
                            <div class="space-y-2">
                                <Label for="admin_password">{{ t('dashboard.super_admin.tenants.form.new_password') }}</Label>
                                <Input
                                    id="admin_password"
                                    v-model="form.admin_password"
                                    type="password"
                                    :placeholder="t('dashboard.super_admin.tenants.form.password_placeholder')"
                                    autocomplete="new-password"
                                />
                                <div v-if="form.errors.admin_password" class="text-sm text-red-600">
                                    {{ form.errors.admin_password }}
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label for="admin_password_confirmation">{{ t('dashboard.super_admin.tenants.form.confirm_new_password') }}</Label>
                                <Input
                                    id="admin_password_confirmation"
                                    v-model="form.admin_password_confirmation"
                                    type="password"
                                    :placeholder="t('dashboard.super_admin.tenants.form.password_placeholder')"
                                    autocomplete="new-password"
                                />
                            </div>
                        </div>

                        <div class="flex gap-2 pt-4">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? t('dashboard.super_admin.common.saving') : t('dashboard.super_admin.tenants.edit.save') }}
                            </Button>
                            <Link :href="`/superadmin/tenants/${props.tenant.id}`">
                                <Button type="button" variant="outline">{{ t('dashboard.super_admin.common.cancel') }}</Button>
                            </Link>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </main>
    </SuperAdminLayout>
</template>
