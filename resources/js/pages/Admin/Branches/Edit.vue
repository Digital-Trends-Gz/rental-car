<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { index, store, update } from '@/routes/admin/branches';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { City } from 'country-state-city';
import { computed, ref, watch } from 'vue';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    branch: any | null;
    showroomFiles?: Array<{ id: number; url: string }>;
    countries: Array<{ value: string; label: string }>;
}>();

const { t } = useTrans();
const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);

const isEdit = computed(() => !!props.branch);

// Initialize form with default values
const form = useForm({
    name: props.branch?.name ?? '',
    country: props.branch?.country ?? '',
    city: props.branch?.city ?? '',
    street_name: props.branch?.street_name ?? '',
    street_number: props.branch?.street_number ?? '',
    building_number: props.branch?.building_number ?? '',
    office_number: props.branch?.office_number ?? '',
    post_code: props.branch?.post_code ?? '',
    google_map_url: props.branch?.google_map_url ?? '',
    phone_1: props.branch?.phone_1 ?? '',
    phone_2: props.branch?.phone_2 ?? '',
    whatsapp: props.branch?.whatsapp ?? '',
    cr_number: props.branch?.cr_number ?? '',
    manager_name: props.branch?.manager_name ?? '',
    manager_civil_number: props.branch?.manager_civil_number ?? '',
    email: props.branch?.email ?? '',
    showroom_temp_folders: [] as string[],
    showroom_removed_files: [] as number[],
});

const availableCities = computed(() => {
    const normalizedCountry = String(form.country || '').trim().toUpperCase();

    if (!normalizedCountry) {
        return [] as Array<{ value: string; label: string }>;
    }

    const uniqueCities = new Map<string, { value: string; label: string }>();

    for (const city of City.getCitiesOfCountry(normalizedCountry) ?? []) {
        const name = String(city.name || '').trim();

        if (!name) {
            continue;
        }

        const key = name.toLocaleLowerCase();

        if (!uniqueCities.has(key)) {
            uniqueCities.set(key, {
                value: name,
                label: name,
            });
        }
    }

    const options = Array.from(uniqueCities.values()).sort((left, right) =>
        left.label.localeCompare(right.label, undefined, { sensitivity: 'base' }),
    );

    if (form.city && !options.some((option) => option.value === form.city)) {
        return [...options, { value: form.city, label: form.city }];
    }

    return options;
});

const hasCityOptions = computed(() => availableCities.value.length > 0);

const showroomUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const showroomTempFolders = ref<string[]>([]);
const showroomRemovedFiles = ref<number[]>([]);

watch(
    showroomTempFolders,
    (value) => {
        form.showroom_temp_folders = [...value];
    },
    { deep: true },
);

watch(
    () => form.country,
    () => {
        const options = availableCities.value;
        if (form.city && !options.some((option) => option.value === form.city)) {
            form.city = '';
        }
    },
);

function handleShowroomFileRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId !== undefined) {
        showroomRemovedFiles.value.push(data.fileId);
        form.showroom_removed_files = [...showroomRemovedFiles.value];
    }
}

function submit() {
    if (!subdomain.value) return;
    
    if (isEdit.value) {
        form.put(update([subdomain.value, props.branch.id]).url);
    } else {
        form.post(store(subdomain.value).url, {
            onSuccess: () => {
                form.reset();
                showroomTempFolders.value = [];
                form.showroom_temp_folders = [];
                form.showroom_removed_files = [];
                showroomUploadRef.value?.resetFiles();
            },
        });
    }
}
</script>

