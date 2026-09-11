@props(['amount' => 0, 'currency' => null, 'decimals' => 2])

{{-- Grouping is the Indian one for rupees; App\Support\Money decides, so every
     screen and every PDF says the same thing. --}}
<span {{ $attributes->class(['whitespace-nowrap tabular-nums']) }}>{{ \App\Support\Money::withSymbol($amount, $currency, $decimals) }}</span>
