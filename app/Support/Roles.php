<?php

namespace App\Support;

/**
 * Canonical role names for the HRMS. Roles are stored via spatie/laravel-permission
 * but referenced through these constants so a rename only happens in one place.
 */
final class Roles
{
    public const SUPER_ADMIN = 'super-admin';

    public const HR_MANAGER = 'hr-manager';

    public const BRANCH_MANAGER = 'branch-manager';

    public const ACCOUNTANT = 'accountant';

    public const EMPLOYEE = 'employee';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [
            self::SUPER_ADMIN,
            self::HR_MANAGER,
            self::BRANCH_MANAGER,
            self::ACCOUNTANT,
            self::EMPLOYEE,
        ];
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::SUPER_ADMIN => 'Super Admin',
            self::HR_MANAGER => 'HR Manager',
            self::BRANCH_MANAGER => 'Branch Manager',
            self::ACCOUNTANT => 'Accountant',
            self::EMPLOYEE => 'Employee',
        ];
    }

    public static function label(string $role): string
    {
        return self::labels()[$role] ?? str($role)->replace('-', ' ')->title()->toString();
    }

    /** Roles that may see data outside their own branch. */
    public static function organisationWide(): array
    {
        return [self::SUPER_ADMIN, self::HR_MANAGER, self::ACCOUNTANT];
    }
}
