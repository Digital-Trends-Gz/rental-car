<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import ClientLayout from '@/layouts/ClientLayout.vue';
import { update } from '@/routes/client/profile';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    profile: {
        name: string;
        email: string;
        civil_number?: string | null;
        phone?: string | null;
        whatsapp?: string | null;
    };
}>();

const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);
const { t } = useTrans();
const tr = (key: string) => t(`client_pages.profile.${key}`);

const form = useForm({
    name: props.profile.name ?? '',
    email: props.profile.email ?? '',
    civil_number: props.profile.civil_number ?? '',
    phone: props.profile.phone ?? '',
    whatsapp: props.profile.whatsapp ?? '',
});

function submit() {
    if (!subdomain.value) {
        return;
    }

    form.put(update(subdomain.value).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="tr('head_title')" />

    <ClientLayout>
        <main class="mx-auto w-full max-w-3xl space-y-6 p-6">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">{{ tr('title') }}</h1>
                <p class="text-sm text-muted-foreground">{{ tr('subtitle') }}</p>
            </div>

            <form @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ tr('card_title') }}</CardTitle>
                        <CardDescription>{{ tr('card_description') }}</CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-5">
                        <div class="space-y-2">
                            <Label for="name">{{ tr('full_name') }}</Label>
                            <Input id="name" v-model="form.name" type="text" required autocomplete="name" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="email">{{ tr('email') }}</Label>
                            <Input id="email" v-model="form.email" type="email" required autocomplete="email" />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="civil_number">{{ tr('civil_number') }}</Label>
                            <Input id="civil_number" v-model="form.civil_number" type="text" required autocomplete="off" />
                            <InputError :message="form.errors.civil_number" />
                        </div>

                        <div class="space-y-2">
                            <Label for="phone">{{ tr('phone') }}</Label>
                            <Input id="phone" v-model="form.phone" type="tel" required dir="ltr" class="text-left" autocomplete="tel" />
                            <InputError :message="form.errors.phone" />
                        </div>

                        <div class="space-y-2">
                            <Label for="whatsapp">
                                {{ tr('whatsapp') }}
                                <span class="font-normal text-muted-foreground">({{ tr('optional') }})</span>
                            </Label>
                            <Input id="whatsapp" v-model="form.whatsapp" type="tel" dir="ltr" class="text-left" autocomplete="tel" />
                            <InputError :message="form.errors.whatsapp" />
                        </div>

                        <div class="flex justify-end">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? tr('saving') : tr('save_changes') }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </main>
    </ClientLayout>
</template>
