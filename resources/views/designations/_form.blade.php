<div class="grid gap-6 lg:grid-cols-3">
    <x-card title="Designation details" class="lg:col-span-2">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-input label="Title" name="name" :value="$designation->name" required />
            <x-input label="Code" name="code" :value="$designation->code" required />
            <x-input label="Seniority level" name="level" type="number" min="1" max="20"
                :value="$designation->level ?? 1" required
                help="1 is the most junior, 20 the most senior. Used to order org charts." />
            <x-select label="Department" name="department_id" :selected="$designation->department_id"
                placeholder="Cross functional" :options="$departments->pluck('name', 'id')->all()" />
            <x-textarea label="Description" name="description" :value="$designation->description" class="sm:col-span-2" rows="3" />
        </div>
    </x-card>

    <div class="space-y-6">
        <x-card title="Status">
            <x-select label="Status" name="status" :selected="$designation->status ?? 'active'" required
                :options="['active' => 'Active', 'inactive' => 'Inactive']" />
        </x-card>

        <div class="flex gap-2">
            <x-button class="flex-1">{{ $designation->exists ? 'Save changes' : 'Create designation' }}</x-button>
            <x-button href="{{ route('designations.index') }}" variant="secondary">Cancel</x-button>
        </div>
    </div>
</div>
