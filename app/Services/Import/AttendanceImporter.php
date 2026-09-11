<?php

namespace App\Services\Import;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AttendanceService;
use App\Support\ImportTypes;
use Illuminate\Validation\Rule;

/**
 * Days that happened before the migration.
 *
 * The times go through the same service a person's punches do, so an imported
 * day is rebuilt into sessions and totalled exactly like one recorded here —
 * late minutes, overtime and worked hours all come out the same. Payroll can
 * then be run for a month that started in the old system.
 */
class AttendanceImporter extends RowImporter
{
    public function __construct(protected AttendanceService $attendance) {}

    public function type(): string
    {
        return ImportTypes::ATTENDANCE;
    }

    public function aliases(): array
    {
        return [
            'employee_code' => ['emp_code', 'employee_id', 'emp_id', 'staff_id'],
            'date' => ['attendance_date', 'day'],
            'check_in' => ['in_time', 'punch_in', 'intime', 'first_in'],
            'check_out' => ['out_time', 'punch_out', 'outtime', 'last_out'],
            'remarks' => ['remark', 'notes', 'comment'],
        ];
    }

    public function rules(): array
    {
        return [
            'employee_code' => ['required', 'string', 'max:32'],
            'date' => ['required', 'string'],
            'status' => ['nullable', Rule::in(array_column(AttendanceStatus::cases(), 'value'))],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function checkReferences(array $row): void
    {
        if (! $this->employee($row['employee_code'])) {
            $this->fail('employee_code', 'There is no employee with the code "'.$row['employee_code'].'". Load the employees first.');
        }

        $date = $this->date($row['date']);

        if (! $date) {
            $this->fail('date', 'The date "'.$row['date'].'" could not be read. Use YYYY-MM-DD.');
        }

        if ($date->isFuture()) {
            $this->fail('date', 'The date '.$date->toDateString().' is in the future.');
        }

        foreach (['check_in', 'check_out'] as $column) {
            if ($row[$column] !== null && $this->time($row[$column]) === null) {
                $this->fail($column, 'The '.str_replace('_', ' ', $column).' "'.$row[$column]
                    .'" is not a time. Use 24-hour, e.g. 09:30.');
            }
        }

        if ($row['check_out'] !== null && $row['check_in'] === null) {
            $this->fail('check_in', 'There is a check-out time but no check-in time.');
        }

        $employee = $this->employee($row['employee_code']);

        if ($date->lt($employee->date_of_joining)) {
            $this->fail('date', 'The date '.$date->toDateString().' is before '.$employee->full_name
                .' joined on '.$employee->date_of_joining->toDateString().'.');
        }
    }

    public function apply(array $row): string
    {
        $row = $this->prepare($row);

        $employee = $this->employee($row['employee_code']);
        $date = $this->date($row['date']);

        $existed = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $date->toDateString())
            ->exists();

        $this->attendance->record($employee, [
            'date' => $date->toDateString(),
            'check_in' => $this->time($row['check_in']),
            'check_out' => $this->time($row['check_out']),
            'status' => $row['status'] ?? $this->inferStatus($row),
            'remarks' => $row['remarks'],
            'source' => 'import',
        ]);

        return $existed ? 'updated' : 'created';
    }

    /**
     * A row with times but no status is a day somebody worked; a row with
     * neither is a day they did not.
     */
    protected function inferStatus(array $row): string
    {
        return $row['check_in'] !== null
            ? AttendanceStatus::Present->value
            : AttendanceStatus::Absent->value;
    }

    protected function employee(?string $code): ?Employee
    {
        return $code === null ? null
            : $this->remember('employee', $code, fn () => Employee::where('employee_code', $code)->first());
    }
}
