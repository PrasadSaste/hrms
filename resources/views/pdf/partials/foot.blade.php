{{--
    The foot of every generated document: whatever the company put there, then
    the line identifying the document itself.

    Expects $company, and $lines — the document's own closing lines.
--}}
@php $lines ??= []; @endphp

<div class="doc-foot">
    @if (! empty($company['footer']))
        {{ $company['footer'] }}<br>
    @endif
    @foreach (array_filter($lines) as $line)
        {!! $line !!}@if (! $loop->last)<br>@endif
    @endforeach
</div>
