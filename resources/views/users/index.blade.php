<x-app-layout title="User accounts">
    <x-page-header title="User accounts" subtitle="Logins and the roles that grant them access">
        <x-slot:actions>
            @can('create', App\Models\User::class)
                <x-button href="{{ route('users.create') }}"><x-icon.plus class="size-4" /> New user</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('users.index')">
        <x-input label="Search" name="search" :value="request('search')" placeholder="Name or email" class="sm:w-64" />
        <x-select label="Role" name="role" :selected="request('role')" placeholder="All roles"
            :options="$roles->mapWithKeys(fn ($r) => [$r => $roleLabels[$r] ?? $r])->all()" class="sm:w-48" />
        <x-select label="Status" name="status" :selected="request('status')" placeholder="All statuses"
            :options="['active' => 'Active', 'inactive' => 'Inactive']" class="sm:w-40" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Filter</x-button>
        @if (request()->hasAny(['search', 'role', 'status']))
            <x-button href="{{ route('users.index') }}" variant="ghost">Clear</x-button>
        @endif
    </x-filter-bar>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr><th>User</th><th>Roles</th><th>Employee</th><th>Last sign-in</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($users as $account)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-avatar :name="$account->name" :src="$account->avatarUrl()" size="sm" />
                                    <div class="min-w-0">
                                        <div class="font-medium text-slate-900">{{ $account->name }}</div>
                                        <div class="truncate text-xs text-slate-500">{{ $account->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($account->roles as $role)
                                        <x-badge color="brand">{{ $roleLabels[$role->name] ?? $role->name }}</x-badge>
                                    @empty
                                        <span class="text-xs text-slate-400">No role</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="text-sm">
                                @if ($account->employee)
                                    <a href="{{ route('employees.show', $account->employee) }}" class="text-slate-900 hover-ink">
                                        {{ $account->employee->employee_code }}
                                    </a>
                                    <div class="text-xs text-slate-500">{{ $account->employee->branch?->name }}</div>
                                @else
                                    <span class="text-slate-400">Not linked</span>
                                @endif
                            </td>
                            <td class="text-xs whitespace-nowrap text-slate-500">
                                {{ $account->last_login_at?->format('d M Y, h:i A') ?? 'Never' }}
                                @if ($account->must_change_password)
                                    <div class="mt-0.5"><x-badge color="amber">Temporary password</x-badge></div>
                                @endif
                            </td>
                            <td><x-badge :color="$account->status === 'active' ? 'emerald' : 'slate'" dot>{{ ucfirst($account->status) }}</x-badge></td>
                            <td class="num whitespace-nowrap">
                                @can('update', $account)
                                    <x-button href="{{ route('users.edit', $account) }}" variant="ghost" size="sm"><x-icon.pencil class="size-3.5" /> Edit</x-button>
                                    <form method="POST" action="{{ route('users.reset-password', $account) }}" class="inline">
                                        @csrf
                                        <x-button variant="ghost" size="sm"
                                            data-confirm="Send {{ $account->email }} a new temporary password?">Reset password</x-button>
                                    </form>
                                    @if ($account->hasTwoFactor())
                                        <form method="POST" action="{{ route('users.reset-two-factor', $account) }}" class="inline">
                                            @csrf
                                            <x-button variant="ghost" size="sm"
                                                data-confirm="Clear two-step verification for {{ $account->name }}? They will set it up again on their next sign-in, and their old recovery codes stop working.">
                                                Clear 2FA
                                            </x-button>
                                        </form>
                                    @endif
                                @endcan
                                @can('delete', $account)
                                    <form method="POST" action="{{ route('users.destroy', $account) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <x-button variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50"
                                            data-confirm="Remove the account for {{ $account->email }}?"><x-icon.trash class="size-3.5" /> Delete</x-button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty title="No user accounts found" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $users->links() }}</div>
</x-app-layout>
