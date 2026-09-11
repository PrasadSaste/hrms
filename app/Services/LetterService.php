<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\Payslip;
use App\Models\Setting;
use App\Models\Signatory;
use App\Models\User;
use App\Support\LetterTypes;
use App\Support\Money;
use App\Support\Placeholders;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writing the letters a company issues to its people.
 *
 * The wording comes from the catalogue, with an administrator's edits laid over
 * it. The values come from the employee's own record, the salary structure in
 * force and the company that employs them, so an increment letter works out its
 * own percentage and a salary certificate quotes a figure nobody had to retype.
 *
 * Once issued, the words are frozen onto the letter. Re-rendering an old one
 * from today's template would quietly rewrite what somebody is holding a signed
 * copy of.
 */
class LetterService
{
    public function __construct(protected PayrollService $payroll) {}

    /**
     * The letter as it would read, without issuing it.
     *
     * @return array{subject: string, body: string, data: array<string, string>}
     */
    public function preview(
        Employee $employee,
        string $type,
        array $fields = [],
        ?Signatory $signatory = null,
        array $overrides = [],
    ): array {
        $this->assertType($type);

        $template = $this->template($type, $overrides);
        $data = $this->dataFor($employee, $type, $fields, $signatory);

        return [
            'subject' => Placeholders::render($template['subject'], $data, fn () => ''),
            'body' => Placeholders::render($template['body'], $data, fn () => '—'),
            'data' => $data,
        ];
    }

    /**
     * Write the letter and give it a number.
     *
     * Who signs it is settled here and frozen onto the row: the name, the title
     * and the specimen signature as they stood the day it went out. Somebody
     * leaving the company later must not change a letter they already signed.
     */
    public function issue(
        Employee $employee,
        string $type,
        array $fields,
        ?User $issuer = null,
        ?Signatory $signatory = null,
    ): Letter {
        $this->assertType($type);
        $this->assertIssuable($employee, $type);
        $this->assertFields($type, $fields);

        $employee->loadMissing(['company', 'branch', 'department', 'designation', 'manager']);
        $signatory = $this->signatoryFor($employee, $signatory);

        return DB::transaction(function () use ($employee, $type, $fields, $issuer, $signatory) {
            $rendered = $this->preview($employee, $type, $fields, $signatory);
            $company = $employee->company;

            return Letter::create([
                'employee_id' => $employee->id,
                'company_id' => $company?->id,
                'signatory_id' => $signatory?->id,
                'type' => $type,
                'reference' => $this->nextReference($type, $company),
                'subject' => $rendered['subject'] ?: LetterTypes::label($type),
                'body' => $rendered['body'],
                'data' => $rendered['data'],
                'fields' => $fields,
                'signatory_name' => $signatory?->name ?: $company?->signatory_name,
                'signatory_designation' => $signatory?->designation ?: $company?->signatory_designation,
                'signature_path' => $signatory?->signature_path,
                'issued_on' => Carbon::today()->toDateString(),
                'effective_from' => $this->effectiveDate($fields),
                'issued_by' => $issuer?->id,
            ]);
        });
    }

    /**
     * Who signs, when the issuer did not say.
     *
     * A signatory from another company is refused rather than quietly ignored —
     * a letter signed by a director of a different legal entity is worse than
     * one that fails to issue.
     */
    public function signatoryFor(Employee $employee, ?Signatory $signatory = null): ?Signatory
    {
        if ($signatory && $employee->company_id && $signatory->company_id !== $employee->company_id) {
            throw ValidationException::withMessages([
                'signatory_id' => $signatory->name.' signs for another company, not '
                    .$employee->company?->displayName().'.',
            ]);
        }

        return $signatory ?: $employee->company?->defaultSignatory();
    }

