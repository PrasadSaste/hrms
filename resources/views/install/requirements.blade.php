<x-install.layout :step="$step" title="Server check">
    <h2>Can this server run it?</h2>
    <p class="sub">Everything below has to be in place before the HRMS will start. Nothing has been changed yet.</p>

    @if ($prepared)
        <div class="notice ok">
            Prepared this deployment for setup:
            <ul>@foreach ($prepared as $done)<li>{{ $done }}</li>@endforeach</ul>
        </div>
    @endif

    @foreach ($groups as $heading => $checks)
        <div class="card">
            <h3>{{ $heading }}</h3>
            <table class="checks">
                @foreach ($checks as $check)
                    <tr>
                        <td>
                            <span class="what">{{ $check['name'] }}</span>
                            <span class="why">{{ $check['detail'] }}</span>
                        </td>
                        <td class="verdict {{ $check['met'] ? 'yes' : ($check['required'] ? 'no' : 'soft') }}">
                            {{ $check['met'] ? '✓' : ($check['required'] ? '✕' : '—') }} {{ $check['found'] }}
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach

    @unless ($satisfied)
        <div class="notice bad">
            <strong>Not ready yet.</strong>
            Install the missing extensions with your package manager, and give the web server's user write
            access to the folders listed above. Reload this page when you have.
        </div>
    @endunless

    <div class="actions">
        @if ($satisfied)
            <a class="btn primary" href="{{ route('install.database') }}">Continue to the database</a>
        @else
            <a class="btn primary" href="{{ route('install.index') }}">Check again</a>
            <a class="btn ghost" href="{{ route('install.database') }}">Continue anyway</a>
        @endif
    </div>
</x-install.layout>
