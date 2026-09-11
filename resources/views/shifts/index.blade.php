<x-app-layout title="Shifts">
    <x-page-header title="Shifts" subtitle="Working hours that drive late-arrival and overtime calculations">
        <x-slot:actions>
            @if ($canManage)
                <x-button href="{{ route('shifts.create') }}"><x-icon.plus class="size-4" /> New shift</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Shift</th><th>Branch</th><th>Hours</th><th class="num">Grace</th>
                        <th class="num">Break</th><th>Full / half day</th><th>Working days</th>
                        <th class="num">Employees</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @php $dayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun']; @endphp
                    @forelse ($shifts as $shift)
                        <tr>
                            <td>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-slate-900">{{ $shift->name }}</span>
                                    @if ($shift->is_default)<x-badge color="brand">Default</x-badge>@endif
                                </div>
                                <div class="font-mono text-xs text-slate-500">{{ $shift->code }}</div>
                            </td>
                            <td>{{ $shift->branch?->name ?? 'All branches' }}</td>
                            <td class="whitespace-nowrap tabular-nums">
                                {{ \Illuminate\Support\Carbon::parse($shift->start_time)->format('h:i A') }}
                                &ndash;
                                {{ \Illuminate\Support\Carbon::parse($shift->end_time)->format('h:i A') }}
                            </td>
                            <td class="num tabular-nums">{{ $shift->grace_minutes }}m</td>
                            <td class="num tabular-nums">{{ $shift->break_minutes }}m</td>
                            <td class="tabular-nums">{{ $shift->full_day_hours }}h / {{ $shift->half_day_hours }}h</td>
                            <td class="text-xs text-slate-600">
                                {{ collect($shift->workingDays())->map(fn ($d) => $dayNames[$d] ?? $d)->implode(', ') }}
                            </td>
                            <td class="num tabular-nums">{{ $shift->employees_count }}</td>
                            <td><x-badge :color="$shift->status === 'active' ? 'emerald' : 'slate'" dot>{{ ucfirst($shift->status) }}</x-badge></td>
                            <td class="num whitespace-nowrap">
                                @if ($canManage)
                                    <x-button href="{{ route('shifts.edit', $shift) }}" variant="ghost" size="sm"><x-icon.pencil class="size-3.5" /> Edit</x-button>
                                    <form method="POST" action="{{ route('shifts.destroy', $shift) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <x-button variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50"
                                            data-confirm="Remove {{ $shift->name }}?"><x-icon.trash class="size-3.5" /> Delete</x-button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><x-empty title="No shifts defined" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $shifts->links() }}</div>
</x-app-layout>