    /**
     * Every value a letter of this type can use.
     *
     * @return array<string, string>
     */
    public function dataFor(
        Employee $employee,
        string $type,
        array $fields = [],
        ?Signatory $signatory = null,
    ): array {
        $employee->loadMissing(['company', 'branch', 'department', 'designation', 'manager']);
        $signatory ??= $employee->company?->defaultSignatory();

        $structure = $employee->salaryStructureOn();
        $currency = $structure?->currency ?: (string) Setting::get('currency', 'INR');

        $data = [
            'employee_name' => $employee->full_name,
            'first_name' => $employee->first_name,
            'employee_code' => $employee->employee_code,
            'designation' => $employee->designation?->name,
            'last_designation' => $employee->designation?->name,
            'department' => $employee->department?->name,
            'branch' => $employee->branch?->name,
            'company_name' => $employee->company?->displayName() ?? Setting::get('company_name'),
            'date_of_joining' => $this->date($employee->date_of_joining),
            'date_of_exit' => $this->date($employee->date_of_exit),
            'last_working_day' => $this->date($employee->date_of_exit),
            'today' => $this->date(Carbon::today()),
            'reporting_to' => $employee->manager?->full_name,
            'notice_period_days' => (string) $employee->notice_period_days,
            'employee_address' => $employee->fullAddress() ?: null,
            'working_days' => $this->workingWeek($employee),
            'working_hours' => $this->workingHours($employee),
            'period_of_service' => $this->periodOfService($employee),
            'tenure' => $this->tenure($employee),

            // Pay, from the structure in force today.
            'ctc_annual' => $this->money($structure?->ctc_annual, $currency),
            'monthly_gross' => $this->money($structure?->gross_monthly, $currency),
            'basic_salary' => $this->money($structure?->basic_salary, $currency),
            'monthly_net' => $this->money($this->latestNetPay($employee), $currency),

            'signatory_name' => $signatory?->name ?: $employee->company?->signatory_name,
            'signatory_designation' => $signatory?->designation ?: $employee->company?->signatory_designation,
        ];

        return array_merge($data, $this->fieldValues($employee, $type, $fields, $currency, $structure));
    }

    /**
     * The values that come from what the issuer typed, plus anything worked
     * out from it — an increment's percentage, a relieving letter's sentence
     * about dues.
     *
     * @return array<string, string|null>
     */
    protected function fieldValues(
        Employee $employee,
        string $type,
        array $fields,
        string $currency,
        $structure = null,
    ): array {
        $values = [];

        foreach (LetterTypes::fields($type) as $name => $definition) {
            $given = $fields[$name] ?? $definition['default'] ?? null;

            $values[$name] = match ($definition['type']) {
                'date' => $given ? $this->date(Carbon::parse($given)) : null,
                // Money carries its symbol; a plain number — a probation period
                // in months — must not suddenly read as rupees.
                'money' => $given !== null && $given !== '' ? $this->money((float) $given, $currency) : null,
                'number' => $given !== null && $given !== '' ? (string) (0 + $given) : null,
                'boolean' => null, // chooses wording rather than being printed
                default => $given !== null && $given !== '' ? $given : null,
            };
        }

        // An increment says what the raise was, so nobody has to work it out
        // twice and disagree with the letter.
        if ($type === LetterTypes::INCREMENT) {
            $previous = (float) ($structure?->ctc_annual ?? 0);
            $revised = (float) ($fields['revised_ctc'] ?? 0);

            $changed = $previous > 0 && abs($revised - $previous) > 0.005;

            $values['previous_ctc'] = $this->money($previous, $currency);
            // The sign is left to speak for itself: a revision downwards is
            // unusual but not impossible, and a letter that hides it is worse.
            $values['increase_amount'] = $changed ? $this->money($revised - $previous, $currency) : null;
            $values['increase_percentage'] = $changed
                ? number_format(($revised - $previous) / $previous * 100, 1).'%'
                : null;
            $values['revised_designation'] = $fields['revised_designation'] ?? $employee->designation?->name;
        }

        if ($type === LetterTypes::CONFIRMATION) {
            $values['effective_from'] ??= $this->date($employee->date_of_confirmation);
            $values['revised_ctc'] ??= $this->money($structure?->ctc_annual, $currency);
            $values['probation_months'] = (string) $this->probationMonths($employee);
        }

        if ($type === LetterTypes::APPOINTMENT && blank($fields['working_hours'] ?? null)) {
            // Left empty, the branch's own hours stand rather than a dash.
            unset($values['working_hours']);
        }

        if ($type === LetterTypes::RELIEVING) {
            $settled = (bool) ($fields['dues_settled'] ?? true);

            $values['settlement_sentence'] = $settled
                ? 'Your full and final settlement has been completed and all dues payable to you '
                    .'have been settled. We confirm that no dues are outstanding on either side.'
                : 'Your full and final settlement is in progress and will be completed in the '
                    .'ordinary course.';
        }

        // "To whomsoever it may concern" is the right default for the letters
        // that are addressed to nobody in particular.
        foreach ([LetterTypes::SALARY_CERTIFICATE, LetterTypes::NOC, LetterTypes::EMPLOYMENT_PROOF] as $open) {
            if ($type === $open) {
                $values['addressed_to'] = filled($fields['addressed_to'] ?? null)
                    ? 'To,'."\n\n".$fields['addressed_to']
                    : '**To whomsoever it may concern**';
            }
        }

        if ($type === LetterTypes::SALARY_CERTIFICATE) {
            $values['purpose'] = filled($fields['purpose'] ?? null)
                ? $fields['purpose']
                : 'their own records';
        }

        return $values;
    }

