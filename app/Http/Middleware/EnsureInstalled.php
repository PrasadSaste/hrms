<?php

namespace App\Http\Middleware;

use App\Services\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a system that has not been set up to the setup wizard.
 *
 * Every page, not only the sign-in one: a deep link into a system with no
 * database behind it should land on the thing that fixes that, not on a stack
 * trace about a missing table. An API caller gets a 503 saying the same, since
 * redirecting a machine to a form helps nobody.
 */
class EnsureInstalled
{
    public function __construct(protected Installer $installer) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->installer->installed() || $request->routeIs('install.*')) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'This system has not been set up yet. Open it in a browser to finish installing it.',
            ], 503);
        }

        return redirect()->route('install.index');
    }
}
