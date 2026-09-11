<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\GeofenceService;
use App\Support\Geo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The evening report of punches made away from the branch.
 *
 * Run by the scheduler once a day, and safe to run by hand for any past date
 * when somebody asks what happened last Tuesday.
 */
class SendAttendanceLocationReport extends AutomationCommand
{
    protected $signature = 'hrms:location-report
        {--date= : The day to report on (Y-m-d). Defaults to today.}
        {--dry-run : Build the report and print it without sending anything.}';

    protected $description = 'Email the day’s out-of-range attendance punches to the nominated recipients';

    protected function automationKey(): string
    {
        return 'attendance.location-report';
    }

    protected function work(): string
    {
        $geofence = app(GeofenceService::class);
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        if (! $geofence->enabled()) {
            return 'Location checking is switched off under Settings, Attendance. Nothing to report.';
        }

        $rows = $geofence->flaggedPunches($date);
        $data = $this->reportData($date, $rows, $geofence);

        if ($this->option('dry-run')) {
            $this->line($data['summary']);
            $this->newLine();
            $this->line($data['report_table']);

            return $data['summary'];
        }

        if (! (bool) Setting::get('attendance_geofence_daily_report', true)) {
            return 'The daily report is switched off under Settings, Attendance.';
        }

        $sent = $geofence->sendDailyReport($data);
        $this->affected = $rows->count();

        return sprintf(
            '%d flagged %s, report sent to %d %s.',
            $rows->count(),
            $rows->count() === 1 ? 'punch' : 'punches',
            $sent,
            $sent === 1 ? 'recipient' : 'recipients',
        );
    }

    /**
     * The placeholder values the report template expects.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, string>
     */
    protected function reportData(Carbon $date, Collection $rows, GeofenceService $geofence): array
    {
        $people = $rows->pluck('employee.id')->filter()->unique()->count();

        return [
            'date' => $date->format('d M Y'),
            'punch_count' => (string) $rows->count(),
            'employee_count' => (string) $people,
            'summary' => $this->summary($rows->count(), $people, $geofence),
            'report_table' => $this->markdownTable($rows),
            'url' => route('attendance.location-alerts', ['date' => $date->toDateString()]),
        ];
    }

    protected function summary(int $punches, int $people, GeofenceService $geofence): string
    {
        if ($punches === 0) {
            $summary = 'No punches were made outside the allowed radius today.';
        } else {
            $summary = sprintf(
                '**%d %s** %s made outside the allowed radius today, by **%d %s**.',
                $punches,
                $punches === 1 ? 'punch' : 'punches',
                $punches === 1 ? 'was' : 'were',
                $people,
                $people === 1 ? 'person' : 'people',
            );
        }

        // A branch nobody has placed on the map is never checked, which is
        // worth saying in the same breath as "nothing to report".
        $unplaced = $geofence->branchesWithoutCoordinates();

        if ($unplaced->isNotEmpty()) {
            $summary .= sprintf(
                "\n\n_%s no coordinates set, so punches there are never checked: %s._",
                $unplaced->count() === 1 ? 'One branch has' : $unplaced->count().' branches have',
                $unplaced->pluck('name')->implode(', '),
            );
        }

        return $summary;
    }

    /**
     * The flagged punches as a markdown table.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    protected function markdownTable(Collection $rows): string
    {
        if ($rows->isEmpty()) {
            return '';
        }

        $lines = [
            '| Employee | Branch | Punch | Time | Distance | Allowed |',
            '| --- | --- | --- | --- | --- | --- |',
        ];

        foreach ($rows as $row) {
            $lines[] = sprintf(
                '| %s (%s) | %s | %s | %s | %s | %s |',
                $row['employee']?->full_name ?? '—',
                $row['employee']?->employee_code ?? '—',
                $row['branch']?->name ?? '—',
                $row['punch_label'],
                $row['at']?->format('h:i A') ?? '—',
                $row['distance'],
                Geo::describeDistance($row['allowed_radius']),
            );
        }

        return implode("\n", $lines);
    }
}
