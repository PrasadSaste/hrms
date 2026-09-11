@props(['label' => null, 'name', 'options' => [], 'selected' => null, 'placeholder' => null, 'help' => null, 'required' => false])

<div {{ $attributes->only('class')->class(['w-full']) }}>
    @if ($label)
        <label class="form-label" for="{{ $name }}">
            {{ $label }}
            @if ($required)<span class="text-rose-500">*</span>@endif
        </label>
    @endif

    <select
        name="{{ $name }}"
        id="{{ $name }}"
        @if ($required) required @endif
        {{ $attributes->except('class')->class(['form-select', 'border-rose-400' => $errors->has($name)]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @if (count($options))
            @foreach ($options as $key => $option)
                <option value="{{ $key }}" @selected((string) old($name, $selected) === (string) $key)>{{ $option }}</option>
            @endforeach
        @else
            {{ $slot }}
        @endif
    </select>

    @if ($help)
        <p class="form-help">{{ $help }}</p>
    @endif

    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
