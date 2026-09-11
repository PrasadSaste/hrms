<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case HalfDay = 'half_day';
    case OnLeave = 'on_leave';
    case Holiday = 'holiday';
    case Weekend = 'weekend';

    /**
     * A working day in the past that nobody recorded.
     *
     * Computed rather than stored: an attendance row only exists once somebody
     * has been marked, so this never reaches the database. Whether it costs
     * anybody a day's pay is the "treat unmarked days as absent" setting's
     * business, not this enum's.
     */
    case NotMarked = 'not_marked';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Late => 'Late',
            self::HalfDay => 'Half Day',
            self::OnLeave => 'On Leave',
            self::Holiday => 'Holiday',
            self::Weekend => 'Weekend',
            self::NotMarked => 'Not marked',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'emerald',
            self::Absent => 'rose',
            self::Late => 'amber',
            self::HalfDay => 'orange',
            self::OnLeave => 'sky',
            self::Holiday => 'violet',
            self::Weekend => 'slate',
            self::NotMarked => 'slate',
        };
    }

    /** Statuses that count as a paid working day. */
    public function isPayable(): bool
    {
        return in_array($this, [self::Present, self::Late, self::Holiday, self::Weekend], true);
    }

    /**
     * The statuses somebody can choose when recording a day by hand.
     *
     * "Not marked" is what the absence of a record looks like, so it is not
     * something anybody picks.
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->reject(fn (self $c) => $c === self::NotMarked)
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
