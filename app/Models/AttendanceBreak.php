<?php

namespace App\Models;

use App\Support\BreakReasons;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Time away from work during the day, and what it was for.
 */
class AttendanceBreak extends Model
{
    protected $fillable = [
        'attendance_id', 'employee_id', 'reason', 'comment',
        'started_at', 'ended_at', 'duration_minutes', 'source',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('ended_at');
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    public function label(): string
    {
        return BreakReasons::label($this->reason);
    }

    public function icon(): string
    {
        return BreakReasons::icon($this->reason);
    }

    /** Counts up while the break is still running. */
    public function minutes(?Carbon $now = null): int
    {
        if ($this->ended_at) {
            return (int) $this->duration_minutes;
        }

        return max(0, (int) $this->started_at->diffInMinutes($now ?? Carbon::now()));
    }

    public function seconds(?Carbon $now = null): int
    {
        $end = $this->ended_at ?? ($now ?? Carbon::now());

        return max(0, (int) $this->started_at->diffInSeconds($end));
    }
}
