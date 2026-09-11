<x-app-layout title="Daily attendance">
    <x-page-header title="Daily attendance roster" :subtitle="$date->format('l, d F Y')">
        <x-slot:actions>
            @can('attendance.manage')
                <x-button href="{{ route('attendance.create', ['date' => $date->toDateString()]) }}">
                    <x-icon.plus class="size-4" /> Record attendance
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-stat label="Present" :value="($counts['present'] ?? 0) + ($counts['late'] ?? 0)" color="emerald">
            <x-slot:icon><x-icon.check class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Late" :value="$counts['late'] ?? 0" color="amber">
            <x-slot:icon><x-icon.clock class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Absent" :value="$counts['absent'] ?? 0" color="rose">
            <x-slot:icon><x-icon.x class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="On leave" :value="$counts['on_leave'] ?? 0" color="sky">
            <x-slot:icon><x-icon.calendar class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Week off / holiday" :value="($counts['weekend'] ?? 0) + ($counts['holiday'] ?? 0)" color="violet">
            <x-slot:icon><x-icon.sun class="size-5" /></x-slot:icon>
        </x-stat>
    </div>

    <x-filter-bar :action="route('attendance.daily')">
        <x-input label="Date" name="date" type="date" :value="$date->toDateString()" class="sm:w-44" />
        <x-input label="Search" name="search" :value="request('search')" placeholder="Name or code" class="sm:w-56" />
        <x-select label="Branch" name="branch_id" :selected="request('branch_id')" placeholder="All branches"
            :options="$branches->pluck('name', 'id')->all()" class="sm:w-44" />
        <x-select label="Department" name="department_id" :selected="request('department_id')" placeholder="All departments"
            :options="$departments->pluck('name', 'id')->all()" class="sm:w-44" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Apply</x-button>
        <x-button href="{{ route('attendance.daily') }}" variant="ghost">Today</x-button>
    </x-filter-bar>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th><th>Department</th><th>Status</th>
                        <th>Check in</th><th>Check out</th>
                        <th class="num">Worked</th><th class="num">Late</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roster as $row)
                        @php
                            $employee = $row['employee'];
                            $attendance = $row['attendance'];
                        @endphp
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-avatar :name="$employee->full_name" :src="$employee->photoUrl()" size="sm" />
                                    <div class="min-w-0">
                                        <a href="{{ route('attendance.employee', $employee) }}" class="font-medium text-slate-900 hover-ink">
                                            {{ $employee->full_name }}
                                        </a>
                                        <div class="font-mono text-xs text-slate-500">{{ $employee->employee_code }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>{{ $employee->department?->name ?? '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $employee->branch?->name }}</div>
                            </td>
                            <td>
                                <x-badge :color="$row['status']->color()" dot>{{ $row['status']->label() }}</x-badge>
                                @if ($row['leave'])
                                    <div class="mt-0.5 text-xs text-slate-500">{{ $row['leave']->leaveType->name }}</div>
                                @endif
                                @if ($attendance?->needs_correction)
                                    <div class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-amber-700"
                                        title="This punch was never closed. The system closed it at the end of the shift, so the hours are an assumption until the employee corrects them.">
                                        <x-icon.clock class="size-3.5" />
                                        Punch-out assumed
                                    </div>
                                @endif

                                @php $away = $attendance?->furthestPunchAway(); @endphp
                                @if ($away)
                                    <a href="{{ route('attendance.location-alerts', ['date' => $date->toDateString()]) }}"
                                        class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-rose-600 hover:underline"
                                        title="A punch on this day was made further from the branch than it allows.">
                                        <x-icon.map-pin class="size-3.5" />
                                        {{ \App\Support\Geo::describeDistance($away) }} away
                                    </a>
                                @endif
                            </td>
                            <td class="tabular-nums">
                                {{ $attendance?->check_in?->format('h:i A') ?? '-' }}
                                <x-punch-place :attendance="$attendance" side="check_in" class="block" />
                            </td>
                            <td class="tabular-nums">
                                {{ $attendance?->check_out?->format('h:i A') ?? '-' }}
                                <x-punch-place :attendance="$attendance" side="check_out" class="block" />
                            </td>
                            <td class="num tabular-nums">{{ $attendance?->durationLabel() ?? '-' }}</td>
                            <td class="num tabular-nums">
                                @if ($attendance && $attendance->late_minutes > 0)
                                    <span class="text-amber-600">{{ $attendance->late_minutes }}m</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="num whitespace-nowrap">
                                @if ($attendance)
                                    @can('update', $attendance)
                                        <x-button href="{{ route('attendance.edit', $attendance) }}" variant="ghost" size="sm"><x-icon.pencil class="size-3.5" /> Edit</x-button>
                                    @endcan
                                @else
                                    @can('attendance.manage')
                                        <x-button variant="ghost" size="sm"
                                            href="{{ route('attendance.create', ['date' => $date->toDateString(), 'employee_id' => $employee->id]) }}">
                                            Mark
                                        </x-button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty title="No employees match this view" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>
