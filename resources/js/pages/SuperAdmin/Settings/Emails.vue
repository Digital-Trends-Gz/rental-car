<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { useTrans } from '@/composables/useTrans';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { computed, ref } from 'vue';

type TemplateKey = 'verify_email_after_payment' | 'tenant_admin_invitation';

type TemplateConfig = {
    name: string;
    description: string;
    subject: string;
    greeting: string;
    intro: string;
    body: string;
    action_text: string;
    outro: string;
    salutation: string;
};

const props = defineProps<{
    templates: Record<TemplateKey, TemplateConfig>;
    placeholders: Record<string, string>;
    actions: {
        update: string;
    };
}>();
const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const form = useForm({
    templates: JSON.parse(JSON.stringify(props.templates)),
});

const selectedTemplate = ref<TemplateKey>('verify_email_after_payment');

const currentTemplate = computed(() => form.templates[selectedTemplate.value]);

const previewTokens = computed<Record<string, string>>(() => ({
    '{app_name}': 'Car4u',
    '{name}': 'John Doe',
    '{email}': 'john@example.com',
    '{tenant_name}': 'Acme Rentals',
    '{tenant_slug}': 'acme-rentals',
    '{expire_minutes}': '60',
}));

const renderPreview = (value: string) => {
    let output = value || '';

    Object.entries(previewTokens.value).forEach(([token, replacement]) => {
        output = output.split(token).join(replacement);
    });

    return output;
};

const submit = () => {
    form.put(props.actions.update, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="localize('Email Templates', 'قوالب البريد')" />

    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Email Templates', 'قوالب البريد') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Edit the email messages used for account verification and tenant admin activation.', 'حرر رسائل البريد المستخدمة لتأكيد الحساب وتفعيل مدير المستأجر.') }}
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                </Button>
            </div>

            <div class="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)_420px]">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Templates', 'القوالب') }}</CardTitle>
                        <CardDescription>{{ localize('Select a template to edit.', 'اختر قالبًا لتحريره.') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <button
                            v-for="(template, key) in form.templates"
                            :key="key"
                            type="button"
                            class="w-full rounded-lg border p-4 text-left transition"
                            :class="selectedTemplate === key ? 'border-primary bg-primary/5' : 'hover:bg-muted/40'"
                            @click="selectedTemplate = key as TemplateKey"
                        >
                            <div class="font-medium">{{ template.name }}</div>
                            <div class="mt-1 text-sm text-muted-foreground">{{ template.description }}</div>
                        </button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ currentTemplate.name }}</CardTitle>
                        <CardDescription>{{ currentTemplate.description }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label>{{ localize('Subject', 'الموضوع') }}</Label>
                            <Input v-model="currentTemplate.subject" />
                        </div>

                        <div class="space-y-2">
                            <Label>{{ localize('Greeting', 'التحية') }}</Label>
                            <Input v-model="currentTemplate.greeting" />
                        </div>

                        <div class="space-y-2">
                            <Label>{{ localize('Intro', 'المقدمة') }}</Label>
                            <Textarea v-model="currentTemplate.intro" rows="3" />
                        </div>

                        <div class="space-y-2">
                            <Label>{{ localize('Body', 'المحتوى') }}</Label>
                            <Textarea v-model="currentTemplate.body" rows="5" />
                        </div>

                        <div class="space-y-2">
                            <Label>{{ localize('Action Button Text', 'نص زر الإجراء') }}</Label>
                            <Input v-model="currentTemplate.action_text" />
                        </div>

                        <div class="space-y-2">
                            <Label>{{ localize('Outro', 'الخاتمة') }}</Label>
                            <Textarea v-model="currentTemplate.outro" rows="4" />
                        </div>

                        <div class="space-y-2">
                            <Label>{{ localize('Salutation', 'عبارة التوقيع') }}</Label>
                            <Input v-model="currentTemplate.salutation" />
                        </div>
                    </CardContent>
                </Card>

                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Preview', 'المعاينة') }}</CardTitle>
                            <CardDescription>{{ localize('Live preview using sample values.', 'معاينة مباشرة باستخدام قيم تجريبية.') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="rounded-lg border bg-background p-4 shadow-sm">
                                <div class="text-sm font-semibold text-muted-foreground">{{ localize('Subject', 'الموضوع') }}</div>
                                <div class="mt-1 text-base font-medium">{{ renderPreview(currentTemplate.subject) }}</div>
                            </div>

                            <div class="rounded-lg border bg-background p-5 shadow-sm">
                                <div class="text-base font-medium">{{ renderPreview(currentTemplate.greeting) }}</div>
                                <p class="mt-4 text-sm leading-6 text-muted-foreground">{{ renderPreview(currentTemplate.intro) }}</p>
                                <p class="mt-3 text-sm leading-6 text-muted-foreground">{{ renderPreview(currentTemplate.body) }}</p>
                                <div class="mt-5">
                                    <button type="button" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground">
                                        {{ renderPreview(currentTemplate.action_text) }}
                                    </button>
                                </div>
                                <p class="mt-5 text-sm leading-6 text-muted-foreground">{{ renderPreview(currentTemplate.outro) }}</p>
                                <p class="mt-5 text-sm font-medium">{{ renderPreview(currentTemplate.salutation) }}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ localize('Available Placeholders', 'المتغيرات المتاحة') }}</CardTitle>
                            <CardDescription>{{ localize('Use these tokens inside any template field.', 'استخدم هذه الرموز داخل أي حقل في القالب.') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <div
                                v-for="(description, token) in placeholders"
                                :key="token"
                                class="flex items-center justify-between gap-3 rounded-md border px-3 py-2"
                            >
                                <code class="font-mono text-xs">{{ token }}</code>
                                <span class="text-muted-foreground">{{ description }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </main>
    </SuperAdminLayout>
</template>
