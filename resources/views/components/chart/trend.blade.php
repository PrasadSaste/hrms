@props([
    'points' => [],   // [['label' => 'w/c 04 Aug', 'raised' => 9, 'decided' => 7], ...]
    'series' => [],   // ['raised' => 'Raised', 'decided' => 'Decided'] — in slot order
])

{{--
    Two series over time, drawn as an SVG line chart.

    Two series means a legend is not optional — it is the identity channel that
    does not depend on telling two colours apart. The end of each line is also
    labelled, so the two are named twice over.

    Colours are the validated categorical slots 1 and 2, fixed in that order.
    They are deliberately *not* the installation's brand colour: an
    administrator may pick any colour they like, and a pair chosen at runtime
    cannot be checked for the separation two series need.
--}}
@php
    $points = collect($points)->values();
    $keys = array_keys($series);
    $peak = max(1, $points->flatMap(fn ($p) => array_map(fn ($k) => (int) ($p[$k] ?? 0), $keys))->max());

    $w = 100; $h = 42; $pad = 3;
    $x = fn ($i) => $points->count() < 2 ? $w / 2 : $pad + $i * (($w - 2 * $pad) / ($points->count() - 1));
    $y = fn ($v) => $h - $pad - ($v / $peak) * ($h - 2 * $pad);

    $line = function (string $key) use ($points, $x, $y) {
        return $points->map(fn ($p, $i) => ($i ? 'L' : 'M').round($x($i), 2).' '.round($y((int) ($p[$key] ?? 0)), 2))->implode(' ');
    };
    $id = 'trend-'.Str::random(6);
@endphp

<figure {{ $attributes->merge(['class' => 'chart chart--trend']) }} role="group" aria-labelledby="{{ $id }}-cap">
    {{-- Legend first: it is what identity actually rests on. --}}
    <ul class="chart__legend">
        @foreach ($series as $key => $name)
            <li><span class="chart__swatch chart__swatch--s{{ $loop->iteration }}"></span>{{ $name }}</li>
        @endforeach
    </ul>

    <div class="chart__trend-plot">
        <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" aria-hidden="true" focusable="false">
            {{-- One recessive baseline. No grid: two lines and an end label
                 carry it, and gridlines would out-ink the data. --}}
            <line x1="{{ $pad }}" y1="{{ $h - $pad }}" x2="{{ $w - $pad }}" y2="{{ $h - $pad }}"
                class="chart__axis" vector-effect="non-scaling-stroke" />

            @foreach ($keys as $i => $key)
                <path d="{{ $line($key) }}" class="chart__line chart__line--s{{ $i + 1 }}"
                    vector-effect="non-scaling-stroke" fill="none" />
                @if ($points->isNotEmpty())
                    <circle cx="{{ round($x($points->count() - 1), 2) }}"
                        cy="{{ round($y((int) ($points->last()[$key] ?? 0)), 2) }}"
                        r="1.6" class="chart__dot chart__dot--s{{ $i + 1 }}" />
                @endif
            @endforeach
        </svg>

        {{-- Hover targets sit above the drawing, one per period, full height,
             so the tooltip is easy to hit rather than needing the line itself. --}}
        <div class="chart__hits">
            @foreach ($points as $point)
                <div class="chart__hit" tabindex="0"
                    aria-label="{{ $point['label'] }}: {{ collect($series)->map(fn ($n, $k) => (int) ($point[$k] ?? 0).' '.Str::lower($n))->implode(', ') }}">
                    <span class="chart__tip" role="tooltip">
                        <strong>{{ $point['label'] }}</strong>
                        @foreach ($series as $key => $name)
                            <span class="chart__tip-row">
                                <span class="chart__swatch chart__swatch--s{{ $loop->iteration }}"></span>
                                {{ $name }} <b>{{ (int) ($point[$key] ?? 0) }}</b>
                            </span>
                        @endforeach
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="chart__trend-ticks">
        @foreach ($points as $point)
            <span>{{ $loop->first || $loop->last || $loop->index % 2 === 0 ? $point['label'] : '' }}</span>
        @endforeach
    </div>

    <figcaption id="{{ $id }}-cap" class="sr-only">
        {{ implode(' and ', array_values($series)) }} for each of the last {{ $points->count() }} periods.
    </figcaption>

    <details class="chart__table">
        <summary>See the figures</summary>
        <table>
            <thead>
                <tr><th scope="col">Period</th>@foreach ($series as $name)<th scope="col">{{ $name }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @foreach ($points as $point)
                    <tr>
                        <th scope="row">{{ $point['label'] }}</th>
                        @foreach ($keys as $key)<td>{{ (int) ($point[$key] ?? 0) }}</td>@endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </details>
</figure>
