<?php

namespace App\Console\Commands;

use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\AttendanceService;
use App\Services\GeofenceService;
use App\Services\NotificationDispatcher;
use App\Support\NotificationEvents;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Say whether a month is fit to be paid, before it is.
 *
 * Payroll pays whatever the record says on the day it runs. A day nobody
 * marked and a leave request nobody decided both become a figure in somebody's
 * salary, and the first anybody hears of it is usually the employee. This
 * looks at the month about to be paid and lists what would make it wrong.
 *
 * It changes nothing. The point is to be told before, not corrected after.
 */
class CheckPayrollReadiness extends AutomationCommand
{
    protected $signature = 'hrms:payroll-readiness
        {--month= : The month to check as YYYY-MM. Defaults to the one just ended.}
        {--dry-run : Show the findings without sending anything.}';

    protected $description = 'Check the month about to be paid for anything that would make it wrong';

    public function __construct(
        protected AttendanceService $attendance,
        protected NotificationDispatcher $dispatcher,
        protected GeofenceService $recipients,
    ) {
        parent::__construct();
    }

    protected function automationKey(): string
    {
        return 'payroll.readiness';
    }

    protected function work(): string
    {
        // The month just ended, because that is the one about to be paid.
        $month = $this->option('month')
            ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : Carbon::today()->subMonthNoOverflow()->startOfMonth();

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $unmarked = $this->peopleWithUnmarkedDays($start, $end);
        $pendingLeave = $this->leaveStillUndecided($start, $end);

        $clean = $unmarked->isEmpty() && $pendingLeave->isEmpty();
        $this->affected = $unmarked->count() + $pendingLeave->count();

        $verdict = $clean
            ? 'Nothing needs attention. Every working day is accounted for and no leave is undecided.'
            : sprintf(
                '%d %s with days nobody recorded, and %d leave %s still undecided.',
                $unmarked->count(),
                str('person')->plural($unmarked->count()),
                $pendingLeave->count(),
                str('request')->plural($pendingLeave->count()),
            );

        $this->line($verdict);

        if ($this->option('dry-run')) {
            $unmarked->each(fn (array $row) => $this->line('  '.$row['line']));
            $pendingLeave->each(fn (string $line) => $this->line('  '.$line));
            $this->affected = 0;

            return $verdict;
        }

        $data = [
            'period' => $month->format('F Y'),
            'verdict' => $verdict,
            'unmarked_count' => (string) $unmarked->count(),
            'pending_leave_count' => (string) $pendingLeave->count(),
            'details' => $this->details($unmarked, $pendingLeave),
            'url' => route('payroll.index'),
        ];

        $sent = 0;

        foreach ($this->recipients->recipients() as $recipient) {
            if ($this->dispatcher->toAddress(NotificationEvents::PAYROLL_READINESS, $recipient['email'], $data)) {
                $sent++;
            }
        }

        return $verdict.' Told '.$sent.' '.str('person')->plural($sent).'.';
    }

    /**
     * People with a working day in the month that nobody recorded.
     *
     * @return Collection<int, array{line: string, days: float}>
     */
    protected function peopleWithUnmarkedDays(Carbon $start, Carbon $end): Collection
    {
        return Employee::query()
            ->active()
            ->onRoll()
            ->with(['branch', 'company'])
            ->get()
            ->map(function (Employee $employee) use ($start, $end) {
                $totals = $this->attendance->summaryForPeriod($employee, $start, $end)['totals'];
                $days = (float) ($totals['unmarked_days'] ?? 0);

                return $days > 0
                    ? [
                        'days' => $days,
                        'line' => sprintf(
                            '- **%s** (%s) — %s %s nobody recorded',
                            $employee->full_name,
                            $employee->employee_code,
                            rtrim(rtrim(number_format($days, 1), '0'), '.'),
                            str('day')->plural((int) ceil($days)),
                        ),
                    ]
                    : null;
            })
            ->filter()
            ->sortByDesc('days')
            ->values();
    }

    /** @return Collection<int, string> */
    protected function leaveStillUndecided(Carbon $start, Carbon $end): Collection
    {
        return LeaveRequest::query()
            ->with(['employee', 'leaveType'])
            ->where('status', LeaveStatus::Pending)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get()
            ->map(fn (LeaveRequest $request) => sprintf(
                '- **%s** — %s, %s to %s, still undecided',
                $request->employee?->full_name ?? 'Somebody',
                $request->leaveType?->name ?? 'Leave',
                $request->start_date->format('d M'),
                $request->end_date->format('d M'),
            ));
    }

    protected function details(Collection $unmarked, Collection $pendingLeave): string
    {
        if ($unmarked->isEmpty() && $pendingLeave->isEmpty()) {
            return '_Nothing outstanding._';
        }

        $parts = [];

        if ($unmarked->isNotEmpty()) {
            $parts[] = "**Days nobody recorded**\n\n".$unmarked->pluck('line')->implode("\n");
        }

        if ($pendingLeave->isNotEmpty()) {
            $parts[] = "**Leave still undecided**\n\n".$pendingLeave->implode("\n");
        }

        return implode("\n\n", $parts);
    }
}
