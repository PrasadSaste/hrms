<x-app-layout title="Data import">
    <x-page-header title="Data import"
        subtitle="Bring an old system's people, balances and pay in from spreadsheets" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="What to load, in this order"
                subtitle="Each sheet points at the ones above it by code, so work down the list">
                <ol class="divide-y divide-slate-100">
                    @foreach ($types as $key => $type)
                        <li class="flex items-start gap-4 py-4 first:pt-0 last:pb-0">
                            <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                                {{ $loop->iteration }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <a href="{{ route('data-import.show', $key) }}"
                                    class="font-medium text-slate-900 hover-ink">{{ $type['label'] }}</a>
                                <p class="mt-0.5 text-sm text-slate-500">{{ $type['summary'] }}</p>
                            </div>

                            <div class="shrink-0 text-right">
                                <div class="text-sm tabular-nums text-slate-500">
                                    {{ number_format($counts[$key] ?? 0) }} in the system
                                </div>
                                <x-button href="{{ route('data-import.show', $key) }}" variant="secondary" size="sm" class="mt-1.5">
                                    <x-icon.upload class="size-4" /> Import
                                </x-button>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-card>

            <x-card title="Recent imports" :padded="false">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>File</th><th>What</th><th>Result</th><th>By</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($history as $run)
                                <tr>
                                    <td>
                                        <div class="max-w-56 truncate font-medium text-slate-900">{{ $run->original_filename }}</div>
                                        <div class="text-xs text-slate-500">{{ $run->created_at->format('d M Y, h:i A') }}</div>
                                    </td>
                                    <td>{{ $run->typeLabel() }}</td>
                                    <td>
                                        <x-badge :color="$run->statusColor()">{{ $run->statusLabel() }}</x-badge>
                                        <div class="mt-0.5 text-xs tabular-nums text-slate-500">
                                            @if ($run->isImported())
                                                {{ $run->rows_created }} added, {{ $run->rows_updated }} updated{{ $run->rows_skipped ? ', '.$run->rows_skipped.' skipped' : '' }}
                                            @else
                                                {{ $run->rows_total }} rows, {{ $run->rows_invalid }} with problems
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-sm text-slate-500">{{ $run->user?->name ?? '—' }}</td>
                                    <td class="num">
                                        <x-button href="{{ route('data-import.review', $run) }}" variant="ghost" size="sm">Open</x-button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <x-empty title="Nothing has been imported yet"
                                            message="Start with the branches, then work down the list." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="How this works">
                <ol class="space-y-3 text-sm text-slate-600">
                    <li><span class="font-medium text-slate-900">1.</span> Download the template for what you are loading. It carries the exact column names and one example row.</li>
                    <li><span class="font-medium text-slate-900">2.</span> Paste your old system's export into it and save as CSV.</li>
                    <li><span class="font-medium text-slate-900">3.</span> Upload it. Every row is checked and nothing is written yet.</li>
                    <li><span class="font-medium text-slate-900">4.</span> Read what came back. Bad rows are named with the reason, and can be downloaded as a file to fix and send again.</li>
                    <li><span class="font-medium text-slate-900">5.</span> Import. It happens in one go — if anything fails, nothing is saved.</li>
                </ol>
            </x-card>

            <x-card title="Worth knowing">
                <ul class="space-y-3 text-sm text-slate-600">
                    <li>
                        <span class="font-medium text-slate-900">Re-uploading is safe.</span>
                        A row whose code already exists updates that record instead of creating a
                        second one, so a corrected file can simply be sent again.
                    </li>
                    <li>
                        <span class="font-medium text-slate-900">Nobody is emailed.</span>
                        Importing employees creates their logins but sends nothing. Send each
                        person their credentials from their own screen when you are ready for them
                        to sign in.
                    </li>
                    <li>
                        <span class="font-medium text-slate-900">Very large files.</span>
                        Above 20,000 rows, split the file or load it on the server with
                        <span class="font-mono text-xs">php artisan hrms:import</span>.
                    </li>
                </ul>
            </x-card>
        </div>
    </div>
</x-app-layout>
