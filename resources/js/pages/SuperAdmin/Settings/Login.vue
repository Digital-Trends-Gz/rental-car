<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

interface SocialLoginSettings {
    google: {
        enabled: boolean;
        client_id: string;
        client_secret: string;
    };
    apple: {
        enabled: boolean;
        client_id: string;
        client_secret: string;
    };
}

const props = defineProps<{
    socialLoginSettings: SocialLoginSettings;
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const form = useForm<{
    social_login: SocialLoginSettings;
}>({
    social_login: JSON.parse(JSON.stringify(props.socialLoginSettings || {
        google: { enabled: false, client_id: '', client_secret: '' },
        apple: { enabled: false, client_id: '', client_secret: '' },
    })),
});

const submit = () => {
    form.put('/superadmin/settings/login', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="localize('Login Settings', 'إعدادات تسجيل الدخول')" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Login Settings', 'إعدادات تسجيل الدخول') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Configure authenticators including Google and Apple Socialite credentials.', 'اضبط وسائل التوثيق بما في ذلك بيانات Google و Apple عبر Socialite.') }}
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                </Button>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Social Login Integrations', 'تكاملات تسجيل الدخول الاجتماعي') }}</CardTitle>
                        <CardDescription>
                            {{ localize('Configure dynamic provider credentials for tenant clients. Leave the Client Secret fields blank to retain the currently saved values.', 'اضبط بيانات مزودي تسجيل الدخول لعملاء المستأجرين. اترك حقول السر فارغة للاحتفاظ بالقيم المحفوظة الحالية.') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="space-y-4 rounded-md border p-4">
                            <h3 class="text-sm font-semibold">{{ localize('Google Auth', 'تسجيل دخول Google') }}</h3>

                            <label class="flex items-center gap-3">
                                <input v-model="form.social_login.google.enabled" type="checkbox" class="h-4 w-4" />
                                <span class="text-sm font-medium">{{ localize('Enable Google Login', 'تفعيل تسجيل الدخول عبر Google') }}</span>
                            </label>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="google_client_id">{{ localize('Client ID', 'معرّف العميل') }}</Label>
                                    <Input id="google_client_id" v-model="form.social_login.google.client_id" />
                                    <p v-if="form.errors['social_login.google.client_id']" class="text-sm text-red-600">
                                        {{ form.errors['social_login.google.client_id'] }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="google_client_secret">{{ localize('Client Secret', 'سر العميل') }}</Label>
                                    <Input id="google_client_secret" v-model="form.social_login.google.client_secret" type="password" />
                                    <p v-if="form.errors['social_login.google.client_secret']" class="text-sm text-red-600">
                                        {{ form.errors['social_login.google.client_secret'] }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 rounded-md border p-4">
                            <h3 class="text-sm font-semibold">{{ localize('Apple Auth', 'تسجيل دخول Apple') }}</h3>

                            <label class="flex items-center gap-3">
                                <input v-model="form.social_login.apple.enabled" type="checkbox" class="h-4 w-4" />
                                <span class="text-sm font-medium">{{ localize('Enable Apple Login', 'تفعيل تسجيل الدخول عبر Apple') }}</span>
                            </label>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="apple_client_id">{{ localize('Client ID', 'معرّف العميل') }}</Label>
                                    <Input id="apple_client_id" v-model="form.social_login.apple.client_id" />
                                    <p v-if="form.errors['social_login.apple.client_id']" class="text-sm text-red-600">
                                        {{ form.errors['social_login.apple.client_id'] }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="apple_client_secret">{{ localize('Client Secret / Private Key', 'سر العميل / المفتاح الخاص') }}</Label>
                                    <Input id="apple_client_secret" v-model="form.social_login.apple.client_secret" type="password" />
                                    <p v-if="form.errors['social_login.apple.client_secret']" class="text-sm text-red-600">
                                        {{ form.errors['social_login.apple.client_secret'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                    </Button>
                </div>
            </form>
        </main>
    </SuperAdminLayout>
</template>
