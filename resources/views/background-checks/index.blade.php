<x-app-layout title="Background verification">
    <x-page-header title="Background verification"
        subtitle="New joiners' documents, and the decision that onboards them">
        <x-slot:actions>
            @can('bgv.manage')
                <x-button data-dialog-open="invite">
                    <x-icon.plus class="size-4" /> Ask someone for documents
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Where every case has got to --}}
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('background-checks.index') }}"
            @class([
                'rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition',
                'bg-brand-50 text-brand-700 ring-brand-600/20' => ! $status,
                'bg-white text-slate-600 ring-slate-300 hover:bg-slate-50' => (bool) $status,
            ])>
            All <span class="text-slate-400">{{ array_sum($counts) }}</span>
        </a>

        @foreach (App\Models\BackgroundCheck::STATUSES as $key => $label)
            <a href="{{ route('background-checks.index', ['status' => $key]) }}"
                @class([
                    'rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition',
                    'bg-brand-50 text-brand-700 ring-brand-600/20' => $status === $key,
                    'bg-white text-slate-600 ring-slate-300 hover:bg-slate-50' => $status !== $key,
                ])>
                {{ $label }} <span class="text-slate-400">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th class="w-40">Joining</th>
                        <th class="w-44">Documents</th>
                        <th class="w-40">Stage</th>
                        <th class="w-32">Waiting since</th>
                        <th class="w-24"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($checks as $check)
                        <tr>
                            <td>
                                <a href="{{ route('background-checks.show', $check) }}"
                                    class="font-medium text-slate-900 hover-ink">
                                    {{ $check->employee->full_name }}
                                </a>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $check->employee->employee_code }}
                                    @if ($check->employee->designation)
                                        &middot; {{ $check->employee->designation->name }}
                                    @endif
                                </p>
                            </td>
                            <td class="text-sm text-slate-600">
                                {{ $check->employee->date_of_joining?->format('d M Y') ?? '—' }}
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-brand-500"
                                            style="width: {{ max($check->progress(), 2) }}%"></div>
                                    </div>
                                    <span class="text-xs text-slate-500">
                                        {{ $check->items->filter(fn ($i) => $i->hasUpload())->count() }}/{{ $check->items->count() }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <x-badge :color="match ($check->status) {
                                    App\Models\BackgroundCheck::VERIFIED => 'emerald',
                                    App\Models\BackgroundCheck::SUBMITTED => 'sky',
                                    App\Models\BackgroundCheck::CHANGES_REQUESTED => 'amber',
                                    default => 'slate',
                                }" dot>{{ $check->statusLabel() }}</x-badge>

                                @if ($check->isOverdue())
                                    <x-badge color="rose" class="mt-1">Overdue</x-badge>
                                @endif
                            </td>
                            <td class="text-sm text-slate-600">
                                {{ ($check->submitted_at ?? $check->invited_at)?->diffForHumans() ?? '—' }}
                            </td>
                            <td class="num">
                                <x-button variant="secondary" size="sm" :href="route('background-checks.show', $check)">
                                    {{ $check->isAwaitingReview() ? 'Review' : 'Open' }}
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty title="Nothing here"
                                    description="Nobody is at this stage. Ask a new joiner for their documents to start one." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($checks->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">{{ $checks->links() }}</div>
        @endif
    </x-card>

    @can('bgv.manage')
        <x-dialog name="invite" title="Ask someone for their documents"
            description="They are emailed a checklist and a link to their own screen."
            :open="$errors->hasAny(['employee_id', 'due_on', 'requirements'])">
            <form method="POST" action="{{ route('background-checks.invite') }}" id="invite-form" class="space-y-6 px-5 py-5">
                @csrf

                <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_11rem]">
                    <x-select name="employee_id" label="Employee" required placeholder="Choose someone">
                        @foreach ($employeesWithout as $employee)
                            <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>
                                {{ $employee->full_name }} ({{ $employee->employee_code }})
                            </option>
                        @endforeach
                    </x-select>

                    <x-input name="due_on" type="date" label="Complete by" :value="old('due_on')"
                        :min="now()->toDateString()" help="Optional" />
                </div>

                @if ($employeesWithout->isEmpty())
                    <x-alert type="info" :dismissible="false">
                        Everyone you can see has already been asked. New joiners appear here as soon as they are added.
                    </x-alert>
                @endif

                <fieldset>
                    <legend class="form-label">What to ask for</legend>
                    <p class="-mt-1 mb-3 text-xs text-slate-500">
                        The standard set is ticked. Untick what does not apply, or add the rest.
                    </p>

                    @include('background-checks.partials.requirement-picker', [
                        'ticked' => old('requirements') !== null
                            ? (array) old('requirements')
                            : array_keys(array_filter($requirements, fn ($r) => $r['default'])),
                        'uploaded' => [],
                    ])
                    @error('requirements')<p class="form-error">{{ $message }}</p>@enderror
                    <p class="form-help">You can change the list later from the case itself.</p>
                </fieldset>
            </form>

            <x-slot:footer>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-button type="button" variant="secondary" data-dialog-close="invite" class="w-full sm:w-auto">Cancel</x-button>
                    <x-button form="invite-form" class="w-full sm:w-auto" :disabled="$employeesWithout->isEmpty()">
                        <x-icon.mail class="size-4" /> Send the request
                    </x-button>
                </div>
            </x-slot:footer>
        </x-dialog>
    @endcan
</x-app-layout>
