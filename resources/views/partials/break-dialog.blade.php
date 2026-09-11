{{--
    The break picker.

    It lives here, at the end of the page, rather than beside the button that
    opens it. The header is sticky with a z-index of its own, which makes it a
    stacking context: a dialog inside it can never rise above the sidebar,
    however high its own z-index goes, so it opened behind the page.
--}}
@php
    $breakEmployee = auth()->user()?->employee;
    $breakToday = $breakEmployee && ! $breakEmployee->awaitingBackgroundCheck()
        ? app(\App\Services\AttendanceService::class)->todayFor($breakEmployee)?->load(['sessions', 'breaks'])
        : null;
@endphp

@can('attendance.punch')
    @if ($breakToday?->isPunchedIn() && ! $breakToday->isOnBreak())
        <x-dialog name="break" title="What is the break for?" size="sm">
            <form id="break-form" method="POST" action="{{ route('attendance.break.start') }}" class="space-y-4">
                @csrf

                <div class="grid gap-1.5 sm:grid-cols-2">
                    @foreach (App\Support\BreakReasons::all() as $key => $reason)
                        <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-slate-200 px-3 py-2.5 transition hover:border-brand-300 hover:bg-brand-50/40 has-checked:border-brand-400 has-checked:bg-brand-50">
                            <input type="radio" name="reason" value="{{ $key }}"
                                class="form-checkbox mt-0.5 rounded-full" @checked(old('reason', $loop->first ? $key : null) === $key)>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-800">{{ $reason['label'] }}</span>
                                <span class="block text-xs text-slate-500">{{ $reason['description'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('reason')<p class="form-error">{{ $message }}</p>@enderror

                <x-input name="comment" label="Anything to add?" placeholder="Optional comment"
                    help="Only you and your manager see this." />

            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="secondary" data-dialog-close>Go back</x-button>
                    <x-button form="break-form">Start the break</x-button>
                </div>
            </x-slot:footer>
        </x-dialog>
    @endif
@endcan
