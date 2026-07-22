<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ExternalLink, Languages, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

type ApplicationRole = {
    enabled?: boolean;
    key?: string;
    label: string;
    title: string;
    description: string;
    image_url?: string;
    localized_images?: Record<string, string>;
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
    hero_enabled?: boolean;
    hero_eyebrow: string;
    hero_title: string;
    hero_highlight?: string;
    hero_description: string;
    hero_image_url?: string;
    hero_localized_images?: Record<string, string>;
    primary_cta_label: string;
    secondary_cta_label: string;
    owner_employee_note?: string;
    apps_enabled?: boolean;
    section_eyebrow: string;
    section_title: string;
    section_description: string;
    store_ios_label: string;
    store_ios_caption: string;
    store_android_label: string;
    store_android_caption: string;
    roles: ApplicationRole[];
    compare_title: string;
    comparison_enabled?: boolean;
    compare_description: string;
    compare_badge: string;
    comparison: ComparisonItem[];
    ecosystem_title: string;
    ecosystem_enabled?: boolean;
    ecosystem_description: string;
    ecosystem_cta_label: string;
};

const props = defineProps<{
    applicationsPage: ApplicationsPage;
    previewUrl: string;
    translationsUrl: string;
    updateUrl: string;
    availableLocales: string[];
    heroFiles: Array<{ id: number; url: string }>;
    heroLocalizedFiles: Record<string, Array<{ id: number; url: string }>>;
    roleFiles: Record<number, Array<{ id: number; url: string }>>;
    roleLocalizedFiles: Record<number, Record<string, Array<{ id: number; url: string }>>>;
}>();

const page = usePage<any>();

const form = useForm<{
    applications_page: ApplicationsPage;
    application_hero_direct_file: File | null;
    application_hero_removed_files: number[];
    application_hero_locale_direct_files: Record<string, File | null>;
    application_hero_locale_removed_files: Record<string, number[]>;
    application_role_direct_files: Record<number, File | null>;
    application_role_removed_files: Record<number, number[]>;
    application_role_locale_direct_files: Record<number, Record<string, File | null>>;
    application_role_locale_removed_files: Record<number, Record<string, number[]>>;
}>({
    applications_page: {
        ...props.applicationsPage,
        hero_localized_images: { ...(props.applicationsPage.hero_localized_images || {}) },
        enabled: props.applicationsPage.enabled !== false,
        hero_enabled: props.applicationsPage.hero_enabled !== false,
        apps_enabled: props.applicationsPage.apps_enabled !== false,
        comparison_enabled: props.applicationsPage.comparison_enabled !== false,
        ecosystem_enabled: props.applicationsPage.ecosystem_enabled !== false,
        roles: (props.applicationsPage.roles || []).map((role) => ({
            ...role,
            enabled: role.enabled !== false,
            image_url: role.image_url || '',
            localized_images: { ...(role.localized_images || {}) },
            features: [...(role.features || [])],
        })),
        comparison: (props.applicationsPage.comparison || []).map((item) => ({
            ...item,
            items: [...(item.items || [])],
        })),
    },
    application_hero_direct_file: null,
    application_hero_removed_files: [],
    application_hero_locale_direct_files: {},
    application_hero_locale_removed_files: {},
    application_role_direct_files: {},
    application_role_removed_files: {},
    application_role_locale_direct_files: {},
    application_role_locale_removed_files: {},
});

const availableLocales = computed(() => props.availableLocales?.length ? props.availableLocales : ['en']);
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const formErrors = computed(() =>
    Object.values(form.errors ?? {}).filter((value): value is string => typeof value === 'string' && value.length > 0),
);

const localeDisplayName = (localeCode: string) =>
    (
        {
            en: 'English',
            ar: 'Arabic',
            ur: 'Urdu',
        } as Record<string, string>
    )[localeCode] || localeCode.toUpperCase();

if (!form.applications_page.hero_localized_images) {
    form.applications_page.hero_localized_images = {};
}

