<x-app-layout title="My profile">
    <x-page-header title="My profile" subtitle="Your account, personal details and password" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @php
                $sections = ['account' => 'Account'];
                if ($employee) {
                    $sections['personal'] = 'Personal details';
                }
                $sections['password'] = 'Password';
            @endphp

            <x-tabs :tabs="$sections" key="profile">

            <div data-tab-panel="account">
            <x-card title="Account">
                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf @method('PUT')

                    <div class="flex items-center gap-4">
                        <x-avatar :name="$user->name" :src="$user->avatarUrl()" size="xl" />
                        <div class="flex-1">
                            <label class="form-label" for="avatar">Profile photo</label>
                            <input type="file" name="avatar" id="avatar" accept="image/*"
                                class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                            @error('avatar')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="Full name" name="name" :value="$user->name" required />
                        <x-input label="Email address" name="email" type="email" :value="$user->email" required />
                        <x-input label="Phone" name="phone" :value="$user->phone" />
                    </div>

                    <x-button>Save account details</x-button>
                </form>
            </x-card>

            </div>

            @if ($employee)
                <div data-tab-panel="personal">
                <x-card title="Personal details" subtitle="Keep these current so HR can reach you">
                    <form method="POST" action="{{ route('profile.personal') }}" class="space-y-4">
                        @csrf @method('PUT')

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-input label="Personal email" name="personal_email" type="email" :value="$employee->personal_email" />
                            <x-input label="Alternate phone" name="alternate_phone" :value="$employee->alternate_phone" />
                            <x-select label="Marital status" name="marital_status" :selected="$employee->marital_status"
                                placeholder="Not specified"
                                :options="['single' => 'Single', 'married' => 'Married', 'divorced' => 'Divorced', 'widowed' => 'Widowed']" />
                            <x-input label="Blood group" name="blood_group" :value="$employee->blood_group" />
                        </div>

                        <h3 class="border-t border-slate-100 pt-4 text-sm font-semibold text-slate-700">Address</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-input label="Address line 1" name="address_line1" :value="$employee->address_line1" class="sm:col-span-2" />
                            <x-input label="Address line 2" name="address_line2" :value="$employee->address_line2" class="sm:col-span-2" />
                            <x-input label="City" name="city" :value="$employee->city" />
                            <x-input label="State" name="state" :value="$employee->state" />
                            <x-input label="Country" name="country" :value="$employee->country" />
                            <x-input label="Postal code" name="postal_code" :value="$employee->postal_code" />
                        </div>

                        <h3 class="border-t border-slate-100 pt-4 text-sm font-semibold text-slate-700">Emergency contact</h3>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-input label="Name" name="emergency_contact_name" :value="$employee->emergency_contact_name" />
                            <x-input label="Phone" name="emergency_contact_phone" :value="$employee->emergency_contact_phone" />
                            <x-input label="Relationship" name="emergency_contact_relation" :value="$employee->emergency_contact_relation" />
                        </div>

                        <x-button>Save personal details</x-button>
                    </form>
                </x-card>

                </div>
            @endif

            <div data-tab-panel="password">
            <x-card title="Password" subtitle="Use a password you do not reuse elsewhere">
                <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
                    @csrf @method('PUT')

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="form-label" for="current_password">Current password</label>
                            <input type="password" name="current_password" id="current_password"
                                autocomplete="current-password" class="form-input">
                            @error('current_password')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="new_password">New password</label>
                            <input type="password" name="password" id="new_password" autocomplete="new-password" class="form-input">
                            @error('password')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="password_confirmation">Confirm new password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation"
                                autocomplete="new-password" class="form-input">
                        </div>
                    </div>

                    <x-button>Change password</x-button>
                </form>
            </x-card>
            </div>

            </x-tabs>
        </div>

        <div class="space-y-6">
            <x-card title="Two-step verification">
                @if ($user->hasTwoFactor())
                    <p class="text-sm text-slate-600">
                        <x-badge color="emerald" dot>On</x-badge>
                        <span class="mt-2 block">
                            You are asked for a code from your authenticator app when you sign in.
                        </span>
                    </p>
                @else
                    <p class="text-sm text-slate-600">
                        <x-badge color="slate" dot>Off</x-badge>
                        <span class="mt-2 block">
                            Your password is the only thing protecting an account that can see
                            salaries and bank details.
                        </span>
                    </p>
                @endif

                <a href="{{ route('two-factor.setup') }}" class="link mt-3 inline-block text-sm font-medium">
                    {{ $user->hasTwoFactor() ? 'Manage it' : 'Set it up' }}
                </a>
            </x-card>

            <x-card title="Roles and access">
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($user->roles as $role)
                        <x-badge color="brand">{{ App\Support\Roles::label($role->name) }}</x-badge>
                    @endforeach
                </div>
                <p class="mt-3 text-sm text-slate-500">
                    Your roles decide which screens appear in the sidebar.
                    Contact an administrator if something you need is missing.
                </p>
            </x-card>

            @if ($employee)
                <x-card title="Employment">
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Employee code</dt>
                            <dd class="text-right font-mono text-slate-900">{{ $employee->employee_code }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Designation</dt>
                            <dd class="text-right text-slate-900">{{ $employee->designation?->name ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Department</dt>
                            <dd class="text-right text-slate-900">{{ $employee->department?->name ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Branch</dt>
                            <dd class="text-right text-slate-900">{{ $employee->branch?->name ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Reports to</dt>
                            <dd class="text-right text-slate-900">{{ $employee->manager?->full_name ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Joined</dt>
                            <dd class="text-right text-slate-900">{{ $employee->date_of_joining->format('d M Y') }}</dd>
                        </div>
                    </dl>

                    <x-button href="{{ route('employees.show', $employee) }}" variant="secondary" size="sm" class="mt-4 w-full">
                        View my employee record
                    </x-button>
                </x-card>
            @endif

            <x-card title="Sign-in activity">
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Last sign-in</dt>
                        <dd class="text-right text-slate-900">{{ $user->last_login_at?->format('d M Y, h:i A') ?? 'This session' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Last IP</dt>
                        <dd class="text-right font-mono text-slate-900">{{ $user->last_login_ip ?? '-' }}</dd>
                    </div>
                </dl>
            </x-card>
        </div>
    </div>
</x-app-layout>
