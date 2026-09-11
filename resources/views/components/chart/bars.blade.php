@props([
    'rows' => [],          // [['label' => 'Engineering', 'value' => 12], ...]
    'label' => 'Total',
    'series' => 'brand',
    'limit' => 8,          // past this, the tail folds into "Other" — never a new hue
])

{{--
    Magnitude by category, ranked. Horizontal, because a category name reads
    along a line rather than turned on its side.

    Every bar is labelled with its value at the tip, so the chart never depends
    on the reader judging length against a gridline.
--}}
@php
    $rows = collect($rows)->sortByDesc('value')->values();

    if ($rows->count() > $limit) {
        $tail = $rows->slice($limit);
        $rows = $rows->take($limit)->push([
            'label' => 'Other ('.$tail->count().')',
            'value' => $tail->sum('value'),
        ])->values();
    }

    $peak = max(1, (int) $rows->max('value'));
@endphp

<div {{ $attributes->merge(['class' => 'chart chart--bars']) }}>
    @foreach ($rows as $row)
        <div class="chart__bar-row" tabindex="0"
            aria-label="{{ $row['label'] }}: {{ (int) $row['value'] }}">
            <span class="chart__bar-label">{{ $row['label'] }}</span>
            <span class="chart__bar-track">
                <span class="chart__bar chart__bar--{{ $series }}"
                    style="width: {{ (int) $row['value'] === 0 ? '2px' : max(2, round($row['value'] / $peak * 100)).'%' }}"></span>
            </span>
            <span class="chart__bar-value">{{ (int) $row['value'] }}</span>
        </div>
    @endforeach

    @if ($rows->isEmpty())
        <p class="chart__empty">Nothing to show yet.</p>
    @endif
</div>
