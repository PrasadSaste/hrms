/**
 * A rich text editor over a Markdown textarea.
 *
 * The textarea stays the source of truth: it is what the form submits, what
 * the preview reads and what the plain-text email is built from. The editor
 * loads its Markdown, writes Markdown back on every change and fires an
 * `input` event on the textarea so anything listening to it keeps working.
 *
 * Every button here produces Markdown the renderer can render: the app uses
 * GitHub-flavoured Markdown, so strikethrough, tables, task lists and fenced
 * code all survive the round trip. Nothing is offered that would be thrown
 * away on save.
 */
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { Markdown } from '@tiptap/markdown';
import { TableKit } from '@tiptap/extension-table';
import { TaskItem, TaskList } from '@tiptap/extension-list';

const ACTIONS = {
    // Marks
    bold: (chain) => chain.toggleBold(),
    italic: (chain) => chain.toggleItalic(),
    strike: (chain) => chain.toggleStrike(),
    code: (chain) => chain.toggleCode(),

    // Blocks
    paragraph: (chain) => chain.setParagraph(),
    heading: (chain) => chain.toggleHeading({ level: 1 }),
    subheading: (chain) => chain.toggleHeading({ level: 2 }),
    subsubheading: (chain) => chain.toggleHeading({ level: 3 }),
    quote: (chain) => chain.toggleBlockquote(),
    codeblock: (chain) => chain.toggleCodeBlock(),
    rule: (chain) => chain.setHorizontalRule(),

    // Lists
    bullets: (chain) => chain.toggleBulletList(),
    numbers: (chain) => chain.toggleOrderedList(),
    tasks: (chain) => chain.toggleTaskList(),

    // Tables
    table: (chain) => chain.insertTable({ rows: 3, cols: 3, withHeaderRow: true }),
    'row-before': (chain) => chain.addRowBefore(),
    'row-after': (chain) => chain.addRowAfter(),
    'row-delete': (chain) => chain.deleteRow(),
    'column-before': (chain) => chain.addColumnBefore(),
    'column-after': (chain) => chain.addColumnAfter(),
    'column-delete': (chain) => chain.deleteColumn(),
    'table-delete': (chain) => chain.deleteTable(),

    // Everything else
    unlink: (chain) => chain.extendMarkRange('link').unsetLink(),
    clear: (chain) => chain.unsetAllMarks().clearNodes(),
    undo: (chain) => chain.undo(),
    redo: (chain) => chain.redo(),
};

const ACTIVE = {
    bold: (editor) => editor.isActive('bold'),
    italic: (editor) => editor.isActive('italic'),
    strike: (editor) => editor.isActive('strike'),
    code: (editor) => editor.isActive('code'),
    heading: (editor) => editor.isActive('heading', { level: 1 }),
    subheading: (editor) => editor.isActive('heading', { level: 2 }),
    subsubheading: (editor) => editor.isActive('heading', { level: 3 }),
    quote: (editor) => editor.isActive('blockquote'),
    codeblock: (editor) => editor.isActive('codeBlock'),
    bullets: (editor) => editor.isActive('bulletList'),
    numbers: (editor) => editor.isActive('orderedList'),
    tasks: (editor) => editor.isActive('taskList'),
    link: (editor) => editor.isActive('link'),
    table: (editor) => editor.isActive('table'),
};

function toggleLink(editor) {
    const current = editor.getAttributes('link').href || '';
    const href = window.prompt('Link address', current);

    if (href === null) return;

    const chain = editor.chain().focus().extendMarkRange('link');

    if (href.trim() === '') {
        chain.unsetLink().run();
    } else {
        chain.setLink({ href: href.trim() }).run();
    }
}

/**
 * Markdown from the editor, tidied for the way the app uses it.
 *
 * The serializer backslash-escapes underscores, which would turn
 * `{{ first_name }}` into `{{ first\_name }}` and stop it being replaced, so
 * placeholders get their underscores back. It also writes `&`, `<` and `>` as
 * HTML entities, which the plain-text email would show literally; the renderer
 * escapes raw markup itself, so the characters are safe to keep as typed.
 */
/**
 * Wrapped prose, flowed back into one line per paragraph.
 *
 * Where the renderer does not break on every newline — a help guide, where a
 * paragraph wrapped across four lines reads as one paragraph — the editor must
 * be shown that paragraph as one line. It turns every newline it is given into
 * a break, so a guide merely opened and saved would come back full of breaks
 * its author never typed.
 *
 * Structure is left alone: headings, list items, quotes, table rows and
 * anything inside a fence keep their own lines, and a line ending in two
 * spaces is a break the author asked for, so it stays.
 */
