<?php

namespace App\Support;

use App\Services\Import\AttendanceImporter;
use App\Services\Import\BranchImporter;
use App\Services\Import\DepartmentImporter;
use App\Services\Import\DesignationImporter;
use App\Services\Import\EmployeeImporter;
use App\Services\Import\LeaveBalanceImporter;
use App\Services\Import\SalaryStructureImporter;

/**
 * Everything that can be loaded from a spreadsheet, and what each column means.
 *
 * Like the other catalogues, this is the single list the interface renders: the
 * cards on the import screen, the downloadable template, the column reference
 * beside it and the validation all read from here. Adding a type is a class and
 * an entry, with no further wiring.
 *
 * The order is the order to load in. Nothing further down the list can be
 * loaded before the things it points at exist — an employee needs their branch,
 * a leave balance needs the employee.
 *
 * Each entry declares:
 *   label        what it is called
 *   summary      one line for the card
 *   description  what it does and when to use it
 *   importer     the class that validates and applies a row
 *   key_column   the column that decides create-or-update
 *   columns      name => [required, help, example]
 */
final class ImportTypes
{
    public const BRANCHES = 'branches';

    public const DEPARTMENTS = 'departments';

    public const DESIGNATIONS = 'designations';

    public const EMPLOYEES = 'employees';

    public const LEAVE_BALANCES = 'leave-balances';

    public const SALARY_STRUCTURES = 'salary-structures';

    public const ATTENDANCE = 'attendance';

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            self::BRANCHES => [
                'label' => 'Branches',
                'summary' => 'The offices and sites people work at.',
                'description' => 'Load these first: an employee row names its branch by code, so '
                    .'the branches have to exist before anybody can point at one.',
                'importer' => BranchImporter::class,
                'key_column' => 'code',
                'columns' => [
                    'code' => [true, 'A short unique code, e.g. BLR. This is what other sheets point at.', 'BLR'],
                    'name' => [true, 'The branch name.', 'Bengaluru Head Office'],
                    'email' => [false, '', 'blr@example.com'],
                    'phone' => [false, '', '+91 80 4000 1200'],
                    'address_line1' => [false, '', 'Level 6, Prestige Tower'],
                    'address_line2' => [false, '', 'Outer Ring Road'],
                    'city' => [false, '', 'Bengaluru'],
                    'state' => [false, '', 'Karnataka'],
                    'country' => [false, 'Defaults to India.', 'India'],
                    'postal_code' => [false, '', '560103'],
                    'latitude' => [false, 'For the location check on punches. Leave both empty and this branch is never checked.', '12.9591'],
                    'longitude' => [false, '', '77.6974'],
                    'geofence_radius_metres' => [false, 'Empty uses the organisation-wide default.', '200'],
                    'timezone' => [false, 'Defaults to the application timezone.', 'Asia/Kolkata'],
                    'work_start_time' => [false, '24-hour. Defaults to 09:30.', '09:30'],
                    'work_end_time' => [false, '24-hour. Defaults to 18:30.', '18:30'],
                    'working_days' => [false, 'Day numbers or names, e.g. "1,2,3,4,5" or "Mon-Fri". Defaults to Monday to Friday.', 'Mon-Fri'],
                    'saturday_offs' => [false, 'Which Saturdays of the month are off, when Saturday is worked at all. "1,3" is the usual alternate-Saturday week.', '1,3'],
                    'is_head_office' => [false, 'yes or no.', 'yes'],
                    'status' => [false, 'active or inactive. Defaults to active.', 'active'],
                ],
            ],

            self::DEPARTMENTS => [
                'label' => 'Departments',
                'summary' => 'Departments, each belonging to a branch.',
                'description' => 'A department belongs to one branch, so the branch code has to name '
                    .'a branch that is already loaded.',
                'importer' => DepartmentImporter::class,
                'key_column' => 'code',
                'columns' => [
                    'code' => [true, 'Unique within the branch.', 'ENG'],
                    'name' => [true, '', 'Engineering'],
                    'branch_code' => [true, 'The branch this department sits in.', 'BLR'],
                    'description' => [false, '', 'Product engineering'],
                    'status' => [false, 'active or inactive. Defaults to active.', 'active'],
                ],
            ],

