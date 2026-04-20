<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/composables/useTrans';

interface Option {
    value: string;
    label: string;
    disabled?: boolean;
}

const props = withDefaults(
    defineProps<{
        modelValue: string | number | null | undefined;
        options: Option[];
        placeholder?: string;
        searchPlaceholder?: string;
        emptyText?: string;
        disabled?: boolean;
        clearable?: boolean;
    }>(),
    {
        placeholder: 'Select option',
        searchPlaceholder: 'Search...',
        emptyText: 'No results found.',
        disabled: false,
        clearable: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    blur: [];
}>();

const { direction } = useTrans();
const menuOpen = ref(false);
const search = ref('');
const isRtl = computed(() => direction.value === 'rtl');

const normalizedValue = computed(() => (props.modelValue === null || props.modelValue === undefined ? '' : String(props.modelValue)));

const selectedOption = computed(() => props.options.find((option) => option.value === normalizedValue.value) ?? null);

const filteredOptions = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) {
        return props.options;
    }

    return props.options.filter((option) => option.label.toLowerCase().includes(term));
});

watch(
    selectedOption,
    (option) => {
        if (!menuOpen.value) {
            search.value = option?.label ?? '';
        }
    },
    { immediate: true },
);

function openMenu() {
    if (props.disabled) {
        return;
    }

    menuOpen.value = true;
    search.value = selectedOption.value?.label ?? '';
}

function selectOption(option: Option) {
    if (option.disabled) {
        return;
    }

    emit('update:modelValue', option.value);
    search.value = option.label;
    menuOpen.value = false;
}

function clearSelection() {
    emit('update:modelValue', '');
    search.value = '';
    menuOpen.value = false;
}

function handleInput() {
    menuOpen.value = true;
}

function handleBlur() {
    window.setTimeout(() => {
        menuOpen.value = false;
        search.value = selectedOption.value?.label ?? '';
        emit('blur');
    }, 150);
}
</script>

<template>
    <div class="relative">
        <Input
            :model-value="menuOpen ? search : selectedOption?.label ?? ''"
            :placeholder="placeholder"
            :disabled="disabled"
            autocomplete="off"
            @focus="openMenu"
            @input="search = String(($event.target as HTMLInputElement).value); handleInput()"
            @blur="handleBlur"
        />

        <button
            v-if="clearable && normalizedValue && !disabled"
            type="button"
            class="absolute right-8 top-1/2 -translate-y-1/2 text-xs text-muted-foreground hover:text-foreground"
            @mousedown.prevent
            @click="clearSelection"
        >
            Clear
        </button>

        <button
            type="button"
            class="absolute top-1/2 -translate-y-1/2 text-muted-foreground"
            :class="isRtl ? 'left-3' : 'right-3'"
            :disabled="disabled"
            @mousedown.prevent
            @click="menuOpen = !menuOpen"
        >
            <span class="text-xs">▼</span>
        </button>

        <div
            v-if="menuOpen && !disabled"
            class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-background shadow-lg"
        >
            <div v-if="filteredOptions.length === 0" class="px-3 py-2 text-sm text-muted-foreground">
                {{ emptyText }}
            </div>

            <button
                v-for="option in filteredOptions"
                :key="option.value"
                type="button"
                class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="option.disabled"
                @mousedown.prevent="selectOption(option)"
            >
                <span>{{ option.label }}</span>
                <span v-if="normalizedValue === option.value" class="text-xs text-muted-foreground">✓</span>
            </button>
        </div>
    </div>
</template>
