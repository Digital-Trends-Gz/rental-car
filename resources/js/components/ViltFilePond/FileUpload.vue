<script setup lang="ts">
import { ref, onMounted, watch, computed } from "vue";
import vueFilePond from "vue-filepond";
import FilePondPluginFileValidateType from "filepond-plugin-file-validate-type";
import FilePondPluginFileValidateSize from "filepond-plugin-file-validate-size";
import FilePondPluginImagePreview from "filepond-plugin-image-preview";
import { setOptions } from "filepond";
import { usePage } from "@inertiajs/vue3";

// Import locales
import ar_AR from "filepond/locale/ar-ar";
import fr_FR from "filepond/locale/fr-fr";
import es_ES from "filepond/locale/es-es";

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    initialFiles: {
        type: Array,
        default: () => [],
    },
    allowedFileTypes: {
        type: Array,
        default: () => [
            "image/jpeg",
            "image/png",
        ],
    },
    allowMultiple: {
        type: Boolean,
        default: false,
    },
    maxFiles: {
        type: Number,
        default: 1,
    },
    maxFileSize: {
        type: Number,
        default: 1024 * 1024 * 5, // 5MB
    },
    collection: {
        type: String,
        default: "default",
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
    theme: {
        type: String,
        default: "light",
        validator: (value) => ["light", "dark"].includes(value),
    },
    width: {
        type: String,
        default: "100%",
    },
    instantUpload: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits([
    "update:modelValue",
    "fileAdded",
    "localFileAdded",
    "fileRemoved",
    "error",
]);

// Reactive state
const page = usePage<any>();
const files = ref<any[]>([]);
const tempFolders = ref<string[]>([]);
const filePondRef = ref<any>(null);
const uploadError = ref("");

const wrapperStyle = computed(() => ({
    width: props.width,
}));

const csrfToken = computed(() => {
    const tokenFromProps = page.props.csrf_token;

    if (tokenFromProps) {
        return String(tokenFromProps);
    }

    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content") || ""
    );
});

const requestHeaders = computed(() => ({
    "X-CSRF-TOKEN": csrfToken.value,
    "X-Requested-With": "XMLHttpRequest",
    Accept: "application/json",
}));

// Locale configuration
const LOCALE_MAP: Record<string, any> = {
    ar: ar_AR,
    fr: fr_FR,
    es: es_ES,
    null: null, // English (default)
};

// Initialize FilePond component
const FilePond = vueFilePond(
    FilePondPluginFileValidateType,
    FilePondPluginFileValidateSize,
    FilePondPluginImagePreview
);

// Initialize locale
function initializeLocale() {
    const configuredLocale = page.props.fileUploadConfig?.locale || null;
    const localeOptions = LOCALE_MAP[configuredLocale];

    if (localeOptions) {
        setOptions(localeOptions);
    }
}

// Initialize chunk file size
const chunkFileSize =
    page.props.fileUploadConfig?.chunkSize || 1024 * 1024 * 10; // 10MB

// Helper: Fallback route builder if Ziggy's route() is not available
function routeOrFallback(name: string, params: Record<string, any> = {}) {
    const hasRoute = typeof window !== 'undefined' && typeof (window as any).route === 'function';
    if (hasRoute) {
        return (window as any).route(name, params);
    }
    const baseMap = {
        'filepond.upload': '/filepond/upload',
        'filepond.patch': '/filepond/patch',
        'filepond.revert': '/filepond/revert',
        'filepond.restore': '/filepond/restore',
    };
    const base = (baseMap as any)[name] || '';
    if (name === 'filepond.revert' && (params as any).folder) {
        return `${base}/${encodeURIComponent((params as any).folder)}`;
    }
    return base;
}

