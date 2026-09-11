<?php

namespace Tests;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use App\Support\Roles;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SalaryComponentSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    protected bool $rolesSeeded = false;

    /** Roles, permissions, leave types and salary components. */
    protected function seedReferenceData(): void
    {
        if ($this->rolesSeeded) {
            return;
        }

        $this->seed([
            RolePermissionSeeder::class,
            SettingSeeder::class,
            LeaveTypeSeeder::class,
            SalaryComponentSeeder::class,
        ]);

        $this->rolesSeeded = true;
    }

    /** The payroll entity test employees belong to. */
    protected function defaultCompany(): Company
    {
        return Company::firstOrCreate(
            ['code' => 'TESTCO'],
            [
                'name' => 'Test Company',
                'legal_name' => 'Test Company Private Limited',
                'currency' => 'INR',
                'payslip_prefix' => 'TC',
                'status' => 'active',
                'is_default' => true,
            ],
        );
    }

    protected function makeBranch(array $attributes = []): Branch
    {
        static $n = 0;
        $n++;

        return Branch::create(array_merge([
            'name' => "Branch {$n}",
            'code' => "BR{$n}",
            'country' => 'India',
            'timezone' => 'UTC',
            'work_start_time' => '09:30:00',
            'work_end_time' => '18:30:00',
            'working_days' => [1, 2, 3, 4, 5],
            'status' => 'active',
        ], $attributes));
    }

    protected function makeShift(array $attributes = []): Shift
    {
        static $n = 0;
        $n++;

        return Shift::create(array_merge([
            'name' => "Shift {$n}",
            'code' => "SH{$n}",
            'start_time' => '09:30:00',
            'end_time' => '18:30:00',
            'grace_minutes' => 15,
            'break_minutes' => 60,
            'half_day_hours' => 4,
            'full_day_hours' => 8,
            'working_days' => [1, 2, 3, 4, 5],
            'is_default' => true,
            'status' => 'active',
        ], $attributes));
    }

    /**
     * An employee with a linked user account holding the given role.
     */
    protected function makeEmployee(string $role = Roles::EMPLOYEE, array $attributes = []): Employee
    {
        static $n = 0;
        $n++;

        $this->seedReferenceData();

        $branch = $attributes['branch'] ?? $this->makeBranch();
        unset($attributes['branch']);

        $department = Department::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'ENG'],
            ['name' => 'Engineering', 'status' => 'active'],
        );

        $designation = Designation::firstOrCreate(
            ['code' => 'SE'],
            ['name' => 'Software Engineer', 'level' => 4, 'status' => 'active'],
        );

        $shift = Shift::query()->where('is_default', true)->first() ?? $this->makeShift();

        $user = User::create([
            'name' => "Test Person {$n}",
            'email' => "person{$n}@example.test",
            'password' => 'Password123!',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$role]);

        return Employee::create(array_merge([
            'user_id' => $user->id,
            'company_id' => $this->defaultCompany()->id,
            'employee_code' => sprintf('EMP%04d', $n),
            'first_name' => 'Test',
            'last_name' => "Person {$n}",
            'email' => "person{$n}@example.test",
            'gender' => 'female',
            'date_of_birth' => Carbon::parse('1992-05-14'),
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'shift_id' => $shift->id,
            'employment_type' => 'full_time',
            'employment_status' => 'permanent',
            'date_of_joining' => Carbon::today()->subYears(3)->startOfYear(),
            'notice_period_days' => 30,
            'status' => 'active',
        ], $attributes));
    }
}
