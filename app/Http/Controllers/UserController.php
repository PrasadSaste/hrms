<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Services\TwoFactorService;
use App\Support\NotificationEvents;
use App\Services\EmployeeService;
use App\Services\NotificationDispatcher;
use App\Services\NotificationService;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        protected EmployeeService $employees,
        protected NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with(['roles', 'employee.branch'])
            ->when($request->string('search')->toString(), fn ($q, $term) => $q->where(
                fn ($sub) => $sub->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")
            ))
            ->when($request->string('role')->toString(), fn ($q, $role) => $q->role($role))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->pluck('name'),
            'roleLabels' => Roles::labels(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', [
            'user' => new User(['status' => 'active']),
            'roles' => Role::orderBy('name')->get(),
            'employees' => Employee::whereNull('user_id')->active()->orderBy('first_name')->get(),
            'roleLabels' => Roles::labels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'send_credentials' => ['nullable', 'boolean'],
        ]);

        $password = $validated['password'] ?? Str::password(12, true, true, false);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $password,
            'status' => $validated['status'],
            'must_change_password' => empty($validated['password']),
            'email_verified_at' => now(),
        ]);

        $user->syncRoles($validated['roles']);

        if (! empty($validated['employee_id'])) {
            Employee::whereKey($validated['employee_id'])->update(['user_id' => $user->id]);
        }

        if ($request->boolean('send_credentials', true)) {
            $this->notifications->sendCredentials($user, $password);
        }

        return redirect()->route('users.index')
            ->with('success', 'User account created for '.$user->email.'.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', [
            'user' => $user->load('roles', 'employee'),
            'roles' => Role::orderBy('name')->get(),
            'employees' => Employee::where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))
                ->active()->orderBy('first_name')->get(),
            'roleLabels' => Roles::labels(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:32'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        // Never let the last super admin lose the role or be deactivated.
        if ($user->isSuperAdmin() && ! in_array(Roles::SUPER_ADMIN, $validated['roles'], true)) {
            $remaining = User::role(Roles::SUPER_ADMIN)->where('id', '!=', $user->id)->count();

            if ($remaining === 0) {
                return back()->withErrors(['roles' => 'At least one super admin must remain.']);
            }
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
        ]);

        if ($request->user()->can('assignRoles', $user)) {
            $user->syncRoles($validated['roles']);
        }

        return redirect()->route('users.index')->with('success', 'User account updated.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $password = $this->employees->resetPassword($user);

        $this->notifications->sendCredentials($user, $password, reset: true);

        return back()->with('success', 'A new temporary password has been emailed to '.$user->email.'.');
    }

    /**
     * Clear somebody's second factor, for the lost phone with no codes left.
     *
     * Deliberately not a "show me their secret" — it removes the factor and
     * makes them set a new one up on the new phone. The person is told, in
     * their own inbox, because an administrator quietly stripping a second
     * factor is exactly the thing they would want to know about.
     */
    public function resetTwoFactor(
        Request $request,
        User $user,
        TwoFactorService $totp,
        NotificationDispatcher $dispatcher,
    ): RedirectResponse {
        $this->authorize('update', $user);

        if (! $user->hasTwoFactor()) {
            return back()->with('info', $user->name.' does not have two-step verification on.');
        }

        $totp->disable($user);

        $dispatcher->toUser(NotificationEvents::TWO_FACTOR_DISABLED, $user, [
            'name' => $user->name,
            'when' => now()->format('d M Y, h:i A'),
            'ip' => $request->ip() ?? '—',
            'url' => route('two-factor.setup'),
        ]);

        return back()->with(
            'success',
            'Two-step verification cleared for '.$user->name.'. They can set it up again on their next sign-in.',
        );
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ($user->isSuperAdmin() && User::role(Roles::SUPER_ADMIN)->count() <= 1) {
            return back()->withErrors(['delete' => 'The last super admin cannot be deleted.']);
        }

        $user->update(['status' => 'inactive']);
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User account removed.');
    }
}
