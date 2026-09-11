@php $isNew = ! $article->exists; @endphp

<x-app-layout :title="$isNew ? 'New guide' : 'Edit '.$article->title">
    <x-page-header :title="$isNew ? 'New guide' : $article->title"
        subtitle="Written for whoever has to run this screen on a Monday morning."
        :back="route('help-articles.index')" />

    <form method="POST"
        action="{{ $isNew ? route('help-articles.store') : route('help-articles.update', $article) }}">
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-card title="The page">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input name="title" label="Title" :value="old('title', $article->title)" required
                            class="sm:col-span-2" />

                        <x-textarea name="summary" label="One-line summary" :rows="2"
                            :value="old('summary', $article->summary)" class="sm:col-span-2"
                            help="Shown under the title and on the contents page." />

                        <x-select name="group" label="Section" required>
                            @foreach ($groups as $group)
                                <option value="{{ $group }}" @selected(old('group', $article->group) === $group)>{{ $group }}</option>
                            @endforeach
                        </x-select>

                        <x-select name="icon" label="Icon" required>
                            @foreach ($icons as $icon)
                                <option value="{{ $icon }}" @selected(old('icon', $article->icon) === $icon)>{{ $icon }}</option>
                            @endforeach
                        </x-select>

                        <x-select name="route_name" label="The screen this describes"
                            help="Used by “Help for this page” in the header.">
                            <option value="">Not tied to a screen</option>
                            @foreach ($routes as $route)
                                <option value="{{ $route }}" @selected(old('route_name', $article->route_name) === $route)>{{ $route }}</option>
                            @endforeach
                        </x-select>

                        <x-select name="permission" label="Only show to people who can"
                            help="Leave open when the guide is for everyone.">
                            <option value="">Everyone</option>
                            @foreach ($permissions as $permission)
                                <option value="{{ $permission }}" @selected(old('permission', $article->permission) === $permission)>
                                    {{ $permission }}
                                </option>
                            @endforeach
                        </x-select>

                        <x-input name="slug" label="Address" :value="old('slug', $article->slug)"
                            help="Left blank, it follows the title." />

                        <x-input name="position" type="number" label="Order in its section"
                            :value="old('position', $article->position ?? 0)"
                            help="Lower numbers come first." />
                    </div>
                </x-card>

                <x-card title="What is this page?"
                    subtitle="A paragraph or two: what the screen is for, and what it is not for">
                    {{-- No hard breaks: a guide is prose, and a paragraph the
                         author wrapped across several lines reads as one
                         paragraph, so the editor flows it the same way. --}}
                    <x-rich-text name="body" :rows="10" :value="old('body', $article->body)"
                        help="Headings, lists, tables and links. Press Enter for a new paragraph." />
                </x-card>

                <x-card title="What the fields mean"
                    subtitle="The words on the screen that need explaining — a column, a status, a figure">
                    <x-help.repeater name="fields" add-label="Add a field"
                        :rows="old('fields', $article->fields ?? [])"
                        :columns="[
                            'term' => ['label' => 'The word on the screen'],
                            'description' => ['label' => 'What it means', 'type' => 'textarea', 'rows' => 2],
                        ]" />
                </x-card>

                <x-card title="How do I?" subtitle="The handful of jobs people come to this screen to do">
                    <x-help.repeater name="tasks" add-label="Add a task"
                        :rows="old('tasks', $article->tasks ?? [])"
                        :columns="[
                            'question' => ['label' => 'The question, as someone would ask it'],
                            'answer' => ['label' => 'The steps', 'type' => 'textarea', 'rows' => 4],
                        ]" />
                </x-card>

                <x-card title="Troubleshooting" subtitle="What people get stuck on, and what to try">
                    <x-help.repeater name="troubleshooting" add-label="Add a problem"
                        :rows="old('troubleshooting', $article->troubleshooting ?? [])"
                        :columns="[
                            'problem' => ['label' => 'What they see'],
                            'answer' => ['label' => 'What to do', 'type' => 'textarea', 'rows' => 3],
                        ]" />
                </x-card>
            </div>

            <div class="space-y-6 self-start lg:sticky lg:top-20">
                <x-card title="Publishing">
                    <x-checkbox name="is_published" label="Show this guide to readers"
                        :checked="old('is_published', $article->is_published ?? true)"
                        help="Unchecked, only people who can edit guides can see it." />

                    <div class="mt-4 space-y-2">
                        <x-button size="lg" class="w-full">{{ $isNew ? 'Create guide' : 'Save changes' }}</x-button>

                        @unless ($isNew)
                            <x-button variant="secondary" size="lg" class="w-full"
                                :href="route('self-assistance.show', $article)">
                                <x-icon.eye class="size-4" /> View as a reader
                            </x-button>
                        @endunless
                    </div>

                    @unless ($isNew)
                        @if ($article->editor)
                            <p class="form-help mt-4">
                                Last edited by {{ $article->editor->name }}
                                {{ $article->updated_at?->diffForHumans() }}.
                            </p>
                        @endif
                    @endunless
                </x-card>

                <x-card title="Writing a good guide">
                    <ul class="list-disc space-y-1.5 pl-4 text-sm text-slate-600">
                        <li>Say what the screen is for in one sentence.</li>
                        <li>Explain the words a new administrator would not know.</li>
                        <li>Answer the questions people actually ask, in their words.</li>
                        <li>Say what to do when it does not work, not why it broke.</li>
                    </ul>
                </x-card>
            </div>
        </div>
    </form>

    @unless ($isNew)
        <form method="POST" action="{{ route('help-articles.destroy', $article) }}" class="mt-6">
            @csrf @method('DELETE')
            <x-button variant="ghost" size="sm"
                data-confirm="Delete this guide? The seeder can put the shipped one back.">
                <x-icon.trash class="size-3.5" /> Delete this guide
            </x-button>
        </form>
    @endunless
</x-app-layout>
