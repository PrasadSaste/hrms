<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $letter->subject }} — {{ $letter->reference }}</title>
    <style>
        @page { margin: 34px 44px 56px; }
        .doc-foot { bottom: -40px; }

        * { box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.65;
            color: #1e293b;
            margin: 0;
        }

        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        @include('pdf.partials.pad-styles')

        .meta { margin-top: 22px; font-size: 10px; color: #475569; }
        .meta .reference { font-weight: bold; color: #0f172a; }
        .meta td { padding-bottom: 2px; }

        .subject {
            margin-top: 22px; font-size: 13px; font-weight: bold; color: #0f172a;
            text-align: center; text-transform: uppercase; letter-spacing: 0.6px;
        }
        .subject-rule {
            width: 90px; height: 2px; margin: 6px auto 0;
            background: {{ $company['brand_color'] ?? '#1e293b' }};
        }

        /* The body is the administrator's markdown, rendered. */
        .body { margin-top: 22px; }
        .body p { margin: 0 0 11px; text-align: justify; }
        .body strong { color: #0f172a; }
        .body ul, .body ol { margin: 0 0 11px 16px; padding: 0; }
        .body li { margin-bottom: 3px; }
        .body h1, .body h2, .body h3 {
            font-size: 12px; margin: 16px 0 8px; color: #0f172a;
        }
        .body table { margin: 0 0 11px; }
        .body td, .body th { padding: 3px 6px; border-bottom: 1px solid #e2e8f0; font-size: 10.5px; }

        .signature { margin-top: 34px; }
        .signature .for { font-size: 10.5px; color: #0f172a; }
        .signature .space { height: 46px; }
        .signature .signature-image { max-height: 44px; max-width: 200px; }
        .signature .name { font-size: 11px; font-weight: bold; color: #0f172a; }
        .signature .designation { font-size: 9.5px; color: #64748b; }

        .acknowledgement {
            margin-top: 30px; padding-top: 10px; border-top: 1px dashed #cbd5e1;
            font-size: 9.5px; color: #64748b;
        }

    </style>
</head>
<body>
    @include('pdf.partials.watermark')

    @include('pdf.partials.pad', [
        'title' => $letter->typeLabel(),
        'meta' => ['Ref.' => $letter->reference],
    ])

    <table class="meta">
        <tr>
            <td>
                <div>{{ $employee->full_name }}</div>
                <div style="color: #64748b;">
                    {{ $employee->employee_code }}@if ($employee->designation) &middot; {{ $employee->designation->name }}@endif
                </div>
            </td>
            <td style="text-align: right;">
                {{ $letter->issued_on->format('d F Y') }}
            </td>
        </tr>
    </table>

    <div class="subject">{{ $letter->subject }}</div>
    <div class="subject-rule"></div>

    {{-- Rendered from markdown with raw HTML escaped, so nothing typed into a
         template can reach the document as markup. --}}
    <div class="body">{!! $body !!}</div>

    <table class="signature">
        <tr>
            <td style="width: 55%;">
                <div class="for">For {{ $company['name'] }}</div>
                @if ($signature)
                    {{-- The signature this letter went out over, frozen at issue.
                         It replaces the blank space rather than adding to it. --}}
                    <div class="space"><img src="{{ $signature }}" alt="" class="signature-image"></div>
                @else
                    <div class="space"></div>
                @endif
                <div class="name">{{ $letter->signatory_name ?: $company['signatory_name'] ?: '' }}</div>
                <div class="designation">{{ $letter->signatory_designation ?: $company['signatory_designation'] ?: 'Authorised Signatory' }}</div>
            </td>
            @if ($acknowledgement)
                <td style="text-align: right;">
                    <div class="for">Accepted and agreed</div>
                    <div class="space"></div>
                    <div class="name">{{ $employee->full_name }}</div>
                    <div class="designation">Date: ______________</div>
                </td>
            @endif
        </tr>
    </table>

    @include('pdf.partials.foot', ['lines' => [
        e($company['name']).' &middot; '.e($letter->reference).' &middot; issued on '
            .$letter->issued_on->format('d M Y').'.',
    ]])
</body>
</html>
