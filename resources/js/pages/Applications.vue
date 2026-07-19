<script setup lang="ts">
import SeoHead from '@/components/SeoHead.vue';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { register as mainRegister } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Apple, ArrowRight, BriefcaseBusiness, Building2, Check, Play, Smartphone, Users } from 'lucide-vue-next';
import { computed } from 'vue';

type ApplicationRole = {
    key?: string;
    label: string;
    title: string;
    description: string;
    image_url?: string;
    note_title: string;
    note: string;
    floating_one_title: string;
    floating_one_text: string;
    floating_two_title: string;
    floating_two_text: string;
    screen_label: string;
    screen_title: string;
    screen_stat_label: string;
    screen_stat_value: string;
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
    hero_highlight: string;
    hero_description: string;
    hero_image_url?: string;
    primary_cta_label: string;
    secondary_cta_label: string;
    owner_employee_note: string;
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
    landingSettings: Record<string, unknown>;
    seo?: {
        title: string;
        description?: string | null;
        canonical_url?: string | null;
        robots?: string | null;
        og_title?: string | null;
        og_description?: string | null;
        og_image?: string | null;
        alternates?: Array<{ locale: string; url: string }>;
    } | null;
}>();

const page = usePage<any>();
const locale = computed(() => String(page.props.locale || 'en'));
const isRtl = computed(() => ['ar', 'ur'].includes(locale.value.toLowerCase().split('-')[0]));
const registerUrl = mainRegister().url;
const titleLead = computed(() => {
    const title = props.applicationsPage.hero_title || '';
    const highlight = props.applicationsPage.hero_highlight || '';

    if (!highlight || !title.toLowerCase().includes(highlight.toLowerCase())) {
        return title;
    }

    return title.slice(0, title.toLowerCase().indexOf(highlight.toLowerCase())).trim();
});
const titleHighlight = computed(() => {
    const title = props.applicationsPage.hero_title || '';
    const highlight = props.applicationsPage.hero_highlight || '';

    return highlight && title.toLowerCase().includes(highlight.toLowerCase()) ? highlight : '';
});
const roleIcon = (key?: string) => {
    const normalized = String(key || '').toLowerCase();

    if (normalized === 'owner') {
        return Building2;
    }

    if (normalized === 'employee') {
        return BriefcaseBusiness;
    }

    return Users;
};
const roleTone = (index: number) =>
    [
        'from-sky-50 via-white to-indigo-50',
        'from-indigo-50 via-white to-violet-50',
        'from-cyan-50 via-white to-fuchsia-50',
    ][index % 3];
</script>

