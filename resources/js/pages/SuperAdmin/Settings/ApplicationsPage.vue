<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ExternalLink, Languages } from 'lucide-vue-next';

type ApplicationRole = {
    key?: string;
    label: string;
    title: string;
    description: string;
    features?: string[];
};

type ApplicationsPage = {
    enabled?: boolean;
    hero_eyebrow?: string;
    hero_title?: string;
    hero_description?: string;
    section_title?: string;
    roles?: ApplicationRole[];
    compare_title?: string;
    ecosystem_title?: string;
};

const props = defineProps<{
    applicationsPage: ApplicationsPage;
    previewUrl: string;
    translationsUrl: string;
}>();
</script>

<template>
    <Head title="Applications Page" />

    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Applications Page</h1>
                    <p class="text-sm text-muted-foreground">
                        Review the public applications page and edit its localized text from Landing Translations.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button as-child variant="outline">
                        <a :href="previewUrl" target="_blank" rel="noopener noreferrer">
                            <ExternalLink class="h-4 w-4" />
                            Preview Page
                        </a>
                    </Button>
                    <Button as-child>
                        <Link :href="translationsUrl">
                            <Languages class="h-4 w-4" />
                            Edit Translations
                        </Link>
                    </Button>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Translation Keys</CardTitle>
                    <CardDescription>
                        Search for <span class="font-mono">applications_page</span> in Landing Translations to edit this page in each enabled language.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="rounded-md border bg-muted/30 p-4 font-mono text-sm">
                        applications_page.hero_title<br />
                        applications_page.roles.0.title<br />
                        applications_page.comparison.0.items.0<br />
                        applications_page.ecosystem_title
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                <Card>
                    <CardHeader>
                        <CardTitle>Hero</CardTitle>
                        <CardDescription>Current default content shown before locale overrides.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div>
                            <p class="text-xs font-semibold uppercase text-muted-foreground">Eyebrow</p>
                            <p class="text-sm">{{ props.applicationsPage.hero_eyebrow }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-muted-foreground">Title</p>
                            <p class="text-lg font-semibold">{{ props.applicationsPage.hero_title }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-muted-foreground">Description</p>
                            <p class="text-sm leading-6 text-muted-foreground">{{ props.applicationsPage.hero_description }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Experiences</CardTitle>
                        <CardDescription>Owner, employee, and renter content blocks.</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-3">
                        <div
                            v-for="role in props.applicationsPage.roles || []"
                            :key="role.key || role.title"
                            class="rounded-md border p-4"
                        >
                            <p class="text-xs font-semibold uppercase text-muted-foreground">{{ role.label }}</p>
                            <h3 class="mt-2 font-semibold">{{ role.title }}</h3>
                            <p class="mt-2 line-clamp-4 text-sm leading-6 text-muted-foreground">{{ role.description }}</p>
                            <p class="mt-3 text-xs text-muted-foreground">
                                {{ role.features?.length || 0 }} features
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Comparison Section</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm text-muted-foreground">
                        {{ props.applicationsPage.compare_title }}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Ecosystem CTA</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm text-muted-foreground">
                        {{ props.applicationsPage.ecosystem_title }}
                    </CardContent>
                </Card>
            </div>
        </main>
    </SuperAdminLayout>
</template>
