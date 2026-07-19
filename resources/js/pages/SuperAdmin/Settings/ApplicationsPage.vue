<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ExternalLink, Languages, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

type ApplicationRole = {
    key?: string;
    label: string;
    title: string;
    description: string;
    image_url?: string;
    note_title?: string;
    note?: string;
    floating_one_title?: string;
    floating_one_text?: string;
    floating_two_title?: string;
    floating_two_text?: string;
    screen_label?: string;
    screen_title?: string;
    screen_stat_label?: string;
    screen_stat_value?: string;
    features: string[];
};

type ComparisonItem = {
    title: string;
    description: string;
    items: string[];
};

type ApplicationsPage = {
    enabled?: boolean;
    hero_eyebrow: string;
    hero_title: string;
    hero_highlight?: string;
    hero_description: string;
    hero_image_url?: string;
    primary_cta_label: string;
    secondary_cta_label: string;
    owner_employee_note?: string;
    section_eyebrow: string;
    section_title: string;
    section_description: string;
    store_ios_label: string;
    store_ios_caption: string;
    store_android_label: string;
    store_android_caption: string;
    roles: ApplicationRole[];
    compare_title: string;
    compare_description: string;
    compare_badge: string;
    comparison: ComparisonItem[];
    ecosystem_title: string;
    ecosystem_description: string;
    ecosystem_cta_label: string;
};

const props = defineProps<{
    applicationsPage: ApplicationsPage;
    previewUrl: string;
    translationsUrl: string;
    updateUrl: string;
    heroFiles: Array<{ id: number; url: string }>;
    roleFiles: Record<number, Array<{ id: number; url: string }>>;
}>();

const page = usePage<any>();

