<x-app-layout title="Monthly in-out report">
    <x-page-header title="Monthly in-out report"
        :subtitle="$month->format('F Y') . ' · the first punch in and last punch out of each day'"
        :back="route('reports.index')">
        <x-slot:actions>
            <x-button :href="route('reports.attendance.in-out.export', request()->query())" variant="secondary">
                <x-icon.download class="size-4" /> Export CSV
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('reports.attendance.in-out')">
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
        @if ($rows->isEmpty())
            {{-- A month-wide table with no rows is just an empty scrollbar. --}}
            <x-empty title="Nobody to report on" message="No employees match these filters." />
        @else
        <div class="table-wrap is-scrollable">
            <table class="grid-report">
                <thead>
                    <tr>
                        <th class="sticky-col text-left" rowspan="2">Employee</th>
                        @foreach ($days as $day)
                            <th colspan="2" @class(['border-l border-slate-200', 'text-slate-400' => $day->isWeekend()])>
                                {{ $day->format('j') }}
                                <span class="text-[10px] font-normal text-slate-400">{{ $day->format('D') }}</span>
                            </th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach ($days as $day)
                            <th class="min-w-20 border-l border-slate-200 text-[10px] font-medium">In</th>
                            <th class="min-w-20 text-[10px] font-medium">Out</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="sticky-col">
                                <div class="flex items-center gap-2.5">
                                    <x-avatar :name="$row['employee']->full_name" size="sm" />
                                    <div class="min-w-0">
                                        <a href="{{ route('attendance.employee', $row['employee']) }}"
                                            class="font-medium text-slate-900 hover-ink">{{ $row['employee']->full_name }}</a>
                                        <div class="font-mono text-xs text-slate-500">{{ $row['employee']->employee_code }}</div>
                                    </div>
                                </div>
                            </td>

                            @foreach ($row['cells'] as $cell)
                                @if ($cell['in'])
                                    <td class="border-l border-slate-200">
                                        <span class="cell block cell-value">{{ $cell['in']->format('h:i A') }}</span>
                                    </td>
                                    <td>
                                        @if ($cell['out'])
                                            <span class="cell block cell-value">{{ $cell['out']->format('h:i A') }}</span>
                                        @else
                                            <span class="cell block cell-open">Still in</span>
                                        @endif
                                    </td>
                                @else
                                    {{-- No punch: one cell across both columns says why. --}}
                                    <td colspan="2" class="border-l border-slate-200">
                                        <span class="cell block cell-{{ $cell['tone'] }}"
                                            title="{{ $cell['date']->format('D, d M Y') }}">
                                            @switch ($cell['tone'])
                                                @case('off')
                                                    Weekly off
                                                    @break
                                                @case('holiday')
                                                    Holiday
                                                    @break
                                                @case('leave')
                                                    Leave
                                                    @break
                                                @case('absent')
                                                    A
                                                    @break
                                                @default
                                                    &nbsp;
                                            @endswitch
                                        </span>
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($paginator->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">{{ $paginator->links() }}</div>
        @endif
        @endif
    </x-card>

    @unless ($rows->isEmpty())
        <x-reports.legend :tones="['open', 'absent', 'leave', 'off', 'holiday']" />
    @endunless
</x-app-layout>
