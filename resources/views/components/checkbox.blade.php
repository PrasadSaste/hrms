@props(['label', 'name', 'checked' => false, 'value' => 1, 'help' => null])

<label class="flex items-start gap-2.5">
    <input type="hidden" name="{{ $name }}" value="0">
    <input
        type="checkbox"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ $value }}"
        @checked((bool) old($name, $checked))
        {{ $attributes->class(['form-checkbox mt-0.5']) }}
    >
    <span>
        <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
        @if ($help)
            <span class="block text-xs text-slate-500">{{ $help }}</span>
        @endif
    </span>
</label>
