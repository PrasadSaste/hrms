@props(['size' => 'md', 'showName' => true, 'inverted' => false, 'onNav' => false])

@php
    $company = App\Models\Setting::get('company_name', config('app.name'));
    $logo = App\Models\Setting::get('company_logo');

    $box = ['sm' => 'size-8 text-xs', 'md' => 'size-9 text-sm', 'lg' => 'size-11 text-base'][$size] ?? 'size-9 text-sm';
    // A logo keeps its own proportions so a wide wordmark is not squashed into
    // a square; only the height is fixed.
    $logoBox = ['sm' => 'h-8 max-w-32', 'md' => 'h-9 max-w-40', 'lg' => 'h-11 max-w-48'][$size] ?? 'h-9 max-w-40';
    $text = ['sm' => 'text-sm', 'md' => 'text-sm', 'lg' => 'text-lg'][$size] ?? 'text-sm';

    // Initials stand in until a logo is uploaded: "Beyond Sure" becomes "BS".
    $initials = collect(preg_split('/[\s\-]+/', trim((string) $company)))
        ->filter()
        ->take(2)
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('') ?: 'HR';
@endphp

<span {{ $attributes->class(['flex min-w-0 items-center gap-2.5']) }}>
    @if ($logo)
        {{-- The logo speaks for itself: the company name is not repeated beside it. --}}
        <img src="{{ route('branding.logo') }}?v={{ substr(md5($logo), 0, 8) }}" alt="{{ $company }}"
            class="{{ $logoBox }} w-auto shrink-0 object-contain object-left">
    @else
        <span class="{{ $box }} flex shrink-0 items-center justify-center rounded-lg font-bold"
            style="background-image: linear-gradient(135deg, var(--color-brand-600), var(--color-accent-600));
                   color: var(--color-brand-foreground, #fff)">{{ $initials }}</span>

        @if ($showName)
            {{-- On the navigation column the name takes the theme's own text
                 colour, because that column can be light or dark and a fixed
                 slate would disappear into one of them. --}}
            <span @class([
                'truncate font-semibold',
                $text,
                'text-white' => $inverted,
                'text-slate-900' => ! $inverted && ! $onNav,
            ]) @style(['color: var(--surface-nav-text)' => $onNav && ! $inverted])>{{ $company }}</span>
        @endif
    @endif
</span>
