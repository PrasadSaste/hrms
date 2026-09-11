<x-app-layout title="Leave calendar">
    <x-page-header title="Leave calendar" :subtitle="'Who is away in ' . $month->format('F Y')">
        <x-slot:actions>
            <form method="GET" class="flex items-center gap-2">
                <select name="month" class="form-select w-32 py-1.5 text-sm" data-auto-submit>
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}" @selected((int) $month->format('n') === $m)>
                            {{ \Illuminate\Support\Carbon::create(null, $m, 1)->format('F') }}
                        </option>
                    @endforeach
                </select>
                <select name="year" class="form-select w-24 py-1.5 text-sm" data-auto-submit>
                    @foreach (range((int) date('Y') + 1, (int) date('Y') - 3) as $y)
                        <option value="{{ $y }}" @selected((int) $month->format('Y') === $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </form>
        </x-slot:actions>
    </x-page-header>

    @php
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $leadingBlanks = ((int) $start->isoWeekday()) - 1;
    @endphp

    <x-card class="mb-6">
        <div class="grid grid-cols-7 gap-1.5 text-center text-xs font-medium tracking-wide text-slate-500 uppercase">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $label)
                <div class="py-1">{{ $label }}</div>
            @endforeach
        </div>

        <div class="mt-1.5 grid grid-cols-7 gap-1.5">
            @for ($i = 0; $i < $leadingBlanks; $i++)
                <div></div>
            @endfor

            @for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay())
                @php
                    $key = $cursor->toDateString();
                    $onLeave = $byDate[$key] ?? [];
                    $isWeekend = $cursor->isoWeekday() >= 6;
                @endphp
                <div @class([
                    'min-h-28 rounded-lg border p-2',
                    'border-slate-200 bg-white' => ! $isWeekend,
                    'border-slate-200 bg-slate-50' => $isWeekend,
                    'ring-2 ring-brand-400' => $cursor->isToday(),
                ])>
                    <div class="flex items-baseline justify-between">
                        <span @class(['text-sm font-semibold', 'text-slate-900' => ! $isWeekend, 'text-slate-400' => $isWeekend])>
                            {{ $cursor->format('j') }}
                        </span>
                        @if (count($onLeave))
                            <span class="rounded-full bg-sky-100 px-1.5 text-[10px] font-semibold text-sky-700">{{ count($onLeave) }}</span>
                        @endif
                    </div>

                    <div class="mt-1 space-y-0.5">
                        @foreach (array_slice($onLeave, 0, 3) as $leaveRequest)
                            <a href="{{ route('leave.show', $leaveRequest) }}"
                                class="block truncate rounded px-1 py-0.5 text-[10px] font-medium text-white"
                                style="background-color: {{ $leaveRequest->leaveType->color }}"
                                title="{{ $leaveRequest->employee->full_name }} — {{ $leaveRequest->leaveType->name }}">
                                {{ $leaveRequest->employee->first_name }}
                            </a>
                        @endforeach
                        @if (count($onLeave) > 3)
                            <span class="block px-1 text-[10px] text-slate-500">+{{ count($onLeave) - 3 }} more</span>
                        @endif
                    </div>
                </div>
            @endfor
        </div>
    </x-card>

    <x-card :title="'Approved leave in ' . $month->format('F Y')" :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr><th>Employee</th><th>Type</th><th>Period</th><th class="num">Days</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($requests->sortBy('start_date') as $leaveRequest)
                        <tr>
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <x-avatar :name="$leaveRequest->employee->full_name" size="xs" />
                                    <span class="font-medium text-slate-900">{{ $leaveRequest->employee->full_name }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="flex items-center gap-1.5">
                                    <span class="size-2 rounded-full" style="background-color: {{ $leaveRequest->leaveType->color }}"></span>
                                    {{ $leaveRequest->leaveType->name }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap">{{ $leaveRequest->periodLabel() }}</td>
                            <td class="num tabular-nums">
                                {{ rtrim(rtrim(number_format($leaveRequest->total_days, 1), '0'), '.') }}
                            </td>
                            <td class="num">
                                <x-button href="{{ route('leave.show', $leaveRequest) }}" variant="ghost" size="sm">View</x-button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty title="Nobody is on leave this month" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>
