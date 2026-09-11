<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveRequestRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveApiController extends Controller
{
    public function __construct(
        protected LeaveService $leave,
        protected NotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        $requests = LeaveRequest::with(['leaveType', 'approver'])
            ->where('employee_id', $employee->id)
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->integer('year'), fn ($q, $v) => $q->whereYear('start_date', $v))
            ->orderByDesc('start_date')
            ->paginate($request->integer('per_page') ?: 20);

        return response()->json([
            'data' => LeaveRequestResource::collection($requests->items()),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    public function types(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        return response()->json([
            'data' => LeaveType::active()->orderBy('name')->get()
                ->filter(fn (LeaveType $t) => $t->isApplicableTo($employee))
                ->map(fn (LeaveType $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'code' => $t->code,
                    'is_paid' => $t->is_paid,
                    'allow_half_day' => $t->allow_half_day,
                    'min_notice_days' => $t->min_notice_days,
                    'max_consecutive_days' => $t->max_consecutive_days,
                    'requires_attachment' => $t->requires_attachment,
                    'color' => $t->color,
                ])->values(),
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $year = $request->integer('year') ?: (int) date('Y');

        return response()->json([
            'year' => $year,
            'data' => $this->leave->balanceSummary($employee, $year)->map(fn (array $row) => [
                'leave_type' => [
                    'id' => $row['leave_type']->id,
                    'name' => $row['leave_type']->name,
                    'code' => $row['leave_type']->code,
                    'color' => $row['leave_type']->color,
                ],
                'allocated' => $row['allocated'],
                'carried_forward' => $row['carried_forward'],
                'entitled' => $row['entitled'],
                'used' => $row['used'],
                'pending' => $row['pending'],
                'remaining' => $row['remaining'],
            ])->values(),
        ]);
    }

    public function store(LeaveRequestRequest $request): JsonResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $employee = $this->employee($request);
        $data = $request->validated();

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('leave/attachments');
        }

        $leaveRequest = $this->leave->apply($employee, $data);

        if ($leaveRequest->isPending()) {
            $this->notifications->notifyLeaveSubmitted($leaveRequest);
        }

        return response()->json([
            'message' => 'Leave request '.$leaveRequest->reference.' submitted.',
            'data' => new LeaveRequestResource($leaveRequest->load(['leaveType', 'employee'])),
        ], 201);
    }

    public function show(Request $request, LeaveRequest $leave): JsonResponse
    {
        $this->authorize('view', $leave);

        return response()->json([
            'data' => new LeaveRequestResource($leave->load(['leaveType', 'approver', 'employee'])),
        ]);
    }

    public function cancel(Request $request, LeaveRequest $leave): JsonResponse
    {
        $this->authorize('cancel', $leave);

        $validated = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $leave = $this->leave->cancel($leave, $validated['cancel_reason'] ?? null);

        $this->notifications->notifyLeaveCancelled($leave);

        return response()->json([
            'message' => 'Leave request cancelled.',
            'data' => new LeaveRequestResource($leave->load('leaveType')),
        ]);
    }

    /** Requests awaiting the authenticated approver. */
    public function pendingApprovals(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('leave.approve'), 403);

        $user = $request->user();

        $requests = LeaveRequest::with(['employee.department', 'leaveType'])
            ->pending()
            ->when(! $user->can('leave.view-all') && $user->employee, fn ($q) => $q
                ->whereHas('employee', fn ($e) => $e
                    ->where('reporting_to', $user->employee->id)
                    ->orWhere('branch_id', $user->employee->branch_id)))
            ->orderBy('start_date')
            ->paginate($request->integer('per_page') ?: 20);

        return response()->json([
            'data' => LeaveRequestResource::collection($requests->items()),
            'meta' => ['total' => $requests->total()],
        ]);
    }

    public function approve(Request $request, LeaveRequest $leave): JsonResponse
    {
        $this->authorize('approve', $leave);

        $validated = $request->validate([
            'approver_remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $leave = $this->leave->approve($leave, $request->user(), $validated['approver_remarks'] ?? null);
        $this->notifications->notifyLeaveActioned($leave);

        return response()->json([
            'message' => 'Leave approved.',
            'data' => new LeaveRequestResource($leave->load('leaveType')),
        ]);
    }

    public function reject(Request $request, LeaveRequest $leave): JsonResponse
    {
        $this->authorize('approve', $leave);

        $validated = $request->validate([
            'approver_remarks' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $leave = $this->leave->reject($leave, $request->user(), $validated['approver_remarks']);
        $this->notifications->notifyLeaveActioned($leave);

        return response()->json([
            'message' => 'Leave rejected.',
            'data' => new LeaveRequestResource($leave->load('leaveType')),
        ]);
    }

    protected function employee(Request $request)
    {
        $employee = $request->user()->employee;

        abort_unless($employee, 404, 'No employee record is linked to your account.');

        return $employee;
    }
}
