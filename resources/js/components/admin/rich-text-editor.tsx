import Highlight from '@tiptap/extension-highlight';
import Link from '@tiptap/extension-link';
import { Color, TextStyle } from '@tiptap/extension-text-style';
import { EditorContent, useEditor, type Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Baseline,
    Bold,
    Eraser,
    Heading2,
    Heading3,
    Highlighter,
    Italic,
    Link2,
    List,
    ListOrdered,
    Quote,
    Unlink,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect } from 'react';

const DEFAULT_TEXT_COLOR = '#111827';
const DEFAULT_HIGHLIGHT_COLOR = '#fef08a';

const EMPTY = '<p></p>';

type RichTextEditorProps = {
    value: string;
    onChange: (html: string) => void;
};

export function RichTextEditor({ value, onChange }: RichTextEditorProps) {
    const editor = useEditor({
        immediatelyRender: false,
        extensions: [
            StarterKit.configure({
                link: false,
                heading: { levels: [2, 3] },
            }),
            Link.configure({ openOnClick: false, autolink: true }),
            TextStyle,
            Color,
            Highlight.configure({ multicolor: true }),
        ],
        content: value || EMPTY,
        editorProps: {
            attributes: {
                class: 'rich-text min-h-[12rem] px-3 py-2 focus:outline-none',
            },
        },
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
    });

    // Sync when the parent swaps to a different section while not editing.
    useEffect(() => {
        if (!editor) {
            return;
        }

        const next = value || EMPTY;
        if (editor.getHTML() !== next && !editor.isFocused) {
            editor.commands.setContent(next);
        }
    }, [value, editor]);

    if (!editor) {
        return null;
    }

    return (
        <div className="overflow-hidden rounded-md border border-neutral-200 dark:border-neutral-800">
            <Toolbar editor={editor} />
            <EditorContent editor={editor} />
        </div>
    );
}

function Toolbar({ editor }: { editor: Editor }) {
    const setLink = () => {
        const previous = editor.getAttributes('link').href as string | undefined;
        const url = window.prompt('URL del enlace', previous ?? 'https://');

        if (url === null) {
            return;
        }

        if (url === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();

            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: url })
            .run();
    };

    return (
        <div className="flex flex-wrap items-center gap-1 border-b border-neutral-200 bg-neutral-50 p-1.5 dark:border-neutral-800 dark:bg-neutral-900">
            <ToolbarButton
                onClick={() => editor.chain().focus().toggleBold().run()}
                active={editor.isActive('bold')}
                label="Negrita"
            >
                <Bold className="size-4" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => editor.chain().focus().toggleItalic().run()}
                active={editor.isActive('italic')}
                label="Cursiva"
            >
                <Italic className="size-4" />
            </ToolbarButton>
            <Divider />
            <ToolbarButton
                onClick={() =>
                    editor.chain().focus().toggleHeading({ level: 2 }).run()
                }
                active={editor.isActive('heading', { level: 2 })}
                label="Título"
            >
                <Heading2 className="size-4" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() =>
                    editor.chain().focus().toggleHeading({ level: 3 }).run()
                }
                active={editor.isActive('heading', { level: 3 })}
                label="Subtítulo"
            >
                <Heading3 className="size-4" />
            </ToolbarButton>
            <Divider />
            <ToolbarButton
                onClick={() => editor.chain().focus().toggleBulletList().run()}
                active={editor.isActive('bulletList')}
                label="Lista"
            >
                <List className="size-4" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => editor.chain().focus().toggleOrderedList().run()}
                active={editor.isActive('orderedList')}
                label="Lista numerada"
            >
                <ListOrdered className="size-4" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => editor.chain().focus().toggleBlockquote().run()}
                active={editor.isActive('blockquote')}
                label="Cita"
            >
                <Quote className="size-4" />
            </ToolbarButton>
            <Divider />
            <ToolbarButton
                onClick={setLink}
                active={editor.isActive('link')}
                label="Enlace"
            >
                <Link2 className="size-4" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() =>
                    editor.chain().focus().extendMarkRange('link').unsetLink().run()
                }
                active={false}
                disabled={!editor.isActive('link')}
                label="Quitar enlace"
            >
                <Unlink className="size-4" />
            </ToolbarButton>
            <Divider />
            <ColorPicker
                icon={Baseline}
                label="Color de texto"
                value={
                    (editor.getAttributes('textStyle').color as string) ||
                    DEFAULT_TEXT_COLOR
                }
                onChange={(color) =>
                    editor.chain().focus().setColor(color).run()
                }
            />
            <ColorPicker
                icon={Highlighter}
                label="Resaltado"
                value={
                    (editor.getAttributes('highlight').color as string) ||
                    DEFAULT_HIGHLIGHT_COLOR
                }
                onChange={(color) =>
                    editor.chain().focus().setHighlight({ color }).run()
                }
            />
            <ToolbarButton
                onClick={() =>
                    editor.chain().focus().unsetColor().unsetHighlight().run()
                }
                active={false}
                label="Quitar color y resaltado"
            >
                <Eraser className="size-4" />
            </ToolbarButton>
        </div>
    );
}

function ColorPicker({
    icon: Icon,
    value,
    label,
    onChange,
}: {
    icon: LucideIcon;
    value: string;
    label: string;
    onChange: (color: string) => void;
}) {
    return (
        <label
            title={label}
            aria-label={label}
            className="relative flex size-8 cursor-pointer items-center justify-center rounded text-neutral-600 transition hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800"
        >
            <Icon className="size-4" />
            <span
                className="absolute inset-x-1.5 bottom-1 h-1 rounded-full"
                style={{ backgroundColor: value }}
            />
            <input
                type="color"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="absolute inset-0 cursor-pointer opacity-0"
            />
        </label>
    );
}

function ToolbarButton({
    onClick,
    active,
    disabled = false,
    label,
    children,
}: {
    onClick: () => void;
    active: boolean;
    disabled?: boolean;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            aria-label={label}
            title={label}
            className={`flex size-8 items-center justify-center rounded transition disabled:opacity-40 ${
                active
                    ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                    : 'text-neutral-600 hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800'
            }`}
        >
            {children}
        </button>
    );
}

function Divider() {
    return <span className="mx-1 h-5 w-px bg-neutral-300 dark:bg-neutral-700" />;
}
