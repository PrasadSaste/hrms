<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Salary slip {{ $payslip->slip_number }}</title>
    <style>
        @page { margin: 26px 30px 66px; }

        * { box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
        }

        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        @include('pdf.partials.pad-styles')

        .section-title {
            font-size: 9px; font-weight: bold; letter-spacing: 0.8px;
            text-transform: uppercase; color: #475569;
            background: #f1f5f9; padding: 5px 8px;
            border: 1px solid #e2e8f0;
        }

        .detail-table td { padding: 3px 8px; font-size: 9.5px; }
        .detail-label { color: #64748b; width: 88px; }
        .detail-value { color: #0f172a; font-weight: bold; }

        .attendance { margin-top: 12px; border: 1px solid #e2e8f0; background: #f8fafc; }
        .attendance td { padding: 7px 6px; text-align: center; font-size: 9px; }
        .attendance .label { color: #64748b; text-transform: uppercase; letter-spacing: 0.4px; font-size: 8px; }
        .attendance .value { font-size: 12px; font-weight: bold; color: #0f172a; padding-top: 2px; }

        .lines { margin-top: 12px; border: 1px solid #e2e8f0; }
        .lines th {
            font-size: 9px; text-transform: uppercase; letter-spacing: 0.6px;
            padding: 6px 8px; text-align: left; border-bottom: 1px solid #e2e8f0;
        }
        .lines th.earn { background: #ecfdf5; color: #065f46; }
        .lines th.deduct { background: #fef2f2; color: #991b1b; }
        .lines td { padding: 4.5px 8px; font-size: 9.5px; border-bottom: 1px solid #f1f5f9; }
        .amount { text-align: right; font-weight: bold; }
        .subtotal td {
            background: #f8fafc; font-weight: bold; font-size: 10px;
            border-top: 1px solid #cbd5e1; border-bottom: none;
        }
        .col-split { width: 50%; vertical-align: top; }

        .net {
            margin-top: 12px; border: 2px solid {{ $company['brand_color'] ?? '#1e293b' }};
            background: #f8fafc; padding: 10px 12px;
        }
        .net-label {
            font-size: 10px; font-weight: bold; text-transform: uppercase;
            letter-spacing: 1px; color: {{ $company['brand_color'] ?? '#1e3a8a' }};
        }
        .net-value {
            font-size: 20px; font-weight: bold; text-align: right;
            color: {{ $company['brand_color'] ?? '#1e3a8a' }};
        }
        .net-words { font-size: 9px; color: #475569; padding-top: 5px; }

        /* Sits in the page's bottom margin, which this document makes room for. */
        .doc-foot { bottom: -60px; }
        .doc-foot .signature-image { max-height: 34px; max-width: 160px; margin-top: 3px; }
    </style>
</head>
<body>

@include('pdf.partials.watermark')

@include('pdf.partials.pad', [
    'title' => 'Salary Slip',
    'meta' => [
        'Period' => $payslip->periodLabel(),
        'Slip no.' => $payslip->slip_number,
    ],
    'stamp' => $payslip->payment_status === 'paid' ? 'Paid' : null,
])

<div class="section-title" style="margin-top: 12px;">Employee Details</div>
<table style="border: 1px solid #e2e8f0; border-top: none;">
    <tr>
        <td class="col-split">
            <table class="detail-table">
                <tr><td class="detail-label">Name</td><td class="detail-value">{{ $employee->full_name }}</td></tr>
                <tr><td class="detail-label">Employee code</td><td class="detail-value">{{ $employee->employee_code }}</td></tr>
                <tr><td class="detail-label">Designation</td><td class="detail-value">{{ $employee->designation?->name ?? '-' }}</td></tr>
                <tr><td class="detail-label">Department</td><td class="detail-value">{{ $employee->department?->name ?? '-' }}</td></tr>
                <tr><td class="detail-label">Branch</td><td class="detail-value">{{ $employee->branch?->name ?? '-' }}</td></tr>
            </table>
        </td>
        <td class="col-split">
            <table class="detail-table">
                <tr><td class="detail-label">Date of joining</td><td class="detail-value">{{ $employee->date_of_joining->format('d M Y') }}</td></tr>
                <tr><td class="detail-label">Bank account</td><td class="detail-value">{{ $employee->bank_account_number ?: '-' }}</td></tr>
                <tr><td class="detail-label">IFSC</td><td class="detail-value">{{ $employee->bank_ifsc ?: '-' }}</td></tr>
                <tr><td class="detail-label">PAN</td><td class="detail-value">{{ $employee->pan_number ?: '-' }}</td></tr>
                <tr><td class="detail-label">UAN</td><td class="detail-value">{{ $employee->uan_number ?: '-' }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="attendance">
    <tr>
        <td>
            <div class="label">Working Days</div>
            <div class="value">{{ rtrim(rtrim(number_format($payslip->working_days, 1), '0'), '.') }}</div>
        </td>
        <td>
            <div class="label">Paid Days</div>
            <div class="value">{{ rtrim(rtrim(number_format($payslip->paid_days, 1), '0'), '.') }}</div>
        </td>
        <td>
            <div class="label">Paid Leave</div>
            <div class="value">{{ rtrim(rtrim(number_format($payslip->paid_leave_days, 1), '0'), '.') }}</div>
        </td>
        <td>
            <div class="label">Loss of Pay</div>
            <div class="value">{{ rtrim(rtrim(number_format($payslip->lop_days, 1), '0'), '.') }}</div>
        </td>
        <td>
            <div class="label">Overtime</div>
            <div class="value">
                {{ $payslip->overtime_hours }}h
                @if ($payslip->overtime_rate > 0)
                    at {{ App\Support\Money::withSymbol($payslip->overtime_rate, $payslip->currency) }}
                @endif
            </div>
        </td>
    </tr>
</table>

<table class="lines">
    <tr>
        <td class="col-split" style="border-right: 1px solid #e2e8f0;">
            <table>
                <tr>
                    <th class="earn">Earnings</th>
                    <th class="earn" style="text-align: right;">Amount</th>
                </tr>
                @foreach ($payslip->earnings as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td class="amount">{{ \App\Support\Money::withSymbol($item->amount, $payslip->currency) }}</td>
                    </tr>
                @endforeach
                @for ($i = $payslip->earnings->count(); $i < max($payslip->deductions->count(), 1); $i++)
                    <tr><td>&nbsp;</td><td></td></tr>
                @endfor
                <tr class="subtotal">
                    <td>Gross Earnings</td>
                    <td class="amount">{{ \App\Support\Money::withSymbol($payslip->gross_earnings, $payslip->currency) }}</td>
                </tr>
            </table>
        </td>
        <td class="col-split">
            <table>
                <tr>
                    <th class="deduct">Deductions</th>
                    <th class="deduct" style="text-align: right;">Amount</th>
                </tr>
                @forelse ($payslip->deductions as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td class="amount">{{ \App\Support\Money::withSymbol($item->amount, $payslip->currency) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" style="color: #94a3b8;">No deductions</td></tr>
                @endforelse
                @for ($i = max($payslip->deductions->count(), 1); $i < $payslip->earnings->count(); $i++)
                    <tr><td>&nbsp;</td><td></td></tr>
                @endfor
                <tr class="subtotal">
                    <td>Total Deductions</td>
                    <td class="amount">{{ \App\Support\Money::withSymbol($payslip->total_deductions, $payslip->currency) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="net">
    <tr>
        <td style="vertical-align: middle;"><span class="net-label">Net Pay</span></td>
        <td class="net-value">{{ \App\Support\Money::withSymbol($payslip->net_pay, $payslip->currency) }}</td>
    </tr>
    @if ($payslip->net_pay_words)
        <tr>
            <td colspan="2" class="net-words">Amount in words: {{ $payslip->net_pay_words }}</td>
        </tr>
    @endif
</table>

@if ($payslip->remarks)
    <p style="margin-top: 10px; font-size: 9px; color: #475569;">
        <strong>Remarks:</strong> {{ $payslip->remarks }}
    </p>
@endif

@include('pdf.partials.foot', ['lines' => [
    'Pay period '.$payslip->period_start->format('d M Y').' to '.$payslip->period_end->format('d M Y')
        .($payslip->payment_date ? ' &middot; paid on '.$payslip->payment_date->format('d M Y') : '')
        .($payslip->payment_reference ? ' &middot; reference '.e($payslip->payment_reference) : ''),

    ! empty($company['signature_src'])
        ? '<img src="'.$company['signature_src'].'" alt="" class="signature-image">'
        : null,

    ! empty($company['signatory_name'])
        ? 'For '.e($company['name']).' &middot; '.e($company['signatory_name'])
            .(! empty($company['signatory_designation']) ? ', '.e($company['signatory_designation']) : '').'.'
        : null,

    empty($company['signature_src'])
        ? 'This is a computer-generated salary slip and does not require a signature.'
        : null,

    'Generated on '.now()->format('d M Y').'.',
]])

</body>
</html>
