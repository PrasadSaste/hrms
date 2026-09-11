<?php

namespace App\Policies;

use App\Models\Payslip;
use App\Models\User;

class PayslipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['payslips.view-own', 'payslips.view-all']);
    }

    public function view(User $user, Payslip $payslip): bool
    {
        if ($user->employee?->id === $payslip->employee_id) {
            // Employees only ever see published payslips.
            return $user->can('payslips.view-own') && $payslip->isPublished();
        }

        if (! $user->can('payslips.view-all')) {
            return false;
        }

        return $user->hasOrganisationScope()
            || $user->employee?->branch_id === $payslip->employee?->branch_id;
    }

    public function download(User $user, Payslip $payslip): bool
    {
        return $user->can('payslips.download') && $this->view($user, $payslip);
    }

    public function email(User $user, Payslip $payslip): bool
    {
        return $user->can('payslips.email') && $payslip->isPublished();
    }

    public function update(User $user, Payslip $payslip): bool
    {
        return $user->can('payroll.update') && $payslip->payroll->isEditable();
    }
}
