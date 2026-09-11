<x-app-layout title="Check the file">
    <x-page-header :title="$import->original_filename"
        :subtitle="$import->typeLabel().' · uploaded '.$import->created_at->format('d M Y, h:i A').' by '.($import->user?->name ?? 'someone since removed')">
        <x-slot:actions>
            <x-button href="{{ route('data-import.show', $import->type) }}" variant="secondary">
                Upload another
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Rows in the file" :value="number_format($import->rows_total)" color="slate">
            <x-slot:icon><x-icon.list class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Ready to import" :value="number_format($import->rows_valid)" color="emerald">
            <x-slot:icon><x-icon.check class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="With problems" :value="number_format($import->rows_invalid)" :color="$import->rows_invalid ? 'rose' : 'slate'">
            <x-slot:icon><x-icon.x class="size-5" /></x-slot:icon>
        </x-stat>
        @if ($import->isImported())
            <x-stat label="Imported" :value="number_format($import->rows_created + $import->rows_updated)" color="brand">
                <x-slot:icon><x-icon.upload class="size-5" /></x-slot:icon>
            </x-stat>
        @else
            <x-stat label="Status" value="Checked" color="sky"
                :sublabel="$import->hasErrors() ? 'Nothing written yet' : 'Ready to import'">
                <x-slot:icon><x-icon.info class="size-5" /></x-slot:icon>
            </x-stat>
        @endif
    </div>

    @if ($import->status === \App\Models\DataImport::STATUS_FAILED)
        <x-alert type="error" class="mb-6" title="Nothing was saved">
            {{ $import->failure_reason }}
        </x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($import->hasErrors())
                <x-card :padded="false" title="Rows that cannot be imported"
                    subtitle="The row number is the one your spreadsheet shows, counting the header as row 1.">
                    <x-slot:actions>
                        <x-button href="{{ route('data-import.errors', $import) }}" variant="secondary" size="sm">
                            <x-icon.download class="size-4" /> Download these rows
                        </x-button>
                    </x-slot:actions>

                    <div class="table-wrap is-scrollable">
                        <table class="table">
                            <thead>
                                <tr><th class="w-16">Row</th><th>What is wrong</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($import->errors as $error)
                                    <tr>
                                        <td class="tabular-nums font-medium">{{ $error['line'] }}</td>
                                        <td>
                                            <ul class="space-y-1 text-sm text-rose-700">
                                                @foreach ($error['problems'] as $problem)
                                                    <li>{{ $problem }}</li>
                                                @endforeach
                                            </ul>
                                            @php $identity = collect($error['values'])->filter()->take(3)->implode(' · '); @endphp
                                            @if ($identity)
                                                <div class="mt-1 truncate text-xs text-slate-400">{{ $identity }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif

            <x-card title="The first few rows, as they were read" :padded="false">
                <div class="table-wrap is-scrollable">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="w-16">Row</th>
                                @foreach ($preview['headers'] as $header)
                                    <th class="font-mono text-xs whitespace-nowrap">{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($preview['rows'] as $row)
                                <tr>
                                    <td class="tabular-nums text-slate-400">{{ $row['line'] }}</td>
                                    @foreach ($preview['headers'] as $header)
                                        <td class="max-w-48 truncate text-sm">{{ $row['values'][$header] ?? '' }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($preview['headers']) + 1 }}">
                                        <x-empty title="The file holds no data rows"
                                            message="There is a header row but nothing under it." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            @if ($import->isImported())
                <x-card title="Imported">
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Added</dt>
                            <dd class="tabular-nums text-slate-900">{{ number_format($import->rows_created) }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Updated</dt>
                            <dd class="tabular-nums text-slate-900">{{ number_format($import->rows_updated) }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Skipped</dt>
                            <dd class="tabular-nums text-slate-900">{{ number_format($import->rows_skipped) }}</dd>
                        </div>
                        <div class="flex justify-between gap-2 border-t border-slate-100 pt-2.5">
                            <dt class="text-slate-500">When</dt>
                            <dd class="text-right text-slate-900">{{ $import->imported_at?->format('d M Y, h:i A') }}</dd>
                        </div>
                    </dl>

                    @if ($import->rows_skipped > 0)
                        <p class="mt-4 text-xs text-slate-500">
                            Skipped rows are the ones that could not be imported. Download them from
                            the panel on the left, fix them, and upload that file on its own.
                        </p>
                    @endif
                </x-card>
            @elseif ($import->hasSomethingToImport())
                <x-card title="Import this file">
                    <form method="POST" action="{{ route('data-import.commit', $import) }}" class="space-y-4">
                        @csrf

                        <p class="text-sm text-slate-600">
                            {{ number_format($import->rows_valid) }}
                            {{ $import->rows_valid === 1 ? 'row is' : 'rows are' }} ready.
                            A code that already exists updates that record; anything new is added.
                        </p>

                        @if ($import->hasErrors())
                            <x-checkbox name="skip_invalid" :checked="true"
                                label="Import the good rows and leave the {{ $import->rows_invalid }} with problems"
                                help="Untick to refuse the whole file until every row is right." />
                        @else
                            <input type="hidden" name="skip_invalid" value="1">
                        @endif

                        <x-button class="w-full" data-confirm="Import {{ number_format($import->rows_valid) }} rows into {{ strtolower($import->typeLabel()) }}?">
                            Import {{ number_format($import->rows_valid) }} {{ $import->rows_valid === 1 ? 'row' : 'rows' }}
                        </x-button>

                        <p class="text-xs text-slate-500">
                            It all happens together. If anything goes wrong part way, the whole file is
                            rolled back and nothing is saved.
                        </p>
                    </form>
                </x-card>
            @else
                <x-card title="Nothing to import">
                    <p class="text-sm text-slate-600">
                        Not one row in this file can be used. Download the rows above, fix what is
                        named against each one, and upload the file again.
                    </p>
                </x-card>
            @endif

            <x-card title="What this file is for">
                <p class="text-sm text-slate-600">{{ $definition['description'] }}</p>
                <div class="mt-4">
                    <x-button href="{{ route('data-import.show', $import->type) }}" variant="secondary" size="sm">
                        Column reference
                    </x-button>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
