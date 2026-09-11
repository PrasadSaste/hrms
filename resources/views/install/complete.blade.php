<x-install.layout :step="$step" title="Done">
    <h2>{{ $company ?: 'Your HRMS' }} is ready.</h2>
    <p class="sub">Setup is closed. This page will not answer again, and neither will the rest of the wizard.</p>

    <div class="card">
        <h3>Sign in as</h3>
        <p style="margin: 0; font-size: 17px; font-weight: 600;">{{ $email }}</p>
        <p class="hint">With the password you just chose.</p>

        <div class="actions" style="margin-top: 18px;">
            <a class="btn primary" href="{{ route('login') }}">Sign in</a>
        </div>
    </div>

    <div class="card">
        <h3>Worth doing first</h3>
        <table class="checks">
            <tr>
                <td>
                    <span class="what">Point mail at your own server</span>
                    <span class="why">Administration → Settings. Until then nothing is sent — no offer letters, no salary slips, no password resets.</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="what">Check the professional tax slabs</span>
                    <span class="why">Payroll → Salary components. Karnataka's bands are loaded as an example; every state sets its own.</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="what">Add your branches and their map positions</span>
                    <span class="why">Organisation → Branches. A branch with coordinates can check where a punch came from.</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="what">Set the scheduler running</span>
                    <span class="why">Leave accrual, probation confirmations, reminders and the nightly reports all come from one cron entry. It is in DEPLOYMENT.md.</span>
                </td>
            </tr>
            @unless ($logo)
                <tr>
                    <td>
                        <span class="what">Upload a logo</span>
                        <span class="why">Administration → Settings. It goes on the sign-in page, the salary slips and every letter.</span>
                    </td>
                </tr>
            @endunless
        </table>
    </div>
</x-install.layout>
