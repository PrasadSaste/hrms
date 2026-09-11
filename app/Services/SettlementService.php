<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Payslip;
use App\Models\Setting;
use App\Models\Settlement;
use App\Models\SettlementLine;
use App\Models\User;
use App\Support\NotificationEvents;
use App\Support\SettlementLines;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What somebody is owed, or owes, on their last day.
 *
 * The arithmetic lives here because it has to be identical wherever it is
 * asked for, and because every line of it is the kind of number a person
 * queries. Three of the lines are worked out; the rest are somebody's
 * judgement and are typed in.
 *
 * Nothing here is recomputed once a settlement is approved. A settlement is
 * the arithmetic behind a payment already made, and re-deriving it later from
 * a changed salary structure or a changed statutory cap would answer a
 * different question and contradict a statement somebody is holding.
 */
class SettlementService
{
    /**
     * The statutory ceiling on gratuity.
     *
     * Raised by notification from time to time, so it is a setting rather than
     * a constant — but it has a default, because an installation that has
     * never opened the settings screen should still compute a lawful figure.
     */
    public const GRATUITY_CAP = 2000000.0;

    /** Five completed years, under the Payment of Gratuity Act. */
    public const GRATUITY_MINIMUM_YEARS = 5.0;

    /**
     * Fifteen days' wages per completed year, where a month's wages are
     * treated as twenty-six days. Both halves of 15/26 come from the Act.
     */
    public const GRATUITY_DAYS = 15.0;

    public const GRATUITY_DIVISOR = 26.0;

    public function __construct(
        protected LeaveService $leave,
        protected AssetService $assets,
        protected NotificationDispatcher $dispatcher,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Working it out
    |--------------------------------------------------------------------------
    */

    /**
     * Everything this system can work out for itself, without writing anything.
     *
     * Handed to the screen so whoever prepares the settlement sees the figures
     * and the reasoning before committing to them.
     *
     * @return array<string, mixed>
     */
    public function preview(Employee $employee, ?Carbon $lastWorkingDay = null): array
    {
        $lastDay = $lastWorkingDay ?? $employee->date_of_exit ?? Carbon::today();

        $structure = $employee->salaryStructureOn($lastDay) ?? $employee->salaryStructureOn();
        $basic = $this->basicFrom($structure);
        $gross = $structure?->totalEarnings() ?? 0.0;
        $divisor = $this->perDayDivisor();
        $perDay = $divisor > 0 ? round($basic / $divisor, 2) : 0.0;

        $service = $this->serviceYears($employee, $lastDay);
        $encashable = $this->encashableDays($employee, $lastDay);
        $notice = $this->notice($employee, $lastDay);

        return [
            'employee' => $employee,
            'last_working_day' => $lastDay,
            'structure' => $structure,
            'last_drawn_basic' => $basic,
            'last_drawn_gross' => $gross,
            'per_day_divisor' => $divisor,
            'per_day' => $perDay,
            // Rounded for the screen and the row; the rules above used the
            // exact figure, which is the whole point.
            'service_years' => round($service, 2),
            'encashable_days' => $encashable,
            'notice' => $notice,
            'gratuity_eligible' => $service >= self::GRATUITY_MINIMUM_YEARS,
            'outstanding_assets' => $this->assets->outstandingFor($employee),
            'lines' => $this->computedLines($employee, $lastDay, $basic, $perDay, $service, $encashable, $notice),
        ];
    }

    /**
     * The three lines this system works out, with the reasoning kept beside
     * each so the statement can be read without a spreadsheet.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function computedLines(
        Employee $employee,
        Carbon $lastDay,
        float $basic,
        float $perDay,
        float $service,
        float $encashable,
        array $notice,
    ): array {
        $lines = [];

        /*
         * The final month's pay.
         *
         * Prorated across the month's own days on the whole salary, not at the
         * twenty-six day rate: that rate exists because the Gratuity Act says
         * a month's wages are twenty-six days, and applying it to a full
         * month's salary would pay 30/26 of it — fifteen per cent too much.
         *
         * And nothing is added at all if payroll already paid that month.
         * A settlement that quietly pays September twice is worse than one
         * that leaves it out, because the second is noticed.
         */
        $worked = $this->workedDaysInFinalMonth($employee, $lastDay);
        $alreadyPaid = $this->finalMonthAlreadyPaid($employee, $lastDay);

        if ($worked['paid'] > 0 && ! $alreadyPaid) {
            $gross = $this->grossFor($employee, $lastDay);

            $lines[] = $this->line(
                SettlementLines::FINAL_SALARY,
                round($gross * $worked['paid'] / max(1, $worked['total']), 2),
                sprintf(
                    '%s of the %s days in %s, on a monthly %s.',
                    $this->number($worked['paid']),
                    $this->number($worked['total']),
                    $lastDay->format('F Y'),
                    number_format($gross, 2),
                ),
            );
        }

        // ---------------------------------------------- leave encashment
        if ($encashable > 0) {
            $lines[] = $this->line(
                SettlementLines::LEAVE_ENCASHMENT,
                round($perDay * $encashable, 2),
                sprintf(
                    '%s unused days that carry forward, at a basic of %s over %d days.',
                    $this->number($encashable),
                    number_format($basic, 2),
                    $this->perDayDivisor(),
                ),
            );
        }

        // ------------------------------------------------------- gratuity
        if ($service >= self::GRATUITY_MINIMUM_YEARS) {
            $completed = floor($service);
            $raw = $basic * self::GRATUITY_DAYS / self::GRATUITY_DIVISOR * $completed;
            $capped = min($raw, $this->gratuityCap());

            $lines[] = $this->line(
                SettlementLines::GRATUITY,
                round($capped, 2),
                $raw > $capped
                    ? sprintf('%d completed years — capped at the statutory maximum of %s.', $completed, number_format($this->gratuityCap(), 2))
                    : sprintf('%d completed years at 15/26 of a last drawn basic of %s.', $completed, number_format($basic, 2)),
            );
        }

        // ------------------------------------------------ notice shortfall
        if ($notice['shortfall'] > 0) {
            $lines[] = $this->line(
                SettlementLines::NOTICE_RECOVERY,
                round($perDay * $notice['shortfall'], 2),
                sprintf(
                    '%d days short of the %d required, at %s a day.',
                    $notice['shortfall'],
                    $notice['required'],
                    number_format($perDay, 2),
                ),
            );
        }

        return $lines;
    }