<template>
    <SeoHead v-if="seo" :seo="seo" />
    <Head v-else :title="applicationsPage.hero_title || 'Applications'" />

    <HomeLayout shell-variant="landing">
        <div class="applications-page bg-background text-foreground" :dir="isRtl ? 'rtl' : 'ltr'">
            <section class="relative overflow-hidden border-b border-border bg-white">
                <div class="section-container grid min-h-[calc(100vh-4rem)] max-w-7xl items-center gap-12 py-14 md:grid-cols-[1fr_0.92fr] md:py-20">
                    <div class="max-w-3xl">
                        <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-2 text-xs font-black uppercase tracking-wider text-primary">
                            <span class="h-2 w-2 rounded-full bg-cyan-400"></span>
                            {{ applicationsPage.hero_eyebrow }}
                        </div>
                        <h1 class="mt-5 text-4xl font-black leading-tight tracking-normal text-slate-950 sm:text-5xl lg:text-6xl">
                            <span>{{ titleLead }}</span>
                            <br v-if="titleHighlight" />
                            <span v-if="titleHighlight" class="bg-gradient-to-r from-primary to-indigo-700 bg-clip-text text-transparent">
                                {{ titleHighlight }}
                            </span>
                        </h1>
                        <p class="mt-5 max-w-2xl text-lg leading-8 text-muted-foreground">
                            {{ applicationsPage.hero_description }}
                        </p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="#apps" class="inline-flex h-12 items-center justify-center gap-2 rounded-md bg-primary px-6 text-sm font-extrabold text-white shadow-lg shadow-primary/20 transition hover:bg-primary/90">
                                {{ applicationsPage.primary_cta_label }}
                                <ArrowRight class="h-4 w-4 rtl:rotate-180" />
                            </a>
                            <a href="#compare" class="inline-flex h-12 items-center justify-center rounded-md border border-border bg-white px-6 text-sm font-extrabold text-slate-800 shadow-sm transition hover:border-primary/40 hover:text-primary">
                                {{ applicationsPage.secondary_cta_label }}
                            </a>
                        </div>
                        <p class="mt-4 max-w-xl text-sm text-muted-foreground">
                            {{ applicationsPage.owner_employee_note }}
                        </p>
                    </div>

                    <div class="relative min-h-[31rem]">
                        <img
                            v-if="applicationsPage.hero_image_url"
                            :src="applicationsPage.hero_image_url"
                            :alt="applicationsPage.hero_title"
                            class="h-[31rem] w-full rounded-lg border border-border object-cover shadow-2xl shadow-slate-900/10"
                        />
                        <template v-else>
                        <div class="absolute inset-10 rounded-full bg-gradient-to-br from-primary/10 to-indigo-500/10"></div>
                        <div class="phone-mock phone-owner">
                            <div class="phone-screen">
                                <div class="phone-notch"></div>
                                <p class="screen-muted">OWNER APP</p>
                                <h3 class="screen-title">Business overview</h3>
                                <div class="screen-stat">
                                    <span>Monthly revenue</span>
                                    <strong>$42,860</strong>
                                </div>
                                <div class="screen-grid">
                                    <div>Fleet<b>38</b></div>
                                    <div>Bookings<b>24</b></div>
                                    <div>Branches<b>4</b></div>
                                    <div>Contracts<b>18</b></div>
                                </div>
                            </div>
                        </div>
                        <div class="phone-mock phone-employee">
                            <div class="phone-screen">
                                <div class="phone-notch"></div>
                                <p class="screen-muted">EMPLOYEE APP</p>
                                <h3 class="screen-title">Today tasks</h3>
                                <div class="screen-task">09:30 - Vehicle handover</div>
                                <div class="screen-task">11:00 - Return inspection</div>
                                <div class="screen-task">13:15 - Contract update</div>
                                <div class="screen-task">15:20 - Payment follow-up</div>
                            </div>
                        </div>
                        <div class="phone-mock phone-renter">
                            <div class="phone-screen">
                                <div class="phone-notch"></div>
                                <p class="screen-muted">RENTER APP</p>
                                <h3 class="screen-title">Find your car</h3>
                                <div class="screen-car">
                                    <Smartphone class="h-10 w-10 text-primary" />
                                </div>
                                <div class="screen-task">Book in simple steps</div>
                                <div class="screen-task">Track rental status</div>
                            </div>
                        </div>
                        </template>
                    </div>
                </div>
            </section>

            <section id="apps" class="section-container max-w-7xl py-16 md:py-24">
                <div class="mx-auto mb-14 max-w-3xl text-center">
                    <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-2 text-xs font-black uppercase tracking-wider text-primary">
                        <span class="h-2 w-2 rounded-full bg-cyan-400"></span>
                        {{ applicationsPage.section_eyebrow }}
                    </div>
                    <h2 class="mt-4 text-3xl font-black tracking-normal text-slate-950 sm:text-4xl">
                        {{ applicationsPage.section_title }}
                    </h2>
                    <p class="mt-4 text-base leading-7 text-muted-foreground">
                        {{ applicationsPage.section_description }}
                    </p>
                </div>

                <article
                    v-for="(role, index) in applicationsPage.roles"
                    :key="`${role.key || role.title}-${index}`"
                    class="grid items-center gap-10 py-10 md:grid-cols-2 md:gap-16"
                >
                    <div class="relative min-h-[29rem] overflow-hidden rounded-lg border border-border bg-gradient-to-br p-8 shadow-xl shadow-slate-900/5" :class="[roleTone(index), index % 2 === 1 ? 'md:order-2' : '']">
                        <div class="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-cyan-300/15"></div>
                        <div class="float-card left-6 top-16">
                            <strong>{{ role.floating_one_title }}</strong>
                            <span>{{ role.floating_one_text }}</span>
                        </div>
                        <img
                            v-if="role.image_url"
                            :src="role.image_url"
                            :alt="role.title"
                            class="absolute left-1/2 top-1/2 z-[2] h-[28rem] max-w-[76%] -translate-x-1/2 -translate-y-1/2 rounded-[1.5rem] object-cover shadow-2xl shadow-slate-900/20"
                        />
                        <div v-else class="big-phone">
                            <div class="phone-screen">
                                <div class="phone-notch"></div>
                                <p class="screen-muted">{{ role.screen_label }}</p>
                                <h3 class="screen-title">{{ role.screen_title }}</h3>
                                <div class="screen-stat">
                                    <span>{{ role.screen_stat_label }}</span>
                                    <strong>{{ role.screen_stat_value }}</strong>
                                </div>
                                <div class="screen-grid">
                                    <div v-for="feature in role.features.slice(0, 4)" :key="feature">
                                        {{ feature.split(' ').slice(0, 2).join(' ') }}
                                        <b>OK</b>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="float-card bottom-16 right-6">
                            <strong>{{ role.floating_two_title }}</strong>
                            <span>{{ role.floating_two_text }}</span>
                        </div>
                    </div>

                    <div>
                        <div class="inline-flex items-center gap-3 text-xs font-black uppercase tracking-wider text-slate-600">
                            <span class="grid h-10 w-10 place-items-center rounded-md bg-primary/10 text-primary">
                                <component :is="roleIcon(role.key)" class="h-5 w-5" />
                            </span>
                            {{ role.label }}
                        </div>
                        <h3 class="mt-5 text-3xl font-black tracking-normal text-slate-950">
                            {{ role.title }}
                        </h3>
                        <p class="mt-4 text-base leading-8 text-muted-foreground">
                            {{ role.description }}
                        </p>
                        <div class="mt-5 rounded-lg border border-primary/15 bg-primary/5 p-4 text-sm leading-6 text-slate-700">
                            <strong>{{ role.note_title }}</strong>
                            {{ role.note }}
                        </div>
                        <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                            <li v-for="feature in role.features" :key="feature" class="flex gap-2 text-sm leading-6 text-slate-700">
                                <Check class="mt-1 h-4 w-4 shrink-0 text-primary" />
                                <span>{{ feature }}</span>
                            </li>
                        </ul>
                        <div class="mt-7 flex flex-wrap gap-3">
                            <a href="#" class="store-button">
                                <Apple class="h-5 w-5" />
                                <span><small>{{ applicationsPage.store_ios_caption }}</small>{{ applicationsPage.store_ios_label }}</span>
                            </a>
                            <a href="#" class="store-button">
                                <Play class="h-5 w-5" />
                                <span><small>{{ applicationsPage.store_android_caption }}</small>{{ applicationsPage.store_android_label }}</span>
                            </a>
                        </div>
                    </div>
                </article>
            </section>

            <section id="compare" class="bg-slate-50 py-16 md:py-24">
                <div class="section-container max-w-7xl">
                    <div class="rounded-lg border border-border bg-white p-6 shadow-xl shadow-slate-900/5 md:p-10">
                        <div class="mb-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                            <div>
                                <h2 class="text-3xl font-black tracking-normal text-slate-950">
                                    {{ applicationsPage.compare_title }}
                                </h2>
                                <p class="mt-3 max-w-3xl leading-7 text-muted-foreground">
                                    {{ applicationsPage.compare_description }}
                                </p>
                            </div>
                            <span class="w-fit rounded-full bg-primary/10 px-3 py-2 text-xs font-black uppercase tracking-wider text-primary">
                                {{ applicationsPage.compare_badge }}
                            </span>
                        </div>
                        <div class="grid overflow-hidden rounded-lg border border-border md:grid-cols-3">
                            <div v-for="item in applicationsPage.comparison" :key="item.title" class="border-b border-border p-6 last:border-b-0 md:border-b-0 md:border-e">
                                <h3 class="text-lg font-black text-slate-950">{{ item.title }}</h3>
                                <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ item.description }}</p>
                                <ul class="mt-5">
                                    <li v-for="feature in item.items" :key="feature" class="border-t border-slate-100 py-3 text-sm text-slate-700">
                                        {{ feature }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-gradient-to-r from-primary to-indigo-700 py-16 text-white">
                <div class="section-container grid max-w-7xl items-center gap-8 md:grid-cols-[1fr_auto]">
                    <div>
                        <h2 class="text-3xl font-black tracking-normal sm:text-4xl">
                            {{ applicationsPage.ecosystem_title }}
                        </h2>
                        <p class="mt-4 max-w-3xl leading-8 text-white/85">
                            {{ applicationsPage.ecosystem_description }}
                        </p>
                    </div>
                    <Link :href="registerUrl" class="inline-flex h-12 items-center justify-center rounded-md bg-white px-6 text-sm font-extrabold text-primary shadow-lg transition hover:bg-white/90">
                        {{ applicationsPage.ecosystem_cta_label }}
                    </Link>
                </div>
            </section>
        </div>
    </HomeLayout>
</template>

<style scoped>
.phone-mock,
.big-phone {
    position: absolute;
    width: 11.25rem;
    height: 22.8rem;
    border-radius: 2.35rem;
    background: #111827;
    padding: 0.55rem;
    box-shadow: 0 1.9rem 3.5rem rgba(15, 23, 42, 0.24);
}

.big-phone {
    left: 50%;
    top: 50%;
    z-index: 2;
    width: 13.75rem;
    height: 28rem;
    transform: translate(-50%, -50%);
}

.phone-owner {
    left: 1.5rem;
    top: 4.5rem;
    transform: rotate(-8deg);
}

.phone-employee {
    left: 38%;
    top: 7rem;
    z-index: 3;
    transform: rotate(3deg);
}

.phone-renter {
    right: 0.25rem;
    top: 3.5rem;
    transform: rotate(10deg);
}

.phone-screen {
    height: 100%;
    border-radius: 1.85rem;
    background: #f8fafc;
    padding: 1rem 0.75rem;
}

.phone-notch {
    width: 4rem;
    height: 0.9rem;
    margin: -0.45rem auto 0.8rem;
    border-radius: 999px;
    background: #111827;
}

.screen-muted {
    font-size: 0.58rem;
    font-weight: 800;
    color: #8791a4;
    text-transform: uppercase;
}

.screen-title {
    margin-top: 0.2rem;
    font-size: 0.9rem;
    font-weight: 900;
    color: #111827;
}

.screen-stat {
    margin-top: 1rem;
    border-radius: 0.9rem;
    background: linear-gradient(135deg, hsl(var(--primary)), #4338ca);
    padding: 0.8rem;
    color: white;
}

.screen-stat span {
    display: block;
    font-size: 0.62rem;
    color: rgba(255, 255, 255, 0.78);
}

.screen-stat strong {
    display: block;
    margin-top: 0.25rem;
    font-size: 1.2rem;
}

.screen-grid {
    margin-top: 0.6rem;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.45rem;
}

.screen-grid div,
.screen-task {
    border: 1px solid #edf0f4;
    border-radius: 0.7rem;
    background: white;
    padding: 0.55rem;
    font-size: 0.55rem;
    color: #667085;
}

.screen-grid b {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.7rem;
    color: #111827;
}

.screen-task {
    margin-top: 0.5rem;
}

.screen-car {
    margin-top: 0.8rem;
    display: grid;
    height: 6.5rem;
    place-items: center;
    border-radius: 0.95rem;
    background: linear-gradient(145deg, #edf5ff, #f5edff);
}

.float-card {
    position: absolute;
    z-index: 4;
    border: 1px solid #e5e8ef;
    border-radius: 0.8rem;
    background: white;
    padding: 0.8rem 0.95rem;
    box-shadow: 0 0.9rem 2rem rgba(16, 24, 40, 0.12);
}

.float-card strong,
.float-card span {
    display: block;
}

.float-card strong {
    font-size: 0.78rem;
    color: #111827;
}

.float-card span {
    margin-top: 0.2rem;
    font-size: 0.68rem;
    color: #7f8798;
}

.store-button {
    display: inline-flex;
    min-height: 3.2rem;
    min-width: 10rem;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    border-radius: 0.5rem;
    border: 1px solid hsl(var(--border));
    background: white;
    padding: 0.5rem 0.95rem;
    font-size: 0.82rem;
    font-weight: 900;
    color: #111827;
    box-shadow: 0 0.5rem 1.4rem rgba(16, 24, 40, 0.05);
}

.store-button small {
    display: block;
    font-size: 0.55rem;
    font-weight: 700;
    color: #818899;
    text-transform: uppercase;
}

@media (max-width: 767px) {
    .phone-owner {
        left: 0;
    }

    .phone-employee {
        left: 31%;
    }

    .phone-renter {
        right: -2rem;
    }
}
</style>
