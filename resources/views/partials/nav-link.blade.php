@php
    $pattern = str_replace('.index', '.*', $item['route']);
    $active = request()->routeIs($item['route']) || request()->routeIs($pattern);
    $locked = $item['locked'] ?? false;
@endphp

{{-- The colours are theme variables rather than utility classes, because the
     navigation column can be light or dark and one set of classes cannot be
     both. `nav-link` in the stylesheet holds the hover rules. --}}
@if ($locked)
    {{-- Closed until background verification clears: the link leads to the
         screen where that happens rather than to a redirect. --}}
    <a href="{{ route('my-verification.edit') }}"
        class="nav-link is-locked group flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition"
        style="color: var(--surface-nav-muted)"
        title="Opens once your background verification is complete"
        aria-label="{{ $item['label'] }}, locked until your background verification is complete">
        <span class="shrink-0" style="color: var(--surface-nav-muted)">
            <x-dynamic-component :component="'icon.' . $item['icon']" class="size-4" />
        </span>
        <span class="truncate">{{ $item['label'] }}</span>
        <x-icon.lock class="ml-auto size-3.5 shrink-0" />
    </a>
@else
    <a href="{{ route($item['route']) }}"
        @class(['nav-link group flex items-center gap-2.5 rounded px-2.5 py-1.5 text-[13px] font-medium transition', 'is-active' => $active])
        @style([
            'background-color: var(--surface-nav-active); color: var(--surface-nav-active-text)' => $active,
            'color: var(--surface-nav-text)' => ! $active,
        ])>
        <span class="nav-link-icon shrink-0"
            style="color: var({{ $active ? '--surface-nav-active-icon' : '--surface-nav-icon' }})">
            <x-dynamic-component :component="'icon.' . $item['icon']" class="size-4" />
        </span>
        <span class="truncate">{{ $item['label'] }}</span>
    </a>
@endif
