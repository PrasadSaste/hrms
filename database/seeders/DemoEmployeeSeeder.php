<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\SalaryStructure;
use App\Models\Shift;
use App\Models\User;
use App\Services\PayrollService;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Creates the demo workforce: leadership, HR, finance and delivery teams, each
 * with a login, a reporting line and an active salary structure.
 */
class DemoEmployeeSeeder extends Seeder
{
    protected array $created = [];

    public function run(): void
    {
        $people = [
            // code, first, last, gender, designation, department, branch, role, reports to, ctc, joined
            ['EMP0001', 'Aarav', 'Mehta', 'male', 'CEO', null, 'BLR', Roles::SUPER_ADMIN, null, 6000000, '2018-04-02'],
            ['EMP0002', 'Priya', 'Raghavan', 'female', 'HR-HEAD', 'HR', 'BLR', Roles::HR_MANAGER, 'EMP0001', 3600000, '2019-01-07'],
            ['EMP0003', 'Vikram', 'Desai', 'male', 'FIN-CTRL', 'FIN', 'BLR', Roles::ACCOUNTANT, 'EMP0001', 3400000, '2019-03-11'],
            ['EMP0004', 'Ananya', 'Iyer', 'female', 'VP-ENG', 'ENG', 'BLR', Roles::BRANCH_MANAGER, 'EMP0001', 4200000, '2019-06-03'],

            ['EMP0005', 'Rohan', 'Kulkarni', 'male', 'EM', 'ENG', 'BLR', Roles::BRANCH_MANAGER, 'EMP0004', 2800000, '2020-02-10'],
            ['EMP0006', 'Meera', 'Nair', 'female', 'PE', 'ENG', 'BLR', Roles::EMPLOYEE, 'EMP0004', 2600000, '2020-07-20'],
            ['EMP0007', 'Karthik', 'Subramanian', 'male', 'SSE', 'ENG', 'BLR', Roles::EMPLOYEE, 'EMP0005', 1800000, '2021-01-18'],
            ['EMP0008', 'Sneha', 'Patil', 'female', 'SSE', 'ENG', 'BLR', Roles::EMPLOYEE, 'EMP0005', 1750000, '2021-05-24'],
            ['EMP0009', 'Arjun', 'Sharma', 'male', 'SE', 'ENG', 'BLR', Roles::EMPLOYEE, 'EMP0005', 1200000, '2022-03-14'],
            ['EMP0010', 'Divya', 'Krishnan', 'female', 'SE', 'ENG', 'HYD', Roles::EMPLOYEE, 'EMP0005', 1150000, '2022-08-01'],
            ['EMP0011', 'Rahul', 'Verma', 'male', 'ASE', 'ENG', 'HYD', Roles::EMPLOYEE, 'EMP0007', 700000, '2024-07-15'],
            ['EMP0012', 'Ishita', 'Bose', 'female', 'QA', 'ENG', 'BLR', Roles::EMPLOYEE, 'EMP0005', 1050000, '2022-11-07'],

            ['EMP0013', 'Neha', 'Gupta', 'female', 'HR-MGR', 'HR', 'BLR', Roles::HR_MANAGER, 'EMP0002', 1600000, '2021-02-15'],
            ['EMP0014', 'Sanjay', 'Rao', 'male', 'HR-EXEC', 'HR', 'HYD', Roles::EMPLOYEE, 'EMP0013', 750000, '2023-04-03'],

            ['EMP0015', 'Pooja', 'Shetty', 'female', 'SR-ACC', 'FIN', 'BLR', Roles::ACCOUNTANT, 'EMP0003', 1300000, '2021-09-06'],
            ['EMP0016', 'Amit', 'Joshi', 'male', 'ACC', 'FIN', 'PNQ', Roles::EMPLOYEE, 'EMP0015', 800000, '2023-01-09'],

            ['EMP0017', 'Ravi', 'Menon', 'male', 'SALES-DIR', 'SALES', 'BLR', Roles::BRANCH_MANAGER, 'EMP0001', 3000000, '2020-01-06'],
            ['EMP0018', 'Kavya', 'Reddy', 'female', 'AE', 'SALES', 'HYD', Roles::EMPLOYEE, 'EMP0017', 1100000, '2022-06-13'],
            ['EMP0019', 'Nikhil', 'Agarwal', 'male', 'AE', 'SALES', 'PNQ', Roles::EMPLOYEE, 'EMP0017', 1050000, '2023-02-20'],

            ['EMP0020', 'Tanvi', 'Malhotra', 'female', 'MKT-MGR', 'MKT', 'BLR', Roles::EMPLOYEE, 'EMP0001', 1500000, '2021-11-08'],
            ['EMP0021', 'Aditya', 'Pillai', 'male', 'CONTENT', 'MKT', 'BLR', Roles::EMPLOYEE, 'EMP0020', 700000, '2023-08-21'],

            ['EMP0022', 'Shreya', 'Ghosh', 'female', 'CS-LEAD', 'CS', 'HYD', Roles::BRANCH_MANAGER, 'EMP0001', 1400000, '2021-07-12'],
            ['EMP0023', 'Manish', 'Tiwari', 'male', 'CS-ENG', 'CS', 'HYD', Roles::EMPLOYEE, 'EMP0022', 650000, '2023-10-02'],
            ['EMP0024', 'Farah', 'Khan', 'female', 'CS-ENG', 'CS', 'PNQ', Roles::EMPLOYEE, 'EMP0022', 640000, '2024-01-15'],

            ['EMP0025', 'Deepak', 'Chandra', 'male', 'OPS-MGR', 'OPS', 'BLR', Roles::EMPLOYEE, 'EMP0001', 1250000, '2020-09-14'],
            ['EMP0026', 'Lakshmi', 'Venkatesh', 'female', 'ADMIN', 'OPS', 'BLR', Roles::EMPLOYEE, 'EMP0025', 480000, '2023-06-05'],
        ];

        $branches = Branch::pluck('id', 'code');
        $departments = Department::pluck('id', 'code');
        $designations = Designation::pluck('id', 'code');
        $defaultShift = Shift::where('code', 'GEN')->value('id');

        foreach ($people as $index => $person) {
            [$code, $first, $last, $gender, $designation, $department, $branch, $role, $manager, $ctc, $joined] = $person;

            $email = strtolower($first.'.'.$last).'@beyondsure.example';

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $first.' '.$last,
                    'password' => 'Password123!',
                    'phone' => '+91 98'.str_pad((string) (45000000 + $index * 137), 8, '0', STR_PAD_LEFT),
                    'status' => 'active',
                    'must_change_password' => false,
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$role]);

