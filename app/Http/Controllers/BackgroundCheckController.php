<?php

namespace App\Http\Controllers;

use App\Models\BackgroundCheck;
use App\Models\BackgroundCheckItem;
use App\Models\Employee;
use App\Services\BackgroundCheckService;
use App\Support\BackgroundCheckRequirements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HR's side of background verification: who is outstanding, what they sent, and
 * the decision that onboards them.
 */
class BackgroundCheckController extends Controller
{
    public function __construct(protected BackgroundCheckService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('bgv.view'), 403);

        $status = $request->query('status');

        $checks = BackgroundCheck::query()
            ->with(['employee.designation', 'employee.branch', 'items'])
            ->whereHas('employee', fn ($q) => $q->visibleTo($request->user()))
            ->when($status && array_key_exists($status, BackgroundCheck::STATUSES),
                fn ($q) => $q->where('status', $status))
            // Waiting on us first, then waiting on them, then the finished
            // ones. A CASE keeps this working on SQLite as well as MySQL.
            ->orderByRaw(
                'case status'
                ." when 'submitted' then 1"
                ." when 'changes_requested' then 2"
                ." when 'in_progress' then 3"
                ." when 'invited' then 4"
                .' else 5 end'
            )
            ->orderByDesc('submitted_at')
            ->paginate(20)
            ->withQueryString();

        return view('background-checks.index', [
            'checks' => $checks,
            'status' => $status,
            'counts' => $this->counts($request),
            'employeesWithout' => $this->employeesWithoutCheck($request),
            'requirements' => BackgroundCheckRequirements::all(),
        ]);
    }

    public function show(Request $request, BackgroundCheck $check): View
    {
        abort_unless($request->user()->can('bgv.view'), 403);
        $this->authorize('view', $check->employee);

        return view('background-checks.show', [
            'check' => $check->load(['employee.designation', 'employee.branch', 'items.reviewer', 'reviewer', 'inviter']),
            'requirements' => BackgroundCheckRequirements::all(),
            'canManage' => $request->user()->can('bgv.manage'),
        ]);
    }

    /** Ask somebody to complete their verification. */
    public function invite(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('bgv.manage'), 403);

        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'due_on' => ['nullable', 'date', 'after_or_equal:today'],
            'requirements' => ['required', 'array', 'min:1'],
            'requirements.*' => ['string', Rule::in(BackgroundCheckRequirements::keys())],
        ], [
            'requirements.required' => 'Tick at least one document to ask for.',
        ], [
            'employee_id' => 'employee',
            'due_on' => 'completion date',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $this->authorize('view', $employee);

        $check = $this->service->invite(
            $employee,
            $validated['requirements'],
            $request->user(),
            $validated['due_on'] ?? null,
        );

        return redirect()->route('background-checks.show', $check)
            ->with('success', $employee->full_name.' has been asked for their documents.');
    }

    /** Change the list of documents after the invitation has gone out. */
    public function updateRequirements(Request $request, BackgroundCheck $check): RedirectResponse
    {
        abort_unless($request->user()->can('bgv.manage'), 403);
        $this->authorize('view', $check->employee);

        if ($check->isVerified()) {
            return back()->with('error', 'This verification is already complete, so the list cannot change.');
        }

        $validated = $request->validate([
            'due_on' => ['nullable', 'date'],
            'requirements' => ['required', 'array', 'min:1'],
            'requirements.*' => ['string', Rule::in(BackgroundCheckRequirements::keys())],
        ], [
            'requirements.required' => 'Keep at least one document on the list.',
        ], [
            'due_on' => 'completion date',
        ]);

        $result = $this->service->updateRequirements(
            $check,
            $validated['requirements'],
            $validated['due_on'] ?? null,
        );

        $label = fn (array $keys) => collect($keys)->map(fn ($k) => BackgroundCheckRequirements::label($k))->join(', ', ' and ');

        $message = match (true) {
            $result['added'] && $result['removed'] => 'Added '.$label($result['added']).'; removed '.$label($result['removed']).'.',
            (bool) $result['added'] => 'Added '.$label($result['added']).'. '.$check->employee->first_name.' has been emailed the revised list.',
            (bool) $result['removed'] => 'Removed '.$label($result['removed']).'.',
            default => 'The list is unchanged.',
        };

        return redirect()->route('background-checks.show', $check)->with('success', $message);
    }

    /** Accept or send back one document. */
    public function reviewItem(Request $request, BackgroundCheckItem $item): RedirectResponse
    {
        abort_unless($request->user()->can('bgv.manage'), 403);
        $this->authorize('view', $item->check->employee);

        $validated = $request->validate([
            'decision' => ['required', 'in:verify,reject'],
            'remarks' => ['nullable', 'string', 'max:1000', 'required_if:decision,reject'],
        ], [], ['remarks' => 'reason']);

        if ($validated['decision'] === 'verify') {
            $this->service->verifyItem($item, $request->user(), $validated['remarks'] ?? null);

            return back()->with('success', $item->label().' accepted.');
        }

        $this->service->rejectItem($item, $request->user(), $validated['remarks']);

        return back()->with('success', $item->label().' sent back. Use “Ask for changes” when you have finished reviewing.');
    }

    /** Hand the case back to the employee. */
    public function requestChanges(Request $request, BackgroundCheck $check): RedirectResponse
    {
        abort_unless($request->user()->can('bgv.manage'), 403);
        $this->authorize('view', $check->employee);

        $validated = $request->validate([
            'review_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->requestChanges($check, $request->user(), $validated['review_remarks'] ?? null);

        return back()->with('success', $check->employee->full_name.' has been asked for the outstanding documents.');
    }

    /** Clear the check and onboard the employee. */
    public function complete(Request $request, BackgroundCheck $check): RedirectResponse
    {
        abort_unless($request->user()->can('bgv.manage'), 403);
        $this->authorize('view', $check->employee);

        $validated = $request->validate([
            'review_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $check->load('items');

        if ($check->items->contains(fn (BackgroundCheckItem $item) => $item->is_required && ! $item->hasUpload())) {
            return back()->with('error', 'Some required documents have not been provided yet.');
        }

        $this->service->complete($check, $request->user(), $validated['review_remarks'] ?? null);

        return back()->with('success', $check->employee->full_name.' is verified and onboarded.');
    }

    /**
     * Stream one uploaded document.
     *
     * These are passports and bank details, so they are stored off the public
     * disk and served only to people who may see that employee.
     */
    public function document(Request $request, BackgroundCheckItem $item): StreamedResponse
    {
        $employee = $item->check->employee;
        $isOwner = $request->user()->employee?->is($employee) ?? false;

        abort_unless($isOwner || $request->user()->can('bgv.view'), 403);

        if (! $isOwner) {
            $this->authorize('view', $employee);
        }

        return $this->service->download($item);
    }

    /** How many cases sit at each stage, for the filter chips. */
    protected function counts(Request $request): array
    {
        return BackgroundCheck::query()
            ->whereHas('employee', fn ($q) => $q->visibleTo($request->user()))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    /** People who have never been asked, so HR can invite them from here. */
    protected function employeesWithoutCheck(Request $request)
    {
        return Employee::query()
            ->visibleTo($request->user())
            ->where('status', 'active')
            ->whereDoesntHave('backgroundCheck')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'employee_code']);
    }
}
