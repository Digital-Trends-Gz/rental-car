<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { City } from 'country-state-city';
import { computed } from 'vue';

const props = defineProps<{
    maintenanceType: {
        id: number;
        name: string;
        description: string | null;
        is_active: boolean;
        sort_order: number;
    } | null;
    workshops: Array<{
        id?: number;
        name: string;
        phone: string;
        rate: string | number;
        country?: string | null;
        city?: string | null;
        street_name?: string | null;
        street_number?: string | null;
        building_number?: string | null;
        office_number?: string | null;
        post_code?: string | null;
        google_map_url?: string | null;
        frontImageFiles?: Array<{ id: number; url: string }>;
    }>;
    countries: Array<{ value: string; label: string }>;
    indexUrl: string;
    submitUrl: string;
    method: 'post' | 'put';
}>();

const isEdit = computed(() => !!props.maintenanceType);

const form = useForm({
    name: props.maintenanceType?.name ?? '',
    description: props.maintenanceType?.description ?? '',
    is_active: props.maintenanceType?.is_active ?? true,
    sort_order: props.maintenanceType?.sort_order ?? 0,
    workshops: (props.workshops ?? []).map((workshop) => ({
        id: workshop.id ?? null,
        name: workshop.name ?? '',
        phone: workshop.phone ?? '',
        rate: workshop.rate ?? '',
        country: workshop.country ?? '',
        city: workshop.city ?? '',
        street_name: workshop.street_name ?? '',
        street_number: workshop.street_number ?? '',
        building_number: workshop.building_number ?? '',
        office_number: workshop.office_number ?? '',
        post_code: workshop.post_code ?? '',
        google_map_url: workshop.google_map_url ?? '',
        front_image_temp_folders: [] as string[],
        front_image_removed_file_ids: [] as number[],
        frontImageFiles: workshop.frontImageFiles ?? [],
    })),
});

function addWorkshop() {
    form.workshops.push({
        id: null,
        name: '',
        phone: '',
        rate: '',
        country: '',
        city: '',
        street_name: '',
        street_number: '',
        building_number: '',
        office_number: '',
        post_code: '',
        google_map_url: '',
        front_image_temp_folders: [],
        front_image_removed_file_ids: [],
        frontImageFiles: [],
    });
}

function removeWorkshop(index: number) {
    form.workshops.splice(index, 1);
}

function onWorkshopFrontImageRemoved(
    index: number,
    data: { type: string; fileId?: number },
) {
    const workshop = form.workshops[index];
    if (!workshop) return;

    if (data.type === 'existing' && data.fileId !== undefined && !workshop.front_image_removed_file_ids.includes(data.fileId)) {
        workshop.front_image_removed_file_ids.push(data.fileId);
    }
}

function availableCities(countryCode: string | null | undefined) {
    const normalizedCountry = String(countryCode || '').trim().toUpperCase();

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

    return Array.from(uniqueCities.values()).sort((left, right) =>
        left.label.localeCompare(right.label, undefined, { sensitivity: 'base' }),
    );
}

function onWorkshopCountryChanged(index: number) {
    const workshop = form.workshops[index];
    if (!workshop) return;

    const options = availableCities(workshop.country);
    if (workshop.city && !options.some((option) => option.value === workshop.city)) {
        workshop.city = '';
    }
}

