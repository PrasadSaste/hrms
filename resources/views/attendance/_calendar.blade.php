{{-- Month grid shared by the personal and per-employee attendance sheets. --}}
@php
    $days = $summary['days'];
    $first = \Illuminate\Support\Carbon::parse(array_key_first($days))->startOfMonth();
    $leadingBlanks = ((int) $first->isoWeekday()) - 1;

    $tone = [
        'present' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
        'late' => 'bg-amber-50 border-amber-200 text-amber-800',
        'half_day' => 'bg-orange-50 border-orange-200 text-orange-800',
        'absent' => 'bg-rose-50 border-rose-200 text-rose-800',
        'on_leave' => 'bg-sky-50 border-sky-200 text-sky-800',
        'holiday' => 'bg-violet-50 border-violet-200 text-violet-800',
        'weekend' => 'bg-slate-50 border-slate-200 text-slate-400',
    ];
@endphp

<div class="grid grid-cols-7 gap-1.5 text-center text-xs font-medium tracking-wide text-slate-500 uppercase">
    @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $label)
        <div class="py-1">{{ $label }}</div>
    @endforeach
</div>

<div class="mt-1.5 grid grid-cols-7 gap-1.5">
    @for ($i = 0; $i < $leadingBlanks; $i++)
        <div></div>
    @endfor

    @foreach ($days as $date => $day)
        @php
            $carbon = \Illuminate\Support\Carbon::parse($date);
            $key = $day['status']?->value ?? 'weekend';
            $attendance = $day['attendance'];
        @endphp
        <div @class(['min-h-20 rounded-lg border p-1.5 text-left', $tone[$key] ?? $tone['weekend'], 'ring-1 ring-amber-400' => $attendance?->needs_correction])
            title="{{ $carbon->format('l, d M Y') }} — {{ $day['status']?->label() ?? 'Not marked' }}{{ $attendance?->needs_correction ? ' — punch-out assumed, correct it if it is wrong' : '' }}">
            <div class="flex items-baseline justify-between">
                <span class="text-sm font-semibold">{{ $carbon->format('j') }}</span>
                @if ($carbon->isToday())
                    <span class="rounded bg-brand-600 px-1 text-[9px] font-semibold text-white">TODAY</span>
                @endif
            </div>

            @if ($attendance?->check_in)
                <p class="mt-1 text-[11px] leading-tight">
                    {{ $attendance->check_in->format('H:i') }}
                    @if ($attendance->check_out) &ndash; {{ $attendance->check_out->format('H:i') }} @endif
                </p>
                <p class="text-[10px] opacity-75">{{ $attendance->durationLabel() }}</p>
                @if ($attendance->late_minutes > 0)
                    <p class="text-[10px] font-medium">+{{ $attendance->late_minutes }}m late</p>
                @endif
            @elseif ($day['leave'])
                <p class="mt-1 text-[11px] leading-tight">{{ $day['leave']['request']->leaveType->name }}</p>
            @elseif ($key === 'holiday')
                <p class="mt-1 text-[11px] leading-tight">Holiday</p>
            @elseif ($key === 'absent')
                <p class="mt-1 text-[11px] leading-tight">Absent</p>
            @endif
        </div>
    @endforeach
</div>

<div class="mt-4 flex flex-wrap gap-3 text-xs text-slate-500">
    @foreach (['present' => 'Present', 'late' => 'Late', 'half_day' => 'Half day', 'absent' => 'Absent', 'on_leave' => 'On leave', 'holiday' => 'Holiday', 'weekend' => 'Week off'] as $key => $label)
        <span class="flex items-center gap-1.5">
            <span class="size-3 rounded border {{ $tone[$key] }}"></span>{{ $label }}
        </span>
    @endforeach
</div>
