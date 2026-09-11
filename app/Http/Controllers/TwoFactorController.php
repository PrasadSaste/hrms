<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Services\TwoFactorService;
use App\Support\NotificationEvents;
use App\Support\TwoFactorPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Setting up a second factor, and answering it.
 *
 * The secret lives in the session between the QR being shown and the first
 * code being typed, and only reaches the user's row once a code proves the
 * phone actually has it. Enrolling somebody against a secret they never
 * successfully scanned is how people get locked out.
 */
class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorService $totp,
        protected NotificationDispatcher $dispatcher,
    ) {}

    /** Where somebody manages their own second factor. */
    public function setup(Request $request): View
    {
        $user = $request->user();

        // A secret per visit to this screen, held in the session until a code
        // confirms it. Regenerating on each visit means a half-finished
        // attempt on another device cannot be completed later.
        if (! $user->hasTwoFactor()) {
            $secret = $request->session()->get('two-factor.pending')
                ?: $this->totp->generateSecret();
            $request->session()->put('two-factor.pending', $secret);
        }

        return view('auth.two-factor.setup', [
            'user' => $user,
            'enabled' => $user->hasTwoFactor(),
            'required' => TwoFactorPolicy::requiredFor($user),
            'secret' => $secret ?? null,
            'qr' => isset($secret)
                ? $this->totp->qrCode($this->totp->provisioningUri($user, $secret))
                : null,
            'remaining' => count($user->recoveryCodes()),
            'codes' => $request->session()->pull('two-factor.codes'),
        ]);
    }

    /** Prove the phone has the secret, then store it. */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();
        $secret = $request->session()->get('two-factor.pending');

        if (! $secret) {
            return redirect()->route('two-factor.setup')
                ->with('error', 'That setup expired. Scan the new code and try again.');
        }

        if ($this->totp->verifyToStep($secret, $request->string('code')->toString()) === null) {
            throw ValidationException::withMessages([
                'code' => 'That code is not right. Check your phone’s clock is set automatically, then try the current code.',
            ]);
        }

        $codes = $this->totp->enable($user, $secret);

        $request->session()->forget('two-factor.pending');
        $request->session()->put('two-factor.codes', $codes);
        $request->session()->put('two-factor.passed', $user->id);

        $this->tell($user, NotificationEvents::TWO_FACTOR_ENABLED);

        return redirect()->route('two-factor.setup')
            ->with('success', 'Two-factor authentication is on. Save your recovery codes.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (TwoFactorPolicy::requiredFor($user)) {
            return back()->with('error', 'Your role requires a second factor, so it cannot be turned off.');
        }

        // Their password, because somebody who walked up to an unlocked screen
        // should not be able to take the second factor off it.
        $request->validate(['password' => ['required', 'current_password']]);

        $this->totp->disable($user);
        $request->session()->forget('two-factor.passed');

        $this->tell($user, NotificationEvents::TWO_FACTOR_DISABLED);

        return back()->with('success', 'Two-factor authentication is off.');
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $codes = $this->totp->replaceRecoveryCodes($request->user());
        $request->session()->put('two-factor.codes', $codes);

        return redirect()->route('two-factor.setup')
            ->with('success', 'New recovery codes. The old ones no longer work.');
    }

    // ------------------------------------------------------------- signing in

    public function challenge(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactor()) {
            return redirect()->route('dashboard');
        }

        if ($request->session()->get('two-factor.passed') === $user->id) {
            return redirect()->intended(route('dashboard'));
        }

        return view('auth.two-factor.challenge', [
            'recoveryCodesLeft' => count($user->recoveryCodes()),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required_without:recovery_code', 'nullable', 'string'],
            'recovery_code' => ['required_without:code', 'nullable', 'string'],
        ]);

        $user = $request->user();
        $recovery = $request->string('recovery_code')->toString();

        $passed = $recovery !== ''
            ? $this->totp->consumeRecoveryCode($user, $recovery)
            : $this->totp->verifyFor($user, $request->string('code')->toString());

        if (! $passed) {
            throw ValidationException::withMessages([
                'code' => $recovery !== ''
                    ? 'That recovery code is not one of yours, or it has been used already.'
                    : 'That code is not right, or it has been used already. Wait for the next one.',
            ]);
        }

        // Fixation: the session that answered the challenge is not the session
        // that was handed the login page.
        $request->session()->regenerate();
        $request->session()->put('two-factor.passed', $user->id);

        if ($recovery !== '') {
            $left = count($user->fresh()->recoveryCodes());

            return redirect()->intended(route('dashboard'))->with(
                'warning',
                'You used a recovery code. '.$left.' '.str('code')->plural($left).' left — '
                    .'make new ones if you are running low.',
            );
        }

        return redirect()->intended(route('dashboard'));
    }

    /** Turning it on or off is a security event, so the person is told. */
    protected function tell(User $user, string $event): void
    {
        $this->dispatcher->toUser($event, $user, [
            'name' => $user->name,
            'when' => now()->format('d M Y, h:i A'),
            'ip' => request()->ip() ?? '—',
            'url' => route('two-factor.setup'),
        ]);
    }
}
