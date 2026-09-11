@props(['items' => []])

{{--
    The facts about one record, ruled apart along a single line: the four or
    five things somebody would otherwise open three tabs to ask. Pass
    `items` as label => value, or use the slot for anything that needs markup.
--}}
<div {{ $attributes->class(['meta-strip']) }}>
    @foreach ($items as $label => $value)
        <div class="meta-strip__item">
            <span class="meta-strip__label">{{ $label }}</span>
            <span class="meta-strip__value">{{ $value === null || $value === '' ? '—' : $value }}</span>
        </div>
    @endforeach

    {{ $slot }}
</div>
