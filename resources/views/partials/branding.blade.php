{{--
    Re-declares the theme that the stylesheet defines as Tailwind tokens: the
    primary, secondary and tertiary ramps and the shell's own surfaces. Changing
    a colour in Settings re-themes the whole interface without rebuilding the
    CSS.
--}}
@php
    $brand = app(App\Services\BrandPalette::class);
    // Changing the icon or any theme colour changes this token, so browsers
    // pick the new tab icon up instead of holding on to the old one.
    $iconVersion = substr(md5(
        (string) App\Models\Setting::get('company_favicon').implode('', $brand->colors()),
    ), 0, 8);
@endphp

<meta name="theme-color" content="{{ $brand->color() }}">
<link rel="icon" href="{{ route('branding.favicon') }}?v={{ $iconVersion }}" sizes="any">
<link rel="apple-touch-icon" href="{{ route('branding.favicon') }}?v={{ $iconVersion }}">
<style>
    :root {
        {!! $brand->cssVariables() !!}
        /* Kept for anything still asking for the old name. */
        --color-brand-foreground: {{ $brand->foregroundOn() }};
    }
</style>
