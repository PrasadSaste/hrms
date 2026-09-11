<?php

namespace App\Http\Controllers;

use App\Enums\ComponentType;
use App\Models\Payroll;
use App\Models\SalaryComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalaryComponentController extends Controller
{
    public function index(): View
    {
        $this->authorize('manageComponents', Payroll::class);

        return view('payroll.components.index', [
            'components' => SalaryComponent::orderBy('type')->orderBy('sequence')->paginate(25),
        ]);
    }

    public function create(): View
    {
        $this->authorize('manageComponents', Payroll::class);

        return view('payroll.components.create', [
            'salaryComponent' => new SalaryComponent([
                'type' => ComponentType::Earning,
                'calculation_type' => 'fixed',
                'status' => 'active',
                'affects_gross' => true,
                'prorate_on_lop' => true,
                'is_taxable' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageComponents', Payroll::class);

        $component = SalaryComponent::create($this->validated($request));

        return redirect()->route('salary-components.index')
            ->with('success', 'Component "'.$component->name.'" was created.');
    }

    public function edit(SalaryComponent $salaryComponent): View
    {
        $this->authorize('manageComponents', Payroll::class);

        return view('payroll.components.edit', ['salaryComponent' => $salaryComponent]);
    }

    public function update(Request $request, SalaryComponent $salaryComponent): RedirectResponse
    {
        $this->authorize('manageComponents', Payroll::class);

        $salaryComponent->update($this->validated($request, $salaryComponent->id));

        return redirect()->route('salary-components.index')->with('success', 'Component updated.');
    }

    public function destroy(SalaryComponent $salaryComponent): RedirectResponse
    {
        $this->authorize('manageComponents', Payroll::class);

        if ($salaryComponent->structureComponents()->exists()) {
            return back()->withErrors([
                'delete' => 'This component is used by a salary structure. Set it to inactive instead.',
            ]);
        }

        $salaryComponent->delete();

        return redirect()->route('salary-components.index')->with('success', 'Component removed.');
    }

    /**
     * The slab table, tidied.
     *
     * Empty rows are dropped rather than stored, and the bands are sorted so
     * that the lowest ceiling is read first — the amount is decided by the
     * first band a wage does not exceed, so an unsorted table would charge the
     * wrong one. A band with no ceiling is the top and goes last.
     *
     * @return array<int, array{up_to: float|null, amount: float}>
     */
    protected function cleanSlabs(array $slabs): array
    {
        $rows = collect($slabs)
            ->filter(fn ($slab) => filled($slab['up_to'] ?? null) || filled($slab['amount'] ?? null))
            ->map(fn ($slab) => [
                'up_to' => filled($slab['up_to'] ?? null) ? (float) $slab['up_to'] : null,
                'amount' => (float) ($slab['amount'] ?? 0),
            ])
            ->values();

        return $rows
            ->sortBy(fn (array $slab) => $slab['up_to'] ?? INF)
            ->values()
            ->all();
    }

    protected function validated(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'alpha_dash', Rule::unique('salary_components', 'code')->ignore($id)],
            'type' => ['required', Rule::in(array_column(ComponentType::cases(), 'value'))],
            'calculation_type' => ['required', Rule::in(array_keys(SalaryComponent::CALCULATION_TYPES))],
            // A slab table is read against a base too, so it needs one named.
            'percentage_of' => ['nullable', 'required_if:calculation_type,percentage', 'required_if:calculation_type,slab', Rule::in(array_keys(SalaryComponent::PERCENTAGE_BASES))],
            'default_value' => ['required', 'numeric', 'min:0'],
            'wage_ceiling' => ['nullable', 'numeric', 'min:0'],
            'eligibility_ceiling' => ['nullable', 'numeric', 'min:0'],
            'statutory_note' => ['nullable', 'string', 'max:255'],
            'slabs' => ['nullable', 'array', 'max:20'],
            'slabs.*.up_to' => ['nullable', 'numeric', 'min:0'],
            'slabs.*.amount' => ['nullable', 'numeric', 'min:0'],
            'is_taxable' => ['nullable', 'boolean'],
            'is_statutory' => ['nullable', 'boolean'],
            'affects_gross' => ['nullable', 'boolean'],
            'prorate_on_lop' => ['nullable', 'boolean'],
            'sequence' => ['required', 'integer', 'between:0,999'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        foreach (['is_taxable', 'is_statutory', 'affects_gross', 'prorate_on_lop'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        if (! in_array($data['calculation_type'], ['percentage', 'slab'], true)) {
            $data['percentage_of'] = null;
        }

        $data['slabs'] = $this->cleanSlabs($data['slabs'] ?? []);

        return $data;
    }
}
