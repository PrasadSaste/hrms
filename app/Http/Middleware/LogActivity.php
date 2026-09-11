<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records write requests so administrators can audit who changed what.
 */
class LogActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->user() || $request->isMethod('GET')) {
            return $response;
        }

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => strtolower($request->method()),
            'description' => trim($request->method().' '.$request->path()),
            'properties' => ['route' => $request->route()?->getName()],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return $response;
    }
}
