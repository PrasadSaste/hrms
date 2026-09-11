@props(['label' => null, 'name', 'value' => null, 'type' => 'text', 'help' => null, 'required' => false])

<div {{ $attributes->only('class')->class(['w-full']) }}>
    @if ($label)
        <label class="form-label" for="{{ $name }}">
            {{ $label }}
            @if ($required)<span class="text-rose-500">*</span>@endif
        </label>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ old($name, $value) }}"
        @if ($required) required @endif
        {{ $attributes->except('class')->class(['form-input', 'border-rose-400' => $errors->has($name)]) }}
    >

    @if ($help)
        <p class="form-help">{{ $help }}</p>
    @endif

    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
