<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Signatory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Who may sign for a company.
 *
 * A list rather than a single name, because a director is sometimes away and
 * an offer letter still has to go out. One of them is the default: that is who
 * signs a payslip and who the letter screen offers first.
 *
 * A specimen signature is an image somebody could lift and reuse, so it lives
 * on the private disk and is streamed through here, never linked publicly.
 */
class SignatoryController extends Controller
{
    public function index(Request $request, Company $company): View
    {
        abort_unless($request->user()->can('companies.view'), 403);

        return view('signatories.index', [
            'company' => $company,
            'signatories' => $company->signatories()->withCount('letters')->ordered()->get(),
            'canManage' => $request->user()->can('companies.manage'),
        ]);
    }

    public function store(Request $request, Company $company): RedirectResponse
    {
        abort_unless($request->user()->can('companies.manage'), 403);

        $signatory = $company->signatories()->create($this->validated($request, $company));
        $this->storeSignature($request, $signatory);

        // The first name on an empty list is the default, so the setting is
        // never half-done after somebody adds exactly one person.
        if ($company->signatories()->count() === 1) {
            $signatory->update(['is_default' => true]);
        }

        return redirect()->route('companies.signatories.index', $company)
            ->with('success', $signatory->name.' can now sign for '.$company->displayName().'.');
    }

    public function update(Request $request, Company $company, Signatory $signatory): RedirectResponse
    {
        abort_unless($request->user()->can('companies.manage'), 403);
        $this->assertBelongs($company, $signatory);

        $signatory->update($this->validated($request, $company, $signatory));
        $this->storeSignature($request, $signatory);

        return redirect()->route('companies.signatories.index', $company)
            ->with('success', 'Saved '.$signatory->name.'.');
    }

    /** Whose name a payslip carries, and who the letter screen offers first. */
    public function makeDefault(Request $request, Company $company, Signatory $signatory): RedirectResponse
    {
        abort_unless($request->user()->can('companies.manage'), 403);
        $this->assertBelongs($company, $signatory);

        if (! $signatory->isActive()) {
            return back()->with('error', $signatory->name.' is retired. Make them active before setting them as the default.');
        }

        $signatory->update(['is_default' => true]);

        return back()->with('success', $signatory->name.' now signs for '.$company->displayName().' by default.');
    }

    public function destroy(Request $request, Company $company, Signatory $signatory): RedirectResponse
    {
        abort_unless($request->user()->can('companies.manage'), 403);
        $this->assertBelongs($company, $signatory);

        // A letter keeps the name it went out over, but the register should
        // still be able to point at whoever signed it, so somebody who has
        // signed anything is retired rather than removed.
        if ($signatory->letters()->exists()) {
            $signatory->update(['status' => 'inactive', 'is_default' => false]);

            return back()->with(
                'success',
                $signatory->name.' has signed letters already, so they were retired rather than deleted.',
            );
        }

        $this->deleteSignature($signatory);
        $name = $signatory->name;
        $signatory->delete();

        return back()->with('success', $name.' removed.');
    }

    /** The specimen signature, streamed rather than linked. */
    public function signature(Request $request, Company $company, Signatory $signatory): StreamedResponse
    {
        abort_unless($request->user()->can('companies.view'), 403);
        $this->assertBelongs($company, $signatory);

        abort_unless(
            $signatory->signature_path && Storage::disk('local')->exists($signatory->signature_path),
            404,
            'No signature has been uploaded.',
        );

        return Storage::disk('local')->response($signatory->signature_path);
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, Company $company, ?Signatory $signatory = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_default' => ['nullable', 'boolean'],
            'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:512'],
            'remove_signature' => ['nullable', 'boolean'],
        ]);

        unset($validated['signature'], $validated['remove_signature']);

        // Who signs by default is changed by the Make default action, never by
        // editing somebody: an edit form that quietly cleared it would leave the
        // company with nobody. A retired signatory does lose it, though, or a
        // payslip would go out over the name of somebody who has stopped signing.
        $isDefault = $signatory
            ? $signatory->is_default
            : $request->boolean('is_default');

        $isDefault = $isDefault && $validated['status'] === 'active';

        return [
            ...$validated,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_default' => $isDefault,
        ];
    }

    protected function storeSignature(Request $request, Signatory $signatory): void
    {
        if ($request->hasFile('signature')) {
            $this->deleteSignature($signatory);
            $signatory->update([
                'signature_path' => $request->file('signature')->store('signatures', 'local'),
            ]);

            return;
        }

        if ($request->boolean('remove_signature')) {
            $this->deleteSignature($signatory);
            $signatory->update(['signature_path' => null]);
        }
    }

    /**
     * Only the current file goes: a letter that froze this path keeps its own
     * copy of the image, so removing the specimen never blanks an issued PDF.
     */
    protected function deleteSignature(Signatory $signatory): void
    {
        if (! $signatory->signature_path) {
            return;
        }

        $stillInUse = $signatory->letters()
            ->where('signature_path', $signatory->signature_path)
            ->exists();

        if (! $stillInUse) {
            Storage::disk('local')->delete($signatory->signature_path);
        }
    }

    protected function assertBelongs(Company $company, Signatory $signatory): void
    {
        abort_unless($signatory->company_id === $company->id, 404);
    }
}
