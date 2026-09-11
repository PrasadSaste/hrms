<?php

namespace App\Services\Import;

use App\Models\Employee;
use App\Models\LeaveAllocation;
use App\Models\LeaveType;
use App\Support\ImportTypes;

/**
 * What everybody had left when they walked out of the old system.
 *
 * Without this everyone starts on zero, or on a fresh full entitlement, and
 * both are wrong. A row replaces that employee's figures for that year rather
 * than adding to them, so a corrected sheet can simply be uploaded again.
 */
class LeaveBalanceImporter extends RowImporter
{
    public function type(): string
    {
        return ImportTypes::LEAVE_BALANCES;
    }

    protected function numericColumns(): array
    {
        return ['year', 'allocated_days', 'carried_forward_days', 'used_days', 'encashed_days'];
    }

    public function aliases(): array
    {
        return [
            'employee_code' => ['emp_code', 'employee_id', 'emp_id', 'staff_id'],
            'leave_type_code' => ['leave_type', 'leave_code', 'type'],
            'allocated_days' => ['allocated', 'entitlement', 'entitled_days'],
            'carried_forward_days' => ['carried_forward', 'opening_balance', 'carry_forward'],
            'used_days' => ['used', 'availed', 'availed_days', 'taken'],
            'encashed_days' => ['encashed'],
        ];
    }

    public function rules(): array
    {
        return [
            'employee_code' => ['required', 'string', 'max:32'],
            'leave_type_code' => ['required', 'string', 'max:32'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'allocated_days' => ['required', 'numeric', 'between:0,400'],
            'carried_forward_days' => ['nullable', 'numeric', 'between:0,400'],
            'used_days' => ['nullable', 'numeric', 'between:0,400'],
            'encashed_days' => ['nullable', 'numeric', 'between:0,400'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function checkReferences(array $row): void
    {
        if (! $this->employee($row['employee_code'])) {
            $this->fail('employee_code', 'There is no employee with the code "'.$row['employee_code'].'". Load the employees first.');
        }

        if (! $this->leaveType($row['leave_type_code'])) {
            $this->fail('leave_type_code', 'There is no leave type with the code "'.$row['leave_type_code']
                .'". The codes you have are set up under Leave Types.');
        }

        $entitled = (float) $row['allocated_days'] + (float) ($row['carried_forward_days'] ?? 0);
        $spent = (float) ($row['used_days'] ?? 0) + (float) ($row['encashed_days'] ?? 0);

        if ($spent > $entitled) {
            $this->fail('used_days', 'Used and encashed days ('.$spent.') come to more than the '
                .$entitled.' days allocated and carried forward, which would leave a negative balance.');
        }
    }

    public function apply(array $row): string
    {
        $row = $this->prepare($row);

        $employee = $this->employee($row['employee_code']);
        $type = $this->leaveType($row['leave_type_code']);

        $allocation = LeaveAllocation::firstOrNew([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'year' => (int) $row['year'],
        ]);

        $existed = $allocation->exists;

        $allocation->fill([
            'allocated_days' => $this->number($row['allocated_days']),
            'carried_forward_days' => $this->number($row['carried_forward_days']) ?? 0,
            'used_days' => $this->number($row['used_days']) ?? 0,
            'encashed_days' => $this->number($row['encashed_days']) ?? 0,
            'notes' => $row['notes'],
        ])->save();

        return $existed ? 'updated' : 'created';
    }

    protected function employee(?string $code): ?Employee
    {
        return $code === null ? null
            : $this->remember('employee', $code, fn () => Employee::where('employee_code', $code)->first());
    }

    protected function leaveType(?string $code): ?LeaveType
    {
        return $code === null ? null
            : $this->remember('leaveType', $code, fn () => LeaveType::where('code', $code)->first());
    }
}
