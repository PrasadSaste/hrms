<x-app-layout title="Leave report">
    <x-page-header title="Leave report"
        :subtitle="'Approved leave from ' . $from->format('d M Y') . ' to ' . $to->format('d M Y')"
        :back="route('reports.index')" />

    <x-filter-bar :action="route('reports.leave')">
        <x-input label="From" name="from" type="date" :value="$from->toDateString()" class="sm:w-44" />
        <x-input label="To" name="to" type="date" :value="$to->toDateString()" class="sm:w-44" />
        <x-select label="Branch" name="branch_id" :selected="request('branch_id')" placeholder="All branches"
            :options="$branches->pluck('name', 'id')->all()" class="sm:w-44" />
        <x-select label="Department" name="department_id" :selected="request('department_id')" placeholder="All departments"
            :options="$departments->pluck('name', 'id')->all()" class="sm:w-44" />
        <x-select label="Leave type" name="leave_type_id" :selected="request('leave_type_id')" placeholder="All types"
            :options="$leaveTypes->pluck('name', 'id')->all()" class="sm:w-44" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Run report</x-button>
    </x-filter-bar>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="Leave taken per employee" :padded="false">
                <div class="table-wrap is-scrollable">
                    <table class="table">
                        <thead>
                            <tr><th>Employee</th><th>Department</th><th>Breakdown</th>
                                <th class="num">Requests</th><th class="num">Days</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($byEmployee as $row)
                                <tr>
                                    <td>
                                        <div class="font-medium text-slate-900">{{ $row['employee']->full_name }}</div>
                                        <div class="font-mono text-xs text-slate-500">{{ $row['employee']->employee_code }}</div>
                                    </td>
                                    <td class="text-sm">{{ $row['employee']->department?->name ?? '-' }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($row['by_type'] as $slice)
                                                <span class="rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                                    style="background-color: {{ $slice['type']->color }}">
                                                    {{ $slice['type']->code }} {{ rtrim(rtrim(number_format($slice['days'], 1), '0'), '.') }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="num tabular-nums">{{ $row['count'] }}</td>
                                    <td class="num font-semibold tabular-nums">
                                        {{ rtrim(rtrim(number_format($row['days'], 1), '0'), '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-empty title="No approved leave in this period" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-stat label="Total leave days" :value="rtrim(rtrim(number_format($totalDays, 1), '0'), '.')" color="sky">
                <x-slot:icon><x-icon.calendar class="size-5" /></x-slot:icon>
            </x-stat>

            <x-card title="By leave type">
                @php $maxDays = max(1, $byType->max('days') ?? 1); @endphp
                @forelse ($byType as $row)
                    <div class="border-b border-slate-100 py-2.5 first:pt-0 last:border-0 last:pb-0">
                        <div class="mb-1 flex justify-between text-sm">
                            <span class="flex items-center gap-2 text-slate-700">
                                <span class="size-2 rounded-full" style="background-color: {{ $row['type']->color }}"></span>
                                {{ $row['type']->name }}
                            </span>
                            <span class="font-medium text-slate-900">
                                {{ rtrim(rtrim(number_format($row['days'], 1), '0'), '.') }}d
                            </span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full"
                                style="width: {{ round($row['days'] / $maxDays * 100) }}%; background-color: {{ $row['type']->color }}"></div>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ $row['count'] }} request(s)</p>
                    </div>
                @empty
                    <p class="py-4 text-center text-sm text-slate-500">No data for this period.</p>
                @endforelse
            </x-card>
        </div>
    </div>
</x-app-layout>
