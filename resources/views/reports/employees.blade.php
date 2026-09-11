<x-app-layout title="Workforce report">
    <x-page-header title="Workforce report" subtitle="Headcount, composition and attrition" :back="route('reports.index')" />

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-stat label="Headcount" :value="$total" color="brand">
            <x-slot:icon><x-icon.users class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Joined this year" :value="$newJoiners" :sublabel="$yearLabel" color="emerald">
            <x-slot:icon><x-icon.plus class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Exits this year" :value="$exits" :sublabel="$yearLabel" color="rose">
            <x-slot:icon><x-icon.logout class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Attrition" :value="$attritionRate . '%'" color="amber">
            <x-slot:icon><x-icon.chart class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="On probation" :value="$onProbation" color="sky">
            <x-slot:icon><x-icon.clock class="size-5" /></x-slot:icon>
        </x-stat>
    </div>

    <x-filter-bar :action="route('reports.employees')">
        <x-select label="Branch" name="branch_id" :selected="request('branch_id')" placeholder="All branches"
            :options="$branches->pluck('name', 'id')->all()" class="sm:w-52" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Run report</x-button>
    </x-filter-bar>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            'By department' => $byDepartment,
            'By branch' => $byBranch,
            'By employment type' => $byEmploymentType,
            'By employment status' => $byStatus,
            'By gender' => $byGender,
            'By tenure' => collect($tenureBands),
        ] as $heading => $data)
            <x-card :title="$heading">
                @php $max = max(1, collect($data)->max() ?? 1); @endphp
                @forelse ($data as $label => $count)
                    <div class="border-b border-slate-100 py-2.5 first:pt-0 last:border-0 last:pb-0">
                        <div class="mb-1 flex justify-between text-sm">
                            <span class="truncate text-slate-700">{{ $label }}</span>
                            <span class="ml-2 font-medium text-slate-900">{{ $count }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-brand-500" style="width: {{ round($count / $max * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-center text-sm text-slate-500">No data.</p>
                @endforelse
            </x-card>
        @endforeach
    </div>
</x-app-layout>
