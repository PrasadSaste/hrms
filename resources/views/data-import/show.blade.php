<x-app-layout :title="'Import '.strtolower($definition['label'])">
    <x-page-header :title="'Import '.strtolower($definition['label'])" :subtitle="$definition['summary']">
        <x-slot:actions>
            <x-button href="{{ route('data-import.index') }}" variant="secondary">All imports</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="1. Start from the template">
                <p class="text-sm text-slate-600">{{ $definition['description'] }}</p>

                <div class="mt-4">
                    <x-button href="{{ route('data-import.template', $type) }}" variant="secondary">
                        <x-icon.download class="size-4" /> Download the template
                    </x-button>
                </div>

                <p class="mt-3 text-xs text-slate-500">
                    It opens in Excel or Google Sheets. Keep the header row exactly as it is, replace
                    the example row with your data, then save as CSV.
                </p>
            </x-card>

            <x-card title="2. Upload it">
                <form method="POST" action="{{ route('data-import.store', $type) }}" enctype="multipart/form-data"
                    class="space-y-4">
                    @csrf

                    <x-input type="file" name="file" label="Your CSV file" accept=".csv,.txt,.tsv" required
                        help="Up to 10 MB and 20,000 rows. Nothing is saved yet — the next screen shows what the file holds." />

                    <x-button>
                        <x-icon.upload class="size-4" /> Check the file
                    </x-button>
                </form>
            </x-card>

            <x-card title="The columns" :padded="false">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Column</th><th>Needed</th><th>What goes in it</th><th>Example</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($definition['columns'] as $name => $column)
                                <tr>
                                    <td class="font-mono text-xs whitespace-nowrap">{{ $name }}</td>
                                    <td>
                                        @if ($column[0])
                                            <x-badge color="rose">Required</x-badge>
                                        @else
                                            <span class="text-xs text-slate-400">Optional</span>
                                        @endif
                                    </td>
                                    <td class="text-sm text-slate-600">
                                        {{ $column[1] ?: '—' }}
                                        @if (! empty($aliases[$name]))
                                            <div class="mt-0.5 text-xs text-slate-400">
                                                Also read from: {{ implode(', ', $aliases[$name]) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="font-mono text-xs text-slate-500">{{ $column[2] ?: '' }}</td>
                                </tr>
                            @endforeach

                            @foreach ($componentColumns as $column)
                                <tr>
                                    <td class="font-mono text-xs whitespace-nowrap">{{ $column }}</td>
                                    <td><span class="text-xs text-slate-400">Optional</span></td>
                                    <td class="text-sm text-slate-600">
                                        The monthly amount for this salary component. Leave empty if it is
                                        not part of that person's pay.
                                    </td>
                                    <td class="font-mono text-xs text-slate-500"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Reading your columns">
                <ul class="space-y-3 text-sm text-slate-600">
                    <li>Column names are matched loosely: <span class="font-mono text-xs">Employee Code</span>,
                        <span class="font-mono text-xs">employee_code</span> and
                        <span class="font-mono text-xs">EMPLOYEE-CODE</span> are the same column.</li>
                    <li>Columns you do not need can be deleted from the file. Extra columns are ignored.</li>
                    <li>Dates are read as <span class="font-mono text-xs">YYYY-MM-DD</span> or
                        <span class="font-mono text-xs">DD/MM/YYYY</span>. Day comes before month.</li>
                    <li>Amounts may carry commas — <span class="font-mono text-xs">1,20,000</span> is fine.</li>
                    <li>Blank lines are skipped, and so are rows where every cell is empty.</li>
                </ul>
            </x-card>

            @if ($history->isNotEmpty())
                <x-card title="Earlier files of this kind">
                    <ul class="space-y-3">
                        @foreach ($history as $run)
                            <li class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('data-import.review', $run) }}"
                                        class="block truncate text-sm font-medium text-slate-900 hover-ink">
                                        {{ $run->original_filename }}
                                    </a>
                                    <div class="text-xs text-slate-500">{{ $run->created_at->diffForHumans() }}</div>
                                </div>
                                <x-badge :color="$run->statusColor()">{{ $run->statusLabel() }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
