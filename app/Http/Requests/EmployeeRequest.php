<?php

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Support\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employee = $this->route('employee');
        $employeeId = $employee?->id;
        $userId = $employee?->user_id;

        return [
            'employee_code' => [
                'nullable', 'string', 'max:32',
                Rule::unique('employees', 'employee_code')->ignore($employeeId)->whereNull('deleted_at'),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('employees', 'email')->ignore($employeeId)->whereNull('deleted_at'),
                Rule::unique('users', 'email')->ignore($userId)->whereNull('deleted_at'),
            ],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'alternate_phone' => ['nullable', 'string', 'max:32'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'blood_group' => ['nullable', 'string', 'max:8'],
            'nationality' => ['nullable', 'string', 'max:64'],
            'photo' => ['nullable', 'image', 'max:2048'],

            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],

            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:64'],

            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'designation_id' => ['nullable', 'integer', 'exists:designations,id'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'reporting_to' => [
                'nullable', 'integer', 'exists:employees,id',
                Rule::notIn(array_filter([$employeeId])),
            ],
            'employment_type' => ['required', Rule::in(array_column(EmploymentType::cases(), 'value'))],
            'employment_status' => ['required', Rule::in(array_column(EmploymentStatus::cases(), 'value'))],
            'date_of_joining' => ['required', 'date'],
            'date_of_confirmation' => ['nullable', 'date', 'after_or_equal:date_of_joining'],
            'date_of_exit' => ['nullable', 'date', 'after_or_equal:date_of_joining'],
            'exit_reason' => ['nullable', 'string', 'max:1000'],
            'notice_period_days' => ['required', 'integer', 'between:0,365'],
            'overtime_eligible' => ['nullable', 'boolean'],

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

            'status' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:5000'],

            'create_account' => ['nullable', 'boolean'],
            'role' => ['nullable', 'string', Rule::in(Roles::all())],
            'send_welcome_email' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'reporting_to.not_in' => 'An employee cannot report to themselves.',
            'company_id.required' => 'Please choose which company employs this person — their salary slips are issued in its name.',
            'branch_id.required' => 'Please choose the branch this employee belongs to.',
        ];
    }

    /** Only the employee columns, without the account-provisioning flags. */
    public function employeeData(): array
    {
        return collect($this->validated())
            ->except(['photo', 'create_account', 'role', 'send_welcome_email'])
            ->all();
    }
}
