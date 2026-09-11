@props([
    'segments' => [],  // [['label' => 'Awaiting a decision', 'value' => 4, 'status' => 'warning', 'icon' => 'clock'], ...]
    'total' => null,
])

{{--
    Where a queue of requests currently stands.

    These are states, not series, so they wear the reserved status colours —
    and every one of them ships with an icon and a written label beside its
    count. Warning and serious sit below 3:1 on a white card by design; the
    icon and the words are what carry the meaning, never the colour alone.
--}}
@php
    $segments = collect($segments)->filter(fn ($s) => (int) $s['value'] > 0)->values();
    $total ??= $segments->sum('value');
@endphp

<div {{ $attributes->merge(['class' => 'chart chart--status']) }}>
    @if ($total > 0)
        <div class="chart__stack" role="img"
            aria-label="{{ $segments->map(fn ($s) => $s['value'].' '.Str::lower($s['label']))->implode(', ') }}">
            @foreach ($segments as $segment)
                <span class="chart__stack-part chart__stack-part--{{ $segment['status'] }}"
                    style="flex-grow: {{ (int) $segment['value'] }}"
                    title="{{ $segment['label'] }}: {{ $segment['value'] }}"></span>
            @endforeach
        </div>

        <ul class="chart__status-key">
            @foreach ($segments as $segment)
                <li>
                    <x-dynamic-component :component="'icon.'.($segment['icon'] ?? 'info')"
                        class="chart__status-icon chart__status-icon--{{ $segment['status'] }}" />
                    <span class="chart__status-label">{{ $segment['label'] }}</span>
                    <b>{{ (int) $segment['value'] }}</b>
                </li>
            @endforeach
        </ul>
    @else
        <p class="chart__empty">Nothing waiting.</p>
    @endif
</div>
