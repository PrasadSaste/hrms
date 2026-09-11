<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Department::class);

        $departments = Department::query()
            ->with(['branch', 'head'])
            ->withCount(['employees', 'designations'])
            ->when($request->string('search')->toString(), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->when($request->integer('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('departments.index', [
            'departments' => $departments,
            'branches' => Branch::active()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Department::class);

        return view('departments.create', $this->formData(new Department(['status' => 'active'])));
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        $this->authorize('create', Department::class);

        $department = Department::create($request->validated());

        return redirect()->route('departments.index')
            ->with('success', 'Department "'.$department->name.'" was created.');
    }

    public function show(Department $department): View
    {
        $this->authorize('view', $department);

        $department->load(['branch', 'head', 'designations' => fn ($q) => $q->withCount('employees')]);

        return view('departments.show', [
            'department' => $department,
            'employees' => $department->employees()->with('designation')->active()->orderBy('first_name')->paginate(12),
        ]);
    }

    public function edit(Department $department): View
    {
        $this->authorize('update', $department);

        return view('departments.edit', $this->formData($department));
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->authorize('update', $department);

        $department->update($request->validated());

        return redirect()->route('departments.index')
            ->with('success', 'Department was updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);

        $department->delete();

        return redirect()->route('departments.index')
            ->with('success', 'Department was removed.');
    }

    protected function formData(Department $department): array
    {
        return [
            'department' => $department,
            'branches' => Branch::active()->orderBy('name')->get(),
            'heads' => Employee::active()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_code']),
        ];
    }
}
