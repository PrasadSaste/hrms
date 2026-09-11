<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Models\AttendanceRegularization;
use App\Models\BackgroundCheck;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The things sitting still that somebody meant to deal with.
 *
 * Everything here was already visible on a screen. Nothing chased it, so a
 * leave request waiting on a manager who is away, or a joiner who was invited
 * to submit their documents and never did, sat there until somebody happened
 * to look. This finds them and works out who ought to hear about it.
 *
 * The cut-off is a number of days rather than a fixed date so that a request
 * made this morning is not chased this morning.
 */
class ReminderService
{
    /** Days something may sit before it is chased. */
    public const DEFAULT_AFTER_DAYS = 3;

    /**
     * Leave requests still waiting, grouped by whoever should act.
     *
     * Grouped by approver rather than sent one per request: a manager with six
     * waiting does not need six emails, and one list is easier to act on.
     *
     * @return Collection<int, array{user: User, requests: Collection}>
     */
    public function pendingLeave(int $afterDays, ?Carbon $asOf = null): Collection
    {
        $cutoff = ($asOf ?? Carbon::today())->copy()->subDays($afterDays);

        $requests = LeaveRequest::query()
            ->with(['employee.manager.user', 'employee.branch.manager.user', 'leaveType'])
            ->where('status', LeaveStatus::Pending)
            ->whereDate('applied_on', '<=', $cutoff)
            ->get();

        return $this->groupByApprover($requests, fn (LeaveRequest $r) => $r->employee);
    }

    /**
     * Attendance corrections nobody has looked at.
     *
     * @return Collection<int, array{user: User, requests: Collection}>
     */
    public function pendingRegularizations(int $afterDays, ?Carbon $asOf = null): Collection
    {
        $cutoff = ($asOf ?? Carbon::today())->copy()->subDays($afterDays);

        $requests = AttendanceRegularization::query()
            ->with(['employee.manager.user', 'employee.branch.manager.user'])
            ->where('status', LeaveStatus::Pending)
            ->where('created_at', '<=', $cutoff->copy()->endOfDay())
            ->get();

        return $this->groupByApprover($requests, fn (AttendanceRegularization $r) => $r->employee);
    }

    /**
     * Joiners invited to send their documents who never did.
     *
     * Chased to the joiner themselves, not to HR: they are the only person who
     * can do anything about it.
     *
     * @return Collection<int, BackgroundCheck>
     */
    public function unsubmittedChecks(int $afterDays, ?Carbon $asOf = null): Collection
    {
        $cutoff = ($asOf ?? Carbon::today())->copy()->subDays($afterDays);

        return BackgroundCheck::query()
            ->with(['employee.user'])
            ->where('status', BackgroundCheck::INVITED)
            ->whereNotNull('invited_at')
            ->where('invited_at', '<=', $cutoff->copy()->endOfDay())
            ->get()
            ->filter(fn (BackgroundCheck $check) => $check->employee?->user?->isActive());
    }

    /**
     * Who should hear about something waiting on an employee's record.
     *
     * Their own manager first, then the branch manager, and HR when neither is
     * available — a reminder sent nowhere is the same as no reminder.
     */
    public function approverFor(Employee $employee): ?User
    {
        foreach ([$employee->manager?->user, $employee->branch?->manager?->user] as $user) {
            if ($user?->isActive() && ! $user->is($employee->user)) {
                return $user;
            }
        }

        return User::query()
            ->active()
            ->role([Roles::HR_MANAGER, Roles::SUPER_ADMIN])
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @return Collection<int, array{user: User, requests: Collection}>
     */
    protected function groupByApprover(Collection $items, callable $employeeOf): Collection
    {
        return $items
            ->groupBy(function ($item) use ($employeeOf) {
                $employee = $employeeOf($item);

                return $employee ? ($this->approverFor($employee)?->id ?? 0) : 0;
            })
            ->filter(fn (Collection $group, $userId) => (int) $userId > 0)
            ->map(fn (Collection $group, $userId) => [
                'user' => User::find($userId),
                'requests' => $group,
            ])
            ->filter(fn (array $row) => $row['user'] !== null)
            ->values();
    }
}
