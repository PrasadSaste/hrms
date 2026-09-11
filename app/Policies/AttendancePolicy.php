<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceService;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['attendance.view-own', 'attendance.view-team', 'attendance.view-all']);
    }

    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->employee?->id === $attendance->employee_id) {
            return $user->can('attendance.view-own');
        }

        return $this->viewEmployee($user, $attendance->employee);
    }

    public function viewEmployee(User $user, ?Employee $employee): bool
    {
        if (! $employee) {
            return false;
        }

        if ($user->employee?->id === $employee->id) {
            return $user->can('attendance.view-own');
        }

        if ($user->can('attendance.view-all')) {
            return true;
        }

        if (! $user->can('attendance.view-team')) {
            return false;
        }

        $actor = $user->employee;

        return $actor !== null
            && ($employee->reporting_to === $actor->id
                || ($actor->branch_id !== null && $actor->branch_id === $employee->branch_id));
    }

    public function create(User $user): bool
    {
        return $user->can('attendance.manage');
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $user->can('attendance.manage') && $this->viewEmployee($user, $attendance->employee);
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->can('attendance.manage') && $this->viewEmployee($user, $attendance->employee);
    }

    /**
     * Whether somebody may record their own attendance.
     *
     * The organisation-wide switch is checked here rather than in the views,
     * so turning self-punching off closes the API and any other way in at the
     * same moment it hides the buttons.
     */
    public function punch(User $user): bool
    {
        return $user->can('attendance.punch')
            && $user->employee !== null
            && AttendanceService::selfPunchAllowed();
    }

    public function approveRegularization(User $user): bool
    {
        return $user->can('attendance.approve-regularization');
    }
}
