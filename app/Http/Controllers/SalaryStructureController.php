<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalaryStructureController extends Controller
{
    public function __construct(protected PayrollService $payroll) {}

    public function index(Request $request): View
    {
        $this->authorize('manageStructures', Payroll::class);

        $structures = SalaryStructure::query()
            ->with(['employee.department', 'employee.designation'])
            ->when($request->integer('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('effective_from')
            ->paginate(20)
            ->withQueryString();

        return view('payroll.structures.index', [
            'structures' => $structures,
            'employees' => Employee::active()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_code']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('manageStructures', Payroll::class);

        $employee = $request->integer('employee_id')
            ? Employee::findOrFail($request->integer('employee_id'))
            : null;

        return view('payroll.structures.create', [
            'structure' => new SalaryStructure([
                'employee_id' => $employee?->id,
                'effective_from' => now()->startOfMonth()->toDateString(),
                'currency' => 'INR',
                'payment_mode' => 'bank_transfer',
                'status' => 'active',
            ]),
            'employee' => $employee,
            'employees' => Employee::active()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_code']),
            'components' => SalaryComponent::active()->orderBy('type')->orderBy('sequence')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageStructures', Payroll::class);

        $validated = $this->validated($request);

        $structure = DB::transaction(function () use ($validated, $request) {
            // Close any structure that is still open for this employee.
            SalaryStructure::where('employee_id', $validated['employee_id'])
                ->where('status', 'active')
                ->whereNull('effective_to')
                ->update([
                    'effective_to' => now()->parse($validated['effective_from'])->subDay()->toDateString(),
                    'status' => 'inactive',
                ]);

            $structure = SalaryStructure::create($validated + ['created_by' => $request->user()->id]);

            $this->syncComponents($structure, $request->input('components', []));

            return $this->payroll->syncStructureAmounts($structure->fresh('components.salaryComponent'));
        });

        return redirect()->route('salary-structures.show', $structure)
            ->with('success', 'Salary structure saved.');
    }

    public function show(SalaryStructure $salaryStructure): View
    {
        $this->authorize('manageStructures', Payroll::class);

        $salaryStructure->load(['employee.department', 'employee.designation', 'components.salaryComponent', 'creator']);

        return view('payroll.structures.show', ['structure' => $salaryStructure]);
    }

    public function edit(SalaryStructure $salaryStructure): View
    {
        $this->authorize('manageStructures', Payroll::class);

        $salaryStructure->load('components');

        return view('payroll.structures.edit', [
            'structure' => $salaryStructure,
            'employee' => $salaryStructure->employee,
            'employees' => Employee::active()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_code']),
            'components' => SalaryComponent::active()->orderBy('type')->orderBy('sequence')->get(),
            'selected' => $salaryStructure->components->keyBy('salary_component_id'),
        ]);
    }

    public function update(Request $request, SalaryStructure $salaryStructure): RedirectResponse
    {
        $this->authorize('manageStructures', Payroll::class);

        $validated = $this->validated($request, $salaryStructure);

        DB::transaction(function () use ($salaryStructure, $validated, $request) {
            $salaryStructure->update($validated);
            $this->syncComponents($salaryStructure, $request->input('components', []));
            $this->payroll->syncStructureAmounts($salaryStructure->fresh('components.salaryComponent'));
        });

        return redirect()->route('salary-structures.show', $salaryStructure)
            ->with('success', 'Salary structure updated.');
    }

    public function destroy(SalaryStructure $salaryStructure): RedirectResponse
    {
        $this->authorize('manageStructures', Payroll::class);

        $salaryStructure->delete();

        return redirect()->route('salary-structures.index')->with('success', 'Salary structure removed.');
    }

    /** Replace the structure's component rows with the submitted selection. */
    protected function syncComponents(SalaryStructure $structure, array $rows): void
    {
        $keep = [];

        foreach ($rows as $componentId => $row) {
            if (! ($row['enabled'] ?? false)) {
                continue;
            }

            $component = SalaryComponent::find($componentId);

            if (! $component) {
                continue;
            }

            $structure->components()->updateOrCreate(
                ['salary_component_id' => $component->id],
                [
                    'calculation_type' => $row['calculation_type'] ?? $component->calculation_type,
                    'value' => (float) ($row['value'] ?? 0),
                ],
            );

            $keep[] = $component->id;
        }

        $structure->components()->whereNotIn('salary_component_id', $keep ?: [0])->delete();
    }

    protected function validated(Request $request, ?SalaryStructure $structure = null): array
    {
        return $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'ctc_annual' => ['required', 'numeric', 'min:0'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_mode' => ['required', Rule::in(array_keys(SalaryStructure::PAYMENT_MODES))],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
