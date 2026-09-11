<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Support\BankFormats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The legal entities that run payroll.
 *
 * Each one is a separate employer: its own registration numbers, its own logo,
 * its own salary slips. Employees are assigned to one when they are onboarded.
 */
class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('companies.view'), 403);

        return view('companies.index', [
            'companies' => Company::query()
                ->withCount(['employees', 'payrolls', 'signatories'])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'canManage' => $request->user()->can('companies.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('companies.manage'), 403);

        return view('companies.edit', [
            'company' => new Company(['currency' => 'INR', 'status' => 'active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('companies.manage'), 403);

        $company = Company::create($this->validated($request));
        $this->storeImages($request, $company);

        return redirect()->route('companies.index')
            ->with('success', $company->displayName().' added. Employees can now be assigned to it.');
    }

    public function edit(Request $request, Company $company): View
    {
        abort_unless($request->user()->can('companies.manage'), 403);

        return view('companies.edit', ['company' => $company]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        abort_unless($request->user()->can('companies.manage'), 403);

        $company->update($this->validated($request, $company));
        $this->storeImages($request, $company);

        return redirect()->route('companies.index')
            ->with('success', 'Saved '.$company->displayName().'.');
    }

    public function destroy(Request $request, Company $company): RedirectResponse
    {
        abort_unless($request->user()->can('companies.manage'), 403);

        // Salary slips must keep pointing at whoever issued them, so a company
        // that has ever paid anybody is retired rather than removed.
        if ($company->isInUse()) {
            return back()->with(
                'error',
                'This company employs people or has payroll history. Mark it inactive instead of deleting it.',
            );
        }

        $name = $company->displayName();
        $company->delete();

        return redirect()->route('companies.index')->with('success', $name.' removed.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?Company $company = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:12', 'alpha_num',
                Rule::unique('companies', 'code')->ignore($company?->id),
            ],
            'registration_number' => ['nullable', 'string', 'max:64'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'gst_number' => ['nullable', 'string', 'max:64'],
            'pf_number' => ['nullable', 'string', 'max:64'],
            'esi_number' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'website' => ['nullable', 'url', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:96'],
            'state' => ['nullable', 'string', 'max:96'],
            'country' => ['nullable', 'string', 'max:96'],
            'postal_code' => ['nullable', 'string', 'max:24'],
            'currency' => ['required', 'string', 'size:3'],
            'payslip_prefix' => ['required', 'string', 'max:12', 'alpha_num'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
            // Not required to look like an IFSC here — a company being set up
            // may not have its account yet. The bank file screen refuses one
            // that is present and malformed, which is where it matters.
            'bank_ifsc' => ['nullable', 'string', 'max:32'],
            'bank_file_format' => ['nullable', 'string', Rule::in(BankFormats::keys())],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_default' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image:allow_svg', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
            // The document pad. SVG is not offered here: the PDF renderer does
            // not rasterise it, so an SVG print logo would simply not appear.
            'letterhead_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_letterhead_logo' => ['nullable', 'boolean'],
            'letterhead_footer' => ['nullable', 'string', 'max:500'],
            'watermark_enabled' => ['nullable', 'boolean'],
            'watermark_text' => ['nullable', 'string', 'max:60'],
        ]);

        unset(
            $validated['logo'], $validated['remove_logo'],
            $validated['letterhead_logo'], $validated['remove_letterhead_logo'],
        );

        return [
            ...$validated,
            'code' => strtoupper($validated['code']),
            'currency' => strtoupper($validated['currency']),
            'payslip_prefix' => strtoupper($validated['payslip_prefix']),
            'bank_ifsc' => strtoupper(trim((string) ($validated['bank_ifsc'] ?? ''))) ?: null,
            'is_default' => $request->boolean('is_default'),
            'watermark_enabled' => $request->boolean('watermark_enabled'),
        ];
    }

    /**
     * The two logos an entity keeps.
     *
     * The screen logo appears in lists and on the interface; the letterhead
     * logo is the one printed on its documents, and can be a version that
     * survives a black and white printer. Either may be left empty.
     */
    protected function storeImages(Request $request, Company $company): void
    {
        $this->storeImage($request, $company, 'logo', 'logo_path');
        $this->storeImage($request, $company, 'letterhead_logo', 'letterhead_logo_path');
    }

    protected function storeImage(Request $request, Company $company, string $field, string $column): void
    {
        if ($request->hasFile($field)) {
            $this->deleteImage($company, $column);
            $company->update([
                $column => $request->file($field)->store('companies', 'public'),
            ]);

            return;
        }

        if ($request->boolean('remove_'.$field)) {
            $this->deleteImage($company, $column);
            $company->update([$column => null]);
        }
    }

    protected function deleteImage(Company $company, string $column): void
    {
        if ($company->{$column}) {
            Storage::disk('public')->delete($company->{$column});
        }
    }
}
