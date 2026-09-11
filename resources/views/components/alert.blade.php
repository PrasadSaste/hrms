@props(['type' => 'info', 'title' => null, 'dismissible' => true])

@php
    $styles = [
        'success' => ['bg-emerald-50 text-emerald-800 ring-emerald-600/20', 'text-emerald-500'],
        'error' => ['bg-rose-50 text-rose-800 ring-rose-600/20', 'text-rose-500'],
        'warning' => ['bg-amber-50 text-amber-800 ring-amber-600/20', 'text-amber-500'],
        'info' => ['bg-sky-50 text-sky-800 ring-sky-600/20', 'text-sky-500'],
    ];
    [$box, $iconColor] = $styles[$type] ?? $styles['info'];
@endphp

<div data-flash {{ $attributes->class(['flex items-start gap-3 rounded-lg px-4 py-3 text-sm ring-1 ring-inset', $box]) }} role="alert">
    <svg class="mt-0.5 size-5 shrink-0 {{ $iconColor }}" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
        @if ($type === 'success')
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        @elseif ($type === 'error')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
        @elseif ($type === 'warning')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z" />
        @else
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
        @endif
    </svg>

    <div class="min-w-0 flex-1">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div @class(['mt-0.5' => (bool) $title])>{{ $slot }}</div>
    </div>

    @if ($dismissible)
        <button type="button" data-flash-dismiss class="shrink-0 rounded p-0.5 opacity-60 transition hover:opacity-100" aria-label="Dismiss">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    @endif
</div>
