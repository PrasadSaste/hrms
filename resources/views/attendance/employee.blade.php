<x-app-layout :title="$employee->full_name . ' attendance'">
    <x-page-header :title="$employee->full_name"
        :subtitle="'Attendance for ' . $month->format('F Y')"
        :back="route('employees.show', $employee)">
        <x-slot:actions>
            @include('attendance._month-picker', ['action' => route('attendance.employee', $employee)])
            <x-button href="{{ route('attendance.export', array_merge(['employee' => $employee->id], request()->query())) }}"
                variant="secondary">
                <x-icon.download class="size-4" /> Export
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-6">
        <div class="mb-5 flex flex-wrap items-center gap-4 border-b border-slate-100 pb-5">
            <x-avatar :name="$employee->full_name" :src="$employee->photoUrl()" size="lg" />
            <div>
                <p class="text-lg font-semibold text-slate-900">{{ $employee->full_name }}</p>
                <p class="text-sm text-slate-500">
                    {{ $employee->employee_code }} &middot;
                    {{ $employee->designation?->name ?? 'No designation' }} &middot;
                    {{ $employee->branch?->name ?? 'No branch' }}
                </p>
                <p class="mt-0.5 text-xs text-slate-500">
                    Shift: {{ $employee->shift?->name ?? 'Branch default' }}
                </p>
            </div>
        </div>

        @include('attendance._totals')
    </x-card>

    <x-tabs key="attendance-employee" :tabs="['calendar' => 'Calendar', 'records' => 'Daily records']">

    <div data-tab-panel="calendar">
    <x-card title="Calendar">
        @include('attendance._calendar')
    </x-card>
    </div>

    <div data-tab-panel="records">
    <x-card title="Daily records" :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th><th>Day</th><th>Status</th><th>Check in</th><th>Check out</th>
                        <th class="num">Worked</th><th class="num">Late</th>
                        <th class="num">Overtime</th><th>Remarks</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['days'] as $date => $day)
                        @php
                            $carbon = \Illuminate\Support\Carbon::parse($date);
                            $attendance = $day['attendance'];
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap">{{ $carbon->format('d M Y') }}</td>
                            <td class="text-slate-500">{{ $carbon->format('D') }}</td>
                            <td>
                                @if ($day['status'])
                                    <x-badge :color="$day['status']->color()">{{ $day['status']->label() }}</x-badge>
                                @else
                                    <span class="text-slate-400">-</span>
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
                                {{ $attendance && $attendance->late_minutes > 0 ? $attendance->late_minutes . 'm' : '-' }}
                            </td>
                            <td class="num tabular-nums">
                                {{ $attendance && $attendance->overtime_minutes > 0 ? $attendance->overtimeHours() . 'h' : '-' }}
                            </td>
                            <td class="max-w-48 truncate text-xs text-slate-500">
                                {{ $attendance?->remarks ?? ($day['leave']['request']->leaveType->name ?? '') }}
                            </td>
                            <td class="num">
                                @if ($attendance)
                                    @can('update', $attendance)
                                        <x-button href="{{ route('attendance.edit', $attendance) }}" variant="ghost" size="sm"><x-icon.pencil class="size-3.5" /> Edit</x-button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
    </div>

    </x-tabs>
</x-app-layout>
