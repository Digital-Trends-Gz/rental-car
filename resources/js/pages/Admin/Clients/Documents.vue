<script setup lang="ts">
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';

type DocumentItem = {
    id?: number | null;
    document_type: string;
    label: string;
    description: string;
    extraction_status: string;
    extraction_status_label: string;
    extraction_provider?: string | null;
    extraction_engine?: string | null;
    confidence?: number | null;
    raw_text?: string | null;
    raw_output?: Record<string, unknown> | null;
    extracted_data?: Record<string, string> | null;
    approved_data?: Record<string, string> | null;
    reviewed_at?: string | null;
    files?: Array<{ id: number; url: string | null }>;
};

const props = defineProps<{
    client: {
        id: number;
        name: string;
        email: string;
        branch_name?: string | null;
    };
    documents: DocumentItem[];
    fieldSchema: Array<{ key: string; label: string }>;
    actions: {
        back: string;
        save: string;
        extract: string;
    };
    ocr: {
        enabled: boolean;
        python_binary: string;
    };
}>();

const page = usePage<any>();
const { raw } = useTrans();
const translationRoot = 'dashboard.admin.clients.documents';
const translate = (key: string, fallback = '') => {
    const value = raw(`${translationRoot}.${key}`, fallback);
    return typeof value === 'string' && value !== '' ? value : fallback;
};
const translateTypeLabel = (type: string, fallback = '') =>
    translate(`types.${type}.label`, fallback);
const translateTypeDescription = (type: string, fallback = '') =>
    translate(`types.${type}.description`, fallback);
const translateStatusLabel = (status: string, fallback = '') =>
    translate(`statuses.${status}`, fallback);

const allowedFileTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/jpg',
];

const tempFolders = reactive<Record<string, string[]>>({});
const removedFileIds = reactive<Record<string, number[]>>({});
const approvedData = reactive<Record<string, Record<string, string>>>({});
const extractedData = reactive<Record<string, Record<string, string>>>({});
const rawOutput = reactive<Record<string, Record<string, unknown> | null>>({});
const rawText = reactive<Record<string, string>>({});
const confidence = reactive<Record<string, number | null>>({});
const extractionProvider = reactive<Record<string, string>>({});
const extractionEngine = reactive<Record<string, string>>({});
const extracting = reactive<Record<string, boolean>>({});
const saving = reactive<Record<string, boolean>>({});
const messages = reactive<Record<string, string>>({});
const errors = reactive<Record<string, string>>({});

function buildSeed(document: DocumentItem) {
    const seed: Record<string, string> = {};

    props.fieldSchema.forEach((field) => {
        seed[field.key] = String(
            document.approved_data?.[field.key]
            ?? document.extracted_data?.[field.key]
            ?? '',
        );
    });

    return seed;
}

props.documents.forEach((document) => {
    tempFolders[document.document_type] = [];
    removedFileIds[document.document_type] = [];
    approvedData[document.document_type] = buildSeed(document);
    extractedData[document.document_type] = { ...(document.extracted_data ?? {}) };
    rawOutput[document.document_type] = document.raw_output ?? null;
    rawText[document.document_type] = document.raw_text ?? '';
    confidence[document.document_type] = document.confidence ?? null;
    extractionProvider[document.document_type] = document.extraction_provider ?? '';
    extractionEngine[document.document_type] = document.extraction_engine ?? '';
    extracting[document.document_type] = false;
    saving[document.document_type] = false;
    messages[document.document_type] = '';
    errors[document.document_type] = '';
});

function statusClasses(status: string) {
    if (status === 'reviewed') return 'bg-emerald-100 text-emerald-700';
    if (status === 'completed') return 'bg-blue-100 text-blue-700';
    if (status === 'failed') return 'bg-red-100 text-red-700';
    return 'bg-slate-100 text-slate-700';
}

function onFileRemoved(documentType: string, data: { type: string; fileId?: number }) {
    if (data.type !== 'existing' || !data.fileId) return;

    removedFileIds[documentType] = [...removedFileIds[documentType], data.fileId];
}

function applyExtracted(documentType: string, force = false) {
    const source = extractedData[documentType] ?? {};

    props.fieldSchema.forEach((field) => {
        const nextValue = source[field.key];
        if (nextValue === undefined || nextValue === null || nextValue === '') return;

        if (!force && approvedData[documentType][field.key]?.trim()) return;

        approvedData[documentType][field.key] = String(nextValue);
    });
}

async function extractDocument(documentType: string) {
    if (extracting[documentType]) return;
    if (!tempFolders[documentType]?.length) {
        errors[documentType] = translate('upload_file_first');
        return;
    }

    extracting[documentType] = true;
    errors[documentType] = '';
    messages[documentType] = '';

    try {
        const response = await fetch(props.actions.extract, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': String(page.props.csrf_token || ''),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                document_type: documentType,
                temp_folders: tempFolders[documentType],
            }),
        });

        const payload = await response.json();
        if (!response.ok) {
            errors[documentType] = payload?.message || translate('extraction_failed');
            return;
        }

        extractedData[documentType] = payload?.fields || {};
        rawOutput[documentType] = payload?.raw_output || null;
        rawText[documentType] = payload?.raw_text || '';
        confidence[documentType] = payload?.confidence ?? null;
        extractionProvider[documentType] = payload?.provider || '';
        extractionEngine[documentType] = payload?.engine || '';
        messages[documentType] = payload?.message || translate('extraction_completed');
        applyExtracted(documentType, false);
    } catch {
        errors[documentType] = translate('extraction_request_failed');
    } finally {
        extracting[documentType] = false;
    }
}

