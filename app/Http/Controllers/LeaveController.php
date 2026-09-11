<?php

namespace App\Http\Controllers;

use App\Enums\DayType;
use App\Enums\LeaveStatus;
use App\Http\Requests\LeaveRequestRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveAccrualService;
use App\Services\LeaveService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveController extends Controller
{
    public function __construct(
        protected LeaveService $leave,
        protected NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $user = $request->user();

        $requests = $this->scopedQuery($request)
            ->with(['employee.department', 'leaveType', 'approver'])
            ->orderByDesc('applied_on')
            ->paginate(15)
            ->withQueryString();

        return view('leave.index', [
            'requests' => $requests,
            'statuses' => LeaveStatus::options(),
            'leaveTypes' => LeaveType::active()->orderBy('name')->get(),
            'branches' => Branch::active()->orderBy('name')->get(),
            'departments' => Department::active()->orderBy('name')->get(),
            'balance' => $user->employee ? $this->leave->balanceSummary($user->employee) : collect(),
            'canApprove' => $user->can('leave.approve'),
            'pendingCount' => (clone $this->scopedQuery($request))->pending()->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', LeaveRequest::class);

        $employee = $this->requireEmployee($request);

        return view('leave.create', [
            'leaveTypes' => LeaveType::active()->orderBy('name')->get()
                ->filter(fn (LeaveType $t) => $t->isApplicableTo($employee))
                ->values(),
            'balance' => $this->leave->balanceSummary($employee),
            'dayTypes' => DayType::options(),
        ]);
    }

    public function store(LeaveRequestRequest $request): RedirectResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $employee = $this->requireEmployee($request);
        $data = $request->validated();

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('leave/attachments');
        }

        $leaveRequest = $this->leave->apply($employee, $data);

        if ($leaveRequest->isPending()) {
            $this->notifications->notifyLeaveSubmitted($leaveRequest);
            $message = 'Leave request '.$leaveRequest->reference.' submitted for approval.';
        } else {
            $message = 'Leave request '.$leaveRequest->reference.' was auto-approved.';
        }

        return redirect()->route('leave.show', $leaveRequest)->with('success', $message);
    }

    public function show(LeaveRequest $leave): View
    {
        $this->authorize('view', $leave);

        $leave->load(['employee.department', 'employee.manager', 'leaveType', 'approver']);

        return view('leave.show', [
            'request' => $leave,
            'balance' => $this->leave->balanceSummary($leave->employee, (int) $leave->start_date->format('Y')),
        ]);
    }

    public function approve(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorize('approve', $leave);

        $validated = $request->validate([
            'approver_remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $leave = $this->leave->approve($leave, $request->user(), $validated['approver_remarks'] ?? null);

        $this->notifications->notifyLeaveActioned($leave);

        return back()->with('success', 'Leave approved and the balance has been updated.');
    }

    public function reject(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorize('approve', $leave);

        $validated = $request->validate([
            'approver_remarks' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $leave = $this->leave->reject($leave, $request->user(), $validated['approver_remarks']);

        $this->notifications->notifyLeaveActioned($leave);

        return back()->with('success', 'Leave request rejected.');
    }

    public function cancel(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorize('cancel', $leave);

        $validated = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->notifications->notifyLeaveCancelled(
            $this->leave->cancel($leave, $validated['cancel_reason'] ?? null)
        );

        return back()->with('success', 'Leave request cancelled.');
    }

    /** Personal balance screen. */
    public function balance(Request $request): View
    {
        $employee = $this->requireEmployee($request);

        $year = $request->integer('year') ?: (int) date('Y');

        return view('leave.balance', [
            'employee' => $employee,
            'year' => $year,
            'balance' => $this->leave->balanceSummary($employee, $year),
            // What has been credited month by month, so a balance that grows
            // through the year has its arithmetic on the same screen.
            'accruals' => app(LeaveAccrualService::class)->ledgerFor($employee, $year),
            'history' => $employee->leaveRequests()
                ->with('leaveType')
                ->whereYear('start_date', $year)
                ->orderByDesc('start_date')
                ->get(),
        ]);
    }

    /** Team calendar: who is away and when. */
    public function calendar(Request $request): View
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $month = Carbon::createFromDate(
            $request->integer('year') ?: (int) date('Y'),
            $request->integer('month') ?: (int) date('n'),
            1,
        );

        $requests = LeaveRequest::with(['employee', 'leaveType'])
            ->approved()
            ->overlapping($month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString())
            ->when(
                ! $request->user()->hasOrganisationScope() && $request->user()->employee,
                fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $request->user()->employee->branch_id)),
            )
            ->get();

        // date => list of requests covering it
        $byDate = [];
        foreach ($requests as $leaveRequest) {
            foreach ($leaveRequest->datesInRange() as $date) {
                $byDate[$date][] = $leaveRequest;
            }
        }

        return view('leave.calendar', [
            'month' => $month,
            'byDate' => $byDate,
            'requests' => $requests,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $requests = $this->scopedQuery($request)
            ->with(['employee', 'leaveType', 'approver'])
            ->orderByDesc('start_date')
            ->get();

        return response()->streamDownload(function () use ($requests) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Reference', 'Employee Code', 'Employee', 'Leave Type', 'From', 'To',
                'Days', 'Day Type', 'Status', 'Applied On', 'Approver', 'Remarks',
            ]);

            foreach ($requests as $r) {
                fputcsv($handle, [
                    $r->reference,
                    $r->employee?->employee_code,
                    $r->employee?->full_name,
                    $r->leaveType?->name,
                    $r->start_date->toDateString(),
                    $r->end_date->toDateString(),
                    $r->total_days,
                    $r->day_type->label(),
                    $r->status->label(),
                    $r->applied_on?->toDateTimeString(),
                    $r->approver?->name,
                    $r->approver_remarks,
                ]);
            }

            fclose($handle);
        }, 'leave-requests-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Screens that act on "my leave" need a linked employee record. An
     * administrator account without one gets a clear message, not a crash.
     */
    protected function requireEmployee(Request $request): Employee
    {
        $employee = $request->user()->employee;

        abort_if(
            $employee === null,
            404,
            'Your user account is not linked to an employee record, so it has no leave of its own. '
            .'Link it from the employee screen, or use an employee account.',
        );

        return $employee;
    }

    /** Query limited to what the current user may see, plus request filters. */
    protected function scopedQuery(Request $request)
    {
        $user = $request->user();

        return LeaveRequest::query()
            ->when(
                ! $user->can('leave.view-all'),
                function ($q) use ($user) {
                    if ($user->can('leave.view-team') && $user->employee) {
                        $q->whereHas('employee', fn ($e) => $e
                            ->where('branch_id', $user->employee->branch_id)
                            ->orWhere('reporting_to', $user->employee->id)
                            ->orWhere('id', $user->employee->id));
                    } else {
                        $q->where('employee_id', $user->employee?->id ?? 0);
                    }
                }
            )
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->integer('leave_type_id'), fn ($q, $v) => $q->where('leave_type_id', $v))
            ->when($request->integer('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->integer('branch_id'), fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $v)))
            ->when($request->integer('department_id'), fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $v)))
            ->when($request->date('from'), fn ($q, $v) => $q->whereDate('end_date', '>=', $v))
            ->when($request->date('to'), fn ($q, $v) => $q->whereDate('start_date', '<=', $v));
    }
}