const form = useForm<{
    applications_page: ApplicationsPage;
    application_hero_direct_file: File | null;
    application_hero_removed_files: number[];
    application_role_direct_files: Record<number, File | null>;
    application_role_removed_files: Record<number, number[]>;
}>({
    applications_page: {
        ...props.applicationsPage,
        enabled: props.applicationsPage.enabled !== false,
        roles: (props.applicationsPage.roles || []).map((role) => ({
            ...role,
            image_url: role.image_url || '',
            features: [...(role.features || [])],
        })),
        comparison: (props.applicationsPage.comparison || []).map((item) => ({
            ...item,
            items: [...(item.items || [])],
        })),
    },
    application_hero_direct_file: null,
    application_hero_removed_files: [],
    application_role_direct_files: {},
    application_role_removed_files: {},
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const formErrors = computed(() =>
    Object.values(form.errors ?? {}).filter((value): value is string => typeof value === 'string' && value.length > 0),
);

function submit(): void {
    form.transform((data) => ({
        ...data,
        _method: 'put',
    })).post(props.updateUrl, {
        preserveScroll: true,
        forceFormData: true,
    });
}

function handleHeroLocalFileAdded(file: File): void {
    form.application_hero_direct_file = file;
}

function handleHeroFileRemoved(data: { type: string; fileId?: number }): void {
    if (data.type !== 'existing' || !data.fileId) {
        return;
    }

    form.application_hero_removed_files = [...new Set([...form.application_hero_removed_files, data.fileId])];
    form.applications_page.hero_image_url = '';
}

function roleFileList(index: number): Array<{ id: number; url: string }> {
    return props.roleFiles?.[index] || [];
}

function handleRoleLocalFileAdded(index: number, file: File): void {
    form.application_role_direct_files = {
        ...form.application_role_direct_files,
        [index]: file,
    };
}

function handleRoleFileRemoved(index: number, data: { type: string; fileId?: number }): void {
    if (data.type !== 'existing' || !data.fileId) {
        return;
    }

    form.application_role_removed_files = {
        ...form.application_role_removed_files,
        [index]: [...new Set([...(form.application_role_removed_files[index] || []), data.fileId])],
    };
    form.applications_page.roles[index].image_url = '';
}

function addRoleFeature(role: ApplicationRole): void {
    role.features.push('');
}

function removeRoleFeature(role: ApplicationRole, index: number): void {
    role.features.splice(index, 1);
}

function addComparisonItem(item: ComparisonItem): void {
    item.items.push('');
}

function removeComparisonItem(item: ComparisonItem, index: number): void {
    item.items.splice(index, 1);
}
</script>

<template>
    <Head title="Applications Page" />

    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Applications Page</h1>
                    <p class="text-sm text-muted-foreground">
                        Edit the public applications page content, role blocks, comparison copy, and uploaded images.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button as-child variant="outline">
                        <a :href="previewUrl" target="_blank" rel="noopener noreferrer">
                            <ExternalLink class="h-4 w-4" />
                            Preview
                        </a>
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="translationsUrl">
                            <Languages class="h-4 w-4" />
                            Translations
                        </Link>
                    </Button>
                    <Button :disabled="form.processing" @click="submit">
                        <Save class="h-4 w-4" />
                        {{ form.processing ? 'Saving...' : 'Save Page' }}
                    </Button>
                </div>
            </div>

            <div v-if="flashSuccess" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ flashError }}
            </div>
            <div v-if="formErrors.length" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <div class="font-medium">Please fix the following errors:</div>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="(message, index) in formErrors" :key="index">{{ message }}</li>
                </ul>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Hero</CardTitle>
                    <CardDescription>Main headline, intro copy, calls to action, and hero image.</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 lg:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Eyebrow</Label>
                        <Input v-model="form.applications_page.hero_eyebrow" />
                    </div>
                    <div class="space-y-2">
                        <Label>Hero Image</Label>
                        <FileUpload
                            :initial-files="heroFiles"
                            :allow-multiple="false"
                            :max-files="1"
                            :instant-upload="false"
                            :max-file-size="1024 * 1024 * 50"
                            :allowed-file-types="['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml']"
                            collection="applications_page_hero"
                            theme="light"
                            width="100%"
                            @local-file-added="handleHeroLocalFileAdded"
                            @file-removed="handleHeroFileRemoved"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label>Title</Label>
                        <Input v-model="form.applications_page.hero_title" />
                    </div>
                    <div class="space-y-2">
                        <Label>Highlighted Title Text</Label>
                        <Input v-model="form.applications_page.hero_highlight" />
                    </div>
                    <div class="space-y-2 lg:col-span-2">
                        <Label>Description</Label>
                        <Textarea v-model="form.applications_page.hero_description" rows="3" />
                    </div>
                    <div class="space-y-2">
                        <Label>Primary CTA</Label>
                        <Input v-model="form.applications_page.primary_cta_label" />
                    </div>
                    <div class="space-y-2">
                        <Label>Secondary CTA</Label>
                        <Input v-model="form.applications_page.secondary_cta_label" />
                    </div>
                    <div class="space-y-2 lg:col-span-2">
                        <Label>Owner / Employee Note</Label>
                        <Textarea v-model="form.applications_page.owner_employee_note" rows="2" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Applications Section</CardTitle>
                    <CardDescription>Section heading and store button labels.</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 lg:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Section Eyebrow</Label>
                        <Input v-model="form.applications_page.section_eyebrow" />
                    </div>
                    <div class="space-y-2">
                        <Label>Section Title</Label>
                        <Input v-model="form.applications_page.section_title" />
                    </div>
                    <div class="space-y-2 lg:col-span-2">
                        <Label>Section Description</Label>
                        <Textarea v-model="form.applications_page.section_description" rows="3" />
                    </div>
                    <div class="space-y-2">
                        <Label>iOS Caption</Label>
                        <Input v-model="form.applications_page.store_ios_caption" />
                    </div>
                    <div class="space-y-2">
                        <Label>iOS Label</Label>
                        <Input v-model="form.applications_page.store_ios_label" />
                    </div>
                    <div class="space-y-2">
                        <Label>Android Caption</Label>
                        <Input v-model="form.applications_page.store_android_caption" />
                    </div>
                    <div class="space-y-2">
                        <Label>Android Label</Label>
                        <Input v-model="form.applications_page.store_android_label" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Role Experiences</CardTitle>
                    <CardDescription>Edit owner, employee, and renter blocks. Uploaded images appear in the public page visual area.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div
                        v-for="(role, roleIndex) in form.applications_page.roles"
                        :key="`${role.key || role.title}-${roleIndex}`"
                        class="space-y-4 rounded-lg border p-4"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase text-muted-foreground">{{ role.key || `Role ${roleIndex + 1}` }}</p>
                                <h3 class="font-semibold">{{ role.title || role.label }}</h3>
                            </div>
                            <img v-if="role.image_url" :src="role.image_url" alt="" class="h-16 w-24 rounded-md border object-cover" />
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Label</Label>
                                <Input v-model="role.label" />
                            </div>
                            <div class="space-y-2">
                                <Label>Image</Label>
                                <FileUpload
                                    :initial-files="roleFileList(roleIndex)"
                                    :allow-multiple="false"
                                    :max-files="1"
                                    :instant-upload="false"
                                    :max-file-size="1024 * 1024 * 50"
                                    :allowed-file-types="['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml']"
                                    :collection="`applications_page_role_${roleIndex}_image`"
                                    theme="light"
                                    width="100%"
                                    @local-file-added="(file) => handleRoleLocalFileAdded(roleIndex, file)"
                                    @file-removed="(data) => handleRoleFileRemoved(roleIndex, data)"
                                />
                            </div>
                            <div class="space-y-2 lg:col-span-2">
                                <Label>Title</Label>
                                <Input v-model="role.title" />
                            </div>
                            <div class="space-y-2 lg:col-span-2">
                                <Label>Description</Label>
                                <Textarea v-model="role.description" rows="3" />
                            </div>
                            <div class="space-y-2">
                                <Label>Note Title</Label>
                                <Input v-model="role.note_title" />
                            </div>
                            <div class="space-y-2">
                                <Label>Note</Label>
                                <Input v-model="role.note" />
                            </div>
                            <div class="space-y-2">
                                <Label>Floating Card 1 Title</Label>
                                <Input v-model="role.floating_one_title" />
                            </div>
                            <div class="space-y-2">
                                <Label>Floating Card 1 Text</Label>
                                <Input v-model="role.floating_one_text" />
                            </div>
                            <div class="space-y-2">
                                <Label>Floating Card 2 Title</Label>
                                <Input v-model="role.floating_two_title" />
                            </div>
                            <div class="space-y-2">
                                <Label>Floating Card 2 Text</Label>
                                <Input v-model="role.floating_two_text" />
                            </div>
                            <div class="space-y-2">
                                <Label>Screen Label</Label>
                                <Input v-model="role.screen_label" />
                            </div>
                            <div class="space-y-2">
                                <Label>Screen Title</Label>
                                <Input v-model="role.screen_title" />
                            </div>
                            <div class="space-y-2">
                                <Label>Screen Stat Label</Label>
                                <Input v-model="role.screen_stat_label" />
                            </div>
                            <div class="space-y-2">
                                <Label>Screen Stat Value</Label>
                                <Input v-model="role.screen_stat_value" />
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <Label>Features</Label>
                                <Button type="button" size="sm" variant="outline" @click="addRoleFeature(role)">
                                    <Plus class="h-4 w-4" />
                                    Add Feature
                                </Button>
                            </div>
                            <div v-for="(_feature, featureIndex) in role.features" :key="featureIndex" class="flex gap-2">
                                <Input v-model="role.features[featureIndex]" />
                                <Button type="button" size="icon" variant="outline" @click="removeRoleFeature(role, featureIndex)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Comparison</CardTitle>
                    <CardDescription>Comparison section content and table items.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Title</Label>
                            <Input v-model="form.applications_page.compare_title" />
                        </div>
                        <div class="space-y-2">
                            <Label>Badge</Label>
                            <Input v-model="form.applications_page.compare_badge" />
                        </div>
                        <div class="space-y-2 lg:col-span-2">
                            <Label>Description</Label>
                            <Textarea v-model="form.applications_page.compare_description" rows="3" />
                        </div>
                    </div>

                    <div
                        v-for="(item, itemIndex) in form.applications_page.comparison"
                        :key="`${item.title}-${itemIndex}`"
                        class="space-y-3 rounded-lg border p-4"
                    >
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Column Title</Label>
                                <Input v-model="item.title" />
                            </div>
                            <div class="space-y-2">
                                <Label>Column Description</Label>
                                <Input v-model="item.description" />
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <Label>Items</Label>
                            <Button type="button" size="sm" variant="outline" @click="addComparisonItem(item)">
                                <Plus class="h-4 w-4" />
                                Add Item
                            </Button>
                        </div>
                        <div v-for="(_row, rowIndex) in item.items" :key="rowIndex" class="flex gap-2">
                            <Input v-model="item.items[rowIndex]" />
                            <Button type="button" size="icon" variant="outline" @click="removeComparisonItem(item, rowIndex)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Ecosystem CTA</CardTitle>
                    <CardDescription>Bottom call to action section.</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 lg:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Title</Label>
                        <Input v-model="form.applications_page.ecosystem_title" />
                    </div>
                    <div class="space-y-2">
                        <Label>CTA Label</Label>
                        <Input v-model="form.applications_page.ecosystem_cta_label" />
                    </div>
                    <div class="space-y-2 lg:col-span-2">
                        <Label>Description</Label>
                        <Textarea v-model="form.applications_page.ecosystem_description" rows="3" />
                    </div>
                </CardContent>
            </Card>
        </main>
    </SuperAdminLayout>
</template>
