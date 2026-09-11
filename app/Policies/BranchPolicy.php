<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        if (! $user->can('branches.view')) {
            return false;
        }

        return $user->hasOrganisationScope() || $user->employee?->branch_id === $branch->id;
    }

    public function create(User $user): bool
    {
        return $user->can('branches.create');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can('branches.update');
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->can('branches.delete') && $branch->employees()->count() === 0;
    }
}
