<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasFactory;

    public const ACCRUAL_YEARLY = 'yearly';

    public const ACCRUAL_MONTHLY = 'monthly';

    protected $fillable = [
        'name', 'code', 'description', 'days_per_year',
        'accrual', 'days_per_month', 'probation_days_per_month', 'accrue_in_advance', 'accrual_starts_on',
        'is_paid', 'requires_approval',
        'allow_half_day', 'carry_forward', 'max_carry_forward_days', 'max_consecutive_days',
        'min_notice_days', 'applicable_gender', 'applicable_after_months',
        'requires_attachment', 'color', 'status',
    ];

    protected function casts(): array
    {
        return [
            'days_per_year' => 'float',
            'days_per_month' => 'float',
            'probation_days_per_month' => 'float',
            'accrue_in_advance' => 'boolean',
            'accrual_starts_on' => 'date:Y-m-d',
            'is_paid' => 'boolean',
            'requires_approval' => 'boolean',
            'allow_half_day' => 'boolean',
            'carry_forward' => 'boolean',
            'requires_attachment' => 'boolean',
            'max_carry_forward_days' => 'float',
            'max_consecutive_days' => 'integer',
            'min_notice_days' => 'integer',
            'applicable_after_months' => 'integer',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LeaveAllocation::class);
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(LeaveAccrual::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function accruesMonthly(): bool
    {
        return $this->accrual === self::ACCRUAL_MONTHLY;
    }

    /**
     * How many days this employee earns for a full month.
     *
     * Somebody still on probation earns the lower rate where one is set; where
     * it is not, probation earns the same as everybody else rather than
     * nothing, because an empty box means "not configured", not "zero".
     */
    public function monthlyRateFor(Employee $employee, ?CarbonInterface $asOf = null): float
    {
        if ($employee->isOnProbation($asOf) && $this->probation_days_per_month !== null) {
            return (float) $this->probation_days_per_month;
        }

        return (float) $this->days_per_month;
    }

    /** How the entitlement reads on screen. */
    public function entitlementLabel(): string
    {
        if (! $this->accruesMonthly()) {
            return self::days($this->days_per_year).' a year';
        }

        $standard = self::days($this->days_per_month).' a month';

        if ($this->probation_days_per_month === null
            || (float) $this->probation_days_per_month === (float) $this->days_per_month) {
            return $standard;
        }

        return $standard.', '.self::days($this->probation_days_per_month).' on probation';
    }

    /** A day count without a trailing .00 or .50. */
    public static function days(float|int|null $days): string
    {
        return rtrim(rtrim(number_format((float) $days, 2), '0'), '.').' '
            .((float) $days === 1.0 ? 'day' : 'days');
    }

    /** Whether this type may be used by the given employee. */
    public function isApplicableTo(Employee $employee): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->applicable_gender !== 'any'
            && $employee->gender
            && $this->applicable_gender !== $employee->gender) {
            return false;
        }

        return $employee->tenureInMonths() >= $this->applicable_after_months;
    }
}
