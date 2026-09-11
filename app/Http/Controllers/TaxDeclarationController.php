<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\TaxDeclaration;
use App\Models\TaxDeclarationItem;
use App\Services\IncomeTaxService;
use App\Services\TaxDeclarationService;
use App\Services\TaxStatementService;
use App\Support\FinancialYear;
use App\Support\TaxDeductionSections;
use App\Support\TaxRegimes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxDeclarationController extends Controller
{
    public function __construct(
        protected TaxDeclarationService $declarations,
        protected IncomeTaxService $tax,
    ) {}

    /** Everybody's declarations, for the people who verify them. */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', TaxDeclaration::class);

        $year = $this->year($request);

        $declarations = TaxDeclaration::query()
            ->with(['employee.department', 'items', 'verifier'])
            ->where('financial_year', $year)
            ->whereHas('employee', fn ($q) => $q->visibleTo($request->user()))
            // Qualified: employees carries a status column of its own, and the
            // join below makes a bare one ambiguous.
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('tax_declarations.status', $request->string('status')),
            )
            ->join('employees', 'employees.id', '=', 'tax_declarations.employee_id')
            ->orderBy('employees.employee_code')
            ->select('tax_declarations.*')
            ->paginate(25)
            ->withQueryString();

        return view('tax.index', [
            'declarations' => $declarations,
            'year' => $year,
            'years' => $this->yearOptions(),
            'statuses' => TaxDeclaration::statuses(),
            'counts' => $this->counts($request, $year),
        ]);
    }

    /** My own declaration, and what it does to my tax. */
    public function mine(Request $request): View
    {
        abort_unless($request->user()->can('tax.declare'), 403);

        $employee = $request->user()->employee;

        abort_unless($employee !== null, 404, 'Your account is not linked to an employee record.');

        $year = $this->year($request);
        $declaration = $this->declarations->forEmployee($employee, $year);

        return view('tax.mine', [
            'employee' => $employee,
            'declaration' => $declaration,
            'year' => $year,
            'years' => $this->yearOptions(),
            'groups' => TaxDeductionSections::GROUPS,
            'sections' => TaxDeductionSections::all(),
            'regimes' => TaxRegimes::labels(),
            'comparison' => $this->tax->compare($employee, $year),
            'editable' => $request->user()->can('update', $declaration),
        ]);
    }

    /** Save what the employee typed, and optionally hand it in. */
    public function update(Request $request, TaxDeclaration $declaration): RedirectResponse
    {
        $this->authorize('update', $declaration);

        $validated = $request->validate([
            'regime' => ['required', Rule::in(array_keys(TaxRegimes::labels()))],
            'metro' => ['nullable', 'boolean'],
            'amounts' => ['array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'submit' => ['nullable', 'boolean'],
        ]);

        // Only sections the catalogue knows about. A key that is not one is
        // dropped rather than refused: it is a stale form, not an attack, and
        // refusing would lose everything else the employee typed.
        $amounts = array_intersect_key(
            $validated['amounts'] ?? [],
            array_flip(TaxDeductionSections::keys()),
        );

        $this->declarations->save($declaration, $amounts, [
            'regime' => $validated['regime'],
            'metro' => $request->boolean('metro'),
        ]);

        if ($request->boolean('submit')) {
            $this->authorize('submit', $declaration->fresh('items'));
            $this->declarations->submit($declaration);

            return back()->with('success', 'Declaration submitted. HR will check it against your proofs.');
        }

        return back()->with('success', 'Declaration saved.');
    }

    /** Somebody else's declaration, with the tax it produces. */
    public function show(Request $request, TaxDeclaration $declaration): View
    {
        $this->authorize('view', $declaration);

        $declaration->load(['employee.department', 'items', 'verifier']);

        return view('tax.show', [
            'declaration' => $declaration,
            'sections' => TaxDeductionSections::all(),
            'groups' => TaxDeductionSections::GROUPS,
            'comparison' => $this->tax->compare($declaration->employee, $declaration->financial_year),
            'canVerify' => $request->user()->can('verify', $declaration),
        ]);
    }

    /** Record what HR accepted against each proof. */
    public function verify(Request $request, TaxDeclaration $declaration): RedirectResponse
    {
        $this->authorize('verify', $declaration);

        $validated = $request->validate([
            'verified' => ['array'],
            'verified.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->declarations->verify(
            $declaration,
            $validated['verified'] ?? [],
            $request->user(),
            $validated['remarks'] ?? null,
        );

        return back()->with('success', 'Declaration verified.');
    }

    /** Send it back for the employee to correct. */
    public function sendBack(Request $request, TaxDeclaration $declaration): RedirectResponse
    {
        $this->authorize('verify', $declaration);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $this->declarations->sendBack($declaration, $request->user(), $validated['remarks']);

        return back()->with('success', 'Sent back to the employee.');
    }

    public function uploadProof(Request $request, TaxDeclaration $declaration): RedirectResponse
    {
        $this->authorize('update', $declaration);

        $validated = $request->validate([
            'section' => ['required', Rule::in(TaxDeductionSections::keys())],
            'proof' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:4096'],
        ]);

        $this->declarations->attachProof($declaration, $validated['section'], $request->file('proof'));

        return back()->with('success', 'Proof attached.');
    }

    /** Streamed, never linked: a proof is a bank statement. */
    public function proof(Request $request, TaxDeclarationItem $item): StreamedResponse
    {
        $this->authorize('view', $item->declaration);

        return $this->declarations->downloadProof($item);
    }

    /** The working behind one year's tax, for whoever is asked about it. */
    public function computation(Request $request, Employee $employee): View
    {
        $isOwner = $request->user()->employee?->is($employee) ?? false;

        abort_unless(
            ($isOwner && $request->user()->can('tax.declare')) || $request->user()->can('tax.view'),
            403,
        );

        if (! $isOwner) {
            $this->authorize('view', $employee);
        }

        $year = $this->year($request);
        $declaration = $this->tax->declarationFor($employee, $year);
        $regime = $request->string('regime')->toString();
        $regime = TaxRegimes::has($regime) ? $regime : ($declaration?->regime ?? TaxRegimes::default());

        return view('tax.computation', [
            'employee' => $employee,
            'year' => $year,
            'years' => $this->yearOptions(),
            'regime' => $regime,
            'regimes' => TaxRegimes::labels(),
            'computation' => $this->tax->computeFor($employee, $year, $regime),
            'comparison' => $this->tax->compare($employee, $year),
            'declaration' => $declaration,
        ]);
    }

    /** The year's statement as a PDF. */
    public function statement(Request $request, Employee $employee, TaxStatementService $statements): Response
    {
        $isOwner = $request->user()->employee?->is($employee) ?? false;

        abort_unless(
            ($isOwner && $request->user()->can('tax.declare')) || $request->user()->can('tax.view'),
            403,
        );

        if (! $isOwner) {
            $this->authorize('view', $employee);
        }

        $year = $this->year($request);

        return $statements->make($employee, $year)
            ->download($statements->filename($employee, $year));
    }

    protected function year(Request $request): int
    {
        $year = (int) $request->input('year', FinancialYear::current());
        $current = FinancialYear::current();

        // Bounded rather than free: a stray query string should not send the
        // projection off into a year nobody has payslips in.
        return max($current - 5, min($current + 1, $year));
    }

    /** @return array<int, string> */
    protected function yearOptions(): array
    {
        $current = FinancialYear::current();
        $options = [];

        for ($year = $current + 1; $year >= $current - 3; $year--) {
            $options[$year] = FinancialYear::label($year);
        }

        return $options;
    }

    /** @return array<string, int> */
    protected function counts(Request $request, int $year): array
    {
        $counts = TaxDeclaration::query()
            ->where('financial_year', $year)
            ->whereHas('employee', fn ($q) => $q->visibleTo($request->user()))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        // Keyed by status, in the catalogue's order, with a nought for the
        // ones nobody is in — the chips have to add up to the table.
        return collect(array_keys(TaxDeclaration::statuses()))
            ->mapWithKeys(fn (string $status) => [$status => (int) ($counts[$status] ?? 0)])
            ->all();
    }
}
