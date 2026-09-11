<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Letter;
use App\Models\Signatory;
use App\Services\AssetService;
use App\Services\LetterPdfService;
use App\Services\LetterService;
use App\Services\NotificationService;
use App\Support\LetterTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Issuing the letters a company gives its people, and the register of them.
 */
class LetterController extends Controller
{
    public function __construct(
        protected LetterService $letters,
        protected LetterPdfService $pdf,
        protected NotificationService $notifications,
        protected AssetService $assets,
    ) {}

    /** Everything issued, newest first. */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Letter::class);
        abort_unless($request->user()->can('letters.view'), 403);

        $letters = Letter::query()
            ->with(['employee.designation', 'issuer', 'company'])
            ->whereHas('employee', fn ($q) => $q->visibleTo($request->user()))
            ->ofType($request->string('type')->toString() ?: null)
            ->when($request->integer('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->string('search')->toString(), fn ($q, $term) => $q
                ->where(fn ($sub) => $sub->where('reference', 'like', "%{$term}%")
                    ->orWhereHas('employee', fn ($e) => $e->search($term))))
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('letters.index', [
            'letters' => $letters,
            'types' => LetterTypes::options(),
            'grouped' => LetterTypes::grouped(),
            'counts' => Letter::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
        ]);
    }

    /** Choose what to issue and to whom. */
    public function create(Request $request): View
    {
        $this->authorize('create', Letter::class);

        $employee = $request->integer('employee_id')
            ? Employee::visibleTo($request->user())->findOrFail($request->integer('employee_id'))
            : null;

        $type = $request->string('type')->toString();
        $type = LetterTypes::exists($type) ? $type : null;

        // Who signs: whoever was picked, else the company's default. The
        // preview shows the name it will actually go out over.
        $signatories = $employee?->company?->signatories()->active()->ordered()->get() ?? collect();
        $signatory = $signatories->firstWhere('id', $request->integer('signatory_id'))
            ?: $signatories->first();

        $preview = null;

        if ($employee && $type) {
            $preview = $this->letters->preview($employee, $type, $request->input('fields', []), $signatory);
        }

        return view('letters.create', [
            'employees' => Employee::visibleTo($request->user())->active()->orderBy('first_name')->get(),
            'employee' => $employee,
            'type' => $type,
            'definition' => $type ? LetterTypes::find($type) : null,
            'grouped' => LetterTypes::grouped(),
            'fields' => $request->input('fields', []),
            'preview' => $preview,
            'signatories' => $signatories,
            'signatory' => $signatory,
            // A relieving letter says somebody has left cleanly. Saying so
            // while they still have the laptop is how a laptop is lost, so
            // what they hold is put in front of whoever is about to sign it.
            'outstandingAssets' => $employee && $type === LetterTypes::RELIEVING
                ? $this->assets->outstandingFor($employee)
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Letter::class);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'type' => ['required', 'string', Rule::in(LetterTypes::keys())],
            'fields' => ['nullable', 'array'],
            'signatory_id' => ['nullable', 'integer', 'exists:signatories,id'],
            'send_email' => ['nullable', 'boolean'],
        ]);

        $employee = Employee::visibleTo($request->user())->findOrFail($validated['employee_id']);

        // Whose name goes at the foot. A signatory belonging to another company
        // is refused by the service rather than quietly swapped.
        $signatory = ! empty($validated['signatory_id'])
            ? Signatory::find($validated['signatory_id'])
            : null;

        $letter = $this->letters->issue(
            $employee,
            $validated['type'],
            $validated['fields'] ?? [],
            $request->user(),
            $signatory,
        );

        if ($request->boolean('send_email')) {
            $this->notifications->sendLetter($letter);
        }

        return redirect()->route('letters.show', $letter)->with('success', sprintf(
            '%s issued as %s%s.',
            $letter->typeLabel(),
            $letter->reference,
            $request->boolean('send_email') ? ' and emailed to '.$employee->email : '',
        ));
    }

    /** The letter as it was issued. */
    public function show(Request $request, Letter $letter): View
    {
        $this->authorize('view', $letter);

        return view('letters.show', [
            'letter' => $letter->load(['employee.designation', 'employee.department', 'company', 'issuer']),
            'definition' => LetterTypes::find($letter->type),
            'signature' => $letter->signatureDataUri(),
        ]);
    }

    public function download(Request $request, Letter $letter): Response
    {
        $this->authorize('download', $letter);

        return $this->pdf->make($letter)->download($letter->filename());
    }

    /** Send it to the employee with the PDF attached. */
    public function email(Request $request, Letter $letter): RedirectResponse
    {
        $this->authorize('send', $letter);

        $this->notifications->sendLetter($letter);

        return back()->with('success', 'Letter emailed to '.$letter->employee->email.'.');
    }

    public function destroy(Request $request, Letter $letter): RedirectResponse
    {
        $this->authorize('delete', $letter);

        $reference = $letter->reference;
        $letter->delete();

        return redirect()->route('letters.index')
            ->with('success', 'Letter '.$reference.' was removed.');
    }
}
