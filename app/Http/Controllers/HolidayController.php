<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('holidays.view'), 403);

        $year = $request->integer('year') ?: (int) date('Y');
        $branchId = $request->integer('branch_id') ?: $request->user()->scopedBranchId();

        return view('holidays.index', [
            'holidays' => Holiday::with('branch')
                ->forBranch($branchId)
                ->inYear($year)
                ->orderBy('date')
                ->get(),
            'year' => $year,
            'branchId' => $branchId,
            'branches' => Branch::active()->orderBy('name')->get(),
            'canManage' => $request->user()->can('holidays.manage'),
            'types' => Holiday::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('holidays.manage'), 403);

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'type' => ['required', Rule::in(array_keys(Holiday::TYPES))],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_recurring' => ['nullable', 'boolean'],
        ]);

        $validated['is_recurring'] = $request->boolean('is_recurring');

        Holiday::create($validated);

        return back()->with('success', 'Holiday added to the calendar.');
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        abort_unless($request->user()->can('holidays.manage'), 403);

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'type' => ['required', Rule::in(array_keys(Holiday::TYPES))],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_recurring' => ['nullable', 'boolean'],
        ]);

        $validated['is_recurring'] = $request->boolean('is_recurring');

        $holiday->update($validated);

        return back()->with('success', 'Holiday updated.');
    }

    public function destroy(Request $request, Holiday $holiday): RedirectResponse
    {
        abort_unless($request->user()->can('holidays.manage'), 403);

        $holiday->delete();

        return back()->with('success', 'Holiday removed.');
    }
}
