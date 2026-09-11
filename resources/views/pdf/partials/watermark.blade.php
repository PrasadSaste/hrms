{{--
    The diagonal mark across every page.

    Fixed rather than in the flow, so DomPDF repeats it on each page of a
    multi-page letter, and pale enough that the text over it stays the thing
    you read. Nothing is drawn at all when the company has switched it off.
--}}
@if (! empty($company['watermark']))
    <div class="watermark">{{ Str::upper($company['watermark']) }}</div>
@endif
