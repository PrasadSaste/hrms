<?php

namespace App\Policies;

use App\Models\Letter;
use App\Models\User;

/**
 * Who may see a letter.
 *
 * Everybody may read their own, which is the point of issuing them through the
 * system rather than by email attachment. Reading somebody else's takes the
 * permission for it, and a branch manager stays inside their branch.
 */
class LetterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['letters.view', 'letters.view-own']);
    }

    public function view(User $user, Letter $letter): bool
    {
        if ($user->employee?->id === $letter->employee_id) {
            return $user->can('letters.view-own');
        }

        return $user->can('letters.view') && $this->sharesScope($user, $letter);
    }

    public function download(User $user, Letter $letter): bool
    {
        return $this->view($user, $letter);
    }

    public function create(User $user): bool
    {
        return $user->can('letters.issue');
    }

    /** Emailing a letter is issuing it again, so it takes the same permission. */
    public function send(User $user, Letter $letter): bool
    {
        return $user->can('letters.issue') && $this->sharesScope($user, $letter);
    }

    /**
     * A letter issued by mistake can be removed.
     *
     * Deliberately not something an employee can do to their own record.
     */
    public function delete(User $user, Letter $letter): bool
    {
        return $user->can('letters.issue') && $this->sharesScope($user, $letter);
    }

    public function manageTemplates(User $user): bool
    {
        return $user->can('letters.manage-templates');
    }

    protected function sharesScope(User $user, Letter $letter): bool
    {
        if ($user->hasOrganisationScope()) {
            return true;
        }

        $actor = $user->employee;

        if (! $actor) {
            return false;
        }

        $letter->loadMissing('employee');

        return $actor->branch_id !== null && $actor->branch_id === $letter->employee?->branch_id;
    }
}
