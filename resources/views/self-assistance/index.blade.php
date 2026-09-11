<x-app-layout title="Self Assistance">
    <x-page-header title="Self Assistance" subtitle="A guide to every screen, written for administrators.">
        <x-slot:actions>
            @can('help.manage')
                <x-button variant="secondary" :href="route('help-articles.index')">
                    <x-icon.pencil class="size-4" /> Manage guides
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
        @include('self-assistance.partials.sidebar')

        <div class="space-y-6">
            @if ($query !== '')
                <x-card :title="'Results for “'.$query.'”'"
                    :subtitle="trans_choice(':count guide|:count guides', $results->count(), ['count' => $results->count()])">
                    @forelse ($results as $result)
                        <a href="{{ route('self-assistance.show', $result['article']) }}"
                            class="-mx-2 block rounded-lg px-2 py-3 transition hover:bg-slate-50">
                            <p class="text-xs text-slate-500">{{ $result['article']->group }}</p>
                            <p class="mt-0.5 font-medium text-slate-900">{{ $result['article']->title }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $result['excerpt'] }}</p>
                        </a>
                    @empty
                        <x-empty title="Nothing matched"
                            description="Try a word that appears on the screen you are asking about — a button label, or the name of a column." />
                    @endforelse
                </x-card>
            @else
                @foreach ($groups as $group => $articles)
                    <x-card :title="$group">
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($articles as $item)
                                <a href="{{ route('self-assistance.show', $item) }}"
                                    class="flex gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-brand-300 hover:bg-brand-50/40">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                                        <x-dynamic-component :component="'icon.'.$item->icon" class="size-5" />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block font-medium text-slate-900">{{ $item->title }}</span>
                                        <span class="mt-0.5 block text-xs text-slate-500">{{ $item->summary }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </x-card>
                @endforeach
            @endif
        </div>
    </div>
</x-app-layout>
