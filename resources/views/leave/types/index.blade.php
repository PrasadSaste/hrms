<x-app-layout title="Leave types">
    <x-page-header title="Leave types" subtitle="Policies that govern how each kind of leave behaves">
        <x-slot:actions>
            <x-button href="{{ route('leave-types.create') }}"><x-icon.plus class="size-4" /> New leave type</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Leave type</th><th class="num">Days / year</th><th>Paid</th>
                        <th>Half day</th><th>Carry forward</th><th>Notice</th><th>Applies to</th>
                        <th class="num">Requests</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($types as $type)
                        <tr>
                            <td>
                                <div class="flex items-center gap-2">
                                    <span class="size-2.5 rounded-full" style="background-color: {{ $type->color }}"></span>
                                    <div>
                                        <div class="font-medium text-slate-900">{{ $type->name }}</div>
                                        <div class="font-mono text-xs text-slate-500">{{ $type->code }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="num tabular-nums">{{ rtrim(rtrim(number_format($type->days_per_year, 1), '0'), '.') }}</td>
                            <td><x-badge :color="$type->is_paid ? 'emerald' : 'amber'">{{ $type->is_paid ? 'Paid' : 'Unpaid' }}</x-badge></td>
                            <td class="text-slate-600">{{ $type->allow_half_day ? 'Allowed' : 'No' }}</td>
                            <td class="text-slate-600">
                                {{ $type->carry_forward ? 'Up to ' . rtrim(rtrim(number_format($type->max_carry_forward_days, 1), '0'), '.') . 'd' : 'No' }}
                            </td>
                            <td class="text-slate-600">{{ $type->min_notice_days > 0 ? $type->min_notice_days . ' day(s)' : 'None' }}</td>
                            <td class="text-slate-600">
                                {{ $type->applicable_gender === 'any' ? 'Everyone' : ucfirst($type->applicable_gender) }}
                                @if ($type->applicable_after_months > 0)
                                    <div class="text-xs text-slate-500">after {{ $type->applicable_after_months }} month(s)</div>
                                @endif
                            </td>
                            <td class="num tabular-nums">{{ $type->requests_count }}</td>
                            <td><x-badge :color="$type->status === 'active' ? 'emerald' : 'slate'" dot>{{ ucfirst($type->status) }}</x-badge></td>
                            <td class="num whitespace-nowrap">
                                <x-button href="{{ route('leave-types.edit', $type) }}" variant="ghost" size="sm"><x-icon.pencil class="size-3.5" /> Edit</x-button>
                                <form method="POST" action="{{ route('leave-types.destroy', $type) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <x-button variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50"
                                        data-confirm="Remove {{ $type->name }}?"><x-icon.trash class="size-3.5" /> Delete</x-button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><x-empty title="No leave types defined" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $types->links() }}</div>
</x-app-layout>
