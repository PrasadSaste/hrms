{{-- Renders whatever the administrator has written for this event. The body
     arrives as HTML that the renderer produced with raw tags escaped, so
     nothing typed into the console can execute here. --}}
<x-mail::message>
{{-- The wrapper is what the mail theme styles tables through, so a table an
     administrator writes into a template arrives looking like the rest. --}}
<div class="table">
{!! $bodyHtml !!}
</div>

@if ($actionLabel && $actionUrl)
<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>
@endif

Thanks,<br>
{{ $companyName }}
</x-mail::message>
