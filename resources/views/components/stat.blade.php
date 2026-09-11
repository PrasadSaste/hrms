@props(['label', 'value', 'sublabel' => null, 'color' => 'brand', 'href' => null])

@php
    $tint = [
        'brand' => 'bg-brand-50 text-brand-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'rose' => 'bg-rose-50 text-rose-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'sky' => 'bg-sky-50 text-sky-600',
        'violet' => 'bg-violet-50 text-violet-600',
        'slate' => 'bg-slate-100 text-slate-600',
    ][$color] ?? 'bg-brand-50 text-brand-600';
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['card flex items-center gap-3 px-3.5 py-3 transition', 'hover:border-brand-400 hover:bg-brand-50/40' => (bool) $href]) }}>
    @isset($icon)
        <span class="flex size-9 shrink-0 items-center justify-center rounded {{ $tint }}">
            {{ $icon }}
        </span>
    @endisset
    <div class="min-w-0">
        <p class="eyebrow truncate">{{ $label }}</p>
        <p class="mt-1 text-xl font-semibold text-slate-900 tabular-nums">{{ $value }}</p>
        @if ($sublabel)
            <p class="mt-0.5 truncate text-[11.5px] text-slate-500">{{ $sublabel }}</p>
        @endif
    </div>
</{{ $tag }}>
