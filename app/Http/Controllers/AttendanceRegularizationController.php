<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Models\Attendance;
use App\Models\AttendanceRegularization;
use App\Services\AttendanceService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AttendanceRegularizationController extends Controller
{
    public function __construct(
        protected AttendanceService $attendance,
        protected NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $canApprove = $user->can('attendance.approve-regularization');

        $requests = AttendanceRegularization::query()
            ->with(['employee.department', 'reviewer'])
            ->when(! $canApprove, fn ($q) => $q->where('employee_id', $user->employee?->id))
            ->when($canApprove && ! $user->hasOrganisationScope() && $user->employee, function ($q) use ($user) {
                $q->whereHas('employee', fn ($e) => $e->where('branch_id', $user->employee->branch_id));
            })
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest('date')
            ->paginate(15)
            ->withQueryString();

        return view('attendance.regularizations', [
            'requests' => $requests,
            'canApprove' => $canApprove,
            'statuses' => LeaveStatus::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;

        abort_unless($employee, 404, 'No employee record is linked to your account.');

        $validated = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'requested_check_in' => ['nullable', 'date_format:H:i'],
            'requested_check_out' => ['nullable', 'date_format:H:i', 'after:requested_check_in'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        $exists = AttendanceRegularization::where('employee_id', $employee->id)
            ->whereDate('date', $date->toDateString())
            ->where('status', LeaveStatus::Pending->value)
            ->exists();

        if ($exists) {
            return back()->withErrors(['date' => 'You already have a pending request for this date.']);
        }

        $regularization = AttendanceRegularization::create([
            'employee_id' => $employee->id,
            'attendance_id' => Attendance::where('employee_id', $employee->id)
                ->whereDate('date', $date->toDateString())
                ->value('id'),
            'date' => $date,
            'requested_check_in' => $validated['requested_check_in']
                ? Carbon::parse($date->toDateString().' '.$validated['requested_check_in'])
                : null,
            'requested_check_out' => $validated['requested_check_out']
                ? Carbon::parse($date->toDateString().' '.$validated['requested_check_out'])
                : null,
            'reason' => $validated['reason'],
            'status' => LeaveStatus::Pending,
        ]);

        $this->notifications->notifyRegularizationSubmitted($regularization);

        return back()->with('success', 'Regularisation request submitted for approval.');
    }

    public function approve(Request $request, AttendanceRegularization $regularization): RedirectResponse
    {
        $this->authorize('approveRegularization', Attendance::class);

        abort_unless($regularization->isPending(), 422, 'This request has already been reviewed.');

        $validated = $request->validate([
            'review_remarks' => ['nullable', 'string', 'max:500'],
        ]);

        // Applying the request writes the corrected attendance row.
        $this->attendance->record($regularization->employee, [
            'date' => $regularization->date->toDateString(),
            'check_in' => $regularization->requested_check_in?->format('H:i'),
            'check_out' => $regularization->requested_check_out?->format('H:i'),
            'source' => 'manual',
            'remarks' => 'Regularised: '.$regularization->reason,
        ], $request->user()->id);

        $regularization->update([
            'status' => LeaveStatus::Approved,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_remarks' => $validated['review_remarks'] ?? null,
        ]);

        $this->notifications->notifyRegularizationActioned($regularization->fresh('employee'));

        return back()->with('success', 'Regularisation approved and attendance updated.');
    }

    public function reject(Request $request, AttendanceRegularization $regularization): RedirectResponse
    {
        $this->authorize('approveRegularization', Attendance::class);

        abort_unless($regularization->isPending(), 422, 'This request has already been reviewed.');

        $validated = $request->validate([
            'review_remarks' => ['required', 'string', 'max:500'],
        ]);

        $regularization->update([
            'status' => LeaveStatus::Rejected,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_remarks' => $validated['review_remarks'],
        ]);

        $this->notifications->notifyRegularizationActioned($regularization->fresh('employee'));

        return back()->with('success', 'Regularisation rejected.');
    }
}
