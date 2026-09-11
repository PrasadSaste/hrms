<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\TaxDeclaration;
use App\Models\User;

class TaxDeclarationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tax.view');
    }

    /** Your own is always yours to see; anybody else's needs the permission. */
    public function view(User $user, TaxDeclaration $declaration): bool
    {
        if ($this->owns($user, $declaration)) {
            return true;
        }

        return $user->can('tax.view') && $this->sharesScope($user, $declaration->employee);
    }

    /**
     * Only the employee edits what they declared.
     *
     * HR records what it verified, which is a different column and a different
     * act — nobody should be able to change what somebody claimed to have
     * claimed. And a declaration already handed in is closed until it is sent
     * back, so a figure cannot move under a verification in progress.
     */
    public function update(User $user, TaxDeclaration $declaration): bool
    {
        return $this->owns($user, $declaration)
            && $user->can('tax.declare')
            && $declaration->isEditable();
    }

    public function submit(User $user, TaxDeclaration $declaration): bool
    {
        return $this->update($user, $declaration) && $declaration->items()->exists();
    }

    public function verify(User $user, TaxDeclaration $declaration): bool
    {
        return $user->can('tax.verify')
            && $this->sharesScope($user, $declaration->employee)
            && ! $this->owns($user, $declaration);
    }

    protected function owns(User $user, TaxDeclaration $declaration): bool
    {
        return $user->employee?->is($declaration->employee) ?? false;
    }

    protected function sharesScope(User $user, ?Employee $employee): bool
    {
        if ($user->hasOrganisationScope()) {
            return true;
        }

        $actor = $user->employee;

        if (! $actor || ! $employee) {
            return false;
        }

        return $actor->branch_id !== null && $actor->branch_id === $employee->branch_id;
    }
}
