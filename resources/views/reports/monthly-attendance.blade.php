@php
    use App\Support\Duration;
@endphp

<x-app-layout title="Monthly attendance report">
    <x-page-header title="Monthly attendance report"
        :subtitle="$month->format('F Y') . ' · ' . strtolower($metrics[$metric]) . ' for every day of the month'"
        :back="route('reports.index')">
        <x-slot:actions>
            <x-button :href="route('reports.attendance.monthly.export', request()->query())" variant="secondary">
                <x-icon.download class="size-4" /> Export CSV
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('reports.attendance.monthly')">
        <x-select label="Measure" name="metric" :selected="$metric" :options="$metrics" class="sm:w-44" />
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
                        <th class="sticky-col text-left">Employee</th>
                        @foreach ($days as $day)
                            <th @class(['min-w-20', 'text-slate-400' => $day->isWeekend()])>
                                <span class="block">{{ $day->format('j') }}</span>
                                <span class="block text-[10px] font-normal text-slate-400">{{ $day->format('D') }}</span>
                            </th>
                        @endforeach
                        <th class="min-w-24 bg-slate-100">Total</th>
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
                                <td>
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
                                            @case('future')
                                                &nbsp;
                                                @break
                                            @case('open')
                                                Still in
                                                @break
                                            @default
                                                {{ Duration::hhmm($cell['minutes']) }}
                                        @endswitch
                                    </span>
                                </td>
                            @endforeach

                            <td class="bg-slate-50 font-semibold tabular-nums text-slate-900">
                                {{ Duration::hhmm($row['total']) }}
                            </td>
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
        <x-reports.legend />
    @endunless
</x-app-layout>