    /** @return array<string, mixed> */
    protected function line(string $key, float $amount, string $basis): array
    {
        return [
            'key' => $key,
            'label' => SettlementLines::label($key),
            'type' => SettlementLines::type($key),
            'amount' => $amount,
            'basis' => $basis,
            'is_computed' => true,
            'sequence' => SettlementLines::sequence($key),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | The pieces
    |--------------------------------------------------------------------------
    */

    /**
     * Completed service, in years, to two places.
     *
     * Gratuity turns on whether five years are *complete*, so this is a length
     * rather than a count of calendar years.
     */
    /**
     * Returned unrounded, deliberately.
     *
     * Four years, three hundred and sixty-four days rounds to 5.00 at two
     * places, and gratuity turns on five years being *complete*. Rounding here
     * would have granted a statutory payment a day early, every time. The
     * figure is rounded where it is stored and shown, never where it is
     * compared.
     */
    public function serviceYears(Employee $employee, ?Carbon $asOf = null): float
    {
        $joined = $employee->date_of_joining;

        if (! $joined) {
            return 0.0;
        }

        $end = $asOf ?? $employee->date_of_exit ?? Carbon::today();

        return $end->lt($joined) ? 0.0 : $joined->diffInDays($end) / 365.25;
    }

    /**
     * Days that can actually be paid out.
     *
     * Only leave that carries forward is encashed: a casual leave allowance
     * that lapses at the end of the year does not become money because
     * somebody left in November.
     */
    public function encashableDays(Employee $employee, ?Carbon $asOf = null): float
    {
        $year = (int) ($asOf ?? Carbon::today())->format('Y');

        return round(
            collect($this->leave->balanceSummary($employee, $year))
                ->filter(fn (array $row) => $row['leave_type']->carry_forward && $row['leave_type']->is_paid)
                ->sum(fn (array $row) => max(0, $row['remaining'])),
            2,
        );
    }

    /**
     * Notice required, served, and the gap between them.
     *
     * @return array{required: int, served: int, shortfall: int, waived: bool}
     */
    public function notice(Employee $employee, ?Carbon $lastDay = null): array
    {
        $required = (int) ($employee->notice_period_days ?? 0);
        $lastDay ??= $employee->date_of_exit ?? Carbon::today();

        // Nothing records the day somebody resigned, so notice served cannot
        // be derived — it is the required period unless whoever prepares the
        // settlement says otherwise. That keeps the default at "no recovery",
        // which is the right way for a guess to be wrong.
        return [
            'required' => $required,
            'served' => $required,
            'shortfall' => 0,
            'waived' => false,
        ];
    }

    /**
     * How much of the final month was actually worked.
     *
     * @return array{paid: float, total: int}
     */
    protected function workedDaysInFinalMonth(Employee $employee, Carbon $lastDay): array
    {
        $start = $lastDay->copy()->startOfMonth();

        return [
            'paid' => (float) $start->diffInDays($lastDay) + 1,
            'total' => (int) $lastDay->daysInMonth,
        ];
    }

    /**
     * Last drawn basic, which is what gratuity, encashment and notice are all
     * reckoned on — not gross, and not cost to company.
     */
    /** The whole monthly salary, which is what a part-month is prorated from. */
    protected function grossFor(Employee $employee, Carbon $lastDay): float
    {
        $structure = $employee->salaryStructureOn($lastDay) ?? $employee->salaryStructureOn();

        return round((float) ($structure?->totalEarnings() ?? 0), 2);
    }

    /**
     * Whether payroll has already paid the month somebody left in.
     *
     * Only a published slip counts: a draft run is not money anybody has.
     */
    protected function finalMonthAlreadyPaid(Employee $employee, Carbon $lastDay): bool
    {
        return Payslip::query()
            ->where('employee_id', $employee->id)
            ->published()
            ->whereYear('period_start', $lastDay->year)
            ->whereMonth('period_start', $lastDay->month)
            ->exists();
    }

    protected function basicFrom(?object $structure): float
    {
        if (! $structure) {
            return 0.0;
        }

        $structure->loadMissing('components.salaryComponent');

        $basic = $structure->components
            ->first(fn ($component) => strtoupper((string) $component->salaryComponent?->code) === 'BASIC');

        // computed_amount, not amount: the figure the structure actually
        // resolved to, which is what a payslip pays.
        return round((float) ($basic?->computed_amount ?? 0), 2);
    }

    /** The days a month's pay is divided by. Twenty-six is the usual answer. */
    public function perDayDivisor(): int
    {
        return max(1, (int) Setting::get('settlement_per_day_divisor', 26));
    }

    public function gratuityCap(): float
    {
        return (float) Setting::get('settlement_gratuity_cap', self::GRATUITY_CAP);
    }

    protected function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }

    /*
    |--------------------------------------------------------------------------
    | Preparing, approving, paying
    |--------------------------------------------------------------------------
    */

    /**
     * Open a draft from what can be worked out.
     *
     * @param  array<int, array<string, mixed>>  $extraLines
     */
    public function prepare(Employee $employee, array $data = [], ?User $by = null): Settlement
    {
        if ($employee->settlement()->exists()) {
            throw ValidationException::withMessages([
                'employee_id' => $employee->full_name.' already has a settlement. Open that one instead of starting another.',
            ]);
        }

        $lastDay = isset($data['last_working_day'])
            ? Carbon::parse($data['last_working_day'])
            : ($employee->date_of_exit ?? Carbon::today());

        $preview = $this->preview($employee, $lastDay);

        return DB::transaction(function () use ($employee, $preview, $lastDay, $data, $by) {
            $settlement = Settlement::create([
                'reference' => $this->nextReference($lastDay),
                'employee_id' => $employee->id,
                'company_id' => $employee->company_id,
                'date_of_joining' => $employee->date_of_joining,
                'last_working_day' => $lastDay,
                'exit_reason' => $data['exit_reason'] ?? $employee->exit_reason,
                'last_drawn_basic' => $preview['last_drawn_basic'],
                'last_drawn_gross' => $preview['last_drawn_gross'],
                'service_years' => $preview['service_years'],
                'per_day_divisor' => $preview['per_day_divisor'],
                'encashable_days' => $preview['encashable_days'],
                'notice_required_days' => $preview['notice']['required'],
                'notice_served_days' => $preview['notice']['served'],
                'notice_waived' => $preview['notice']['waived'],
                'gratuity_eligible' => $preview['gratuity_eligible'],
                'currency' => $employee->company?->currency ?? 'INR',
                'status' => Settlement::DRAFT,
                'notes' => $data['notes'] ?? null,
                'prepared_by' => $by?->id,
            ]);

            foreach ($preview['lines'] as $line) {
                $settlement->lines()->create($line);
            }

            return $this->retotal($settlement);
        });
    }

    /**
     * Add or change a line somebody has to decide for themselves.
     *
     * @param  array{key: string, amount: float, label?: ?string, basis?: ?string}  $data
     */
    public function addLine(Settlement $settlement, array $data): SettlementLine
    {
        $this->refuseIfSettled($settlement);

        $key = $data['key'];

        $line = $settlement->lines()->create([
            'key' => $key,
            'label' => $data['label'] ?: SettlementLines::label($key),
            'type' => SettlementLines::type($key),
            'amount' => round((float) $data['amount'], 2),
            'basis' => $data['basis'] ?? null,
            'is_computed' => false,
            'sequence' => SettlementLines::sequence($key),
        ]);

        $this->retotal($settlement);

        return $line;
    }

    public function removeLine(Settlement $settlement, SettlementLine $line): void
    {
        $this->refuseIfSettled($settlement);

        abort_unless($line->settlement_id === $settlement->id, 403);

        $line->delete();

        $this->retotal($settlement);
    }

    /** Add the lines up. Deductions are stored positive and subtracted here. */
    public function retotal(Settlement $settlement): Settlement
    {
        $settlement->load('lines');

        $earnings = round($settlement->lines->where('type', SettlementLines::EARNING)->sum('amount'), 2);
        $deductions = round($settlement->lines->where('type', SettlementLines::DEDUCTION)->sum('amount'), 2);

        $settlement->update([
            'total_earnings' => $earnings,
            'total_deductions' => $deductions,
            'net_payable' => round($earnings - $deductions, 2),
        ]);

        return $settlement->fresh('lines');
    }

    /**
     * Sign it off. From here the figures are fixed.
     */
    public function approve(Settlement $settlement, User $approver): Settlement
    {
        if ($settlement->isApproved()) {
            throw ValidationException::withMessages(['status' => 'This settlement has already been approved.']);
        }

        if ($settlement->lines->isEmpty()) {
            throw ValidationException::withMessages(['status' => 'There is nothing on this settlement to approve.']);
        }

        $settlement->update([
            'status' => Settlement::APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        $this->tell(NotificationEvents::SETTLEMENT_READY, $settlement->fresh());

        return $settlement->fresh();
    }

    public function markPaid(Settlement $settlement, ?Carbon $on = null): Settlement
    {
        if (! $settlement->isApproved()) {
            throw ValidationException::withMessages(['status' => 'Approve the settlement before marking it paid.']);
        }

        $settlement->update([
            'status' => Settlement::PAID,
            'settled_on' => $on ?? Carbon::today(),
            'paid_at' => now(),
        ]);

        $this->tell(NotificationEvents::SETTLEMENT_PAID, $settlement->fresh());

        return $settlement->fresh();
    }

    protected function refuseIfSettled(Settlement $settlement): void
    {
        if (! $settlement->isEditable()) {
            throw ValidationException::withMessages([
                'status' => 'An approved settlement cannot be changed. Somebody is holding a statement of these figures.',
            ]);
        }
    }

    /** SET/2026/0001, counted within the year it belongs to. */
    protected function nextReference(Carbon $lastDay): string
    {
        $year = $lastDay->format('Y');

        $count = Settlement::where('reference', 'like', "SET/{$year}/%")->count() + 1;

        return sprintf('SET/%s/%04d', $year, $count);
    }

    protected function tell(string $event, Settlement $settlement): void
    {
        $employee = $settlement->employee;

        if (! $employee?->user) {
            return;
        }

        $this->dispatcher->toUser($event, $employee->user, [
            'first_name' => $employee->first_name,
            'employee_name' => $employee->full_name,
            'employee_code' => $employee->employee_code,
            'reference' => $settlement->reference,
            'last_working_day' => $settlement->last_working_day?->format('d M Y'),
            'total_earnings' => number_format($settlement->total_earnings, 2),
            'total_deductions' => number_format($settlement->total_deductions, 2),
            'net_payable' => number_format(abs($settlement->net_payable), 2),
            'net_label' => $settlement->netLabel(),
            'settled_on' => $settlement->settled_on?->format('d M Y'),
        ]);
    }

    /** @return Collection<int, Settlement> */
    public function awaitingApproval(): Collection
    {
        return Settlement::draft()->with('employee')->orderBy('last_working_day')->get();
    }
}
