<script setup lang="ts">
import { Button } from '@/components/ui/button';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        dir?: 'ltr' | 'rtl';
        placeholder?: string;
    }>(),
    {
        dir: 'ltr',
        placeholder: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const editor = useEditor({
    content: props.modelValue || '',
    extensions: [
        StarterKit.configure({
            heading: {
                levels: [2, 3, 4],
            },
        }),
    ],
    editorProps: {
        attributes: {
            class: 'rich-text-editor__content',
            dir: props.dir,
            'data-placeholder': props.placeholder,
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

watch(
    () => props.modelValue,
    (value) => {
        if (!editor.value || editor.value.getHTML() === value) {
            return;
        }

        editor.value.commands.setContent(value || '', false);
    },
);

watch(
    () => props.dir,
    (dir) => {
        editor.value?.setOptions({
            editorProps: {
                attributes: {
                    class: 'rich-text-editor__content',
                    dir,
                    'data-placeholder': props.placeholder,
                },
            },
        });
    },
);

watch(
    () => props.placeholder,
    (placeholder) => {
        editor.value?.setOptions({
            editorProps: {
                attributes: {
                    class: 'rich-text-editor__content',
                    dir: props.dir,
                    'data-placeholder': placeholder,
                },
            },
        });
    },
);
</script>

<template>
    <div class="overflow-hidden rounded-md border bg-background shadow-sm">
        <div v-if="editor" class="flex flex-wrap gap-1 border-b bg-muted/30 p-2">
            <Button
                type="button"
                size="sm"
                :variant="editor.isActive('bold') ? 'default' : 'outline'"
                @click="editor.chain().focus().toggleBold().run()"
            >
                Bold
            </Button>
            <Button
                type="button"
                size="sm"
                :variant="editor.isActive('italic') ? 'default' : 'outline'"
                @click="editor.chain().focus().toggleItalic().run()"
            >
                Italic
            </Button>
            <Button
                type="button"
                size="sm"
                :variant="editor.isActive('heading', { level: 2 }) ? 'default' : 'outline'"
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
            >
                H2
            </Button>
            <Button
                type="button"
                size="sm"
                :variant="editor.isActive('heading', { level: 3 }) ? 'default' : 'outline'"
                @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
            >
                H3
            </Button>
            <Button
                type="button"
                size="sm"
                :variant="editor.isActive('bulletList') ? 'default' : 'outline'"
                @click="editor.chain().focus().toggleBulletList().run()"
            >
                Bullets
            </Button>
            <Button
                type="button"
                size="sm"
                :variant="editor.isActive('orderedList') ? 'default' : 'outline'"
                @click="editor.chain().focus().toggleOrderedList().run()"
            >
                Numbers
            </Button>
            <Button type="button" size="sm" variant="outline" @click="editor.chain().focus().unsetAllMarks().clearNodes().run()">
                Clear
            </Button>
        </div>

        <EditorContent :editor="editor" />
    </div>
</template>

<style>
.rich-text-editor__content {
    min-height: 16rem;
    padding: 1rem;
    outline: none;
}

.rich-text-editor__content > * + * {
    margin-top: 0.75rem;
}

.rich-text-editor__content h2 {
    font-size: 1.35rem;
    font-weight: 700;
    line-height: 1.35;
}

.rich-text-editor__content h3 {
    font-size: 1.15rem;
    font-weight: 700;
    line-height: 1.4;
}

.rich-text-editor__content h4 {
    font-size: 1rem;
    font-weight: 700;
}

.rich-text-editor__content ul {
    list-style: disc;
    padding-inline-start: 1.5rem;
}

.rich-text-editor__content ol {
    list-style: decimal;
    padding-inline-start: 1.5rem;
}

.rich-text-editor__content p {
    line-height: 1.75;
}

.rich-text-editor__content strong {
    font-weight: 700;
}

.rich-text-editor__content em {
    font-style: italic;
}
</style>
