<?php

namespace App\Http\Controllers;

use App\Http\Requests\DesignationRequest;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DesignationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Designation::class);

        $designations = Designation::query()
            ->with('department.branch')
            ->withCount('employees')
            ->when($request->string('search')->toString(), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->when($request->integer('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->orderBy('level')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('designations.index', [
            'designations' => $designations,
            'departments' => Department::active()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Designation::class);

        return view('designations.create', [
            'designation' => new Designation(['status' => 'active', 'level' => 1]),
            'departments' => Department::active()->orderBy('name')->get(),
        ]);
    }

    public function store(DesignationRequest $request): RedirectResponse
    {
        $this->authorize('create', Designation::class);

        $designation = Designation::create($request->validated());

        return redirect()->route('designations.index')
            ->with('success', 'Designation "'.$designation->name.'" was created.');
    }

    public function edit(Designation $designation): View
    {
        $this->authorize('update', $designation);

        return view('designations.edit', [
            'designation' => $designation,
            'departments' => Department::active()->orderBy('name')->get(),
        ]);
    }

    public function update(DesignationRequest $request, Designation $designation): RedirectResponse
    {
        $this->authorize('update', $designation);

        $designation->update($request->validated());

        return redirect()->route('designations.index')
            ->with('success', 'Designation was updated.');
    }

    public function destroy(Designation $designation): RedirectResponse
    {
        $this->authorize('delete', $designation);

        $designation->delete();

        return redirect()->route('designations.index')
            ->with('success', 'Designation was removed.');
    }
}
