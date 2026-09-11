<x-app-layout title="Letters">
    <x-page-header title="Letters" subtitle="Every letter issued, and the register of them">
        <x-slot:actions>
            @can('create', App\Models\Letter::class)
                <x-button href="{{ route('letters.create') }}">
                    <x-icon.plus class="size-4" /> Issue a letter
                </x-button>
            @endcan
            @can('letters.manage-templates')
                <x-button href="{{ route('letter-templates.index') }}" variant="secondary">
                    Wording
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('letters.index')">
        <x-input label="Search" name="search" :value="request('search')" placeholder="Reference, name or code" class="sm:w-64" />
        <x-select label="Kind" name="type" :selected="request('type')" placeholder="Every kind"
            :options="$types" class="sm:w-56" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Filter</x-button>
        <x-button href="{{ route('letters.index') }}" variant="ghost">Clear</x-button>
    </x-filter-bar>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Reference</th><th>Employee</th><th>Letter</th>
                        <th>Issued</th><th>By</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($letters as $letter)
                        <tr>
                            <td class="font-mono text-xs whitespace-nowrap">
                                <a href="{{ route('letters.show', $letter) }}" class="link font-medium">
                                    {{ $letter->reference }}
                                </a>
                            </td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-avatar :name="$letter->employee->full_name" :src="$letter->employee->photoUrl()" size="sm" />
                                    <div class="min-w-0">
                                        <div class="font-medium text-slate-900">{{ $letter->employee->full_name }}</div>
                                        <div class="font-mono text-xs text-slate-500">{{ $letter->employee->employee_code }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>{{ $letter->typeLabel() }}</div>
                                <div class="max-w-64 truncate text-xs text-slate-500">{{ $letter->subject }}</div>
                            </td>
                            <td class="whitespace-nowrap">
                                {{ $letter->issued_on->format('d M Y') }}
                                @if ($letter->wasEmailed())
                                    <span class="block text-xs text-emerald-600">Emailed</span>
                                @endif
                            </td>
                            <td class="text-sm text-slate-500">{{ $letter->issuer?->name ?? '—' }}</td>
                            <td class="num whitespace-nowrap">
                                <x-button href="{{ route('letters.download', $letter) }}" variant="ghost" size="sm">
                                    <x-icon.download class="size-4" /> PDF
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty title="No letters issued yet"
                                    message="Issue one from here or from an employee's own screen." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($letters->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $letters->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
