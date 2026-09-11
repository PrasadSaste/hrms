<x-app-layout :title="$employee->full_name">
    <x-page-header :title="$employee->full_name"
        :subtitle="($employee->designation?->name ?? 'No designation') . ' · ' . ($employee->department?->name ?? 'No department')"
        :back="route('employees.index')" backLabel="Employees">
        <x-slot:actions>
            @can('viewEmployee', [App\Models\Attendance::class, $employee])
                <x-button href="{{ route('attendance.employee', $employee) }}" variant="secondary">
                    <x-icon.clock class="size-4" /> Attendance
                </x-button>
            @endcan
            @can('create', App\Models\Letter::class)
                <x-button href="{{ route('letters.create', ['employee_id' => $employee->id]) }}" variant="secondary">
                    <x-icon.document class="size-4" /> Issue a letter
                </x-button>
            @endcan
            @can('update', $employee)
                <x-button href="{{ route('employees.edit', $employee) }}" variant="secondary"><x-icon.pencil class="size-3.5" /> Edit</x-button>
            @endcan
            @can('delete', $employee)
                <form method="POST" action="{{ route('employees.destroy', $employee) }}" class="inline">
                    @csrf @method('DELETE')
                    <x-button variant="danger" data-confirm="Archive {{ $employee->full_name }}? Their login will be disabled.">
                        Archive
                    </x-button>
                </form>
            @endcan
        </x-slot:actions>
        {{-- The facts somebody would otherwise open three tabs to ask, ruled
             apart along one line under the name. --}}
        <x-slot:meta>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <x-avatar :name="$employee->full_name" :src="$employee->photoUrl()" size="sm" />
                <x-badge :color="$employee->employment_status->color()">{{ $employee->employment_status->label() }}</x-badge>
                <x-badge color="slate">{{ $employee->employment_type->label() }}</x-badge>
                @if (! $employee->user)
                    <x-badge color="amber">No login</x-badge>
                @endif

                <x-meta-strip class="ml-1" :items="[
                    'Code' => $employee->employee_code,
                    'Company' => $employee->company?->displayName(),
                    'Branch' => $employee->branch?->name,
                    'Reports to' => $employee->manager?->full_name,
                    'Joined' => $employee->date_of_joining->format('d M Y')
                        . ' (' . intdiv($employee->tenureInMonths(), 12) . 'y ' . $employee->tenureInMonths() % 12 . 'm)',
                ]" />
            </div>
        </x-slot:meta>
    </x-page-header>

    @can('update', $employee)
        @unless ($employee->user)
            <x-card class="mb-5" title="This employee cannot sign in yet">
                <form method="POST" action="{{ route('employees.account', $employee) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <x-select label="Create login as" name="role" :options="App\Support\Roles::labels()"
                        selected="employee" class="w-44" />
                    <x-button variant="secondary"><x-icon.key class="size-4" /> Create login</x-button>
                </form>
            </x-card>
        @endunless
    @endcan

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @php
                $sections = [
                    'attendance' => 'Attendance',
                    'details' => 'Details',
                    'documents' => 'Documents',
                    'leave' => 'Leave',
                ];
                if (auth()->user()->can('assets.view')) {
                    $sections['assets'] = 'Assets' . ($heldAssets->isNotEmpty() ? ' (' . $heldAssets->count() . ')' : '');
                }
                if ($employee->subordinates->isNotEmpty()) {
                    $sections['team'] = 'Team (' . $employee->subordinates->count() . ')';
                }
            @endphp

            <x-tabs :tabs="$sections" key="employee-detail">

            {{-- ------------------------------------------ attendance summary --}}
            <div data-tab-panel="attendance">
            <x-card :title="'Attendance in ' . $month->format('F Y')">
                <x-slot:actions>
                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="year" value="{{ $month->format('Y') }}">
                        <select name="month" class="form-select w-36 py-1.5 text-xs" data-auto-submit>
                            @foreach (range(1, 12) as $m)
                                <option value="{{ $m }}" @selected((int) $month->format('n') === $m)>
                                    {{ \Illuminate\Support\Carbon::create(null, $m, 1)->format('F') }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </x-slot:actions>

                @php $totals = $attendanceSummary['totals']; @endphp
                <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">Present</dt>
                        <dd class="mt-1 text-2xl font-semibold text-slate-900">
                            {{ rtrim(rtrim(number_format($totals['present_days'], 1), '0'), '.') }}
                            <span class="text-sm font-normal text-slate-400">/ {{ $totals['working_days'] }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">Absent</dt>
                        <dd class="mt-1 text-2xl font-semibold text-rose-600">
                            {{ rtrim(rtrim(number_format($totals['absent_days'], 1), '0'), '.') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">On leave</dt>
                        <dd class="mt-1 text-2xl font-semibold text-sky-600">
                            {{ rtrim(rtrim(number_format($totals['paid_leave_days'] + $totals['unpaid_leave_days'], 1), '0'), '.') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">Hours worked</dt>
                        <dd class="mt-1 text-2xl font-semibold text-slate-900">{{ $totals['worked_hours'] }}</dd>
                    </div>
                </dl>

                <div class="mt-5 flex flex-wrap gap-1">
                    @foreach ($attendanceSummary['days'] as $date => $day)
                        @php
                            $color = match ($day['status']?->value) {
                                'present' => 'bg-emerald-500', 'late' => 'bg-amber-500',
                                'half_day' => 'bg-orange-400', 'absent' => 'bg-rose-500',
                                'on_leave' => 'bg-sky-500', 'holiday' => 'bg-violet-400',
                                default => 'bg-slate-200',
                            };
                        @endphp
                        <span class="size-6 rounded {{ $color }} text-center text-[10px] leading-6 font-medium text-white/90"
                            title="{{ \Illuminate\Support\Carbon::parse($date)->format('D, d M') }} — {{ $day['status']?->label() ?? 'Not marked' }}">
                            {{ \Illuminate\Support\Carbon::parse($date)->format('j') }}
                        </span>
                    @endforeach
                </div>
            </x-card>

            </div>

            {{-- --------------------------------------------------- details --}}
            <div data-tab-panel="details">
                <div class="grid gap-6 sm:grid-cols-2">
                    <x-card title="Contact">
                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-xs tracking-wide text-slate-500 uppercase">Work email</dt>
                                <dd class="mt-0.5 break-all text-slate-800">{{ $employee->email }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs tracking-wide text-slate-500 uppercase">Personal email</dt>
                                <dd class="mt-0.5 break-all text-slate-800">{{ $employee->personal_email ?: '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs tracking-wide text-slate-500 uppercase">Phone</dt>
                                <dd class="mt-0.5 text-slate-800">{{ $employee->phone ?: '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs tracking-wide text-slate-500 uppercase">Address</dt>
                                <dd class="mt-0.5 text-slate-800">
                                    {{ collect([$employee->address_line1, $employee->address_line2, $employee->city, $employee->state, $employee->postal_code, $employee->country])->filter()->implode(', ') ?: 'Not set' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs tracking-wide text-slate-500 uppercase">Emergency contact</dt>
                                <dd class="mt-0.5 text-slate-800">
                                    @if ($employee->emergency_contact_name)
                                        {{ $employee->emergency_contact_name }}
                                        <span class="text-slate-500">({{ $employee->emergency_contact_relation ?: 'contact' }})</span>
                                        <br><span class="text-slate-600">{{ $employee->emergency_contact_phone }}</span>
                                    @else
                                        Not set
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </x-card>

                    <x-card title="Bank and statutory">
                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-xs tracking-wide text-slate-500 uppercase">Bank</dt>
                                <dd class="mt-0.5 text-slate-800">
                                    {{ $employee->bank_name ?: '-' }}
                                    @if ($employee->bank_branch)
                                        <span class="block text-xs text-slate-500">{{ $employee->bank_branch }}</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs tracking-wide text-slate-500 uppercase">Account number</dt>
                                <dd class="mt-0.5 font-mono text-slate-800">{{ $employee->bank_account_number ?: '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs tracking-wide text-slate-500 uppercase">IFSC</dt>
                                <dd class="mt-0.5 font-mono text-slate-800">{{ $employee->bank_ifsc ?: '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs tracking-wide text-slate-500 uppercase">PAN</dt>
                                <dd class="mt-0.5 font-mono text-slate-800">{{ $employee->pan_number ?: '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs tracking-wide text-slate-500 uppercase">UAN</dt>
                                <dd class="mt-0.5 font-mono text-slate-800">{{ $employee->uan_number ?: '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs tracking-wide text-slate-500 uppercase">PF / ESI</dt>
                                <dd class="mt-0.5 font-mono text-slate-800">
                                    {{ $employee->pf_number ?: '-' }} / {{ $employee->esi_number ?: '-' }}
                                </dd>
                            </div>
                        </dl>
                    </x-card>
                </div>
            </div>

            {{-- --------------------------------------------------- documents --}}
            <div data-tab-panel="documents">
            <x-card title="Documents" :subtitle="$employee->documents->count() . ' file(s) on record'">
                <x-slot:actions>
                    @can('manageDocuments', $employee)
                        <x-button size="sm" data-dialog-open="upload-document" type="button">
                            <x-icon.upload class="size-4" /> Upload
                        </x-button>
                    @endcan
                </x-slot:actions>

                @forelse ($employee->documents as $document)
                    <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 py-3 first:pt-0 last:border-0 last:pb-0">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                            <x-icon.document class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-900">{{ $document->title }}</p>
                            <p class="truncate text-xs text-slate-500">
                                {{ $document->typeLabel() }} &middot; {{ $document->humanSize() }}
                                @if ($document->expiry_date)
                                    &middot; expires {{ $document->expiry_date->format('d M Y') }}
                                @endif
                            </p>
                        </div>
                        @if ($document->isExpired())
                            <x-badge color="rose">Expired</x-badge>
                        @endif
                        <x-button href="{{ route('employees.documents.download', [$employee, $document]) }}"
                            variant="ghost" size="sm">Download</x-button>
                        @can('manageDocuments', $employee)
                            <form method="POST" action="{{ route('employees.documents.destroy', [$employee, $document]) }}" class="inline">
                                @csrf @method('DELETE')
                                <x-button variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50"
                                    data-confirm="Delete {{ $document->title }}?"><x-icon.trash class="size-3.5" /> Delete</x-button>
                            </form>
                        @endcan
                    </div>
                @empty
                    <x-empty title="No documents" message="Upload contracts, ID proofs and certificates here." />
                @endforelse
            </x-card>

            </div>

            {{-- ------------------------------------------------------ assets --}}
            @can('assets.view')
                <div data-tab-panel="assets">
                <x-card title="Company property"
                    subtitle="What they are holding, and what they have handed back">
                    @if ($heldAssets->isEmpty())
                        <x-empty title="Nothing issued"
                            message="Nothing on the register is with this person." />
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($heldAssets as $assignment)
                                <li class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0">
                                    <div class="min-w-0">
                                        <a href="{{ route('assets.show', $assignment->asset) }}"
                                            class="font-medium text-slate-900 hover-ink">
                                            {{ $assignment->asset->name }}
                                        </a>
                                        <p class="text-xs text-slate-500">
                                            {{ $assignment->asset->typeLabel() }} · tag {{ $assignment->asset->asset_tag }}
                                            · since {{ $assignment->issued_on->format('d M Y') }}
                                        </p>
                                    </div>
                                    <x-badge color="sky">With them</x-badge>
                                </li>
                            @endforeach
                        </ul>

                        @if ($employee->date_of_exit)
                            <x-alert type="warning" class="mt-4">
                                This person is leaving on {{ $employee->date_of_exit->format('d M Y') }} and still
                                has company property. Take it back before issuing a relieving letter.
                            </x-alert>
                        @endif
                    @endif

                    @if ($returnedAssets->isNotEmpty())
                        <div class="mt-5 border-t border-slate-100 pt-4">
                            <p class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Handed back</p>
                            <ul class="space-y-1.5">
                                @foreach ($returnedAssets as $assignment)
                                    <li class="text-sm text-slate-600">
                                        {{ $assignment->asset?->name ?? 'An asset since removed' }}
                                        <span class="text-slate-400">
                                            — {{ $assignment->issued_on->format('M Y') }} to {{ $assignment->returned_on->format('M Y') }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </x-card>
                </div>
            @endcan

            {{-- ------------------------------------------------------- leave --}}
            <div data-tab-panel="leave">
            <x-card title="Recent leave">
                @forelse ($recentLeave as $leaveRequest)
                    <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 py-3 first:pt-0 last:border-0 last:pb-0">
                        <span class="size-2 shrink-0 rounded-full" style="background-color: {{ $leaveRequest->leaveType->color }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-900">{{ $leaveRequest->leaveType->name }}</p>
                            <p class="text-xs text-slate-500">{{ $leaveRequest->periodLabel() }}</p>
                        </div>
                        <span class="text-sm text-slate-600">
                            {{ rtrim(rtrim(number_format($leaveRequest->total_days, 1), '0'), '.') }}d
                        </span>
                        <x-badge :color="$leaveRequest->status->color()">{{ $leaveRequest->status->label() }}</x-badge>
                    </div>
                @empty
                    <x-empty title="No leave taken yet" />
                @endforelse
            </x-card>

            </div>

            @if ($employee->subordinates->isNotEmpty())
                <div data-tab-panel="team">
                <x-card title="Direct reports" :subtitle="$employee->subordinates->count() . ' team member(s)'">
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($employee->subordinates as $report)
                            <a href="{{ route('employees.show', $report) }}"
                                class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 transition hover:border-brand-300 hover:bg-slate-50">
                                <x-avatar :name="$report->full_name" :src="$report->photoUrl()" size="sm" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-900">{{ $report->full_name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $report->designation?->name ?? '-' }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </x-card>
                </div>
            @endif

            </x-tabs>
        </div>

        {{-- ------------------------------------------------------ side rail --}}
        <div class="space-y-6">
            <x-card title="Leave balance">
                @forelse ($leaveBalance as $row)
                    <div class="border-b border-slate-100 py-2.5 first:pt-0 last:border-0 last:pb-0">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="text-sm text-slate-700">{{ $row['leave_type']->name }}</span>
                            <span class="text-sm font-semibold text-slate-900">
                                {{ rtrim(rtrim(number_format($row['remaining'], 1), '0'), '.') }}
                                <span class="text-xs font-normal text-slate-400">/ {{ rtrim(rtrim(number_format($row['entitled'], 1), '0'), '.') }}</span>
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-center text-sm text-slate-500">No allocations for this year.</p>
                @endforelse
            </x-card>

            @if ($canViewSalary)
                <x-card title="Salary">
                    <x-slot:actions>
                        @can('payroll.manage-structures')
                            <x-button href="{{ route('salary-structures.create', ['employee_id' => $employee->id]) }}"
                                variant="ghost" size="sm">Revise</x-button>
                        @endcan
                    </x-slot:actions>

                    @if ($salaryStructure)
                        <dl class="space-y-2.5 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Annual CTC</dt>
                                <dd class="font-semibold text-slate-900">
                                    <x-money :amount="$salaryStructure->ctc_annual" :currency="$salaryStructure->currency" :decimals="0" />
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Monthly gross</dt>
                                <dd class="font-medium text-slate-900">
                                    <x-money :amount="$salaryStructure->gross_monthly" :currency="$salaryStructure->currency" />
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Basic</dt>
                                <dd class="text-slate-800">
                                    <x-money :amount="$salaryStructure->basic_salary" :currency="$salaryStructure->currency" />
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Effective from</dt>
                                <dd class="text-slate-800">{{ $salaryStructure->effective_from->format('d M Y') }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Payment mode</dt>
                                <dd class="text-slate-800">{{ App\Models\SalaryStructure::PAYMENT_MODES[$salaryStructure->payment_mode] ?? '-' }}</dd>
                            </div>
                        </dl>
                        @can('payroll.manage-structures')
                            <x-button href="{{ route('salary-structures.show', $salaryStructure) }}" variant="secondary" size="sm" class="mt-4 w-full">
                                View structure
                            </x-button>
                        @endcan
                    @else
                        <x-empty title="No salary structure" message="Payroll will skip this employee until one is set.">
                            <x-slot:action>
                                @can('payroll.manage-structures')
                                    <x-button href="{{ route('salary-structures.create', ['employee_id' => $employee->id]) }}" size="sm">
                                        Add structure
                                    </x-button>
                                @endcan
                            </x-slot:action>
                        </x-empty>
                    @endif
                </x-card>

                <x-card title="Recent payslips">
                    @forelse ($payslips as $payslip)
                        <a href="{{ route('payslips.show', $payslip) }}"
                            class="flex items-center justify-between border-b border-slate-100 py-2.5 transition first:pt-0 last:border-0 last:pb-0 hover-ink">
                            <span class="text-sm text-slate-700">{{ $payslip->periodLabel() }}</span>
                            <span class="text-sm font-medium text-slate-900">
                                <x-money :amount="$payslip->net_pay" :currency="$payslip->currency" :decimals="0" />
                            </span>
                        </a>
                    @empty
                        <p class="py-4 text-center text-sm text-slate-500">No payslips generated yet.</p>
                    @endforelse
                </x-card>
            @endif

            @can('update', $employee)
                @if ($employee->isOnRoll())
                    <x-card title="Offboarding">
                        <p class="text-sm text-slate-500">
                            Record the exit date to close the employee record and disable their login.
                        </p>
                        <x-button type="button" variant="secondary" size="sm" class="mt-3 w-full" data-dialog-open="offboard">
                            Start offboarding
                        </x-button>
                    </x-card>
                @endif
            @endcan
        </div>
    </div>

    {{-- ------------------------------------------------------------ dialogs --}}
    @can('manageDocuments', $employee)
        <x-dialog name="upload-document" title="Upload document">
            <form id="upload-document-form" method="POST" action="{{ route('employees.documents.store', $employee) }}" enctype="multipart/form-data">
                @csrf
                <div class="space-y-4 px-5 py-5">
                    <x-input label="Title" name="title" required placeholder="Offer letter" />
                    <x-select label="Document type" name="type" required :options="App\Models\EmployeeDocument::TYPES" />
                    <div>
                        <label class="form-label" for="file">File <span class="text-rose-500">*</span></label>
                        <input type="file" name="file" id="file" required
                            class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                        <p class="form-help">PDF, image, Word or Excel. Up to 10 MB.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="Issue date" name="issue_date" type="date" />
                        <x-input label="Expiry date" name="expiry_date" type="date" />
                    </div>
                    <x-textarea label="Notes" name="notes" rows="2" />
                </div>
            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="secondary" data-dialog-close="upload-document">Cancel</x-button>
                    <x-button form="upload-document-form">Upload document</x-button>
                </div>
            </x-slot:footer>
        </x-dialog>
    @endcan

    @can('update', $employee)
        <x-dialog name="offboard" title="Offboard employee">
            <form id="offboard-form" method="POST" action="{{ route('employees.offboard', $employee) }}">
                @csrf
                <div class="space-y-4 px-5 py-5">
                    <x-alert type="warning" :dismissible="false">
                        This marks {{ $employee->full_name }} as exited and disables their login.
                        Their historical attendance, leave and payslips are kept.
                    </x-alert>
                    <x-input label="Last working day" name="date_of_exit" type="date" required
                        :value="now()->toDateString()" />
                    <x-select label="Exit type" name="employment_status" required
                        :options="['resigned' => 'Resigned', 'terminated' => 'Terminated', 'retired' => 'Retired']" />
                    <x-textarea label="Reason" name="exit_reason" rows="3" />
                </div>
            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="secondary" data-dialog-close="offboard">Cancel</x-button>
                    <x-button form="offboard-form" variant="danger">Confirm offboarding</x-button>
                </div>
            </x-slot:footer>
        </x-dialog>
    @endcan
</x-app-layout>
