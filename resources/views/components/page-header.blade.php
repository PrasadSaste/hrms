@props([
    'title',
    'subtitle' => null,
    'back' => null,
    'backLabel' => 'Back',
])

{{--
    The band every screen opens with. It spans the content area rather than
    floating in it, carries a brand hairline along the top, and docks the
    record's tabs to its lower edge — so a page reads as part of one
    instrument instead of a document on a grey background.

    Slots: `actions` on the right, `meta` for a strip of key facts, `tabs` for
    the section bar along the bottom. All optional; a plain screen passing only
    a title looks exactly as it did.
--}}
<div {{ $attributes->class(['page-chrome']) }}>
    <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
        <div class="min-w-0">
            @if ($back)
                <a href="{{ $back }}" class="page-chrome__crumb">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                    {{ $backLabel }}
                </a>
            @endif

            <h1 class="page-chrome__title @if ($back) mt-0.5 @endif">{{ $title }}</h1>

            @if ($subtitle)
                <p class="mt-0.5 text-[12.5px] text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>

    @isset($meta)
        <div class="mt-3 border-t border-slate-100 pt-3">{{ $meta }}</div>
    @endisset

    <div @class(['h-3' => ! isset($tabs)])></div>

    @isset($tabs)
        <div class="-mb-px flex gap-0.5 overflow-x-auto pt-3">{{ $tabs }}</div>
    @endisset
</div>
