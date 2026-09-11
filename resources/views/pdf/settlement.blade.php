<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Settlement {{ $settlement->reference }}</title>
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
        .detail-table .label { color: #64748b; width: 38%; }
        .detail-table .value { font-weight: bold; }
        .box { border: 1px solid #e2e8f0; margin-bottom: 10px; }

        .lines th {
            font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.6px;
            color: #64748b; text-align: left; padding: 5px 8px;
            border-bottom: 1px solid #e2e8f0; font-weight: bold;
        }
        .lines td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; font-size: 9.5px; }
        .lines .amount { text-align: right; white-space: nowrap; }
        /* The reasoning, printed under the line it explains. */
        .basis { color: #64748b; font-size: 8.5px; padding-top: 1px; }
        .total-row td { font-weight: bold; border-top: 1px solid #cbd5e1; border-bottom: 0; padding-top: 6px; }

        .net {
            border: 1px solid #cbd5e1; background: #f8fafc;
            padding: 8px 10px; margin-top: 10px;
        }
        .net .caption { font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.6px; color: #64748b; }
        .net .figure { font-size: 15px; font-weight: bold; }
        .net .words { font-size: 8.5px; color: #475569; font-style: italic; }

        .note { font-size: 8.5px; color: #64748b; margin-top: 10px; line-height: 1.5; }
        .sign { margin-top: 26px; font-size: 9px; }
    </style>
</head>
<body>
@include('pdf.partials.watermark')
@include('pdf.partials.pad', [
    'title' => 'Full and Final Settlement',
    'meta' => [
        'Reference' => $settlement->reference,
        'Last working day' => $settlement->last_working_day?->format('d M Y'),
    ],
])

<div class="box">
    <div class="section-title">Employee</div>
    <table class="detail-table">
        <tr>
            <td class="label">Name</td><td class="value">{{ $employee?->full_name }}</td>
            <td class="label">Employee code</td><td class="value">{{ $employee?->employee_code }}</td>
        </tr>
        <tr>
            <td class="label">Designation</td><td class="value">{{ $employee?->designation?->name ?? '—' }}</td>
            <td class="label">Branch</td><td class="value">{{ $employee?->branch?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Date of joining</td><td class="value">{{ $settlement->date_of_joining?->format('d M Y') }}</td>
            <td class="label">Last working day</td><td class="value">{{ $settlement->last_working_day?->format('d M Y') }}</td>
        </tr>
        <tr>
            <td class="label">Length of service</td>
            <td class="value">{{ number_format($settlement->service_years, 2) }} years</td>
            <td class="label">Last drawn basic</td>
            <td class="value">{{ App\Support\Money::withSymbol($settlement->last_drawn_basic, $settlement->currency) }}</td>
        </tr>
    </table>
</div>

@foreach ([['Earnings', $settlement->earnings], ['Deductions', $settlement->deductions]] as [$heading, $lines])
    @if ($lines->isNotEmpty())
        <div class="box">
            <div class="section-title">{{ $heading }}</div>
            <table class="lines">
                <thead><tr><th>What</th><th class="amount">Amount</th></tr></thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr>
                            <td>
                                {{ $line->label }}
                                @if ($line->basis)
                                    <div class="basis">{{ $line->basis }}</div>
                                @endif
                            </td>
                            <td class="amount">{{ App\Support\Money::withSymbol($line->amount, $settlement->currency) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td>Total {{ strtolower($heading) }}</td>
                        <td class="amount">
                            {{ App\Support\Money::withSymbol(
                                $heading === 'Earnings' ? $settlement->total_earnings : $settlement->total_deductions,
                                $settlement->currency,
                            ) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
@endforeach

<div class="net">
    <table>
        <tr>
            <td>
                <div class="caption">{{ $settlement->netLabel() }}</div>
                <div class="figure">{{ App\Support\Money::withSymbol(abs($settlement->net_payable), $settlement->currency) }}</div>
                <div class="words">{{ $settlement->netInWords() }}</div>
            </td>
            <td style="text-align: right; width: 40%;">
                <div class="caption">Status</div>
                <div style="font-size: 10px; font-weight: bold;">{{ $settlement->statusLabel() }}</div>
                @if ($settlement->settled_on)
                    <div style="font-size: 8.5px; color: #64748b;">Paid {{ $settlement->settled_on->format('d M Y') }}</div>
                @endif
            </td>
        </tr>
    </table>
</div>

@if ($settlement->notes)
    <p class="note"><strong>Notes:</strong> {{ $settlement->notes }}</p>
@endif

<p class="note">
    Every figure above was fixed when this settlement was approved and is not recalculated afterwards.
    Each line states how it was arrived at. If anything looks wrong, raise it with Human Resources
    quoting {{ $settlement->reference }}.
</p>

<div class="sign">
    <table>
        <tr>
            <td>
                @if ($settlement->approver)
                    Approved by {{ $settlement->approver->name }}
                    @if ($settlement->approved_at)
                        on {{ $settlement->approved_at->format('d M Y') }}
                    @endif
                @endif
            </td>
            <td style="text-align: right;">For {{ $company['name'] }}</td>
        </tr>
    </table>
</div>

@include('pdf.partials.foot', ['lines' => [
    'Settlement '.e($settlement->reference).' &middot; '.e($settlement->statusLabel())
        .($settlement->settled_on ? ' &middot; paid on '.$settlement->settled_on->format('d M Y') : ''),
]])
</body>
</html>
