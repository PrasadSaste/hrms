{{--
    The letterhead pad. Every document the system generates starts with this,
    so a salary slip and an experience letter from the same company look like
    they came from the same company.

    Expects:
      $company  the array from Letterhead::forCompany()
      $title    what this document is, e.g. "Salary Slip" (optional)
      $meta     lines printed under the title, label => value (optional)
      $stamp    a short word boxed beside the title, e.g. "Paid" (optional)
--}}
@php
    $title ??= null;
    $meta ??= [];
    $stamp ??= null;
@endphp

<table class="pad">
    <tr>
        <td class="pad-identity">
            @if (! empty($company['logo_src']))
                <img src="{{ $company['logo_src'] }}" alt="{{ $company['name'] }}" class="pad-logo">
            @endif
            {{-- The name is printed whether or not there is a logo: a document
                 has to name the entity that issued it, and a wordmark cannot be
                 relied on to be legible in black and white. --}}
            <div class="pad-name">{{ $company['name'] }}</div>
            <div class="pad-meta">
                @foreach ($company['address_lines'] ?? [] as $line)
                    {{ $line }}<br>
                @endforeach
                @if ($company['phone'])Phone: {{ $company['phone'] }}<br>@endif
                @if ($company['email']){{ $company['email'] }}<br>@endif
                @if (! empty($company['registration_number']))Reg. no: {{ $company['registration_number'] }}<br>@endif
                @if ($company['tax_id'])Tax ID: {{ $company['tax_id'] }}<br>@endif
                @if (! empty($company['pf_number']))PF: {{ $company['pf_number'] }}<br>@endif
                @if (! empty($company['esi_number']))ESI: {{ $company['esi_number'] }}@endif
            </div>
        </td>

        @if ($title || $meta || $stamp)
            <td class="pad-document">
                @if ($title)
                    <div class="pad-title">{{ $title }}</div>
                @endif
                @foreach ($meta as $label => $value)
                    @continue(blank($value))
                    <div class="pad-line"><span class="pad-line-label">{{ $label }}</span> {{ $value }}</div>
                @endforeach
                @if ($stamp)
                    <div class="pad-stamp-wrap"><span class="pad-stamp">{{ $stamp }}</span></div>
                @endif
            </td>
        @endif
    </tr>
</table>
<div class="pad-rule"></div>
