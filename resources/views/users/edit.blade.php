<x-app-layout :title="'Edit ' . $user->name">
    <x-page-header :title="'Edit ' . $user->name" :subtitle="$user->email" :back="route('users.index')" />

    <form method="POST" action="{{ route('users.update', $user) }}">
        @csrf @method('PUT')
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-card title="Account">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="Full name" name="name" :value="$user->name" required />
                        <x-input label="Email address" name="email" type="email" :value="$user->email" required />
                        <x-input label="Phone" name="phone" :value="$user->phone" />
                        <div>
                            <span class="form-label">Linked employee</span>
                            <p class="pt-2 text-sm text-slate-700">
                                @if ($user->employee)
                                    <a href="{{ route('employees.show', $user->employee) }}" class="link-plain">
                                        {{ $user->employee->full_name }} ({{ $user->employee->employee_code }})
                                    </a>
                                @else
                                    Not linked to an employee record.
                                @endif
                            </p>
                        </div>
                    </div>
                </x-card>

                <x-card title="Roles">
                    @can('assignRoles', $user)
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($roles as $role)
                                <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-slate-200 p-3 transition has-checked:border-brand-400 has-checked:bg-brand-50">
                                    <input type="checkbox" name="roles[]" value="{{ $role->name }}" class="form-checkbox mt-0.5"
                                        @checked(in_array($role->name, old('roles', $user->roles->pluck('name')->all())))>
                                    <span>
                                        <span class="text-sm font-medium text-slate-800">{{ $roleLabels[$role->name] ?? $role->name }}</span>
                                        <span class="block text-xs text-slate-500">{{ $role->permissions()->count() }} permission(s)</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('roles')<p class="form-error">{{ $message }}</p>@enderror
                    @else
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($user->roles as $role)
                                <input type="hidden" name="roles[]" value="{{ $role->name }}">
                                <x-badge color="brand">{{ $roleLabels[$role->name] ?? $role->name }}</x-badge>
                            @endforeach
                        </div>
                        <p class="mt-2 text-sm text-slate-500">You do not have permission to change role assignments.</p>
                    @endcan
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Status">
                    <x-select label="Status" name="status" :selected="$user->status" required
                        :options="['active' => 'Active', 'inactive' => 'Inactive']"
                        help="An inactive account cannot sign in, on the web or through the API." />
                </x-card>

                <x-card title="Sign-in activity">
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Last sign-in</dt>
                            <dd class="text-right text-slate-900">{{ $user->last_login_at?->format('d M Y, h:i A') ?? 'Never' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Last IP</dt>
                            <dd class="text-right font-mono text-slate-900">{{ $user->last_login_ip ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Created</dt>
                            <dd class="text-right text-slate-900">{{ $user->created_at->format('d M Y') }}</dd>
                        </div>
                    </dl>
                </x-card>

                <div class="flex gap-2">
                    <x-button class="flex-1">Save changes</x-button>
                    <x-button href="{{ route('users.index') }}" variant="secondary">Cancel</x-button>
                </div>
            </div>
        </div>
    </form>
</x-app-layout>
