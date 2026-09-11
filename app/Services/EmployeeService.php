<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Setting;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeService
{
    public function __construct(protected LeaveService $leaveService) {}

    /**
     * Create an employee, optionally provisioning a login account and seeding
     * the current year's leave allocations.
     *
     * @return array{employee: Employee, user: ?User, password: ?string}
     */
    public function create(array $data, bool $createAccount = true, ?string $role = null): array
    {
        return DB::transaction(function () use ($data, $createAccount, $role) {
            $data['employee_code'] ??= $this->nextEmployeeCode();

            $user = null;
            $password = null;

            if ($createAccount) {
                $password = $data['password'] ?? Str::password(12, true, true, false);

                $user = User::create([
                    'name' => trim($data['first_name'].' '.($data['last_name'] ?? '')),
                    'email' => $data['email'],
                    'password' => $password,
                    'phone' => $data['phone'] ?? null,
                    'status' => 'active',
                    'must_change_password' => empty($data['password']),
                    'email_verified_at' => now(),
                ]);

                $user->syncRoles([$role ?: Roles::EMPLOYEE]);

                $data['user_id'] = $user->id;
            }

            unset($data['password'], $data['role']);

            $employee = Employee::create($data);

            $this->seedLeaveAllocations($employee);

            return [
                'employee' => $employee->fresh(['branch', 'department', 'designation', 'user']),
                'user' => $user,
                'password' => $password,
            ];
        });
    }

    public function update(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            $employee->update($data);

            if ($employee->user) {
                $employee->user->update([
                    'name' => $employee->full_name,
                    'email' => $employee->email,
                    'phone' => $employee->phone,
                    'status' => $employee->status === 'active' ? $employee->user->status : 'inactive',
                ]);
            }

            return $employee->fresh(['branch', 'department', 'designation', 'user']);
        });
    }

    /** Provision a login for an existing employee record. */
    public function provisionAccount(Employee $employee, string $role = Roles::EMPLOYEE, ?string $password = null): array
    {
        if ($employee->user) {
            return ['user' => $employee->user, 'password' => null];
        }

        $password ??= Str::password(12, true, true, false);

        $user = User::create([
            'name' => $employee->full_name,
            'email' => $employee->email,
            'password' => $password,
            'phone' => $employee->phone,
            'status' => 'active',
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$role]);
        $employee->update(['user_id' => $user->id]);

        return ['user' => $user, 'password' => $password];
    }

    /** Reset an employee's login password to a fresh temporary one. */
    public function resetPassword(User $user): string
    {
        $password = Str::password(12, true, true, false);

        $user->forceFill([
            'password' => Hash::make($password),
            'must_change_password' => true,
        ])->save();

        return $password;
    }

    /** Mark an employee as exited and disable their login. */
    public function offboard(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            $employee->update([
                'date_of_exit' => $data['date_of_exit'],
                'exit_reason' => $data['exit_reason'] ?? null,
                'employment_status' => $data['employment_status'] ?? 'resigned',
                'status' => 'inactive',
            ]);

            $employee->user?->update(['status' => 'inactive']);

            return $employee->fresh();
        });
    }

    public function seedLeaveAllocations(Employee $employee, ?int $year = null): void
    {
        $year ??= (int) date('Y');

        foreach (LeaveType::active()->get() as $type) {
            if ($type->isApplicableTo($employee)) {
                $this->leaveService->allocationFor($employee, $type, $year);
            }
        }
    }

    /** Next sequential employee code, e.g. EMP0007. */
    public function nextEmployeeCode(): string
    {
        $prefix = (string) Setting::get('employee_code_prefix', 'EMP');
        $padding = (int) Setting::get('employee_code_padding', 4);

        $last = Employee::withTrashed()
            ->where('employee_code', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('employee_code');

        $next = 1;

        if ($last && preg_match('/(\d+)$/', $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        do {
            $code = $prefix.str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
            $next++;
        } while (Employee::withTrashed()->where('employee_code', $code)->exists());

        return $code;
    }
}
