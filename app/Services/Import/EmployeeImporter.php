<?php

namespace App\Services\Import;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use App\Services\EmployeeService;
use App\Support\ImportTypes;
use App\Support\Roles;
use Illuminate\Validation\Rule;

/**
 * The people themselves.
 *
 * Two things make this more than a column-for-column copy. Everything is named
 * by code rather than by database id, because the person filling in the sheet
 * has the old system's codes and not ours. And the reporting line is resolved
 * only after every row has been read: a manager is usually somewhere further
 * down the same file than the people who report to them.
 */
class EmployeeImporter extends RowImporter
{
    /** employee_code => manager_code, resolved once the file is read. */
    protected array $reportingLines = [];

    public function __construct(protected EmployeeService $employees) {}

    public function type(): string
    {
        return ImportTypes::EMPLOYEES;
    }

    protected function numericColumns(): array
    {
        return ['notice_period_days'];
    }

    public function aliases(): array
    {
        return [
            'employee_code' => ['emp_code', 'employee_id', 'emp_id', 'staff_id', 'code'],
            'first_name' => ['firstname', 'given_name'],
            'last_name' => ['lastname', 'surname', 'family_name'],
            'email' => ['work_email', 'official_email', 'company_email', 'email_address'],
            'personal_email' => ['private_email'],
            'phone' => ['mobile', 'mobile_number', 'contact_number', 'phone_number'],
            'date_of_joining' => ['doj', 'joining_date', 'date_joined', 'hire_date'],
            'date_of_birth' => ['dob', 'birth_date'],
            'date_of_exit' => ['dol', 'leaving_date', 'exit_date', 'last_working_day'],
            'branch_code' => ['branch', 'location_code'],
            'department_code' => ['dept_code'],
            'designation_code' => ['title_code'],
            'manager_code' => ['reporting_to', 'reports_to', 'manager_id', 'manager_employee_code'],
            'bank_account_number' => ['account_number', 'bank_ac_no'],
            'bank_ifsc' => ['ifsc', 'ifsc_code'],
            'pan_number' => ['pan'],
            'uan_number' => ['uan'],
            'pf_number' => ['pf'],
            'esi_number' => ['esi'],
        ];
    }

