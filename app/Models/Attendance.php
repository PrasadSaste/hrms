<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Support\Geo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'shift_id', 'date', 'check_in', 'check_out',
        'check_in_ip', 'check_out_ip', 'check_in_location', 'check_out_location',
        'check_in_latitude', 'check_in_longitude', 'check_in_accuracy',
        'check_out_latitude', 'check_out_longitude', 'check_out_accuracy',
        'worked_minutes', 'break_minutes', 'late_minutes', 'early_leaving_minutes',
        'overtime_minutes', 'status', 'needs_correction', 'source', 'remarks', 'marked_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'status' => AttendanceStatus::class,
            'needs_correction' => 'boolean',
            'worked_minutes' => 'integer',
            'break_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_leaving_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'check_in_latitude' => 'float',
            'check_in_longitude' => 'float',
            'check_in_accuracy' => 'integer',
            'check_out_latitude' => 'float',
            'check_out_longitude' => 'float',
            'check_out_accuracy' => 'integer',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class)->orderBy('started_at');
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class)->orderBy('started_at');
    }

    /** The session someone is currently punched into, if any. */
    public function openSession(): ?AttendanceSession
    {
        return $this->sessions->firstWhere('ended_at', null);
    }

    /** The break someone is currently on, if any. */
    public function openBreak(): ?AttendanceBreak
    {
        return $this->breaks->firstWhere('ended_at', null);
    }

    /**
     * The furthest a punch on this day was made from the branch, in metres,
     * counting only the punches that were further than the branch allows.
     *
     * Needs the sessions loaded; a day whose punches were all within range,
     * or which was never measured, returns null.
     */
    public function furthestPunchAway(): ?int
    {
        return $this->sessions
            ->flatMap(fn (AttendanceSession $session) => [
                $session->check_in_outside_geofence ? $session->check_in_distance_metres : null,
                $session->check_out_outside_geofence ? $session->check_out_distance_metres : null,
            ])
            ->filter()
            ->max();
    }

    public function isPunchedIn(): bool
    {
        return $this->openSession() !== null;
    }

    public function isOnBreak(): bool
    {
        return $this->openBreak() !== null;
    }

    /**
     * Time at work so far today, breaks taken out, counting up while somebody
     * is still punched in.
     */
    public function workedSecondsSoFar(?Carbon $now = null): int
    {
        $now ??= Carbon::now();

        $attended = $this->sessions->sum(fn (AttendanceSession $s) => $s->seconds($now));

        return max(0, $attended - $this->breakSecondsSoFar($now));
    }

    /** Every break added up, including one still running. */
    public function breakSecondsSoFar(?Carbon $now = null): int
    {
        $now ??= Carbon::now();

        return (int) $this->breaks->sum(fn (AttendanceBreak $b) => $b->seconds($now));
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('date', $year)->whereMonth('date', $month);
    }

    public function isOpen(): bool
    {
        return $this->check_in !== null && $this->check_out === null;
    }

    public function workedHours(): float
    {
        return round($this->worked_minutes / 60, 2);
    }

    public function overtimeHours(): float
    {
        return round($this->overtime_minutes / 60, 2);
    }

    public function hasCheckInCoordinates(): bool
    {
        return $this->check_in_latitude !== null && $this->check_in_longitude !== null;
    }

    public function hasCheckOutCoordinates(): bool
    {
        return $this->check_out_latitude !== null && $this->check_out_longitude !== null;
    }

    /** A map link for one side of the day, or null when no fix was recorded. */
    public function mapUrl(string $side = 'check_in'): ?string
    {
        return Geo::mapUrl($this->{$side.'_latitude'}, $this->{$side.'_longitude'});
    }

    public function durationLabel(): string
    {
        $h = intdiv($this->worked_minutes, 60);
        $m = $this->worked_minutes % 60;

        return sprintf('%dh %02dm', $h, $m);
    }
}
