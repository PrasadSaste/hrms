@props(['attendance', 'side' => 'check_in'])

@php
    $url = $attendance?->mapUrl($side);
    $accuracy = $attendance?->{$side.'_accuracy'};
@endphp

@if ($url)
    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
        {{ $attributes->class(['inline-flex items-center gap-1 text-xs text-slate-500 transition hover-ink']) }}
        title="Recorded at {{ $attendance->{$side.'_location'} }}{{ $accuracy ? ', accurate to about '.$accuracy.' m' : '' }}. Opens a map.">
        <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
        </svg>
        <span class="sr-only">Location recorded</span>
        @if ($accuracy)
            <span aria-hidden="true">&plusmn;{{ $accuracy }}m</span>
        @endif
    </a>
@endif
