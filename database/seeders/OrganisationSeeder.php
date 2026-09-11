<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Holiday;
use App\Models\Shift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class OrganisationSeeder extends Seeder
{
    public function run(): void
    {
        $this->branches();
        $this->shifts();
        $this->departments();
        $this->designations();
        $this->holidays();
    }

    protected function branches(): void
    {
        $branches = [
            [
                'name' => 'Bengaluru Head Office', 'code' => 'BLR', 'email' => 'blr@beyondsure.example',
                'phone' => '+91 80 4000 1200', 'city' => 'Bengaluru', 'state' => 'Karnataka',
                'address_line1' => 'Level 6, Prestige Tower', 'address_line2' => 'Outer Ring Road',
                'postal_code' => '560103', 'is_head_office' => true,
                // Marathahalli, Outer Ring Road.
                'latitude' => 12.9591000, 'longitude' => 77.6974000,
            ],
            [
                'name' => 'Hyderabad Office', 'code' => 'HYD', 'email' => 'hyd@beyondsure.example',
                'phone' => '+91 40 4000 1300', 'city' => 'Hyderabad', 'state' => 'Telangana',
                'address_line1' => 'Block C, Cyber Gateway', 'address_line2' => 'HITEC City',
                'postal_code' => '500081',
                // HITEC City.
                'latitude' => 17.4435000, 'longitude' => 78.3772000,
            ],
            [
                'name' => 'Pune Office', 'code' => 'PNQ', 'email' => 'pnq@beyondsure.example',
                'phone' => '+91 20 4000 1400', 'city' => 'Pune', 'state' => 'Maharashtra',
                'address_line1' => 'Tower B, Magarpatta City', 'postal_code' => '411013',
                // Magarpatta City. A larger campus, so a wider radius.
                'latitude' => 18.5158000, 'longitude' => 73.9330000,
                'geofence_radius_metres' => 500,
            ],
        ];

        foreach ($branches as $branch) {
            Branch::updateOrCreate(['code' => $branch['code']], $branch + [
                'country' => 'India',
                'timezone' => 'Asia/Kolkata',
                'work_start_time' => '09:30:00',
                'work_end_time' => '18:30:00',
                // A six-day week with the first and third Saturday off, which
                // is how the offices actually run.
                'working_days' => [1, 2, 3, 4, 5, 6],
                'saturday_offs' => [1, 3],
                'status' => 'active',
            ]);
        }
    }

    protected function shifts(): void
    {
        $shifts = [
            [
                'name' => 'General Shift', 'code' => 'GEN', 'start_time' => '09:30:00', 'end_time' => '18:30:00',
                'grace_minutes' => 15, 'break_minutes' => 60, 'is_default' => true,
            ],
            [
                'name' => 'Early Shift', 'code' => 'EARLY', 'start_time' => '07:00:00', 'end_time' => '16:00:00',
                'grace_minutes' => 10, 'break_minutes' => 60,
            ],
            [
                'name' => 'Night Shift', 'code' => 'NIGHT', 'start_time' => '21:00:00', 'end_time' => '06:00:00',
                'grace_minutes' => 15, 'break_minutes' => 60,
            ],
        ];

        foreach ($shifts as $shift) {
            Shift::updateOrCreate(['code' => $shift['code']], $shift + [
                'half_day_hours' => 4,
                'full_day_hours' => 8,
                // Saturday is a working day here, so the branch decides which
                // Saturdays are actually off. A shift that said Monday to
                // Friday would override the branch and the pattern would never
                // reach anybody.
                'working_days' => [1, 2, 3, 4, 5, 6],
                'status' => 'active',
            ]);
        }
    }

    protected function departments(): void
    {
        $blr = Branch::where('code', 'BLR')->first();

        $departments = [
            ['name' => 'Engineering', 'code' => 'ENG', 'description' => 'Product engineering and platform teams.'],
            ['name' => 'Human Resources', 'code' => 'HR', 'description' => 'People operations, hiring and employee experience.'],
            ['name' => 'Finance', 'code' => 'FIN', 'description' => 'Accounting, payroll and financial planning.'],
            ['name' => 'Sales', 'code' => 'SALES', 'description' => 'New business and account management.'],
            ['name' => 'Marketing', 'code' => 'MKT', 'description' => 'Brand, demand generation and communications.'],
            ['name' => 'Customer Support', 'code' => 'CS', 'description' => 'Customer success and technical support.'],
            ['name' => 'Operations', 'code' => 'OPS', 'description' => 'Facilities, IT and internal operations.'],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                ['branch_id' => $blr?->id, 'code' => $department['code']],
                $department + ['branch_id' => $blr?->id, 'status' => 'active'],
            );
        }
    }

    protected function designations(): void
    {
        $byCode = Department::pluck('id', 'code');

        $designations = [
            ['name' => 'Chief Executive Officer', 'code' => 'CEO', 'level' => 10, 'department' => null],
            ['name' => 'Vice President, Engineering', 'code' => 'VP-ENG', 'level' => 9, 'department' => 'ENG'],
            ['name' => 'Engineering Manager', 'code' => 'EM', 'level' => 7, 'department' => 'ENG'],
            ['name' => 'Principal Engineer', 'code' => 'PE', 'level' => 7, 'department' => 'ENG'],
            ['name' => 'Senior Software Engineer', 'code' => 'SSE', 'level' => 5, 'department' => 'ENG'],
            ['name' => 'Software Engineer', 'code' => 'SE', 'level' => 4, 'department' => 'ENG'],
            ['name' => 'Associate Software Engineer', 'code' => 'ASE', 'level' => 2, 'department' => 'ENG'],
            ['name' => 'QA Engineer', 'code' => 'QA', 'level' => 4, 'department' => 'ENG'],
            ['name' => 'Head of Human Resources', 'code' => 'HR-HEAD', 'level' => 8, 'department' => 'HR'],
            ['name' => 'HR Manager', 'code' => 'HR-MGR', 'level' => 6, 'department' => 'HR'],
            ['name' => 'HR Executive', 'code' => 'HR-EXEC', 'level' => 3, 'department' => 'HR'],
            ['name' => 'Finance Controller', 'code' => 'FIN-CTRL', 'level' => 8, 'department' => 'FIN'],
            ['name' => 'Senior Accountant', 'code' => 'SR-ACC', 'level' => 5, 'department' => 'FIN'],
            ['name' => 'Accountant', 'code' => 'ACC', 'level' => 3, 'department' => 'FIN'],
            ['name' => 'Sales Director', 'code' => 'SALES-DIR', 'level' => 8, 'department' => 'SALES'],
            ['name' => 'Account Executive', 'code' => 'AE', 'level' => 4, 'department' => 'SALES'],
            ['name' => 'Marketing Manager', 'code' => 'MKT-MGR', 'level' => 6, 'department' => 'MKT'],
            ['name' => 'Content Specialist', 'code' => 'CONTENT', 'level' => 3, 'department' => 'MKT'],
            ['name' => 'Support Lead', 'code' => 'CS-LEAD', 'level' => 5, 'department' => 'CS'],
            ['name' => 'Support Engineer', 'code' => 'CS-ENG', 'level' => 3, 'department' => 'CS'],
            ['name' => 'Operations Manager', 'code' => 'OPS-MGR', 'level' => 6, 'department' => 'OPS'],
            ['name' => 'Office Administrator', 'code' => 'ADMIN', 'level' => 2, 'department' => 'OPS'],
        ];

        foreach ($designations as $designation) {
            $departmentCode = $designation['department'];
            unset($designation['department']);

            Designation::updateOrCreate(
                ['code' => $designation['code']],
                $designation + [
                    'department_id' => $departmentCode ? $byCode[$departmentCode] ?? null : null,
                    'status' => 'active',
                ],
            );
        }
    }

    protected function holidays(): void
    {
        $year = (int) date('Y');

        $holidays = [
            ['name' => "New Year's Day", 'month' => 1, 'day' => 1],
            ['name' => 'Republic Day', 'month' => 1, 'day' => 26],
            ['name' => 'Holi', 'month' => 3, 'day' => 14],
            ['name' => 'Good Friday', 'month' => 4, 'day' => 18],
            ['name' => 'Labour Day', 'month' => 5, 'day' => 1],
            ['name' => 'Independence Day', 'month' => 8, 'day' => 15],
            ['name' => 'Gandhi Jayanti', 'month' => 10, 'day' => 2],
            ['name' => 'Diwali', 'month' => 10, 'day' => 20],
            ['name' => 'Diwali (Second Day)', 'month' => 10, 'day' => 21],
            ['name' => 'Christmas Day', 'month' => 12, 'day' => 25],
        ];

        foreach ([$year, $year - 1] as $holidayYear) {
            foreach ($holidays as $holiday) {
                $date = Carbon::create($holidayYear, $holiday['month'], $holiday['day']);

                Holiday::updateOrCreate(
                    ['branch_id' => null, 'name' => $holiday['name'], 'date' => $date->toDateString()],
                    ['type' => 'public', 'is_recurring' => true],
                );
            }
        }
    }
}
