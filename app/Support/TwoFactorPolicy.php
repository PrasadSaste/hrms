<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * Who has to use a second factor.
 *
 * Available to everybody, required of nobody, until an administrator says
 * otherwise — an HRMS holds salaries and bank details, so requiring it of the
 * people who can see those is reasonable, and requiring it of a factory hand
 * who punches in from a shared terminal is not.
 *
 * Requirement is by **role**, because that is how this system already decides
 * who sees what. A role named here means everybody holding it must enrol
 * before they can use anything else.
 *
 * Nobody is ever refused for not having enrolled: they are sent to the
 * enrolment screen. A security control that locks the payroll clerk out on the
 * afternoon of the 30th is a control that gets switched off.
 */
class TwoFactorPolicy
{
    public const SETTING = 'security_two_factor_required_roles';

    /**
     * The roles that must enrol.
     *
     * @return array<int, string>
     */
    public static function requiredRoles(): array
    {
        $stored = (string) Setting::get(self::SETTING, '');

        return collect(explode(',', $stored))
            ->map(fn ($role) => trim($role))
            ->filter()
            ->intersect(Roles::all())
            ->values()
            ->all();
    }

    public static function requiredFor(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $required = self::requiredRoles();

        return $required !== [] && $user->hasAnyRole($required);
    }

    /** Whether anybody at all is required to use it. */
    public static function enforced(): bool
    {
        return self::requiredRoles() !== [];
    }
}
