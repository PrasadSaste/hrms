<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Setting;

/**
 * Whether extra hours are paid, and at what rate.
 *
 * Attendance has always recorded the minutes worked beyond a full day —
 * `AttendanceService::rebuild()` writes them to `attendances.overtime_minutes`
 * whether anybody is paid for them or not. This service is the only thing that
 * turns those minutes into money, and it does nothing at all until somebody
 * switches overtime on: an installation that leaves it alone keeps reporting
 * the hours and paying nobody for them, which is what a salaried office wants.
 *
 * The rate is an hourly one derived from the month, not a stored figure:
 *
 *     hourly = base ÷ (working days in the month × hours in a working day)
 *     paid   = hourly × multiplier × hours
 *
 * The base is basic or gross, because both readings of "ordinary rate of
 * wages" are in use; the multiplier defaults to two, which is what section 59
 * of the Factories Act 1948 requires. Whatever comes out is frozen onto the
 * payslip beside the hours, so a slip issued last March still explains itself
 * after the rate is changed.
 */
class OvertimeService
{
    public const BASES = [
        'basic' => 'Basic salary',
        'gross' => 'Gross earnings',
    ];

    /** Section 59 of the Factories Act 1948: twice the ordinary rate. */
    public const DEFAULT_MULTIPLIER = 2.0;

    public const DEFAULT_HOURS_PER_DAY = 8.0;

    public function enabled(): bool
    {
        return (bool) Setting::get('payroll_overtime_enabled', false);
    }

    public function basis(): string
    {
        $basis = (string) Setting::get('payroll_overtime_basis', 'basic');

        return array_key_exists($basis, self::BASES) ? $basis : 'basic';
    }

    public function multiplier(): float
    {
        $multiplier = (float) Setting::get('payroll_overtime_multiplier', self::DEFAULT_MULTIPLIER);

        return $multiplier > 0 ? $multiplier : self::DEFAULT_MULTIPLIER;
    }

    public function hoursPerDay(): float
    {
        $hours = (float) Setting::get('payroll_overtime_hours_per_day', self::DEFAULT_HOURS_PER_DAY);

        return $hours > 0 ? $hours : self::DEFAULT_HOURS_PER_DAY;
    }

    /** Hours beyond which a month's overtime is not paid. Zero means no cap. */
    public function monthlyCapHours(): float
    {
        return max(0.0, (float) Setting::get('payroll_overtime_monthly_cap_hours', 0));
    }

    /** Somebody is only paid overtime if the feature is on and they are eligible. */
    public function appliesTo(Employee $employee): bool
    {
        return $this->enabled() && $employee->overtime_eligible;
    }

    /** The hours actually payable, once the monthly cap has had its say. */
    public function payableHours(float $hours): float
    {
        $hours = max(0.0, round($hours, 2));
        $cap = $this->monthlyCapHours();

        return $cap > 0 ? min($hours, $cap) : $hours;
    }

    /**
     * The hourly rate for a month, or zero if the month has no working hours.
     *
     * Divided by the month's own working days rather than a fixed divisor, so
     * an hour in February is worth slightly more than an hour in March — the
     * same salary spread over fewer days.
     */
    public function hourlyRate(float $base, float $workingDays): float
    {
        $hours = $workingDays * $this->hoursPerDay();

        return $hours > 0 ? round($base / $hours, 2) : 0.0;
    }

    /**
     * What an employee is owed for a month's extra hours.
     *
     * Returns the rate as well as the amount because the payslip stores both:
     * "12 hours at ₹289.00" is a sentence somebody can check, where a single
     * total is one they have to take on trust.
     *
     * @return array{hours: float, rate: float, amount: float}
     */
    public function payFor(Employee $employee, float $hours, float $base, float $workingDays): array
    {
        if (! $this->appliesTo($employee)) {
            return ['hours' => 0.0, 'rate' => 0.0, 'amount' => 0.0];
        }

        $hours = $this->payableHours($hours);
        $rate = round($this->hourlyRate($base, $workingDays) * $this->multiplier(), 2);

        return [
            'hours' => $hours,
            'rate' => $rate,
            'amount' => round($rate * $hours, 2),
        ];
    }
}
