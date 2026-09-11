<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('employees.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        if ($user->employee?->id === $employee->id) {
            return true;
        }

        if (! $user->can('employees.view')) {
            return false;
        }

        return $this->sharesScope($user, $employee);
    }

    public function create(User $user): bool
    {
        return $user->can('employees.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('employees.update') && $this->sharesScope($user, $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can('employees.delete')
            && $this->sharesScope($user, $employee)
            && $user->employee?->id !== $employee->id;
    }

    public function restore(User $user, Employee $employee): bool
    {
        return $user->can('employees.delete');
    }

    public function manageDocuments(User $user, Employee $employee): bool
    {
        return $user->can('employees.manage-documents') && $this->sharesScope($user, $employee);
    }

    public function viewSalary(User $user, Employee $employee): bool
    {
        if ($user->employee?->id === $employee->id) {
            return true;
        }

        return $user->can('employees.view-salary') && $this->sharesScope($user, $employee);
    }

    public function manageSalary(User $user, Employee $employee): bool
    {
        return $user->can('payroll.manage-structures') && $this->sharesScope($user, $employee);
    }

    /** Branch managers only act within their own branch. */
    protected function sharesScope(User $user, Employee $employee): bool
    {
        if ($user->hasOrganisationScope()) {
            return true;
        }

        $actor = $user->employee;

        if (! $actor) {
            return false;
        }

        return $actor->branch_id !== null && $actor->branch_id === $employee->branch_id;
    }
}
