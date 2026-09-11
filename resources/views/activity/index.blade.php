<x-app-layout title="Activity log">
    <x-page-header title="Activity log" subtitle="Every change made through the application, and by whom" />

    <x-filter-bar :action="route('activity.index')">
        <x-select label="User" name="user_id" :selected="request('user_id')" placeholder="All users"
            :options="$users->pluck('name', 'id')->all()" class="sm:w-52" />
        <x-select label="Action" name="action" :selected="request('action')" placeholder="All actions"
            :options="['post' => 'Created', 'put' => 'Updated', 'patch' => 'Updated', 'delete' => 'Deleted']"
            class="sm:w-40" />
        <x-input label="From" name="from" type="date" :value="request('from')" class="sm:w-44" />
        <x-input label="To" name="to" type="date" :value="request('to')" class="sm:w-44" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Filter</x-button>
        @if (request()->hasAny(['user_id', 'action', 'from', 'to']))
            <x-button href="{{ route('activity.index') }}" variant="ghost">Clear</x-button>
        @endif
    </x-filter-bar>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr><th>When</th><th>User</th><th>Action</th><th>Description</th><th>Route</th><th>IP address</th></tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-xs whitespace-nowrap text-slate-500">
                                {{ $log->created_at->format('d M Y, H:i:s') }}
                                <div class="text-slate-400">{{ $log->created_at->diffForHumans() }}</div>
                            </td>
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <x-avatar :name="$log->user?->name ?? 'System'" size="xs" />
                                    <span class="text-sm text-slate-900">{{ $log->user?->name ?? 'System' }}</span>
                                </div>
                            </td>
                            <td>
                                <x-badge :color="match ($log->action) {
                                    'post' => 'emerald', 'delete' => 'rose', default => 'sky' }">
                                    {{ match ($log->action) {
                                        'post' => 'Created', 'delete' => 'Deleted',
                                        'put', 'patch' => 'Updated', default => ucfirst($log->action) } }}
                                </x-badge>
                            </td>
                            <td class="font-mono text-xs text-slate-600">{{ $log->description }}</td>
                            <td class="font-mono text-xs text-slate-500">{{ $log->properties['route'] ?? '-' }}</td>
                            <td class="font-mono text-xs text-slate-500">{{ $log->ip_address ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty title="No activity recorded" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-app-layout>
