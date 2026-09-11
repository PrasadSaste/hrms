<?php

namespace App\Http\Controllers;

use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Branch::class);

        $branches = Branch::query()
            ->withCount(['employees', 'departments'])
            ->with('manager')
            ->when($request->string('search')->toString(), function ($q, $term) {
                $q->where(fn ($sub) => $sub->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('city', 'like', "%{$term}%"));
            })
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('is_head_office')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('branches.index', compact('branches'));
    }

    public function create(): View
    {
        $this->authorize('create', Branch::class);

        return view('branches.create', [
            'branch' => new Branch(['status' => 'active', 'country' => 'India', 'timezone' => config('app.timezone')]),
            'managers' => $this->managerOptions(),
        ]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $this->authorize('create', Branch::class);

        $branch = Branch::create($request->validated());

        return redirect()->route('branches.show', $branch)
            ->with('success', 'Branch "'.$branch->name.'" was created.');
    }

    public function show(Branch $branch): View
    {
        $this->authorize('view', $branch);

        $branch->load(['manager', 'departments' => fn ($q) => $q->withCount('employees')]);

        return view('branches.show', [
            'branch' => $branch,
            'employeeCount' => $branch->employees()->active()->count(),
            'recentEmployees' => $branch->employees()
                ->with('designation')
                ->active()
                ->orderByDesc('date_of_joining')
                ->take(8)
                ->get(),
        ]);
    }

    public function edit(Branch $branch): View
    {
        $this->authorize('update', $branch);

        return view('branches.edit', [
            'branch' => $branch,
            'managers' => $this->managerOptions($branch->id),
        ]);
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);

        $branch->update($request->validated());

        return redirect()->route('branches.show', $branch)
            ->with('success', 'Branch was updated.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);

        $branch->delete();

        return redirect()->route('branches.index')
            ->with('success', 'Branch was removed.');
    }

    protected function managerOptions(?int $branchId = null)
    {
        return Employee::query()
            ->active()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'employee_code']);
    }
}
