@props(['color' => 'slate', 'dot' => false])

@php
    $palette = [
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-500/20',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'orange' => 'bg-orange-50 text-orange-700 ring-orange-600/20',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-600/20',
        'brand' => 'bg-brand-50 text-brand-700 ring-brand-600/20',
    ];
    $dots = [
        'slate' => 'bg-slate-500', 'emerald' => 'bg-emerald-500', 'rose' => 'bg-rose-500',
        'amber' => 'bg-amber-500', 'orange' => 'bg-orange-500', 'sky' => 'bg-sky-500',
        'violet' => 'bg-violet-500', 'brand' => 'bg-brand-500',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded px-1.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset whitespace-nowrap',
    $palette[$color] ?? $palette['slate'],
]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full {{ $dots[$color] ?? $dots['slate'] }}"></span>
    @endif
    {{ $slot }}
</span>
