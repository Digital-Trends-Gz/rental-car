<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

type SeoAlternate = {
    locale: string;
    url: string;
};

type SeoPayload = {
    title: string;
    description?: string | null;
    canonical_url?: string | null;
    robots?: string | null;
    og_title?: string | null;
    og_description?: string | null;
    og_image?: string | null;
    og_image_alt?: string | null;
    alternates?: SeoAlternate[];
    schemas?: Array<Record<string, unknown>>;
};

defineProps<{
    seo: SeoPayload | null | undefined;
}>();
</script>

<template>
    <Head v-if="seo">
        <title>{{ seo.title }}</title>
        <meta v-if="seo.description" head-key="description" name="description" :content="seo.description" />
        <meta v-if="seo.robots" head-key="robots" name="robots" :content="seo.robots" />
        <meta v-if="seo.og_title" head-key="og:title" property="og:title" :content="seo.og_title" />
        <meta v-if="seo.og_description" head-key="og:description" property="og:description" :content="seo.og_description" />
        <meta v-if="seo.og_image" head-key="og:image" property="og:image" :content="seo.og_image" />
        <meta v-if="seo.og_image_alt" head-key="og:image:alt" property="og:image:alt" :content="seo.og_image_alt" />
        <meta v-if="seo.og_image_alt" head-key="twitter:image:alt" name="twitter:image:alt" :content="seo.og_image_alt" />
        <link v-if="seo.canonical_url" head-key="canonical" rel="canonical" :href="seo.canonical_url" />
        <link
            v-for="alternate in seo.alternates ?? []"
            :key="alternate.locale"
            :head-key="`alternate-${alternate.locale}`"
            rel="alternate"
            :hreflang="alternate.locale"
            :href="alternate.url"
        />
        <script
            v-for="(schema, index) in seo.schemas ?? []"
            :key="`schema-${index}`"
            :head-key="`schema-${index}`"
            type="application/ld+json"
            v-text="JSON.stringify(schema)"
        />
    </Head>
</template>
