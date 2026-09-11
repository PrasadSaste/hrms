<x-app-layout title="Attendance report">
    <x-page-header title="Attendance report" :subtitle="$month->format('F Y')" :back="route('reports.index')">
        <x-slot:actions>
            <x-button href="{{ route('reports.attendance.export', request()->query()) }}" variant="secondary">
                <x-icon.download class="size-4" /> Export CSV
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-stat label="Employees" :value="$aggregate['employees']" color="brand">
            <x-slot:icon><x-icon.users class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Average attendance" :value="$aggregate['avg_attendance'] . '%'" color="emerald">
            <x-slot:icon><x-icon.chart class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Absent days" :value="rtrim(rtrim(number_format($aggregate['total_absent'], 1), '0'), '.')" color="rose">
            <x-slot:icon><x-icon.x class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Late arrivals" :value="$aggregate['total_late']" color="amber">
            <x-slot:icon><x-icon.clock class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Overtime hours" :value="$aggregate['total_overtime']" color="sky">
            <x-slot:icon><x-icon.plus class="size-5" /></x-slot:icon>
        </x-stat>
    </div>

    <x-filter-bar :action="route('reports.attendance')">
        <x-select label="Month" name="month" :selected="(int) $month->format('n')"
            :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Illuminate\Support\Carbon::create(null, $m, 1)->format('F')])->all()"
            class="sm:w-40" />
        <x-input label="Year" name="year" type="number" min="2000" max="2100" :value="$month->format('Y')" class="sm:w-28" />
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
                        <th>Employee</th><th>Department</th>
                        <th class="num">Working</th><th class="num">Present</th>
                        <th class="num">Paid leave</th><th class="num">Unpaid</th>
                        <th class="num">Absent</th><th class="num">Late</th>
                        <th class="num">Hours</th><th class="num">Overtime</th>
                        <th class="num">Attendance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php $t = $row['totals']; @endphp
                        <tr>
                            <td>
                                <a href="{{ route('attendance.employee', $row['employee']) }}"
                                    class="font-medium text-slate-900 hover-ink">{{ $row['employee']->full_name }}</a>
                                <div class="font-mono text-xs text-slate-500">{{ $row['employee']->employee_code }}</div>
                            </td>
                            <td class="text-sm">{{ $row['employee']->department?->name ?? '-' }}</td>
                            <td class="num tabular-nums">{{ $t['working_days'] }}</td>
                            <td class="num tabular-nums">{{ rtrim(rtrim(number_format($t['present_days'], 1), '0'), '.') }}</td>
                            <td class="num tabular-nums">{{ rtrim(rtrim(number_format($t['paid_leave_days'], 1), '0'), '.') }}</td>
                            <td class="num tabular-nums">{{ rtrim(rtrim(number_format($t['unpaid_leave_days'], 1), '0'), '.') }}</td>
                            <td class="num tabular-nums {{ $t['absent_days'] > 0 ? 'text-rose-600' : '' }}">
                                {{ rtrim(rtrim(number_format($t['absent_days'], 1), '0'), '.') }}
                            </td>
                            <td class="num tabular-nums {{ $t['late_days'] > 0 ? 'text-amber-600' : '' }}">{{ $t['late_days'] }}</td>
                            <td class="num tabular-nums">{{ $t['worked_hours'] }}</td>
                            <td class="num tabular-nums">{{ $t['overtime_hours'] }}</td>
                            <td class="num">
                                <span @class([
                                    'font-semibold tabular-nums',
                                    'text-emerald-600' => $t['attendance_percentage'] >= 95,
                                    'text-amber-600' => $t['attendance_percentage'] < 95 && $t['attendance_percentage'] >= 85,
                                    'text-rose-600' => $t['attendance_percentage'] < 85,
                                ])>{{ $t['attendance_percentage'] }}%</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><x-empty title="No employees match this report" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>
