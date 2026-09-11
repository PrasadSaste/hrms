<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id', 'name', 'code', 'start_time', 'end_time',
        'grace_minutes', 'break_minutes', 'half_day_hours', 'full_day_hours',
        'working_days', 'is_default', 'status',
    ];

    protected function casts(): array
    {
        return [
            'working_days' => 'array',
            'is_default' => 'boolean',
            'half_day_hours' => 'float',
            'full_day_hours' => 'float',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function workingDays(): array
    {
        return $this->working_days ?: [1, 2, 3, 4, 5];
    }

    public function fullDayMinutes(): int
    {
        return (int) round($this->full_day_hours * 60);
    }

    public function halfDayMinutes(): int
    {
        return (int) round($this->half_day_hours * 60);
    }
}