            self::DESIGNATIONS => [
                'label' => 'Designations',
                'summary' => 'Job titles and their seniority.',
                'description' => 'Job titles are organisation-wide rather than per branch.',
                'importer' => DesignationImporter::class,
                'key_column' => 'code',
                'columns' => [
                    'code' => [true, 'A short unique code.', 'SE'],
                    'name' => [true, '', 'Software Engineer'],
                    'level' => [false, 'A number: higher is more senior. Used for ordering.', '4'],
                    'description' => [false, '', ''],
                    'status' => [false, 'active or inactive. Defaults to active.', 'active'],
                ],
            ],

            self::EMPLOYEES => [
                'label' => 'Employees',
                'summary' => 'The people themselves, with their employment and bank details.',
                'description' => 'The main sheet. Branches, departments and designations are named by '
                    .'code and must already exist; the reporting line is resolved after every row has '
                    .'been read, so a manager may appear further down the file than their reports.',
                'importer' => EmployeeImporter::class,
                'key_column' => 'employee_code',
                'columns' => [
                    'employee_code' => [false, 'Their code in the old system. Leave empty and one is generated. Re-importing the same code updates that person rather than creating a second.', 'EMP0001'],
                    'first_name' => [true, '', 'Arjun'],
                    'last_name' => [false, '', 'Sharma'],
                    'email' => [true, 'Their work email. This is also their sign-in name, so it has to be unique.', 'arjun.sharma@example.com'],
                    'personal_email' => [false, '', ''],
                    'phone' => [false, '', '+91 98765 43210'],
                    'alternate_phone' => [false, '', ''],
                    'gender' => [false, 'male, female or other.', 'male'],
                    'date_of_birth' => [false, 'YYYY-MM-DD, or DD/MM/YYYY.', '1994-03-18'],
                    'marital_status' => [false, 'single, married, divorced or widowed.', 'single'],
                    'blood_group' => [false, '', 'O+'],
                    'nationality' => [false, '', 'Indian'],
                    'company_code' => [false, 'Which legal entity employs them, by company code. Defaults to the default company.', 'BSPL'],
                    'branch_code' => [true, 'Must match a branch already loaded.', 'BLR'],
                    'department_code' => [false, 'Must match a department in that branch.', 'ENG'],
                    'designation_code' => [false, '', 'SE'],
                    'shift_code' => [false, 'Defaults to the default shift.', 'GEN'],
                    'manager_code' => [false, 'The employee code of the person they report to. Resolved after the whole file is read.', 'EMP0004'],
                    'employment_type' => [false, 'full_time, part_time, contract, intern or consultant. Defaults to full_time.', 'full_time'],
                    'employment_status' => [false, 'probation, permanent, notice_period, resigned, terminated or retired. Defaults to permanent.', 'permanent'],
                    'date_of_joining' => [true, 'Their original joining date, not the day you migrate.', '2023-04-01'],
                    'date_of_confirmation' => [false, '', ''],
                    'date_of_exit' => [false, 'Only for people who have already left.', ''],
                    'notice_period_days' => [false, 'Defaults to 30.', '30'],
                    'address_line1' => [false, '', ''],
                    'address_line2' => [false, '', ''],
                    'city' => [false, '', 'Bengaluru'],
                    'state' => [false, '', 'Karnataka'],
                    'country' => [false, 'Defaults to India.', 'India'],
                    'postal_code' => [false, '', ''],
                    'emergency_contact_name' => [false, '', ''],
                    'emergency_contact_phone' => [false, '', ''],
                    'emergency_contact_relation' => [false, '', ''],
                    'bank_name' => [false, '', ''],
                    'bank_account_name' => [false, '', ''],
                    'bank_account_number' => [false, '', ''],
                    'bank_ifsc' => [false, '', ''],
                    'bank_branch' => [false, '', ''],
                    'pan_number' => [false, '', ''],
                    'national_id' => [false, '', ''],
                    'pf_number' => [false, '', ''],
                    'esi_number' => [false, '', ''],
                    'uan_number' => [false, '', ''],
                    'role' => [false, 'Which role their login gets: Employee, Branch Manager, HR Manager, Accountant. Defaults to Employee. Leave the column out entirely and everybody is an employee.', 'Employee'],
                    'status' => [false, 'active or inactive. Defaults to active.', 'active'],
                    'notes' => [false, '', ''],
                ],
            ],

