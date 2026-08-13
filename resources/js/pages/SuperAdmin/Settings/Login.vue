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

interface CaptchaSettings {
    enabled: boolean;
    provider: 'turnstile';
    site_key: string;
    secret_key: string;
    forms: {
        login: boolean;
        register: boolean;
    };
}

const props = defineProps<{
    socialLoginSettings: SocialLoginSettings;
    socialLoginRedirectUris: {
        google: string;
        apple: string;
    };
    captchaSettings: CaptchaSettings;
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const form = useForm<{
    social_login: SocialLoginSettings;
    captcha: CaptchaSettings;
}>({
    social_login: JSON.parse(JSON.stringify(props.socialLoginSettings || {
        google: { enabled: false, client_id: '', client_secret: '' },
        apple: { enabled: false, client_id: '', client_secret: '' },
    })),
    captcha: JSON.parse(JSON.stringify(props.captchaSettings || {
        enabled: false,
        provider: 'turnstile',
        site_key: '',
        secret_key: '',
        forms: { login: false, register: false },
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
                            <div class="space-y-2">
                                <Label for="google_redirect_uri">{{ localize('Authorized redirect URI', 'رابط إعادة التوجيه المعتمد') }}</Label>
                                <Input id="google_redirect_uri" :model-value="props.socialLoginRedirectUris.google" readonly dir="ltr" class="font-mono text-sm" />
                                <p class="text-xs text-muted-foreground">
                                    {{ localize('Add this exact URL in Google Cloud Console.', 'أضف هذا الرابط كما هو داخل Google Cloud Console.') }}
                                </p>
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
                            <div class="space-y-2">
                                <Label for="apple_redirect_uri">{{ localize('Authorized redirect URI', 'رابط إعادة التوجيه المعتمد') }}</Label>
                                <Input id="apple_redirect_uri" :model-value="props.socialLoginRedirectUris.apple" readonly dir="ltr" class="font-mono text-sm" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('CAPTCHA Protection', 'حماية CAPTCHA') }}</CardTitle>
                        <CardDescription>
                            {{ localize('Enable Cloudflare Turnstile on tenant authentication forms.', 'فعّل Cloudflare Turnstile على نماذج تسجيل الدخول والتسجيل للمستأجرين.') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="space-y-4 rounded-md border p-4">
                            <h3 class="text-sm font-semibold">{{ localize('Cloudflare Turnstile', 'Cloudflare Turnstile') }}</h3>

                            <label class="flex items-center gap-3">
                                <input v-model="form.captcha.enabled" type="checkbox" class="h-4 w-4" />
                                <span class="text-sm font-medium">{{ localize('Enable CAPTCHA', 'تفعيل CAPTCHA') }}</span>
                            </label>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="captcha_site_key">{{ localize('Site Key', 'مفتاح الموقع') }}</Label>
                                    <Input id="captcha_site_key" v-model="form.captcha.site_key" />
                                    <p v-if="form.errors['captcha.site_key']" class="text-sm text-red-600">
                                        {{ form.errors['captcha.site_key'] }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="captcha_secret_key">{{ localize('Secret Key', 'المفتاح السري') }}</Label>
                                    <Input id="captcha_secret_key" v-model="form.captcha.secret_key" type="password" />
                                    <p v-if="form.errors['captcha.secret_key']" class="text-sm text-red-600">
                                        {{ form.errors['captcha.secret_key'] }}
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <p class="text-sm font-medium">{{ localize('Show CAPTCHA on', 'إظهار CAPTCHA في') }}</p>
                                <div class="flex flex-wrap gap-6">
                                    <label class="flex items-center gap-3">
                                        <input v-model="form.captcha.forms.login" type="checkbox" class="h-4 w-4" />
                                        <span class="text-sm">{{ localize('Tenant Login', 'تسجيل دخول المستأجر') }}</span>
                                    </label>
                                    <label class="flex items-center gap-3">
                                        <input v-model="form.captcha.forms.register" type="checkbox" class="h-4 w-4" />
                                        <span class="text-sm">{{ localize('Tenant Register', 'تسجيل عميل المستأجر') }}</span>
                                    </label>
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
