<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_catalogued_permission_exists_after_seeding(): void
    {
        $this->seedReferenceData();

        $this->assertSame(
            count(Permissions::all()),
            Permission::count(),
            'The permissions table should mirror the catalogue exactly.',
        );
    }

    public function test_all_five_roles_are_created(): void
    {
        $this->seedReferenceData();

        foreach (Roles::all() as $role) {
            $this->assertNotNull(Role::where('name', $role)->first(), "Role {$role} is missing.");
        }
    }

    public function test_a_super_admin_holds_every_permission(): void
    {
        $employee = $this->makeEmployee(Roles::SUPER_ADMIN);

        foreach (Permissions::all() as $permission) {
            $this->assertTrue(
                $employee->user->can($permission),
                "A super admin should hold {$permission}.",
            );
        }
    }

    public function test_an_employee_cannot_reach_administration_screens(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        foreach (['/users', '/roles', '/settings', '/branches', '/payroll', '/salary-components'] as $path) {
            $this->actingAs($employee->user)->get($path)->assertForbidden();
        }
    }

    public function test_an_employee_can_reach_their_own_screens(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        foreach (['/dashboard', '/attendance', '/leave', '/leave/balance', '/payslips', '/holidays', '/profile'] as $path) {
            $this->actingAs($employee->user)->get($path)->assertOk();
        }
    }

    public function test_an_hr_manager_can_manage_employees_but_not_roles(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $this->actingAs($hr->user)->get('/employees')->assertOk();
        $this->actingAs($hr->user)->get('/employees/create')->assertOk();
        $this->actingAs($hr->user)->get('/roles')->assertOk();

        // Viewing roles is allowed; changing them is not.
        $this->assertFalse($hr->user->can('roles.manage'));
    }

    public function test_an_accountant_can_run_payroll_but_not_approve_it(): void
    {
        $accountant = $this->makeEmployee(Roles::ACCOUNTANT);

        $this->actingAs($accountant->user)->get('/payroll')->assertOk();
        $this->actingAs($accountant->user)->get('/payroll/create')->assertOk();

        $this->assertTrue($accountant->user->can('payroll.create'));
        $this->assertFalse($accountant->user->can('payroll.approve'));
    }

    public function test_a_branch_manager_only_sees_their_own_branch(): void
    {
        $branchA = $this->makeBranch(['name' => 'Alpha', 'code' => 'ALPHA']);
        $branchB = $this->makeBranch(['name' => 'Beta', 'code' => 'BETA']);

        $manager = $this->makeEmployee(Roles::BRANCH_MANAGER, ['branch' => $branchA]);
        $sameBranch = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $branchA]);
        $otherBranch = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $branchB]);

        $visible = Employee::visibleTo($manager->user)->pluck('id');

        $this->assertTrue($visible->contains($sameBranch->id), 'Own-branch colleagues should be visible.');
        $this->assertFalse($visible->contains($otherBranch->id), 'Other branches must stay hidden.');
    }

    public function test_a_branch_manager_cannot_open_an_employee_from_another_branch(): void
    {
        $branchA = $this->makeBranch(['name' => 'Alpha', 'code' => 'ALPHA']);
        $branchB = $this->makeBranch(['name' => 'Beta', 'code' => 'BETA']);

        $manager = $this->makeEmployee(Roles::BRANCH_MANAGER, ['branch' => $branchA]);
        $outsider = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $branchB]);

        $this->actingAs($manager->user)
            ->get(route('employees.show', $outsider))
            ->assertForbidden();
    }

    public function test_an_hr_manager_sees_employees_across_branches(): void
    {
        $branchA = $this->makeBranch(['name' => 'Alpha', 'code' => 'ALPHA']);
        $branchB = $this->makeBranch(['name' => 'Beta', 'code' => 'BETA']);

        $hr = $this->makeEmployee(Roles::HR_MANAGER, ['branch' => $branchA]);
        $farAway = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $branchB]);

        $this->assertTrue(
            Employee::visibleTo($hr->user)->pluck('id')->contains($farAway->id),
        );
    }

    public function test_an_employee_only_sees_themselves_in_the_directory(): void
    {
        $branch = $this->makeBranch();
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $branch]);
        $colleague = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $branch]);

        $visible = Employee::visibleTo($employee->user)->pluck('id');

        $this->assertTrue($visible->contains($employee->id));
        $this->assertFalse($visible->contains($colleague->id));
    }

    public function test_the_last_super_admin_cannot_be_stripped_of_the_role(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->put(route('users.update', $admin->user), [
                'name' => $admin->user->name,
                'email' => $admin->user->email,
                'roles' => [Roles::EMPLOYEE],
                'status' => 'active',
            ])
            ->assertSessionHasErrors('roles');

        $this->assertTrue($admin->user->fresh()->hasRole(Roles::SUPER_ADMIN));
    }

    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->delete(route('users.destroy', $admin->user))
            ->assertForbidden();
    }

    public function test_a_deactivated_user_is_blocked_mid_session(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        $this->actingAs($employee->user)->get('/dashboard')->assertOk();

        $employee->user->update(['status' => 'inactive']);

        $this->actingAs($employee->user)->get('/dashboard')->assertRedirect(route('login'));
    }
}
