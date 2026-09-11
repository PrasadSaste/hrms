@props(['name', 'title', 'size' => 'md', 'open' => false, 'description' => null])

{{--
    A modal that behaves on every screen: on a phone it rises from the bottom
    and takes the full width, on a desktop it floats in the middle. The header
    and the optional footer stay put while the body scrolls, so the buttons
    never fall off the bottom of a long form.

    Pass `open` to render it already showing, for instance when the form inside
    it came back with validation errors. A footer slot is rendered below the
    scrolling body; a button in it can submit the form in the body with the
    `form` attribute.
--}}

@php
    $widths = ['sm' => 'sm:max-w-md', 'md' => 'sm:max-w-xl', 'lg' => 'sm:max-w-3xl', 'xl' => 'sm:max-w-5xl'];
@endphp

<div data-dialog="{{ $name }}"
    @class(['fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-xs', 'hidden' => ! $open])
    role="dialog" aria-modal="true" aria-label="{{ $title }}">
    <div class="flex min-h-full items-end justify-center sm:items-start sm:p-6 sm:pt-16">
        <div class="flex max-h-dvh w-full flex-col rounded-t-2xl bg-white shadow-xl sm:max-h-[calc(100dvh-5.5rem)] sm:rounded-xl {{ $widths[$size] ?? $widths['md'] }}">
            <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                    @if ($description)
                        <p class="mt-0.5 text-sm text-slate-500">{{ $description }}</p>
                    @endif
                </div>
                <button type="button" data-dialog-close="{{ $name }}"
                    class="-m-1 shrink-0 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Close">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-[10px]">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="shrink-0 rounded-b-xl border-t border-slate-200 bg-slate-50 px-5 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
