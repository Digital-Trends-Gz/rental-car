<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ExternalLink, Languages, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

type ComparisonRow = {
    label: string;
    tone?: string;
    values: string[];
};

type ComparisonSection = {
    title: string;
    rows: ComparisonRow[];
};

type PlansPage = {
    enabled?: boolean;
    hero_enabled?: boolean;
    summary_enabled?: boolean;
    comparison_enabled?: boolean;
    addons_enabled?: boolean;
    policy_enabled?: boolean;
    footer_enabled?: boolean;
    hero_badge: string;
    hero_title: string;
    hero_description: string;
    monthly_label: string;
    current_price_label: string;
    official_price_label: string;
    launch_discount_label: string;
    most_value_label: string;
    custom_price_label: string;
    custom_price_caption: string;
    custom_price_badge: string;
    unlimited_label: string;
    not_available_label: string;
    included_label: string;
    table_title: string;
    table_description: string;
    table_note: string;
    comparison_scroll_hint: string;
    feature_column_label: string;
    comparison_sections: ComparisonSection[];
    addons_title: string;
    addons: string[];
    trial_title: string;
    trial_items: string[];
    policy_title: string;
    policy_paragraphs: string[];
    footer_text: string;
};

const props = defineProps<{
    plansPage: PlansPage;
    previewUrl: string;
    translationsUrl: string;
    updateUrl: string;
}>();

