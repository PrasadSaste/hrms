@props([
    'label' => null,
    'name',
    'value' => null,
    'rows' => 12,
    'help' => null,
    'required' => false,
    // Whether the renderer turns every line break into a break. On, a line the
    // author put on its own line stays there; off, wrapped prose flows into a
    // paragraph. The editor has to agree with the renderer, or what is typed
    // and what is read are different documents.
    'hardBreaks' => false,
])

{{--
    A Markdown textarea with a rich text editor on top. Without JavaScript the
    textarea is a plain Markdown field; with it, rich-text.js hides the
    textarea and keeps it filled in from the editor.

    Every button here produces Markdown the renderer renders — the app uses
    GitHub-flavoured Markdown, so strikethrough, tables, task lists and fenced
    code all survive. Underline is the one common mark deliberately absent:
    Markdown has no syntax for it, so offering it would lose it on save.
--}}
@php
    // icon, action, title. A null row is a divider.
    $groups = [
        [
            ['label' => 'B', 'class' => 'font-bold', 'action' => 'bold', 'title' => 'Bold'],
            ['label' => 'I', 'class' => 'font-serif italic', 'action' => 'italic', 'title' => 'Italic'],
            ['label' => 'S', 'class' => 'line-through', 'action' => 'strike', 'title' => 'Strikethrough'],
            ['label' => '</>', 'class' => 'font-mono text-[10px]', 'action' => 'code', 'title' => 'Inline code'],
        ],
        [
            ['label' => 'H1', 'action' => 'heading', 'title' => 'Heading'],
            ['label' => 'H2', 'action' => 'subheading', 'title' => 'Subheading'],
            ['label' => 'H3', 'action' => 'subsubheading', 'title' => 'Small heading'],
            ['label' => 'P', 'action' => 'paragraph', 'title' => 'Plain paragraph'],
        ],
    ];
@endphp

