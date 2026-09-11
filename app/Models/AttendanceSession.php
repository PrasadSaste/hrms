<?php

namespace App\Models;

use App\Support\Geo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One punch in and the punch out that closed it.
 *
 * A day holds as many of these as somebody needs: out to a client at eleven,
 * back at three, out again at five.
 */
class AttendanceSession extends Model
{
    protected $fillable = [
        'attendance_id', 'employee_id', 'started_at', 'ended_at', 'auto_closed_at', 'duration_minutes',
        'check_in_ip', 'check_in_location', 'check_in_latitude', 'check_in_longitude', 'check_in_accuracy',
        'check_in_distance_metres', 'check_in_outside_geofence',
        'check_out_ip', 'check_out_location', 'check_out_latitude', 'check_out_longitude', 'check_out_accuracy',
        'check_out_distance_metres', 'check_out_outside_geofence',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_minutes' => 'integer',
            'auto_closed_at' => 'datetime',
            'check_in_latitude' => 'float',
            'check_in_longitude' => 'float',
            'check_out_latitude' => 'float',
            'check_out_longitude' => 'float',
            'check_in_distance_metres' => 'integer',
            'check_out_distance_metres' => 'integer',
            'check_in_outside_geofence' => 'boolean',
            'check_out_outside_geofence' => 'boolean',
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

    /** Sessions where either punch was made too far from the branch. */
    public function scopeOutsideGeofence(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('check_in_outside_geofence', true)
            ->orWhere('check_out_outside_geofence', true));
    }

    public function isOutsideGeofence(): bool
    {
        return $this->check_in_outside_geofence || $this->check_out_outside_geofence;
    }

    /** A map link for one side of the session, or null when no fix was recorded. */
    public function mapUrl(string $side = 'check_in'): ?string
    {
        return Geo::mapUrl($this->{$side.'_latitude'}, $this->{$side.'_longitude'});
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    /** Closed by the nightly job rather than by somebody punching out. */
    public function wasAutoClosed(): bool
    {
        return $this->auto_closed_at !== null;
    }

    /**
     * How long this session has run, counting up to now while it is still open
     * so a live timer has something to show.
     */
    public function minutes(?Carbon $now = null): int
    {
        if ($this->ended_at) {
            return (int) $this->duration_minutes;
        }

        return max(0, (int) $this->started_at->diffInMinutes($now ?? Carbon::now()));
    }

    /** The same figure in whole seconds, for the counter in the header. */
    public function seconds(?Carbon $now = null): int
    {
        $end = $this->ended_at ?? ($now ?? Carbon::now());

        return max(0, (int) $this->started_at->diffInSeconds($end));
    }
}
