<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Tax statement {{ $year_label }} — {{ $employee->employee_code }}</title>
    <style>
        @page { margin: 26px 30px 66px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        @include('pdf.partials.pad-styles')

        .section-title {
            font-size: 9px; font-weight: bold; letter-spacing: 0.8px;
            text-transform: uppercase; color: #475569;
            background: #f1f5f9; padding: 5px 8px; border: 1px solid #e2e8f0;
        }
        .detail-table td { padding: 3px 8px; font-size: 9.5px; }
        .detail-table .label { color: #64748b; width: 22%; }
        .detail-table .value { font-weight: bold; }
        .box { border: 1px solid #e2e8f0; margin-bottom: 10px; }

        .lines th {
            font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.6px;
            color: #64748b; text-align: left; padding: 5px 8px;
            border-bottom: 1px solid #e2e8f0; font-weight: bold;
        }
        .lines td { padding: 4px 8px; border-bottom: 1px solid #f1f5f9; font-size: 9.5px; }
        .lines .amount { text-align: right; white-space: nowrap; }
        .lines .muted { color: #64748b; font-size: 8.5px; }
        .total-row td { font-weight: bold; border-top: 1px solid #cbd5e1; border-bottom: 0; padding-top: 6px; }

        .headline {
            border: 1px solid #cbd5e1; background: #f8fafc;
            padding: 8px 10px; margin-top: 10px;
        }
        .headline .caption { font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.6px; color: #64748b; }
        .headline .figure { font-size: 15px; font-weight: bold; }

        /* Said on the face of the document, not in a footnote: this is not the
           certificate anybody files a return with. */
        .disclaimer {
            border: 1px solid #cbd5e1; background: #fffbeb;
            padding: 7px 9px; margin-bottom: 10px;
            font-size: 8.5px; color: #475569; line-height: 1.5;
        }
        .disclaimer strong { color: #1e293b; }

        .note { font-size: 8.5px; color: #64748b; margin-top: 10px; line-height: 1.5; }
    </style>
</head>
<body>
@include('pdf.partials.watermark')
@include('pdf.partials.pad', [
    'title' => 'Statement of Salary and Tax Deducted',
    'meta' => [
        'Financial year' => $year_label,
        'Assessment year' => $assessment_year,
    ],
])

<div class="disclaimer">
    <strong>This is not a Form 16.</strong> Part A of a Form 16 carries the deductor’s TAN, the
    challan identification numbers and the quarterly figures the Income Tax Department itself holds;
    it is issued from the TRACES portal against the returns actually filed, and cannot be produced
    here. This statement sets out the same salary breakdown and tax computation that Part B carries,
    so that the figures can be checked — but the certificate to be relied on is the one your employer
    downloads from TRACES.
</div>

<div class="box">
    <div class="section-title">Employee</div>
    <table class="detail-table">
        <tr>
            <td class="label">Name</td><td class="value">{{ $employee->full_name }}</td>
            <td class="label">Employee code</td><td class="value">{{ $employee->employee_code }}</td>
        </tr>
        <tr>
            <td class="label">Designation</td><td class="value">{{ $employee->designation?->name ?? '—' }}</td>
            <td class="label">PAN</td><td class="value">{{ $employee->pan_number ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Paid by</td>
            <td class="value">{{ $companies->pluck('name')->join(', ') ?: '—' }}</td>
            <td class="label">Months paid</td><td class="value">{{ $months }}</td>
        </tr>
        <tr>
            <td class="label">Regime</td><td class="value">{{ $regime_label }}</td>
            <td class="label">Rates applied</td>
            <td class="value">
                {{ App\Support\FinancialYear::label($computation['rules_year']) }}
                @if ($computation['rules_assumed']) (assumed) @endif
            </td>
        </tr>
    </table>
</div>

@foreach ([['Earnings for the year', $earnings], ['Deductions from salary', $deductions]] as [$heading, $rows])
    @if (count($rows))
        <div class="box">
            <div class="section-title">{{ $heading }}</div>
            <table class="lines">
                <thead><tr><th>What</th><th class="amount">Amount</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td class="amount">{{ App\Support\Money::withSymbol($row['amount']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td>Total</td>
                        <td class="amount">
                            {{ App\Support\Money::withSymbol(collect($rows)->sum('amount')) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
@endforeach

<div class="box">
    <div class="section-title">How the tax was worked out</div>
    <table class="lines">
        <tbody>
            <tr>
                <td>Gross salary</td>
                <td class="amount">{{ App\Support\Money::withSymbol($computation['gross_total']) }}</td>
            </tr>
            <tr>
                <td>Less: standard deduction</td>
                <td class="amount">− {{ App\Support\Money::withSymbol($computation['standard_deduction']) }}</td>
            </tr>

            @foreach ($computation['exemptions']['lines'] as $line)
                <tr>
                    <td>
                        Less: {{ $line['label'] }}
                        @if ($line['reason'])
                            <div class="muted">{{ $line['reason'] }}</div>
                        @endif
                    </td>
                    <td class="amount">
                        @if ($line['allowed'] > 0) − @endif{{ App\Support\Money::withSymbol($line['allowed']) }}
                    </td>
                </tr>
            @endforeach

            <tr class="total-row">
                <td>Taxable income</td>
                <td class="amount">{{ App\Support\Money::withSymbol($computation['taxable_income']) }}</td>
            </tr>

            @foreach ($computation['tax']['bands'] as $band)
                @php
                    // Blade will not read an @endif that follows a word
                    // character, so the range is built here rather than inline.
                    $from = App\Support\Money::withSymbol($band['from'], null, 0);
                    $range = $band['to']
                        ? $from.' to '.App\Support\Money::withSymbol($band['to'], null, 0)
                        : $from.' and above';
                @endphp
                <tr>
                    <td>
                        {{ number_format($band['rate'] * 100, 0) }}% on
                        {{ App\Support\Money::withSymbol($band['income'], null, 0) }}
                        <div class="muted">{{ $range }}</div>
                    </td>
                    <td class="amount">{{ App\Support\Money::withSymbol($band['tax']) }}</td>
                </tr>
            @endforeach

            @if ($computation['tax']['rebate'] > 0)
                <tr>
                    <td>Less: rebate under section 87A</td>
                    <td class="amount">− {{ App\Support\Money::withSymbol($computation['tax']['rebate']) }}</td>
                </tr>
            @endif

            @if ($computation['tax']['surcharge'] > 0)
                <tr>
                    <td>
                        Surcharge at {{ number_format($computation['tax']['surcharge_rate'] * 100, 0) }}%
                        @if ($computation['tax']['surcharge_relief'] > 0)
                            <div class="muted">After marginal relief of {{ App\Support\Money::withSymbol($computation['tax']['surcharge_relief']) }}.</div>
                        @endif
                    </td>
                    <td class="amount">{{ App\Support\Money::withSymbol($computation['tax']['surcharge']) }}</td>
                </tr>
            @endif

            <tr>
                <td>Health and education cess at {{ number_format($computation['tax']['cess_rate'] * 100, 0) }}%</td>
                <td class="amount">{{ App\Support\Money::withSymbol($computation['tax']['cess']) }}</td>
            </tr>

            <tr class="total-row">
                <td>Tax payable for the year</td>
                <td class="amount">{{ App\Support\Money::withSymbol($computation['tax']['total']) }}</td>
            </tr>
            <tr>
                <td>Tax actually deducted from salary</td>
                <td class="amount">{{ App\Support\Money::withSymbol($tax_deducted) }}</td>
            </tr>
            <tr class="total-row">
                <td>
                    {{ $tax_deducted >= $computation['tax']['total'] ? 'Deducted in excess of the tax due' : 'Still payable' }}
                    <div class="muted">Anything left is settled through your own return.</div>
                </td>
                <td class="amount">
                    {{ App\Support\Money::withSymbol(abs(round($tax_deducted - $computation['tax']['total'], 2))) }}
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="headline">
    <div class="caption">Net salary paid during {{ $year_label }}</div>
    <div class="figure">{{ App\Support\Money::withSymbol($net_paid) }}</div>
</div>

<p class="note">
    Built from the {{ $months }} {{ Str::plural('salary slip', $months) }} issued during the year,
    not from today’s salary structure, so this statement reads the same in a year’s time as it does
    now. Deductions are those verified against proof where HR has checked them, and as declared
    where they have not.
</p>

@include('pdf.partials.foot', ['lines' => [
    $year_label.' &middot; assessment year '.$assessment_year.' &middot; '.e($regime_label),
    'Not a Form 16. The certificate to be relied on is issued from TRACES.',
]])
</body>
</html>
