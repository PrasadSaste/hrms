<?php

namespace App\Services\Import;

use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\Setting;
use App\Services\PayrollService;
use App\Support\ImportTypes;
use Illuminate\Validation\Rule;

/**
 * Current pay, so payroll can be run the month after the migration.
 *
 * The fixed columns describe the structure; the pay itself arrives in columns
 * named after the salary components you already have — "component:basic",
 * "component:hra" and so on. An empty amount means that component is not part
 * of this person's pay, which is different from it being zero.
 */
class SalaryStructureImporter extends RowImporter
{
    /** How a component column is spelled in the header row. */
    private const PREFIX = 'component:';

    public function __construct(protected PayrollService $payroll) {}

    public function type(): string
    {
        return ImportTypes::SALARY_STRUCTURES;
    }

    protected function numericColumns(): array
    {
        return ['ctc_annual', 'basic_salary'];
    }

    public function aliases(): array
    {
        return [
            'employee_code' => ['emp_code', 'employee_id', 'emp_id', 'staff_id'],
            'effective_from' => ['effective_date', 'from_date', 'wef'],
            'ctc_annual' => ['ctc', 'annual_ctc', 'gross_annual'],
            'basic_salary' => ['basic', 'basic_pay'],
            'payment_mode' => ['mode_of_payment', 'payment_method'],
        ];
    }

    public function rules(): array
    {
        return [
            'employee_code' => ['required', 'string', 'max:32'],
            'effective_from' => ['required', 'string'],
            'ctc_annual' => ['required', 'numeric', 'min:0'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_mode' => ['nullable', Rule::in(array_keys(SalaryStructure::PAYMENT_MODES))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The component columns are not in the catalogue — they depend on what is
     * set up — so they are carried through prepare() alongside it.
     */
    public function prepare(array $row): array
    {
        $prepared = parent::prepare($row);

        foreach ($row as $key => $value) {
            if (str_starts_with(strtolower((string) $key), self::PREFIX)) {
                $amount = trim((string) $value);
                $prepared[strtolower($key)] = $amount === '' ? null : $this->plainNumber($amount);
            }
        }

        return $prepared;
    }

    protected function checkReferences(array $row): void
    {
        if (! $this->employee($row['employee_code'])) {
            $this->fail('employee_code', 'There is no employee with the code "'.$row['employee_code'].'". Load the employees first.');
        }

        if (! $this->date($row['effective_from'])) {
            $this->fail('effective_from', 'The effective from date "'.$row['effective_from'].'" is not a date. Use YYYY-MM-DD.');
        }

        foreach ($this->componentColumns($row) as $column => $value) {
            $code = substr($column, strlen(self::PREFIX));

            if (! $this->component($code)) {
                $this->fail($column, 'There is no salary component with the code "'.strtoupper($code)
                    .'". The components you have are listed under Salary Components.');
            }

            if ($this->number($value) === null) {
                $this->fail($column, 'The amount "'.$value.'" for '.strtoupper($code).' is not a number.');
            }
        }

        $basic = (float) $row['basic_salary'];
        $ctc = (float) $row['ctc_annual'];

        if ($ctc > 0 && $basic * 12 > $ctc) {
            $this->fail('basic_salary', 'A monthly basic of '.$basic.' is more than the annual CTC of '
                .$ctc.' spread over twelve months. One of the two is wrong.');
        }
    }

    public function apply(array $row): string
    {
        $row = $this->prepare($row);

        $employee = $this->employee($row['employee_code']);
        $from = $this->date($row['effective_from']);

        // Whatever they were on before ends the day this starts, so an employee
        // never has two open structures and payroll never has to guess.
        $closed = SalaryStructure::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->whereNull('effective_to')
            ->where('effective_from', '<', $from->toDateString())
            ->update([
                'effective_to' => $from->copy()->subDay()->toDateString(),
                'status' => 'inactive',
            ]);

        // A structure already starting on the same day is this row again,
        // so it is replaced rather than stacked.
        $structure = SalaryStructure::firstOrNew([
            'employee_id' => $employee->id,
            'effective_from' => $from->toDateString(),
        ]);

        $existed = $structure->exists;

        $structure->fill([
            'ctc_annual' => $this->number($row['ctc_annual']),
            'basic_salary' => $this->number($row['basic_salary']),
            'currency' => strtoupper($row['currency'] ?? (string) Setting::get('currency', 'INR')),
            'payment_mode' => $row['payment_mode'] ?? 'bank_transfer',
            'status' => 'active',
            'effective_to' => null,
            'notes' => $row['notes'],
        ])->save();

        $this->syncComponents($structure, $row);

        $this->payroll->syncStructureAmounts($structure->fresh('components.salaryComponent'));

        return $existed || $closed ? 'updated' : 'created';
    }

    /** Replace the structure's component rows with the ones in this row. */
    protected function syncComponents(SalaryStructure $structure, array $row): void
    {
        $keep = [];

        foreach ($this->componentColumns($row) as $column => $value) {
            $component = $this->component(substr($column, strlen(self::PREFIX)));

            $structure->components()->updateOrCreate(
                ['salary_component_id' => $component->id],
                // An amount in a spreadsheet is an amount, not a percentage of
                // something else, whatever the component normally does.
                ['calculation_type' => 'fixed', 'value' => $this->number($value)],
            );

            $keep[] = $component->id;
        }

        $structure->components()->whereNotIn('salary_component_id', $keep ?: [0])->delete();
    }

    /** @return array<string, string> the component columns that carry a value */
    protected function componentColumns(array $row): array
    {
        return collect($row)
            ->filter(fn ($value, $key) => str_starts_with((string) $key, self::PREFIX) && $value !== null && $value !== '')
            ->all();
    }

    protected function employee(?string $code): ?Employee
    {
        return $code === null ? null
            : $this->remember('employee', $code, fn () => Employee::where('employee_code', $code)->first());
    }

    protected function component(string $code): ?SalaryComponent
    {
        return $this->remember('component', $code, fn () => SalaryComponent::whereRaw(
            'LOWER(code) = ?',
            [strtolower($code)],
        )->first());
    }
}
