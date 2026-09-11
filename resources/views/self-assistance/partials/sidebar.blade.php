{{-- The contents list: every guide the reader may open, in menu order. --}}
<aside class="lg:sticky lg:top-20 lg:self-start">
    <x-card :padded="false">
        <div class="border-b border-slate-200 p-3">
            <form method="GET" action="{{ route('self-assistance.index') }}">
                <label class="sr-only" for="help-search">Search help</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                        <x-icon.search class="size-4" />
                    </span>
                    <input type="search" name="q" id="help-search" value="{{ $query ?? '' }}"
                        placeholder="Search help" autocomplete="off"
                        class="form-input pl-9" data-help-search>
                </div>
            </form>
        </div>

        <nav class="max-h-[60vh] overflow-y-auto p-2">
            @forelse ($groups as $group => $articles)
                <p class="px-3 pt-3 pb-1.5 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                    {{ $group }}
                </p>

                @foreach ($articles as $item)
                    <a href="{{ route('self-assistance.show', $item) }}"
                        @class([
                            'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition',
                            'bg-brand-50 font-medium text-brand-700' => isset($current) && $current?->is($item),
                            'text-slate-700 hover:bg-slate-50' => ! (isset($current) && $current?->is($item)),
                        ])>
                        <x-dynamic-component :component="'icon.'.$item->icon" class="size-4 shrink-0 text-slate-400" />
                        <span class="truncate">{{ $item->title }}</span>
                        @unless ($item->is_published)
                            <x-badge color="amber" class="ml-auto">Draft</x-badge>
                        @endunless
                    </a>
                @endforeach
            @empty
                <p class="px-3 py-6 text-center text-sm text-slate-500">No guides yet.</p>
            @endforelse
        </nav>
    </x-card>
</aside>
