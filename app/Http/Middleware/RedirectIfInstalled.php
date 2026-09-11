<?php

namespace App\Http\Middleware;

use App\Services\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shuts the setup wizard once the system is set up.
 *
 * This is the security of the whole feature. The wizard writes database
 * credentials and creates an account that can see every salary in the company,
 * and it does so without asking anybody to sign in — so from the moment the
 * lock file is written it has to be unreachable, whatever URL is typed.
 */
class RedirectIfInstalled
{
    public function __construct(protected Installer $installer) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->installer->installed()) {
            return $next($request);
        }

        return redirect()->route('login')
            ->with('status', 'This system is already set up. Sign in to use it.');
    }
}
