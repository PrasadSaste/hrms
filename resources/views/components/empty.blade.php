@props(['title' => 'Nothing here yet', 'message' => null, 'description' => null])

@php
    // Several screens call the line "description". It reads as the same thing,
    // and without this the text was silently rendered as an attribute on the
    // wrapper instead of shown to anybody.
    $message ??= $description;
@endphp

<div {{ $attributes->except('description')->class(['px-6 py-14 text-center']) }}>
    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
        </svg>
    </div>
    <h3 class="mt-3 text-sm font-semibold text-slate-900">{{ $title }}</h3>
    @if ($message)
        <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">{{ $message }}</p>
    @endif
    @isset($action)
        <div class="mt-5 flex justify-center">{{ $action }}</div>
    @endisset
</div>
