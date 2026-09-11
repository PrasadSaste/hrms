<x-app-layout title="My letters">
    <x-page-header title="My letters"
        subtitle="Everything the company has issued to you, to download whenever you need it" />

    <x-card :padded="false">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Letter</th><th>Reference</th><th>Issued</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($letters as $letter)
                        <tr>
                            <td>
                                <div class="font-medium text-slate-900">{{ $letter->typeLabel() }}</div>
                                <div class="max-w-80 truncate text-xs text-slate-500">{{ $letter->subject }}</div>
                            </td>
                            <td class="font-mono text-xs text-slate-500">{{ $letter->reference }}</td>
                            <td class="whitespace-nowrap">{{ $letter->issued_on->format('d M Y') }}</td>
                            <td class="num">
                                <x-button href="{{ route('my-letters.download', $letter) }}" variant="secondary" size="sm">
                                    <x-icon.download class="size-4" /> Download
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-empty title="No letters yet"
                                    message="Letters issued to you — an appointment letter, an increment, an experience certificate — appear here for you to download." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>