            $employee = Employee::updateOrCreate(
                ['employee_code' => $code],
                [
                    'user_id' => $user->id,
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $email,
                    'personal_email' => strtolower($first.$last).'@example.com',
                    'phone' => $user->phone,
                    'gender' => $gender,
                    'date_of_birth' => Carbon::parse($joined)->subYears(26 + ($index % 12))->subDays($index * 11),
                    'marital_status' => $index % 3 === 0 ? 'married' : 'single',
                    'blood_group' => ['A+', 'B+', 'O+', 'AB+', 'O-'][$index % 5],
                    'nationality' => 'Indian',
                    'address_line1' => (100 + $index).', 4th Cross',
                    'city' => match ($branch) {
                        'BLR' => 'Bengaluru', 'HYD' => 'Hyderabad', default => 'Pune'
                    },
                    'state' => match ($branch) {
                        'BLR' => 'Karnataka', 'HYD' => 'Telangana', default => 'Maharashtra'
                    },
                    'country' => 'India',
                    'postal_code' => match ($branch) {
                        'BLR' => '560103', 'HYD' => '500081', default => '411013'
                    },
                    'emergency_contact_name' => 'Emergency contact for '.$first,
                    'emergency_contact_phone' => '+91 99'.str_pad((string) (10000000 + $index * 971), 8, '0', STR_PAD_LEFT),
                    'emergency_contact_relation' => $index % 2 === 0 ? 'Spouse' : 'Parent',
                    'branch_id' => $branches[$branch] ?? null,
                    'department_id' => $department ? ($departments[$department] ?? null) : null,
                    'designation_id' => $designations[$designation] ?? null,
                    'shift_id' => $defaultShift,
                    'employment_type' => 'full_time',
                    'employment_status' => Carbon::parse($joined)->lt(now()->subMonths(6)) ? 'permanent' : 'probation',
                    'date_of_joining' => $joined,
                    'date_of_confirmation' => Carbon::parse($joined)->addMonths(6)->lte(now())
                        ? Carbon::parse($joined)->addMonths(6)->toDateString()
                        : null,
                    'notice_period_days' => 60,
                    'bank_name' => 'HDFC Bank',
                    'bank_account_name' => $first.' '.$last,
                    'bank_account_number' => '5011'.str_pad((string) (100000 + $index * 733), 8, '0', STR_PAD_LEFT),
                    'bank_ifsc' => 'HDFC0001234',
                    'bank_branch' => 'Koramangala',
                    'pan_number' => 'ABCPD'.str_pad((string) (1000 + $index), 4, '0', STR_PAD_LEFT).'K',
                    'pf_number' => 'KN/BNG/'.str_pad((string) (20000 + $index), 7, '0', STR_PAD_LEFT),
                    'uan_number' => '10'.str_pad((string) (10000000 + $index * 13), 10, '0', STR_PAD_LEFT),
                    'status' => 'active',
                ],
            );

