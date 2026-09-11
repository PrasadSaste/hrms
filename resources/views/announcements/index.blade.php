<x-app-layout title="Announcements">
    <x-page-header title="Announcements" subtitle="Company-wide and targeted notices">
        <x-slot:actions>
            @if ($canManage)
                <x-button href="{{ route('announcements.create') }}"><x-icon.plus class="size-4" /> New announcement</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($announcements as $announcement)
            <x-card class="flex flex-col p-5">
                <div class="flex items-start justify-between gap-3">
                    <a href="{{ route('announcements.show', $announcement) }}"
                        class="flex min-w-0 items-center gap-2 font-semibold text-slate-900 hover-ink">
                        @if ($announcement->is_pinned)
                            <span class="text-amber-500" title="Pinned">&#9733;</span>
                        @endif
                        <span class="truncate">{{ $announcement->title }}</span>
                    </a>
                    @if ($canManage)
                        <x-badge :color="match ($announcement->status) {
                            'published' => 'emerald', 'draft' => 'slate', default => 'amber' }">
                            {{ ucfirst($announcement->status) }}
                        </x-badge>
                    @endif
                </div>

                <p class="mt-2 line-clamp-3 flex-1 text-sm text-slate-600">{{ $announcement->excerpt(40) }}</p>

                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    <span>{{ $announcement->author?->name ?? 'System' }}</span>
                    <span>&middot;</span>
                    <span>{{ $announcement->published_at?->format('d M Y') ?? 'Not published' }}</span>
                    @if ($announcement->branch)
                        <x-badge color="slate">{{ $announcement->branch->name }}</x-badge>
                    @endif
                    @if ($announcement->department)
                        <x-badge color="sky">{{ $announcement->department->name }}</x-badge>
                    @endif
                    @if ($announcement->isExpired())
                        <x-badge color="rose">Expired</x-badge>
                    @endif
                </div>
            </x-card>
        @empty
            <div class="lg:col-span-2">
                <x-card>
                    <x-empty title="No announcements" message="Nothing has been published yet.">
                        <x-slot:action>
                            @if ($canManage)
                                <x-button href="{{ route('announcements.create') }}">Write one</x-button>
                            @endif
                        </x-slot:action>
                    </x-empty>
                </x-card>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $announcements->links() }}</div>
</x-app-layout>