<div {{ $attributes->only('class')->class(['w-full']) }} data-rich-text
    @if ($hardBreaks) data-hard-breaks @endif>
    @if ($label)
        <label class="form-label" for="{{ $name }}">
            {{ $label }}
            @if ($required)<span class="text-rose-500">*</span>@endif
        </label>
    @endif

    <div @class(['rich-text', 'border-rose-400' => $errors->has($name)])>
        <div class="rich-text-toolbar" role="toolbar" aria-label="Formatting">
            @foreach ($groups as $group)
                @foreach ($group as $button)
                    <button type="button" class="rich-text-button" data-rich-action="{{ $button['action'] }}"
                        title="{{ $button['title'] }}" aria-label="{{ $button['title'] }}">
                        <span class="{{ $button['class'] ?? '' }}">{{ $button['label'] }}</span>
                    </button>
                @endforeach
                <span class="rich-text-divider" aria-hidden="true"></span>
            @endforeach

            <button type="button" class="rich-text-button" data-rich-action="bullets" title="Bulleted list" aria-label="Bulleted list">
                <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M7 5h9M7 10h9M7 15h9"/><circle cx="3.5" cy="5" r="1.1" fill="currentColor" stroke="none"/><circle cx="3.5" cy="10" r="1.1" fill="currentColor" stroke="none"/><circle cx="3.5" cy="15" r="1.1" fill="currentColor" stroke="none"/></svg>
            </button>
            <button type="button" class="rich-text-button" data-rich-action="numbers" title="Numbered list" aria-label="Numbered list">
                <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M8 5h8M8 10h8M8 15h8"/><path d="M2.5 3.5h1v3M2 8.8c.4-.6 1.6-.6 1.6.2 0 .7-1.6 1-1.6 2h2M2.2 13.8h1.5l-1 1.2c.9 0 1.4.4 1.4 1 0 .8-1.2 1.2-2 .6"/></svg>
            </button>
            <button type="button" class="rich-text-button" data-rich-action="tasks" title="Checklist" aria-label="Checklist">
                <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5h8M9 14h8"/><rect x="2" y="2.5" width="5" height="5" rx="1"/><path d="M2 12.5 3.6 14l3-3.2"/></svg>
            </button>

            <span class="rich-text-divider" aria-hidden="true"></span>

            <button type="button" class="rich-text-button" data-rich-action="quote" title="Quote" aria-label="Quote">
                <span class="font-serif text-lg leading-none">&rdquo;</span>
            </button>
            <button type="button" class="rich-text-button" data-rich-action="codeblock" title="Code block" aria-label="Code block">
                <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m7 6-4 4 4 4M13 6l4 4-4 4"/></svg>
            </button>
            <button type="button" class="rich-text-button" data-rich-action="rule" title="Divider line" aria-label="Divider line">
                <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 10h14"/></svg>
            </button>

            <span class="rich-text-divider" aria-hidden="true"></span>

            <button type="button" class="rich-text-button" data-rich-action="link" title="Link" aria-label="Link">
                <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 11.5a3.5 3.5 0 0 0 5 0l2-2a3.5 3.5 0 0 0-5-5l-1 1"/><path d="M11.5 8.5a3.5 3.5 0 0 0-5 0l-2 2a3.5 3.5 0 0 0 5 5l1-1"/></svg>
            </button>
            <button type="button" class="rich-text-button" data-rich-action="unlink" title="Remove link" aria-label="Remove link">
                <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 11.5a3.5 3.5 0 0 0 5 0l2-2a3.5 3.5 0 0 0-5-5"/><path d="M11.5 8.5a3.5 3.5 0 0 0-5 0l-2 2a3.5 3.5 0 0 0 5 5"/><path d="m3 3 14 14"/></svg>
            </button>
            <button type="button" class="rich-text-button" data-rich-action="table" title="Insert table" aria-label="Insert table">
                <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="3.5" width="15" height="13" rx="1.5"/><path d="M2.5 8h15M8 8v8.5M13 8v8.5"/></svg>
            </button>

            <span class="rich-text-divider" aria-hidden="true"></span>

            <button type="button" class="rich-text-button" data-rich-action="clear" title="Clear formatting" aria-label="Clear formatting">
                <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4h9M11 4 8 16M4 16h7"/><path d="m14 11 4 4M18 11l-4 4"/></svg>
            </button>
            <button type="button" class="rich-text-button" data-rich-action="undo" title="Undo" aria-label="Undo">
                <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 5 3 9l4 4"/><path d="M3 9h8a4 4 0 0 1 0 8H9"/></svg>
            </button>
            <button type="button" class="rich-text-button" data-rich-action="redo" title="Redo" aria-label="Redo">
                <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m13 5 4 4-4 4"/><path d="M17 9H9a4 4 0 0 0 0 8h2"/></svg>
            </button>
        </div>

        {{-- Only meaningful with the cursor inside a table, so it stays hidden
             until there is one. --}}
        <div class="rich-text-toolbar rich-text-table-tools hidden" data-rich-table-tools
            role="toolbar" aria-label="Table">
            <span class="rich-text-tools-label">Table</span>
            <button type="button" class="rich-text-button is-wide" data-rich-action="row-before">Row above</button>
            <button type="button" class="rich-text-button is-wide" data-rich-action="row-after">Row below</button>
            <button type="button" class="rich-text-button is-wide" data-rich-action="row-delete">Delete row</button>
            <span class="rich-text-divider" aria-hidden="true"></span>
            <button type="button" class="rich-text-button is-wide" data-rich-action="column-before">Column left</button>
            <button type="button" class="rich-text-button is-wide" data-rich-action="column-after">Column right</button>
            <button type="button" class="rich-text-button is-wide" data-rich-action="column-delete">Delete column</button>
            <span class="rich-text-divider" aria-hidden="true"></span>
            <button type="button" class="rich-text-button is-wide is-danger" data-rich-action="table-delete">Delete table</button>
        </div>

        <div data-rich-text-editor></div>

        <textarea
            name="{{ $name }}"
            id="{{ $name }}"
            rows="{{ $rows }}"
            @if ($required) required @endif
            {{ $attributes->except('class')->class(['form-textarea rich-text-source']) }}
        >{{ old($name, $value) }}</textarea>
    </div>

    @if ($help)
        <p class="form-help">{{ $help }}</p>
    @endif

    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
