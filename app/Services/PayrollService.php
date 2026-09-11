<?php

namespace App\Services;

use App\Enums\ComponentType;
use App\Enums\PayrollStatus;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\PayslipItem;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\Setting;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function __construct(
        protected AttendanceService $attendance,
        protected WorkCalendar $calendar,
        protected OvertimeService $overtime,
        protected TaxWithholdingService $tax,
    ) {}

    /**
     * Create a payroll run for one company, month and year, optionally narrowed
     * to a single branch.
     *
     * Each legal entity is paid separately, so two companies can both run March
     * without colliding, and a run never mixes people from different employers.
     */
    public function createRun(array $data, ?User $user = null): Payroll
    {
        $month = (int) $data['month'];
        $year = (int) $data['year'];
        $branchId = $data['branch_id'] ?? null;
        $companyId = $data['company_id'] ?? Company::default()?->id;

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => 'Set up a payroll company before running payroll.',
            ]);
        }

        $exists = Payroll::where('month', $month)
            ->where('year', $year)
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'month' => 'A payroll run already exists for this company, branch and period.',
            ]);
        }

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        return Payroll::create([
            'reference' => $this->nextRunReference($year, $month),
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'title' => $data['title'] ?? ('Payroll '.$start->format('F Y')),
            'month' => $month,
            'year' => $year,
            'period_start' => $start,
            'period_end' => $end,
            'payment_date' => $data['payment_date'] ?? $end->copy()->toDateString(),
            'status' => PayrollStatus::Draft,
            'notes' => $data['notes'] ?? null,
            'generated_by' => $user?->id,
        ]);
    }

    /**
     * Generate payslips for every eligible employee in the run. Existing draft
     * payslips are replaced so the run can be regenerated safely.
     *
     * @return array{generated: int, skipped: array<int, string>}
     */
    public function generate(Payroll $payroll, ?array $employeeIds = null): array
    {
        if (! $payroll->isEditable()) {
            throw ValidationException::withMessages([
                'status' => 'Only a draft payroll run can be generated.',
            ]);
        }

        $employees = $this->eligibleEmployees($payroll, $employeeIds);
        $skipped = [];
        $generated = 0;

        DB::transaction(function () use ($payroll, $employees, &$skipped, &$generated) {
            foreach ($employees as $employee) {
                $structure = $employee->salaryStructureOn($payroll->period_end);

                if (! $structure) {
                    $skipped[] = $employee->full_name.' ('.$employee->employee_code.') has no active salary structure.';

                    continue;
                }

                $this->buildPayslip($payroll, $employee, $structure);
                $generated++;
            }

            $payroll->recalculateTotals();
        });

        return ['generated' => $generated, 'skipped' => $skipped];
    }

    /** Employees that should be paid in this run. */
    public function eligibleEmployees(Payroll $payroll, ?array $employeeIds = null): Collection
    {
        return Employee::with(['branch', 'department', 'designation', 'shift'])
            ->active()
            ->onRoll()
            // The run's own company, plus anyone who somehow has none: leaving
            // an employee out of payroll in silence is the worst thing this
            // method could do.
            ->when($payroll->company_id, fn ($q) => $q->where(
                fn ($inner) => $inner->where('company_id', $payroll->company_id)
                    ->when(
                        $payroll->company_id === Company::default()?->id,
                        fn ($orphans) => $orphans->orWhereNull('company_id'),
                    ),
            ))
            ->forBranch($payroll->branch_id)
            ->when($employeeIds, fn ($q) => $q->whereIn('id', $employeeIds))
            ->whereDate('date_of_joining', '<=', $payroll->period_end)
            ->where(function ($q) use ($payroll) {
                $q->whereNull('date_of_exit')
                    ->orWhereDate('date_of_exit', '>=', $payroll->period_start);
            })
            ->orderBy('employee_code')
            ->get();
    }

    /**
     * Build (or rebuild) a single payslip: attendance-driven paid days, prorated
     * earnings, deductions, and the resulting net pay.
     */
    public function buildPayslip(Payroll $payroll, Employee $employee, SalaryStructure $structure): Payslip
    {
        $periodStart = $payroll->period_start->copy();
        $periodEnd = $payroll->period_end->copy();

        // A joiner or leaver is only paid for the part of the month they worked.
        if ($employee->date_of_joining->gt($periodStart)) {
            $periodStart = $employee->date_of_joining->copy();
        }

        if ($employee->date_of_exit && $employee->date_of_exit->lt($periodEnd)) {
            $periodEnd = $employee->date_of_exit->copy();
        }

        $summary = $this->attendance->summaryForPeriod($employee, $periodStart, $periodEnd);
        $totals = $summary['totals'];

        // Working days the employee was actually on the roll for.
        $employedWorkingDays = (float) $totals['working_days'];

        // Working days the whole month contained. Salary is always divided by
        // this, so a mid-month joiner or leaver is paid for their share of the
        // month rather than a full month's salary over a shorter window.
        $monthWorkingDays = (float) $this->calendar->workingDayCount(
            $employee,
            $payroll->period_start->copy(),
            $payroll->period_end->copy(),
        );

        // Days nobody recorded are only in this bucket when the organisation
        // has said an unmarked day is not an absence, and in that case they are
        // paid: a forgotten punch should not quietly cut somebody's salary.
        $paidDays = round(
            $totals['present_days'] + $totals['paid_leave_days'] + ($totals['unmarked_days'] ?? 0),
            2,
        );

        // With no attendance recorded at all, treat the employee as present
        // rather than withholding their salary on missing data.
        if ($employedWorkingDays > 0 && $paidDays <= 0 && $totals['absent_days'] <= 0) {
            $paidDays = $employedWorkingDays;
        }

        // Loss of pay only counts days they were employed but unpaid; days
        // before joining or after leaving are simply not payable.
        $lopDays = round(max(0, $employedWorkingDays - $paidDays), 2);

        $workingDays = $monthWorkingDays > 0 ? $monthWorkingDays : $employedWorkingDays;
        $payFactor = $workingDays > 0 ? round($paidDays / $workingDays, 6) : 1.0;

        $payslip = Payslip::updateOrCreate(
            ['payroll_id' => $payroll->id, 'employee_id' => $employee->id],
            [
                'slip_number' => $this->slipNumberFor($payroll, $employee),
                // Stamped on the slip: moving somebody to another entity later
                // must not rewrite who paid them last March.
                'company_id' => $employee->company_id ?? $payroll->company_id,
                'period_start' => $payroll->period_start,
                'period_end' => $payroll->period_end,
                'working_days' => $workingDays,
                'present_days' => $totals['present_days'],
                'paid_leave_days' => $totals['paid_leave_days'],
                'unpaid_leave_days' => $totals['unpaid_leave_days'],
                'holiday_days' => $totals['holiday_days'],
                'weekend_days' => $totals['weekend_days'],
                'absent_days' => $totals['absent_days'],
                'lop_days' => $lopDays,
                'paid_days' => $paidDays,
                'overtime_hours' => $totals['overtime_hours'],
                'currency' => $structure->currency,
                'payment_mode' => $structure->payment_mode,
                'payment_status' => 'unpaid',
                'status' => 'draft',
            ],
        );

        $payslip->items()->delete();

        // Overtime is paid alongside the structure rather than out of it: it
        // is not a component somebody is entitled to every month, it is what
        // this month's attendance happened to record. It is handed to the
        // resolver as an extra earning rather than appended afterwards, so
        // that a deduction expressed as a percentage of gross — state
        // insurance is, and is due on overtime — sees it.
        //
        // The rate is worked out on the ordinary monthly wage, before any loss
        // of pay: an hour worked is worth the same whether or not the employee
        // was absent on some other day. Nobody is paid overtime on overtime,
        // so the gross basis is the structure's own gross.
        $overtime = $this->overtime->payFor(
            $employee,
            (float) $totals['overtime_hours'],
            $this->overtime->basis() === 'gross'
                ? $this->monthlyGross($structure)
                : (float) $structure->basic_salary,
            $workingDays,
        );

        // Tax deducted at source, when the organisation has asked for it.
        // It is worked out from the whole year rather than from this slip, so
        // it is handed in as a deduction line rather than resolved from the
        // structure — and it is taken after the statutory deductions, never
        // as a base for them.
        $tax = $this->tax->forPayslip($employee, $payroll);

        $lines = $this->resolveComponentAmounts(
            $structure,
            $payFactor,
            $this->overtimeLine($overtime),
            $this->taxLine($tax),
        );

        $sequence = 0;
        foreach ($lines as $line) {
            PayslipItem::create([
                'payslip_id' => $payslip->id,
                'salary_component_id' => $line['component_id'],
                'name' => $line['name'],
                'code' => $line['code'],
                'type' => $line['type'],
                'amount' => $line['amount'],
                'is_statutory' => $line['is_statutory'],
                'sequence' => $sequence++,
            ]);
        }

        $gross = round(collect($lines)->where('type', ComponentType::Earning->value)->sum('amount'), 2);
        $deductions = round(collect($lines)->where('type', ComponentType::Deduction->value)->sum('amount'), 2);
        $net = round($gross - $deductions, 2);

        $basic = round(collect($lines)->firstWhere('code', 'BASIC')['amount'] ?? ($structure->basic_salary * $payFactor), 2);

        $payslip->forceFill([
            'basic_salary' => $basic,
            'overtime_rate' => $overtime['rate'],
            'overtime_amount' => $overtime['amount'],
            'tax_regime' => $tax['regime'],
            'tax_deducted' => $tax['amount'],
            'gross_earnings' => $gross,
            'total_deductions' => $deductions,
            'net_pay' => $net,
            'net_pay_words' => $this->amountInWords($net, $structure->currency),
        ])->save();

        return $payslip->fresh(['items', 'employee']);
    }

    /**
     * Resolve every structure component to a monetary amount for the period.
     *
     * Percentage components are resolved against basic first, then gross, so a
     * deduction expressed as a percentage of gross sees the final gross.
     *
     * `$extraEarnings` are lines that belong to this month rather than to the
     * structure — overtime is the only one today. They join gross before the
     * deductions are resolved, because a statutory deduction on gross wages is
     * due on them too. `$extraDeductions` is the mirror of that on the other
     * side: tax deducted at source, which is worked out from the whole year
     * rather than from anything on this structure.
     *
     * An extra deduction **replaces** a structure component carrying the same
     * code rather than joining it. The default component set has a flat five
     * per cent of gross under TDS, which is a placeholder for exactly the
     * arithmetic that now exists; leaving both on the slip would deduct tax
     * twice, and picking the wrong one silently would be worse.
     *
     * @param  array<int, array<string, mixed>>  $extraEarnings
     * @param  array<int, array<string, mixed>>  $extraDeductions
     * @return array<int, array<string, mixed>>
     */
    public function resolveComponentAmounts(
        SalaryStructure $structure,
        float $payFactor = 1.0,
        array $extraEarnings = [],
        array $extraDeductions = [],
    ): array {
        $structure->loadMissing('components.salaryComponent');

        $monthlyCtc = $structure->monthlyCtc();
        $basicFull = (float) $structure->basic_salary;

        $bases = [
            'basic' => $basicFull,
            'gross' => 0.0,
            'ctc' => $monthlyCtc,
        ];

        $rows = $structure->components
            ->filter(fn ($sc) => $sc->salaryComponent !== null)
            ->sortBy(fn ($sc) => $sc->salaryComponent->sequence)
            ->values();

        // Pass 1: earnings, so gross is known before deductions are resolved.
        $earnings = [];
        foreach ($rows as $row) {
            $component = $row->salaryComponent;

            if (! $component->isEarning()) {
                continue;
            }

            $full = $component->resolveAmount($row->value, $row->calculation_type, $bases);
            $amount = $component->prorate_on_lop ? round($full * $payFactor, 2) : round($full, 2);

            $earnings[] = [
                'component_id' => $component->id,
                'name' => $component->name,
                'code' => $component->code,
                'type' => ComponentType::Earning->value,
                'amount' => $amount,
                'is_statutory' => $component->is_statutory,
                'affects_gross' => (bool) $component->affects_gross,
            ];

            if ($component->affects_gross) {
                $bases['gross'] += $amount;
            }
        }

        foreach ($extraEarnings as $extra) {
            $earnings[] = $extra;

            if ($extra['affects_gross'] ?? true) {
                $bases['gross'] += (float) $extra['amount'];
            }
        }

        $bases['basic'] = round($basicFull * $payFactor, 2);
        $bases['gross'] = round($bases['gross'], 2);

        // Pass 2: deductions, now that gross is settled.
        $replaced = array_column($extraDeductions, 'code');
        $deductions = [];

        foreach ($rows as $row) {
            $component = $row->salaryComponent;

            if ($component->isEarning() || in_array($component->code, $replaced, true)) {
                continue;
            }

            $amount = $component->resolveAmount($row->value, $row->calculation_type, $bases);

            $deductions[] = [
                'component_id' => $component->id,
                'name' => $component->name,
                'code' => $component->code,
                'type' => ComponentType::Deduction->value,
                'amount' => round($amount, 2),
                'is_statutory' => $component->is_statutory,
                'affects_gross' => false,
            ];
        }

        // A replaced code is suppressed whether or not the line replacing it
        // has anything in it. Turning tax deduction on for somebody who owes
        // nothing must leave them owing nothing, not fall back to the flat
        // percentage the component carried.
        $extraDeductions = array_values(array_filter(
            $extraDeductions,
            fn ($line) => $line['amount'] > 0,
        ));

        return array_merge($earnings, $deductions, $extraDeductions);
    }

    /**
     * The structure's ordinary monthly gross, before loss of pay and before
     * overtime. This is the "ordinary rate of wages" an overtime hour is
     * multiplied from when the rate is set on gross rather than basic.
     */
    public function monthlyGross(SalaryStructure $structure): float
    {
        return round(
            collect($this->resolveComponentAmounts($structure, 1.0))
                ->where('type', ComponentType::Earning->value)
                ->where('affects_gross', true)
                ->sum('amount'),
            2,
        );
    }

    /**
     * The payslip row for the month's tax.
     *
     * Returned even at nothing, because the resolver reads its code to
     * suppress the structure's own TDS component before dropping the empty
     * line; the two are one decision and must not come apart.
     *
     * @param  array{enabled: bool, regime: ?string, amount: float}  $tax
     * @return array<int, array<string, mixed>>
     */
    protected function taxLine(array $tax): array
    {
        // Nothing at all while the feature is off, so a structure carrying its
        // own TDS component behaves exactly as it did before this existed.
        if (! $tax['enabled']) {
            return [];
        }

        return [[
            'component_id' => null,
            'name' => 'Income tax (TDS)',
            'code' => 'TDS',
            'type' => ComponentType::Deduction->value,
            'amount' => $tax['amount'],
            'is_statutory' => true,
            'affects_gross' => false,
        ]];
    }

    /**
     * The payslip row for a month's overtime, or nothing when none is payable.
     *
     * The hours go into the row's own name because they are what makes the
     * amount checkable, and because a name frozen onto the slip still reads
     * correctly after the rate or the cap is changed.
     *
     * @param  array{hours: float, rate: float, amount: float}  $overtime
     * @return array<int, array<string, mixed>>
     */
    protected function overtimeLine(array $overtime): array
    {
        if ($overtime['amount'] <= 0) {
            return [];
        }

        return [[
            'component_id' => null,
            'name' => 'Overtime ('.number_format($overtime['hours'], 2).' hrs)',
            'code' => 'OT',
            'type' => ComponentType::Earning->value,
            'amount' => $overtime['amount'],
            'is_statutory' => false,
            'affects_gross' => true,
        ]];
    }

    /** Recompute a structure's stored component amounts and gross. */
    public function syncStructureAmounts(SalaryStructure $structure): SalaryStructure
    {
        $lines = $this->resolveComponentAmounts($structure, 1.0);
        $byComponent = collect($lines)->keyBy('component_id');

        foreach ($structure->components as $row) {
            if ($line = $byComponent->get($row->salary_component_id)) {
                $row->update(['computed_amount' => $line['amount']]);
            }
        }

        $gross = collect($lines)->where('type', ComponentType::Earning->value)->sum('amount');

        $structure->forceFill(['gross_monthly' => round($gross, 2)])->save();

        return $structure->fresh('components.salaryComponent');
    }

    public function submitForApproval(Payroll $payroll): Payroll
    {
        if ($payroll->status !== PayrollStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Only a draft run can be submitted.']);
        }

        if (! $payroll->payslips()->exists()) {
            throw ValidationException::withMessages(['status' => 'Generate payslips before submitting the run.']);
        }

        $payroll->forceFill(['status' => PayrollStatus::PendingApproval])->save();

        return $payroll;
    }

    /** Approve a run and publish its payslips so employees can see them. */
    public function approve(Payroll $payroll, User $approver): Payroll
    {
        if ($payroll->isApproved()) {
            throw ValidationException::withMessages(['status' => 'This run has already been approved.']);
        }

        return DB::transaction(function () use ($payroll, $approver) {
            $payroll->payslips()->update(['status' => 'published']);

            $payroll->forceFill([
                'status' => PayrollStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ])->save();

            $payroll->recalculateTotals();

            return $payroll->fresh();
        });
    }

    public function markPaid(Payroll $payroll, ?string $reference = null, ?Carbon $paidOn = null): Payroll
    {
        if ($payroll->status !== PayrollStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => 'Only an approved run can be marked as paid.',
            ]);
        }

        $paidOn ??= Carbon::today();

        return DB::transaction(function () use ($payroll, $reference, $paidOn) {
            $payroll->payslips()->update([
                'payment_status' => 'paid',
                'payment_date' => $paidOn->toDateString(),
                'payment_reference' => $reference,
            ]);

            $payroll->forceFill([
                'status' => PayrollStatus::Paid,
                'payment_date' => $paidOn->toDateString(),
            ])->save();

            return $payroll->fresh();
        });
    }

    public function cancel(Payroll $payroll): Payroll
    {
        $payroll->forceFill(['status' => PayrollStatus::Cancelled])->save();

        return $payroll;
    }

    /** Attach the default component set to a new salary structure. */
    public function applyDefaultComponents(SalaryStructure $structure): SalaryStructure
    {
        foreach (SalaryComponent::active()->orderBy('sequence')->get() as $component) {
            $structure->components()->firstOrCreate(
                ['salary_component_id' => $component->id],
                [
                    'calculation_type' => $component->calculation_type,
                    'value' => $component->default_value,
                ],
            );
        }

        return $this->syncStructureAmounts($structure->fresh('components.salaryComponent'));
    }

    /** Aggregate register rows for the payroll report. */
    public function registerFor(Payroll $payroll): Collection
    {
        return $payroll->payslips()
            ->with(['employee.department', 'employee.designation', 'items'])
            ->join('employees', 'employees.id', '=', 'payslips.employee_id')
            ->orderBy('employees.employee_code')
            ->select('payslips.*')
            ->get();
    }

    /**
     * Slip numbers carry the employing company's prefix, so a slip says which
     * entity issued it without anybody having to open it.
     */
    protected function slipNumberFor(Payroll $payroll, Employee $employee): string
    {
        $prefix = $employee->company?->payslip_prefix
            ?: $payroll->company?->payslip_prefix
            ?: 'PS';

        return sprintf('%s-%d%02d-%s', $prefix, $payroll->year, $payroll->month, $employee->employee_code);
    }

    protected function nextRunReference(int $year, int $month): string
    {
        return sprintf('PR-%d%02d-%s', $year, $month, Str::upper(Str::random(4)));
    }

    /**
     * Render an amount as words for the payslip footer.
     *
     * The counting is the currency's own: rupees are read in lakh and crore.
     */
    public function amountInWords(float $amount, string $currency = 'INR'): string
    {
        return Money::inWords($amount, $currency);
    }

    /** Currency symbol used across the UI and PDFs. */
    public static function currencySymbol(?string $currency = null): string
    {
        $currency ??= Setting::get('currency', 'INR');

        return match ($currency) {
            'INR' => "\u{20B9}",
            'USD' => '$',
            'EUR' => "\u{20AC}",
            'GBP' => "\u{A3}",
            'AED' => 'AED ',
            default => $currency.' ',
        };
    }
}
