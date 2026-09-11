<x-app-layout :title="'Verification · '.$check->employee->full_name">
    <x-page-header :title="$check->employee->full_name"
        :subtitle="$check->employee->employee_code.' · '.($check->employee->designation?->name ?? 'Designation to be confirmed')"
        :back="route('background-checks.index')">
        <x-slot:actions>
            <x-badge :color="match ($check->status) {
                App\Models\BackgroundCheck::VERIFIED => 'emerald',
                App\Models\BackgroundCheck::SUBMITTED => 'sky',
                App\Models\BackgroundCheck::CHANGES_REQUESTED => 'amber',
                default => 'slate',
            }" dot>{{ $check->statusLabel() }}</x-badge>

            <x-button variant="secondary" :href="route('employees.show', $check->employee)">
                <x-icon.user class="size-4" /> Employee record
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-4">
            @if ($check->isVerified())
                <x-alert type="success" :dismissible="false">
                    <span>
                        <strong>Verified and onboarded</strong>
                        on {{ $check->onboarded_at?->format('d M Y, h:i A') }}
                        by {{ $check->reviewer?->name ?? 'a colleague' }}.
                        {{ $check->review_remarks }}
                    </span>
                </x-alert>
            @elseif ($check->isAwaitingReview())
                <x-alert type="info" :dismissible="false">
                    <span>
                        Submitted {{ $check->submitted_at?->diffForHumans() }}. Accept each document
                        that is in order, then either onboard them or send the rest back.
                    </span>
                </x-alert>
            @endif

            @foreach ($check->items as $item)
                <x-card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold text-slate-900">{{ $item->label() }}</h2>
                                <x-badge :color="$item->statusColour()" dot>{{ $item->statusLabel() }}</x-badge>
                                @unless ($item->is_required)<x-badge>Optional</x-badge>@endunless
                            </div>

                            @if ($item->hasUpload())
                                <p class="mt-1 text-xs text-slate-500">
                                    Uploaded {{ $item->uploaded_at?->format('d M Y, h:i A') }}
                                    &middot; {{ $item->humanSize() }}
                                </p>
                            @else
                                <p class="mt-1 text-sm text-slate-500">Not provided yet.</p>
                            @endif
                        </div>

                        @if ($item->hasUpload())
                            <x-button variant="secondary" size="sm"
                                :href="route('background-checks.items.document', $item)">
                                <x-icon.download class="size-4" /> Open the document
                            </x-button>
                        @endif
                    </div>

                    @if (filled($item->details))
                        <dl class="mt-3 grid gap-x-6 gap-y-2 rounded-lg bg-slate-50 p-3 text-sm sm:grid-cols-2">
                            @foreach ($item->fields() as $field => $definition)
                                <div class="flex justify-between gap-3 sm:block">
                                    <dt class="text-slate-500">{{ $definition['label'] }}</dt>
                                    <dd class="font-medium text-slate-900">{{ $item->detail($field) ?: '—' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    @if ($item->remarks)
                        <p class="mt-3 text-sm {{ $item->status === App\Models\BackgroundCheckItem::REJECTED ? 'text-rose-700' : 'text-slate-600' }}">
                            <span class="font-medium">
                                {{ $item->reviewer?->name ?? 'Reviewer' }}
                                {{ $item->reviewed_at?->format('d M Y') }}:
                            </span>
                            {{ $item->remarks }}
                        </p>
                    @endif

                    @if ($canManage && $item->hasUpload() && ! $check->isVerified())
                        <form method="POST" action="{{ route('background-checks.items.review', $item) }}"
                            class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                            @csrf

                            <x-input name="remarks" label="Note"
                                help="Required when sending a document back — the employee reads it." />

                            <div class="flex flex-wrap gap-2">
                                <x-button variant="success" size="sm" name="decision" value="verify">
                                    <x-icon.check class="size-4" /> Accept
                                </x-button>
                                <x-button variant="secondary" size="sm" name="decision" value="reject">
                                    <x-icon.x class="size-4" /> Send back
                                </x-button>
                            </div>
                        </form>
                    @endif
                </x-card>
            @endforeach
        </div>

        <div class="space-y-4 self-start lg:sticky lg:top-20">
            <x-card title="The case">
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Joining</dt>
                        <dd class="text-right text-slate-900">
                            {{ $check->employee->date_of_joining?->format('d M Y') ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Branch</dt>
                        <dd class="text-right text-slate-900">{{ $check->employee->branch?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Asked on</dt>
                        <dd class="text-right text-slate-900">{{ $check->invited_at?->format('d M Y') ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Complete by</dt>
                        <dd class="text-right {{ $check->isOverdue() ? 'font-medium text-rose-600' : 'text-slate-900' }}">
                            {{ $check->due_on?->format('d M Y') ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Submitted</dt>
                        <dd class="text-right text-slate-900">{{ $check->submitted_at?->format('d M Y') ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Accepted</dt>
                        <dd class="text-right text-slate-900">
                            {{ $check->items->filter(fn ($i) => $i->isVerified())->count() }}
                            of {{ $check->items->count() }}
                        </dd>
                    </div>
                </dl>

                @if ($canManage && ! $check->isVerified())
                    <x-button variant="secondary" size="sm" class="mt-4 w-full" data-dialog-open="requirements">
                        <x-icon.pencil class="size-4" /> Change what is asked for
                    </x-button>
                @endif
            </x-card>

            @if ($canManage && ! $check->isVerified())
                <x-card title="Decision">
                    {{-- One note, two possible decisions: the buttons differ only
                         in where they post. --}}
                    <form method="POST" action="{{ route('background-checks.complete', $check) }}" class="space-y-3">
                        @csrf
                        <x-textarea name="review_remarks" label="Note to the employee" :rows="3"
                            help="Goes into whichever email you send." />

                        <x-button class="w-full"
                            data-confirm="Clear this verification and onboard {{ $check->employee->first_name }}?">
                            <x-icon.check class="size-4" /> Verify and onboard
                        </x-button>

                        <x-button variant="secondary" class="w-full"
                            formaction="{{ route('background-checks.request-changes', $check) }}">
                            Ask for changes
                        </x-button>
                    </form>

                    <p class="form-help mt-3">
                        Onboarding accepts anything still unreviewed, so send back what is wrong first.
                    </p>
                </x-card>
            @endif

            @if ($check->inviter)
                <x-card title="History">
                    <ul class="space-y-2 text-sm text-slate-600">
                        <li>Asked by {{ $check->inviter->name }} on {{ $check->invited_at?->format('d M Y') }}.</li>
                        @if ($check->submitted_at)
                            <li>Submitted by the employee on {{ $check->submitted_at->format('d M Y') }}.</li>
                        @endif
                        @if ($check->reviewed_at)
                            <li>Last reviewed by {{ $check->reviewer?->name }} on {{ $check->reviewed_at->format('d M Y') }}.</li>
                        @endif
                    </ul>
                </x-card>
            @endif
        </div>
    </div>

    @if ($canManage && ! $check->isVerified())
        <x-dialog name="requirements" title="Change what is asked for"
            :description="'Tick what '.$check->employee->first_name.' should provide. Anything new is emailed to them; anything unticked is dropped from the case.'"
            :open="$errors->hasAny(['requirements', 'due_on'])">
            <form method="POST" action="{{ route('background-checks.requirements', $check) }}" id="requirements-form" class="space-y-6 px-5 py-5">
                @csrf @method('PUT')

                <x-input name="due_on" type="date" label="Complete by" class="sm:max-w-xs"
                    :value="old('due_on', $check->due_on?->toDateString())" help="Optional" />

                <fieldset>
                    <legend class="form-label">Documents</legend>
                    @include('background-checks.partials.requirement-picker', [
                        'ticked' => old('requirements') !== null
                            ? (array) old('requirements')
                            : $check->items->pluck('requirement')->all(),
                        'uploaded' => $check->items->filter(fn ($i) => $i->hasUpload())->pluck('file_name', 'requirement')->all(),
                    ])
                    @error('requirements')<p class="form-error">{{ $message }}</p>@enderror
                </fieldset>
            </form>

            <x-slot:footer>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-button type="button" variant="secondary" data-dialog-close="requirements" class="w-full sm:w-auto">Cancel</x-button>
                    <x-button form="requirements-form" class="w-full sm:w-auto">
                        <x-icon.check class="size-4" /> Save the list
                    </x-button>
                </div>
            </x-slot:footer>
        </x-dialog>
    @endif
</x-app-layout>