            $this->created[$code] = ['employee' => $employee, 'manager' => $manager, 'ctc' => $ctc];
        }

        $this->linkManagers();
        $this->assignBranchManagers();
        $this->createSalaryStructures();
    }

    protected function linkManagers(): void
    {
        foreach ($this->created as $code => $row) {
            if ($row['manager'] && isset($this->created[$row['manager']])) {
                $row['employee']->update([
                    'reporting_to' => $this->created[$row['manager']]['employee']->id,
                ]);
            }
        }
    }

    protected function assignBranchManagers(): void
    {
        $map = ['BLR' => 'EMP0004', 'HYD' => 'EMP0022', 'PNQ' => 'EMP0017'];

        foreach ($map as $branchCode => $employeeCode) {
            if (isset($this->created[$employeeCode])) {
                Branch::where('code', $branchCode)->update([
                    'manager_id' => $this->created[$employeeCode]['employee']->id,
                ]);
            }
        }

        $heads = ['ENG' => 'EMP0004', 'HR' => 'EMP0002', 'FIN' => 'EMP0003', 'SALES' => 'EMP0017',
            'MKT' => 'EMP0020', 'CS' => 'EMP0022', 'OPS' => 'EMP0025'];

        foreach ($heads as $departmentCode => $employeeCode) {
            if (isset($this->created[$employeeCode])) {
                Department::where('code', $departmentCode)->update([
                    'head_id' => $this->created[$employeeCode]['employee']->id,
                ]);
            }
        }
    }

    protected function createSalaryStructures(): void
    {
        $payroll = app(PayrollService::class);

        foreach ($this->created as $row) {
            $employee = $row['employee'];
            $ctc = $row['ctc'];

            $structure = SalaryStructure::updateOrCreate(
                ['employee_id' => $employee->id, 'effective_from' => $employee->date_of_joining->toDateString()],
                [
                    'ctc_annual' => $ctc,
                    'basic_salary' => round($ctc / 12 * 0.4, 2),
                    'currency' => 'INR',
                    'payment_mode' => 'bank_transfer',
                    'status' => 'active',
                    'notes' => 'Seeded demo structure.',
                ],
            );

            $payroll->applyDefaultComponents($structure);
        }
    }
}
