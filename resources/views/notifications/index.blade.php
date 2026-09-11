<x-app-layout title="Notifications">
    <x-page-header title="Notifications" subtitle="Approvals, payslips and announcements addressed to you">
        <x-slot:actions>
            @if (auth()->user()->unreadNotifications()->count() > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <x-button variant="secondary"><x-icon.check class="size-4" /> Mark all read</x-button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-card :padded="false">
        @forelse ($notifications as $notification)
            <a href="{{ route('notifications.read', $notification->id) }}"
                @class([
                    'flex gap-4 border-b border-slate-100 px-5 py-4 transition last:border-0 hover:bg-slate-50',
                    'bg-brand-50/40' => $notification->read_at === null,
                ])>
                <span @class([
                    'mt-1.5 size-2 shrink-0 rounded-full',
                    'bg-brand-500' => $notification->read_at === null,
                    'bg-slate-300' => $notification->read_at !== null,
                ])></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-slate-900">{{ $notification->data['title'] ?? 'Notification' }}</p>
                    <p class="mt-0.5 text-sm text-slate-600">{{ $notification->data['message'] ?? '' }}</p>
                    <p class="mt-1 text-xs text-slate-400">
                        {{ $notification->created_at->format('d M Y, h:i A') }}
                        &middot; {{ $notification->created_at->diffForHumans() }}
                    </p>
                </div>
                @if ($notification->read_at === null)
                    <x-badge color="brand">New</x-badge>
                @endif
            </a>
        @empty
            <x-empty title="No notifications" message="Approvals, payslips and announcements will appear here." />
        @endforelse
    </x-card>

    <div class="mt-4">{{ $notifications->links() }}</div>
</x-app-layout>