function flowSoftBreaks(markdown) {
    const structural = (line) => {
        const text = line.trim();

        return (
            text === '' ||
            /^(#{1,6}\s|>|\||```|:::)/.test(text) ||
            /^[-*+]\s/.test(text) ||
            /^\d+[.)]\s/.test(text) ||
            /^(-{3,}|\*{3,}|_{3,})$/.test(text)
        );
    };

    const out = [];
    let fenced = false;

    markdown.split('\n').forEach((line) => {
        if (line.trim().startsWith('```')) fenced = !fenced;

        const previous = out[out.length - 1];
        const joinable =
            !fenced &&
            previous !== undefined &&
            !structural(previous) &&
            !structural(line) &&
            // Two trailing spaces, or a trailing backslash, is a break the
            // author asked for.
            !/( {2,}|\\)$/.test(previous);

        if (joinable) {
            out[out.length - 1] = previous.replace(/\s+$/, '') + ' ' + line.trim();

            return;
        }

        out.push(line);
    });

    return out.join('\n');
}

function toMarkdown(editor, hardBreaks) {
    const markdown = editor
        .getMarkdown()
        .replace(/\{\{\s*([^{}]+?)\s*\}\}/g, (match, token) => `{{ ${token.replace(/\\_/g, '_')} }}`)
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>');

    // Where the renderer already breaks on every line, Markdown's own hard
    // break — two spaces at the end of a line — says nothing extra. Left in, it
    // would make an untouched template differ from the one it was loaded from,
    // and the screen would call it edited when nobody had edited anything.
    return hardBreaks ? markdown.replace(/[ \t]+$/gm, '') : markdown;
}

function mount(root) {
    const textarea = root.querySelector('textarea');
    const host = root.querySelector('[data-rich-text-editor]');
    const buttons = Array.from(root.querySelectorAll('[data-rich-action]'));
    // The table controls only mean anything with the cursor inside a table, so
    // they stay out of the way until there is one.
    const tableTools = root.querySelector('[data-rich-table-tools]');
    const hardBreaks = root.hasAttribute('data-hard-breaks');
    if (!textarea || !host) return;

    let editor = null;

    const paint = () => {
        if (!editor) return;

        buttons.forEach((button) => {
            const action = button.dataset.richAction;
            const active = ACTIVE[action];
            button.setAttribute('aria-pressed', active && active(editor) ? 'true' : 'false');

            // Undo and redo say plainly when there is nothing to undo.
            if (action === 'undo' || action === 'redo') {
                button.disabled = !editor.can()[action]();
            }
        });

        tableTools?.classList.toggle('hidden', !editor.isActive('table'));
    };

    // The form's placeholder chips drop a token into whichever field was used
    // last, which they learn from the field's focus event. Any activity in the
    // editor counts, so a chip works straight after a click or a keystroke here.
    const mark = () => textarea.dispatchEvent(new Event('focus'));
    host.addEventListener('pointerdown', mark);

    editor = new Editor({
        element: host,
        extensions: [
            StarterKit.configure({
                link: { openOnClick: false, autolink: false },
                // Underline is the one common formatting mark Markdown has no
                // syntax for, so it is not offered rather than being offered
                // and then lost on save.
                underline: false,
            }),
            TaskList,
            TaskItem.configure({ nested: true }),
            TableKit.configure({ table: { resizable: false } }),
            // The editor turns every newline it is handed into a break, and no
            // parser option changes that, so the difference between a field
            // that breaks on each line and one that flows is made on the way
            // in (flowSoftBreaks) and on the way out (toMarkdown).
            Markdown,
        ],
        content: hardBreaks ? textarea.value : flowSoftBreaks(textarea.value),
        contentType: 'markdown',
        editorProps: {
            attributes: { class: 'prose-preview rich-text-content', id: textarea.id + '-editor' },
        },
        onUpdate: () => {
            textarea.value = toMarkdown(editor, hardBreaks);
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        },
        onFocus: mark,
        onSelectionUpdate: () => {
            mark();
            paint();
        },
        onTransaction: paint,
    });

    buttons.forEach((button) =>
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const action = button.dataset.richAction;

            if (action === 'link') return toggleLink(editor);

            const run = ACTIONS[action];
            if (run) run(editor.chain().focus()).run();
        })
    );

    // Lets the placeholder chips drop a token where the cursor is.
    textarea.richText = {
        editor,
        insert: (text) => editor.chain().focus().insertContent(text).run(),
    };

    textarea.hidden = true;
    root.classList.add('rich-text-ready');
    paint();
}

export function initRichText() {
    document.querySelectorAll('[data-rich-text]').forEach(mount);
}
