@php
    $isNew = ! $employee->exists;

    $sections = [
        'personal' => 'Personal',
        'employment' => 'Employment',
        'address' => 'Address & contact',
        'bank' => 'Bank & statutory',
    ];

    if ($isNew) {
        $sections['account'] = 'Login account';
    }

    $sections['notes'] = 'Notes';
@endphp

<x-tabs :tabs="$sections" key="employee-form">

    {{-- ------------------------------------------------------------ personal --}}
    <div data-tab-panel="personal">
        <x-card title="Personal details">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-input label="First name" name="first_name" :value="$employee->first_name" required />
                <x-input label="Last name" name="last_name" :value="$employee->last_name" />
                <x-input label="Employee code" name="employee_code"
                    :value="$employee->employee_code ?? ($suggestedCode ?? null)"
                    help="Leave blank to generate the next code automatically." />

                <x-input label="Work email" name="email" type="email" :value="$employee->email" required
                    help="Also used as the login for this employee." />
                <x-input label="Personal email" name="personal_email" type="email" :value="$employee->personal_email" />
                <x-input label="Phone" name="phone" :value="$employee->phone" />

                <x-input label="Alternate phone" name="alternate_phone" :value="$employee->alternate_phone" />
                <x-select label="Gender" name="gender" :selected="$employee->gender" placeholder="Not specified"
                    :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" />
                <x-input label="Date of birth" name="date_of_birth" type="date"
                    :value="$employee->date_of_birth?->toDateString()" />

                <x-select label="Marital status" name="marital_status" :selected="$employee->marital_status" placeholder="Not specified"
                    :options="['single' => 'Single', 'married' => 'Married', 'divorced' => 'Divorced', 'widowed' => 'Widowed']" />
                <x-input label="Blood group" name="blood_group" :value="$employee->blood_group" placeholder="O+" />
                <x-input label="Nationality" name="nationality" :value="$employee->nationality" />

                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="form-label" for="photo">Photograph</label>
                    <div class="flex items-center gap-4">
                        <x-avatar :name="$employee->full_name ?: 'New'" :src="$employee->photoUrl()" size="lg" />
                        <input type="file" name="photo" id="photo" accept="image/*"
                            class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100">
                    </div>
                    @error('photo')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </x-card>
    </div>

    {{-- ---------------------------------------------------------- employment --}}
    <div data-tab-panel="employment">
        <x-card title="Employment">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-select label="Payroll company" name="company_id" required
                    :selected="$employee->company_id ?? App\Models\Company::default()?->id"
                    placeholder="Choose a company"
                    :options="$companies->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all()"
                    help="Who employs them. Their salary slips are issued in this company's name." />
                <x-select label="Branch" name="branch_id" :selected="$employee->branch_id" required
                    placeholder="Choose a branch" :options="$branches->pluck('name', 'id')->all()" />
                <x-select label="Department" name="department_id" :selected="$employee->department_id"
                    placeholder="Not assigned" :options="$departments->pluck('name', 'id')->all()" />
                <x-select label="Designation" name="designation_id" :selected="$employee->designation_id"
                    placeholder="Not assigned" :options="$designations->pluck('name', 'id')->all()" />

                <x-select label="Shift" name="shift_id" :selected="$employee->shift_id" placeholder="Branch default"
                    :options="$shifts->pluck('name', 'id')->all()" />
                <x-select label="Reports to" name="reporting_to" :selected="$employee->reporting_to" placeholder="No manager"
                    :options="$managers->mapWithKeys(fn ($e) => [$e->id => $e->first_name . ' ' . $e->last_name . ' (' . $e->employee_code . ')'])->all()" />
                <x-select label="Employment type" name="employment_type" required
                    :selected="$employee->employment_type?->value ?? 'full_time'"
                    :options="App\Enums\EmploymentType::options()" />

                <x-select label="Employment status" name="employment_status" required
                    :selected="$employee->employment_status?->value ?? 'probation'"
                    :options="App\Enums\EmploymentStatus::options()" />
                <x-input label="Date of joining" name="date_of_joining" type="date" required
                    :value="$employee->date_of_joining?->toDateString() ?? now()->toDateString()" />
                <x-input label="Date of confirmation" name="date_of_confirmation" type="date"
                    :value="$employee->date_of_confirmation?->toDateString()" />

                <x-input label="Notice period (days)" name="notice_period_days" type="number" min="0" max="365"
                    :value="$employee->notice_period_days ?? 30" required />
                <x-input label="Date of exit" name="date_of_exit" type="date"
                    :value="$employee->date_of_exit?->toDateString()"
                    help="Leave blank while the employee is still with the company." />
                <x-select label="Record status" name="status" :selected="$employee->status ?? 'active'" required
                    :options="['active' => 'Active', 'inactive' => 'Inactive']" />

                <x-textarea label="Exit reason" name="exit_reason" :value="$employee->exit_reason" rows="2"
                    class="sm:col-span-2 lg:col-span-3" />

                <div class="sm:col-span-2 lg:col-span-3 border-t border-slate-100 pt-4">
                    <x-checkbox label="Eligible for overtime pay" name="overtime_eligible"
                        :checked="$employee->exists ? $employee->overtime_eligible : true"
                        help="Only has an effect where overtime pay is switched on under Settings → Payroll. Extra hours are recorded either way." />
                </div>
            </div>
        </x-card>
    </div>

    {{-- ------------------------------------------------------------- address --}}
    <div data-tab-panel="address">
        <x-card title="Address and emergency contact">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-input label="Address line 1" name="address_line1" :value="$employee->address_line1" class="sm:col-span-2" />
                <x-input label="Address line 2" name="address_line2" :value="$employee->address_line2" />
                <x-input label="City" name="city" :value="$employee->city" />
                <x-input label="State" name="state" :value="$employee->state" />
                <x-input label="Country" name="country" :value="$employee->country ?? 'India'" />
                <x-input label="Postal code" name="postal_code" :value="$employee->postal_code" />

                <x-input label="Emergency contact name" name="emergency_contact_name" :value="$employee->emergency_contact_name" />
                <x-input label="Emergency contact phone" name="emergency_contact_phone" :value="$employee->emergency_contact_phone" />
                <x-input label="Relationship" name="emergency_contact_relation" :value="$employee->emergency_contact_relation" />
            </div>
        </x-card>
    </div>

    {{-- ---------------------------------------------------------------- bank --}}
    <div data-tab-panel="bank">
        <x-card title="Bank and statutory details" subtitle="Used when generating salary slips and bank transfer files">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-input label="Bank name" name="bank_name" :value="$employee->bank_name" />
                <x-input label="Account holder name" name="bank_account_name" :value="$employee->bank_account_name" />
                <x-input label="Account number" name="bank_account_number" :value="$employee->bank_account_number" />
                <x-input label="IFSC / routing code" name="bank_ifsc" :value="$employee->bank_ifsc" />
                <x-input label="Bank branch" name="bank_branch" :value="$employee->bank_branch" />
                <x-input label="PAN / tax number" name="pan_number" :value="$employee->pan_number" />
                <x-input label="National ID" name="national_id" :value="$employee->national_id" />
                <x-input label="PF number" name="pf_number" :value="$employee->pf_number" />
                <x-input label="UAN number" name="uan_number" :value="$employee->uan_number" />
                <x-input label="ESI number" name="esi_number" :value="$employee->esi_number" />
            </div>
        </x-card>
    </div>

    {{-- ------------------------------------------------------------- account --}}
    @if ($isNew)
        <div data-tab-panel="account">
            <x-card title="Login account" subtitle="Create a login so this employee can use the portal">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-checkbox label="Create a login account" name="create_account" :checked="true"
                        help="A temporary password is generated and emailed." class="sm:col-span-2 lg:col-span-1" />
                    <x-select label="Role" name="role" :selected="old('role', App\Support\Roles::EMPLOYEE)"
                        :options="$roles" help="Determines which screens this person can reach." />
                    <x-checkbox label="Send a welcome email" name="send_welcome_email" :checked="true"
                        help="Includes the temporary password and sign-in link." />
                </div>
            </x-card>

            @can('bgv.manage')
                <x-card title="Background verification" class="mt-6"
                    subtitle="Ask for the documents that have to be checked before onboarding is confirmed">
                    <div class="space-y-4">
                        <x-checkbox label="Ask for background verification" name="request_bgv" :checked="true"
                            help="They are emailed the checklist and complete it in the portal." />

                        <x-input label="Complete by" name="bgv_due_on" type="date"
                            :value="old('bgv_due_on', now()->addDays(14)->toDateString())"
                            help="Optional. It appears in the invitation email." class="sm:w-64" />

                        <div>
                            <p class="form-label">What to ask for</p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach (App\Support\BackgroundCheckRequirements::all() as $key => $requirement)
                                    <label class="flex items-start gap-2.5">
                                        <input type="checkbox" name="bgv_requirements[]" value="{{ $key }}"
                                            class="form-checkbox mt-0.5"
                                            @checked(in_array($key, old('bgv_requirements', App\Support\BackgroundCheckRequirements::defaults()), true))>
                                        <span>
                                            <span class="text-sm font-medium text-slate-700">{{ $requirement['label'] }}</span>
                                            <span class="block text-xs text-slate-500">{{ $requirement['description'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </x-card>
            @endcan
        </div>
    @endif

    {{-- --------------------------------------------------------------- notes --}}
    <div data-tab-panel="notes">
        <x-card title="Internal notes">
            <x-textarea label="Notes" name="notes" :value="$employee->notes" rows="5"
                help="Only visible to HR and administrators." />
        </x-card>
    </div>
</x-tabs>

<x-form-actions note="Every section is saved together, whichever tab you are on.">
    <x-button size="lg">{{ $isNew ? 'Create employee' : 'Save changes' }}</x-button>
    <x-button href="{{ $employee->exists ? route('employees.show', $employee) : route('employees.index') }}"
        variant="secondary" size="lg">Cancel</x-button>
</x-form-actions>
