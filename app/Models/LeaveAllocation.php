<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'leave_type_id', 'year',
        'allocated_days', 'carried_forward_days', 'used_days', 'encashed_days', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'allocated_days' => 'float',
            'carried_forward_days' => 'float',
            'used_days' => 'float',
            'encashed_days' => 'float',
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

    public function entitledDays(): float
    {
        return round($this->allocated_days + $this->carried_forward_days, 2);
    }

    public function remainingDays(): float
    {
        return round($this->entitledDays() - $this->used_days - $this->encashed_days, 2);
    }

    public function usedPercentage(): float
    {
        $entitled = $this->entitledDays();

        return $entitled > 0 ? round(($this->used_days / $entitled) * 100, 1) : 0.0;
    }
}
