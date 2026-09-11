@props(['name' => '?', 'src' => null, 'size' => 'md'])

@php
    $sizes = [
        'xs' => 'size-6 text-[10px]',
        'sm' => 'size-8 text-xs',
        'md' => 'size-10 text-sm',
        'lg' => 'size-14 text-lg',
        'xl' => 'size-20 text-2xl',
    ];
    $initials = collect(explode(' ', trim((string) $name)))
        ->filter()->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    // A stable colour per person so avatars stay recognisable between pages.
    $tints = [
        'bg-brand-100 text-brand-700', 'bg-emerald-100 text-emerald-700', 'bg-amber-100 text-amber-700',
        'bg-rose-100 text-rose-700', 'bg-violet-100 text-violet-700', 'bg-sky-100 text-sky-700',
    ];
    $tint = $tints[crc32((string) $name) % count($tints)];
@endphp

@if ($src)
    <img src="{{ $src }}" alt="{{ $name }}"
        {{ $attributes->class(['shrink-0 rounded-full object-cover ring-1 ring-slate-200', $sizes[$size] ?? $sizes['md']]) }}>
@else
    <span {{ $attributes->class([
        'inline-flex shrink-0 items-center justify-center rounded-full font-semibold',
        $sizes[$size] ?? $sizes['md'], $tint,
    ]) }} title="{{ $name }}">{{ $initials ?: '?' }}</span>
@endif
