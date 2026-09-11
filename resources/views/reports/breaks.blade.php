@php
    use App\Support\Duration;

    $isMonthly = $view === 'monthly';
    $query = request()->except(['view', 'page']);
@endphp

<x-app-layout title="Break report">
    <x-page-header title="Break report"
        :subtitle="$isMonthly ? $month->format('F Y') . ' · time away per employee' : $date->format('l, d M Y') . ' · every break taken'"
        :back="route('reports.index')">
        <x-slot:actions>
            {{-- Daily lists each break; monthly totals them per person. --}}
            <div class="flex rounded-lg bg-slate-100 p-0.5">
                @foreach (['daily' => 'Daily', 'monthly' => 'Monthly'] as $key => $label)
                    <a href="{{ route('reports.breaks', array_merge($query, ['view' => $key])) }}"
                        @class([
                            'rounded-md px-3 py-1.5 text-sm font-medium transition',
                            'bg-white text-slate-900 shadow-xs' => $view === $key,
                            'text-slate-500 hover:text-slate-800' => $view !== $key,
                        ])>{{ $label }}</a>
                @endforeach
            </div>

            <x-button :href="route('reports.breaks.export', request()->query())" variant="secondary">
                <x-icon.download class="size-4" /> Export CSV
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('reports.breaks')">
        <input type="hidden" name="view" value="{{ $view }}">

        @if ($isMonthly)
            <x-select label="Month" name="month" :selected="(int) $month->format('n')"
                :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Illuminate\Support\Carbon::create(null, $m, 1)->format('F')])->all()"
                class="sm:w-40" />
            <x-input label="Year" name="year" type="number" min="2000" max="2100" :value="$month->format('Y')" class="sm:w-28" />
        @else
            <x-input label="Date" name="date" type="date" :value="$date->toDateString()" class="sm:w-44" />
        @endif

        <x-select label="Branch" name="branch_id" :selected="request('branch_id')" placeholder="All branches"
            :options="$branches->pluck('name', 'id')->all()" class="sm:w-44" />
        <x-select label="Department" name="department_id" :selected="request('department_id')" placeholder="All departments"
            :options="$departments->pluck('name', 'id')->all()" class="sm:w-44" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Run report</x-button>
    </x-filter-bar>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            @if ($isMonthly)
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th class="num">Breaks</th>
                            @foreach ($reasons as $key => $reason)
                                <th class="num">{{ $reason['label'] }}</th>
                            @endforeach
                            <th class="num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('attendance.employee', $row['employee']) }}"
                                        class="font-medium text-slate-900 hover-ink">{{ $row['employee']->full_name }}</a>
                                    <div class="font-mono text-xs text-slate-500">{{ $row['employee']->employee_code }}</div>
                                </td>
                                <td class="num tabular-nums text-slate-500">{{ $row['count'] ?: '—' }}</td>
                                @foreach ($reasons as $key => $reason)
                                    <td class="num tabular-nums {{ $row['reasons'][$key] ? '' : 'text-slate-300' }}">
                                        {{ $row['reasons'][$key] ? Duration::short($row['reasons'][$key]) : '—' }}
                                    </td>
                                @endforeach
                                <td class="num font-semibold tabular-nums text-slate-900">
                                    {{ $row['total'] ? Duration::short($row['total']) : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($reasons) + 3 }}">
                                    <x-empty title="Nobody to report on"
                                        message="No employees match these filters." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Break type</th>
                            <th>Break start</th>
                            <th>Break end</th>
                            <th class="num">Duration</th>
                            <th>Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php $break = $row['break']; @endphp
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <x-avatar :name="$row['employee']->full_name" size="sm" />
                                        <div class="min-w-0">
                                            <a href="{{ route('attendance.employee', $row['employee']) }}"
                                                class="font-medium text-slate-900 hover-ink">{{ $row['employee']->full_name }}</a>
                                            <div class="font-mono text-xs text-slate-500">{{ $row['employee']->employee_code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="inline-flex items-center gap-1.5 text-slate-700">
                                        <x-dynamic-component :component="'icon.' . $break->icon()" class="size-4 text-slate-400" />
                                        {{ $break->label() }}
                                    </span>
                                </td>
                                <td class="tabular-nums">{{ $break->started_at?->format('h:i a') }}</td>
                                <td class="tabular-nums">
                                    @if ($break->ended_at)
                                        {{ $break->ended_at->format('h:i a') }}
                                    @else
                                        <x-badge color="amber" dot>Still away</x-badge>
                                    @endif
                                </td>
                                <td class="num tabular-nums">
                                    {{ $break->ended_at ? Duration::short($break->duration_minutes) : '—' }}
                                </td>
                                <td class="max-w-xs truncate text-slate-500">{{ $break->comment ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-empty title="No breaks on this day"
                                        message="Nobody in this selection recorded a break. Try another date." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>
    </x-card>
</x-app-layout>
