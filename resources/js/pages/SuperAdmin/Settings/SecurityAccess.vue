<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { useTrans } from '@/composables/useTrans';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

const props = defineProps<{
    settings: {
        superadmin_allowed_countries: string[];
        superadmin_allowed_ips: string;
        superadmin_blocked_ips: string;
        website_blocked_ips: string;
    };
    countries: Array<{
        iso2: string;
        name_en: string;
        name_ar: string;
        dial_code: string;
    }>;
    currentRequest: {
        ip: string | null;
        country: string | null;
    };
    actions: {
        update: string;
    };
}>();
const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const form = useForm({
    settings: { ...props.settings },
});

const selectedCountryToAdd = ref<string>('');

const availableCountries = computed(() =>
    props.countries.filter((country) => !form.settings.superadmin_allowed_countries.includes(country.iso2)),
);

const selectedCountries = computed(() =>
    form.settings.superadmin_allowed_countries
        .map((iso2) => props.countries.find((country) => country.iso2 === iso2))
        .filter(Boolean) as Array<{ iso2: string; name_en: string; name_ar: string; dial_code: string }>,
);

const addCountry = () => {
    if (!selectedCountryToAdd.value || form.settings.superadmin_allowed_countries.includes(selectedCountryToAdd.value)) {
        return;
    }

    form.settings.superadmin_allowed_countries.push(selectedCountryToAdd.value);
    form.settings.superadmin_allowed_countries.sort((a, b) => a.localeCompare(b));
    selectedCountryToAdd.value = '';
};

const removeCountry = (iso2: string) => {
    form.settings.superadmin_allowed_countries = form.settings.superadmin_allowed_countries.filter((item) => item !== iso2);
};

const submit = () => {
    form.put(props.actions.update, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="localize('Security Access', 'أمان الوصول')" />

    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Security Access', 'أمان الوصول') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Restrict Super Admin access by country and IP, and block abusive IPs from the website.', 'قيد وصول السوبر أدمن حسب الدولة وعنوان IP، واحظر عناوين IP المسيئة من الموقع.') }}
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ localize('Current Request', 'الطلب الحالي') }}</CardTitle>
                    <CardDescription>
                        {{ localize('Use this to avoid locking yourself out when adding allowlists.', 'استخدم هذه البيانات لتجنب حظر نفسك عند إضافة قوائم السماح.') }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-lg border p-4">
                        <div class="text-xs uppercase text-muted-foreground">{{ localize('Detected IP', 'عنوان IP المكتشف') }}</div>
                        <div class="mt-1 font-medium">{{ currentRequest.ip || localize('Unknown', 'غير معروف') }}</div>
                    </div>
                    <div class="rounded-lg border p-4">
                        <div class="text-xs uppercase text-muted-foreground">{{ localize('Detected Country', 'الدولة المكتشفة') }}</div>
                        <div class="mt-1 font-medium">{{ currentRequest.country || localize('Unknown', 'غير معروف') }}</div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Super Admin Access Rules', 'قواعد وصول السوبر أدمن') }}</CardTitle>
                        <CardDescription>
                            {{ localize('If an allowlist field is filled, anything not matching it will be denied.', 'إذا تم تعبئة أي حقل لقائمة السماح، فسيتم رفض أي شيء لا يطابقه.') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="superadmin_allowed_countries">{{ localize('Allowed Countries', 'الدول المسموح بها') }}</Label>
                            <div class="flex gap-2">
                                <Select v-model="selectedCountryToAdd">
                                    <SelectTrigger id="superadmin_allowed_countries" class="flex-1">
                                        <SelectValue :placeholder="localize('Select a country', 'اختر دولة')" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="country in availableCountries"
                                            :key="country.iso2"
                                            :value="country.iso2"
                                        >
                                            {{ country.name_en }} ({{ country.iso2 }})
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button type="button" variant="outline" :disabled="!selectedCountryToAdd" @click="addCountry">
                                    {{ localize('Add', 'إضافة') }}
                                </Button>
                            </div>
                            <div v-if="selectedCountries.length" class="flex flex-wrap gap-2 pt-2">
                                <div
                                    v-for="country in selectedCountries"
                                    :key="country.iso2"
                                    class="inline-flex items-center gap-2 rounded-full border bg-background px-3 py-1 text-sm"
                                >
                                    <span>{{ country.name_en }} ({{ country.iso2 }})</span>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="removeCountry(country.iso2)"
                                    >
                                        x
                                    </button>
                                </div>
                            </div>
                            <p v-else class="text-xs text-muted-foreground">
                                {{ localize('No country restrictions. Leave empty to allow all countries.', 'لا توجد قيود على الدول. اتركه فارغًا للسماح لجميع الدول.') }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Headers like ', 'يتم استخدام ترويسات مثل ') }}<code>CF-IPCountry</code>{{ localize(' when available.', ' عند توفرها.') }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="superadmin_allowed_ips">{{ localize('Allowed IPs / CIDR', 'عناوين IP / CIDR المسموح بها') }}</Label>
                            <Textarea
                                id="superadmin_allowed_ips"
                                v-model="form.settings.superadmin_allowed_ips"
                                rows="8"
                                placeholder="203.0.113.10&#10;198.51.100.0/24&#10;2001:db8::/32"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ localize('One IP or CIDR per line. Useful for office IPs or VPN ranges.', 'عنوان IP واحد أو CIDR في كل سطر. مفيد لعناوين المكتب أو نطاقات VPN.') }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="superadmin_blocked_ips">{{ localize('Blocked IPs / CIDR', 'عناوين IP / CIDR المحظورة') }}</Label>
                            <Textarea
                                id="superadmin_blocked_ips"
                                v-model="form.settings.superadmin_blocked_ips"
                                rows="8"
                                placeholder="192.0.2.15&#10;203.0.113.0/24"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ localize('These are blocked from all ', 'سيتم حظر هذه العناوين من جميع مسارات ') }}<code>/superadmin</code>{{ localize(' routes, including the login page.', ' بما في ذلك صفحة تسجيل الدخول.') }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ localize('Website-wide IP Blocklist', 'قائمة حظر IP على مستوى الموقع') }}</CardTitle>
                        <CardDescription>
                            {{ localize('These IPs are denied before the application continues, on all public and tenant routes.', 'يتم رفض هذه العناوين قبل متابعة التطبيق، في جميع المسارات العامة ومسارات المستأجرين.') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="website_blocked_ips">{{ localize('Blocked IPs / CIDR', 'عناوين IP / CIDR المحظورة') }}</Label>
                            <Textarea
                                id="website_blocked_ips"
                                v-model="form.settings.website_blocked_ips"
                                rows="12"
                                placeholder="198.51.100.7&#10;198.51.100.0/24"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Use this for abusive IPs. It applies to the whole website, not only Super Admin.', 'استخدمه لعناوين IP المسيئة. يطبق على الموقع كله وليس على السوبر أدمن فقط.') }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            {{ localize('Keep your current IP in the allowlist before tightening Super Admin rules. In local and testing environments, these restrictions are bypassed automatically.', 'أبقِ عنوان IP الحالي ضمن قائمة السماح قبل تشديد قواعد السوبر أدمن. في بيئات local وtesting يتم تجاوز هذه القيود تلقائيًا.') }}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </main>
    </SuperAdminLayout>
</template>
