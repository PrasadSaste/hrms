{{-- The pad, the watermark and the foot, shared by every generated document. --}}
.pad { width: 100%; border-collapse: collapse; }
.pad td { vertical-align: top; }
.pad-identity { width: 62%; }
.pad-logo { max-height: 48px; max-width: 190px; margin-bottom: 5px; }
.pad-name { font-size: 14px; font-weight: bold; color: #0f172a; }
.pad-meta { font-size: 8px; color: #64748b; line-height: 1.55; margin-top: 2px; }

.pad-document { text-align: right; }
.pad-title {
    font-size: 12px; font-weight: bold; text-transform: uppercase;
    letter-spacing: 1px; color: {{ $company['brand_color'] ?? '#1e3a8a' }};
}
.pad-line { font-size: 8.5px; color: #475569; margin-top: 2px; }
.pad-line-label { color: #94a3b8; }
.pad-stamp-wrap { padding-top: 6px; }
.pad-stamp {
    display: inline-block; border: 1.5px solid #059669; color: #059669;
    padding: 2px 8px; font-size: 9px; font-weight: bold;
    text-transform: uppercase; letter-spacing: 1px;
}

.pad-rule {
    height: 2px; margin-top: 8px;
    background: {{ $company['brand_color'] ?? '#1e293b' }};
}

/*
    Behind the content, at an angle, and pale. Fixed position so it lands on
    every page rather than only the first.
*/
.watermark {
    position: fixed; top: 38%; left: 0; right: 0;
    text-align: center;
    font-size: 58px; font-weight: bold; letter-spacing: 3px;
    color: {{ $company['watermark_color'] ?? '#eef1f5' }};
    transform: rotate(-30deg);
    z-index: -1;
}

.doc-foot {
    position: fixed; bottom: -30px; left: 0; right: 0;
    font-size: 7.5px; color: #94a3b8; text-align: center; line-height: 1.5;
}
