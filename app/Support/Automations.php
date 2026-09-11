<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Everything the system does on its own, declared in one place.
 *
 * Before this, a scheduled job existed only in `routes/console.php`, where
 * nobody running the system could see it: what runs, when it last ran and
 * whether it worked were questions only a developer with a log could answer.
 * A job listed here is scheduled, recorded and shown on the automations screen
 * without anything else being written.
 *
 * The keys are the contract. `command` is what the scheduler calls, `time` is
 * the hour it runs unless an administrator moves it, and `frequency` says how
 * often. `time_setting` exists for the one job that had its own setting before
 * this catalogue did, so an installation that already set that hour keeps it.
 */
final class Automations
{
    public const DAILY = 'daily';

    public const MONTHLY = 'monthly';

    public const YEARLY = 'yearly';

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'leave.accrue' => [
                'label' => 'Credit monthly leave',
                'group' => 'Leave',
                'command' => 'hrms:accrue-leave',
                'frequency' => self::DAILY,
                'time' => '01:15',
                'summary' => 'Adds the month\'s earned leave to everybody on a monthly type.',
                'detail' => 'Runs daily rather than monthly on purpose: the accrual ledger '
                    .'decides what is owed, so a day the server was down is caught up the next '
                    .'morning instead of costing somebody a month.',
            ],

            'leave.allocate-year' => [
                'label' => 'Open the new leave year',
                'group' => 'Leave',
                'command' => 'hrms:allocate-leave-year',
                'frequency' => self::YEARLY,
                'time' => '02:00',
                'summary' => 'Gives everybody their allocation for the new year and carries '
                    .'forward what the type allows.',
                'detail' => 'Fires on 1 January. Until now this only happened when somebody '
                    .'remembered to press the button on Leave Allocations, and a year opened '
                    .'late is a year in which nobody can apply for leave.',
            ],

            'people.confirm-probation' => [
                'label' => 'Confirm people whose probation has ended',
                'group' => 'People',
                'command' => 'hrms:confirm-probation',
                'frequency' => self::DAILY,
                'time' => '02:15',
                'summary' => 'Moves anybody past their confirmation date off probation and '
                    .'tells HR they are due a confirmation letter.',
                'detail' => 'Somebody whose confirmation date has passed is still marked as on '
                    .'probation until this runs, and where no confirmation date was recorded at '
                    .'all they keep earning the lower probation leave rate indefinitely.',
            ],

            'people.celebrations' => [
                'label' => 'Birthdays and work anniversaries',
                'group' => 'People',
                'command' => 'hrms:celebrations',
                'frequency' => self::DAILY,
                'time' => '08:00',
                'summary' => 'Tells the team about today\'s birthdays and work anniversaries.',
                'detail' => 'These were worked out for the dashboard and then dropped. Whether '
                    .'anybody is told, and who, is a notification like any other.',
            ],

            'reminders.chase-pending' => [
                'label' => 'Chase what is waiting on somebody',
                'group' => 'Reminders',
                'command' => 'hrms:chase-pending',
                'frequency' => self::DAILY,
                'time' => '09:30',
                'summary' => 'Reminds approvers about leave and attendance corrections that '
                    .'have been sitting, and joiners about documents they have not sent.',
                'detail' => 'Anything untouched for three days is chased. One message per '
                    .'person listing everything, rather than one per request — a manager with '
                    .'six waiting does not need six emails. Pass --after to change the three '
                    .'days when running it by hand.',
            ],

            'payroll.readiness' => [
                'label' => 'Check the month is fit to be paid',
                'group' => 'Payroll',
                'command' => 'hrms:payroll-readiness',
                'frequency' => self::MONTHLY,
                'time' => '07:00',
                'summary' => 'Lists anything in the month just ended that would make the pay '
                    .'wrong: days nobody recorded, leave still undecided.',
                'detail' => 'Runs on the 1st, for the month that just finished. It changes '
                    .'nothing — payroll pays whatever the record says on the day it runs, and '
                    .'the point is to be told before rather than corrected after.',
            ],

            'attendance.close-abandoned-punches' => [
                'label' => 'Close punches nobody closed',
                'group' => 'Attendance',
                'command' => 'hrms:close-abandoned-punches',
                'frequency' => self::DAILY,
                'time' => '02:00',
                'summary' => 'Closes yesterday’s punches that were left open, at the end of the '
                    .'shift, and asks the employee to correct the time.',
                'detail' => 'A forgotten punch-out never costs anybody their salary — the day is '
                    .'counted present on the check-in — but the hours on it stay at nought until '
                    .'the session closes, and it closes only when somebody presses the button. '
                    .'This closes it at the shift’s end, never at the hour the job runs, marks '
                    .'the day as needing a correction so it reads as wrong rather than empty, '
                    .'and writes to the employee, who is the only one who knows when they left. '
                    .'Switched off until somebody turns it on under Attendance settings.',
            ],

            'attendance.location-report' => [
                'label' => 'Punches away from the branch',
                'group' => 'Attendance',
                'command' => 'hrms:location-report',
                'frequency' => self::DAILY,
                'time' => '19:30',
                // This job had its own setting before the catalogue existed.
                'time_setting' => 'attendance_geofence_report_time',
                'summary' => 'The evening report of punches made outside the branch fence.',
                'detail' => 'Sent to the people named under Attendance settings, falling back '
                    .'to HR and administrators so a report is never sent nowhere.',
            ],
        ];
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** The catalogue by group, in the order it is declared. */
    public static function grouped(): array
    {
        return collect(self::all())
            ->groupBy('group', preserveKeys: true)
            ->all();
    }

    /** The command an automation runs, or null when the key is unknown. */
    public static function command(string $key): ?string
    {
        return self::find($key)['command'] ?? null;
    }

    /** The key an automation is known by, found from the command it runs. */
    public static function keyForCommand(string $command): ?string
    {
        foreach (self::all() as $key => $definition) {
            if ($definition['command'] === $command) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Whether this one runs at all.
     *
     * Everything is on unless an administrator has turned it off, so a new
     * automation starts working without anybody being asked to switch it on.
     */
    public static function enabled(string $key): bool
    {
        if (! self::exists($key)) {
            return false;
        }

        return (bool) Setting::get(self::settingKey($key, 'enabled'), true);
    }

    /**
     * The hour it runs, as HH:MM.
     *
     * Read defensively: the scheduler is loaded by `artisan migrate` on an
     * empty database, before there is a settings table to read.
     */
    public static function time(string $key): string
    {
        $definition = self::find($key);

        if (! $definition) {
            return '00:00';
        }

        $fallback = $definition['time'];

        try {
            $stored = (string) Setting::get(
                $definition['time_setting'] ?? self::settingKey($key, 'time'),
                $fallback,
            );
        } catch (\Throwable) {
            return $fallback;
        }

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $stored) ? $stored : $fallback;
    }

    /** Where a knob for this automation is stored. */
    public static function settingKey(string $key, string $knob): string
    {
        return 'automation.'.$key.'.'.$knob;
    }

    /** How often it runs, in words. */
    public static function frequencyLabel(string $key): string
    {
        return match (self::find($key)['frequency'] ?? null) {
            self::MONTHLY => 'On the 1st of each month',
            self::YEARLY => 'On 1 January',
            self::DAILY => 'Every day',
            default => 'On demand',
        };
    }
}
