<?php

namespace App\Services\Bank;

use App\Enums\PayrollStatus;
use App\Models\Company;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Support\BankFormats;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns an approved payroll run into a file a bank will accept.
 *
 * The register export beside it is a report: somebody reads it, and a column
 * out of place is a nuisance. This is neither read nor forgiving — it is
 * parsed by a bank, and the failure it protects against is money reaching the
 * wrong account, so the file is checked whole before a byte of it is written,
 * exactly as `DataImportService` checks a spreadsheet before writing a row.
 *
 * Nothing here decides *whether* to pay. A run that has not been approved has
 * no file, and producing one does not mark the run paid: the money leaves at
 * the bank, and somebody says so afterwards.
 */
class BankFileService
{
    /** An IFSC is four letters, a zero, then six more characters. */
    public const IFSC_PATTERN = '/^[A-Z]{4}0[A-Z0-9]{6}$/';

    /** Most banks reject a narration longer than this, or silently truncate it. */
    public const NARRATION_LIMIT = 30;

    /**
     * Every payslip in the run that is meant to be paid into a bank account.
     *
     * Somebody paid in cash or by cheque is not a problem to be fixed — they
     * are simply not part of a bank file, and are counted separately so the
     * totals on the screen and the totals in the file can be reconciled.
     */
    public function payable(Payroll $payroll): Collection
    {
        return $payroll->payslips()
            ->with(['employee'])
            ->join('employees', 'employees.id', '=', 'payslips.employee_id')
            ->orderBy('employees.employee_code')
            ->select('payslips.*')
            ->get()
            ->filter(fn (Payslip $slip) => $slip->payment_mode === 'bank_transfer')
            ->values();
    }

    /** Payslips left out of the file because they are not paid by transfer. */
    public function excluded(Payroll $payroll): Collection
    {
        return $payroll->payslips()
            ->with(['employee'])
            ->get()
            ->filter(fn (Payslip $slip) => $slip->payment_mode !== 'bank_transfer')
            ->values();
    }

    /**
     * Everything wrong with the run, before anything is written.
     *
     * Returned as one list of sentences naming the employee, because the
     * person fixing them is going to open those records one after another.
     *
     * @return array<int, string>
     */
    public function problems(Payroll $payroll): array
    {
        $problems = [];

        if (! in_array($payroll->status, [PayrollStatus::Approved, PayrollStatus::Paid], true)) {
            $problems[] = 'The run has not been approved yet. A bank file is only produced for an approved run.';
        }

        $company = $payroll->company;

        if (! $company?->bank_account_number) {
            $problems[] = 'The company has no bank account on record, so there is nothing for the money to leave from. Set it under Payroll → Companies.';
        }

        if ($company?->bank_ifsc && ! $this->looksLikeIfsc($company->bank_ifsc)) {
            $problems[] = 'The company’s own IFSC does not look like an IFSC: '.$company->bank_ifsc.'.';
        }

        $payable = $this->payable($payroll);

        if ($payable->isEmpty()) {
            $problems[] = 'Nobody in this run is paid by bank transfer.';
        }

        $seen = [];

        foreach ($payable as $slip) {
            $who = $slip->employee?->employee_code.' '.$slip->employee?->full_name;

            foreach ($this->problemsWith($slip) as $problem) {
                $problems[] = trim($who).': '.$problem;
            }

            $account = (string) $slip->employee?->bank_account_number;

            if ($account !== '') {
                // Two people on one account is legitimate now and then, and is
                // also exactly what a typed-in digit looks like when it is
                // wrong. Say so and let a person decide.
                if (isset($seen[$account])) {
                    $problems[] = trim($who).' is paid into the same account as '.$seen[$account].'.';
                }

                $seen[$account] ??= trim($who);
            }
        }

        return $problems;
    }

    /**
     * What is wrong with one payslip.
     *
     * @return array<int, string>
     */
    public function problemsWith(Payslip $slip): array
    {
        $problems = [];
        $employee = $slip->employee;

        if (! $employee) {
            return ['the employee record is missing.'];
        }

        if (blank($employee->bank_account_number)) {
            $problems[] = 'no bank account number.';
        }

        if (blank($employee->bank_ifsc)) {
            $problems[] = 'no IFSC.';
        } elseif (! $this->looksLikeIfsc($employee->bank_ifsc)) {
            $problems[] = '“'.$employee->bank_ifsc.'” does not look like an IFSC.';
        }

        if (blank($employee->bank_account_name) && blank($employee->full_name)) {
            $problems[] = 'no name for the bank to match against the account.';
        }

        if ($slip->net_pay <= 0) {
            $problems[] = 'net pay is '.number_format($slip->net_pay, 2).', which cannot be transferred.';
        }

        return $problems;
    }

    public function looksLikeIfsc(?string $ifsc): bool
    {
        return (bool) preg_match(self::IFSC_PATTERN, strtoupper(trim((string) $ifsc)));
    }

