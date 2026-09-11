<x-app-layout :title="$announcement->title">
    <x-page-header :title="$announcement->title"
        :subtitle="($announcement->author?->name ?? 'System') . ' · ' . ($announcement->published_at?->format('d M Y, h:i A') ?? 'Not published')"
        :back="route('announcements.index')">
        <x-slot:actions>
            @if ($canManage)
                <x-button href="{{ route('announcements.edit', $announcement) }}" variant="secondary"><x-icon.pencil class="size-3.5" /> Edit</x-button>
                <form method="POST" action="{{ route('announcements.destroy', $announcement) }}" class="inline">
                    @csrf @method('DELETE')
                    <x-button variant="danger" data-confirm="Delete this announcement?"><x-icon.trash class="size-3.5" /> Delete</x-button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            {{-- Markdown with hard breaks: an announcement written as plain text
                 before the editor existed still reads exactly as it did, line
                 breaks and all. Raw HTML is escaped by the renderer. --}}
            <article class="prose-preview max-w-none text-sm leading-relaxed text-slate-700">
                {!! App\Support\Markdown::html($announcement->body, hardBreaks: true) !!}
            </article>
        </x-card>

        <x-card title="Details">
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs tracking-wide text-slate-500 uppercase">Status</dt>
                    <dd class="mt-0.5">
                        <x-badge :color="$announcement->status === 'published' ? 'emerald' : 'slate'" dot>
                            {{ ucfirst($announcement->status) }}
                        </x-badge>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs tracking-wide text-slate-500 uppercase">Audience</dt>
                    <dd class="mt-0.5 text-slate-900">
                        {{ $announcement->branch?->name ?? 'All branches' }}
                        @if ($announcement->department)
                            &middot; {{ $announcement->department->name }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs tracking-wide text-slate-500 uppercase">Published</dt>
                    <dd class="mt-0.5 text-slate-900">{{ $announcement->published_at?->format('d M Y, h:i A') ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs tracking-wide text-slate-500 uppercase">Expires</dt>
                    <dd class="mt-0.5 text-slate-900">{{ $announcement->expires_at?->format('d M Y, h:i A') ?? 'Never' }}</dd>
                </div>
                @if ($canManage)
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">Read by</dt>
                        <dd class="mt-0.5 text-slate-900">{{ $readCount }} employee(s)</dd>
                    </div>
                @endif
            </dl>
        </x-card>
    </div>
</x-app-layout>
