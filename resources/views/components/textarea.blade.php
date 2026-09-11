@props(['label' => null, 'name', 'value' => null, 'rows' => 4, 'help' => null, 'required' => false])

<div {{ $attributes->only('class')->class(['w-full']) }}>
    @if ($label)
        <label class="form-label" for="{{ $name }}">
            {{ $label }}
            @if ($required)<span class="text-rose-500">*</span>@endif
        </label>
    @endif

    <textarea
        name="{{ $name }}"
        id="{{ $name }}"
        rows="{{ $rows }}"
        @if ($required) required @endif
        {{ $attributes->except('class')->class(['form-textarea', 'border-rose-400' => $errors->has($name)]) }}
    >{{ old($name, $value) }}</textarea>

    @if ($help)
        <p class="form-help">{{ $help }}</p>
    @endif

    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