    /**
     * How this payment should travel.
     *
     * Same bank as the company's own account and it never leaves the bank, so
     * it settles at once; two lakh or more has to go by RTGS; everything else
     * is what a salary file usually is, NEFT.
     */
    public function transactionType(Payslip $slip, ?Company $company): string
    {
        $theirs = strtoupper(trim((string) $slip->employee?->bank_ifsc));
        $ours = strtoupper(trim((string) $company?->bank_ifsc));

        if ($ours !== '' && $theirs !== '' && substr($theirs, 0, 4) === substr($ours, 0, 4)) {
            return 'IFT';
        }

        return $slip->net_pay >= BankFormats::RTGS_THRESHOLD ? 'RTGS' : 'NEFT';
    }

    /**
     * What the employee sees on their statement.
     *
     * Kept to letters, digits and spaces: a narration carrying a comma or an
     * accent is the usual reason a file is rejected, and nobody wants to learn
     * that from the bank.
     */
    public function narration(Payroll $payroll): string
    {
        $text = 'Salary '.$payroll->period_start->format('M Y');

        return Str::limit(
            trim(preg_replace('/[^A-Za-z0-9 ]+/', ' ', $text) ?? ''),
            self::NARRATION_LIMIT,
            '',
        );
    }

    /**
     * The rows of the file, before they are joined into one.
     *
     * @return array<int, array<int, string>>
     */
    public function rows(Payroll $payroll, string $formatKey, ?Carbon $valueDate = null): array
    {
        $format = BankFormats::get($formatKey) ?? BankFormats::get(BankFormats::default());
        $company = $payroll->company;
        $valueDate ??= $payroll->payment_date ? $payroll->payment_date->copy() : Carbon::today();
        $narration = $this->narration($payroll);

        $rows = [];
        $serial = 0;

        foreach ($this->payable($payroll) as $slip) {
            $serial++;
            $row = [];

            foreach ($format['columns'] as $column) {
                $row[] = $this->value($column['field'], [
                    'serial' => $serial,
                    'slip' => $slip,
                    'payroll' => $payroll,
                    'company' => $company,
                    'value_date' => $valueDate,
                    'narration' => $narration,
                    'date_format' => $format['date_format'],
                ]);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /** One field of one row. */
    public function value(string $field, array $context): string
    {
        /** @var Payslip $slip */
        $slip = $context['slip'];
        $employee = $slip->employee;
        $company = $context['company'];

        return match ($field) {
            'serial' => (string) $context['serial'],
            'transaction_type' => $this->transactionType($slip, $company),
            'debit_account' => (string) ($company?->bank_account_number ?? ''),
            // The name the bank holds, where the employee record has one:
            // an account is matched on it, and it is not always the name the
            // employee is known by here.
            'beneficiary_name' => (string) ($employee?->bank_account_name ?: $employee?->full_name ?: ''),
            'beneficiary_account' => (string) ($employee?->bank_account_number ?? ''),
            'ifsc' => strtoupper(trim((string) ($employee?->bank_ifsc ?? ''))),
            'bank_name' => (string) ($employee?->bank_name ?? ''),
            // Unformatted and unrounded further: a bank reads 1234.50, never
            // ₹1,234.50, which is the one place Money deliberately stays out of.
            'amount' => number_format($slip->net_pay, 2, '.', ''),
            'value_date' => $context['value_date']->format($context['date_format']),
            'narration' => $context['narration'],
            'reference' => (string) $context['payroll']->reference,
            'employee_code' => (string) ($employee?->employee_code ?? ''),
            'email' => (string) ($employee?->email ?? ''),
            'mobile' => (string) ($employee?->phone ?? ''),
            default => '',
        };
    }

    /** The whole file as a string. */
    public function contents(Payroll $payroll, string $formatKey, ?Carbon $valueDate = null): string
    {
        $format = BankFormats::get($formatKey) ?? BankFormats::get(BankFormats::default());

        $handle = fopen('php://temp', 'r+');

        if ($format['header']) {
            fputcsv(
                $handle,
                array_map(fn ($column) => $column['heading'] ?? $column['field'], $format['columns']),
                $format['delimiter'],
                escape: '',
            );
        }

        foreach ($this->rows($payroll, $formatKey, $valueDate) as $row) {
            fputcsv($handle, $row, $format['delimiter'], escape: '');
        }

        rewind($handle);
        $contents = (string) stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }

    public function filename(Payroll $payroll, string $formatKey): string
    {
        $format = BankFormats::get($formatKey) ?? BankFormats::get(BankFormats::default());

        return implode('-', [
            'salary',
            $formatKey,
            $payroll->year,
            str_pad((string) $payroll->month, 2, '0', STR_PAD_LEFT),
        ]).'.'.$format['extension'];
    }

    /** The total the bank will be asked to move, for checking against the file. */
    public function total(Payroll $payroll): float
    {
        return round($this->payable($payroll)->sum('net_pay'), 2);
    }

    /** Which layout this run's company uses, falling back to the generic one. */
    public function formatFor(Payroll $payroll): string
    {
        $stored = (string) ($payroll->company?->bank_file_format ?? '');

        return BankFormats::has($stored) ? $stored : BankFormats::default();
    }
}
