<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed } from 'vue';

interface Redirect {
    id: string;
    fromPath: string;
    toPath: string;
    statusCode: 301 | 302 | 307 | 308;
    isPermanent: boolean;
    isActive: boolean;
}

interface RedirectSettingsPayload {
    items: Redirect[];
}

const props = defineProps<{
    modelValue: RedirectSettingsPayload;
}>();
const emit = defineEmits<{
    (event: 'update:modelValue', value: RedirectSettingsPayload): void;
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const redirects = computed<Redirect[]>({
    get: () => {
        if (Array.isArray(props.modelValue?.items) && props.modelValue.items.length > 0) {
            return props.modelValue.items;
        }

        return [];
    },
    set: (value) => emit('update:modelValue', { items: value }),
});

function addRedirect() {
    redirects.value = [...redirects.value, {
        id: Math.random().toString(36),
        fromPath: '',
        toPath: '',
        statusCode: 301,
        isPermanent: true,
        isActive: true,
    }];
}

function removeRedirect(index: number) {
    redirects.value = redirects.value.filter((_, idx) => idx !== index);
}

function toggleRedirect(index: number) {
    redirects.value = redirects.value.map((item, idx) => idx === index ? { ...item, isActive: !item.isActive } : item);
}

const statusCodeDescriptions: Record<301 | 302 | 307 | 308, string> = {
    301: localize('Moved Permanently (SEO Best)', 'تم النقل بشكل دائم (الأفضل لـ SEO)'),
    302: localize('Found (Temporary)', 'موجود (مؤقت)'),
    307: localize('Temporary Redirect', 'إعادة توجيه مؤقتة'),
    308: localize('Permanent Redirect', 'إعادة توجيه دائمة'),
};

const normalizePath = (value: string): string => {
    const trimmed = String(value || '').trim();
    if (trimmed === '') return '';
    const prefixed = trimmed.startsWith('/') ? trimmed : `/${trimmed}`;
    const squashed = prefixed.replace(/\/+/g, '/');
    const normalized = squashed.length > 1 ? squashed.replace(/\/+$/, '') : squashed;
    return normalized || '/';
};

const redirectValidation = computed(() => {
    const rowErrors: Record<string, string[]> = {};
    const active = redirects.value
        .map((item, idx) => ({ item, idx, from: normalizePath(item.fromPath), to: normalizePath(item.toPath) }))
        .filter((entry) => entry.item.isActive && entry.from !== '' && entry.to !== '');

    const fromMap = new Map<string, number>();

    for (const entry of active) {
        const key = entry.item.id || String(entry.idx);
        rowErrors[key] = rowErrors[key] || [];

        if (entry.from === entry.to) {
            rowErrors[key].push(localize('Source and destination cannot be the same.', 'لا يمكن أن يكون مسار المصدر والوجهة متطابقين.'));
        }

        if (fromMap.has(entry.from)) {
            rowErrors[key].push(localize('Duplicate active source path detected.', 'تم اكتشاف مسار مصدر نشط مكرر.'));
        } else {
            fromMap.set(entry.from, entry.idx);
        }
    }

    for (const entry of active) {
        const reverse = active.find((candidate) => candidate.from === entry.to && candidate.to === entry.from);
        if (reverse) {
            const key = entry.item.id || String(entry.idx);
            rowErrors[key] = rowErrors[key] || [];
            rowErrors[key].push(localize('Two-way redirect loop detected.', 'تم اكتشاف حلقة إعادة توجيه ثنائية.'));
        }
    }

    const allErrors = Object.values(rowErrors).flat();

    return {
        rowErrors,
        totalErrors: allErrors.length,
    };
});
</script>

<template>
    <div class="space-y-6">
        <section class="rounded-lg border p-5 space-y-4">
            <div>
                <h2 class="text-lg font-semibold">{{ localize('Redirect Manager', 'مدير إعادات التوجيه') }}</h2>
                <p class="text-sm text-muted-foreground">
                    {{ localize('Manage URL redirects to maintain SEO value when moving pages.', 'أدر إعادات توجيه الروابط للحفاظ على قيمة SEO عند نقل الصفحات.') }}
                </p>
            </div>

            <!-- Redirects List -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium">{{ localize('Active Redirects', 'إعادات التوجيه النشطة') }}</h3>
                    <Button type="button" size="sm" @click="addRedirect">
                        {{ localize('+ Add Redirect', '+ إضافة إعادة توجيه') }}
                    </Button>
                </div>

                <div class="space-y-3">
                    <div v-for="(redirect, idx) in redirects" :key="redirect.id" class="rounded-lg border p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 space-y-3">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="space-y-1">
                                        <Label class="text-xs">{{ localize('From Path', 'من المسار') }}</Label>
                                        <Input
                                            v-model="redirect.fromPath"
                                            placeholder="/old-page"
                                            :disabled="!redirect.isActive"
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <Label class="text-xs">{{ localize('To Path', 'إلى المسار') }}</Label>
                                        <Input
                                            v-model="redirect.toPath"
                                            placeholder="/new-page"
                                            :disabled="!redirect.isActive"
                                        />
                                    </div>
                                </div>

                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="space-y-1">
                                        <Label class="text-xs">{{ localize('Status Code', 'رمز الحالة') }}</Label>
                                        <select
                                            v-model.number="redirect.statusCode"
                                            :disabled="!redirect.isActive"
                                            class="w-full rounded-md border border-input bg-background px-2 py-1.5 text-sm"
                                        >
                                            <option :value="301">301 - {{ statusCodeDescriptions[301] }}</option>
                                            <option :value="302">302 - {{ statusCodeDescriptions[302] }}</option>
                                            <option :value="307">307 - {{ statusCodeDescriptions[307] }}</option>
                                            <option :value="308">308 - {{ statusCodeDescriptions[308] }}</option>
                                        </select>
                                    </div>
                                    <div class="space-y-1">
                                        <Label class="text-xs">&nbsp;</Label>
                                        <div class="flex items-center gap-2 h-9">
                                            <div class="text-xs text-muted-foreground" :class="redirect.statusCode === 301 ? 'text-emerald-600 font-medium' : ''">
                                                {{ redirect.statusCode === 301 ? '✓ SEO-Safe' : '⚠ Check compatibility' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    :variant="redirect.isActive ? 'default' : 'outline'"
                                    @click="toggleRedirect(idx)"
                                >
                                    {{ redirect.isActive ? localize('Active', 'نشط') : localize('Inactive', 'غير نشط') }}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="destructive"
                                    @click="removeRedirect(idx)"
                                >
                                    {{ localize('Remove', 'حذف') }}
                                </Button>
                            </div>
                        </div>
                        <div
                            v-if="(redirectValidation.rowErrors[redirect.id] || []).length"
                            class="rounded-md border border-red-200 bg-red-50 p-2 text-xs text-red-700 space-y-1"
                        >
                            <div v-for="(message, errorIdx) in redirectValidation.rowErrors[redirect.id]" :key="`${redirect.id}-error-${errorIdx}`">
                                - {{ message }}
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="redirects.length === 0" class="rounded-lg border border-dashed p-4 text-center">
                    <p class="text-sm text-muted-foreground">
                        {{ localize('No redirects configured yet.', 'لم يتم تكوين أي إعادات توجيه حتى الآن.') }}
                    </p>
                </div>
                <div
                    v-else-if="redirectValidation.totalErrors > 0"
                    class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                >
                    {{ localize('Redirect validation found issues. Fix them before saving.', 'تحقق إعادة التوجيه اكتشف مشاكل. أصلحها قبل الحفظ.') }}
                </div>
            </div>

            <!-- Statistics -->
            <div class="rounded-lg border p-4 space-y-3">
                <h3 class="font-medium">{{ localize('Redirect Statistics', 'إحصائيات إعادات التوجيه') }}</h3>
                <div class="grid gap-3 md:grid-cols-3">
                    <div class="rounded bg-emerald-50 p-3">
                        <div class="text-2xl font-bold text-emerald-600">{{ redirects.filter((r) => r.isActive).length }}</div>
                        <div class="text-xs text-emerald-700">{{ localize('Active Redirects', 'إعادات توجيه نشطة') }}</div>
                    </div>
                    <div class="rounded bg-amber-50 p-3">
                        <div class="text-2xl font-bold text-amber-600">{{ redirects.filter((r) => r.statusCode === 301).length }}</div>
                        <div class="text-xs text-amber-700">{{ localize('Permanent (301/308)', 'دائمة (301/308)') }}</div>
                    </div>
                    <div class="rounded bg-blue-50 p-3">
                        <div class="text-2xl font-bold text-blue-600">{{ redirects.filter((r) => r.statusCode === 302).length }}</div>
                        <div class="text-xs text-blue-700">{{ localize('Temporary (302/307)', 'مؤقتة (302/307)') }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border bg-blue-50 p-3 space-y-2">
                <h4 class="text-sm font-medium text-blue-900">{{ localize('💡 Best Practices', '💡 أفضل الممارسات') }}</h4>
                <ul class="text-sm text-blue-700 space-y-1 list-disc pl-5">
                    <li>{{ localize('Use 301 (Permanent) for long-term page moves - preserves SEO value', 'استخدم 301 (دائم) لنقل الصفحات طويل الأجل - يحافظ على قيمة SEO') }}</li>
                    <li>{{ localize('Use 302/307 (Temporary) only for short-term redirects', 'استخدم 302/307 (مؤقت) فقط لإعادات التوجيه قصيرة الأجل') }}</li>
                    <li>{{ localize('Keep redirects active for at least 6-12 months', 'احتفظ بإعادات التوجيه نشطة لمدة 6-12 شهراً على الأقل') }}</li>
                    <li>{{ localize('Monitor redirect chains - avoid redirect loops', 'راقب سلاسل إعادات التوجيه - تجنب حلقات إعادة التوجيه') }}</li>
                </ul>
            </div>
        </section>
    </div>
</template>