function saveDocument(documentType: string) {
    if (saving[documentType]) return;

    saving[documentType] = true;
    errors[documentType] = '';
    messages[documentType] = '';

    router.post(props.actions.save, {
        document_type: documentType,
        approved_data: approvedData[documentType],
        extracted_data: extractedData[documentType],
        raw_output: rawOutput[documentType] ? JSON.stringify(rawOutput[documentType]) : null,
        raw_text: rawText[documentType],
        confidence: confidence[documentType],
        extraction_provider: extractionProvider[documentType],
        extraction_engine: extractionEngine[documentType],
        temp_folders: tempFolders[documentType],
        removed_file_ids: removedFileIds[documentType],
    }, {
        preserveScroll: true,
        onSuccess: () => {
            messages[documentType] = translate('document_saved');
            tempFolders[documentType] = [];
            removedFileIds[documentType] = [];
        },
        onError: () => {
            errors[documentType] = translate('document_save_failed');
        },
        onFinish: () => {
            saving[documentType] = false;
        },
    });
}
</script>

<template>
    <Head :title="`${translate('title')} - ${client.name}`" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ translate('title') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ client.name }} · {{ client.email }}
                        <span v-if="client.branch_name">· {{ client.branch_name }}</span>
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ translate('local_ocr_status').replace(':status', translate(ocr.enabled ? 'local_ocr_enabled' : 'local_ocr_disabled')) }}
                        {{ translate('python_binary') }}: <code>{{ ocr.python_binary }}</code>
                    </p>
                </div>
                <Link :href="actions.back">
                    <Button variant="outline">{{ translate('back_to_client') }}</Button>
                </Link>
            </div>

            <div class="grid gap-6">
                <section
                    v-for="document in documents"
                    :key="document.document_type"
                    class="rounded-lg border bg-white p-5 shadow-sm"
                >
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">{{ translateTypeLabel(document.document_type, document.label) }}</h2>
                            <p class="text-sm text-muted-foreground">{{ translateTypeDescription(document.document_type, document.description) }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="statusClasses(document.extraction_status)"
                            >
                                {{ translateStatusLabel(document.extraction_status, document.extraction_status_label) }}
                            </span>
                            <span v-if="confidence[document.document_type] !== null" class="text-xs text-muted-foreground">
                                {{ translate('confidence') }} {{ Number(confidence[document.document_type]).toFixed(2) }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4">
                        <Label class="mb-2 block">{{ translate('document_file') }}</Label>
                        <FileUpload
                            v-model="tempFolders[document.document_type]"
                            :initial-files="document.files || []"
                            :allowed-file-types="allowedFileTypes"
                            :allow-multiple="false"
                            :max-files="1"
                            collection="scan"
                            theme="light"
                            width="100%"
                            @file-removed="(data: { type: string; fileId?: number }) => onFileRemoved(document.document_type, data)"
                        />
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="extracting[document.document_type]"
                            @click="extractDocument(document.document_type)"
                        >
                            {{ extracting[document.document_type] ? translate('extracting') : translate('run_ocr_extraction') }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            @click="applyExtracted(document.document_type, true)"
                        >
                            {{ translate('apply_extracted_to_all_fields') }}
                        </Button>
                        <Button
                            type="button"
                            :disabled="saving[document.document_type]"
                            @click="saveDocument(document.document_type)"
                        >
                            {{ saving[document.document_type] ? translate('saving') : translate('save_document') }}
                        </Button>
                    </div>

                    <div
                        v-if="messages[document.document_type]"
                        class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700"
                    >
                        {{ messages[document.document_type] }}
                    </div>

                    <div
                        v-if="errors[document.document_type]"
                        class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                    >
                        {{ errors[document.document_type] }}
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="field in fieldSchema" :key="`${document.document_type}-${field.key}`">
                            <Label :for="`${document.document_type}-${field.key}`">{{ translate(`fields.${field.key}`, field.label) }}</Label>
                            <Input
                                :id="`${document.document_type}-${field.key}`"
                                v-model="approvedData[document.document_type][field.key]"
                                class="mt-1"
                            />
                        </div>
                    </div>

                    <details class="mt-5 rounded-md border bg-slate-50 p-3">
                        <summary class="cursor-pointer text-sm font-medium">{{ translate('raw_ocr_output') }}</summary>
                        <div class="mt-3 grid gap-3">
                            <div>
                                <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ translate('raw_text') }}</div>
                                <pre class="mt-1 overflow-x-auto whitespace-pre-wrap text-xs">{{ rawText[document.document_type] || translate('no_ocr_text_yet') }}</pre>
                            </div>
                            <div>
                                <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ translate('json') }}</div>
                                <pre class="mt-1 overflow-x-auto whitespace-pre-wrap text-xs">{{ JSON.stringify(rawOutput[document.document_type] || {}, null, 2) }}</pre>
                            </div>
                        </div>
                    </details>
                </section>
            </div>
        </main>
    </AdminLayout>
</template>
