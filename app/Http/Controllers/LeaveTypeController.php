<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaveTypeController extends Controller
{
    public function index(): View
    {
        $this->authorize('manageTypes', LeaveRequest::class);

        return view('leave.types.index', [
            'types' => LeaveType::withCount('requests')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('manageTypes', LeaveRequest::class);

        return view('leave.types.create', [
            'type' => new LeaveType([
                'status' => 'active',
                'is_paid' => true,
                'requires_approval' => true,
                'allow_half_day' => true,
                'color' => '#2563eb',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageTypes', LeaveRequest::class);

        $type = LeaveType::create($this->validated($request));

        return redirect()->route('leave-types.index')
            ->with('success', 'Leave type "'.$type->name.'" was created.');
    }

    public function edit(LeaveType $leaveType): View
    {
        $this->authorize('manageTypes', LeaveRequest::class);

        return view('leave.types.edit', ['type' => $leaveType]);
    }

    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $this->authorize('manageTypes', LeaveRequest::class);

        $leaveType->update($this->validated($request, $leaveType->id));

        return redirect()->route('leave-types.index')
            ->with('success', 'Leave type updated.');
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        $this->authorize('manageTypes', LeaveRequest::class);

        if ($leaveType->requests()->exists()) {
            return back()->withErrors([
                'delete' => 'This leave type is in use and cannot be deleted. Set it to inactive instead.',
            ]);
        }

        $leaveType->delete();

        return redirect()->route('leave-types.index')->with('success', 'Leave type removed.');
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'days_per_month.required_if' => 'Say how many days a month this type earns.',
            'accrual_starts_on.required_if' => 'Say which month to start crediting from. '
                .'Everything before it is left alone.',
        ];
    }

    protected function validated(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'alpha_dash', Rule::unique('leave_types', 'code')->ignore($id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'days_per_year' => ['required', 'numeric', 'between:0,365'],
            'accrual' => ['required', Rule::in([LeaveType::ACCRUAL_YEARLY, LeaveType::ACCRUAL_MONTHLY])],
            'days_per_month' => ['nullable', 'required_if:accrual,monthly', 'numeric', 'between:0,31'],
            'probation_days_per_month' => ['nullable', 'numeric', 'between:0,31'],
            'accrue_in_advance' => ['nullable', 'boolean'],
            'accrual_starts_on' => ['nullable', 'required_if:accrual,monthly', 'date'],
            'is_paid' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
            'allow_half_day' => ['nullable', 'boolean'],
            'carry_forward' => ['nullable', 'boolean'],
            'max_carry_forward_days' => ['required', 'numeric', 'between:0,365'],
            'max_consecutive_days' => ['required', 'integer', 'between:0,365'],
            'min_notice_days' => ['required', 'integer', 'between:0,365'],
            'applicable_gender' => ['required', Rule::in(['any', 'male', 'female', 'other'])],
            'applicable_after_months' => ['required', 'integer', 'between:0,120'],
            'requires_attachment' => ['nullable', 'boolean'],
            'color' => ['required', 'string', 'max:16'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], $this->messages());

        foreach (['is_paid', 'requires_approval', 'allow_half_day', 'carry_forward', 'requires_attachment', 'accrue_in_advance'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        // A yearly type keeps its rates on file but does not use them, so
        // switching back and forth does not lose what was typed.
        if ($data['accrual'] === LeaveType::ACCRUAL_YEARLY) {
            $data['accrual_starts_on'] = $data['accrual_starts_on'] ?? null;
        }

        $data['days_per_month'] = $data['days_per_month'] ?? 0;

        return $data;
    }
}
