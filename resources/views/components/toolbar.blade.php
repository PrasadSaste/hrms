@props([
    'action' => null,
    'method' => 'GET',
    'count' => null,
    'of' => null,
    'noun' => 'record',
])

{{--
    The bar above a register. Filters on the left where you read first, the
    count and the actions on the right. It is attached to the panel it filters
    rather than floating above it, so what you asked for and what came back are
    plainly one thing.

    Rendered as a form when given an action, so a select inside it can carry
    `data-auto-submit` and filter on change without any script of its own.
--}}
@php
    $tag = $action ? 'form' : 'div';
    $summary = $count === null
        ? null
        : ($of !== null && $of !== $count
            ? number_format($count).' of '.number_format($of).' '.Str::plural($noun, $of)
            : number_format($count).' '.Str::plural($noun, $count));
@endphp

<{{ $tag }}
    @if ($action) method="{{ $method }}" action="{{ $action }}" @endif
    {{ $attributes->class(['toolbar']) }}
>
    {{ $slot }}

    <div class="toolbar__spacer flex items-center gap-2">
        @if ($summary)
            <span class="toolbar__count">{{ $summary }}</span>
        @endif
        @isset($actions)
            {{ $actions }}
        @endisset
    </div>
</{{ $tag }}>
