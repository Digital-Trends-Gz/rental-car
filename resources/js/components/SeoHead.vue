<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

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
    og_type?: string | null;
    og_url?: string | null;
    og_locale?: string | null;
    twitter_card?: string | null;
    twitter_title?: string | null;
    twitter_description?: string | null;
    twitter_image?: string | null;
    alternates?: SeoAlternate[];
    schemas?: Array<Record<string, unknown>>;
};

const props = defineProps<{
    seo: SeoPayload | null | undefined;
}>();

const twitterCard = computed(() => props.seo?.twitter_card || (props.seo?.twitter_image || props.seo?.og_image ? 'summary_large_image' : 'summary'));
const twitterTitle = computed(() => props.seo?.twitter_title || props.seo?.og_title || props.seo?.title || '');
const twitterDescription = computed(() => props.seo?.twitter_description || props.seo?.og_description || props.seo?.description || '');
const twitterImage = computed(() => props.seo?.twitter_image || props.seo?.og_image || '');
</script>

<template>
    <Head v-if="seo">
        <title>{{ seo.title }}</title>
        <meta v-if="seo.description" head-key="description" name="description" :content="seo.description" />
        <meta v-if="seo.robots" head-key="robots" name="robots" :content="seo.robots" />
        <meta v-if="seo.og_type" head-key="og:type" property="og:type" :content="seo.og_type" />
        <meta v-if="seo.og_url || seo.canonical_url" head-key="og:url" property="og:url" :content="seo.og_url || seo.canonical_url" />
        <meta v-if="seo.og_locale" head-key="og:locale" property="og:locale" :content="seo.og_locale" />
        <meta v-if="seo.og_title" head-key="og:title" property="og:title" :content="seo.og_title" />
        <meta v-if="seo.og_description" head-key="og:description" property="og:description" :content="seo.og_description" />
        <meta v-if="seo.og_image" head-key="og:image" property="og:image" :content="seo.og_image" />
        <meta v-if="seo.og_image_alt" head-key="og:image:alt" property="og:image:alt" :content="seo.og_image_alt" />
        <meta head-key="twitter:card" name="twitter:card" :content="twitterCard" />
        <meta v-if="twitterTitle" head-key="twitter:title" name="twitter:title" :content="twitterTitle" />
        <meta v-if="twitterDescription" head-key="twitter:description" name="twitter:description" :content="twitterDescription" />
        <meta v-if="twitterImage" head-key="twitter:image" name="twitter:image" :content="twitterImage" />
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
