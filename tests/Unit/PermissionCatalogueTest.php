<?php

namespace Tests\Unit;

use App\Support\Permissions;
use App\Support\Roles;
use PHPUnit\Framework\TestCase;

class PermissionCatalogueTest extends TestCase
{
    public function test_the_catalogue_has_no_duplicate_permissions(): void
    {
        $all = Permissions::all();

        $this->assertSame(count($all), count(array_unique($all)));
    }

    public function test_every_permission_follows_the_module_dot_action_shape(): void
    {
        foreach (Permissions::all() as $permission) {
            $this->assertMatchesRegularExpression(
                '/^[a-z]+\.[a-z-]+$/',
                $permission,
                "Permission {$permission} does not follow the module.action convention.",
            );
        }
    }

    public function test_every_role_grants_only_permissions_that_exist(): void
    {
        $catalogue = Permissions::all();

        foreach (Roles::all() as $role) {
            foreach (Permissions::forRole($role) as $permission) {
                $this->assertContains(
                    $permission,
                    $catalogue,
                    "Role {$role} grants {$permission}, which is not in the catalogue.",
                );
            }
        }
    }

    public function test_a_super_admin_is_granted_the_whole_catalogue(): void
    {
        $this->assertSame(
            count(Permissions::all()),
            count(Permissions::forRole(Roles::SUPER_ADMIN)),
        );
    }

    public function test_a_plain_employee_gets_no_administrative_permissions(): void
    {
        $granted = Permissions::forRole(Roles::EMPLOYEE);

        foreach (['users.manage', 'roles.manage', 'settings.manage', 'payroll.approve', 'employees.delete'] as $forbidden) {
            $this->assertNotContains($forbidden, $granted);
        }
    }

    public function test_an_accountant_can_run_payroll_but_not_approve_it(): void
    {
        $granted = Permissions::forRole(Roles::ACCOUNTANT);

        $this->assertContains('payroll.create', $granted);
        $this->assertContains('payslips.email', $granted);
        $this->assertNotContains('payroll.approve', $granted);
    }

    public function test_a_branch_manager_cannot_see_every_branch(): void
    {
        $granted = Permissions::forRole(Roles::BRANCH_MANAGER);

        $this->assertContains('employees.view', $granted);
        $this->assertNotContains('employees.view-any-branch', $granted);
        $this->assertNotContains('attendance.view-all', $granted);
    }

    public function test_role_labels_exist_for_every_role(): void
    {
        foreach (Roles::all() as $role) {
            $this->assertNotSame('', Roles::label($role));
        }
    }
}
