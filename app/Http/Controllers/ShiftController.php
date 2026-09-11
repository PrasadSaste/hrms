<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('shifts.view'), 403);

        return view('shifts.index', [
            'shifts' => Shift::with('branch')->withCount('employees')->orderBy('name')->paginate(20),
            'branches' => Branch::active()->orderBy('name')->get(),
            'canManage' => $request->user()->can('shifts.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('shifts.manage'), 403);

        return view('shifts.create', [
            'shift' => new Shift([
                'start_time' => '09:30:00',
                'end_time' => '18:30:00',
                'grace_minutes' => 15,
                'break_minutes' => 60,
                'half_day_hours' => 4,
                'full_day_hours' => 8,
                'working_days' => [1, 2, 3, 4, 5],
                'status' => 'active',
            ]),
            'branches' => Branch::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('shifts.manage'), 403);

        Shift::create($this->validated($request));

        return redirect()->route('shifts.index')->with('success', 'Shift created.');
    }

    public function edit(Request $request, Shift $shift): View
    {
        abort_unless($request->user()->can('shifts.manage'), 403);

        return view('shifts.edit', [
            'shift' => $shift,
            'branches' => Branch::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Shift $shift): RedirectResponse
    {
        abort_unless($request->user()->can('shifts.manage'), 403);

        $shift->update($this->validated($request, $shift->id));

        return redirect()->route('shifts.index')->with('success', 'Shift updated.');
    }

    public function destroy(Request $request, Shift $shift): RedirectResponse
    {
        abort_unless($request->user()->can('shifts.manage'), 403);

        if ($shift->employees()->exists()) {
            return back()->withErrors(['delete' => 'Employees are assigned to this shift. Reassign them first.']);
        }

        $shift->delete();

        return redirect()->route('shifts.index')->with('success', 'Shift removed.');
    }

    protected function validated(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'alpha_dash', Rule::unique('shifts', 'code')->ignore($id)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'grace_minutes' => ['required', 'integer', 'between:0,240'],
            'break_minutes' => ['required', 'integer', 'between:0,240'],
            'half_day_hours' => ['required', 'numeric', 'between:0,24'],
            'full_day_hours' => ['required', 'numeric', 'between:0,24'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $data['is_default'] = $request->boolean('is_default');
        $data['working_days'] = array_map('intval', $data['working_days']);

        return $data;
    }
}
