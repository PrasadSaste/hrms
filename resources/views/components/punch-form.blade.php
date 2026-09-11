@props([
    'action',
    'label',
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
])

{{--
    A punch button that collects the device position before submitting.

    Without JavaScript the form still posts, and the server refuses it with the
    same explanation, so the rule never depends on the browser alone.
--}}
<form method="POST" action="{{ $action }}" data-punch-form class="contents">
    @csrf
    <input type="hidden" name="latitude" data-punch-latitude>
    <input type="hidden" name="longitude" data-punch-longitude>
    <input type="hidden" name="accuracy" data-punch-accuracy>

    <x-button :variant="$variant" :size="$size" data-punch-button>
        <span data-punch-idle class="flex items-center gap-1.5">
            {{ $icon }}
            {{ $label }}
        </span>
        <span data-punch-busy hidden class="flex items-center gap-1.5">
            <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
            </svg>
            Finding you&hellip;
        </span>
    </x-button>
</form>
