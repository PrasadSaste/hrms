<x-app-layout :title="$letter->subject">
    <x-page-header :title="$letter->subject"
        :subtitle="$letter->typeLabel().' · '.$letter->reference"
        :back="route('letters.index')">
        <x-slot:actions>
            <x-button href="{{ route('letters.download', $letter) }}" variant="secondary">
                <x-icon.download class="size-4" /> Download PDF
            </x-button>
            @can('send', $letter)
                <form method="POST" action="{{ route('letters.email', $letter) }}">
                    @csrf
                    <x-button data-confirm="Email this letter to {{ $letter->employee->email }}?">
                        <x-icon.mail class="size-4" /> Email to employee
                    </x-button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            {{-- Shown the way it prints, so nobody has to open the PDF to check. --}}
            <x-card>
                <div class="mb-6 border-b border-slate-200 pb-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                        <span class="font-mono font-medium text-slate-900">Ref: {{ $letter->reference }}</span>
                        <span class="text-slate-500">{{ $letter->issued_on->format('d F Y') }}</span>
                    </div>
                    <div class="mt-3 text-sm text-slate-700">{{ $letter->employee->full_name }}</div>
                    <div class="text-xs text-slate-500">
                        {{ $letter->employee->employee_code }}
                        @if ($letter->employee->designation) &middot; {{ $letter->employee->designation->name }} @endif
                    </div>
                </div>

                <h2 class="text-center text-sm font-semibold tracking-wide text-slate-900 uppercase">
                    {{ $letter->subject }}
                </h2>

                <div class="prose-letter mx-auto mt-6 max-w-2xl text-sm leading-relaxed text-slate-700">
                    {!! $letter->html() !!}
                </div>

                <div class="mt-10 text-sm">
                    <div class="text-slate-700">For {{ $letter->company?->displayName() ?? config('app.name') }}</div>
                    @if ($signature)
                        <img src="{{ $signature }}" alt="" class="mt-3 h-12 max-w-52 object-contain">
                    @endif
                    <div class="mt-4 font-medium text-slate-900">{{ $letter->signatory_name }}</div>
                    <div class="text-xs text-slate-500">{{ $letter->signatory_designation ?: 'Authorised Signatory' }}</div>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="This letter">
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Kind</dt>
                        <dd class="text-right text-slate-900">{{ $letter->typeLabel() }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Issued on</dt>
                        <dd class="text-right text-slate-900">{{ $letter->issued_on->format('d M Y') }}</dd>
                    </div>
                    @if ($letter->effective_from)
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Effective from</dt>
                            <dd class="text-right text-slate-900">{{ $letter->effective_from->format('d M Y') }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Issued by</dt>
                        <dd class="text-right text-slate-900">{{ $letter->issuer?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Emailed</dt>
                        <dd class="text-right text-slate-900">
                            {{ $letter->emailed_at?->format('d M Y, h:i A') ?? 'Not yet' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2 border-t border-slate-100 pt-2.5">
                        <dt class="text-slate-500">Employee</dt>
                        <dd class="text-right">
                            <a href="{{ route('employees.show', $letter->employee) }}" class="link">
                                {{ $letter->employee->full_name }}
                            </a>
                        </dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Why the words cannot change">
                <p class="text-sm text-slate-600">
                    The wording was fixed when this letter was issued. Editing the template under
                    <span class="font-medium">Wording</span> changes what the next one says and
                    leaves this one exactly as it went out — which is what somebody holding a
                    signed copy would expect.
                </p>

                @can('delete', $letter)
                    <form method="POST" action="{{ route('letters.destroy', $letter) }}" class="mt-4">
                        @csrf @method('DELETE')
                        <x-button variant="danger" size="sm"
                            data-confirm="Remove {{ $letter->reference }}? Issue it again if it was only the wording that was wrong.">
                            <x-icon.trash class="size-4" /> Remove this letter
                        </x-button>
                    </form>
                @endcan
            </x-card>
        </div>
    </div>
</x-app-layout>
