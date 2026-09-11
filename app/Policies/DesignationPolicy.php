<?php

namespace App\Policies;

use App\Models\Designation;
use App\Models\User;

class DesignationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('designations.view');
    }

    public function view(User $user, Designation $designation): bool
    {
        return $user->can('designations.view');
    }

    public function create(User $user): bool
    {
        return $user->can('designations.create');
    }

    public function update(User $user, Designation $designation): bool
    {
        return $user->can('designations.update');
    }

    public function delete(User $user, Designation $designation): bool
    {
        return $user->can('designations.delete') && $designation->employees()->count() === 0;
    }
}