for (const localeCode of availableLocales.value) {
    form.applications_page.hero_localized_images[localeCode] =
        form.applications_page.hero_localized_images[localeCode] || '';
    form.application_hero_locale_direct_files[localeCode] = null;
    form.application_hero_locale_removed_files[localeCode] = [];
}

form.applications_page.roles.forEach((role, index) => {
    if (!role.localized_images) {
        role.localized_images = {};
    }

    form.application_role_locale_direct_files[index] = {};
    form.application_role_locale_removed_files[index] = {};

    for (const localeCode of availableLocales.value) {
        role.localized_images[localeCode] = role.localized_images[localeCode] || '';
        form.application_role_locale_direct_files[index][localeCode] = null;
        form.application_role_locale_removed_files[index][localeCode] = [];
    }
});

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

function heroLocaleFileList(localeCode: string): Array<{ id: number; url: string }> {
    return props.heroLocalizedFiles?.[localeCode] || [];
}

function handleHeroLocaleLocalFileAdded(localeCode: string, file: File): void {
    form.application_hero_locale_direct_files = {
        ...form.application_hero_locale_direct_files,
        [localeCode]: file,
    };
}

function handleHeroLocaleFileRemoved(localeCode: string, data: { type: string; fileId?: number }): void {
    if (data.type !== 'existing' || !data.fileId) {
        return;
    }

    form.application_hero_locale_removed_files = {
        ...form.application_hero_locale_removed_files,
        [localeCode]: [...new Set([...(form.application_hero_locale_removed_files[localeCode] || []), data.fileId])],
    };
    if (form.applications_page.hero_localized_images) {
        form.applications_page.hero_localized_images[localeCode] = '';
    }
}

function roleFileList(index: number): Array<{ id: number; url: string }> {
    return props.roleFiles?.[index] || [];
}