    public function rules(): array
    {
        return [
            'employee_code' => ['nullable', 'string', 'max:32'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'alternate_phone' => ['nullable', 'string', 'max:32'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'blood_group' => ['nullable', 'string', 'max:8'],
            'nationality' => ['nullable', 'string', 'max:64'],
            'branch_code' => ['required', 'string', 'max:32'],
            'employment_type' => ['nullable', Rule::in(array_column(EmploymentType::cases(), 'value'))],
            'employment_status' => ['nullable', Rule::in(array_column(EmploymentStatus::cases(), 'value'))],
            'notice_period_days' => ['nullable', 'integer', 'between:0,365'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:64'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
            'bank_ifsc' => ['nullable', 'string', 'max:32'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'pan_number' => ['nullable', 'string', 'max:32'],
            'national_id' => ['nullable', 'string', 'max:64'],
            'pf_number' => ['nullable', 'string', 'max:64'],
            'esi_number' => ['nullable', 'string', 'max:64'],
            'uan_number' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function uniqueColumns(): array
    {
        return ['employee_code', 'email'];
    }

    protected function messages(): array
    {
        return [
            'email.required' => 'A work email is needed: it is how the person signs in.',
            'branch_code.required' => 'Every employee belongs to a branch. Give its code, e.g. BLR.',
        ];
    }

    protected function checkReferences(array $row): void
    {
        $existing = $this->existing($row['employee_code']);

        // The email is the login, so it may not belong to somebody else.
        $emailOwner = Employee::withTrashed()->where('email', $row['email'])->first();

        if ($emailOwner && (! $existing || $emailOwner->id !== $existing->id)) {
            $this->fail('email', 'The email "'.$row['email'].'" already belongs to '
                .$emailOwner->full_name.' ('.$emailOwner->employee_code.').');
        }

        $userOwner = User::withTrashed()->where('email', $row['email'])->first();

        if ($userOwner && (! $existing || $existing->user_id !== $userOwner->id)) {
            $this->fail('email', 'There is already a login using "'.$row['email'].'".');
        }

        $branch = $this->branch($row['branch_code']);

        if (! $branch) {
            $this->fail('branch_code', 'There is no branch with the code "'.$row['branch_code'].'". Load the branches first.');
        }

        if ($row['department_code'] && ! $this->department($branch->id, $row['department_code'])) {
            $this->fail('department_code', 'The branch "'.$row['branch_code'].'" has no department "'
                .$row['department_code'].'".');
        }

        if ($row['designation_code'] && ! $this->designation($row['designation_code'])) {
            $this->fail('designation_code', 'There is no designation with the code "'.$row['designation_code'].'".');
        }

        if ($row['shift_code'] && ! $this->shift($row['shift_code'])) {
            $this->fail('shift_code', 'There is no shift with the code "'.$row['shift_code'].'".');
        }

        if ($row['company_code'] && ! $this->company($row['company_code'])) {
            $this->fail('company_code', 'There is no company with the code "'.$row['company_code'].'".');
        }

        if (! $row['company_code'] && ! Company::default()) {
            $this->fail('company_code', 'No default company is set, so every row must name one.');
        }

        if ($row['role'] && ! $this->role($row['role'])) {
            $this->fail('role', 'There is no role called "'.$row['role'].'".');
        }

        $joining = $this->date($row['date_of_joining']);

        if (! $joining) {
            $this->fail('date_of_joining', 'The joining date is required, as YYYY-MM-DD.');
        }

        foreach (['date_of_birth', 'date_of_confirmation', 'date_of_exit'] as $column) {
            if ($row[$column] !== null && $this->date($row[$column]) === null) {
                $this->fail($column, 'The '.str_replace('_', ' ', $column).' "'.$row[$column].'" is not a date. Use YYYY-MM-DD.');
            }
        }

        if ($row['date_of_exit'] && $this->date($row['date_of_exit'])->lt($joining)) {
            $this->fail('date_of_exit', 'The exit date is before the joining date.');
        }

        if ($row['manager_code'] && strcasecmp($row['manager_code'], (string) $row['employee_code']) === 0) {
            $this->fail('manager_code', 'An employee cannot report to themselves.');
        }
    }

    public function apply(array $row): string
    {
        $row = $this->prepare($row);

        $branch = $this->branch($row['branch_code']);
        $exit = $this->date($row['date_of_exit']);

        $attributes = [
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'],
            'personal_email' => $row['personal_email'],
            'phone' => $row['phone'],
            'alternate_phone' => $row['alternate_phone'],
            'gender' => $row['gender'],
            'date_of_birth' => $this->date($row['date_of_birth'])?->toDateString(),
            'marital_status' => $row['marital_status'],
            'blood_group' => $row['blood_group'],
            'nationality' => $row['nationality'],
            'address_line1' => $row['address_line1'],
            'address_line2' => $row['address_line2'],
            'city' => $row['city'],
            'state' => $row['state'],
            'country' => $row['country'] ?? 'India',
            'postal_code' => $row['postal_code'],
            'emergency_contact_name' => $row['emergency_contact_name'],
            'emergency_contact_phone' => $row['emergency_contact_phone'],
            'emergency_contact_relation' => $row['emergency_contact_relation'],
            'company_id' => ($this->company($row['company_code']) ?? Company::default())?->id,
            'branch_id' => $branch->id,
            'department_id' => $this->department($branch->id, $row['department_code'])?->id,
            'designation_id' => $this->designation($row['designation_code'])?->id,
            'shift_id' => $this->shift($row['shift_code'])?->id ?? Shift::where('is_default', true)->value('id'),
            'employment_type' => $row['employment_type'] ?? EmploymentType::FullTime->value,
            'employment_status' => $row['employment_status'] ?? EmploymentStatus::Permanent->value,
            'date_of_joining' => $this->date($row['date_of_joining'])->toDateString(),
            'date_of_confirmation' => $this->date($row['date_of_confirmation'])?->toDateString(),
            'date_of_exit' => $exit?->toDateString(),
            'notice_period_days' => $row['notice_period_days'] !== null ? (int) $row['notice_period_days'] : 30,
            'bank_name' => $row['bank_name'],
            'bank_account_name' => $row['bank_account_name'],
            'bank_account_number' => $row['bank_account_number'],
            'bank_ifsc' => $row['bank_ifsc'],
            'bank_branch' => $row['bank_branch'],
            'pan_number' => $row['pan_number'],
            'national_id' => $row['national_id'],
            'pf_number' => $row['pf_number'],
            'esi_number' => $row['esi_number'],
            'uan_number' => $row['uan_number'],
            // Somebody with an exit date has already left, whatever the sheet says.
            'status' => $exit ? 'inactive' : ($row['status'] ?? 'active'),
            'notes' => $row['notes'],
        ];

        $existing = $this->existing($row['employee_code']);

        if ($existing) {
            $existing->restore();
            $this->employees->update($existing, $attributes);
            $this->rememberReportingLine($existing->employee_code, $row['manager_code']);

            return 'updated';
        }

        $result = $this->employees->create(
            $attributes + ['employee_code' => $row['employee_code']],
            // A login is created but no welcome email is sent: an import runs
            // over the whole company at once, and nobody wants six hundred
            // people emailed a password in the middle of a migration. Send
            // credentials from the employee's own screen when you are ready.
            createAccount: true,
            role: $this->role($row['role']) ?? Roles::EMPLOYEE,
        );

        $this->rememberReportingLine($result['employee']->employee_code, $row['manager_code']);

        return 'created';
    }

    /**
     * Join up the reporting lines now that everybody exists.
     *
     * A manager named in the file but never loaded is left unset rather than
     * failing the import: the employee is more valuable than the line.
     */
    public function finish(): void
    {
        if ($this->reportingLines === []) {
            return;
        }

        $employees = Employee::whereIn('employee_code', array_merge(
            array_keys($this->reportingLines),
            array_values($this->reportingLines),
        ))->get()->keyBy(fn (Employee $e) => strtolower($e->employee_code));

        foreach ($this->reportingLines as $code => $managerCode) {
            $employee = $employees->get(strtolower($code));
            $manager = $employees->get(strtolower($managerCode));

            if ($employee && $manager && $employee->id !== $manager->id) {
                $employee->update(['reporting_to' => $manager->id]);
            }
        }

        $this->reportingLines = [];
    }

    protected function rememberReportingLine(?string $code, ?string $managerCode): void
    {
        if ($code && $managerCode) {
            $this->reportingLines[$code] = $managerCode;
        }
    }

    protected function existing(?string $code): ?Employee
    {
        if ($code === null) {
            return null;
        }

        // Trashed too: a code that has been used and deleted still occupies it,
        // and re-importing that person should bring their record back rather
        // than collide with a row nobody can see.
        return Employee::withTrashed()->where('employee_code', $code)->first();
    }

    protected function branch(?string $code): ?Branch
    {
        return $code === null ? null
            : $this->remember('branch', $code, fn () => Branch::where('code', $code)->first());
    }

    protected function department(int $branchId, ?string $code): ?Department
    {
        return $code === null ? null : $this->remember(
            'department',
            $branchId.'|'.$code,
            fn () => Department::where('branch_id', $branchId)->where('code', $code)->first(),
        );
    }

    protected function designation(?string $code): ?Designation
    {
        return $code === null ? null
            : $this->remember('designation', $code, fn () => Designation::where('code', $code)->first());
    }

    protected function shift(?string $code): ?Shift
    {
        return $code === null ? null
            : $this->remember('shift', $code, fn () => Shift::where('code', $code)->first());
    }

    protected function company(?string $code): ?Company
    {
        return $code === null ? null
            : $this->remember('company', $code, fn () => Company::where('code', $code)->first());
    }

    /** "HR Manager", "hr-manager" and "hr manager" all name the same role. */
    protected function role(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $wanted = strtolower(preg_replace('/[\s\-_]+/', '', $name));

        foreach (Roles::all() as $role) {
            if (strtolower(preg_replace('/[\s\-_]+/', '', $role)) === $wanted
                || strtolower(preg_replace('/[\s\-_]+/', '', Roles::label($role))) === $wanted) {
                return $role;
            }
        }

        return null;
    }
}