    /**
     * The next number for this type, e.g. BSPL/OFR/2026/0007.
     *
     * Scoped to the company and the calendar year, because that is how anybody
     * reading a reference off a printed letter expects to find it again.
     */
    public function nextReference(string $type, ?Company $company = null): string
    {
        $prefix = $company?->payslip_prefix ?: (string) Setting::get('employee_code_prefix', 'HR');
        $code = LetterTypes::code($type);
        $year = Carbon::today()->format('Y');
        $stem = sprintf('%s/%s/%s/', strtoupper($prefix), $code, $year);

        $last = Letter::where('reference', 'like', $stem.'%')
            ->orderByDesc('id')
            ->value('reference');

        $next = $last && preg_match('/(\d+)$/', $last, $matches) ? ((int) $matches[1]) + 1 : 1;

        do {
            $reference = $stem.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (Letter::where('reference', $reference)->exists());

        return $reference;
    }

    /** The wording in force, with anything unsaved from a form laid over it. */
    public function template(string $type, array $overrides = []): array
    {
        $template = LetterTemplate::resolve($type) ?? ['subject' => '', 'body' => ''];

        foreach (['subject', 'body'] as $field) {
            if (filled($overrides[$field] ?? null)) {
                $template[$field] = $overrides[$field];
            }
        }

        return $template;
    }

    /** Sample values, so the wording console can be previewed without an employee. */
    public function sampleData(string $type): array
    {
        $data = collect(LetterTypes::placeholders($type))
            ->mapWithKeys(fn (string $description, string $token) => [
                $token => ucfirst(str_replace('_', ' ', $token)),
            ])
            ->all();

        return array_merge($data, [
            'employee_name' => 'Priya Sharma',
            'first_name' => 'Priya',
            'employee_code' => 'EMP0042',
            'designation' => 'Senior Analyst',
            'last_designation' => 'Senior Analyst',
            'department' => 'Finance',
            'branch' => 'Bengaluru Head Office',
            'company_name' => Setting::get('company_name', config('app.name')),
            'date_of_joining' => '01 Apr 2023',
            'date_of_exit' => '31 Mar 2026',
            'last_working_day' => '31 Mar 2026',
            'today' => Carbon::today()->format('d M Y'),
            'reference' => 'BSPL/'.LetterTypes::code($type).'/'.date('Y').'/0001',
            'tenure' => '2 years and 11 months',
            'ctc_annual' => '₹9,90,000.00',
            'monthly_gross' => '₹79,200.00',
            'basic_salary' => '₹33,000.00',
            'monthly_net' => '₹73,150.00',
            'previous_ctc' => '₹9,00,000.00',
            'revised_ctc' => '₹9,90,000.00',
            'increase_amount' => '₹90,000.00',
            'increase_percentage' => '10.0%',
            'notice_period_days' => '30',
            'probation_months' => '6',
            'addressed_to' => '**To whomsoever it may concern**',
            'settlement_sentence' => 'Your full and final settlement has been completed.',
        ]);
    }

    // ------------------------------------------------------------- internals

    protected function assertType(string $type): void
    {
        if (! LetterTypes::exists($type)) {
            throw ValidationException::withMessages(['type' => 'There is no letter called "'.$type.'".']);
        }
    }

    /**
     * Whether this letter makes sense for this person at all.
     *
     * An experience letter for somebody still on the payroll is not a letter
     * anybody meant to send, and the record is where the dates come from.
     */
    public function assertIssuable(Employee $employee, string $type): void
    {
        $needsExit = in_array($type, [LetterTypes::EXPERIENCE, LetterTypes::RELIEVING], true);

        if ($needsExit && ! $employee->date_of_exit) {
            throw ValidationException::withMessages([
                'type' => LetterTypes::label($type).' needs a last working day. Record '
                    .$employee->first_name."'s exit on their profile first.",
            ]);
        }
    }

    /** The issuer has to have filled in what the type asks for. */
    protected function assertFields(string $type, array $fields): void
    {
        foreach (LetterTypes::fields($type) as $name => $definition) {
            $required = $definition['required'] ?? false;
            $given = $fields[$name] ?? $definition['default'] ?? null;

            if ($required && ($given === null || $given === '')) {
                throw ValidationException::withMessages([
                    'fields.'.$name => $definition['label'].' is needed for this letter.',
                ]);
            }
        }
    }

    protected function effectiveDate(array $fields): ?string
    {
        foreach (['effective_from', 'start_date'] as $key) {
            if (filled($fields[$key] ?? null)) {
                return Carbon::parse($fields[$key])->toDateString();
            }
        }

        return null;
    }

    protected function date($date): ?string
    {
        return $date ? Carbon::parse($date)->format('d M Y') : null;
    }

    protected function money(?float $amount, string $currency = 'INR'): ?string
    {
        if ($amount === null) {
            return null;
        }

        return Money::withSymbol($amount, $currency);
    }

    /** What they actually took home last, for a salary certificate. */
    protected function latestNetPay(Employee $employee): ?float
    {
        return Payslip::where('employee_id', $employee->id)
            ->orderByDesc('period_start')
            ->value('net_pay');
    }

    protected function periodOfService(Employee $employee): string
    {
        $from = $this->date($employee->date_of_joining);
        $to = $this->date($employee->date_of_exit);

        return $to ? $from.' to '.$to : $from.' to date';
    }

    protected function tenure(Employee $employee): string
    {
        $months = $employee->tenureInMonths($employee->date_of_exit);
        $years = intdiv($months, 12);
        $rest = $months % 12;

        $parts = [];

        if ($years > 0) {
            $parts[] = $years.' year'.($years === 1 ? '' : 's');
        }

        if ($rest > 0 || $parts === []) {
            $parts[] = $rest.' month'.($rest === 1 ? '' : 's');
        }

        return implode(' and ', $parts);
    }

    protected function probationMonths(Employee $employee): int
    {
        if (! $employee->date_of_confirmation) {
            return 6;
        }

        return max(1, (int) $employee->date_of_joining->diffInMonths($employee->date_of_confirmation));
    }

    protected function workingWeek(Employee $employee): string
    {
        $names = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
        $days = app(WorkCalendar::class)->workingDaysFor($employee);

        $week = collect($days)->map(fn (int $day) => $names[$day] ?? $day);
        $label = $week->count() > 2 && $days === range(min($days), max($days))
            ? $week->first().' to '.$week->last()
            : $week->join(', ', ' and ');

        $saturdays = $employee->branch?->saturdayLabel();

        return $saturdays && str_contains((string) $saturdays, 'Saturday off')
            ? $label.' ('.lcfirst($saturdays).')'
            : $label;
    }

    protected function workingHours(Employee $employee): ?string
    {
        $branch = $employee->branch;

        if (! $branch?->work_start_time || ! $branch?->work_end_time) {
            return null;
        }

        return Carbon::parse($branch->work_start_time)->format('g:i A')
            .' to '.Carbon::parse($branch->work_end_time)->format('g:i A');
    }
}