<template>
    <Head :title="isEdit ? t('dashboard.admin.branches.edit_branch') : t('dashboard.admin.branches.new_branch')" />
    <AdminLayout>
        <!-- Main -->
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{ isEdit ? t('dashboard.admin.branches.edit_branch') : t('dashboard.admin.branches.new_branch') }}
                </h1>
                <Link v-if="subdomain" :href="index(subdomain).url">
                    <Button variant="outline">{{ t('dashboard.admin.common.back') }}</Button>
                </Link>
            </div>

            <div class="max-w-5xl">
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <!-- Name -->
                        <div>
                            <Label for="name">{{ t('dashboard.admin.branches.form.branch_name') }}</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                :placeholder="t('dashboard.admin.branches.form.branch_name_placeholder')"
                                required
                            />
                            <InputError :message="form.errors.name" class="mt-1" />
                        </div>

                        <div>
                            <Label for="country">{{ t('dashboard.admin.branches.form.country') }}</Label>
                            <SearchableSelect
                                v-model="form.country"
                                :options="countries"
                                :placeholder="t('dashboard.admin.branches.form.select_country')"
                                :search-placeholder="t('dashboard.admin.branches.form.select_country')"
                            />
                            <InputError :message="form.errors.country" class="mt-1" />
                        </div>

                        <div>
                            <Label for="city">{{ t('dashboard.admin.branches.form.city') }}</Label>
                            <select
                                id="city"
                                v-model="form.city"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                                :disabled="!form.country || !hasCityOptions"
                            >
                                <option value="">
                                    {{
                                        !form.country
                                            ? t('dashboard.admin.branches.form.select_country_first')
                                            : hasCityOptions
                                              ? t('dashboard.admin.branches.form.select_city')
                                              : t('dashboard.admin.branches.form.no_cities_available')
                                    }}
                                </option>
                                <option v-for="city in availableCities" :key="city.value" :value="city.value">
                                    {{ city.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.city" class="mt-1" />
                            <p v-if="form.country && !hasCityOptions" class="mt-1 text-xs text-muted-foreground">
                                {{ t('dashboard.admin.branches.form.no_city_options_found') }}
                            </p>
                        </div>

                        <div>
                            <Label for="street_name">{{ t('dashboard.admin.branches.form.street_name') }}</Label>
                            <Input
                                id="street_name"
                                v-model="form.street_name"
                                :placeholder="t('dashboard.admin.branches.form.street_name_placeholder')"
                            />
                            <InputError :message="form.errors.street_name" class="mt-1" />
                        </div>

                        <div>
                            <Label for="street_number">{{ t('dashboard.admin.branches.form.street_number') }}</Label>
                            <Input id="street_number" v-model="form.street_number" :placeholder="t('dashboard.admin.branches.form.street_number_placeholder')" />
                            <InputError :message="form.errors.street_number" class="mt-1" />
                        </div>

                        <div>
                            <Label for="building_number">{{ t('dashboard.admin.branches.form.building_number') }}</Label>
                            <Input id="building_number" v-model="form.building_number" :placeholder="t('dashboard.admin.branches.form.building_number_placeholder')" />
                            <InputError :message="form.errors.building_number" class="mt-1" />
                        </div>

                        <div>
                            <Label for="office_number">{{ t('dashboard.admin.branches.form.office_number') }}</Label>
                            <Input id="office_number" v-model="form.office_number" :placeholder="t('dashboard.admin.branches.form.office_number_placeholder')" />
                            <InputError :message="form.errors.office_number" class="mt-1" />
                        </div>

                        <div>
                            <Label for="post_code">{{ t('dashboard.admin.branches.form.post_code') }}</Label>
                            <Input id="post_code" v-model="form.post_code" :placeholder="t('dashboard.admin.branches.form.post_code_placeholder')" />
                            <InputError :message="form.errors.post_code" class="mt-1" />
                        </div>

                        <div>
                            <Label for="google_map_url">{{ t('dashboard.admin.branches.form.google_map_url') }}</Label>
                            <Input id="google_map_url" v-model="form.google_map_url" dir="ltr" class="text-left" :placeholder="t('dashboard.admin.branches.form.google_map_url_placeholder')" />
                            <InputError :message="form.errors.google_map_url" class="mt-1" />
                        </div>

                        <div>
                            <Label for="phone_1">{{ t('dashboard.admin.branches.form.phone_1') }}</Label>
                            <Input id="phone_1" v-model="form.phone_1" dir="ltr" class="text-left" :placeholder="t('dashboard.admin.branches.form.phone_placeholder')" />
                            <InputError :message="form.errors.phone_1" class="mt-1" />
                        </div>

                        <div>
                            <Label for="phone_2">{{ t('dashboard.admin.branches.form.phone_2') }}</Label>
                            <Input id="phone_2" v-model="form.phone_2" dir="ltr" class="text-left" :placeholder="t('dashboard.admin.branches.form.phone_placeholder')" />
                            <InputError :message="form.errors.phone_2" class="mt-1" />
                        </div>

                        <div>
                            <Label for="whatsapp">{{ t('dashboard.admin.branches.form.whatsapp') }}</Label>
                            <Input id="whatsapp" v-model="form.whatsapp" dir="ltr" class="text-left" :placeholder="t('dashboard.admin.branches.form.phone_placeholder')" />
                            <InputError :message="form.errors.whatsapp" class="mt-1" />
                        </div>

                        <div>
                            <Label for="cr_number">{{ t('dashboard.admin.branches.form.cr_number') }}</Label>
                            <Input id="cr_number" v-model="form.cr_number" :placeholder="t('dashboard.admin.branches.form.cr_number_placeholder')" />
                            <InputError :message="form.errors.cr_number" class="mt-1" />
                        </div>

                        <div>
                            <Label for="manager_name">{{ t('dashboard.admin.branches.form.manager_name') }}</Label>
                            <Input id="manager_name" v-model="form.manager_name" :placeholder="t('dashboard.admin.branches.form.manager_name_placeholder')" />
                            <InputError :message="form.errors.manager_name" class="mt-1" />
                        </div>

                        <div>
                            <Label for="manager_civil_number">{{ t('dashboard.admin.branches.form.manager_civil_number') }}</Label>
                            <Input id="manager_civil_number" v-model="form.manager_civil_number" :placeholder="t('dashboard.admin.branches.form.manager_civil_number_placeholder')" />
                            <InputError :message="form.errors.manager_civil_number" class="mt-1" />
                        </div>

                        <div>
                            <Label for="email">{{ t('dashboard.admin.branches.form.email') }}</Label>
                            <Input id="email" v-model="form.email" type="email" :placeholder="t('dashboard.admin.branches.form.email_placeholder')" />
                            <InputError :message="form.errors.email" class="mt-1" />
                        </div>

                        <div class="md:col-span-2">
                            <Label class="mb-2 block">{{ t('dashboard.admin.branches.form.showroom_image') }}</Label>
                            <FileUpload
                                ref="showroomUploadRef"
                                v-model="showroomTempFolders"
                                :initial-files="showroomFiles || []"
                                :allow-multiple="false"
                                :max-files="1"
                                collection="showroom"
                                theme="light"
                                width="100%"
                                @file-removed="handleShowroomFileRemoved"
                            />
                            <InputError :message="form.errors.showroom_temp_folders" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <Button type="submit" :disabled="form.processing">
                            {{
                                form.processing
                                    ? isEdit
                                        ? t('dashboard.admin.common.saving')
                                        : t('dashboard.admin.common.creating')
                                    : isEdit
                                      ? t('dashboard.admin.common.save_changes')
                                      : t('dashboard.admin.branches.new_branch')
                            }}
                        </Button>
                        <Link v-if="subdomain" :href="index(subdomain).url">
                            <Button type="button" variant="outline">{{ t('dashboard.admin.common.cancel') }}</Button>
                        </Link>
                    </div>
                </form>
            </div>
        </main>
    </AdminLayout>
</template>