            self::LEAVE_BALANCES => [
                'label' => 'Leave balances',
                'summary' => 'What everybody had left in the old system.',
                'description' => 'Opening balances, so nobody starts the new system on zero. One row '
                    .'per employee per leave type per year. Importing a year again replaces that '
                    .'year\'s figures rather than adding to them.',
                'importer' => LeaveBalanceImporter::class,
                'key_column' => 'employee_code',
                'columns' => [
                    'employee_code' => [true, '', 'EMP0001'],
                    'leave_type_code' => [true, 'The code of the leave type, e.g. CL, SL, EL.', 'CL'],
                    'year' => [true, 'The calendar year these figures belong to.', '2026'],
                    'allocated_days' => [true, 'The entitlement for the year.', '12'],
                    'carried_forward_days' => [false, 'Brought in from the year before. Defaults to 0.', '2'],
                    'used_days' => [false, 'Already taken. Defaults to 0.', '5'],
                    'encashed_days' => [false, 'Paid out rather than taken. Defaults to 0.', '0'],
                    'notes' => [false, '', 'Migrated from the old system'],
                ],
            ],

            self::SALARY_STRUCTURES => [
                'label' => 'Salary structures',
                'summary' => 'Current pay, so payroll can be run.',
                'description' => 'One row per employee: the CTC and basic, plus a column for each '
                    .'salary component you want set. Component columns are named "component:CODE" — '
                    .'the template lists the ones you have. An amount left empty means that '
                    .'component is not part of this person\'s pay. Importing again for the same '
                    .'employee closes their previous structure and starts a new one from the '
                    .'effective date.',
                'importer' => SalaryStructureImporter::class,
                'key_column' => 'employee_code',
                'columns' => [
                    'employee_code' => [true, '', 'EMP0001'],
                    'effective_from' => [true, 'When this pay started. Use the migration date if you do not know.', '2026-04-01'],
                    'ctc_annual' => [true, 'Annual cost to company.', '990000'],
                    'basic_salary' => [true, 'Monthly basic.', '33000'],
                    'currency' => [false, 'Defaults to the organisation currency.', 'INR'],
                    'payment_mode' => [false, 'bank_transfer, cheque, cash or upi. Defaults to bank transfer.', 'bank_transfer'],
                    'notes' => [false, '', ''],
                ],
            ],

            self::ATTENDANCE => [
                'label' => 'Attendance history',
                'summary' => 'Past days, for reporting and back-dated payroll.',
                'description' => 'Optional, and usually the largest file. Load it only as far back as '
                    .'you actually need — for payroll that is the current financial year. A day that '
                    .'is already recorded is replaced by the row in the file.',
                'importer' => AttendanceImporter::class,
                'key_column' => 'employee_code',
                'columns' => [
                    'employee_code' => [true, '', 'EMP0001'],
                    'date' => [true, 'YYYY-MM-DD.', '2026-04-01'],
                    'check_in' => [false, '24-hour time. Empty with a status of absent or leave is fine.', '09:28'],
                    'check_out' => [false, '24-hour time. A time before check-in is treated as the next morning.', '18:35'],
                    'status' => [false, 'present, absent, half_day, on_leave, holiday or weekend. Worked out from the times when empty.', 'present'],
                    'remarks' => [false, '', 'Migrated'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    public static function label(string $key): string
    {
        return self::all()[$key]['label'] ?? $key;
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /** Column names in the order the template writes them. */
    public static function columns(string $key): array
    {
        return array_keys(self::all()[$key]['columns'] ?? []);
    }

    /** @return array<int, string> */
    public static function requiredColumns(string $key): array
    {
        return collect(self::all()[$key]['columns'] ?? [])
            ->filter(fn (array $column) => $column[0])
            ->keys()
            ->all();
    }

    /** The example row that ships in the template. */
    public static function exampleRow(string $key): array
    {
        return collect(self::all()[$key]['columns'] ?? [])
            ->map(fn (array $column) => $column[2] ?? '')
            ->all();
    }
}
