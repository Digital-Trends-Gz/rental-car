<script setup lang="ts">
import SeoHead from '@/components/SeoHead.vue';
import { Button } from '@/components/ui/button';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type ProviderOption = {
    code: 'stripe' | 'myfatoorah';
    name: string;
    description?: string | null;
    is_ready: boolean;
};

const props = defineProps<{
    reservation: {
        id: number;
        reservation_number: string;
        total_amount: number;
        currency: string;
        car?: {
            make?: string | null;
            model?: string | null;
            year?: number | string | null;
            image_url?: string | null;
        } | null;
    };
    providers: ProviderOption[];
    selectedProvider?: string | null;
    actions: {
        checkout: string;
        cancel: string;
    };
    seo?: {
        title?: string | null;
        description?: string | null;
        canonical_url?: string | null;
        robots?: string | null;
        og_title?: string | null;
        og_description?: string | null;
        og_image?: string | null;
        alternates?: Array<{ locale: string; url: string }>;
        schemas?: Array<Record<string, unknown>>;
    } | null;
}>();

const selectedProvider = ref<string>(
    props.selectedProvider && props.providers.some((p) => p.code === props.selectedProvider)
        ? props.selectedProvider
        : (props.providers[0]?.code ?? ''),
);

const selectedProviderMeta = computed(
    () => props.providers.find((provider) => provider.code === selectedProvider.value) ?? null,
);

function continueCheckout() {
    if (!selectedProvider.value) return;

    router.get(
        props.actions.checkout,
        { provider: selectedProvider.value },
        { preserveScroll: true },
    );
}
</script>

<template>
    <HomeLayout>
        <SeoHead :seo="props.seo || null" />
        <main class="min-h-screen bg-slate-50 py-10">
            <div class="mx-auto max-w-4xl px-4">
                <div class="mb-6">
                    <Link :href="actions.cancel" class="text-sm font-medium text-slate-700 hover:underline">
                        Cancel payment and return to cars
                    </Link>
                </div>

                <div class="mb-8">
                    <p class="text-sm font-semibold text-blue-700">Payment</p>
                    <h1 class="mt-2 text-3xl font-bold text-slate-900">Choose payment provider</h1>
                    <p class="mt-2 text-slate-600">
                        Select how you want to pay for reservation
                        <span class="font-semibold">{{ reservation.reservation_number }}</span>.
                    </p>
                </div>

                <div class="grid gap-6 lg:grid-cols-5">
                    <section class="rounded-2xl border bg-white p-5 lg:col-span-3">
                        <h2 class="mb-4 text-lg font-semibold text-slate-900">Available providers</h2>

                        <div class="space-y-3">
                            <label
                                v-for="provider in providers"
                                :key="provider.code"
                                class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition"
                                :class="selectedProvider === provider.code ? 'border-blue-500 bg-blue-50/50' : 'border-slate-200'"
                            >
                                <input
                                    v-model="selectedProvider"
                                    :value="provider.code"
                                    type="radio"
                                    class="mt-1"
                                >
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-slate-900">{{ provider.name }}</span>
                                        <span
                                            class="rounded px-2 py-0.5 text-xs"
                                            :class="provider.is_ready ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                        >
                                            {{ provider.is_ready ? 'Ready' : 'Not ready' }}
                                        </span>
                                    </div>
                                    <p v-if="provider.description" class="text-sm text-slate-500">
                                        {{ provider.description }}
                                    </p>
                                </div>
                            </label>

                            <div v-if="providers.length === 0" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                No online payment providers are available for this tenant.
                            </div>
                        </div>

                        <div class="mt-6 flex items-center gap-3">
                            <Button
                                type="button"
                                class="min-w-44"
                                :disabled="!selectedProvider || providers.length === 0"
                                @click="continueCheckout"
                            >
                                Continue to {{ selectedProviderMeta?.name || 'payment' }}
                            </Button>
                            <Link :href="actions.cancel">
                                <Button type="button" variant="outline">Cancel</Button>
                            </Link>
                        </div>
                    </section>

                    <aside class="rounded-2xl border bg-white p-5 lg:col-span-2">
                        <h2 class="mb-4 text-lg font-semibold text-slate-900">Order summary</h2>

                        <div class="space-y-3 text-sm">
                            <p class="text-slate-500">Reservation</p>
                            <p class="font-medium text-slate-900">{{ reservation.reservation_number }}</p>

                            <div v-if="reservation.car" class="rounded-md border bg-slate-50 p-3">
                                <p class="font-medium text-slate-900">
                                    {{ reservation.car.year }} {{ reservation.car.make }} {{ reservation.car.model }}
                                </p>
                            </div>

                            <div class="border-t pt-4">
                                <p class="text-slate-500">Total</p>
                                <p class="text-3xl font-bold text-slate-900">
                                    {{ reservation.currency }} {{ Number(reservation.total_amount).toFixed(2) }}
                                </p>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </main>
    </HomeLayout>
</template>