const page = usePage<any>();
const form = useForm<{ plans_comparison_page: PlansPage }>({
    plans_comparison_page: {
        ...props.plansPage,
        enabled: props.plansPage.enabled !== false,
        hero_enabled: props.plansPage.hero_enabled !== false,
        summary_enabled: props.plansPage.summary_enabled !== false,
        comparison_enabled: props.plansPage.comparison_enabled !== false,
        addons_enabled: props.plansPage.addons_enabled !== false,
        policy_enabled: props.plansPage.policy_enabled !== false,
        footer_enabled: props.plansPage.footer_enabled !== false,
        comparison_sections: (props.plansPage.comparison_sections || []).map((section) => ({
            ...section,
            rows: (section.rows || []).map((row) => ({
                ...row,
                values: [...(row.values || [])],
            })),
        })),
        addons: [...(props.plansPage.addons || [])],
        trial_items: [...(props.plansPage.trial_items || [])],
        policy_paragraphs: [...(props.plansPage.policy_paragraphs || [])],
    },
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const formErrors = computed(() =>
    Object.values(form.errors ?? {}).filter((value): value is string => typeof value === 'string' && value.length > 0),
);

function submit(): void {
    form.put(props.updateUrl, { preserveScroll: true });
}

function addSection(): void {
    form.plans_comparison_page.comparison_sections.push({
        title: 'New section',
        rows: [{ label: 'New feature', tone: 'mixed', values: ['', '', '', ''] }],
    });
}

function removeSection(index: number): void {
    form.plans_comparison_page.comparison_sections.splice(index, 1);
}

function addRow(section: ComparisonSection): void {
    section.rows.push({ label: 'New feature', tone: 'mixed', values: ['', '', '', ''] });
}

function removeRow(section: ComparisonSection, index: number): void {
    section.rows.splice(index, 1);
}

function addListItem(key: 'addons' | 'trial_items' | 'policy_paragraphs'): void {
    form.plans_comparison_page[key].push('');
}

function removeListItem(key: 'addons' | 'trial_items' | 'policy_paragraphs', index: number): void {
    form.plans_comparison_page[key].splice(index, 1);
}
</script>

<template>
    <Head title="Plans Page" />

    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Plans Page</h1>
                    <p class="text-sm text-muted-foreground">Edit the public plans comparison page content and table rows.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2 rounded-md border px-3 py-2">
                        <Switch v-model:checked="form.plans_comparison_page.enabled" />
                        <span class="text-sm font-medium">{{ form.plans_comparison_page.enabled ? 'Page Shown' : 'Page Hidden' }}</span>
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
                        <CardDescription>Top page intro copy.</CardDescription>
                    </div>
                    <div class="flex items-center gap-2">
                        <Switch v-model:checked="form.plans_comparison_page.hero_enabled" />
                        <span class="text-sm font-medium">{{ form.plans_comparison_page.hero_enabled ? 'Shown' : 'Hidden' }}</span>
                    </div>
                </CardHeader>
                <CardContent class="grid gap-4 lg:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Badge</Label>
                        <Input v-model="form.plans_comparison_page.hero_badge" />
                    </div>
                    <div class="space-y-2">
                        <Label>Title</Label>
                        <Input v-model="form.plans_comparison_page.hero_title" />
                    </div>
                    <div class="space-y-2 lg:col-span-2">
                        <Label>Description</Label>
                        <Textarea v-model="form.plans_comparison_page.hero_description" rows="3" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <CardTitle>Plan Cards Labels</CardTitle>
                        <CardDescription>Labels used around live plan prices and custom pricing cards.</CardDescription>
                    </div>
                    <div class="flex items-center gap-2">
                        <Switch v-model:checked="form.plans_comparison_page.summary_enabled" />
                        <span class="text-sm font-medium">{{ form.plans_comparison_page.summary_enabled ? 'Shown' : 'Hidden' }}</span>
                    </div>
                </CardHeader>
                <CardContent class="grid gap-4 lg:grid-cols-3">
                    <div class="space-y-2"><Label>Monthly Label</Label><Input v-model="form.plans_comparison_page.monthly_label" /></div>
                    <div class="space-y-2"><Label>Current Price Label</Label><Input v-model="form.plans_comparison_page.current_price_label" /></div>
                    <div class="space-y-2"><Label>Official Price Label</Label><Input v-model="form.plans_comparison_page.official_price_label" /></div>
                    <div class="space-y-2"><Label>Launch Discount Label</Label><Input v-model="form.plans_comparison_page.launch_discount_label" /></div>
                    <div class="space-y-2"><Label>Most Value Label</Label><Input v-model="form.plans_comparison_page.most_value_label" /></div>
                    <div class="space-y-2"><Label>Unlimited Label</Label><Input v-model="form.plans_comparison_page.unlimited_label" /></div>
                    <div class="space-y-2"><Label>Custom Price Label</Label><Input v-model="form.plans_comparison_page.custom_price_label" /></div>
                    <div class="space-y-2"><Label>Custom Price Caption</Label><Input v-model="form.plans_comparison_page.custom_price_caption" /></div>
                    <div class="space-y-2"><Label>Custom Price Badge</Label><Input v-model="form.plans_comparison_page.custom_price_badge" /></div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <CardTitle>Comparison Table</CardTitle>
                        <CardDescription>Table heading, note, sections, and row values. Values map to the first four active plans.</CardDescription>
                    </div>
                    <div class="flex items-center gap-2">
                        <Switch v-model:checked="form.plans_comparison_page.comparison_enabled" />
                        <span class="text-sm font-medium">{{ form.plans_comparison_page.comparison_enabled ? 'Shown' : 'Hidden' }}</span>
                    </div>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="space-y-2"><Label>Title</Label><Input v-model="form.plans_comparison_page.table_title" /></div>
                        <div class="space-y-2"><Label>Note</Label><Input v-model="form.plans_comparison_page.table_note" /></div>
                        <div class="space-y-2"><Label>Mobile Scroll Hint</Label><Input v-model="form.plans_comparison_page.comparison_scroll_hint" /></div>
                        <div class="space-y-2"><Label>Feature Column Label</Label><Input v-model="form.plans_comparison_page.feature_column_label" /></div>
                        <div class="space-y-2 lg:col-span-2"><Label>Description</Label><Textarea v-model="form.plans_comparison_page.table_description" rows="2" /></div>
                    </div>
                    <div class="flex justify-end">
                        <Button type="button" variant="outline" size="sm" @click="addSection">
                            <Plus class="h-4 w-4" />
                            Add Section
                        </Button>
                    </div>
                    <div v-for="(section, sectionIndex) in form.plans_comparison_page.comparison_sections" :key="sectionIndex" class="space-y-4 rounded-lg border p-4">
                        <div class="flex items-center gap-2">
                            <Input v-model="section.title" class="font-semibold" />
                            <Button type="button" size="icon" variant="outline" @click="removeSection(sectionIndex)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                        <div v-for="(row, rowIndex) in section.rows" :key="rowIndex" class="grid gap-2 rounded-md bg-muted/30 p-3 lg:grid-cols-[1.3fr_repeat(4,1fr)_auto]">
                            <Input v-model="row.label" placeholder="Feature" />
                            <Input v-for="valueIndex in 4" :key="valueIndex" v-model="row.values[valueIndex - 1]" :placeholder="`Plan ${valueIndex}`" />
                            <Button type="button" size="icon" variant="outline" @click="removeRow(section, rowIndex)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="addRow(section)">
                            <Plus class="h-4 w-4" />
                            Add Row
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <CardTitle>Add-ons</CardTitle>
                            <CardDescription>Paid add-ons box.</CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Switch v-model:checked="form.plans_comparison_page.addons_enabled" />
                            <span class="text-sm font-medium">{{ form.plans_comparison_page.addons_enabled ? 'Shown' : 'Hidden' }}</span>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="space-y-2"><Label>Title</Label><Input v-model="form.plans_comparison_page.addons_title" /></div>
                        <div v-for="(_item, index) in form.plans_comparison_page.addons" :key="index" class="flex gap-2">
                            <Input v-model="form.plans_comparison_page.addons[index]" />
                            <Button type="button" size="icon" variant="outline" @click="removeListItem('addons', index)"><Trash2 class="h-4 w-4" /></Button>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="addListItem('addons')"><Plus class="h-4 w-4" /> Add Item</Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Free Trial</CardTitle>
                        <CardDescription>Free trial box next to add-ons.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="space-y-2"><Label>Title</Label><Input v-model="form.plans_comparison_page.trial_title" /></div>
                        <div v-for="(_item, index) in form.plans_comparison_page.trial_items" :key="index" class="flex gap-2">
                            <Input v-model="form.plans_comparison_page.trial_items[index]" />
                            <Button type="button" size="icon" variant="outline" @click="removeListItem('trial_items', index)"><Trash2 class="h-4 w-4" /></Button>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="addListItem('trial_items')"><Plus class="h-4 w-4" /> Add Item</Button>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <CardTitle>Pricing Policy</CardTitle>
                        <CardDescription>Bottom launch pricing policy section and footer line.</CardDescription>
                    </div>
                    <div class="flex items-center gap-2">
                        <Switch v-model:checked="form.plans_comparison_page.policy_enabled" />
                        <span class="text-sm font-medium">{{ form.plans_comparison_page.policy_enabled ? 'Shown' : 'Hidden' }}</span>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2"><Label>Policy Title</Label><Input v-model="form.plans_comparison_page.policy_title" /></div>
                    <div v-for="(_paragraph, index) in form.plans_comparison_page.policy_paragraphs" :key="index" class="flex gap-2">
                        <Textarea v-model="form.plans_comparison_page.policy_paragraphs[index]" rows="2" />
                        <Button type="button" size="icon" variant="outline" @click="removeListItem('policy_paragraphs', index)"><Trash2 class="h-4 w-4" /></Button>
                    </div>
                    <Button type="button" variant="outline" size="sm" @click="addListItem('policy_paragraphs')"><Plus class="h-4 w-4" /> Add Paragraph</Button>
                    <div class="flex items-center gap-2 pt-2">
                        <Switch v-model:checked="form.plans_comparison_page.footer_enabled" />
                        <span class="text-sm font-medium">{{ form.plans_comparison_page.footer_enabled ? 'Footer Shown' : 'Footer Hidden' }}</span>
                    </div>
                    <div class="space-y-2"><Label>Footer Text</Label><Input v-model="form.plans_comparison_page.footer_text" /></div>
                </CardContent>
            </Card>
        </main>
    </SuperAdminLayout>
</template>