// Parse upload response (handles both JSON and plain text)
function parseUploadResponse(responseText: any) {
    // Check if it's an XMLHttpRequest object and extract responseText
    if (
        responseText &&
        typeof responseText === "object" &&
        responseText.responseText !== undefined
    ) {
        responseText = responseText.responseText;
    }

    // Handle empty or null responses
    if (!responseText || responseText === "" || responseText === "null") {
        return null;
    }

    try {
        // If it's already a parsed object
        if (typeof responseText === "object" && responseText !== null) {
            return responseText.folder || responseText;
        }

        const response = JSON.parse(responseText);
        return typeof response === "string"
            ? response
            : response.folder || response;
    } catch {
        const stringResponse =
            typeof responseText === "string"
                ? responseText.trim()
                : String(responseText).trim();

        // Check for invalid responses
        if (
            stringResponse.includes("[object") ||
            stringResponse === "null" ||
            stringResponse === ""
        ) {
            console.error("Invalid response format:", stringResponse);
            return null;
        }

        return stringResponse;
    }
}

// Add temporary folder to state
function addTempFolder(folder: string, file: any) {
    uploadError.value = "";
    tempFolders.value.push(folder);
    emit("fileAdded", { folder, file });
    emit("update:modelValue", [...tempFolders.value]);
}

function uploadErrorMessage(response: any, fallback: string) {
    if (!response) {
        return fallback;
    }

    if (typeof response === "string") {
        return response;
    }

    if (response.responseText) {
        try {
            const parsed = JSON.parse(response.responseText);
            return parsed.error || parsed.message || fallback;
        } catch {
            return response.responseText || fallback;
        }
    }

    if (response.status) {
        return `${fallback} (${response.status})`;
    }

    return fallback;
}

// Handle file revert (removal of temporary files)
function handleRevert(uniqueId: string, load: any, error: (msg: string) => void) {
    if (!uniqueId) {
        error("No unique ID provided");
        return;
    }

    const index = tempFolders.value.indexOf(uniqueId);
    if (index === -1) {
        error("File not found");
        return;
    }

    // Optimistically remove from UI
    tempFolders.value.splice(index, 1);
    emit("update:modelValue", [...tempFolders.value]);

    // Send delete request to server
    fetch(routeOrFallback("filepond.revert", { folder: uniqueId }), {
        method: "DELETE",
        credentials: "same-origin",
        headers: requestHeaders.value,
    })
        .then((response) => {
            if (response.ok) {
                emit("fileRemoved", { folder: uniqueId, type: "temp" });
                load();
            } else {
                // Restore folder on server error
                tempFolders.value.splice(index, 0, uniqueId);
                emit("update:modelValue", [...tempFolders.value]);
                error("Failed to delete file from server");
            }
        })
        .catch(() => {
            // Restore folder on network error
            tempFolders.value.splice(index, 0, uniqueId);
            emit("update:modelValue", [...tempFolders.value]);
            error("Failed to delete file");
        });
}

// Handle removal of existing files
function handleFileRemove(error: any, file: any) {
    if (error) return;

    // Check if this is a local file (existing file)
    if ((file.origin === 3 || file.origin === 1) && file.source) {
        const existingFile = (props.initialFiles as any[]).find(
            (f: any) => f.url === file.source
        ) as any;

        if (existingFile?.id) {
            emit("fileRemoved", {
                fileId: existingFile.id,
                type: "existing",
                file: existingFile,
            });
        }
    }
}

function handleFileAdd(error: any, file: any) {
    if (error || !file?.file) {
        return;
    }

    emit("localFileAdded", file.file);
}

function emitLocalFile(fileItem: any) {
    const file = fileItem?.file || fileItem;

    if (file instanceof File) {
        emit("localFileAdded", file);
    }
}

function emitLocalFileFromItems(items: any[]) {
    for (const item of items || []) {
        const file = item?.file || item;

        if (file instanceof File) {
            emit("localFileAdded", file);
            return;
        }
    }
}

// Public method to reset component state
function resetFiles() {
    files.value = [];
    tempFolders.value = [];
    emit("update:modelValue", []);

    // Clear FilePond instance if available
    if (filePondRef.value) {
        filePondRef.value.removeFiles();
    }
}

