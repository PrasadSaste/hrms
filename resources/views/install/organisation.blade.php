<x-install.layout :step="$step" title="Your company">
    <h2>Whose system is this?</h2>
    <p class="sub">
        This names the first payroll entity and its head office, and puts your logo and colours on
        the screens, the salary slips and the letters. All of it is editable afterwards under Administration.
    </p>

    <form method="POST" action="{{ route('install.organisation.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="card">
            <h3>The company</h3>

            <div class="row two">
                <div class="field">
                    <label for="company_name">Name</label>
                    <input type="text" name="company_name" id="company_name" required autofocus
                        value="{{ old('company_name', $current['company_name']) }}" placeholder="Beyond Sure">
                    <p class="hint">What people call it. It appears in the sidebar and on every document.</p>
                </div>
                <div class="field">
                    <label for="legal_name">Registered name <span class="opt">— optional</span></label>
                    <input type="text" name="legal_name" id="legal_name"
                        value="{{ old('legal_name', $current['legal_name']) }}" placeholder="Beyondsure Private Limited">
                    <p class="hint">The name on the letterhead and the salary slip.</p>
                </div>
            </div>

            <div class="row three">
                <div class="field">
                    <label for="company_email">Email <span class="opt">— optional</span></label>
                    <input type="email" name="company_email" id="company_email" value="{{ old('company_email', $current['company_email']) }}">
                </div>
                <div class="field">
                    <label for="company_phone">Phone <span class="opt">— optional</span></label>
                    <input type="text" name="company_phone" id="company_phone" value="{{ old('company_phone', $current['company_phone']) }}">
                </div>
                <div class="field">
                    <label for="company_tax_id">Tax number <span class="opt">— optional</span></label>
                    <input type="text" name="company_tax_id" id="company_tax_id" value="{{ old('company_tax_id', $current['company_tax_id']) }}" placeholder="PAN or TAN">
                </div>
            </div>

            <div class="field">
                <label for="company_website">Website <span class="opt">— optional</span></label>
                <input type="url" name="company_website" id="company_website" value="{{ old('company_website', $current['company_website']) }}" placeholder="https://example.com">
            </div>
        </div>

        <div class="card">
            <h3>Head office</h3>
            <p class="hint" style="margin: -8px 0 14px;">Becomes the first branch. Add the rest, and their map positions, once you are in.</p>

            <div class="field">
                <label for="address_line1">Address <span class="opt">— optional</span></label>
                <input type="text" name="address_line1" id="address_line1" value="{{ old('address_line1', $current['address_line1']) }}">
            </div>

            <div class="row three">
                <div class="field">
                    <label for="city">City</label>
                    <input type="text" name="city" id="city" value="{{ old('city', $current['city']) }}">
                </div>
                <div class="field">
                    <label for="state">State</label>
                    <input type="text" name="state" id="state" value="{{ old('state', $current['state']) }}">
                </div>
                <div class="field">
                    <label for="postal_code">Postcode</label>
                    <input type="text" name="postal_code" id="postal_code" value="{{ old('postal_code', $current['postal_code']) }}">
                </div>
            </div>

            <div class="row three">
                <div class="field">
                    <label for="country">Country</label>
                    <input type="text" name="country" id="country" value="{{ old('country', $current['country']) }}">
                </div>
                <div class="field">
                    <label for="currency">Currency</label>
                    <input type="text" name="currency" id="currency" maxlength="3" required
                        value="{{ old('currency', $current['currency']) }}" style="text-transform: uppercase;">
                    <p class="hint">INR prints amounts in lakh and crore.</p>
                </div>
                <div class="field">
                    <label for="timezone">Time zone</label>
                    <select name="timezone" id="timezone">
                        @foreach ($timezones as $zone)
                            <option value="{{ $zone }}" @selected(old('timezone', $current['timezone']) === $zone)>{{ $zone }}</option>
                        @endforeach
                    </select>
                    <p class="hint">Decides when a punch counts as late.</p>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Look</h3>

            <div class="field">
                <label for="company_logo">Logo <span class="opt">— optional</span></label>
                <input type="file" name="company_logo" id="company_logo" accept=".png,.jpg,.jpeg,.webp,.svg">
                <p class="hint">PNG, JPG, WEBP or SVG, up to 1 MB. Without one, the company's initials are drawn instead.</p>
            </div>

            <div class="row three">
                @foreach ($colours as $role => $definition)
                    <div class="field">
                        <label for="{{ $definition['setting'] }}">{{ ucfirst($role) }} colour</label>
                        <input type="color" name="{{ $definition['setting'] }}" id="{{ $definition['setting'] }}"
                            value="{{ old($definition['setting'], $current[$definition['setting']]) }}">
                    </div>
                @endforeach
            </div>
            <p class="hint">Ten shades are derived from each. Change them any time under Administration → Settings.</p>
        </div>

        <div class="actions">
            <button type="submit" class="primary">Save and continue</button>
        </div>
    </form>
</x-install.layout>
