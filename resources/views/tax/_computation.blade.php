@props(['computation', 'compact' => false])

@php $c = $computation; @endphp

<div class="space-y-5">
    @if ($c['rules_assumed'])
        <x-alert type="warning" :dismissible="false">
            {{ $c['year_label'] }} is not in the rate tables yet, so this was worked out under the
            rules for {{ App\Support\FinancialYear::label($c['rules_year']) }}. Check it against the
            current Finance Act before anybody relies on it.
        </x-alert>
    @endif

    <dl class="divide-y divide-slate-100 text-sm">
        <div class="flex items-baseline justify-between gap-4 py-2">
            <dt class="text-slate-600">Salary paid so far ({{ $c['salary']['months_paid'] }} {{ Str::plural('month', $c['salary']['months_paid']) }})</dt>
            <dd class="text-right font-medium text-slate-900"><x-money :amount="$c['salary']['paid_gross']" /></dd>
        </div>
        <div class="flex items-baseline justify-between gap-4 py-2">
            <dt class="text-slate-600">
                Expected for the rest of the year
                <span class="block text-xs text-slate-500">{{ $c['salary']['months_ahead'] }} {{ Str::plural('month', $c['salary']['months_ahead']) }} at <x-money :amount="$c['salary']['monthly_gross']" /></span>
            </dt>
            <dd class="text-right font-medium text-slate-900"><x-money :amount="$c['salary']['expected_gross']" /></dd>
        </div>

        @foreach ($c['additions']['lines'] as $line)
            <div class="flex items-baseline justify-between gap-4 py-2">
                <dt class="text-slate-600">{{ $line['label'] }}</dt>
                <dd class="text-right font-medium text-slate-900"><x-money :amount="$line['amount']" /></dd>
            </div>
        @endforeach

        <div class="flex items-baseline justify-between gap-4 bg-slate-50/60 py-2">
            <dt class="font-medium text-slate-900">Gross for the year</dt>
            <dd class="text-right font-semibold text-slate-900"><x-money :amount="$c['gross_total']" /></dd>
        </div>

        <div class="flex items-baseline justify-between gap-4 py-2">
            <dt class="text-slate-600">Standard deduction</dt>
            <dd class="text-right font-medium text-rose-600">− <x-money :amount="$c['standard_deduction']" /></dd>
        </div>

        @foreach ($c['exemptions']['lines'] as $line)
            <div class="flex items-baseline justify-between gap-4 py-2">
                <dt @class(['text-slate-600', 'text-slate-400' => ! $line['applies']])>
                    {{ $line['label'] }}
                    @if ($line['reason'])
                        <span class="block text-xs text-slate-500">
                            {{-- The helper rather than <x-money>: the component ends
                                 its output with a newline, which puts a space
                                 between the figure and the full stop. --}}
                            {{ $line['reason'] }}@if ($line['declared'] > $line['allowed']) Declared {{ App\Support\Money::withSymbol($line['declared']) }}.@endif
                        </span>
                    @endif
                </dt>
                <dd @class(['text-right font-medium', 'text-rose-600' => $line['allowed'] > 0, 'text-slate-400' => $line['allowed'] <= 0])>
                    @if ($line['allowed'] > 0) − @endif<x-money :amount="$line['allowed']" />
                </dd>
            </div>
        @endforeach

        <div class="flex items-baseline justify-between gap-4 bg-slate-50/60 py-2">
            <dt class="font-medium text-slate-900">Taxable income</dt>
            <dd class="text-right font-semibold text-slate-900"><x-money :amount="$c['taxable_income']" /></dd>
        </div>

        @foreach ($c['tax']['bands'] as $band)
            @php
                // Built here rather than inline: Blade will not read an @endif
                // that follows a word character, so "and above@endif" compiles
                // to literal text and takes the whole loop down with it.
                $from = App\Support\Money::withSymbol($band['from'], null, 0);
                $range = $band['to']
                    ? $from.' to '.App\Support\Money::withSymbol($band['to'], null, 0)
                    : $from.' and above';
            @endphp
            <div class="flex items-baseline justify-between gap-4 py-2">
                <dt class="text-slate-600">
                    {{ number_format($band['rate'] * 100, 0) }}% on
                    <x-money :amount="$band['income']" :decimals="0" />
                    <span class="text-xs text-slate-500">({{ $range }})</span>
                </dt>
                <dd class="text-right font-medium text-slate-900"><x-money :amount="$band['tax']" /></dd>
            </div>
        @endforeach

        @if ($c['tax']['rebate'] > 0)
            <div class="flex items-baseline justify-between gap-4 py-2">
                <dt class="text-slate-600">
                    Rebate under section 87A
                    <span class="block text-xs text-slate-500">Includes the marginal relief that stops a rupee of extra income costing far more than a rupee of tax.</span>
                </dt>
                <dd class="text-right font-medium text-emerald-600">− <x-money :amount="$c['tax']['rebate']" /></dd>
            </div>
        @endif

        @if ($c['tax']['surcharge'] > 0 || $c['tax']['surcharge_relief'] > 0)
            <div class="flex items-baseline justify-between gap-4 py-2">
                <dt class="text-slate-600">
                    Surcharge at {{ number_format($c['tax']['surcharge_rate'] * 100, 0) }}%
                    @if ($c['tax']['surcharge_relief'] > 0)
                        <span class="block text-xs text-slate-500">After marginal relief of {{ App\Support\Money::withSymbol($c['tax']['surcharge_relief']) }}.</span>
                    @endif
                </dt>
                <dd class="text-right font-medium text-slate-900"><x-money :amount="$c['tax']['surcharge']" /></dd>
            </div>
        @endif

        <div class="flex items-baseline justify-between gap-4 py-2">
            <dt class="text-slate-600">Health and education cess at {{ number_format($c['tax']['cess_rate'] * 100, 0) }}%</dt>
            <dd class="text-right font-medium text-slate-900"><x-money :amount="$c['tax']['cess']" /></dd>
        </div>

        <div class="flex items-baseline justify-between gap-4 border-t-2 border-slate-200 py-2.5">
            <dt class="font-semibold text-slate-900">Tax for the year</dt>
            <dd class="text-right text-base font-semibold text-slate-900"><x-money :amount="$c['tax']['total']" /></dd>
        </div>

        <div class="flex items-baseline justify-between gap-4 py-2">
            <dt class="text-slate-600">Already deducted</dt>
            <dd class="text-right font-medium text-slate-900"><x-money :amount="$c['already_deducted']" /></dd>
        </div>

        <div class="flex items-baseline justify-between gap-4 py-2">
            <dt class="text-slate-600">
                Left to deduct
                <span class="block text-xs text-slate-500">Spread over the {{ $c['months_remaining'] }} {{ Str::plural('month', $c['months_remaining']) }} of the year that remain.</span>
            </dt>
            <dd class="text-right font-medium text-slate-900"><x-money :amount="$c['remaining']" /></dd>
        </div>

        <div class="flex items-baseline justify-between gap-4 bg-brand-50/60 py-2.5">
            <dt class="font-semibold text-slate-900">This month</dt>
            <dd class="text-right text-base font-semibold text-brand-700"><x-money :amount="$c['monthly']" /></dd>
        </div>
    </dl>
</div>
