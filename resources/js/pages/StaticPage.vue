<script setup lang="ts">
import SeoHead from '@/components/SeoHead.vue';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type StaticPagePayload = {
    section: string;
    title: string;
    content_html: string;
    locale: string;
    direction: 'ltr' | 'rtl';
};

const props = defineProps<{
    page: StaticPagePayload;
    seo?: Record<string, unknown> | null;
}>();

const inertiaPage = usePage<any>();
const isTenant = computed(() => Boolean(inertiaPage.props.current_tenant));
const sectionLabel = computed(() => props.page.section.replace(/_/g, ' '));
const seoPayload = computed(() => props.seo as any);
</script>

<template>
    <SeoHead :seo="seoPayload" />

    <HomeLayout :shell-variant="isTenant ? 'tenant' : 'landing'">
        <main class="min-h-screen bg-slate-50 px-4 py-24 sm:px-6 lg:px-8">
            <section
                class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10"
                :dir="page.direction"
            >
                <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">
                    {{ page.title }}
                </h1>

                <article
                    v-if="page.content_html"
                    class="static-page-content mt-8"
                    v-html="page.content_html"
                />
                <div
                    v-else
                    class="mt-8 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-slate-600"
                >
                    This page content has not been published yet.
                </div>
            </section>
        </main>
    </HomeLayout>
</template>

<style scoped>
.static-page-content {
    color: rgb(51 65 85);
    font-size: 1rem;
    line-height: 1.85;
}

.static-page-content :deep(h2) {
    margin-top: 2rem;
    margin-bottom: 0.75rem;
    color: rgb(15 23 42);
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.3;
}

.static-page-content :deep(h3) {
    margin-top: 1.5rem;
    margin-bottom: 0.5rem;
    color: rgb(15 23 42);
    font-size: 1.25rem;
    font-weight: 700;
    line-height: 1.35;
}

.static-page-content :deep(p) {
    margin: 0.9rem 0;
}

.static-page-content :deep(ul),
.static-page-content :deep(ol) {
    margin: 1rem 0;
    padding-inline-start: 1.5rem;
}

.static-page-content :deep(li) {
    margin: 0.4rem 0;
}

.static-page-content :deep(blockquote) {
    margin: 1.5rem 0;
    border-inline-start: 4px solid rgb(59 130 246);
    background: rgb(248 250 252);
    padding: 0.75rem 1rem;
    color: rgb(71 85 105);
}

.static-page-content :deep(a) {
    color: rgb(37 99 235);
    font-weight: 600;
    text-decoration: underline;
}

.static-page-content :deep(hr) {
    margin: 2rem 0;
    border: 0;
    border-top: 1px solid rgb(226 232 240);
}

.static-page-content :deep(ul) {
    list-style: disc !important;
    list-style-type: disc !important;
}

.static-page-content :deep(ol) {
    list-style: decimal !important;
    list-style-type: decimal !important;
}
</style>
