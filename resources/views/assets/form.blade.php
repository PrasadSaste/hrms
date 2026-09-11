<x-app-layout :title="$asset->exists ? 'Edit asset' : 'Add asset'">
    <x-page-header :title="$asset->exists ? $asset->name : 'Add an asset'"
        :subtitle="$asset->exists ? 'Tag '.$asset->asset_tag : 'Record something the company owns, so it can be issued and asked back for'">
        <x-slot:actions>
            <x-button :href="route('assets.index')" variant="secondary">Back to the register</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $asset->exists ? route('assets.update', $asset) : route('assets.store') }}">
        @csrf
        @if ($asset->exists) @method('PUT') @endif

        <div class="grid gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                <x-card class="p-5">
                    <h3 class="mb-4 text-sm font-semibold tracking-wide text-slate-500 uppercase">What it is</h3>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="Asset tag" name="asset_tag" :value="old('asset_tag', $asset->asset_tag)" required
                            help="The sticker on the side. Unique across the company." />
                        <x-select label="Kind" name="type" required
                            :selected="old('type', $asset->type)"
                            :options="collect($types)->map(fn ($t) => $t['label'])->all()"
                            help="Decides what else is asked for below." />
                    </div>

                    <div class="mt-4">
                        <x-input label="Name" name="name" :value="old('name', $asset->name)" required
                            placeholder="MacBook Pro 14&quot; — Meera" />
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <x-input label="Make" name="make" :value="old('make', $asset->make)" />
                        <x-input label="Model" name="model" :value="old('model', $asset->model)" />
                        <x-input label="Serial number" name="serial_number" :value="old('serial_number', $asset->serial_number)" />
                    </div>

                    <div class="mt-4">
                        <x-textarea label="Description" name="description" rows="2">{{ old('description', $asset->description) }}</x-textarea>
                    </div>
                </x-card>

                {{-- One block per kind; JavaScript shows the one that applies. --}}
                @foreach ($types as $key => $type)
                    {{-- The hidden state lives on a plain wrapper: Blade cannot
                         parse an @if inside a component tag's attributes. --}}
                    <div class="js-type-fields" data-type="{{ $key }}" @if ($key !== old('type', $asset->type)) hidden @endif>
                    <x-card class="p-5">
                        <h3 class="mb-1 text-sm font-semibold tracking-wide text-slate-500 uppercase">
                            {{ $type['label'] }} details
                        </h3>
                        <p class="mb-4 text-xs text-slate-500">{{ $type['description'] }}</p>

                        @if ($type['fields'])
                            <div class="grid gap-4 sm:grid-cols-2">
                                @foreach ($type['fields'] as $field => $definition)
                                    <x-input
                                        :label="$definition['label']"
                                        :name="'details['.$field.']'"
                                        :type="$definition['type'] === 'date' ? 'date' : 'text'"
                                        :value="old('details.'.$field, $asset->detail($field))"
                                        :required="$definition['required'] && $key === old('type', $asset->type)" />
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-slate-500">Nothing extra is asked for this kind.</p>
                        @endif
                    </x-card>
                    </div>
                @endforeach
            </div>

            <div class="space-y-5">
                <x-card class="p-5">
                    <h3 class="mb-4 text-sm font-semibold tracking-wide text-slate-500 uppercase">Where it belongs</h3>

                    <div class="space-y-4">
                        <x-select label="Company" name="company_id" :selected="old('company_id', $asset->company_id)"
                            placeholder="Not set" :options="$companies->pluck('name', 'id')->all()"
                            help="The entity that bought it." />
                        <x-select label="Branch" name="branch_id" :selected="old('branch_id', $asset->branch_id)"
                            placeholder="Not set" :options="$branches->pluck('name', 'id')->all()"
                            help="A branch manager sees their own branch's assets." />
                        <x-select label="Condition" name="condition" required
                            :selected="old('condition', $asset->condition ?: 'good')" :options="$conditions" />
                    </div>
                </x-card>

                <x-card class="p-5">
                    <h3 class="mb-4 text-sm font-semibold tracking-wide text-slate-500 uppercase">Purchase</h3>

                    <div class="space-y-4">
                        <x-input label="Bought on" name="purchased_on" type="date"
                            :value="old('purchased_on', $asset->purchased_on?->toDateString())" />
                        <x-input label="What it cost" name="purchase_cost" type="number" step="0.01" min="0"
                            :value="old('purchase_cost', $asset->purchase_cost)" />
                        <x-input label="Warranty expires" name="warranty_expires_on" type="date"
                            :value="old('warranty_expires_on', $asset->warranty_expires_on?->toDateString())" />
                        <x-textarea label="Notes" name="notes" rows="3">{{ old('notes', $asset->notes) }}</x-textarea>
                    </div>
                </x-card>

            </div>
        </div>

        <x-form-actions>
            <x-button size="lg">{{ $asset->exists ? 'Save changes' : 'Add to the register' }}</x-button>
            <x-button :href="$asset->exists ? route('assets.show', $asset) : route('assets.index')"
                variant="secondary" size="lg">Cancel</x-button>
        </x-form-actions>
    </form>

    <script>
        // Only the chosen kind's own fields are shown, and only those are
        // required — a hidden required input stops a form submitting with no
        // visible reason why.
        (function () {
            // x-select sets its own id from the field name.
            var picker = document.getElementById('type');
            if (! picker) return;

            function show(type) {
                document.querySelectorAll('.js-type-fields').forEach(function (block) {
                    var mine = block.dataset.type === type;
                    block.hidden = ! mine;
                    block.querySelectorAll('input').forEach(function (input) {
                        if (input.dataset.optional === undefined) {
                            input.disabled = ! mine;
                        }
                    });
                });
            }

            picker.addEventListener('change', function () { show(this.value); });
            show(picker.value);
        })();
    </script>
</x-app-layout>
