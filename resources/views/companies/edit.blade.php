@php $isNew = ! $company->exists; @endphp

<x-app-layout :title="$isNew ? 'Add a company' : $company->displayName()">
    <x-page-header :title="$isNew ? 'Add a company' : $company->displayName()"
        subtitle="What appears on this entity's salary slips"
        :back="route('companies.index')" />

    <form method="POST"
        action="{{ $isNew ? route('companies.store') : route('companies.update', $company) }}"
        enctype="multipart/form-data">
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-card title="Identity">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="Short name" name="name" :value="$company->name" required
                            help="Used in lists and filters, e.g. Beyond Sure." />
                        <x-input label="Registered name" name="legal_name" :value="$company->legal_name"
                            help="Printed on the salary slip, e.g. Beyondsure Private Limited." />
                        <x-input label="Code" name="code" :value="$company->code" required
                            help="Letters and numbers, e.g. BSPL." />
                        <x-input label="Salary slip prefix" name="payslip_prefix"
                            :value="$company->payslip_prefix ?? 'PS'" required
                            help="Slip numbers begin with this, so a slip says which entity issued it." />
                    </div>
                </x-card>

                <x-card title="Registration and statutory numbers"
                    subtitle="These print on every salary slip this company issues">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="Registration number" name="registration_number"
                            :value="$company->registration_number" help="Company identification number." />
                        <x-input label="Tax identification number" name="tax_id" :value="$company->tax_id" />
                        <x-input label="GST number" name="gst_number" :value="$company->gst_number" />
                        <x-input label="Provident fund number" name="pf_number" :value="$company->pf_number" />
                        <x-input label="ESI number" name="esi_number" :value="$company->esi_number" />
                        <x-input label="Currency" name="currency" :value="$company->currency ?? 'INR'" required
                            help="Three-letter code. Salary slips for this company are shown in it." />
                    </div>
                </x-card>

                <x-card title="Registered address and contact">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="Address line 1" name="address_line1" :value="$company->address_line1"
                            class="sm:col-span-2" />
                        <x-input label="Address line 2" name="address_line2" :value="$company->address_line2"
                            class="sm:col-span-2" />
                        <x-input label="City" name="city" :value="$company->city" />
                        <x-input label="State" name="state" :value="$company->state" />
                        <x-input label="Postal code" name="postal_code" :value="$company->postal_code" />
                        <x-input label="Country" name="country" :value="$company->country" />
                        <x-input label="Email" name="email" type="email" :value="$company->email" />
                        <x-input label="Phone" name="phone" :value="$company->phone" />
                        <x-input label="Website" name="website" type="url" :value="$company->website"
                            class="sm:col-span-2" />
                    </div>
                </x-card>

                <x-card title="The account salaries are paid from"
                    subtitle="Named in the bank payment file as the account the money leaves. Two legal entities do not share one.">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="Bank" name="bank_name" :value="$company->bank_name" />
                        <x-input label="Account name" name="bank_account_name" :value="$company->bank_account_name"
                            help="As the bank holds it, which is not always the trading name." />
                        <x-input label="Account number" name="bank_account_number" :value="$company->bank_account_number" />
                        <x-input label="IFSC" name="bank_ifsc" :value="$company->bank_ifsc"
                            help="Also decides which payments never leave the bank and settle at once." />
                        <x-select label="Bank file layout" name="bank_file_format"
                            :selected="$company->bank_file_format ?? App\Support\BankFormats::default()"
                            :options="App\Support\BankFormats::options()"
                            class="sm:col-span-2"
                            help="Which shape of file this company's bank accepts. What each one writes is listed on the bank file screen, to compare against the template your bank sent you." />
                    </div>
                </x-card>

                <x-card title="Who signs for this company"
                    subtitle="Their name prints at the foot of its letters and salary slips">
                    @if ($isNew)
                        <p class="text-sm text-slate-500">
                            Save the company first, then add the directors or managers who may sign for it.
                            Until somebody is added, a salary slip says it is computer generated and needs
                            no signature.
                        </p>
                    @else
                        @php $signatories = $company->signatories()->active()->get(); @endphp

                        @if ($signatories->isEmpty())
                            <p class="text-sm text-slate-500">
                                Nobody yet. Until somebody is added, a salary slip says it is computer
                                generated and needs no signature.
                            </p>
                        @else
                            <ul class="space-y-1 text-sm text-slate-600">
                                @foreach ($signatories as $signatory)
                                    <li class="flex items-center gap-2">
                                        <span class="text-slate-900">{{ $signatory->label() }}</span>
                                        @if ($signatory->is_default)
                                            <x-badge color="brand">Default</x-badge>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <x-button variant="secondary" size="sm" class="mt-4"
                            :href="route('companies.signatories.index', $company)">
                            Manage signatories
                        </x-button>
                    @endif
                </x-card>
            </div>

            <div class="space-y-6 self-start lg:sticky lg:top-20">
                <x-card title="Logo" subtitle="Shown in lists and on the interface">
                    <div class="flex items-start gap-4">
                        <span class="flex size-20 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white p-2">
                            @if ($company->logo_path)
                                <img src="{{ Storage::disk('public')->url($company->logo_path) }}"
                                    alt="{{ $company->name }}" class="max-h-full max-w-full object-contain">
                            @else
                                <span class="text-xs text-slate-400">None</span>
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                            <p class="form-help">
                                PNG, JPEG, WebP or SVG up to 1 MB. Also used on this company's documents
                                unless a separate letterhead logo is set below.
                            </p>
                            @error('logo')<p class="form-error">{{ $message }}</p>@enderror

                            @if ($company->logo_path)
                                <label class="mt-3 flex items-center gap-2.5">
                                    <input type="hidden" name="remove_logo" value="0">
                                    <input type="checkbox" name="remove_logo" value="1" class="form-checkbox">
                                    <span class="text-sm text-slate-700">Remove this logo</span>
                                </label>
                            @endif
                        </div>
                    </div>
                </x-card>

                <x-card title="Document pad"
                    subtitle="What every salary slip and letter this company issues is printed on">
                    <div class="space-y-5">
                        <div>
                            <label class="form-label" for="letterhead_logo">Letterhead logo</label>
                            <div class="flex items-start gap-4">
                                <span class="flex size-20 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white p-2">
                                    @if ($company->letterhead_logo_path)
                                        <img src="{{ Storage::disk('public')->url($company->letterhead_logo_path) }}"
                                            alt="{{ $company->name }}" class="max-h-full max-w-full object-contain">
                                    @else
                                        <span class="text-xs text-slate-400">None</span>
                                    @endif
                                </span>
                                <div class="min-w-0 flex-1">
                                    <input type="file" name="letterhead_logo" id="letterhead_logo"
                                        accept="image/png,image/jpeg,image/webp"
                                        class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                                    <p class="form-help">
                                        PNG, JPEG or WebP up to 1 MB. No SVG — the PDF renderer cannot
                                        rasterise it, so an SVG would simply not print. Leave empty to use
                                        the logo above. A document never falls back to another entity's logo.
                                    </p>
                                    @error('letterhead_logo')<p class="form-error">{{ $message }}</p>@enderror

                                    @if ($company->letterhead_logo_path)
                                        <label class="mt-3 flex items-center gap-2.5">
                                            <input type="hidden" name="remove_letterhead_logo" value="0">
                                            <input type="checkbox" name="remove_letterhead_logo" value="1" class="form-checkbox">
                                            <span class="text-sm text-slate-700">Remove this logo</span>
                                        </label>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <x-textarea label="Footer line" name="letterhead_footer" rows="2"
                            :value="$company->letterhead_footer"
                            help="Printed at the foot of every document this company issues — a registered office, a CIN, a licence number." />

                        <div class="border-t border-slate-100 pt-4">
                            <x-checkbox name="watermark_enabled" label="Watermark its documents"
                                :checked="old('watermark_enabled', $company->watermark_enabled ?? true)"
                                help="A pale diagonal mark across every page, so a photocopy is visibly a copy of something." />

                            <div class="mt-3">
                                <x-input label="Watermark wording" name="watermark_text"
                                    :value="$company->watermark_text"
                                    maxlength="60"
                                    :placeholder="$company->name ?: 'The company name'"
                                    help="Left empty, the company's short name is used." />
                            </div>
                        </div>
                    </div>
                </x-card>

                <x-card title="Status">
                    <div class="space-y-4">
                        <x-select label="Status" name="status" :selected="$company->status ?? 'active'" required
                            :options="['active' => 'Active', 'inactive' => 'Inactive']"
                            help="An inactive company cannot be chosen for new employees or new runs." />

                        <x-checkbox name="is_default" label="Use as the default company"
                            :checked="$company->is_default"
                            help="Chosen automatically when nobody picks one. Only one company can be the default." />
                    </div>
                </x-card>

                @unless ($isNew)
                    <x-card title="Removing this company">
                        <p class="text-sm text-slate-600">
                            A company that employs people or has payroll history cannot be deleted, because its
                            salary slips must keep pointing at whoever issued them. Mark it inactive instead.
                        </p>
                    </x-card>
                @endunless
            </div>
        </div>

        <x-form-actions>
            <x-button size="lg">{{ $isNew ? 'Add company' : 'Save changes' }}</x-button>
            <x-button :href="route('companies.index')" variant="secondary" size="lg">Cancel</x-button>
        </x-form-actions>
    </form>

    @unless ($isNew)
        <form method="POST" action="{{ route('companies.destroy', $company) }}" class="mt-6">
            @csrf @method('DELETE')
            <x-button variant="ghost" size="sm" data-confirm="Delete this company?">
                <x-icon.trash class="size-3.5" /> Delete this company
            </x-button>
        </form>
    @endunless
</x-app-layout>
