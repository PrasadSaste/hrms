<x-app-layout :title="$article->title">
    <div class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)_12rem]">
        @include('self-assistance.partials.sidebar')

        <div class="min-w-0 space-y-6">
            {{-- Where this guide sits --}}
            <nav class="flex flex-wrap items-center gap-1.5 text-sm text-slate-500" aria-label="Breadcrumb">
                <a href="{{ route('self-assistance.index') }}" class="hover:text-slate-700">Self Assistance</a>
                <span aria-hidden="true">/</span>
                <span>{{ $article->group }}</span>
                <span aria-hidden="true">/</span>
                <span class="font-medium text-slate-700">{{ $article->title }}</span>
            </nav>

            <x-card>
                <div class="flex flex-wrap items-start gap-4">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <x-dynamic-component :component="'icon.'.$article->icon" class="size-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $article->title }}</h1>
                        @if ($article->summary)
                            <p class="mt-1 text-slate-600">{{ $article->summary }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($article->route_name && Route::has($article->route_name))
                            <x-button variant="secondary" size="sm" :href="route($article->route_name)">
                                Open the screen
                            </x-button>
                        @endif
                        @can('help.manage')
                            <x-button variant="ghost" size="sm" :href="route('help-articles.edit', $article)">
                                <x-icon.pencil class="size-3.5" /> Edit
                            </x-button>
                        @endcan
                    </div>
                </div>
            </x-card>

            @if (filled($article->body))
                <x-card id="overview" title="What is this page?">
                    <div class="prose-help">{!! $article->bodyHtml() !!}</div>
                </x-card>
            @endif

            @if (filled($article->fields))
                <x-card id="fields" title="What the fields mean">
                    <dl class="divide-y divide-slate-100">
                        @foreach ($article->fields as $field)
                            <div class="py-3 first:pt-0 last:pb-0">
                                <dt class="flex items-center gap-1.5 font-medium text-slate-900">
                                    {{ $field['term'] ?? '' }}
                                    <x-icon.info class="size-4 text-slate-300" />
                                </dt>
                                <dd class="mt-1 text-sm text-slate-600">{{ $field['description'] ?? '' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>
            @endif

            @if (filled($article->tasks))
                <x-card id="tasks" title="How do I?">
                    <div class="space-y-4">
                        @foreach ($article->tasks as $task)
                            <div class="rounded-xl border border-slate-200 p-4">
                                <p class="font-medium text-slate-900">{{ $task['question'] ?? '' }}</p>
                                <div class="prose-help mt-2 text-sm">{!! App\Support\Markdown::html($task['answer'] ?? '') !!}</div>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

            @if (filled($article->troubleshooting))
                <x-card id="troubleshooting" title="Troubleshooting">
                    <dl class="space-y-4">
                        @foreach ($article->troubleshooting as $item)
                            <div>
                                <dt class="font-medium text-slate-900">{{ $item['problem'] ?? '' }}</dt>
                                <dd class="prose-help mt-1 text-sm text-slate-600">
                                    {!! App\Support\Markdown::html($item['answer'] ?? '') !!}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>
            @endif

            {{-- Read the guide straight through, in menu order. --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                @if ($neighbours['previous'])
                    <x-button variant="secondary" size="sm" :href="route('self-assistance.show', $neighbours['previous'])">
                        &larr; {{ $neighbours['previous']->title }}
                    </x-button>
                @else
                    <span></span>
                @endif

                @if ($neighbours['next'])
                    <x-button variant="secondary" size="sm" :href="route('self-assistance.show', $neighbours['next'])">
                        {{ $neighbours['next']->title }} &rarr;
                    </x-button>
                @endif
            </div>
        </div>

        {{-- On this page --}}
        <nav class="hidden lg:sticky lg:top-20 lg:block lg:self-start" aria-label="On this page">
            <p class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">On this page</p>
            <ul class="space-y-1 border-l border-slate-200 text-sm">
                @foreach ($article->sections() as $anchor => $label)
                    <li>
                        <a href="#{{ $anchor }}"
                            class="-ml-px block border-l border-transparent py-1 pl-3 text-slate-500 transition hover:border-brand-500 hover-ink">
                            {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</x-app-layout>
