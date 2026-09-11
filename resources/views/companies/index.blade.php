<x-app-layout title="Companies">
    <x-page-header title="Companies"
        subtitle="The legal entities that employ people and issue salary slips">
        <x-slot:actions>
            @if ($canManage)
                <x-button :href="route('companies.create')">
                    <x-icon.plus class="size-4" /> Add a company
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th class="w-32">Slip prefix</th>
                        <th class="w-28 text-right">Employees</th>
                        <th class="w-28 text-right">Runs</th>
                        <th class="w-28">Status</th>
                        <th class="w-56"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($companies as $company)
                        <tr>
                            <td>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-slate-900">{{ $company->displayName() }}</span>
                                    @if ($company->is_default)
                                        <x-badge color="brand">Default</x-badge>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $company->code }}
                                    @if ($company->tax_id) &middot; Tax ID {{ $company->tax_id }} @endif
                                    @if ($company->city) &middot; {{ $company->city }} @endif
                                </p>
                            </td>
                            <td class="font-mono text-xs text-slate-600">{{ $company->payslip_prefix }}</td>
                            <td class="num tabular-nums">{{ $company->employees_count }}</td>
                            <td class="num tabular-nums">{{ $company->payrolls_count }}</td>
                            <td>
                                <x-badge :color="$company->isActive() ? 'emerald' : 'slate'" dot>
                                    {{ ucfirst($company->status) }}
                                </x-badge>
                            </td>
                            <td class="num">
                                <div class="flex justify-end gap-1">
                                    <x-button variant="ghost" size="sm"
                                        :href="route('companies.signatories.index', $company)">
                                        Signatories
                                        @if ($company->signatories_count)
                                            <span class="text-slate-400">({{ $company->signatories_count }})</span>
                                        @endif
                                    </x-button>
                                    @if ($canManage)
                                        <x-button variant="secondary" size="sm" :href="route('companies.edit', $company)">
                                            <x-icon.pencil class="size-3.5" /> Edit
                                        </x-button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty title="No companies yet"
                                    description="Add the legal entity that employs your staff. Payroll and salary slips are issued in its name." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <p class="mt-4 max-w-3xl text-sm text-slate-500">
        An employee belongs to exactly one company, chosen when they are onboarded. Payroll is run for
        one company at a time, and each salary slip carries that company's name, registration numbers
        and logo. Branches are separate — where somebody sits has nothing to do with who employs them.
    </p>
</x-app-layout>