function roleLocaleFileList(index: number, localeCode: string): Array<{ id: number; url: string }> {
    return props.roleLocalizedFiles?.[index]?.[localeCode] || [];
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

function handleRoleLocaleLocalFileAdded(index: number, localeCode: string, file: File): void {
    form.application_role_locale_direct_files = {
        ...form.application_role_locale_direct_files,
        [index]: {
            ...(form.application_role_locale_direct_files[index] || {}),
            [localeCode]: file,
        },
    };
}

function handleRoleLocaleFileRemoved(index: number, localeCode: string, data: { type: string; fileId?: number }): void {
    if (data.type !== 'existing' || !data.fileId) {
        return;
    }

    form.application_role_locale_removed_files = {
        ...form.application_role_locale_removed_files,
        [index]: {
            ...(form.application_role_locale_removed_files[index] || {}),
            [localeCode]: [
                ...new Set([...(form.application_role_locale_removed_files[index]?.[localeCode] || []), data.fileId]),
            ],
        },
    };
    if (form.applications_page.roles[index].localized_images) {
        form.applications_page.roles[index].localized_images[localeCode] = '';
    }
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
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2 rounded-md border px-3 py-2">
                        <Switch v-model:checked="form.applications_page.enabled" />
                        <span class="text-sm font-medium">{{ form.applications_page.enabled ? 'Page Shown' : 'Page Hidden' }}</span>
                    </div>
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
                <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <CardTitle>Hero</CardTitle>
                        <CardDescription>Main headline, intro copy, calls to action, and hero image.</CardDescription>
                    </div>
                    <div class="flex items-center gap-2">
                        <Switch v-model:checked="form.applications_page.hero_enabled" />
                        <span class="text-sm font-medium">{{ form.applications_page.hero_enabled ? 'Shown' : 'Hidden' }}</span>
                    </div>
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
                    <div class="space-y-3 rounded-lg border border-dashed p-3 lg:col-span-2">
                        <Label>Hero Images By Language</Label>
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div v-for="localeCode in availableLocales" :key="`application-hero-locale-${localeCode}`" class="space-y-2">
                                <Label>{{ localeDisplayName(localeCode) }}</Label>
                                <FileUpload
                                    :initial-files="heroLocaleFileList(localeCode)"
                                    :allow-multiple="false"
                                    :max-files="1"
                                    :instant-upload="false"
                                    :max-file-size="1024 * 1024 * 50"
                                    :allowed-file-types="['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml']"
                                    :collection="`applications_page_hero_${localeCode}`"
                                    theme="light"
                                    width="100%"
                                    @local-file-added="(file) => handleHeroLocaleLocalFileAdded(localeCode, file)"
                                    @file-removed="(data) => handleHeroLocaleFileRemoved(localeCode, data)"
                                />
                                <img
                                    v-if="form.applications_page.hero_localized_images?.[localeCode]"
                                    :src="form.applications_page.hero_localized_images[localeCode]"
                                    alt=""
                                    class="h-20 w-full rounded-md border object-cover"
                                />
                            </div>
                        </div>
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
                <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <CardTitle>Applications Section</CardTitle>
                        <CardDescription>Section heading, role blocks, and store button labels.</CardDescription>
                    </div>
                    <div class="flex items-center gap-2">
                        <Switch v-model:checked="form.applications_page.apps_enabled" />
                        <span class="text-sm font-medium">{{ form.applications_page.apps_enabled ? 'Shown' : 'Hidden' }}</span>
                    </div>
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
                            <div class="flex items-center gap-3">
                                <img v-if="role.image_url" :src="role.image_url" alt="" class="h-16 w-24 rounded-md border object-cover" />
                                <div class="flex items-center gap-2">
                                    <Switch v-model:checked="role.enabled" />
                                    <span class="text-sm font-medium">{{ role.enabled ? 'Shown' : 'Hidden' }}</span>
                                </div>
                            </div>
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
                            <div class="space-y-3 rounded-lg border border-dashed p-3 lg:col-span-2">
                                <Label>Role Images By Language</Label>
                                <div class="grid gap-4 lg:grid-cols-3">
                                    <div v-for="localeCode in availableLocales" :key="`application-role-${roleIndex}-${localeCode}`" class="space-y-2">
                                        <Label>{{ localeDisplayName(localeCode) }}</Label>
                                        <FileUpload
                                            :initial-files="roleLocaleFileList(roleIndex, localeCode)"
                                            :allow-multiple="false"
                                            :max-files="1"
                                            :instant-upload="false"
                                            :max-file-size="1024 * 1024 * 50"
                                            :allowed-file-types="['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml']"
                                            :collection="`applications_page_role_${roleIndex}_image_${localeCode}`"
                                            theme="light"
                                            width="100%"
                                            @local-file-added="(file) => handleRoleLocaleLocalFileAdded(roleIndex, localeCode, file)"
                                            @file-removed="(data) => handleRoleLocaleFileRemoved(roleIndex, localeCode, data)"
                                        />
                                        <img
                                            v-if="role.localized_images?.[localeCode]"
                                            :src="role.localized_images[localeCode]"
                                            alt=""
                                            class="h-20 w-full rounded-md border object-cover"
                                        />
                                    </div>
                                </div>
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
                <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <CardTitle>Comparison</CardTitle>
                        <CardDescription>Comparison section content and table items.</CardDescription>
                    </div>
                    <div class="flex items-center gap-2">
                        <Switch v-model:checked="form.applications_page.comparison_enabled" />
                        <span class="text-sm font-medium">{{ form.applications_page.comparison_enabled ? 'Shown' : 'Hidden' }}</span>
                    </div>
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
                <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <CardTitle>Ecosystem CTA</CardTitle>
                        <CardDescription>Bottom call to action section.</CardDescription>
                    </div>
                    <div class="flex items-center gap-2">
                        <Switch v-model:checked="form.applications_page.ecosystem_enabled" />
                        <span class="text-sm font-medium">{{ form.applications_page.ecosystem_enabled ? 'Shown' : 'Hidden' }}</span>
                    </div>
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
