<x-app-layout title="Letter wording">
    <x-page-header title="Letter wording"
        subtitle="What each kind of letter says. Changes apply to the next letter issued, never to one already sent."
        :back="route('letters.index')" />

    <div class="space-y-6">
        @foreach ($grouped as $group => $types)
            <x-card :title="$group" :padded="false">
                <ul class="divide-y divide-slate-100">
                    @foreach ($types as $key => $definition)
                        <li class="flex flex-wrap items-start gap-4 px-5 py-4">
                            <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                <x-dynamic-component :component="'icon.' . $definition['icon']" class="size-5" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('letter-templates.edit', $key) }}"
                                        class="font-medium text-slate-900 hover-ink">{{ $definition['label'] }}</a>
                                    @if ($customised[$key])
                                        <x-badge color="brand">Reworded</x-badge>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-sm text-slate-500">{{ $definition['summary'] }}</p>
                            </div>

                            <div class="shrink-0 text-right">
                                <div class="text-xs tabular-nums text-slate-500">
                                    {{ number_format($issued[$key] ?? 0) }} issued
                                </div>
                                <x-button href="{{ route('letter-templates.edit', $key) }}" variant="secondary" size="sm" class="mt-1.5">
                                    <x-icon.pencil class="size-4" /> Edit
                                </x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endforeach
    </div>
</x-app-layout>
