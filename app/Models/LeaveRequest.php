<?php

namespace App\Models;

use App\Enums\DayType;
use App\Enums\LeaveStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'employee_id', 'leave_type_id', 'start_date', 'end_date',
        'day_type', 'total_days', 'reason', 'contact_during_leave', 'attachment_path',
        'status', 'applied_on', 'approver_id', 'actioned_at', 'approver_remarks', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'applied_on' => 'datetime',
            'actioned_at' => 'datetime',
            'total_days' => 'float',
            'status' => LeaveStatus::class,
            'day_type' => DayType::class,
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function scopeStatus(Builder $query, string|LeaveStatus|null $status): Builder
    {
        if (! $status) {
            return $query;
        }

        return $query->where('status', $status instanceof LeaveStatus ? $status->value : $status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LeaveStatus::Pending->value);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', LeaveStatus::Approved->value);
    }

    /** Requests overlapping the given date range. */
    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->where('start_date', '<=', $to)->where('end_date', '>=', $from);
    }

    public function isPending(): bool
    {
        return $this->status === LeaveStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === LeaveStatus::Approved;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [LeaveStatus::Pending, LeaveStatus::Approved], true)
            && $this->end_date->gte(Carbon::today());
    }

    public function periodLabel(): string
    {
        if ($this->start_date->isSameDay($this->end_date)) {
            return $this->start_date->format('d M Y').
                ($this->day_type !== DayType::FullDay ? ' ('.$this->day_type->label().')' : '');
        }

        return $this->start_date->format('d M Y').' to '.$this->end_date->format('d M Y');
    }

    /** Every calendar date covered by this request. */
    public function datesInRange(): array
    {
        $dates = [];
        $cursor = $this->start_date->copy();

        while ($cursor->lte($this->end_date)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }
}
