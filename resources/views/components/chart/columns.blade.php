@props([
    'points' => [],        // [['label' => '02 Sep', 'value' => 12, 'full' => 'Wednesday, 02 September'], ...]
    'label' => 'Value',    // what one column counts, for the tooltip and the table
    'series' => 'brand',   // brand (single series) — never a cycled hue
    'height' => 'h-44',
    'caption' => null,
])

{{--
    Counts over time, one series.

    One series means no legend: the card's own title says what is plotted, and a
    box with a single swatch would only restate it. Every column carries its
    value in the tooltip and in the table underneath, so nothing is gated behind
    hovering — which is also the relief the contrast rule asks for.
--}}
@php
    $points = collect($points);
    $peak = max(1, (int) $points->max('value'));
    $tallest = $points->search(fn ($p) => (int) $p['value'] === $peak);
    $id = 'chart-'.Str::random(6);
@endphp

<figure {{ $attributes->merge(['class' => 'chart']) }} role="group" aria-labelledby="{{ $id }}-cap">
    <div class="chart__plot {{ $height }}">
        @foreach ($points as $i => $point)
            @php $value = (int) $point['value']; @endphp
            <div class="chart__slot" tabindex="0"
                aria-label="{{ $point['full'] ?? $point['label'] }}: {{ $value }} {{ Str::plural(Str::lower($label), $value) }}">
                <div class="chart__column-track">
                    {{-- The value rides only the tallest column: a number on
                         every one is chaos and goes unread. --}}
                    @if ($i === $tallest && $peak > 0)
                        <span class="chart__peak">{{ $value }}</span>
                    @endif
                    <div class="chart__column chart__column--{{ $series }}"
                        style="height: {{ $value === 0 ? '2px' : max(3, round($value / $peak * 100)).'%' }}"></div>
                </div>
                <span class="chart__tick">{{ $loop->index % 2 === 0 ? $point['label'] : '' }}</span>
                <span class="chart__tip" role="tooltip">
                    <strong>{{ $point['full'] ?? $point['label'] }}</strong>
                    {{ $value }} {{ Str::plural(Str::lower($label), $value) }}
                </span>
            </div>
        @endforeach
    </div>

    <figcaption id="{{ $id }}-cap" class="sr-only">
        {{ $caption ?? $label.' for each of the last '.$points->count().' days.' }}
    </figcaption>

    <details class="chart__table">
        <summary>See the figures</summary>
        <table>
            <thead><tr><th scope="col">When</th><th scope="col">{{ $label }}</th></tr></thead>
            <tbody>
                @foreach ($points as $point)
                    <tr>
                        <th scope="row">{{ $point['full'] ?? $point['label'] }}</th>
                        <td>{{ (int) $point['value'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </details>
</figure>
