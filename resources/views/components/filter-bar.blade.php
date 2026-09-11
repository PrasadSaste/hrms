@props(['action' => null, 'method' => 'GET', 'count' => null, 'of' => null, 'noun' => 'record'])

{{--
    A control bar, not a card. It wears the same clothes as `<x-toolbar>` and
    sits directly on top of the panel it filters, so every screen that already
    used a filter bar reads as a register with a control strip rather than two
    unrelated boxes — without any of them having to be rewritten.

    A screen that wants the bar truly attached inside the panel uses
    `<x-toolbar>` within the card instead.
--}}
@php
    $summary = $count === null
        ? null
        : ($of !== null && $of !== $count
            ? number_format($count).' of '.number_format($of).' '.Str::plural($noun, $of)
            : number_format($count).' '.Str::plural($noun, $count));
@endphp

<form method="{{ $method }}" action="{{ $action }}"
    {{ $attributes->class(['toolbar mb-4 rounded-md border border-slate-200']) }}>
    {{ $slot }}

    @if ($summary || isset($actions))
        <div class="toolbar__spacer flex items-center gap-2">
            @if ($summary)
                <span class="toolbar__count">{{ $summary }}</span>
            @endif
            @isset($actions)
                {{ $actions }}
            @endisset
        </div>
    @endif
</form>
