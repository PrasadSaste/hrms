@props(['tones' => ['full', 'partial', 'short', 'open', 'absent', 'leave', 'off', 'holiday']])

@php
    // Only the tones a report actually uses, so the key does not explain
    // colours that never appear on the page below it.
    $labels = [
        'full' => 'Full day',
        'partial' => 'Half day or more',
        'short' => 'Under half a day',
        'open' => 'Still punched in',
        'absent' => 'Absent',
        'leave' => 'On leave',
        'off' => 'Weekly off',
        'holiday' => 'Holiday',
    ];
@endphp

<div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-500">
    @foreach ($tones as $tone)
        <span class="flex items-center gap-1.5">
            <span class="size-3 rounded cell-{{ $tone }}"></span>
            {{ $labels[$tone] ?? $tone }}
        </span>
    @endforeach
</div>
