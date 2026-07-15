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

const normalizeUrl = (value: string) => {
    const url = value.trim();

    if (url === '') {
        return '';
    }

    return /^(https?:|mailto:|tel:)/i.test(url) ? url : `https://${url}`;
};

const editor = useEditor({
    content: props.modelValue || '',
    extensions: [
        StarterKit.configure({
            heading: {
                levels: [2, 3, 4],
            },
            link: {
                openOnClick: false,
                HTMLAttributes: {
                    rel: 'noopener noreferrer',
                    target: '_blank',
                },
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

const setLink = () => {
    const previousUrl = editor.value?.getAttributes('link').href ?? '';
    const url = normalizeUrl(window.prompt('Link URL', previousUrl) ?? '');

    if (!editor.value) {
        return;
    }

    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }

    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
};
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
                :variant="editor.isActive('underline') ? 'default' : 'outline'"
                @click="editor.chain().focus().toggleUnderline().run()"
            >
                Underline
            </Button>
            <Button
                type="button"
                size="sm"
                :variant="editor.isActive('strike') ? 'default' : 'outline'"
                @click="editor.chain().focus().toggleStrike().run()"
            >
                Strike
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
            <Button
                type="button"
                size="sm"
                :variant="editor.isActive('blockquote') ? 'default' : 'outline'"
                @click="editor.chain().focus().toggleBlockquote().run()"
            >
                Quote
            </Button>
            <Button type="button" size="sm" variant="outline" @click="editor.chain().focus().setHorizontalRule().run()">
                Line
            </Button>
            <Button
                type="button"
                size="sm"
                :variant="editor.isActive('link') ? 'default' : 'outline'"
                @click="setLink"
            >
                Link
            </Button>
            <Button type="button" size="sm" variant="outline" @click="editor.chain().focus().extendMarkRange('link').unsetLink().run()">
                Unlink
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

.rich-text-editor__content blockquote {
    border-inline-start: 3px solid hsl(var(--border));
    color: hsl(var(--muted-foreground));
    padding-inline-start: 1rem;
}

.rich-text-editor__content a {
    color: hsl(var(--primary));
    text-decoration: underline;
}

.rich-text-editor__content hr {
    border: 0;
    border-top: 1px solid hsl(var(--border));
    margin: 1rem 0;
}
</style>