function submit() {
    if (props.method === 'put') {
        form.put(props.submitUrl, { preserveScroll: true });
        return;
    }

    form.post(props.submitUrl, { preserveScroll: true });
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Maintenance Type' : 'Create Maintenance Type'" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{ isEdit ? 'Edit Maintenance Type' : 'Create Maintenance Type' }}
                </h1>
                <Link :href="indexUrl">
                    <Button variant="outline">Back</Button>
                </Link>
            </div>

            <div class="max-w-5xl">
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="space-y-2">
                        <Label for="sort_order">Sort Order</Label>
                        <Input id="sort_order" v-model="form.sort_order" min="0" step="1" type="number" />
                        <InputError :message="form.errors.sort_order" />
                    </div>

                    <label class="flex items-center gap-2">
                        <input v-model="form.is_active" class="h-4 w-4" type="checkbox" />
                        <span class="text-sm font-medium">Active</span>
                    </label>
                    <InputError :message="form.errors.is_active" />

                    <div class="space-y-4 rounded-lg border p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold">Workshops</h2>
                                <p class="text-sm text-muted-foreground">
                                    Add one or more workshops for this maintenance type.
                                </p>
                            </div>
                            <Button type="button" variant="outline" @click="addWorkshop">Add Workshop</Button>
                        </div>

                        <div v-if="form.workshops.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                            No workshops added yet.
                        </div>

                        <div
                            v-for="(workshop, index) in form.workshops"
                            :key="workshop.id ?? `new-${index}`"
                            class="space-y-4 rounded-lg border p-4"
                        >
                            <div class="flex items-center justify-between gap-4">
                                <h3 class="font-medium">Workshop {{ index + 1 }}</h3>
                                <Button type="button" variant="destructive" @click="removeWorkshop(index)">Remove</Button>
                            </div>

                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="space-y-2">
                                    <Label :for="`workshop-name-${index}`">Name</Label>
                                    <Input :id="`workshop-name-${index}`" v-model="workshop.name" />
                                    <InputError :message="form.errors[`workshops.${index}.name`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`workshop-phone-${index}`">Phone</Label>
                                    <Input :id="`workshop-phone-${index}`" v-model="workshop.phone" />
                                    <InputError :message="form.errors[`workshops.${index}.phone`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`workshop-rate-${index}`">Rate</Label>
                                    <Input :id="`workshop-rate-${index}`" v-model="workshop.rate" max="5" min="0" step="0.1" type="number" />
                                    <InputError :message="form.errors[`workshops.${index}.rate`]" />
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label :for="`workshop-country-${index}`">Country</Label>
                                    <select
                                        :id="`workshop-country-${index}`"
                                        v-model="workshop.country"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                                        @change="onWorkshopCountryChanged(index)"
                                    >
                                        <option value="">Select country</option>
                                        <option v-for="country in countries" :key="country.value" :value="country.value">
                                            {{ country.label }}
                                        </option>
                                    </select>
                                    <InputError :message="form.errors[`workshops.${index}.country`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`workshop-city-${index}`">City</Label>
                                    <select
                                        :id="`workshop-city-${index}`"
                                        v-model="workshop.city"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                                        :disabled="!workshop.country"
                                    >
                                        <option value="">{{ workshop.country ? 'Select city' : 'Select country first' }}</option>
                                        <option
                                            v-for="city in availableCities(workshop.country)"
                                            :key="city.value"
                                            :value="city.value"
                                        >
                                            {{ city.label }}
                                        </option>
                                    </select>
                                    <InputError :message="form.errors[`workshops.${index}.city`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`workshop-street-name-${index}`">Street Name</Label>
                                    <Input :id="`workshop-street-name-${index}`" v-model="workshop.street_name" />
                                    <InputError :message="form.errors[`workshops.${index}.street_name`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`workshop-street-number-${index}`">Street Number</Label>
                                    <Input :id="`workshop-street-number-${index}`" v-model="workshop.street_number" />
                                    <InputError :message="form.errors[`workshops.${index}.street_number`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`workshop-building-number-${index}`">Building Number</Label>
                                    <Input :id="`workshop-building-number-${index}`" v-model="workshop.building_number" />
                                    <InputError :message="form.errors[`workshops.${index}.building_number`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`workshop-office-number-${index}`">Office Number</Label>
                                    <Input :id="`workshop-office-number-${index}`" v-model="workshop.office_number" />
                                    <InputError :message="form.errors[`workshops.${index}.office_number`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`workshop-post-code-${index}`">Post Code</Label>
                                    <Input :id="`workshop-post-code-${index}`" v-model="workshop.post_code" />
                                    <InputError :message="form.errors[`workshops.${index}.post_code`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="`workshop-google-map-${index}`">Google Map URL</Label>
                                    <Input :id="`workshop-google-map-${index}`" v-model="workshop.google_map_url" />
                                    <InputError :message="form.errors[`workshops.${index}.google_map_url`]" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label>Front Image (Optional)</Label>
                                <FileUpload
                                    v-model="workshop.front_image_temp_folders"
                                    :initial-files="workshop.frontImageFiles || []"
                                    :allow-multiple="false"
                                    :max-files="1"
                                    collection="maintenance_workshop_front"
                                    theme="light"
                                    width="100%"
                                    @file-removed="(data: { type: string; fileId?: number }) => onWorkshopFrontImageRemoved(index, data)"
                                />
                                <InputError :message="form.errors[`workshops.${index}.front_image_temp_folders`]" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <Button :disabled="form.processing" type="submit">
                            {{ form.processing ? 'Saving...' : isEdit ? 'Save Changes' : 'Create Type' }}
                        </Button>
                        <Link :href="indexUrl">
                            <Button type="button" variant="outline">Cancel</Button>
                        </Link>
                    </div>
                </form>
            </div>
        </main>
    </AdminLayout>
</template>
