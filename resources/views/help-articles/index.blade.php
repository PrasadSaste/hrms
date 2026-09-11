<x-app-layout title="Self Assistance guides">
    <x-page-header title="Self Assistance guides"
        subtitle="One guide per screen. Edit the wording, add your own pages, or hide one while you rewrite it.">
        <x-slot:actions>
            <x-button variant="secondary" :href="route('self-assistance.index')">
                <x-icon.eye class="size-4" /> View as a reader
            </x-button>
            <x-button :href="route('help-articles.create')">
                <x-icon.plus class="size-4" /> New guide
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Guide</th>
                        <th class="w-44">Screen</th>
                        <th class="w-28 text-center">Sections</th>
                        <th class="w-24 text-center">Shown</th>
                        <th class="w-28"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($articles as $group => $rows)
                        <tr class="bg-slate-50/80">
                            <th colspan="5"
                                class="px-4 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                {{ $group }}
                            </th>
                        </tr>

                        @foreach ($rows as $article)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <x-dynamic-component :component="'icon.'.$article->icon"
                                            class="size-4 shrink-0 text-slate-400" />
                                        <a href="{{ route('help-articles.edit', $article) }}"
                                            class="font-medium text-slate-900 hover-ink">{{ $article->title }}</a>
                                    </div>
                                    <p class="mt-0.5 max-w-xl text-xs text-slate-500">{{ $article->summary }}</p>
                                </td>
                                <td class="text-xs text-slate-500">{{ $article->route_name ?? '—' }}</td>
                                <td class="text-center text-xs text-slate-500">{{ count($article->sections()) }}</td>
                                <td class="text-center">
                                    @if ($article->is_published)
                                        <x-badge color="emerald" dot>Live</x-badge>
                                    @else
                                        <x-badge color="amber" dot>Draft</x-badge>
                                    @endif
                                </td>
                                <td class="num">
                                    <x-button variant="secondary" size="sm" :href="route('help-articles.edit', $article)">
                                        <x-icon.pencil class="size-3.5" /> Edit
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-empty title="No guides yet"
                                    description="Run php artisan db:seed --class=HelpArticleSeeder to load a guide for every screen, or write your own." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>
