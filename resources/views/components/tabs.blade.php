@props(['tabs' => [], 'key' => null, 'active' => null])

@php
    // Panels are written by the caller as <div data-tab-panel="slug">…</div>.
    // Without JavaScript every panel stays visible, so the page still works.
    $storageKey = $key ?? md5(implode('|', array_keys($tabs)));
    $first = $active ?? array_key_first($tabs);
@endphp

<div data-tabs="{{ $storageKey }}" data-tabs-default="{{ $first }}" {{ $attributes->class(['space-y-5']) }}>
    <div class="border-b border-slate-200">
        <nav class="-mb-px flex gap-0.5 overflow-x-auto" role="tablist" aria-label="Sections">
            @foreach ($tabs as $slug => $label)
                {{-- .page-tab: the same section tab the page band uses, so a
                     record's sections look the same wherever they are drawn. --}}
                <button type="button" role="tab" data-tab-button="{{ $slug }}"
                    aria-selected="{{ $slug === $first ? 'true' : 'false' }}"
                    class="page-tab">
                    {{ $label }}
                    <span data-tab-badge
                        class="hidden size-1.5 rounded-full bg-rose-500"
                        title="This section has a problem to fix"></span>
                </button>
            @endforeach
        </nav>
    </div>

    {{ $slot }}
</div>
