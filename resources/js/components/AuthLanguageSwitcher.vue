<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTrans } from '@/composables/useTrans';
import { usePage } from '@inertiajs/vue3';
import { ChevronDown, Languages } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<any>();
const { locale, t } = useTrans();

const availableLocales = computed<string[]>(() =>
    Array.isArray(page.props?.available_locales) && page.props.available_locales.length
        ? page.props.available_locales
        : ['ar', 'en']
);

const localeNames: Record<string, string> = {
    ar: t('language.ar'),
    en: t('language.en'),
    ur: t('language.ur'),
};

const currentLocaleName = computed(
    () => localeNames[locale.value] || String(locale.value).toUpperCase(),
);

const normalizedRedirectPath = computed(() => {
    const currentPath = String(page.url || '/');
    const escapedLocales = availableLocales.value.map((item) => item.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const localeRegex = new RegExp(`^\\/(${escapedLocales.join('|')})(?=\\/|$)`);
    const strippedPath = currentPath.replace(localeRegex, '') || '/';

    return strippedPath.startsWith('/') ? strippedPath : `/${strippedPath}`;
});

const localeSwitcherUrl = (targetLocale: string) => {
    const path = `/locale/${targetLocale}?redirect=${encodeURIComponent(normalizedRedirectPath.value)}`;
    const origin = typeof window !== 'undefined' ? window.location.origin : '';

    return `${origin}${path}`;
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="outline"
                size="sm"
                class="flex h-9 items-center gap-2 rounded-full border border-gray-200 bg-white/80 px-4 text-xs font-semibold text-gray-700 shadow-sm backdrop-blur transition hover:bg-white hover:text-gray-900"
            >
                <Languages class="h-3.5 w-3.5 text-gray-500" />
                <span>{{ currentLocaleName }}</span>
                <ChevronDown class="h-3.5 w-3.5 text-gray-400" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            class="min-w-[120px] rounded-xl border border-gray-100 bg-white p-1 shadow-lg"
        >
            <DropdownMenuItem
                v-for="localeCode in availableLocales"
                :key="localeCode"
                as-child
                class="cursor-pointer rounded-lg px-3 py-2 text-xs font-medium transition-colors hover:bg-gray-50"
            >
                <a
                    :href="localeSwitcherUrl(localeCode)"
                    class="flex w-full items-center justify-between gap-2"
                >
                    <span :class="{ 'font-semibold text-blue-600': locale === localeCode }">
                        {{ localeNames[localeCode] || localeCode.toUpperCase() }}
                    </span>
                    <span
                        v-if="locale === localeCode"
                        class="h-1.5 w-1.5 rounded-full bg-blue-600"
                    ></span>
                </a>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
