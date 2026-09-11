<?php

namespace App\Http\Controllers;

use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('roles.view'), 403);

        return view('roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])->orderBy('name')->get(),
            'labels' => Roles::labels(),
            'canManage' => $request->user()->can('roles.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('roles.manage'), 403);

        return view('roles.create', [
            'role' => new Role,
            'catalogue' => Permissions::catalogue(),
            'assigned' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('roles.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/', Rule::unique('roles', 'name')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ], [
            'name.regex' => 'Use lowercase letters, numbers and hyphens only, e.g. "team-lead".',
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('roles.index')->with('success', 'Role "'.$role->name.'" created.');
    }

    public function edit(Request $request, Role $role): View
    {
        abort_unless($request->user()->can('roles.manage'), 403);

        return view('roles.edit', [
            'role' => $role,
            'catalogue' => Permissions::catalogue(),
            'assigned' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()->can('roles.manage'), 403);

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        // The super admin role always keeps every permission.
        if ($role->name === Roles::SUPER_ADMIN) {
            $role->syncPermissions(Permission::pluck('name')->all());

            return back()->with('info', 'The super admin role always holds every permission.');
        }

        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('roles.index')->with('success', 'Permissions updated for "'.$role->name.'".');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()->can('roles.manage'), 403);

        if (in_array($role->name, Roles::all(), true)) {
            return back()->withErrors(['delete' => 'Built-in roles cannot be deleted.']);
        }

        if ($role->users()->exists()) {
            return back()->withErrors(['delete' => 'This role is still assigned to users.']);
        }

        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Role removed.');
    }
}
