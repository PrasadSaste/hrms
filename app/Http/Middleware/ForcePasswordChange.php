<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users created by HR receive a temporary password and must replace it before
 * they can use the rest of the application.
 */
class ForcePasswordChange
{
    /** @var array<int, string> */
    protected array $except = [
        'password.change',
        'password.change.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && $user->must_change_password
            && ! $request->expectsJson()
            && ! in_array($request->route()?->getName(), $this->except, true)) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
