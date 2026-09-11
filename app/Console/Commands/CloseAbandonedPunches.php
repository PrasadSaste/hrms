<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Services\AttendanceService;
use App\Services\NotificationDispatcher;
use App\Support\NotificationEvents;
use Illuminate\Support\Carbon;

/**
 * Close the punches nobody ever closed, and tell the person.
 *
 * Somebody punches in, closes the browser and forgets. The punch itself is
 * safe — it is a row, not a browser session — and their pay is safe, because
 * payroll counts a day present on the check-in. What is lost is the hours: a
 * session's duration is only written when it closes, so the day reports nought
 * and the card still reads "still working" a fortnight later.
 *
 * This closes it at the end of their shift, marks the day as needing a
 * correction, and writes to the employee in the morning. It deliberately does
 * **not** try to be right — only the person knows when they left. It tries to
 * be *visible*, so a wrong number is one somebody is asked to fix rather than
 * a zero nobody notices until payday.
 *
 * Safe to run twice: a session that has been closed is no longer open.
 */
class CloseAbandonedPunches extends AutomationCommand
{
    protected $signature = 'hrms:close-abandoned-punches
        {--before= : Treat this date as today (Y-m-d), closing anything before it.}
        {--dry-run : List what would be closed without writing anything.}';

    protected $description = 'Close punches left open on a past day and ask the employee to correct them';

    public function __construct(
        protected AttendanceService $attendance,
        protected NotificationDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    protected function automationKey(): string
    {
        return 'attendance.close-abandoned-punches';
    }

    protected function work(): string
    {
        if (! $this->attendance->autoCloseEnabled()) {
            return 'Closing forgotten punches is switched off under Attendance settings.';
        }

        $before = $this->option('before') ? Carbon::parse($this->option('before')) : Carbon::today();

        if ($this->option('dry-run')) {
            $open = AttendanceSession::query()
                ->whereNull('ended_at')
                ->whereHas('attendance', fn ($q) => $q->whereDate('date', '<', $before->toDateString()))
                ->with('employee')
                ->get();

            foreach ($open as $session) {
                $this->line('  '.($session->employee?->employee_code ?? '?').'  '
                    .$session->started_at->format('d M Y H:i').'  still open');
            }

            return $open->count().' would be closed.';
        }

        $closed = $this->attendance->closeAbandonedSessions($before);

        if ($closed === []) {
            return 'No punches were left open.';
        }

        foreach ($closed as $attendance) {
            $this->line('  '.($attendance->employee?->employee_code ?? '?').'  '
                .$attendance->date->format('d M Y').'  closed at '
                .$attendance->check_out?->format('H:i'));

            $this->tell($attendance);
        }

        $this->affected = count($closed);

        return 'Closed '.count($closed).' forgotten '.str('punch')->plural(count($closed)).'.';
    }

    /**
     * The employee is told, and nobody else.
     *
     * This is not a disciplinary matter and their pay has not moved — it is a
     * correction only they can make, so it goes to them and stops there.
     */
    protected function tell(Attendance $attendance): void
    {
        $employee = $attendance->employee;
        $user = $employee?->user;

        if (! $employee || ! $user) {
            return;
        }

        $minutes = (int) $attendance->worked_minutes;

        $data = [
            'employee_name' => $employee->full_name,
            'first_name' => $employee->first_name,
            'date' => $attendance->date->format('d M Y'),
            'check_in' => $attendance->check_in?->format('h:i A') ?? '—',
            'assumed_check_out' => $attendance->check_out?->format('h:i A') ?? '—',
            'hours' => intdiv($minutes, 60).'h '.($minutes % 60).'m',
            // The correction form is a dialog on the attendance screen; the
            // date opens it already filled in with the day in question.
            'url' => route('attendance.index', ['correct' => $attendance->date->toDateString()]),
        ];

        $this->dispatcher->toUser(NotificationEvents::PUNCH_NOT_CLOSED, $user, $data);
    }
}
