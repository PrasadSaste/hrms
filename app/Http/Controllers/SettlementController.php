<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Settlement;
use App\Models\SettlementLine;
use App\Services\AssetService;
use App\Services\SettlementPdfService;
use App\Services\SettlementService;
use App\Support\SettlementLines;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Full and final settlements.
 *
 * The arithmetic is all in {@see SettlementService}; this asks, shows and
 * records. The one rule enforced here rather than there is who may do what:
 * whoever prepares a settlement is generally not whoever approves it.
 */
class SettlementController extends Controller
{
    public function __construct(
        protected SettlementService $settlements,
        protected AssetService $assets,
        protected SettlementPdfService $pdf,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('settlements.view'), 403);

        $query = Settlement::with(['employee.designation', 'employee.branch', 'company'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when(trim((string) $request->query('q', '')), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn ($e) => $e
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_code', 'like', "%{$search}%"));
                });
            });

        return view('settlements.index', [
            'settlements' => $query->orderByDesc('last_working_day')->paginate(20)->withQueryString(),
            'statuses' => Settlement::STATUSES,
            'filters' => [
                'status' => $request->string('status')->toString(),
                'q' => trim((string) $request->query('q', '')),
            ],
            // Anybody already gone with nothing prepared for them.
            'awaiting' => Employee::query()
                ->visibleTo($request->user())
                ->whereNotNull('date_of_exit')
                ->whereDoesntHave('settlement')
                ->orderByDesc('date_of_exit')
                ->take(10)
                ->get(),
            'canManage' => $request->user()->can('settlements.manage'),
        ]);
    }

    /** The figures before anything is written down. */
    public function create(Request $request): View
    {
        abort_unless($request->user()->can('settlements.manage'), 403);

        $employee = $request->integer('employee_id')
            ? Employee::visibleTo($request->user())->findOrFail($request->integer('employee_id'))
            : null;

        $lastDay = $request->date('last_working_day')
            ?? $employee?->date_of_exit
            ?? Carbon::today();

        return view('settlements.create', [
            'employees' => Employee::visibleTo($request->user())
                ->whereDoesntHave('settlement')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'employee_code', 'date_of_exit']),
            'employee' => $employee,
            'lastDay' => $lastDay,
            'preview' => $employee ? $this->settlements->preview($employee, $lastDay) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settlements.manage'), 403);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'last_working_day' => ['required', 'date'],
            'exit_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $employee = Employee::visibleTo($request->user())->findOrFail($validated['employee_id']);

        $settlement = $this->settlements->prepare($employee, $validated, $request->user());

        return redirect()->route('settlements.show', $settlement)
            ->with('success', 'Settlement '.$settlement->reference.' prepared. Check every line before approving it.');
    }

    public function show(Request $request, Settlement $settlement): View
    {
        abort_unless(
            $request->user()->can('settlements.view')
                || $request->user()->employee?->is($settlement->employee),
            403,
        );

        return view('settlements.show', [
            'settlement' => $settlement->load(['employee.designation', 'employee.branch', 'company', 'lines', 'preparer', 'approver']),
            'outstandingAssets' => $this->assets->outstandingFor($settlement->employee),
            'earningOptions' => SettlementLines::options(SettlementLines::EARNING),
            'deductionOptions' => SettlementLines::options(SettlementLines::DEDUCTION),
            'canManage' => $request->user()->can('settlements.manage'),
            'canApprove' => $request->user()->can('settlements.approve'),
        ]);
    }

    public function addLine(Request $request, Settlement $settlement): RedirectResponse
    {
        abort_unless($request->user()->can('settlements.manage'), 403);

        $validated = $request->validate([
            'key' => ['required', Rule::in(SettlementLines::manual())],
            'label' => ['nullable', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'basis' => ['nullable', 'string', 'max:255'],
        ]);

        $this->settlements->addLine($settlement, $validated);

        return back()->with('success', 'Line added.');
    }

    public function removeLine(Request $request, Settlement $settlement, SettlementLine $line): RedirectResponse
    {
        abort_unless($request->user()->can('settlements.manage'), 403);

        $this->settlements->removeLine($settlement, $line);

        return back()->with('success', 'Line removed.');
    }

    public function approve(Request $request, Settlement $settlement): RedirectResponse
    {
        abort_unless($request->user()->can('settlements.approve'), 403);

        $this->settlements->approve($settlement, $request->user());

        return back()->with('success', 'Approved. The figures are fixed and '
            .$settlement->employee->first_name.' has been sent the statement.');
    }

    public function markPaid(Request $request, Settlement $settlement): RedirectResponse
    {
        abort_unless($request->user()->can('settlements.approve'), 403);

        $validated = $request->validate([
            'settled_on' => ['nullable', 'date'],
        ]);

        $this->settlements->markPaid(
            $settlement,
            isset($validated['settled_on']) ? Carbon::parse($validated['settled_on']) : null,
        );

        return back()->with('success', 'Marked paid, and a receipt has gone out.');
    }

    public function download(Request $request, Settlement $settlement): Response
    {
        abort_unless(
            $request->user()->can('settlements.view')
                || $request->user()->employee?->is($settlement->employee),
            403,
        );

        return $this->pdf->make($settlement)->download($settlement->filename());
    }

    /** An employee's own settlement, when there is one. */
    public function mine(Request $request): View
    {
        abort_unless($request->user()->can('settlements.view-own'), 403);

        $employee = $request->user()->employee;

        abort_unless($employee, 404, 'No employee record is linked to your account.');

        $settlement = $employee->settlement()->with('lines')->first();

        abort_unless($settlement, 404, 'There is no settlement for you.');

        // A draft is somebody's working out, not a statement. It is not shown
        // until it has been approved.
        abort_unless($settlement->isApproved(), 404, 'Your settlement is still being prepared.');

        return view('settlements.mine', ['settlement' => $settlement]);
    }
}
