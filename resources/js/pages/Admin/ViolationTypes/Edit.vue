<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    violationType: {
        id: number;
        name: string;
        description: string | null;
        is_active: boolean;
        sort_order: number;
    } | null;
    indexUrl: string;
    submitUrl: string;
    method: 'post' | 'put';
}>();

const isEdit = computed(() => !!props.violationType);
const { t } = useTrans();

const form = useForm({
    name: props.violationType?.name ?? '',
    description: props.violationType?.description ?? '',
    is_active: props.violationType?.is_active ?? true,
    sort_order: props.violationType?.sort_order ?? 0,
});

function submit() {
    if (props.method === 'put') {
        form.put(props.submitUrl, { preserveScroll: true });
        return;
    }

    form.post(props.submitUrl, { preserveScroll: true });
}
</script>

<template>
    <Head
        :title="
            isEdit
                ? t('dashboard.admin.violation_types.edit.head_title_edit')
                : t('dashboard.admin.violation_types.edit.head_title_create')
        "
    />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{
                        isEdit
                            ? t('dashboard.admin.violation_types.edit.title_edit')
                            : t('dashboard.admin.violation_types.edit.title_create')
                    }}
                </h1>
                <Link :href="indexUrl">
                    <Button variant="outline">{{ t('dashboard.admin.common.back') }}</Button>
                </Link>
            </div>

            <div class="max-w-3xl">
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="name">{{ t('dashboard.admin.violation_types.edit.fields.name') }}</Label>
                        <Input id="name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="description">{{ t('dashboard.admin.violation_types.edit.fields.description') }}</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="space-y-2">
                        <Label for="sort_order">{{ t('dashboard.admin.violation_types.edit.fields.sort_order') }}</Label>
                        <Input id="sort_order" v-model="form.sort_order" min="0" step="1" type="number" />
                        <InputError :message="form.errors.sort_order" />
                    </div>

                    <label class="flex items-center gap-2">
                        <input v-model="form.is_active" class="h-4 w-4" type="checkbox" />
                        <span class="text-sm font-medium">{{ t('dashboard.admin.violation_types.edit.fields.active') }}</span>
                    </label>
                    <InputError :message="form.errors.is_active" />

                    <div class="flex items-center gap-3">
                        <Button :disabled="form.processing" type="submit">
                            {{
                                form.processing
                                    ? t('dashboard.admin.common.saving')
                                    : isEdit
                                        ? t('dashboard.admin.common.save_changes')
                                        : t('dashboard.admin.violation_types.edit.create_type')
                            }}
                        </Button>
                        <Link :href="indexUrl">
                            <Button type="button" variant="outline">{{ t('dashboard.admin.common.cancel') }}</Button>
                        </Link>
                    </div>
                </form>
            </div>
        </main>
    </AdminLayout>
</template>
