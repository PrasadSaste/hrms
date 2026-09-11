<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps attendance, leave and salary closed until a new joiner's background
 * verification has cleared.
 *
 * Someone who was never asked for verification is not affected: the gate only
 * applies while a case exists and has not been verified. On the web the person
 * is taken to their verification screen, which is the one thing they can do
 * about it; the API answers with a 403 that says the same.
 */
class EnsureBackgroundCheckCleared
{
    public const MESSAGE = 'My Attendance, Leave and Salary Slips open once your background verification is complete.';

    public function handle(Request $request, Closure $next): Response
    {
        $employee = $request->user()?->employee;

        if ($employee && $employee->awaitingBackgroundCheck()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => self::MESSAGE,
                    'background_check_status' => $employee->backgroundCheck->status,
                ], 403);
            }

            return redirect()->route('my-verification.edit')->with('warning', self::MESSAGE);
        }

        return $next($request);
    }
}
