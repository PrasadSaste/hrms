<?php

namespace App\Policies;

use App\Models\Payroll;
use App\Models\User;

class PayrollPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payroll.view');
    }

    public function view(User $user, Payroll $payroll): bool
    {
        if (! $user->can('payroll.view')) {
            return false;
        }

        return $user->hasOrganisationScope()
            || $payroll->branch_id === null
            || $payroll->branch_id === $user->employee?->branch_id;
    }

    public function create(User $user): bool
    {
        return $user->can('payroll.create');
    }

    public function update(User $user, Payroll $payroll): bool
    {
        return $user->can('payroll.update') && $payroll->isEditable();
    }

    public function generate(User $user, Payroll $payroll): bool
    {
        return $user->can('payroll.create') && $payroll->isEditable();
    }

    public function approve(User $user, Payroll $payroll): bool
    {
        return $user->can('payroll.approve')
            && in_array($payroll->status->value, ['draft', 'pending_approval'], true)
            && $payroll->payslips()->exists();
    }

    public function markPaid(User $user, Payroll $payroll): bool
    {
        return $user->can('payroll.mark-paid') && $payroll->status->value === 'approved';
    }

    /**
     * A bank file is an instrument, not a report: it exists to move money.
     * So it wants its own permission, and it is only offered once somebody
     * has approved the run — and still afterwards, because the bank rejects
     * files and the second attempt is on a run already marked paid.
     */
    public function bankFile(User $user, Payroll $payroll): bool
    {
        return $user->can('payroll.bank-file')
            && in_array($payroll->status->value, ['approved', 'paid'], true);
    }

    public function delete(User $user, Payroll $payroll): bool
    {
        return $user->can('payroll.delete') && $payroll->isEditable();
    }

    public function manageComponents(User $user): bool
    {
        return $user->can('payroll.manage-components');
    }

    public function manageStructures(User $user): bool
    {
        return $user->can('payroll.manage-structures');
    }
}
