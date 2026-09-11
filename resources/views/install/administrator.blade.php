<x-install.layout :step="$step" title="Administrator">
    <h2>Who runs {{ $company ?: 'this system' }}?</h2>
    <p class="sub">
        One account, with every permission there is. Use it to add the rest of the people and to
        hand out narrower roles — HR manager, accountant, branch manager.
    </p>

    <form method="POST" action="{{ route('install.administrator.store') }}">
        @csrf

        <div class="card">
            <h3>The first account</h3>

            <div class="row two">
                <div class="field">
                    <label for="name">Full name</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus autocomplete="name">
                </div>
                <div class="field">
                    <label for="email">Email address</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required autocomplete="username">
                    <p class="hint">This is the sign-in name, and where a password reset would go.</p>
                </div>
            </div>

            <div class="row two">
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" required autocomplete="new-password">
                    <p class="hint">At least eight characters. Choose something long rather than something clever.</p>
                </div>
                <div class="field">
                    <label for="password_confirmation">Password again</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password">
                </div>
            </div>
        </div>

        <div class="notice warn">
            There is no way to recover this password from the server without a working mail
            server, so keep it somewhere safe until you have set one up under Administration → Settings.
        </div>

        <div class="actions">
            <button type="submit" class="primary">Finish setting up</button>
        </div>
    </form>
</x-install.layout>
