@php
    use App\Models\BackgroundCheck;
    use App\Models\BackgroundCheckItem;

    $editable = $check->isOpenToEmployee();
    $outstanding = $check->itemsNeedingWork();
    $items = $check->items->values();
    $required = $check->requiredItems();
    $provided = $required->filter(fn ($i) => $i->hasUpload())->count();

    // The first document that still needs something is the one open on
    // arrival; everything done sits folded above it with a tick.
    $current = $items->first(fn ($i) => $i->needsWork())?->id;

    // Where the whole case is in its journey, for the strip at the top.
    $stage = match (true) {
        $check->isVerified() => 4,
        $check->isAwaitingReview() => 3,
        $check->isReadyToSubmit() => 2,
        default => 1,
    };

    $stages = [
        1 => ['Upload documents', 'One at a time, in any order'],
        2 => ['Submit', 'Send the set to HR'],
        3 => ['HR review', 'Usually a couple of working days'],
        4 => ['Onboarded', 'Attendance, leave and pay open up'],
    ];
@endphp

<x-app-layout title="My verification">
    <x-page-header title="My verification"
        subtitle="A few documents before your onboarding is confirmed. Do them at your own pace; nothing goes to HR until you submit." />

    {{-- ------------------------------------------------- journey strip --}}
    <ol class="mb-6 grid gap-2 sm:grid-cols-4" aria-label="Where this is up to">
        @foreach ($stages as $number => [$label, $hint])
            @php
                $done = $number < $stage || $check->isVerified();
                $active = $number === $stage && ! $check->isVerified();
            @endphp
            <li @class([
                'flex items-center gap-3 rounded-xl border px-4 py-3 transition',
                'border-emerald-200 bg-emerald-50/60' => $done,
                'border-brand-300 bg-white shadow-sm ring-2 ring-brand-500/20' => $active,
                'border-slate-200 bg-white' => ! $done && ! $active,
            ])>
                <span @class([
                    'flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                    'bg-emerald-600 text-white' => $done,
                    'bg-brand-600 text-white' => $active,
                    'bg-slate-100 text-slate-500' => ! $done && ! $active,
                ])>
                    @if ($done)
                        <x-icon.check class="size-4" />
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span class="min-w-0">
                    <span @class(['block text-sm font-semibold', 'text-emerald-800' => $done, 'text-slate-900' => ! $done])>{{ $label }}</span>
                    <span class="block truncate text-xs text-slate-500">{{ $hint }}</span>
                </span>
            </li>
        @endforeach
    </ol>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-4">

            {{-- --------------------------------------------- status notes --}}
            @if ($check->isVerified())
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-6">
                    <div class="flex items-start gap-4">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white">
                            <x-icon.check class="size-6" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-emerald-900">You are all set</h2>
                            <p class="mt-1 text-sm text-emerald-800">
                                Your documents were accepted on {{ $check->reviewed_at?->format('d M Y') }} and your onboarding is
                                confirmed. Everything is open to you now.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                @can('attendance.view-own')
                                    <x-button :href="route('attendance.index')" variant="secondary" size="sm">
                                        <x-icon.clock class="size-4" /> My Attendance
                                    </x-button>
                                @endcan
                                @can('leave.view-own')
                                    <x-button :href="route('leave.index')" variant="secondary" size="sm">
                                        <x-icon.calendar class="size-4" /> Leave
                                    </x-button>
                                @endcan
                                @can('payslips.view-own')
                                    <x-button :href="route('payslips.index')" variant="secondary" size="sm">
                                        <x-icon.receipt class="size-4" /> Salary Slips
                                    </x-button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            @elseif ($check->isAwaitingReview())
                <x-alert type="info" :dismissible="false">
                    <span>
                        <strong>With our HR team.</strong>
                        You submitted on {{ $check->submitted_at?->format('d M Y, h:i A') }}. We will email you once it has
                        been looked at, usually within a couple of working days. Nothing to do until then.
                    </span>
                </x-alert>
            @elseif ($check->status === BackgroundCheck::CHANGES_REQUESTED)
                <x-alert type="warning" :dismissible="false">
                    <span>
                        <strong>{{ $outstanding->count() }} {{ Str::plural('document', $outstanding->count()) }} to replace.</strong>
                        {{ $check->review_remarks ?: 'The note on each one says what was wrong. Replace them and submit again; everything else stays accepted.' }}
                    </span>
                </x-alert>
            @endif

            @error('submit')
                <x-alert type="error" :dismissible="false">{{ $message }}</x-alert>
            @enderror

            {{-- ---------------------------------------------- the steps --}}
            <ol class="space-y-3" aria-label="Documents">
                @foreach ($items as $index => $item)
                    @php
                        $isCurrent = $editable && $item->id === $current;
                        $rejected = $item->status === BackgroundCheckItem::REJECTED;
                        $state = match (true) {
                            $item->isVerified() => 'verified',
                            $rejected => 'rejected',
                            $item->hasUpload() => 'uploaded',
                            default => 'pending',
                        };
                        // Open on arrival: the one to do now, and anything sent back.
                        $open = $isCurrent || ($editable && $rejected) || (! $editable && false);
                    @endphp

                    <li>
                        <details @class([
                            'group card overflow-hidden transition',
                            'ring-2 ring-brand-500/30' => $isCurrent,
                            'border-rose-300' => $rejected && $editable,
                        ]) @if ($open) open @endif data-verification-step>
                            <summary class="flex cursor-pointer list-none items-center gap-4 px-5 py-4 select-none hover:bg-slate-50/70 [&::-webkit-details-marker]:hidden">
                                <span @class([
                                    'flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                                    'bg-emerald-600 text-white' => $state === 'verified',
                                    'bg-sky-100 text-sky-700' => $state === 'uploaded',
                                    'bg-rose-100 text-rose-700' => $state === 'rejected',
                                    'bg-brand-600 text-white' => $state === 'pending' && $isCurrent,
                                    'bg-slate-100 text-slate-500' => $state === 'pending' && ! $isCurrent,
                                ])>
                                    @if ($state === 'verified' || $state === 'uploaded')
                                        <x-icon.check class="size-4" />
                                    @elseif ($state === 'rejected')
                                        !
                                    @else
                                        {{ $index + 1 }}
                                    @endif
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Step {{ $index + 1 }} of {{ $items->count() }}</span>
                                        @unless ($item->is_required)
                                            <x-badge>Optional</x-badge>
                                        @endunless
                                    </span>
                                    <span class="block truncate font-semibold text-slate-900">{{ $item->label() }}</span>
                                    <span class="block truncate text-sm text-slate-500">
                                        @if ($state === 'verified')
                                            Accepted by HR
                                        @elseif ($state === 'rejected')
                                            Needs replacing: {{ $item->remarks ?: 'see the note below' }}
                                        @elseif ($state === 'uploaded')
                                            {{ $item->file_name }} &middot; saved {{ $item->uploaded_at?->diffForHumans() }}
                                        @elseif ($isCurrent)
                                            Do this one next
                                        @else
                                            Not provided yet
                                        @endif
                                    </span>
                                </span>

                                <span class="flex shrink-0 items-center gap-3">
                                    <x-badge :color="$item->statusColour()" dot class="hidden sm:inline-flex">{{ $item->statusLabel() }}</x-badge>
                                    <svg class="size-5 text-slate-400 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </span>
                            </summary>

                            <div class="border-t border-slate-100 px-5 py-5">
                                <p class="max-w-2xl text-sm text-slate-600">{{ $item->description() }}</p>

                                @if ($rejected && $item->remarks)
                                    <x-alert type="error" class="mt-4" :dismissible="false">
                                        <span><strong>Please replace this.</strong> {{ $item->remarks }}</span>
                                    </x-alert>
                                @endif

                                @if ($item->hasUpload())
                                    <a href="{{ route('background-checks.items.document', $item) }}"
                                        class="mt-4 inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
                                        <x-icon.document class="size-4 text-slate-400" />
                                        {{ $item->file_name }}
                                        <span class="text-xs text-slate-400">{{ $item->humanSize() }}</span>
                                    </a>
                                @endif

                                @if ($editable)
                                    <form method="POST" action="{{ route('my-verification.upload', $item) }}"
                                        enctype="multipart/form-data" class="mt-5 space-y-5">
                                        @csrf

                                        @if ($item->fields())
                                            <div>
                                                <p class="mb-3 text-sm font-medium text-slate-900">
                                                    <span class="mr-1 inline-flex size-5 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">1</span>
                                                    Fill in the details
                                                </p>
                                                <div class="grid gap-4 sm:grid-cols-2">
                                                    @foreach ($item->fields() as $field => $definition)
                                                        @php $name = 'details['.$field.']'; @endphp

                                                        @if ($definition['type'] === 'textarea')
                                                            <x-textarea :name="$name" :label="$definition['label']" :rows="2"
                                                                :required="$definition['required']" class="sm:col-span-2"
                                                                :value="$item->detail($field)" />
                                                        @else
                                                            <x-input :name="$name" :label="$definition['label']"
                                                                :type="$definition['type'] === 'date' ? 'date' : 'text'"
                                                                :required="$definition['required']" :value="$item->detail($field)" />
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <div>
                                            <p class="mb-3 text-sm font-medium text-slate-900">
                                                <span class="mr-1 inline-flex size-5 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ $item->fields() ? 2 : 1 }}</span>
                                                {{ $item->hasUpload() ? 'Replace the file, if you need to' : 'Attach the document' }}
                                                @unless ($item->hasUpload())<span class="text-rose-500">*</span>@endunless
                                            </p>

                                            <label for="file-{{ $item->id }}"
                                                class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center transition hover:border-brand-400 hover:bg-brand-50/40 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/30">
                                                <x-icon.upload class="size-7 text-brand-600" />
                                                <span class="text-sm font-medium text-slate-800">
                                                    Tap to choose a file, or take a photo on your phone
                                                </span>
                                                <span class="text-xs text-slate-500">PDF, JPG or PNG, up to 10 MB. Make sure every corner is readable.</span>
                                                <input type="file" name="file" id="file-{{ $item->id }}"
                                                    accept="application/pdf,image/jpeg,image/png"
                                                    @required(! $item->hasUpload())
                                                    class="mt-2 block w-full max-w-xs text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-brand-700 file:ring-1 file:ring-slate-300">
                                            </label>
                                            @error('file')<p class="form-error">{{ $message }}</p>@enderror
                                        </div>

                                        <div class="flex flex-wrap items-center gap-3">
                                            <x-button>
                                                <x-icon.check class="size-4" />
                                                {{ $item->hasUpload() ? 'Save changes' : 'Save and continue' }}
                                            </x-button>
                                            <span class="text-xs text-slate-500">Saved as you go. You can come back and change it until you submit.</span>
                                        </div>
                                    </form>
                                @else
                                    <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                                        @foreach ($item->fields() as $field => $definition)
                                            <div class="flex justify-between gap-3 sm:block">
                                                <dt class="text-slate-500">{{ $definition['label'] }}</dt>
                                                <dd class="text-slate-900">{{ $item->detail($field) ?: '—' }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                @endif
                            </div>
                        </details>
                    </li>
                @endforeach
            </ol>

            {{-- ------------------------------------------- the last step --}}
            @if ($editable)
                <div @class([
                    'card p-5 transition',
                    'ring-2 ring-brand-500/30' => $check->isReadyToSubmit(),
                ])>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <span @class([
                            'flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                            'bg-brand-600 text-white' => $check->isReadyToSubmit(),
                            'bg-slate-100 text-slate-500' => ! $check->isReadyToSubmit(),
                        ])>{{ $items->count() + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-900">Submit to HR</p>
                            <p class="text-sm text-slate-500">
                                @if ($check->isReadyToSubmit())
                                    Everything is here. Once submitted you cannot change it unless HR sends something back.
                                @else
                                    {{ $outstanding->count() }} {{ Str::plural('document', $outstanding->count()) }} still to provide before you can submit.
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('my-verification.submit') }}">
                            @csrf
                            <x-button size="lg" :disabled="! $check->isReadyToSubmit()"
                                data-confirm="Submit these documents to HR? You will not be able to change them afterwards.">
                                Submit for review
                            </x-button>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        {{-- --------------------------------------------------- side panel --}}
        <div class="space-y-4 self-start lg:sticky lg:top-20">
            <x-card title="Where you are up to">
                <div class="flex items-center gap-5">
                    @php
                        $percent = $check->isVerified() || $check->isAwaitingReview() ? 100 : $check->progress();
                        $ring = 2 * M_PI * 30;
                    @endphp
                    <div class="relative size-20 shrink-0">
                        <svg class="size-20 -rotate-90" viewBox="0 0 72 72" aria-hidden="true">
                            <circle cx="36" cy="36" r="30" fill="none" stroke="currentColor" stroke-width="7" class="text-slate-100" />
                            <circle cx="36" cy="36" r="30" fill="none" stroke="currentColor" stroke-width="7" stroke-linecap="round"
                                class="{{ $check->isVerified() ? 'text-emerald-500' : 'text-brand-500' }} transition-all"
                                stroke-dasharray="{{ round($ring, 1) }}"
                                stroke-dashoffset="{{ round($ring * (1 - $percent / 100), 1) }}" />
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-lg font-semibold text-slate-900">{{ $percent }}%</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-900">
                            {{ $provided }} of {{ $required->count() }} provided
                        </p>
                        <p class="mt-0.5 text-sm text-slate-500">
                            @if ($check->isVerified())
                                Verified and onboarded.
                            @elseif ($check->isAwaitingReview())
                                Waiting for HR.
                            @elseif ($outstanding->isEmpty())
                                Ready to submit.
                            @else
                                {{ $outstanding->count() }} to go.
                            @endif
                        </p>
                    </div>
                </div>

                @if ($check->due_on && ! $check->isVerified())
                    <p @class(['form-help mt-4 flex items-center gap-1.5', 'text-rose-600 font-medium' => $check->isOverdue()])>
                        <x-icon.calendar class="size-4" />
                        @if ($check->isOverdue())
                            Due {{ $check->due_on->format('d M Y') }}, which has passed.
                        @else
                            Please finish by {{ $check->due_on->format('d M Y') }}.
                        @endif
                    </p>
                @endif
            </x-card>

            @unless ($check->isVerified())
                <x-card>
                    <div class="flex items-start gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                            <x-icon.lock class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">Opens when this is complete</p>
                            <ul class="mt-2 space-y-1.5 text-sm text-slate-600">
                                <li class="flex items-center gap-2"><x-icon.clock class="size-4 text-slate-400" /> My Attendance</li>
                                <li class="flex items-center gap-2"><x-icon.calendar class="size-4 text-slate-400" /> Leave and leave balance</li>
                                <li class="flex items-center gap-2"><x-icon.receipt class="size-4 text-slate-400" /> Salary Slips</li>
                            </ul>
                        </div>
                    </div>
                </x-card>
            @endunless

            <x-card title="How this works">
                <ol class="space-y-2.5 text-sm text-slate-600">
                    @foreach ([
                        'Work through the steps. Each one takes a file and a few details.',
                        'Submit the set when everything is there.',
                        'HR checks it, usually within a couple of working days.',
                        'If something is unclear they send that one back. The rest stays done.',
                        'Once accepted, your onboarding is confirmed by email.',
                    ] as $i => $line)
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ $i + 1 }}</span>
                            <span>{{ $line }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="form-help mt-4 flex items-start gap-1.5">
                    <x-icon.shield class="mt-0.5 size-4 shrink-0" />
                    Your documents are stored privately and are only visible to the HR team.
                </p>
            </x-card>
        </div>
    </div>
</x-app-layout>
