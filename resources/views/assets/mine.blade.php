<x-app-layout title="My assets">
    <x-page-header title="My assets" subtitle="Company property issued to you, and what you have handed back" />

    <x-card class="mb-5">
        <div class="border-b border-slate-100 px-5 py-4">
            <h3 class="text-sm font-semibold tracking-wide text-slate-500 uppercase">With you now</h3>
            <p class="mt-1 text-xs text-slate-500">
                Yours to look after until it is handed back, and expected back on your last working day.
            </p>
        </div>

        @if ($held->isEmpty())
            <x-empty title="Nothing issued to you"
                message="When the company hands you a laptop, a phone or an access card, it appears here." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($held as $assignment)
                    <li class="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900">{{ $assignment->asset->name }}</p>
                            <p class="text-sm text-slate-600">
                                {{ $assignment->asset->typeLabel() }}
                                · tag {{ $assignment->asset->asset_tag }}
                                @if ($assignment->asset->identifier())
                                    · {{ $assignment->asset->identifier() }}
                                @endif
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Issued {{ $assignment->issued_on->format('d M Y') }} in
                                {{ strtolower(\App\Models\Asset::CONDITIONS[$assignment->condition_out] ?? $assignment->condition_out) }} condition
                                · {{ $assignment->heldDays() }} days
                            </p>
                            @if ($assignment->issue_remarks)
                                <p class="mt-1 text-xs text-slate-500">{{ $assignment->issue_remarks }}</p>
                            @endif
                        </div>
                        <x-badge color="sky">With you</x-badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    @if ($history->isNotEmpty())
        <x-card>
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="text-sm font-semibold tracking-wide text-slate-500 uppercase">Handed back</h3>
                <p class="mt-1 text-xs text-slate-500">Your receipt for everything you have returned.</p>
            </div>

            <ul class="divide-y divide-slate-100">
                @foreach ($history as $assignment)
                    <li class="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900">{{ $assignment->asset?->name ?? 'An asset since removed' }}</p>
                            <p class="text-sm text-slate-600">
                                {{ $assignment->issued_on->format('d M Y') }} &rarr; {{ $assignment->returned_on->format('d M Y') }}
                            </p>
                            @if ($assignment->return_remarks)
                                <p class="mt-1 text-xs text-slate-500">{{ $assignment->return_remarks }}</p>
                            @endif
                        </div>
                        <x-badge color="slate">Returned</x-badge>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</x-app-layout>
