<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One month's leave credited to one person.
 *
 * The ledger behind a balance. Every credit is a row, so the monthly job can
 * be run as often as you like without paying anybody twice, a month the server
 * missed is simply a row that is not there yet, and "why do I have 7.5 days"
 * has an answer with dates against it.
 */
class LeaveAccrual extends Model
{
    public const BASIS_PERMANENT = 'permanent';

    public const BASIS_PROBATION = 'probation';

    protected $fillable = [
        'employee_id', 'leave_type_id', 'year', 'month',
        'days', 'rate', 'basis', 'worked_fraction', 'note',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'days' => 'float',
            'rate' => 'float',
            'worked_fraction' => 'float',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /** The month this credit was for. */
    public function period(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1)->startOfDay();
    }

    public function periodLabel(): string
    {
        return $this->period()->format('F Y');
    }

    public function basisLabel(): string
    {
        return $this->basis === self::BASIS_PROBATION ? 'On probation' : 'Confirmed';
    }

    /** Whether this month was credited at less than the full rate. */
    public function isPartial(): bool
    {
        return $this->worked_fraction < 1;
    }
}