// Server configuration for FilePond
const serverOptions: any = {
    process: {
        url: routeOrFallback("filepond.upload"),
        method: "POST",
        withCredentials: true,
        headers: requestHeaders.value,
        ondata: (formData: FormData) => {
            if (props.collection) {
                formData.append("collection", props.collection);
            }
            return formData;
        },
        onload: (response: any) => {
            // Extract response text from XMLHttpRequest object
            let responseText = response;
            if (
                response &&
                typeof response === "object" &&
                response.responseText !== undefined
            ) {
                responseText = response.responseText;
            }

            const result = parseUploadResponse(responseText);
            if (result) {
                addTempFolder(result, null);
            }
            return result;
        },
        onerror: (response) => {
            uploadError.value = uploadErrorMessage(response, "Upload failed");
            emit("error", uploadError.value);
            console.error("Upload error:", response);
        },
    },
    patch: {
        url: routeOrFallback("filepond.patch") + "?patch=",
        method: "PATCH",
        withCredentials: true,
        headers: requestHeaders.value,
        onload: (response) => {
            // Extract response text from XMLHttpRequest object
            let responseText = response;
            if (
                response &&
                typeof response === "object" &&
                response.responseText !== undefined
            ) {
                responseText = response.responseText;
            }

            // For chunk uploads, response might be empty for intermediate chunks
            if (
                !responseText ||
                responseText === "" ||
                responseText === "null"
            ) {
                return null;
            }

            const result = parseUploadResponse(responseText);

            if (result && result !== "null") {
                addTempFolder(result, null);
                return result;
            }
            return null;
        },
        onerror: (response) => {
            uploadError.value = uploadErrorMessage(response, "Chunk upload failed");
            emit("error", uploadError.value);
            console.error("Patch error:", response);
        },
    },
    revert: handleRevert,
    restore: routeOrFallback("filepond.restore") + "?restore=",
    load: (source: string, load: (b: Blob) => void, error: (msg: string) => void) => {
        fetch(source)
            .then((response) => response.blob())
            .then(load)
            .catch(() => error("Could not load file"));
    },
};

// FilePond component options - made reactive with computed
const filePondOptions = computed(() => ({
    server: serverOptions,
    name: "filepond",
    instantUpload: props.instantUpload,
    beforeAddFile: (fileItem: any) => {
        emitLocalFile(fileItem);
        return true;
    },
    onaddfile: (_error: any, fileItem: any) => {
        emitLocalFile(fileItem);
    },
    oninitfile: (fileItem: any) => {
        emitLocalFile(fileItem);
    },
    allowMultiple: props.allowMultiple,
    acceptedFileTypes: props.allowedFileTypes,
    maxFiles: props.maxFiles,
    maxFileSize: props.maxFileSize,
    credits: "none",
    disabled: props.disabled,
    required: props.required,
    chunkUploads: false,
    chunkSize: chunkFileSize,
    chunkRetryDelays: [500, 1000, 3000],
    chunkForce: false,
}));

// Watch for external modelValue changes - simplified
watch(
    () => props.modelValue,
    (newValue: any) => {
        const currentValue = tempFolders.value;
        if (JSON.stringify(newValue) !== JSON.stringify(currentValue)) {
            tempFolders.value = [...(((newValue || []) as string[]))];
        }
    },
    { deep: true }
);

watch(
    files,
    (newFiles: any[]) => {
        emitLocalFileFromItems(newFiles || []);
    },
    { deep: true }
);

// Initialize component
onMounted(() => {
    initializeLocale();

    // Initialize modelValue
    if ((props.modelValue as any[])?.length > 0) {
        tempFolders.value = [...((props.modelValue as any[]) as string[])];
    }

    // Initialize initial files
    if ((props.initialFiles as any[])?.length > 0) {
        files.value = (props.initialFiles as any[]).map((file: any) => ({
            source: file.url,
            options: { type: "local" },
        }));
    }
});

// Expose public methods
defineExpose({
    resetFiles,
});
</script>

<template>
    <div
        class="filepond-wrapper"
        :class="{ 'filepond-dark': theme === 'dark' }"
        :style="wrapperStyle"
    >
        <FilePond
            ref="filePondRef"
            v-model="files"
            v-bind="filePondOptions"
            :files="files"
            @addfile="handleFileAdd"
            @removefile="handleFileRemove"
        />
        <input
            v-if="required"
            type="hidden"
            :value="tempFolders.length > 0 ? 'has-files' : ''"
            :required="required"
        />
        <p v-if="uploadError" class="mt-2 text-xs text-red-600">
            {{ uploadError }}
        </p>
    </div>
</template>
