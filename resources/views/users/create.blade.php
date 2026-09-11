<x-app-layout title="New user account">
    <x-page-header title="New user account" subtitle="Create a login and assign its roles" :back="route('users.index')" />

    <form method="POST" action="{{ route('users.store') }}">
        @csrf
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-card title="Account">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="Full name" name="name" required />
                        <x-input label="Email address" name="email" type="email" required />
                        <x-input label="Phone" name="phone" />
                        <x-select label="Link to employee" name="employee_id" placeholder="Not linked"
                            :options="$employees->mapWithKeys(fn ($e) => [$e->id => $e->first_name . ' ' . $e->last_name . ' (' . $e->employee_code . ')'])->all()"
                            help="Links the login to an employee record so attendance and leave work." />

                        <div>
                            <label class="form-label" for="password">Password</label>
                            <input type="password" name="password" id="password" autocomplete="new-password" class="form-input">
                            <p class="form-help">Leave blank to generate a temporary password automatically.</p>
                            @error('password')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="password_confirmation">Confirm password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation"
                                autocomplete="new-password" class="form-input">
                        </div>
                    </div>
                </x-card>

                <x-card title="Roles" subtitle="A user may hold more than one role">
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($roles as $role)
                            <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-slate-200 p-3 transition has-checked:border-brand-400 has-checked:bg-brand-50">
                                <input type="checkbox" name="roles[]" value="{{ $role->name }}" class="form-checkbox mt-0.5"
                                    @checked(in_array($role->name, old('roles', ['employee'])))>
                                <span>
                                    <span class="text-sm font-medium text-slate-800">{{ $roleLabels[$role->name] ?? $role->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ $role->permissions_count ?? $role->permissions()->count() }} permission(s)</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('roles')<p class="form-error">{{ $message }}</p>@enderror
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Options">
                    <div class="space-y-4">
                        <x-select label="Status" name="status" selected="active" required
                            :options="['active' => 'Active', 'inactive' => 'Inactive']" />
                        <x-checkbox label="Email the credentials" name="send_credentials" :checked="true"
                            help="Sends the sign-in link and temporary password." />
                    </div>
                </x-card>

                <div class="flex gap-2">
                    <x-button class="flex-1">Create account</x-button>
                    <x-button href="{{ route('users.index') }}" variant="secondary">Cancel</x-button>
                </div>
            </div>
        </div>
    </form>
</x-app-layout>
