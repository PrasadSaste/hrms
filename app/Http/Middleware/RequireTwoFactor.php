<?php

namespace App\Http\Middleware;

use App\Support\TwoFactorPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stand between a signed-in session and the rest of the system.
 *
 * Two states, and **each lets a different set of routes through** — which is
 * the whole of the security here.
 *
 * Somebody who *has* a second factor and has not answered it this session may
 * reach the challenge and nothing else. Not the setup screen: that screen can
 * turn the second factor off, and although doing so asks for their password,
 * the attacker in this scenario is precisely somebody who has the password and
 * not the phone. Letting them near it would make the second factor optional
 * for exactly the person it exists to stop.
 *
 * Somebody whose *role requires* one and has not enrolled may reach the setup
 * screen and nothing else, so that the requirement is a door rather than a
 * wall.
 *
 * Signing out is allowed from both. Being unable to leave a screen you cannot
 * pass is the worst version of this, and a person locked in that way will ask
 * for the whole feature to be switched off.
 */
class RequireTwoFactor
{
    /** Answering the challenge, and leaving. */
    protected const WHILE_UNCHALLENGED = [
        'two-factor.challenge',
        'two-factor.verify',
        'logout',
        'password.change',
        'password.change.update',
    ];

    /** Enrolling, and leaving. */
    protected const WHILE_UNENROLLED = [
        'two-factor.setup',
        'two-factor.confirm',
        'logout',
        'password.change',
        'password.change.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $route = $request->route()?->getName();

        if ($user->hasTwoFactor()) {
            if ($request->session()->get('two-factor.passed') === $user->id) {
                return $next($request);
            }

            return in_array($route, self::WHILE_UNCHALLENGED, true)
                ? $next($request)
                : redirect()->route('two-factor.challenge');
        }

        if (TwoFactorPolicy::requiredFor($user)) {
            return in_array($route, self::WHILE_UNENROLLED, true)
                ? $next($request)
                : redirect()->route('two-factor.setup')
                    ->with('warning', 'Your role requires a second factor. Set one up to carry on.');
        }

        return $next($request);
    }
}
