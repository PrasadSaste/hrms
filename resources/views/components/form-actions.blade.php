@props(['note' => null, 'align' => 'start'])

{{--
    Where a form's Save and Cancel live.

    Sticky to the bottom of the screen, which is the whole point: on a form
    taller than the viewport the bar floats above the page so Save is always one
    click away, and on a form that fits it settles at the end of the content
    exactly where it would have been anyway. That is `position: sticky`
    behaving as designed rather than anything clever — no measuring, no
    JavaScript deciding, nothing to go wrong when a section is expanded or the
    window is resized.

    The only script involved adds a shadow while it is genuinely floating, and
    the bar works without it.
--}}
<div data-form-actions
    {{ $attributes->class([
        'form-actions',
        'form-actions--end' => $align === 'end',
    ]) }}>
    <div class="form-actions__inner">
        <div class="form-actions__buttons">
            {{ $slot }}
        </div>

        @if ($note)
            <p class="form-actions__note">{{ $note }}</p>
        @endif
    </div>
</div>
