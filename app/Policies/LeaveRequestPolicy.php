<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['leave.view-own', 'leave.view-team', 'leave.view-all']);
    }

    public function view(User $user, LeaveRequest $request): bool
    {
        if ($user->employee?->id === $request->employee_id) {
            return $user->can('leave.view-own');
        }

        return $this->viewEmployee($user, $request->employee);
    }

    public function viewEmployee(User $user, ?Employee $employee): bool
    {
        if (! $employee) {
            return false;
        }

        if ($user->employee?->id === $employee->id) {
            return $user->can('leave.view-own');
        }

        if ($user->can('leave.view-all')) {
            return true;
        }

        if (! $user->can('leave.view-team')) {
            return false;
        }

        $actor = $user->employee;

        return $actor !== null
            && ($employee->reporting_to === $actor->id
                || ($actor->branch_id !== null && $actor->branch_id === $employee->branch_id));
    }

    public function create(User $user): bool
    {
        return $user->can('leave.apply') && $user->employee !== null;
    }

    public function update(User $user, LeaveRequest $request): bool
    {
        return $user->employee?->id === $request->employee_id && $request->isPending();
    }

    public function cancel(User $user, LeaveRequest $request): bool
    {
        if (! $request->canBeCancelled()) {
            return false;
        }

        return $user->employee?->id === $request->employee_id || $user->can('leave.approve');
    }

    /** An approver may not action their own request. */
    public function approve(User $user, LeaveRequest $request): bool
    {
        if (! $user->can('leave.approve') || ! $request->isPending()) {
            return false;
        }

        if ($user->employee?->id === $request->employee_id) {
            return false;
        }

        return $this->viewEmployee($user, $request->employee);
    }

    public function delete(User $user, LeaveRequest $request): bool
    {
        return $user->can('leave.view-all') && $user->can('leave.approve');
    }

    public function manageTypes(User $user): bool
    {
        return $user->can('leave.manage-types');
    }

    public function manageAllocations(User $user): bool
    {
        return $user->can('leave.manage-allocations');
    }
}
