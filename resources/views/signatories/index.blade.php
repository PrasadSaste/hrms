<x-app-layout :title="'Signatories · ' . $company->displayName()">
    <x-page-header title="Signatories"
        :subtitle="'Who may sign for ' . $company->displayName()"
        :back="route('companies.index')">
        <x-slot:actions>
            @if ($canManage)
                <x-button type="button" data-dialog-open="add-signatory">
                    <x-icon.plus class="size-4" /> Add a signatory
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th class="w-56">Designation</th>
                        <th class="w-40">Signature</th>
                        <th class="w-28 text-right">Letters</th>
                        <th class="w-28">Status</th>
                        <th class="w-72"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($signatories as $signatory)
                        <tr>
                            <td>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-slate-900">{{ $signatory->name }}</span>
                                    @if ($signatory->is_default)
                                        <x-badge color="brand">Default</x-badge>
                                    @endif
                                </div>
                                @if ($signatory->email)
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $signatory->email }}</p>
                                @endif
                            </td>
                            <td class="text-slate-600">{{ $signatory->designation ?: '—' }}</td>
                            <td>
                                @if ($signatory->hasSignature())
                                    <img src="{{ route('companies.signatories.signature', [$company, $signatory]) }}"
                                        alt="Signature of {{ $signatory->name }}"
                                        class="h-10 max-w-36 object-contain">
                                @else
                                    <span class="text-xs text-slate-400">Name only</span>
                                @endif
                            </td>
                            <td class="num tabular-nums text-slate-600">{{ $signatory->letters_count }}</td>
                            <td>
                                <x-badge :color="$signatory->isActive() ? 'emerald' : 'slate'" dot>
                                    {{ $signatory->isActive() ? 'Active' : 'Retired' }}
                                </x-badge>
                            </td>
                            <td class="num">
                                @if ($canManage)
                                    <div class="flex justify-end gap-1">
                                        @if (! $signatory->is_default && $signatory->isActive())
                                            <form method="POST"
                                                action="{{ route('companies.signatories.default', [$company, $signatory]) }}">
                                                @csrf
                                                <x-button variant="ghost" size="sm" class="whitespace-nowrap">Make default</x-button>
                                            </form>
                                        @endif
                                        <x-button type="button" variant="secondary" size="sm"
                                            data-dialog-open="edit-signatory"
                                            data-action="{{ route('companies.signatories.update', [$company, $signatory]) }}"
                                            data-fill-name="{{ $signatory->name }}"
                                            data-fill-designation="{{ $signatory->designation }}"
                                            data-fill-email="{{ $signatory->email }}"
                                            data-fill-sort_order="{{ $signatory->sort_order }}"
                                            data-fill-status="{{ $signatory->status }}">
                                            <x-icon.pencil class="size-3.5" /> Edit
                                        </x-button>
                                        <form method="POST"
                                            action="{{ route('companies.signatories.destroy', [$company, $signatory]) }}">
                                            @csrf @method('DELETE')
                                            <x-button variant="ghost" size="sm" class="whitespace-nowrap text-rose-600 hover:bg-rose-50"
                                                data-confirm="Remove {{ $signatory->name }} from the signatories of {{ $company->displayName() }}?"><x-icon.trash class="size-3.5" /> Remove</x-button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty title="Nobody signs for this company yet"
                                    description="Add the director or manager whose name should appear at the foot of its letters and salary slips.">
                                    <x-slot:action>
                                        @if ($canManage)
                                            <x-button type="button" data-dialog-open="add-signatory">Add a signatory</x-button>
                                        @endif
                                    </x-slot:action>
                                </x-empty>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <p class="mt-4 max-w-3xl text-sm text-slate-500">
        The default signatory signs {{ $company->displayName() }}'s salary slips and is the name the letter
        screen offers first; whoever issues a letter may pick somebody else from this list. What a letter
        went out over is frozen onto it, so retiring somebody here never changes a letter already issued.
        A specimen signature is stored privately and only ever printed onto a document.
    </p>

    @if ($canManage)
        <x-dialog name="add-signatory" title="Add a signatory" size="sm"
            description="Their name and title as they should print.">
            <form id="add-signatory-form" method="POST"
                action="{{ route('companies.signatories.store', $company) }}"
                enctype="multipart/form-data">
                @csrf
                <div class="space-y-4 px-5 py-5">
                    <x-input label="Name" name="name" required placeholder="Anita Rao" />
                    <x-input label="Designation" name="designation" placeholder="Director"
                        help="Printed under the name. Blank prints “Authorised Signatory”." />
                    <x-input label="Email" name="email" type="email"
                        help="For your own reference only. Nothing is sent here." />
                    <x-input label="Specimen signature" name="signature" type="file"
                        accept="image/png,image/jpeg,image/webp"
                        help="Optional. PNG on a white or transparent background reads best. Up to 512 KB." />
                    <x-input label="Order" name="sort_order" type="number" value="0" min="0"
                        help="Lower numbers come first in the list." />
                    <x-select label="Status" name="status" required selected="active"
                        :options="['active' => 'Active', 'inactive' => 'Retired']" />
                    <x-checkbox label="Signs for this company by default" name="is_default" />
                </div>
            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="secondary" data-dialog-close="add-signatory">Cancel</x-button>
                    <x-button form="add-signatory-form">Add signatory</x-button>
                </div>
            </x-slot:footer>
        </x-dialog>

        <x-dialog name="edit-signatory" title="Edit signatory" size="sm">
            <form id="edit-signatory-form" method="POST" action="" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="space-y-4 px-5 py-5">
                    <x-input label="Name" name="name" required />
                    <x-input label="Designation" name="designation" />
                    <x-input label="Email" name="email" type="email" />
                    <x-input label="Replace the specimen signature" name="signature" type="file"
                        accept="image/png,image/jpeg,image/webp"
                        help="Leave empty to keep the one already stored. Letters already issued keep the signature they went out with." />
                    <x-checkbox label="Remove the stored signature" name="remove_signature" />
                    <x-input label="Order" name="sort_order" type="number" min="0" />
                    <x-select label="Status" name="status" required
                        :options="['active' => 'Active', 'inactive' => 'Retired']"
                        help="Retiring somebody keeps every letter they signed and stops them being offered again." />
                </div>
            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="secondary" data-dialog-close="edit-signatory">Cancel</x-button>
                    <x-button form="edit-signatory-form">Save changes</x-button>
                </div>
            </x-slot:footer>
        </x-dialog>
    @endif
</x-app-layout>
