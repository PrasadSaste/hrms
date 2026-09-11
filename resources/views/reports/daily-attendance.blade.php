@php
    use App\Support\Duration;
@endphp

<x-app-layout title="Daily attendance report">
    <x-page-header title="Daily attendance report"
        :subtitle="$date->format('l, d M Y') . ' · punch in and out across the organisation'"
        :back="route('reports.index')">
        <x-slot:actions>
            <x-button :href="route('reports.attendance.daily.export', request()->query())" variant="secondary">
                <x-icon.download class="size-4" /> Export CSV
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Punched in" :value="$aggregate['present']" color="emerald">
            <x-slot:icon><x-icon.check class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Late arrivals" :value="$aggregate['late']" color="amber">
            <x-slot:icon><x-icon.clock class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="On leave" :value="$aggregate['on_leave']" color="sky">
            <x-slot:icon><x-icon.calendar class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="No punch" :value="$aggregate['absent']" color="rose">
            <x-slot:icon><x-icon.x class="size-5" /></x-slot:icon>
        </x-stat>
    </div>

    <x-filter-bar :action="route('reports.attendance.daily')">
        <x-input label="Date" name="date" type="date" :value="$date->toDateString()" class="sm:w-44" />
        <x-select label="Branch" name="branch_id" :selected="request('branch_id')" placeholder="All branches"
            :options="$branches->pluck('name', 'id')->all()" class="sm:w-44" />
        <x-select label="Department" name="department_id" :selected="request('department_id')" placeholder="All departments"
            :options="$departments->pluck('name', 'id')->all()" class="sm:w-44" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Run report</x-button>
    </x-filter-bar>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Shift</th>
                        <th>Arrival</th>
                        <th>In</th>
                        <th>Out</th>
                        <th>Punch in location</th>
                        <th>Punch out location</th>
                        <th>Departure</th>
                        <th class="num">Working time</th>
                        <th class="num">Break</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $attendance = $row['attendance'];
                            $shift = $row['shift'];
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <x-avatar :name="$row['employee']->full_name" size="sm" />
                                    <div class="min-w-0">
                                        <a href="{{ route('attendance.employee', $row['employee']) }}"
                                            class="font-medium text-slate-900 hover-ink">{{ $row['employee']->full_name }}</a>
                                        <div class="font-mono text-xs text-slate-500">{{ $row['employee']->employee_code }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap text-sm text-slate-600">
                                @if ($shift)
                                    {{ $shift->name }}
                                    <div class="text-xs text-slate-400 tabular-nums">
                                        {{ \Illuminate\Support\Str::of($shift->start_time)->substr(0, 5) }}–{{ \Illuminate\Support\Str::of($shift->end_time)->substr(0, 5) }}
                                    </div>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($row['arrival'] === 'late')
                                    <x-badge color="rose">Late by {{ Duration::short($attendance->late_minutes) }}</x-badge>
                                @elseif ($row['arrival'] === 'on-time')
                                    <x-badge color="emerald">On time</x-badge>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap tabular-nums">{{ $attendance?->check_in?->format('h:i a') ?? '—' }}</td>
                            <td class="whitespace-nowrap tabular-nums">
                                @if ($attendance?->check_out)
                                    {{ $attendance->check_out->format('h:i a') }}
                                @elseif ($attendance?->check_in)
                                    <x-badge color="sky" dot>Still in</x-badge>
                                @else
                                    —
                                @endif
                            </td>

                            {{-- A punch stores coordinates, which nobody can read. The
                                 map link carries them; the cell stays narrow. --}}
                            @foreach (['check_in', 'check_out'] as $side)
                                <td class="text-sm text-slate-500">
                                    @php $url = $attendance?->mapUrl($side); @endphp
                                    @if ($url)
                                        <a href="{{ $url }}" target="_blank" rel="noopener"
                                            class="link-plain"
                                            title="{{ $attendance->{$side.'_location'} }}">On map</a>
                                    @elseif ($attendance?->{$side.'_location'})
                                        <span class="block max-w-40 truncate">{{ $attendance->{$side.'_location'} }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                @if ($row['departure'] === 'early')
                                    <x-badge color="amber">Early by {{ Duration::short($attendance->early_leaving_minutes) }}</x-badge>
                                @elseif ($row['departure'] === 'on-time')
                                    <x-badge color="emerald">On time</x-badge>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="num font-medium tabular-nums text-slate-900">
                                {{ Duration::hhmm($attendance?->worked_minutes) }}
                            </td>
                            <td class="num tabular-nums text-slate-500">
                                {{ $attendance?->break_minutes ? Duration::short($attendance->break_minutes) : '—' }}
                            </td>
                            <td>
                                <x-badge :color="$row['status']->color()" dot>{{ $row['status']->label() }}</x-badge>
                                @if ($row['leave'])
                                    <div class="mt-0.5 text-xs text-slate-500">{{ $row['leave']->leaveType?->name }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">
                                <x-empty title="Nobody to report on"
                                    message="No employees match these filters." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>
