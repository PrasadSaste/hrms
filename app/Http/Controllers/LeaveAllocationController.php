<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveAllocation;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveAllocationController extends Controller
{
    public function __construct(protected LeaveService $leave) {}

    public function index(Request $request): View
    {
        $this->authorize('manageAllocations', LeaveRequest::class);

        $year = $request->integer('year') ?: (int) date('Y');

        $allocations = LeaveAllocation::query()
            ->with(['employee.department', 'leaveType'])
            ->where('year', $year)
            ->when($request->integer('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->integer('leave_type_id'), fn ($q, $v) => $q->where('leave_type_id', $v))
            ->when($request->integer('branch_id'), fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $v)))
            ->join('employees', 'employees.id', '=', 'leave_allocations.employee_id')
            ->orderBy('employees.employee_code')
            ->select('leave_allocations.*')
            ->paginate(25)
            ->withQueryString();

        return view('leave.allocations.index', [
            'allocations' => $allocations,
            'year' => $year,
            'leaveTypes' => LeaveType::active()->orderBy('name')->get(),
            'branches' => Branch::active()->orderBy('name')->get(),
            'employees' => Employee::active()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_code']),
        ]);
    }

    /** Grant the year's entitlement to everyone, with carry-forward applied. */
    public function bulkAllocate(Request $request): RedirectResponse
    {
        $this->authorize('manageAllocations', LeaveRequest::class);

        $validated = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $count = $this->leave->allocateYear($validated['year'], $validated['branch_id'] ?? null);

        return back()->with('success', $count.' leave allocation(s) created or refreshed for '.$validated['year'].'.');
    }

    public function update(Request $request, LeaveAllocation $allocation): RedirectResponse
    {
        $this->authorize('manageAllocations', LeaveRequest::class);

        $validated = $request->validate([
            'allocated_days' => ['required', 'numeric', 'between:0,365'],
            'carried_forward_days' => ['required', 'numeric', 'between:0,365'],
            'used_days' => ['required', 'numeric', 'between:0,365'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $allocation->update($validated);

        return back()->with('success', 'Allocation updated.');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageAllocations', LeaveRequest::class);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'allocated_days' => ['required', 'numeric', 'between:0,365'],
            'carried_forward_days' => ['nullable', 'numeric', 'between:0,365'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        LeaveAllocation::updateOrCreate(
            [
                'employee_id' => $validated['employee_id'],
                'leave_type_id' => $validated['leave_type_id'],
                'year' => $validated['year'],
            ],
            [
                'allocated_days' => $validated['allocated_days'],
                'carried_forward_days' => $validated['carried_forward_days'] ?? 0,
                'notes' => $validated['notes'] ?? null,
            ],
        );

        return back()->with('success', 'Allocation saved.');
    }
}
