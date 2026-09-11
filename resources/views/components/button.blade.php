@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'submit'])

@php
    $variants = [
        'primary' => 'bg-brand-600 text-white shadow-xs hover:bg-brand-700 focus-visible:outline-brand-600',
        'secondary' => 'bg-white text-slate-700 ring-1 ring-slate-300 ring-inset shadow-xs hover:bg-slate-50 focus-visible:outline-slate-400',
        'danger' => 'bg-rose-600 text-white shadow-xs hover:bg-rose-700 focus-visible:outline-rose-600',
        'success' => 'bg-emerald-600 text-white shadow-xs hover:bg-emerald-700 focus-visible:outline-emerald-600',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-slate-400',
        'link' => 'link focus-visible:outline-brand-600',
    ];
    $sizes = [
        'sm' => 'px-2.5 py-1.5 text-xs gap-1',
        'md' => 'px-3.5 py-2 text-sm gap-1.5',
        'lg' => 'px-5 py-2.5 text-sm gap-2',
    ];
    $classes = implode(' ', [
        'inline-flex cursor-pointer items-center justify-center rounded-lg font-medium transition',
        'focus-visible:outline-2 focus-visible:outline-offset-2',
        'disabled:cursor-not-allowed disabled:opacity-60',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
