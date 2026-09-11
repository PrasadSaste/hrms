<div class="grid gap-6 lg:grid-cols-3">
    <x-card title="Department details" class="lg:col-span-2">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-input label="Department name" name="name" :value="$department->name" required />
            <x-input label="Code" name="code" :value="$department->code" required
                help="Unique within the branch, e.g. ENG." />
            <x-textarea label="Description" name="description" :value="$department->description" class="sm:col-span-2" rows="3" />
        </div>
    </x-card>

    <div class="space-y-6">
        <x-card title="Assignment">
            <div class="space-y-4">
                <x-select label="Branch" name="branch_id" :selected="$department->branch_id" placeholder="Organisation wide"
                    :options="$branches->pluck('name', 'id')->all()" />

                <x-select label="Department head" name="head_id" :selected="$department->head_id" placeholder="Not assigned"
                    :options="$heads->mapWithKeys(fn ($e) => [$e->id => $e->first_name . ' ' . $e->last_name . ' (' . $e->employee_code . ')'])->all()" />

                <x-select label="Status" name="status" :selected="$department->status ?? 'active'" required
                    :options="['active' => 'Active', 'inactive' => 'Inactive']" />
            </div>
        </x-card>

        <div class="flex gap-2">
            <x-button class="flex-1">{{ $department->exists ? 'Save changes' : 'Create department' }}</x-button>
            <x-button href="{{ route('departments.index') }}" variant="secondary">Cancel</x-button>
        </div>
    </div>
</div>
