<?php

namespace App\Models;

use App\Support\Geo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'code', 'email', 'phone',
        'address_line1', 'address_line2', 'city', 'state', 'country', 'postal_code',
        'latitude', 'longitude', 'geofence_radius_metres',
        'timezone', 'manager_id', 'work_start_time', 'work_end_time', 'working_days',
        'saturday_offs', 'is_head_office', 'status',
    ];

    protected function casts(): array
    {
        return [
            'working_days' => 'array',
            'saturday_offs' => 'array',
            'is_head_office' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'geofence_radius_metres' => 'integer',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function fullAddress(): string
    {
        return collect([
            $this->address_line1, $this->address_line2,
            $this->city, $this->state, $this->postal_code, $this->country,
        ])->filter()->implode(', ');
    }

    /** Day-of-week numbers (1=Mon .. 7=Sun) considered working days. */
    public function workingDays(): array
    {
        return $this->working_days ?: [1, 2, 3, 4, 5];
    }

    /**
     * Which Saturdays of the month are off, as ordinals: [1, 3] is the first
     * and third Saturday.
     *
     * Only meaningful while Saturday is a working day at all — a branch that
     * never works Saturdays says so in its working days instead.
     *
     * @return array<int, int>
     */
    public function saturdayOffWeeks(): array
    {
        if (! in_array(6, $this->workingDays(), true)) {
            return [];
        }

        return collect($this->saturday_offs ?? [])
            ->map(fn ($week) => (int) $week)
            ->filter(fn (int $week) => $week >= 1 && $week <= 5)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** How the working week reads on screen. */
    public function saturdayLabel(): string
    {
        if (! in_array(6, $this->workingDays(), true)) {
            return 'Saturdays are off';
        }

        $weeks = $this->saturdayOffWeeks();

        if ($weeks === []) {
            return 'Every Saturday is worked';
        }

        $ordinals = collect($weeks)->map(fn (int $week) => match ($week) {
            1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', default => '5th',
        });

        return $ordinals->join(', ', ' and ').' Saturday off, the rest worked';
    }

    /** Whether this branch has been placed on the map. */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** A map link for the branch itself, or null when it has no coordinates. */
    public function mapUrl(): ?string
    {
        return Geo::mapUrl($this->latitude, $this->longitude);
    }

    /**
     * How far from here a punch may be made.
     *
     * An empty radius on the branch means the organisation-wide default,
     * so widening one campus does not loosen the rule everywhere.
     */
    public function geofenceRadius(): int
    {
        return $this->geofence_radius_metres
            ?: (int) Setting::get('attendance_geofence_radius', 200);
    }
}
